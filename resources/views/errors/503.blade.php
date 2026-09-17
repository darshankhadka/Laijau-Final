<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scheduled Maintenance &bull; Laijau.com</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Favicons -->
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: #0F172A;
            color: #F8FAFC;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
            text-align: center;
        }
        .container {
            max-width: 520px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .brand {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            font-weight: 900;
            color: #FFFFFF;
            margin-bottom: 1.5rem;
            letter-spacing: -0.02em;
        }
        .brand span {
            color: #3B82F6;
        }
        h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            line-height: 1.25;
            color: #F8FAFC;
            font-weight: 800;
            margin-bottom: 1rem;
        }
        .message {
            font-size: 0.875rem;
            color: #94A3B8;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .meta-card {
            background: #1E293B;
            border: 1px solid #334155;
            border-radius: 0.75rem;
            padding: 1rem 1.5rem;
            font-size: 0.75rem;
            color: #CBD5E1;
            margin-bottom: 2rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            width: 100%;
        }
        .meta-card strong {
            color: #3B82F6;
        }
        .contact-link {
            color: #3B82F6;
            text-decoration: none;
        }
        .footer {
            font-size: 0.6875rem;
            color: #64748B;
        }
        .admin-link {
            color: #475569;
            text-decoration: none;
            font-size: 0.6875rem;
            margin-top: 1.5rem;
        }
        .admin-link:hover {
            color: #3B82F6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="brand">LAIJAU<span>.COM</span></div>

        <h1>Platform Maintenance</h1>

        <p class="message">
            {{ $message ?? 'Laijau is currently undergoing routine maintenance to optimize catalog speed and order dispatch systems. We will be back online shortly.' }}
        </p>

        <div class="meta-card">
            <div>Expected Availability: <strong>{{ $expectedReturn ?? 'Shortly' }}</strong></div>
            <div>Order Inquiries: <a href="mailto:{{ $contactEmail ?? 'info@laijau.com' }}" class="contact-link">{{ $contactEmail ?? 'info@laijau.com' }}</a></div>
            <div>WhatsApp Support: <strong>9843512095</strong></div>
        </div>

        <div class="footer">
            Laijau &bull; Delta Nine business group &bull; Kathmandu, Nepal
        </div>
    </div>
</body>
</html>
