@extends('layouts.storefront')

@section('title', 'Session Expired | Laijau')
@section('description', 'Your security session has expired. Please refresh the page.')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center py-20 px-6 bg-slate-50">
    <div class="max-w-lg mx-auto text-center">
        <span class="text-xs uppercase tracking-widest text-amber-600 font-bold">419 &bull; Security Notice</span>
        <h1 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight mt-3 mb-4">Session Expired</h1>
        <div class="w-12 h-1 bg-amber-500 rounded-full mx-auto mb-6"></div>
        <p class="text-xs sm:text-sm text-slate-600 font-normal leading-relaxed mb-8">
            Your security token has expired due to inactivity. Please refresh your browser or sign in again to continue shopping safely.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="javascript:window.location.reload();" class="inline-flex items-center justify-center px-6 py-3 text-xs uppercase tracking-wider font-bold bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors shadow-sm">
                Refresh Page
            </a>
            <a href="{{ route('storefront.home') }}" class="inline-flex items-center justify-center px-6 py-3 text-xs uppercase tracking-wider font-bold border border-slate-300 text-slate-700 rounded-xl hover:bg-slate-100 transition-colors">
                Return Home
            </a>
        </div>
    </div>
</div>
@endsection
