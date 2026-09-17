<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Laijau.com — Order Confirmation</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F8FAFC; color: #0F172A; margin: 0; padding: 30px 15px; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; background-color: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; }
        .header { background-color: #0F172A; color: #FFFFFF; padding: 28px 24px; text-align: center; }
        .header h1 { font-size: 24px; font-weight: 900; letter-spacing: -0.02em; margin: 0; color: #FFFFFF; }
        .header h1 span { color: #3B82F6; }
        .header p { font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase; color: #94A3B8; margin-top: 6px; margin-bottom: 0; }
        .content { padding: 30px 24px; }
        .intro { font-size: 14px; color: #334155; margin-bottom: 20px; }
        .order-meta { background-color: #F1F5F9; padding: 16px 18px; border-radius: 8px; margin-bottom: 24px; font-size: 12px; }
        .order-meta table { width: 100%; }
        .order-meta td { padding: 4px 0; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 13px; }
        .items-table th { text-align: left; padding: 10px 8px; border-bottom: 2px solid #0F172A; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #0F172A; }
        .items-table td { padding: 12px 8px; border-bottom: 1px solid #F1F5F9; }
        .totals-table { width: 100%; margin-top: 10px; font-size: 13px; }
        .totals-table td { padding: 5px 0; text-align: right; }
        .totals-table .total-row { font-size: 16px; color: #0F172A; font-weight: 800; border-top: 2px solid #E2E8F0; }
        .footer { background-color: #F1F5F9; text-align: center; padding: 20px 24px; font-size: 11px; color: #64748B; }
        .footer p { margin: 3px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>LAIJAU<span>.COM</span></h1>
            <p>Order Confirmation • Kathmandu, Nepal</p>
        </div>
        <div class="content">
            <p class="intro">Namaste {{ $order->first_name }},</p>
            <p class="intro">Thank you for shopping at Laijau! Your order has been placed successfully and is being prepared for dispatch.</p>

            <div class="order-meta">
                <table>
                    <tr>
                        <td><strong>Order Number:</strong> {{ $order->order_number }}</td>
                        <td style="text-align: right;"><strong>Date:</strong> {{ $order->created_at->format('M d, Y') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Payment Method:</strong> {{ ucfirst(str_replace('_', ' ', $order->payment_method ?? 'Cash on Delivery')) }}</td>
                        <td style="text-align: right;"><strong>Payment Status:</strong> {{ ucfirst($order->payment_status ?? 'Pending') }}</td>
                    </tr>
                </table>
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Details</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->product_name ?? $item->product?->name ?? 'Product' }}</strong><br>
                                <span style="font-size: 11px; color: #64748B;">Qty: {{ $item->quantity }}</span>
                            </td>
                            <td style="font-size: 11px; color: #475569;">
                                @if($item->selected_size)
                                    Size: {{ $item->selected_size }}<br>
                                @endif
                                @if($item->selected_color)
                                    Color: {{ $item->selected_color }}
                                @endif
                            </td>
                            <td style="text-align: right; font-weight: 600;">
                                Rs. {{ number_format($item->unit_price * $item->quantity) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="totals-table">
                <tr>
                    <td style="color: #64748B;">Subtotal:</td>
                    <td style="width: 130px; font-weight: 600;">Rs. {{ number_format($order->subtotal) }}</td>
                </tr>
                @if($order->coupon_discount > 0)
                    <tr>
                        <td style="color: #EF4444;">Discount:</td>
                        <td style="color: #EF4444; font-weight: 600;">-Rs. {{ number_format($order->coupon_discount) }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="color: #64748B;">Shipping:</td>
                    <td style="font-weight: 600;">{{ $order->shipping_fee > 0 ? 'Rs. ' . number_format($order->shipping_fee) : 'Free' }}</td>
                </tr>
                <tr class="total-row">
                    <td style="padding-top: 10px;">Total Amount:</td>
                    <td style="padding-top: 10px; color: #2563EB;">Rs. {{ number_format($order->total_amount) }}</td>
                </tr>
            </table>

            <div style="margin-top: 28px; padding: 16px; border-left: 3px solid #2563EB; background-color: #F8FAFC; border-radius: 4px;">
                <h4 style="margin: 0 0 6px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #0F172A;">Delivery Address</h4>
                <p style="margin: 0; font-size: 12px; color: #475569;">
                    {{ $order->first_name }} {{ $order->last_name }}<br>
                    {{ $order->shipping_address }}<br>
                    @if($order->shipping_city){{ $order->shipping_city }}, @endif{{ $order->shipping_postal_code }}<br>
                    Phone: {{ $order->phone }}
                </p>
            </div>
        </div>
        <div class="footer">
            <p>Laijau • Delta Nine business group — Dispatched from Kathmandu, Nepal.</p>
            <p>Track your order online at <a href="https://laijau.com/track-order" style="color: #2563EB; text-decoration: underline;">laijau.com/track-order</a></p>
            <p>Need support? Contact us on WhatsApp at 9843512095 or email <a href="mailto:info@laijau.com" style="color: #2563EB;">info@laijau.com</a></p>
        </div>
    </div>
</body>
</html>
