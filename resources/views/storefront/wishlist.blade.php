@extends('layouts.storefront')

@section('title', 'Your Wishlist | Laijau.com')
@section('description', 'View and manage your saved products on Laijau Nepal. Fast checkout with Cash on Delivery and nationwide courier delivery.')

@section('content')
<div
    class="bg-slate-50 min-h-screen py-8 sm:py-12 text-left"
    x-data="{
        products: [],
        loading: true,

        async init() {
            const ids = $store.store.wishlist;
            if (!ids || ids.length === 0) {
                this.products = [];
                this.loading = false;
                return;
            }

            try {
                const res = await fetch(`{{ url('/api/products') }}?ids=${ids.join(',')}&per_page=50`);
                if (res.ok) {
                    const data = await res.json();
                    this.products = data.data || [];
                }
            } catch (e) {
                this.products = [];
            } finally {
                this.loading = false;
            }

            this.$watch('$store.store.wishlist', (newIds) => {
                if (newIds.length === 0) {
                    this.products = [];
                } else {
                    this.products = this.products.filter(p => newIds.includes(Number(p.id)));
                }
            });
        }
    }">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-xs mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600 block mb-1">
                    Saved Items
                </span>
                <h1 class="text-xl sm:text-3xl font-black text-slate-900 tracking-tight">
                    Your Wishlist
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Items you have saved. Come back to them whenever you're ready to order.
                </p>
            </div>
            <a href="{{ route('storefront.catalogue') }}" class="text-xs font-bold text-blue-600 hover:text-blue-800">
                Continue Shopping &rarr;
            </a>
        </div>

        <!-- Loading State -->
        <div x-show="loading" class="py-24 text-center">
            <div class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
            <p class="text-xs font-bold text-slate-600 uppercase tracking-wider">
                Loading your wishlist...
            </p>
        </div>

        <!-- Empty Wishlist State -->
        <div x-show="!loading && products.length === 0" class="bg-white border border-slate-200 rounded-3xl p-12 text-center max-w-md mx-auto shadow-xs" style="display: none;">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                <svg class="w-8 h-8 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
            </div>
            <h2 class="text-base font-bold text-slate-900 mb-1">Your wishlist is empty</h2>
            <p class="text-xs text-slate-500 mb-6">
                Tap the heart icon on any product to save it here for later.
            </p>
            <a
                href="{{ route('storefront.catalogue') }}"
                class="inline-block px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors shadow-xs">
                Browse All Products
            </a>
        </div>

        <!-- Wishlist Items Grid -->
        <div x-show="!loading && products.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4" style="display: none;">
            <template x-for="product in products" :key="product.id">
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden flex flex-col justify-between group shadow-xs hover:shadow-md transition-all">
                    <div class="relative aspect-square bg-slate-100 overflow-hidden">
                        <a :href="`{{ url('/products') }}/${product.slug || product.id}`">
                            <img
                                :src="$store.store.getImageUrl(product.featured_image)"
                                :alt="product.name"
                                width="300"
                                height="300"
                                loading="lazy"
                                decoding="async"
                                class="h-full w-full object-cover object-center group-hover:scale-105 transition-transform duration-300" />
                        </a>
                        <button
                            type="button"
                            @click="$store.store.toggleWishlist(product.id)"
                            class="absolute top-2.5 right-2.5 w-8 h-8 bg-white/90 backdrop-blur-xs text-rose-600 hover:bg-white rounded-full flex items-center justify-center transition-colors cursor-pointer shadow-xs"
                            :aria-label="`Remove ${product.name} from wishlist`">
                            <svg class="w-4 h-4" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="p-4 flex flex-col justify-between flex-1">
                        <div>
                            <span class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold" x-text="product.categories && product.categories[0] ? product.categories[0].name : 'Footwear'"></span>
                            <h3 class="text-xs font-bold text-slate-900 mt-1 line-clamp-2">
                                <a :href="`{{ url('/products') }}/${product.slug || product.id}`" x-text="product.name" class="hover:text-blue-600 transition-colors"></a>
                            </h3>
                            <p class="text-sm font-black text-slate-900 mt-2" x-text="$store.store.formatAmount(product.price)"></p>
                        </div>
                        <div class="pt-3 mt-3 border-t border-slate-100 flex gap-2">
                            <button
                                type="button"
                                @click="$store.store.addToCart({
                                    product_id: product.id,
                                    name: product.name,
                                    price: Number(product.price) || 0,
                                    image: product.featured_image,
                                    slug: product.slug,
                                    max_stock: product.quantity || 99
                                }, true);"
                                class="flex-1 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors cursor-pointer">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection