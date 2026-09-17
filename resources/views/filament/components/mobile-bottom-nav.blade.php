@php
    $currentRoute = request()->route()?->getName() ?? '';
    $currentUri = request()->getRequestUri();
    
    $isPos = str_contains($currentRoute, 'offline-sales') || str_contains($currentRoute, 'point-of-sale') || str_contains($currentUri, '/intadmin/offline-sales') || str_contains($currentUri, '/intadmin/point-of-sale');
    $isOrders = str_contains($currentRoute, 'orders.') || str_contains($currentUri, '/intadmin/orders');
    $isInventory = str_contains($currentRoute, 'stock-levels') || str_contains($currentRoute, 'quick-stock-entry') || str_contains($currentRoute, 'stock-transfers') || str_contains($currentUri, '/intadmin/stock');
    $isFulfillment = str_contains($currentRoute, 'fulfillment-hub') || str_contains($currentUri, '/intadmin/fulfillment-hub');
    $isDashboard = str_contains($currentRoute, 'dashboard') || $currentUri === '/intadmin' || $currentUri === '/intadmin/';
    
    $posUrl = \Illuminate\Support\Facades\Route::has('filament.admin.pages.offline-sales') 
        ? route('filament.admin.pages.offline-sales') 
        : (\Illuminate\Support\Facades\Route::has('filament.admin.pages.point-of-sale') ? route('filament.admin.pages.point-of-sale') : '/intadmin/offline-sales');
        
    $ordersUrl = \Illuminate\Support\Facades\Route::has('filament.admin.resources.orders.index') 
        ? route('filament.admin.resources.orders.index') 
        : '/intadmin/orders';
        
    $stockUrl = \Illuminate\Support\Facades\Route::has('filament.admin.resources.stock-levels.index') 
        ? route('filament.admin.resources.stock-levels.index') 
        : '/intadmin/stock-levels';
        
    $fulfillmentUrl = \Illuminate\Support\Facades\Route::has('filament.admin.pages.fulfillment-hub') 
        ? route('filament.admin.pages.fulfillment-hub') 
        : '/intadmin/fulfillment-hub';
@endphp

<style>
    /* Strictly hide mobile bottom dock on desktop viewports */
    #lj-mobile-bottom-dock,
    .lj-mobile-bottom-nav {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
        height: 0 !important;
        max-height: 0 !important;
        overflow: hidden !important;
    }
    @media (max-width: 1023px) {
        #lj-mobile-bottom-dock,
        .lj-mobile-bottom-nav {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            pointer-events: auto !important;
            height: auto !important;
            max-height: none !important;
            overflow: visible !important;
        }
    }
    @media (min-width: 1024px) {
        #lj-mobile-bottom-dock,
        .lj-mobile-bottom-nav,
        .lg\:hidden {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
            height: 0 !important;
            max-height: 0 !important;
            overflow: hidden !important;
        }
    }
</style>

<!-- ============================================================================== -->
<!-- LAIJAU MOBILE ADMIN GLOBAL BOTTOM NAVIGATION DOCK -->
<!-- ============================================================================== -->
<aside 
    id="lj-mobile-bottom-dock" 
    class="lj-mobile-bottom-nav lg:hidden"
    aria-label="Quick Mobile Navigation"
>
    <div class="lj-mobile-nav-inner">
        <!-- 1. POS Terminal -->
        <a 
            href="{{ $posUrl }}" 
            class="lj-mobile-nav-item {{ $isPos ? 'active' : '' }}"
            title="Point of Sale"
        >
            <div class="lj-mobile-nav-icon-wrap">
                <svg class="lj-mobile-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                    <path d="M7 8h10M7 12h5"></path>
                </svg>
                @if($isPos)
                    <span class="lj-mobile-nav-dot"></span>
                @endif
            </div>
            <span class="lj-mobile-nav-label">POS</span>
        </a>

        <!-- 2. Orders -->
        <a 
            href="{{ $ordersUrl }}" 
            class="lj-mobile-nav-item {{ $isOrders ? 'active' : '' }}"
            title="Live Orders"
        >
            <div class="lj-mobile-nav-icon-wrap">
                <svg class="lj-mobile-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                @if($isOrders)
                    <span class="lj-mobile-nav-dot"></span>
                @endif
            </div>
            <span class="lj-mobile-nav-label">Orders</span>
        </a>

        <!-- 3. Stock & Inventory -->
        <a 
            href="{{ $stockUrl }}" 
            class="lj-mobile-nav-item {{ $isInventory ? 'active' : '' }}"
            title="Inventory & Stock"
        >
            <div class="lj-mobile-nav-icon-wrap">
                <svg class="lj-mobile-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
                @if($isInventory)
                    <span class="lj-mobile-nav-dot"></span>
                @endif
            </div>
            <span class="lj-mobile-nav-label">Stock</span>
        </a>

        <!-- 4. Courier Dispatch (NCM & Pathao) -->
        <a 
            href="{{ $fulfillmentUrl }}" 
            class="lj-mobile-nav-item {{ $isFulfillment ? 'active' : '' }}"
            title="Courier Fulfillment (NCM / Pathao)"
        >
            <div class="lj-mobile-nav-icon-wrap">
                <svg class="lj-mobile-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="3" width="15" height="13"></rect>
                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                </svg>
                @if($isFulfillment)
                    <span class="lj-mobile-nav-dot"></span>
                @endif
            </div>
            <span class="lj-mobile-nav-label">Dispatch</span>
        </a>

        <!-- 5. Full Menu Trigger (Opens Filament Sidebar) -->
        <button 
            type="button" 
            x-data="{}" 
            x-on:click="$store.sidebar ? ($store.sidebar.isOpen ? $store.sidebar.close() : $store.sidebar.open()) : null"
            class="lj-mobile-nav-item"
            title="Open Full Menu"
            aria-label="Toggle Full Navigation Menu"
        >
            <div class="lj-mobile-nav-icon-wrap">
                <svg class="lj-mobile-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </div>
            <span class="lj-mobile-nav-label">Menu</span>
        </button>
    </div>
</aside>
