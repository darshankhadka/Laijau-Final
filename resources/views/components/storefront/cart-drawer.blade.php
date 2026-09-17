<div
    x-data
    x-cloak
    x-show="$store.store.isCartDrawerOpen"
    x-transition:enter="transition-opacity duration-250"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    id="cart-drawer"
    class="fixed inset-0 z-[70] overflow-hidden text-left"
    role="dialog"
    aria-modal="true"
    style="display: none;">
    <!-- Backdrop -->
    <div
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-250 cursor-pointer"
        @click="$store.store.closeCartDrawer()"
        aria-hidden="true"></div>

    <div class="fixed inset-y-0 right-0 flex max-w-full pl-6 sm:pl-10">
        <div
            x-show="$store.store.isCartDrawerOpen"
            x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="w-full sm:w-screen max-w-md bg-white text-slate-900 shadow-2xl flex flex-col justify-between border-l border-slate-200">
            <!-- Drawer Header -->
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <div class="flex items-center gap-3">
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2">
                        <span>Shopping Cart</span>
                        <span class="sr-only">Your Bag</span>
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-extrabold" x-text="$store.store.cartCount"></span>
                    </h2>
                    <template x-if="$store.store.cart.length > 0">
                        <button
                            type="button"
                            @click="if (confirm('Are you sure you want to clear your cart?')) $store.store.clearCart()"
                            class="text-[11px] font-semibold text-red-600 hover:underline cursor-pointer">
                            Clear All
                        </button>
                    </template>
                </div>
                <button
                    type="button"
                    id="cart-drawer-close"
                    @click="$store.store.closeCartDrawer()"
                    class="p-2 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-200/60 transition-colors cursor-pointer"
                    aria-label="Close Cart">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Kathmandu Valley Free Delivery Progress Bar -->
            <div class="px-5 py-3 bg-blue-50/60 border-b border-blue-100 text-xs text-slate-700">
                <template x-if="$store.store.remainingForFreeShipping > 0">
                    <p class="font-medium text-slate-800">
                        Add <strong class="text-blue-700 font-bold" x-text="$store.store.formatAmount($store.store.remainingForFreeShipping)"></strong> more for
                        <span class="font-bold text-blue-700">Free Kathmandu Valley Delivery</span>!
                    </p>
                </template>
                <template x-if="$store.store.remainingForFreeShipping <= 0">
                    <p class="font-bold text-emerald-700 flex items-center gap-1.5">
                        <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px]">✓</span>
                        Free Delivery unlocked for Kathmandu Valley!
                    </p>
                </template>
                <div class="w-full bg-slate-200 h-1.5 mt-2 rounded-full overflow-hidden">
                    <div
                        class="bg-blue-600 h-full rounded-full transition-all duration-300"
                        :style="`width: ${$store.store.freeShippingProgressPercent}%`"></div>
                </div>
            </div>

            <!-- Cart Items List -->
            <div class="flex-1 overflow-y-auto p-5 divide-y divide-slate-100">
                <!-- Empty Cart State -->
                <template x-if="$store.store.cart.length === 0">
                    <div class="h-full flex flex-col items-center justify-center text-center py-12 space-y-3">
                        <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">Your shopping cart is empty <span class="sr-only">Your bag is empty</span></h3>
                        <p class="text-xs text-slate-500 max-w-xs">
                            Browse over 1,000+ shoes, boots, sneakers, and fashion essentials on Laijau.
                        </p>
                        <a
                            href="{{ route('storefront.catalogue') }}"
                            @click="$store.store.closeCartDrawer()"
                            class="mt-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors shadow-xs">
                            Continue Shopping &rarr;
                        </a>
                    </div>
                </template>

                <!-- Items Loop -->
                <template x-for="(item, idx) in $store.store.cart" :key="$store.store.getLineKey(item)">
                    <div class="py-3.5 flex gap-3.5 text-left items-start">
                        <a :href="'/products/' + (item.slug || item.product_id)" class="w-18 h-18 bg-slate-100 rounded-xl overflow-hidden shrink-0 border border-slate-200 block">
                            <img
                                :src="$store.store.getImageUrl(item.image)"
                                :alt="item.name"
                                width="72"
                                height="72"
                                loading="lazy"
                                decoding="async"
                                class="w-full h-full object-cover object-center" />
                        </a>
                        <div class="flex-1 min-w-0 flex flex-col justify-between h-full">
                            <div>
                                <div class="flex justify-between items-start gap-2">
                                    <a :href="'/products/' + (item.slug || item.product_id)" class="text-xs font-bold text-slate-900 hover:text-blue-600 transition-colors line-clamp-2" x-text="item.name"></a>
                                    <button
                                        type="button"
                                        @click="$store.store.removeItemByKey($store.store.getLineKey(item))"
                                        class="text-slate-400 hover:text-red-600 p-1 transition-colors cursor-pointer shrink-0"
                                        title="Remove item">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                                <template x-if="item.selected_color || item.selected_size">
                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                        <span x-show="item.selected_size" x-text="`Size: ${item.selected_size}`"></span>
                                        <span x-show="item.selected_color && item.selected_size"> • </span>
                                        <span x-show="item.selected_color" x-text="`Color: ${item.selected_color}`"></span>
                                    </p>
                                </template>
                            </div>

                            <!-- Quantity controls & Line Total -->
                            <div class="flex items-center justify-between mt-2.5">
                                <div class="flex items-center border border-slate-200 rounded-lg bg-slate-50">
                                    <button
                                        type="button"
                                        @click="$store.store.updateQuantity(idx, item.quantity - 1)"
                                        class="w-7 h-7 flex items-center justify-center text-xs font-bold text-slate-600 hover:bg-slate-200 rounded-l-lg transition-colors cursor-pointer"
                                        aria-label="Decrease quantity">
                                        -
                                    </button>
                                    <span class="w-8 text-center text-xs font-extrabold text-slate-900" x-text="item.quantity"></span>
                                    <button
                                        type="button"
                                        @click="$store.store.updateQuantity(idx, item.quantity + 1)"
                                        class="w-7 h-7 flex items-center justify-center text-xs font-bold text-slate-600 hover:bg-slate-200 rounded-r-lg transition-colors cursor-pointer"
                                        aria-label="Increase quantity">
                                        +
                                    </button>
                                </div>
                                <span class="text-xs font-extrabold text-slate-900" x-text="$store.store.formatAmount(item.price * item.quantity)"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Drawer Footer (Subtotal & Fast Checkout CTA) -->
            <template x-if="$store.store.cart.length > 0">
                <div class="p-5 border-t border-slate-200 bg-slate-50 space-y-3 pb-[calc(1.25rem+env(safe-area-inset-bottom,0px))]">
                    <div class="flex justify-between items-baseline">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Subtotal</span>
                        <span class="text-lg font-black text-slate-900" x-text="$store.store.formatAmount($store.store.cartSubtotal)"></span>
                    </div>
                    <p class="text-[11px] text-slate-500">
                        Cash on Delivery available for Kathmandu, Lalitpur & Bhaktapur.
                    </p>
                    <div class="space-y-2 pt-1">
                        <a
                            href="{{ route('storefront.checkout') }}"
                            @click="$store.store.closeCartDrawer()"
                            class="w-full block py-3.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-extrabold tracking-wide uppercase text-center rounded-xl transition-colors shadow-md cursor-pointer">
                            Proceed to Checkout &rarr;
                        </a>
                        <a
                            href="{{ route('storefront.cart') }}"
                            @click="$store.store.closeCartDrawer()"
                            class="w-full block py-2.5 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold text-center rounded-xl transition-colors cursor-pointer">
                            View Bag
                        </a>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>