<x-filament-panels::page>
    @php
    $po = $this->record;
    $po->loadMissing([
    'supplier',
    'warehouse',
    'items.product.categories',
    'items.variant',
    'createdByUser',
    'approvedByUser',
    ]);

    $totalOrdered = (int)$po->items->sum('quantity_ordered');
    $totalReceived = (int)$po->items->sum('quantity_received');
    $totalRemaining = max(0, $totalOrdered - $totalReceived);

    $statusColor = match($po->status) {
    'received' => 'background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;',
    'partially_received' => 'background: #fef3c7; color: #92400e; border: 1px solid #fde68a;',
    'ordered', 'in_transit' => 'background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;',
    'approved' => 'background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe;',
    'cancelled', 'rejected' => 'background: #ffe4e6; color: #9f1239; border: 1px solid #fecdd3;',
    default => 'background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;',
    };
    @endphp

    <style>
        .lj-po-page {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            color: #0f172a;
        }

        .lj-po-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .lj-po-header {
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

        .lj-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 700;
            line-height: 1.2;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .lj-dossier-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        @media (max-width: 768px) {
            .lj-dossier-grid {
                grid-template-columns: 1fr;
            }
        }

        .lj-dossier-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem 1.25rem;
        }

        .lj-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        @media (max-width: 900px) {
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
            margin-bottom: 0.25rem;
        }

        .lj-kpi-val {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            font-family: monospace;
            line-height: 1.1;
        }

        .lj-kpi-sub {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 0.25rem;
        }

        .lj-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }

        .lj-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.6875rem;
            letter-spacing: 0.05em;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        .lj-table td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .lj-table tr:hover {
            background: #fcfcfc;
        }
    </style>

    <div class="lj-po-page">
        {{-- Header Summary Strip --}}
        <div class="lj-po-header">
            <div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; font-family: monospace;">
                        {{ $po->po_number }}
                    </h2>
                    <span class="lj-badge" style="{{ $statusColor }}">
                        {{ ucfirst(str_replace('_', ' ', $po->status)) }}
                    </span>
                </div>
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-top: 0.35rem; font-size: 0.75rem; color: #64748b;">
                    <span>Order Date: <strong>{{ $po->order_date ? $po->order_date->format('M d, Y') : $po->created_at->format('M d, Y') }}</strong></span>
                    @if($po->expected_delivery_date)
                    <span>•</span>
                    <span>Expected Delivery: <strong>{{ $po->expected_delivery_date->format('M d, Y') }}</strong></span>
                    @endif
                    @if($po->received_date)
                    <span>•</span>
                    <span>Received: <strong>{{ $po->received_date->format('M d, Y') }}</strong></span>
                    @endif
                </div>
            </div>

            <div style="font-size: 0.75rem; text-align: right; color: #64748b;">
                @if($po->notes)
                <div style="max-width: 350px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                    📝 {{ $po->notes }}
                </div>
                @endif
            </div>
        </div>

        {{-- Supplier & Destination Warehouse Dossier Cards --}}
        <div class="lj-dossier-grid">
            {{-- Supplier Card --}}
            <div class="lj-po-card lj-dossier-box">
                <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.35rem;">
                    SUPPLIER / VENDOR
                </div>
                <div style="font-size: 1.05rem; font-weight: 800; color: #0f172a;">
                    {{ $po->supplier?->name ?: 'Vendor' }}
                </div>
                <div style="font-size: 0.75rem; color: #475569; margin-top: 0.25rem;">
                    Contact: <strong>{{ $po->supplier?->contact_person ?: '—' }}</strong>
                </div>
                <div style="display: flex; gap: 1rem; font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
                    @if($po->supplier?->phone)
                    <span>📞 {{ $po->supplier->phone }}</span>
                    @endif
                    @if($po->supplier?->email)
                    <span>✉️ {{ $po->supplier->email }}</span>
                    @endif
                </div>
                <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
                    Payment Terms: <strong>{{ $po->supplier?->payment_terms ?: 'Net 30' }}</strong> · Currency: <strong>NPR / Rs.</strong>
                </div>
            </div>

            {{-- Destination Warehouse Card --}}
            <div class="lj-po-card lj-dossier-box">
                <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.35rem;">
                    RECEIVING DESTINATION
                </div>
                <div style="font-size: 1.05rem; font-weight: 800; color: #0f172a;">
                    {{ $po->warehouse?->name ?: 'Warehouse' }}
                </div>
                <div style="font-size: 0.75rem; font-family: monospace; color: #475569; margin-top: 0.25rem;">
                    Code: {{ $po->warehouse?->code }}
                </div>
                <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
                    📍 {{ $po->warehouse?->address ?: $po->warehouse?->city }}
                </div>
                <div style="font-size: 0.75rem; color: #047857; font-weight: 600; margin-top: 0.25rem;">
                    Manager: {{ $po->warehouse?->manager_name ?: 'Store Operations' }}
                </div>
            </div>
        </div>

        {{-- 4 Core Procurement KPI Cards --}}
        <div class="lj-kpi-grid">
            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Total PO Value</div>
                <div class="lj-kpi-val" style="color: #047857;">
                    Rs. {{ number_format((float)$po->total_amount_npr, 2) }}
                </div>
                <div class="lj-kpi-sub">Total procurement commitment</div>
            </div>

            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Units Ordered</div>
                <div class="lj-kpi-val">{{ $totalOrdered }} <span style="font-size: 0.875rem; font-weight: 500; color: #64748b;">pcs</span></div>
                <div class="lj-kpi-sub">Total contracted units</div>
            </div>

            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Units Received</div>
                <div class="lj-kpi-val" style="color: #2563eb;">{{ $totalReceived }} <span style="font-size: 0.875rem; font-weight: 500; color: #64748b;">pcs</span></div>
                <div class="lj-kpi-sub">Physically verified on hand</div>
            </div>

            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Remaining Inbound</div>
                <div class="lj-kpi-val" style="{{ $totalRemaining > 0 ? 'color: #d97706;' : 'color: #64748b;' }}">
                    {{ $totalRemaining }} <span style="font-size: 0.875rem; font-weight: 500; color: #64748b;">pcs</span>
                </div>
                <div class="lj-kpi-sub">{{ $totalRemaining > 0 ? 'Pending receipt at warehouse' : 'All items fully received' }}</div>
            </div>
        </div>

        {{-- Purchase Order Items Table --}}
        <div class="lj-po-card">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
                <div style="font-size: 0.875rem; font-weight: 800; color: #0f172a;">
                    Procurement Line Items & Receiving Progress
                </div>
                <span style="font-size: 0.75rem; color: #64748b;">
                    {{ $po->items->count() }} line items
                </span>
            </div>

            <div style="overflow-x: auto;">
                <table class="lj-table">
                    <thead>
                        <tr>
                            <th>Item Description</th>
                            <th>Variant / Spec</th>
                            <th>SKU</th>
                            <th style="text-align: right;">Unit Cost (Rs.)</th>
                            <th style="text-align: right;">Ordered</th>
                            <th style="text-align: right;">Received</th>
                            <th style="text-align: right;">Remaining</th>
                            <th style="text-align: right;">Line Total (Rs.)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($po->items as $item)
                        @php
                        $prod = $item->product;
                        $var = $item->variant;
                        $spec = $var ? implode(' / ', array_filter([$var->color, $var->size])) : 'Standard';
                        $rem = max(0, (int)$item->quantity_ordered - (int)$item->quantity_received);
                        @endphp
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: #0f172a;">{{ $prod?->name ?: 'Product' }}</div>
                                @if($prod?->categories?->first())
                                <div style="font-size: 0.6875rem; color: #64748b;">{{ $prod->categories->first()->name }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="lj-badge" style="background: #f1f5f9; color: #475569;">
                                    {{ $spec ?: 'Standard' }}
                                </span>
                            </td>
                            <td>
                                <span style="font-family: monospace; font-weight: 700; color: #0f172a;">
                                    {{ $var?->sku ?: ($prod?->sku ?: '—') }}
                                </span>
                            </td>
                            <td style="text-align: right; font-family: monospace;">
                                Rs. {{ number_format((float)($item->unit_cost_currency ?: $item->unit_cost_npr), 2) }}
                            </td>
                            <td style="text-align: right; font-family: monospace; font-weight: 700; color: #0f172a;">
                                {{ $item->quantity_ordered }} pcs
                            </td>
                            <td style="text-align: right; font-family: monospace; font-weight: 700; {{ (int)$item->quantity_received > 0 ? 'color: #047857;' : 'color: #64748b;' }}">
                                {{ (int)$item->quantity_received }} pcs
                            </td>
                            <td style="text-align: right; font-family: monospace; font-weight: 700; {{ $rem > 0 ? 'color: #d97706;' : 'color: #047857;' }}">
                                {{ $rem }} pcs
                            </td>
                            <td style="text-align: right; font-family: monospace; font-weight: 700; color: #0f172a;">
                                Rs. {{ number_format((float)(($item->unit_cost_currency ?: $item->unit_cost_npr) * $item->quantity_ordered), 2) }}
                            </td>
                            <td>
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
                            <td colspan="9" style="padding: 2rem; text-align: center; color: #94a3b8;">
                                No line items attached to this purchase order.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Financial Summary Strip --}}
        <div class="lj-po-card" style="padding: 1.25rem;">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
                <div style="font-size: 0.8125rem; color: #64748b;">
                    Created By: <strong>{{ $po->createdByUser?->name ?: 'Procurement Team' }}</strong>
                    @if($po->approvedByUser)
                    · Approved By: <strong>{{ $po->approvedByUser->name }}</strong>
                    @endif
                </div>

                <div style="display: flex; align-items: center; gap: 1.5rem; font-size: 0.875rem;">
                    @if((float)$po->shipping_cost_npr > 0)
                    <div>Shipping: <strong>Rs. {{ number_format((float)$po->shipping_cost_npr, 2) }}</strong></div>
                    @endif
                    @if((float)$po->customs_duty_npr > 0)
                    <div>Customs Duty: <strong>Rs. {{ number_format((float)$po->customs_duty_npr, 2) }}</strong></div>
                    @endif
                    <div style="font-size: 1.05rem; font-weight: 800; color: #047857;">
                        Total Commitment: Rs. {{ number_format((float)$po->total_amount_npr, 2) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>