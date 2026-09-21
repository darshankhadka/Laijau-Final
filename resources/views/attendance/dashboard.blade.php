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

        <!-- Location & Verification Status -->
        <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.75rem;color:var(--att-gray-600);">
            <span style="display:flex;align-items:center;gap:0.35rem;">
                <span>📍</span>
                <span>{{ $defaultLocation->name ?? 'Laijau Showroom' }}</span>
            </span>

            <template x-if="status.state === 'checked_in'">
                <span style="color:var(--att-emerald);font-weight:700;display:flex;align-items:center;gap:0.25rem;">
                    <span style="width:6px;height:6px;border-radius:9999px;background:currentColor;"></span>
                    Location Verified
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
                @click="openPunchModal('check_in')"
                class="att-btn att-btn-emerald"
                style="min-height:3.75rem;font-size:1.1rem;font-weight:800;letter-spacing:0.02em;">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                <span>CHECK IN</span>
            </button>
        </template>

        <!-- 2. CHECK OUT BUTTON -->
        <template x-if="status.state === 'checked_in'">
            <button
                type="button"
                @click="openPunchModal('check_out')"
                class="att-btn att-btn-rose"
                style="min-height:3.75rem;font-size:1.1rem;font-weight:800;letter-spacing:0.02em;">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                <span>CHECK OUT</span>
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

        <!-- Home GPS Status Banner (Visible if permission denied or error detected) -->
        <template x-if="gpsErrorType">
            <div style="padding:0.75rem 1rem;border-radius:var(--att-radius);background:#fef2f2;border:1px solid #fecaca;color:#991b1b;display:flex;align-items:flex-start;gap:0.75rem;font-size:0.75rem;">
                <div style="font-size:1.25rem;line-height:1;">⚠️</div>
                <div style="flex:1;">
                    <div style="font-weight:800;font-size:0.8125rem;">Location Required for Attendance</div>
                    <div style="font-size:0.75rem;margin-top:0.25rem;line-height:1.4;" x-text="gpsErrorMessage"></div>
                </div>
                <button
                    type="button"
                    @click="requestGps()"
                    :disabled="gpsLoading"
                    class="att-btn att-btn-outline"
                    style="width:auto;min-height:2.25rem;padding:0.25rem 0.75rem;font-size:0.75rem;font-weight:800;white-space:nowrap;align-self:center;">
                    <span x-show="!gpsLoading">🔄 Retry Location</span>
                    <span x-show="gpsLoading">Locating...</span>
                </button>
            </div>
        </template>

        <div style="text-align:center;font-size:0.6875rem;color:var(--att-gray-500);">
            Requires mandatory GPS location & verification photo
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
            Device: {{ $device->device_name ?? 'Mobile' }}
        </span>
    </div>

    <!-- ========================================================================= -->
    <!-- ATTENDANCE PUNCH MODAL (CAMERA + GPS VERIFICATION) -->
    <!-- ========================================================================= -->
    <div
        x-show="showPunchModal"
        x-cloak
        style="position:fixed;inset:0;background:rgba(0,15,43,0.7);z-index:90;display:flex;align-items:flex-end;justify-content:center;">

        <div
            @click.away="closePunchModal()"
            style="width:100%;max-width:440px;background:#ffffff;border-radius:1.5rem 1.5rem 0 0;padding:1.5rem;max-height:90vh;overflow-y:auto;display:flex;flex-direction:column;gap:1rem;animation:att-slide-up 0.25s ease-out;">

            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <h3 style="font-size:1.1rem;font-weight:900;color:var(--att-navy);margin:0;" x-text="punchType === 'check_in' ? 'Check In Verification' : 'Check Out Verification'"></h3>
                    <p style="font-size:0.75rem;color:var(--att-gray-500);margin:0;">Take photo and verify GPS location</p>
                </div>
                <button type="button" @click="closePunchModal()" style="width:2rem;height:2rem;border-radius:9999px;background:var(--att-gray-100);border:none;cursor:pointer;font-size:1.1rem;font-weight:700;">✕</button>
            </div>

            <!-- GPS Status Banner -->
            <div style="padding:0.75rem 1rem;border-radius:var(--att-radius-sm);border:1px solid;display:flex;align-items:flex-start;gap:0.75rem;font-size:0.75rem;"
                 :style="gpsAcquired
                    ? 'background:#ecfdf5;border-color:#a7f3d0;color:#065f46;'
                    : (gpsErrorType ? 'background:#fef2f2;border-color:#fecaca;color:#991b1b;' : 'background:#fffbeb;border-color:#fde68a;color:#92400e;')">

                <div style="font-size:1.25rem;line-height:1;" x-text="gpsAcquired ? '📍' : (gpsErrorType ? '⚠️' : '⏳')"></div>

                <div style="flex:1;">
                    <div style="font-weight:800;font-size:0.8125rem;"
                         x-text="gpsAcquired ? 'Live GPS Position Verified' : (gpsLoading ? 'Acquiring GPS Position...' : 'GPS Verification Required')">
                    </div>
                    <div style="font-size:0.75rem;margin-top:0.25rem;line-height:1.4;" x-text="gpsDetails"></div>
                </div>

                <button
                    type="button"
                    @click="requestGps(punchType)"
                    :disabled="gpsLoading"
                    class="att-btn att-btn-outline"
                    style="width:auto;min-height:2.25rem;padding:0.25rem 0.75rem;font-size:0.75rem;font-weight:800;white-space:nowrap;align-self:center;">
                    <span x-show="!gpsLoading">🔄 Retry Location</span>
                    <span x-show="gpsLoading">Locating...</span>
                </button>
            </div>

            <!-- Camera Viewfinder / Preview Box -->
            <div style="position:relative;width:100%;max-width:280px;height:280px;margin:0 auto;border-radius:1.25rem;overflow:hidden;background:#0f172a;box-shadow:inset 0 2px 8px rgba(0,0,0,0.4);border:2px solid var(--att-gray-300);">
                <!-- Live Video Feed -->
                <video
                    x-ref="modalVideo"
                    autoplay
                    playsinline
                    muted
                    x-show="!modalPhoto"
                    style="width:100%;height:100%;object-fit:cover;transform:scaleX(-1);">
                </video>

                <!-- Captured Image Preview -->
                <img
                    :src="modalPhoto"
                    x-show="modalPhoto"
                    style="width:100%;height:100%;object-fit:cover;"
                    alt="Attendance Verification Snapshot">

                <div x-show="!modalPhoto" style="position:absolute;inset:20px;border:2px dashed rgba(255,255,255,0.4);border-radius:9999px;pointer-events:none;"></div>
            </div>

            <canvas x-ref="modalCanvas" style="display:none;"></canvas>

            <!-- Camera Snapshot Actions -->
            <div style="display:flex;gap:0.5rem;justify-content:center;">
                <button
                    type="button"
                    x-show="!modalPhoto"
                    @click="captureModalSnapshot()"
                    class="att-btn att-btn-emerald"
                    style="width:auto;min-width:180px;">
                    📸 Capture Photo
                </button>

                <button
                    type="button"
                    x-show="modalPhoto"
                    @click="retakeModalSnapshot()"
                    class="att-btn att-btn-outline"
                    style="width:auto;">
                    🔄 Retake
                </button>

                <label class="att-btn att-btn-outline" style="width:auto;cursor:pointer;" title="Choose photo from camera">
                    <span>📁 Gallery</span>
                    <input type="file" accept="image/*" capture="user" @change="handleModalFile($event)" style="display:none;">
                </label>
            </div>

            <!-- Error message if submission or geofence fails -->
            <template x-if="punchError">
                <div style="padding:0.75rem 1rem;background:#fee2e2;border:1px solid #f87171;border-radius:var(--att-radius);color:#991b1b;font-size:0.75rem;font-weight:600;">
                    <span x-text="punchError"></span>
                </div>
            </template>

            <!-- Final Submit Button -->
            <button
                type="button"
                @click="submitPunch()"
                :disabled="!modalPhoto || !gpsAcquired || punchLoading"
                class="att-btn"
                :class="punchType === 'check_in' ? 'att-btn-emerald' : 'att-btn-rose'"
                style="min-height:3.25rem;font-size:1rem;font-weight:800;">
                <span x-show="!punchLoading" x-text="punchType === 'check_in' ? 'Confirm Check In' : 'Confirm Check Out'"></span>
                <span x-show="punchLoading">Recording Attendance...</span>
            </button>
        </div>
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
        showPunchModal: false,
        punchType: 'check_in',
        punchLoading: false,
        punchError: null,
        modalPhoto: null,
        modalStream: null,
        gpsAcquired: false,
        gpsLoading: false,
        gpsCoords: null,
        gpsAccuracy: null,
        gpsErrorType: null, // 'permission_denied', 'position_unavailable', 'accuracy_low', 'timeout', 'unsupported'
        gpsErrorMessage: '',
        gpsDetails: 'Requesting location permission...',
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

            // Request GPS immediately to verify permissions
            this.requestGps();

            // Setup periodic location heartbeat while PWA is actively open (every 3 minutes)
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

        requestGps(actionType = null) {
            this.gpsAcquired = false;
            this.gpsLoading = true;
            this.gpsErrorType = null;
            this.gpsErrorMessage = '';
            this.gpsDetails = 'Acquiring GPS fix...';

            if (!navigator.geolocation) {
                this.gpsLoading = false;
                this.gpsErrorType = 'unsupported';
                this.gpsErrorMessage = 'Geolocation is not supported by your browser or device.';
                this.gpsDetails = this.gpsErrorMessage;
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.gpsLoading = false;
                    const lat = pos.coords.latitude;
                    const lon = pos.coords.longitude;
                    const acc = Math.round(pos.coords.accuracy);

                    if (lat === null || lon === null || isNaN(lat) || isNaN(lon) || lat < -90 || lat > 90 || lon < -180 || lon > 180) {
                        this.gpsAcquired = false;
                        this.gpsErrorType = 'invalid_coords';
                        this.gpsErrorMessage = 'GPS returned invalid coordinates. Please retry in an open area.';
                        this.gpsDetails = this.gpsErrorMessage;
                        return;
                    }

                    const maxAcc = this.settings?.max_gps_accuracy_meters || 100;
                    if (acc > maxAcc) {
                        this.gpsAcquired = false;
                        this.gpsErrorType = 'accuracy_low';
                        this.gpsAccuracy = acc;
                        this.gpsErrorMessage = `Location accuracy is too low (measured: ±${acc}m, required: within ${maxAcc}m). Please move to an open area and try again.`;
                        this.gpsDetails = this.gpsErrorMessage;
                        this.logGpsFailure(actionType || this.punchType || 'check_in', 'accuracy_low', this.gpsErrorMessage, acc);
                        return;
                    }

                    this.gpsAcquired = true;
                    this.gpsErrorType = null;
                    this.gpsErrorMessage = '';
                    this.gpsCoords = { latitude: lat, longitude: lon };
                    this.gpsAccuracy = acc;
                    this.gpsDetails = `Accuracy: ±${acc}m (Lat: ${lat.toFixed(4)}, Lon: ${lon.toFixed(4)})`;
                },
                (err) => {
                    this.gpsLoading = false;
                    this.gpsAcquired = false;
                    this.gpsCoords = null;
                    this.gpsAccuracy = null;

                    if (err.code === 1 || err.code === err.PERMISSION_DENIED) {
                        this.gpsErrorType = 'permission_denied';
                        this.gpsErrorMessage = 'Location permission is required to record attendance. Please enable Location access and try again.';
                    } else if (err.code === 2 || err.code === err.POSITION_UNAVAILABLE) {
                        this.gpsErrorType = 'position_unavailable';
                        this.gpsErrorMessage = 'Please turn on Location Services and try again.';
                    } else if (err.code === 3 || err.code === err.TIMEOUT) {
                        this.gpsErrorType = 'timeout';
                        this.gpsErrorMessage = 'GPS request timed out. Please move to an open area and try again.';
                    } else {
                        this.gpsErrorType = 'unknown';
                        this.gpsErrorMessage = err.message || 'Unable to determine your GPS location. Please retry.';
                    }
                    this.gpsDetails = this.gpsErrorMessage;

                    this.logGpsFailure(actionType || this.punchType || 'check_in', this.gpsErrorType, this.gpsErrorMessage);
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
            );
        },

        async logGpsFailure(actionType, errorType, message, accuracy = null) {
            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                if (!csrfMeta) return;

                await fetch('/attendance/api/log-gps-failure', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfMeta.getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        action_type: actionType,
                        error_type: errorType,
                        message: message,
                        accuracy: accuracy,
                    })
                });
            } catch (e) {
                // Silently ignore log network failure
            }
        },

        openPunchModal(type) {
            this.punchType = type;
            this.punchError = null;
            this.modalPhoto = null;
            this.showPunchModal = true;
            this.requestGps(type);
            this.$nextTick(() => this.startModalCamera());
        },

        closePunchModal() {
            this.showPunchModal = false;
            this.stopModalCamera();
        },

        async startModalCamera() {
            try {
                if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    this.modalStream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 640 } },
                        audio: false,
                    });
                    if (this.$refs.modalVideo) {
                        this.$refs.modalVideo.srcObject = this.modalStream;
                    }
                }
            } catch (e) {
                console.warn('Camera error:', e);
            }
        },

        stopModalCamera() {
            if (this.modalStream) {
                this.modalStream.getTracks().forEach(t => t.stop());
                this.modalStream = null;
            }
        },

        captureModalSnapshot() {
            const video = this.$refs.modalVideo;
            const canvas = this.$refs.modalCanvas;
            if (!video || !canvas) return;

            const size = Math.min(video.videoWidth || 480, video.videoHeight || 480, 600);
            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');
            ctx.translate(size, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0, size, size);

            this.modalPhoto = canvas.toDataURL('image/jpeg', 0.75);
            this.punchError = null;
        },

        retakeModalSnapshot() {
            this.modalPhoto = null;
            this.startModalCamera();
        },

        handleModalFile(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = this.$refs.modalCanvas;
                    const maxDim = 600;
                    let w = img.width;
                    let h = img.height;
                    if (w > maxDim || h > maxDim) {
                        if (w > h) { h = Math.round(h * (maxDim / w)); w = maxDim; }
                        else { w = Math.round(w * (maxDim / h)); h = maxDim; }
                    }
                    canvas.width = w;
                    canvas.height = h;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, w, h);
                    this.modalPhoto = canvas.toDataURL('image/jpeg', 0.75);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        },

        async submitPunch() {
            if (!this.modalPhoto) {
                this.punchError = 'Please capture an attendance verification photo.';
                return;
            }

            if (!this.gpsAcquired || !this.gpsCoords) {
                this.punchError = this.gpsErrorMessage || 'Location permission is required to record attendance. Please enable Location access and try again.';
                return;
            }

            this.punchLoading = true;
            this.punchError = null;

            // Generate unique idempotency key
            const idempotencyKey = `${Date.now()}_${Math.random().toString(36).substring(2, 9)}`;
            const endpoint = this.punchType === 'check_in' ? '/attendance/api/check-in' : '/attendance/api/check-out';

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        latitude: this.gpsCoords?.latitude,
                        longitude: this.gpsCoords?.longitude,
                        accuracy_meters: this.gpsAccuracy,
                        client_captured_at: new Date().toISOString(),
                        photo: this.modalPhoto,
                        idempotency_key: idempotencyKey,
                        verification_method: 'pin',
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    this.punchError = data.errors?.geofence?.[0] || data.errors?.gps?.[0] || data.errors?.photo?.[0] || data.error || data.message || 'Verification failed.';
                    return;
                }

                this.status = data.state;
                this.showToast(data.message || 'Attendance recorded successfully!');
                this.closePunchModal();
            } catch (e) {
                this.punchError = 'Network error while recording attendance. Please retry.';
            } finally {
                this.punchLoading = false;
            }
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
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
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
                const res = await fetch('/attendance/api/auth/change-pin', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
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
