@php
    /** @var \App\Models\Inventory\PurchaseOrder $record */
    $record->loadMissing([
        'supplier',
        'warehouse',
        'items.product.categories',
        'items.variant',
        'createdByUser',
        'approvedByUser',
    ]);

    $totalOrdered = (int)$record->items->sum('quantity_ordered');
    $totalReceived = (int)$record->items->sum('quantity_received');
    $totalRemaining = max(0, $totalOrdered - $totalReceived);
    $receivePct = $totalOrdered > 0 ? min(100, round(($totalReceived / $totalOrdered) * 100)) : 0;

    $printUrl = route('admin.purchase-orders.print', ['purchaseOrder' => $record->id]);
    $viewUrl = \App\Filament\Resources\PurchaseOrderResource::getUrl('view', ['record' => $record->id]);

    $statusColor = match($record->status) {
        'received' => 'background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;',
        'partially_received' => 'background: #fef3c7; color: #92400e; border: 1px solid #fde68a;',
        'ordered', 'in_transit' => 'background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;',
        'approved' => 'background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe;',
        'cancelled', 'rejected' => 'background: #ffe4e6; color: #9f1239; border: 1px solid #fecdd3;',
        default => 'background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;',
    };
@endphp

<div class="lj-po-inspect-wrap" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: var(--gray-900, #0f172a); display: flex; flex-direction: column; gap: 1.25rem;">
    <style>
        .lj-po-inspect-wrap {
            font-size: 0.875rem;
            line-height: 1.45;
        }
        .lj-po-card {
            background: var(--gray-50, #f8fafc);
            border: 1px solid var(--gray-200, #e2e8f0);
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
        }
        .dark .lj-po-card {
            background: rgba(30, 41, 59, 0.4);
            border-color: #334155;
            color: #f1f5f9;
        }
        .lj-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 700;
            line-height: 1.2;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .lj-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }
        .lj-table th {
            text-align: left;
            padding: 0.65rem 0.75rem;
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-500, #64748b);
            border-bottom: 1px solid var(--gray-200, #e2e8f0);
            font-weight: 700;
        }
        .lj-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--gray-100, #f1f5f9);
            vertical-align: middle;
        }
        .dark .lj-table th {
            border-color: #334155;
            color: #94a3b8;
        }
        .dark .lj-table td {
            border-color: #1e293b;
        }
        .lj-kpi-val {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .lj-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.15s ease;
            cursor: pointer;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #0f172a;
        }
        .lj-btn:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }
        .lj-btn-primary {
            background: #0f3770;
            color: #ffffff;
            border-color: #0c2b57;
        }
        .lj-btn-primary:hover {
            background: #0c2b57;
        }
    </style>

    {{-- Top Header Strip --}}
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 1px solid var(--gray-200, #e2e8f0); padding-bottom: 1rem;">
        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <span style="font-size: 1.25rem; font-weight: 800; letter-spacing: -0.02em;">
                    PO #<span style="font-family: ui-monospace, monospace; color: #0f3770;">{{ $record->po_number }}</span>
                </span>
                <span class="lj-badge" style="{{ $statusColor }}">
                    {{ ucfirst(str_replace('_', ' ', (string)$record->status)) }}
                </span>
            </div>
            <div style="font-size: 0.75rem; color: var(--gray-500, #64748b);">
                Order Date: <strong>{{ $record->order_date ? $record->order_date->format('M d, Y') : $record->created_at->format('M d, Y') }}</strong>
                @if($record->expected_delivery_date)
                    · Expected: <strong>{{ $record->expected_delivery_date->format('M d, Y') }}</strong>
                @endif
                · Warehouse: <strong>{{ $record->warehouse?->name ?: 'Central Hub' }}</strong>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <a href="{{ $printUrl }}" target="_blank" class="lj-btn lj-btn-primary">
                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Print PO Voucher</span>
            </a>
            <a href="{{ $viewUrl }}" class="lj-btn">
                <span>View Full Dossier →</span>
            </a>
        </div>
    </div>

    {{-- 4 Core KPI Summary Cards --}}
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.85rem;">
        <div class="lj-po-card" style="padding: 0.85rem 1rem;">
            <div style="font-size: 0.6875rem; text-transform: uppercase; font-weight: 700; color: var(--gray-500, #64748b); margin-bottom: 0.25rem;">
                Total Landed Value
            </div>
            <div class="lj-kpi-val" style="color: #047857;">
                Rs. {{ number_format((float)$record->total_amount_npr, 2) }}
            </div>
            <div style="font-size: 0.6875rem; color: var(--gray-400, #94a3b8); margin-top: 0.2rem;">
                Procurement commitment
            </div>
        </div>

        <div class="lj-po-card" style="padding: 0.85rem 1rem;">
            <div style="font-size: 0.6875rem; text-transform: uppercase; font-weight: 700; color: var(--gray-500, #64748b); margin-bottom: 0.25rem;">
                Units Contracted
            </div>
            <div class="lj-kpi-val" style="color: #0f172a;">
                {{ $totalOrdered }} <span style="font-size: 0.8125rem; font-weight: 500; color: #64748b;">pcs</span>
            </div>
            <div style="font-size: 0.6875rem; color: var(--gray-400, #94a3b8); margin-top: 0.2rem;">
                Across {{ $record->items->count() }} line items
            </div>
        </div>

        <div class="lj-po-card" style="padding: 0.85rem 1rem;">
            <div style="font-size: 0.6875rem; text-transform: uppercase; font-weight: 700; color: var(--gray-500, #64748b); margin-bottom: 0.25rem;">
                Units Received
            </div>
            <div class="lj-kpi-val" style="color: #2563eb;">
                {{ $totalReceived }} <span style="font-size: 0.8125rem; font-weight: 500; color: #64748b;">pcs</span>
            </div>
            <div style="margin-top: 0.35rem; width: 100%; background: #e2e8f0; border-radius: 9999px; height: 5px; overflow: hidden;">
                <div style="background: #2563eb; height: 100%; width: {{ $receivePct }}%;"></div>
            </div>
            <div style="font-size: 0.6875rem; color: var(--gray-500, #64748b); margin-top: 0.25rem;">
                {{ $receivePct }}% physically verified
            </div>
        </div>

        <div class="lj-po-card" style="padding: 0.85rem 1rem;">
            <div style="font-size: 0.6875rem; text-transform: uppercase; font-weight: 700; color: var(--gray-500, #64748b); margin-bottom: 0.25rem;">
                Remaining Inbound
            </div>
            <div class="lj-kpi-val" style="{{ $totalRemaining > 0 ? 'color: #d97706;' : 'color: #047857;' }}">
                {{ $totalRemaining }} <span style="font-size: 0.8125rem; font-weight: 500; color: #64748b;">pcs</span>
            </div>
            <div style="font-size: 0.6875rem; color: var(--gray-400, #94a3b8); margin-top: 0.2rem;">
                {{ $totalRemaining > 0 ? 'Pending warehouse receipt' : 'Order fully received' }}
            </div>
        </div>
    </div>

    {{-- Supplier & Destination Warehouse Dossier Grid --}}
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        {{-- Supplier Card --}}
        <div class="lj-po-card">
            <div style="font-size: 0.6875rem; text-transform: uppercase; font-weight: 700; color: var(--gray-500, #64748b); margin-bottom: 0.35rem;">
                Supplier / Artisan Guild
            </div>
            <div style="font-size: 1rem; font-weight: 800; color: #0f172a;">
                {{ $record->supplier?->name ?: 'Vendor / Artisan Guild' }}
            </div>
            <div style="font-size: 0.75rem; color: var(--gray-600, #475569); margin-top: 0.25rem;">
                Contact Person: <strong>{{ $record->supplier?->contact_person ?: '—' }}</strong>
            </div>
            <div style="display: flex; gap: 1rem; font-size: 0.75rem; color: var(--gray-500, #64748b); margin-top: 0.25rem;">
                @if($record->supplier?->phone)
                    <span>📞 {{ $record->supplier->phone }}</span>
                @endif
                @if($record->supplier?->email)
                    <span>✉️ {{ $record->supplier->email }}</span>
                @endif
            </div>
            <div style="font-size: 0.75rem; color: var(--gray-500, #64748b); margin-top: 0.25rem;">
                Payment Terms: <strong>{{ $record->supplier?->payment_terms ?: 'Net 30' }}</strong>
                @if($record->supplier?->pan_number)
                    · PAN: <strong>{{ $record->supplier->pan_number }}</strong>
                @endif
            </div>
        </div>

        {{-- Destination Warehouse Card --}}
        <div class="lj-po-card">
            <div style="font-size: 0.6875rem; text-transform: uppercase; font-weight: 700; color: var(--gray-500, #64748b); margin-bottom: 0.35rem;">
                Receiving Destination Warehouse
            </div>
            <div style="font-size: 1rem; font-weight: 800; color: #0f172a;">
                {{ $record->warehouse?->name ?: 'Warehouse' }}
            </div>
            <div style="font-size: 0.75rem; font-family: monospace; color: var(--gray-600, #475569); margin-top: 0.25rem;">
                Warehouse Code: <strong>{{ $record->warehouse?->code ?: '—' }}</strong>
            </div>
            <div style="font-size: 0.75rem; color: var(--gray-500, #64748b); margin-top: 0.25rem;">
                📍 {{ $record->warehouse?->address ?: ($record->warehouse?->city ?: 'Kathmandu') }}
            </div>
            @if($record->warehouse?->manager_name)
            <div style="font-size: 0.75rem; color: #047857; font-weight: 600; margin-top: 0.25rem;">
                Receiving Manager: {{ $record->warehouse->manager_name }}
            </div>
            @endif
        </div>
    </div>

    {{-- Line Items Table --}}
    <div class="lj-po-card" style="padding: 0;">
        <div style="padding: 0.75rem 1.25rem; border-bottom: 1px solid var(--gray-200, #e2e8f0); display: flex; align-items: center; justify-content: space-between;">
            <div style="font-size: 0.8125rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #0f172a;">
                Procurement Items & Receiving Status
            </div>
            <span style="font-size: 0.75rem; color: var(--gray-500, #64748b);">
                {{ $record->items->count() }} line items
            </span>
        </div>

        <div style="overflow-x: auto;">
            <table class="lj-table">
                <thead>
                    <tr>
                        <th style="width: 32px;">#</th>
                        <th>Product & Specification</th>
                        <th>SKU</th>
                        <th style="text-align: right;">Unit Rate</th>
                        <th style="text-align: center;">Ordered</th>
                        <th style="text-align: center;">Received</th>
                        <th style="text-align: center;">Remaining</th>
                        <th style="text-align: right;">Line Total</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($record->items as $idx => $item)
                    @php
                        $prod = $item->product;
                        $var = $item->variant;
                        $spec = $var ? implode(' / ', array_filter([$var->color, $var->size])) : 'Standard';
                        $lineTotal = (float)($item->total_cost_npr ?: ($item->quantity_ordered * $item->unit_cost_npr));
                        $rem = max(0, (int)$item->quantity_ordered - (int)$item->quantity_received);
                    @endphp
                    <tr>
                        <td style="color: var(--gray-400, #94a3b8); font-size: 0.75rem;">{{ $idx + 1 }}</td>
                        <td>
                            <div style="font-weight: 700; color: #0f172a;">{{ $prod?->name ?: 'Product Item' }}</div>
                            <div style="font-size: 0.6875rem; color: var(--gray-500, #64748b);">
                                Spec: <strong>{{ $spec }}</strong>
                                @if($prod?->brand) · {{ $prod->brand }} @endif
                            </div>
                        </td>
                        <td>
                            <code style="font-size: 0.75rem; font-weight: 700;">{{ $var?->sku ?: ($prod?->sku ?: '—') }}</code>
                        </td>
                        <td style="text-align: right; font-family: ui-monospace, monospace; font-size: 0.75rem;">
                            Rs. {{ number_format((float)$item->unit_cost_npr, 2) }}
                        </td>
                        <td style="text-align: center; font-family: ui-monospace, monospace; font-weight: 700;">
                            {{ $item->quantity_ordered }}
                        </td>
                        <td style="text-align: center; font-family: ui-monospace, monospace; font-weight: 700; {{ (int)$item->quantity_received > 0 ? 'color: #047857;' : 'color: #64748b;' }}">
                            {{ (int)$item->quantity_received }}
                        </td>
                        <td style="text-align: center; font-family: ui-monospace, monospace; font-weight: 700; {{ $rem > 0 ? 'color: #d97706;' : 'color: #047857;' }}">
                            {{ $rem }}
                        </td>
                        <td style="text-align: right; font-family: ui-monospace, monospace; font-weight: 700; color: #0f172a;">
                            Rs. {{ number_format($lineTotal, 2) }}
                        </td>
                        <td style="text-align: center;">
                            @if((int)$item->quantity_received >= (int)$item->quantity_ordered)
                                <span class="lj-badge" style="background: #ecfdf5; color: #065f46;">Received</span>
                            @elseif((int)$item->quantity_received > 0)
                                <span class="lj-badge" style="background: #fef3c7; color: #92400e;">Partial</span>
                            @else
                                <span class="lj-badge" style="background: #f1f5f9; color: #64748b;">Pending</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 2rem; color: var(--gray-400, #94a3b8);">
                            No line items found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Bottom Summary & Notes --}}
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1.5rem;">
        <div style="flex: 1; min-width: 280px; font-size: 0.75rem; color: var(--gray-500, #64748b);">
            @if($record->notes)
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem 1rem;">
                    <div style="font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 0.25rem;">Internal & Commercial Notes</div>
                    <div style="color: #0f172a;">{{ $record->notes }}</div>
                </div>
            @endif
            <div style="margin-top: 0.75rem;">
                Created by: <strong>{{ $record->createdByUser?->name ?: 'Procurement Team' }}</strong>
                @if($record->approvedByUser)
                    · Approved by: <strong>{{ $record->approvedByUser->name }}</strong>
                @endif
            </div>
        </div>

        <div style="width: 300px; font-size: 0.8125rem;">
            <div style="display: flex; justify-content: space-between; padding: 0.25rem 0; color: var(--gray-600, #475569);">
                <span>Goods Subtotal:</span>
                <span style="font-family: ui-monospace, monospace; font-weight: 600;">Rs. {{ number_format((float)$record->subtotal_currency ?: (float)$record->total_amount_npr, 2) }}</span>
            </div>
            @if((float)$record->shipping_cost_npr > 0)
            <div style="display: flex; justify-content: space-between; padding: 0.25rem 0; color: var(--gray-600, #475569);">
                <span>Inbound Logistics:</span>
                <span style="font-family: ui-monospace, monospace;">Rs. {{ number_format((float)$record->shipping_cost_npr, 2) }}</span>
            </div>
            @endif
            @if((float)$record->customs_duty_npr > 0)
            <div style="display: flex; justify-content: space-between; padding: 0.25rem 0; color: var(--gray-600, #475569);">
                <span>Customs Duty:</span>
                <span style="font-family: ui-monospace, monospace;">Rs. {{ number_format((float)$record->customs_duty_npr, 2) }}</span>
            </div>
            @endif
            <div style="display: flex; justify-content: space-between; border-top: 2px solid #0f3770; margin-top: 0.5rem; padding-top: 0.5rem; font-size: 0.95rem; font-weight: 800; color: #0f172a;">
                <span>Total Landed Amount:</span>
                <span style="font-family: ui-monospace, monospace; color: #0f3770;">Rs. {{ number_format((float)$record->total_amount_npr, 2) }}</span>
            </div>
        </div>
    </div>
</div>
