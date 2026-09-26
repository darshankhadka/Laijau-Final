@extends('attendance.layout')

@section('title', 'Attendance Dashboard — Laijau')

@section('content')
<div x-data="attendanceDashboard({{ json_encode($status) }}, {{ json_encode($settings) }}, {{ $canPunchFromAnywhere ? 'true' : 'false' }})" class="att-dash-wrap" style="display:flex;flex-direction:column;gap:1.25rem;">

    <!-- Greeting & Live Clock Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding-bottom:0.25rem;">
        <div>
            <div style="font-size:0.75rem;font-weight:700;color:var(--att-gray-500);text-transform:uppercase;letter-spacing:0.04em;">
                <span x-text="greetingText">Good Day</span>,
            </div>
            <h2 style="font-size:1.35rem;font-weight:900;color:var(--att-navy);letter-spacing:-0.03em;margin:0;">
                {{ $employee->first_name }}
            </h2>
            <div style="font-size:0.6875rem;color:var(--att-gray-500);font-family:monospace;margin-top:0.15rem;">
                {{ $employee->employee_number }} &bull; @if($canPunchFromAnywhere) <span style="color:#0284c7;font-weight:700;">Punch From Anywhere</span> @else {{ $defaultLocation->name ?? 'Laijau Showroom' }} @endif
            </div>
        </div>

        <div style="text-align:right;">
            <div style="font-size:1.3rem;font-weight:900;font-family:monospace;color:var(--att-navy);line-height:1;" x-text="liveTime">
                --:-- --
            </div>
            <div style="font-size:0.6875rem;color:var(--att-gray-500);margin-top:0.25rem;" x-text="liveDate">
                {{ now()->format('M d, Y') }}
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <template x-if="toastMessage">
        <div class="att-toast" :class="toastType">
            <span x-text="toastType === 'success' ? '✓' : '⚠️'"></span>
            <span x-text="toastMessage"></span>
        </div>
    </template>

    <!-- TODAY'S ATTENDANCE STATUS CARD -->
    <div class="att-card" style="position:relative;overflow:hidden;border:1px solid #e2e8f0;border-radius:1rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <div style="font-size:0.6875rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--att-gray-500);">
                Today's Attendance
            </div>

            <!-- Server Authoritative Status Badge -->
            <template x-if="status.state === 'not_checked_in'">
                <span class="att-badge att-badge-amber">Clocked Out</span>
            </template>
            <template x-if="status.state === 'checked_in'">
                <span class="att-badge att-badge-emerald" style="display:flex;align-items:center;gap:0.35rem;">
                    <span style="width:6px;height:6px;border-radius:9999px;background:#10b981;animation:pulse 1.5s infinite;"></span>
                    Clocked In
                </span>
            </template>
            <template x-if="status.state === 'checked_out'">
                <span class="att-badge att-badge-navy">Shift Complete</span>
            </template>
        </div>

        <!-- Punch Timestamps & Working Timer -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;padding:0.75rem 0;border-top:1px solid var(--att-gray-100);border-bottom:1px solid var(--att-gray-100);">
            <div>
                <div style="font-size:0.65rem;font-weight:700;color:var(--att-gray-500);text-transform:uppercase;">Clock In</div>
                <div style="font-size:1.15rem;font-weight:800;color:var(--att-navy);font-family:monospace;margin-top:0.15rem;">
                    <span x-text="status.check_in_time || '--:--'"></span>
                </div>
            </div>
            <div>
                <div style="font-size:0.65rem;font-weight:700;color:var(--att-gray-500);text-transform:uppercase;">Clock Out</div>
                <div style="font-size:1.15rem;font-weight:800;color:var(--att-navy);font-family:monospace;margin-top:0.15rem;">
                    <span x-text="status.check_out_time || '--:--'"></span>
                </div>
            </div>
        </div>

        <!-- Duration & Verified Location Info -->
        <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.75rem;color:var(--att-gray-600);">
            <span style="display:flex;align-items:center;gap:0.35rem;">
                <span>📍</span>
                <span>
                    @if($canPunchFromAnywhere)
                        <strong style="color:#0369a1;">Anywhere (Remote Authorized)</strong>
                    @else
                        {{ $defaultLocation->name ?? 'Laijau Showroom' }}
                    @endif
                </span>
            </span>

            <template x-if="status.state === 'checked_in'">
                <span style="color:var(--att-emerald);font-weight:700;">
                    Session #<span x-text="status.current_session?.session_number || 1"></span>: <span x-text="shiftDuration">0h 0m</span>
                </span>
            </template>
            <template x-if="status.state !== 'checked_in'">
                <span style="color:var(--att-gray-700);font-weight:700;">
                    Worked Today: <span x-text="status.total_worked_formatted || '0h 0m'"></span>
                </span>
            </template>
        </div>
    </div>

    <!-- PRIMARY ACTION BUTTON (CLOCK IN / CLOCK OUT) -->
    <div style="display:flex;flex-direction:column;gap:0.625rem;">
        
        <!-- 1. CLOCK IN (Shown when employee is not in an active open session) -->
        <template x-if="status.state !== 'checked_in'">
            <button
                type="button"
                @click="punch('check_in')"
                :disabled="punchLoading"
                class="att-btn att-btn-emerald"
                style="min-height:3.85rem;font-size:1.15rem;font-weight:900;letter-spacing:0.04em;border-radius:1rem;box-shadow:0 8px 20px -2px rgba(5,150,105,0.35);">
                <svg x-show="!punchLoading" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                <svg x-show="punchLoading" style="animation:spin 1s linear infinite;width:22px;height:22px;" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity:0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path style="opacity:0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="punchLoading ? 'Verifying GPS & Clocking In...' : (status.completed_sessions_today > 0 ? 'CLOCK IN (Start Session #' + (status.next_session_number || 2) + ')' : 'CLOCK IN')"></span>
            </button>
        </template>

        <!-- 2. CLOCK OUT (Shown when employee is actively clocked in) -->
        <template x-if="status.state === 'checked_in'">
            <button
                type="button"
                @click="punch('check_out')"
                :disabled="punchLoading"
                class="att-btn att-btn-rose"
                style="min-height:3.85rem;font-size:1.15rem;font-weight:900;letter-spacing:0.04em;border-radius:1rem;box-shadow:0 8px 20px -2px rgba(225,29,72,0.35);">
                <svg x-show="!punchLoading" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                <svg x-show="punchLoading" style="animation:spin 1s linear infinite;width:22px;height:22px;" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity:0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path style="opacity:0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="punchLoading ? 'Verifying GPS & Clocking Out...' : 'CLOCK OUT (End Session #' + (status.current_session?.session_number || 1) + ')'"></span>
            </button>
        </template>

        <!-- TODAY'S ATTENDANCE SESSIONS BREAKDOWN -->
        <template x-if="status.today_sessions && status.today_sessions.length > 0">
            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:0.875rem;padding:0.875rem;margin-top:0.25rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.625rem;">
                    <div style="display:flex;align-items:center;gap:0.35rem;font-size:0.8125rem;font-weight:800;color:var(--att-navy);">
                        <span>⏱</span>
                        <span>Today's Sessions</span>
                    </div>
                    <span style="font-size:0.6875rem;font-weight:700;color:var(--att-emerald);" x-text="'Total: ' + (status.total_worked_formatted || '0h 0m')"></span>
                </div>

                <div style="display:flex;flex-direction:column;gap:0.375rem;">
                    <template x-for="sess in status.today_sessions" :key="sess.id">
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:0.5rem 0.65rem;border-radius:0.5rem;font-size:0.75rem;"
                            :style="sess.status === 'open' ? 'background:#ecfdf5;border:1px solid #a7f3d0;' : 'background:var(--att-gray-50);border:1px solid var(--att-gray-200);'">
                            <div>
                                <div style="display:flex;align-items:center;gap:0.35rem;">
                                    <span style="font-weight:800;color:var(--att-navy);" x-text="'Session #' + sess.session_number"></span>
                                    <span x-show="sess.status === 'open'" style="font-size:0.5625rem;font-weight:800;color:#047857;background:#d1fae5;padding:0.05rem 0.35rem;border-radius:9999px;">
                                        ACTIVE
                                    </span>
                                    <span x-show="sess.status === 'completed'" style="font-size:0.5625rem;font-weight:700;color:#475569;background:#e2e8f0;padding:0.05rem 0.35rem;border-radius:9999px;">
                                        DONE
                                    </span>
                                </div>
                                <div style="font-size:0.65rem;color:var(--att-gray-500);margin-top:0.15rem;">
                                    <span x-text="'In: ' + (sess.clock_in_time || '—')"></span>
                                    <span x-show="sess.clock_out_time" x-text="' • Out: ' + sess.clock_out_time"></span>
                                    <span x-text="' • ' + (sess.clock_in_location || 'Showroom')"></span>
                                </div>
                            </div>
                            <div style="font-weight:800;font-family:monospace;font-size:0.75rem;"
                                :style="sess.status === 'open' ? 'color:#047857;' : 'color:var(--att-navy);'"
                                x-text="sess.status === 'open' ? shiftDuration : (sess.duration_formatted || '0h 0m')">
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- GPS Verification Error Notice -->
        <template x-if="punchErrorMessage">
            <div style="padding:0.875rem 1rem;border-radius:0.75rem;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;display:flex;align-items:flex-start;gap:0.75rem;font-size:0.75rem;animation:att-slide-up 0.2s ease-out;">
                <div style="font-size:1.25rem;line-height:1;">⚠️</div>
                <div style="flex:1;">
                    <div style="font-weight:800;font-size:0.8125rem;">Location Notice</div>
                    <div style="font-size:0.75rem;margin-top:0.25rem;line-height:1.4;" x-text="punchErrorMessage"></div>
                </div>
                <button
                    type="button"
                    @click="punchErrorMessage = null"
                    style="background:none;border:none;color:#991b1b;font-weight:800;font-size:1.1rem;cursor:pointer;">
                    ×
                </button>
            </div>
        </template>

        <div style="text-align:center;font-size:0.6875rem;color:var(--att-gray-500);">
            @if($canPunchFromAnywhere)
                📍 Remote punch authorized &bull; No location restrictions for {{ $employee->first_name }}
            @else
                GPS location verified automatically upon punch &bull; No camera required
            @endif
        </div>
    </div>

    <!-- RECENT ATTENDANCE HISTORY -->
    <div class="att-card" style="border:1px solid #e2e8f0;border-radius:1rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <h4 style="font-size:0.875rem;font-weight:800;color:var(--att-navy);margin:0;">Recent Attendance</h4>
            <span style="font-size:0.6875rem;color:var(--att-gray-500);font-weight:600;">Past Records</span>
        </div>

        <div style="display:flex;flex-direction:column;gap:0.5rem;">
            @forelse($recentHistory as $item)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:0.625rem 0.75rem;background:var(--att-gray-50);border:1px solid var(--att-gray-200);border-radius:var(--att-radius-sm);">
                    <div>
                        <div style="font-size:0.8125rem;font-weight:700;color:var(--att-navy);">{{ $item['date'] }}</div>
                        <div style="font-size:0.6875rem;color:var(--att-gray-500);">{{ $item['day'] }} &bull; {{ $item['location'] }}</div>
                    </div>
                    <div style="text-align:right;font-family:monospace;font-size:0.75rem;font-weight:700;">
                        <div style="color:var(--att-emerald);">IN: {{ $item['check_in'] ?? '—' }}</div>
                        <div style="color:var(--att-rose);">OUT: {{ $item['check_out'] ?? '—' }}</div>
                    </div>
                </div>
            @empty
                <div style="text-align:center;padding:1.5rem 0;color:var(--att-gray-500);font-size:0.75rem;">
                    No recent attendance punches recorded yet.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Bottom Actions: Change PIN & Logout -->
    <div style="display:flex;justify-content:space-between;align-items:center;padding-top:0.25rem;font-size:0.75rem;">
        <button
            type="button"
            @click="showPinChangeModal = true"
            style="background:none;border:none;color:var(--att-navy);font-weight:700;cursor:pointer;text-decoration:underline;">
            Change My PIN
        </button>

        <button
            type="button"
            onclick="handleAttendanceLogout()"
            style="background:none;border:none;color:#dc2626;font-weight:700;cursor:pointer;text-decoration:underline;">
            Sign Out
        </button>
    </div>

    <!-- ========================================================================= -->
    <!-- CHANGE PIN MODAL -->
    <!-- ========================================================================= -->
    <div
        x-show="showPinChangeModal"
        x-cloak
        style="position:fixed;inset:0;background:rgba(0,15,43,0.7);z-index:90;display:flex;align-items:flex-end;justify-content:center;">

        <div
            @click.away="showPinChangeModal = false"
            style="width:100%;max-width:440px;background:#ffffff;border-radius:1.5rem 1.5rem 0 0;padding:1.5rem;display:flex;flex-direction:column;gap:1rem;animation:att-slide-up 0.25s ease-out;">

            <div style="display:flex;align-items:center;justify-content:space-between;">
                <h3 style="font-size:1.05rem;font-weight:800;color:var(--att-navy);margin:0;">Change Attendance PIN</h3>
                <button type="button" @click="showPinChangeModal = false" style="border:none;background:none;font-size:1.25rem;cursor:pointer;">✕</button>
            </div>

            <div class="att-form-group">
                <label class="att-label">Current PIN</label>
                <input type="password" x-model="pinForm.current_pin" maxlength="8" class="att-input font-mono" inputmode="numeric" placeholder="••••">
            </div>

            <div class="att-form-group">
                <label class="att-label">New PIN (4-8 digits)</label>
                <input type="password" x-model="pinForm.new_pin" maxlength="8" class="att-input font-mono" inputmode="numeric" placeholder="••••">
            </div>

            <div class="att-form-group">
                <label class="att-label">Confirm New PIN</label>
                <input type="password" x-model="pinForm.new_pin_confirmation" maxlength="8" class="att-input font-mono" inputmode="numeric" placeholder="••••">
            </div>

            <template x-if="pinChangeError">
                <div style="font-size:0.75rem;color:var(--att-rose);font-weight:600;" x-text="pinChangeError"></div>
            </template>

            <button
                type="button"
                @click="submitPinChange"
                :disabled="pinLoading"
                class="att-btn att-btn-primary"
                style="min-height:3rem;font-size:0.95rem;font-weight:800;">
                <span x-show="!pinLoading">Update Attendance PIN</span>
                <span x-show="pinLoading">Updating PIN...</span>
            </button>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
