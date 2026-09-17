@props(['product', 'priority' => false])

@php
    use App\Helpers\StorefrontHelper;

    $primaryImage = StorefrontHelper::getProductDerivativeUrl($product, 'card');
    $primarySrcset = StorefrontHelper::getProductSrcset($product);
    $images = StorefrontHelper::resolveImageList($product, 'card');
    $hoverImage = count($images) > 1 ? $images[1] : null;
    $hoverSrcset = count($images) > 1 ? StorefrontHelper::getProductSrcset($images[1]) : null;

    $isOutOfStock = $product->track_quantity && $product->quantity <= 0;
    $isLowStock = $product->track_quantity && $product->quantity > 0 && $product->quantity <= ($product->low_stock_threshold ?? 3);
    
    $currentPrice = (float)($product->price ?? 0);
    $originalPrice = (float)($product->compare_at_price ?? 0);
    $hasDiscount = $originalPrice > $currentPrice;
    $discountPercent = $hasDiscount ? round((($originalPrice - $currentPrice) / $originalPrice) * 100) : 0;

    $productUrl = route('storefront.product', $product->slug ?: $product->id);
    $firstCategory = $product->categories->first();
    $hasActiveVariants = $product->relationLoaded('variants') 
        ? $product->variants->where('is_active', true)->isNotEmpty() 
        : $product->variants()->where('is_active', true)->exists();
@endphp

<div
    x-data="{ isHovered: false, adding: false }"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
    class="group relative flex flex-col bg-white rounded-2xl border border-slate-200/80 hover:border-slate-300 hover:shadow-lg transition-all duration-300 overflow-hidden text-left"
