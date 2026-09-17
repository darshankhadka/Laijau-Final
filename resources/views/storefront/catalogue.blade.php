@extends('layouts.storefront')

@section('title', ($activeCategory && $activeCategory !== 'all' ? ucwords(str_replace('-', ' ', $activeCategory)) : ($isNewArrivals ? 'New Arrivals' : 'All Products')) . ' | Laijau.com')
@section('description', 'Browse authentic footwear, sneakers, boots, and apparel on Laijau Nepal. Cash on Delivery in Kathmandu Valley and fast nationwide delivery.')

@section('content')
<div class="bg-slate-50 min-h-screen py-6 sm:py-8 text-left" x-data="{ mobileFiltersOpen: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Breadcrumbs -->
        <nav class="text-xs text-slate-500 mb-4 flex items-center gap-1.5 font-medium" aria-label="Breadcrumb">
            <a href="{{ url('/') }}" class="hover:text-blue-600 transition-colors">Home</a>
            <span class="text-slate-300">/</span>
            <a href="{{ route('storefront.catalogue') }}" class="hover:text-blue-600 transition-colors">Catalog</a>
            @if($activeCategory && $activeCategory !== 'all')
                <span class="text-slate-300">/</span>
                <span class="text-slate-900 font-bold capitalize">{{ str_replace('-', ' ', $activeCategory) }}</span>
            @endif
        </nav>

        <!-- Page Header & Active Filter Pills -->
        <div class="bg-white p-4 sm:p-6 rounded-2xl border border-slate-200 shadow-xs mb-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-3xl font-black text-slate-900 tracking-tight capitalize">
                        @if($isNewArrivals)
                            New Arrivals
                        @elseif($activeCategory && $activeCategory !== 'all')
                            {{ str_replace('-', ' ', $activeCategory) }}
                        @elseif($activeSearch)
                            Search results for "{{ $activeSearch }}"
                        @else
                            All Products
                        @endif
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Showing <strong class="text-slate-900 font-bold">{{ $products->total() }}</strong> authentic products with live inventory
                    </p>
                </div>

                <!-- Sort & Mobile Filter Toggle -->
                <div class="flex items-center gap-3">
                    <!-- Mobile Filter Trigger -->
                    <button
                        type="button"
                        @click="mobileFiltersOpen = true"
                        class="lg:hidden flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-xl transition-colors cursor-pointer"
                    >
                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        <span>Filters</span>
                    </button>

                    <!-- Sort Selector Form -->
                    <form method="GET" action="{{ route('storefront.catalogue') }}" class="flex items-center gap-2">
                        @if($activeCategory && $activeCategory !== 'all')
                            <input type="hidden" name="category" value="{{ $activeCategory }}">
                        @endif
                        @if($activeSearch)
                            <input type="hidden" name="search" value="{{ $activeSearch }}">
                        @endif
                        @if($isNewArrivals)
                            <input type="hidden" name="new_arrivals" value="true">
                        @endif
                        @if($inStockOnly)
                            <input type="hidden" name="in_stock" value="1">
                        @endif
                        @if($dealsOnly)
                            <input type="hidden" name="deals" value="1">
                        @endif
                        @if($minPrice)
                            <input type="hidden" name="min_price" value="{{ $minPrice }}">
                        @endif
                        @if($maxPrice)
                            <input type="hidden" name="max_price" value="{{ $maxPrice }}">
                        @endif

                        <label for="sort" class="text-xs font-semibold text-slate-500 hidden sm:inline">Sort:</label>
                        <select
                            id="sort"
                            name="sort"
                            onchange="this.form.submit()"
                            class="px-3 py-2 bg-slate-100 border border-slate-300 text-xs font-semibold text-slate-800 rounded-xl focus:outline-none focus:border-blue-600 cursor-pointer"
                        >
                            <option value="newest" {{ $activeSort === 'newest' ? 'selected' : '' }}>Newest First</option>
                            <option value="popular" {{ $activeSort === 'popular' ? 'selected' : '' }}>Popular & Featured</option>
                            <option value="price_asc" {{ $activeSort === 'price_asc' ? 'selected' : '' }}>Price: Low &rarr; High</option>
                            <option value="price_desc" {{ $activeSort === 'price_desc' ? 'selected' : '' }}>Price: High &rarr; Low</option>
                            <option value="discount" {{ $activeSort === 'discount' ? 'selected' : '' }}>Biggest Discount</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Active Filters List -->
            @if(($activeCategory && $activeCategory !== 'all') || $activeSearch || $inStockOnly || $dealsOnly || $minPrice || $maxPrice)
                <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center gap-2">
                    <span class="text-[11px] font-bold text-slate-400 uppercase">Active Filters:</span>
                    @if($activeCategory && $activeCategory !== 'all')
                        <a href="{{ request()->fullUrlWithQuery(['category' => null, 'page' => null]) }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold">
                            <span>Category: {{ str_replace('-', ' ', $activeCategory) }}</span>
                            <span>&times;</span>
                        </a>
                    @endif
                    @if($dealsOnly)
                        <a href="{{ request()->fullUrlWithQuery(['deals' => null, 'page' => null]) }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-red-50 text-red-700 text-xs font-semibold">
                            <span>Deals Only</span>
                            <span>&times;</span>
                        </a>
                    @endif
                    @if($inStockOnly)
                        <a href="{{ request()->fullUrlWithQuery(['in_stock' => null, 'page' => null]) }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-semibold">
                            <span>In Stock Only</span>
                            <span>&times;</span>
                        </a>
                    @endif
                    @if($minPrice || $maxPrice)
                        <a href="{{ request()->fullUrlWithQuery(['min_price' => null, 'max_price' => null, 'page' => null]) }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold">
                            <span>Rs. {{ number_format($minPrice ?: 0) }} - {{ $maxPrice ? number_format($maxPrice) : 'Any' }}</span>
                            <span>&times;</span>
                        </a>
                    @endif
                    <a href="{{ route('storefront.catalogue') }}" class="text-xs font-bold text-red-600 hover:underline ml-2">
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <!-- Main Layout: Sidebar Filters + Products Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Desktop Sidebar Filter Panel -->
            <aside class="hidden lg:block lg:col-span-3 space-y-6">
                <!-- Categories Filter -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 mb-3 pb-2 border-b border-slate-100">Categories</h3>
                    <div class="space-y-1 max-h-80 overflow-y-auto pr-1">
                        <a
                            href="{{ request()->fullUrlWithQuery(['category' => null, 'page' => null]) }}"
                            class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors {{ empty($activeCategory) || $activeCategory === 'all' ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600 hover:bg-slate-100' }}"
                        >
                            <span>All Categories</span>
                        </a>
                        @foreach($categories as $cat)
                            <a
                                href="{{ request()->fullUrlWithQuery(['category' => $cat->slug, 'page' => null]) }}"
                                class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $activeCategory === $cat->slug ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600 hover:bg-slate-100' }}"
                            >
                                <span class="truncate">{{ $cat->name }}</span>
                                <span class="text-[10px] text-slate-400 font-semibold bg-slate-100 px-1.5 py-0.5 rounded-md">{{ $cat->products_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Price Range Filter Form -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 mb-3 pb-2 border-b border-slate-100">Price Range (NPR)</h3>
                    <form method="GET" action="{{ route('storefront.catalogue') }}" class="space-y-3">
                        @if($activeCategory && $activeCategory !== 'all')
                            <input type="hidden" name="category" value="{{ $activeCategory }}">
                        @endif
                        @if($activeSort)
                            <input type="hidden" name="sort" value="{{ $activeSort }}">
                        @endif
                        @if($inStockOnly)
                            <input type="hidden" name="in_stock" value="1">
                        @endif
                        @if($dealsOnly)
                            <input type="hidden" name="deals" value="1">
                        @endif

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="min_price" class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Min</label>
                                <input
                                    type="number"
                                    id="min_price"
                                    name="min_price"
                                    placeholder="Rs. 0"
                                    value="{{ $minPrice }}"
                                    class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-900 focus:outline-none focus:border-blue-600"
                                />
                            </div>
                            <div>
                                <label for="max_price" class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Max</label>
                                <input
                                    type="number"
                                    id="max_price"
                                    name="max_price"
                                    placeholder="Rs. 10,000"
                                    value="{{ $maxPrice }}"
                                    class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-900 focus:outline-none focus:border-blue-600"
                                />
                            </div>
                        </div>
                        <button
                            type="submit"
                            class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition-colors cursor-pointer"
                        >
                            Apply Price
                        </button>
                    </form>
                </div>

                <!-- Quick Toggle Filters -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-3">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 pb-2 border-b border-slate-100">Preferences</h3>

                    <label class="flex items-center gap-2.5 cursor-pointer text-xs font-medium text-slate-700">
                        <input
                            type="checkbox"
                            {{ $dealsOnly ? 'checked' : '' }}
                            onchange="window.location.href = this.checked ? '{{ request()->fullUrlWithQuery(['deals' => '1', 'page' => null]) }}' : '{{ request()->fullUrlWithQuery(['deals' => null, 'page' => null]) }}'"
                            class="w-4 h-4 rounded text-red-600 focus:ring-red-500 border-slate-300"
                        />
                        <span>Discounted Deals Only</span>
                    </label>

                    <label class="flex items-center gap-2.5 cursor-pointer text-xs font-medium text-slate-700">
                        <input
                            type="checkbox"
                            {{ $inStockOnly ? 'checked' : '' }}
                            onchange="window.location.href = this.checked ? '{{ request()->fullUrlWithQuery(['in_stock' => '1', 'page' => null]) }}' : '{{ request()->fullUrlWithQuery(['in_stock' => null, 'page' => null]) }}'"
                            class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 border-slate-300"
                        />
                        <span>In Stock Only</span>
                    </label>
                </div>
            </aside>

            <!-- Product Grid -->
            <main class="lg:col-span-9">
                @if($products->isEmpty())
                    <!-- Empty State -->
                    <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center space-y-4">
                        <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h2 class="text-lg font-bold text-slate-900">No products match your active filters</h2>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto">
                            Try broadening your search, adjusting the price range, or selecting a different category.
                        </p>
                        <div class="pt-2">
                            <a
                                href="{{ route('storefront.catalogue') }}"
                                class="inline-block px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors shadow-xs"
                            >
                                Reset All Filters
                            </a>
                        </div>
                    </div>
                @else
                    <!-- 4-Column Grid on Desktop, 3 on Tablet, 2 on Mobile -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4">
                        @foreach($products as $prod)
                            <x-storefront.product-card :product="$prod" />
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="mt-8 pt-6 border-t border-slate-200">
                        {{ $products->links() }}
                    </div>
                @endif
            </main>
        </div>
    </div>

    <!-- Mobile Filter Drawer (Modal) -->
    <div
        x-show="mobileFiltersOpen"
        class="lg:hidden fixed inset-0 z-50 flex justify-end"
        style="display: none;"
    >
        <div
            x-show="mobileFiltersOpen"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="mobileFiltersOpen = false"
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"
        ></div>

        <div
            x-show="mobileFiltersOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="relative w-4/5 max-w-sm bg-white h-full shadow-2xl flex flex-col z-10 overflow-y-auto"
        >
            <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <span class="text-sm font-bold text-slate-900">Filter Products</span>
                <button @click="mobileFiltersOpen = false" class="p-1 text-slate-400 hover:text-slate-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-5 space-y-6 flex-1 overflow-y-auto">
                <!-- Categories -->
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-2">Categories</h4>
                    <div class="space-y-1 max-h-60 overflow-y-auto">
                        <a
                            href="{{ request()->fullUrlWithQuery(['category' => null, 'page' => null]) }}"
                            class="block py-1.5 text-xs font-medium {{ empty($activeCategory) || $activeCategory === 'all' ? 'text-blue-600 font-bold' : 'text-slate-600' }}"
                        >
                            All Categories
                        </a>
                        @foreach($categories as $cat)
                            <a
                                href="{{ request()->fullUrlWithQuery(['category' => $cat->slug, 'page' => null]) }}"
                                class="flex items-center justify-between py-1.5 text-xs font-medium {{ $activeCategory === $cat->slug ? 'text-blue-600 font-bold' : 'text-slate-600' }}"
                            >
                                <span>{{ $cat->name }}</span>
                                <span class="text-[10px] text-slate-400">({{ $cat->products_count }})</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Price Range -->
                <div class="pt-4 border-t border-slate-100">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-2">Price Range</h4>
                    <form method="GET" action="{{ route('storefront.catalogue') }}" class="space-y-2">
                        @if($activeCategory && $activeCategory !== 'all')
                            <input type="hidden" name="category" value="{{ $activeCategory }}">
                        @endif
                        <div class="grid grid-cols-2 gap-2">
                            <input
                                type="number"
                                name="min_price"
                                placeholder="Min"
                                value="{{ $minPrice }}"
                                class="w-full px-3 py-1.5 text-xs border border-slate-200 rounded-lg"
                            />
                            <input
                                type="number"
                                name="max_price"
                                placeholder="Max"
                                value="{{ $maxPrice }}"
                                class="w-full px-3 py-1.5 text-xs border border-slate-200 rounded-lg"
                            />
                        </div>
                        <button type="submit" class="w-full py-2 bg-blue-600 text-white text-xs font-bold rounded-lg">
                            Apply Filter
                        </button>
                    </form>
                </div>
            </div>

            <div class="p-4 bg-slate-50 border-t border-slate-200">
                <a href="{{ route('storefront.catalogue') }}" class="block text-center text-xs font-bold text-red-600 py-2">
                    Reset All Filters
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
