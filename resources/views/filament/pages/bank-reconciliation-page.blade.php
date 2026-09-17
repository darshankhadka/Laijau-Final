<x-filament-panels::page class="w-full max-w-full !p-0">
    @php
    $bankAccounts = $this->bankAccounts;
    $clearing = $this->clearingBalances;
    $metrics = $this->liquidityMetrics;
    $transactions = $this->filteredTransactions;

    // Calculate live ConnectIPS preview
    $connectIpsNet = $connectIpsNetPayout;
    $connectIpsFeeVal = $connectIpsFee;
    $connectIpsGross = round($connectIpsNet + $connectIpsFeeVal, 2);
    $connectIpsFeePercent = $connectIpsGross > 0 ? round(($connectIpsFeeVal / $connectIpsGross) * 100, 2) : 0.0;

    // Calculate live eSewa preview
    $esewaNet = $esewaNetPayout;
    $esewaFeeVal = $esewaFee;
    $esewaGross = round($esewaNet + $esewaFeeVal, 2);
    $esewaFeePercent = $esewaGross > 0 ? round(($esewaFeeVal / $esewaGross) * 100, 2) : 0.0;
    @endphp

    <link rel="stylesheet" href="{{ asset('css/nepal-accounting.css') }}?v=4.0">

    <style>
        /* ==========================================================================
       COMPLETE SELF-CONTAINED VANILLA CSS FOR BANK & SETTLEMENTS (Zero Tailwind)
       ========================================================================== */
        .fi-header,
        .fi-page-header,
        .fi-header-heading,
        .fi-breadcrumbs {
            display: none !important;
        }

        :root {
            --na-emerald: #0A2E23;
            --na-emerald-hover: #134637;
            --na-emerald-light: #EBF3EE;
            --na-emerald-border: #B7D2C5;
            --na-gold: #C5A059;
            --na-gold-light: #FBF6EC;
            --na-gold-border: #DFC288;
            --na-bg: #F8F7F4;
            --na-card: #FFFFFF;
            --na-card-subtle: #F9FAFB;
            --na-border: rgba(229, 231, 235, 0.95);
            --na-border-strong: #D1D5DB;
            --na-text: #111827;
            --na-text-secondary: #4B5563;
            --na-text-muted: #6B7280;
            --na-rose: #DC2626;
            --na-rose-light: #FEF2F2;
            --na-rose-border: #FCA5A5;
            --na-success: #059669;
            --na-success-light: #ECFDF5;
            --na-blue: #2563EB;
            --na-blue-light: #EFF6FF;
            --na-purple: #635BFF;
            --na-purple-light: #F5F3FF;
        }

        .dark {
            --na-bg: #0D1117;
            --na-card: #161B22;
            --na-card-subtle: #1F242C;
            --na-border: rgba(48, 54, 61, 0.9);
            --na-border-strong: #30363D;
            --na-text: #F3F4F6;
            --na-text-secondary: #9CA3AF;
            --na-text-muted: #6B7280;
            --na-emerald-light: #162C24;
            --na-emerald-border: #1E4637;
            --na-gold-light: #2A2418;
        }

        .na-bank-root {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica NNpe", Arial, sans-serif;
            color: var(--na-text);
            background: var(--na-bg);
            min-height: 100vh;
            padding: 1.5rem;
            box-sizing: border-box;
        }

        .na-card {
            background: var(--na-card);
            border: 1px solid var(--na-border);
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            box-sizing: border-box;
            overflow: hidden;
        }

        .na-card-p6 {
            padding: 1.5rem;
        }

        .na-card-p4 {
            padding: 1rem;
        }

        .na-header-banner {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem 1.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
            color: #0f172a;
        }

        .dark .na-header-banner {
            background: #0f172a;
            border-color: #1e293b;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.25);
            color: #f8fafc;
        }

        .na-title-main {
            font-family: inherit;
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #0f172a;
            margin: 0.25rem 0 0 0;
        }

        .dark .na-title-main {
            color: #f8fafc;
        }

        .na-subtitle {
            font-size: 0.8125rem;
            color: #64748b;
            margin: 0.25rem 0 0 0;
            max-width: 48rem;
            line-height: 1.45;
        }

        .dark .na-subtitle {
            color: #94a3b8;
        }

        /* Grids */
        .na-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 1024px) {
            .na-grid-4 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .na-grid-4 {
                grid-template-columns: 1fr;
            }
        }

        .na-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 900px) {
            .na-grid-3 {
                grid-template-columns: 1fr;
            }
        }

        .na-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .na-grid-2 {
                grid-template-columns: 1fr;
            }
        }

        .na-pipeline-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        @media (max-width: 900px) {
            .na-pipeline-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Flex Utilities */
        .na-flex {
            display: flex;
        }

        .na-flex-col {
            display: flex;
            flex-direction: column;
        }

        .na-flex-wrap {
            display: flex;
            flex-wrap: wrap;
        }

        .na-items-center {
            align-items: center;
        }

        .na-items-start {
            align-items: flex-start;
        }

        .na-items-end {
            align-items: flex-end;
        }

        .na-justify-between {
            justify-content: space-between;
        }

        .na-justify-end {
            justify-content: flex-end;
        }

        .na-justify-center {
            justify-content: center;
        }

        .na-gap-1 {
            gap: 0.25rem;
        }

        .na-gap-2 {
            gap: 0.5rem;
        }

        .na-gap-3 {
            gap: 0.75rem;
        }

        .na-gap-4 {
            gap: 1rem;
        }

        /* Metric Cards */
        .na-metric-card {
            background: var(--na-card);
            border: 1px solid var(--na-border);
            border-radius: 12px;
            padding: 1.15rem 1.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-sizing: border-box;
        }

        .na-metric-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--na-text-muted);
        }

        .na-metric-val {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--na-text);
            margin: 0.4rem 0 0.2rem 0;
        }

        .na-metric-sub {
            font-size: 0.72rem;
            color: var(--na-text-secondary);
        }

        /* Nordic Virtual Debit Cards */
        .nordic-debit-card {
            border-radius: 16px;
            padding: 1.65rem;
            position: relative;
            overflow: hidden;
            color: #FFFFFF;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 220px;
            box-sizing: border-box;
        }

        .debit-card-npr {
            background: linear-gradient(135deg, #0A2E23 0%, #154D3B 100%);
        }

        .debit-card-bank {
            background: linear-gradient(135deg, #0F1E36 0%, #1E3A8A 100%);
        }

        .debit-card-cash {
            background: linear-gradient(135deg, #26231E 0%, #423C34 60%, #1A1814 100%);
        }

        .nordic-chip {
            width: 42px;
            height: 30px;
            background: linear-gradient(135deg, #e6c875 0%, #b89345 50%, #f3df9b 100%);
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            position: relative;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.25);
        }

        .nordic-chip::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(0, 0, 0, 0.2);
        }

        .nordic-chip::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            width: 1px;
            background: rgba(0, 0, 0, 0.2);
        }

        /* Pipeline Step */
        .na-pipeline-step {
            border-radius: 12px;
            padding: 1.15rem;
            border: 1px solid var(--na-border);
            background: var(--na-card-subtle);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-sizing: border-box;
        }

        /* Buttons & Inputs */
        .na-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            padding: 0.6rem 1.15rem;
            font-size: 0.78rem;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
            border: 1px solid transparent;
            text-decoration: none;
            box-sizing: border-box;
        }

        .na-btn-emerald {
            background: var(--na-emerald);
            color: #FFFFFF;
        }

        .na-btn-secondary {
            background: var(--na-card);
            border: 1px solid var(--na-border-strong);
            color: var(--na-text);
        }

        .na-btn-purple {
            background: #635BFF;
            color: #FFFFFF;
        }

        .na-btn-blue {
            background: #2563EB;
            color: #FFFFFF;
        }

        .na-input {
            width: 100%;
            padding: 0.6rem 0.85rem;
            font-size: 0.8rem;
            border: 1px solid var(--na-border-strong);
            border-radius: 8px;
            background: var(--na-card);
            color: var(--na-text);
            box-sizing: border-box;
            outline: none;
        }

        .na-select {
            padding: 0.6rem 0.85rem;
            font-size: 0.8rem;
            border: 1px solid var(--na-border-strong);
            border-radius: 8px;
            background: var(--na-card);
            color: var(--na-text);
            box-sizing: border-box;
        }

        /* Badges */
        .na-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .na-badge-emerald {
            background: var(--na-emerald-light);
            color: var(--na-emerald);
            border: 1px solid var(--na-emerald-border);
        }

        .na-badge-gold {
            background: var(--na-gold-light);
            color: #8A651E;
            border: 1px solid var(--na-gold-border);
        }

        .na-badge-purple {
            background: #F5F3FF;
            color: #635BFF;
            border: 1px solid #DDD6FE;
        }

        .na-badge-blue {
            background: #EFF6FF;
            color: #2563EB;
            border: 1px solid #BFDBFE;
        }

        .na-badge-amber {
            background: #FEF3C7;
            color: #92400E;
            border: 1px solid #FDE68A;
        }

        .na-badge-rose {
            background: var(--na-rose-light);
            color: var(--na-rose);
            border: 1px solid var(--na-rose-border);
        }

        /* Calculation Box */
        .na-calc-box {
            border-radius: 10px;
            padding: 1rem 1.15rem;
            border: 1px solid var(--na-border);
            margin: 1rem 0;
            font-size: 0.78rem;
            box-sizing: border-box;
        }

        .na-calc-row {
            display: flex;
            justify-content: space-between;
            padding: 0.3rem 0;
        }

        .na-calc-total {
            border-top: 1px solid var(--na-border);
            margin-top: 0.4rem;
            padding-top: 0.5rem;
            font-weight: 800;
        }

        /* Tables */
        .na-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .na-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            text-align: left;
        }

        .na-table th {
            padding: 0.8rem 1rem;
            background: var(--na-card-subtle);
            color: var(--na-text-secondary);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--na-border);
        }

        .na-table td {
            padding: 0.8rem 1rem;
            border-bottom: 1px solid var(--na-border);
            color: var(--na-text);
        }

        .na-table tr:hover td {
            background: rgba(10, 46, 35, 0.02);
        }

        /* Modal */
        .na-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(2px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .na-modal-content {
            background: var(--na-card);
            border: 1px solid var(--na-border);
            border-radius: 16px;
            width: 100%;
            max-width: 580px;
            padding: 1.75rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            box-sizing: border-box;
        }

        .na-font-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
    </style>

    <div class="na-bank-root">

        <!-- 1. TOP HEADER & CONTEXT BAR (Clean Minimalist Light Mode) -->
        <div class="na-header-banner">
            <div>
                <div class="na-flex na-items-center na-gap-2">
                    <span class="na-badge" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; font-size: 12px; padding: 4px 10px; border-radius: 9999px; font-weight: 600;">
                        Liquidity & Cash Management
                    </span>
                    <span class="na-badge" style="background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; font-size: 12px; padding: 4px 10px; border-radius: 9999px; font-weight: 600;">
                        Operating Bank • connectIPS • eSewa • COD
                    </span>
                </div>
                <h1 class="na-title-main">
                    Bank Reconciliation & Settlement Clearing
                </h1>
                <p class="na-subtitle">
                    Manage company bank accounts, reconcile payment gateway payout batches, and maintain showroom register balance.
                </p>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="na-flex na-items-center na-gap-2">
                <button wire:click="openTransferModal" class="na-btn na-btn-emerald">
                    <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    <span>Internal Transfer / Cash Deposit</span>
                </button>
            </div>
        </div>

        <!-- 2. HIGH-DENSITY KPI STRIP -->
        <div class="na-grid-4">
            <!-- Total Liquid Bank & Cash -->
            <div class="na-metric-card" style="border-left: 3px solid var(--na-emerald);">
                <div class="na-flex na-justify-between na-items-center">
                    <span class="na-metric-label">Liquid Capital (Bank + Cash)</span>
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--na-success); display: inline-block;"></span>
                </div>
                <div class="na-metric-val" style="color: var(--na-emerald);">
                    Rs. {{ number_format($metrics['total_liquid_npr'] ?? $metrics['total_liquid_npr'], 2, '.', ',') }}
                </div>
                <div class="na-metric-sub">
                    Operating Bank Accounts + Showroom Drawer
                </div>
            </div>

            <!-- Pending Gateways Clearing -->
            <div class="na-metric-card" style="border-left: 3px solid var(--na-gold);">
                <div class="na-flex na-justify-between na-items-center">
                    <span class="na-metric-label">Pending Gateways Clearing</span>
                    <span class="na-badge na-badge-amber" style="font-size: 0.65rem;">Account 1130/1140/1150</span>
                </div>
                <div class="na-metric-val" style="color: #92400E;">
                    Rs. {{ number_format($metrics['clearing_total_npr'] ?? $metrics['clearing_total_npr'], 2, '.', ',') }}
                </div>
                <div class="na-metric-sub">
                    eSewa: Rs. {{ number_format($clearing['esewa'] ?? 0, 2, '.', ',') }} • connectIPS/Card: Rs. {{ number_format($clearing['connectips'] ?? 0, 2, '.', ',') }} • COD: Rs. {{ number_format($clearing['cod'] ?? 0, 2, '.', ',') }}
                </div>
            </div>

            <!-- Total Available Runway -->
            <div class="na-metric-card" style="border-left: 3px solid #2563EB;">
                <div class="na-flex na-justify-between na-items-center">
                    <span class="na-metric-label">Total Available Capital</span>
                    <span class="na-badge na-badge-blue" style="font-size: 0.65rem;">Liquidity + Pipeline</span>
                </div>
                <div class="na-metric-val" style="color: #2563EB;">
                    Rs. {{ number_format($metrics['total_available_npr'] ?? $metrics['total_available_npr'], 2, '.', ',') }}
                </div>
                <div class="na-metric-sub">
                    Authoritative balance in Nepalese Rupees (NPR)
                </div>
            </div>

            <!-- Reconciled Rate -->
            <div class="na-metric-card" style="border-left: 3px solid var(--na-success);">
                <div class="na-flex na-justify-between na-items-center">
                    <span class="na-metric-label">Reconciliation Rate</span>
                    <span class="na-badge na-badge-emerald" style="font-size: 0.65rem;">Posted</span>
                </div>
                <div class="na-metric-val" style="color: var(--na-success);">
                    {{ $metrics['reconciled_rate'] }}%
                </div>
                <div class="na-metric-sub">
                    {{ $metrics['total_tx_count'] }} recorded ledger transactions
                </div>
            </div>
        </div>

        <!-- 3. REAL-TIME BANK ACCOUNTS DEBIT CARD TILES -->
        <div style="margin-bottom: 2rem;">
            <div class="na-flex na-justify-between na-items-center" style="margin-bottom: 1rem;">
                <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--na-text); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <span>Active Financial Registers & Bank Accounts</span>
                    <span class="na-badge na-badge-emerald" style="font-size: 0.7rem;">NPR Authoritative</span>
                </h2>
            </div>

            <div class="na-grid-3">
                @foreach ($bankAccounts as $bank)
                @php
                $isCash = str_contains(strtolower($bank->name), 'kasse') || str_contains(strtolower($bank->name), 'showroom') || str_contains(strtolower($bank->name), 'cash');
                @endphp

                @if (!$isCash)
                <!-- OPERATING BANK ACCOUNT -->
                <div class="nordic-debit-card debit-card-npr">
                    <div class="na-flex na-justify-between na-items-center">
                        <div>
                            <div style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.12em; color: #A7F3D0; font-weight: 700;">Commercial Bank Account</div>
                            <div style="font-size: 1rem; font-weight: 800; color: #FFFFFF; margin-top: 0.2rem;">{{ $bank->name }}</div>
                        </div>
                        <div class="nordic-chip"></div>
                    </div>

                    <div style="margin: 1.5rem 0;">
                        <div style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.75);">Booked Bank Balance</div>
                        <div class="na-font-mono" style="font-size: 1.85rem; font-weight: 800; color: #FFFFFF; margin-top: 0.2rem;">
                            Rs. {{ number_format($bank->current_balance, 2, '.', ',') }}
                        </div>
                    </div>

                    <div class="na-flex na-justify-between na-items-end" style="border-top: 1px solid rgba(255, 255, 255, 0.2); padding-top: 0.75rem; font-size: 0.75rem;">
                        <div class="na-font-mono">
                            <div>Account No: {{ $bank->account_number ?: '•••• 4821' }}</div>
                            <div style="font-size: 0.68rem; opacity: 0.75;">Currency: NPR (Rs.)</div>
                        </div>
                        <span class="na-badge" style="background: rgba(10, 46, 35, 0.9); border: 1px solid #10B981; color: #A7F3D0;">
                            Account {{ $bank->ledgerAccount?->account_number ?: '1120' }}
                        </span>
                    </div>
                </div>
                @else
                <!-- CASH IN HAND / POS REGISTER -->
                <div class="nordic-debit-card debit-card-cash">
                    <div class="na-flex na-justify-between na-items-center">
                        <div>
                            <div style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.12em; color: #FDE68A; font-weight: 700;">Showroom Cash Drawer</div>
                            <div style="font-size: 1rem; font-weight: 800; color: #FFFFFF; margin-top: 0.2rem;">{{ $bank->name }}</div>
                        </div>
                        <div style="padding: 0.5rem; border-radius: 8px; background: rgba(180, 83, 9, 0.3); border: 1px solid rgba(245, 158, 11, 0.5); color: #FDE68A; font-size: 1.25rem;">
                            💵
                        </div>
                    </div>

                    <div style="margin: 1.5rem 0;">
                        <div style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.75);">Physical Cash Balance</div>
                        <div class="na-font-mono" style="font-size: 1.85rem; font-weight: 800; color: #FFFFFF; margin-top: 0.2rem;">
                            Rs. {{ number_format($bank->current_balance, 2, '.', ',') }}
                        </div>
                        <div style="font-size: 0.72rem; color: #FDE68A; margin-top: 0.25rem;">
                            Cash drawer for POS & retail showroom
                        </div>
                    </div>

                    <div class="na-flex na-justify-between na-items-end" style="border-top: 1px solid rgba(255, 255, 255, 0.2); padding-top: 0.75rem; font-size: 0.75rem;">
                        <div>
                            <div>Daily reconciliation per cash journal</div>
                            <div class="na-font-mono" style="font-size: 0.68rem; color: #FDE68A;">Laijau Showroom, Kathmandu, Nepal</div>
                        </div>
                        <span class="na-badge" style="background: rgba(38, 35, 30, 0.9); border: 1px solid #D97706; color: #FDE68A;">
                            Account {{ $bank->ledgerAccount?->account_number ?: '1110' }}
                        </span>
                    </div>
                </div>
                @endif
                @endforeach
            </div>
        </div>

        <!-- 4. FINTECH CLEARING PIPELINE INFOGRAPHIC -->
        <div class="na-card na-card-p6" style="border-left: 4px solid var(--na-emerald); margin-bottom: 1.5rem;">
            <div class="na-flex na-flex-wrap na-justify-between na-items-center na-gap-2" style="border-bottom: 1px solid var(--na-border); padding-bottom: 0.85rem;">
                <div>
                    <h3 style="font-size: 0.95rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--na-text); margin: 0;">
                        Fintech Clearing Pipeline
                        <span style="font-size: 0.75rem; font-weight: normal; color: var(--na-text-muted); font-family: monospace;">(Funds Flow & Clearings)</span>
                    </h3>
                    <p style="font-size: 0.78rem; color: var(--na-text-secondary); margin: 0.2rem 0 0 0;">
                        Visualizes how customer funds from online webshop and showroom POS clear through merchant accounts into Operating Bank.
                    </p>
                </div>
                <span class="na-badge na-badge-gold">
                    Nepal Accounting Standards: Gross posting mandate without offset
                </span>
            </div>

            <div class="na-pipeline-grid">
                <!-- STEP 1: CUSTOMER CHECKOUT -->
                <div class="na-pipeline-step">
                    <div class="na-flex na-justify-between na-items-center">
                        <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: var(--na-text-muted);">1. Sales Channel</span>
                        <span style="font-size: 1.25rem;">🛍️</span>
                    </div>
                    <div style="font-weight: 800; font-size: 0.9rem; margin-top: 0.5rem;">Customer Checkout</div>
                    <div style="font-size: 0.75rem; color: var(--na-text-secondary); margin-top: 0.25rem;">
                        Webshop (NPR) & Showroom POS with eSewa / Fonepay / Card
                    </div>
                    <div style="border-top: 1px solid var(--na-border); margin-top: 0.75rem; padding-top: 0.5rem; font-size: 0.72rem; font-weight: 700; color: var(--na-emerald);">
                        Account 4110 / 1010 Credited
                    </div>
                </div>

                <!-- STEP 2: CLEARING GATEWAYS -->
                <div class="na-pipeline-step" style="background: var(--na-gold-light); border-color: var(--na-gold-border);">
                    <div class="na-flex na-justify-between na-items-center">
                        <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #8A651E;">2. Gateway Clearing</span>
                        <span style="font-size: 1.25rem;">⚡</span>
                    </div>
                    <div style="font-weight: 800; font-size: 0.9rem; color: #8A651E; margin-top: 0.5rem;">Gateways & Wallets</div>
                    <div style="font-size: 0.75rem; color: #8A651E; margin-top: 0.25rem;">
                        Pending payout batch to bank
                    </div>
                    <div class="na-flex na-justify-between na-items-center na-font-mono" style="border-top: 1px solid var(--na-gold-border); margin-top: 0.75rem; padding-top: 0.5rem; font-size: 0.75rem; font-weight: 800; color: #8A651E;">
                        <span>Pending:</span>
                        <span>Rs. {{ number_format($clearing['total'], 2, '.', ',') }}</span>
                    </div>
                </div>

                <!-- STEP 3: TRANSACTION FEES -->
                <div class="na-pipeline-step" style="background: var(--na-rose-light); border-color: var(--na-rose-border);">
                    <div class="na-flex na-justify-between na-items-center">
                        <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: var(--na-rose);">3. Fee Deduction</span>
                        <span style="font-size: 1.25rem;">🧾</span>
                    </div>
                    <div style="font-weight: 800; font-size: 0.9rem; color: var(--na-rose); margin-top: 0.5rem;">Processing Fees</div>
                    <div style="font-size: 0.75rem; color: var(--na-rose); margin-top: 0.25rem;">
                        Card fee (1310) and Digital wallet fee (1320)
                    </div>
                    <div style="border-top: 1px solid var(--na-rose-border); margin-top: 0.75rem; padding-top: 0.5rem; font-size: 0.72rem; font-weight: 700; color: var(--na-rose);">
                        Automated P&L expense deduction
                    </div>
                </div>

                <!-- STEP 4: OPERATING BANK -->
                <div class="na-pipeline-step" style="background: var(--na-emerald-light); border-color: var(--na-emerald-border);">
                    <div class="na-flex na-justify-between na-items-center">
                        <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: var(--na-emerald);">4. Bank Credit</span>
                        <span style="font-size: 1.25rem;">🏦</span>
                    </div>
                    <div style="font-weight: 800; font-size: 0.9rem; color: var(--na-emerald); margin-top: 0.5rem;">Operating Bank</div>
                    <div style="font-size: 0.75rem; color: var(--na-emerald); margin-top: 0.25rem;">
                        Net proceeds deposited into Operating Account (1120 / 2410)
                    </div>
                    <div style="border-top: 1px solid var(--na-emerald-border); margin-top: 0.75rem; padding-top: 0.5rem; font-size: 0.72rem; font-weight: 800; color: var(--na-emerald);">
                        ✓ 100% Reconciled to Bank Statement
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. INTERACTIVE SETTLEMENT WIZARDS -->
        <div class="na-grid-2">

            <!-- CONNECTIPS / CARD SETTLEMENT WIZARD (PURPLE THEME) -->
            <div class="na-card na-card-p6">
                <div class="na-flex na-justify-between na-items-center" style="border-bottom: 1px solid var(--na-border); padding-bottom: 1rem; margin-bottom: 1rem;">
                    <div class="na-flex na-items-center na-gap-3">
                        <div style="width: 38px; height: 38px; border-radius: 10px; background: #F5F3FF; border: 1px solid #DDD6FE; color: #635BFF; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem;">
                            C
                        </div>
                        <div>
                            <h3 style="font-size: 1rem; font-weight: 800; margin: 0; color: var(--na-text);">Reconcile connectIPS / Card Payout</h3>
                            <p style="font-size: 0.75rem; color: var(--na-text-secondary); margin: 0.15rem 0 0 0;">connectIPS Clearing (1140) ➔ Operating Bank Account (1120)</p>
                        </div>
                    </div>

                    <button type="button" wire:click="prefillConnectIps" class="na-btn" style="background: #F5F3FF; border: 1px solid #DDD6FE; color: #635BFF; font-size: 0.72rem; padding: 0.4rem 0.75rem;">
                        Fill from clearing (Rs. {{ number_format($clearing['connectips'] ?? 0, 2, '.', ',') }})
                    </button>
                </div>

                <div class="na-flex-col na-gap-3">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem;">
                            connectIPS Batch ID / Reference <span style="color: var(--na-rose);">*</span>
                        </label>
                        <input type="text" wire:model.live="connectIpsPayoutId" placeholder="e.g. CIP_2026_BATCH or bank statement ref" class="na-input na-font-mono">
                    </div>

                    <div class="na-grid-2" style="margin-bottom: 0; gap: 0.85rem;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem;">
                                Net Payout in Bank (NPR) <span style="color: var(--na-rose);">*</span>
                            </label>
                            <input type="number" step="0.01" wire:model.live="connectIpsNetPayout" placeholder="0.00" class="na-input na-font-mono" style="font-weight: 800;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem;">
                                Gateway Fee (NPR) <span style="color: var(--na-rose);">*</span>
                            </label>
                            <input type="number" step="0.01" wire:model.live="connectIpsFee" placeholder="0.00" class="na-input na-font-mono">
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem;">Payout Date per Bank Statement</label>
                        <input type="date" wire:model="connectIpsDate" class="na-input">
                    </div>

                    <!-- LIVE CALCULATION PREVIEW BOX -->
                    <div class="na-calc-box" style="background: #F5F3FF; border-color: #DDD6FE;">
                        <div style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #635BFF; margin-bottom: 0.5rem;">
                            Automated Journal Entry Preview
                        </div>
                        <div class="na-calc-row">
                            <span>Debit Operating Bank (1120):</span>
                            <strong class="na-font-mono" style="color: var(--na-emerald);">Rs. {{ number_format($connectIpsNet, 2, '.', ',') }}</strong>
                        </div>
                        <div class="na-calc-row">
                            <span>Debit Gateway Fee Expense (6120):</span>
                            <strong class="na-font-mono" style="color: var(--na-rose);">Rs. {{ number_format($connectIpsFeeVal, 2, '.', ',') }} ({{ $connectIpsFeePercent }}%)</strong>
                        </div>
                        <div class="na-calc-row na-calc-total">
                            <span>Credit connectIPS Clearing (1140 Gross):</span>
                            <strong class="na-font-mono" style="color: #635BFF;">Rs. {{ number_format($connectIpsGross, 2, '.', ',') }}</strong>
                        </div>
                    </div>

                    <button type="button" wire:click="reconcileConnectIps" wire:loading.attr="disabled" class="na-btn na-btn-purple" style="width: 100%; padding: 0.75rem; font-size: 0.85rem;">
                        <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Post & Reconcile connectIPS Settlement</span>
                    </button>
                </div>
            </div>

            <!-- DIGITAL WALLET SETTLEMENT WIZARD (BLUE THEME) -->
            <div class="na-card na-card-p6">
                <div class="na-flex na-justify-between na-items-center" style="border-bottom: 1px solid var(--na-border); padding-bottom: 1rem; margin-bottom: 1rem;">
                    <div class="na-flex na-items-center na-gap-3">
                        <div style="width: 38px; height: 38px; border-radius: 10px; background: #EFF6FF; border: 1px solid #BFDBFE; color: #2563EB; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem;">
                            E
                        </div>
                        <div>
                            <h3 style="font-size: 1rem; font-weight: 800; margin: 0; color: var(--na-text);">Reconcile eSewa Wallet Settlement</h3>
                            <p style="font-size: 0.75rem; color: var(--na-text-secondary); margin: 0.15rem 0 0 0;">eSewa Clearing (1130) ➔ Operating Bank Account (1120)</p>
                        </div>
                    </div>

                    <button type="button" wire:click="prefillEsewa" class="na-btn" style="background: #EFF6FF; border: 1px solid #BFDBFE; color: #2563EB; font-size: 0.72rem; padding: 0.4rem 0.75rem;">
                        Fill from clearing (Rs. {{ number_format($clearing['esewa'] ?? 0, 2, '.', ',') }})
                    </button>
                </div>

                <div class="na-flex-col na-gap-3">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem;">
                            eSewa Batch / Statement Reference <span style="color: var(--na-rose);">*</span>
                        </label>
                        <input type="text" wire:model.live="esewaBatchRef" placeholder="e.g. ESEWA-BATCH-2026 or statement ref" class="na-input na-font-mono">
                    </div>

                    <div class="na-grid-2" style="margin-bottom: 0; gap: 0.85rem;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem;">
                                Net Payout in Bank (NPR) <span style="color: var(--na-rose);">*</span>
                            </label>
                            <input type="number" step="0.01" wire:model.live="esewaNetPayout" placeholder="0.00" class="na-input na-font-mono" style="font-weight: 800;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem;">
                                Wallet Commission Fee (NPR) <span style="color: var(--na-rose);">*</span>
                            </label>
                            <input type="number" step="0.01" wire:model.live="esewaFee" placeholder="0.00" class="na-input na-font-mono">
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem;">Payout Date per Bank Statement</label>
                        <input type="date" wire:model="esewaDate" class="na-input">
                    </div>

                    <!-- LIVE CALCULATION PREVIEW BOX -->
                    <div class="na-calc-box" style="background: #EFF6FF; border-color: #BFDBFE;">
                        <div style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #2563EB; margin-bottom: 0.5rem;">
                            Automated Journal Entry Preview
                        </div>
                        <div class="na-calc-row">
                            <span>Debit Operating Bank (1120):</span>
                            <strong class="na-font-mono" style="color: var(--na-emerald);">Rs. {{ number_format($esewaNet, 2, '.', ',') }}</strong>
                        </div>
                        <div class="na-calc-row">
                            <span>Debit eSewa Fee Expense (6120):</span>
                            <strong class="na-font-mono" style="color: var(--na-rose);">Rs. {{ number_format($esewaFeeVal, 2, '.', ',') }} ({{ $esewaFeePercent }}%)</strong>
                        </div>
                        <div class="na-calc-row na-calc-total">
                            <span>Credit eSewa Clearing (1130 Gross):</span>
                            <strong class="na-font-mono" style="color: #2563EB;">Rs. {{ number_format($esewaGross, 2, '.', ',') }}</strong>
                        </div>
                    </div>

                    <button type="button" wire:click="reconcileEsewa" wire:loading.attr="disabled" class="na-btn na-btn-blue" style="width: 100%; padding: 0.75rem; font-size: 0.85rem;">
                        <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Post & Reconcile eSewa Settlement</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 6. BANK TRANSACTIONS AUDIT & RECONCILIATION FEED -->
        <div class="na-card na-card-p6">
            <div class="na-flex na-flex-wrap na-justify-between na-items-center na-gap-4" style="border-bottom: 1px solid var(--na-border); padding-bottom: 1rem; margin-bottom: 1rem;">
                <div>
                    <h3 style="font-size: 1rem; font-weight: 800; margin: 0; color: var(--na-text);">
                        Audit Trail: Bank Transactions (Revisionsspor: Banktransaktioner)
                        <span class="na-badge na-badge-gold" style="font-size: 0.7rem; font-family: monospace; font-weight: normal;">{{ $transactions->count() }} entries</span>
                    </h3>
                    <p style="font-size: 0.75rem; color: var(--na-text-secondary); margin: 0.2rem 0 0 0;">
                        All registered account movements directly linked to accounting vouchers per statutory bookkeeping retention rules.
                    </p>
                </div>

                <!-- SEARCH & FILTERS -->
                <div class="na-flex na-flex-wrap na-items-center na-gap-2">
                    <input type="text" wire:model.live.debounce.250ms="transactionSearch" placeholder="Search transactions, ref or voucher..." class="na-input" style="width: 220px;">

                    <select wire:model.live="selectedBankFilter" class="na-select">
                        <option value="all">All Accounts</option>
                        @foreach ($bankAccounts as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="transactionType" class="na-select">
                        <option value="all">All Types</option>
                        <option value="connectips_payout">ConnectIPS Payouts</option>
                        <option value="esewa_settlement">eSewa Payouts</option>
                        <option value="internal_transfer">Internal Transfers</option>
                    </select>

                    <select wire:model.live="dateFilter" class="na-select">
                        <option value="7days">Last 7 days</option>
                        <option value="30days">Last 30 days</option>
                        <option value="90days">Last 90 days</option>
                        <option value="year">Year to date</option>
                        <option value="all">All time</option>
                    </select>
                </div>
            </div>

            <div class="na-table-wrap">
                <table class="na-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Bank Account</th>
                            <th>Type & Reference</th>
                            <th>Description</th>
                            <th>Voucher #</th>
                            <th style="text-align: right;">Amount</th>
                            <th style="text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $tx)
                        @php
                        $isDeposit = $tx->amount > 0;
                        @endphp
                        <tr>
                            <td class="na-font-mono" style="white-space: nowrap;">
                                {{ $tx->transaction_date ? $tx->transaction_date->format('d.m.Y') : '-' }}
                            </td>
                            <td style="white-space: nowrap; font-weight: 600;">
                                {{ $tx->bankAccount?->name }}
                            </td>
                            <td style="white-space: nowrap;">
                                <div class="na-flex na-items-center na-gap-1">
                                    @if ($tx->match_type === 'connectips_payout')
                                    <span class="na-badge na-badge-purple" style="font-size: 0.65rem;">ConnectIPS</span>
                                    @elseif ($tx->match_type === 'esewa_settlement')
                                    <span class="na-badge na-badge-blue" style="font-size: 0.65rem;">eSewa</span>
                                    @elseif ($tx->match_type === 'internal_transfer')
                                    <span class="na-badge na-badge-gold" style="font-size: 0.65rem;">Transfer</span>
                                    @else
                                    <span class="na-badge" style="background: #F3F4F6; color: #374151; font-size: 0.65rem;">Bank</span>
                                    @endif
                                    <span class="na-font-mono" style="font-size: 0.72rem; color: var(--na-text-muted);">{{ $tx->external_reference ?: '-' }}</span>
                                </div>
                            </td>
                            <td>
                                {{ $tx->description }}
                            </td>
                            <td style="white-space: nowrap;">
                                @if ($tx->journalEntry)
                                <span class="na-font-mono" style="font-weight: 800; color: var(--na-emerald);">
                                    {{ $tx->journalEntry->entry_number }}
                                </span>
                                @else
                                <span class="na-font-mono" style="color: var(--na-text-muted);">-</span>
                                @endif
                            </td>
                            <td class="na-font-mono" style="text-align: right; font-weight: 800; white-space: nowrap; color: {{ $isDeposit ? 'var(--na-success)' : 'var(--na-text)' }};">
                                {{ $isDeposit ? '+' : '' }}{{ number_format($tx->amount, 2, '.', ',') }} {{ $tx->currency }}
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                @if ($tx->is_reconciled)
                                <span class="na-badge na-badge-emerald" style="font-size: 0.68rem;">
                                    ✓ Reconciled
                                </span>
                                @else
                                <span class="na-badge na-badge-amber" style="font-size: 0.68rem;">
                                    Pending
                                </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--na-text-muted);">
                                No bank transactions match the selected filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 7. INTERNAL TRANSFER MODAL -->
        @if ($showTransferModal)
        <div class="na-modal-backdrop">
            <div class="na-modal-content">
                <div class="na-flex na-justify-between na-items-center" style="border-bottom: 1px solid var(--na-border); padding-bottom: 1rem; margin-bottom: 1rem;">
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 800; margin: 0; color: var(--na-text);">Internal Transfer / Cash Deposit</h3>
                        <p style="font-size: 0.75rem; color: var(--na-text-secondary); margin: 0.2rem 0 0 0;">Move funds between accounts or deposit showroom cash drawer</p>
                    </div>
                    <button type="button" wire:click="closeTransferModal" style="background: transparent; border: none; font-size: 1.5rem; cursor: pointer; color: var(--na-text-muted);">
                        &times;
                    </button>
                </div>

                <div class="na-flex-col na-gap-3">
                    <div class="na-grid-2" style="margin-bottom: 0; gap: 0.85rem;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem;">From Account</label>
                            <select wire:model="transferFromAccountId" class="na-select" style="width: 100%;">
                                @foreach ($bankAccounts as $b)
                                <option value="{{ $b->id }}">{{ $b->name }} ({{ number_format($b->current_balance, 2, '.', ',') }} {{ $b->currency }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem;">To Account</label>
                            <select wire:model="transferToAccountId" class="na-select" style="width: 100%;">
                                @foreach ($bankAccounts as $b)
                                <option value="{{ $b->id }}">{{ $b->name }} ({{ number_format($b->current_balance, 2, '.', ',') }} {{ $b->currency }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="na-grid-2" style="margin-bottom: 0; gap: 0.85rem;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem;">Amount (NPR)</label>
                            <input type="number" step="0.01" wire:model="transferAmount" placeholder="0.00" class="na-input na-font-mono" style="font-weight: 800;">
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem;">Date</label>
                            <input type="date" wire:model="transferDate" class="na-input">
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem;">Reference / Note</label>
                        <input type="text" wire:model="transferReference" placeholder="e.g. Cash deposit week 36 or Currency exchange" class="na-input">
                    </div>
                </div>

                <div class="na-flex na-justify-end na-gap-2" style="border-top: 1px solid var(--na-border); padding-top: 1rem; margin-top: 1.25rem;">
                    <button type="button" wire:click="closeTransferModal" class="na-btn na-btn-secondary">
                        Cancel
                    </button>
                    <button type="button" wire:click="executeInternalTransfer" class="na-btn na-btn-emerald">
                        Execute Transfer & Post Voucher
                    </button>
                </div>
            </div>
        </div>
        @endif

    </div>
</x-filament-panels::page>
