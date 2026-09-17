@extends('layouts.storefront')

@php
use App\Helpers\StorefrontHelper;

$images = StorefrontHelper::resolveImageList($product);
$primaryImg = $images[0] ?? '';

// Query active variants sorted logically (numeric sizes first)
$activeVariants = $product->variants()
->where('is_active', true)
->orderByRaw("CAST(size AS UNSIGNED) ASC, size ASC")
->get();

$hasActiveVariants = $activeVariants->isNotEmpty();

// Default to first in-stock variant, or first active variant
$defaultVariant = $hasActiveVariants
? ($activeVariants->first(fn($v) => $v->stock_quantity > 0) ?: $activeVariants->first())
: null;

$initialStock = $defaultVariant ? (int)$defaultVariant->stock_quantity : (int)$product->quantity;
$initialPrice = $defaultVariant ? (float)$defaultVariant->price : (float)($product->price ?? 0);
$initialSku = $defaultVariant ? $defaultVariant->sku : $product->sku;
$initialSize = $defaultVariant ? $defaultVariant->size : null;
$initialColor = $defaultVariant ? $defaultVariant->color : null;

$comparePrice = (float)($product->compare_at_price ?? 0);
$hasDiscount = $comparePrice > $initialPrice;
$discountPercent = $hasDiscount ? round((($comparePrice - $initialPrice) / $comparePrice) * 100) : 0;

$category = $product->categories->first();

// Serialized variants array for Alpine
$variantsJson = $activeVariants->map(function ($v) {
return [
'id' => $v->id,
'sku' => $v->sku,
'size' => $v->size,
'color' => $v->color,
'price' => (float)$v->price,
'stock' => (int)$v->stock_quantity,
];
})->values();

$distinctSizes = $activeVariants->pluck('size')->filter()->unique()->values()->all();
if (empty($distinctSizes) && !empty($product->dimensions)) {
$decoded = is_array($product->dimensions) ? $product->dimensions : json_decode($product->dimensions, true);
if (is_array($decoded)) {
foreach ($decoded as $opt) {
if (isset($opt['title']) && strtolower($opt['title']) === 'size' && !empty($opt['options'])) {
$distinctSizes = array_map('trim', $opt['options']);
}
}
}
}

$distinctColors = $activeVariants->pluck('color')->filter()->unique()->values()->all();
@endphp

@section('title', ($product->seo_title ?: $product->name . ' - Price in Nepal') . ' | Laijau.com')
@section('description', StorefrontHelper::stripHtml($product->seo_description ?: "Buy {$product->name} online at best price in Nepal. Cash on Delivery in Kathmandu Valley, fast nationwide delivery."))
@section('og_image', $primaryImg)

