<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LAIJAU HR — Mobile Attendance Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Favicons -->
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <style>
        :root {
            --bg-base: #090d16;
            --card-bg: rgba(22, 28, 45, 0.75);
            --card-border: rgba(255, 255, 255, 0.08);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --primary: #10b981;
            --primary-glow: rgba(16, 185, 129, 0.35);
            --accent: #d97706;
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.35);
            --font-sans: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: #0b1329;
            color: var(--text-main);
            font-family: var(--font-sans);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: stretch;
        }

        .mobile-container {
            width: 100%;
            max-width: 440px;
            min-height: 100vh;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(24px);
            border-left: 1px solid var(--card-border);
            border-right: 1px solid var(--card-border);
            display: flex;
            flex-direction: column;
            position: relative;
            padding-bottom: 90px;
        }

        /* Top Header */
        .header {
            padding: 24px 20px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .brand-badge {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .brand-logo {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #ffffff;
            font-size: 16px;
            box-shadow: 0 4px 12px var(--primary-glow);
        }

        .brand-title {
            font-size: 17px;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: #ffffff;
            text-transform: uppercase;
        }

        .branch-pill {
            font-size: 11px;
            font-weight: 500;
            color: #cbd5e1;
            background: rgba(255, 255, 255, 0.06);
            padding: 4px 10px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        /* Main Content */
        .content {
            padding: 24px 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .greeting-section {
            margin-bottom: 28px;
        }

        .greeting-name {
            font-size: 26px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
        }

        .date-display {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 400;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Primary Action Card */
        .action-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 32px 24px;
            text-align: center;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5);
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }

        /* Large Punch Button */
        .punch-button {
            width: 170px;
            height: 170px;
            border-radius: 50%;
            margin: 0 auto 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            outline: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            text-decoration: none;
        }

        .punch-button.clock-in {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            box-shadow: 0 0 35px var(--primary-glow), inset 0 2px 4px rgba(255, 255, 255, 0.3);
        }

        .punch-button.clock-in:active {
            transform: scale(0.95);
            box-shadow: 0 0 15px var(--primary-glow);
        }

        .punch-button.clock-out {
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            box-shadow: 0 0 35px var(--danger-glow), inset 0 2px 4px rgba(255, 255, 255, 0.3);
        }

        .punch-button.clock-out:active {
            transform: scale(0.95);
            box-shadow: 0 0 15px var(--danger-glow);
        }

        .punch-icon {
            font-size: 32px;
            margin-bottom: 6px;
        }

        .punch-text {
            font-size: 17px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .punch-subtext {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.85);
            margin-top: 2px;
        }

        /* Clocked In Active State */
        .active-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 8px #10b981;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.3); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }

        .punch-time-large {
            font-size: 32px;
            font-weight: 700;
            color: #ffffff;
            font-family: var(--font-mono);
            margin-bottom: 4px;
        }

        .working-time-container {
            margin: 16px 0 24px;
            padding: 12px;
            background: rgba(0, 0, 0, 0.25);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .working-time-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .working-time-value {
            font-size: 22px;
            font-weight: 700;
            color: #fbbf24;
            font-family: var(--font-mono);
        }

        .btn-clock-out {
            display: inline-block;
            background: #ef4444;
            color: #ffffff;
            font-weight: 600;
            font-size: 15px;
            padding: 12px 32px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 14px var(--danger-glow);
            transition: all 0.2s ease;
            width: 100%;
        }

        .btn-clock-out:active {
            transform: scale(0.98);
        }

        /* Shift & Info Boxes */
        .info-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .info-label {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 400;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #ffffff;
        }

        .gps-notice {
            font-size: 11px;
            color: #64748b;
            text-align: center;
            margin-top: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        /* Bottom Navigation Bar */
        .bottom-nav {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 72px;
            background: rgba(10, 15, 30, 0.95);
            backdrop-filter: blur(16px);
            border-top: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: space-around;
            padding: 0 10px;
            z-index: 50;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 11px;
            font-weight: 500;
            padding: 6px 10px;
            border-radius: 8px;
            transition: all 0.2s ease;
            flex: 1;
        }

        .nav-item.active {
            color: #10b981;
        }

        .nav-icon {
            font-size: 20px;
        }

        /* Alerts */
        .alert-toast {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 13px;
            font-weight: 500;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }
    </style>
</head>
<body>
    <div class="mobile-container">
        <!-- Top Header -->
        <header class="header">
            <div class="brand-badge">
                <div class="brand-logo">L</div>
                <div class="brand-title">LAIJAU HR</div>
            </div>
            <div class="branch-pill">
                {{ $employee->branch_location ? Str::limit($employee->branch_location, 16) : 'Showroom' }}
            </div>
        </header>

        <!-- Main Content View -->
        <main class="content">
            @if(session('success'))
                <div class="alert-toast alert-success">
                    ✓ {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="alert-toast alert-error">
                    ⚠ {{ session('error') }}
                </div>
            @endif

            <!-- Greeting Section -->
            <div class="greeting-section">
                <h1 class="greeting-name">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ $employee->first_name }}</h1>
                <div class="date-display">
                    <span>📅</span>
                    <span>{{ now()->format('l, d M') }}</span>
                    <span style="color: #64748b;">•</span>
                    <span style="color: #fbbf24; font-size: 12px; font-weight: 600;">FY {{ $status['timesheet']?->payrollRun?->fiscal_year ?? '2083/84' }}</span>
                </div>
            </div>

            <!-- Single Obvious Action Card -->
            <div class="action-card">
                @if(!$status['is_clocked_in'])
                    <!-- Not Clocked In State -->
                    <form id="clockInForm" action="{{ route('hrm.clock_in') }}" method="POST">
                        @csrf
                        <input type="hidden" name="latitude" id="in_lat">
                        <input type="hidden" name="longitude" id="in_lng">
                        <input type="hidden" name="accuracy" id="in_acc">

                        <button type="button" onclick="handleClockIn()" class="punch-button clock-in" id="clockInBtn">
                            <span class="punch-icon">🟢</span>
                            <span class="punch-text">CLOCK IN</span>
                            <span class="punch-subtext">Tap to punch</span>
                        </button>
                    </form>
                @elseif($status['is_clocked_in'] && !$status['is_clocked_out'])
                    <!-- Clocked In Active Working State -->
                    <div class="active-status-badge">
                        <span class="pulse-dot"></span>
                        <span>🟢 Clocked In</span>
                    </div>

                    <div class="punch-time-large">
                        {{ $status['clock_in_time'] }}
                    </div>

                    @if($status['is_late'])
                        <div style="font-size: 11px; color: #f87171; margin-bottom: 8px;">
                            ⚠ Late by {{ $status['late_minutes'] }} min
                        </div>
                    @endif

                    <div class="working-time-container">
                        <div class="working-time-label">Working Time</div>
                        <div class="working-time-value" id="liveWorkingTimer">
                            {{ $status['working_time_formatted'] }}
                        </div>
                    </div>

                    <form id="clockOutForm" action="{{ route('hrm.clock_out') }}" method="POST">
                        @csrf
                        <input type="hidden" name="latitude" id="out_lat">
                        <input type="hidden" name="longitude" id="out_lng">
                        <input type="hidden" name="accuracy" id="out_acc">

                        <button type="button" onclick="handleClockOut()" class="btn-clock-out" id="clockOutBtn">
                            [ Clock Out ]
                        </button>
                    </form>
                @else
                    <!-- Completed Shift State -->
                    <div class="active-status-badge" style="background: rgba(100, 116, 139, 0.2); border-color: rgba(100, 116, 139, 0.4); color: #cbd5e1;">
                        <span>✓ Shift Completed</span>
                    </div>
                    <div class="punch-time-large" style="font-size: 24px;">
                        {{ $status['clock_in_time'] }} – {{ $status['clock_out_time'] }}
                    </div>
                    <div class="working-time-container">
                        <div class="working-time-label">Total Worked</div>
                        <div class="working-time-value" style="color: #34d399;">
                            {{ $status['regular_hours'] }}h regular
                            @if($status['overtime_hours'] > 0)
                                + {{ $status['overtime_hours'] }}h OT
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <!-- Current Shift Details -->
            <div class="info-card">
                <div>
                    <div class="info-label">Current shift</div>
                    <div class="info-value">{{ $status['shift_timing'] }}</div>
                </div>
                <div style="font-size: 20px;">⏱</div>
            </div>

            <!-- Today's Attendance Summary -->
            <div class="info-card">
                <div>
                    <div class="info-label">Today's attendance</div>
                    <div class="info-value">
                        @if(!$status['is_clocked_in'])
                            <span style="color: #94a3b8;">— Not clocked in</span>
                        @elseif(!$status['is_clocked_out'])
                            <span style="color: #34d399;">In Progress (from {{ $status['clock_in_time'] }})</span>
                        @else
                            <span style="color: #38bdf8;">Completed ({{ $status['clock_in_time'] }} – {{ $status['clock_out_time'] }})</span>
                        @endif
                    </div>
                </div>
                <div style="font-size: 20px;">📋</div>
            </div>

            <div class="gps-notice">
                <span>📍</span>
                <span>GPS Location geofencing enabled for Kathmandu showroom</span>
            </div>
        </main>

        <!-- Mobile Bottom Navigation Bar -->
        <nav class="bottom-nav">
            <a href="{{ route('hrm.portal') }}?tab=home" class="nav-item {{ $activeTab === 'home' ? 'active' : '' }}">
                <span class="nav-icon">🏠</span>
                <span>Home</span>
            </a>
            <a href="/intadmin/timesheets" class="nav-item {{ $activeTab === 'attendance' ? 'active' : '' }}">
                <span class="nav-icon">🕐</span>
                <span>Attendance</span>
            </a>
            <a href="/intadmin/leave-requests" class="nav-item {{ $activeTab === 'leave' ? 'active' : '' }}">
                <span class="nav-icon">🌴</span>
                <span>Leave</span>
            </a>
            <a href="/intadmin/payroll-runs" class="nav-item {{ $activeTab === 'payroll' ? 'active' : '' }}">
                <span class="nav-icon">💰</span>
                <span>Payroll</span>
            </a>
            <a href="/intadmin/employees/{{ $employee->id }}/edit" class="nav-item {{ $activeTab === 'profile' ? 'active' : '' }}">
                <span class="nav-icon">👤</span>
                <span>Profile</span>
            </a>
        </nav>
    </div>

    <script>
        // GPS capture & punch helper
        function handleClockIn() {
            const btn = document.getElementById('clockInBtn');
            btn.style.opacity = '0.6';
            btn.querySelector('.punch-text').innerText = 'PUNCHING...';

            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        document.getElementById('in_lat').value = position.coords.latitude;
                        document.getElementById('in_lng').value = position.coords.longitude;
                        document.getElementById('in_acc').value = position.coords.accuracy;
                        document.getElementById('clockInForm').submit();
                    },
                    (error) => {
                        console.warn("GPS lookup skipped or denied:", error.message);
                        document.getElementById('clockInForm').submit();
                    },
                    { timeout: 4000, enableHighAccuracy: true }
                );
            } else {
                document.getElementById('clockInForm').submit();
            }
        }

        function handleClockOut() {
            const btn = document.getElementById('clockOutBtn');
            btn.style.opacity = '0.6';
            btn.innerText = 'CLOCKING OUT...';

            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        document.getElementById('out_lat').value = position.coords.latitude;
                        document.getElementById('out_lng').value = position.coords.longitude;
                        document.getElementById('out_acc').value = position.coords.accuracy;
                        document.getElementById('clockOutForm').submit();
                    },
                    (error) => {
                        document.getElementById('clockOutForm').submit();
                    },
                    { timeout: 4000, enableHighAccuracy: true }
                );
            } else {
                document.getElementById('clockOutForm').submit();
            }
        }

        // Live working time ticker when clocked in
        @if($status['is_clocked_in'] && !$status['is_clocked_out'])
            let startTimestamp = {{ strtotime($status['date'] . ' ' . $status['timesheet']->clock_in) * 1000 }};
            function updateTimer() {
                const now = new Date().getTime();
                const diff = Math.max(0, Math.floor((now - startTimestamp) / 1000));
                const hours = Math.floor(diff / 3600);
                const minutes = Math.floor((diff % 3600) / 60);
                const seconds = diff % 60;
                const formatted = String(hours).padStart(2, '0') + 'h ' + 
                                  String(minutes).padStart(2, '0') + 'm ' + 
                                  String(seconds).padStart(2, '0') + 's';
                const timerEl = document.getElementById('liveWorkingTimer');
                if (timerEl) {
                    timerEl.innerText = formatted;
                }
            }
            setInterval(updateTimer, 1000);
            updateTimer();
        @endif
    </script>
</body>
</html>
