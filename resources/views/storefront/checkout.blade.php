@extends('layouts.storefront')

@section('title', 'Checkout | Laijau.com — Fast Nepal Nationwide Delivery')
@section('description', 'Complete your order with Cash on Delivery in Kathmandu Valley, or prepaid nationwide delivery via eSewa, Khalti, or Direct Bank Transfer.')

@php
$provincesJson = json_encode($provincesWithDistricts ?? \App\Services\NepalLocationService::PROVINCES_WITH_DISTRICTS);
$valleyDistrictsJson = json_encode($valleyDistricts ?? \App\Services\NepalLocationService::VALLEY_DISTRICTS);
@endphp

@section('content')
<div
    class="bg-slate-50 min-h-screen py-8 sm:py-12 text-left"
    x-data="{
        provinces: {{ $provincesJson }},
        valleyDistricts: {{ $valleyDistrictsJson }},
        customer: {
            first_name: '',
            last_name: '',
            phone: '',
            alt_phone: '',
            email: '',
            province: 'Bagmati Province',
            district: 'Kathmandu',
            municipality: '',
            ward: '',
            tole: '',
            landmark: '',
            notes: ''
        },
        paymentMethod: 'cod',
        paymentReference: '',
        receiptFile: null,
        receiptFileName: '',
        couponCode: '',
        appliedCoupon: '',
        couponDiscount: 0,
        couponMessage: '',
        isApplyingCoupon: false,
        isSubmitting: false,
        errorMessage: null,
        stockErrors: [],
        serverShippingFee: 100,

        get availableDistricts() {
            return this.provinces[this.customer.province] || [];
        },

        get isInsideValley() {
            return this.valleyDistricts.includes(this.customer.district);
        },

        get subtotal() {
            return $store.store.cartSubtotal;
        },

        get shippingFee() {
            if (this.subtotal <= 0) return 0;
            if (this.isInsideValley) {
                return this.subtotal >= 2000 ? 0 : 100;
            } else {
                return this.subtotal >= 3500 ? 0 : 150;
            }
        },

        get freeShippingThreshold() {
            return this.isInsideValley ? 2000 : 3500;
        },

        get freeShippingRemaining() {
            const rem = this.freeShippingThreshold - this.subtotal;
            return rem > 0 ? rem : 0;
        },

        get freeShippingProgressPercent() {
            if (this.subtotal >= this.freeShippingThreshold) return 100;
            return Math.min(100, Math.round((this.subtotal / this.freeShippingThreshold) * 100));
        },

        get grandTotal() {
            const discounted = Math.max(0, this.subtotal - this.couponDiscount);
            return discounted + this.shippingFee;
        },

        init() {
            // Populate from authenticated user if available
            if ($store.store.user) {
                const parts = ($store.store.user.name || '').trim().split(' ');
                this.customer.first_name = parts[0] || '';
                this.customer.last_name = parts.slice(1).join(' ') || '';
                this.customer.email = $store.store.user.email || '';
                this.customer.phone = $store.store.user.phone || '';
                this.customer.alt_phone = $store.store.user.alt_phone || '';
                if ($store.store.user.province) this.customer.province = $store.store.user.province;
                if ($store.store.user.district) this.customer.district = $store.store.user.district;
                if ($store.store.user.municipality) this.customer.municipality = $store.store.user.municipality;
                if ($store.store.user.ward) this.customer.ward = $store.store.user.ward;
                if ($store.store.user.tole) this.customer.tole = $store.store.user.tole;
                if ($store.store.user.landmark) this.customer.landmark = $store.store.user.landmark;
            }

            // Watch district changes to enforce Valley COD rule
            this.$watch('customer.district', (newDistrict) => {
                if (!this.valleyDistricts.includes(newDistrict)) {
                    if (this.paymentMethod === 'cod') {
                        this.paymentMethod = 'connectips';
                    }
                }
            });

            // Watch province to auto-select first district of new province
            this.$watch('customer.province', (newProv) => {
                const list = this.provinces[newProv] || [];
                if (list.length > 0 && !list.includes(this.customer.district)) {
                    this.customer.district = list[0];
                }
            });
        },

        handleReceiptChange(event) {
            const file = event.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    alert('Screenshot file size must be under 5MB.');
                    event.target.value = '';
                    this.receiptFile = null;
                    this.receiptFileName = '';
                    return;
                }
                const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    alert('Please upload a valid image (JPG, PNG, or WEBP).');
                    event.target.value = '';
                    this.receiptFile = null;
                    this.receiptFileName = '';
                    return;
                }
                this.receiptFile = file;
                this.receiptFileName = file.name;
            } else {
                this.receiptFile = null;
                this.receiptFileName = '';
            }
        },

        async applyCoupon() {
            if (!this.couponCode.trim()) return;
            this.isApplyingCoupon = true;
            this.couponMessage = '';

            try {
                const csrfToken = document.querySelector('meta[name=csrf-token]')?.content;
                const res = await fetch('/api/cart/validate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || ''
                    },
                    body: JSON.stringify({
                        items: $store.store.cart.map(i => ({
                            product_id: i.product_id,
                            variant_id: i.variant_id || null,
                            quantity: i.quantity
                        })),
                        coupon_code: this.couponCode.trim(),
                        district: this.customer.district,
                        currency: 'NPR'
                    })
                });

                const data = await res.json();
                if (data.coupon && data.coupon.valid) {
                    this.appliedCoupon = data.coupon.code;
                    this.couponDiscount = data.coupon.discount;
                    this.couponMessage = data.coupon.message || 'Coupon applied successfully!';
                } else {
                    this.couponDiscount = 0;
                    this.appliedCoupon = '';
                    this.couponMessage = data.coupon?.message || 'Invalid coupon code.';
                }
            } catch (err) {
                this.couponMessage = 'Failed to validate coupon.';
            } finally {
                this.isApplyingCoupon = false;
            }
        },

        removeCoupon() {
            this.appliedCoupon = '';
            this.couponCode = '';
            this.couponDiscount = 0;
            this.couponMessage = '';
        },

        async submitOrder() {
            this.errorMessage = null;
            this.stockErrors = [];

            if ($store.store.cart.length === 0) {
                this.errorMessage = 'Your shopping bag is empty.';
                return;
            }

            // Validations
            if (!this.customer.first_name.trim() || !this.customer.last_name.trim()) {
                this.errorMessage = 'Please enter your full name.';
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            const cleanPhone = this.customer.phone.replace(/[^0-9]/g, '');
            if (cleanPhone.length < 10) {
                this.errorMessage = 'Please enter a valid 10-digit mobile number (e.g., 9843512095).';
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            if (!this.customer.province || !this.customer.district) {
                this.errorMessage = 'Please select your Province and District.';
                return;
            }

            if (!this.customer.municipality.trim() || !this.customer.ward.trim() || !this.customer.tole.trim()) {
                this.errorMessage = 'Please provide your Municipality, Ward No., and Tole / Street address.';
                return;
            }

            // COD Outside Valley Check
            if (this.paymentMethod === 'cod' && !this.isInsideValley) {
                this.errorMessage = 'Cash on Delivery (COD) is strictly available only inside Kathmandu Valley (Kathmandu, Lalitpur, Bhaktapur). Please choose connectIPS or eSewa QR payment for outside valley delivery.';
                return;
            }

            // eSewa Payment Proof Check
            if (this.paymentMethod === 'esewa' && !this.receiptFile && !this.paymentReference.trim()) {
                this.errorMessage = 'Please provide your eSewa Transaction Reference Code or upload a payment screenshot.';
                return;
            }

            this.isSubmitting = true;

            try {
                const csrfToken = document.querySelector('meta[name=csrf-token]')?.content;
                const formData = new FormData();

                // Append customer details
                formData.append('customer[first_name]', this.customer.first_name.trim());
                formData.append('customer[last_name]', this.customer.last_name.trim());
                formData.append('customer[phone]', this.customer.phone.trim());
                if (this.customer.alt_phone) formData.append('customer[alt_phone]', this.customer.alt_phone.trim());
                if (this.customer.email) formData.append('customer[email]', this.customer.email.trim());
                formData.append('customer[province]', this.customer.province);
                formData.append('customer[district]', this.customer.district);
                formData.append('customer[municipality]', this.customer.municipality.trim());
                formData.append('customer[ward]', this.customer.ward.trim());
                formData.append('customer[tole]', this.customer.tole.trim());
                if (this.customer.landmark) formData.append('customer[landmark]', this.customer.landmark.trim());
                if (this.customer.notes) formData.append('customer[notes]', this.customer.notes.trim());

                // Payment details
                formData.append('payment_method', this.paymentMethod);
                if (this.paymentReference) formData.append('payment_reference', this.paymentReference.trim());
                if (this.receiptFile) formData.append('payment_receipt', this.receiptFile);

                // Cart items
                $store.store.cart.forEach((item, index) => {
                    formData.append(`items[${index}][product_id]`, item.product_id);
                    formData.append(`items[${index}][quantity]`, item.quantity);
                    if (item.variant_id) formData.append(`items[${index}][variant_id]`, item.variant_id);
                    if (item.selected_size) formData.append(`items[${index}][selected_size]`, item.selected_size);
                    if (item.selected_color) formData.append(`items[${index}][selected_color]`, item.selected_color);
                    if (item.custom_measurements) {
                        formData.append(`items[${index}][custom_measurements]`, JSON.stringify(item.custom_measurements));
                    }
                });

                formData.append('currency', 'NPR');
                if (this.appliedCoupon) formData.append('coupon_code', this.appliedCoupon);

                const res = await fetch('/api/checkout/process', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || ''
                    },
                    body: formData
                });

                const data = await res.json();

                if (res.ok && data.success) {
                    // Clear cart upon successful order creation
                    $store.store.clearCart();

                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        window.location.href = `/checkout/success?order=${data.order_number}`;
                    }
                } else {
                    this.errorMessage = data.message || 'We could not process your order. Please check the form and try again.';
                    if (data.stock_errors && data.stock_errors.length > 0) {
                        this.stockErrors = data.stock_errors;
                    }
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            } catch (err) {
                console.error(err);
                this.errorMessage = 'A network error occurred while placing your order. Please check your connection or contact us via WhatsApp.';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } finally {
                this.isSubmitting = false;
            }
        }
    }">
    <div class="container mx-auto px-4 sm:px-6 max-w-7xl">

        <!-- Top Navigation / Back to Cart -->
        <div class="flex items-center justify-between mb-8 pb-4 border-b border-slate-200">
            <a href="{{ url('/cart') }}" class="inline-flex items-center gap-2 text-xs font-bold text-slate-900 hover:text-blue-600 transition-colors uppercase tracking-wider">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Return to Cart
            </a>
            <div class="flex items-center gap-2 text-[11px] text-slate-500 uppercase tracking-widest font-semibold">
                <span class="text-blue-600 font-bold">1. Delivery</span>
                <span>&bull;</span>
                <span class="text-blue-600 font-bold">2. Payment</span>
                <span>&bull;</span>
                <span class="text-slate-400">3. Confirmation</span>
            </div>
        </div>

        <!-- Empty Cart Notice -->
        <div x-show="$store.store.cart.length === 0" class="py-16 text-center max-w-md mx-auto bg-white p-8 rounded-2xl border border-slate-200 shadow-xs">
            <div class="w-14 h-14 mx-auto rounded-full bg-blue-50 flex items-center justify-center mb-4 text-blue-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
            </div>
            <h2 class="text-2xl font-black text-slate-900 mb-2">Your Cart is Empty</h2>
            <p class="text-xs text-slate-500 font-normal mb-6">Explore 1,000+ footwear, apparel, and accessories ready for fast delivery across Nepal.</p>
            <a href="{{ url('/products') }}" class="inline-block px-6 py-3 bg-blue-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-blue-700 shadow-sm transition-colors">
                Explore Catalog
            </a>
        </div>

        <!-- Mobile Order Summary Accordion (< 1024px) -->
        <div
            x-show="$store.store.cart.length > 0"
            x-data="{ mobileSummaryOpen: false }"
            class="lg:hidden mb-6 bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs">
            <button
                type="button"
                @click="mobileSummaryOpen = !mobileSummaryOpen"
                class="w-full px-4 py-3.5 flex items-center justify-between bg-slate-50 hover:bg-slate-100/80 transition-colors cursor-pointer text-left"
                aria-expanded="false"
                :aria-expanded="mobileSummaryOpen">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-900">
                            <span x-text="mobileSummaryOpen ? 'Hide Order Summary' : 'Show Order Summary'"></span>
                            <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="mobileSummaryOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                        <span class="text-[11px] text-slate-500" x-text="`${$store.store.cartCount} items in cart`"></span>
                    </div>
                </div>
                <span class="text-sm font-black text-slate-900 font-mono" x-text="'Rs. ' + grandTotal.toLocaleString()"></span>
            </button>

            <!-- Collapsible Items & Totals Breakdown -->
            <div
                x-show="mobileSummaryOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="p-4 border-t border-slate-200 space-y-4"
                style="display: none;">
                <!-- Items list -->
                <div class="space-y-3 divide-y divide-slate-100 max-h-56 overflow-y-auto pr-1">
                    <template x-for="item in $store.store.cart" :key="$store.store.getLineKey(item)">
                        <div class="pt-3 first:pt-0 flex items-center gap-3">
                            <div class="relative w-12 h-12 bg-slate-100 rounded-lg overflow-hidden shrink-0 border border-slate-200">
                                <img :src="$store.store.getImageUrl(item.image)" :alt="item.name" class="w-full h-full object-cover" />
                                <span class="absolute -top-1 -right-1 bg-slate-800 text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center" x-text="item.quantity"></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold text-slate-900 truncate" x-text="item.name"></p>
                                <p class="text-[10px] text-slate-500" x-show="item.selected_size" x-text="`Size: ${item.selected_size}`"></p>
                            </div>
                            <span class="text-xs font-bold text-slate-900 font-mono shrink-0" x-text="'Rs. ' + (item.price * item.quantity).toLocaleString()"></span>
                        </div>
                    </template>
                </div>

                <!-- Subtotal and Fee breakdown -->
                <div class="pt-3 border-t border-slate-200 space-y-1.5 text-xs text-slate-600">
                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <span class="font-mono font-medium text-slate-900" x-text="'Rs. ' + subtotal.toLocaleString()"></span>
                    </div>
                    <template x-if="couponDiscount > 0">
                        <div class="flex justify-between text-emerald-700 font-medium">
                            <span>Coupon Discount</span>
                            <span class="font-mono" x-text="'-Rs. ' + couponDiscount.toLocaleString()"></span>
                        </div>
                    </template>
                    <div class="flex justify-between">
                        <span>Delivery Fee</span>
                        <span class="font-mono font-medium text-slate-900" x-text="shippingFee === 0 ? 'FREE' : 'Rs. ' + shippingFee.toLocaleString()"></span>
                    </div>
                    <div class="pt-2 border-t border-slate-200 flex justify-between items-baseline font-bold text-slate-900">
                        <span>Grand Total</span>
                        <span class="text-base text-blue-600 font-mono" x-text="'Rs. ' + grandTotal.toLocaleString()"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Checkout Grid -->
        <div x-show="$store.store.cart.length > 0" class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">

            <!-- LEFT COLUMN: Customer & Nepal Delivery Details + Payment (Cols 7) -->
            <div class="lg:col-span-7 space-y-8">

                <!-- Alert / Error Banner -->
                <div x-show="errorMessage" class="p-4 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl space-y-1" x-cloak>
                    <div class="font-bold flex items-center gap-2">
                        <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Please correct the following:</span>
                    </div>
                    <p x-text="errorMessage" class="pl-6 font-medium"></p>
                    <template x-if="stockErrors.length > 0">
                        <ul class="pl-6 list-disc list-inside text-[11px] pt-1 space-y-0.5">
                            <template x-for="err in stockErrors" :key="err">
                                <li x-text="err"></li>
                            </template>
                        </ul>
                    </template>
                </div>

                <!-- SECTION 1: DELIVERY DESTINATION (NEPAL ADDRESS) -->
                <div class="bg-white p-6 sm:p-8 border border-slate-200 shadow-xs">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-6">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold font-mono">1</span>
                            <h2 class="font-sans font-bold text-lg sm:text-xl text-slate-900 uppercase tracking-wide">Delivery Address (Nepal)</h2>
                        </div>
                        <span class="text-[11px] text-blue-600 font-bold tracking-wider uppercase">Guest Checkout</span>
                    </div>

                    <!-- Valley Status Highlight -->
                    <div
                        class="mb-6 p-3.5 rounded-xl border text-xs flex items-start gap-3 transition-colors"
                        :class="isInsideValley ? 'bg-emerald-50/80 border-emerald-200 text-emerald-900' : 'bg-amber-50/80 border-amber-200 text-amber-900'">
                        <span class="text-base shrink-0" x-text="isInsideValley ? '🛵' : '📦'"></span>
                        <div>
                            <p class="font-semibold text-[11px] uppercase tracking-wider" x-text="isInsideValley ? 'Kathmandu Valley Delivery' : 'Nationwide Courier Dispatch'"></p>
                            <p class="text-[11px] mt-0.5" x-text="isInsideValley ? 'Delivery inside Kathmandu, Lalitpur, or Bhaktapur in 24-48 hours. Cash on Delivery is supported!' : 'Nationwide courier via NCM / Pathao in 2-4 business days. Prepaid order required.'"></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- First Name -->
                        <div>
                            <label class="block text-[11px] font-semibold tracking-wider uppercase text-slate-900 mb-1">
                                First Name <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                x-model="customer.first_name"
                                required
                                placeholder="e.g. Aayush"
                                class="w-full bg-slate-50 border border-slate-300 focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none transition-colors" />
                        </div>

                        <!-- Last Name -->
                        <div>
                            <label class="block text-[11px] font-semibold tracking-wider uppercase text-slate-900 mb-1">
                                Last Name <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                x-model="customer.last_name"
                                required
                                placeholder="e.g. Shrestha"
                                class="w-full bg-slate-50 border border-slate-300 focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none transition-colors" />
                        </div>

                        <!-- Mobile Phone (Required) -->
                        <div>
                            <label class="block text-[11px] font-semibold tracking-wider uppercase text-slate-900 mb-1">
                                Mobile Number <span class="text-red-500">*</span>
                            </label>
                            <div class="flex">
                                <span class="inline-flex items-center px-3 bg-slate-100 border border-r-0 border-slate-300 text-xs font-mono text-slate-900/70 select-none">
                                    +977
                                </span>
                                <input
                                    type="tel"
                                    x-model="customer.phone"
                                    required
                                    placeholder="98XXXXXXXX"
                                    class="w-full bg-slate-50 border border-slate-300 focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none transition-colors" />
                            </div>
                            <span class="text-[10px] text-slate-400 font-light mt-0.5 block">Courier will call this number prior to delivery.</span>
                        </div>

                        <!-- Alternative Phone (Optional) -->
                        <div>
                            <label class="block text-[11px] font-semibold tracking-wider uppercase text-slate-900 mb-1">
                                Alternate Phone (Optional)
                            </label>
                            <input
                                type="tel"
                                x-model="customer.alt_phone"
                                placeholder="Secondary contact / WhatsApp"
                                class="w-full bg-slate-50 border border-slate-300 focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none transition-colors" />
                        </div>

                        <!-- Email Address (Optional for Guest) -->
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-semibold tracking-wider uppercase text-slate-900 mb-1">
                                Email Address (Optional)
                            </label>
                            <input
                                type="email"
                                x-model="customer.email"
                                placeholder="name@example.com (for receipt & order tracking link)"
                                class="w-full bg-slate-50 border border-slate-300 focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none transition-colors" />
                        </div>

                        <!-- Province Selector -->
                        <div>
                            <label class="block text-[11px] font-semibold tracking-wider uppercase text-slate-900 mb-1">
                                Province <span class="text-red-500">*</span>
                            </label>
                            <select
                                x-model="customer.province"
                                class="w-full bg-slate-50 border border-slate-300 focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 focus:outline-none transition-colors cursor-pointer">
                                <template x-for="(dists, prov) in provinces" :key="prov">
                                    <option :value="prov" x-text="prov" :selected="prov === customer.province"></option>
                                </template>
                            </select>
                        </div>

                        <!-- District Selector -->
                        <div>
                            <label class="block text-[11px] font-semibold tracking-wider uppercase text-slate-900 mb-1">
                                District <span class="text-red-500">*</span>
                            </label>
                            <select
                                x-model="customer.district"
                                class="w-full bg-slate-50 border border-slate-300 focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 focus:outline-none transition-colors cursor-pointer">
                                <template x-for="dist in availableDistricts" :key="dist">
                                    <option :value="dist" x-text="dist" :selected="dist === customer.district"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Municipality / Gaunpalika -->
                        <div>
                            <label class="block text-[11px] font-semibold tracking-wider uppercase text-slate-900 mb-1">
                                Municipality / Nagarpalika <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                x-model="customer.municipality"
                                required
                                placeholder="e.g. Kathmandu Metropolitan City"
                                class="w-full bg-slate-50 border border-slate-300 focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none transition-colors" />
                        </div>

                        <!-- Ward Number -->
                        <div>
                            <label class="block text-[11px] font-semibold tracking-wider uppercase text-slate-900 mb-1">
                                Ward No. <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                x-model="customer.ward"
                                required
                                placeholder="e.g. 28"
                                class="w-full bg-slate-50 border border-slate-300 focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none transition-colors" />
                        </div>

                        <!-- Tole / Street -->
                        <div>
                            <label class="block text-[11px] font-semibold tracking-wider uppercase text-slate-900 mb-1">
                                Tole / Street Address <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                x-model="customer.tole"
                                required
                                placeholder="e.g. Putalisadak Chowk"
                                class="w-full bg-slate-50 border border-slate-300 focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none transition-colors" />
                        </div>

                        <!-- Landmark -->
                        <div>
                            <label class="block text-[11px] font-semibold tracking-wider uppercase text-slate-900 mb-1">
                                Nearby Landmark
                            </label>
                            <input
                                type="text"
                                x-model="customer.landmark"
                                placeholder="e.g. Opposite Star Mall / Near Petrol Pump"
                                class="w-full bg-slate-50 border border-slate-300 focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none transition-colors" />
                        </div>

                        <!-- Delivery Instructions -->
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-semibold tracking-wider uppercase text-slate-900 mb-1">
                                Order / Delivery Notes (Optional)
                            </label>
                            <textarea
                                x-model="customer.notes"
                                rows="2"
                                placeholder="Any special delivery instructions or package drop-off notes..."
                                class="w-full bg-slate-50 border border-slate-300 focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none transition-colors"></textarea>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: PAYMENT METHOD (MANUAL NEPAL METHODS) -->
                <div class="bg-white p-6 sm:p-8 border border-slate-200 shadow-xs">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-6">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold font-mono">2</span>
                            <h2 class="font-sans font-bold text-lg sm:text-xl text-slate-900 uppercase tracking-wide">Payment Method</h2>
                        </div>
                        <span class="text-[11px] text-slate-400 tracking-wider uppercase">Secure Manual Verification</span>
                    </div>

                    <!-- Payment Options Grid -->
                    <div class="space-y-3 mb-6">

                        <!-- OPTION 1: CASH ON DELIVERY (VALLEY ONLY) -->
                        <div
                            class="border p-4 transition-all rounded-xl cursor-pointer"
                            :class="[
                                paymentMethod === 'cod' ? 'border-[#922b27] bg-[#922b27]/5 shadow-xs' : 'border-slate-200 hover:border-slate-300',
                                !isInsideValley ? 'opacity-60 cursor-not-allowed bg-gray-50' : ''
                            ]"
                            @click="if (isInsideValley) paymentMethod = 'cod'">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value="cod"
                                        :checked="paymentMethod === 'cod'"
                                        :disabled="!isInsideValley"
                                        class="accent-[#922b27] w-4 h-4 cursor-pointer" />
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-slate-900 text-sm">Cash on Delivery</span>
                                            <span class="px-2 py-0.5 bg-amber-100 text-amber-800 text-[10px] font-semibold uppercase tracking-wider rounded-md">
                                                Kathmandu Valley only
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-600 font-light mt-0.5">
                                            Pay in cash upon doorstep delivery in Kathmandu, Lalitpur, or Bhaktapur (Delivery fee: Rs. 100).
                                        </p>
                                    </div>
                                </div>
                                <span class="text-xl">💵</span>
                            </div>

                            <template x-if="!isInsideValley">
                                <div class="mt-2.5 pt-2 border-t border-slate-200 text-[11px] text-amber-800 font-medium">
                                    ⚠️ Cash on Delivery is strictly restricted to Kathmandu Valley (Kathmandu, Lalitpur, Bhaktapur). For delivery to other districts, please choose ConnectIPS or eSewa QR below.
                                </div>
                            </template>
                        </div>

                        <!-- OPTION 2: ConnectIPS / NCHL Online Payment -->
                        <div
                            class="border p-4 transition-all rounded-xl cursor-pointer"
                            :class="paymentMethod === 'connectips' ? 'border-indigo-600 bg-indigo-50/40 shadow-xs' : 'border-slate-200 hover:border-slate-300'"
                            @click="paymentMethod = 'connectips'">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value="connectips"
                                        :checked="paymentMethod === 'connectips'"
                                        class="accent-indigo-600 w-4 h-4 cursor-pointer" />
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-slate-900 text-sm">ConnectIPS</span>
                                            <span class="px-2 py-0.5 bg-indigo-100 text-indigo-800 text-[10px] font-bold uppercase tracking-wider rounded-md">
                                                Interbank Gateway
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-600 font-light mt-0.5">
                                            Pay securely with ConnectIPS directly from your Nepalese bank account with real-time server verification.
                                        </p>
                                    </div>
                                </div>
                                <span class="font-bold text-indigo-700 text-sm tracking-tight border border-indigo-200 bg-indigo-50 px-2 py-0.5 rounded">connectIPS</span>
                            </div>
                        </div>

                        <!-- OPTION 3: eSewa Manual QR Payment -->
                        <div
                            class="border p-4 transition-all rounded-xl cursor-pointer"
                            :class="paymentMethod === 'esewa' ? 'border-[#1A8A36] bg-[#1A8A36]/5 shadow-xs' : 'border-slate-200 hover:border-slate-300'"
                            @click="paymentMethod = 'esewa'">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value="esewa"
                                        :checked="paymentMethod === 'esewa'"
                                        class="accent-[#1A8A36] w-4 h-4 cursor-pointer" />
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-slate-900 text-sm">eSewa QR</span>
                                            <span class="px-2 py-0.5 bg-[#1A8A36]/20 text-[#1A8A36] text-[10px] font-bold uppercase tracking-wider rounded-md">
                                                Scan & Pay
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-600 font-light mt-0.5">
                                            Scan our official eSewa merchant QR, enter your transaction reference, and upload screenshot proof.
                                        </p>
                                    </div>
                                </div>
                                <span class="font-bold text-emerald-600 text-base">eSewa</span>
                            </div>
                        </div>

                    </div>

                    <!-- PAYMENT INSTRUCTIONS ACCORDING TO SELECTION -->
                    <div x-show="paymentMethod !== 'cod'" class="bg-slate-50 p-5 border border-slate-200 space-y-5 rounded-xl" x-cloak>

                        <!-- ConnectIPS Instructions Card -->
                        <template x-if="paymentMethod === 'connectips'">
                            <div class="space-y-3 bg-white p-5 border border-indigo-200 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-900">Pay securely with ConnectIPS</h4>
                                    <span class="text-[10px] bg-indigo-100 text-indigo-800 px-2.5 py-0.5 font-bold uppercase rounded">Instant Online Payment</span>
                                </div>
                                <div class="text-xs text-slate-600 space-y-2 leading-relaxed">
                                    <p>Upon clicking <strong>Place Order</strong>, you will be redirected to the official connectIPS portal to authorize payment directly from your bank.</p>
                                    <ul class="list-disc list-inside space-y-1 text-slate-500 pl-1 text-[11px]">
                                        <li>Instant interbank transaction supported by all major commercial & development banks in Nepal.</li>
                                        <li>Payment status is confirmed in real-time by the Laijau server.</li>
                                        <li>Your inventory items are held safely while you authorize the payment.</li>
                                    </ul>
                                </div>
                            </div>
                        </template>

                        <!-- eSewa Manual QR Payment Card -->
                        <template x-if="paymentMethod === 'esewa'">
                            <div class="space-y-4 bg-white p-5 border border-emerald-200 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-900">Pay with eSewa</h4>
                                    <span class="text-[10px] bg-emerald-100 text-emerald-800 px-2.5 py-0.5 font-bold uppercase rounded">Official Merchant QR</span>
                                </div>

                                <p class="text-xs text-slate-700 font-medium">Scan the QR code using your eSewa app.</p>

                                <!-- Official eSewa QR Image Display -->
                                <div class="text-center py-2">
                                    <img src="{{ asset('images/payments/esewa-qr.png') }}" alt="Laijau Official eSewa QR Code" class="w-48 sm:w-56 h-auto mx-auto border-2 border-emerald-300 rounded-lg shadow-sm">
                                    <p class="text-[11px] text-slate-500 mt-2">Scan with eSewa App on your mobile device</p>
                                </div>

                                <div class="bg-emerald-50/80 p-3.5 border border-emerald-200 text-xs space-y-1.5 font-mono rounded-lg">
                                    <p class="text-slate-700">eSewa ID: <strong class="text-emerald-800 text-sm font-bold">9843512095</strong></p>
                                    <p class="text-slate-700">Account Name: <strong class="text-slate-900 font-bold">Delta Nine business group</strong></p>
                                    <p class="text-slate-700">Total to Pay: <strong class="text-emerald-800 text-sm font-bold" x-text="'Rs. ' + grandTotal.toLocaleString()"></strong></p>
                                </div>

                                <div class="border-t border-slate-200 pt-3 space-y-3">
                                    <p class="text-xs text-slate-700 font-medium">After payment, upload your payment screenshot below.</p>

                                    <!-- Transaction Reference Input -->
                                    <div>
                                        <label class="block text-[11px] font-semibold tracking-wider uppercase text-slate-900 mb-1">
                                            eSewa Transaction ID / Reference Code
                                        </label>
                                        <input
                                            type="text"
                                            x-model="paymentReference"
                                            placeholder="e.g. 7X89Q12 or Transaction ID"
                                            class="w-full bg-white border border-slate-300 focus:border-emerald-600 px-3.5 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none font-mono rounded-md" />
                                    </div>

                                    <!-- Screenshot / Receipt File Upload -->
                                    <div>
                                        <label class="block text-[11px] font-semibold tracking-wider uppercase text-slate-900 mb-1">
                                            Payment Screenshot / Receipt Proof
                                        </label>
                                        <div class="flex items-center gap-3">
                                            <label class="px-4 py-2.5 bg-white border border-slate-300 hover:border-emerald-600 text-slate-900 text-xs font-medium cursor-pointer transition-colors shrink-0 rounded-md">
                                                <span>Choose Screenshot</span>
                                                <input
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/webp"
                                                    @change="handleReceiptChange"
                                                    class="hidden" />
                                            </label>
                                            <span class="text-xs text-slate-500 truncate" x-text="receiptFileName || 'No file selected yet'"></span>
                                        </div>
                                        <span class="text-[10px] text-slate-400 mt-1 block">
                                            Accepted: JPG, PNG, WEBP (Max 5MB). Proof is required for admin payment verification.
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>

                    </div>
                </div>

                <!-- SECTION 3: PLACE ORDER BUTTON -->
                <div>
                    <button
                        type="button"
                        @click="submitOrder"
                        :disabled="isSubmitting"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white py-4 px-6 text-sm font-semibold uppercase tracking-[0.2em] shadow-md hover:shadow-lg transition-all disabled:opacity-50 cursor-pointer flex items-center justify-center gap-2">
                        <template x-if="isSubmitting">
                            <span class="inline-flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Securing Your Order...</span>
                            </span>
                        </template>
                        <template x-if="!isSubmitting">
                            <span>
                                Confirm Order &bull; Rs. <span x-text="grandTotal.toLocaleString()"></span>
                            </span>
                        </template>
                    </button>
                    <p class="text-center text-[10px] text-slate-400 mt-2 font-light">
                        By placing your order, you agree to Laijau's Delivery and Return policies. No advance registration needed.
                    </p>
                </div>

            </div>

            <!-- RIGHT COLUMN: ORDER SUMMARY SIDEBAR (Cols 5) -->
            <div class="lg:col-span-5 sticky top-24 space-y-6">

                <!-- Order Summary Card -->
                <div class="bg-white p-6 sm:p-7 border border-slate-200 shadow-xs space-y-6">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <h3 class="font-sans font-bold text-base text-slate-900 uppercase tracking-wide">
                            Order Summary (<span x-text="$store.store.cartCount"></span>)
                        </h3>
                        <a href="{{ url('/cart') }}" class="text-[11px] text-blue-600 hover:underline font-semibold uppercase tracking-wider">
                            Edit Cart
                        </a>
                    </div>

                    <!-- Free Delivery Progress Bar -->
                    <div class="bg-slate-50 p-3.5 border border-blue-200 rounded-md text-xs space-y-2">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="font-semibold text-slate-900">
                                <template x-if="freeShippingRemaining === 0">
                                    <span class="text-emerald-700 font-bold">🎉 Congratulations! You have Free Shipping!</span>
                                </template>
                                <template x-if="freeShippingRemaining > 0">
                                    <span>Add <strong>Rs. <span x-text="freeShippingRemaining.toLocaleString()"></span></strong> more for Free Shipping!</span>
                                </template>
                            </span>
                            <span class="text-[10px] text-slate-500" x-text="isInsideValley ? 'Valley (Rs. 2,000)' : 'Nationwide (Rs. 3,500)'"></span>
                        </div>
                        <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
                            <div
                                class="bg-blue-600 h-full transition-all duration-300"
                                :style="`width: ${freeShippingProgressPercent}%`"></div>
                        </div>
                    </div>

                    <!-- Cart Items Mini List -->
                    <div class="divide-y divide-[#222222]/10 max-h-80 overflow-y-auto pr-1">
                        <template x-for="item in $store.store.cart" :key="item.variant_id ? `${item.product_id}-${item.variant_id}` : item.product_id">
                            <div class="py-3 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-14 bg-slate-100 shrink-0 overflow-hidden border border-slate-200">
                                        <img
                                            :src="item.image"
                                            :alt="item.name"
                                            class="w-full h-full object-cover" />
                                    </div>
                                    <div class="text-left">
                                        <h4 class="text-xs font-sans font-bold font-medium text-slate-900 line-clamp-1" x-text="item.name"></h4>
                                        <p class="text-[10px] text-slate-500 font-light mt-0.5">
                                            Qty: <span x-text="item.quantity"></span>
                                            <template x-if="item.selected_color || item.color">
                                                <span> &bull; Color: <span x-text="item.selected_color || item.color"></span></span>
                                            </template>
                                            <template x-if="item.selected_size || item.size">
                                                <span> &bull; Size: <span x-text="item.selected_size || item.size"></span></span>
                                            </template>
                                        </p>
                                    </div>
                                </div>
                                <span class="text-xs font-mono font-semibold text-slate-900 shrink-0" x-text="'Rs. ' + ((item.price || 0) * item.quantity).toLocaleString()"></span>
                            </div>
                        </template>
                    </div>

                    <!-- Coupon Input Section -->
                    <div class="pt-4 border-t border-slate-200">
                        <template x-if="!appliedCoupon">
                            <div class="space-y-2">
                                <div class="flex gap-2">
                                    <input
                                        type="text"
                                        x-model="couponCode"
                                        placeholder="Promo / Coupon Code"
                                        class="w-full bg-slate-50 border border-slate-300 focus:border-blue-600 px-3 py-2 text-xs text-slate-900 uppercase tracking-wider placeholder-slate-400 focus:outline-none" />
                                    <button
                                        type="button"
                                        @click="applyCoupon"
                                        :disabled="isApplyingCoupon || !couponCode.trim()"
                                        class="px-4 py-2 bg-blue-600 text-white text-xs font-semibold uppercase tracking-wider hover:bg-blue-700 transition-colors disabled:opacity-40 shrink-0">
                                        <span x-text="isApplyingCoupon ? '...' : 'Apply'"></span>
                                    </button>
                                </div>
                                <template x-if="couponMessage">
                                    <p class="text-[11px] text-red-600" x-text="couponMessage"></p>
                                </template>
                            </div>
                        </template>
                        <template x-if="appliedCoupon">
                            <div class="flex items-center justify-between p-2.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-md">
                                <div>
                                    <span class="font-bold font-mono" x-text="appliedCoupon"></span>
                                    <span class="text-[11px] block text-emerald-700">Coupon discount applied!</span>
                                </div>
                                <button
                                    type="button"
                                    @click="removeCoupon"
                                    class="text-[11px] text-red-600 hover:underline font-semibold">
                                    Remove
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- Financial Breakdown Table -->
                    <div class="pt-4 border-t border-slate-200 space-y-2.5 text-xs text-[#222222]/80">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span class="font-mono font-medium text-slate-900" x-text="'Rs. ' + subtotal.toLocaleString()"></span>
                        </div>

                        <template x-if="couponDiscount > 0">
                            <div class="flex justify-between text-emerald-700 font-medium">
                                <span>Discount</span>
                                <span class="font-mono" x-text="'-Rs. ' + couponDiscount.toLocaleString()"></span>
                            </div>
                        </template>

                        <div class="flex justify-between items-center">
                            <div>
                                <span>Shipping Fee</span>
                                <span class="text-[10px] text-slate-400 block" x-text="isInsideValley ? 'Kathmandu Valley' : 'Nationwide Courier'"></span>
                            </div>
                            <span class="font-mono font-medium">
                                <template x-if="shippingFee === 0">
                                    <span class="text-emerald-700 font-bold uppercase">Free</span>
                                </template>
                                <template x-if="shippingFee > 0">
                                    <span class="text-slate-900" x-text="'Rs. ' + shippingFee.toLocaleString()"></span>
                                </template>
                            </span>
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex justify-between items-baseline text-sm sm:text-base">
                            <span class="font-bold text-slate-900 text-slate-900 uppercase">Grand Total</span>
                            <span class="font-bold text-slate-900 text-lg text-slate-900" x-text="'Rs. ' + grandTotal.toLocaleString()"></span>
                        </div>
                    </div>
                </div>

                <!-- Trust Guarantee Box -->
                <div class="p-4 bg-white rounded-xl border border-slate-200 text-xs space-y-3">
                    <div class="flex items-center gap-2.5 text-slate-900 font-bold text-[11px] uppercase tracking-wider">
                        <span>🛡️</span>
                        <span>Laijau Nepal Customer Guarantee</span>
                    </div>
                    <ul class="text-[11px] text-slate-500 font-normal space-y-1.5">
                        <li class="flex items-center gap-2">
                            <span class="text-emerald-600 font-bold">✓</span>
                            <span>100% Genuine Products & Verified Stock</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-emerald-600 font-bold">✓</span>
                            <span>Doorstep Courier Delivery with NCM & Pathao</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-emerald-600 font-bold">✓</span>
                            <span>7-Day Exchange Only (No Returns)</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-emerald-600 font-bold">✓</span>
                            <span>Direct WhatsApp Support: 9843512095</span>
                        </li>
                    </ul>
                </div>

            </div>

        </div>

    </div>
</div>
@endsection