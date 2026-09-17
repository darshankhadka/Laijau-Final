@php
    $variants = $product->variants ?? collect();
    $totalStock = $variants->count() > 0 ? $variants->sum('stock_quantity') : $product->quantity;
    $imgUrl = $product ? \App\Helpers\StorefrontHelper::getProductPrimaryImage($product) : null;
    $salesCount = \App\Models\OrderItem::where('product_id', $product->id)->sum('quantity');
    $salesRevenue = \App\Models\OrderItem::where('product_id', $product->id)->sum(\Illuminate\Support\Facades\DB::raw('quantity * unit_price'));
@endphp

<div class="space-y-6 text-sm text-gray-900 dark:text-gray-100">
    {{-- Header Banner with Image & Essential Info --}}
    <div class="flex flex-col sm:flex-row gap-5 items-start bg-gray-50 dark:bg-gray-800/60 p-4 rounded-xl border border-gray-200/80 dark:border-gray-700/80">
        <div class="w-28 h-28 shrink-0 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 flex items-center justify-center">
            @if($imgUrl)
                <img src="{{ $imgUrl }}" alt="{{ $product->name }}" class="w-full h-full object-cover" />
            @else
                <span class="font-serif text-2xl font-bold text-[#0A2E23] dark:text-[#C5A059]">LJ</span>
            @endif
        </div>

        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-mono px-2 py-0.5 rounded bg-gray-200 dark:bg-gray-700 font-semibold text-gray-700 dark:text-gray-300">
                    {{ $product->sku }}
                </span>
                @if($product->is_published && $product->is_active)
                    <span class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                        ● Active & Live
                    </span>
                @elseif(!$product->is_published)
                    <span class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                        ○ Draft
                    </span>
                @else
                    <span class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                        ⏸ Inactive
                    </span>
                @endif

                @if($product->is_featured)
                    <span class="text-xs px-2 py-0.5 rounded bg-[#C5A059]/20 text-[#8B6B2B] dark:text-[#C5A059] font-bold">
                        ★ Featured
                    </span>
                @endif
            </div>

            <h3 class="text-lg font-bold font-serif text-[#0A2E23] dark:text-[#C5A059] mt-1.5 leading-snug">
                {{ $product->name }}
            </h3>

            <div class="flex items-baseline gap-3 mt-2">
                <span class="text-xl font-bold font-serif text-[#0A2E23] dark:text-white">
                    Rs. {{ number_format((float)($product->price ?? 0), 2) }}
                </span>
                @if($product->compare_at_price > 0)
                    <span class="text-xs line-through text-gray-400">
                        Rs. {{ number_format((float)$product->compare_at_price, 2) }}
                    </span>
                @endif
            </div>

            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Fabric: <strong class="text-gray-700 dark:text-gray-300">{{ $product->fabric ?: ($product->material ?: 'Artisanal Silk') }}</strong> · Origin: <strong>{{ $product->country_of_origin ?: 'Nepal' }}</strong>
            </div>
        </div>
    </div>

    {{-- Stock & Commercial KPI Tiles --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="p-3.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-center">
            <div class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Total Stock</div>
            <div class="text-xl font-mono font-bold mt-1 {{ $totalStock > 3 ? 'text-emerald-600' : ($totalStock > 0 ? 'text-amber-600' : 'text-rose-600') }}">
                {{ $totalStock }} pcs
            </div>
        </div>

        <div class="p-3.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-center">
            <div class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Variants</div>
            <div class="text-xl font-mono font-bold mt-1 text-[#0A2E23] dark:text-[#C5A059]">
                {{ $variants->count() ?: 1 }}
            </div>
        </div>

        <div class="p-3.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-center">
            <div class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Units Sold</div>
            <div class="text-xl font-mono font-bold mt-1 text-gray-800 dark:text-gray-200">
                {{ $salesCount }}
            </div>
        </div>

        <div class="p-3.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-center">
            <div class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Sales Revenue</div>
            <div class="text-xl font-mono font-bold mt-1 text-[#0A2E23] dark:text-[#C5A059]">
                Rs. {{ number_format((float)$salesRevenue, 2) }}
            </div>
        </div>
    </div>

    {{-- Variant Breakdown Table (if multiple variants) --}}
    @if($variants->count() > 0)
        <div>
            <h4 class="text-xs uppercase font-bold tracking-wider text-gray-500 mb-2">Variant Matrix ({{ $variants->count() }})</h4>
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700 font-semibold text-gray-600 dark:text-gray-300">
                        <tr>
                            <th class="p-2.5">Colour</th>
                            <th class="p-2.5">Size</th>
                            <th class="p-2.5 font-mono">SKU</th>
                            <th class="p-2.5 text-right">Price</th>
                            <th class="p-2.5 text-center">Stock</th>
                            <th class="p-2.5 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($variants as $v)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                <td class="p-2.5 flex items-center gap-1.5 font-medium">
                                    <span class="w-3 h-3 rounded-full border border-black/10 shrink-0" style="background-color: {{ $v->color_hex ?: '#0A2E23' }};"></span>
                                    <span>{{ $v->color ?: 'Standard' }}</span>
                                </td>
                                <td class="p-2.5 font-medium">{{ $v->size ?: 'Standard' }}</td>
                                <td class="p-2.5 font-mono text-gray-500">{{ $v->sku }}</td>
                                <td class="p-2.5 text-right font-mono font-semibold text-[#0A2E23] dark:text-[#C5A059]">
                                    Rs. {{ number_format((float)($v->price ?: $product->price), 2) }}
                                </td>
                                <td class="p-2.5 text-center font-mono font-bold {{ $v->stock_quantity > 2 ? 'text-emerald-600' : ($v->stock_quantity > 0 ? 'text-amber-600' : 'text-rose-600') }}">
                                    {{ $v->stock_quantity }}
                                </td>
                                <td class="p-2.5 text-center">
                                    @if($v->is_active)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold">Active</span>
                                    @else
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-200 text-gray-700">Off</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Short Description preview --}}
    @if(!empty($product->short_description))
        <div class="p-3 bg-gray-50 dark:bg-gray-800/40 rounded-lg text-xs text-gray-600 dark:text-gray-300 leading-relaxed border border-gray-100 dark:border-gray-800">
            <strong>Summary:</strong> {{ $product->short_description }}
        </div>
    @endif

    {{-- Bottom Action Shortcuts --}}
    <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-200 dark:border-gray-700">
        <a
            href="{{ url('/products/' . $product->id) }}"
            target="_blank"
            class="px-3.5 py-2 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors inline-flex items-center gap-1.5"
        >
            <span>🌐 View on Store</span>
        </a>

        <a
            href="{{ \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $product->id]) }}"
            class="px-4 py-2 text-xs font-bold rounded-lg bg-[#0A2E23] text-white hover:bg-[#124032] transition-colors inline-flex items-center gap-1.5 shadow-xs"
        >
            <span>✏️ Edit Product</span>
        </a>
    </div>
</div>
