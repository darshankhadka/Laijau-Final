<x-filament-panels::page class="w-full max-w-full">

    <style>
        /* MASTER LUXURY SETTINGS STYLESHEET */
        .na-set-wrap {
            width: 100%;
            max-width: 100%;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            font-family: inherit;
            box-sizing: border-box;
            padding-bottom: 3rem;
        }

        /* System Status Ribbon (Clean Minimalist Light Mode) */
        .na-status-ribbon {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            color: #0f172a;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr 1fr;
            gap: 1rem;
            align-items: center;
        }

        .dark .na-status-ribbon {
            background: #111827;
            border-color: #1f2937;
            color: #f8fafc;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.25);
        }

        @media (max-width: 1024px) {
            .na-status-ribbon {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .na-status-ribbon {
                grid-template-columns: 1fr;
            }
        }

        .na-status-col {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .na-status-label {
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }

        .dark .na-status-label {
            color: #94a3b8;
        }

        .na-status-val {
            font-size: 0.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.375rem;
            color: #0f172a;
        }

        .dark .na-status-val {
            color: #f8fafc;
        }

        /* Main 2-Column Settings Layout */
        .na-set-grid {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 1.25rem;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .na-set-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Tab Sidebar */
        .na-tab-nav {
            background: #ffffff;
            border: 1px solid rgba(229, 231, 235, 0.9);
            border-radius: 0.875rem;
            padding: 0.625rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.03);
        }

        .dark .na-tab-nav {
            background: #111827;
            border-color: rgba(31, 41, 55, 0.9);
        }

        @media (max-width: 1024px) {
            .na-tab-nav {
                flex-direction: row;
                overflow-x: auto;
                padding: 0.5rem;
            }
        }

        .na-tab-btn {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.625rem 0.875rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #4b5563;
            border: 1px solid transparent;
            background: transparent;
            cursor: pointer;
            transition: all 0.15s ease;
            text-align: left;
            white-space: nowrap;
            width: 100%;
            box-sizing: border-box;
        }

        .dark .na-tab-btn {
            color: #9ca3af;
        }

        .na-tab-btn:hover {
            background: #f9fafb;
            color: #111827;
        }

        .dark .na-tab-btn:hover {
            background: #1f2937;
            color: #f9fafb;
        }

        .na-tab-btn.active {
            background: #0A2E23;
            color: #ffffff;
            border-color: #0A2E23;
            box-shadow: 0 2px 8px rgba(10, 46, 35, 0.2);
        }

        .dark .na-tab-btn.active {
            background: #C5A059;
            color: #0A2E23;
            border-color: #C5A059;
        }

        /* Tab Content Card */
        .na-panel-card {
            background: #ffffff;
            border: 1px solid rgba(229, 231, 235, 0.9);
            border-radius: 0.875rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .dark .na-panel-card {
            background: #111827;
            border-color: rgba(31, 41, 55, 0.9);
        }

        .na-panel-header {
            border-bottom: 1px solid rgba(243, 244, 246, 0.9);
            padding-bottom: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dark .na-panel-header {
            border-bottom-color: rgba(31, 41, 55, 0.8);
        }

        .na-panel-title {
            font-size: 1.05rem;
            font-family: 'Playfair Display', Georgia, serif;
            font-weight: 700;
            color: #111827;
        }

        .dark .na-panel-title {
            color: #f9fafb;
        }

        .na-panel-desc {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .dark .na-panel-desc {
            color: #9ca3af;
        }

        .na-section-subtitle {
            font-size: 0.8125rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #0A2E23;
            border-bottom: 1px dashed rgba(197, 160, 89, 0.3);
            padding-bottom: 0.35rem;
            margin-top: 0.5rem;
        }

        .dark .na-section-subtitle {
            color: #C5A059;
        }

        /* Form Fields */
        .na-field-group {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }

        .na-field-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #374151;
        }

        .dark .na-field-label {
            color: #d1d5db;
        }

        .na-field-hint {
            font-size: 0.6875rem;
            color: #9ca3af;
        }

        .na-input {
            width: 100%;
            padding: 0.55rem 0.75rem;
            font-size: 0.8125rem;
            border-radius: 0.375rem;
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #111827;
            outline: none;
            box-sizing: border-box;
            transition: all 0.15s ease;
        }

        .dark .na-input {
            background: #1f2937;
            border-color: #374151;
            color: #f9fafb;
        }

        .na-input:focus {
            border-color: #C5A059;
            box-shadow: 0 0 0 1px #C5A059;
        }

        .na-input-readonly {
            background: #f9fafb;
            color: #4b5563;
            cursor: not-allowed;
        }

        .dark .na-input-readonly {
            background: #111827;
            color: #9ca3af;
        }

        .na-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .na-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }

        .na-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        @media (max-width: 768px) {

            .na-grid-2,
            .na-grid-3,
            .na-grid-4 {
                grid-template-columns: 1fr;
            }
        }

        /* Callout Alert */
        .na-callout {
            border-radius: 0.5rem;
            padding: 0.875rem 1rem;
            font-size: 0.75rem;
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
            line-height: 1.4;
        }

        .na-callout-gold {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        .dark .na-callout-gold {
            background: rgba(120, 53, 15, 0.25);
            border-color: rgba(180, 83, 9, 0.4);
            color: #fcd34d;
        }

        .na-callout-emerald {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .dark .na-callout-emerald {
            background: rgba(6, 78, 59, 0.25);
            border-color: rgba(6, 95, 70, 0.4);
            color: #6ee7b7;
        }

        /* Action Buttons */
        .na-btn-save {
            background: #0A2E23;
            color: #ffffff;
            font-size: 0.8125rem;
            font-weight: 600;
            padding: 0.55rem 1.25rem;
            border-radius: 0.375rem;
            border: 1px solid #0A2E23;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            transition: all 0.15s;
        }

        .na-btn-save:hover {
            background: #0d3b2d;
        }

        .na-btn-outline {
            background: #ffffff;
            color: #374151;
            font-size: 0.8125rem;
            font-weight: 600;
            padding: 0.55rem 1rem;
            border-radius: 0.375rem;
            border: 1px solid #d1d5db;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            transition: all 0.15s;
        }

        .dark .na-btn-outline {
            background: #1f2937;
            border-color: #374151;
            color: #d1d5db;
        }

        .na-btn-outline:hover {
            background: #f9fafb;
        }

        .dark .na-btn-outline:hover {
            background: #374151;
        }

        /* Switch / Toggle */
        .na-toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .dark .na-toggle-row {
            background: #1f2937;
            border-color: #374151;
        }

        /* Diagnostics Table */
        .na-diag-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .na-diag-table th {
            text-align: left;
            padding: 0.5rem 0.75rem;
            background: #f3f4f6;
            color: #4b5563;
            font-weight: 600;
            border-bottom: 1px solid #e5e7eb;
        }

        .dark .na-diag-table th {
            background: #1f2937;
            color: #9ca3af;
            border-color: #374151;
        }

        .na-diag-table td {
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            color: #111827;
        }

        .dark .na-diag-table td {
            border-color: #1f2937;
            color: #f9fafb;
        }

        /* Gateway Mode Switcher & Sandbox Controls */
        .na-mode-switcher-card {
            background: #fdfdfd;
            border: 1.5px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            transition: all 0.2s ease;
        }

        .dark .na-mode-switcher-card {
            background: #151d2c;
            border-color: #2e384d;
        }

        .na-mode-switcher-card.test-active {
            border-color: #f59e0b;
            background: linear-gradient(to bottom, #fffdf8, #fffbf0);
        }

        .dark .na-mode-switcher-card.test-active {
            border-color: #d97706;
            background: linear-gradient(to bottom, #1a160a, #151d2c);
        }

        .na-mode-switcher-card.live-active {
            border-color: #10b981;
            background: linear-gradient(to bottom, #f7fdfa, #f0fdf4);
        }

        .dark .na-mode-switcher-card.live-active {
            border-color: #059669;
            background: linear-gradient(to bottom, #091c15, #151d2c);
        }

        .na-mode-segmented {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        @media (max-width: 640px) {
            .na-mode-segmented {
                grid-template-columns: 1fr;
            }
        }

        .na-mode-btn {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 0.875rem 1rem;
            border-radius: 0.625rem;
            border: 2px solid #e5e7eb;
            background: #ffffff;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: left;
        }

        .dark .na-mode-btn {
            background: #1f2937;
            border-color: #374151;
        }

        .na-mode-btn:hover {
            border-color: #C5A059;
            transform: translateY(-1px);
        }

        .na-mode-btn.active-test {
            border-color: #f59e0b;
            background: #fffbeb;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
        }

        .dark .na-mode-btn.active-test {
            border-color: #f59e0b;
            background: #271f0c;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
        }

        .na-mode-btn.active-live {
            border-color: #10b981;
            background: #ecfdf5;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }

        .dark .na-mode-btn.active-live {
            border-color: #10b981;
            background: #08291c;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }

        .na-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .na-badge-amber {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .dark .na-badge-amber {
            background: #78350f;
            color: #fef3c7;
            border-color: #b45309;
        }

        .na-badge-emerald {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .dark .na-badge-emerald {
            background: #064e3b;
            color: #d1fae5;
            border-color: #047857;
        }

        .na-testcard-box {
            background: rgba(245, 158, 11, 0.08);
            border: 1px dashed #f59e0b;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            font-size: 0.75rem;
            flex-wrap: wrap;
        }

        .dark .na-testcard-box {
            background: rgba(245, 158, 11, 0.12);
            border-color: #d97706;
        }

        .na-key-card {
            border: 1.5px solid #e5e7eb;
            border-radius: 0.625rem;
            padding: 1rem;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            transition: all 0.2s ease;
        }

        .dark .na-key-card {
            background: #1a2233;
            border-color: #2d3748;
        }

        .na-key-card.is-active-target {
            border-color: #C5A059;
            box-shadow: 0 2px 10px rgba(197, 160, 89, 0.15);
        }
    </style>

    <div class="na-set-wrap">

        {{-- 1. SYSTEM HEALTH & ARCHITECTURE RIBBON --}}
        <div class="na-status-ribbon">
            <div class="na-status-col">
                <span class="na-status-label">Store Architecture</span>
                <span class="na-status-val">
                    <span>✨</span>
                    <span>Laijau Nepal Retail Operations</span>
                </span>
            </div>

            <div class="na-status-col">
                <span class="na-status-label">Payment Engine</span>
                <span class="na-status-val">
                    <span style="color: #10B981; font-size: 1rem;">🟢</span>
                    <span style="color: #10B981; font-weight: 700;">Nepal Gateways (NPR)</span>
                </span>
            </div>

            <div class="na-status-col">
                <span class="na-status-label">Tax & VAT Engine</span>
                <span class="na-status-val">
                    <span style="color: #10B981;">●</span>
                    <span>{{ $vat_enabled ? 'Inland Revenue Department (IRD) 13% Statutory VAT' : 'Tax Disabled' }}</span>
                </span>
            </div>

            <div class="na-status-col">
                <span class="na-status-label">Currency Precision</span>
                <span class="na-status-val">
                    <span>🇳🇵</span>
                    <span>NPR (Rs.) Sole Standard</span>
                </span>
            </div>
        </div>

        {{-- 2. MAIN SETTINGS GRID --}}
        <div class="na-set-grid">

            {{-- Left Navigation Sidebar --}}
            <div class="na-tab-nav">
                <button
                    type="button"
                    wire:click="setTab('identity')"
                    class="na-tab-btn {{ $activeTab === 'identity' ? 'active' : '' }}">
                    <span>🏛️</span>
                    <span>Store Identity</span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('payments')"
                    class="na-tab-btn {{ $activeTab === 'payments' ? 'active' : '' }}">
                    <span>💳</span>
                    <span>Nepal Payments</span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('shipping')"
                    class="na-tab-btn {{ $activeTab === 'shipping' ? 'active' : '' }}">
                    <span>📦</span>
                    <span>Logistics & Shipping</span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('taxes')"
                    class="na-tab-btn {{ $activeTab === 'taxes' ? 'active' : '' }}">
                    <span>⚖️</span>
                    <span>Nepal Tax & VAT Engine</span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('smtp')"
                    class="na-tab-btn {{ $activeTab === 'smtp' ? 'active' : '' }}">
                    <span>✉️</span>
                    <span>Transactional Mail & SMTP</span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('concierge')"
                    class="na-tab-btn {{ $activeTab === 'concierge' ? 'active' : '' }}">
                    <span>💬</span>
                    <span>Concierge & Socials</span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('security')"
                    class="na-tab-btn {{ $activeTab === 'security' ? 'active' : '' }}">
                    <span>🛡️</span>
                    <span>Maintenance & System</span>
                </button>
            </div>

            {{-- Right Setting Panels Container --}}
            <div class="na-panel-card">

                {{-- TAB 1: STORE IDENTITY --}}
                @if($activeTab === 'identity')
                <div class="na-panel-header">
                    <div>
                        <h2 class="na-panel-title">🏛️ Store Identity & Brand Positioning</h2>
                        <p class="na-panel-desc">Configure your store identity, head office details, announcement bar, and SEO metadata.</p>
                    </div>
                </div>

                <div class="na-section-subtitle">Brand Identity</div>
                <div class="na-grid-2">
                    <div class="na-field-group">
                        <label class="na-field-label">Store / Brand Name *</label>
                        <input type="text" wire:model="store_name" class="na-input" />
                        <span class="na-field-hint">Publicly displayed in page headers, invoices, and email templates.</span>
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Short Brand Name</label>
                        <input type="text" wire:model="store_short_name" class="na-input" />
                        <span class="na-field-hint">e.g. Laijau</span>
                    </div>
                </div>

                <div class="na-grid-2">
                    <div class="na-field-group">
                        <label class="na-field-label">Tagline & Brand Subtitle</label>
                        <input type="text" wire:model="store_tagline" class="na-input" />
                        <span class="na-field-hint">e.g. Nepal's Premier Shopping Destination</span>
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Founding / Established Year</label>
                        <input type="text" wire:model="brand_established_year" class="na-input" />
                    </div>
                </div>

                <div class="na-field-group">
                    <label class="na-field-label">Brand Description</label>
                    <textarea wire:model="store_description" rows="2" class="na-input"></textarea>
                    <span class="na-field-hint">Used in SEO meta description, about snippets, and social sharing tags.</span>
                </div>

                <div class="na-section-subtitle">Head Office & Contact</div>
                <div class="na-grid-3">
                    <div class="na-field-group">
                        <label class="na-field-label">Brand Inquiries Email *</label>
                        <input type="email" wire:model="brand_email" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Customer Support Email *</label>
                        <input type="email" wire:model="support_email" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Support / WhatsApp Hotline</label>
                        <input type="text" wire:model="support_phone" class="na-input" />
                    </div>
                </div>

                <div class="na-grid-4">
                    <div class="na-field-group">
                        <label class="na-field-label">City</label>
                        <input type="text" wire:model="business_city" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Postal Code</label>
                        <input type="text" wire:model="business_postal_code" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Country</label>
                        <input type="text" wire:model="business_country" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Timezone</label>
                        <input type="text" wire:model="store_timezone" class="na-input" />
                    </div>
                </div>

                <div class="na-grid-2">
                    <div class="na-field-group">
                        <label class="na-field-label">Legal Registered Entity Name</label>
                        <input type="text" wire:model="legal_entity_name" class="na-input" />
                        <span class="na-field-hint">e.g. Laijau Pvt. Ltd.</span>
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Business Registration (PAN / VAT ID)</label>
                        <input type="text" wire:model="business_registration_number" class="na-input" />
                    </div>
                </div>

                <div class="na-field-group">
                    <label class="na-field-label">Central Office & Warehouse Address</label>
                    <textarea wire:model="showroom_address" rows="2" class="na-input"></textarea>
                </div>

                <div class="na-section-subtitle">Operational & Logistics Positioning</div>
                <div class="na-grid-3">
                    <div class="na-field-group">
                        <label class="na-field-label">Dispatch Origin Hub</label>
                        <input type="text" wire:model="dispatch_origin_hub" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Primary Market</label>
                        <input type="text" wire:model="primary_market" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Service Region</label>
                        <input type="text" wire:model="service_region" class="na-input" />
                    </div>
                </div>

                <div class="na-field-group">
                    <label class="na-field-label">Nepal Delivery Messaging</label>
                    <input type="text" wire:model="nepal_delivery_message" class="na-input" />
                    <span class="na-field-hint">Displayed in header, checkout, and shipping policy page.</span>
                </div>

                <div class="na-section-subtitle">Storefront Announcement Bar</div>
                <div class="na-toggle-row">
                    <div>
                        <div style="font-size: 0.8125rem; font-weight: 600; color: #111827;" class="dark:!text-white">Announcement Bar Active</div>
                        <div style="font-size: 0.6875rem; color: #6b7280;">Displays the ribbon at the top of the storefront.</div>
                    </div>
                    <input type="checkbox" wire:model="announcement_enabled" style="width: 1.125rem; height: 1.125rem; accent-color: #0A2E23;" />
                </div>

                <div class="na-field-group">
                    <label class="na-field-label">Announcement Text (Nepal Storefront)</label>
                    <input type="text" wire:model="announcement_text" class="na-input" />
                </div>

                <div class="na-grid-2">
                    <div class="na-field-group">
                        <label class="na-field-label">Announcement Link</label>
                        <input type="text" wire:model="announcement_link" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Announcement CTA Text</label>
                        <input type="text" wire:model="announcement_cta" class="na-input" />
                    </div>
                </div>

                <div class="na-section-subtitle">SEO & Social Sharing Identity</div>
                <div class="na-grid-2">
                    <div class="na-field-group">
                        <label class="na-field-label">SEO Site Title</label>
                        <input type="text" wire:model="seo_site_title" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Canonical Base URL</label>
                        <input type="url" wire:model="seo_canonical_base_url" class="na-input" />
                    </div>
                </div>

                <div class="na-field-group">
                    <label class="na-field-label">Default SEO Keywords</label>
                    <input type="text" wire:model="seo_keywords" class="na-input" />
                </div>
                @endif

                {{-- TAB 2: NEPAL PAYMENT GATEWAYS --}}
                @if($activeTab === 'payments')
                <div class="na-panel-header">
                    <div>
                        <h2 class="na-panel-title">💳 Nepal Payment Gateways & Digital Wallets</h2>
                        <p class="na-panel-desc">Configure native Nepal payment methods: Cash on Delivery (COD), connectIPS / NCHL direct interbank, eSewa QR & Wallet, and Direct Bank Transfer.</p>
                    </div>
                </div>

                <div class="na-section-subtitle">Payment Gateways Active at Checkout</div>
                <div class="na-grid-3">
                    <div class="na-toggle-row">
                        <div>
                            <div style="font-size: 0.8125rem; font-weight: 600; color: #111827;" class="dark:!text-white">Cash on Delivery (COD)</div>
                            <div style="font-size: 0.6875rem; color: #6b7280;">Kathmandu Valley doorstep cash payment</div>
                        </div>
                        <input type="checkbox" wire:model="payment_cod_enabled" style="width: 1.125rem; height: 1.125rem; accent-color: #0A2E23;" />
                    </div>

                    <div class="na-toggle-row">
                        <div>
                            <div style="font-size: 0.8125rem; font-weight: 600; color: #111827;" class="dark:!text-white">connectIPS / NCHL</div>
                            <div style="font-size: 0.6875rem; color: #6b7280;">Real-time interbank online payment</div>
                        </div>
                        <input type="checkbox" wire:model="payment_connectips_enabled" style="width: 1.125rem; height: 1.125rem; accent-color: #0A2E23;" />
                    </div>

                    <div class="na-toggle-row">
                        <div>
                            <div style="font-size: 0.8125rem; font-weight: 600; color: #111827;" class="dark:!text-white">eSewa Mobile Wallet</div>
                            <div style="font-size: 0.6875rem; color: #6b7280;">QR scan & digital wallet transfer</div>
                        </div>
                        <input type="checkbox" wire:model="payment_esewa_enabled" style="width: 1.125rem; height: 1.125rem; accent-color: #0A2E23;" />
                    </div>
                </div>

                <div class="na-grid-2" style="margin-top: 1rem;">
                    <div class="na-toggle-row">
                        <div>
                            <div style="font-size: 0.8125rem; font-weight: 600; color: #111827;" class="dark:!text-white">Khalti Digital Wallet</div>
                            <div style="font-size: 0.6875rem; color: #6b7280;">Khalti wallet and Fonepay gateway</div>
                        </div>
                        <input type="checkbox" wire:model="payment_khalti_enabled" style="width: 1.125rem; height: 1.125rem; accent-color: #0A2E23;" />
                    </div>

                    <div class="na-toggle-row">
                        <div>
                            <div style="font-size: 0.8125rem; font-weight: 600; color: #111827;" class="dark:!text-white">Direct Bank Transfer / Fonepay</div>
                            <div style="font-size: 0.6875rem; color: #6b7280;">Manual bank deposit with receipt verification</div>
                        </div>
                        <input type="checkbox" wire:model="payment_bank_transfer_enabled" style="width: 1.125rem; height: 1.125rem; accent-color: #0A2E23;" />
                    </div>
                </div>

                {{-- SECTION A: CONNECTIPS / NCHL CONFIGURATION --}}
                <div class="na-key-card" style="border-left: 4px solid #10b981; margin-top: 1.5rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 1rem;">🏦</span>
                            <strong style="font-size: 0.875rem; color: #111827;" class="dark:!text-white">connectIPS / NCHL Gateway Configuration</strong>
                        </div>
                        <span class="na-badge-pill na-badge-emerald">PRIMARY ONLINE GATEWAY</span>
                    </div>

                    <div class="na-grid-3">
                        <div class="na-field-group">
                            <label class="na-field-label">Merchant ID</label>
                            <input type="text" wire:model="connectips_merchant_id" placeholder="MERCHANT-123" class="na-input" style="font-family: monospace;" />
                        </div>

                        <div class="na-field-group">
                            <label class="na-field-label">App ID</label>
                            <input type="text" wire:model="connectips_app_id" placeholder="APP-456" class="na-input" style="font-family: monospace;" />
                        </div>

                        <div class="na-field-group">
                            <label class="na-field-label">App Name</label>
                            <input type="text" wire:model="connectips_app_name" placeholder="LAIJAU" class="na-input" />
                        </div>
                    </div>
                </div>

                {{-- SECTION B: ESEWA QR WALLET --}}
                <div class="na-key-card" style="border-left: 4px solid #10b981; margin-top: 1.5rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 1rem;">📱</span>
                            <strong style="font-size: 0.875rem; color: #111827;" class="dark:!text-white">eSewa Wallet & QR Configuration</strong>
                        </div>
                        <span class="na-badge-pill na-badge-emerald">QR SCAN & PAY</span>
                    </div>

                    <div class="na-grid-2">
                        <div class="na-field-group">
                            <label class="na-field-label">eSewa ID / Mobile Number</label>
                            <input type="text" wire:model="esewa_id" placeholder="9843512095" class="na-input" style="font-family: monospace;" />
                        </div>

                        <div class="na-field-group">
                            <label class="na-field-label">eSewa Registered Account Name</label>
                            <input type="text" wire:model="esewa_account_name" placeholder="Delta Nine business group" class="na-input" />
                        </div>
                    </div>
                </div>

                {{-- SECTION C: BANK TRANSFER / FONEPAY --}}
                <div class="na-key-card" style="border-left: 4px solid #3b82f6; margin-top: 1.5rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 1rem;">🏛️</span>
                            <strong style="font-size: 0.875rem; color: #111827;" class="dark:!text-white">Direct Bank Transfer / Fonepay Deposit Account</strong>
                        </div>
                        <span class="na-badge-pill" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">MANUAL VERIFICATION</span>
                    </div>

                    <div class="na-grid-2">
                        <div class="na-field-group">
                            <label class="na-field-label">Bank Name</label>
                            <input type="text" wire:model="bank_name" placeholder="Nabil Bank Limited" class="na-input" />
                        </div>

                        <div class="na-field-group">
                            <label class="na-field-label">Account Holder Name</label>
                            <input type="text" wire:model="bank_account_name" placeholder="Delta Nine business group" class="na-input" />
                        </div>
                    </div>

                    <div class="na-grid-2">
                        <div class="na-field-group">
                            <label class="na-field-label">Bank Account Number</label>
                            <input type="text" wire:model="bank_account_number" placeholder="01234567890123" class="na-input" style="font-family: monospace;" />
                        </div>

                        <div class="na-field-group">
                            <label class="na-field-label">Branch Name</label>
                            <input type="text" wire:model="bank_branch" placeholder="Kathmandu Main Branch" class="na-input" />
                        </div>
                    </div>
                </div>
                @endif

                {{-- TAB 3: LOGISTICS & SHIPPING --}}
                @if($activeTab === 'shipping')
                <div class="na-panel-header">
                    <div>
                        <h2 class="na-panel-title">📦 Logistics, Dispatch Origin & Delivery Options</h2>
                        <p class="na-panel-desc">Configure Nepal nationwide delivery carriers, thresholds for free luxury shipping, and dispatch origin.</p>
                    </div>
                </div>

                <div class="na-section-subtitle">Shipping Origin & Central Warehouse Dispatch</div>
                <div class="na-grid-4">
                    <div class="na-field-group">
                        <label class="na-field-label">Origin Country (ISO)</label>
                        <input type="text" wire:model="shipping_origin_country" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Origin City</label>
                        <input type="text" wire:model="shipping_origin_city" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Postal Code</label>
                        <input type="text" wire:model="shipping_origin_postal_code" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Dispatch Street Address</label>
                        <input type="text" wire:model="shipping_origin_address" class="na-input" />
                    </div>
                </div>

                <div class="na-section-subtitle">Complimentary Delivery Thresholds</div>
                <div class="na-field-group">
                    <label class="na-field-label">Free Delivery Threshold (Rs.)</label>
                    <input type="number" step="0.01" wire:model="shipping_free_threshold_npr" class="na-input" style="font-family: monospace;" />
                    <span class="na-field-hint">Orders at or above this amount qualify for complimentary delivery in Nepal (e.g. Rs. 5,000.00).</span>
                </div>

                <div class="na-section-subtitle">Kathmandu Valley & Outside Valley Courier Options</div>
                <div class="na-grid-3">
                    <div class="na-field-group">
                        <label class="na-field-label">Inside Kathmandu Valley Rate (Rs.)</label>
                        <input type="number" step="0.01" wire:model="standard_shipping_rate_npr" class="na-input" style="font-family: monospace;" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Outside Valley Standard Courier Rate (Rs.)</label>
                        <input type="number" step="0.01" wire:model="outside_valley_shipping_rate_npr" class="na-input" style="font-family: monospace;" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Remote Districts Express Courier Rate (Rs.)</label>
                        <input type="number" step="0.01" wire:model="express_shipping_rate_npr" class="na-input" style="font-family: monospace;" />
                    </div>
                </div>

                <div class="na-section-subtitle">Showroom Pickup & Local Delivery</div>
                <div class="na-grid-2">
                    <div class="na-field-group">
                        <label class="na-field-label">Kathmandu Showroom Pickup Rate (Rs.)</label>
                        <input type="number" step="0.01" wire:model="pickup_rate_npr" class="na-input" style="font-family: monospace;" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Valley Express Same-Day Rate (Rs.)</label>
                        <input type="number" step="0.01" wire:model="express_delivery_rate_npr" class="na-input" style="font-family: monospace;" />
                    </div>
                </div>

                <div class="na-field-group">
                    <label class="na-field-label">Luxury Packaging Protocol</label>
                    <textarea wire:model="packaging_notes" rows="2" class="na-input"></textarea>
                    <span class="na-field-hint">Warehouse dispatch guidelines printed on packing slips.</span>
                </div>
                @endif

                {{-- TAB 4: NEPAL TAX & VAT ENGINE --}}
                @if($activeTab === 'taxes')
                <div class="na-panel-header">
                    <div>
                        <h2 class="na-panel-title">⚖️ Nepal Statutory VAT & Tax Configuration</h2>
                        <p class="na-panel-desc">Manage Nepal Inland Revenue Department (IRD) 13% statutory VAT calculation and pricing rules.</p>
                    </div>
                </div>

                <div class="na-grid-2">
                    <div class="na-toggle-row">
                        <div>
                            <div style="font-size: 0.8125rem; font-weight: 600; color: #111827;" class="dark:!text-white">Statutory VAT Engine Active</div>
                            <div style="font-size: 0.6875rem; color: #6b7280;">Enables automated calculation of Nepal 13% VAT at checkout.</div>
                        </div>
                        <input type="checkbox" wire:model="vat_enabled" style="width: 1.125rem; height: 1.125rem; accent-color: #0A2E23;" />
                    </div>

                    <div class="na-toggle-row">
                        <div>
                            <div style="font-size: 0.8125rem; font-weight: 600; color: #111827;" class="dark:!text-white">Display Prices Inclusive of VAT</div>
                            <div style="font-size: 0.6875rem; color: #6b7280;">Shows all store product prices with statutory 13% VAT included.</div>
                        </div>
                        <input type="checkbox" wire:model="display_prices_with_vat" style="width: 1.125rem; height: 1.125rem; accent-color: #0A2E23;" />
                    </div>
                </div>

                <div class="na-grid-2">
                    <div class="na-field-group">
                        <label class="na-field-label">Nepal Statutory VAT Rate (%)</label>
                        <input type="number" step="0.01" wire:model="default_vat_rate" class="na-input" style="font-family: monospace;" />
                        <span class="na-field-hint">Statutory rate mandated by Nepal IRD (13.00%).</span>
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Authoritative Currency</label>
                        <input type="text" value="NPR (Rs.)" disabled class="na-input" style="font-family: monospace; background: #f3f4f6;" />
                        <span class="na-field-hint">Sole authoritative operating currency for Nepal.</span>
                    </div>
                </div>

                <div class="na-grid-2">
                    <div class="na-field-group">
                        <label class="na-field-label">Tax Display Label</label>
                        <input type="text" wire:model="tax_display_label" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">VAT Pricing Mode</label>
                        <div class="na-toggle-row" style="padding: 0.5rem 0.75rem; margin-top: 0.25rem;">
                            <span style="font-size: 0.75rem;">Prices Include VAT</span>
                            <input type="checkbox" wire:model="display_prices_with_vat" style="accent-color: #0A2E23;" />
                        </div>
                    </div>
                </div>

                {{-- Live VAT Calculation Preview Widget --}}
                <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem;" class="dark:!bg-gray-800/60 dark:!border-gray-700">
                    <div style="font-size: 0.8125rem; font-weight: 700; color: #111827; margin-bottom: 0.5rem;" class="dark:!text-white">
                        🔍 Live Nepal Statutory VAT Calculation Preview
                    </div>
                    <div style="font-size: 0.75rem; color: #4b5563; line-height: 1.6;" class="dark:!text-gray-300">
                        For a sample luxury piece priced at <strong>Rs. 10,000.00</strong>:
                        <ul style="list-style: disc; margin-left: 1.25rem; margin-top: 0.25rem;">
                            <li>Statutory VAT ({{ number_format($default_vat_rate, 1) }}% Included): <strong>Rs. {{ number_format(10000.00 * ($default_vat_rate / (100 + $default_vat_rate)), 2) }}</strong></li>
                            <li>Net Base Value before VAT: <strong>Rs. {{ number_format(10000.00 / (1 + ($default_vat_rate / 100)), 2) }}</strong></li>
                        </ul>
                    </div>
                </div>
                @endif

                {{-- TAB 5: TRANSACTIONAL EMAIL & SMTP --}}
                @if($activeTab === 'smtp')
                <div class="na-panel-header">
                    <div>
                        <h2 class="na-panel-title">✉️ Transactional Mail Relay & SMTP Transport</h2>
                        <p class="na-panel-desc">Configure secure SMTP relay parameters for order notifications, status updates, and patron correspondence.</p>
                    </div>
                </div>

                <div class="na-grid-4">
                    <div class="na-field-group">
                        <label class="na-field-label">Mail Provider</label>
                        <select wire:model="smtp_provider" class="na-input">
                            <option value="Custom SMTP">Custom SMTP Server</option>
                            <option value="Amazon SES">Amazon SES</option>
                            <option value="Postmark">Postmark</option>
                            <option value="Mailgun">Mailgun</option>
                        </select>
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">SMTP Server Host *</label>
                        <input type="text" wire:model="smtp_host" placeholder="mail.laijau.com" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">SMTP Port *</label>
                        <input type="text" wire:model="smtp_port" placeholder="465" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Encryption Protocol</label>
                        <select wire:model="smtp_encryption" class="na-input">
                            <option value="ssl">SSL (Port 465 - Recommended)</option>
                            <option value="tls">TLS (Port 587)</option>
                            <option value="none">None / Plaintext</option>
                        </select>
                    </div>
                </div>

                <div class="na-grid-2">
                    <div class="na-field-group">
                        <label class="na-field-label">SMTP Username</label>
                        <input type="text" wire:model="smtp_username" placeholder="info@laijau.com" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">SMTP Password (Masked)</label>
                        <input type="text" wire:model="smtp_password" placeholder="••••••••••••" class="na-input" />
                        <span class="na-field-hint">Leave masked value unchanged to preserve existing password.</span>
                    </div>
                </div>

                <div class="na-grid-3">
                    <div class="na-field-group">
                        <label class="na-field-label">Sender Email Address (From) *</label>
                        <input type="email" wire:model="smtp_from_address" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Sender Display Name *</label>
                        <input type="text" wire:model="smtp_from_name" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Reply-To Email</label>
                        <input type="email" wire:model="smtp_reply_to" class="na-input" />
                    </div>
                </div>

                <div class="na-section-subtitle">Transactional Notification Triggers</div>
                <div class="na-grid-3">
                    <div class="na-toggle-row">
                        <span style="font-size: 0.75rem; font-weight: 600;">Order Confirmation</span>
                        <input type="checkbox" wire:model="mail_event_order_confirmation" style="accent-color: #0A2E23;" />
                    </div>

                    <div class="na-toggle-row">
                        <span style="font-size: 0.75rem; font-weight: 600;">Order Shipped / Tracking</span>
                        <input type="checkbox" wire:model="mail_event_order_shipped" style="accent-color: #0A2E23;" />
                    </div>

                    <div class="na-toggle-row">
                        <span style="font-size: 0.75rem; font-weight: 600;">Order Delivered</span>
                        <input type="checkbox" wire:model="mail_event_order_delivered" style="accent-color: #0A2E23;" />
                    </div>

                    <div class="na-toggle-row">
                        <span style="font-size: 0.75rem; font-weight: 600;">Order Cancelled</span>
                        <input type="checkbox" wire:model="mail_event_order_cancelled" style="accent-color: #0A2E23;" />
                    </div>

                    <div class="na-toggle-row">
                        <span style="font-size: 0.75rem; font-weight: 600;">Customer Welcome</span>
                        <input type="checkbox" wire:model="mail_event_customer_registration" style="accent-color: #0A2E23;" />
                    </div>

                    <div class="na-toggle-row">
                        <span style="font-size: 0.75rem; font-weight: 600;">Admin New Order Alert</span>
                        <input type="checkbox" wire:model="mail_event_admin_order_notification" style="accent-color: #0A2E23;" />
                    </div>
                </div>

                {{-- Test Email Dispatcher Box --}}
                <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem;" class="dark:!bg-gray-800/60 dark:!border-gray-700">
                    <div style="font-size: 0.8125rem; font-weight: 700; color: #111827;" class="dark:!text-white">
                        🚀 Secure SMTP Diagnostic Dispatcher
                    </div>
                    <div style="font-size: 0.6875rem; color: #6b7280;" class="dark:!text-gray-300">
                        Test live mail connectivity from the server directly to your inbox without exposing credentials in logs.
                    </div>
                    <div style="display: flex; gap: 0.5rem; max-width: 540px;">
                        <input
                            type="email"
                            wire:model="test_email_recipient"
                            placeholder="recipient@example.com"
                            class="na-input" />
                        <button
                            type="button"
                            wire:click="sendTestEmail"
                            class="na-btn-outline"
                            style="white-space: nowrap;">
                            <span>Send Test</span>
                        </button>
                    </div>
                </div>
                @endif

                {{-- TAB 6: CONCIERGE & SOCIALS --}}
                @if($activeTab === 'concierge')
                <div class="na-panel-header">
                    <div>
                        <h2 class="na-panel-title">💬 Concierge & Social Clienteling</h2>
                        <p class="na-panel-desc">Configure direct patron communication channels, WhatsApp instant messaging, and social profiles.</p>
                    </div>
                </div>

                <div class="na-section-subtitle">VIP Client Concierge & WhatsApp</div>
                <div class="na-grid-3">
                    <div class="na-field-group">
                        <label class="na-field-label">Official WhatsApp Number</label>
                        <input type="text" wire:model="whatsapp_number" placeholder="+45 91 76 81 41" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Concierge Hours</label>
                        <input type="text" wire:model="concierge_hours" placeholder="Mon – Sat: 09:00 – 18:00 CET" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Response Time Indicator</label>
                        <input type="text" wire:model="concierge_response_time" placeholder="Typically responds within 1 hour" class="na-input" />
                    </div>
                </div>

                <div class="na-grid-2">
                    <div class="na-field-group">
                        <label class="na-field-label">Contact CTA Text</label>
                        <input type="text" wire:model="concierge_cta_text" placeholder="Speak with Concierge" class="na-input" />
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Floating Storefront Widget</label>
                        <div class="na-toggle-row" style="padding: 0.55rem 0.75rem;">
                            <span style="font-size: 0.75rem;">Enable Floating WhatsApp Widget</span>
                            <input type="checkbox" wire:model="concierge_widget_enabled" style="accent-color: #0A2E23;" />
                        </div>
                    </div>
                </div>

                <div class="na-field-group">
                    <label class="na-field-label">Default WhatsApp Client Welcome Script</label>
                    <textarea wire:model="whatsapp_welcome_message" rows="2" class="na-input"></textarea>
                    <span class="na-field-hint">Pre-filled message when patrons initiate chat via the WhatsApp badge.</span>
                </div>

                <div class="na-section-subtitle" style="display: flex; align-items: center; justify-content: space-between;">
                    <span>Social Media Platforms (CRUD)</span>
                    <button
                        type="button"
                        wire:click="addSocialLink"
                        class="na-btn-outline"
                        style="font-size: 0.6875rem; padding: 0.25rem 0.6rem;">
                        + Add Platform
                    </button>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    @foreach($social_links as $index => $link)
                    <div style="display: grid; grid-template-columns: 140px 1fr 140px 80px 50px 40px; gap: 0.5rem; align-items: center; background: #f9fafb; padding: 0.625rem; border-radius: 0.375rem; border: 1px solid #e5e7eb;" class="dark:!bg-gray-800/60 dark:!border-gray-700">
                        <input
                            type="text"
                            wire:model="social_links.{{ $index }}.platform"
                            placeholder="Platform"
                            class="na-input"
                            style="font-size: 0.75rem; padding: 0.35rem 0.5rem;" />
                        <input
                            type="url"
                            wire:model="social_links.{{ $index }}.url"
                            placeholder="https://..."
                            class="na-input"
                            style="font-size: 0.75rem; padding: 0.35rem 0.5rem;" />
                        <input
                            type="text"
                            wire:model="social_links.{{ $index }}.display_label"
                            placeholder="Label"
                            class="na-input"
                            style="font-size: 0.75rem; padding: 0.35rem 0.5rem;" />
                        <input
                            type="number"
                            wire:model="social_links.{{ $index }}.sort_order"
                            placeholder="Order"
                            class="na-input"
                            style="font-size: 0.75rem; padding: 0.35rem 0.5rem;" />
                        <div style="display: flex; justify-content: center;">
                            <input
                                type="checkbox"
                                wire:model="social_links.{{ $index }}.enabled"
                                title="Active"
                                style="accent-color: #0A2E23;" />
                        </div>
                        <button
                            type="button"
                            wire:click="removeSocialLink({{ $index }})"
                            style="color: #ef4444; background: transparent; border: none; font-size: 1rem; cursor: pointer;"
                            title="Remove">
                            &times;
                        </button>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- TAB 7: MAINTENANCE & SYSTEM --}}
                @if($activeTab === 'security')
                <div class="na-panel-header">
                    <div>
                        <h2 class="na-panel-title">🛡️ Maintenance Mode, Health & System Utilities</h2>
                        <p class="na-panel-desc">Control storefront access during private salon curation, inspect server runtime diagnostics, and optimize caches.</p>
                    </div>
                </div>

                <div class="na-section-subtitle">Storefront Maintenance Mode</div>
                <div class="na-toggle-row">
                    <div>
                        <div style="font-size: 0.8125rem; font-weight: 600; color: #111827;" class="dark:!text-white">Storefront Maintenance Active</div>
                        <div style="font-size: 0.6875rem; color: #6b7280;">When activated, visitors see a private VIP salon notice (503) while administrators maintain full access.</div>
                    </div>
                    <input type="checkbox" wire:model="maintenance_mode" style="width: 1.125rem; height: 1.125rem; accent-color: #0A2E23;" />
                </div>

                <div class="na-grid-2">
                    <div class="na-field-group">
                        <label class="na-field-label">Maintenance Notice Message</label>
                        <textarea wire:model="maintenance_message" rows="2" class="na-input"></textarea>
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Expected Return Time Indicator</label>
                        <input type="text" wire:model="maintenance_expected_return" placeholder="e.g. In 2 hours or Shortly" class="na-input" />
                    </div>
                </div>

                <div class="na-grid-2">
                    <div class="na-field-group">
                        <label class="na-field-label">Whitelisted IP Addresses (Comma-separated)</label>
                        <input type="text" wire:model="maintenance_allowed_ips" placeholder="127.0.0.1, 192.168.1.100" class="na-input" />
                        <span class="na-field-hint">IPs that can bypass maintenance mode and browse the public storefront.</span>
                    </div>

                    <div class="na-field-group">
                        <label class="na-field-label">Maintenance Contact Email</label>
                        <input type="email" wire:model="maintenance_contact_email" class="na-input" />
                    </div>
                </div>

                <div class="na-section-subtitle">E-Commerce Feature Toggles</div>
                <div class="na-grid-3">
                    <div class="na-toggle-row">
                        <span style="font-size: 0.75rem; font-weight: 600;">User Registration</span>
                        <input type="checkbox" wire:model="feature_registration_enabled" style="accent-color: #0A2E23;" />
                    </div>

                    <div class="na-toggle-row">
                        <span style="font-size: 0.75rem; font-weight: 600;">Guest Checkout</span>
                        <input type="checkbox" wire:model="feature_guest_checkout_enabled" style="accent-color: #0A2E23;" />
                    </div>

                    <div class="na-toggle-row">
                        <span style="font-size: 0.75rem; font-weight: 600;">Customer Wishlist</span>
                        <input type="checkbox" wire:model="feature_wishlist_enabled" style="accent-color: #0A2E23;" />
                    </div>

                    <div class="na-toggle-row">
                        <span style="font-size: 0.75rem; font-weight: 600;">Customer Accounts</span>
                        <input type="checkbox" wire:model="feature_customer_accounts_enabled" style="accent-color: #0A2E23;" />
                    </div>

                    <div class="na-toggle-row">
                        <span style="font-size: 0.75rem; font-weight: 600;">Customer Reviews</span>
                        <input type="checkbox" wire:model="feature_reviews_enabled" style="accent-color: #0A2E23;" />
                    </div>

                    <div class="na-toggle-row">
                        <span style="font-size: 0.75rem; font-weight: 600;">Newsletter Signup</span>
                        <input type="checkbox" wire:model="feature_newsletter_enabled" style="accent-color: #0A2E23;" />
                    </div>
                </div>

                {{-- Runtime Diagnostics Table --}}
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <div class="na-section-subtitle">
                        📊 Live Server Runtime Diagnostics
                    </div>
                    <div style="overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 0.5rem;" class="dark:!border-gray-700">
                        <table class="na-diag-table">
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th>Status / Value</th>
                                    <th>Component</th>
                                    <th>State</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>PHP Runtime</strong></td>
                                    <td><code>{{ $systemDiagnostics['php_version'] ?? PHP_VERSION }}</code></td>
                                    <td><strong>Laravel Engine</strong></td>
                                    <td><code>v{{ $systemDiagnostics['laravel_version'] ?? app()->version() }}</code></td>
                                </tr>
                                <tr>
                                    <td><strong>GD WebP Support</strong></td>
                                    <td><span style="color: #059669; font-weight: 600;">{{ $systemDiagnostics['gd_webp_support'] ?? 'Active' }}</span></td>
                                    <td><strong>Database Engine</strong></td>
                                    <td><code>{{ $systemDiagnostics['mysql_version'] ?? 'MySQL 8.x' }}</code></td>
                                </tr>
                                <tr>
                                    <td><strong>Environment Mode</strong></td>
                                    <td><code>{{ $systemDiagnostics['environment'] ?? app()->environment() }}</code></td>
                                    <td><strong>Memory Limit</strong></td>
                                    <td><code>{{ $systemDiagnostics['memory_limit'] ?? ini_get('memory_limit') }}</code></td>
                                </tr>
                                <tr>
                                    <td><strong>Public Storage Link</strong></td>
                                    <td><span style="color: #059669; font-weight: 600;">{{ $systemDiagnostics['storage_status'] ?? 'Active' }}</span></td>
                                    <td><strong>Timezone</strong></td>
                                    <td><code>{{ $systemDiagnostics['timezone'] ?? config('app.timezone') }}</code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Cache and Storage Actions --}}
                <div class="na-grid-2">
                    <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; display: flex; align-items: center; justify-content: space-between;" class="dark:!bg-gray-800/60 dark:!border-gray-700">
                        <div>
                            <div style="font-size: 0.8125rem; font-weight: 700; color: #111827;" class="dark:!text-white">Application Cache</div>
                            <div style="font-size: 0.6875rem; color: #6b7280;" class="dark:!text-gray-300">Flush compiled Blade, routes, and configs.</div>
                        </div>
                        <button
                            type="button"
                            wire:click="purgeCache"
                            class="na-btn-outline">
                            <span>⚡ Purge Cache</span>
                        </button>
                    </div>

                    <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; display: flex; align-items: center; justify-content: space-between;" class="dark:!bg-gray-800/60 dark:!border-gray-700">
                        <div>
                            <div style="font-size: 0.8125rem; font-weight: 700; color: #111827;" class="dark:!text-white">Public Storage Link</div>
                            <div style="font-size: 0.6875rem; color: #6b7280;" class="dark:!text-gray-300">Synchronize public/storage symlink.</div>
                        </div>
                        <button
                            type="button"
                            wire:click="optimizeStorageLink"
                            class="na-btn-outline">
                            <span>🔗 Sync Storage</span>
                        </button>
                    </div>
                </div>
                @endif

                {{-- Footer Action Bar --}}
                <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 1rem; border-top: 1px solid rgba(243, 244, 246, 0.9); margin-top: 0.5rem;" class="dark:!border-gray-700">
                    <div style="font-size: 0.6875rem; color: #9ca3af;">
                        Settings are database-backed, encrypted at rest, and applied instantly across the store.
                    </div>

                    <button
                        type="button"
                        wire:click="saveSettings"
                        class="na-btn-save">
                        <span>✓ Save Settings</span>
                    </button>
                </div>

            </div>

        </div>

    </div>
</x-filament-panels::page>