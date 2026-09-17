<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice - {{ $order->order_number }}</title>
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
            line-height: 1.5;
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
            background: #064e3b;
            color: #ffffff;
            border-color: #064e3b;
        }

        .invoice-box {
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
            align-items: flex-start;
            border-bottom: 2px solid #064e3b;
            padding-bottom: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .brand-title {
            font-size: 24px;
            font-weight: 800;
            color: #064e3b;
            letter-spacing: -0.025em;
        }

        .brand-sub {
            color: #64748b;
            font-size: 12px;
            margin-top: 2px;
        }

        .invoice-title {
            text-align: right;
        }

        .invoice-title h2 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-nepal {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 4px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 1.5rem;
            background: #f8fafc;
            padding: 1rem 1.25rem;
            border-radius: 0.375rem;
            border: 1px solid #f1f5f9;
        }

        .details-col h4 {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 0.35rem;
            letter-spacing: 0.05em;
        }

        .meta-list {
            list-style: none;
        }

        .meta-list li {
            margin-bottom: 0.2rem;
            display: flex;
            justify-content: space-between;
        }

        .meta-label {
            color: #64748b;
        }

        .meta-val {
            font-weight: 600;
            color: #0f172a;
            text-align: right;
        }

        .table-invoice {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }

        .table-invoice th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 700;
            text-align: left;
            padding: 0.65rem 0.75rem;
            font-size: 11px;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e1;
        }

        .table-invoice td {
            padding: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .table-invoice tr:nth-child(even) td {
            background: #fafafa;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .item-specs {
            font-size: 11px;
            color: #64748b;
            margin-top: 3px;
        }

        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 2rem;
        }

        .totals-table {
            width: 300px;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 0.35rem 0.5rem;
            font-size: 12px;
        }

        .totals-table .total-row td {
            border-top: 2px solid #064e3b;
            font-size: 14px;
            font-weight: 800;
            color: #064e3b;
            padding-top: 0.5rem;
        }

        .footer-notice {
            border-top: 1px dashed #cbd5e1;
            padding-top: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            color: #64748b;
            font-size: 11px;
        }

        .signatory-box {
            text-align: center;
            width: 180px;
        }

        .sign-line {
            border-top: 1px solid #94a3b8;
            margin-top: 2.5rem;
            padding-top: 0.25rem;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .actions-bar {
                display: none;
            }

            .invoice-box {
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
            <button onclick="window.print()" class="btn btn-primary">&#128438; Print Nepal Tax Invoice</button>
        </div>
    </div>

    <div class="invoice-box">
        <div class="header-row">
            <div>
                <h1 class="brand-title">LAIJAU</h1>
                <div class="brand-sub">Delta Nine business group</div>
                <div style="font-size: 11px; color: #475569; margin-top: 4px;">
                    VAT No: <strong>604335148</strong><br>
                    Bohara Tol, Kageshwori Manahara 09, Kathmandu, Nepal | Tel: 9843512095<br>
                    Email: info@laijau.com | Web: laijau.com
                </div>
            </div>
            <div class="invoice-title">
                <h2>Retail Tax Invoice</h2>
                <div class="badge-nepal">कर बिजक / Tax Compliant</div>
                <div style="margin-top: 0.5rem; font-size: 13px; font-weight: 700; color: #064e3b;">
                    Invoice #{{ $order->order_number }}
                </div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                    Date: {{ $order->created_at->timezone('Asia/Kathmandu')->format('M d, Y h:i A') }} (NPT)
                </div>
            </div>
        </div>

        <div class="details-grid">
            <div class="details-col">
                <h4>Billed & Delivered To</h4>
                <div style="font-weight: 700; font-size: 14px; color: #0f172a;">
                    {{ $order->first_name }} {{ $order->last_name }}
                </div>
                <div style="margin-top: 4px; color: #334155;">
                    Phone: <strong>{{ $order->phone }}</strong>
                    @if($order->alt_phone) / {{ $order->alt_phone }} @endif
                </div>
                @if($order->email)
                <div style="color: #64748b;">Email: {{ $order->email }}</div>
                @endif
                <div style="margin-top: 4px; color: #475569; line-height: 1.4;">
                    {{ $order->full_address }}
                </div>
                @if($order->landmark)
                <div style="font-size: 11px; color: #064e3b; font-weight: 600; margin-top: 2px;">
                    Landmark: Near {{ $order->landmark }}
                </div>
                @endif
            </div>

            <div class="details-col">
                <h4>Order & Fulfillment Meta</h4>
                <ul class="meta-list">
                    <li>
                        <span class="meta-label">Channel:</span>
                        <span class="meta-val">{{ ucfirst($order->channel ?? 'Online') }}</span>
                    </li>
                    <li>
                        <span class="meta-label">Payment Method:</span>
                        <span class="meta-val">{{ strtoupper($order->payment_method ?? 'COD') }}</span>
                    </li>
                    <li>
                        <span class="meta-label">Payment Status:</span>
                        <span class="meta-val">{{ ucfirst($order->payment_status ?? 'Unpaid') }}</span>
                    </li>
                    <li>
                        <span class="meta-label">Delivery Zone:</span>
                        <span class="meta-val">{{ $order->is_inside_valley ? 'Kathmandu Valley' : 'Outside Valley' }}</span>
                    </li>
                    @if($order->carrier)
                    <li>
                        <span class="meta-label">Courier:</span>
                        <span class="meta-val">{{ $order->carrier }}</span>
                    </li>
                    @endif
                    @if($order->tracking_number)
                    <li>
                        <span class="meta-label">AWB / Tracking:</span>
                        <span class="meta-val">{{ $order->tracking_number }}</span>
                    </li>
                    @endif
                </ul>
            </div>
        </div>

        <table class="table-invoice">
            <thead>
                <tr>
                    <th style="width: 40px;" class="text-center">S.N.</th>
                    <th>Item Description</th>
                    <th style="width: 100px;">SKU</th>
                    <th style="width: 60px;" class="text-center">Qty</th>
                    <th style="width: 110px;" class="text-right">Rate (NPR)</th>
                    <th style="width: 120px;" class="text-right">Amount (NPR)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->items as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <strong style="color: #0f172a;">{{ $item->product_name }}</strong>
                        <div class="item-specs">
                            @if($item->selected_color) Color: {{ $item->selected_color }} @endif
                            @if($item->selected_size) | Size: {{ $item->selected_size }} @endif
                            @if($item->is_preorder) <span style="color: #d97706; font-weight: 600;">[Pre-Order]</span> @endif
                        </div>
                    </td>
                    <td style="font-family: monospace; font-size: 11px; color: #475569;">
                        {{ $item->sku ?: '—' }}
                    </td>
                    <td class="text-center font-weight-bold">{{ $item->quantity }}</td>
                    <td class="text-right">{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float)$item->unit_price, 'Rs. ', 2) }}</td>
                    <td class="text-right font-weight-bold">
                        {{ \App\Helpers\NepaliNumberHelper::formatCurrency((float)($item->unit_price * $item->quantity), 'Rs. ', 2) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center" style="color: #94a3b8; padding: 2rem;">
                        No line items recorded for this order.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="meta-label">Subtotal:</td>
                    <td class="text-right font-weight-bold">{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float)$order->subtotal, 'Rs. ', 2) }}</td>
                </tr>
                @if((float)$order->coupon_discount > 0)
                <tr>
                    <td class="meta-label" style="color: #dc2626;">Discount:</td>
                    <td class="text-right" style="color: #dc2626;">- {{ \App\Helpers\NepaliNumberHelper::formatCurrency((float)$order->coupon_discount, 'Rs. ', 2) }}</td>
                </tr>
                @endif
                <tr>
                    <td class="meta-label">Shipping Fee:</td>
                    <td class="text-right">
                        @if((float)$order->shipping_fee > 0)
                        {{ \App\Helpers\NepaliNumberHelper::formatCurrency((float)$order->shipping_fee, 'Rs. ', 2) }}
                        @else
                        <span style="color: #059669; font-weight: 600;">FREE</span>
                        @endif
                    </td>
                </tr>
                @if((float)$order->vat_amount > 0)
                <tr>
                    <td class="meta-label">VAT (13%):</td>
                    <td class="text-right">{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float)$order->vat_amount, 'Rs. ', 2) }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td>Grand Total:</td>
                    <td class="text-right">{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float)$order->total_amount, 'Rs. ', 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="footer-notice">
            <div>
                <strong>Terms & Conditions:</strong><br>
                • 7-day exchange only. No returns.<br>
                • Cash on Delivery orders require physical cash or instant digital payment upon handover.<br>
                • For inquiries or logistics support, WhatsApp: 9843512095 | Email: info@laijau.com.
            </div>
            <div class="signatory-box">
                <div class="sign-line">Authorized Signatory</div>
                <div style="font-size: 10px; color: #94a3b8; margin-top: 2px;">Laijau Retail ERP</div>
            </div>
        </div>
    </div>

</body>

</html>