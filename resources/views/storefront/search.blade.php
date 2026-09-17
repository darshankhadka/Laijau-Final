@extends('layouts.storefront')

@section('title', 'Search: ' . ($queryText ?: 'All Products') . ' | Laijau.com')
@section('description', 'Search over 1,000+ shoes, sneakers, boots, slippers, and apparel online at Laijau Nepal. Fast delivery across Kathmandu Valley and nationwide.')

@section('content')
<div class="bg-slate-50 min-h-screen py-6 sm:py-8 text-left">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumbs -->
        <nav class="text-xs text-slate-500 mb-4 flex items-center gap-1.5 font-medium" aria-label="Breadcrumb">
            <a href="{{ url('/') }}" class="hover:text-blue-600 transition-colors">Home</a>
            <span class="text-slate-300">/</span>
            <a href="{{ route('storefront.catalogue') }}" class="hover:text-blue-600 transition-colors">Catalog</a>
            <span class="text-slate-300">/</span>
            <span class="text-slate-900 font-bold">Search</span>
        </nav>

        <!-- Header -->
        <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-xs mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600 block mb-1">
                    Search Results
                </span>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                    @if($queryText)
                        Results for "<span class="text-blue-600">{{ $queryText }}</span>"
                    @else
                        Browse All Products
                    @endif
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Found <strong class="text-slate-900 font-bold">{{ $products->total() }}</strong> matching products in the Laijau catalog
                </p>
            </div>

            <!-- In-page Search Bar -->
            <form action="{{ route('storefront.search') }}" method="GET" class="flex items-center gap-2 max-w-md w-full">
                <input
                    type="search"
                    name="q"
                    value="{{ $queryText }}"
                    placeholder="Search again..."
                    class="w-full px-3.5 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-blue-600"
                />
                <button
                    type="submit"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-colors cursor-pointer shrink-0 shadow-xs"
                >
                    Search
                </button>
            </form>
        </div>

        @if(!empty($isRelaxed))
            <div class="mb-5 p-3.5 bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl text-xs flex items-center gap-2.5 shadow-2xs">
                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Exact match not found for <strong>"{{ $queryText }}"</strong>. Showing relevant items matching your keywords.</span>
            </div>
        @endif

        @if(!empty($matchingCategories) && $matchingCategories->isNotEmpty())
            <div class="mb-5 flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Related Categories:</span>
                @foreach($matchingCategories as $cat)
                    <a href="{{ route('storefront.category', $cat->slug) }}" class="inline-flex items-center gap-1.5 px-3 py-1 bg-white hover:bg-blue-50 hover:text-blue-700 hover:border-blue-300 border border-slate-200 text-xs font-medium text-slate-700 rounded-full transition-colors shadow-2xs">
                        <span>{{ $cat->name }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <!-- Products Grid -->
        @if($products->count() > 0)
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
                @foreach($products as $idx => $product)
                    <x-storefront.product-card :product="$product" :priority="$idx < 5" />
                @endforeach
            </div>

            <div class="mt-8 pt-6 border-t border-slate-200 flex justify-center">
                {{ $products->links() }}
            </div>
        @else
            <div class="bg-white py-16 px-6 text-center max-w-md mx-auto rounded-3xl border border-slate-200">
                <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-1">No products found for "{{ $queryText }}"</h3>
                <p class="text-xs text-slate-500 mb-5 leading-relaxed">
                    Check your spelling or try searching for more general terms like "shoes", "sneakers", "boots", or "slippers".
                </p>
                <div class="flex flex-col sm:flex-row gap-2 justify-center">
                    <a href="{{ route('storefront.catalogue') }}" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors shadow-xs">
                        Browse All Products
                    </a>
                    <a href="{{ url('/') }}" class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors">
                        Go to Homepage
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
