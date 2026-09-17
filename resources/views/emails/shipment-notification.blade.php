<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Laijau.com — Shipment Dispatched</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F8FAFC; color: #0F172A; margin: 0; padding: 30px 15px; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; background-color: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; }
        .header { background-color: #0F172A; color: #FFFFFF; padding: 28px 24px; text-align: center; }
        .header h1 { font-size: 24px; font-weight: 900; letter-spacing: -0.02em; margin: 0; color: #FFFFFF; }
        .header h1 span { color: #3B82F6; }
        .header p { font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase; color: #94A3B8; margin-top: 6px; margin-bottom: 0; }
        .content { padding: 30px 24px; }
        .intro { font-size: 14px; color: #334155; margin-bottom: 20px; }
        .tracking-card { background-color: #0F172A; color: #FFFFFF; padding: 24px; border-radius: 8px; text-align: center; margin: 24px 0; }
        .tracking-card h3 { color: #60A5FA; margin-top: 0; margin-bottom: 8px; font-size: 18px; }
        .tracking-btn { display: inline-block; background-color: #2563EB; color: #FFFFFF; text-decoration: none; padding: 12px 28px; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; border-radius: 6px; margin-top: 14px; }
        .footer { background-color: #F1F5F9; text-align: center; padding: 20px 24px; font-size: 11px; color: #64748B; }
        .footer p { margin: 3px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>LAIJAU<span>.COM</span></h1>
            <p>Shipment Dispatched • Nepal Courier Delivery</p>
        </div>
        <div class="content">
            <p class="intro">Namaste {{ $order->first_name }},</p>
            <p class="intro">Good news! Your order <strong>#{{ $order->order_number }}</strong> has been packed and handed over to our courier partner for delivery.</p>

            <div class="tracking-card">
                <h3>Your Parcel is on the Way</h3>
                <p style="margin: 4px 0; font-size: 13px; color: #CBD5E1;">Courier Partner: <strong>{{ $order->carrier ?? 'Nepal Courier Dispatch' }}</strong></p>
                <p style="margin: 4px 0; font-size: 13px; color: #CBD5E1;">Consignment / Tracking Number: <strong>{{ $order->tracking_number ?? 'Available shortly' }}</strong></p>
                @php
                    $trackingUrl = $order->tracking_url ?? \App\Models\Order::resolveTrackingUrl($order->carrier, $order->tracking_number);
                @endphp
                @if($trackingUrl)
                    <a href="{{ $trackingUrl }}" target="_blank" class="tracking-btn">Track Courier Shipment &rarr;</a>
                @else
                    <a href="https://laijau.com/track-order?order_number={{ urlencode($order->order_number) }}&phone={{ urlencode($order->phone) }}" target="_blank" class="tracking-btn">Track on Laijau.com &rarr;</a>
                @endif
            </div>

            <p style="font-size: 12px; color: #64748B;">For Kathmandu Valley orders, our rider will phone you before delivery. For nationwide orders via NCM / Pathao, courier tracking updates will reflect once scanned at the destination hub.</p>
        </div>
        <div class="footer">
            <p>Laijau • Delta Nine business group — Kathmandu, Nepal.</p>
            <p>Order inquiries? Chat with us on WhatsApp at 9843512095 or email <a href="mailto:info@laijau.com" style="color: #2563EB;">info@laijau.com</a></p>
        </div>
    </div>
</body>
</html>
