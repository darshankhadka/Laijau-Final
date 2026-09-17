<x-filament-panels::page>
    @php
    $stockLevel = $this->record;
    $stockLevel->loadMissing(['product.categories', 'variant', 'warehouse']);
    $product = $stockLevel->product;
    $variant = $stockLevel->variant;

    // Calculate distribution across all active warehouses
    $warehouses = \App\Models\Inventory\Warehouse::where('is_active', true)->get();
    $distribution = $warehouses->map(function ($wh) use ($stockLevel) {
    $lvl = \App\Models\Inventory\StockLevel::where('warehouse_id', $wh->id)
    ->where('product_id', $stockLevel->product_id)
    ->when($stockLevel->variant_id, fn($q) => $q->where('variant_id', $stockLevel->variant_id))
    ->when(!$stockLevel->variant_id, fn($q) => $q->whereNull('variant_id'))
    ->first();
    return [
    'warehouse' => $wh,
    'level' => $lvl,
    'on_hand' => $lvl?->quantity_on_hand ?? 0,
    'reserved' => $lvl?->quantity_reserved ?? 0,
    'available' => $lvl?->available_quantity ?? 0,
    'reorder_point' => $lvl?->reorder_point ?? 3,
    ];
    });

    // Fetch recent movements for this product & variant
    $movements = \App\Models\Inventory\StockMovement::with(['warehouse', 'targetWarehouse', 'user'])
    ->where('product_id', $stockLevel->product_id)
    ->when($stockLevel->variant_id, fn($q) => $q->where('variant_id', $stockLevel->variant_id))
    ->when(!$stockLevel->variant_id, fn($q) => $q->whereNull('variant_id'))
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();

    $imgUrl = null;
    if ($variant && !empty($variant->image_url)) {
    $imgUrl = \App\Helpers\StorefrontHelper::getImageUrl($variant->image_url);
    } elseif ($product) {
    $imgUrl = \App\Helpers\StorefrontHelper::getProductPrimaryImage($product);
    }
    @endphp

    {{-- SELF-CONTAINED EMBEDDED STYLES FOR STOCK DETAIL WORKSPACE --}}
    <style>
        .lj-stk-page {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            box-sizing: border-box;
        }

        .lj-stk-page * {
            box-sizing: border-box;
        }

        .lj-stk-page svg {
            width: 15px !important;
            height: 15px !important;
            min-width: 15px !important;
            max-width: 15px !important;
            flex-shrink: 0 !important;
            display: inline-block !important;
            vertical-align: middle !important;
        }

        .lj-stk-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .lj-stk-card-p5 {
            padding: 1.25rem;
        }

        .lj-stk-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .lj-stk-thumb {
            width: 64px;
            height: 64px;
            min-width: 64px;
            max-width: 64px;
            border-radius: 0.5rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .lj-stk-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .lj-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 600;
            line-height: 1.2;
        }

        .lj-kpi-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
        }

        @media (max-width: 1100px) {
            .lj-kpi-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 640px) {
            .lj-kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .lj-kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .lj-kpi-label {
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }

        .lj-kpi-val {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            margin-top: 0.25rem;
            line-height: 1.2;
        }

        .lj-kpi-sub {
            font-size: 0.6875rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .lj-stk-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
        }

        .lj-table {
            width: 100%;
            text-align: left;
            font-size: 0.75rem;
            border-collapse: collapse;
        }

        .lj-table th {
            padding: 0.625rem 0.875rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
        }

        .lj-table td {
            padding: 0.75rem 0.875rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .lj-table tr:hover td {
            background: #f8fafc;
        }

        .lj-table tr:last-child td {
            border-bottom: none;
        }

        .lj-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            background: #f8fafc;
        }

        .lj-card-title {
            font-size: 0.875rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
    </style>

    <div class="lj-stk-page">
        {{-- Product Identity & Location Strip --}}
        <div class="lj-stk-header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="lj-stk-thumb">
                    @if($imgUrl)
                    <img src="{{ $imgUrl }}" alt="{{ $product?->name }}">
                    @else
                    <svg style="width: 24px !important; height: 24px !important; color: #cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    @endif
                </div>
                <div>
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.625rem;">
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
                            {{ $product?->name }}
                        </h2>

                        {{-- Warehouse Badge --}}
                        <span class="lj-badge" style="background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe;">
                            📍 {{ $stockLevel->warehouse?->name ?: 'Warehouse' }}
                        </span>

                        {{-- Status Badge --}}
                        @php
                        $stName = $stockLevel->quantity_on_hand <= 0 ? 'Out of Stock' : ($stockLevel->isLowStock() ? 'Low Stock' : ($stockLevel->isOverStock() ? 'Overstocked' : 'In Stock'));
                            $stStyle = match($stName) {
                            'Out of Stock' => 'background: #ffe4e6; color: #9f1239; border: 1px solid #fecdd3;',
                            'Low Stock' => 'background: #fef3c7; color: #92400e; border: 1px solid #fde68a;',
                            'Overstocked' => 'background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;',
                            default => 'background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;',
                            };
                            @endphp
                            <span class="lj-badge" style="{{ $stStyle }}">
                                {{ $stName }}
                            </span>
                    </div>

                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-top: 0.35rem; font-size: 0.75rem; color: #64748b;">
                        <span style="font-family: monospace; font-weight: 700; color: #334155;">
                            SKU: {{ $variant?->sku ?: ($product?->sku ?: '—') }}
                        </span>
                        @if($variant?->barcode ?: $product?->barcode)
                        <span>•</span>
                        <span style="font-family: monospace; color: #475569;">
                            Barcode: {{ $variant?->barcode ?: $product?->barcode }}
                        </span>
                        @endif
                        @if($variant)
                        <span>•</span>
                        <span style="color: #475569;">
                            Spec: {{ implode(' / ', array_filter([$variant->color, $variant->size])) ?: 'Standard' }}
                        </span>
                        @endif
                        @if($stockLevel->bin_location)
                        <span>•</span>
                        <span style="color: #047857; font-weight: 600;">
                            Shelf: {{ $stockLevel->bin_location }}
                        </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- 4 Core Stock KPI Cards --}}
        <div class="lj-kpi-grid">
            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Physical On Hand</div>
                <div class="lj-kpi-val" style="{{ $stockLevel->quantity_on_hand <= 0 ? 'color: #e11d48;' : ($stockLevel->isLowStock() ? 'color: #d97706;' : 'color: #047857;') }}">
                    {{ $stockLevel->quantity_on_hand }} <span style="font-size: 0.875rem; font-weight: 500; color: #64748b;">pcs</span>
                </div>
                <div class="lj-kpi-sub">Total physical units at this location</div>
            </div>

            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Reserved Units</div>
                <div class="lj-kpi-val" style="color: #475569;">
                    {{ $stockLevel->quantity_reserved }} <span style="font-size: 0.875rem; font-weight: 500; color: #64748b;">pcs</span>
                </div>
                <div class="lj-kpi-sub">Allocated to pending orders / holds</div>
            </div>

            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Available to Sell</div>
                <div class="lj-kpi-val" style="{{ $stockLevel->available_quantity <= 0 ? 'color: #e11d48;' : 'color: #047857;' }}">
                    {{ $stockLevel->available_quantity }} <span style="font-size: 0.875rem; font-weight: 500; color: #64748b;">pcs</span>
                </div>
                <div class="lj-kpi-sub">Net stock free for checkout</div>
            </div>

            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Incoming Units</div>
                <div class="lj-kpi-val" style="color: #2563eb;">
                    {{ (int)$stockLevel->quantity_incoming }} <span style="font-size: 0.875rem; font-weight: 500; color: #64748b;">pcs</span>
                </div>
                <div class="lj-kpi-sub">Units on open purchase orders</div>
            </div>

            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Reorder Point</div>
                <div class="lj-kpi-val">
                    {{ $stockLevel->reorder_point }} <span style="font-size: 0.875rem; font-weight: 500; color: #64748b;">pcs</span>
                </div>
                <div class="lj-kpi-sub">Trigger threshold (Safety: {{ $stockLevel->safety_stock }} pcs)</div>
            </div>
        </div>

        {{-- Multi-Warehouse Distribution Table --}}
        <div class="lj-stk-card">
            <div class="lj-card-header">
                <div class="lj-card-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    <span>Omnichannel Multi-Warehouse Distribution</span>
                </div>
                <span style="font-size: 0.6875rem; color: #64748b;">Live inventory balance across retail network</span>
            </div>

            <div style="overflow-x: auto;">
                <table class="lj-table">
                    <thead>
                        <tr>
                            <th>Warehouse Location</th>
                            <th>Type</th>
                            <th style="text-align: right;">Physical On Hand</th>
                            <th style="text-align: right;">Reserved</th>
                            <th style="text-align: right;">Available to Sell</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($distribution as $dist)
                        @php
                        $wh = $dist['warehouse'];
                        $isCurrent = ($wh->id === $stockLevel->warehouse_id);
                        @endphp
                        <tr style="{{ $isCurrent ? 'background: #f8fafc;' : '' }}">
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-family: monospace; font-weight: 700; color: #0f172a;">{{ $wh->code }}</span>
                                    <span style="color: #475569;">— {{ $wh->name }}</span>
                                    @if($isCurrent)
                                    <span class="lj-badge" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;">Viewing</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="lj-badge" style="background: #f1f5f9; color: #475569;">
                                    {{ ucfirst(str_replace('_', ' ', $wh->type)) }}
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: #0f172a;">
                                {{ $dist['on_hand'] }}
                            </td>
                            <td style="text-align: right; color: #64748b;">
                                {{ $dist['reserved'] }}
                            </td>
                            <td style="text-align: right; font-weight: 800; {{ $dist['available'] <= 0 ? 'color: #e11d48;' : 'color: #047857;' }}">
                                {{ $dist['available'] }}
                            </td>
                            <td>
                                @php
                                $statusLabel = $dist['on_hand'] <= 0 ? 'Out of Stock' : ($dist['on_hand'] <=$dist['reorder_point'] ? 'Low Stock' : 'Healthy' );
                                    $badgeBg=match($statusLabel) { 'Out of Stock'=> 'background: #ffe4e6; color: #9f1239;',
                                    'Low Stock' => 'background: #fef3c7; color: #92400e;',
                                    default => 'background: #ecfdf5; color: #065f46;',
                                    };
                                    @endphp
                                    <span class="lj-badge" style="{{ $badgeBg }}">
                                        {{ $statusLabel }}
                                    </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Movement Audit Ledger --}}
        <div class="lj-stk-card">
            <div class="lj-card-header">
                <div class="lj-card-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Audited Stock Movement Ledger</span>
                </div>
                <span style="font-size: 0.6875rem; color: #64748b;">Nepal Time (NPT) · Immutable double-entry records</span>
            </div>

            <div style="overflow-x: auto;">
                <table class="lj-table">
                    <thead>
                        <tr>
                            <th>Movement #</th>
                            <th>Timestamp</th>
                            <th>Movement Type</th>
                            <th style="text-align: right;">Delta Qty</th>
                            <th>Warehouse / Target</th>
                            <th>Staff Attribution</th>
                            <th>Reference / Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $mov)
                        <tr>
                            <td style="font-family: monospace; font-weight: 700; color: #0f172a;">
                                {{ $mov->movement_number }}
                            </td>
                            <td style="color: #64748b; white-space: nowrap;">
                                {{ $mov->created_at->timezone('Asia/Kathmandu')->format('M d, Y · H:i') }}
                            </td>
                            <td>
                                <span class="lj-badge" style="background: #f1f5f9; color: #334155;">
                                    {{ ucfirst(str_replace('_', ' ', $mov->movement_type)) }}
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 800; font-family: monospace; {{ $mov->quantity > 0 ? 'color: #047857;' : 'color: #e11d48;' }}">
                                {{ $mov->quantity > 0 ? "+{$mov->quantity}" : (string)$mov->quantity }}
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #0f172a;">{{ $mov->warehouse?->name }}</div>
                                @if($mov->targetWarehouse)
                                <div style="font-size: 0.6875rem; color: #2563eb;">&rarr; {{ $mov->targetWarehouse->name }}</div>
                                @endif
                            </td>
                            <td>
                                <span style="font-weight: 500; color: #334155;">{{ $mov->user?->name ?: 'System Engine' }}</span>
                            </td>
                            <td>
                                <div style="color: #0f172a;">{{ $mov->reason ?: ($mov->notes ?: '—') }}</div>
                                @if($mov->reference_number)
                                <div style="font-size: 0.6875rem; font-family: monospace; color: #64748b;">Ref: {{ $mov->reference_number }}</div>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" style="padding: 2rem; text-align: center; color: #94a3b8;">
                                No stock movements recorded yet for this item.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>