<x-filament-panels::page>
    @php
    $product = $this->getRecord();
    $canViewCost = auth()->user()?->isSuperAdmin() || auth()->user()?->hasRole('Store Manager', 'admin') || auth()->user()?->hasRole('Store Manager', 'web');
    $primaryImg = \App\Helpers\StorefrontHelper::getProductPrimaryImage($product) ?: '';
    $sellingPrice = (float) ($product->price ?: 0);
    $costPrice = (float) ($product->cost_price ?: 0);
    $margin = $product->gross_margin;
    $marginPct = $product->gross_margin_percent;
    $totalStock = $product->total_stock;
    $variantsCount = $product->variants->count();
    $sales = $this->salesAnalytics;
    @endphp

    <style>
        .lj-pv-page {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            box-sizing: border-box;
        }

        .lj-tab-btn {
            padding: 0.875rem 1rem !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            color: #64748b !important;
            background: transparent !important;
            border: none !important;
            border-bottom: 2px solid transparent !important;
            cursor: pointer !important;
            transition: all 0.15s ease !important;
            white-space: nowrap !important;
            text-decoration: none !important;
        }

        .lj-tab-btn:hover {
            color: #0f172a !important;
            border-bottom-color: #cbd5e1 !important;
        }

        .lj-tab-btn.active {
            color: #065f46 !important;
            font-weight: 800 !important;
            border-bottom-color: #065f46 !important;
            background: #ffffff !important;
        }
    </style>

    <div class="space-y-6">

        <!-- 1. TOP PRODUCT PROFILE HEADER -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div class="flex flex-col md:flex-row gap-5 items-start md:items-center justify-between">
                <div class="flex items-start gap-4">
                    <!-- Product Thumbnail -->
                    <div class="w-20 h-20 rounded-lg overflow-hidden border border-gray-200 bg-gray-50 shrink-0">
                        @if($primaryImg)
                        <img src="{{ $primaryImg }}" alt="{{ $product->name }}" class="w-full h-full object-cover object-center" />
                        @else
                        <div class="w-full h-full flex items-center justify-center text-gray-400 font-bold text-xl bg-gray-100">
                            {{ substr($product->name, 0, 1) }}
                        </div>
                        @endif
                    </div>

                    <!-- Product Identity -->
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800">
                                {{ $product->categories->first()?->name ?? 'General Retail' }}
                            </span>
                            @if($product->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                ● Catalog: Active (POS & Inventory)
                            </span>
                            @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-300">
                                ⏸ Catalog: Archived
                            </span>
                            @endif

                            @if($product->is_published && $product->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                                🌐 Storefront: Published & Live
                            </span>
                            @elseif($product->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                🔒 Storefront: Unpublished (POS Only)
                            </span>
                            @endif

                            @if($product->brand)
                            <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ $product->brand }}
                            </span>
                            @endif
                        </div>

                        <h1 class="text-xl font-bold text-gray-900 leading-tight">
                            {{ $product->name }}
                            @if($product->model)
                            <span class="text-sm font-normal text-gray-500 font-mono">({{ $product->model }})</span>
                            @endif
                        </h1>

                        <div class="flex items-center gap-4 text-xs text-gray-500 font-mono">
                            <span>SKU: <strong class="text-gray-800">{{ $product->sku ?? 'N/A' }}</strong></span>
                            @if($product->barcode)
                            <span>Barcode: <strong class="text-gray-800">{{ $product->barcode }}</strong></span>
                            @endif
                            @if($product->internal_reference)
                            <span>Bin: <strong class="text-gray-800">{{ $product->internal_reference }}</strong></span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- EAN-13 Barcode Vector Preview -->
                @if(!empty($this->barcodeSvg))
                <div class="bg-gray-50 border border-gray-200 p-2.5 rounded-lg flex flex-col items-center justify-center shrink-0 w-44">
                    <div class="w-full">
                        {!! $this->barcodeSvg !!}
                    </div>
                    <span class="text-[11px] font-mono text-gray-600 tracking-widest mt-1">{{ $product->barcode }}</span>
                </div>
                @endif
            </div>
        </div>

        <!-- 2. FOUR KEY RETAIL SUMMARY TILES -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Tile 1: Retail Price -->
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Retail Selling Price</span>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-gray-900 font-mono">Rs. {{ number_format($sellingPrice, 2) }}</span>
                    @if($product->compare_at_price && (float)$product->compare_at_price > $sellingPrice)
                    <span class="text-xs text-gray-400 line-through font-mono">Rs. {{ number_format((float)$product->compare_at_price, 2) }}</span>
                    @endif
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    VAT: {{ $product->tax_class === 'exempt' ? 'Exempt' : '13% Included' }}
                </div>
            </div>

            <!-- Tile 2: Sourcing Cost & Margin (Role Protected) -->
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Landed Cost & Margin</span>
                @if($canViewCost)
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-gray-900 font-mono">
                        {{ $costPrice > 0 ? 'Rs. ' . number_format($costPrice, 2) : '—' }}
                    </span>
                    @if($marginPct > 0)
                    <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                        {{ $marginPct }}% Margin
                    </span>
                    @endif
                </div>
                <div class="mt-1 text-xs text-gray-500 font-mono">
                    Unit Margin: {{ $margin > 0 ? '+Rs. ' . number_format($margin, 2) : 'Not calculated' }}
                </div>
                @else
                <div class="mt-1 text-sm font-semibold text-gray-400 italic">
                    Restricted Access
                </div>
                <div class="mt-1 text-xs text-gray-400">
                    Visible to Store Managers only
                </div>
                @endif
            </div>

            <!-- Tile 3: Available Physical Stock -->
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Physical Inventory</span>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-gray-900 font-mono">{{ $totalStock }}</span>
                    <span class="text-xs font-semibold {{ $totalStock > 3 ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : ($totalStock > 0 ? 'text-amber-700 bg-amber-50 border-amber-200' : 'text-rose-700 bg-rose-50 border-rose-200') }} px-2 py-0.5 rounded border">
                        {{ $product->formatted_stock }}
                    </span>
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    Alert threshold: {{ $product->low_stock_threshold ?? 3 }} units
                </div>
            </div>

            <!-- Tile 4: Total Variants -->
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Product Variations</span>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-gray-900 font-mono">{{ $variantsCount }}</span>
                    <span class="text-xs text-gray-600">
                        {{ $variantsCount > 0 ? 'Color & Size Matrix' : 'Single SKU Product' }}
                    </span>
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    {{ $product->isAvailable() ? 'Active for Sale' : 'Currently Unavailable' }}
                </div>
            </div>
        </div>

        <!-- 3. RETAIL TAB NAVIGATION -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="border-b border-gray-200 bg-gray-50 px-4">
                <nav class="flex gap-2 sm:gap-4 overflow-x-auto" style="-webkit-overflow-scrolling: touch;" aria-label="Tabs">
                    @php
                    $tabs = [
                    'overview' => 'Overview & Details',
                    'variants' => 'Variants Matrix (' . $variantsCount . ')',
                    'inventory' => 'Warehouse Inventory & Movements',
                    'pricing' => 'Pricing & Margins',
                    'sales' => 'Sales Performance',
                    'activity' => 'Activity History',
                    ];
                    @endphp

                    @foreach($tabs as $tabKey => $tabLabel)
                    <button
                        type="button"
                        wire:click="setTab('{{ $tabKey }}')"
                        class="lj-tab-btn {{ $this->activeTab === $tabKey ? 'active' : '' }}">
                        {{ $tabLabel }}
                    </button>
                    @endforeach
                </nav>
            </div>

            <div class="p-6">
                <!-- TAB 1: OVERVIEW & DETAILS -->
                @if($this->activeTab === 'overview')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b pb-2">Description & Notes</h3>
                        @if($product->short_description)
                        <div class="text-xs text-gray-700 italic bg-gray-50 p-3 rounded-lg border border-gray-200">
                            {{ $product->short_description }}
                        </div>
                        @endif

                        <div class="prose prose-sm text-gray-800 max-w-none text-xs leading-relaxed">
                            {!! !empty($product->description) ? clean_html($product->description) : '<span class="text-gray-400 italic">No detailed description entered.</span>' !!}
                        </div>

                        @if(!empty($product->images) && is_array($product->images))
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b pb-2 pt-4">Gallery Photography ({{ count($product->images) }})</h3>
                        <div class="grid grid-cols-4 gap-2">
                            @foreach($product->images as $img)
                            @php $galleryUrl = \App\Helpers\StorefrontHelper::getImageUrl($img); @endphp
                            @if($galleryUrl)
                            <a href="{{ $galleryUrl }}" target="_blank" class="block aspect-square rounded-lg border border-gray-200 overflow-hidden bg-gray-50 hover:opacity-90">
                                <img src="{{ $galleryUrl }}" alt="" class="w-full h-full object-cover" />
                            </a>
                            @endif
                            @endforeach
                        </div>
                        @endif
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b pb-2">Technical Specifications</h3>
                        <dl class="divide-y divide-gray-100 text-xs font-mono">
                            <div class="py-2 flex justify-between">
                                <dt class="text-gray-500 font-sans">Brand:</dt>
                                <dd class="font-bold text-gray-800 font-sans">{{ $product->brand ?? '—' }}</dd>
                            </div>
                            <div class="py-2 flex justify-between">
                                <dt class="text-gray-500 font-sans">Model / Style Code:</dt>
                                <dd class="font-bold text-gray-800">{{ $product->model ?? '—' }}</dd>
                            </div>
                            <div class="py-2 flex justify-between">
                                <dt class="text-gray-500 font-sans">Material Composition:</dt>
                                <dd class="font-bold text-gray-800 font-sans">{{ $product->material ?? '—' }}</dd>
                            </div>
                            <div class="py-2 flex justify-between">
                                <dt class="text-gray-500 font-sans">Fabric / Weave:</dt>
                                <dd class="font-bold text-gray-800 font-sans">{{ $product->fabric ?? '—' }}</dd>
                            </div>
                            <div class="py-2 flex justify-between">
                                <dt class="text-gray-500 font-sans">Country of Origin:</dt>
                                <dd class="font-bold text-gray-800 font-sans">{{ $product->country_of_origin ?? 'Nepal' }}</dd>
                            </div>
                            <div class="py-2 flex justify-between">
                                <dt class="text-gray-500 font-sans">Weight (kg):</dt>
                                <dd class="font-bold text-gray-800">{{ $product->weight ? $product->weight . ' kg' : '—' }}</dd>
                            </div>
                            <div class="py-2 flex justify-between">
                                <dt class="text-gray-500 font-sans">Supplier Name:</dt>
                                <dd class="font-bold text-gray-800 font-sans">{{ $product->supplier_name ?? '—' }}</dd>
                            </div>
                            <div class="py-2 flex justify-between">
                                <dt class="text-gray-500 font-sans">Supplier SKU:</dt>
                                <dd class="font-bold text-gray-800">{{ $product->supplier_sku ?? '—' }}</dd>
                            </div>
                            <div class="py-2 flex justify-between">
                                <dt class="text-gray-500 font-sans">Internal Reference:</dt>
                                <dd class="font-bold text-gray-800">{{ $product->internal_reference ?? '—' }}</dd>
                            </div>
                            <div class="py-2 flex justify-between">
                                <dt class="text-gray-500 font-sans">Created Date:</dt>
                                <dd class="text-gray-600">{{ $product->created_at?->format('d M Y, h:i A') }}</dd>
                            </div>
                            <div class="py-2 flex justify-between">
                                <dt class="text-gray-500 font-sans">Last Updated:</dt>
                                <dd class="text-gray-600">{{ $product->updated_at?->format('d M Y, h:i A') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
                @endif

                <!-- TAB 2: VARIANTS MATRIX -->
                @if($this->activeTab === 'variants')
                @if($product->variants->isEmpty())
                <div class="text-center py-10 text-gray-500 text-xs">
                    <div class="text-base font-semibold text-gray-700">Single SKU Product</div>
                    <p class="mt-1">This product does not have variants. All stock and pricing are managed at the product master level.</p>
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-gray-600 uppercase font-semibold">
                            <tr>
                                <th class="py-3 px-3">Color</th>
                                <th class="py-3 px-3">Size</th>
                                <th class="py-3 px-3">SKU</th>
                                <th class="py-3 px-3">Barcode (EAN-13)</th>
                                <th class="py-3 px-3 text-right">Retail Price</th>
                                @if($canViewCost)
                                <th class="py-3 px-3 text-right">Cost Price</th>
                                <th class="py-3 px-3 text-right">Margin</th>
                                @endif
                                <th class="py-3 px-3 text-right">Stock</th>
                                <th class="py-3 px-3 text-center">Status</th>
                                <th class="py-3 px-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-mono">
                            @foreach($product->variants as $variant)
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-3 font-sans">
                                    <div class="flex items-center gap-2">
                                        @if($variant->color_hex)
                                        <span class="w-3.5 h-3.5 rounded-full border border-gray-300 inline-block" style="background-color: {{ $variant->color_hex }}"></span>
                                        @endif
                                        <span class="font-medium text-gray-900">{{ $variant->color ?? 'Standard' }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-3 font-bold text-gray-800 font-sans">
                                    {{ $variant->size ?? 'Standard' }}
                                </td>
                                <td class="py-3 px-3 text-gray-700">
                                    {{ $variant->sku ?? '—' }}
                                </td>
                                <td class="py-3 px-3 text-gray-700">
                                    {{ $variant->barcode ?? '—' }}
                                </td>
                                <td class="py-3 px-3 text-right font-bold text-gray-900">
                                    Rs. {{ number_format((float)($variant->price ?: $product->price ?: 0), 2) }}
                                </td>
                                @if($canViewCost)
                                <td class="py-3 px-3 text-right text-gray-600">
                                    {{ $variant->cost_price ? 'Rs. ' . number_format((float)$variant->cost_price, 2) : '—' }}
                                </td>
                                <td class="py-3 px-3 text-right font-sans">
                                    <span class="px-1.5 py-0.5 rounded text-[11px] font-bold {{ $variant->margin_percent >= 30 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ $variant->margin_percent }}%
                                    </span>
                                </td>
                                @endif
                                <td class="py-3 px-3 text-right font-bold {{ $variant->stock_quantity > 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                    {{ $variant->stock_quantity }}
                                </td>
                                <td class="py-3 px-3 text-center font-sans">
                                    @if($variant->is_active)
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                                    @else
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-600 border border-gray-300">Inactive</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-right font-sans space-x-1">
                                    <a href="/intadmin/barcodes-labels?variant_id={{ $variant->id }}" class="text-blue-600 hover:underline text-xs font-semibold">Print</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
                @endif

                <!-- TAB 3: WAREHOUSE INVENTORY & MOVEMENTS -->
                @if($this->activeTab === 'inventory')
                <div class="space-y-6">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-3">Warehouse Stock Distribution</h3>
                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="w-full text-left text-xs divide-y divide-gray-200">
                                <thead class="bg-gray-50 text-gray-600 uppercase font-semibold">
                                    <tr>
                                        <th class="py-2.5 px-3">Warehouse Hub</th>
                                        <th class="py-2.5 px-3">Code</th>
                                        <th class="py-2.5 px-3 text-right">Physical On Hand</th>
                                        <th class="py-2.5 px-3 text-right">Reserved / Committed</th>
                                        <th class="py-2.5 px-3 text-right font-bold text-gray-900">Available For Sale</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 font-mono">
                                    @foreach($this->warehouseStock as $wh)
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2.5 px-3 font-sans font-medium text-gray-900">
                                            {{ $wh['name'] }}
                                            @if($wh['is_default'])
                                            <span class="ml-1 text-[10px] text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded border border-blue-200">Default Hub</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 px-3 text-gray-500">{{ $wh['code'] }}</td>
                                        <td class="py-2.5 px-3 text-right text-gray-700">{{ $wh['on_hand'] }}</td>
                                        <td class="py-2.5 px-3 text-right text-amber-700">{{ $wh['reserved'] }}</td>
                                        <td class="py-2.5 px-3 text-right font-bold {{ $wh['available'] > 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                            {{ $wh['available'] }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-3">Recent Stock Movements & Receiving Log</h3>
                        @if(empty($this->recentMovements))
                        <div class="text-xs text-gray-500 italic p-4 bg-gray-50 rounded-lg border">
                            No stock movements recorded yet for this product.
                        </div>
                        @else
                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="w-full text-left text-xs divide-y divide-gray-200">
                                <thead class="bg-gray-50 text-gray-600 uppercase font-semibold">
                                    <tr>
                                        <th class="py-2.5 px-3">Movement #</th>
                                        <th class="py-2.5 px-3">Date</th>
                                        <th class="py-2.5 px-3">Warehouse</th>
                                        <th class="py-2.5 px-3">Type</th>
                                        <th class="py-2.5 px-3 text-right">Delta</th>
                                        <th class="py-2.5 px-3 text-right">Before → After</th>
                                        <th class="py-2.5 px-3">Reason / Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 font-mono text-[11px]">
                                    @foreach($this->recentMovements as $mv)
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 px-3 font-bold text-gray-800">{{ $mv['movement_number'] }}</td>
                                        <td class="py-2 px-3 text-gray-500 font-sans">{{ \Carbon\Carbon::parse($mv['created_at'])->format('d M Y, H:i') }}</td>
                                        <td class="py-2 px-3 font-sans">{{ $mv['warehouse']['name'] ?? 'Warehouse' }}</td>
                                        <td class="py-2 px-3 font-sans">
                                            <span class="px-1.5 py-0.5 rounded text-[10px] uppercase font-semibold bg-gray-100 text-gray-800">
                                                {{ str_replace('_', ' ', $mv['movement_type']) }}
                                            </span>
                                        </td>
                                        <td class="py-2 px-3 text-right font-bold {{ $mv['quantity'] > 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                            {{ $mv['quantity'] > 0 ? '+' . $mv['quantity'] : $mv['quantity'] }}
                                        </td>
                                        <td class="py-2 px-3 text-right text-gray-600">
                                            {{ $mv['quantity_before'] }} → {{ $mv['quantity_after'] }}
                                        </td>
                                        <td class="py-2 px-3 text-gray-600 font-sans truncate max-w-xs">{{ $mv['notes'] ?? $mv['reason'] }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                <!-- TAB 4: PRICING & MARGINS -->
                @if($this->activeTab === 'pricing')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gray-50 p-5 rounded-xl border border-gray-200 space-y-4">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Nepal Pricing Architecture (NPR)</h3>
                        <dl class="divide-y divide-gray-200 text-xs font-mono">
                            <div class="py-2.5 flex justify-between">
                                <span class="font-sans text-gray-600">Primary Retail Price:</span>
                                <span class="font-bold text-gray-900 text-sm">Rs. {{ number_format($sellingPrice, 2) }}</span>
                            </div>
                            <div class="py-2.5 flex justify-between">
                                <span class="font-sans text-gray-600">Compare-At / MRP Was Price:</span>
                                <span class="text-gray-600">{{ $product->compare_at_price ? 'Rs. ' . number_format((float)$product->compare_at_price, 2) : 'Not configured' }}</span>
                            </div>
                            <div class="py-2.5 flex justify-between">
                                <span class="font-sans text-gray-600">Wholesale / B2B Price:</span>
                                <span class="text-gray-600">{{ $product->wholesale_price ? 'Rs. ' . number_format((float)$product->wholesale_price, 2) : 'Not configured' }}</span>
                            </div>
                            @if($canViewCost)
                            <div class="py-2.5 flex justify-between">
                                <span class="font-sans text-gray-600">Landed Sourcing Cost:</span>
                                <span class="font-bold text-gray-800">Rs. {{ number_format($costPrice, 2) }}</span>
                            </div>
                            <div class="py-2.5 flex justify-between">
                                <span class="font-sans text-gray-600">Gross Margin (Rs.):</span>
                                <span class="font-bold text-emerald-700">+Rs. {{ number_format($margin, 2) }}</span>
                            </div>
                            <div class="py-2.5 flex justify-between">
                                <span class="font-sans text-gray-600">Margin Percentage:</span>
                                <span class="font-bold text-emerald-700">{{ $marginPct }}%</span>
                            </div>
                            @endif
                        </dl>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Tax & Compliance Configuration</h3>
                        <div class="p-4 bg-white rounded-xl border border-gray-200 text-xs space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Tax Regime:</span>
                                <span class="font-bold text-gray-800">Inland Revenue Department (Nepal)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">VAT Status:</span>
                                <span class="font-bold text-blue-700">{{ $product->tax_class === 'exempt' ? 'Exempt / Zero-Rated' : 'Standard 13% VAT' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Tax Accounting:</span>
                                <span class="font-bold text-gray-800">Price Inclusive (Standard Retail)</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- TAB 5: SALES PERFORMANCE -->
                @if($this->activeTab === 'sales')
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <span class="text-xs text-gray-500 uppercase font-semibold">Total Units Sold</span>
                        <div class="text-2xl font-bold font-mono text-gray-900 mt-1">{{ $sales['total_units'] }} units</div>
                        <div class="text-xs text-gray-500 mt-1">Last sold: {{ $sales['last_sold'] }}</div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <span class="text-xs text-gray-500 uppercase font-semibold">Total Product Revenue</span>
                        <div class="text-2xl font-bold font-mono text-emerald-700 mt-1">Rs. {{ number_format($sales['total_revenue'], 2) }}</div>
                        <div class="text-xs text-gray-500 mt-1">Avg selling price: Rs. {{ number_format($sales['avg_selling_price'], 2) }}</div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <span class="text-xs text-gray-500 uppercase font-semibold">Channel Split</span>
                        <div class="mt-2 space-y-1 text-xs">
                            <div class="flex justify-between">
                                <span>Showroom POS:</span>
                                <strong class="font-mono">{{ $sales['pos_units'] }} units (Rs. {{ number_format($sales['pos_revenue'], 0) }})</strong>
                            </div>
                            <div class="flex justify-between">
                                <span>Online Storefront:</span>
                                <strong class="font-mono">{{ $sales['online_units'] }} units (Rs. {{ number_format($sales['online_revenue'], 0) }})</strong>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- TAB 6: ACTIVITY HISTORY -->
                @if($this->activeTab === 'activity')
                <div class="space-y-3 font-mono text-xs">
                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 flex justify-between items-center">
                        <div>
                            <strong class="text-gray-900 font-sans">Product Created</strong>
                            <div class="text-gray-500 text-[11px] font-sans">Initial creation with SKU: {{ $product->sku ?? 'N/A' }}</div>
                        </div>
                        <span class="text-gray-400 text-[11px]">{{ $product->created_at?->format('d M Y, h:i A') }}</span>
                    </div>

                    @if($product->updated_at && $product->updated_at != $product->created_at)
                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 flex justify-between items-center">
                        <div>
                            <strong class="text-gray-900 font-sans">Product Updated</strong>
                            <div class="text-gray-500 text-[11px] font-sans">Metadata / Pricing update saved</div>
                        </div>
                        <span class="text-gray-400 text-[11px]">{{ $product->updated_at?->format('d M Y, h:i A') }}</span>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>

    </div>
</x-filament-panels::page>