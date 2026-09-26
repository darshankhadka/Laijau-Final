@extends('attendance.layout')

@section('title', 'Attendance Dashboard — Laijau')

@section('content')
<div x-data="attendanceDashboard({{ json_encode($status) }}, {{ json_encode($settings) }})" class="att-dash-wrap" style="display:flex;flex-direction:column;gap:1.25rem;">

    <!-- Greeting & Live Time Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;">
        <div>
            <div style="font-size:0.75rem;font-weight:700;color:var(--att-gray-500);text-transform:uppercase;letter-spacing:0.04em;">
                <span x-text="greetingText">Good Day</span>,
            </div>
            <h2 style="font-size:1.35rem;font-weight:900;color:var(--att-navy);letter-spacing:-0.03em;margin:0;">
                {{ $employee->first_name }}
            </h2>
            <div style="font-size:0.6875rem;color:var(--att-gray-500);font-family:monospace;">
                {{ $employee->employee_number }} &bull; {{ $defaultLocation->name ?? 'Showroom' }}
            </div>
        </div>

        <div style="text-align:right;">
            <div style="font-size:1.25rem;font-weight:900;font-family:monospace;color:var(--att-navy);line-height:1;" x-text="liveTime">
                --:-- --
            </div>
            <div style="font-size:0.6875rem;color:var(--att-gray-500);margin-top:0.25rem;" x-text="liveDate">
                {{ now()->format('M d, Y') }}
            </div>
        </div>
    </div>

    <!-- Status Toast Notification -->
    <template x-if="toastMessage">
        <div class="att-toast" :class="toastType">
            <span x-text="toastType === 'success' ? '✓' : '⚠️'"></span>
            <span x-text="toastMessage"></span>
        </div>
    </template>

    <!-- TODAY'S ATTENDANCE STATUS CARD -->
    <div class="att-card" style="position:relative;overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <div style="font-size:0.6875rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--att-gray-500);">
                Today's Attendance
            </div>

            <!-- Dynamic State Badge -->
            <template x-if="status.state === 'not_checked_in'">
                <span class="att-badge att-badge-amber">Not Checked In</span>
            </template>
            <template x-if="status.state === 'checked_in'">
                <span class="att-badge att-badge-emerald">Checked In</span>
            </template>
            <template x-if="status.state === 'checked_out'">
                <span class="att-badge att-badge-navy">Shift Complete</span>
            </template>
        </div>

        <!-- Timestamps Row -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;padding:0.75rem 0;border-top:1px solid var(--att-gray-100);border-bottom:1px solid var(--att-gray-100);">
            <div>
                <div style="font-size:0.65rem;font-weight:700;color:var(--att-gray-500);text-transform:uppercase;">Check In</div>
                <div style="font-size:1.1rem;font-weight:800;color:var(--att-navy);font-family:monospace;margin-top:0.15rem;">
                    <span x-text="status.check_in_time || '--:--'"></span>
                </div>
            </div>
            <div>
                <div style="font-size:0.65rem;font-weight:700;color:var(--att-gray-500);text-transform:uppercase;">Check Out</div>
                <div style="font-size:1.1rem;font-weight:800;color:var(--att-navy);font-family:monospace;margin-top:0.15rem;">
                    <span x-text="status.check_out_time || '--:--'"></span>
                </div>
            </div>
        </div>

        <!-- Location & Status Verification Notice -->
        <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.75rem;color:var(--att-gray-600);">
            <span style="display:flex;align-items:center;gap:0.35rem;">
                <span>📍</span>
                <span>{{ $defaultLocation->name ?? 'Laijau Showroom' }}</span>
            </span>

            <template x-if="status.state === 'checked_in'">
                <span style="color:var(--att-emerald);font-weight:700;display:flex;align-items:center;gap:0.25rem;">
                    <span style="width:6px;height:6px;border-radius:9999px;background:currentColor;"></span>
                    Active Shift
                </span>
            </template>
        </div>
    </div>

    <!-- PRIMARY ACTION BUTTON -->
    <div style="display:flex;flex-direction:column;gap:0.5rem;">
        <!-- 1. CHECK IN BUTTON -->
        <template x-if="status.state === 'not_checked_in'">
            <button
                type="button"
                @click="punchAttendance('check_in')"
                :disabled="punchLoading"
                class="att-btn att-btn-emerald"
                style="min-height:3.75rem;font-size:1.1rem;font-weight:800;letter-spacing:0.02em;">
                <svg x-show="!punchLoading" width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                <svg x-show="punchLoading" style="animation:spin 1s linear infinite;width:20px;height:20px;" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity:0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path style="opacity:0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="punchLoading ? 'Verifying Location & Checking In...' : 'CHECK IN'"></span>
            </button>
        </template>

        <!-- 2. CHECK OUT BUTTON -->
        <template x-if="status.state === 'checked_in'">
            <button
                type="button"
                @click="punchAttendance('check_out')"
                :disabled="punchLoading"
                class="att-btn att-btn-rose"
                style="min-height:3.75rem;font-size:1.1rem;font-weight:800;letter-spacing:0.02em;">
                <svg x-show="!punchLoading" width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                <svg x-show="punchLoading" style="animation:spin 1s linear infinite;width:20px;height:20px;" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity:0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path style="opacity:0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="punchLoading ? 'Verifying Location & Checking Out...' : 'CHECK OUT'"></span>
            </button>
        </template>

        <!-- 3. SHIFT COMPLETE STATE -->
        <template x-if="status.state === 'checked_out'">
            <button
                type="button"
                disabled
                class="att-btn"
                style="min-height:3.75rem;background:var(--att-gray-100);color:var(--att-gray-500);border-color:var(--att-gray-200);font-size:0.95rem;font-weight:700;">
                <span>✓ Shift Completed for Today</span>
            </button>
        </template>

        <!-- Error Banner (GPS or Geofence notice) -->
        <template x-if="punchErrorMessage">
            <div style="padding:0.75rem 1rem;border-radius:var(--att-radius);background:#fef2f2;border:1px solid #fecaca;color:#991b1b;display:flex;align-items:flex-start;gap:0.75rem;font-size:0.75rem;">
                <div style="font-size:1.25rem;line-height:1;">⚠️</div>
                <div style="flex:1;">
                    <div style="font-weight:800;font-size:0.8125rem;">Location Verification Notice</div>
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
            Location verified upon punch &bull; No camera or photo required
        </div>
    </div>

    <!-- RECENT ATTENDANCE HISTORY -->
    <div class="att-card">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <h4 style="font-size:0.875rem;font-weight:800;color:var(--att-navy);margin:0;">Recent History</h4>
            <span style="font-size:0.6875rem;color:var(--att-gray-500);font-weight:600;">Personal Records</span>
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

    <!-- Bottom Actions -->
    <div style="display:flex;justify-content:space-between;align-items:center;padding-top:0.5rem;font-size:0.75rem;">
        <button
            type="button"
            @click="showPinChangeModal = true"
            style="background:none;border:none;color:var(--att-navy);font-weight:700;cursor:pointer;text-decoration:underline;">
            Change My PIN
        </button>

        <span style="color:var(--att-gray-500);font-size:0.6875rem;">
            Device: {{ $device->device_name ?? 'Mobile Device' }}
        </span>
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
            style="width:100%;max-width:440px;background:#ffffff;border-radius:1.5rem 1.5rem 0 0;padding:1.5rem;display:flex;flex-direction:column;gap:1rem;">

            <div style="display:flex;align-items:center;justify-content:space-between;">
                <h3 style="font-size:1rem;font-weight:800;color:var(--att-navy);margin:0;">Change Attendance PIN</h3>
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
                @click="submitPinChange()"
                :disabled="pinLoading"
                class="att-btn att-btn-primary">
                <span x-show="!pinLoading">Update Attendance PIN</span>
                <span x-show="pinLoading">Updating PIN...</span>
            </button>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
