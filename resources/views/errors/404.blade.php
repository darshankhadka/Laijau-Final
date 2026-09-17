@extends('layouts.storefront')

@section('title', 'Page Not Found | Laijau')
@section('description', 'The product or page you are looking for may have moved or is unavailable.')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center py-20 px-6 bg-slate-50">
    <div class="max-w-lg mx-auto text-center">
        <span class="text-xs uppercase tracking-widest text-blue-600 font-bold">404 &bull; Not Found</span>
        <h1 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight mt-3 mb-4">Page Not Found</h1>
        <div class="w-12 h-1 bg-blue-600 rounded-full mx-auto mb-6"></div>
        <p class="text-xs sm:text-sm text-slate-600 font-normal leading-relaxed mb-8">
            The product or page you requested is no longer available or may have moved. Browse our latest arrivals or return to the homepage.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('storefront.catalogue') }}" class="inline-flex items-center justify-center px-6 py-3 text-xs uppercase tracking-wider font-bold bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors shadow-sm">
                Browse Products
            </a>
            <a href="{{ route('storefront.home') }}" class="inline-flex items-center justify-center px-6 py-3 text-xs uppercase tracking-wider font-bold border border-slate-300 text-slate-700 rounded-xl hover:bg-slate-100 transition-colors">
                Return Home
            </a>
        </div>
    </div>
</div>
@endsection
