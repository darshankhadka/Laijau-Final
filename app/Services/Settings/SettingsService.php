<?php

namespace App\Services\Settings;

use App\Models\Settings\ModuleSetting;
use App\Models\Settings\ModuleSettingAuditLog;
use App\Models\Settings\ModuleStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettingsService
{
    const CACHE_PREFIX = 'module_settings_';
    const STATUS_CACHE_PREFIX = 'module_status_';

    /**
     * Cache key for all settings of a module.
     */
    protected static function getCacheKey(string $module): string
    {
        return self::CACHE_PREFIX . strtolower($module);
    }

    /**
     * Cache key for module active status.
     */
    protected static function getStatusCacheKey(string $module): string
    {
        return self::STATUS_CACHE_PREFIX . strtolower($module);
    }

    /**
     * Retrieve all settings for a module as a cached key => typed value array.
     */
    public function getModuleSettingsMap(string $module): array
    {
        return Cache::rememberForever(self::getCacheKey($module), function () use ($module) {
            $records = ModuleSetting::where('module', $module)->get();
            $map = [];

            foreach ($records as $setting) {
                $map[$setting->key] = [
                    'typed_value' => $setting->typed_value,
                    'is_enabled' => $setting->is_enabled,
                    'value_type' => $setting->value_type,
                    'default_value' => $setting->default_value,
                ];
            }

            return $map;
        });
    }

    /**
     * Determine if a setting key exists for the module.
     */
    public function has(string $module, string $key): bool
    {
        $map = $this->getModuleSettingsMap($module);
        return array_key_exists($key, $map);
    }

    /**
     * Get a setting value (strongly-typed).
     */
    public function get(string $module, string $key, $default = null)
    {
        $map = $this->getModuleSettingsMap($module);

        if (array_key_exists($key, $map)) {
            $val = $map[$key]['typed_value'];
            return $val !== null ? $val : $default;
        }

        return $default;
    }

    /**
     * Get setting cast as boolean.
     */
    public function getBoolean(string $module, string $key, ?bool $default = null): bool
    {
        $val = $this->get($module, $key, $default);
        if ($val === null && $default !== null) {
            return (bool)$default;
        }
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get setting cast as integer.
     */
    public function getInteger(string $module, string $key, ?int $default = null): int
    {
        $val = $this->get($module, $key, $default);
        return (int)$val;
    }

    /**
     * Get setting cast as decimal / float.
     */
    public function getDecimal(string $module, string $key, ?float $default = null): float
    {
        $val = $this->get($module, $key, $default);
        return (float)$val;
    }

    /**
     * Get setting cast as string.
     */
    public function getString(string $module, string $key, ?string $default = null): string
    {
        $val = $this->get($module, $key, $default);
        return (string)($val ?? '');
    }

    /**
     * Get setting cast as JSON array or decoded structure.
     */
    public function getJson(string $module, string $key, $default = [])
    {
        $val = $this->get($module, $key, $default);
        if (is_array($val)) {
            return $val;
        }
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
        }
        return $default;
    }

    /**
     * Check if a module, or a specific setting within a module, is enabled.
     */
    public function isEnabled(string $module, ?string $key = null): bool
    {
        if (!$this->isModuleEnabled($module)) {
            return false;
        }

        if ($key === null) {
            return true;
        }

        $map = $this->getModuleSettingsMap($module);
        if (!array_key_exists($key, $map)) {
            return false;
        }

        return (bool)$map[$key]['is_enabled'] && filter_var($map[$key]['typed_value'], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Check whether a module is enabled system-wide.
     */
    public function isModuleEnabled(string $module): bool
    {
        return Cache::rememberForever(self::getStatusCacheKey($module), function () use ($module) {
            $record = ModuleStatus::where('module', $module)->first();
            if ($record) {
                return (bool)$record->is_enabled;
            }

            // By default all registered modules are active
            return true;
        });
    }

    /**
     * Enable or disable a module with dependency protection.
     */
    public function setModuleStatus(string $module, bool $enabled, ?int $userId = null, ?string $reason = null): bool
    {
        if (!$enabled) {
            $dependents = [];
            if (!ModuleRegistry::canDisable($module, $dependents)) {
                $depList = implode(', ', $dependents);
                throw new \InvalidArgumentException("Cannot disable module '{$module}'. Active dependent modules: {$depList}.");
            }
        }

        ModuleStatus::updateOrCreate(
            ['module' => $module],
            [
                'is_enabled' => $enabled,
                'disabled_reason' => $enabled ? null : $reason,
                'disabled_at' => $enabled ? null : now(),
                'disabled_by' => $enabled ? null : $userId,
            ]
        );

        Cache::forget(self::getStatusCacheKey($module));
        return true;
    }

    /**
     * Set a single setting value atomically.
     */
    public function set(string $module, string $key, $value, ?int $userId = null): ModuleSetting
    {
        $setting = ModuleSetting::where('module', $module)->where('key', $key)->firstOrFail();

        // Validate value if validation rules exist
        $setting->validateValue($value);

        $oldRaw = $setting->value;
        $prepared = ModuleSetting::prepareValueForStorage($value, $setting->value_type, $setting->is_sensitive);

        $setting->update([
            'value' => $prepared,
            'updated_by' => $userId ?? Auth::id(),
        ]);

        // Audit Log
        ModuleSettingAuditLog::record(
            $setting,
            $oldRaw,
            $prepared,
            'updated',
            $userId ? User::find($userId) : Auth::user()
        );

        $this->clearCache($module);

        return $setting->fresh();
    }

    /**
     * Atomically update multiple settings within a module transactionally.
     * Validates all inputs first, rolls back on any error, and invalidates cache on commit.
     */
    public function setMultiple(string $module, array $settings, ?int $userId = null): bool
    {
        $actor = $userId ? User::find($userId) : Auth::user();

        return DB::transaction(function () use ($module, $settings, $actor) {
            $existing = ModuleSetting::where('module', $module)
                ->whereIn('key', array_keys($settings))
                ->get()
                ->keyBy('key');

            // 1. Validation pass
            foreach ($settings as $key => $candidateValue) {
                if (isset($existing[$key])) {
                    $setting = $existing[$key];
                    $setting->validateValue($candidateValue);
                }
            }

            // 2. Mutation and Audit pass
            foreach ($settings as $key => $val) {
                if (isset($existing[$key])) {
                    $setting = $existing[$key];
                    $oldRaw = $setting->value;
                    $prepared = ModuleSetting::prepareValueForStorage($val, $setting->value_type, $setting->is_sensitive);

                    $setting->update([
                        'value' => $prepared,
                        'updated_by' => $actor?->id,
                    ]);

                    ModuleSettingAuditLog::record(
                        $setting,
                        $oldRaw,
                        $prepared,
                        'updated',
                        $actor
                    );
                }
            }

            // 3. Clear cache only on successful commit
            $this->clearCache($module);

            return true;
        });
    }

    /**
     * Reset a setting to its factory default value.
     */
    public function resetToDefault(string $module, string $key, ?int $userId = null): ModuleSetting
    {
        $setting = ModuleSetting::where('module', $module)->where('key', $key)->firstOrFail();
        $oldRaw = $setting->value;

        $setting->update([
            'value' => $setting->default_value,
            'updated_by' => $userId ?? Auth::id(),
        ]);

        ModuleSettingAuditLog::record(
            $setting,
            $oldRaw,
            $setting->default_value,
            'reset_to_default',
            $userId ? User::find($userId) : Auth::user()
        );

        $this->clearCache($module);

        return $setting->fresh();
    }

    /**
     * Reset all settings for a module to their defaults.
     */
    public function resetModuleToDefaults(string $module, ?int $userId = null): bool
    {
        $actor = $userId ? User::find($userId) : Auth::user();

        return DB::transaction(function () use ($module, $actor) {
            $settings = ModuleSetting::where('module', $module)->get();

            foreach ($settings as $setting) {
                $oldRaw = $setting->value;
                $setting->update([
                    'value' => $setting->default_value,
                    'updated_by' => $actor?->id,
                ]);

                ModuleSettingAuditLog::record(
                    $setting,
                    $oldRaw,
                    $setting->default_value,
                    'reset_to_default',
                    $actor
                );
            }

            $this->clearCache($module);
            return true;
        });
    }

    /**
     * Get all Eloquent setting objects for a module, sorted by group and sort order.
     */
    public function getAllForModule(string $module): Collection
    {
        return ModuleSetting::where('module', $module)
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Export module settings as a sanitized, portable JSON bundle.
     */
    public function exportConfiguration(?string $module = null, bool $sanitizeSecrets = true): array
    {
        $query = ModuleSetting::query()->orderBy('module')->orderBy('group')->orderBy('sort_order');
        if ($module !== null) {
            $query->where('module', $module);
        }

        $records = $query->get();
        $exportedSettings = [];

        foreach ($records as $s) {
            $val = $s->typed_value;
            if ($s->is_sensitive && $sanitizeSecrets) {
                $val = '[REDACTED_SECRET]';
            }

            $exportedSettings["{$s->module}.{$s->key}"] = [
                'module' => $s->module,
                'group' => $s->group,
                'key' => $s->key,
                'value' => $val,
                'value_type' => $s->value_type,
                'is_sensitive' => (bool)$s->is_sensitive,
                'is_enabled' => (bool)$s->is_enabled,
            ];
        }

        return [
            'app' => 'Laijau ERP',
            'schema_version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'scope' => $module ?? 'all_modules',
            'setting_count' => count($exportedSettings),
            'settings' => $exportedSettings,
        ];
    }

    /**
     * Pre-flight dry run validation and impact analysis for importing a configuration bundle.
     */
    public function previewImport(array $payload): array
    {
        $settingsPayload = $payload['settings'] ?? $payload;
        if (!is_array($settingsPayload)) {
            throw new \InvalidArgumentException("Invalid configuration payload: 'settings' dictionary expected.");
        }

        $changes = [];
        $errors = [];

        foreach ($settingsPayload as $item) {
            if (!isset($item['module'], $item['key'], $item['value'])) {
                continue;
            }

            $module = strtolower($item['module']);
            $key = $item['key'];
            $proposedValue = $item['value'];

            // Skip redacted secrets
            if ($proposedValue === '[REDACTED_SECRET]') {
                continue;
            }

            $existing = ModuleSetting::where('module', $module)->where('key', $key)->first();
            if (!$existing) {
                continue; // Unknown setting in payload
            }

            // Validate against model rules
            try {
                $existing->validateValue($proposedValue);
            } catch (ValidationException $ve) {
                $errors[] = "Setting '{$module}.{$key}': " . implode(', ', $ve->validator->errors()->all());
                continue;
            } catch (\Throwable $e) {
                $errors[] = "Setting '{$module}.{$key}': " . $e->getMessage();
                continue;
            }

            $currentVal = $existing->typed_value;
            $typedProposed = ModuleSetting::castValueToType($proposedValue, $existing->value_type);

            if ($currentVal !== $typedProposed) {
                $impact = ConfigurationImpactService::evaluateImpact($module, $key, $currentVal, $typedProposed);
                $changes[] = [
                    'module' => $module,
                    'key' => $key,
                    'group' => $existing->group,
                    'current_value' => $currentVal,
                    'new_value' => $typedProposed,
                    'affects' => $impact['affects'],
                    'severity' => $impact['severity'],
                    'impact_text' => $impact['impact_text'],
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'change_count' => count($changes),
            'changes' => $changes,
            'errors' => $errors,
        ];
    }

    /**
     * Import a configuration payload transactionally.
     */
    public function importConfiguration(array $payload, ?int $userId = null): array
    {
        $preview = $this->previewImport($payload);
        if (!$preview['valid']) {
            throw new \InvalidArgumentException("Import rejected due to validation errors: " . implode('; ', $preview['errors']));
        }

        $actor = $userId ? User::find($userId) : Auth::user();
        $affectedModules = [];

        DB::transaction(function () use ($preview, $actor, &$affectedModules) {
            foreach ($preview['changes'] as $change) {
                $module = $change['module'];
                $key = $change['key'];
                $newVal = $change['new_value'];

                $setting = ModuleSetting::where('module', $module)->where('key', $key)->first();
                if ($setting) {
                    $oldRaw = $setting->value;
                    $prepared = ModuleSetting::prepareValueForStorage($newVal, $setting->value_type, $setting->is_sensitive);

                    $setting->update([
                        'value' => $prepared,
                        'updated_by' => $actor?->id,
                    ]);

                    ModuleSettingAuditLog::record(
                        $setting,
                        $oldRaw,
                        $prepared,
                        'imported',
                        $actor
                    );

                    $affectedModules[$module] = true;
                }
            }
        });

        foreach (array_keys($affectedModules) as $mod) {
            $this->clearCache($mod);
        }

        return [
            'imported_count' => count($preview['changes']),
            'affected_modules' => array_keys($affectedModules),
        ];
    }

    /**
     * Invalidate caches.
     */
    public function clearCache(?string $module = null): void
    {
        if ($module !== null) {
            Cache::forget(self::getCacheKey($module));
            Cache::forget(self::getStatusCacheKey($module));
        } else {
            foreach (array_keys(ModuleRegistry::all()) as $mod) {
                Cache::forget(self::getCacheKey($mod));
                Cache::forget(self::getStatusCacheKey($mod));
            }
        }
    }
}

