@php
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

$allActiveCategories = Category::where('is_active', true)
->whereHas('products', fn($q) => $q->where('is_active', true))
->withCount(['products' => fn($q) => $q->where('is_active', true)])
->orderByDesc('products_count')
->get();

if ($allActiveCategories->isEmpty()) {
$allActiveCategories = Category::where('is_active', true)
->withCount('products')
->orderBy('name')
->get();
}

$primaryNavCategories = $allActiveCategories->take(6);
$moreCategories = $allActiveCategories->slice(6)->take(12);
$categories = $allActiveCategories;
$announcement = app(\App\Services\StoreSettingsService::class)->getAnnouncementBar();
@endphp

<header
    x-data="{
        isScrolled: false,
        mobileMenuOpen: false,
        searchFocused: false,
        searchVal: '',
        desktopCategoriesOpen: false
    }"
    x-init="
        window.addEventListener('scroll', () => { isScrolled = window.scrollY > 20 });
    "
    class="sticky top-0 z-40 w-full bg-white transition-shadow duration-200"
    :class="isScrolled ? 'shadow-md' : 'border-b border-slate-200'">
    <!-- Top Utility Announcement Bar -->
    <div class="bg-slate-100 text-slate-700 text-[11px] font-medium py-1.5 px-4 sm:px-8 border-b border-slate-200">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="truncate pr-4 flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold">✓</span>
                <span class="font-normal text-slate-600">
                    Cash on Delivery inside <strong class="text-slate-900 font-semibold">Kathmandu Valley</strong> • Fast Courier Dispatch Across <strong class="text-slate-900 font-semibold">All 77 Districts</strong>
                </span>
            </div>
            <div class="hidden md:flex items-center gap-5 shrink-0 text-slate-600">
                <a href="{{ route('storefront.track_order') }}" class="hover:text-blue-600 transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Track Order
                </a>
                <span class="text-slate-300">|</span>
                <a href="https://wa.me/9779843512095?text=Hello%20Laijau%2C%20I%20have%20an%20inquiry" target="_blank" rel="noopener" class="hover:text-emerald-700 transition-colors flex items-center gap-1">
                    <span>WhatsApp: 9843512095</span>
                </a>
                <span class="text-slate-300">|</span>
                <span class="text-slate-800 font-semibold">NPR (Rs.)</span>
            </div>
        </div>
    </div>

    <!-- Main Commercial Header -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between gap-4 lg:gap-8">
        <!-- Logo -->
        <div class="flex items-center gap-3 shrink-0">
            <!-- Mobile Menu Toggle Button -->
            <button
                type="button"
                @click="mobileMenuOpen = !mobileMenuOpen"
                class="lg:hidden p-2 -ml-2 text-slate-700 hover:text-slate-900 rounded-lg hover:bg-slate-100 focus:outline-none"
                aria-label="Toggle mobile menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <a href="{{ url('/') }}" class="flex items-center gap-2 focus:outline-none group" aria-label="Laijau Home">
                <picture>
                    <source srcset="/images/logo.webp" type="image/webp">
                    <img src="/images/logo.png" alt="Laijau" width="130" height="36" class="h-8 sm:h-9 w-auto object-contain" onerror="this.onerror=null; this.src='/logo.png';" decoding="async" />
                </picture>
                <span class="sr-only">LAIJAU.COM</span>
            </a>
        </div>

        <!-- Desktop Prominent Search Bar with Instant Autocomplete Dropdown -->
        <div class="hidden lg:block flex-1 max-w-2xl relative" x-data="{ localOpen: false }">
            <form
                action="{{ route('storefront.search') }}"
                method="GET"
                @submit="localOpen = false"
                class="relative flex items-center">
                <div class="relative w-full">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input
                        type="search"
                        name="q"
                        x-model="searchVal"
                        @focus="localOpen = true; $store.store.fetchSearchSuggestions(searchVal)"
                        @input.debounce.250ms="$store.store.fetchSearchSuggestions(searchVal); localOpen = true"
                        @click.away="localOpen = false"
                        @keydown.escape="localOpen = false"
                        placeholder="Search 1,000+ shoes, sneakers, boots, clothing, accessories..."
                        class="w-full pl-10 pr-24 py-2.5 bg-slate-100 hover:bg-slate-50 focus:bg-white text-slate-900 placeholder-slate-500 rounded-full border border-slate-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 text-sm transition-all shadow-inner focus:shadow-none outline-none"
                        autocomplete="off" />
                    <button
                        type="submit"
                        class="absolute right-1 top-1 bottom-1 px-5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-semibold rounded-full transition-colors flex items-center justify-center cursor-pointer shadow-xs">
                        Search
                    </button>
                </div>
            </form>

            <!-- Autocomplete Live Dropdown Panel -->
            <div
                x-show="localOpen"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-2"
                class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-50 text-left"
                style="display: none;">
                <!-- Loading State -->
                <div x-show="$store.store.isSearching" class="p-4 text-center text-xs text-slate-500 flex items-center justify-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    Searching Laijau catalog...
                </div>

                <div x-show="!$store.store.isSearching" class="p-4 divide-y divide-slate-100">
                    <!-- Matching Categories -->
                    <template x-if="$store.store.searchSuggestions.categories && $store.store.searchSuggestions.categories.length > 0">
                        <div class="pb-3">
                            <span class="text-[10px] font-bold tracking-wider uppercase text-slate-400 block mb-2">Categories</span>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="cat in $store.store.searchSuggestions.categories" :key="cat.id">
                                    <a
                                        :href="'/categories/' + cat.slug"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-700 rounded-full text-xs font-medium transition-colors">
                                        <span x-text="cat.name"></span>
                                        <span class="text-[10px] text-slate-400" x-text="'(' + cat.count + ')'"></span>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Popular / Suggested Keywords -->
                    <template x-if="$store.store.searchSuggestions.popular_searches && $store.store.searchSuggestions.popular_searches.length > 0">
                        <div class="py-3">
                            <span class="text-[10px] font-bold tracking-wider uppercase text-slate-400 block mb-2">Trending Searches</span>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="term in $store.store.searchSuggestions.popular_searches" :key="term">
                                    <a
                                        :href="'/search?q=' + encodeURIComponent(term)"
                                        class="px-2.5 py-1 bg-slate-50 hover:bg-slate-200 text-slate-700 rounded-md text-xs transition-colors"
                                        x-text="term"></a>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Matching Products -->
                    <template x-if="$store.store.searchSuggestions.products && $store.store.searchSuggestions.products.length > 0">
                        <div class="pt-3">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-[10px] font-bold tracking-wider uppercase text-slate-400">Products</span>
                                <span class="text-[11px] text-blue-600 font-semibold" x-text="$store.store.searchSuggestions.total_products_count + ' items found'"></span>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="prod in $store.store.searchSuggestions.products" :key="prod.id">
                                    <a
                                        :href="prod.url"
                                        class="flex items-center gap-3 p-2 rounded-xl hover:bg-slate-50 transition-colors group">
                                        <img
                                            :src="prod.image"
                                            :alt="prod.name"
                                            class="w-12 h-12 object-cover rounded-lg border border-slate-200 shrink-0" />
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-semibold text-slate-900 group-hover:text-blue-600 truncate" x-text="prod.name"></p>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span class="text-xs font-bold text-slate-900" x-text="prod.formatted_price"></span>
                                                <span x-show="prod.compare_at_price" class="text-[10px] text-slate-400 line-through" x-text="prod.formatted_compare_at_price"></span>
                                            </div>
                                        </div>
                                    </a>
                                </template>
                            </div>

                            <div class="mt-3 pt-2 text-center border-t border-slate-100">
                                <a
                                    :href="'/search?q=' + encodeURIComponent(searchVal)"
                                    class="text-xs font-semibold text-blue-600 hover:text-blue-800 transition-colors">
                                    View All Search Results &rarr;
                                </a>
                            </div>
                        </div>
                    </template>

                    <!-- Empty Results State -->
                    <div x-show="searchVal.length >= 2 && $store.store.searchSuggestions.products.length === 0 && $store.store.searchSuggestions.categories.length === 0" class="py-6 text-center">
                        <p class="text-xs text-slate-500 font-medium">No direct matches for "<span x-text="searchVal"></span>"</p>
                        <p class="text-[11px] text-slate-400 mt-1">Try searching for sneakers, boots, slippers, or loafer</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Utility Actions: Account | Wishlist | Cart -->
        <div class="flex items-center gap-2 sm:gap-4 shrink-0 text-slate-700">
            <!-- Mobile Search Trigger Button -->
            <button
                type="button"
                @click="$store.store.isSearchOpen = true"
                class="lg:hidden p-2 text-slate-700 hover:text-slate-900 rounded-full hover:bg-slate-100"
                aria-label="Open Search">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </button>

            <!-- Customer Account -->
            <a
                href="{{ route('storefront.account') }}"
                class="flex items-center gap-2 p-2 sm:px-3 sm:py-2 text-slate-700 hover:text-blue-600 hover:bg-slate-50 rounded-xl transition-all"
                title="Customer Account">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <div class="hidden xl:flex flex-col text-left text-xs leading-tight">
                    <span class="text-[10px] text-slate-500" x-text="$store.store.user ? 'Hello,' : 'Welcome'"></span>
                    <span class="font-semibold text-slate-800 truncate max-w-[90px]" x-text="$store.store.user ? ($store.store.user.name || 'Account') : 'Sign In'"></span>
                </div>
            </a>

            <!-- Wishlist -->
            <a
                href="{{ route('storefront.wishlist') }}"
                class="relative p-2 text-slate-700 hover:text-rose-600 hover:bg-slate-50 rounded-xl transition-all"
                title="Wishlist"
                aria-label="Wishlist">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
                <span
                    x-show="$store.store.wishlistCount > 0"
                    x-text="$store.store.wishlistCount"
                    class="absolute top-1 right-1 w-4 h-4 bg-rose-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center shadow-xs"
                    style="display: none;"></span>
            </a>

            <!-- Cart Trigger Button -->
            <button
                id="header-cart-button"
                data-open-cart
                type="button"
                @click="$store.store.openCartDrawer()"
                class="flex items-center gap-2.5 px-3 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-xl transition-all font-semibold text-xs cursor-pointer active:scale-95"
                aria-label="View Cart">
                <div class="relative">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span
                        x-show="$store.store.cartCount > 0"
                        x-text="$store.store.cartCount"
                        class="absolute -top-1.5 -right-2 min-w-4 h-4 px-1 bg-blue-600 text-white text-[9px] font-extrabold rounded-full flex items-center justify-center shadow-xs"
                        style="display: none;"></span>
                </div>
                <span class="hidden sm:inline font-bold" x-text="$store.store.cartSubtotal > 0 ? $store.store.formatAmount($store.store.cartSubtotal) : 'Cart'"></span>
            </button>
        </div>
    </div>

    <!-- Secondary Category Navigation Bar (Desktop) -->
    <div class="hidden lg:block border-t border-slate-200 bg-slate-50/70 text-slate-800 text-xs font-semibold relative z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-3 xl:gap-4 py-1">
            <!-- Left: All Categories Mega Trigger & Dropdown (No overflow clipping on parent) -->
            <div class="relative shrink-0" @click.away="desktopCategoriesOpen = false">
                <button
                    type="button"
                    @click="desktopCategoriesOpen = !desktopCategoriesOpen"
                    class="px-4 py-2 flex items-center gap-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-all font-bold text-xs cursor-pointer shadow-xs active:scale-95"
                    :class="desktopCategoriesOpen ? 'bg-blue-700 ring-2 ring-blue-400/40' : ''"
                    aria-haspopup="true"
                    :aria-expanded="desktopCategoriesOpen">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                    </svg>
                    <span class="whitespace-nowrap font-bold">All Categories</span>
                    <svg class="w-3.5 h-3.5 transition-transform duration-200 shrink-0" :class="desktopCategoriesOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- All Categories Mega Dropdown Menu -->
                <div
                    x-show="desktopCategoriesOpen"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1 scale-98"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 -translate-y-1 scale-98"
                    class="absolute left-0 top-full mt-2 w-[540px] xl:w-[620px] bg-white rounded-2xl shadow-2xl border border-slate-200 z-50 overflow-hidden"
                    style="display: none;">
                    <!-- Mega Dropdown Header -->
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-900 text-sm tracking-tight">All Store Categories</span>
                            <span class="text-[11px] font-semibold text-blue-600 bg-blue-50 px-2.5 py-0.5 rounded-full border border-blue-100">{{ $allActiveCategories->count() }} Available</span>
                        </div>
                        <a href="{{ route('storefront.catalogue') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800 transition-colors flex items-center gap-1">
                            <span>Browse All Products</span>
                            <span>&rarr;</span>
                        </a>
                    </div>

                    <!-- Mega Dropdown 2-Column Grid -->
                    <div class="p-3 grid grid-cols-2 gap-1.5 max-h-[60vh] overflow-y-auto">
                        @foreach($allActiveCategories as $c)
                        <a
                            href="{{ route('storefront.category', $c->slug) }}"
                            class="group flex items-center justify-between px-3 py-2 rounded-xl text-slate-700 hover:bg-blue-50 hover:text-blue-700 transition-colors text-xs font-medium">
                            <span class="truncate group-hover:font-semibold">{{ $c->name }}</span>
                            <span class="text-[10px] text-slate-400 group-hover:text-blue-600 font-semibold bg-slate-100 group-hover:bg-blue-100/70 px-2 py-0.5 rounded-full shrink-0 ml-2 transition-colors">
                                {{ $c->products_count }}
                            </span>
                        </a>
                        @endforeach
                    </div>

                    <!-- Mega Dropdown Footer -->
                    <div class="p-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs text-slate-600">
                        <div class="flex items-center gap-3">
                            <a href="{{ url('/products?deals=true') }}" class="font-bold text-red-600 hover:underline flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                Hot Deals & Discounts
                            </a>
                            <span class="text-slate-300">|</span>
                            <a href="{{ url('/products?new_arrivals=true') }}" class="font-medium text-slate-700 hover:text-blue-600">
                                New Arrivals
                            </a>
                        </div>
                        <a href="{{ route('storefront.catalogue') }}" class="font-bold text-blue-600 hover:text-blue-800">
                            Browse All Products &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- Center: Quick Category Pills with Clean Hover & Spacing -->
            <div class="flex-1 min-w-0 flex items-center gap-1 xl:gap-2 overflow-hidden">
                @foreach($primaryNavCategories as $pCat)
                <a
                    href="{{ route('storefront.category', $pCat->slug) }}"
                    class="px-2.5 py-1.5 text-slate-700 hover:text-blue-600 hover:bg-white rounded-lg transition-colors whitespace-nowrap text-xs font-medium {{ request()->is('categories/'.$pCat->slug) ? 'text-blue-600 font-bold bg-white shadow-xs' : '' }}">
                    {{ $pCat->name }}
                </a>
                @endforeach

                @if($moreCategories->isNotEmpty())
                <div class="relative" x-data="{ openMore: false }" @click.away="openMore = false">
                    <button
                        type="button"
                        @click="openMore = !openMore"
                        class="px-2.5 py-1.5 flex items-center gap-1 text-slate-600 hover:text-blue-600 hover:bg-white rounded-lg transition-colors cursor-pointer whitespace-nowrap text-xs font-medium">
                        <span>More</span>
                        <svg class="w-3 h-3 transition-transform" :class="openMore ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div
                        x-show="openMore"
                        x-transition
                        class="absolute left-0 top-full mt-2 w-60 bg-white rounded-xl shadow-xl border border-slate-200 py-2 z-50 max-h-72 overflow-y-auto"
                        style="display: none;">
                        @foreach($moreCategories as $mCat)
                        <a
                            href="{{ route('storefront.category', $mCat->slug) }}"
                            class="flex items-center justify-between px-4 py-2 text-slate-700 hover:bg-blue-50 hover:text-blue-600 text-xs font-medium transition-colors">
                            <span class="truncate">{{ $mCat->name }}</span>
                            <span class="text-[10px] text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full font-semibold">{{ $mCat->products_count }}</span>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <!-- Right: Deals, New Arrivals & All Products -->
            <div class="hidden xl:flex items-center gap-3 shrink-0 font-medium">
                <a
                    href="{{ url('/products?deals=true') }}"
                    class="px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-full font-bold text-[11px] flex items-center gap-1.5 transition-colors whitespace-nowrap">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                    <span>Hot Deals & Discounts</span>
                </a>
                <a
                    href="{{ url('/products?new_arrivals=true') }}"
                    class="px-2.5 py-1.5 hover:text-blue-600 transition-colors flex items-center gap-1 whitespace-nowrap text-slate-600 text-xs">
                    <span>New Arrivals</span>
                </a>
                <a
                    href="{{ route('storefront.catalogue') }}"
                    class="px-2.5 py-1.5 text-slate-500 hover:text-slate-900 transition-colors whitespace-nowrap text-xs font-semibold">
                    <span>All Products</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Mobile Slide-out Menu Drawer -->
    <div
        x-show="mobileMenuOpen"
        class="lg:hidden fixed inset-0 z-50 flex"
        style="display: none;">
        <!-- Overlay -->
        <div
            x-show="mobileMenuOpen"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="mobileMenuOpen = false"
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"></div>

        <!-- Sidebar Panel -->
        <div
            x-show="mobileMenuOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="relative w-4/5 max-w-sm bg-white h-full shadow-2xl flex flex-col z-10 overflow-y-auto">
            <div class="p-4 bg-white border-b border-slate-200 text-slate-900 flex items-center justify-between">
                <a href="{{ url('/') }}" class="flex items-center gap-2" aria-label="Laijau Home">
                    <picture>
                        <source srcset="/images/logo.webp" type="image/webp">
                        <img src="/images/logo.png" alt="Laijau" width="105" height="28" class="h-7 w-auto object-contain" onerror="this.onerror=null; this.src='/logo.png';" decoding="async" />
                    </picture>
                    <span class="sr-only">LAIJAU.COM</span>
                </a>
                <button @click="mobileMenuOpen = false" class="p-1.5 text-slate-500 hover:text-slate-800 rounded-lg hover:bg-slate-100 transition-colors" aria-label="Close menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Mobile Search Bar inside Drawer -->
            <div class="p-4 border-b border-slate-100 bg-slate-50">
                <form action="{{ route('storefront.search') }}" method="GET">
                    <input
                        type="search"
                        name="q"
                        placeholder="Search shoes, boots, clothes..."
                        class="w-full px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:border-blue-600" />
                </form>
            </div>

            <!-- Navigation Links -->
            <div class="flex-1 px-4 py-3 divide-y divide-slate-100 text-sm font-medium text-slate-800">
                <div class="py-2">
                    <a href="{{ url('/') }}" class="block py-2 text-blue-600 font-bold">Home</a>
                    <a href="{{ route('storefront.catalogue') }}" class="block py-2">Browse All Products</a>
                    <a href="{{ url('/products?deals=true') }}" class="block py-2 text-red-600 font-semibold">⚡ Hot Deals & Discounts</a>
                    <a href="{{ url('/products?new_arrivals=true') }}" class="block py-2">New Arrivals</a>
                    <a href="{{ route('storefront.track_order') }}" class="block py-2 font-semibold text-slate-700">📍 Track Your Order</a>
                    <button
                        type="button"
                        id="mobile-menu-cart-button"
                        @click="mobileMenuOpen = false; $store.store.openCartDrawer()"
                        class="w-full text-left flex items-center justify-between py-2 font-semibold text-slate-700 hover:text-blue-600 cursor-pointer">
                        <span>🛍️ View Shopping Bag</span>
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-bold" x-text="$store.store.cartCount"></span>
                    </button>
                </div>

                <div class="py-3">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Top Categories</span>
                    @foreach($categories as $c)
                    <a
                        href="{{ route('storefront.category', $c->slug) }}"
                        class="flex items-center justify-between py-2 text-slate-700 hover:text-blue-600">
                        <span>{{ $c->name }}</span>
                        <span class="text-xs text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ $c->products_count }}</span>
                    </a>
                    @endforeach
                </div>
            </div>

            <!-- Mobile Account / Help Footer -->
            <div class="p-4 bg-slate-50 border-t border-slate-200 text-xs text-slate-600 space-y-2">
                <a href="{{ route('storefront.account') }}" class="block font-semibold text-slate-900">
                    My Account / Orders
                </a>
                <a href="https://wa.me/9779843512095" target="_blank" class="block text-emerald-600 font-medium">
                    Chat with Laijau on WhatsApp (9843512095)
                </a>
                <p class="text-[10px] text-slate-400 pt-2">
                    Cash on Delivery inside Kathmandu Valley. Nationwide fast courier delivery.
                </p>
            </div>
        </div>
    </div>

    <!-- Floating Global Toast Feedback Banner -->
    <div
        x-show="$store.store.toastVisible"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        class="fixed bottom-20 sm:bottom-6 right-4 sm:right-6 z-50 bg-white text-slate-900 px-5 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 border border-slate-200 text-xs font-semibold"
        style="display: none;">
        <div class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-xs shrink-0">✓</div>
        <span x-text="$store.store.toastMessage"></span>
        <button
            type="button"
            @click="$store.store.openCartDrawer()"
            class="ml-2 px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-[11px] font-bold cursor-pointer">
            View Cart
        </button>
    </div>
</header>