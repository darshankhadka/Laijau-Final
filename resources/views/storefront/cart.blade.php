@extends('layouts.storefront')

@section('title', 'Shopping Cart | Laijau.com')
@section('description', 'Review your shopping cart items before proceeding to checkout with Cash on Delivery or manual eSewa/Khalti payment on Laijau Nepal.')

@section('content')
<div class="bg-slate-50 min-h-screen py-8 sm:py-12 text-left" x-data>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200 pb-5 mb-6 gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                    Shopping Cart
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Review your items and proceed to fast guest checkout.
                </p>
            </div>
            <template x-if="$store.store.cart.length > 0">
                <button
                    type="button"
                    @click="if (confirm('Are you sure you want to clear your entire cart?')) $store.store.clearCart()"
                    class="text-xs font-bold text-red-600 hover:underline cursor-pointer">
                    Clear Cart
                </button>
            </template>
        </div>

        <!-- Free Delivery Progress Bar -->
        <div class="p-4 bg-blue-50/80 border border-blue-100 rounded-2xl mb-8 text-xs text-slate-700">
            <template x-if="$store.store.remainingForFreeShipping <= 0">
                <div class="flex items-center gap-2 font-bold text-emerald-700">
                    <span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs">✓</span>
                    <span>Free Delivery unlocked for Kathmandu Valley (Orders over Rs. 2,000)!</span>
                </div>
            </template>
            <template x-if="$store.store.remainingForFreeShipping > 0">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <span>
                        Add <strong class="text-blue-700 font-extrabold" x-text="$store.store.formatAmount($store.store.remainingForFreeShipping)"></strong> more for <strong>Free Delivery in Kathmandu Valley</strong> (Free above Rs. 2,000).
                    </span>
                    <span class="text-slate-500 text-[11px]">Nationwide courier flat Rs. 150 (Free above Rs. 3,500)</span>
                </div>
            </template>
            <div class="w-full bg-slate-200 h-2 mt-2.5 rounded-full overflow-hidden">
                <div
                    class="bg-blue-600 h-full rounded-full transition-all duration-300"
                    :style="`width: ${$store.store.freeShippingProgressPercent}%`"></div>
            </div>
        </div>

        <!-- Empty Cart State -->
        <template x-if="$store.store.cart.length === 0">
            <div class="py-16 text-center max-w-md mx-auto bg-white rounded-3xl border border-slate-200 p-8">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-100 flex items-center justify-center mb-4 text-slate-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-slate-900 mb-1">Your cart is currently empty</h2>
                <p class="text-xs text-slate-500 mb-6">
                    Browse authentic footwear, sneakers, boots, and clothing on Laijau.
                </p>
                <a
                    href="{{ route('storefront.catalogue') }}"
                    class="inline-block px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors shadow-xs">
                    Start Shopping &rarr;
                </a>
            </div>
        </template>

        <!-- Two Columns: Cart Items & Summary -->
        <template x-if="$store.store.cart.length > 0">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <!-- Items Table (8 Cols) -->
                <div class="lg:col-span-8 space-y-4">
                    <template x-for="(item, idx) in $store.store.cart" :key="$store.store.getLineKey(item)">
                        <div class="flex flex-col sm:flex-row gap-4 p-4 rounded-2xl border border-slate-200 bg-white shadow-xs">
                            <div class="w-24 h-24 sm:w-28 sm:h-28 bg-slate-100 rounded-xl overflow-hidden shrink-0 border border-slate-200">
                                <img
                                    :src="$store.store.getImageUrl(item.image)"
                                    :alt="item.name"
                                    class="h-full w-full object-cover object-center" />
                            </div>

                            <div class="flex-1 flex flex-col justify-between">
                                <div>
                                    <div class="flex justify-between items-start gap-4">
                                        <a :href="`{{ url('/products') }}/${item.slug || item.product_id}`" class="text-sm font-bold text-slate-900 hover:text-blue-600 transition-colors" x-text="item.name"></a>
                                        <span class="text-sm font-black text-slate-900" x-text="$store.store.formatAmount(item.price * item.quantity)"></span>
                                    </div>
                                    <template x-if="item.sku">
                                        <p class="text-[11px] font-mono text-slate-400 mt-0.5" x-text="`SKU: ${item.sku}`"></p>
                                    </template>
                                    <template x-if="item.selected_size || item.selected_color">
                                        <p class="text-xs text-slate-600 mt-1 font-medium flex items-center gap-1.5 flex-wrap">
                                            <span x-show="item.selected_size">Size: <strong class="text-slate-900 font-bold" x-text="item.selected_size"></strong></span>
                                            <span x-show="item.selected_size && item.selected_color" class="text-slate-300">•</span>
                                            <span x-show="item.selected_color">Color: <strong class="text-slate-900 font-bold" x-text="item.selected_color"></strong></span>
                                        </p>
                                    </template>
                                </div>

                                <!-- Quantity and Delete -->
                                <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100">
                                    <div class="flex items-center border border-slate-200 rounded-lg bg-slate-50">
                                        <button
                                            type="button"
                                            @click="$store.store.updateQuantity(idx, item.quantity - 1)"
                                            class="w-7 h-7 flex items-center justify-center text-xs font-bold text-slate-600 hover:bg-slate-200 rounded-l-lg transition-colors cursor-pointer">
                                            -
                                        </button>
                                        <span class="w-8 text-center text-xs font-extrabold text-slate-900" x-text="item.quantity"></span>
                                        <button
                                            type="button"
                                            @click="$store.store.updateQuantity(idx, item.quantity + 1)"
                                            class="w-7 h-7 flex items-center justify-center text-xs font-bold text-slate-600 hover:bg-slate-200 rounded-r-lg transition-colors cursor-pointer">
                                            +
                                        </button>
                                    </div>

                                    <button
                                        type="button"
                                        @click="$store.store.removeItemByKey($store.store.getLineKey(item))"
                                        class="text-xs font-semibold text-red-600 hover:underline cursor-pointer flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        <span>Remove</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Order Summary (4 Cols) -->
                <div class="lg:col-span-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 pb-3 border-b border-slate-100">
                        Order Summary
                    </h2>

                    <div class="space-y-2 text-xs text-slate-600">
                        <div class="flex justify-between">
                            <span>Subtotal (<span x-text="$store.store.cartCount"></span> items)</span>
                            <span class="font-bold text-slate-900" x-text="$store.store.formatAmount($store.store.cartSubtotal)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Estimated Delivery</span>
                            <span class="text-slate-500 font-medium">Calculated at Checkout</span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex justify-between items-baseline">
                        <span class="text-sm font-bold text-slate-900">Total</span>
                        <span class="text-xl font-black text-blue-600" x-text="$store.store.formatAmount($store.store.cartSubtotal)"></span>
                    </div>

                    <div class="pt-2 space-y-2.5">
                        <a
                            href="{{ route('storefront.checkout') }}"
                            class="w-full block py-3.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-black uppercase tracking-wider text-center rounded-xl transition-colors shadow-md cursor-pointer">
                            Proceed to Checkout &rarr;
                        </a>
                        <a
                            href="{{ route('storefront.catalogue') }}"
                            class="w-full block py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold text-center rounded-xl transition-colors">
                            Continue Shopping
                        </a>
                    </div>

                    <div class="pt-4 border-t border-slate-100 text-[11px] text-slate-500 space-y-1.5">
                        <p class="flex items-center gap-1.5 font-medium text-slate-700">
                            <span>✓</span> Cash on Delivery for Kathmandu Valley
                        </p>
                        <p class="flex items-center gap-1.5">
                            <span>✓</span> Nationwide delivery across all 77 districts
                        </p>
                        <p class="flex items-center gap-1.5">
                            <span>✓</span> eSewa, Khalti, and Bank Transfer supported
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
@endsection