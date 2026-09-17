<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $order->order_number ?: ('#' . $order->id) }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica NNpe", Arial, sans-serif;
            background: #f4f4f5;
            color: #18181b;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .receipt-card {
            background: #ffffff;
            width: 100%;
            max-width: 380px;
            padding: 2rem 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid #e4e4e7;
        }

        .actions-bar {
            width: 100%;
            max-width: 380px;
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }

        .btn {
            flex: 1;
            padding: 0.65rem 1rem;
            font-size: 0.8125rem;
            font-weight: 600;
            border-radius: 0.5rem;
            cursor: pointer;
            border: 1px solid #d4d4d8;
            background: #ffffff;
            color: #18181b;
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background: #1e3a8a;
            color: #ffffff;
            border-color: #1e3a8a;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .brand-header {
            text-align: center;
            border-bottom: 1px dashed #d4d4d8;
            padding-bottom: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .brand-logo {
            max-height: 2.25rem;
            margin-bottom: 0.5rem;
        }

        .brand-sub {
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #71717a;
        }

        .brand-address {
            font-size: 0.75rem;
            color: #71717a;
            margin-top: 0.25rem;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: #71717a;
            margin-bottom: 0.35rem;
        }

        .meta-row strong {
            color: #18181b;
        }

        .items-table {
            width: 100%;
            margin: 1.25rem 0;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }

        .items-table th {
            text-align: left;
            font-size: 0.6875rem;
            text-transform: uppercase;
            color: #71717a;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e4e4e7;
        }

        .items-table td {
            padding: 0.5rem 0;
            vertical-align: top;
            border-bottom: 1px solid #f4f4f5;
        }

        .item-name {
            font-weight: 600;
            color: #18181b;
        }

        .item-variant {
            font-size: 0.6875rem;
            color: #71717a;
        }

        .totals-section {
            border-top: 1px dashed #d4d4d8;
            padding-top: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            font-size: 0.8125rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            color: #71717a;
        }

        .total-row.grand {
            font-size: 1.125rem;
            font-weight: 700;
            color: #0A2E23;
            border-top: 1px solid #e4e4e7;
            padding-top: 0.5rem;
            margin-top: 0.25rem;
        }

        .footer-note {
            text-align: center;
            font-size: 0.75rem;
            color: #71717a;
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px dashed #d4d4d8;
            line-height: 1.4;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .receipt-card {
                box-shadow: none;
                border: none;
                max-width: 100%;
                padding: 0;
            }
        }
    </style>
</head>

<body>

    <div class="actions-bar no-print">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Print Receipt</button>
        <button onclick="window.print()" class="btn">📥 Save PDF</button>
        @if(!empty($order->phone) && $order->phone !== '—')
        @php
        $cleanPhone = preg_replace('/[^0-9]/', '', $order->phone);
        $curr = 'Rs. ';
        $waMsg = "🛍️ *LAIJAU.COM — SALES RECEIPT* 🛍️\n\nOrder #" . ($order->order_number ?: $order->id) . "\nTotal: " . \App\Helpers\NepaliNumberHelper::formatCurrency($order->total_amount, 'Rs. ', 2) . "\nView online: " . url('/orders/' . $order->id . '/receipt');
        @endphp
        <a href="https://wa.me/{{ $cleanPhone }}?text={{ urlencode($waMsg) }}" target="_blank" class="btn" style="background: #25D366; color: #ffffff; border-color: #25D366;">
            💬 WhatsApp
        </a>
        @endif
        <button onclick="window.close()" class="btn">✕ Close</button>
    </div>

    <div class="receipt-card">
        {{-- Header --}}
        <div class="brand-header">
            <div style="font-size: 1.5rem; font-weight: 900; letter-spacing: 0.05em; color: #1e3a8a;">LAIJAU</div>
            <div class="brand-sub">Delta Nine business group</div>
            <div class="brand-address" style="font-weight: 600; color: #18181b;">VAT No: 604335148</div>
            <div class="brand-address">Bohara Tol, Kageshwori Manahara 09, Kathmandu, Nepal</div>
            <div class="brand-address">WhatsApp: 9843512095 &bull; info@laijau.com &bull; laijau.com</div>
        </div>

        {{-- Meta info --}}
        <div class="meta-row">
            <span>Receipt #:</span>
            <strong>#{{ $order->order_number ?: ('#' . $order->id) }}</strong>
        </div>
        <div class="meta-row">
            <span>Date & Time:</span>
            <span>{{ $order->created_at ? $order->created_at->format('d M Y, H:i') : now()->format('d M Y, H:i') }}</span>
        </div>
        <div class="meta-row">
            <span>Customer:</span>
            <strong>{{ $order->first_name }} {{ $order->last_name }}</strong>
        </div>
        @if(!empty($order->phone) && $order->phone !== '—')
        <div class="meta-row">
            <span>Phone / WhatsApp:</span>
            <span>{{ $order->phone }}</span>
        </div>
        @endif
        @php
        $pm = strtolower((string)$order->payment_method);
        $pmLabel = match($pm) {
            'fonepay', 'esewa' => 'Digital Payment (eSewa / Fonepay)',
            'khalti' => 'Digital Payment (Khalti QR)',
            'bank_transfer' => 'Bank Transfer / Fonepay',
            'card' => 'Card Terminal',
            'cash', 'cod' => 'Cash',
            default => ucfirst(str_replace('_', ' ', $pm)),
        };
        @endphp
        <div class="meta-row">
            <span>Payment Method:</span>
            <strong>{{ $pmLabel }}</strong>
        </div>
        <div class="meta-row">
            <span>Payment Status:</span>
            <strong style="color: #059669;">✓ {{ ucfirst((string)$order->payment_status) }}</strong>
        </div>

        {{-- Items Table --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Price</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td>
                        <div class="item-name">{{ $item->product_name }}</div>
                        @if(!empty($item->selected_color) || !empty($item->selected_size))
                        <div class="item-variant">
                            {{ $item->selected_color ?? '' }} @if($item->selected_size) · {{ $item->selected_size }} @endif
                        </div>
                        @endif
                    </td>
                    <td style="text-align: center; font-family: monospace;">{{ $item->quantity }}</td>
                    <td style="text-align: right; font-family: monospace;">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($item->unit_price, 'Rs. ', 2) }}</td>
                    <td style="text-align: right; font-family: monospace; font-weight: 600;">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($item->unit_price * $item->quantity, 'Rs. ', 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals Breakdown --}}
        <div class="totals-section">
            <div class="total-row">
                <span>Subtotal</span>
                <span style="font-family: monospace;">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($order->subtotal, 'Rs. ', 2) }}</span>
            </div>

            @if($order->coupon_discount > 0)
            <div class="total-row" style="color: #dc2626;">
                <span>Discount</span>
                <span style="font-family: monospace;">-{{ \App\Helpers\NepaliNumberHelper::formatCurrency($order->coupon_discount, 'Rs. ', 2) }}</span>
            </div>
            @endif

            @if($order->shipping_fee > 0)
            <div class="total-row">
                <span>Delivery / Courier</span>
                <span style="font-family: monospace;">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($order->shipping_fee, 'Rs. ', 2) }}</span>
            </div>
            @endif

            @if($order->vat_amount > 0)
            <div class="total-row">
                <span>VAT (13% included)</span>
                <span style="font-family: monospace;">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($order->vat_amount, 'Rs. ', 2) }}</span>
            </div>
            @endif

            <div class="total-row grand">
                <span>TOTAL</span>
                <span style="font-family: monospace;">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($order->total_amount, 'Rs. ', 2) }}</span>
            </div>
        </div>

        {{-- Footer message --}}
        <div class="footer-note">
            Thank you for shopping at Laijau!<br />
            7-day exchange only. No returns.<br />
            <strong>info@laijau.com &bull; 9843512095</strong>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('print') || urlParams.has('autoprint') || urlParams.has('download')) {
                setTimeout(() => {
                    window.print();
                }, 300);
            }
        });
    </script>
</body>

</html>