<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - {{ $purchaseOrder->po_number }} | Laijau</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 13px;
            line-height: 1.5;
        }

        .actions-bar {
            width: 100%;
            max-width: 820px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
            font-weight: 600;
            border-radius: 0.375rem;
            cursor: pointer;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #0f172a;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-primary {
            background: #0f3770;
            color: #ffffff;
            border-color: #0c2b57;
        }

        .po-box {
            background: #ffffff;
            width: 100%;
            max-width: 820px;
            padding: 2.5rem;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f3770;
            padding-bottom: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .brand-title {
            font-size: 24px;
            font-weight: 800;
            color: #0f3770;
            letter-spacing: -0.025em;
        }

        .brand-sub {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .po-badge-title {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .po-num {
            font-family: monospace;
            font-size: 14px;
            font-weight: 700;
            color: #0f3770;
            text-align: right;
            margin-top: 2px;
        }

        .status-pill {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 4px;
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .grid-parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 1.75rem;
            background: #f8fafc;
            padding: 1.25rem;
            border-radius: 0.5rem;
            border: 1px solid #f1f5f9;
        }

        .party-title {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.35rem;
        }

        .party-name {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }

        .party-detail {
            font-size: 12px;
            color: #475569;
            margin-top: 0.2rem;
        }

        .dates-bar {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
            font-size: 12px;
        }

        .dates-item strong {
            color: #0f172a;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }

        .table th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.05em;
            padding: 0.75rem 0.85rem;
            border-bottom: 1px solid #cbd5e1;
            text-align: left;
        }

        .table td {
            padding: 0.75rem 0.85rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 12.5px;
            vertical-align: middle;
        }

        .table tr:last-child td {
            border-bottom: 2px solid #cbd5e1;
        }

        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 2rem;
        }

        .totals-box {
            width: 320px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 0.35rem 0;
            font-size: 12.5px;
            color: #475569;
        }

        .totals-row.grand-total {
            border-top: 2px solid #0f3770;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
        }

        .signatures-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 3.5rem;
            padding-top: 1rem;
        }

        .signature-box {
            border-top: 1px dashed #94a3b8;
            padding-top: 0.5rem;
            text-align: center;
        }

        .signature-title {
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
        }

        .signature-subtitle {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 2px;
        }

        .footer-note {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            font-size: 11px;
            color: #64748b;
            text-align: center;
            line-height: 1.4;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .actions-bar {
                display: none;
            }

            .po-box {
                border: none;
                box-shadow: none;
                max-width: 100%;
                padding: 1.5cm;
            }
        }
    </style>
</head>

<body>
    <div class="actions-bar">
        <a href="javascript:history.back()" class="btn">
            ← Back to Admin
        </a>
        <div style="display: flex; gap: 0.5rem;">
            <button onclick="window.print()" class="btn btn-primary">
                🖨️ Print Purchase Order
            </button>
        </div>
    </div>

    <div class="po-box">
        <!-- Header -->
        <div class="header-row">
            <div>
                <div class="brand-title">LAIJAU ENTERPRISES</div>
                <div class="brand-sub">Footwear & Apparel Procurement Division • Kathmandu, Nepal</div>
                <div style="font-size: 11.5px; color: #475569; margin-top: 0.4rem;">
                    PAN/VAT: <strong>610293847</strong> • Tel: +977 1 4589201<br>
                    Central Hub: Putalisadak, Kathmandu
                </div>
            </div>
            <div>
                <div class="po-badge-title">Purchase Order</div>
                <div class="po-num">{{ $purchaseOrder->po_number }}</div>
                <div style="text-align: right;">
                    <span class="status-pill">{{ ucfirst(str_replace('_', ' ', $purchaseOrder->status)) }}</span>
                </div>
            </div>
        </div>

        <!-- Supplier & Delivery Destination -->
        <div class="grid-parties">
            <div>
                <div class="party-title">Supplier / Vendor:</div>
                <div class="party-name">{{ $purchaseOrder->supplier?->name ?: 'Vendor / Artisan Guild' }}</div>
                @if($purchaseOrder->supplier?->contact_person)
                <div class="party-detail">Attn: <strong>{{ $purchaseOrder->supplier->contact_person }}</strong></div>
                @endif
                @if($purchaseOrder->supplier?->phone)
                <div class="party-detail">Phone: {{ $purchaseOrder->supplier->phone }}</div>
                @endif
                @if($purchaseOrder->supplier?->email)
                <div class="party-detail">Email: {{ $purchaseOrder->supplier->email }}</div>
                @endif
                @if($purchaseOrder->supplier?->pan_number)
                <div class="party-detail">VAT/PAN: {{ $purchaseOrder->supplier->pan_number }}</div>
                @endif
                <div class="party-detail">Payment Terms: <strong>{{ $purchaseOrder->supplier?->payment_terms ?: 'Net 30' }}</strong></div>
            </div>

            <div>
                <div class="party-title">Receiving Destination:</div>
                <div class="party-name">{{ $purchaseOrder->warehouse?->name ?: 'Central Warehouse' }}</div>
                <div class="party-detail">Warehouse Code: <strong>{{ $purchaseOrder->warehouse?->code }}</strong></div>
                <div class="party-detail">Address: {{ $purchaseOrder->warehouse?->address ?: ($purchaseOrder->warehouse?->city ?: 'Kathmandu') }}</div>
                @if($purchaseOrder->warehouse?->manager_name)
                <div class="party-detail">Receiving Officer: <strong>{{ $purchaseOrder->warehouse->manager_name }}</strong></div>
                @endif
                @if($purchaseOrder->warehouse?->phone)
                <div class="party-detail">Dock Phone: {{ $purchaseOrder->warehouse->phone }}</div>
                @endif
            </div>
        </div>

        <!-- Dates & Currency Strip -->
        <div class="dates-bar">
            <div class="dates-item">Order Date: <strong>{{ $purchaseOrder->order_date ? $purchaseOrder->order_date->format('M d, Y') : $purchaseOrder->created_at->format('M d, Y') }}</strong></div>
            <div class="dates-item">Expected Delivery: <strong>{{ $purchaseOrder->expected_delivery_date ? $purchaseOrder->expected_delivery_date->format('M d, Y') : 'Immediate' }}</strong></div>
            <div class="dates-item">Currency: <strong>NPR (Nepalese Rupee)</strong></div>
            <div class="dates-item">Lines: <strong>{{ $purchaseOrder->items->count() }} items</strong></div>
        </div>

        <!-- Line Items Table -->
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">#</th>
                    <th>Product Description</th>
                    <th>Variant / Specification</th>
                    <th>SKU / Code</th>
                    <th style="text-align: right;">Rate (Rs.)</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Total (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                @php $subtotal = 0; @endphp
                @forelse($purchaseOrder->items as $idx => $item)
                @php
                    $prod = $item->product;
                    $var = $item->variant;
                    $lineTotal = (float)($item->total_cost_npr ?: ($item->quantity_ordered * $item->unit_cost_npr));
                    $subtotal += $lineTotal;
                @endphp
                <tr>
                    <td style="text-align: center; color: #94a3b8;">{{ $idx + 1 }}</td>
                    <td>
                        <strong style="color: #0f172a;">{{ $prod?->name ?: 'Item' }}</strong>
                        @if($prod?->brand)
                        <div style="font-size: 11px; color: #64748b;">Brand: {{ $prod->brand }}</div>
                        @endif
                    </td>
                    <td>
                        @if($var)
                            {{ implode(' / ', array_filter([$var->color, $var->size])) ?: 'Standard' }}
                        @else
                            Standard
                        @endif
                    </td>
                    <td><code style="font-size: 11px; font-weight: 700;">{{ $var?->sku ?: ($prod?->sku ?: '—') }}</code></td>
                    <td style="text-align: right; font-family: monospace;">Rs. {{ number_format((float)$item->unit_cost_npr, 2) }}</td>
                    <td style="text-align: center; font-weight: 700;">{{ $item->quantity_ordered }}</td>
                    <td style="text-align: right; font-family: monospace; font-weight: 700;">Rs. {{ number_format($lineTotal, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem; color: #94a3b8;">No line items on this purchase order.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Totals Breakdown -->
        <div class="totals-section">
            <div class="totals-box">
                <div class="totals-row">
                    <span>Goods Subtotal:</span>
                    <span style="font-family: monospace; font-weight: 600;">Rs. {{ number_format($subtotal, 2) }}</span>
                </div>
                @if((float)$purchaseOrder->shipping_cost_npr > 0)
                <div class="totals-row">
                    <span>Freight / Inbound Logistics:</span>
                    <span style="font-family: monospace;">Rs. {{ number_format((float)$purchaseOrder->shipping_cost_npr, 2) }}</span>
                </div>
                @endif
                @if((float)$purchaseOrder->customs_duty_npr > 0)
                <div class="totals-row">
                    <span>Customs Duty & Import Tariff:</span>
                    <span style="font-family: monospace;">Rs. {{ number_format((float)$purchaseOrder->customs_duty_npr, 2) }}</span>
                </div>
                @endif
                <div class="totals-row grand-total">
                    <span>Total Procurement Amount:</span>
                    <span style="font-family: monospace; color: #0f3770;">Rs. {{ number_format((float)$purchaseOrder->total_amount_npr ?: $subtotal, 2) }}</span>
                </div>
            </div>
        </div>

        @if($purchaseOrder->notes)
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.375rem; padding: 0.75rem 1rem; margin-bottom: 2rem; font-size: 11.5px; color: #475569;">
            <strong>Special Instructions / Terms:</strong> {{ $purchaseOrder->notes }}
        </div>
        @endif

        <!-- Signatures & Authorization -->
        <div class="signatures-grid">
            <div class="signature-box">
                <div class="signature-title">{{ $purchaseOrder->createdByUser?->name ?: 'Purchasing Officer' }}</div>
                <div class="signature-subtitle">Prepared By</div>
            </div>
            <div class="signature-box">
                <div class="signature-title">{{ $purchaseOrder->approvedByUser?->name ?: 'Operations Director' }}</div>
                <div class="signature-subtitle">Authorized Approval</div>
            </div>
            <div class="signature-box">
                <div class="signature-title">Authorized Representative</div>
                <div class="signature-subtitle">Supplier Acceptance</div>
            </div>
        </div>

        <div class="footer-note">
            This is an official procurement order issued by Laijau Enterprises. All goods must match specified SKU codes and pass physical quality inspection at destination warehouse before payment clearing.
        </div>
    </div>
</body>

</html>
