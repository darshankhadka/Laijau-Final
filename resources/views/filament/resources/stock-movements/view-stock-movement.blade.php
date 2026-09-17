<x-filament-panels::page>
    @php
    $movement = $this->record;
    $movement->loadMissing(['warehouse', 'targetWarehouse', 'product.categories', 'variant', 'user']);
    $product = $movement->product;
    $variant = $movement->variant;
    $user = $movement->user;

    $primaryImg = $product ? \App\Helpers\StorefrontHelper::getProductPrimaryImage($product) : null;
    $delta = (int)$movement->quantity;
    $isPositive = $delta > 0;

    $typeLabels = [
    'opening_stock' => ['label' => 'Opening Stock', 'color' => 'background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;'],
    'purchase_receive' => ['label' => 'Purchase Receiving', 'color' => 'background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;'],
    'sale_order' => ['label' => 'Order Fulfillment', 'color' => 'background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;'],
    'sale_pos' => ['label' => 'POS / Showroom Sale', 'color' => 'background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa;'],
    'return_customer' => ['label' => 'Customer Return', 'color' => 'background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;'],
    'return_supplier' => ['label' => 'Supplier Return', 'color' => 'background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;'],
    'transfer_out' => ['label' => 'Transfer Outbound', 'color' => 'background: #fef3c7; color: #b45309; border: 1px solid #fde68a;'],
    'transfer_in' => ['label' => 'Transfer Inbound', 'color' => 'background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;'],
    'adjustment_gain' => ['label' => 'Adjustment Gain', 'color' => 'background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;'],
    'adjustment_loss' => ['label' => 'Adjustment Loss', 'color' => 'background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;'],
    'damage' => ['label' => 'Damaged Inventory', 'color' => 'background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;'],
    'loss' => ['label' => 'Shrinkage / Loss', 'color' => 'background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;'],
    'correction' => ['label' => 'Count Correction', 'color' => 'background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe;'],
    'count_reconciliation' => ['label' => 'Count Reconciliation', 'color' => 'background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe;'],
    ];

    $typeInfo = $typeLabels[$movement->movement_type] ?? [
    'label' => ucwords(str_replace('_', ' ', $movement->movement_type ?? 'Movement')),
    'color' => 'background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;'
    ];
    @endphp

    <div class="space-y-6 text-left">

        <!-- 1. TRANSACTION HEADER BANNER -->
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="px-3 py-1 rounded-lg text-xs font-black uppercase tracking-wider" style="{{ $typeInfo['color'] }}">
                            {{ $typeInfo['label'] }}
                        </span>
                        <span class="text-xs font-mono text-gray-500">
                            {{ $movement->created_at ? $movement->created_at->format('d M Y, h:i:s A') : '—' }}
                        </span>
                    </div>
                    <h1 class="text-2xl font-black text-gray-900 tracking-tight flex items-center gap-2">
                        <span>{{ $movement->movement_number }}</span>
                    </h1>
                    <p class="text-xs text-gray-500">
                        Authoritative double-entry stock ledger transaction record.
                    </p>
                </div>

                <!-- Quick Action Links -->
                <div class="flex items-center gap-2">
                    @if($product)
                    <a
                        href="/intadmin/products/{{ $product->id }}"
                        class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl border border-gray-200 transition-colors flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>View Product</span>
                    </a>
                    @endif
                    <a
                        href="/intadmin/stock-levels"
                        class="px-3.5 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold rounded-xl border border-blue-200 transition-colors flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Stock Overview</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- 2. FOUR KEY METRIC TILES -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Tile 1: Quantity Change -->
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-xs">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Quantity Impact</span>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-3xl font-black font-mono {{ $isPositive ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $isPositive ? '+' . $delta : $delta }}
                    </span>
                    <span class="text-xs font-bold text-gray-500">pcs</span>
                </div>
                <span class="text-[11px] text-gray-400 mt-1 block">
                    {{ $isPositive ? 'Stock Added to Hub' : 'Stock Deducted from Hub' }}
                </span>
            </div>

            <!-- Tile 2: On-Hand Transition -->
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-xs">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Balance Transition</span>
                <div class="mt-1 flex items-baseline gap-2 font-mono">
                    <span class="text-xl font-bold text-gray-500">{{ $movement->quantity_before ?? '—' }}</span>
                    <span class="text-xs text-gray-400">&rarr;</span>
                    <span class="text-2xl font-black text-gray-900">{{ $movement->quantity_after ?? '—' }}</span>
                </div>
                <span class="text-[11px] text-gray-400 mt-1 block">
                    Physical on-hand balance transition
                </span>
            </div>

            <!-- Tile 3: Available Transition -->
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-xs">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Available Transition</span>
                <div class="mt-1 flex items-baseline gap-2 font-mono">
                    <span class="text-xl font-bold text-gray-500">{{ $movement->available_before ?? '—' }}</span>
                    <span class="text-xs text-gray-400">&rarr;</span>
                    <span class="text-2xl font-black text-blue-600">{{ $movement->available_after ?? '—' }}</span>
                </div>
                <span class="text-[11px] text-gray-400 mt-1 block">
                    Committed & sellable stock impact
                </span>
            </div>

            <!-- Tile 4: Warehouse Hub -->
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-xs">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Fulfillment Location</span>
                <div class="mt-1 font-bold text-gray-900 text-sm truncate">
                    {{ $movement->warehouse->name ?? 'Central Warehouse' }}
                </div>
                <span class="text-[11px] font-mono text-gray-500 mt-0.5 block">
                    Code: {{ $movement->warehouse->code ?? 'WH-01' }}
                </span>
            </div>
        </div>

        <!-- 3. TWO-COLUMN DOSSIER GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- Left Column: Product & Variant Card (7 cols) -->
            <div class="lg:col-span-7 space-y-6">
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <span>Item & Variation Details</span>
                    </h3>

                    @if($product)
                    <div class="flex items-start gap-4">
                        <!-- Product Thumbnail -->
                        <div class="w-20 h-20 rounded-xl overflow-hidden border border-gray-200 bg-gray-50 shrink-0">
                            @if($primaryImg)
                            <img src="{{ $primaryImg }}" alt="{{ $product->name }}" class="w-full h-full object-cover" />
                            @else
                            <div class="w-full h-full flex items-center justify-center text-gray-400 font-bold text-lg bg-gray-100">
                                {{ substr($product->name, 0, 1) }}
                            </div>
                            @endif
                        </div>

                        <!-- Product Attributes -->
                        <div class="space-y-1.5 flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-50 text-blue-700 border border-blue-200">
                                    {{ $product->categories->first()?->name ?? 'General' }}
                                </span>
                                @if($variant)
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-purple-50 text-purple-700 border border-purple-200">
                                    Size: {{ $variant->size ?? 'Standard' }}
                                </span>
                                @endif
                                <span class="text-[11px] font-mono text-gray-500">ID: {{ $product->id }}</span>
                            </div>

                            <a href="/intadmin/products/{{ $product->id }}" class="text-sm font-bold text-gray-900 hover:text-blue-600 transition-colors block truncate">
                                {{ $product->name }}
                            </a>

                            <div class="flex items-center gap-4 text-xs font-mono text-gray-500 flex-wrap">
                                <span>Master SKU: <strong class="text-gray-800">{{ $product->sku ?? 'N/A' }}</strong></span>
                                @if($variant && $variant->sku)
                                <span>Variant SKU: <strong class="text-purple-700">{{ $variant->sku }}</strong></span>
                                @endif
                                @if($product->barcode)
                                <span>Barcode: <strong class="text-gray-800">{{ $product->barcode }}</strong></span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Additional Details Table -->
                    <div class="pt-2 border-t border-gray-100">
                        <dl class="divide-y divide-gray-100 text-xs font-mono">
                            <div class="py-2 flex justify-between">
                                <span class="font-sans text-gray-500">Retail Selling Price:</span>
                                <span class="font-bold text-gray-900">Rs. {{ number_format((float)($variant->price ?? $product->price ?? 0), 2) }}</span>
                            </div>
                            <div class="py-2 flex justify-between">
                                <span class="font-sans text-gray-500">Current Catalog Total Stock:</span>
                                <span class="font-bold text-gray-900">{{ $product->quantity }} pcs</span>
                            </div>
                            @if($movement->unit_cost_npr)
                            <div class="py-2 flex justify-between">
                                <span class="font-sans text-gray-500">Recorded Unit Cost:</span>
                                <span class="font-bold text-gray-800">Rs. {{ number_format((float)$movement->unit_cost_npr, 2) }}</span>
                            </div>
                            @endif
                        </dl>
                    </div>
                    @else
                    <div class="text-xs text-gray-500 italic p-4 bg-gray-50 rounded-xl border">
                        Product record ID #{{ $movement->product_id }} has been archived or unlinked.
                    </div>
                    @endif
                </div>

                <!-- Reason & Notes Card -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-3">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                        <span>Reason, Reference & Notes</span>
                    </h3>

                    <div class="space-y-2 text-xs">
                        <div>
                            <span class="text-gray-500 font-semibold block mb-0.5">Primary Reason / Trigger:</span>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-200 text-gray-800 font-medium leading-relaxed">
                                {{ $movement->reason ?: 'Standard stock ledger transaction.' }}
                            </div>
                        </div>

                        @if($movement->notes)
                        <div>
                            <span class="text-gray-500 font-semibold block mb-0.5">Internal Remarks / Staff Notes:</span>
                            <div class="p-3 bg-blue-50/50 rounded-xl border border-blue-100 text-blue-900 font-medium leading-relaxed">
                                {{ $movement->notes }}
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column: Context, Reference & Audit (5 cols) -->
            <div class="lg:col-span-5 space-y-6">

                <!-- Linked Document / Source Reference -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-3">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Source Document</span>
                    </h3>

                    <dl class="divide-y divide-gray-100 text-xs font-mono">
                        <div class="py-2 flex justify-between">
                            <span class="font-sans text-gray-500">Reference Type:</span>
                            <span class="font-bold text-gray-800 font-sans uppercase">{{ $movement->reference_type ?: 'Manual' }}</span>
                        </div>
                        @if($movement->reference_number)
                        <div class="py-2 flex justify-between items-center">
                            <span class="font-sans text-gray-500">Document #:</span>
                            <span class="font-bold text-blue-600">
                                @if($movement->reference_type === 'order' && $movement->reference_id)
                                <a href="/intadmin/orders/{{ $movement->reference_id }}" class="hover:underline">
                                    #{{ $movement->reference_number }} &rarr;
                                </a>
                                @elseif($movement->reference_type === 'purchase_order' && $movement->reference_id)
                                <a href="/intadmin/purchase-orders/{{ $movement->reference_id }}" class="hover:underline">
                                    #{{ $movement->reference_number }} &rarr;
                                </a>
                                @else
                                #{{ $movement->reference_number }}
                                @endif
                            </span>
                        </div>
                        @endif
                        @if($movement->reference_id)
                        <div class="py-2 flex justify-between">
                            <span class="font-sans text-gray-500">Reference Record ID:</span>
                            <span class="text-gray-700">#{{ $movement->reference_id }}</span>
                        </div>
                        @endif
                        @if($movement->journal_entry_id)
                        <div class="py-2 flex justify-between items-center">
                            <span class="font-sans text-gray-500">Journal Entry:</span>
                            <a href="/intadmin/journal-entries/{{ $movement->journal_entry_id }}" class="font-bold text-blue-600 hover:underline">
                                JV #{{ $movement->journal_entry_id }} &rarr;
                            </a>
                        </div>
                        @endif
                    </dl>
                </div>

                <!-- Warehouse Location Details -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-3">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span>Warehouse Hub Details</span>
                    </h3>

                    @if($movement->warehouse)
                    <div class="space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Hub Name:</span>
                            <span class="font-bold text-gray-900">{{ $movement->warehouse->name }}</span>
                        </div>
                        <div class="flex items-center justify-between font-mono">
                            <span class="text-gray-500 font-sans">Identifier:</span>
                            <span class="font-bold text-gray-700">{{ $movement->warehouse->code }}</span>
                        </div>
                        @if($movement->warehouse->address)
                        <div class="text-gray-600 pt-1 border-t border-gray-100 leading-snug">
                            <span class="text-gray-400 block text-[10px] uppercase font-bold">Address:</span>
                            {{ $movement->warehouse->address }}
                        </div>
                        @endif
                    </div>
                    @endif

                    @if($movement->targetWarehouse)
                    <div class="mt-3 p-3 bg-purple-50 rounded-xl border border-purple-100 text-xs">
                        <span class="text-purple-700 font-bold uppercase tracking-wider text-[10px] block">Destination Hub:</span>
                        <div class="font-bold text-purple-900 mt-0.5">{{ $movement->targetWarehouse->name }}</div>
                        <div class="text-purple-600 font-mono text-[11px]">{{ $movement->targetWarehouse->code }}</div>
                    </div>
                    @endif
                </div>

                <!-- Operator & Audit Context -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-3">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Audit & Operator</span>
                    </h3>

                    <dl class="divide-y divide-gray-100 text-xs font-mono">
                        <div class="py-2 flex justify-between items-center">
                            <span class="font-sans text-gray-500">Performed By:</span>
                            <span class="font-bold text-gray-900 font-sans">
                                {{ $user ? $user->name : 'System Automated / Migration' }}
                            </span>
                        </div>
                        @if($user && $user->email)
                        <div class="py-2 flex justify-between">
                            <span class="font-sans text-gray-500">Operator Email:</span>
                            <span class="text-gray-600">{{ $user->email }}</span>
                        </div>
                        @endif
                        <div class="py-2 flex justify-between">
                            <span class="font-sans text-gray-500">Timestamp:</span>
                            <span class="text-gray-600">{{ $movement->created_at?->format('d M Y, h:i:s A') }}</span>
                        </div>
                    </dl>
                </div>

            </div>

        </div>

    </div>
</x-filament-panels::page>