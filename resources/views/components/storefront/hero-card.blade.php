@props(['product'])

@php
    use App\Helpers\StorefrontHelper;
    if (!$product) return;
    $imageSrc = StorefrontHelper::getProductDerivativeUrl($product, 'thumbnail');
    $productUrl = url('/products/' . ($product->slug ?: $product->id));
    $firstCat = $product->categories->first() ?? null;
@endphp

<a
    href="{{ $productUrl }}"
    class="group relative block bg-white border border-slate-200 rounded-2xl p-3.5 sm:p-4 max-w-sm w-full text-left transition-all duration-300 hover:border-blue-400 hover:shadow-lg shadow-xs"
>
    <div class="flex items-center gap-3.5">
        <!-- Thumbnail Frame -->
        <div class="relative h-24 w-20 sm:h-28 sm:w-24 shrink-0 bg-slate-50 rounded-xl overflow-hidden border border-slate-100">
            <img
                src="{{ $imageSrc }}"
                alt="{{ $product->name }}"
                width="96"
                height="112"
                class="h-full w-full object-cover object-center group-hover:scale-105 transition-transform duration-500 ease-out"
                loading="lazy"
                decoding="async"
            />
            <div class="absolute top-1.5 left-1.5">
                <span class="bg-blue-600 text-white text-[8px] tracking-wider uppercase px-1.5 py-0.5 font-bold rounded-md">
                    Featured
                </span>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 min-w-0">
            <span class="text-[9px] font-bold tracking-wider uppercase text-blue-600 block mb-0.5">
                {{ $firstCat ? $firstCat->name : 'Featured Item' }}
            </span>
            <h3 class="text-xs sm:text-sm font-bold text-slate-900 line-clamp-2 mb-1 group-hover:text-blue-600 transition-colors leading-snug">
                {{ $product->name }}
            </h3>
            @if($product->material)
                <p class="text-[11px] text-slate-500 line-clamp-1 mb-1.5">
                    {{ $product->material }}
                </p>
            @endif

            <div class="text-xs font-black text-slate-900 mb-1.5">
                <span>{{ \App\Helpers\NepaliNumberHelper::formatCurrency($product->price) }}</span>
            </div>

            <span class="inline-flex items-center text-[10px] font-bold uppercase tracking-wider text-blue-600 group-hover:text-blue-700 transition-colors">
                <span>View Details</span>
                <span class="ml-1 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
            </span>
        </div>
    </div>
</a>