function attendanceDashboard(initialStatus, settings) {
    return {
        status: initialStatus,
        settings: settings,
        greetingText: 'Good Day',
        liveTime: '',
        liveDate: '',
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

        init() {
            this.updateGreeting();
            this.updateClock();
            setInterval(() => this.updateClock(), 1000);

            // Fetch latest server-side status to ensure perfect synchronization
            this.refreshServerStatus();

            // Periodic location heartbeat while active in foreground (every 3 minutes)
            const hbInterval = (this.settings.heartbeat_interval_seconds || 180) * 1000;
            this.heartbeatTimer = setInterval(() => this.sendHeartbeat(), hbInterval);
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

        showToast(msg, type = 'success') {
            this.toastMessage = msg;
            this.toastType = type;
            setTimeout(() => { this.toastMessage = null; }, 4000);
        },

        async refreshServerStatus() {
            try {
                const cachedToken = localStorage.getItem('laijau_att_token');
                const res = await fetch('/attendance/api/status', {
                    headers: {
                        'Accept': 'application/json',
                        ...(cachedToken ? { 'X-Device-Token': cachedToken } : {})
                    }
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data.state) {
                        this.status = data.state;
                    }
                }
            } catch (e) {
                // Keep initial server-rendered status
            }
        },

        punchAttendance(type) {
            this.punchLoading = true;
            this.punchType = type;
            this.punchErrorMessage = null;

            if (!navigator.geolocation) {
                this.punchLoading = false;
                this.punchErrorMessage = 'Geolocation is not supported by your browser or device.';
                return;
            }

            // GPS is requested ONLY when Clock In or Clock Out is pressed
            navigator.geolocation.getCurrentPosition(
                async (pos) => {
                    const lat = pos.coords.latitude;
                    const lon = pos.coords.longitude;
                    const acc = Math.round(pos.coords.accuracy);

                    const maxAcc = this.settings?.max_gps_accuracy_meters || 100;
                    if (acc > maxAcc) {
                        this.punchLoading = false;
                        this.punchErrorMessage = `GPS accuracy is too low (measured: ±${acc}m, required: within ${maxAcc}m). Please move to an open area and try again.`;
                        return;
                    }

                    const endpoint = type === 'check_in' ? '/attendance/api/check-in' : '/attendance/api/check-out';
                    const cachedToken = localStorage.getItem('laijau_att_token');
                    const idempotencyKey = `${Date.now()}_${Math.random().toString(36).substring(2, 9)}`;

                    try {
                        const res = await fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                'Accept': 'application/json',
                                ...(cachedToken ? { 'X-Device-Token': cachedToken } : {})
                            },
                            body: JSON.stringify({
                                latitude: lat,
                                longitude: lon,
                                accuracy_meters: acc,
                                client_captured_at: new Date().toISOString(),
                                idempotency_key: idempotencyKey,
                                verification_method: 'gps_verified'
                            })
                        });

                        const data = await res.json();
                        if (!res.ok) {
                            this.punchErrorMessage = data.errors?.geofence?.[0] || data.errors?.gps?.[0] || data.error || data.message || 'Location verification failed.';
                            this.punchLoading = false;
                            return;
                        }

                        // Persist authoritative attendance state returned from server
                        this.status = data.state;
                        this.showToast(data.message || (type === 'check_in' ? 'Checked in successfully!' : 'Checked out successfully!'));
                        if (navigator.vibrate) navigator.vibrate([60, 40, 60]);
                        this.punchLoading = false;
                        this.punchErrorMessage = null;
                    } catch (err) {
                        this.punchLoading = false;
                        this.punchErrorMessage = 'Network error while recording attendance. Please retry.';
                    }
                },
                (err) => {
                    this.punchLoading = false;
                    if (err.code === 1 || err.code === err.PERMISSION_DENIED) {
                        this.punchErrorMessage = 'Location permission is required to record attendance. Please enable Location in your device settings.';
                    } else if (err.code === 2 || err.code === err.POSITION_UNAVAILABLE) {
                        this.punchErrorMessage = 'GPS position unavailable. Please ensure location services are turned on.';
                    } else if (err.code === 3 || err.code === err.TIMEOUT) {
                        this.punchErrorMessage = 'GPS request timed out. Please move to an open area and try again.';
                    } else {
                        this.punchErrorMessage = err.message || 'Unable to retrieve GPS coordinates.';
                    }
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
            );
        },

        async sendHeartbeat() {
            if (this.status.state !== 'checked_in') return;
            if (!navigator.geolocation) return;

            navigator.geolocation.getCurrentPosition(async (pos) => {
                try {
                    const cachedToken = localStorage.getItem('laijau_att_token');
                    await fetch('/attendance/api/heartbeat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Accept': 'application/json',
                            ...(cachedToken ? { 'X-Device-Token': cachedToken } : {})
                        },
                        body: JSON.stringify({
                            latitude: pos.coords.latitude,
                            longitude: pos.coords.longitude,
                            accuracy_meters: Math.round(pos.coords.accuracy),
                        })
                    });
                } catch (e) {
                    // Silent heartbeat failure
                }
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
                const cachedToken = localStorage.getItem('laijau_att_token');
                const res = await fetch('/attendance/api/auth/change-pin', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'Accept': 'application/json',
                        ...(cachedToken ? { 'X-Device-Token': cachedToken } : {})
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
                this.showToast('PIN updated successfully!');
            } catch (e) {
                this.pinChangeError = 'Network error while updating PIN.';
            } finally {
                this.pinLoading = false;
            }
        }
    };
}
</script>
@endsection
