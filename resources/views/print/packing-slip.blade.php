<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Packing Slip - {{ $order->order_number }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica NNpe", Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 13px;
        }

        .actions-bar {
            width: 100%;
            max-width: 800px;
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
            background: #0284c7;
            color: #ffffff;
            border-color: #0284c7;
        }

        .slip-box {
            background: #ffffff;
            width: 100%;
            max-width: 800px;
            padding: 2.5rem;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .slip-title {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.025em;
        }

        .slip-sub {
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-zone {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
        }

        .shipping-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            display: grid;
            grid-template-columns: 3fr 2fr;
            gap: 1.5rem;
        }

        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        .table-items th {
            background: #0f172a;
            color: #ffffff;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.65rem 0.75rem;
            text-align: left;
        }

        .table-items td {
            padding: 0.85rem 0.75rem;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .checkbox-cell {
            width: 40px;
            text-align: center;
            vertical-align: middle;
        }

        .check-box-square {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid #94a3b8;
            border-radius: 3px;
        }

        .instructions-box {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
            font-size: 12px;
            color: #92400e;
        }

        .signatures-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 2rem;
            margin-top: 3rem;
            padding-top: 1rem;
            border-top: 1px dashed #cbd5e1;
            text-align: center;
            font-size: 12px;
            color: #475569;
        }

        .sign-placeholder {
            border-top: 1px solid #94a3b8;
            margin-top: 2.5rem;
            padding-top: 0.35rem;
            font-weight: 600;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .actions-bar {
                display: none;
            }

            .slip-box {
                border: none;
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
        }
    </style>
</head>

<body>

    <div class="actions-bar">
        <a href="javascript:window.history.back()" class="btn">&larr; Back to Order</a>
        <div>
            <button onclick="window.print()" class="btn btn-primary">&#128438; Print Packing Slip</button>
        </div>
    </div>

    <div class="slip-box">
        <div class="header-row">
            <div>
                <h1 class="slip-title">WAREHOUSE PACKING SLIP</h1>
                <div class="slip-sub">Laijau Central Fulfillment Center & Logistics Hub</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                    Printed at: {{ now()->timezone('Asia/Kathmandu')->format('M d, Y h:i A') }} (NPT)
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 18px; font-weight: 800; color: #0f172a;">
                    {{ $order->order_number }}
                </div>
                <div class="badge-zone" style="margin-top: 4px;">
                    {{ $order->is_inside_valley ? 'VALLEY DISPATCH' : 'OUTSIDE VALLEY (AIR/SURFACE)' }}
                </div>
            </div>
        </div>

        {{-- Internal Document Notice --}}
        <div style="border: 1.5px dashed #475569; background: #f8fafc; color: #1e293b; padding: 0.45rem 0.75rem; border-radius: 0.25rem; text-align: center; font-size: 11px; font-weight: 800; line-height: 1.35; margin-bottom: 1.25rem; text-transform: uppercase; letter-spacing: 0.02em;">
            THIS IS NOT A TAX INVOICE. FOR LAIJAU INTERNAL USE ONLY. PLEASE RETAIN YOUR TAX INVOICE FROM THE COUNTER.
        </div>

        <div class="shipping-card">
            <div>
                <div style="font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase;">Ship / Handover To</div>
                <div style="font-size: 15px; font-weight: 700; color: #0f172a; margin-top: 2px;">
                    {{ $order->first_name }} {{ $order->last_name }}
                </div>
                <div style="font-size: 13px; font-weight: 600; color: #0369a1; margin-top: 2px;">
                    📞 {{ $order->phone }} @if($order->alt_phone) | {{ $order->alt_phone }} @endif
                </div>
                <div style="margin-top: 6px; color: #334155; line-height: 1.4;">
                    {{ $order->full_address }}
                </div>
                @if($order->landmark)
                <div style="font-size: 11px; font-weight: 600; color: #0284c7; margin-top: 2px;">
                    📍 Landmark: Near {{ $order->landmark }}
                </div>
                @endif
            </div>

            <div style="border-left: 1px solid #e2e8f0; padding-left: 1.25rem;">
                <div style="font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase;">Logistics Routing</div>
                <div style="margin-top: 4px;">
                    <strong>Carrier:</strong> {{ $order->carrier ?: 'Nepal Can Move / Pathao' }}
                </div>
                <div style="margin-top: 2px;">
                    <strong>AWB / Tracking:</strong>
                    <span style="font-family: monospace; font-weight: 700; color: #0f172a;">
                        {{ $order->tracking_number ?: 'Pending Generation' }}
                    </span>
                </div>
                <div style="margin-top: 2px;">
                    <strong>Payment Type:</strong>
                    <span style="font-weight: 700; color: {{ $order->payment_method === 'cod' ? '#b45309' : '#059669' }}">
                        {{ strtoupper($order->payment_method ?? 'COD') }}
                        ({{ ucfirst($order->payment_status ?? 'unpaid') }})
                    </span>
                </div>
                @if($order->payment_method === 'cod')
                <div style="margin-top: 4px; background: #fef2f2; border: 1px solid #fee2e2; padding: 4px 6px; border-radius: 4px; font-weight: 700; color: #dc2626; font-size: 11px;">
                    COLLECT CASH: {{ \App\Helpers\NepaliNumberHelper::formatCurrency((float)$order->total_amount, 'Rs. ', 2) }}
                </div>
                @endif
            </div>
        </div>

        @if($order->customer_notes || $order->internal_notes)
        <div class="instructions-box">
            @if($order->customer_notes)
            <div><strong>Customer Note:</strong> {{ $order->customer_notes }}</div>
            @endif
            @if($order->internal_notes)
            <div><strong>Fulfillment Note:</strong> {{ $order->internal_notes }}</div>
            @endif
        </div>
        @endif

        <table class="table-items">
            <thead>
                <tr>
                    <th class="checkbox-cell">Pick</th>
                    <th>Item & Specifications</th>
                    <th style="width: 140px;">SKU</th>
                    <th style="width: 80px; text-align: center;">Qty</th>
                    <th class="checkbox-cell">QC</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->items as $item)
                <tr>
                    <td class="checkbox-cell">
                        <span class="check-box-square"></span>
                    </td>
                    <td>
                        <strong style="color: #0f172a; font-size: 13px;">{{ $item->product_name }}</strong>
                        <div style="color: #64748b; font-size: 11px; margin-top: 2px;">
                            @if($item->selected_color) Color: <strong>{{ $item->selected_color }}</strong> @endif
                            @if($item->selected_size) | Size: <strong>{{ $item->selected_size }}</strong> @endif
                            @if($item->is_preorder) <span style="color: #d97706; font-weight: 700;">[Pre-Order]</span> @endif
                        </div>
                        @if(!empty($item->custom_measurements))
                        <div style="margin-top: 4px; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 11px; color: #334155;">
                            📦 <strong>Custom Specifications:</strong>
                            @foreach($item->custom_measurements as $specKey => $specVal)
                            <span style="margin-right: 8px;">{{ ucfirst(str_replace('_', ' ', $specKey)) }}: <strong>{{ $specVal }}</strong></span>
                            @endforeach
                        </div>
                        @endif
                    </td>
                    <td style="font-family: monospace; font-size: 11px; color: #334155;">
                        {{ $item->sku ?: '—' }}
                    </td>
                    <td style="text-align: center; font-size: 14px; font-weight: 700; color: #0f172a;">
                        {{ $item->quantity }}
                    </td>
                    <td class="checkbox-cell">
                        <span class="check-box-square"></span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #94a3b8; padding: 2rem;">
                        No items in this order.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="signatures-row">
            <div>
                <div>Picked From Racks By</div>
                <div class="sign-placeholder">Picker Signature</div>
            </div>
            <div>
                <div>Quality Inspected & Ironed</div>
                <div class="sign-placeholder">QC Inspector</div>
            </div>
            <div>
                <div>Boxed & Bag Tagged</div>
                <div class="sign-placeholder">Packer Signature</div>
            </div>
        </div>
    </div>

</body>

</html>