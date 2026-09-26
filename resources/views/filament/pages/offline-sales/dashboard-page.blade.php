<x-filament-panels::page class="w-full max-w-full !p-0">
    @php
        $currSymbol = 'Rs. ';
        $dash = $this->dashboardData;
        $activeTab = 'dashboard';
        $sessionStatus = $this->sessionState;
        $activeTerminal = $this->activeStation;
        $canSeeMargins = $this->canViewFinancialMargins();
    @endphp

    {{-- POS Scoped Styles & Typography --}}
    @include('filament.pages.offline-sales.styles')

    <div class="lj-pos-root lj-pos-admin-shell-page" style="display: flex; flex-direction: column; gap: 0.75rem;">
        <!-- Sub-page Compact POS Header / Nav Bar -->
        <header class="lj-pos-topbar" style="border-radius: 0.5rem; border: 1px solid var(--lj-border); background: var(--lj-card);">
            <div class="lj-pos-brand">
                <span class="lj-brand-title">LAIJAU</span>
                <span style="font-weight: 700; font-size: 0.8125rem; color: #475569; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <svg style="width: 14px; height: 14px; color: #059669;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="3" width="7" height="9" />
                        <rect x="14" y="3" width="7" height="5" />
                        <rect x="14" y="12" width="7" height="9" />
                        <rect x="3" y="16" width="7" height="5" />
                    </svg>
                    <span>POS Dashboard</span>
                </span>
            </div>

            <!-- Center View Navigation -->
            @include('filament.pages.offline-sales.nav')

            <!-- Quick Action: Launch Fullscreen POS in New Tab -->
            <div class="lj-topbar-actions">
                <a href="{{ url('/intadmin/offline-sales/POS') }}" target="_blank" rel="noopener noreferrer" class="lj-btn-secondary" style="height: 34px; padding: 0 0.85rem; font-size: 0.75rem; font-weight: 700; background: #059669; color: #FFFFFF; border-color: #059669; display: inline-flex; align-items: center; gap: 0.4rem; border-radius: 0.375rem; text-decoration: none;" title="Open Dedicated Fullscreen POS in New Tab">
                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="9" cy="21" r="1" />
                        <circle cx="20" cy="21" r="1" />
                        <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
                    </svg>
                    <span>Open POS</span>
                    <svg style="width: 11px; height: 11px; opacity: 0.8;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                </a>
            </div>
        </header>

        <!-- Dashboard Analytics Content -->
        @include('filament.pages.offline-sales.dashboard')

        <!-- Modals required by Dashboard: Shift Report -->
        @include('filament.pages.offline-sales.modals.shift-report')
    </div>

    <!-- POS Scripts for Shift Report & Modal Printing Handlers -->
    @include('filament.pages.offline-sales.scripts')
</x-filament-panels::page>