@push('head')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org/',
    '@type' => 'Product',
    'name' => $product->name,
    'image' => $images,
    'description' => StorefrontHelper::stripHtml($product->seo_description ?: $product->short_description ?: $product->description),
    'sku' => $initialSku,
    'brand' => [
        '@type' => 'Brand',
        'name' => 'Laijau',
    ],
    'offers' => [
        '@type' => 'Offer',
        'url' => url()->current(),
        'priceCurrency' => 'NPR',
        'price' => $initialPrice,
        'availability' => $initialStock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'seller' => [
            '@type' => 'Organization',
            'name' => 'Laijau.com',
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
@endpush

@section('content')
<script>
    function initProductDetail() {
        return {
            activeImage: @json($primaryImg),
            variants: @json($variantsJson),
            hasVariants: {{ $hasActiveVariants ? 'true' : 'false' }},
            selectedVariantId: {{ $defaultVariant ? $defaultVariant->id : 'null' }},
            selectedSize: @json($initialSize ?? ($distinctSizes[0] ?? '')),
            selectedColor: @json($initialColor ?? ($distinctColors[0] ?? '')),
            activeSku: @json($initialSku),
            activePrice: {{ $initialPrice }},
            availableStock: {{ $initialStock }},
            comparePrice: {{ $comparePrice }},
            quantity: 1,
            showStickyBar: false,
            sizeError: false,

            init() {
                this.recalculateVariant();
                window.addEventListener('scroll', () => {
                    this.showStickyBar = window.scrollY > 420;
                });
            },

            selectSize(size) {
                this.selectedSize = size;
                this.sizeError = false;
                this.recalculateVariant();
            },

            selectColor(color) {
                this.selectedColor = color;
                this.recalculateVariant();
            },

            recalculateVariant() {
                if (!this.hasVariants || this.variants.length === 0) return;

                let matched = null;
                if (this.selectedSize && this.selectedColor) {
                    matched = this.variants.find(v => String(v.size) === String(this.selectedSize) && String(v.color) === String(this.selectedColor));
                }
                if (!matched && this.selectedSize) {
                    matched = this.variants.find(v => String(v.size) === String(this.selectedSize));
                }
                if (!matched && this.selectedColor) {
                    matched = this.variants.find(v => String(v.color) === String(this.selectedColor));
                }
                if (!matched) {
                    matched = this.variants[0];
                }

                if (matched) {
                    this.selectedVariantId = matched.id;
                    this.activeSku = matched.sku;
                    this.activePrice = Number(matched.price) || {{ $initialPrice }};
                    this.availableStock = Number(matched.stock) || 0;
                    if (matched.size) this.selectedSize = matched.size;
                    if (matched.color) this.selectedColor = matched.color;
                    if (this.quantity > this.availableStock && this.availableStock > 0) {
                        this.quantity = this.availableStock;
                    }
                }
            },

            isSizeOutOfStock(size) {
                if (!this.hasVariants) return false;
                const match = this.variants.find(v => String(v.size) === String(size));
                return match ? Number(match.stock) <= 0 : false;
            },

            buyNow() {
                if (this.hasVariants && !this.selectedSize && {{ !empty($distinctSizes) ? 'true' : 'false' }}) {
                    this.sizeError = true;
                    return;
                }
                const store = this.$store?.store || window.Alpine?.store('store');
                if (store) {
                    store.addToCart({
                        product_id: {{ $product->id }},
                        variant_id: this.selectedVariantId,
                        name: @json($product->name),
                        slug: @json($product->slug),
                        sku: this.activeSku,
                        price: this.activePrice,
                        compare_at_price: this.comparePrice || null,
                        image: this.activeImage,
                        quantity: this.quantity,
                        selected_size: this.selectedSize || null,
                        selected_color: this.selectedColor || null,
                        max_stock: this.availableStock
                    }, false);
                }

                window.location.href = @json(route('storefront.checkout'));
            },

            addToBag() {
                this.addToCart();
            },

            addToCart() {
                if (this.hasVariants && !this.selectedSize && {{ !empty($distinctSizes) ? 'true' : 'false' }}) {
                    this.sizeError = true;
                    return;
                }
                this.sizeError = false;
                const store = this.$store?.store || window.Alpine?.store('store');
                if (store) {
                    store.addToCart({
                        product_id: {{ $product->id }},
                        variant_id: this.selectedVariantId,
                        name: @json($product->name),
                        slug: @json($product->slug),
                        sku: this.activeSku,
                        price: this.activePrice,
                        compare_at_price: this.comparePrice || null,
                        image: this.activeImage,
                        quantity: this.quantity,
                        selected_size: this.selectedSize || null,
                        selected_color: this.selectedColor || null,
                        max_stock: this.availableStock
                    }, true);
                }
            }
        };
    }
</script>

<div
    class="bg-slate-50 min-h-screen py-6 sm:py-8 text-left"
    x-data="initProductDetail()">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumbs -->
        <nav class="text-xs text-slate-500 mb-4 flex items-center gap-1.5 font-medium" aria-label="Breadcrumb">
            <a href="{{ url('/') }}" class="hover:text-blue-600 transition-colors">Home</a>
            <span class="text-slate-300">/</span>
            <a href="{{ route('storefront.catalogue') }}" class="hover:text-blue-600 transition-colors">Catalog</a>
            @if($category)
            <span class="text-slate-300">/</span>
            <a href="{{ route('storefront.category', $category->slug) }}" class="hover:text-blue-600 transition-colors">{{ $category->name }}</a>
            @endif
            <span class="text-slate-300">/</span>
            <span class="text-slate-900 font-bold truncate max-w-xs">{{ $product->name }}</span>
        </nav>

        <!-- Main Product Card Layout -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-4 sm:p-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">

                <!-- Left Column: Image Gallery -->
                <div class="lg:col-span-6 space-y-4">
                    <!-- Main Image Frame -->
                    <div class="relative aspect-square w-full bg-slate-100 rounded-2xl overflow-hidden border border-slate-200 group">
                        <img
                            src="{{ $primaryImg }}"
                            :src="activeImage"
                            alt="{{ $product->name }}"
                            width="800"
                            height="800"
                            class="w-full h-full object-cover object-center transition-transform duration-300 group-hover:scale-105"
                            loading="eager"
                            fetchpriority="high"
                            decoding="async"
                        />

                        <!-- Badges -->
                        <div class="absolute top-3 left-3 flex flex-col gap-1.5 z-10">
                            <template x-if="comparePrice > activePrice">
                                <span class="px-2.5 py-1 bg-red-600 text-white text-xs font-black rounded-lg shadow-xs"
                                    x-text="'-' + Math.round(((comparePrice - activePrice) / comparePrice) * 100) + '% OFF'">
                                </span>
                            </template>
                            @if($product->is_new_arrival)
                            <span class="px-2.5 py-1 bg-blue-600 text-white text-xs font-bold rounded-lg shadow-xs">
                                NEW
                            </span>
                            @endif
                        </div>

                        <!-- Wishlist Toggle -->
                        <button
                            type="button"
                            @click="$store.store.toggleWishlist({{ $product->id }})"
                            :class="$store.store.isInWishlist({{ $product->id }}) ? 'bg-rose-50 text-rose-600' : 'bg-white/90 text-slate-700 hover:text-rose-600 hover:bg-white'"
                            class="absolute top-3 right-3 z-10 w-9 h-9 rounded-full flex items-center justify-center backdrop-blur-xs shadow-xs transition-all cursor-pointer"
                            aria-label="Wishlist">
                            <svg class="w-5 h-5" :fill="$store.store.isInWishlist({{ $product->id }}) ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </button>
                    </div>

                    <!-- Thumbnail Strip -->
                    @if(count($images) > 1)
                    <div class="flex items-center gap-3 overflow-x-auto no-scrollbar pb-1">
                        @foreach($images as $img)
                        @php $thumbUrl = StorefrontHelper::getProductDerivativeUrl($img, 'thumbnail'); @endphp
                        <button
                            type="button"
                            @click="activeImage = '{{ $img }}'"
                            :class="activeImage === '{{ $img }}' ? 'border-blue-600 ring-2 ring-blue-600/20' : 'border-slate-200 hover:border-slate-300'"
                            class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden bg-slate-100 border-2 transition-all shrink-0 cursor-pointer">
                            <img
                                src="{{ $thumbUrl }}"
                                alt="{{ $product->name }}"
                                width="80"
                                height="80"
                                class="w-full h-full object-cover object-center"
                                loading="lazy"
                                decoding="async"
                            />
                        </button>
                        @endforeach
                    </div>
                    @endif
                </div>

                <!-- Right Column: Purchasing & Product Info -->
                <div class="lg:col-span-6 flex flex-col justify-between space-y-6 text-left">
                    <div>
                        <!-- Category / SKU -->
                        <div class="flex items-center justify-between gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                            @if($category)
                            <a href="{{ route('storefront.category', $category->slug) }}" class="text-blue-600 hover:underline">
                                {{ $category->name }}
                            </a>
                            @else
                            <span>Footwear & Lifestyle</span>
                            @endif
                            <span class="font-mono" x-text="'SKU: ' + activeSku">SKU: {{ $initialSku }}</span>
                        </div>

                        <!-- Product Title -->
                        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-tight">
                            {{ $product->name }}
                        </h1>

                        <!-- Stock Status Tag (Dynamic per variant) -->
                        <div class="mt-2.5 flex items-center gap-2">
                            <template x-if="availableStock <= 0">
                                <span class="px-2.5 py-0.5 bg-red-100 text-red-700 rounded-md text-xs font-bold flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                    Currently Out of Stock
                                </span>
                            </template>
                            <template x-if="availableStock > 0 && availableStock <= 3">
                                <span class="px-2.5 py-0.5 bg-amber-100 text-amber-800 rounded-md text-xs font-bold flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Only <span x-text="availableStock"></span> units left in stock!
                                </span>
                            </template>
                            <template x-if="availableStock > 3">
                                <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 rounded-md text-xs font-bold flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    In Stock (Ready to Dispatch)
                                </span>
                            </template>
                        </div>

                        <!-- Price Box (Dynamic per variant) -->
                        <div class="mt-4 p-4 bg-slate-50 rounded-2xl border border-slate-200/80 flex items-baseline gap-3 flex-wrap">
                            <span class="text-2xl sm:text-3xl font-black text-slate-900" x-text="'Rs. ' + Number(activePrice).toLocaleString('en-IN')">
                                {{ \App\Helpers\NepaliNumberHelper::formatCurrency($initialPrice) }}
                            </span>
                            <template x-if="comparePrice > activePrice">
                                <div class="flex items-baseline gap-2 flex-wrap">
                                    <span class="text-base sm:text-lg text-slate-400 line-through font-normal" x-text="'Rs. ' + Number(comparePrice).toLocaleString('en-IN')">
                                        {{ \App\Helpers\NepaliNumberHelper::formatCurrency($comparePrice) }}
                                    </span>
                                    <span class="text-xs font-extrabold text-red-600 bg-red-50 px-2.5 py-1 rounded-lg border border-red-100"
                                        x-text="'Save Rs. ' + (comparePrice - activePrice).toLocaleString('en-IN') + ' (' + Math.round(((comparePrice - activePrice) / comparePrice) * 100) + '% Off)'">
                                    </span>
                                </div>
                            </template>
                        </div>

                        <!-- Sizing Selector (If available) -->
                        @if(!empty($distinctSizes))
                        <div class="mt-5 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-900">Select Size:</span>
                                <span x-show="selectedSize" class="text-xs text-blue-600 font-semibold" x-text="'Selected: ' + selectedSize"></span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach($distinctSizes as $sOpt)
                                <button
                                    type="button"
                                    @click="selectSize('{{ $sOpt }}')"
                                    :class="{
                                                'bg-blue-600 text-white border-blue-600 font-bold shadow-xs': selectedSize === '{{ $sOpt }}',
                                                'bg-white text-slate-800 border-slate-300 hover:border-blue-500': selectedSize !== '{{ $sOpt }}' && !isSizeOutOfStock('{{ $sOpt }}'),
                                                'bg-slate-100 text-slate-400 border-dashed border-slate-200 line-through opacity-60': isSizeOutOfStock('{{ $sOpt }}')
                                            }"
                                    class="min-w-11 px-3.5 py-2 text-xs font-semibold rounded-xl border transition-all cursor-pointer"
                                    :title="isSizeOutOfStock('{{ $sOpt }}') ? 'Size {{ $sOpt }} is currently out of stock' : 'Size {{ $sOpt }}'">
                                    {{ $sOpt }}
                                </button>
                                @endforeach
                            </div>
                            <p x-show="sizeError" class="text-xs font-bold text-red-600 mt-1" style="display: none;">
                                Please select a size to proceed.
                            </p>
                        </div>
                        @endif

                        <!-- Color Display/Selector (If available) -->
                        @if(count($distinctColors) > 1)
                        <div class="mt-4 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-900">Select Color:</span>
                                <span x-show="selectedColor" class="text-xs text-blue-600 font-semibold" x-text="'Selected: ' + selectedColor"></span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach($distinctColors as $cOpt)
                                <button
                                    type="button"
                                    @click="selectColor('{{ $cOpt }}')"
                                    :class="selectedColor === '{{ $cOpt }}' ? 'bg-blue-600 text-white border-blue-600 font-bold shadow-xs' : 'bg-white text-slate-800 border-slate-300 hover:border-slate-500'"
                                    class="px-3.5 py-2 text-xs font-semibold rounded-xl border transition-all cursor-pointer">
                                    {{ $cOpt }}
                                </button>
                                @endforeach
                            </div>
                        </div>
                        @elseif(!empty($distinctColors[0]))
                        <div class="mt-4 flex items-center gap-2 text-xs text-slate-600 font-medium">
                            <span class="font-bold text-slate-900 uppercase tracking-wider">Color:</span>
                            <span class="px-2.5 py-1 bg-slate-100 text-slate-800 rounded-lg font-semibold">{{ $distinctColors[0] }}</span>
                        </div>
                        @endif

                        <!-- Quantity Selector -->
                        <div class="mt-5 flex items-center gap-4">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-900">Quantity:</span>
                            <div class="flex items-center border border-slate-200 rounded-xl bg-slate-50 overflow-hidden">
                                <button
                                    type="button"
                                    @click="if (quantity > 1) quantity--"
                                    class="w-9 h-9 flex items-center justify-center text-sm font-bold text-slate-600 hover:bg-slate-200 transition-colors cursor-pointer">
                                    -
                                </button>
                                <span class="w-10 text-center text-xs font-extrabold text-slate-900" x-text="quantity"></span>
                                <button
                                    type="button"
                                    @click="if (quantity < availableStock) quantity++"
                                    class="w-9 h-9 flex items-center justify-center text-sm font-bold text-slate-600 hover:bg-slate-200 transition-colors cursor-pointer"
                                    :disabled="quantity >= availableStock">
                                    +
                                </button>
                            </div>
                        </div>

                        <!-- Purchase Buttons -->
                        <div class="mt-6 space-y-2.5">
                            <template x-if="availableStock > 0">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <button
                                        type="button"
                                        @click="addToCart()"
                                        class="w-full py-3.5 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-xs font-black uppercase tracking-wider rounded-xl shadow-xs transition-all flex items-center justify-center gap-2 cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                        </svg>
                                        <span>Add to Cart</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="buyNow()"
                                        class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md transition-all flex items-center justify-center gap-2 cursor-pointer">
                                        <span>Buy Now &rarr;</span>
                                    </button>
                                </div>
                            </template>
                            <template x-if="availableStock <= 0">
                                <button
                                    type="button"
                                    disabled
                                    class="w-full py-3.5 bg-slate-200 text-slate-400 text-xs font-bold uppercase tracking-wider rounded-xl cursor-not-allowed">
                                    Sold Out
                                </button>
                            </template>

                            <!-- WhatsApp Inquiry Button -->
                            <a
                                :href="`https://wa.me/9779843512095?text=${encodeURIComponent('Hello Laijau, I am interested in ordering: {{ $product->name }} (SKU: ' + activeSku + ')')}`"
                                target="_blank"
                                rel="noopener"
                                class="w-full py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold rounded-xl border border-emerald-200 transition-colors flex items-center justify-center gap-2">
                                <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.983.54 1.777.818 2.795.818 3.18 0 5.766-2.587 5.767-5.767.001-3.182-2.586-5.766-5.767-5.766zm3.385 8.163c-.145.411-.734.782-1.025.82-.284.037-.648.156-2.096-.441-1.748-.72-2.859-2.502-2.946-2.617-.087-.116-.708-.941-.708-1.795 0-.855.449-1.275.609-1.449.16-.174.349-.218.465-.218.117 0 .233.001.334.006.107.005.25.04.39.377.145.349.494 1.205.538 1.293.043.087.073.189.014.305-.058.117-.087.189-.174.291-.088.102-.185.228-.264.306-.088.087-.18.182-.078.357.102.175.454.748.974 1.212.67.597 1.235.782 1.41.87.175.087.276.073.378-.044.102-.116.437-.508.553-.683.117-.174.233-.145.393-.087.16.058 1.018.48 1.193.567.175.087.291.131.335.204.043.072.043.421-.102.832z" />
                                </svg>
                                <span>Order or Inquire via WhatsApp</span>
                            </a>
                        </div>
                    </div>

                    <!-- Nepal Delivery & Trust Card -->
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/90 text-xs space-y-2.5">
                        <div class="flex items-start gap-2.5">
                            <span class="text-base">🛵</span>
                            <div>
                                <span class="font-bold text-slate-900 block">Kathmandu Valley Cash on Delivery</span>
                                <span class="text-slate-500 text-[11px]">Pay cash at your door inside Kathmandu, Lalitpur & Bhaktapur. Flat Rs. 100 or Free above Rs. 2,000.</span>
                            </div>
                        </div>
                        <div class="flex items-start gap-2.5 pt-2 border-t border-slate-200">
                            <span class="text-base">📦</span>
                            <div>
                                <span class="font-bold text-slate-900 block">Nationwide Courier (All 77 Districts)</span>
                                <span class="text-slate-500 text-[11px]">Fast dispatch via NCM & Pathao. Flat Rs. 150 or Free above Rs. 3,500 with prepaid eSewa, Khalti, or Bank Transfer.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Description & Specifications -->
            <div class="mt-10 pt-8 border-t border-slate-200">
                <h3 class="text-base font-black text-slate-900 uppercase tracking-wider mb-4">Product Details & Specifications</h3>
                <div class="prose prose-slate max-w-none text-xs sm:text-sm text-slate-600 leading-relaxed space-y-3">
                    @php
                    $rawDesc = $product->description ?: $product->short_description ?: $product->name;
                    $hasHtml = $rawDesc !== strip_tags($rawDesc);
                    @endphp
                    @if($hasHtml)
                    {!! clean_html($rawDesc) !!}
                    @else
                    {!! nl2br(e($rawDesc)) !!}
                    @endif
                </div>
            </div>
        </div>

        <!-- Related Products Section -->
        @if($relatedProducts->isNotEmpty())
        <div class="mt-12 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight">You May Also Like</h2>
                @if($category)
                <a href="{{ route('storefront.category', $category->slug) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800">
                    More in {{ $category->name }} &rarr;
                </a>
                @endif
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
                @foreach($relatedProducts as $rProd)
                <x-storefront.product-card :product="$rProd" />
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <!-- Sticky Mobile Purchase Bar -->
    <template x-if="availableStock > 0">
        <div
            x-show="showStickyBar"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="sm:hidden fixed bottom-[calc(3.5rem+env(safe-area-inset-bottom,0px))] inset-x-0 z-30 bg-white/95 backdrop-blur-md border-t border-slate-200 p-3 shadow-lg flex items-center justify-between gap-3"
            style="display: none;">
            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                <img src="{{ StorefrontHelper::getProductDerivativeUrl($primaryImg, 'thumbnail') }}" alt="{{ $product->name }}" width="40" height="40" class="w-10 h-10 rounded-lg object-cover border border-slate-200 shrink-0" loading="lazy" decoding="async" />
                <div class="min-w-0">
                    <p class="text-[11px] font-bold text-slate-900 truncate">{{ $product->name }}</p>
                    <p class="text-xs font-black text-blue-600" x-text="'Rs. ' + Number(activePrice).toLocaleString('en-IN')">
                        {{ \App\Helpers\NepaliNumberHelper::formatCurrency($initialPrice) }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    @click="addToCart()"
                    class="px-3 py-2 bg-blue-50 text-blue-700 border border-blue-200 rounded-xl text-xs font-bold cursor-pointer">
                    Cart
                </button>
                <button
                    type="button"
                    @click="buyNow()"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold cursor-pointer">
                    Buy Now
                </button>
            </div>
        </div>
    </template>
</div>
@endsection