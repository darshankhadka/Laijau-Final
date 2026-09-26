@extends('attendance.layout')

@section('title', 'Attendance Sign In — Laijau')

@section('content')
<div x-data="attendancePinLogin()" class="att-pin-wrap" style="width:100%;max-width:380px;margin:auto;display:flex;flex-direction:column;gap:1.5rem;align-items:center;">

    <!-- Brand Header -->
    <div style="text-align:center;display:flex;flex-direction:column;align-items:center;">
        <div style="width:3.75rem;height:3.75rem;border-radius:1.125rem;background:linear-gradient(135deg, #001b48 0%, #002566 100%);color:#ffffff;display:inline-flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:900;box-shadow:0 8px 24px -4px rgba(0,27,72,0.3);margin-bottom:0.875rem;">
            LJ
        </div>
        <h2 style="font-size:1.35rem;font-weight:900;color:#001b48;margin:0;letter-spacing:-0.03em;">
            Laijau Attendance
        </h2>
        <p style="font-size:0.8125rem;color:#64748b;margin-top:0.35rem;line-height:1.4;">
            Enter your employee attendance PIN to sign in
        </p>
    </div>

    <!-- Error Alert -->
    <template x-if="errorMessage">
        <div style="width:100%;padding:0.75rem 1rem;background:#fee2e2;border:1px solid #f87171;border-radius:0.75rem;color:#991b1b;font-size:0.8125rem;font-weight:600;display:flex;align-items:center;justify-content:space-between;gap:0.5rem;animation:att-slide-up 0.2s ease-out;">
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <span>⚠️</span>
                <span x-text="errorMessage"></span>
            </div>
            <button type="button" @click="errorMessage = null" style="background:none;border:none;color:#991b1b;font-weight:800;font-size:1.1rem;cursor:pointer;">×</button>
        </div>
    </template>

    <!-- PIN Card -->
    <div class="att-card" style="width:100%;background:#ffffff;border:1px solid #e2e8f0;border-radius:1.125rem;padding:1.5rem;box-shadow:0 10px 30px -5px rgba(0,27,72,0.08);display:flex;flex-direction:column;gap:1.25rem;">
        
        <!-- Masked PIN Dots Indicator -->
        <div style="display:flex;justify-content:center;gap:0.75rem;padding:0.75rem 0;">
            <template x-for="i in maxDigits" :key="i">
                <div
                    style="width:1rem;height:1rem;border-radius:9999px;border:2px solid #cbd5e1;transition:all 0.15s ease;"
                    :style="pin.length >= i ? 'background:#001b48;border-color:#001b48;transform:scale(1.15);' : 'background:#f1f5f9;'">
                </div>
            </template>
        </div>

        <!-- Hidden Native Input for Hardware / Screen Keyboards -->
        <input
            type="password"
            x-ref="pinInput"
            x-model="pin"
            @input="onPinInput"
            @keydown.enter.prevent="submitPin"
            inputmode="numeric"
            maxlength="8"
            style="position:absolute;opacity:0;pointer-events:none;"
            autocomplete="current-password">

        <!-- Touch-Friendly Numeric Keypad -->
        <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:0.625rem;user-select:none;">
            <template x-for="digit in [1, 2, 3, 4, 5, 6, 7, 8, 9]" :key="digit">
                <button
                    type="button"
                    @click="appendDigit(digit)"
                    :disabled="loading"
                    class="att-keypad-btn">
                    <span x-text="digit"></span>
                </button>
            </template>

            <!-- Bottom Row: Clear, 0, Backspace -->
            <button
                type="button"
                @click="clearPin"
                :disabled="loading || pin.length === 0"
                class="att-keypad-btn action"
                style="font-size:0.8125rem;font-weight:700;color:#64748b;">
                Clear
            </button>

            <button
                type="button"
                @click="appendDigit(0)"
                :disabled="loading"
                class="att-keypad-btn">
                <span>0</span>
            </button>

            <button
                type="button"
                @click="backspace"
                :disabled="loading || pin.length === 0"
                class="att-keypad-btn action"
                style="color:#64748b;">
                ⌫
            </button>
        </div>

        <!-- Primary Action Button -->
        <button
            type="button"
            @click="submitPin"
            :disabled="loading || pin.length < 4"
            class="att-btn att-btn-primary"
            style="min-height:3.25rem;font-size:1rem;font-weight:800;border-radius:0.75rem;margin-top:0.25rem;">
            <svg x-show="loading" style="animation:spin 1s linear infinite;width:20px;height:20px;" fill="none" viewBox="0 0 24 24">
                <circle style="opacity:0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path style="opacity:0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span x-show="!loading">Unlock Dashboard →</span>
            <span x-show="loading">Authenticating...</span>
        </button>
    </div>

    <!-- Security & Device Info -->
    <div style="text-align:center;padding:0.25rem 0.5rem;font-size:0.6875rem;color:#64748b;line-height:1.5;">
        🔒 Attendance authentication persists securely on this device. Contact showroom admin if you forgot your PIN.
    </div>

