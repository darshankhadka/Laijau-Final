@extends('layouts.storefront')

@section('title', 'Featured Collections | Laijau.com')
@section('description', 'Discover curated product collections, footwear edits, and trending styles on Laijau Nepal. Nationwide delivery across all 77 districts.')

@php
use App\Helpers\StorefrontHelper;
@endphp

@section('content')
<div class="bg-slate-50 min-h-screen py-8 sm:py-12 text-left">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumbs -->
        <nav class="text-xs text-slate-500 mb-6 flex items-center gap-1.5 font-medium" aria-label="Breadcrumb">
            <a href="{{ url('/') }}" class="hover:text-blue-600 transition-colors">Home</a>
            <span class="text-slate-300">/</span>
            <span class="text-slate-900 font-bold">Collections</span>
        </nav>

        <!-- Header -->
        <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs mb-8">
            <span class="text-[10px] font-bold tracking-wider text-blue-600 uppercase block mb-1">
                Curated Edits
            </span>
            <h1 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight">
                Featured Collections
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-xl">
                Explore curated product series, seasonal footwear drops, and trending lifestyle picks.
            </p>
        </div>

        <!-- Collections Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($collections as $col)
            @php
            $colImg = $col->image
            ? StorefrontHelper::getProductDerivativeUrl($col->image, 'card')
            : StorefrontHelper::getImageUrl('products/shoe-collection.webp');
            @endphp
            <a
                href="{{ url('/collections/' . $col->slug) }}"
                class="group flex flex-col bg-white rounded-2xl overflow-hidden border border-slate-200 shadow-xs hover:shadow-md hover:border-blue-300 transition-all">
                <div class="relative aspect-[16/10] sm:aspect-[4/3] bg-slate-100 overflow-hidden">
                    <img
                        src="{{ $colImg }}"
                        alt="{{ $col->name }}"
                        width="400"
                        height="300"
                        loading="lazy"
                        decoding="async"
                        class="h-full w-full object-cover object-center group-hover:scale-105 transition-transform duration-500" />
                    <div class="absolute top-3 left-3">
                        <span class="px-2.5 py-1 bg-white/95 text-blue-700 text-[10px] font-extrabold uppercase tracking-wider rounded-lg shadow-xs">
                            Collection
                        </span>
                    </div>
                </div>
                <div class="p-5 flex flex-col flex-1 justify-between bg-white">
                    <div>
                        <h2 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight group-hover:text-blue-600 transition-colors mb-1">
                            {{ $col->name }}
                        </h2>
                        @if($col->description)
                        <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed mb-3">
                            {{ $col->description }}
                        </p>
                        @endif
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-blue-600 group-hover:text-blue-700">
                        <span>Explore Products</span>
                        <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
                    </div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
</div>
@endsection