<?php

namespace App\Filament\Pages;

use App\Models\Settings\ModuleSetting;
use App\Models\Settings\ModuleSettingAuditLog;
use App\Models\Settings\ModuleStatus;
use App\Services\Settings\ConfigurationImpactService;
use App\Services\Settings\ModuleRegistry;
use App\Services\Settings\SettingsService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ModuleSettings extends Page
{
    protected string $view = 'filament.pages.module-settings';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Module Settings';
    protected static ?int $navigationSort = 20;
    protected static ?string $slug = 'module-settings';
    protected static ?string $title = 'Module Control Plane';

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public static function canAccess(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user()
            ?? \Illuminate\Support\Facades\Auth::guard('admin')->user()
            ?? \Illuminate\Support\Facades\Auth::guard('web')->user()
            ?? \Illuminate\Support\Facades\Auth::user();

        if (!$user) {
            return false;
        }

        return method_exists($user, 'canViewModuleSettings') ? $user->canViewModuleSettings() : false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    // 1. Navigation & Filters
    public string $activeTab = 'modules'; // 'modules' | 'audit_logs'
    public string $searchQuery = '';
    public string $selectedCategory = 'all'; // 'all', 'commercial', 'operational', 'financial', 'system'

    // 2. Module Modal / Drawer State
    public ?string $activeModuleId = null;
    public ?string $activeGroup = null;
    public array $formData = [];
    public array $originalData = [];
    public ?string $highlightSettingKey = null;

    // 3. Configuration Impact Review Modal State
    public bool $showImpactConfirmModal = false;
    public array $pendingImpactChanges = [];

    // 4. Export & Import Modals
    public bool $showExportModal = false;
    public bool $showImportModal = false;
    public string $exportJson = '';
    public string $importJson = '';
    public array $importPreview = [];

    // 5. Dependency Conflict Modal State
    public bool $showDependencyModal = false;
    public string $dependencyModalMessage = '';

    public function mount(): void
    {
        $requestedModule = request()->query('module');
        if ($requestedModule && ModuleRegistry::exists($requestedModule)) {
            $this->openModule($requestedModule);
        }
    }

    /**
     * Open configuration panel for a specific module.
     */
    public function openModule(string $moduleId): void
    {
        if (!ModuleRegistry::exists($moduleId)) {
            return;
        }

        $this->activeModuleId = $moduleId;
        $settingsService = app(SettingsService::class);
        $settings = $settingsService->getAllForModule($moduleId);

        $module = ModuleRegistry::get($moduleId);
        $groups = array_keys($module['groups'] ?? []);
        $this->activeGroup = $groups[0] ?? null;

        $this->formData = [];
        foreach ($settings as $s) {
            if ($s->is_sensitive && !empty($s->value)) {
                // Keep sensitive masked representation in form state unless explicitly cleared
                $this->formData[$s->key] = ModuleSetting::maskSecret($s->value);
            } else {
                $this->formData[$s->key] = $s->typed_value;
            }
        }

        $this->originalData = $this->formData;
    }

    /**
     * Close the module configuration panel.
     */
    public function closeModule(): void
    {
        $this->activeModuleId = null;
        $this->activeGroup = null;
        $this->formData = [];
        $this->originalData = [];
    }

    /**
     * Open a specific module, tab group, and optionally highlight a target setting.
     */
    public function openModuleSetting(string $moduleId, ?string $group = null, ?string $settingKey = null): void
    {
        $this->openModule($moduleId);
        if ($group) {
            $this->setActiveGroup($group);
        }
        $this->highlightSettingKey = $settingKey;
    }

    /**
     * Select active settings group tab within the opened module.
     */
    public function setActiveGroup(string $group): void
    {
        $this->activeGroup = $group;
        $this->highlightSettingKey = null;
    }

    /**
     * Calculate pending setting mutations with operational impact analysis.
     */
    public function getPendingChangesWithImpact(): array
    {
        if (!$this->activeModuleId) {
            return [];
        }

        $existing = ModuleSetting::where('module', $this->activeModuleId)->get()->keyBy('key');
        $changes = [];

        foreach ($this->formData as $key => $val) {
            $orig = $this->originalData[$key] ?? null;
            if ($val !== $orig) {
                // If unchanged masked secret, skip
                if (isset($existing[$key]) && $existing[$key]->is_sensitive && is_string($val) && str_contains($val, '••••')) {
                    continue;
                }

                $impact = ConfigurationImpactService::evaluateImpact($this->activeModuleId, $key, $orig, $val);
                $changes[] = [
                    'key' => $key,
                    'group' => $existing[$key]->group ?? 'general',
                    'label' => ucwords(str_replace('_', ' ', $key)),
                    'current_value' => $orig,
                    'new_value' => $val,
                    'affects' => $impact['affects'],
                    'severity' => $impact['severity'],
                    'impact_text' => $impact['impact_text'],
                ];
            }
        }

        return $changes;
    }

    /**
     * Intercept save action to present Configuration Impact review if critical settings changed.
     */
    public function requestSaveModuleSettings(): void
    {
        $changes = $this->getPendingChangesWithImpact();
        if (empty($changes)) {
            Notification::make()->title('No Changes Detected')->body('All settings are already up to date.')->info()->send();
            return;
        }

        $hasSignificantImpact = false;
        foreach ($changes as $c) {
            if ($c['severity'] === 'high' || $c['severity'] === 'medium') {
                $hasSignificantImpact = true;
                break;
            }
        }

        $this->pendingImpactChanges = $changes;

        if ($hasSignificantImpact) {
            $this->showImpactConfirmModal = true;
            return;
        }

        $this->saveModuleSettings();
    }

    /**
     * Explicitly confirm and execute save after reviewing impact.
     */
    public function executeCommitModuleSettings(): void
    {
        $this->showImpactConfirmModal = false;
        $this->saveModuleSettings();
        $this->pendingImpactChanges = [];
    }

    /**
     * Cancel the configuration impact review dialog.
     */
    public function cancelImpactReview(): void
    {
        $this->showImpactConfirmModal = false;
    }

    /**
     * Save module settings changes transactionally.
     */
    public function saveModuleSettings(): void
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'canEditModuleSettings') || !$user->canEditModuleSettings($this->activeModuleId)) {
            Notification::make()
                ->title('Unauthorized')
                ->body('You do not have permission to modify configuration for this module.')
                ->danger()
                ->send();
            return;
        }

        try {
            $settingsService = app(SettingsService::class);

            // Filter out masked secrets if they were not changed by the administrator
            $toSave = [];
            $existing = ModuleSetting::where('module', $this->activeModuleId)->get()->keyBy('key');

            foreach ($this->formData as $key => $val) {
                if (isset($existing[$key]) && $existing[$key]->is_sensitive) {
                    // If unchanged masked placeholder, skip overwriting
                    if (is_string($val) && str_contains($val, '••••')) {
                        continue;
                    }
                }
                $toSave[$key] = $val;
            }

            $settingsService->setMultiple($this->activeModuleId, $toSave, $user->id);

            $this->originalData = $this->formData;

            Notification::make()
                ->title('Configuration Saved')
                ->body("Settings for {$this->getActiveModuleProperty('name')} updated successfully.")
                ->success()
                ->send();

            // Refresh form data
            $this->openModule($this->activeModuleId);
        } catch (ValidationException $e) {
            $errors = implode(' ', $e->validator->errors()->all());
            Notification::make()
                ->title('Validation Failed')
                ->body($errors)
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Save Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Reset a single setting to its default factory value.
     */
    public function resetSetting(string $key): void
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'canEditModuleSettings') || !$user->canEditModuleSettings($this->activeModuleId)) {
            return;
        }

        try {
            $setting = app(SettingsService::class)->resetToDefault($this->activeModuleId, $key, $user->id);
            $this->formData[$key] = $setting->typed_value;

            Notification::make()
                ->title('Reset Complete')
                ->body("Setting '{$key}' restored to factory default.")
                ->info()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Reset Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Reset all settings for the active module to defaults.
     */
    public function resetAllDefaults(): void
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'canEditModuleSettings') || !$user->canEditModuleSettings($this->activeModuleId)) {
            return;
        }

        try {
            app(SettingsService::class)->resetModuleToDefaults($this->activeModuleId, $user->id);
            $this->openModule($this->activeModuleId);

            Notification::make()
                ->title('Factory Reset Complete')
                ->body("All settings for {$this->getActiveModuleProperty('name')} restored to defaults.")
                ->info()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Reset Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Toggle a module's global active status with dependency validation.
     */
    public function toggleModuleStatus(string $moduleId): void
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'canManageModuleStatus') || !$user->canManageModuleStatus($moduleId)) {
            Notification::make()
                ->title('Access Denied')
                ->body('Only Super Administrators can enable or disable platform modules.')
                ->danger()
                ->send();
            return;
        }

        $settingsService = app(SettingsService::class);
        $current = $settingsService->isModuleEnabled($moduleId);
        $target = !$current;

        if (!$target) {
            $dependents = [];
            if (!ModuleRegistry::canDisable($moduleId, $dependents)) {
                $this->dependencyModalMessage = "Cannot disable {$moduleId}: The following active systems require it: " . implode(', ', $dependents) . '.';
                $this->showDependencyModal = true;
                return;
            }
        }

        try {
            $settingsService->setModuleStatus($moduleId, $target, $user->id);

            Notification::make()
                ->title($target ? 'Module Activated' : 'Module Suspended')
                ->body("{$moduleId} is now " . ($target ? 'active' : 'disabled') . '.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Action Blocked')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function closeDependencyModal(): void
    {
        $this->showDependencyModal = false;
        $this->dependencyModalMessage = '';
    }

    /**
     * Open Export Configuration Modal.
     */
    public function openExportModal(?string $moduleId = null): void
    {
        $target = $moduleId ?? $this->activeModuleId;
        $bundle = app(SettingsService::class)->exportConfiguration($target, true);
        $this->exportJson = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->showExportModal = true;
    }

    public function closeExportModal(): void
    {
        $this->showExportModal = false;
        $this->exportJson = '';
    }

    /**
     * Open Import Configuration Modal.
     */
    public function openImportModal(): void
    {
        $this->importJson = '';
        $this->importPreview = [];
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->importJson = '';
        $this->importPreview = [];
    }

    /**
     * Run Pre-Flight Dry Run on Pasted Import JSON.
     */
    public function runImportDryRun(): void
    {
        if (empty(trim($this->importJson))) {
            Notification::make()->title('Empty Input')->body('Please paste JSON configuration payload.')->warning()->send();
            return;
        }

        $decoded = json_decode($this->importJson, true);
        if (!is_array($decoded)) {
            Notification::make()->title('Malformed JSON')->body('JSON syntax error in configuration payload.')->danger()->send();
            return;
        }

        try {
            $this->importPreview = app(SettingsService::class)->previewImport($decoded);
            if ($this->importPreview['valid']) {
                Notification::make()->title('Dry-Run Verified')->body("Valid payload: {$this->importPreview['change_count']} setting change(s) detected.")->success()->send();
            } else {
                Notification::make()->title('Dry-Run Errors')->body(implode('; ', $this->importPreview['errors']))->danger()->send();
            }
        } catch (\Throwable $e) {
            Notification::make()->title('Preview Error')->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * Execute Transactional Import of Verified Configuration.
     */
    public function executeImport(): void
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'canEditModuleSettings') || !$user->canEditModuleSettings()) {
            Notification::make()->title('Unauthorized')->danger()->send();
            return;
        }

        $decoded = json_decode($this->importJson, true);
        if (!is_array($decoded)) {
            return;
        }

        try {
            $result = app(SettingsService::class)->importConfiguration($decoded, $user->id);
            $this->closeImportModal();

            Notification::make()
                ->title('Configuration Imported')
                ->body("Successfully imported {$result['imported_count']} settings across " . count($result['affected_modules']) . " modules.")
                ->success()
                ->send();

            if ($this->activeModuleId) {
                $this->openModule($this->activeModuleId);
            }
        } catch (\Throwable $e) {
            Notification::make()->title('Import Failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function getActiveModuleProperty(string $property = null)
    {
        if (!$this->activeModuleId) {
            return null;
        }

        $mod = ModuleRegistry::get($this->activeModuleId);
        return $property ? ($mod[$property] ?? null) : $mod;
    }

    public function getViewData(): array
    {
        $settingsService = app(SettingsService::class);
        $allModules = ModuleRegistry::all();

        // Cross-Module Deep Search for matching settings keys or descriptions
        $matchedSettings = [];
        if (!empty($this->searchQuery) && strlen(trim($this->searchQuery)) >= 2) {
            $q = trim($this->searchQuery);
            $matchedSettings = ModuleSetting::where('key', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")
                ->orWhere('module', 'like', "%{$q}%")
                ->take(8)
                ->get();
        }

        // Filter modules by category and search query
        $filteredModules = [];
        foreach ($allModules as $id => $module) {
            if ($this->selectedCategory !== 'all' && $module['category'] !== $this->selectedCategory) {
                continue;
            }

            if (!empty($this->searchQuery)) {
                $q = strtolower($this->searchQuery);
                $match = str_contains(strtolower($module['name']), $q)
                    || str_contains(strtolower($module['description']), $q)
                    || str_contains(strtolower($id), $q)
                    || ModuleSetting::where('module', $id)->where('key', 'like', "%{$q}%")->exists();

                if (!$match) {
                    continue;
                }
            }

            $module['is_enabled'] = $settingsService->isModuleEnabled($id);
            $module['setting_count'] = ModuleSetting::where('module', $id)->count();
            $module['last_updated'] = ModuleSetting::where('module', $id)->max('updated_at');
            $filteredModules[$id] = $module;
        }

        // Active module settings grouped
        $activeSettingsGrouped = [];
        if ($this->activeModuleId) {
            $settingsList = $settingsService->getAllForModule($this->activeModuleId);
            foreach ($settingsList as $s) {
                $activeSettingsGrouped[$s->group][] = $s;
            }
        }

        // Audit Logs (recent 20)
        $auditLogs = ModuleSettingAuditLog::with('user')
            ->latest('id')
            ->take(20)
            ->get();

        // Overall stats
        $totalModules = count($allModules);
        $activeModulesCount = 0;
        foreach (array_keys($allModules) as $mid) {
            if ($settingsService->isModuleEnabled($mid)) {
                $activeModulesCount++;
            }
        }

        return [
            'modules' => $filteredModules,
            'matchedSettings' => $matchedSettings,
            'activeSettingsGrouped' => $activeSettingsGrouped,
            'auditLogs' => $auditLogs,
            'stats' => [
                'total_modules' => $totalModules,
                'active_modules' => $activeModulesCount,
                'total_settings' => ModuleSetting::count(),
                'sensitive_count' => ModuleSetting::where('is_sensitive', true)->count(),
            ],
        ];
    }
}
