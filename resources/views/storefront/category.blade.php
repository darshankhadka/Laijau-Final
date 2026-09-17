@extends('layouts.storefront')

@section('title', ($category->seo_title ?: $category->name . ' - Buy Online in Nepal') . ' | Laijau.com')
@section('description', \App\Helpers\StorefrontHelper::stripHtml($category->seo_description ?: 'Buy genuine ' . $category->name . ' online at best price in Nepal. Cash on Delivery in Kathmandu Valley, fast nationwide delivery via Laijau.'))

@section('content')
<div class="bg-slate-50 min-h-screen py-6 sm:py-8 text-left">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumbs -->
        <nav class="text-xs text-slate-500 mb-4 flex items-center gap-1.5 font-medium flex-wrap" aria-label="Breadcrumb">
            <a href="{{ url('/') }}" class="hover:text-blue-600 transition-colors">Home</a>
            <span class="text-slate-300">/</span>
            <a href="{{ route('storefront.catalogue') }}" class="hover:text-blue-600 transition-colors">Catalog</a>
            @if($category->parent)
                <span class="text-slate-300">/</span>
                <a href="{{ route('storefront.category', $category->parent->slug) }}" class="hover:text-blue-600 transition-colors">{{ $category->parent->name }}</a>
            @endif
            <span class="text-slate-300">/</span>
            <span class="text-slate-900 font-bold capitalize">{{ $category->name }}</span>
        </nav>

        <!-- Category Header -->
        <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-xs mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 bg-blue-100 text-blue-800 rounded-md text-[10px] font-bold uppercase tracking-wider">Category</span>
                    <h1 class="text-xl sm:text-3xl font-black text-slate-900 tracking-tight">
                        {{ $category->name }}
                    </h1>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    {{ $category->description ?: "Explore all authentic {$category->name} with live inventory and genuine Nepal pricing." }}
                </p>
            </div>
            <span class="text-xs font-bold text-slate-600 bg-slate-100 px-3 py-1.5 rounded-xl self-start md:self-auto">
                {{ $products->total() }} Products
            </span>
        </div>

        <!-- Subcategories Pills (if any) -->
        @if($category->children && $category->children->count() > 0)
            <div class="flex items-center gap-2 overflow-x-auto pb-3 mb-6 no-scrollbar">
                <span class="text-xs font-semibold text-slate-400 whitespace-nowrap">Subcategories:</span>
                @foreach($category->children as $child)
                    <a href="{{ route('storefront.category', $child->slug) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-blue-50 text-slate-700 hover:text-blue-700 border border-slate-200 hover:border-blue-200 rounded-xl text-xs font-medium transition-all shadow-2xs whitespace-nowrap">
                        <span>{{ $child->name }}</span>
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
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-1">No products in this category right now</h3>
                <p class="text-xs text-slate-500 mb-5">
                    We frequently restock our catalog. Check out our other popular categories or browse all products.
                </p>
                <a href="{{ route('storefront.catalogue') }}" class="inline-block px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors shadow-xs">
                    Browse All Products
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
