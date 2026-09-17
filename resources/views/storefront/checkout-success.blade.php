@extends('layouts.storefront')

@section('title', 'Order Confirmed | Laijau.com')
@section('description', 'Your Laijau order confirmation, Nepal delivery address, and live status tracking.')

@php
use App\Helpers\StorefrontHelper;
@endphp

@section('content')
<div
    class="bg-slate-50 min-h-screen py-10 sm:py-16 text-left"
    x-data="{
        init() {
            @if($order && ($order->payment_status === 'paid' || $order->status !== 'cancelled'))
                $store.store.clearCart();
            @endif
        }
    }">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        @if(!$order)
        <div class="py-16 text-center max-w-md mx-auto bg-white p-8 sm:p-12 rounded-3xl border border-slate-200 shadow-xs">
            <div class="h-16 w-16 mx-auto rounded-2xl bg-blue-50 flex items-center justify-center mb-5 text-blue-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 mb-2">Order Received</h1>
            <p class="text-xs sm:text-sm text-slate-500 mb-6 leading-relaxed">
                Thank you for placing your order with Laijau. If you have an order reference number, you can track its delivery status directly.
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-3">
                <a href="{{ url('/track-order') }}" class="px-6 py-3 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-xs">
                    Track Your Order
                </a>
                <a href="{{ route('storefront.catalogue') }}" class="px-6 py-3 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-200 transition-colors">
                    Continue Shopping
                </a>
            </div>
        </div>
        @else
        <!-- Success Header Banner -->
        <div class="text-center max-w-xl mx-auto mb-10">
            <div class="w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center mx-auto mb-4 shadow-xs text-2xl font-bold">
                ✓
            </div>

            @if($order->payment_method === 'cod')
            <span class="text-[10px] font-bold tracking-wider uppercase text-emerald-700 bg-emerald-50 border border-emerald-100 px-3 py-1 rounded-full inline-block mb-3">
                Cash on Delivery &bull; Kathmandu Valley
            </span>
            <h1 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight mb-2">
                Order #{{ $order->order_number }}
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                Namaste <strong>{{ $order->first_name }}</strong>, your order has been received! Our dispatch team in Kathmandu will call you at <strong>{{ $order->phone }}</strong> to confirm delivery timing.
            </p>
            @elseif($order->payment_status === 'paid')
            <span class="text-[10px] font-bold tracking-wider uppercase text-emerald-700 bg-emerald-50 border border-emerald-100 px-3 py-1 rounded-full inline-block mb-3">
                Payment Verified & Confirmed
            </span>
            <h1 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight mb-2">
                Order #{{ $order->order_number }}
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                Your payment has been successfully verified! Your order is now being packed for fast courier dispatch.
            </p>
            @else
            <span class="text-[10px] font-bold tracking-wider uppercase text-amber-700 bg-amber-50 border border-amber-100 px-3 py-1 rounded-full inline-block mb-3">
                Payment Verification Pending
            </span>
            <h1 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight mb-2">
                Order #{{ $order->order_number }}
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                Thank you for your order via <strong>{{ strtoupper(str_replace('_', ' ', $order->payment_method)) }}</strong>. Our team is verifying your transaction reference and will dispatch your package shortly.
            </p>
            @endif
        </div>

        <!-- Order Confirmation Card -->
        <div class="bg-white p-6 sm:p-10 rounded-3xl border border-slate-200 shadow-xs space-y-8">
            <!-- Order Status Row -->
            <div class="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-slate-100 text-xs">
                <div>
                    <span class="text-slate-400 block uppercase tracking-wider text-[10px] font-semibold">Order Date</span>
                    <span class="font-bold text-slate-900">{{ $order->created_at->timezone('Asia/Kathmandu')->format('d M Y, h:i A') }} NPT</span>
                </div>
                <div>
                    <span class="text-slate-400 block uppercase tracking-wider text-[10px] font-semibold">Payment Method</span>
                    <span class="font-bold text-slate-900 uppercase">
                        {{ str_replace('_', ' ', $order->payment_method) }}
                    </span>
                </div>
                <div>
                    <span class="text-slate-400 block uppercase tracking-wider text-[10px] font-semibold">Payment Status</span>
                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase {{ $order->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                    </span>
                </div>
                <div>
                    <span class="text-slate-400 block uppercase tracking-wider text-[10px] font-semibold">Order Status</span>
                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase bg-blue-50 text-blue-700 border border-blue-100">
                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                    </span>
                </div>
            </div>

            <!-- Items Ordered List -->
            <div class="space-y-4">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">
                    Ordered Items ({{ $order->items->count() }})
                </h3>
                <div class="divide-y divide-slate-100">
                    @foreach($order->items as $item)
                    <div class="py-3.5 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3.5">
                            <div class="h-16 w-14 bg-slate-50 rounded-xl shrink-0 overflow-hidden border border-slate-200">
                                <img
                                    src="{{ $item->product && $item->product->featured_image ? StorefrontHelper::getProductDerivativeUrl($item->product->featured_image, 'thumbnail') : '' }}"
                                    alt="{{ $item->product_name }}"
                                    width="56"
                                    height="64"
                                    loading="lazy"
                                    decoding="async"
                                    class="h-full w-full object-cover object-center" />
                            </div>
                            <div>
                                <h4 class="text-xs sm:text-sm font-bold text-slate-900">{{ $item->product_name }}</h4>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    Qty: {{ $item->quantity }}
                                    @if($item->selected_size || $item->selected_color)
                                    &bull; {{ $item->selected_size ?: '' }} {{ $item->selected_color ? '(' . $item->selected_color . ')' : '' }}
                                    @elseif($item->size || $item->color)
                                    &bull; {{ $item->size ?: '' }} {{ $item->color ? '(' . $item->color . ')' : '' }}
                                    @endif
                                    @if($item->sku)
                                    &bull; SKU: {{ $item->sku }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        <span class="text-xs sm:text-sm font-bold text-slate-900">
                            {{ StorefrontHelper::formatPrice($item->total_price ?: ($item->unit_price * $item->quantity), 'NPR') }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Delivery Address & Totals Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pt-6 border-t border-slate-100 text-xs">
                <!-- Delivery Address (Nepal Format) -->
                <div class="space-y-2">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-2">Delivery Address</h4>
                    <div class="text-slate-600 leading-relaxed space-y-1">
                        <p class="font-bold text-slate-900">{{ $order->first_name }} {{ $order->last_name }}</p>
                        <p>Mobile: <strong class="font-mono text-slate-900">{{ $order->phone }}</strong> @if($order->alt_phone) &bull; Alt: {{ $order->alt_phone }} @endif</p>
                        @if($order->email) <p>Email: {{ $order->email }}</p> @endif
                        <p class="pt-1">
                            {{ $order->tole ?: $order->shipping_address }}@if($order->ward), Ward {{ $order->ward }}@endif<br>
                            {{ $order->municipality ?: $order->shipping_city }}, {{ $order->district }}<br>
                            {{ $order->province }}@if($order->shipping_country), Nepal @endif
                        </p>
                        @if($order->landmark)
                        <p class="text-[11px] text-blue-600 font-semibold">📍 Landmark: {{ $order->landmark }}</p>
                        @endif
                    </div>
                </div>

                <!-- Financial Summary Breakdown -->
                <div class="space-y-2.5">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-2">Payment Breakdown</h4>
                    <div class="flex justify-between text-slate-600">
                        <span>Subtotal</span>
                        <span class="font-semibold text-slate-900">{{ StorefrontHelper::formatPrice($order->subtotal, 'NPR') }}</span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Courier Delivery</span>
                        <span class="font-semibold text-slate-900">{{ (float)($order->shipping_fee ?? 0) <= 0 ? 'Free Shipping' : StorefrontHelper::formatPrice($order->shipping_fee, 'NPR') }}</span>
                    </div>
                    @if((float)($order->coupon_discount ?? 0) > 0)
                    <div class="flex justify-between text-emerald-700 font-semibold">
                        <span>Coupon Discount</span>
                        <span>- {{ StorefrontHelper::formatPrice($order->coupon_discount, 'NPR') }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-sm sm:text-base font-extrabold text-slate-900 pt-3 border-t border-slate-100">
                        <span>Grand Total</span>
                        <span class="text-blue-600">{{ StorefrontHelper::formatPrice($order->total_amount, 'NPR') }}</span>
                    </div>
                </div>
            </div>

            <!-- Next Actions: Live Tracking & WhatsApp Concierge -->
            <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="flex flex-wrap gap-3 w-full sm:w-auto">
                    <a
                        href="{{ url('/track-order?order_number=' . $order->order_number . '&phone=' . urlencode($order->phone)) }}"
                        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors shadow-xs text-center">
                        Track Delivery Status &rarr;
                    </a>

                    <a
                        href="https://wa.me/9779843512095?text={{ urlencode('Namaste Laijau, I just placed order #' . $order->order_number . '. Can you please verify my order?') }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="px-4 py-2.5 bg-[#25D366] hover:bg-[#20bd5a] text-white text-xs font-bold rounded-xl transition-colors shadow-xs flex items-center justify-center gap-2">
                        <span>WhatsApp Support (9843512095)</span>
                    </a>
                </div>

                <a
                    href="{{ route('storefront.catalogue') }}"
                    class="text-xs text-slate-600 hover:text-blue-600 font-semibold transition-colors">
                    Continue Shopping (All Products) &rarr;
                </a>
            </div>
        </div>
        @endif

    </div>
</div>
@endsection