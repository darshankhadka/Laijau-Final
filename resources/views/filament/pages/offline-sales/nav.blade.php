@php
    $isPosFullscreen = request()->is('intadmin/offline-sales/POS*') || request()->is('intadmin/offline-sales');
    $currentActive = $activeTab ?? (request()->is('intadmin/offline-sales/history*') ? 'history' : (request()->is('intadmin/offline-sales/dashboard*') ? 'dashboard' : (request()->is('intadmin/offline-sales/metrics*') ? 'performance' : 'new_sale')));
@endphp

<nav class="lj-tab-nav" aria-label="POS Section Navigation">
    <a
        href="{{ url('/intadmin/offline-sales/POS') }}"
        @if(!$isPosFullscreen) target="_blank" rel="noopener noreferrer" @endif
        class="lj-tab-btn {{ $currentActive === 'new_sale' ? 'active' : '' }}"
        style="display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none;"
        title="Open Fullscreen Point of Sale Terminal {{ !$isPosFullscreen ? '(Opens in new tab)' : '' }}">
        <svg style="width: 15px; height: 15px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="9" cy="21" r="1" />
            <circle cx="20" cy="21" r="1" />
            <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
        </svg>
        <span>New Sale</span>
        @if(!$isPosFullscreen)
            <svg style="width: 11px; height: 11px; opacity: 0.7;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
        @endif
    </a>

    <a
        href="{{ url('/intadmin/offline-sales/history') }}"
        class="lj-tab-btn {{ $currentActive === 'history' ? 'active' : '' }}"
        style="display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none;"
        title="Offline Sales History & Receipts">
        <svg style="width: 15px; height: 15px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>Sales History</span>
    </a>

    <a
        href="{{ url('/intadmin/offline-sales/dashboard') }}"
        class="lj-tab-btn {{ $currentActive === 'dashboard' ? 'active' : '' }}"
        style="display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none;"
        title="POS Intelligence & Register Balancing">
        <svg style="width: 15px; height: 15px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="3" width="7" height="9" />
            <rect x="14" y="3" width="7" height="5" />
            <rect x="14" y="12" width="7" height="9" />
            <rect x="3" y="16" width="7" height="5" />
        </svg>
        <span>Dashboard</span>
    </a>

    <a
        href="{{ url('/intadmin/offline-sales/metrics') }}"
        class="lj-tab-btn {{ $currentActive === 'performance' || $currentActive === 'metrics' ? 'active' : '' }}"
        style="display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none;"
        title="Product-Level Offline Sales Analytics">
        <svg style="width: 15px; height: 15px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
            <polyline points="17 6 23 6 23 12" />
        </svg>
        <span>Product Metrics</span>
    </a>
</nav>
