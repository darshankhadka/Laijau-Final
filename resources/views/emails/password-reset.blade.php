<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Laijau.com — Password Reset</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F8FAFC; color: #0F172A; margin: 0; padding: 30px 15px; line-height: 1.6; }
        .container { max-width: 540px; margin: 0 auto; background-color: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; }
        .header { background-color: #0F172A; color: #FFFFFF; padding: 28px 24px; text-align: center; }
        .header h1 { font-size: 24px; font-weight: 900; letter-spacing: -0.02em; margin: 0; color: #FFFFFF; }
        .header h1 span { color: #3B82F6; }
        .header p { font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase; color: #94A3B8; margin-top: 6px; margin-bottom: 0; }
        .content { padding: 30px 24px; }
        .intro { font-size: 14px; color: #334155; margin-bottom: 20px; }
        .btn-container { text-align: center; margin: 28px 0; }
        .btn { display: inline-block; background-color: #2563EB; color: #FFFFFF !important; text-decoration: none; padding: 12px 30px; font-size: 13px; font-weight: 700; border-radius: 6px; }
        .notice { font-size: 11px; color: #64748B; margin-top: 24px; padding-top: 16px; border-top: 1px solid #F1F5F9; }
        .footer { background-color: #F1F5F9; text-align: center; padding: 20px 24px; font-size: 11px; color: #64748B; }
        .footer p { margin: 3px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>LAIJAU<span>.COM</span></h1>
            <p>Password Reset Request • Laijau Nepal</p>
        </div>
        <div class="content">
            <p class="intro">Namaste {{ $user->name }},</p>
            <p class="intro">We received a request to reset the password for your Laijau customer account.</p>
            <p class="intro">Click the button below to choose a new password. This link is valid for 60 minutes.</p>

            <div class="btn-container">
                <a href="{{ $resetUrl }}" class="btn">Reset My Password &rarr;</a>
            </div>

            <p class="notice">
                If you did not request a password reset, you can safely ignore this email. Your account remains completely secure.
            </p>
        </div>
        <div class="footer">
            <p>Laijau • Delta Nine business group — Kathmandu, Nepal</p>
            <p>Need help? Contact support at <a href="mailto:info@laijau.com" style="color: #2563EB;">info@laijau.com</a></p>
        </div>
    </div>
</body>
</html>
