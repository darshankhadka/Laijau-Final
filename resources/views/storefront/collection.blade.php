@extends('layouts.storefront')

@section('title', ($collection->name) . ' Collection | Laijau.com')
@section('description', \App\Helpers\StorefrontHelper::stripHtml($collection->description ?: 'Shop the ' . $collection->name . ' collection at Laijau Nepal.'))

@section('content')
<div class="bg-slate-50 min-h-screen py-6 sm:py-8 text-left">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumbs -->
        <nav class="text-xs text-slate-500 mb-4 flex items-center gap-1.5 font-medium" aria-label="Breadcrumb">
            <a href="{{ url('/') }}" class="hover:text-blue-600 transition-colors">Home</a>
            <span class="text-slate-300">/</span>
            <a href="{{ url('/collections') }}" class="hover:text-blue-600 transition-colors">Collections</a>
            <span class="text-slate-300">/</span>
            <span class="text-slate-900 font-bold capitalize">{{ $collection->name }}</span>
        </nav>

        <!-- Header -->
        <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-xs mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600 block mb-1">
                    Collection
                </span>
                <h1 class="text-xl sm:text-3xl font-black text-slate-900 tracking-tight">
                    {{ $collection->name }}
                </h1>
                @if($collection->description)
                    <p class="text-xs text-slate-500 mt-1 max-w-xl">
                        {{ $collection->description }}
                    </p>
                @endif
            </div>
            <span class="text-xs font-bold text-slate-600 bg-slate-100 px-3 py-1.5 rounded-xl self-start md:self-auto">
                {{ $products->total() }} Products
            </span>
        </div>

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
                <h3 class="text-base font-bold text-slate-900 mb-1">No products in this collection</h3>
                <p class="text-xs text-slate-500 mb-5">
                    New products for {{ $collection->name }} will be added soon. Explore our full catalog in the meantime.
                </p>
                <a href="{{ route('storefront.catalogue') }}" class="inline-block px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors shadow-xs">
                    Browse All Products
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
