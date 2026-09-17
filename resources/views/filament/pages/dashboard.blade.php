<x-filament-panels::page class="w-full max-w-full">
    @php
    $kpi = $this->getKpiData();
    $financial = $this->getFinancialSummary();
    $sales = $this->getSalesOverview();
    $orderOps = $this->getOrderOperations();
    $attention = $this->getAttentionItems();
    $inventory = $this->getInventorySummary();
    $customerActivity = $this->getCustomerActivity();
    $recentOrders = $this->getRecentOrders();
    @endphp

    <style>
        .lj-dash-root {
            width: 100%;
            max-width: 100%;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            padding-bottom: 2rem;
            font-family: inherit;
        }

        /* Glass Cards with Executive Elevation */
        .lj-dash-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.85);
            border-radius: 1rem;
            padding: 1.125rem 1.375rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.02), 0 4px 12px -2px rgba(0, 0, 0, 0.03);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
        }

        .dark .lj-dash-card {
            background: #0f172a;
            border-color: rgba(30, 41, 59, 0.85);
            box-shadow: 0 4px 20px -4px rgba(0, 0, 0, 0.35);
        }

        .lj-dash-card:hover {
            transform: translateY(-2px);
            border-color: rgba(197, 160, 89, 0.55);
            box-shadow: 0 12px 28px -6px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(197, 160, 89, 0.25);
        }

        .dark .lj-dash-card:hover {
            border-color: rgba(197, 160, 89, 0.5);
            box-shadow: 0 16px 32px -8px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(197, 160, 89, 0.3);
        }

        /* Top Accent Lines */
        .lj-accent-gold::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #C5A059, #DFCA9B, #0A2E23);
        }

        .lj-accent-emerald::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #059669, #10B981, #34D399);
        }

        .lj-accent-sapphire::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #2563EB, #3B82F6, #60A5FA);
        }

        .lj-accent-amethyst::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #7C3AED, #8B5CF6, #C084FC);
        }

        /* Filter Command Bar */
        .lj-command-bar {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 1rem;
            padding: 0.875rem 1.375rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02), 0 4px 12px -2px rgba(0,0,0,0.03);
        }

        .dark .lj-command-bar {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-color: rgba(30, 41, 59, 0.9);
            box-shadow: 0 4px 20px -4px rgba(0,0,0,0.4);
        }

        /* Grid Layouts */
        .lj-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        @media (max-width: 1024px) {
            .lj-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .lj-kpi-grid {
                grid-template-columns: 1fr;
            }
        }

        .lj-fin-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.875rem;
        }

        @media (max-width: 1200px) {
            .lj-fin-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .lj-fin-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .lj-main-grid {
            display: grid;
            grid-template-columns: 66% calc(34% - 1rem);
            gap: 1rem;
        }

        @media (max-width: 1024px) {
            .lj-main-grid {
                grid-template-columns: 1fr;
            }
        }

        .lj-tri-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }

        @media (max-width: 1024px) {
            .lj-tri-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Typography & Metrics */
        .lj-val-serif {
            font-family: 'Playfair Display', Georgia, Cambria, 'Times New Roman', serif;
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.15;
        }

        /* Badges & Pills */
        .lj-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 600;
            line-height: 1;
            letter-spacing: 0.02em;
        }

        .lj-badge-gold {
            background: rgba(197, 160, 89, 0.12);
            color: #92722a;
            border: 1px solid rgba(197, 160, 89, 0.3);
        }

        .dark .lj-badge-gold {
            background: rgba(197, 160, 89, 0.18);
            color: #dfc288;
            border-color: rgba(197, 160, 89, 0.35);
        }

        .lj-badge-emerald {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        .dark .lj-badge-emerald {
            background: rgba(16, 185, 129, 0.18);
            color: #6ee7b7;
            border-color: rgba(16, 185, 129, 0.3);
        }

        .lj-badge-sapphire {
            background: rgba(37, 99, 235, 0.1);
            color: #1d4ed8;
            border: 1px solid rgba(37, 99, 235, 0.25);
        }

        .dark .lj-badge-sapphire {
            background: rgba(37, 99, 235, 0.18);
            color: #93c5fd;
            border-color: rgba(37, 99, 235, 0.3);
        }

        .lj-badge-rose {
            background: rgba(225, 29, 72, 0.1);
            color: #be123c;
            border: 1px solid rgba(225, 29, 72, 0.25);
        }

        .dark .lj-badge-rose {
            background: rgba(225, 29, 72, 0.18);
            color: #fda4af;
            border-color: rgba(225, 29, 72, 0.3);
        }

        .lj-badge-amber {
            background: rgba(217, 119, 6, 0.1);
            color: #b45309;
            border: 1px solid rgba(217, 119, 6, 0.25);
        }

        .dark .lj-badge-amber {
            background: rgba(217, 119, 6, 0.18);
            color: #fcd34d;
            border-color: rgba(217, 119, 6, 0.3);
        }

        /* Luxury Action Link */
        .lj-action-link {
            color: #0A2E23;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.15s ease;
        }

        .dark .lj-action-link {
            color: #C5A059;
        }

        .lj-action-link:hover {
            color: #C5A059;
            transform: translateX(2px);
        }

        .dark .lj-action-link:hover {
            color: #DFCA9B;
        }

        /* Tables */
        .lj-table {
            width: 100%;
            text-align: left;
            font-size: 0.8125rem;
            border-collapse: collapse;
        }

        .lj-table th {
            padding: 0.625rem 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.65rem;
            letter-spacing: 0.06em;
            color: #64748b;
            border-bottom: 1px solid rgba(226, 232, 240, 0.85);
        }

        .dark .lj-table th {
            color: #94a3b8;
            border-bottom-color: rgba(30, 41, 59, 0.85);
        }

        .lj-table td {
            padding: 0.6875rem 0.75rem;
            border-bottom: 1px solid rgba(241, 245, 249, 0.9);
            vertical-align: middle;
        }

        .dark .lj-table td {
            border-bottom-color: rgba(30, 41, 59, 0.6);
        }

        .lj-table tr:hover td {
            background-color: rgba(248, 250, 252, 0.8);
        }

        .dark .lj-table tr:hover td {
            background-color: rgba(30, 41, 59, 0.5);
        }
    </style>

    <div class="lj-dash-root">

        {{-- EXECUTIVE COMMAND & TIMELINE FILTER BAR --}}
        <div class="lj-command-bar">
            <div style="display: flex; align-items: center; gap: 0.875rem;">
                <div style="width: 2.375rem; height: 2.375rem; border-radius: 0.625rem; background: linear-gradient(135deg, #0A2E23 0%, #164e3f 100%); display: flex; align-items: center; justify-content: center; color: #C5A059; font-weight: 800; font-size: 1rem; box-shadow: 0 4px 10px rgba(10, 46, 35, 0.35); border: 1px solid rgba(197, 160, 89, 0.3);">
                    LJ
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 0.875rem; font-weight: 700; color: #0f172a; letter-spacing: -0.01em;" class="dark:!text-white">
                            Enterprise Operations & Performance Matrix
                        </span>
                        <span class="lj-badge lj-badge-emerald" style="padding: 0.15rem 0.45rem;">
                            <span style="width: 0.375rem; height: 0.375rem; border-radius: 9999px; background-color: #10b981;"></span>
                            Live Engine
                        </span>
                    </div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.125rem;" class="dark:!text-slate-400">
                        Active Scope: <span style="font-weight: 600; color: #0A2E23;" class="dark:!text-[#C5A059]">{{ $sales['period_title'] }}</span>
                    </div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 0.625rem;">
                <label for="dash-period-select" style="font-size: 0.75rem; font-weight: 600; color: #475569;" class="dark:!text-slate-300">
                    Analytics Period:
                </label>
                <div style="position: relative;">
                    <select
                        id="dash-period-select"
                        wire:model.live="period"
                        style="font-size: 0.8125rem; font-weight: 600; padding: 0.45rem 2.25rem 0.45rem 0.875rem; border-radius: 0.625rem; border: 1px solid #cbd5e1; background-color: #ffffff; color: #0f172a; cursor: pointer; appearance: none; -webkit-appearance: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
                        class="dark:!bg-slate-800 dark:!border-slate-700 dark:!text-slate-100 focus:outline-none focus:ring-2 focus:ring-[#0A2E23] dark:focus:ring-[#C5A059]">
                        @foreach($this->getPeriodOptions() as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <div style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: #64748b;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- 1. TOP TIER: 4 EXECUTIVE KPI CARDS --}}
        <section aria-label="Key Performance Indicators">
            <div class="lj-kpi-grid">

                {{-- KPI 1: Gross Combined Revenue --}}
                <a href="{{ route('filament.admin.resources.orders.index') }}" class="lj-dash-card lj-accent-gold">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;" class="dark:!text-slate-400">Total Revenue</span>
                        <span class="lj-badge lj-badge-gold">
                            POS + Online
                        </span>
                    </div>
                    <div style="margin-top: 0.5rem; font-size: 1.625rem; color: #0f172a;" class="lj-val-serif dark:!text-white">
                        {{ $kpi['revenue']['value'] }}
                    </div>
                    <div style="margin-top: 0.25rem; font-size: 0.75rem; color: #64748b; display: flex; align-items: center; justify-content: space-between;" class="dark:!text-slate-400">
                        <span>{{ $kpi['revenue']['subtext'] }}</span>
                        <span style="font-size: 0.6875rem; font-weight: 600; color: #0A2E23;" class="dark:!text-[#C5A059]">NPR</span>
                    </div>
                </a>

                {{-- KPI 2: Combined Transactions Volume --}}
                <a href="{{ route('filament.admin.resources.orders.index') }}" class="lj-dash-card lj-accent-emerald">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;" class="dark:!text-slate-400">Sales & Orders</span>
                        <span class="lj-badge lj-badge-emerald">
                            Volume
                        </span>
                    </div>
                    <div style="margin-top: 0.5rem; font-size: 1.625rem; color: #0f172a;" class="lj-val-serif dark:!text-white">
                        {{ $kpi['orders']['value'] }}
                    </div>
                    <div style="margin-top: 0.25rem; font-size: 0.75rem; color: #64748b;" class="dark:!text-slate-400">
                        {{ $kpi['orders']['subtext'] }}
                    </div>
                </a>

                {{-- KPI 3: Average Transaction Value --}}
                <a href="{{ route('filament.admin.resources.orders.index') }}" class="lj-dash-card lj-accent-sapphire">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;" class="dark:!text-slate-400">Average Basket</span>
                        <span class="lj-badge lj-badge-sapphire">
                            Blended AOV
                        </span>
                    </div>
                    <div style="margin-top: 0.5rem; font-size: 1.625rem; color: #0f172a;" class="lj-val-serif dark:!text-white">
                        {{ $kpi['aov']['value'] }}
                    </div>
                    <div style="margin-top: 0.25rem; font-size: 0.75rem; color: #64748b;" class="dark:!text-slate-400">
                        {{ $kpi['aov']['subtext'] }}
                    </div>
                </a>

                {{-- KPI 4: Active Patrons & CRM --}}
                <a href="{{ route('filament.admin.resources.customers.index') }}" class="lj-dash-card lj-accent-amethyst">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;" class="dark:!text-slate-400">Patron Base</span>
                        <span class="lj-badge lj-badge-sapphire">
                            Registered
                        </span>
                    </div>
                    <div style="margin-top: 0.5rem; font-size: 1.625rem; color: #0f172a;" class="lj-val-serif dark:!text-white">
                        {{ $kpi['customers']['value'] }}
                    </div>
                    <div style="margin-top: 0.25rem; font-size: 0.75rem; color: #64748b;" class="dark:!text-slate-400">
                        {{ $kpi['customers']['subtext'] }}
                    </div>
                </a>

            </div>
        </section>

        {{-- 2. FINANCIAL & PROCUREMENT MATRIX --}}
        <section aria-label="Financial and Procurement Performance">
            <div class="lj-fin-grid">

                {{-- Item 1: Purchases Total --}}
                <a href="{{ route('filament.admin.resources.purchase-orders.index') }}" class="lj-dash-card" style="padding: 0.75rem 1rem;">
                    <div style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #64748b;" class="dark:!text-slate-400">Procurement</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin-top: 0.25rem;" class="dark:!text-white">
                        {{ $financial['purchases_total'] }}
                    </div>
                    <div style="font-size: 0.6875rem; color: #64748b; margin-top: 0.125rem;" class="dark:!text-slate-400">
                        {{ $financial['purchases_count'] }} POs recorded
                    </div>
                </a>

                {{-- Item 2: Accounts Payable --}}
                <a href="{{ route('filament.admin.pages.receivables-payables') }}" class="lj-dash-card" style="padding: 0.75rem 1rem;">
                    <div style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #dc2626;">Accounts Payable</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #dc2626; margin-top: 0.25rem;">
                        {{ $financial['payables_total'] }}
                    </div>
                    <div style="font-size: 0.6875rem; color: #64748b; margin-top: 0.125rem;" class="dark:!text-slate-400">
                        Supplier Liabilities
                    </div>
                </a>

                {{-- Item 3: Accounts Receivable --}}
                <a href="{{ route('filament.admin.pages.receivables-payables') }}" class="lj-dash-card" style="padding: 0.75rem 1rem;">
                    <div style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #2563eb;">Accounts Receivable</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #2563eb; margin-top: 0.25rem;">
                        {{ $financial['receivables_total'] }}
                    </div>
                    <div style="font-size: 0.6875rem; color: #64748b; margin-top: 0.125rem;" class="dark:!text-slate-400">
                        Pending Collections
                    </div>
                </a>

                {{-- Item 4: Output VAT Collected --}}
                <a href="{{ route('filament.admin.resources.bikri-khata.index') }}" class="lj-dash-card" style="padding: 0.75rem 1rem;">
                    <div style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #d97706;">Output VAT 13%</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #d97706; margin-top: 0.25rem;">
                        {{ $financial['vat_collected'] }}
                    </div>
                    <div style="font-size: 0.6875rem; color: #64748b; margin-top: 0.125rem;" class="dark:!text-slate-400">
                        Bikri Khata Ledger
                    </div>
                </a>

                {{-- Item 5: Fulfillment Delivered vs Cancelled --}}
                <a href="{{ route('filament.admin.resources.orders.index') }}" class="lj-dash-card" style="padding: 0.75rem 1rem;">
                    <div style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #64748b;" class="dark:!text-slate-400">Fulfillment Ratio</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #047857; margin-top: 0.25rem;">
                        {{ $financial['completed_orders'] }} <span style="font-size: 0.75rem; font-weight: 500; color: #64748b;">delivered</span>
                    </div>
                    <div style="font-size: 0.6875rem; color: #be123c; margin-top: 0.125rem;">
                        {{ $financial['cancelled_orders'] }} cancelled
                    </div>
                </a>

            </div>
        </section>

        {{-- 3. MAIN DASHBOARD SPLIT: SALES TRAJECTORY (66%) & ORDER OPERATIONS (34%) --}}
        <section class="lj-main-grid" aria-label="Sales Trajectory and Operations">

            {{-- Left: Interactive Dynamic Vector Sales Chart --}}
            <div class="lj-dash-card" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(226, 232, 240, 0.85);" class="dark:!border-slate-800">
                        <div>
                            <div style="font-size: 0.875rem; font-weight: 700; color: #0f172a; letter-spacing: -0.01em;" class="dark:!text-white">
                                Sales Trajectory & Volume Dynamics
                            </div>
                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.125rem;" class="dark:!text-slate-400">
                                Integrated Showroom POS & E-Commerce Transactions
                            </div>
                        </div>

                        <div style="text-align: right;">
                            <div style="font-size: 1.125rem; font-weight: 700; color: #0f172a;" class="lj-val-serif dark:!text-white">
                                {{ $sales['period_revenue'] }}
                            </div>
                            <div style="font-size: 0.6875rem; color: #64748b;" class="dark:!text-slate-400">
                                {{ $sales['period_orders'] }} transactions in scope
                            </div>
                        </div>
                    </div>

                    {{-- High Precision Vector SVG Chart --}}
                    <div style="margin-top: 1rem; position: relative; height: 230px; width: 100%;">
                        @php
                        $points = $sales['points'];
                        $count = count($points);
                        $max = $sales['max_revenue'] > 0 ? $sales['max_revenue'] : 1;
                        $width = 800;
                        $height = 210;
                        $paddingX = 30;
                        $paddingTop = 15;
                        $paddingBottom = 25;
                        $chartH = $height - $paddingTop - $paddingBottom;
                        $chartW = $width - ($paddingX * 2);

                        $coords = [];
                        foreach ($points as $idx => $pt) {
                            $x = $paddingX + ($count > 1 ? ($idx / ($count - 1)) * $chartW : ($chartW / 2));
                            $y = $paddingTop + $chartH - (($pt['revenue'] / $max) * $chartH);
                            $coords[] = [
                                'x' => $x,
                                'y' => $y,
                                'label' => $pt['label'],
                                'full_label' => $pt['full_label'] ?? $pt['label'],
                                'rev' => $pt['revenue'],
                                'pos_rev' => $pt['pos_revenue'] ?? 0,
                                'onl_rev' => $pt['online_revenue'] ?? 0,
                                'orders' => $pt['orders'],
                            ];
                        }

                        $polyPoints = [];
                        foreach ($coords as $c) {
                            $polyPoints[] = "{$c['x']},{$c['y']}";
                        }
                        $linePath = implode(' ', $polyPoints);
                        $firstX = $coords[0]['x'] ?? $paddingX;
                        $lastX = end($coords)['x'] ?? ($width - $paddingX);
                        $bottomY = $paddingTop + $chartH;
                        $areaPath = "{$firstX},{$bottomY} " . $linePath . " {$lastX},{$bottomY}";
                        @endphp

                        <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" style="width: 100%; height: 100%; overflow: visible;">
                            <defs>
                                <linearGradient id="ljSalesExecutiveGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" stop-color="#C5A059" stop-opacity="0.35" />
                                    <stop offset="50%" stop-color="#10B981" stop-opacity="0.15" />
                                    <stop offset="100%" stop-color="#0A2E23" stop-opacity="0.01" />
                                </linearGradient>
                            </defs>

                            {{-- Grid Reference Lines --}}
                            <line x1="{{ $paddingX }}" y1="{{ $paddingTop }}" x2="{{ $width - $paddingX }}" y2="{{ $paddingTop }}" stroke="currentColor" stroke-opacity="0.08" stroke-dasharray="4 4" />
                            <line x1="{{ $paddingX }}" y1="{{ $paddingTop + ($chartH / 2) }}" x2="{{ $width - $paddingX }}" y2="{{ $paddingTop + ($chartH / 2) }}" stroke="currentColor" stroke-opacity="0.08" stroke-dasharray="4 4" />
                            <line x1="{{ $paddingX }}" y1="{{ $bottomY }}" x2="{{ $width - $paddingX }}" y2="{{ $bottomY }}" stroke="currentColor" stroke-opacity="0.12" />

                            {{-- Shimmer Gradient Area --}}
                            <polygon points="{{ $areaPath }}" fill="url(#ljSalesExecutiveGrad)" />

                            {{-- Main Trajectory Polyline --}}
                            <polyline points="{{ $linePath }}" fill="none" stroke="#C5A059" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round" />

                            {{-- High Precision Data Markers --}}
                            @foreach($coords as $c)
                            <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="{{ $c['rev'] > 0 ? 4.5 : 2.5 }}" fill="{{ $c['rev'] > 0 ? '#0A2E23' : '#C5A059' }}" stroke="#C5A059" stroke-width="2" style="cursor: pointer; transition: all 0.15s ease;">
                                <title>{{ $c['full_label'] }}: {{ \App\Helpers\NepaliNumberHelper::formatCurrency($c['rev'], 'Rs. ', 0) }} ({{ \App\Helpers\NepaliNumberHelper::format($c['orders']) }} sales | POS: {{ \App\Helpers\NepaliNumberHelper::formatCurrency($c['pos_rev'], 'Rs. ', 0) }}, Online: {{ \App\Helpers\NepaliNumberHelper::formatCurrency($c['onl_rev'], 'Rs. ', 0) }})</title>
                            </circle>
                            @endforeach
                        </svg>
                    </div>
                </div>

                {{-- Timeline Date Points --}}
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.6875rem; color: #64748b; padding-top: 0.5rem; border-top: 1px solid rgba(226, 232, 240, 0.85);" class="dark:!border-slate-800 dark:!text-slate-400">
                    @if(count($points) <= 12)
                        @foreach($points as $pt)
                        <span style="font-weight: 500;">{{ $pt['label'] }}</span>
                        @endforeach
                    @else
                        <span style="font-weight: 500;">{{ $points[0]['label'] ?? '' }}</span>
                        <span style="font-weight: 500;">{{ $points[intval(count($points) * 0.25)]['label'] ?? '' }}</span>
                        <span style="font-weight: 500;">{{ $points[intval(count($points) * 0.5)]['label'] ?? '' }}</span>
                        <span style="font-weight: 500;">{{ $points[intval(count($points) * 0.75)]['label'] ?? '' }}</span>
                        <span style="font-weight: 500;">{{ end($points)['label'] ?? '' }}</span>
                    @endif
                </div>
            </div>

            {{-- Right: Live Order Operations Pipeline --}}
            <div class="lj-dash-card" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(226, 232, 240, 0.85);" class="dark:!border-slate-800">
                        <div>
                            <div style="font-size: 0.875rem; font-weight: 700; color: #0f172a; letter-spacing: -0.01em;" class="dark:!text-white">
                                Order Operations
                            </div>
                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.125rem;" class="dark:!text-slate-400">
                                Live Fulfillment Queue
                            </div>
                        </div>
                        <span class="lj-badge lj-badge-sapphire">Live Queue</span>
                    </div>

                    {{-- Operations Queue Items --}}
                    <div style="margin-top: 0.75rem; display: flex; flex-direction: column; gap: 0.35rem;">
                        @foreach($orderOps as $op)
                        <a
                            href="{{ $op['url'] }}"
                            style="display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0.625rem; border-radius: 0.625rem; text-decoration: none; color: inherit; transition: all 0.15s ease;"
                            class="hover:bg-slate-50 dark:hover:bg-slate-800/60 border border-transparent hover:border-slate-200 dark:hover:border-slate-700">
                            <div style="display: flex; align-items: center; gap: 0.625rem; min-width: 0;">
                                <span style="width: 0.5625rem; height: 0.5625rem; border-radius: 9999px; flex-shrink: 0;" class="{{ $op['color'] }}"></span>
                                <span style="font-size: 0.8125rem; font-weight: 500; color: #334155;" class="dark:!text-slate-200">
                                    {{ $op['status'] }}
                                </span>
                            </div>

                            <div style="display: flex; align-items: center; gap: 0.625rem; flex-shrink: 0;">
                                <span style="font-size: 0.8125rem; font-family: monospace; font-weight: 700; color: #0f172a;" class="dark:!text-white">
                                    {{ \App\Helpers\NepaliNumberHelper::format($op['count']) }}
                                </span>
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>

                <div style="padding-top: 0.75rem; border-top: 1px solid rgba(226, 232, 240, 0.85); text-align: right;" class="dark:!border-slate-800">
                    <a href="{{ route('filament.admin.resources.orders.index') }}" class="lj-action-link">
                        <span>Access Complete Order Log</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                </div>
            </div>

        </section>

        {{-- 4. TRIPLE INTELLIGENCE GRID: ATTENTION, INVENTORY & PATRON LOYALTY --}}
        <section class="lj-tri-grid" aria-label="Operational Insights">

            {{-- Column 1: Actionable Priority Alerts --}}
            <div class="lj-dash-card" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(226, 232, 240, 0.85);" class="dark:!border-slate-800">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 0.875rem; font-weight: 700; color: #0f172a;" class="dark:!text-white">Priority Alerts</span>
                            @if(count($attention) > 0)
                            <span class="lj-badge lj-badge-amber" style="font-size: 0.625rem; padding: 0.1rem 0.375rem;">
                                {{ count($attention) }} Actionable
                            </span>
                            @endif
                        </div>
                    </div>

                    <div style="margin-top: 0.75rem; display: flex; flex-direction: column; gap: 0.45rem;">
                        @forelse($attention as $att)
                        <a
                            href="{{ $att['url'] }}"
                            style="display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0.625rem; border-radius: 0.5rem; text-decoration: none; color: inherit; transition: all 0.15s ease;"
                            class="hover:bg-slate-50 dark:hover:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                            <div style="display: flex; align-items: center; gap: 0.5rem; min-width: 0;">
                                <span style="font-size: 0.875rem; flex-shrink: 0;" class="{{ $att['color'] }}">{{ $att['icon'] }}</span>
                                <span style="font-size: 0.75rem; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500;" class="dark:!text-slate-200">
                                    {{ $att['text'] }}
                                </span>
                            </div>
                            <span style="font-size: 0.6875rem; font-weight: 600; color: #0A2E23; flex-shrink: 0;" class="dark:!text-[#C5A059]">Resolve →</span>
                        </a>
                        @empty
                        <div style="padding: 1.5rem 0; text-align: center;">
                            <div style="font-size: 0.8125rem; font-weight: 600; color: #059669; display: flex; align-items: center; justify-content: center; gap: 0.35rem;">
                                <span style="display: inline-flex; width: 1.25rem; height: 1.25rem; border-radius: 9999px; background: rgba(16, 185, 129, 0.15); align-items: center; justify-content: center;">✓</span>
                                <span>All operational parameters within standard tolerance</span>
                            </div>
                        </div>
                        @endforelse
                    </div>
                </div>

                <div style="padding-top: 0.75rem; border-top: 1px solid rgba(226, 232, 240, 0.85); font-size: 0.6875rem; color: #64748b;" class="dark:!border-slate-800 dark:!text-slate-400">
                    Real-time exception monitor
                </div>
            </div>

            {{-- Column 2: Central Inventory Status --}}
            <div class="lj-dash-card" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(226, 232, 240, 0.85);" class="dark:!border-slate-800">
                        <div style="font-size: 0.875rem; font-weight: 700; color: #0f172a;" class="dark:!text-white">Central Inventory</div>
                        <span class="lj-badge lj-badge-emerald">Stock Health</span>
                    </div>

                    <div style="margin-top: 0.75rem; display: flex; flex-direction: column; gap: 0.35rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.35rem 0; font-size: 0.8125rem; border-bottom: 1px solid rgba(241, 245, 249, 0.9);" class="dark:!border-slate-800">
                            <span style="color: #475569;" class="dark:!text-slate-300">Catalog Products</span>
                            <span style="font-family: monospace; font-weight: 700; color: #0f172a;" class="dark:!text-white">{{ \App\Helpers\NepaliNumberHelper::format($inventory['products']) }}</span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.35rem 0; font-size: 0.8125rem; border-bottom: 1px solid rgba(241, 245, 249, 0.9);" class="dark:!border-slate-800">
                            <span style="color: #475569;" class="dark:!text-slate-300">Active SKUs / Variants</span>
                            <span style="font-family: monospace; font-weight: 700; color: #0f172a;" class="dark:!text-white">{{ \App\Helpers\NepaliNumberHelper::format($inventory['variants']) }}</span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.35rem 0; font-size: 0.8125rem; border-bottom: 1px solid rgba(241, 245, 249, 0.9);" class="dark:!border-slate-800">
                            <span style="color: #475569;" class="dark:!text-slate-300">Total Physical Units</span>
                            <span style="font-family: monospace; font-weight: 700; color: #047857;" class="dark:!text-emerald-400">{{ \App\Helpers\NepaliNumberHelper::format($inventory['total_stock']) }}</span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.35rem 0; font-size: 0.8125rem; border-bottom: 1px solid rgba(241, 245, 249, 0.9);" class="dark:!border-slate-800">
                            <span style="color: #475569;" class="dark:!text-slate-300">Low Stock (1–5 units)</span>
                            <span style="font-family: monospace; font-weight: 700;" class="{{ $inventory['low_stock'] > 0 ? 'text-amber-600' : 'text-slate-900 dark:!text-white' }}">{{ \App\Helpers\NepaliNumberHelper::format($inventory['low_stock']) }}</span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.35rem 0; font-size: 0.8125rem;">
                            <span style="color: #475569;" class="dark:!text-slate-300">Out of Stock (0 units)</span>
                            <span style="font-family: monospace; font-weight: 700;" class="{{ $inventory['out_of_stock'] > 0 ? 'text-rose-600' : 'text-slate-900 dark:!text-white' }}">{{ \App\Helpers\NepaliNumberHelper::format($inventory['out_of_stock']) }}</span>
                        </div>
                    </div>
                </div>

                <div style="padding-top: 0.75rem; border-top: 1px solid rgba(226, 232, 240, 0.85); text-align: right;" class="dark:!border-slate-800">
                    <a href="{{ route('filament.admin.resources.stock-levels.index') }}" class="lj-action-link">
                        <span>Manage Stock Levels</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                </div>
            </div>

            {{-- Column 3: Patron Loyalty & Engagement --}}
            <div class="lj-dash-card" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(226, 232, 240, 0.85);" class="dark:!border-slate-800">
                        <div style="font-size: 0.875rem; font-weight: 700; color: #0f172a;" class="dark:!text-white">Patron Engagement</div>
                        <span class="lj-badge lj-badge-sapphire">Loyalty</span>
                    </div>

                    <div style="margin-top: 0.75rem; display: flex; flex-direction: column; gap: 0.35rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.35rem 0; font-size: 0.8125rem; border-bottom: 1px solid rgba(241, 245, 249, 0.9);" class="dark:!border-slate-800">
                            <span style="color: #475569;" class="dark:!text-slate-300">New Patrons Acquired</span>
                            <span style="font-family: monospace; font-weight: 700; color: #059669;">+{{ \App\Helpers\NepaliNumberHelper::format($customerActivity['new_customers']) }}</span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.35rem 0; font-size: 0.8125rem; border-bottom: 1px solid rgba(241, 245, 249, 0.9);" class="dark:!border-slate-800">
                            <span style="color: #475569;" class="dark:!text-slate-300">Repeat Customers</span>
                            <span style="font-family: monospace; font-weight: 700; color: #0f172a;" class="dark:!text-white">{{ \App\Helpers\NepaliNumberHelper::format($customerActivity['returning_customers']) }}</span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.35rem 0; font-size: 0.8125rem;">
                            <span style="color: #475569;" class="dark:!text-slate-300">Repeat Orders Placed</span>
                            <span style="font-family: monospace; font-weight: 700; color: #0f172a;" class="dark:!text-white">{{ \App\Helpers\NepaliNumberHelper::format($customerActivity['repeat_orders']) }}</span>
                        </div>
                    </div>
                </div>

                <div style="padding-top: 0.75rem; border-top: 1px solid rgba(226, 232, 240, 0.85); text-align: right;" class="dark:!border-slate-800">
                    <a href="{{ route('filament.admin.resources.customers.index') }}" class="lj-action-link">
                        <span>View Customer Profiles</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                </div>
            </div>

        </section>

        {{-- 5. RECENT TRANSACTIONS LEDGER TABLE --}}
        <section class="lj-dash-card" aria-label="Recent Storefront Transactions">
            <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 0.875rem; border-bottom: 1px solid rgba(226, 232, 240, 0.85);" class="dark:!border-slate-800">
                <div>
                    <div style="font-size: 0.9375rem; font-weight: 700; color: #0f172a; letter-spacing: -0.01em;" class="dark:!text-white">
                        Recent Storefront Transactions
                    </div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.125rem;" class="dark:!text-slate-400">
                        Live checkout and customer orders
                    </div>
                </div>

                <a href="{{ route('filament.admin.resources.orders.index') }}" class="lj-action-link">
                    <span>View All Orders</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </a>
            </div>

            <div style="overflow-x: auto; width: 100%; margin-top: 0.5rem;">
                <table class="lj-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th class="hidden sm:table-cell">Customer</th>
                            <th>Amount</th>
                            <th class="hidden md:table-cell">Payment</th>
                            <th>Status</th>
                            <th style="text-align: right;" class="hidden lg:table-cell">Timestamp</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                        <tr>
                            <td style="font-family: monospace; font-weight: 700; color: #0f172a;" class="dark:!text-slate-100">
                                {{ $order->order_number }}
                                <div style="font-size: 0.6875rem; color: #64748b; font-family: sans-serif; font-weight: normal;" class="sm:hidden">
                                    {{ $order->first_name }} {{ $order->last_name }}
                                </div>
                            </td>
                            <td style="color: #334155; font-weight: 500;" class="dark:!text-slate-200 hidden sm:table-cell">
                                {{ $order->first_name }} {{ $order->last_name }}
                            </td>
                            <td style="font-weight: 700; color: #0f172a;" class="dark:!text-white">
                                {{ \App\Helpers\NepaliNumberHelper::formatCurrency($order->total_amount, 'Rs. ', 2) }}
                            </td>
                            <td class="hidden md:table-cell">
                                @if($order->payment_status === 'paid')
                                <span class="lj-badge lj-badge-emerald">Paid</span>
                                @elseif($order->payment_status === 'pending')
                                <span class="lj-badge lj-badge-amber">Pending</span>
                                @else
                                <span class="lj-badge lj-badge-rose">{{ ucfirst($order->payment_status ?? 'failed') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="lj-badge" style="background-color: #f1f5f9; color: #334155;" class="dark:!bg-slate-800 dark:!text-slate-200">
                                    {{ \App\Models\Order::getStatuses()[$order->status] ?? ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </td>
                            <td style="text-align: right; color: #64748b; font-family: monospace; font-size: 0.6875rem;" class="hidden lg:table-cell dark:!text-slate-400">
                                {{ $order->created_at ? $order->created_at->format('d M Y, H:i') : '—' }}
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('filament.admin.resources.orders.view', $order) }}" class="lj-action-link">
                                    <span>Inspect</span>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" style="padding: 2rem 0; text-align: center; color: #94a3b8;">
                                No recent orders recorded.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</x-filament-panels::page>