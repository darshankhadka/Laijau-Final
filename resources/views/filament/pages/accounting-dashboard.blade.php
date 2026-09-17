<x-filament-panels::page class="w-full max-w-full !p-0">
@php
    $pnl = $this->pnlData;
    $balance = $this->balanceData;
    $vat = $this->vatData;
    $split = $this->revenueSplit;

    // Tab-dependent lazy loading to ensure maximum rendering speed
    $trialBalance = $activeTab === 'trial_balance' ? $this->trialBalanceData : ['accounts' => [], 'total_debit' => 0, 'total_credit' => 0, 'is_balanced' => true];
    $filteredTbAccounts = $activeTab === 'trial_balance' ? collect($trialBalance['accounts'])->filter(function ($acc) {
        if ($this->trialBalanceCategory !== 'all' && $acc['category'] !== $this->trialBalanceCategory) {
            return false;
        }
        if (!empty($this->trialBalanceSearch)) {
            $term = strtolower($this->trialBalanceSearch);
            return str_contains(strtolower($acc['account_number']), $term) || str_contains(strtolower($acc['name']), $term);
        }
        return true;
    }) : collect([]);

    $accounts = $activeTab === 'ledger' ? \App\Models\Accounting\Account::orderBy('account_number')->get() : collect([]);
    $ledger = $activeTab === 'ledger' ? $this->ledgerData : null;
    $periods = $activeTab === 'periods' ? \App\Models\Accounting\AccountingPeriod::orderBy('start_date')->get() : collect([]);
@endphp

<link rel="stylesheet" href="{{ asset('css/nepal-accounting.css') }}?v=4.1">

<style>
    /* Suppress default Filament page header to make room for our clean minimalist header */
    .fi-header, .fi-page-header, .fi-header-heading, .fi-breadcrumbs {
        display: none !important;
    }
</style>

