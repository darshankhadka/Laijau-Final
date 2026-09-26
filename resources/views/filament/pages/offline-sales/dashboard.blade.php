        @if($activeTab === 'dashboard')
        @php
            $dashKpis = $dash['period_kpis'] ?? [];
            $payAnalytics = $dash['payment_analytics'] ?? [];
            $cashVsDigital = $payAnalytics['cash_vs_digital'] ?? [];
            $drawer = $payAnalytics['drawer_reconciliation'] ?? [];
            $methods = $payAnalytics['methods_breakdown'] ?? [];
            $hourly = $dash['hourly_distribution'] ?? [];
            $peakTraffic = $dash['peak_traffic'] ?? [];
            $staffPerf = $dash['staff_performance'] ?? [];
            $channelAnalytics = $dash['channel_analytics'] ?? [];
            $customerInsights = $dash['customer_insights'] ?? [];
            $topProducts = $dash['top_products'] ?? [];
            $recentTx = $dash['recent_transactions'] ?? [];
            $staffList = $this->dashboardStaffList;
            $selectedPeriod = $this->dashboardPeriod;
        @endphp

        <div class="lj-pos-dash-wrap">

            <!-- 1. DASHBOARD CONTROL & FILTER TOOLBAR -->
            <div class="lj-pos-dash-header">
                <div class="lj-pos-dash-title-wrap">
                    <div class="lj-pos-dash-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--lj-emerald);"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        <span>POS Register & Sales Intelligence</span>
                        <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 9999px; background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; display: inline-flex; align-items: center; gap: 0.25rem;">
                            <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #10B981; animation: pulse 2s infinite;"></span>
                            Live Terminal
                        </span>
                    </div>
                    <div class="lj-pos-dash-subtitle" style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <span>{{ $dash['period_label'] ?? 'Today' }} · Showing analytics for {{ $this->dashboardStaffName ? 'Cashier: ' . $this->dashboardStaffName : 'All Showroom Staff' }}</span>
                        <span style="color: var(--lj-text-muted);">·</span>
                        <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-weight: 600; color: #059669; font-size: 0.6875rem; background: #ECFDF5; padding: 0.1rem 0.45rem; border-radius: 4px; border: 1px solid #A7F3D0;">
                            Asia/Kathmandu (NPT UTC+5:45)
                        </span>
                    </div>
                </div>

                <div class="lj-pos-dash-controls">
                    <!-- Quick Period Buttons -->
                    <div style="display: flex; align-items: center; gap: 0.25rem; flex-wrap: wrap;">
                        <button
                            type="button"
                            wire:click="setDashboardPeriod('today')"
                            class="lj-pos-period-btn {{ $selectedPeriod === 'today' ? 'active' : '' }}">
                            Today
                        </button>
                        <button
                            type="button"
                            wire:click="setDashboardPeriod('yesterday')"
                            class="lj-pos-period-btn {{ $selectedPeriod === 'yesterday' ? 'active' : '' }}">
                            Yesterday
                        </button>
                        <button
                            type="button"
                            wire:click="setDashboardPeriod('week')"
                            class="lj-pos-period-btn {{ $selectedPeriod === 'week' ? 'active' : '' }}">
                            Last 7 Days
                        </button>
                        <button
                            type="button"
                            wire:click="setDashboardPeriod('month')"
                            class="lj-pos-period-btn {{ $selectedPeriod === 'month' ? 'active' : '' }}">
                            This Month
                        </button>
                        <button
                            type="button"
                            wire:click="setDashboardPeriod('last_month')"
                            class="lj-pos-period-btn {{ $selectedPeriod === 'last_month' ? 'active' : '' }}">
                            Last Month
                        </button>
                        <button
                            type="button"
                            wire:click="setDashboardPeriod('year')"
                            class="lj-pos-period-btn {{ $selectedPeriod === 'year' ? 'active' : '' }}">
                            This Year
                        </button>
                        <button
                            type="button"
                            wire:click="setDashboardPeriod('all')"
                            class="lj-pos-period-btn {{ $selectedPeriod === 'all' ? 'active' : '' }}">
                            All Time
                        </button>
                    </div>

                    <!-- Staff Filter -->
                    <select
                        wire:model.live="dashboardStaffName"
                        class="lj-pos-dash-select"
                        title="Filter by Cashier / Staff">
                        <option value="">All Staff / Cashiers</option>
                        @foreach($staffList as $stName)
                        <option value="{{ $stName }}">{{ $stName }}</option>
                        @endforeach
                    </select>

                    <!-- Payment Method Filter -->
                    <select
                        wire:model.live="dashboardPaymentMethod"
                        class="lj-pos-dash-select"
                        title="Filter by Payment Method">
                        <option value="">All Payment Methods</option>
                        <option value="cash">Cash Only</option>
                        <option value="fonepay">Fonepay QR</option>
                        <option value="split">Split (Cash + QR)</option>
                        <option value="esewa">eSewa QR</option>
                        <option value="khalti">Khalti QR</option>
                        <option value="card">Card Terminal (POS)</option>
                        <option value="bank_transfer">Bank Wire / ConnectIPS</option>
                    </select>

                    <!-- Reset Filters Button (if filtered) -->
                    @if($this->dashboardStaffName || $this->dashboardPaymentMethod || $selectedPeriod !== 'today')
                    <button
                        type="button"
                        wire:click="resetDashboardFilters"
                        class="lj-pos-period-btn"
                        style="color: var(--lj-rose); border-color: var(--lj-rose);"
                        title="Reset all filters to Today">
                        Reset
                    </button>
                    @endif

                    <!-- Print Shift Closing Register Report -->
                    <button
                        type="button"
                        wire:click="openModal('shift_report')"
                        class="lj-pos-period-btn"
                        style="background: #ECFDF5; color: #059669; border-color: #A7F3D0; display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 700; cursor: pointer;"
                        title="Review and Print Daily / Shift Closing Register Report">
                        <svg style="width: 15px; height: 15px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span>Print Shift Report</span>
                    </button>
                </div>
            </div>

            <!-- SHOWROOM TERMINALS DAILY STATUS -->
            @php
                $termsStatus = $this->allTerminalsStatus;
            @endphp
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 0.75rem; margin-bottom: 1rem;">
                @foreach($termsStatus as $tStat)
                @php
                    $tObj = $tStat['terminal'];
                    $tState = $tStat['state'];
                    $tSess = $tStat['session'];
                    $isOverdue = $tStat['overdue_session'] !== null;
                    $isSelected = $this->selectedStationId == $tObj->id;
                @endphp
                <div style="background: var(--lj-card); border: 1.5px solid {{ $isSelected ? 'var(--lj-emerald)' : 'var(--lj-border)' }}; border-radius: 0.75rem; padding: 0.85rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 38px; height: 38px; border-radius: 0.5rem; background: {{ $isSelected ? '#ECFDF5' : '#F1F5F9' }}; display: flex; align-items: center; justify-content: center; color: {{ $isSelected ? '#059669' : '#64748B' }};">
                            <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                                <line x1="8" y1="21" x2="16" y2="21" />
                                <line x1="12" y1="17" x2="12" y2="21" />
                            </svg>
                        </div>
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.4rem;">
                                <span style="font-weight: 800; font-size: 0.875rem; color: var(--lj-text);">{{ $tObj->name }}</span>
                                @if($isSelected)
                                <span style="font-size: 0.65rem; background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 700;">Selected</span>
                                @endif
                            </div>
                            <div style="font-size: 0.75rem; color: var(--lj-text-muted); margin-top: 0.15rem;">
                                @if($isOverdue)
                                    <span style="color: #DC2626; font-weight: 700;">Overdue Closing Required</span>
                                @elseif($tState === 'active')
                                    <span style="color: #059669; font-weight: 700;">● Register Open (Float: Rs. {{ number_format($tSess?->opening_balance ?? 0) }})</span>
                                @elseif($tState === 'opening_required')
                                    <span style="color: #D97706; font-weight: 700;">○ Opening Balance Required</span>
                                @elseif($tState === 'closed')
                                    <span style="color: #6B7280; font-weight: 700;">Session Closed</span>
                                @else
                                    <span style="color: var(--lj-text-muted);">{{ ucfirst($tState) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.35rem;">
                        @if(!$isSelected)
                        <button
                            type="button"
                            wire:click="setTerminal({{ $tObj->id }})"
                            class="lj-btn-secondary"
                            style="font-size: 0.75rem; padding: 0.35rem 0.65rem; height: auto;"
                            title="Switch active dashboard context to {{ $tObj->name }}">
                            Switch
                        </button>
                        @endif

                        @if($isOverdue)
                        <button
                            type="button"
                            wire:click="openCloseSessionModal({{ $tStat['overdue_session']->id }})"
                            class="lj-checkout-btn"
                            style="width: auto; height: 32px; padding: 0 0.75rem; font-size: 0.75rem; background: #DC2626; color: #fff;">
                            Resolve Overdue
                        </button>
                        @elseif($tState === 'opening_required')
                        <button
                            type="button"
                            wire:click="openSessionModal({{ $tObj->id }})"
                            class="lj-checkout-btn"
                            style="width: auto; height: 32px; padding: 0 0.75rem; font-size: 0.75rem; background: #059669; color: #fff;">
                            Open Float
                        </button>
                        @elseif($tState === 'active')
                        <button
                            type="button"
                            wire:click="openCloseSessionModal({{ $tSess?->id }})"
                            class="lj-btn-secondary"
                            style="height: 32px; padding: 0 0.75rem; font-size: 0.75rem; color: #DC2626; border-color: #FECACA;">
                            Close Register
                        </button>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            <!-- 2. HIGH-IMPACT KPI SCORECARDS (8 PILLARS) -->
            <div class="lj-pos-kpi-grid">
                <!-- 1. Gross Revenue -->
                <div class="lj-pos-kpi-card" style="border-left: 4px solid var(--lj-emerald);">
                    <div>
                        <div class="lj-pos-kpi-label">
                            <span>Total POS Revenue</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--lj-emerald);"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                        <div class="lj-pos-kpi-value" style="color: var(--lj-emerald);">
                            {{ $currSymbol }}{{ number_format($dashKpis['revenue'] ?? 0, 2) }}
                        </div>
                    </div>
                    <div class="lj-pos-kpi-subtext">
                        @if(($dashKpis['revenue_growth_pct'] ?? null) !== null)
                        <span class="lj-pos-growth-badge {{ $dashKpis['revenue_growth_pct'] >= 0 ? 'lj-pos-growth-up' : 'lj-pos-growth-down' }}">
                            {{ $dashKpis['revenue_growth_pct'] >= 0 ? '▲ +' : '▼ ' }}{{ $dashKpis['revenue_growth_pct'] }}%
                        </span>
                        <span>vs previous period</span>
                        @else
                        <span>From {{ number_format($dashKpis['orders_count'] ?? 0) }} completed transactions</span>
                        @endif
                    </div>
                </div>

                <!-- 2. Physical Cash in Drawer -->
                <div class="lj-pos-kpi-card" style="border-left: 4px solid #059669;">
                    <div>
                        <div class="lj-pos-kpi-label">
                            <span>Physical Cash in Till</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #059669;"><rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                        </div>
                        <div class="lj-pos-kpi-value" style="color: #059669;">
                            {{ $currSymbol }}{{ number_format($cashVsDigital['physical_cash_total'] ?? 0, 2) }}
                        </div>
                    </div>
                    <div class="lj-pos-kpi-subtext">
                        <span style="font-weight: 700; color: #059669;">{{ $cashVsDigital['physical_cash_pct'] ?? 0 }}% of total</span>
                        <span>· {{ $cashVsDigital['physical_cash_count'] ?? 0 }} cash receipts</span>
                    </div>
                </div>

                <!-- 3. Digital QR & Card Payments -->
                <div class="lj-pos-kpi-card" style="border-left: 4px solid #2563EB;">
                    <div>
                        <div class="lj-pos-kpi-label">
                            <span>Digital (QR & Card)</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #2563EB;"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                        </div>
                        <div class="lj-pos-kpi-value" style="color: #2563EB;">
                            {{ $currSymbol }}{{ number_format($cashVsDigital['digital_total'] ?? 0, 2) }}
                        </div>
                    </div>
                    <div class="lj-pos-kpi-subtext">
                        <span style="font-weight: 700; color: #2563EB;">{{ $cashVsDigital['digital_pct'] ?? 0 }}% digital</span>
                        <span>· Fonepay, eSewa, Wire</span>
                    </div>
                </div>

                <!-- 4. Average Order Value (AOV) -->
                <div class="lj-pos-kpi-card">
                    <div>
                        <div class="lj-pos-kpi-label">
                            <span>Average Ticket (AOV)</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #64748B;"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
                        </div>
                        <div class="lj-pos-kpi-value">
                            {{ $currSymbol }}{{ number_format($dashKpis['aov'] ?? 0, 2) }}
                        </div>
                    </div>
                    <div class="lj-pos-kpi-subtext">
                        <span>Avg customer spend per visit</span>
                    </div>
                </div>

                <!-- 5. Units Sold & Basket Depth -->
                <div class="lj-pos-kpi-card">
                    <div>
                        <div class="lj-pos-kpi-label">
                            <span>Units Sold (UPT)</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #64748B;"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                        </div>
                        <div class="lj-pos-kpi-value">
                            {{ number_format($dashKpis['units_sold'] ?? 0) }} <span style="font-size: 1rem; font-weight: 600; color: var(--lj-text-muted);">pcs</span>
                        </div>
                    </div>
                    <div class="lj-pos-kpi-subtext">
                        <span>Avg {{ $dashKpis['upt'] ?? 0 }} items per order ticket</span>
                    </div>
                </div>

                <!-- 6. Total Discounts Granted -->
                <div class="lj-pos-kpi-card" style="border-left: 4px solid var(--lj-amber);">
                    <div>
                        <div class="lj-pos-kpi-label">
                            <span>Discounts Granted</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--lj-amber);"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                        </div>
                        <div class="lj-pos-kpi-value" style="color: var(--lj-amber);">
                            {{ $currSymbol }}{{ number_format($dashKpis['discount_total'] ?? 0, 2) }}
                        </div>
                    </div>
                    <div class="lj-pos-kpi-subtext">
                        <span>{{ $dashKpis['discount_rate_pct'] ?? 0 }}% courtesy discount rate</span>
                    </div>
                </div>

                <!-- 7. Realized Gross Margin (Restricted) -->
                @if($canSeeMargins)
                <div class="lj-pos-kpi-card" style="border-left: 4px solid #10B981;">
                    <div>
                        <div class="lj-pos-kpi-label">
                            <span>Realized Gross Profit</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #059669;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                        </div>
                        <div class="lj-pos-kpi-value" style="color: #059669;">
                            {{ $currSymbol }}{{ number_format($dashKpis['profit_npr'] ?? 0, 2) }}
                        </div>
                    </div>
                    <div class="lj-pos-kpi-subtext">
                        <span style="font-weight: 700; color: #059669;">{{ number_format($dashKpis['margin_pct'] ?? 0, 1) }}% Gross Margin</span>
                    </div>
                </div>
                @else
                <div class="lj-pos-kpi-card">
                    <div>
                        <div class="lj-pos-kpi-label">
                            <span>Total Receipts</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #64748B;"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="12" y2="16"/></svg>
                        </div>
                        <div class="lj-pos-kpi-value">
                            {{ number_format($dashKpis['orders_count'] ?? 0) }}
                        </div>
                    </div>
                    <div class="lj-pos-kpi-subtext">
                        <span>Successfully tendered sales</span>
                    </div>
                </div>
                @endif

                <!-- 8. Voided / Returns Volume -->
                <div class="lj-pos-kpi-card" style="border-left: 4px solid var(--lj-rose);">
                    <div>
                        <div class="lj-pos-kpi-label">
                            <span>Voided / Cancelled</span>
                            <span style="font-size: 1rem;">↩️</span>
                        </div>
                        <div class="lj-pos-kpi-value" style="color: var(--lj-rose);">
                            {{ $dashKpis['voided_count'] ?? 0 }} <span style="font-size: 0.9rem; font-weight: 600; color: var(--lj-text-muted);">sales</span>
                        </div>
                    </div>
                    <div class="lj-pos-kpi-subtext">
                        <span>{{ $currSymbol }}{{ number_format($dashKpis['voided_amount'] ?? 0, 2) }} voided</span>
                    </div>
                </div>
            </div>

            <!-- 3. DEEP-DIVE 2-COLUMN ANALYTICS SECTION -->
            <div class="lj-pos-analytics-2col">

                <!-- LEFT COLUMN: PAYMENT METHOD INTELLIGENCE ("HOW IT WAS PAID") -->
                <div style="display: flex; flex-direction: column; gap: 1rem;">

                    <!-- Card A: Cash vs Digital Split & Till Reconciliation -->
                    <div class="lj-pos-card">
                        <div class="lj-pos-card-header">
                            <div class="lj-pos-card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--lj-emerald);"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg>
                                <span>Cash vs. Digital Split & Till Reconciliation</span>
                            </div>
                            <span style="font-size: 0.6875rem; font-weight: 700; color: var(--lj-text-muted);">
                                Exact Register Reconciliation
                            </span>
                        </div>
                        <div class="lj-pos-card-body">
                            <!-- Dual Color Bar Indicator -->
                            <div>
                                <div style="display: flex; justify-content: space-between; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem;">
                                    <span style="color: #059669;">Cash ({{ $cashVsDigital['physical_cash_pct'] ?? 0 }}%)</span>
                                    <span style="color: #2563EB;">Digital ({{ $cashVsDigital['digital_pct'] ?? 0 }}%)</span>
                                </div>
                                <div class="lj-pos-split-bar-wrap">
                                    <div class="lj-pos-split-bar-cash" style="width: {{ $cashVsDigital['physical_cash_pct'] ?? 0 }}%;"></div>
                                    <div class="lj-pos-split-bar-digital" style="width: {{ $cashVsDigital['digital_pct'] ?? 0 }}%;"></div>
                                </div>
                            </div>

                            <!-- Two Comparison Boxes -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <!-- Physical Cash Box -->
                                <div style="background: var(--lj-card-subtle); border: 1px solid var(--lj-border); border-radius: 0.625rem; padding: 0.875rem;">
                                    <div style="font-size: 0.6875rem; font-weight: 700; color: #059669; text-transform: uppercase;">
                                        Physical Currency
                                    </div>
                                    <div style="font-size: 1.35rem; font-weight: 900; color: var(--lj-text); margin-top: 0.25rem;">
                                        {{ $currSymbol }}{{ number_format($cashVsDigital['physical_cash_total'] ?? 0, 2) }}
                                    </div>
                                    <div style="font-size: 0.6875rem; color: var(--lj-text-muted); margin-top: 0.25rem;">
                                        {{ $cashVsDigital['physical_cash_count'] ?? 0 }} total transactions
                                    </div>
                                </div>

                                <!-- Digital Box -->
                                <div style="background: var(--lj-card-subtle); border: 1px solid var(--lj-border); border-radius: 0.625rem; padding: 0.875rem;">
                                    <div style="font-size: 0.6875rem; font-weight: 700; color: #2563EB; text-transform: uppercase;">
                                        Digital QR & Cards
                                    </div>
                                    <div style="font-size: 1.35rem; font-weight: 900; color: var(--lj-text); margin-top: 0.25rem;">
                                        {{ $currSymbol }}{{ number_format($cashVsDigital['digital_total'] ?? 0, 2) }}
                                    </div>
                                    <div style="font-size: 0.6875rem; color: var(--lj-text-muted); margin-top: 0.25rem;">
                                        {{ $cashVsDigital['digital_count'] ?? 0 }} electronic payments
                                    </div>
                                </div>
                            </div>

                            <!-- Cash Register Till Balancing Card -->
                            <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 0.625rem; padding: 0.875rem; display: flex; flex-direction: column; gap: 0.5rem;" class="dark:!bg-emerald-950/30 dark:!border-emerald-800">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <span style="font-size: 0.75rem; font-weight: 800; color: #166534;" class="dark:!text-emerald-400">
                                        🪙 CASH DRAWER BALANCING (EXPECTED IN TILL)
                                    </span>
                                    <span style="font-size: 0.6875rem; font-weight: 700; color: #15803D;" class="dark:!text-emerald-300">
                                        {{ $drawer['cash_transactions'] ?? 0 }} Cash Tickets
                                    </span>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr 1.2fr; gap: 0.5rem; font-size: 0.75rem; border-top: 1px dashed #86EFAC; padding-top: 0.5rem;" class="dark:!border-emerald-800">
                                    <div>
                                        <div style="color: #4B5563; font-size: 0.6875rem;" class="dark:!text-gray-400">Cash Handed Over:</div>
                                        <div style="font-weight: 800; color: #111827;" class="dark:!text-gray-200">
                                            {{ $currSymbol }}{{ number_format($drawer['cash_tendered'] ?? 0, 2) }}
                                        </div>
                                    </div>
                                    <div>
                                        <div style="color: #4B5563; font-size: 0.6875rem;" class="dark:!text-gray-400">Change Returned:</div>
                                        <div style="font-weight: 800; color: var(--lj-rose);">
                                            - {{ $currSymbol }}{{ number_format($drawer['change_returned'] ?? 0, 2) }}
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="color: #166534; font-weight: 700; font-size: 0.6875rem;" class="dark:!text-emerald-400">Net Till Cash:</div>
                                        <div style="font-size: 1.15rem; font-weight: 900; color: #15803D;" class="dark:!text-emerald-300">
                                            {{ $currSymbol }}{{ number_format($drawer['net_cash_in_drawer'] ?? 0, 2) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card B: Complete Payment Methods Breakdown -->
                    <div class="lj-pos-card">
                        <div class="lj-pos-card-header">
                            <div class="lj-pos-card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--lj-emerald);"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                <span>Payment Method Distribution & Channel Share</span>
                            </div>
                            <span style="font-size: 0.6875rem; color: var(--lj-text-muted); font-weight: 600;">
                                Ranked by Total Volume
                            </span>
                        </div>
                        <div class="lj-pos-card-body" style="gap: 0.625rem;">
                            @forelse($methods as $mKey => $m)
                            <div class="lj-pos-pm-row">
                                <div style="display: flex; align-items: center; gap: 0.75rem; flex-1; min-width: 0;">
                                    <div style="width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; background: {{ $m['color'] }}15; border: 1px solid {{ $m['color'] }}30; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: {{ $m['color'] }}; flex-shrink: 0;">
                                        {{ strtoupper(substr($m['label'] ?? 'PM', 0, 2)) }}
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                                            <span style="font-size: 0.8125rem; font-weight: 700; color: var(--lj-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                {{ $m['label'] }}
                                            </span>
                                            <span style="font-size: 0.8125rem; font-weight: 800; color: var(--lj-text);">
                                                {{ $currSymbol }}{{ number_format($m['amount'], 2) }}
                                            </span>
                                        </div>
                                        <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.6875rem; color: var(--lj-text-muted); margin-top: 0.15rem;">
                                            <span>{{ $m['count'] }} orders ({{ $m['count_pct'] }}% of volume) · Avg {{ $currSymbol }}{{ number_format($m['avg_ticket'], 0) }}</span>
                                            <span style="font-weight: 700; color: {{ $m['color'] }};">{{ $m['amount_pct'] }}%</span>
                                        </div>
                                        <!-- Progress bar -->
                                        <div style="height: 4px; width: 100%; border-radius: 9999px; background: var(--lj-border); margin-top: 0.35rem; overflow: hidden;">
                                            <div style="height: 100%; width: {{ min(100, $m['amount_pct']) }}%; background: {{ $m['color'] }}; border-radius: 9999px;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <div style="text-align: center; padding: 2rem 1rem; color: var(--lj-text-muted); font-size: 0.8125rem;">
                                No payments recorded in this period.
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: PEAK HOURS & STAFF PERFORMANCE -->
                <div style="display: flex; flex-direction: column; gap: 1rem;">

                    <!-- Card C: Hourly Showroom Traffic & Peak Hours Curve -->
                    <div class="lj-pos-card">
                        <div class="lj-pos-card-header">
                            <div class="lj-pos-card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--lj-emerald);"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <span>Hourly Sales Velocity & Peak Traffic</span>
                            </div>
                            @if(!empty($peakTraffic['peak_hour']))
                            <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 9999px; background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A;">
                                Peak: {{ $peakTraffic['peak_hour'] }} ({{ $currSymbol }}{{ number_format($peakTraffic['peak_revenue'] ?? 0, 0) }})
                            </span>
                            @endif
                        </div>
                        <div class="lj-pos-card-body" style="padding-top: 0.75rem;">
                            <!-- Hourly Vertical Bar Chart -->
                            <div class="lj-pos-hourly-chart">
                                @foreach($hourly as $h)
                                @php
                                    $isPeak = ($h['revenue'] > 0 && ($h['label'] === ($peakTraffic['peak_hour'] ?? '')));
                                    $barHeight = max(4, round(($h['pct_of_peak'] / 100) * 95));
                                @endphp
                                <div class="lj-pos-hourly-col" title="{{ $h['label'] }}: {{ $currSymbol }}{{ number_format($h['revenue'], 2) }} ({{ $h['count'] }} orders)">
                                    @if($h['count'] > 0)
                                    <span style="font-size: 0.5625rem; font-weight: 700; color: {{ $isPeak ? '#D97706' : 'var(--lj-text-muted)' }}; margin-bottom: 0.15rem;">
                                        {{ $h['count'] }}
                                    </span>
                                    @endif
                                    <div
                                        class="lj-pos-hourly-bar-fill {{ $isPeak ? 'peak' : '' }}"
                                        style="height: {{ $barHeight }}px; opacity: {{ $h['revenue'] > 0 ? '1' : '0.2' }};">
                                    </div>
                                    <span class="lj-pos-hourly-label" style="{{ $isPeak ? 'color: #D97706; font-weight: 800;' : '' }}">
                                        {{ $h['label'] }}
                                    </span>
                                </div>
                                @endforeach
                            </div>

                            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.6875rem; color: var(--lj-text-muted); padding: 0 0.25rem;">
                                <span>Store opening: 8:00 AM</span>
                                <span>Peak Rush: Afternoon & Evening</span>
                                <span>Closing: 9:00 PM</span>
                            </div>
                        </div>
                    </div>

                    <!-- Card D: Cashier & Staff Performance Leaderboard -->
                    <div class="lj-pos-card">
                        <div class="lj-pos-card-header">
                            <div class="lj-pos-card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--lj-emerald);"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                <span>Cashier & Staff Sales Leaderboard</span>
                            </div>
                            <span style="font-size: 0.6875rem; color: var(--lj-text-muted); font-weight: 600;">
                                Ranked by Revenue
                            </span>
                        </div>
                        <div class="lj-pos-card-body" style="padding: 0; overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.75rem; text-align: left;">
                                <thead>
                                    <tr style="background: var(--lj-card-subtle); border-bottom: 1px solid var(--lj-border); color: var(--lj-text-muted); font-size: 0.6875rem; text-transform: uppercase; font-weight: 700;">
                                        <th style="padding: 0.65rem 0.875rem;">Staff Member</th>
                                        <th style="padding: 0.65rem 0.5rem; text-align: center;">Orders</th>
                                        <th style="padding: 0.65rem 0.875rem; text-align: right;">Total Sold</th>
                                        <th style="padding: 0.65rem 0.5rem; text-align: right;">AOV</th>
                                        <th style="padding: 0.65rem 0.875rem; text-align: center;">Top Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($staffPerf as $staff)
                                    <tr style="border-bottom: 1px solid var(--lj-border);">
                                        <td style="padding: 0.65rem 0.875rem; font-weight: 700; color: var(--lj-text);">
                                            <div style="display: flex; align-items: center; gap: 0.35rem;">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--lj-text-muted);"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                                <span>{{ $staff['staff_name'] }}</span>
                                            </div>
                                        </td>
                                        <td style="padding: 0.65rem 0.5rem; text-align: center; font-weight: 600;">
                                            {{ $staff['sales_count'] }}
                                        </td>
                                        <td style="padding: 0.65rem 0.875rem; text-align: right; font-weight: 800; color: var(--lj-emerald);">
                                            {{ $currSymbol }}{{ number_format($staff['total_revenue'], 2) }}
                                            <div style="font-size: 0.625rem; font-weight: 600; color: var(--lj-text-muted);">{{ $staff['revenue_pct'] }}%</div>
                                        </td>
                                        <td style="padding: 0.65rem 0.5rem; text-align: right; font-weight: 600; color: var(--lj-text-muted);">
                                            {{ $currSymbol }}{{ number_format($staff['avg_ticket'], 0) }}
                                        </td>
                                        <td style="padding: 0.65rem 0.875rem; text-align: center;">
                                            <span style="font-size: 0.625rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 9999px; background: var(--lj-card-subtle); border: 1px solid var(--lj-border);">
                                                {{ $staff['top_payment_method'] }}
                                            </span>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" style="padding: 2rem 1rem; text-align: center; color: var(--lj-text-muted);">
                                            No staff records for this period.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Card E: Sales Channel & Patron Demographics -->
                    <div class="lj-pos-card">
                        <div class="lj-pos-card-header">
                            <div class="lj-pos-card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--lj-emerald);"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                <span>Sales Channel & Patron Acquisition</span>
                            </div>
                        </div>
                        <div class="lj-pos-card-body" style="gap: 0.75rem;">
                            <!-- Channels -->
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.5rem;">
                                @foreach($channelAnalytics as $ch)
                                <div style="background: var(--lj-card-subtle); border: 1px solid var(--lj-border); border-radius: 0.5rem; padding: 0.625rem;">
                                    <div style="font-size: 0.6875rem; color: var(--lj-text-muted); font-weight: 600;">
                                        {{ $ch['label'] }}
                                    </div>
                                    <div style="font-size: 0.9375rem; font-weight: 800; color: var(--lj-text); margin-top: 0.15rem;">
                                        {{ $currSymbol }}{{ number_format($ch['amount'], 0) }}
                                    </div>
                                    <div style="font-size: 0.625rem; color: var(--lj-text-muted); margin-top: 0.125rem;">
                                        {{ $ch['count'] }} sales ({{ $ch['amount_pct'] }}%)
                                    </div>
                                </div>
                                @endforeach
                            </div>

                            <!-- Customer Demographics Bar -->
                            <div style="border-top: 1px dashed var(--lj-border); padding-top: 0.625rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem;">
                                <div>
                                    <span style="color: var(--lj-text-muted);">Walk-in Guests:</span>
                                    <span style="font-weight: 800; color: var(--lj-text); margin-left: 0.25rem;">
                                        {{ $customerInsights['walkin_count'] ?? 0 }} ({{ $currSymbol }}{{ number_format($customerInsights['walkin_revenue'] ?? 0, 0) }})
                                    </span>
                                </div>
                                <div>
                                    <span style="color: var(--lj-text-muted);">Registered Patrons:</span>
                                    <span style="font-weight: 800; color: #059669; margin-left: 0.25rem;">
                                        {{ $customerInsights['registered_count'] ?? 0 }} ({{ $currSymbol }}{{ number_format($customerInsights['registered_revenue'] ?? 0, 0) }})
                                    </span>
                                </div>
                                <div>
                                    <span style="color: var(--lj-text-muted);">Phones Captured:</span>
                                    <span style="font-weight: 800; color: var(--lj-gold); margin-left: 0.25rem;">
                                        {{ $customerInsights['unique_phones_captured'] ?? 0 }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- 4. BOTTOM SECTION: TOP PRODUCTS & RECENT TRANSACTIONS STREAM -->
            <div style="display: grid; grid-template-columns: 1.15fr 1fr; gap: 1rem;">

                <!-- Left: Top 8 POS Best-Sellers -->
                <div class="lj-pos-card">
                    <div class="lj-pos-card-header">
                        <div class="lj-pos-card-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--lj-emerald);"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>
                            <span>Top Performing Products in POS</span>
                        </div>
                        <span style="font-size: 0.6875rem; color: var(--lj-text-muted); font-weight: 600;">
                            By Revenue in Period
                        </span>
                    </div>
                    <div class="lj-pos-card-body" style="padding: 0; overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.75rem; text-align: left;">
                            <thead>
                                <tr style="background: var(--lj-card-subtle); border-bottom: 1px solid var(--lj-border); color: var(--lj-text-muted); font-size: 0.6875rem; text-transform: uppercase; font-weight: 700;">
                                    <th style="padding: 0.65rem 0.875rem;">Product / SKU</th>
                                    <th style="padding: 0.65rem 0.5rem; text-align: center;">Sold</th>
                                    <th style="padding: 0.65rem 0.875rem; text-align: right;">Revenue</th>
                                    <th style="padding: 0.65rem 0.5rem; text-align: right;">Avg Price</th>
                                    @if($canSeeMargins)
                                    <th style="padding: 0.65rem 0.875rem; text-align: right;">Margin</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topProducts as $prod)
                                <tr style="border-bottom: 1px solid var(--lj-border);">
                                    <td style="padding: 0.65rem 0.875rem;">
                                        <div style="font-weight: 700; color: var(--lj-text); line-height: 1.25;">{{ $prod['product_name'] }}</div>
                                        <div style="font-size: 0.625rem; font-family: monospace; color: var(--lj-text-muted);">{{ $prod['sku'] }}</div>
                                    </td>
                                    <td style="padding: 0.65rem 0.5rem; text-align: center; font-weight: 700;">
                                        {{ $prod['units_sold'] }} pcs
                                    </td>
                                    <td style="padding: 0.65rem 0.875rem; text-align: right; font-weight: 800; color: var(--lj-emerald);">
                                        {{ $currSymbol }}{{ number_format($prod['revenue_npr'], 2) }}
                                    </td>
                                    <td style="padding: 0.65rem 0.5rem; text-align: right; font-weight: 600; color: var(--lj-text-muted);">
                                        {{ $currSymbol }}{{ number_format($prod['avg_price'], 0) }}
                                    </td>
                                    @if($canSeeMargins)
                                    <td style="padding: 0.65rem 0.875rem; text-align: right; font-weight: 700; color: #059669;">
                                        {{ number_format($prod['margin_pct'], 1) }}%
                                    </td>
                                    @endif
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" style="padding: 2rem 1rem; text-align: center; color: var(--lj-text-muted);">
                                        No sales item data for this period.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Right: Recent POS Receipts Stream -->
                <div class="lj-pos-card">
                    <div class="lj-pos-card-header">
                        <div class="lj-pos-card-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--lj-emerald);"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="12" y2="16"/></svg>
                            <span>Recent POS Receipts Stream</span>
                        </div>
                        <span style="font-size: 0.6875rem; color: var(--lj-text-muted); font-weight: 600;">
                            Last 10 Completed Sales
                        </span>
                    </div>
                    <div class="lj-pos-card-body" style="padding: 0.5rem; gap: 0.35rem; max-height: 420px; overflow-y: auto;">
                        @forelse($recentTx as $tx)
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.55rem 0.65rem; border-radius: 0.5rem; background: var(--lj-card-subtle); border: 1px solid var(--lj-border); transition: background 0.15s ease;">
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; align-items: center; gap: 0.35rem;">
                                    <span style="font-family: monospace; font-size: 0.75rem; font-weight: 700; color: var(--lj-emerald);">
                                        {{ $tx['sale_number'] }}
                                    </span>
                                    <span style="font-size: 0.625rem; color: var(--lj-text-muted);">
                                        · {{ $tx['sold_at_time'] }} ({{ $tx['sold_at_date'] }})
                                    </span>
                                </div>
                                <div style="font-size: 0.6875rem; color: var(--lj-text-muted); margin-top: 0.125rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ $tx['customer_name'] }} · {{ $tx['items_count'] }} items · Staff: {{ $tx['staff_name'] }}
                                </div>
                            </div>

                            <div style="text-align: right; flex-shrink: 0;">
                                <div style="font-size: 0.8125rem; font-weight: 800; color: var(--lj-text);">
                                    {{ $currSymbol }}{{ number_format($tx['total_amount'], 2) }}
                                </div>
                                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.25rem; margin-top: 0.15rem;">
                                    <span style="font-size: 0.625rem; font-weight: 700; padding: 0.1rem 0.35rem; border-radius: 0.25rem; background: var(--lj-card); border: 1px solid var(--lj-border); text-transform: uppercase;">
                                        {{ $tx['payment_method'] }}
                                    </span>
                                    <button
                                        type="button"
                                        wire:click="openSaleDetail({{ $tx['id'] }})"
                                        style="font-size: 0.625rem; font-weight: 700; color: var(--lj-emerald); background: none; border: none; cursor: pointer; text-decoration: underline;"
                                        title="View Sale Details">
                                        View
                                    </button>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div style="text-align: center; padding: 2rem 1rem; color: var(--lj-text-muted); font-size: 0.8125rem;">
                            No recent transactions found.
                        </div>
                        @endforelse
                    </div>
                </div>

            </div>

        </div>

        @endif