function attendanceDashboard(initialStatus, settings, canPunchAnywhere = false) {
    return {
        status: initialStatus,
        settings: settings,
        canPunchAnywhere: !!canPunchAnywhere,
        greetingText: 'Good Day',
        liveTime: '',
        liveDate: '',
        shiftDuration: '0h 0m',
        punchLoading: false,
        punchType: null,
        punchErrorMessage: null,
        toastMessage: null,
        toastType: 'success',
        showPinChangeModal: false,
        pinLoading: false,
        pinChangeError: null,
        pinForm: { current_pin: '', new_pin: '', new_pin_confirmation: '' },
        heartbeatTimer: null,
        clockInterval: null,

        init() {
            this.updateGreeting();
            this.updateClock();
            this.updateDuration();
            this.clockInterval = setInterval(() => {
                this.updateClock();
                this.updateDuration();
            }, 1000);

            // Fetch latest authoritative state from server to guarantee sync
            this.refreshServerStatus();

            // Heartbeat
            const hbSeconds = (this.settings.heartbeat_interval_seconds || 180) * 1000;
            this.heartbeatTimer = setInterval(() => this.sendHeartbeat(), hbSeconds);
        },

        updateGreeting() {
            const h = new Date().getHours();
            if (h < 12) this.greetingText = 'Good morning';
            else if (h < 17) this.greetingText = 'Good afternoon';
            else this.greetingText = 'Good evening';
        },

        updateClock() {
            const now = new Date();
            this.liveTime = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.liveDate = now.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
        },

        updateDuration() {
            if (this.status.state === 'checked_in' && this.status.check_in_iso) {
                const start = new Date(this.status.check_in_iso).getTime();
                const now = Date.now();
                const diffMs = Math.max(0, now - start);
                const totalMinutes = Math.floor(diffMs / 60000);
                const hours = Math.floor(totalMinutes / 60);
                const mins = totalMinutes % 60;
                this.shiftDuration = `${hours}h ${mins}m`;
            }
        },

        showToast(msg, type = 'success') {
            this.toastMessage = msg;
            this.toastType = type;
            setTimeout(() => { this.toastMessage = null; }, 4000);
        },

        async refreshServerStatus() {
            try {
                const res = await fetch('/attendance/api/status', {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data.state) {
                        this.status = data.state;
                        this.updateDuration();
                    }
                }
            } catch (e) {}
        },

        async sendPunchRequest(type, lat, lon, acc) {
            const endpoint = type === 'check_in' ? '/attendance/api/check-in' : '/attendance/api/check-out';
            const idempotencyKey = `${Date.now()}_${Math.random().toString(36).substring(2, 9)}`;

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        latitude: lat,
                        longitude: lon,
                        accuracy_meters: acc,
                        client_captured_at: new Date().toISOString(),
                        idempotency_key: idempotencyKey,
                        verification_method: 'pin'
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    this.punchErrorMessage = data.errors?.geofence?.[0] || data.errors?.gps?.[0] || data.error || data.message || 'Location verification failed.';
                    this.punchLoading = false;
                    return;
                }

                // State updated authoritatively by server
                this.status = data.state;
                this.updateDuration();
                this.showToast(data.message || (type === 'check_in' ? 'Clocked in successfully!' : 'Clocked out successfully!'));
                if (navigator.vibrate) navigator.vibrate([40, 30, 40]);
                this.punchLoading = false;
                this.punchErrorMessage = null;
            } catch (err) {
                this.punchLoading = false;
                this.punchErrorMessage = 'Network error while recording attendance. Please retry.';
            }
        },

        punch(type) {
            this.punchLoading = true;
            this.punchType = type;
            this.punchErrorMessage = null;

            if (!navigator.geolocation) {
                if (this.canPunchAnywhere) {
                    this.sendPunchRequest(type, null, null, null);
                    return;
                }
                this.punchLoading = false;
                this.punchErrorMessage = 'Geolocation is not supported on this device/browser.';
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const lat = pos.coords.latitude;
                    const lon = pos.coords.longitude;
                    const acc = Math.round(pos.coords.accuracy);

                    const maxAcc = this.settings?.max_gps_accuracy_meters || 100;
                    if (acc > maxAcc && !this.canPunchAnywhere) {
                        this.punchLoading = false;
                        this.punchErrorMessage = `GPS accuracy is too low (measured: ±${acc}m, required: within ${maxAcc}m). Please move outdoors or near a window and try again.`;
                        return;
                    }

                    this.sendPunchRequest(type, lat, lon, acc);
                },
                (err) => {
                    if (this.canPunchAnywhere) {
                        // Remote authorized employee can punch even if GPS is unavailable
                        this.sendPunchRequest(type, null, null, null);
                        return;
                    }

                    this.punchLoading = false;
                    if (err.code === 1 || err.code === err.PERMISSION_DENIED) {
                        this.punchErrorMessage = 'Location permission is required to record attendance. Please enable Location access in your device settings.';
                    } else if (err.code === 2 || err.code === err.POSITION_UNAVAILABLE) {
                        this.punchErrorMessage = 'GPS position unavailable. Please ensure location services are turned on.';
                    } else if (err.code === 3 || err.code === err.TIMEOUT) {
                        this.punchErrorMessage = 'GPS request timed out. Please try again.';
                    } else {
                        this.punchErrorMessage = err.message || 'Unable to retrieve GPS coordinates.';
                    }
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        },

        async sendHeartbeat() {
            if (this.status.state !== 'checked_in') return;
            if (!navigator.geolocation) return;

            navigator.geolocation.getCurrentPosition(async (pos) => {
                try {
                    await fetch('/attendance/api/heartbeat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            latitude: pos.coords.latitude,
                            longitude: pos.coords.longitude,
                            accuracy_meters: Math.round(pos.coords.accuracy),
                        })
                    });
                } catch (e) {}
            }, null, { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 });
        },

        async submitPinChange() {
            this.pinChangeError = null;
            if (this.pinForm.new_pin !== this.pinForm.new_pin_confirmation) {
                this.pinChangeError = 'New PIN and confirmation do not match.';
                return;
            }

            this.pinLoading = true;
            try {
                const res = await fetch('/attendance/api/auth/change-pin', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.pinForm)
                });

                const data = await res.json();
                if (!res.ok) {
                    this.pinChangeError = data.errors?.current_pin?.[0] || data.errors?.new_pin?.[0] || data.error || 'PIN update failed.';
                    return;
                }

                this.showPinChangeModal = false;
                this.pinForm = { current_pin: '', new_pin: '', new_pin_confirmation: '' };
                this.showToast('Attendance PIN updated successfully!');
            } catch (e) {
                this.pinChangeError = 'Network error while updating PIN.';
            } finally {
                this.pinLoading = false;
            }
        }
    };
}
</script>
<style>
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.3); }
}
</style>
@endsection
