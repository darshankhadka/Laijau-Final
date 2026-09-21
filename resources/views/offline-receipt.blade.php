<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $sale->sale_number }} - Laijau</title>
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

        .brand-title {
            font-size: 1.35rem;
            font-weight: 900;
            letter-spacing: 0.05em;
            color: #1e3a8a;
            text-transform: uppercase;
        }

        .brand-sub {
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #71717a;
            margin-top: 0.25rem;
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
            margin-top: 0.125rem;
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
            @page {
                size: 80mm auto;
                margin: 0;
            }

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
                width: 80mm !important;
                max-width: 80mm !important;
                padding: 3mm 2mm !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

    @php
    $curr = 'Rs. ';
    @endphp

    <div class="actions-bar no-print">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Print Receipt</button>
        <button onclick="window.print()" class="btn">📥 Save PDF</button>
        @if(!empty($sale->customer_phone))
        @php
        $cleanPhone = preg_replace('/[^0-9]/', '', (string)$sale->customer_phone);
        $waMsg = "🛍️ *LAIJAU — OFFLINE SALES RECEIPT* 🛍️\n\nSale #" . $sale->sale_number . "\nTotal: " . \App\Helpers\NepaliNumberHelper::formatCurrency($sale->total_amount, 'Rs. ', 2) . "\nThank you for shopping at Laijau!";
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
            <div class="brand-title" style="font-size: 1.5rem; font-weight: 900; letter-spacing: 0.05em; color: #1e3a8a; text-transform: uppercase;">LAIJAU</div>
            <div class="brand-sub">Delta Nine business group</div>
            <div class="brand-address" style="font-weight: 600; color: #18181b;">VAT No: 604335148</div>
            <div class="brand-address">Bohara Tol, Kageshwori Manahara 09, Kathmandu, Nepal</div>
            <div class="brand-address">WhatsApp: 9843512095 &bull; info@laijau.com &bull; laijau.com</div>
        </div>

        {{-- Internal Document Notice --}}
        <div class="tax-disclaimer-notice" style="border: 1.5px dashed #000000; background: #fff5f5; color: #18181b; padding: 0.45rem 0.5rem; border-radius: 0.25rem; text-align: center; font-size: 0.6875rem; font-weight: 800; line-height: 1.35; margin-bottom: 1.25rem; text-transform: uppercase; letter-spacing: 0.02em;">
            THIS IS NOT A TAX INVOICE. FOR LAIJAU INTERNAL USE ONLY. PLEASE RETAIN YOUR TAX INVOICE FROM THE COUNTER.
        </div>

        {{-- Meta info --}}
        <div class="meta-row">
            <span>Receipt #:</span>
            <strong>#{{ $sale->sale_number }}</strong>
        </div>
        <div class="meta-row">
            <span>Date & Time:</span>
            <span>{{ $sale->sold_at ? $sale->sold_at->format('d M Y, H:i') : now()->format('d M Y, H:i') }}</span>
        </div>
        <div class="meta-row">
            <span>Customer:</span>
            <strong>{{ $sale->customer_name }}</strong>
        </div>
        @if(!empty($sale->customer_phone))
        <div class="meta-row">
            <span>Phone / WhatsApp:</span>
            <span>{{ $sale->customer_phone }}</span>
        </div>
        @endif
        @if(!empty($sale->staff_name))
        <div class="meta-row">
            <span>Staff Attendant:</span>
            <span>{{ $sale->staff_name }}</span>
        </div>
        @endif
        @php
        $pm = strtolower((string)$sale->payment_method);
        $pmLabel = match($pm) {
            'fonepay', 'esewa' => 'Digital Payment (eSewa / Fonepay)',
            'khalti' => 'Digital Payment (Khalti QR)',
            'bank_transfer' => 'Bank Transfer / Fonepay',
            'card' => 'Card Terminal',
            'cash' => 'Cash',
            default => ucfirst(str_replace('_', ' ', $pm)),
        };
        @endphp
        <div class="meta-row">
            <span>Payment Method:</span>
            <strong>{{ $pmLabel }}</strong>
        </div>
        <div class="meta-row">
            <span>Sales Channel:</span>
            <span>{{ ucfirst(str_replace('_', ' ', (string)$sale->sales_channel)) }}</span>
        </div>

        {{-- Items Table --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 55%;">Item</th>
                    <th style="width: 15%; text-align: center;">Qty</th>
                    <th style="width: 30%; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $item)
                <tr>
                    <td>
                        @php
                        $cleanProdName = preg_replace('/-bl\b|-Bl\b|-BL\b/', '-Black', (string)$item->product_name);
                        $cleanSku = preg_replace('/-bl\b|-Bl\b|-BL\b/', '-BLACK', (string)$item->sku);
                        $colorVal = (string)$item->color;
                        if (in_array(strtolower(trim($colorVal)), ['-bl', 'bl', '-bl-'])) {
                            $colorVal = 'Black';
                        }
                        @endphp
                        <div class="item-name">{{ $cleanProdName }}</div>
                        @php
                        $specs = [];
                        if (!empty($colorVal)) $specs[] = 'Color: ' . $colorVal;
                        if (!empty($item->size)) $specs[] = 'Size: ' . $item->size;
                        @endphp
                        @if(!empty($specs))
                        <div class="item-variant">{{ implode(' • ', $specs) }}</div>
                        @endif
                        <div class="item-variant" style="font-family: monospace; font-size: 0.625rem;">SKU: {{ $cleanSku }}</div>
                    </td>
                    <td style="text-align: center; font-weight: 600;">{{ $item->quantity }}</td>
                    <td style="text-align: right; font-weight: 600;">
                        {{ \App\Helpers\NepaliNumberHelper::formatCurrency($item->total_price, 'Rs. ', 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals --}}
        <div class="totals-section">
            <div class="total-row">
                <span>Subtotal</span>
                <span>{{ \App\Helpers\NepaliNumberHelper::formatCurrency($sale->subtotal, 'Rs. ', 2) }}</span>
            </div>

            @if((float)$sale->discount_amount > 0)
            <div class="total-row" style="color: #dc2626;">
                <span>Discount {{ $sale->discount_reason ? '(' . $sale->discount_reason . ')' : '' }}</span>
                <span>-{{ \App\Helpers\NepaliNumberHelper::formatCurrency($sale->discount_amount, 'Rs. ', 2) }}</span>
            </div>
            @endif

            @if((float)$sale->shipping_amount > 0)
            <div class="total-row">
                <span>Shipping / Delivery</span>
                <span>{{ \App\Helpers\NepaliNumberHelper::formatCurrency($sale->shipping_amount, 'Rs. ', 2) }}</span>
            </div>
            @endif

            <div class="total-row grand">
                <span>TOTAL PAID</span>
                <span>{{ \App\Helpers\NepaliNumberHelper::formatCurrency($sale->total_amount, 'Rs. ', 2) }}</span>
            </div>

            {{-- Nepal VAT note --}}
            @php
            $vatIncluded = round($sale->total_amount * (13 / 113), 2);
            @endphp
            <div class="total-row" style="font-size: 0.6875rem; color: #a1a1aa; margin-top: 0.25rem;">
                <span>Includes 13% VAT:</span>
                <span>{{ \App\Helpers\NepaliNumberHelper::formatCurrency($vatIncluded, 'Rs. ', 2) }}</span>
            </div>
        </div>

        {{-- Footer --}}
        <div class="footer-note">
            Thank you for shopping at Laijau.<br>
            7-day exchange only. No returns.<br>
            Items must be in original condition with valid receipt.
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