</div>
@endsection

@section('styles')
<style>
    .att-keypad-btn {
        height: 3.5rem;
        border-radius: 0.75rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #001b48;
        font-size: 1.35rem;
        font-weight: 800;
        font-family: inherit;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.1s ease;
        -webkit-tap-highlight-color: transparent;
    }
    .att-keypad-btn:active:not(:disabled) {
        background: #e2e8f0;
        transform: scale(0.95);
    }
    .att-keypad-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .att-keypad-btn.action {
        font-size: 1.1rem;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>
@endsection

@section('scripts')
<script>
function attendancePinLogin() {
    return {
        pin: '',
        maxDigits: 6,
        loading: false,
        errorMessage: null,

        init() {
            // Keep native input focused for keyboard users
            this.$nextTick(() => {
                this.$refs.pinInput?.focus();
            });

            // Re-focus on click anywhere in card
            window.addEventListener('keydown', (e) => {
                if (e.key >= '0' && e.key <= '9') {
                    if (this.pin.length < 8) {
                        this.appendDigit(parseInt(e.key));
                    }
                } else if (e.key === 'Backspace') {
                    this.backspace();
                } else if (e.key === 'Enter') {
                    this.submitPin();
                }
            });
        },

        appendDigit(d) {
            if (this.pin.length < 8) {
                this.pin += d.toString();
                this.errorMessage = null;

                // If user entered 6 digits, auto-submit for lightning-fast UX
                if (this.pin.length === 6) {
                    setTimeout(() => {
                        this.submitPin();
                    }, 120);
                }
            }
        },

        clearPin() {
            this.pin = '';
            this.errorMessage = null;
        },

        backspace() {
            if (this.pin.length > 0) {
                this.pin = this.pin.slice(0, -1);
                this.errorMessage = null;
            }
        },

        onPinInput(e) {
            this.pin = this.pin.replace(/[^0-9]/g, '').slice(0, 8);
        },

        async submitPin() {
            if (this.pin.length < 4 || this.loading) return;

            this.loading = true;
            this.errorMessage = null;

            const platformName = /iPhone|iPad|iPod/.test(navigator.userAgent) ? 'iOS' : (/Android/.test(navigator.userAgent) ? 'Android' : 'Desktop');
            const browserName = navigator.userAgent.includes('Chrome') ? 'Chrome' : (navigator.userAgent.includes('Safari') ? 'Safari' : 'Browser');
            const deviceName = `${platformName} (${browserName})`;

            try {
                const res = await fetch('/attendance/api/auth/pin', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        pin: this.pin,
                        device_name: deviceName,
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    this.errorMessage = data.errors?.pin?.[0] || data.error || 'Invalid attendance PIN. Please check with your administrator.';
                    this.pin = '';
                    this.loading = false;
                    return;
                }

                // If a token was returned, also save in localStorage as redundant fallback
                if (data.token) {
                    try {
                        localStorage.setItem('laijau_attendance_token', data.token);
                    } catch (e) {}
                }

                // Immediate redirect to Attendance Dashboard
                window.location.href = data.redirect || '/attendance';
            } catch (err) {
                this.loading = false;
                this.errorMessage = 'Network connection failed. Please verify your connection.';
            }
        }
    };
}
</script>
@endsection
