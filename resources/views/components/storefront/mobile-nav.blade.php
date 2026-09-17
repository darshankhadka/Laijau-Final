@if(!request()->is('checkout'))
<nav
    x-data
    aria-label="Mobile Navigation"
    class="fixed bottom-0 inset-x-0 z-40 lg:hidden bg-white/95 backdrop-blur-md border-t border-slate-200 text-slate-700 shadow-lg pb-[env(safe-area-inset-bottom,0px)] text-left select-none"
>
    <div class="grid grid-cols-5 h-14 max-w-md mx-auto items-center px-1">
        <!-- 1. HOME -->
        <a
            href="{{ url('/') }}"
            class="flex flex-col items-center justify-center py-1 gap-0.5 transition-colors relative {{ request()->is('/') ? 'text-blue-600 font-bold' : 'text-slate-500 hover:text-slate-900' }}"
            aria-label="Home"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span class="text-[10px]">Home</span>
            @if(request()->is('/'))
                <span class="absolute bottom-0 w-1 h-1 rounded-full bg-blue-600"></span>
            @endif
        </a>

        <!-- 2. CATEGORIES -->
        <a
            href="{{ route('storefront.catalogue') }}"
            class="flex flex-col items-center justify-center py-1 gap-0.5 transition-colors relative {{ request()->is('products*') || request()->is('categories*') ? 'text-blue-600 font-bold' : 'text-slate-500 hover:text-slate-900' }}"
            aria-label="Categories"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
            <span class="text-[10px]">Catalog</span>
            @if(request()->is('products*') || request()->is('categories*'))
                <span class="absolute bottom-0 w-1 h-1 rounded-full bg-blue-600"></span>
            @endif
        </a>

        <!-- 3. SEARCH -->
        <button
            type="button"
            @click="$store.store.isSearchOpen = true"
            class="flex flex-col items-center justify-center py-1 gap-0.5 transition-colors text-slate-500 hover:text-slate-900 cursor-pointer"
            aria-label="Search"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <span class="text-[10px]">Search</span>
        </button>

        <!-- 4. ACCOUNT / ORDERS -->
        <a
            href="{{ route('storefront.account') }}"
            class="flex flex-col items-center justify-center py-1 gap-0.5 transition-colors relative {{ request()->is('account*') || request()->is('track-order*') ? 'text-blue-600 font-bold' : 'text-slate-500 hover:text-slate-900' }}"
            aria-label="Account"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span class="text-[10px]">Account</span>
            @if(request()->is('account*') || request()->is('track-order*'))
                <span class="absolute bottom-0 w-1 h-1 rounded-full bg-blue-600"></span>
            @endif
        </a>

        <!-- 5. CART -->
        <button
            id="mobile-nav-cart-button"
            type="button"
            @click="$store.store.openCartDrawer()"
            class="flex flex-col items-center justify-center py-1 gap-0.5 transition-colors relative text-slate-500 hover:text-slate-900 cursor-pointer"
            aria-label="Bag"
        >
            <div class="relative">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <span
                    x-show="$store.store.cartCount > 0"
                    x-text="$store.store.cartCount"
                    class="absolute -top-1.5 -right-2.5 bg-blue-600 text-white text-[9px] font-extrabold h-4 min-w-4 px-1 rounded-full flex items-center justify-center shadow-xs"
                    style="display: none;"
                ></span>
            </div>
            <span class="text-[10px]">Cart</span>
        </button>
    </div>
</nav>
@endif
