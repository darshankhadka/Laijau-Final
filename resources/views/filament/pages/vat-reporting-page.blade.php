<x-filament-panels::page>
    @php
    $ret = $this->statutoryReturn;
    $sales = $ret['sales'];
    $purch = $ret['purchases'];
    $recon = $ret['reconciliation'];
    $drill = $ret['drill_down'];
    @endphp
    <div class="lj-vat-root">
        <style>
            .lj-vat-root {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica NNpe", Arial, sans-serif;
                color: #0f172a;
                display: flex;
                flex-direction: column;
                gap: 1.5rem;
                padding-bottom: 2.5rem;
            }

            .dark .lj-vat-root {
                color: #f8fafc;
            }

            /* Header Hero Banner (Clean Minimalist Light Mode) */
            .lj-vat-hero {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 0.75rem;
                padding: 1.25rem 1.75rem;
                color: #0f172a;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
                display: flex;
                flex-direction: column;
                gap: 1.25rem;
            }

            .dark .lj-vat-hero {
                background: #0f172a;
                border-color: #1e293b;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.25);
                color: #f8fafc;
            }

            .lj-vat-hero-top {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 1.25rem;
            }

            .lj-vat-hero-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.25rem 0.65rem;
                border-radius: 9999px;
                font-size: 0.75rem;
                font-weight: 600;
                background: #f1f5f9;
                color: #334155;
                border: 1px solid #e2e8f0;
                margin-bottom: 0.5rem;
            }

            .dark .lj-vat-hero-badge {
                background: #1e293b;
                color: #cbd5e1;
                border-color: #334155;
            }

            .lj-vat-hero-title {
                font-size: 1.35rem;
                font-weight: 700;
                letter-spacing: -0.02em;
                margin: 0;
                color: #0f172a;
            }

            .dark .lj-vat-hero-title {
                color: #f8fafc;
            }

            .lj-vat-hero-sub {
                font-size: 0.8125rem;
                color: #64748b;
                margin-top: 0.35rem;
                line-height: 1.45;
            }

            .dark .lj-vat-hero-sub {
                color: #94a3b8;
            }

            /* Controls Toolbar inside Banner */
            .lj-vat-controls {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 0.75rem;
                background: #f8fafc;
                padding: 0.65rem 0.85rem;
                border-radius: 0.5rem;
                border: 1px solid #e2e8f0;
            }

            .dark .lj-vat-controls {
                background: #1e293b;
                border-color: #334155;
            }

            .lj-vat-control-field {
                display: flex;
                flex-direction: column;
                gap: 0.2rem;
            }

            .lj-vat-control-label {
                font-size: 0.625rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #64748b;
            }

            .dark .lj-vat-control-label {
                color: #94a3b8;
            }

            .lj-vat-select {
                background: #ffffff;
                color: #0f172a;
                border: 1px solid #cbd5e1;
                font-size: 0.75rem;
                font-weight: 600;
                border-radius: 0.375rem;
                padding: 0.35rem 0.65rem;
                outline: none;
            }

            .dark .lj-vat-select {
                background: #0f172a;
                color: #f8fafc;
                border-color: #334155;
            }

            .lj-vat-status-pill {
                display: inline-flex;
                align-items: center;
                gap: 0.375rem;
                padding: 0.375rem 0.75rem;
                border-radius: 0.5rem;
                font-size: 0.75rem;
                font-weight: 800;
            }

            .lj-vat-status-draft {
                background: #fef3c7;
                color: #92400e;
            }

            .lj-vat-status-reviewed {
                background: #dbeafe;
                color: #1e40af;
            }

            .lj-vat-status-filed {
                background: #d1fae5;
                color: #065f46;
            }

            /* Action Buttons */
            .lj-vat-actions {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding-top: 0.85rem;
                border-top: 1px solid #e2e8f0;
            }

            .dark .lj-vat-actions {
                border-top-color: #1e293b;
            }

            .lj-vat-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.45rem 0.85rem;
                border-radius: 0.375rem;
                font-size: 0.75rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.15s ease;
                border: 1px solid transparent;
            }

            .lj-vat-btn-secondary {
                background: #ffffff;
                color: #334155;
                border-color: #cbd5e1;
            }

            .dark .lj-vat-btn-secondary {
                background: #1e293b;
                color: #cbd5e1;
                border-color: #334155;
            }

            .lj-vat-btn-secondary:hover {
                background: #f8fafc;
                border-color: #94a3b8;
                color: #0f172a;
            }

            .lj-vat-btn-primary {
                background: #0f172a;
                color: #ffffff;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            }

            .dark .lj-vat-btn-primary {
                background: #38bdf8;
                color: #0f172a;
            }

            .lj-vat-btn-primary:hover {
                background: #1e293b;
                transform: translateY(-1px);
            }

            .lj-vat-btn-success {
                background: #059669;
                color: #ffffff;
                box-shadow: 0 1px 2px rgba(5, 150, 105, 0.15);
            }

            .lj-vat-btn-success:hover {
                background: #047857;
                transform: translateY(-1px);
            }

            /* 2-Column Section A & Section B Grid */
            .lj-vat-grid-2 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1.25rem;
            }

            @media (max-width: 960px) {
                .lj-vat-grid-2 {
                    grid-template-columns: 1fr;
                }
            }

            .lj-vat-section-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 0.875rem;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
                display: flex;
                flex-direction: column;
            }

            .dark .lj-vat-section-card {
                background: #1e293b;
                border-color: #334155;
            }

            .lj-vat-card-header {
                padding: 1rem 1.5rem;
                border-bottom: 1px solid #e2e8f0;
                background: #f8fafc;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .dark .lj-vat-card-header {
                background: #0f172a;
                border-color: #334155;
            }

            .lj-vat-card-title {
                font-size: 0.9375rem;
                font-weight: 800;
                margin: 0;
            }

            .lj-vat-card-sub {
                font-size: 0.6875rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .lj-vat-btn-drill {
                display: inline-flex;
                align-items: center;
                gap: 0.375rem;
                padding: 0.3rem 0.65rem;
                border-radius: 0.5rem;
                font-size: 0.75rem;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.15s ease;
            }

            .lj-vat-rows {
                padding: 1.25rem 1.5rem;
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                flex: 1;
            }

            .lj-vat-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-size: 0.8125rem;
                padding-bottom: 0.5rem;
                border-bottom: 1px solid #f1f5f9;
            }

            .dark .lj-vat-row {
                border-color: #334155;
            }

            .lj-vat-row:last-child {
                border-bottom: none;
            }

            .lj-vat-row-val {
                font-family: ui-monospace, monospace;
                font-weight: 700;
            }

            .lj-vat-highlight-box {
                border-radius: 0.75rem;
                padding: 1rem 1.25rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-top: auto;
            }

            .lj-vat-box-amber {
                background: #fffbeb;
                border: 1px solid #fde68a;
            }

            .dark .lj-vat-box-amber {
                background: #272115;
                border-color: #574618;
            }

            .lj-vat-box-blue {
                background: #eff6ff;
                border: 1px solid #bfdbfe;
            }

            .dark .lj-vat-box-blue {
                background: #172554;
                border-color: #1e3a8a;
            }

            .lj-vat-highlight-num {
                font-size: 1.35rem;
                font-weight: 900;
                font-family: ui-monospace, monospace;
            }

            /* Section C Reconciliation Card */
            .lj-vat-recon-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 0.875rem;
                padding: 1.5rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            }

            .dark .lj-vat-recon-card {
                background: #1e293b;
                border-color: #334155;
            }

            .lj-vat-recon-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
                margin-top: 1rem;
            }

            @media (max-width: 768px) {
                .lj-vat-recon-grid {
                    grid-template-columns: 1fr;
                }
            }

            .lj-vat-recon-kpi {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 0.75rem;
                padding: 1rem 1.25rem;
            }

            .dark .lj-vat-recon-kpi {
                background: #0f172a;
                border-color: #334155;
            }

            .lj-vat-recon-kpi.final {
                border-width: 2px;
            }

            .lj-vat-recon-kpi.payable {
                background: #fef2f2;
                border-color: #fca5a5;
            }

            .dark .lj-vat-recon-kpi.payable {
                background: #2d1515;
                border-color: #7f1d1d;
            }

            .lj-vat-recon-kpi.credit {
                background: #ecfdf5;
                border-color: #6ee7b7;
            }

            .dark .lj-vat-recon-kpi.credit {
                background: #06281e;
                border-color: #065f46;
            }

            /* Drill Down Modal */
            .lj-vat-modal-overlay {
                position: fixed;
                inset: 0;
                z-index: 9999;
                background: rgba(15, 23, 42, 0.75);
                backdrop-filter: blur(4px);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
            }

            .lj-vat-modal {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 1rem;
                width: 100%;
                max-width: 56rem;
                max-height: 85vh;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
            }

            .dark .lj-vat-modal {
                background: #1e293b;
                border-color: #334155;
            }

            .lj-vat-modal-header {
                padding: 1.125rem 1.5rem;
                border-bottom: 1px solid #e2e8f0;
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: #f8fafc;
            }

            .dark .lj-vat-modal-header {
                background: #0f172a;
                border-color: #334155;
            }

            .lj-vat-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.8125rem;
                text-align: left;
            }

            .lj-vat-table th {
                background: #f8fafc;
                color: #475569;
                font-size: 0.6875rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                padding: 0.75rem 1rem;
                border-bottom: 1px solid #e2e8f0;
            }

            .dark .lj-vat-table th {
                background: #0f172a;
                color: #94a3b8;
                border-color: #334155;
            }

            .lj-vat-table td {
                padding: 0.75rem 1rem;
                border-bottom: 1px solid #f1f5f9;
            }

            .dark .lj-vat-table td {
                border-color: #334155;
            }
        </style>

        <!-- Official IRD Return Header -->
        <div class="lj-vat-hero">
            <div class="lj-vat-hero-top">
                <div>
                    <div class="lj-vat-hero-badge">
                        <span>🏛️</span>
                        <span>अनुसूची १० (नियम २३ सँग सम्बन्धित) — Nepal Value Added Tax Act 2052</span>
                    </div>
                    <h1 class="lj-vat-hero-title">Nepal Inland Revenue Department (IRD) VAT Return</h1>
                    <p class="lj-vat-hero-sub">
                        Taxpayer: <strong>{{ $ret['company_name'] }}</strong> |
                        PAN: <span style="font-family: ui-monospace, monospace; color: #0f172a; font-weight: 700;">{{ $ret['company_pan'] }}</span> |
                        Jurisdiction: <span>{{ $ret['ird_office'] }}</span>
                    </p>
                </div>

                <!-- Controls: FY, Period, Status -->
                <div class="lj-vat-controls">
                    <div class="lj-vat-control-field">
                        <label class="lj-vat-control-label">Fiscal Year</label>
                        <select wire:model.live="selectedFiscalYear" class="lj-vat-select">
                            @foreach(\App\Models\Accounting\AccountingFiscalYear::orderBy('start_date', 'desc')->get() as $fy)
                            <option value="{{ $fy->fiscal_year }}">{{ $fy->fiscal_year }} (BS)</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="lj-vat-control-field">
                        <label class="lj-vat-control-label">Tax Period</label>
                        <select wire:model.live="selectedPeriod" class="lj-vat-select">
                            <option value="">Full Fiscal Year</option>
                            @foreach($this->availablePeriods as $p)
                            <option value="{{ $p->name }}">{{ $p->nepali_label ?: $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="lj-vat-control-field">
                        <label class="lj-vat-control-label">Filing Status</label>
                        <span class="lj-vat-status-pill {{ $filingStatus === 'Filed' ? 'lj-vat-status-filed' : ($filingStatus === 'Reviewed' ? 'lj-vat-status-reviewed' : 'lj-vat-status-draft') }}">
                            ● {{ $filingStatus }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="lj-vat-actions">
                <div>
                    <button type="button" wire:click="exportAnusuchi10Csv" class="lj-vat-btn lj-vat-btn-secondary">
                        <span>📥</span>
                        <span>Export IRD Anusuchi 10 (CSV)</span>
                    </button>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    @if($filingStatus === 'Draft')
                    <button type="button" wire:click="markReviewed" class="lj-vat-btn lj-vat-btn-primary">
                        <span>✓</span>
                        <span>Mark Reviewed by Auditor</span>
                    </button>
                    @endif

                    @if($filingStatus !== 'Filed')
                    <button
                        type="button"
                        wire:click="markFiled"
                        wire:confirm="Lock and archive statutory VAT declaration for IRD filing?"
                        class="lj-vat-btn lj-vat-btn-success">
                        <span>🔒</span>
                        <span>Submit & Lock Return</span>
                    </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Section A: Sales (बिक्री) & Section B: Purchases (खरिद) Grid -->
        <div class="lj-vat-grid-2">
            <!-- SECTION A: SALES (Bikri Khata) -->
            <div class="lj-vat-section-card">
                <div class="lj-vat-card-header">
                    <div>
                        <span class="lj-vat-card-sub" style="color: #059669;">खण्ड १ : बिक्री विवरण</span>
                        <h2 class="lj-vat-card-title">Section A — Bikri Khata (Sales)</h2>
                    </div>
                    <button
                        type="button"
                        wire:click="openDrillDown('sales')"
                        class="lj-vat-btn-drill"
                        style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;">
                        <span>🔍</span>
                        <span>View {{ $drill['sales_count'] }} Invoices</span>
                    </button>
                </div>

                <div class="lj-vat-rows">
                    <div class="lj-vat-row">
                        <span>१.१ करयोग्य बिक्री (Taxable Sales 13%)</span>
                        <span class="lj-vat-row-val">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($sales['taxable_sales_13'], 'Rs. ', 2) }}</span>
                    </div>

                    <div class="lj-vat-row">
                        <span>१.२ निकासी बिक्री (Zero-Rated / Exports)</span>
                        <span class="lj-vat-row-val">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($sales['export_sales_0'], 'Rs. ', 2) }}</span>
                    </div>

                    <div class="lj-vat-row">
                        <span>१.३ कर छुट हुने बिक्री (Exempt Sales)</span>
                        <span class="lj-vat-row-val">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($sales['exempt_sales'], 'Rs. ', 2) }}</span>
                    </div>

                    <div class="lj-vat-row">
                        <span style="color: #dc2626;">१.४ बिक्री फिर्ता / क्रेडिट नोट (Credit Notes)</span>
                        <span class="lj-vat-row-val" style="color: #dc2626;">- {{ \App\Helpers\NepaliNumberHelper::formatCurrency($sales['credit_notes_taxable'], 'Rs. ', 2) }}</span>
                    </div>

                    <div class="lj-vat-row" style="font-weight: 800; font-size: 0.875rem;">
                        <span>कुल बिक्री (Total Gross Sales)</span>
                        <span class="lj-vat-row-val">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($sales['total_sales'], 'Rs. ', 2) }}</span>
                    </div>

                    <!-- Highlight Output VAT -->
                    <div class="lj-vat-highlight-box lj-vat-box-amber">
                        <div>
                            <span style="font-size: 0.6875rem; font-weight: 800; text-transform: uppercase; color: #b45309;">१.५ संकलित कर (Output VAT Collected)</span>
                            <div style="font-size: 0.6875rem; color: #92400e; margin-top: 0.15rem;">Statutory 13% tax collected on sales</div>
                        </div>
                        <div class="lj-vat-highlight-num" style="color: #d97706;">
                            {{ \App\Helpers\NepaliNumberHelper::formatCurrency($sales['output_vat'], 'Rs. ', 2) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION B: PURCHASES (Kharid Khata) -->
            <div class="lj-vat-section-card">
                <div class="lj-vat-card-header">
                    <div>
                        <span class="lj-vat-card-sub" style="color: #2563eb;">खण्ड २ : खरिद तथा पैठारी विवरण</span>
                        <h2 class="lj-vat-card-title">Section B — Kharid Khata (Purchases)</h2>
                    </div>
                    <button
                        type="button"
                        wire:click="openDrillDown('purchases')"
                        class="lj-vat-btn-drill"
                        style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
                        <span>🔍</span>
                        <span>View {{ $drill['purchase_count'] }} Bills</span>
                    </button>
                </div>

                <div class="lj-vat-rows">
                    <div class="lj-vat-row">
                        <span>२.१ स्वदेशी करयोग्य खरिद (Local Taxable Purchases 13%)</span>
                        <span class="lj-vat-row-val">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($purch['taxable_purchases_13'], 'Rs. ', 2) }}</span>
                    </div>

                    <div class="lj-vat-row">
                        <span>२.२ पैठारी करयोग्य (Customs / Imports)</span>
                        <span class="lj-vat-row-val">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($purch['taxable_imports'], 'Rs. ', 2) }}</span>
                    </div>

                    <div class="lj-vat-row">
                        <span>२.३ कर छुट हुने खरिद (Exempt Purchases)</span>
                        <span class="lj-vat-row-val">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($purch['exempt_purchases'], 'Rs. ', 2) }}</span>
                    </div>

                    <div class="lj-vat-row" style="font-weight: 800; font-size: 0.875rem;">
                        <span>कुल खरिद (Total Gross Purchases)</span>
                        <span class="lj-vat-row-val">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($purch['total_purchases'], 'Rs. ', 2) }}</span>
                    </div>

                    <!-- Highlight Input VAT -->
                    <div class="lj-vat-highlight-box lj-vat-box-blue">
                        <div>
                            <span style="font-size: 0.6875rem; font-weight: 800; text-transform: uppercase; color: #1d4ed8;">२.४ कट्टी दाबी कर (Deductible Input VAT)</span>
                            <div style="font-size: 0.6875rem; color: #1e40af; margin-top: 0.15rem;">Statutory 13% tax credit on procurement</div>
                        </div>
                        <div class="lj-vat-highlight-num" style="color: #2563eb;">
                            {{ \App\Helpers\NepaliNumberHelper::formatCurrency($purch['input_vat'], 'Rs. ', 2) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION C: RECONCILIATION & NET POSITION -->
        <div class="lj-vat-recon-card">
            <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem;">
                <div>
                    <span style="font-size: 0.6875rem; font-weight: 800; text-transform: uppercase; color: #4f46e5;">खण्ड ३ : कर मिलान तथा खुद दायित्व</span>
                    <h2 style="font-size: 1.125rem; font-weight: 800; margin: 0.15rem 0 0 0;">Section C — Net VAT Position & IRD Settlement</h2>
                </div>
                <div style="font-size: 0.75rem; color: #64748b;">
                    Formula: <span style="font-family: ui-monospace, monospace; font-weight: 700;">Output VAT − Input VAT Credit = Net Tax Position</span>
                </div>
            </div>

            <div class="lj-vat-recon-grid">
                <div class="lj-vat-recon-kpi">
                    <span style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Output VAT Collected (+)</span>
                    <div style="font-size: 1.35rem; font-weight: 800; font-family: ui-monospace, monospace; color: #d97706; margin-top: 0.35rem;">
                        {{ \App\Helpers\NepaliNumberHelper::formatCurrency($recon['output_vat'], 'Rs. ', 2) }}
                    </div>
                </div>

                <div class="lj-vat-recon-kpi">
                    <span style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Input VAT Deductible (−)</span>
                    <div style="font-size: 1.35rem; font-weight: 800; font-family: ui-monospace, monospace; color: #2563eb; margin-top: 0.35rem;">
                        {{ \App\Helpers\NepaliNumberHelper::formatCurrency($recon['input_vat'], 'Rs. ', 2) }}
                    </div>
                </div>

                <div class="lj-vat-recon-kpi final {{ $recon['status'] === 'vat_payable' ? 'payable' : 'credit' }}">
                    <span style="font-size: 0.6875rem; font-weight: 800; text-transform: uppercase; color: {{ $recon['status'] === 'vat_payable' ? '#b91c1c' : '#047857' }};">
                        {{ $recon['status'] === 'vat_payable' ? 'राजस्व खातामा दाखिला गर्नुपर्ने (Net Payable to IRD)' : 'अर्को महिनामा सार्ने कर (VAT Credit Carried Forward)' }}
                    </span>
                    <div style="font-size: 1.65rem; font-weight: 900; font-family: ui-monospace, monospace; color: {{ $recon['status'] === 'vat_payable' ? '#dc2626' : '#059669' }}; margin-top: 0.35rem;">
                        {{ \App\Helpers\NepaliNumberHelper::formatCurrency(abs($recon['net_vat_position']), 'Rs. ', 2) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- DRILL DOWN MODAL (Line-by-Line Transactions) -->
        @if($drillDownType !== null)
        <div class="lj-vat-modal-overlay">
            <div class="lj-vat-modal">
                <div class="lj-vat-modal-header">
                    <div>
                        <h3 style="font-size: 1.125rem; font-weight: 800; margin: 0;">
                            {{ $drillDownType === 'sales' ? 'Bikri Khata Audit Trail — Sales Invoices' : 'Kharid Khata Audit Trail — Supplier Bills' }}
                        </h3>
                        <p style="margin: 0.25rem 0 0 0; font-size: 0.75rem; color: #64748b;">Line-by-line statutory verification for IRD Tax Return ({{ $selectedFiscalYear }})</p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeDrillDown"
                        style="background: transparent; border: none; font-size: 1.25rem; cursor: pointer; color: #64748b;">
                        ✕
                    </button>
                </div>

                <div style="padding: 1.25rem; overflow-y: auto; flex: 1;">
                    @if($drillDownType === 'sales')
                    <table class="lj-vat-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice #</th>
                                <th>Buyer Name</th>
                                <th>PAN</th>
                                <th style="text-align: right;">Taxable (Rs.)</th>
                                <th style="text-align: right;">Output VAT (Rs.)</th>
                                <th style="text-align: right;">Total (Rs.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($drill['sales_invoices'] as $inv)
                            <tr style="font-family: ui-monospace, monospace;">
                                <td>{{ $inv->issue_date?->format('Y-m-d') }}</td>
                                <td style="font-weight: 800; color: #0A2E23;">{{ $inv->invoice_number }}</td>
                                <td style="font-family: -apple-system, sans-serif; font-weight: 700;">{{ $inv->contact_name }}</td>
                                <td style="color: #64748b;">{{ $inv->buyer_pan ?: 'Retail' }}</td>
                                <td style="text-align: right;">{{ \App\Helpers\NepaliNumberHelper::format((float)$inv->taxable_amount, 2) }}</td>
                                <td style="text-align: right; font-weight: 700; color: #d97706;">{{ \App\Helpers\NepaliNumberHelper::format((float)$inv->vat_amount, 2) }}</td>
                                <td style="text-align: right; font-weight: 800;">{{ \App\Helpers\NepaliNumberHelper::format((float)$inv->total_amount, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" style="text-align: center; color: #64748b; padding: 2rem; font-family: -apple-system, sans-serif;">No sales recorded for this tax period.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @else
                    <table class="lj-vat-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Bill #</th>
                                <th>Supplier Name</th>
                                <th>PAN</th>
                                <th style="text-align: right;">Taxable (Rs.)</th>
                                <th style="text-align: right;">Input VAT Credit (Rs.)</th>
                                <th style="text-align: right;">Total (Rs.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($drill['purchase_bills'] as $bill)
                            <tr style="font-family: ui-monospace, monospace;">
                                <td>{{ $bill->issue_date?->format('Y-m-d') }}</td>
                                <td style="font-weight: 800; color: #1e40af;">{{ $bill->invoice_number }}</td>
                                <td style="font-family: -apple-system, sans-serif; font-weight: 700;">{{ $bill->contact_name }}</td>
                                <td style="color: #64748b;">{{ $bill->seller_pan ?: 'N/A' }}</td>
                                <td style="text-align: right;">{{ \App\Helpers\NepaliNumberHelper::format((float)$bill->taxable_amount, 2) }}</td>
                                <td style="text-align: right; font-weight: 700; color: #2563eb;">{{ \App\Helpers\NepaliNumberHelper::format((float)$bill->vat_amount, 2) }}</td>
                                <td style="text-align: right; font-weight: 800;">{{ \App\Helpers\NepaliNumberHelper::format((float)$bill->total_amount, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" style="text-align: center; color: #64748b; padding: 2rem; font-family: -apple-system, sans-serif;">No supplier purchases recorded for this tax period.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @endif
                </div>

                <div style="padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; background: #f8fafc;">
                    <button
                        type="button"
                        wire:click="closeDrillDown"
                        class="lj-vat-btn lj-vat-btn-secondary"
                        style="background: #0f172a; color: #ffffff;">
                        Close Audit Drill-Down
                    </button>
                </div>
            </div>
        </div>
        @endif
    </div>
</x-filament-panels::page>