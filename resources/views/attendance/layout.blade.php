<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#001b48">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Attendance">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Laijau Attendance')</title>

    <link rel="manifest" href="/attendance/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/attendance-assets/icons/icon-192.png">
    <link rel="icon" type="image/png" href="/attendance-assets/icons/icon-192.png">

    <!-- Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>

    <style>
        /* =========================================================================
           LAIJAU ATTENDANCE PWA — MASTER STYLESHEET (LIGHT-MODE ONLY)
           ========================================================================= */
        :root {
            --att-navy: #001b48;
            --att-navy-light: #002566;
            --att-navy-dark: #001438;
            --att-emerald: #059669;
            --att-emerald-light: #10b981;
            --att-emerald-bg: #ecfdf5;
            --att-rose: #e11d48;
            --att-rose-bg: #fff1f2;
            --att-amber: #d97706;
            --att-amber-bg: #fffbeb;
            --att-gray-50: #f8fafc;
            --att-gray-100: #f1f5f9;
            --att-gray-200: #e2e8f0;
            --att-gray-300: #cbd5e1;
            --att-gray-500: #64748b;
            --att-gray-700: #334155;
            --att-gray-900: #0f172a;
            --att-radius-sm: 0.5rem;
            --att-radius: 0.875rem;
            --att-radius-lg: 1.25rem;
            --att-shadow: 0 4px 20px -2px rgba(0, 27, 72, 0.08);
            --att-shadow-lg: 0 10px 30px -5px rgba(0, 27, 72, 0.12);
        }

        [x-cloak] {
            display: none !important;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--att-gray-100);
            color: var(--att-gray-900);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-bottom: env(safe-area-inset-bottom, 1.5rem);
        }

        /* App Container constrained for true mobile feel */
        .att-app-shell {
            width: 100%;
            max-width: 440px;
            min-height: 100vh;
            background: #ffffff;
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        @media (min-width: 441px) {
            body {
                padding: 1.5rem 1rem;
            }
            .att-app-shell {
                min-height: calc(100vh - 3rem);
                border-radius: var(--att-radius-lg);
                border: 1px solid var(--att-gray-200);
                overflow: hidden;
            }
        }

        /* Header Navigation */
        .att-header {
            background: #ffffff;
            border-bottom: 1px solid var(--att-gray-200);
            padding: 0.875rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 40;
        }
        .att-brand {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            text-decoration: none;
        }
        .att-logo-mark {
            width: 2.25rem;
            height: 2.25rem;
            background: linear-gradient(135deg, var(--att-navy) 0%, var(--att-navy-light) 100%);
            border-radius: 0.625rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-weight: 900;
            font-size: 1.1rem;
            letter-spacing: -0.05em;
            box-shadow: 0 2px 8px rgba(0, 27, 72, 0.2);
        }
        .att-brand-text {
            display: flex;
            flex-direction: column;
        }
        .att-brand-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--att-navy);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .att-brand-sub {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--att-emerald);
        }

        /* Main Body */
        .att-content {
            flex: 1;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        /* Buttons (44px+ touch targets) */
        .att-btn {
            min-height: 3rem;
            padding: 0.75rem 1.25rem;
            border-radius: var(--att-radius);
            font-size: 0.875rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.15s ease-in-out;
            text-decoration: none;
            width: 100%;
            user-select: none;
        }
        .att-btn:active {
            transform: scale(0.98);
        }
        .att-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        .att-btn-primary {
            background: linear-gradient(135deg, var(--att-navy) 0%, var(--att-navy-light) 100%);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(0, 27, 72, 0.25);
        }
        .att-btn-primary:hover:not(:disabled) {
            background: var(--att-navy-light);
        }
        .att-btn-emerald {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
        }
        .att-btn-emerald:hover:not(:disabled) {
            background: #047857;
        }
        .att-btn-rose {
            background: linear-gradient(135deg, #e11d48 0%, #f43f5e 100%);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(225, 29, 72, 0.3);
        }
        .att-btn-rose:hover:not(:disabled) {
            background: #be123c;
        }
        .att-btn-outline {
            background: #ffffff;
            color: var(--att-gray-700);
            border-color: var(--att-gray-300);
        }
        .att-btn-outline:hover:not(:disabled) {
            background: var(--att-gray-50);
            border-color: var(--att-gray-400);
        }

        /* Form Inputs (16px font to prevent mobile auto-zoom) */
        .att-form-group {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .att-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--att-gray-700);
        }
        .att-input {
            width: 100%;
            height: 3rem;
            padding: 0 1rem;
            font-size: 16px; /* Strictly 16px to prevent iOS zoom */
            border-radius: var(--att-radius);
            border: 1.5px solid var(--att-gray-300);
            background: #ffffff;
            color: var(--att-gray-900);
            outline: none;
            transition: all 0.15s ease;
            font-family: inherit;
        }
        .att-input:focus {
            border-color: var(--att-navy);
            box-shadow: 0 0 0 3px rgba(0, 27, 72, 0.1);
        }
        .att-input::placeholder {
            color: #94a3b8;
        }
        .att-input.font-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            letter-spacing: 0.08em;
        }
        .att-input-error {
            font-size: 0.75rem;
            color: var(--att-rose);
            font-weight: 600;
            margin-top: 0.25rem;
        }

        /* Cards */
        .att-card {
            background: #ffffff;
            border: 1px solid var(--att-gray-200);
            border-radius: var(--att-radius);
            padding: 1.25rem;
            box-shadow: var(--att-shadow);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        /* Badges */
        .att-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            line-height: 1;
        }
        .att-badge-emerald { background: var(--att-emerald-bg); color: var(--att-emerald); }
        .att-badge-rose { background: var(--att-rose-bg); color: var(--att-rose); }
        .att-badge-amber { background: var(--att-amber-bg); color: var(--att-amber); }
        .att-badge-navy { background: #eff6ff; color: #1d4ed8; }

        /* Toast Notifications */
        .att-toast {
            position: fixed;
            bottom: calc(env(safe-area-inset-bottom, 1rem) + 1rem);
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 2rem);
            max-width: 400px;
            padding: 0.875rem 1.125rem;
            border-radius: var(--att-radius);
            color: #ffffff;
            font-size: 0.8125rem;
            font-weight: 600;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            animation: att-slide-up 0.25s ease-out;
        }
        .att-toast.success { background: #065f46; border: 1px solid #059669; }
        .att-toast.error { background: #9f1239; border: 1px solid #e11d48; }

        @keyframes att-slide-up {
            from { transform: translate(-50%, 20px); opacity: 0; }
            to { transform: translate(-50%, 0); opacity: 1; }
        }

        /* SVG Sizing */
        svg {
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
        }
    </style>

    @yield('styles')
</head>
<body>

    <div class="att-app-shell">
        <!-- Top Navigation -->
        <header class="att-header">
            <a href="{{ route('attendance.dashboard') }}" class="att-brand" style="display:flex;align-items:center;gap:0.75rem;text-decoration:none;">
                <img src="{{ asset('images/logo.png') }}" alt="Laijau" style="height:2rem;width:auto;max-width:130px;object-fit:contain;" onerror="this.onerror=null; this.src='{{ asset('logo.png') }}';">
                <span class="att-brand-sub" style="border-left:1px solid var(--att-gray-300);padding-left:0.65rem;font-size:0.6875rem;font-weight:700;letter-spacing:0.06em;color:var(--att-emerald);text-transform:uppercase;">Attendance</span>
            </a>

            @if(session('attendance_employee_id'))
                <button
                    type="button"
                    onclick="handleAttendanceLogout()"
                    class="att-btn att-btn-outline"
                    style="min-height:2rem;height:2.125rem;padding:0 0.75rem;font-size:0.6875rem;width:auto;border-radius:0.5rem;"
                    title="Sign Out of Attendance Device">
                    Sign Out
                </button>
            @endif
        </header>

        <!-- Main View Content -->
        <main class="att-content">
            @yield('content')
        </main>
    </div>

    <!-- PWA Registration & Global Handlers -->
    <script>
        // Register Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/attendance/sw.js', { scope: '/attendance' })
                    .then((reg) => {
                        reg.update();
                        console.log('Attendance SW registered:', reg.scope);
                    })
                    .catch((err) => {
                        console.warn('Attendance SW registration error:', err);
                    });
            });
        }

        // Global sign out
        async function handleAttendanceLogout() {
            if (!confirm('Sign out from this attendance session?')) {
                return;
            }
            try {
                const res = await fetch('/attendance/api/logout', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    }
                });
                const data = await res.json();
                window.location.href = data.redirect || '/attendance/login';
            } catch (e) {
                window.location.href = '/attendance/login';
            }
        }
    </script>

    @yield('scripts')
</body>
</html>
