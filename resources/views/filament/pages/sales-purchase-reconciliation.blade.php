<x-filament-panels::page>
    @php
    $data = $this->getReconciliationData();
    $metrics = $data['metrics'];
    $unpostedOrders = $data['unposted_orders'];
    $unpostedPos = $data['unposted_pos'];
    $unpostedPoBills = $data['unposted_purchase_orders'];
    @endphp
    <div class="lj-spr-root">
        <style>
            .lj-spr-root {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica NNpe", Arial, sans-serif;
                color: #0f172a;
                display: flex;
                flex-direction: column;
                gap: 1.5rem;
                padding-bottom: 2.5rem;
            }

            .dark .lj-spr-root {
                color: #f8fafc;
            }

            /* Header Hero Banner (Clean Minimalist Light Mode) */
            .lj-spr-hero {
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

            .dark .lj-spr-hero {
                background: #0f172a;
                border-color: #1e293b;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.25);
                color: #f8fafc;
            }

            .lj-spr-hero-badge {
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

            .dark .lj-spr-hero-badge {
                background: #1e293b;
                color: #cbd5e1;
                border-color: #334155;
            }

            .lj-spr-hero-title {
                font-size: 1.35rem;
                font-weight: 700;
                letter-spacing: -0.02em;
                margin: 0;
                color: #0f172a;
            }

            .dark .lj-spr-hero-title {
                color: #f8fafc;
            }

            .lj-spr-hero-sub {
                font-size: 0.8125rem;
                color: #64748b;
                margin-top: 0.35rem;
                max-width: 48rem;
                line-height: 1.45;
            }

            .dark .lj-spr-hero-sub {
                color: #94a3b8;
            }

            .lj-spr-hero-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.5rem 1rem;
                border-radius: 0.5rem;
                font-size: 0.75rem;
                font-weight: 600;
                background: #0f172a;
                color: #ffffff;
                border: none;
                cursor: pointer;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
                transition: all 0.15s ease;
            }

            .dark .lj-spr-hero-btn {
                background: #38bdf8;
                color: #0f172a;
            }

            .lj-spr-hero-btn:hover {
                background: #1e293b;
                transform: translateY(-1px);
            }

            /* Tab Navigation Bar */
            .lj-spr-tabs {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                overflow-x: auto;
                padding-bottom: 0.5rem;
                border-bottom: 1px solid #e2e8f0;
                scrollbar-width: none;
            }

            .dark .lj-spr-tabs {
                border-color: #1e293b;
            }

            .lj-spr-tab {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.625rem 1.125rem;
                border-radius: 0.75rem;
                font-size: 0.8125rem;
                font-weight: 700;
                background: #ffffff;
                color: #475569;
                border: 1px solid #e2e8f0;
                cursor: pointer;
                transition: all 0.15s ease;
                white-space: nowrap;
            }

            .dark .lj-spr-tab {
                background: #1e293b;
                color: #94a3b8;
                border-color: #334155;
            }

            .lj-spr-tab:hover {
                background: #f1f5f9;
                color: #0f172a;
            }

            .dark .lj-spr-tab:hover {
                background: #334155;
                color: #f8fafc;
            }

            .lj-spr-tab.active {
                background: #0A2E23;
                color: #ffffff;
                border-color: #0A2E23;
                box-shadow: 0 2px 8px rgba(10, 46, 35, 0.25);
            }

            .dark .lj-spr-tab.active {
                background: #38bdf8;
                color: #0f172a;
                border-color: #38bdf8;
            }

            .lj-spr-tab-badge {
                border-radius: 9999px;
                padding: 0.1rem 0.45rem;
                font-size: 0.6875rem;
                font-weight: 800;
            }

            .lj-spr-badge-amber {
                background: #fef3c7;
                color: #92400e;
            }

            .lj-spr-badge-emerald {
                background: #d1fae5;
                color: #065f46;
            }

            .lj-spr-badge-rose {
                background: #fee2e2;
                color: #991b1b;
            }

            /* Metric Cards Grid */
            .lj-spr-grid-3 {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
            }

            @media (max-width: 960px) {
                .lj-spr-grid-3 {
                    grid-template-columns: 1fr;
                }
            }

            .lj-spr-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 0.875rem;
                padding: 1.25rem 1.5rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                transition: all 0.2s ease;
            }

            .dark .lj-spr-card {
                background: #1e293b;
                border-color: #334155;
            }

            .lj-spr-card:hover {
                border-color: #C5A059;
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
            }

            .lj-spr-card-label {
                font-size: 0.6875rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #64748b;
            }

            .dark .lj-spr-card-label {
                color: #94a3b8;
            }

            .lj-spr-card-num {
                font-size: 1.75rem;
                font-weight: 800;
                color: #0f172a;
                margin-top: 0.35rem;
                letter-spacing: -0.02em;
            }

            .dark .lj-spr-card-num {
                color: #f8fafc;
            }

            .lj-spr-card-num.amber {
                color: #d97706;
            }

            .lj-spr-card-num.emerald {
                color: #059669;
            }

            .lj-spr-card-num.rose {
                color: #dc2626;
            }

            .lj-spr-card-num.blue {
                color: #2563eb;
            }

            .lj-spr-card-num.indigo {
                color: #4f46e5;
            }

            .lj-spr-card-sub {
                font-size: 0.75rem;
                color: #64748b;
                margin-top: 0.5rem;
            }

            .dark .lj-spr-card-sub {
                color: #94a3b8;
            }

            /* Unposted Callout Banner */
            .lj-spr-callout {
                background: #fffbeb;
                border: 1px solid #fde68a;
                border-radius: 0.875rem;
                padding: 1.25rem 1.5rem;
            }

            .dark .lj-spr-callout {
                background: #272115;
                border-color: #574618;
            }

            .lj-spr-callout-title {
                font-size: 0.875rem;
                font-weight: 800;
                color: #92400e;
                margin: 0 0 0.5rem 0;
            }

            .dark .lj-spr-callout-title {
                color: #fbbf24;
            }

            .lj-spr-pill-list {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .lj-spr-pill {
                display: inline-flex;
                align-items: center;
                gap: 0.375rem;
                padding: 0.3rem 0.65rem;
                border-radius: 0.5rem;
                font-size: 0.75rem;
                font-family: ui-monospace, monospace;
                font-weight: 700;
                background: #ffffff;
                color: #78350f;
                border: 1px solid #fcd34d;
            }

            .dark .lj-spr-pill {
                background: #1e1b15;
                color: #fde68a;
                border-color: #78350f;
            }

            /* Table Styles */
            .lj-spr-table-wrap {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 0.875rem;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            }

            .dark .lj-spr-table-wrap {
                background: #1e293b;
                border-color: #334155;
            }

            .lj-spr-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.8125rem;
                text-align: left;
            }

            .lj-spr-table th {
                background: #f8fafc;
                color: #475569;
                font-size: 0.6875rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                padding: 0.875rem 1.25rem;
                border-bottom: 1px solid #e2e8f0;
            }

            .dark .lj-spr-table th {
                background: #0f172a;
                color: #94a3b8;
                border-color: #334155;
            }

            .lj-spr-table td {
                padding: 1rem 1.25rem;
                border-bottom: 1px solid #f1f5f9;
                color: #1e293b;
            }

            .dark .lj-spr-table td {
                border-color: #334155;
                color: #e2e8f0;
            }

            .lj-spr-table tr:last-child td {
                border-bottom: none;
            }

            .lj-spr-table tr:hover td {
                background: #f8fafc;
            }

            .dark .lj-spr-table tr:hover td {
                background: #1e293b;
            }
        </style>

        <!-- Hero Header -->
        <div class="lj-spr-hero">
            <div>
                <div class="lj-spr-hero-badge">
                    <span>🏛️</span>
                    <span>Nepal Statutory Audit & Cross-Reconciliation (NAS)</span>
                </div>
                <h1 class="lj-spr-hero-title">General Ledger Cross-Reconciliation Center</h1>
                <p class="lj-spr-hero-sub">
                    Real-time operational audit cross-checking commerce orders, showroom POS, procurement, stock valuation, and courier remittances against the General Ledger.
                </p>
            </div>
            <div>
                <button type="button" wire:click="syncAllUnposted" class="lj-spr-hero-btn">
                    <span>⚡</span>
                    <span>Post All Unposted to GL</span>
                </button>
            </div>
        </div>

        <!-- 6-Tab Navigation Bar -->
        <div class="lj-spr-tabs">
            <button
                type="button"
                wire:click="setTab('bikri_gl')"
                class="lj-spr-tab {{ $activeTab === 'bikri_gl' ? 'active' : '' }}">
                <span>📕</span>
                <span>Bikri ↔ GL</span>
                @if($metrics['orders']['unposted'] > 0)
                <span class="lj-spr-tab-badge lj-spr-badge-amber">{{ $metrics['orders']['unposted'] }}</span>
                @endif
            </button>

            <button
                type="button"
                wire:click="setTab('kharid_gl')"
                class="lj-spr-tab {{ $activeTab === 'kharid_gl' ? 'active' : '' }}">
                <span>📗</span>
                <span>Kharid ↔ GL</span>
                <span class="lj-spr-tab-badge lj-spr-badge-emerald">{{ $metrics['purchases']['supplier_bills'] }}</span>
            </button>

            <button
                type="button"
                wire:click="setTab('inventory_gl')"
                class="lj-spr-tab {{ $activeTab === 'inventory_gl' ? 'active' : '' }}">
                <span>📦</span>
                <span>Inventory ↔ GL (1210)</span>
                @if(abs($metrics['inventory']['variance'] ?? 0) > 1)
                <span class="lj-spr-tab-badge lj-spr-badge-rose">Δ Var</span>
                @else
                <span class="lj-spr-tab-badge lj-spr-badge-emerald">✓</span>
                @endif
            </button>

            <button
                type="button"
                wire:click="setTab('pos_gl')"
                class="lj-spr-tab {{ $activeTab === 'pos_gl' ? 'active' : '' }}">
                <span>🏪</span>
                <span>POS ↔ GL</span>
                @if($metrics['pos']['unposted'] > 0)
                <span class="lj-spr-tab-badge lj-spr-badge-amber">{{ $metrics['pos']['unposted'] }}</span>
                @endif
            </button>

            <button
                type="button"
                wire:click="setTab('cod_bank')"
                class="lj-spr-tab {{ $activeTab === 'cod_bank' ? 'active' : '' }}">
                <span>🚚</span>
                <span>COD ↔ Bank (1150)</span>
                @if(($metrics['cod']['unsettled_orders_count'] ?? 0) > 0)
                <span class="lj-spr-tab-badge lj-spr-badge-amber">{{ $metrics['cod']['unsettled_orders_count'] }}</span>
                @endif
            </button>

            <button
                type="button"
                wire:click="setTab('bank_reconciliation')"
                class="lj-spr-tab {{ $activeTab === 'bank_reconciliation' ? 'active' : '' }}">
                <span>🏦</span>
                <span>Bank Accounts</span>
            </button>
        </div>

        <!-- TAB 1: Bikri ↔ GL -->
        @if($activeTab === 'bikri_gl')
        <div class="lj-spr-grid-3">
            <div class="lj-spr-card">
                <span class="lj-spr-card-label">Active Storefront Orders</span>
                <div class="lj-spr-card-num">{{ number_format($metrics['orders']['total']) }}</div>
                <div class="lj-spr-card-sub" style="color: #059669; font-weight: 600;">✓ {{ $metrics['orders']['posted_invoices'] }} Posted to Bikri Khata</div>
            </div>

            <div class="lj-spr-card">
                <span class="lj-spr-card-label">Unposted Invoices</span>
                <div class="lj-spr-card-num {{ $metrics['orders']['unposted'] > 0 ? 'amber' : 'emerald' }}">
                    {{ number_format($metrics['orders']['unposted']) }}
                </div>
                <div class="lj-spr-card-sub">{{ $metrics['orders']['unposted'] > 0 ? 'Pending double-entry posting' : '100% Synchronized (' . number_format($metrics['orders']['cancelled'] ?? 0) . ' cancelled excluded)' }}</div>
            </div>

            <div class="lj-spr-card">
                <span class="lj-spr-card-label">Credit Notes / Returns</span>
                <div class="lj-spr-card-num rose">{{ number_format($metrics['credit_notes']) }}</div>
                <div class="lj-spr-card-sub">Reversed Output VAT & Revenue</div>
            </div>
        </div>

        @if($unpostedOrders->isNotEmpty())
        <div class="lj-spr-callout">
            <h3 class="lj-spr-callout-title">Unposted Web Storefront Orders (Click 'Post All' above to synchronize)</h3>
            <div class="lj-spr-pill-list">
                @foreach($unpostedOrders as $uo)
                <span class="lj-spr-pill">
                    #{{ $uo->order_number }} — Rs. {{ number_format((float)$uo->total_amount, 2) }}
                </span>
                @endforeach
            </div>
        </div>
        @endif
        @endif

        <!-- TAB 2: Kharid ↔ GL -->
        @if($activeTab === 'kharid_gl')
        <div class="lj-spr-grid-3">
            <div class="lj-spr-card">
                <span class="lj-spr-card-label">Supplier Bills (Annex 8)</span>
                <div class="lj-spr-card-num blue">{{ number_format($metrics['purchases']['supplier_bills']) }}</div>
                <div class="lj-spr-card-sub">Input VAT Credit Tracked</div>
            </div>

            <div class="lj-spr-card">
                <span class="lj-spr-card-label">Debit Notes (Purchase Returns)</span>
                <div class="lj-spr-card-num rose">{{ number_format($metrics['purchases']['debit_notes']) }}</div>
                <div class="lj-spr-card-sub">Reversed Input VAT Credit</div>
            </div>

            <div class="lj-spr-card">
                <span class="lj-spr-card-label">Unposted PO Bills</span>
                <div class="lj-spr-card-num emerald">{{ count($unpostedPoBills) }}</div>
                <div class="lj-spr-card-sub">Ready for bill creation</div>
            </div>
        </div>
        @endif

        <!-- TAB 3: Inventory ↔ GL (1210) -->
        @if($activeTab === 'inventory_gl')
        <div class="lj-spr-grid-3">
            <div class="lj-spr-card">
                <span class="lj-spr-card-label">Physical Inventory Valuation</span>
                <div class="lj-spr-card-num">
                    Rs. {{ number_format($metrics['inventory']['physical_valuation'] ?? 0, 2) }}
                </div>
                <div class="lj-spr-card-sub">Aggregated from variant cost prices and physical stock levels</div>
            </div>

            <div class="lj-spr-card">
                <span class="lj-spr-card-label">GL Inventory Balance (Account 1210)</span>
                <div class="lj-spr-card-num indigo">
                    Rs. {{ number_format($metrics['inventory']['gl_balance'] ?? 0, 2) }}
                </div>
                <div class="lj-spr-card-sub">Live balance from posted general ledger journals</div>
            </div>

            <div class="lj-spr-card">
                <span class="lj-spr-card-label">Audit Variance</span>
                <div class="lj-spr-card-num {{ abs($metrics['inventory']['variance'] ?? 0) > 1 ? 'amber' : 'emerald' }}">
                    Rs. {{ number_format($metrics['inventory']['variance'] ?? 0, 2) }}
                </div>
                <div class="lj-spr-card-sub">
                    {{ abs($metrics['inventory']['variance'] ?? 0) > 1 ? 'Variance to reconcile via stock count adjustment' : 'Perfect Inventory Alignment' }}
                </div>
            </div>
        </div>
        @endif

        <!-- TAB 4: POS ↔ GL -->
        @if($activeTab === 'pos_gl')
        <div class="lj-spr-grid-3">
            <div class="lj-spr-card">
                <span class="lj-spr-card-label">Showroom POS Sales</span>
                <div class="lj-spr-card-num">{{ number_format($metrics['pos']['total']) }}</div>
                <div class="lj-spr-card-sub" style="color: #059669; font-weight: 600;">✓ {{ $metrics['pos']['posted_invoices'] }} Posted to Bikri Khata</div>
            </div>

            <div class="lj-spr-card">
                <span class="lj-spr-card-label">Unposted POS Transactions</span>
                <div class="lj-spr-card-num {{ $metrics['pos']['unposted'] > 0 ? 'amber' : 'emerald' }}">
                    {{ number_format($metrics['pos']['unposted']) }}
                </div>
                <div class="lj-spr-card-sub">{{ $metrics['pos']['unposted'] > 0 ? 'Pending double-entry posting' : '100% Reconciled' }}</div>
            </div>
        </div>
        @endif

        <!-- TAB 5: COD ↔ Bank -->
        @if($activeTab === 'cod_bank')
        <div class="lj-spr-grid-3">
            <div class="lj-spr-card">
                <span class="lj-spr-card-label">Delivered COD Orders Pending Remittance</span>
                <div class="lj-spr-card-num amber">{{ $metrics['cod']['unsettled_orders_count'] ?? 0 }}</div>
                <div class="lj-spr-card-sub">Rs. {{ number_format($metrics['cod']['unsettled_amount'] ?? 0, 2) }} pending payout</div>
            </div>

            <div class="lj-spr-card">
                <span class="lj-spr-card-label">GL COD Clearing Balance (Account 1150)</span>
                <div class="lj-spr-card-num indigo">Rs. {{ number_format($metrics['cod']['gl_clearing_balance'] ?? 0, 2) }}</div>
                <div class="lj-spr-card-sub">Cleared upon courier bank remittance</div>
            </div>
        </div>
        @endif

        <!-- TAB 6: Bank Reconciliation -->
        @if($activeTab === 'bank_reconciliation')
        <div class="lj-spr-table-wrap">
            <table class="lj-spr-table">
                <thead>
                    <tr>
                        <th>Bank Account</th>
                        <th>Account Number</th>
                        <th>GL Account</th>
                        <th style="text-align: right;">Current Ledger Balance (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($metrics['bank'] ?? [] as $b)
                    <tr>
                        <td style="font-weight: 700;">{{ $b['name'] }}</td>
                        <td style="color: #64748b; font-family: ui-monospace, monospace;">{{ $b['account_number'] }}</td>
                        <td style="color: #0A2E23; font-weight: 700; font-family: ui-monospace, monospace;">{{ $b['gl_account'] ?? '1120' }}</td>
                        <td style="text-align: right; font-weight: 800; font-family: ui-monospace, monospace;">Rs. {{ number_format((float)$b['current_balance'], 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: #64748b; padding: 2rem;">No bank accounts registered.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>
</x-filament-panels::page>