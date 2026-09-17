<x-filament-panels::page>
    @php
    $aging = $this->getAgingData();
    $ar = $aging['receivables'];
    $ap = $aging['payables'];
    @endphp
    <div class="lj-arap-root">
        <style>
            .lj-arap-root {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica NNpe", Arial, sans-serif;
                color: #0f172a;
                display: flex;
                flex-direction: column;
                gap: 1.5rem;
                padding-bottom: 2.5rem;
            }

            .dark .lj-arap-root {
                color: #f8fafc;
            }

            /* Header Hero Banner (Clean Minimalist Light Mode) */
            .lj-arap-hero {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 0.75rem;
                padding: 1.25rem 1.75rem;
                color: #0f172a;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 1.25rem;
            }

            .dark .lj-arap-hero {
                background: #0f172a;
                border-color: #1e293b;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.25);
                color: #f8fafc;
            }

            .lj-arap-hero-badge {
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

            .dark .lj-arap-hero-badge {
                background: #1e293b;
                color: #cbd5e1;
                border-color: #334155;
            }

            .lj-arap-hero-title {
                font-size: 1.35rem;
                font-weight: 700;
                letter-spacing: -0.02em;
                margin: 0;
                color: #0f172a;
            }

            .dark .lj-arap-hero-title {
                color: #f8fafc;
            }

            .lj-arap-hero-sub {
                font-size: 0.8125rem;
                color: #64748b;
                margin-top: 0.35rem;
                max-width: 48rem;
                line-height: 1.45;
            }

            .dark .lj-arap-hero-sub {
                color: #94a3b8;
            }

            /* Tab Switcher */
            .lj-arap-tabs {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 0.5rem;
            }

            .dark .lj-arap-tabs {
                border-color: #1e293b;
            }

            .lj-arap-tab-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.65rem 1.25rem;
                border-radius: 0.75rem;
                font-size: 0.875rem;
                font-weight: 700;
                background: #ffffff;
                color: #475569;
                border: 1px solid #e2e8f0;
                cursor: pointer;
                transition: all 0.15s ease;
            }

            .dark .lj-arap-tab-btn {
                background: #1e293b;
                color: #94a3b8;
                border-color: #334155;
            }

            .lj-arap-tab-btn:hover {
                background: #f8fafc;
                color: #0f172a;
            }

            .dark .lj-arap-tab-btn:hover {
                background: #334155;
                color: #f8fafc;
            }

            .lj-arap-tab-btn.active {
                background: #0A2E23;
                color: #ffffff;
                border-color: #0A2E23;
                box-shadow: 0 2px 8px rgba(10, 46, 35, 0.25);
            }

            .dark .lj-arap-tab-btn.active {
                background: #38bdf8;
                color: #0f172a;
                border-color: #38bdf8;
            }

            .lj-arap-count-badge {
                border-radius: 9999px;
                padding: 0.1rem 0.5rem;
                font-size: 0.6875rem;
                font-weight: 800;
                background: rgba(0, 0, 0, 0.08);
            }

            .lj-arap-tab-btn.active .lj-arap-count-badge {
                background: rgba(255, 255, 255, 0.25);
                color: inherit;
            }

            /* 5-Bracket Aging KPI Grid */
            .lj-arap-aging-grid {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 1rem;
            }

            @media (max-width: 1200px) {
                .lj-arap-aging-grid {
                    grid-template-columns: repeat(3, 1fr);
                }
            }

            @media (max-width: 768px) {
                .lj-arap-aging-grid {
                    grid-template-columns: 1fr;
                }
            }

            .lj-arap-aging-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 0.875rem;
                padding: 1.125rem 1.25rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                transition: all 0.2s ease;
            }

            .dark .lj-arap-aging-card {
                background: #1e293b;
                border-color: #334155;
            }

            .lj-arap-aging-card:hover {
                border-color: #C5A059;
                transform: translateY(-1px);
            }

            .lj-arap-card-label {
                font-size: 0.6875rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #64748b;
            }

            .dark .lj-arap-card-label {
                color: #94a3b8;
            }

            .lj-arap-card-label.current {
                color: #059669;
            }

            .lj-arap-card-label.d30 {
                color: #2563eb;
            }

            .lj-arap-card-label.d60 {
                color: #d97706;
            }

            .lj-arap-card-label.d90 {
                color: #dc2626;
            }

            .lj-arap-card-num {
                font-size: 1.45rem;
                font-weight: 800;
                color: #0f172a;
                margin-top: 0.35rem;
                letter-spacing: -0.02em;
                font-family: ui-monospace, monospace;
            }

            .dark .lj-arap-card-num {
                color: #f8fafc;
            }

            .lj-arap-card-num.current {
                color: #059669;
            }

            .lj-arap-card-num.d30 {
                color: #2563eb;
            }

            .lj-arap-card-num.d60 {
                color: #d97706;
            }

            .lj-arap-card-num.d90 {
                color: #dc2626;
            }

            /* Table Container */
            .lj-arap-table-wrap {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 0.875rem;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            }

            .dark .lj-arap-table-wrap {
                background: #1e293b;
                border-color: #334155;
            }

            .lj-arap-table-header {
                padding: 1.125rem 1.5rem;
                border-bottom: 1px solid #e2e8f0;
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: #ffffff;
            }

            .dark .lj-arap-table-header {
                background: #1e293b;
                border-color: #334155;
            }

            .lj-arap-table-title {
                font-size: 1rem;
                font-weight: 800;
                color: #0f172a;
                margin: 0;
            }

            .dark .lj-arap-table-title {
                color: #f8fafc;
            }

            .lj-arap-table-sub {
                font-size: 0.75rem;
                color: #64748b;
            }

            .dark .lj-arap-table-sub {
                color: #94a3b8;
            }

            .lj-arap-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.8125rem;
                text-align: left;
            }

            .lj-arap-table th {
                background: #f8fafc;
                color: #475569;
                font-size: 0.6875rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                padding: 0.875rem 1.25rem;
                border-bottom: 1px solid #e2e8f0;
            }

            .dark .lj-arap-table th {
                background: #0f172a;
                color: #94a3b8;
                border-color: #334155;
            }

            .lj-arap-table td {
                padding: 1rem 1.25rem;
                border-bottom: 1px solid #f1f5f9;
                color: #1e293b;
            }

            .dark .lj-arap-table td {
                border-color: #334155;
                color: #e2e8f0;
            }

            .lj-arap-table tr:last-child td {
                border-bottom: none;
            }

            .lj-arap-table tr:hover td {
                background: #f8fafc;
            }

            .dark .lj-arap-table tr:hover td {
                background: #1e293b;
            }

            .lj-arap-btn-settle {
                display: inline-flex;
                align-items: center;
                gap: 0.375rem;
                padding: 0.35rem 0.75rem;
                border-radius: 0.5rem;
                font-size: 0.75rem;
                font-weight: 700;
                background: #ecfdf5;
                color: #047857;
                border: 1px solid #a7f3d0;
                cursor: pointer;
                transition: all 0.15s ease;
            }

            .dark .lj-arap-btn-settle {
                background: #064e3b;
                color: #6ee7b7;
                border-color: #047857;
            }

            .lj-arap-btn-settle:hover {
                background: #d1fae5;
                transform: translateY(-1px);
            }

            .lj-arap-empty {
                text-align: center;
                padding: 3rem 1.5rem;
                color: #64748b;
            }

            .lj-arap-empty-icon {
                font-size: 2.5rem;
                margin-bottom: 0.5rem;
            }

            .lj-arap-empty-title {
                font-size: 1rem;
                font-weight: 700;
                color: #0f172a;
                margin-bottom: 0.25rem;
            }

            .dark .lj-arap-empty-title {
                color: #f8fafc;
            }
        </style>

        <!-- Hero Header -->
        <div class="lj-arap-hero">
            <div>
                <div class="lj-arap-hero-badge">
                    <span>⚖️</span>
                    <span>Working Capital & Sub-Ledger Control (NAS 1 / NAS 7)</span>
                </div>
                <h1 class="lj-arap-hero-title">Accounts Receivable & Payable Center</h1>
                <p class="lj-arap-hero-sub">
                    Live aging brackets tracking unsettled customer tax invoices (Bikri Khata) and outstanding supplier procurement bills (Kharid Khata) with instant GL voucher settlement.
                </p>
            </div>
        </div>

        <!-- Tab Switcher -->
        <div class="lj-arap-tabs">
            <button
                type="button"
                wire:click="$set('activeTab', 'receivables')"
                class="lj-arap-tab-btn {{ $activeTab === 'receivables' ? 'active' : '' }}">
                <span>👥</span>
                <span>Accounts Receivable (Customer Invoices)</span>
                <span class="lj-arap-count-badge">{{ $ar['count'] }}</span>
            </button>
            <button
                type="button"
                wire:click="$set('activeTab', 'payables')"
                class="lj-arap-tab-btn {{ $activeTab === 'payables' ? 'active' : '' }}">
                <span>🏢</span>
                <span>Accounts Payable (Supplier Bills)</span>
                <span class="lj-arap-count-badge">{{ $ap['count'] }}</span>
            </button>
        </div>

        <!-- TAB: RECEIVABLES -->
        @if($activeTab === 'receivables')
        <!-- AR 5-Bracket Aging KPI Cards -->
        <div class="lj-arap-aging-grid">
            <div class="lj-arap-aging-card">
                <span class="lj-arap-card-label">Total Receivables</span>
                <div class="lj-arap-card-num">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($ar['total'], 'Rs. ', 2) }}</div>
            </div>
            <div class="lj-arap-aging-card">
                <span class="lj-arap-card-label current">Current (0–30 Days)</span>
                <div class="lj-arap-card-num current">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($ar['current'], 'Rs. ', 2) }}</div>
            </div>
            <div class="lj-arap-aging-card">
                <span class="lj-arap-card-label d30">31–60 Days</span>
                <div class="lj-arap-card-num d30">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($ar['days_30'], 'Rs. ', 2) }}</div>
            </div>
            <div class="lj-arap-aging-card">
                <span class="lj-arap-card-label d60">61–90 Days</span>
                <div class="lj-arap-card-num d60">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($ar['days_60'], 'Rs. ', 2) }}</div>
            </div>
            <div class="lj-arap-aging-card">
                <span class="lj-arap-card-label d90">90+ Days Overdue</span>
                <div class="lj-arap-card-num d90">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($ar['days_90_plus'], 'Rs. ', 2) }}</div>
            </div>
        </div>

        <!-- AR Table -->
        <div class="lj-arap-table-wrap">
            <div class="lj-arap-table-header">
                <div>
                    <h3 class="lj-arap-table-title">Unsettled Customer Invoices</h3>
                    <span class="lj-arap-table-sub">Bikri Khata Accounts Receivable Sub-Ledger</span>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="lj-arap-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer Name</th>
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                            <th style="text-align: right;">Total (Rs.)</th>
                            <th style="text-align: right;">Balance Due (Rs.)</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ar['records'] as $rec)
                        <tr>
                            <td style="font-family: ui-monospace, monospace; font-weight: 800; color: #0A2E23;">{{ $rec->invoice_number }}</td>
                            <td style="font-weight: 700;">{{ $rec->contact_name ?: 'Storefront Customer' }}</td>
                            <td style="color: #64748b; font-family: ui-monospace, monospace;">{{ $rec->issue_date?->format('Y-m-d') }}</td>
                            <td style="color: #64748b; font-family: ui-monospace, monospace;">{{ $rec->due_date?->format('Y-m-d') ?: 'Immediate' }}</td>
                            <td style="text-align: right; font-family: ui-monospace, monospace; font-weight: 600;">{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float)$rec->total_amount, 'Rs. ', 2) }}</td>
                            <td style="text-align: right; font-family: ui-monospace, monospace; font-weight: 800; color: #dc2626;">{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float)($rec->total_amount - $rec->paid_amount), 'Rs. ', 2) }}</td>
                            <td style="text-align: center;">
                                <button
                                    type="button"
                                    wire:click="markInvoicePaid({{ $rec->id }})"
                                    wire:confirm="Confirm customer payment receipt and post bank voucher to GL?"
                                    class="lj-arap-btn-settle">
                                    <span>✓</span>
                                    <span>Settle Receipt</span>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7">
                                <div class="lj-arap-empty">
                                    <div class="lj-arap-empty-icon">🎉</div>
                                    <div class="lj-arap-empty-title">All Customer Invoices Settled</div>
                                    <p style="margin: 0; font-size: 0.8125rem;">There are no outstanding accounts receivable. Customer ledger accounts are in balance.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- TAB: PAYABLES -->
        @if($activeTab === 'payables')
        <!-- AP 5-Bracket Aging KPI Cards -->
        <div class="lj-arap-aging-grid">
            <div class="lj-arap-aging-card">
                <span class="lj-arap-card-label">Total Payables</span>
                <div class="lj-arap-card-num">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($ap['total'], 'Rs. ', 2) }}</div>
            </div>
            <div class="lj-arap-aging-card">
                <span class="lj-arap-card-label current">Current (0–30 Days)</span>
                <div class="lj-arap-card-num current">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($ap['current'], 'Rs. ', 2) }}</div>
            </div>
            <div class="lj-arap-aging-card">
                <span class="lj-arap-card-label d30">31–60 Days</span>
                <div class="lj-arap-card-num d30">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($ap['days_30'], 'Rs. ', 2) }}</div>
            </div>
            <div class="lj-arap-aging-card">
                <span class="lj-arap-card-label d60">61–90 Days</span>
                <div class="lj-arap-card-num d60">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($ap['days_60'], 'Rs. ', 2) }}</div>
            </div>
            <div class="lj-arap-aging-card">
                <span class="lj-arap-card-label d90">90+ Days Overdue</span>
                <div class="lj-arap-card-num d90">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($ap['days_90_plus'], 'Rs. ', 2) }}</div>
            </div>
        </div>

        <!-- AP Table -->
        <div class="lj-arap-table-wrap">
            <div class="lj-arap-table-header">
                <div>
                    <h3 class="lj-arap-table-title">Unsettled Supplier Bills (Annex 8)</h3>
                    <span class="lj-arap-table-sub">Kharid Khata Accounts Payable Sub-Ledger</span>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="lj-arap-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Supplier / Artisan</th>
                            <th>Bill Date</th>
                            <th>Due Date</th>
                            <th style="text-align: right;">Gross (Rs.)</th>
                            <th style="text-align: right;">Balance Payable (Rs.)</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ap['records'] as $bill)
                        <tr>
                            <td style="font-family: ui-monospace, monospace; font-weight: 800; color: #1e40af;">{{ $bill->invoice_number }}</td>
                            <td style="font-weight: 700;">{{ $bill->contact_name }}</td>
                            <td style="color: #64748b; font-family: ui-monospace, monospace;">{{ $bill->issue_date?->format('Y-m-d') }}</td>
                            <td style="color: #64748b; font-family: ui-monospace, monospace;">{{ $bill->due_date?->format('Y-m-d') ?: 'Net 30' }}</td>
                            <td style="text-align: right; font-family: ui-monospace, monospace; font-weight: 600;">{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float)$bill->total_amount, 'Rs. ', 2) }}</td>
                            <td style="text-align: right; font-family: ui-monospace, monospace; font-weight: 800; color: #dc2626;">{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float)($bill->total_amount - $bill->paid_amount), 'Rs. ', 2) }}</td>
                            <td style="text-align: center;">
                                <button
                                    type="button"
                                    wire:click="markBillPaid({{ $bill->id }})"
                                    wire:confirm="Disburse supplier payment and post bank withdrawal voucher to GL?"
                                    class="lj-arap-btn-settle">
                                    <span>💳</span>
                                    <span>Disburse Payment</span>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7">
                                <div class="lj-arap-empty">
                                    <div class="lj-arap-empty-icon">✓</div>
                                    <div class="lj-arap-empty-title">All Supplier Bills Disbursed</div>
                                    <p style="margin: 0; font-size: 0.8125rem;">No outstanding supplier liabilities in Kharid Khata. Accounts payable are up to date.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</x-filament-panels::page>