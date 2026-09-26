@extends('attendance.layout')

@section('title', 'Sign In & Device Pairing — Laijau Attendance')

@section('content')
<div x-data="attendanceLogin({{ $device ? 'true' : 'false' }})" class="att-login-wrap" style="width:100%;max-width:440px;margin:0 auto;display:flex;flex-direction:column;gap:1.25rem;">

    <!-- Top Branding -->
    <div style="text-align:center;padding:1rem 0 0.25rem 0;">
        <div style="width:3.25rem;height:3.25rem;border-radius:1rem;background:#001b48;color:#ffffff;display:inline-flex;align-items:center;justify-content:center;font-size:1.35rem;font-weight:900;box-shadow:0 8px 20px -4px rgba(0,27,72,0.25);margin-bottom:0.75rem;">
            LJ
        </div>
        <h2 style="font-size:1.25rem;font-weight:900;color:#001b48;margin:0;letter-spacing:-0.02em;" x-text="mode === 'pair' ? 'Register Attendance Device' : 'Attendance Sign In'">
            Attendance Sign In
        </h2>
        <p style="font-size:0.75rem;color:#64748b;margin-top:0.35rem;line-height:1.4;" x-text="mode === 'pair' ? 'One-time device pairing for showroom staff. Once paired, attendance access is persistent.' : 'Enter your attendance PIN to access your staff dashboard.'">
        </p>
    </div>

    <!-- Error Banner -->
    <template x-if="errorMessage">
        <div style="padding:0.75rem 1rem;background:#fee2e2;border:1px solid #f87171;border-radius:0.75rem;color:#991b1b;font-size:0.8125rem;font-weight:600;display:flex;align-items:center;justify-content:space-between;gap:0.5rem;">
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <span>⚠️</span>
                <span x-text="errorMessage"></span>
            </div>
            <button type="button" @click="errorMessage = null" style="background:none;border:none;color:#991b1b;font-weight:800;font-size:1rem;cursor:pointer;">×</button>
        </div>
    </template>

    <!-- ========================================================================= -->
    <!-- VIEW A: PIN UNLOCK (When Device is Recognized) -->
    <!-- ========================================================================= -->
    <div x-show="mode === 'unlock'" style="display:flex;flex-direction:column;gap:1.25rem;">
        @if($device)
            <div class="att-card" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.875rem;padding:1.25rem;text-align:center;">
                <div style="width:3rem;height:3rem;border-radius:9999px;background:#001b48;color:#ffffff;display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:900;margin:0 auto 0.5rem;">
                    {{ substr($device->employee->first_name ?? 'E', 0, 1) }}
                </div>
                <h3 style="font-size:1.05rem;font-weight:800;color:#001b48;margin:0;">
                    {{ $device->employee->full_name ?? 'Employee' }}
                </h3>
                <div style="font-size:0.75rem;color:#64748b;font-family:monospace;margin-top:0.25rem;">
                    {{ $device->employee->employee_number ?? '' }} &bull; {{ $device->device_name }}
                </div>
            </div>
        @endif

        <div class="att-card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:0.875rem;padding:1.5rem;box-shadow:0 4px 15px -3px rgba(0,0,0,0.05);display:flex;flex-direction:column;gap:1.15rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <h4 style="font-size:0.95rem;font-weight:800;color:#001b48;margin:0;">Enter Attendance PIN</h4>
                <span class="att-badge att-badge-navy" style="font-size:0.6875rem;padding:0.15rem 0.5rem;border-radius:9999px;background:#e0e7ff;color:#3730a3;font-weight:700;">Secure</span>
            </div>

            <div class="att-form-group">
                <input
                    type="password"
                    x-model="pin"
                    @keydown.enter.prevent="submitPin()"
                    maxlength="8"
                    class="att-input font-mono"
                    style="text-align:center;font-size:1.5rem;letter-spacing:0.25em;height:3.5rem;width:100%;border:1px solid #cbd5e1;border-radius:0.5rem;"
                    placeholder="••••"
                    inputmode="numeric"
                    autocomplete="current-password"
                    autofocus>
            </div>

            <button
                type="button"
                @click="submitPin()"
                :disabled="loading || pin.length < 4"
                class="att-btn att-btn-primary"
                style="width:100%;min-height:3rem;border-radius:0.5rem;background:#001b48;color:#ffffff;font-size:0.95rem;font-weight:800;border:none;cursor:pointer;">
                <span x-show="!loading">Unlock Dashboard →</span>
                <span x-show="loading">Authenticating...</span>
            </button>
        </div>

        <div style="text-align:center;padding:0.5rem 0;">
            <button
                type="button"
                @click="setMode('pair')"
                style="background:none;border:none;font-size:0.8125rem;color:#001b48;font-weight:700;text-decoration:underline;cursor:pointer;">
                Pair a Different Device or Reset Pairing &rarr;
            </button>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- VIEW B: DEVICE PAIRING FORM (When Device Not Recognized or Registering) -->
    <!-- ========================================================================= -->
    <div x-show="mode === 'pair'" style="display:flex;flex-direction:column;gap:1.25rem;">
        <div class="att-card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:0.875rem;padding:1.5rem;box-shadow:0 4px 15px -3px rgba(0,0,0,0.05);display:flex;flex-direction:column;gap:1.15rem;">
            
            <div style="display:flex;align-items:center;justify-content:space-between;padding-bottom:0.75rem;border-bottom:1px solid #f1f5f9;">
                <span style="font-size:0.8125rem;font-weight:800;color:#0f172a;text-transform:uppercase;letter-spacing:0.04em;">Employee Verification</span>
                <span class="att-badge att-badge-navy" style="font-size:0.6875rem;padding:0.15rem 0.5rem;border-radius:9999px;background:#e0e7ff;color:#3730a3;font-weight:700;">One-Time Pairing</span>
            </div>

            <!-- 1. Employee ID -->
            <div class="att-form-group" style="display:flex;flex-direction:column;gap:0.35rem;">
                <label class="att-label" style="font-size:0.75rem;font-weight:700;color:#334155;">Employee ID *</label>
                <input
                    type="text"
                    x-model="pairForm.employee_number"
                    @input="clearPairError('employee_number')"
                    class="att-input font-mono"
                    style="width:100%;height:2.85rem;padding:0.5rem 0.85rem;border:1px solid #cbd5e1;border-radius:0.5rem;font-size:0.95rem;text-transform:uppercase;"
                    placeholder="e.g. LJ-EMP-001 or 001"
                    autocomplete="off"
                    autocapitalize="characters">
                <span class="att-input-error" style="color:#dc2626;font-size:0.6875rem;font-weight:600;" x-show="pairErrors.employee_number" x-text="pairErrors.employee_number"></span>
            </div>

            <!-- 2. Registered Phone Number -->
            <div class="att-form-group" style="display:flex;flex-direction:column;gap:0.35rem;">
                <label class="att-label" style="font-size:0.75rem;font-weight:700;color:#334155;">Registered Phone Number *</label>
                <input
                    type="tel"
                    x-model="pairForm.phone"
                    @input="clearPairError('phone')"
                    class="att-input font-mono"
                    style="width:100%;height:2.85rem;padding:0.5rem 0.85rem;border:1px solid #cbd5e1;border-radius:0.5rem;font-size:0.95rem;"
                    placeholder="e.g. 98XXXXXXXX"
                    autocomplete="tel">
                <span class="att-input-error" style="color:#dc2626;font-size:0.6875rem;font-weight:600;" x-show="pairErrors.phone" x-text="pairErrors.phone"></span>
            </div>

            <!-- 3. Admin PIN -->
            <div class="att-form-group" style="display:flex;flex-direction:column;gap:0.35rem;">
                <label class="att-label" style="font-size:0.75rem;font-weight:700;color:#334155;">Attendance Admin PIN *</label>
                <input
                    type="password"
                    x-model="pairForm.pin"
                    @input="clearPairError('pin')"
                    maxlength="8"
                    class="att-input font-mono"
                    style="width:100%;height:2.85rem;padding:0.5rem 0.85rem;border:1px solid #cbd5e1;border-radius:0.5rem;font-size:1.15rem;letter-spacing:0.15em;"
                    placeholder="••••"
                    inputmode="numeric"
                    autocomplete="current-password">
                <div style="font-size:0.6875rem;color:#64748b;">4 to 8 numeric digits assigned by your manager</div>
                <span class="att-input-error" style="color:#dc2626;font-size:0.6875rem;font-weight:600;" x-show="pairErrors.pin" x-text="pairErrors.pin"></span>
            </div>

            <!-- Primary Action Button -->
            <button
                type="button"
                @click="pairDevice()"
                :disabled="loading || !pairForm.employee_number || !pairForm.phone || pairForm.pin.length < 4"
                class="att-btn att-btn-emerald"
                style="width:100%;min-height:3.25rem;border-radius:0.5rem;background:#059669;color:#ffffff;font-size:0.95rem;font-weight:800;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.5rem;box-shadow:0 4px 12px rgba(5,150,105,0.25);transition:all 0.15s ease;margin-top:0.25rem;">
                <svg x-show="loading" style="animation:spin 1s linear infinite;width:18px;height:18px;" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity:0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path style="opacity:0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-show="!loading">Verify &amp; Continue to Attendance &rarr;</span>
                <span x-show="loading">Verifying &amp; Pairing Device...</span>
            </button>
        </div>

        @if($device)
            <div style="text-align:center;padding:0.25rem 0;">
                <button
                    type="button"
                    @click="setMode('unlock')"
                    style="background:none;border:none;font-size:0.8125rem;color:#001b48;font-weight:700;text-decoration:underline;cursor:pointer;">
                    &larr; Back to PIN Unlock
                </button>
            </div>
        @endif

        <div style="text-align:center;padding:0.25rem 0.5rem;">
            <div style="font-size:0.6875rem;color:#64748b;line-height:1.5;">
                🔒 Device pairing utilizes secure cryptographic hardware tokens. GPS location is only checked when punching attendance.
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
function attendanceLogin(hasRecognizedDevice) {
    return {
        mode: hasRecognizedDevice ? 'unlock' : 'pair',
        pin: '',
        loading: false,
        errorMessage: null,
        pairErrors: {},
        pairForm: {
            employee_number: '',
            phone: '',
            pin: '',
        },

        setMode(newMode) {
            this.mode = newMode;
            this.errorMessage = null;
        },

        clearPairError(field) {
            delete this.pairErrors[field];
            if (Object.keys(this.pairErrors).length === 0) {
                this.errorMessage = null;
            }
        },

        async submitPin() {
            if (this.pin.length < 4) return;

            this.loading = true;
            this.errorMessage = null;

            const cachedToken = localStorage.getItem('laijau_att_token');

            try {
                const res = await fetch('/attendance/api/auth/pin', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        ...(cachedToken ? { 'X-Device-Token': cachedToken } : {})
                    },
                    body: JSON.stringify({
                        pin: this.pin,
                        device_token: cachedToken,
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    if (data.requires_setup) {
                        this.setMode('pair');
                        this.errorMessage = data.error || 'Please pair this device first.';
                        return;
                    }
                    this.errorMessage = data.errors?.pin?.[0] || data.error || 'Authentication failed.';
                    this.pin = '';
                    return;
                }

                window.location.href = data.redirect || '/attendance';
            } catch (e) {
                this.errorMessage = 'Network connection failed. Please retry.';
            } finally {
                this.loading = false;
            }
        },

        async pairDevice() {
            this.pairErrors = {};
            this.errorMessage = null;

            if (!this.pairForm.employee_number.trim()) {
                this.pairErrors.employee_number = 'Employee ID is required.';
                return;
            }
            if (!this.pairForm.phone.trim()) {
                this.pairErrors.phone = 'Registered phone number is required.';
                return;
            }
            if (this.pairForm.pin.length < 4) {
                this.pairErrors.pin = 'PIN must be at least 4 digits.';
                return;
            }

            this.loading = true;

            const platformName = /iPhone|iPad|iPod/.test(navigator.userAgent) ? 'iOS' : (/Android/.test(navigator.userAgent) ? 'Android' : 'Desktop');
            const browserName = navigator.userAgent.includes('Chrome') ? 'Chrome' : (navigator.userAgent.includes('Safari') ? 'Safari' : 'Browser');
            const deviceName = navigator.userAgentData?.platform || navigator.platform || (platformName + ' Device');

            try {
                const response = await fetch('/attendance/api/setup', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        employee_number: this.pairForm.employee_number.trim(),
                        phone: this.pairForm.phone.trim(),
                        pin: this.pairForm.pin,
                        device_name: deviceName,
                        platform: platformName,
                        browser: browserName,
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    if (response.status === 422 && data.errors) {
                        this.pairErrors = {};
                        for (const key in data.errors) {
                            this.pairErrors[key] = data.errors[key][0];
                        }
                        this.errorMessage = Object.values(data.errors)[0][0] || 'Validation error.';
                    } else {
                        this.errorMessage = data.error || data.message || 'Verification failed. Please check your credentials.';
                    }
                    this.loading = false;
                    return;
                }

                // Persist device token securely to localStorage as fallback
                if (data.device_token) {
                    try {
                        localStorage.setItem('laijau_att_token', data.device_token);
                    } catch (e) {
                        console.warn('Could not save token to localStorage:', e);
                    }
                }

                // Smoothly proceed to Attendance Dashboard
                window.location.href = data.redirect || '/attendance';
            } catch (err) {
                this.loading = false;
                this.errorMessage = 'Network connection error. Please verify your connection and try again.';
            }
        }
    };
}
</script>
@endsection
