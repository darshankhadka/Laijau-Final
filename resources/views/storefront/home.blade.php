@extends('layouts.storefront')

@section('title', 'Laijau.com — Online Shopping Nepal | Shoes, Boots, Sneakers & Apparel')
@section('description', 'Buy authentic shoes, sneakers, boots, party wear, slippers and apparel online at Laijau Nepal. Cash on Delivery in Kathmandu Valley, fast nationwide courier dispatch across all 77 districts.')

@push('head')
    <link rel="preload" as="image" href="/images/hero-banner.webp" type="image/webp" fetchpriority="high">
@endpush

@section('content')
<div class="flex flex-col min-h-screen bg-slate-50 space-y-8 sm:space-y-12 pb-12">    <!-- 1. MINIMALIST IMAGE BANNER HERO -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 sm:pt-6 w-full">
        <div class="relative overflow-hidden rounded-2xl sm:rounded-3xl shadow-lg border border-slate-900/10 bg-slate-950 group min-h-[380px] sm:min-h-[450px] lg:min-h-[480px] flex items-center">
            <!-- Background Image with Ambient Zoom Effect (LCP Optimized) -->
            <picture class="absolute inset-0 w-full h-full">
                <source media="(max-width: 640px)" srcset="/images/hero-banner-mobile.webp" type="image/webp">
                <source srcset="/images/hero-banner.webp" type="image/webp">
                <img
                    src="/images/hero-banner.webp"
                    alt="Laijau Footwear and Apparel Nepal"
                    width="1280"
                    height="480"
                    class="w-full h-full object-cover object-[75%_center] sm:object-center transform scale-100 group-hover:scale-105 transition-transform duration-1000 ease-out"
                    loading="eager"
                    fetchpriority="high"
                    decoding="async"
                />
            </picture>

            <!-- Clean Left Vignette for Readability -->
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/60 to-transparent sm:via-slate-950/45 sm:to-transparent pointer-events-none"></div>

            <!-- Content Container -->
            <div class="relative z-10 w-full px-6 py-10 sm:px-12 sm:py-14 lg:px-16 flex flex-col justify-center">
                <div class="max-w-xl">
                    <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white leading-[1.1] font-display">
                        Shoes made for <br class="hidden sm:inline" />
                        <span class="text-amber-200">everyday life.</span>
                    </h1>

                    <p class="mt-3.5 sm:mt-4 text-sm sm:text-base text-slate-200/90 max-w-lg leading-relaxed font-normal">
                        From leather boots to clean daily sneakers and jackets. Find a pair that fits right, looks good, and lasts.
                    </p>

                    <div class="mt-6 sm:mt-8 flex flex-wrap items-center gap-3 sm:gap-4">
                        <a
                            href="{{ route('storefront.catalogue') }}"
                            class="px-6 py-3 sm:px-7 sm:py-3.5 bg-white hover:bg-slate-100 active:bg-slate-200 text-slate-950 text-xs sm:text-sm font-bold rounded-xl transition-all shadow-md hover:shadow-xl hover:-translate-y-0.5 flex items-center gap-2 group/btn"
                        >
                            <span>Shop All Shoes</span>
                            <svg class="w-4 h-4 transition-transform group-hover/btn:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                        <a
                            href="{{ url('/products?deals=true') }}"
                            class="px-5 py-3 sm:px-6 sm:py-3.5 bg-white/10 hover:bg-white/20 active:bg-white/25 text-white backdrop-blur-md border border-white/20 text-xs sm:text-sm font-semibold rounded-xl transition-all hover:-translate-y-0.5 flex items-center gap-1.5"
                        >
                            <span>Special Deals</span>
                        </a>
                        <a
                            href="{{ route('storefront.track_order') }}"
                            class="px-3 py-3 text-slate-300 hover:text-white text-xs sm:text-sm font-medium transition-colors"
                        >
                            Track Order
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 2. SHOP BY CATEGORY (SINGLE-LINE DISCOVERY) -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
        <!-- Single-Line Header (No Subtitle) -->
        <div class="flex items-center justify-between mb-3.5">
            <div class="flex items-center gap-2">
                <h2 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">Shop by Category</h2>
                <span class="text-xs font-semibold text-slate-400">({{ $categories->count() }})</span>
            </div>
            <a href="{{ route('storefront.catalogue') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-950 flex items-center gap-1 transition-colors group">
                <span>View All</span>
                <svg class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <!-- Single-Line Category Track (No 2-line wrapping) -->
        <div class="flex items-center gap-2.5 sm:gap-3 overflow-x-auto no-scrollbar scroll-smooth py-1 -mx-4 px-4 sm:mx-0 sm:px-0">
            @foreach($categories as $cat)
                @php
                    $rawImg = $cat->image ?: $cat->products->first()?->featured_image;
                    $imgUrl = \App\Helpers\StorefrontHelper::getProductDerivativeUrl($rawImg, 'thumbnail');
                @endphp
                <a
                    href="{{ route('storefront.category', $cat->slug) }}"
                    class="group flex-none inline-flex items-center gap-3 px-3.5 py-2.5 bg-white rounded-xl border border-slate-200/90 hover:border-slate-400 hover:shadow-sm transition-all shadow-2xs"
                >
                    <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-lg bg-slate-100 overflow-hidden shrink-0 border border-slate-200/60 group-hover:scale-105 transition-transform">
                        @if($imgUrl)
                            <img
                                src="{{ $imgUrl }}"
                                alt="{{ $cat->name }}"
                                width="44"
                                height="44"
                                class="w-full h-full object-cover object-center"
                                loading="lazy"
                                decoding="async"
                            />
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-slate-100 text-slate-400 font-bold text-sm">
                                👞
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-col text-left pr-1">
                        <span class="text-xs sm:text-sm font-bold text-slate-900 group-hover:text-blue-600 transition-colors whitespace-nowrap">
                            {{ $cat->name }}
                        </span>
                        <span class="text-[11px] text-slate-400 font-medium whitespace-nowrap">
                            {{ $cat->products_count }} {{ Str::plural('pair', $cat->products_count) }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <!-- 3. HOT DEALS & DISCOUNTS (FLASH SECTION) -->
    @if($dealProducts->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
            <div class="p-4 sm:p-6 bg-rose-50/70 rounded-2xl border border-rose-200">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 mb-5">
                    <div class="flex items-center gap-2.5">
                        <span class="px-2.5 py-1 bg-red-600 text-white rounded-md text-xs font-bold uppercase tracking-wider">
                            Special Deals
                        </span>
                        <h2 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight">Best Discounts in Nepal</h2>
                    </div>
                    <a href="{{ url('/products?deals=true') }}" class="text-xs font-bold text-red-600 hover:text-red-800 flex items-center gap-1 transition-colors">
                        <span>See All Discounts &rarr;</span>
                    </a>
                </div>

                <!-- Product Grid (5 columns on desktop, 2 on mobile) -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
                    @foreach($dealProducts->take(10) as $dealProd)
                        <x-storefront.product-card :product="$dealProd" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <!-- 4. TRENDING NOW (POPULAR PRODUCTS) -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight">Trending Now</h2>
                <p class="text-xs text-slate-500 mt-0.5">Most sought-after styles and popular picks across Nepal</p>
            </div>
            <a href="{{ route('storefront.catalogue') }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 transition-colors">
                <span>View More</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
            @foreach($trendingProducts as $tProd)
                <x-storefront.product-card :product="$tProd" />
            @endforeach
        </div>
    </section>

    <!-- 5. NEW ARRIVALS -->
    @if($newArrivals->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="px-2 py-0.5 bg-blue-600 text-white rounded-md text-[10px] font-extrabold uppercase">New</span>
                    <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight">New Arrivals</h2>
                </div>
                <a href="{{ url('/products?new_arrivals=true') }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 transition-colors">
                    <span>Explore All New &rarr;</span>
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
                @foreach($newArrivals as $nProd)
                    <x-storefront.product-card :product="$nProd" />
                @endforeach
            </div>
        </section>
    @endif

    <!-- 6. CATEGORY-SPECIFIC HIGHLIGHT (FOOTWEAR & SHOES SHOWCASE) -->
    @if($showcaseCategory && $showcaseProducts->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
            <div class="p-4 sm:p-6 bg-white rounded-3xl border border-slate-200/90 shadow-sm">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 mb-5">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600 block">Featured Collection</span>
                        <h2 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight">{{ $showcaseCategory->name }}</h2>
                    </div>
                    <a href="{{ route('storefront.category', $showcaseCategory->slug) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 transition-colors">
                        <span>Browse {{ $showcaseCategory->name }} ({{ $showcaseCategory->products_count }}) &rarr;</span>
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
                    @foreach($showcaseProducts->take(10) as $sProd)
                        <x-storefront.product-card :product="$sProd" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <!-- 7. EXPLORE ALL PRODUCTS (DENSE DISCOVERY GRID) -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg sm:text-2xl font-black text-slate-900 tracking-tight">Explore More Products</h2>
                <p class="text-xs text-slate-500 mt-0.5">Authentic options selected from our 1,000+ catalog</p>
            </div>
            <a href="{{ route('storefront.catalogue') }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 transition-colors">
                <span>View Full Catalog</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
            @foreach($exploreProducts as $expProd)
                <x-storefront.product-card :product="$expProd" />
            @endforeach
        </div>

        <div class="mt-8 text-center">
            <a
                href="{{ route('storefront.catalogue') }}"
                class="inline-flex items-center justify-center gap-2 px-8 py-3.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold uppercase tracking-wider rounded-xl transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5"
            >
                <span>Browse All Products</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        </div>
    </section>

</div>
@endsection