>
    <!-- Product Image Frame (Square 1:1 Aspect Ratio) -->
    <div class="relative aspect-square w-full bg-slate-100 overflow-hidden">
        <a href="{{ $productUrl }}" class="block w-full h-full focus:outline-none">
            <img
                src="{{ $primaryImage }}"
                @if($primarySrcset)
                srcset="{{ $primarySrcset }}"
                sizes="(max-width: 640px) 45vw, (max-width: 1024px) 25vw, 280px"
                @endif
                :src="isHovered && '{{ $hoverImage }}' ? '{{ $hoverImage }}' : '{{ $primaryImage }}'"
                @if($hoverSrcset)
                :srcset="isHovered && '{{ $hoverSrcset }}' ? '{{ $hoverSrcset }}' : '{{ $primarySrcset }}'"
                @endif
                alt="{{ $product->name }}"
                width="400"
                height="400"
                class="w-full h-full object-cover object-center transition-transform duration-500 group-hover:scale-105 {{ $isOutOfStock ? 'opacity-60 grayscale-[40%]' : '' }}"
                loading="{{ $priority ? 'eager' : 'lazy' }}"
                decoding="async"
            />
        </a>

        <!-- Badges (Discount, New, Featured, Stock) -->
        <div class="absolute top-2.5 left-2.5 flex flex-col gap-1 z-10">
            @if($hasDiscount && $discountPercent > 0)
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-red-600 text-white shadow-xs">
                    -{{ $discountPercent }}%
                </span>
            @endif
            @if($product->is_new_arrival)
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-600 text-white shadow-xs">
                    NEW
                </span>
            @endif
            @if($isOutOfStock)
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-600 text-white shadow-xs">
                    SOLD OUT
                </span>
            @endif
        </div>

        <!-- Wishlist Button -->
        <button
            type="button"
            @click.prevent.stop="$store.store.toggleWishlist({{ $product->id }})"
            :class="$store.store.isInWishlist({{ $product->id }}) ? 'bg-rose-50 text-rose-600' : 'bg-white/90 text-slate-600 hover:text-rose-600 hover:bg-white'"
            class="absolute top-2.5 right-2.5 z-20 w-8 h-8 rounded-full flex items-center justify-center backdrop-blur-xs shadow-xs transition-all active:scale-90 cursor-pointer"
            aria-label="Add to wishlist"
        >
            <svg class="w-4 h-4" :fill="$store.store.isInWishlist({{ $product->id }}) ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
            </svg>
        </button>

        <!-- Quick Add to Cart Overlay on Desktop Hover -->
        @if(!$isOutOfStock)
            <div class="absolute inset-x-2 bottom-2 hidden sm:block opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-200 z-20">
                @if($hasActiveVariants)
                    <a
                        href="{{ $productUrl }}"
                        class="w-full py-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold rounded-xl shadow-md flex items-center justify-center gap-1.5 transition-colors"
                    >
                        <span>Select Size</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                @else
                    <button
                        type="button"
                        @click.prevent.stop="
                            $store.store.addToCart({
                                product_id: {{ $product->id }},
                                name: '{{ addslashes($product->name) }}',
                                slug: '{{ $product->slug }}',
                                sku: '{{ $product->sku }}',
                                price: {{ $currentPrice }},
                                compare_at_price: {{ $originalPrice ?: 'null' }},
                                image: '{{ $primaryImage }}',
                                quantity: 1,
                                max_stock: {{ $product->quantity ?? 99 }}
                            }, false);
                            adding = true;
                            setTimeout(() => adding = false, 1200);
                        "
                        class="w-full py-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold rounded-xl shadow-md flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                    >
                        <svg x-show="!adding" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span x-text="adding ? 'Added!' : 'Quick Add'"></span>
                    </button>
                @endif
            </div>
        @endif
    </div>

    <!-- Product Info Details -->
    <div class="p-3 sm:p-4 flex flex-col flex-1 justify-between">
        <div>
            <!-- Category / Tag -->
            @if($firstCategory)
                <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider block mb-1 truncate">
                    {{ $firstCategory->name }}
                </span>
            @else
                <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider block mb-1">
                    Footwear & Apparel
                </span>
            @endif

            <!-- Product Title -->
            <a
                href="{{ $productUrl }}"
                class="text-xs sm:text-sm font-semibold text-slate-900 hover:text-blue-600 transition-colors line-clamp-2 leading-snug"
                title="{{ $product->name }}"
            >
                {{ $product->name }}
            </a>
        </div>

        <!-- Pricing & Mobile Action -->
        <div class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
            <div class="flex flex-col">
                <div class="flex items-baseline gap-1.5 flex-wrap">
                    <span class="text-sm sm:text-base font-extrabold text-slate-900">
                        {{ \App\Helpers\NepaliNumberHelper::formatCurrency($currentPrice) }}
                    </span>
                    @if($hasDiscount)
                        <span class="text-xs text-slate-400 line-through font-normal">
                            {{ \App\Helpers\NepaliNumberHelper::formatCurrency($originalPrice) }}
                        </span>
                    @endif
                </div>
                @if($isLowStock)
                    <span class="text-[10px] text-amber-600 font-semibold mt-0.5">Only {{ $product->quantity }} left!</span>
                @endif
            </div>

            <!-- Mobile Fast Add Icon -->
            @if(!$isOutOfStock)
                @if($hasActiveVariants)
                    <a
                        href="{{ $productUrl }}"
                        class="sm:hidden w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white flex items-center justify-center transition-colors active:scale-90"
                        aria-label="Select size"
                        title="Select size"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @else
                    <button
                        type="button"
                        @click.prevent.stop="
                            $store.store.addToCart({
                                product_id: {{ $product->id }},
                                name: '{{ addslashes($product->name) }}',
                                slug: '{{ $product->slug }}',
                                sku: '{{ $product->sku }}',
                                price: {{ $currentPrice }},
                                compare_at_price: {{ $originalPrice ?: 'null' }},
                                image: '{{ $primaryImage }}',
                                quantity: 1,
                                max_stock: {{ $product->quantity ?? 99 }}
                            }, false);
                        "
                        class="sm:hidden w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white flex items-center justify-center transition-colors active:scale-90"
                        aria-label="Quick add"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </button>
                @endif
            @endif
        </div>
    </div>
</div>
