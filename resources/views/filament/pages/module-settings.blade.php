<x-filament-panels::page>
    <div class="na-settings-root" style="min-height: auto; padding: 0;">
        {{-- 1. HERO BANNER (Clean Minimalist Light Mode) --}}
        <div class="na-header-banner">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; width: 100%;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 22px; border: 1px solid #e2e8f0;">
                        ⚙️
                    </div>
                    <div>
                        <h1 class="na-title-main" style="margin: 0;">
                            Enterprise Module Settings & Control Plane
                        </h1>
                        <p class="na-subtitle">
                            Authoritative configuration plane across Commerce, Inventory, Accounting, POS, HRM, Payments, Logistics & Security.
                        </p>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <button type="button"
                            wire:click="openExportModal"
                            style="padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; background: #ffffff; color: #334155; display: flex; align-items: center; gap: 6px; transition: all 0.15s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                        📤 Export JSON
                    </button>
                    <button type="button"
                            wire:click="openImportModal"
                            style="padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; background: #f8fafc; color: #0f172a; display: flex; align-items: center; gap: 6px; transition: all 0.15s ease;">
                        📥 Import JSON
                    </button>
                    <span class="na-badge" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; font-size: 12px; padding: 6px 14px;">
                        ● {{ $stats['active_modules'] }} / {{ $stats['total_modules'] }} Modules Active
                    </span>
                    <span class="na-badge" style="background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; font-size: 12px; padding: 6px 14px;">
                        {{ $stats['total_settings'] }} Managed Settings
                    </span>
                    <span class="na-badge" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-size: 12px; padding: 6px 14px;">
                        🔒 {{ $stats['sensitive_count'] }} Encrypted Secrets
                    </span>
                </div>
            </div>
        </div>

        {{-- 2. CONTROL BAR: CATEGORY TABS, SEARCH & AUDIT LOG TOGGLE --}}
        <div class="na-card na-card-p4" style="margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                {{-- Navigation View Tabs --}}
                <div style="display: flex; align-items: center; gap: 8px;">
                    <button type="button"
                            wire:click="$set('activeTab', 'modules')"
                            style="padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; transition: all 0.2s ease; {{ $activeTab === 'modules' ? 'background: #0A2E23; color: #FFFFFF; box-shadow: 0 2px 6px rgba(10,46,35,0.3);' : 'background: transparent; color: var(--na-text-secondary);' }}">
                        🧩 Business Modules
                    </button>
                    <button type="button"
                            wire:click="$set('activeTab', 'audit_logs')"
                            style="padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; transition: all 0.2s ease; {{ $activeTab === 'audit_logs' ? 'background: #0A2E23; color: #FFFFFF; box-shadow: 0 2px 6px rgba(10,46,35,0.3);' : 'background: transparent; color: var(--na-text-secondary);' }}">
                        📜 Mutation Audit Trail
                    </button>
                </div>

                @if($activeTab === 'modules')
                {{-- Search & Category Filters --}}
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    {{-- Category Filter --}}
                    <div style="display: flex; align-items: center; gap: 6px;">
                        @foreach(['all' => 'All', 'commercial' => 'Commercial', 'operational' => 'Operational', 'financial' => 'Financial', 'system' => 'System'] as $catKey => $catLabel)
                            <button type="button"
                                    wire:click="$set('selectedCategory', '{{ $catKey }}')"
                                    style="padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid var(--na-border); transition: all 0.15s ease; {{ $selectedCategory === $catKey ? 'background: var(--na-card-subtle); border-color: var(--na-gold); color: var(--na-text);' : 'background: transparent; color: var(--na-text-secondary);' }}">
                                {{ $catLabel }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Search Input --}}
                    <div style="position: relative; min-width: 240px;">
                        <input type="text"
                               wire:model.live.debounce.250ms="searchQuery"
                               placeholder="Search modules or keys..."
                               style="width: 100%; padding: 8px 12px 8px 32px; border-radius: 8px; border: 1px solid var(--na-border); background: var(--na-card); color: var(--na-text); font-size: 13px; outline: none; box-sizing: border-box;">
                        <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 14px; opacity: 0.5;">
                            🔍
                        </span>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- 2.5 DIRECT CROSS-MODULE SETTING MATCHES --}}
        @if(!empty($matchedSettings) && count($matchedSettings) > 0)
            <div class="na-card na-card-p4" style="margin-bottom: 20px; background: rgba(197, 160, 89, 0.08); border: 1px solid rgba(197, 160, 89, 0.3);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 14px;">⚡</span>
                        <strong style="font-size: 13px; color: var(--na-text);">Direct Setting Matches ({{ count($matchedSettings) }})</strong>
                    </div>
                    <span style="font-size: 11px; color: var(--na-text-muted);">Click any setting to jump directly into its configuration</span>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    @foreach($matchedSettings as $mSet)
                        <button type="button"
                                wire:click="openModuleSetting('{{ $mSet->module }}', '{{ $mSet->group }}', '{{ $mSet->key }}')"
                                style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 6px; font-size: 12px; background: var(--na-card); border: 1px solid var(--na-border); cursor: pointer; text-align: left; transition: all 0.15s ease;">
                            <span style="font-weight: 700; color: #0A2E23; text-transform: uppercase; font-size: 10px;">{{ $mSet->module }}</span>
                            <span style="color: var(--na-text-muted);">/</span>
                            <span style="font-family: monospace; font-weight: 600; color: var(--na-text);">{{ $mSet->key }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- 3. TAB 1: MODULES GRID --}}
        @if($activeTab === 'modules')
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; margin-bottom: 30px;">
                @forelse($modules as $moduleId => $mod)
                    <div class="na-card na-card-p6" style="display: flex; flex-direction: column; justify-content: space-between; border-left: 4px solid {{ $mod['is_enabled'] ? '#059669' : '#9CA3AF' }}; transition: all 0.2s ease;">
                        <div>
                            {{-- Card Header --}}
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(10, 46, 35, 0.08); display: flex; align-items: center; justify-content: center; font-size: 20px;">
                                        @if($moduleId === 'commerce') 🛍️
                                        @elseif($moduleId === 'inventory') 📦
                                        @elseif($moduleId === 'purchasing') 📥
                                        @elseif($moduleId === 'pos') 🏛️
                                        @elseif($moduleId === 'accounting') ⚖️
                                        @elseif($moduleId === 'hrm') 👥
                                        @elseif($moduleId === 'crm') 🤝
                                        @elseif($moduleId === 'shipping') 🚚
                                        @elseif($moduleId === 'payments') 💳
                                        @elseif($moduleId === 'notifications') 🔔
                                        @elseif($moduleId === 'marketing') 📣
                                        @elseif($moduleId === 'analytics') 📊
                                        @else ⚙️
                                        @endif
                                    </div>
                                    <div>
                                        <h3 style="font-size: 15px; font-weight: 700; color: var(--na-text); margin: 0;">
                                            {{ $mod['name'] }}
                                        </h3>
                                        <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                                            <span style="font-size: 11px; font-family: monospace; color: var(--na-text-muted);">
                                                v{{ $mod['version'] }}
                                            </span>
                                            <span style="font-size: 10px; padding: 2px 6px; border-radius: 4px; background: var(--na-card-subtle); color: var(--na-text-secondary); text-transform: uppercase; font-weight: 700;">
                                                {{ $mod['category'] }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Status Pill --}}
                                <span class="na-badge" style="{{ $mod['is_enabled'] ? 'background: #D1FAE5; color: #065F46;' : 'background: #F3F4F6; color: #4B5563;' }}">
                                    {{ $mod['is_enabled'] ? 'Active' : 'Suspended' }}
                                </span>
                            </div>

                            {{-- Description --}}
                            <p style="font-size: 12px; color: var(--na-text-secondary); line-height: 1.45; margin: 0 0 16px 0;">
                                {{ $mod['description'] }}
                            </p>

                            {{-- Dependencies (if any) --}}
                            @if(!empty($mod['dependencies']))
                                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 16px; flex-wrap: wrap;">
                                    <span style="font-size: 11px; color: var(--na-text-muted); font-weight: 600;">Requires:</span>
                                    @foreach($mod['dependencies'] as $dep)
                                        <span style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: rgba(197, 160, 89, 0.15); color: #B45309;">
                                            ● {{ ucfirst($dep) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Card Footer Actions --}}
                        <div style="border-top: 1px solid var(--na-border); padding-top: 14px; display: flex; justify-content: space-between; align-items: center;">
                            <div style="font-size: 11px; color: var(--na-text-muted);">
                                <strong>{{ $mod['setting_count'] }}</strong> settings
                            </div>

                            <div style="display: flex; align-items: center; gap: 8px;">
                                @if(empty($mod['is_core']))
                                    <button type="button"
                                            wire:click="toggleModuleStatus('{{ $moduleId }}')"
                                            style="padding: 6px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; border: 1px solid var(--na-border); background: var(--na-card-subtle); color: var(--na-text-secondary); transition: all 0.15s ease;">
                                        {{ $mod['is_enabled'] ? 'Suspend' : 'Activate' }}
                                    </button>
                                @endif

                                <button type="button"
                                        wire:click="openModule('{{ $moduleId }}')"
                                        style="padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; background: #0A2E23; color: #FFFFFF; border: none; box-shadow: 0 1px 3px rgba(10,46,35,0.2); transition: all 0.15s ease;">
                                    Configure →
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--na-text-muted);">
                        No business modules matched your search filter.
                    </div>
                @endforelse
            </div>
        @endif

        {{-- 4. TAB 2: AUDIT TRAIL VIEW --}}
        @if($activeTab === 'audit_logs')
            <div class="na-card" style="padding: 0; overflow: hidden; margin-bottom: 30px;">
                <div style="padding: 16px 20px; border-bottom: 1px solid var(--na-border); background: var(--na-card-subtle); display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="font-family: 'Cinzel', serif, Georgia; font-size: 16px; font-weight: 700; margin: 0; color: var(--na-text);">
                        Immutable Configuration Audit Log
                    </h2>
                    <span class="na-badge" style="background: rgba(10, 46, 35, 0.1); color: var(--na-emerald); font-weight: 700;">
                        Last 20 Mutations
                    </span>
                </div>

                <div class="na-table-wrap">
                    <table class="inv-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Administrator</th>
                                <th>Module & Group</th>
                                <th>Setting Key</th>
                                <th>Previous Value</th>
                                <th>New Value</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($auditLogs as $log)
                                <tr>
                                    <td style="font-size: 12px; font-family: monospace; color: var(--na-text-secondary); white-space: nowrap;">
                                        {{ $log->created_at?->format('M d, Y H:i:s') }}
                                    </td>
                                    <td style="font-weight: 600; color: var(--na-text);">
                                        {{ $log->user_email }}
                                    </td>
                                    <td>
                                        <span class="na-badge" style="background: rgba(10, 46, 35, 0.08); color: var(--na-emerald); font-family: monospace; font-size: 11px;">
                                            {{ $log->module }} / {{ $log->group }}
                                        </span>
                                    </td>
                                    <td style="font-family: monospace; font-weight: 700; color: var(--na-text);">
                                        {{ $log->key }}
                                    </td>
                                    <td style="font-family: monospace; font-size: 11px; color: #DC2626; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        {{ $log->old_value ?? '—' }}
                                    </td>
                                    <td style="font-family: monospace; font-size: 11px; color: #059669; font-weight: 700; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        {{ $log->new_value ?? '—' }}
                                    </td>
                                    <td style="font-size: 11px; font-family: monospace; color: var(--na-text-muted);">
                                        {{ $log->ip_address }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" style="padding: 30px; text-align: center; color: var(--na-text-muted);">
                                        No configuration mutations logged yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- 5. DEDICATED MODULE CONFIGURATION DRAWER / MODAL --}}
        @if($activeModuleId)
            <div class="na-modal-backdrop" wire:click.self="closeModule" style="align-items: flex-start; padding-top: 40px; padding-bottom: 40px; overflow-y: auto;">
                <div class="na-modal-content" style="max-width: 880px; padding: 0; overflow: hidden; border-radius: 16px;">
                    {{-- Modal Header --}}
                    <div style="padding: 18px 24px; background: #ffffff; color: #0f172a; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;" class="dark:!bg-gray-900 dark:!border-gray-800 dark:!text-white">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 36px; height: 36px; border-radius: 8px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 18px; border: 1px solid #e2e8f0;" class="dark:!bg-gray-800 dark:!border-gray-700">
                                ⚙️
                            </div>
                            <div>
                                <h2 style="font-size: 16px; font-weight: 700; margin: 0; color: #0f172a;" class="dark:!text-white">
                                    {{ $this->getActiveModuleProperty('name') }} Settings
                                </h2>
                                <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;" class="dark:!text-gray-400">
                                    {{ $this->getActiveModuleProperty('description') }}
                                </p>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 10px;">
                            <button type="button"
                                    wire:click="resetAllDefaults"
                                    wire:confirm="Are you sure you want to restore all settings for this module to factory defaults?"
                                    style="padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; border: 1px solid rgba(255, 255, 255, 0.3); background: rgba(0, 0, 0, 0.2); color: #FFFFFF;">
                                ↺ Reset All Defaults
                            </button>
                            <button type="button"
                                    wire:click="closeModule"
                                    style="background: transparent; border: none; font-size: 20px; color: #FFFFFF; cursor: pointer; line-height: 1;">
                                ✕
                            </button>
                        </div>
                    </div>

                    {{-- Modal Body: Group Tabs & Form Controls --}}
                    <div style="padding: 20px 24px; background: var(--na-bg);">
                        {{-- Group Tabs --}}
                        @php
                            $groups = $this->getActiveModuleProperty('groups') ?? [];
                        @endphp
                        <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--na-border); padding-bottom: 12px; margin-bottom: 20px; overflow-x: auto;">
                            @foreach($groups as $grpKey => $grpLabel)
                                <button type="button"
                                        wire:click="setActiveGroup('{{ $grpKey }}')"
                                        style="padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; border: none; cursor: pointer; white-space: nowrap; transition: all 0.15s ease; {{ $activeGroup === $grpKey ? 'background: #0A2E23; color: #FFFFFF; box-shadow: 0 2px 4px rgba(10,46,35,0.25);' : 'background: var(--na-card); color: var(--na-text-secondary); border: 1px solid var(--na-border);' }}">
                                    {{ $grpLabel }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Active Group Settings List --}}
                        <div style="display: flex; flex-direction: column; gap: 16px; max-height: 520px; overflow-y: auto; padding-right: 4px;">
                            @php
                                $currentGroupSettings = $activeSettingsGrouped[$activeGroup] ?? [];
                            @endphp

                            @forelse($currentGroupSettings as $s)
                                @php
                                    $impactMeta = \App\Services\Settings\ConfigurationImpactService::getMetadata($s->module, $s->key);
                                    $isHighlighted = ($highlightSettingKey === $s->key);
                                @endphp
                                <div class="na-card na-card-p4" style="border: {{ $isHighlighted ? '2px solid #C5A059' : '1px solid var(--na-border)' }}; {{ $isHighlighted ? 'box-shadow: 0 0 12px rgba(197, 160, 89, 0.4);' : '' }}">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                        <div>
                                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                                <h4 style="font-size: 13px; font-weight: 700; color: var(--na-text); margin: 0;">
                                                    {{ ucwords(str_replace('_', ' ', $s->key)) }}
                                                </h4>
                                                <span style="font-size: 10px; font-family: monospace; color: var(--na-text-muted); background: var(--na-card-subtle); padding: 1px 6px; border-radius: 4px;">
                                                    {{ $s->module }}.{{ $s->key }}
                                                </span>
                                                @if($s->is_sensitive)
                                                    <span style="font-size: 10px; font-weight: 700; color: #DC2626; background: #FEF2F2; padding: 1px 6px; border-radius: 4px; border: 1px solid #FECACA;">
                                                        🔒 Sensitive / Encrypted
                                                    </span>
                                                @endif
                                            </div>
                                            @if($s->description)
                                                <p style="font-size: 12px; color: var(--na-text-secondary); margin: 4px 0 0 0; line-height: 1.4;">
                                                    {{ $s->description }}
                                                </p>
                                            @endif

                                            {{-- What this affects & Severity tag --}}
                                            <div style="display: flex; align-items: center; gap: 6px; margin-top: 6px; flex-wrap: wrap;">
                                                <span style="font-size: 11px; color: var(--na-text-muted); font-weight: 600;">
                                                    🏷️ Affects: <strong style="color: var(--na-text-secondary);">{{ $impactMeta['affects'] }}</strong>
                                                </span>
                                                @if($impactMeta['severity'] === 'high')
                                                    <span class="na-badge" style="background: #FEE2E2; color: #991B1B; font-weight: 700; font-size: 10px; padding: 2px 6px;">
                                                        ⚠️ High Business Impact
                                                    </span>
                                                @elseif($impactMeta['severity'] === 'medium')
                                                    <span class="na-badge" style="background: #FEF3C7; color: #92400E; font-weight: 600; font-size: 10px; padding: 2px 6px;">
                                                        ⚡ Medium Impact
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Reset to Single Default --}}
                                        <button type="button"
                                                wire:click="resetSetting('{{ $s->key }}')"
                                                title="Reset to default: {{ $s->default_value }}"
                                                style="padding: 4px 8px; font-size: 11px; background: transparent; border: 1px solid var(--na-border); border-radius: 4px; color: var(--na-text-muted); cursor: pointer;">
                                            ↺ Default
                                        </button>
                                    </div>

                                    {{-- Typed Form Control --}}
                                    <div style="margin-top: 10px;">
                                        {{-- 1. Boolean Toggle --}}
                                        @if($s->value_type === 'boolean')
                                            <label style="display: inline-flex; align-items: center; gap: 10px; cursor: pointer;">
                                                <input type="checkbox"
                                                       wire:model="formData.{{ $s->key }}"
                                                       style="width: 18px; height: 18px; cursor: pointer; accent-color: #0A2E23;">
                                                <span style="font-size: 13px; font-weight: 600; color: var(--na-text);">
                                                    {{ !empty($formData[$s->key]) ? 'Enabled / Active' : 'Disabled / Inactive' }}
                                                </span>
                                            </label>

                                        {{-- 2. Select Dropdown --}}
                                        @elseif($s->value_type === 'select')
                                            <select wire:model="formData.{{ $s->key }}"
                                                    style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--na-border); background: var(--na-card); color: var(--na-text); font-size: 13px; outline: none;">
                                                @foreach(($s->options ?? []) as $optKey => $optLabel)
                                                    <option value="{{ $optKey }}">{{ $optLabel }}</option>
                                                @endforeach
                                            </select>

                                        {{-- 3. Numeric Types (Currency, Percentage, Duration, Integer, Decimal) --}}
                                        @elseif(in_array($s->value_type, ['currency', 'percentage', 'duration', 'integer', 'decimal']))
                                            <div style="position: relative; max-width: 320px;">
                                                <input type="number"
                                                       step="{{ in_array($s->value_type, ['currency', 'percentage', 'decimal']) ? '0.01' : '1' }}"
                                                       wire:model="formData.{{ $s->key }}"
                                                       style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--na-border); background: var(--na-card); color: var(--na-text); font-size: 13px; font-family: monospace; outline: none; box-sizing: border-box;">
                                                <span style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 11px; font-weight: 700; color: var(--na-text-muted);">
                                                    @if($s->value_type === 'currency') Rs.
                                                    @elseif($s->value_type === 'percentage') %
                                                    @elseif($s->value_type === 'duration') min / days
                                                    @else units
                                                    @endif
                                                </span>
                                            </div>

                                        {{-- 4. Textarea / Multi-line Text --}}
                                        @elseif($s->value_type === 'text')
                                            <textarea wire:model="formData.{{ $s->key }}"
                                                      rows="3"
                                                      style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--na-border); background: var(--na-card); color: var(--na-text); font-size: 13px; outline: none; box-sizing: border-box;"></textarea>

                                        {{-- 5. Sensitive Secret (Password input) --}}
                                        @elseif($s->is_sensitive)
                                            <div style="position: relative;">
                                                <input type="password"
                                                       wire:model="formData.{{ $s->key }}"
                                                       placeholder="Enter secret key to overwrite..."
                                                       style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--na-border); background: var(--na-card); color: var(--na-text); font-size: 13px; font-family: monospace; outline: none; box-sizing: border-box;">
                                            </div>

                                        {{-- 6. Default String / URL --}}
                                        @else
                                            <input type="text"
                                                   wire:model="formData.{{ $s->key }}"
                                                   style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--na-border); background: var(--na-card); color: var(--na-text); font-size: 13px; outline: none; box-sizing: border-box;">
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div style="text-align: center; padding: 24px; color: var(--na-text-muted);">
                                    No configurable settings in this group.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Modal Footer --}}
                    <div style="padding: 16px 24px; background: var(--na-card); border-top: 1px solid var(--na-border); display: flex; justify-content: space-between; align-items: center;">
                        <div style="font-size: 12px; color: var(--na-text-muted);">
                            Changes will take effect instantly and invalidate relevant caches.
                        </div>

                        <div style="display: flex; align-items: center; gap: 10px;">
                            <button type="button"
                                    wire:click="closeModule"
                                    style="padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid var(--na-border); background: var(--na-card-subtle); color: var(--na-text);">
                                Cancel
                            </button>

                            <button type="button"
                                    wire:click="requestSaveModuleSettings"
                                    style="padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; background: #0A2E23; color: #FFFFFF; border: none; box-shadow: 0 2px 6px rgba(10,46,35,0.25);">
                                Save Configuration
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- 6. DEPENDENCY CONFLICT WARNING MODAL --}}
        @if($showDependencyModal)
            <div class="na-modal-backdrop" wire:click.self="closeDependencyModal">
                <div class="na-modal-content" style="max-width: 480px; text-align: center;">
                    <div style="font-size: 36px; margin-bottom: 12px;">
                        ⚠️
                    </div>
                    <h3 style="font-size: 18px; font-weight: 700; color: var(--na-text); margin: 0 0 8px 0;">
                        Module Dependency Protection
                    </h3>
                    <p style="font-size: 13px; color: var(--na-text-secondary); line-height: 1.5; margin: 0 0 20px 0;">
                        {{ $dependencyModalMessage }}
                    </p>
                    <button type="button"
                            wire:click="closeDependencyModal"
                            style="padding: 10px 24px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; background: #0A2E23; color: #FFFFFF; border: none;">
                        Understood
                    </button>
                </div>
            </div>
        @endif

        {{-- 7. CONFIGURATION IMPACT REVIEW MODAL --}}
        @if($showImpactConfirmModal)
            <div class="na-modal-backdrop" wire:click.self="cancelImpactReview">
                <div class="na-modal-content" style="max-width: 660px; border-radius: 16px; overflow: hidden; padding: 0;">
                    <div style="padding: 18px 24px; background: linear-gradient(135deg, #991B1B 0%, #7F1D1D 100%); color: #FFFFFF; display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 24px;">⚠️</span>
                            <div>
                                <h3 style="font-size: 16px; font-weight: 700; margin: 0; color: #FFFFFF;">
                                    Configuration Impact Review
                                </h3>
                                <p style="font-size: 12px; margin: 2px 0 0 0; opacity: 0.9;">
                                    Verify operational consequences before applying changes to production.
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="cancelImpactReview" style="color: #FFFFFF; background: transparent; border: none; font-size: 20px; cursor: pointer; line-height: 1;">✕</button>
                    </div>

                    <div style="padding: 20px 24px; max-height: 480px; overflow-y: auto; background: var(--na-bg);">
                        <p style="font-size: 13px; color: var(--na-text-secondary); margin: 0 0 16px 0;">
                            The following <strong>{{ count($pendingImpactChanges) }}</strong> configuration change(s) have operational dependencies:
                        </p>

                        @foreach($pendingImpactChanges as $ch)
                            <div class="na-card na-card-p4" style="margin-bottom: 12px; border: 1px solid var(--na-border);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <div>
                                        <strong style="font-size: 13px; color: var(--na-text);">{{ $ch['label'] }}</strong>
                                        <span style="font-size: 11px; font-family: monospace; color: var(--na-text-muted); margin-left: 6px;">{{ $ch['key'] }}</span>
                                    </div>
                                    <span class="na-badge" style="background: {{ $ch['severity'] === 'high' ? '#FEE2E2' : '#FEF3C7' }}; color: {{ $ch['severity'] === 'high' ? '#991B1B' : '#92400E' }}; font-weight: 700; font-size: 10px;">
                                        {{ strtoupper($ch['severity']) }} IMPACT
                                    </span>
                                </div>

                                <div style="font-size: 12px; color: var(--na-text-secondary); margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                                    <span>Current: <strong style="font-family: monospace; color: #DC2626;">{{ is_bool($ch['current_value']) ? ($ch['current_value'] ? 'true' : 'false') : ($ch['current_value'] ?? 'empty') }}</strong></span>
                                    <span>→</span>
                                    <span>Proposed: <strong style="font-family: monospace; color: #059669;">{{ is_bool($ch['new_value']) ? ($ch['new_value'] ? 'true' : 'false') : ($ch['new_value'] ?? 'empty') }}</strong></span>
                                </div>

                                <div style="font-size: 12px; line-height: 1.45; background: var(--na-card-subtle); padding: 10px 12px; border-radius: 6px; border-left: 3px solid {{ $ch['severity'] === 'high' ? '#DC2626' : '#D97706' }}; color: var(--na-text);">
                                    <strong style="color: var(--na-text);">Business Impact:</strong> {{ $ch['impact_text'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div style="padding: 16px 24px; background: var(--na-card); border-top: 1px solid var(--na-border); display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" wire:click="cancelImpactReview" style="padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid var(--na-border); background: var(--na-card-subtle); color: var(--na-text);">
                            Cancel & Re-evaluate
                        </button>
                        <button type="button" wire:click="executeCommitModuleSettings" style="padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; background: #0A2E23; color: #FFFFFF; border: none; box-shadow: 0 2px 6px rgba(10,46,35,0.25);">
                            Confirm & Commit Changes
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- 8. EXPORT CONFIGURATION MODAL --}}
        @if($showExportModal)
            <div class="na-modal-backdrop" wire:click.self="closeExportModal">
                <div class="na-modal-content" style="max-width: 680px; border-radius: 16px; overflow: hidden; padding: 0;">
                    <div style="padding: 16px 24px; background: #ffffff; color: #0f172a; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;" class="dark:!bg-gray-900 dark:!border-gray-800 dark:!text-white">
                        <div>
                            <h3 style="font-size: 15px; font-weight: 700; margin: 0; color: #0f172a;" class="dark:!text-white">
                                📤 Export Configuration JSON
                            </h3>
                            <p style="font-size: 12px; margin: 2px 0 0 0; color: #64748b;" class="dark:!text-gray-400">
                                Portable, environment-safe configuration bundle with sanitized credentials.
                            </p>
                        </div>
                        <button type="button" wire:click="closeExportModal" style="color: #64748b; background: transparent; border: none; font-size: 20px; cursor: pointer;">✕</button>
                    </div>

                    <div style="padding: 20px 24px; background: var(--na-bg);">
                        <textarea readonly
                                  rows="14"
                                  style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--na-border); background: var(--na-card); color: var(--na-text); font-family: monospace; font-size: 12px; box-sizing: border-box;">{{ $exportJson }}</textarea>
                    </div>

                    <div style="padding: 14px 24px; background: var(--na-card); border-top: 1px solid var(--na-border); display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 11px; color: var(--na-text-muted);">
                            Sensitive secrets are securely redacted as [REDACTED_SECRET].
                        </span>
                        <button type="button" wire:click="closeExportModal" style="padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; background: #0f172a; color: #FFFFFF; border: none;">
                            Done
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- 9. IMPORT CONFIGURATION MODAL --}}
        @if($showImportModal)
            <div class="na-modal-backdrop" wire:click.self="closeImportModal">
                <div class="na-modal-content" style="max-width: 680px; border-radius: 16px; overflow: hidden; padding: 0;">
                    <div style="padding: 16px 24px; background: #ffffff; color: #0f172a; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;" class="dark:!bg-gray-900 dark:!border-gray-800 dark:!text-white">
                        <div>
                            <h3 style="font-size: 15px; font-weight: 700; margin: 0; color: #0f172a;" class="dark:!text-white">
                                📥 Import Configuration JSON
                            </h3>
                            <p style="font-size: 12px; margin: 2px 0 0 0; color: #64748b;" class="dark:!text-gray-400">
                                Paste a configuration JSON bundle to preview and import atomically.
                            </p>
                        </div>
                        <button type="button" wire:click="closeImportModal" style="color: #64748b; background: transparent; border: none; font-size: 20px; cursor: pointer;">✕</button>
                    </div>

                    <div style="padding: 20px 24px; background: var(--na-bg);">
                        <textarea wire:model="importJson"
                                  rows="10"
                                  placeholder="Paste exported JSON payload here..."
                                  style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--na-border); background: var(--na-card); color: var(--na-text); font-family: monospace; font-size: 12px; box-sizing: border-box;"></textarea>

                        @if(!empty($importPreview))
                            <div style="margin-top: 14px; padding: 12px; border-radius: 8px; background: {{ $importPreview['valid'] ? '#ECFDF5' : '#FEF2F2' }}; border: 1px solid {{ $importPreview['valid'] ? '#A7F3D0' : '#FECACA' }};">
                                <strong style="font-size: 12px; color: {{ $importPreview['valid'] ? '#065F46' : '#991B1B' }};">
                                    {{ $importPreview['valid'] ? '✓ Validation Passed' : '✕ Validation Errors' }}:
                                </strong>
                                <span style="font-size: 12px; color: var(--na-text);">
                                    {{ $importPreview['change_count'] }} setting(s) will be modified.
                                </span>
                            </div>
                        @endif
                    </div>

                    <div style="padding: 14px 24px; background: var(--na-card); border-top: 1px solid var(--na-border); display: flex; justify-content: space-between; align-items: center;">
                        <button type="button" wire:click="runImportDryRun" style="padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid var(--na-border); background: var(--na-card-subtle); color: var(--na-text);">
                            🔍 Preview & Dry-Run
                        </button>

                        <div style="display: flex; gap: 8px;">
                            <button type="button" wire:click="closeImportModal" style="padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid var(--na-border); background: var(--na-card-subtle); color: var(--na-text);">
                                Cancel
                            </button>
                            <button type="button" wire:click="executeImport" style="padding: 8px 20px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; background: #0A2E23; color: #FFFFFF; border: none;">
                                Apply Import (Commit)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