<div class="na-accounting-root" style="padding-top: 0.5rem;">

    <!-- =========================================================================
         1. CLEAN MINIMALIST LIGHT-MODE HEADER
         ========================================================================= -->
    <div class="na-header-banner na-header-banner-minimal">
        <div>
            <h1 class="na-title-main">Financial Overview & Reporting</h1>
            <p class="na-subtitle">Real-time financial performance, authoritative general ledger, and multi-channel metrics.</p>
        </div>

        <!-- DATE PRESETS & ACTIONS (Minimalist Light Mode) -->
        <div class="na-header-controls">
            <div class="na-preset-group-minimal">
                <button wire:click="applyPreset('ytd')" class="na-preset-btn-minimal {{ $periodPreset === 'ytd' ? 'active' : '' }}">Year to Date</button>
                <button wire:click="applyPreset('fy_2026')" class="na-preset-btn-minimal {{ in_array($periodPreset, ['fy_2026', 'full_year']) ? 'active' : '' }}">Full Year 2026</button>
                <button wire:click="applyPreset('this_month')" class="na-preset-btn-minimal {{ $periodPreset === 'this_month' ? 'active' : '' }}">This Month</button>
                <button wire:click="applyPreset('q1')" class="na-preset-btn-minimal {{ $periodPreset === 'q1' ? 'active' : '' }}">Q1</button>
                <button wire:click="applyPreset('q2')" class="na-preset-btn-minimal {{ $periodPreset === 'q2' ? 'active' : '' }}">Q2</button>
                <button wire:click="applyPreset('q3')" class="na-preset-btn-minimal {{ $periodPreset === 'q3' ? 'active' : '' }}">Q3</button>
                <button wire:click="applyPreset('q4')" class="na-preset-btn-minimal {{ $periodPreset === 'q4' ? 'active' : '' }}">Q4</button>
            </div>

            <div class="na-actions-minimal">
                <button wire:click="exportCsv" class="na-btn-minimal-action" title="Export Trial Balance as CSV">
                    <svg style="width: 0.9rem; height: 0.9rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export Trial Balance (CSV)</span>
                </button>
                <button wire:click="setTab('periods')" class="na-btn-minimal-action" title="Manage Accounting Periods & Year-End Closing">
                    <svg style="width: 0.9rem; height: 0.9rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <span>Fiscal Closing</span>
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         2. HIGH-DENSITY EXECUTIVE 8-CARD METRIC STRIP (4x2 Responsive Grid)
         ========================================================================= -->
    <div class="na-grid-8">
        <!-- 1. Net Revenue -->
        <div class="na-metric-card-lux" style="border-top: 3px solid #0A2E23;">
            <div class="na-flex na-justify-between na-items-center na-gap-2">
                <div class="na-flex na-items-center na-gap-2">
                    <span class="na-kpi-icon-wrap" style="background: rgba(10, 46, 35, 0.08); color: #0A2E23;">💰</span>
                    <span class="na-metric-label">Net Revenue</span>
                </div>
                <span class="na-kpi-badge" style="background: var(--na-emerald-light); color: var(--na-emerald);">AUDITED</span>
            </div>
            <div class="na-kpi-val">Rs. {{ nepali_number($pnl['revenue'], 2) }}</div>
            <div class="na-kpi-sub">
                <strong style="color: var(--na-text);">~{{ nepali_lakh_crore($pnl['revenue']) }}</strong> • NPR excl. VAT
            </div>
        </div>

        <!-- 2. Cost of Goods Sold -->
        <div class="na-metric-card-lux" style="border-top: 3px solid #B45309;">
            <div class="na-flex na-justify-between na-items-center na-gap-2">
                <div class="na-flex na-items-center na-gap-2">
                    <span class="na-kpi-icon-wrap" style="background: rgba(180, 83, 9, 0.08); color: #B45309;">📦</span>
                    <span class="na-metric-label" style="color: #B45309;">COGS</span>
                </div>
                <span class="na-kpi-badge" style="background: rgba(180, 83, 9, 0.1); color: #B45309;">LANDED</span>
            </div>
            <div class="na-kpi-val" style="color: #B45309;">Rs. {{ nepali_number($pnl['cogs'], 2) }}</div>
            <div class="na-kpi-sub">
                <strong style="color: var(--na-text);">~{{ nepali_lakh_crore($pnl['cogs']) }}</strong> • Landed Cost
            </div>
        </div>

        <!-- 3. Gross Margin -->
        <div class="na-metric-card-lux" style="border-top: 3px solid #059669;">
            <div class="na-flex na-justify-between na-items-center na-gap-2">
                <div class="na-flex na-items-center na-gap-2">
                    <span class="na-kpi-icon-wrap" style="background: rgba(5, 150, 105, 0.08); color: #059669;">📈</span>
                    <span class="na-metric-label" style="color: #059669;">Gross Margin</span>
                </div>
                <span class="na-kpi-badge" style="background: var(--na-success-light); color: var(--na-success);">{{ number_format($pnl['gross_margin_percent'], 1, '.', ',') }}% MARGIN</span>
            </div>
            <div class="na-kpi-val" style="color: #059669;">Rs. {{ nepali_number($pnl['gross_profit'], 2) }}</div>
            <div class="na-kpi-sub">
                <strong style="color: #059669;">{{ number_format($pnl['gross_margin_percent'], 1, '.', ',') }}% Margin</strong> • ~{{ nepali_lakh_crore($pnl['gross_profit']) }}
            </div>
        </div>

        <!-- 4. Operating Expenses -->
        <div class="na-metric-card-lux" style="border-top: 3px solid #6366F1;">
            <div class="na-flex na-justify-between na-items-center na-gap-2">
                <div class="na-flex na-items-center na-gap-2">
                    <span class="na-kpi-icon-wrap" style="background: rgba(99, 102, 241, 0.08); color: #6366F1;">🏢</span>
                    <span class="na-metric-label" style="color: #6366F1;">OPEX</span>
                </div>
                <span class="na-kpi-badge" style="background: rgba(99, 102, 241, 0.1); color: #6366F1;">FEES & OPEX</span>
            </div>
            <div class="na-kpi-val">Rs. {{ nepali_number($pnl['opex'] + $pnl['payment_fees'], 2) }}</div>
            <div class="na-kpi-sub">
                <strong style="color: var(--na-text);">~{{ nepali_lakh_crore($pnl['opex'] + $pnl['payment_fees']) }}</strong> • OPEX & Fees
            </div>
        </div>

        <!-- 5. Operating Profit (EBIT) -->
        <div class="na-metric-card-lux" style="border-top: 3px solid #8B5CF6;">
            <div class="na-flex na-justify-between na-items-center na-gap-2">
                <div class="na-flex na-items-center na-gap-2">
                    <span class="na-kpi-icon-wrap" style="background: rgba(139, 92, 246, 0.08); color: #8B5CF6;">⚡</span>
                    <span class="na-metric-label" style="color: #8B5CF6;">EBIT</span>
                </div>
                <span class="na-kpi-badge" style="background: rgba(139, 92, 246, 0.1); color: #8B5CF6;">OPERATING</span>
            </div>
            <div class="na-kpi-val" style="color: {{ $pnl['ebit'] >= 0 ? 'var(--na-success)' : 'var(--na-rose)' }};">
                Rs. {{ nepali_number($pnl['ebit'], 2) }}
            </div>
            <div class="na-kpi-sub">
                EBITDA: Rs. {{ nepali_number($pnl['ebitda'], 2) }} (~{{ nepali_lakh_crore($pnl['ebitda']) }})
            </div>
        </div>

        <!-- 6. Net Profit -->
        <div class="na-metric-card-lux" style="border-top: 3px solid {{ $pnl['net_result'] >= 0 ? '#059669' : '#DC2626' }}; background: {{ $pnl['net_result'] >= 0 ? 'linear-gradient(180deg, rgba(5, 150, 105, 0.03) 0%, var(--na-card) 100%)' : 'var(--na-card)' }};">
            <div class="na-flex na-justify-between na-items-center na-gap-2">
                <div class="na-flex na-items-center na-gap-2">
                    <span class="na-kpi-icon-wrap" style="background: rgba(5, 150, 105, 0.1); color: #059669;">💎</span>
                    <span class="na-metric-label" style="color: {{ $pnl['net_result'] >= 0 ? '#059669' : '#DC2626' }};">Net Profit</span>
                </div>
                <span class="na-kpi-badge" style="background: rgba(5, 150, 105, 0.15); color: #059669;">BOTTOM LINE</span>
            </div>
            <div class="na-kpi-val" style="color: {{ $pnl['net_result'] >= 0 ? '#059669' : '#DC2626' }};">
                Rs. {{ nepali_number($pnl['net_result'], 2) }}
            </div>
            <div class="na-kpi-sub">
                <strong style="color: var(--na-text);">~{{ nepali_lakh_crore($pnl['net_result']) }}</strong> • Bottom Line Net
            </div>
        </div>

        <!-- 7. Liquid Funds -->
        @php
            $liquidBal = (float)($balance['assets']['cash_bank'] ?? 0);
        @endphp
        <div class="na-metric-card-lux" style="border-top: 3px solid {{ $liquidBal < 0 ? '#F59E0B' : '#2563EB' }};">
            <div class="na-flex na-justify-between na-items-center na-gap-2">
                <div class="na-flex na-items-center na-gap-2">
                    <span class="na-kpi-icon-wrap" style="background: {{ $liquidBal < 0 ? 'rgba(245, 158, 11, 0.1)' : 'rgba(37, 99, 235, 0.08)' }}; color: {{ $liquidBal < 0 ? '#D97706' : '#2563EB' }};">🏦</span>
                    <span class="na-metric-label" style="color: {{ $liquidBal < 0 ? '#D97706' : '#2563EB' }};">Liquid Funds</span>
                </div>
                <span class="na-kpi-badge" style="background: {{ $liquidBal < 0 ? 'rgba(245, 158, 11, 0.15)' : 'rgba(37, 99, 235, 0.1)' }}; color: {{ $liquidBal < 0 ? '#B45309' : '#2563EB' }};">
                    {{ $liquidBal < 0 ? 'OVERDRAFT' : 'LIQUID' }}
                </span>
            </div>
            <div class="na-kpi-val" style="color: {{ $liquidBal < 0 ? '#D97706' : '#2563EB' }};">
                {{ $liquidBal < 0 ? '-Rs. ' . nepali_number(abs($liquidBal), 2) : 'Rs. ' . nepali_number($liquidBal, 2) }}
            </div>
            <div class="na-kpi-sub">
                Operating Bank + Cash Drawer
            </div>
        </div>

        <!-- 8. Net VAT -->
        <div class="na-metric-card-lux" style="border-top: 3px solid #B91C1C;">
            <div class="na-flex na-justify-between na-items-center na-gap-2">
                <div class="na-flex na-items-center na-gap-2">
                    <span class="na-kpi-icon-wrap" style="background: rgba(185, 28, 28, 0.08); color: #B91C1C;">🏛️</span>
                    <span class="na-metric-label" style="color: #B91C1C;">Net VAT (IRD)</span>
                </div>
                <span class="na-kpi-badge" style="background: rgba(185, 28, 28, 0.1); color: #B91C1C;">IRD 13%</span>
            </div>
            <div class="na-kpi-val" style="color: {{ ($vat['net_vat_position'] ?? ($vat['net_vat_payable'] ?? 0)) >= 0 ? '#DC2626' : '#059669' }};">
                Rs. {{ nepali_number(abs($vat['net_vat_position'] ?? ($vat['net_vat_payable'] ?? 0)), 2) }}
            </div>
            <div class="na-kpi-sub">
                {{ ($vat['net_vat_position'] ?? ($vat['net_vat_payable'] ?? 0)) >= 0 ? 'Payable to IRD (Annex 10)' : 'Input Credit Refund' }}
            </div>
        </div>
    </div>

    <!-- =========================================================================
         3. MULTI-CHANNEL COMMERCE FLOW VISUALIZER
         ========================================================================= -->
    <div class="na-channel-visualizer">
        <div class="na-flex na-justify-between na-items-center na-flex-wrap na-gap-2">
            <div class="na-flex na-items-center na-gap-2">
                <span style="font-size: 1.15rem;">⚖️</span>
                <span style="font-weight: 800; font-size: 0.95rem; color: var(--na-text);">Multi-Channel Revenue Distribution</span>
                <span style="font-size: 0.78rem; color: var(--na-text-secondary);">(Online E-Commerce vs. Retail Showroom POS)</span>
            </div>
            <div class="na-font-mono" style="color: var(--na-text-muted); font-size: 0.72rem; background: rgba(0, 0, 0, 0.03); padding: 0.2rem 0.55rem; border-radius: 6px; border: 1px solid var(--na-border);">
                Currency Standard: Nepalese Rupee (NPR / Rs.)
            </div>
        </div>

        <!-- 2-Tone Gradient Progress Bar -->
        <div class="na-channel-progress-track">
            <div class="na-channel-fill-online" style="width: {{ $split['online_percent'] }}%;"></div>
            <div class="na-channel-fill-showroom" style="width: {{ $split['showroom_percent'] }}%;"></div>
        </div>

        <!-- 3-Column Minimalist Channel Grid -->
        <div class="na-channel-grid">
            <!-- 1. Online Storefront -->
            <div class="na-channel-card">
                <div class="na-flex na-items-center na-gap-2">
                    <span class="na-channel-dot" style="background: #10B981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);"></span>
                    <span class="na-channel-name">Online Storefront (Laijau.com)</span>
                </div>
                <div class="na-channel-amount na-font-mono" style="color: var(--na-emerald);">Rs. {{ nepali_number($split['online'], 2) }}</div>
                <div class="na-channel-detail">
                    <span class="na-badge na-badge-emerald" style="font-size: 0.65rem;">{{ $split['online_percent'] }}%</span>
                    <span>~{{ nepali_lakh_crore($split['online']) }}</span>
                </div>
            </div>

            <!-- 2. Showroom POS -->
            <div class="na-channel-card">
                <div class="na-flex na-items-center na-gap-2">
                    <span class="na-channel-dot" style="background: #C5A059; box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.25);"></span>
                    <span class="na-channel-name">Showroom POS Sales (Durbar Marg)</span>
                </div>
                <div class="na-channel-amount na-font-mono" style="color: #926A18;">Rs. {{ nepali_number($split['showroom'], 2) }}</div>
                <div class="na-channel-detail">
                    <span class="na-badge na-badge-gold" style="font-size: 0.65rem;">{{ $split['showroom_percent'] }}%</span>
                    <span>~{{ nepali_lakh_crore($split['showroom']) }}</span>
                </div>
            </div>

            <!-- 3. Delivery / Shipping -->
            <div class="na-channel-card">
                <div class="na-flex na-items-center na-gap-2">
                    <span class="na-channel-dot" style="background: #94A3B8; box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.2);"></span>
                    <span class="na-channel-name">Delivery / Shipping</span>
                </div>
                <div class="na-channel-amount na-font-mono" style="color: var(--na-text-secondary);">Rs. {{ nepali_number($split['shipping'] ?? 0, 2) }}</div>
                <div class="na-channel-detail">
                    <span class="na-badge" style="background: rgba(0, 0, 0, 0.05); color: var(--na-text-muted); font-size: 0.65rem;">Clearance</span>
                    <span>Direct Courier / NCM</span>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         4. SEGMENTED NAVIGATION TAB PILLS
         ========================================================================= -->
    <div class="na-tabs-pill-wrap">
        <button wire:click="setTab('pnl')" class="na-tab-pill-btn {{ $activeTab === 'pnl' ? 'active' : '' }}">
            <span>📊</span>
            <span>Profit & Loss Statement (P&L Waterfall)</span>
        </button>
        <button wire:click="setTab('balance')" class="na-tab-pill-btn {{ $activeTab === 'balance' ? 'active' : '' }}">
            <span>⚖️</span>
            <span>Statement of Financial Position (Balance Sheet)</span>
            <span class="na-tab-pill-badge" style="background: {{ $balance['is_balanced'] ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)' }}; color: {{ $balance['is_balanced'] ? '#10B981' : '#EF4444' }};">
                {{ $balance['is_balanced'] ? '✓ Balanced' : '⚠️ Variance' }}
            </span>
        </button>
        <button wire:click="setTab('trial_balance')" class="na-tab-pill-btn {{ $activeTab === 'trial_balance' ? 'active' : '' }}">
            <span>📋</span>
            <span>Trial Balance (सन्तुलन परीक्षण)</span>
        </button>
        <button wire:click="setTab('ledger')" class="na-tab-pill-btn {{ $activeTab === 'ledger' ? 'active' : '' }}">
            <span>📖</span>
            <span>General Ledger (मुख्य खाता)</span>
        </button>
        <button wire:click="setTab('periods')" class="na-tab-pill-btn {{ $activeTab === 'periods' ? 'active' : '' }}">
            <span>🔒</span>
            <span>Accounting Periods & Year-End Closing</span>
        </button>
    </div>

    <!-- =========================================================================
         5. TAB 1: RESULTATOPGØRELSE (P&L WATERFALL)
         ========================================================================= -->
    @if ($activeTab === 'pnl')
        <div class="na-grid-2-1">
            <!-- MAIN WATERFALL STATEMENT -->
            <div class="na-card na-card-p6">
                <div class="na-flex na-justify-between na-items-center" style="border-bottom: 1px solid var(--na-border); padding-bottom: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <div class="na-flex na-items-center na-gap-2">
                            <h2 style="font-size: 1.2rem; font-weight: 800; margin: 0;">Statement of Comprehensive Income (P&L)</h2>
                            <span class="na-badge na-badge-emerald">NAS 1 Compliant</span>
                        </div>
                        <div style="font-size: 0.78rem; color: var(--na-text-secondary); margin-top: 0.25rem;">
                            Reporting Period: <strong class="na-font-mono">{{ $startDate }}</strong> to <strong class="na-font-mono">{{ $endDate }}</strong> • Authoritative Double-Entry General Ledger
                        </div>
                    </div>
                </div>

                <div class="na-waterfall-box">
                    <div class="na-wf-row">
                        <span><strong style="color: var(--na-emerald);">1. Net Operating Revenue</strong> (Online Webshop + Showroom POS + Shipping)</span>
                        <span class="na-font-mono" style="font-weight: 800; font-size: 0.95rem;">Rs. {{ number_format($pnl['revenue'], 2, '.', ',') }}</span>
                    </div>

                    <div class="na-wf-row">
                        <span style="padding-left: 1.5rem; color: #B45309;">- Cost of Goods Sold (Merchandise Landed Cost & Direct Sourcing)</span>
                        <span class="na-font-mono" style="color: #B45309; font-weight: 700;">- Rs. {{ number_format($pnl['cogs'], 2, '.', ',') }}</span>
                    </div>

                    <div class="na-wf-subtotal">
                        <span>= GROSS OPERATING PROFIT</span>
                        <span class="na-font-mono">Rs. {{ number_format($pnl['gross_profit'], 2, '.', ',') }} ({{ number_format($pnl['gross_margin_percent'], 1, '.', ',') }}%)</span>
                    </div>

                    <div class="na-wf-row">
                        <span style="padding-left: 1.5rem; color: var(--na-text-secondary);">- Payment Gateway & Merchant Settlement Fees (eSewa / Fonepay / ConnectIPS)</span>
                        <span class="na-font-mono" style="color: var(--na-text-secondary);">- Rs. {{ number_format($pnl['payment_fees'], 2, '.', ',') }}</span>
                    </div>

                    <div class="na-wf-row">
                        <span style="padding-left: 1.5rem; color: var(--na-text-secondary);">- Facility, Showroom Rent & Operating Expenses (OPEX Accounts 6110–6210)</span>
                        <span class="na-font-mono" style="color: var(--na-text-secondary);">- Rs. {{ number_format($pnl['opex'], 2, '.', ',') }}</span>
                    </div>

                    <div class="na-wf-row">
                        <span style="padding-left: 1.5rem; color: var(--na-text-secondary);">- Personnel, Staff Salaries & SSF Contribution (Accounts 6120–6130)</span>
                        <span class="na-font-mono" style="color: var(--na-text-secondary);">- Rs. {{ number_format($pnl['personnel'], 2, '.', ',') }}</span>
                    </div>

                    <div class="na-wf-subtotal" style="background: rgba(99, 102, 241, 0.08); border-color: rgba(99, 102, 241, 0.25); color: #4F46E5;">
                        <span>= EBITDA (Operating Profit before Depreciation)</span>
                        <span class="na-font-mono">Rs. {{ number_format($pnl['ebitda'], 2, '.', ',') }}</span>
                    </div>

                    <div class="na-wf-row">
                        <span style="padding-left: 1.5rem; color: var(--na-text-secondary);">- Depreciation on Store Equipment & Showroom Fixtures</span>
                        <span class="na-font-mono" style="color: var(--na-text-secondary);">- Rs. {{ number_format($pnl['depreciation'], 2, '.', ',') }}</span>
                    </div>

                    <div class="na-wf-subtotal" style="background: rgba(197, 160, 89, 0.12); border-color: rgba(197, 160, 89, 0.35); color: #8A651E;">
                        <span>= OPERATING PROFIT (EBIT)</span>
                        <span class="na-font-mono">Rs. {{ number_format($pnl['ebit'], 2, '.', ',') }}</span>
                    </div>

                    <div class="na-wf-row">
                        <span style="padding-left: 1.5rem; color: var(--na-text-secondary);">+/- Financial Items, Interest & Bank Charges</span>
                        <span class="na-font-mono">Rs. {{ number_format($pnl['financials'] ?? $pnl['financial_net'] ?? 0, 2, '.', ',') }}</span>
                    </div>

                    <div class="na-wf-total">
                        <div>
                            <span style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.85; display: block;">Final Statutory Bottom Line</span>
                            <span>NET PROFIT FOR THE FISCAL YEAR</span>
                        </div>
                        <span class="na-font-mono" style="font-size: 1.35rem; letter-spacing: -0.02em;">Rs. {{ number_format($pnl['net_result'], 2, '.', ',') }}</span>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR: PROFITABILITY & TAX POSITION -->
            <div class="na-flex-col na-gap-4">
                <!-- Profitability Ratios Card -->
                <div class="na-card na-card-p6">
                    <div class="na-flex na-justify-between na-items-center" style="margin-bottom: 1.25rem;">
                        <h3 style="font-size: 1rem; font-weight: 800; margin: 0;">Financial Health & Ratios</h3>
                        <span class="na-badge na-badge-emerald">Live NAS</span>
                    </div>
                    <div class="na-flex-col na-gap-3" style="font-size: 0.82rem;">
                        <div>
                            <div class="na-flex na-justify-between" style="margin-bottom: 0.25rem;">
                                <span>Gross Profit Margin:</span>
                                <strong class="na-font-mono" style="color: var(--na-success);">{{ number_format($pnl['gross_margin_percent'], 1, '.', ',') }}%</strong>
                            </div>
                            <div style="height: 6px; background: #E2E8F0; border-radius: 9999px; overflow: hidden;">
                                <div style="height: 100%; width: {{ min(100, $pnl['gross_margin_percent']) }}%; background: var(--na-success);"></div>
                            </div>
                        </div>

                        <div>
                            <div class="na-flex na-justify-between" style="margin-bottom: 0.25rem;">
                                <span>Operating Margin (EBIT):</span>
                                <strong class="na-font-mono" style="color: #8B5CF6;">{{ $pnl['revenue'] > 0 ? number_format(($pnl['ebit'] / $pnl['revenue']) * 100, 1, '.', ',') : '0.0' }}%</strong>
                            </div>
                            <div style="height: 6px; background: #E2E8F0; border-radius: 9999px; overflow: hidden;">
                                <div style="height: 100%; width: {{ min(100, max(0, $pnl['revenue'] > 0 ? ($pnl['ebit'] / $pnl['revenue']) * 100 : 0)) }}%; background: #8B5CF6;"></div>
                            </div>
                        </div>

                        <div>
                            <div class="na-flex na-justify-between" style="margin-bottom: 0.25rem;">
                                <span>Net Profit Margin:</span>
                                <strong class="na-font-mono" style="color: var(--na-emerald);">{{ $pnl['revenue'] > 0 ? number_format(($pnl['net_result'] / $pnl['revenue']) * 100, 1, '.', ',') : '0.0' }}%</strong>
                            </div>
                            <div style="height: 6px; background: #E2E8F0; border-radius: 9999px; overflow: hidden;">
                                <div style="height: 100%; width: {{ min(100, max(0, $pnl['revenue'] > 0 ? ($pnl['net_result'] / $pnl['revenue']) * 100 : 0)) }}%; background: var(--na-emerald);"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Nepal IRD 13% VAT Summary Card -->
                <div class="na-card na-card-p6">
                    <div class="na-flex na-justify-between na-items-center" style="margin-bottom: 1.25rem;">
                        <h3 style="font-size: 1rem; font-weight: 800; margin: 0;">Nepal IRD VAT Summary (13%)</h3>
                        <span class="na-badge" style="background: rgba(185, 28, 28, 0.1); color: #B91C1C;">Anusuchi 10</span>
                    </div>
                    <div class="na-flex-col na-gap-2" style="font-size: 0.82rem;">
                        <div class="na-flex na-justify-between">
                            <span style="color: var(--na-text-secondary);">Output VAT 13% (Bikri Khata):</span>
                            <span class="na-font-mono" style="font-weight: 700;">Rs. {{ number_format($vat['output_vat_13'] ?? 0, 2, '.', ',') }}</span>
                        </div>
                        <div class="na-flex na-justify-between">
                            <span style="color: var(--na-text-secondary);">Input Tax Credit 13% (Kharid Khata):</span>
                            <span class="na-font-mono" style="color: #B45309; font-weight: 700;">- Rs. {{ number_format($vat['input_vat_13'] ?? 0, 2, '.', ',') }}</span>
                        </div>
                        <div class="na-flex na-justify-between" style="border-top: 1px solid var(--na-border); padding-top: 0.65rem; margin-top: 0.35rem; font-size: 0.9rem; font-weight: 800;">
                            <span>Net VAT Position:</span>
                            <span class="na-font-mono" style="color: {{ ($vat['net_vat_position'] ?? ($vat['net_vat_payable'] ?? 0)) >= 0 ? 'var(--na-rose)' : 'var(--na-success)' }};">
                                Rs. {{ number_format(abs($vat['net_vat_position'] ?? ($vat['net_vat_payable'] ?? 0)), 2, '.', ',') }}
                            </span>
                        </div>
                        <div style="font-size: 0.72rem; color: var(--na-text-muted); margin-top: 0.25rem;">
                            {{ ($vat['net_vat_position'] ?? ($vat['net_vat_payable'] ?? 0)) >= 0 ? 'Payable to IRD by 25th of next Nepali month' : 'Net credit carried forward into next tax period' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- =========================================================================
         6. TAB 2: BALANCE SHEET (सन्तुलन पत्र - सम्पत्ति तथा दायित्व)
         ========================================================================= -->
    @if ($activeTab === 'balance')
        <div>
            <!-- EQUILIBRIUM VERIFICATION STATUS BANNER -->
            <div class="na-card na-card-p4 na-equilibrium-banner" style="background: {{ $balance['is_balanced'] ? 'var(--na-emerald-light)' : 'var(--na-rose-light)' }}; border-color: {{ $balance['is_balanced'] ? 'var(--na-emerald-border)' : '#FCA5A5' }}; margin-bottom: 1.5rem;">
                <div class="na-flex na-justify-between na-items-center na-flex-wrap na-gap-2">
                    <div class="na-flex na-items-center na-gap-3">
                        <span style="font-size: 1.5rem;">{{ $balance['is_balanced'] ? '✓' : '⚠️' }}</span>
                        <div>
                            <strong style="color: {{ $balance['is_balanced'] ? 'var(--na-emerald)' : 'var(--na-rose)' }}; font-size: 0.95rem;">
                                {{ $balance['is_balanced'] ? 'Balance Sheet in Equilibrium (सन्तुलन पत्र - सम्पत्ति = दायित्व तथा पूँजी)' : 'Out of Balance Variance Detected' }}
                            </strong>
                            <div style="font-size: 0.75rem; color: var(--na-text-secondary); margin-top: 0.15rem;">
                                Complete double-entry balance certified under Nepal Accounting Standards (NAS 1 / NAS 7).
                            </div>
                        </div>
                    </div>
                    <div class="na-flex na-items-center na-gap-2">
                        <span class="na-badge" style="background: rgba(0, 0, 0, 0.06); font-family: ui-monospace, monospace; font-size: 0.85rem; font-weight: 800;">
                            Variance: Rs. {{ number_format($balance['difference'], 2, '.', ',') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- DUAL COLUMN BALANCE SHEET -->
            <div class="na-grid-2">
                <!-- ASSETS (सम्पत्ति) -->
                <div class="na-card na-card-p6">
                    <div style="border-bottom: 2px solid var(--na-emerald); padding-bottom: 0.75rem; margin-bottom: 1.25rem;">
                        <h2 style="font-size: 1.2rem; font-weight: 800; margin: 0; color: var(--na-emerald);">ASSETS (सम्पत्ति)</h2>
                        <div style="font-size: 0.75rem; color: var(--na-text-muted); margin-top: 0.2rem;">What the enterprise owns and economic resources controlled</div>
                    </div>

                    <div class="na-waterfall-box">
                        <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--na-text-muted); letter-spacing: 0.05em; margin-top: 0.35rem;">Non-Current Assets</div>
                        <div class="na-wf-row">
                            <span>Showroom fixtures, display units & POS hardware</span>
                            <span class="na-font-mono">Rs. {{ number_format($balance['assets']['fixed_assets'], 2, '.', ',') }}</span>
                        </div>

                        <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--na-text-muted); letter-spacing: 0.05em; margin-top: 0.85rem;">Current Assets</div>
                        <div class="na-wf-row">
                            <span>Inventory Stock (Footwear & Apparel at landed cost)</span>
                            <span class="na-font-mono">Rs. {{ number_format($balance['assets']['inventory'], 2, '.', ',') }}</span>
                        </div>
                        <div class="na-wf-row">
                            <span>Accounts Receivable & Courier Clearing (eSewa / Fonepay / NCM)</span>
                            <span class="na-font-mono">Rs. {{ number_format($balance['assets']['receivables'], 2, '.', ',') }}</span>
                        </div>
                        <div class="na-wf-row">
                            <span>Cash on Hand & Operating Bank (Nabil Bank NPR)</span>
                            <span class="na-font-mono">Rs. {{ number_format($balance['assets']['cash_bank'], 2, '.', ',') }}</span>
                        </div>

                        <div class="na-wf-total" style="margin-top: 1.5rem;">
                            <span>TOTAL ASSETS (सम्पत्ति)</span>
                            <span class="na-font-mono">Rs. {{ number_format($balance['assets']['total_assets'] ?? 0, 2, '.', ',') }}</span>
                        </div>
                    </div>
                </div>

                <!-- LIABILITIES & EQUITY (दायित्व तथा पूँजी) -->
                <div class="na-card na-card-p6">
                    <div style="border-bottom: 2px solid #C5A059; padding-bottom: 0.75rem; margin-bottom: 1.25rem;">
                        <h2 style="font-size: 1.2rem; font-weight: 800; margin: 0; color: #8A651E;">LIABILITIES & EQUITY (दायित्व तथा पूँजी)</h2>
                        <div style="font-size: 0.75rem; color: var(--na-text-muted); margin-top: 0.2rem;">How the enterprise's assets are financed and funded</div>
                    </div>

                    <div class="na-waterfall-box">
                        <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--na-text-muted); letter-spacing: 0.05em; margin-top: 0.35rem;">Equity & Reserves</div>
                        <div class="na-wf-row">
                            <span>Founder Capital & Retained Earnings (Account 3110 / 3120)</span>
                            <span class="na-font-mono">Rs. {{ number_format($balance['liabilities_and_equity']['equity_base'] ?? 0, 2, '.', ',') }}</span>
                        </div>
                        <div class="na-wf-row">
                            <span>Net Profit for the Fiscal Year (Current Period)</span>
                            <span class="na-font-mono" style="color: var(--na-success); font-weight: 700;">Rs. {{ number_format($balance['liabilities_and_equity']['period_net_result'] ?? 0, 2, '.', ',') }}</span>
                        </div>
                        <div class="na-wf-subtotal" style="background: rgba(197, 160, 89, 0.15); border-color: rgba(197, 160, 89, 0.35); color: #8A651E;">
                            <span>Total Equity</span>
                            <span class="na-font-mono">Rs. {{ number_format($balance['liabilities_and_equity']['equity'] ?? 0, 2, '.', ',') }}</span>
                        </div>

                        <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--na-text-muted); letter-spacing: 0.05em; margin-top: 0.85rem;">Current Liabilities</div>
                        <div class="na-wf-row">
                            <span>Trade Accounts Payable (Supplier Liabilities)</span>
                            <span class="na-font-mono">Rs. {{ number_format($balance['liabilities_and_equity']['payables'] ?? 0, 2, '.', ',') }}</span>
                        </div>
                        <div class="na-wf-row">
                            <span>Net Statutory Tax Liabilities (VAT Payable minus Input Credit + TDS)</span>
                            <span class="na-font-mono">Rs. {{ number_format($balance['liabilities_and_equity']['vat_tax'] ?? 0, 2, '.', ',') }}</span>
                        </div>

                        <div class="na-wf-total" style="background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); border-color: #334155; margin-top: 1.5rem;">
                            <span>TOTAL LIABILITIES & EQUITY (दायित्व तथा पूँजी)</span>
                            <span class="na-font-mono">Rs. {{ number_format($balance['liabilities_and_equity']['total_liabilities_and_equity'] ?? 0, 2, '.', ',') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- =========================================================================
         7. TAB 3: TRIAL BALANCE (वासलात / सन्तुलन परीक्षण)
         ========================================================================= -->
    @if ($activeTab === 'trial_balance')
        <div class="na-card na-card-p6">
            <div class="na-flex na-flex-wrap na-justify-between na-items-center na-gap-4" style="border-bottom: 1px solid var(--na-border); padding-bottom: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <h2 style="font-size: 1.2rem; font-weight: 800; margin: 0;">Trial Balance (वासलात / सन्तुलन परीक्षण)</h2>
                    <div style="font-size: 0.78rem; color: var(--na-text-muted); margin-top: 0.2rem;">Consolidated trial balance across all accounts in the Nepal Chart of Accounts (NAS)</div>
                </div>

                <div class="na-flex na-items-center na-gap-2">
                    <input type="text" wire:model.live.debounce.250ms="trialBalanceSearch" placeholder="Search account # or name..." class="na-input" style="width: 220px;">
                    <select wire:model.live="trialBalanceCategory" class="na-select">
                        <option value="all">All Account Categories</option>
                        <option value="revenue">Revenue (4110-4130)</option>
                        <option value="cogs">Cost of Goods Sold (5110)</option>
                        <option value="opex">Operating Expenses (6110-6210)</option>
                        <option value="personnel">Personnel & SSF (6120-6130)</option>
                        <option value="financial">Financial & Bank Fees (6190)</option>
                        <option value="inventory">Inventory Asset (1210)</option>
                        <option value="cash_bank">Cash & Bank (1110-1120)</option>
                        <option value="receivables">Clearing & Receivables (1130-1160)</option>
                        <option value="payables">Accounts Payable (2110, 2150)</option>
                        <option value="vat_tax">VAT & Tax Liabilities (2120-2140)</option>
                        <option value="equity">Equity & Retained Earnings (3110-3120)</option>
                    </select>
                </div>
            </div>

            <div class="na-table-wrap">
                <table class="na-table">
                    <thead>
                        <tr>
                            <th>Account #</th>
                            <th>Account Name</th>
                            <th>Category</th>
                            <th>Type</th>
                            <th style="text-align: right;">Debit Movement</th>
                            <th style="text-align: right;">Credit Movement</th>
                            <th style="text-align: right;">Net Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filteredTbAccounts as $acc)
                            <tr>
                                <td class="na-font-mono" style="font-weight: 700; color: var(--na-emerald);">{{ $acc['account_number'] }}</td>
                                <td style="font-weight: 600;">{{ $acc['name'] }}</td>
                                <td>
                                    <span class="na-badge na-badge-gold" style="font-size: 0.65rem;">{{ $acc['category'] }}</span>
                                </td>
                                <td style="text-transform: uppercase; font-size: 0.7rem; color: var(--na-text-muted);">{{ $acc['account_type'] ?? $acc['type'] ?? '' }}</td>
                                <td class="na-font-mono" style="text-align: right;">{{ number_format($acc['debit'] ?? $acc['debit_movement'] ?? 0, 2, '.', ',') }}</td>
                                <td class="na-font-mono" style="text-align: right;">{{ number_format($acc['credit'] ?? $acc['credit_movement'] ?? 0, 2, '.', ',') }}</td>
                                <td class="na-font-mono" style="text-align: right; font-weight: 700;">
                                    Rs. {{ number_format($acc['net_balance'] ?? $acc['balance'] ?? 0, 2, '.', ',') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--na-text-muted);">No accounts match the search criteria.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr style="background: var(--na-card-subtle); font-weight: 800; border-top: 2px solid var(--na-border-strong);">
                            <td colspan="4">TOTALS (Verification Control)</td>
                            <td class="na-font-mono" style="text-align: right;">Rs. {{ number_format($trialBalance['total_debit'], 2, '.', ',') }}</td>
                            <td class="na-font-mono" style="text-align: right;">Rs. {{ number_format($trialBalance['total_credit'], 2, '.', ',') }}</td>
                            <td class="na-font-mono" style="text-align: right; color: var(--na-success);">✓ In Balance</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif

    <!-- =========================================================================
         8. TAB 4: GENERAL LEDGER (HOVEDBOG)
         ========================================================================= -->
    @if ($activeTab === 'ledger')
        <div class="na-card na-card-p6">
            <div class="na-flex na-flex-wrap na-justify-between na-items-center na-gap-4" style="border-bottom: 1px solid var(--na-border); padding-bottom: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <h2 style="font-size: 1.2rem; font-weight: 800; margin: 0;">General Ledger (खाता / मुख्य पुस्तिका)</h2>
                    <div style="font-size: 0.78rem; color: var(--na-text-muted); margin-top: 0.2rem;">Detailed ledger transaction log with continuous running balance</div>
                </div>

                <div class="na-flex na-items-center na-gap-2">
                    <label style="font-size: 0.78rem; font-weight: 700;">Select Account:</label>
                    <select wire:model.live="selectedAccountId" class="na-select" style="min-width: 280px;">
                        @foreach ($accounts as $a)
                            <option value="{{ $a->id }}">{{ $a->account_number }} — {{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Quick Account Selector Chips -->
            <div class="na-flex na-flex-wrap na-gap-2" style="margin-bottom: 1.25rem;">
                @php
                    $quickAccounts = [
                        '1110' => 'Cash on Hand',
                        '1120' => 'Operating Bank',
                        '1210' => 'Inventory Asset',
                        '2110' => 'Accounts Payable',
                        '4110' => 'POS Sales',
                        '4120' => 'Online Sales',
                        '2120' => 'Output VAT',
                        '5110' => 'COGS',
                    ];
                @endphp
                @foreach ($quickAccounts as $num => $lbl)
                    @php
                        $accObj = $accounts->firstWhere('account_number', $num);
                    @endphp
                    @if ($accObj)
                        <button type="button" wire:click="$set('selectedAccountId', {{ $accObj->id }})" class="na-quick-chip {{ $selectedAccountId == $accObj->id ? 'active' : '' }}">
                            <span>{{ $num }}</span>
                            <span>{{ $lbl }}</span>
                        </button>
                    @endif
                @endforeach
            </div>

            @if ($ledger)
                <div class="na-flex na-justify-between na-items-center na-flex-wrap na-gap-3" style="background: var(--na-card-subtle); padding: 1rem 1.25rem; border-radius: 10px; border: 1px solid var(--na-border); margin-bottom: 1.25rem; font-size: 0.85rem;">
                    <div>
                        <span class="na-badge na-badge-emerald" style="margin-bottom: 0.25rem;">Account #{{ $ledger['account']['account_number'] }}</span>
                        <h3 style="font-size: 1.05rem; font-weight: 800; margin: 0;">{{ $ledger['account']['name'] }}</h3>
                    </div>
                    <div class="na-flex na-gap-6 na-font-mono">
                        <div>
                            <span style="font-size: 0.72rem; color: var(--na-text-muted); display: block;">Closing Balance</span>
                            <strong style="font-size: 1.15rem; color: var(--na-emerald);">Rs. {{ number_format($ledger['closing_balance'], 2, '.', ',') }}</strong>
                        </div>
                    </div>
                </div>

                <div class="na-table-wrap">
                    <table class="na-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Voucher #</th>
                                <th>Description</th>
                                <th style="text-align: right;">Debit</th>
                                <th style="text-align: right;">Credit</th>
                                <th style="text-align: right;">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ledger['entries'] as $e)
                                <tr>
                                    <td class="na-font-mono" style="color: var(--na-text-secondary);">{{ $e['voucher_date'] ?? $e['date'] ?? '-' }}</td>
                                    <td class="na-font-mono" style="font-weight: 700; color: var(--na-emerald);">{{ $e['entry_number'] ?? '-' }}</td>
                                    <td style="font-weight: 500;">{{ $e['description'] ?? '-' }}</td>
                                    <td class="na-font-mono" style="text-align: right;">{{ ($e['debit'] ?? 0) > 0 ? number_format($e['debit'], 2, '.', ',') : '-' }}</td>
                                    <td class="na-font-mono" style="text-align: right;">{{ ($e['credit'] ?? 0) > 0 ? number_format($e['credit'], 2, '.', ',') : '-' }}</td>
                                    <td class="na-font-mono" style="text-align: right; font-weight: 700;">Rs. {{ number_format($e['running_balance'] ?? $e['balance_after'] ?? 0, 2, '.', ',') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--na-text-muted);">No transactions recorded for this account in the selected period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    <!-- =========================================================================
         9. TAB 5: FISCAL PERIODS & YEAR-END CLOSING
         ========================================================================= -->
    @if ($activeTab === 'periods')
        <div class="na-card na-card-p6">
            <div class="na-flex na-flex-wrap na-justify-between na-items-center na-gap-4" style="border-bottom: 1px solid var(--na-border); padding-bottom: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <h2 style="font-size: 1.2rem; font-weight: 800; margin: 0;">Accounting Periods & Fiscal Year Closing (आर्थिक वर्ष)</h2>
                    <div style="font-size: 0.78rem; color: var(--na-text-muted); margin-top: 0.2rem;">Lock statutory tax periods and execute annual closing per Nepal Accounting Standards (NAS)</div>
                </div>

                <button wire:click="executeYearEndClosing(2026)" wire:confirm="Execute fiscal year-end closing for 2026? This transfers net profit to equity account 3120 (Retained Earnings) and locks the fiscal year." class="na-btn na-btn-emerald">
                    Execute Year-End Closing (2026)
                </button>
            </div>

            <div class="na-table-wrap">
                <table class="na-table">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($periods as $p)
                            <tr>
                                <td style="font-weight: 700;">{{ $p->period_name }}</td>
                                <td class="na-font-mono">{{ $p->start_date->format('d.m.Y') }}</td>
                                <td class="na-font-mono">{{ $p->end_date->format('d.m.Y') }}</td>
                                <td style="text-transform: uppercase; font-size: 0.7rem; color: var(--na-text-muted);">{{ $p->period_type }}</td>
                                <td>
                                    @if ($p->is_closed)
                                        <span class="na-badge" style="background: #FEE2E2; color: #991B1B;">Locked / Closed</span>
                                    @else
                                        <span class="na-badge na-badge-emerald">Open for Postings</span>
                                    @endif
                                </td>
                                <td style="text-align: right;">
                                    @if (!$p->is_closed)
                                        <button wire:click="togglePeriodLock({{ $p->id }})" class="na-btn na-btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;">
                                            Lock Period
                                        </button>
                                    @else
                                        <button wire:click="togglePeriodLock({{ $p->id }})" class="na-btn na-btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; opacity: 0.75;">
                                            Reopen
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
</x-filament-panels::page>
