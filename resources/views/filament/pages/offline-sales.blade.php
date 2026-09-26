<div class="lj-pos-fullscreen-app">
    @php
    $totals = $this->calculateTotals();
    $searchResults = $this->searchResults;
    $categories = !empty($this->categoriesList) ? $this->categoriesList : $this->categories;
    $customersList = $activeModal === 'customer_modal' ? $this->customersList : [];
    $salesHistory = $activeTab === 'history' ? $this->salesHistory : [];
    $currSymbol = 'Rs. ';
    $dash = ($activeTab === 'dashboard' || $activeModal === 'shift_report') ? $this->dashboardData : [];
    $perf = $activeTab === 'performance' ? $this->productPerformanceData : [];
    $canSeeMargins = $this->canViewFinancialMargins();
    $sessionStatus = $this->sessionState;
    $activeTerminal = $this->activeStation;
    @endphp

    {{-- POS Scoped Styles & Typography --}}
    @include('filament.pages.offline-sales.styles')

    <div class="lj-pos-root" x-data="{ mobilePosTab: 'catalog' }">
        {{-- POS Topbar Navigation & Terminal Status --}}
        @include('filament.pages.offline-sales.header')

        {{-- Core Tab Workspaces --}}
        @if($activeTab === 'new_sale')
            @include('filament.pages.offline-sales.pos')
        @elseif($activeTab === 'history')
            @include('filament.pages.offline-sales.history')
        @elseif($activeTab === 'dashboard')
            @include('filament.pages.offline-sales.dashboard')
        @elseif($activeTab === 'performance')
            @include('filament.pages.offline-sales.performance')
        @endif

        {{-- POS Dialogs & Modals --}}
        @include('filament.pages.offline-sales.modals')
    </div>

    {{-- POS Keyboard Shortcuts & Hardware Scanner Scripts --}}
    @include('filament.pages.offline-sales.scripts')
</div>