<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting to ConnectIPS | LAIJAU</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .redirect-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 36px 32px;
            max-width: 440px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        }
        .logo {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0.1em;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-bottom: 24px;
        }
        .spinner {
            width: 44px;
            height: 44px;
            border: 3px solid #e2e8f0;
            border-top-color: #2563eb;
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
            margin: 0 auto 20px auto;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        h1 {
            font-size: 18px;
            margin: 0 0 10px 0;
            font-weight: 700;
            color: #0f172a;
        }
        p {
            font-size: 13px;
            color: #64748b;
            margin: 0 0 20px 0;
            line-height: 1.5;
        }
        .amount-box {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 24px;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }
        .btn-redirect {
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s ease;
        }
        .btn-redirect:hover {
            background-color: #1d4ed8;
        }
    </style>
</head>
<body>
    <div class="redirect-card">
        <div class="logo">LAIJAU</div>
        <div class="spinner"></div>
        <h1>Connecting to connectIPS</h1>
        <p>You are being securely redirected to connectIPS to authorize your interbank payment for <strong>Order #{{ $order->order_number }}</strong>.</p>
        
        <div class="amount-box">
            Amount: Rs. {{ number_format($order->total_amount, 2) }}
        </div>

        <form id="connectips-gateway-form" action="{{ $actionUrl }}" method="POST">
            @foreach($fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach

            <button type="submit" class="btn-redirect">Click here if not redirected automatically</button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var form = document.getElementById('connectips-gateway-form');
                if (form) {
                    form.submit();
                }
            }, 300);
        });
    </script>
</body>
</html>
