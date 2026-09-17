<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Back in Stock — Laijau.com</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F8FAFC; color: #0F172A; margin: 0; padding: 30px 15px; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; background-color: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; }
        .header { background-color: #0F172A; color: #FFFFFF; padding: 28px 24px; text-align: center; }
        .header h1 { font-size: 24px; font-weight: 900; letter-spacing: -0.02em; margin: 0; color: #FFFFFF; }
        .header h1 span { color: #3B82F6; }
        .header p { font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase; color: #94A3B8; margin-top: 6px; margin-bottom: 0; }
        .content { padding: 30px 24px; }
        .card { background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; text-align: center; margin: 24px 0; }
        .title { font-size: 16px; font-weight: 700; color: #0F172A; margin-bottom: 6px; }
        .price { font-size: 18px; font-weight: 900; color: #2563EB; margin-bottom: 16px; }
        .btn { display: inline-block; background-color: #2563EB; color: #FFFFFF; text-decoration: none; padding: 12px 28px; font-size: 13px; font-weight: 700; border-radius: 6px; }
        .footer { background-color: #F1F5F9; text-align: center; padding: 20px 24px; font-size: 11px; color: #64748B; }
        .footer p { margin: 3px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>LAIJAU<span>.COM</span></h1>
            <p>Product Restock Alert • Kathmandu, Nepal</p>
        </div>

        <div class="content">
            <h2 style="color: #0F172A; margin-top: 0; font-size: 18px;">Good news — your requested item is back in stock!</h2>
            <p style="font-size: 13px; color: #475569;">You requested an alert when <strong>{{ $product->name }}</strong> returned to our inventory. It is now restocked at our Kathmandu central warehouse and ready for dispatch.</p>

            <div class="card">
                <div class="title">{{ $product->name }}</div>
                <div class="price">
                    Rs. {{ number_format($product->price) }}
                </div>
                <a href="{{ url('/products/' . $product->slug) }}" class="btn">
                    View & Order Product &rarr;
                </a>
            </div>

            <p style="font-size: 12px; color: #64748B;">
                Cash on Delivery is available inside Kathmandu Valley. Nationwide fast delivery across all 77 districts of Nepal.
            </p>
        </div>

        <div class="footer">
            <p>Laijau • Delta Nine business group • Kathmandu, Nepal</p>
            <p>Questions? Contact support at <a href="mailto:info@laijau.com" style="color: #2563EB;">info@laijau.com</a> or WhatsApp 9843512095</p>
        </div>
    </div>
</body>
</html>
