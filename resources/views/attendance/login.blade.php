@extends('attendance.layout')

@section('title', 'Attendance Sign In — Laijau')

@section('content')
<div x-data="attendancePinLogin()" class="att-pin-screen">

    <!-- Brand Header -->
    <div class="att-pin-header">
        <div class="att-pin-avatar">
            LJ
        </div>
        <h1 class="att-pin-title">
            Laijau Attendance
        </h1>
        <p class="att-pin-subtitle">
            Enter your employee attendance PIN to sign in
        </p>
    </div>

    <!-- Error Alert -->
    <template x-if="errorMessage">
        <div class="att-pin-error" role="alert">
            <div class="att-pin-error-text">
                <span class="att-pin-error-icon">⚠️</span>
                <span x-text="errorMessage"></span>
            </div>
            <button type="button" @click="errorMessage = null" class="att-pin-error-close" aria-label="Dismiss error">×</button>
        </div>
    </template>

    <!-- PIN Card -->
    <div class="att-card att-pin-card">
        
        <!-- Masked PIN Dots Indicator -->
        <div class="att-pin-dots" aria-label="PIN entry progress">
            <template x-for="i in maxDigits" :key="i">
                <div
                    class="att-pin-dot"
                    :class="{ 'filled': pin.length >= i }">
                </div>
            </template>
        </div>

        <!-- Hidden Native Input for Hardware / Physical Keyboards & Screen Readers -->
        <input
            type="password"
            x-ref="pinInput"
            x-model="pin"
            @input="onPinInput"
            @keydown.enter.prevent="submitPin"
            inputmode="numeric"
            maxlength="8"
            tabindex="-1"
            aria-hidden="true"
            class="att-hidden-native-input"
            autocomplete="current-password"
            aria-label="Attendance PIN input">

        <!-- Touch-Friendly Numeric Keypad -->
        <div class="att-keypad-grid">
            <template x-for="digit in [1, 2, 3, 4, 5, 6, 7, 8, 9]" :key="digit">
                <button
                    type="button"
                    @click="appendDigit(digit)"
                    :disabled="loading"
                    class="att-keypad-btn"
                    :aria-label="'Digit ' + digit">
                    <span x-text="digit"></span>
                </button>
            </template>

            <!-- Bottom Row: Clear, 0, Backspace -->
            <button
                type="button"
                @click="clearPin"
                :disabled="loading || pin.length === 0"
                class="att-keypad-btn att-keypad-action"
                aria-label="Clear PIN">
                Clear
            </button>

            <button
                type="button"
                @click="appendDigit(0)"
                :disabled="loading"
                class="att-keypad-btn"
                aria-label="Digit 0">
                <span>0</span>
            </button>

            <button
                type="button"
                @click="backspace"
                :disabled="loading || pin.length === 0"
                class="att-keypad-btn att-keypad-action"
                aria-label="Delete last digit">
                <svg style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414-6.414a2 2 0 011.414-.586H19a2 2 0 012 2v10a2 2 0 01-2 2h-8.172a2 2 0 01-1.414-.586L3 12z"/>
                </svg>
            </button>
        </div>

        <!-- Primary Action Button: Strict Mutual Exclusivity between Idle & Loading -->
        <button
            type="button"
            @click="submitPin"
            :disabled="loading || pin.length < 4"
            class="att-btn att-btn-primary att-pin-submit-btn"
            aria-live="polite">
            
            <!-- IDLE STATE: Only Unlock Dashboard + arrow -->
            <span x-show="!loading" x-cloak class="att-btn-state">
                <span>Unlock Dashboard</span>
                <svg class="att-arrow-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                </svg>
            </span>

            <!-- LOADING STATE: Only Authenticating... + spinner -->
            <span x-show="loading" x-cloak class="att-btn-state">
                <svg class="att-spinner-icon" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="att-spinner-track" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="att-spinner-head" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Authenticating...</span>
            </span>
        </button>
    </div>

    <!-- Security & Device Info -->
    <div class="att-pin-footer">
        🔒 Attendance authentication persists securely on this device.
    </div>

</div>
@endsection

@section('styles')
<style>
    /* Pin Screen Container */
    .att-pin-screen {
        width: 100%;
        max-width: 380px;
        margin: auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: clamp(0.625rem, 1.8vh, 1.25rem);
        flex: 1;
        min-height: 0;
    }

    /* Brand Header */
    .att-pin-header {
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        flex-shrink: 0;
    }
    .att-pin-avatar {
        width: clamp(2.5rem, 5vh, 3.5rem);
        height: clamp(2.5rem, 5vh, 3.5rem);
        border-radius: 0.875rem;
        background: linear-gradient(135deg, #001b48 0%, #002566 100%);
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: clamp(1.1rem, 2.3vh, 1.4rem);
        font-weight: 900;
        box-shadow: 0 6px 20px -3px rgba(0, 27, 72, 0.28);
        margin-bottom: clamp(0.25rem, 0.8vh, 0.65rem);
        transition: transform 0.2s ease;
    }
    .att-pin-title {
        font-size: clamp(1.125rem, 2.4vh, 1.35rem);
        font-weight: 800;
        color: #001b48;
        margin: 0;
        letter-spacing: -0.025em;
        line-height: 1.2;
    }
    .att-pin-subtitle {
        font-size: clamp(0.75rem, 1.4vh, 0.8125rem);
        color: #64748b;
        margin-top: 0.25rem;
        line-height: 1.35;
    }

    /* Error Alert */
    .att-pin-error {
        width: 100%;
        padding: 0.625rem 0.875rem;
        background: #fee2e2;
        border: 1px solid #f87171;
        border-radius: 0.75rem;
        color: #991b1b;
        font-size: 0.8125rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        animation: att-slide-up 0.2s ease-out;
        flex-shrink: 0;
    }
    .att-pin-error-text {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .att-pin-error-icon {
        font-size: 1rem;
    }
    .att-pin-error-close {
        background: none;
        border: none;
        color: #991b1b;
        font-weight: 800;
        font-size: 1.25rem;
        cursor: pointer;
        line-height: 1;
        padding: 0 0.25rem;
    }

    /* PIN Card */
    .att-pin-card {
        width: 100%;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.125rem;
        padding: clamp(0.75rem, 2vh, 1.25rem);
        box-shadow: 0 8px 30px -4px rgba(0, 27, 72, 0.08);
        display: flex;
        flex-direction: column;
        gap: clamp(0.5rem, 1.5vh, 1rem);
        flex-shrink: 0;
    }

    /* Masked PIN Dots Indicator */
    .att-pin-dots {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: clamp(0.5rem, 2vw, 0.75rem);
        padding: clamp(0.2rem, 0.8vh, 0.5rem) 0;
    }
    .att-pin-dot {
        width: clamp(0.75rem, 1.8vh, 0.95rem);
        height: clamp(0.75rem, 1.8vh, 0.95rem);
        border-radius: 9999px;
        border: 2px solid #cbd5e1;
        background: #f1f5f9;
        transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .att-pin-dot.filled {
        background: #001b48;
        border-color: #001b48;
        transform: scale(1.15);
        box-shadow: 0 0 8px rgba(0, 27, 72, 0.35);
    }

    /* Hidden native input */
    .att-hidden-native-input {
        position: fixed;
        top: -9999px;
        left: -9999px;
        opacity: 0;
        pointer-events: none;
        width: 1px;
        height: 1px;
    }

    /* Keypad Grid */
    .att-keypad-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: clamp(0.35rem, 0.9vh, 0.55rem);
        user-select: none;
        -webkit-user-select: none;
        touch-action: manipulation;
    }

    /* Keypad Buttons */
    .att-keypad-btn {
        height: clamp(2.65rem, 5.5vh, 3.25rem);
        border-radius: 0.75rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #001b48;
        font-size: clamp(1.2rem, 2.5vh, 1.4rem);
        font-weight: 800;
        font-family: inherit;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.1s ease, transform 0.1s ease, border-color 0.1s ease;
        -webkit-tap-highlight-color: transparent;
        touch-action: manipulation;
    }
    .att-keypad-btn:active:not(:disabled) {
        background: #e2e8f0;
        border-color: #cbd5e1;
        transform: scale(0.96);
    }
    .att-keypad-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    .att-keypad-action {
        font-size: clamp(0.75rem, 1.4vh, 0.85rem);
        font-weight: 700;
        color: #64748b;
        background: #f1f5f9;
    }

    /* Submit Button & Mutual Exclusivity */
    .att-pin-submit-btn {
        min-height: clamp(2.75rem, 5.5vh, 3.125rem);
        font-size: clamp(0.875rem, 1.8vh, 1rem);
        font-weight: 800;
        border-radius: 0.75rem;
        margin-top: clamp(0.125rem, 0.5vh, 0.35rem);
        width: 100%;
        position: relative;
        overflow: hidden;
    }
    .att-btn-state {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        width: 100%;
    }
    .att-arrow-icon {
        width: 1.125rem;
        height: 1.125rem;
        flex-shrink: 0;
        transition: transform 0.15s ease;
    }
    .att-pin-submit-btn:hover:not(:disabled) .att-arrow-icon {
        transform: translateX(3px);
    }
    .att-spinner-icon {
        width: 1.25rem;
        height: 1.25rem;
        flex-shrink: 0;
        animation: spin 0.8s linear infinite;
    }
    .att-spinner-track {
        opacity: 0.25;
    }
    .att-spinner-head {
        opacity: 0.85;
    }

    /* Footer info */
    .att-pin-footer {
        text-align: center;
        padding: 0 0.5rem;
        font-size: clamp(0.625rem, 1.2vh, 0.6875rem);
        color: #64748b;
        line-height: 1.35;
        flex-shrink: 0;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* Short mobile screens (e.g. height <= 640px, or landscape mode) */
    @media (max-height: 640px) {
        .att-pin-avatar {
            display: none !important;
        }
        .att-pin-subtitle {
            display: none !important;
        }
        .att-pin-title {
            font-size: 1.05rem !important;
        }
        .att-pin-screen {
            gap: 0.4rem !important;
        }
        .att-pin-card {
            padding: 0.65rem 0.875rem !important;
            gap: 0.4rem !important;
        }
        .att-keypad-btn {
            height: 2.65rem !important;
            font-size: 1.2rem !important;
        }
        .att-pin-submit-btn {
            min-height: 2.65rem !important;
        }
        .att-pin-footer {
            display: none !important;
        }
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
            // Support physical keyboard on desktop without popping up mobile virtual keyboards
            window.addEventListener('keydown', (e) => {
                if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') && e.target !== this.$refs.pinInput) {
                    return;
                }
                if (e.key >= '0' && e.key <= '9') {
                    if (this.pin.length < 8) {
                        this.appendDigit(parseInt(e.key));
                    }
                } else if (e.key === 'Backspace') {
                    this.backspace();
                } else if (e.key === 'Enter') {
                    this.submitPin();
                } else if (e.key === 'Escape') {
                    this.clearPin();
                }
            });
        },

        appendDigit(d) {
            if (this.pin.length < 8 && !this.loading) {
                this.pin += d.toString();
                this.errorMessage = null;

                // Auto submit when 6 digits are entered
                if (this.pin.length === 6) {
                    setTimeout(() => {
                        this.submitPin();
                    }, 100);
                }
            }
        },

        clearPin() {
            if (this.loading) return;
            this.pin = '';
            this.errorMessage = null;
        },

        backspace() {
            if (this.loading) return;
            if (this.pin.length > 0) {
                this.pin = this.pin.slice(0, -1);
                this.errorMessage = null;
            }
        },

        onPinInput(e) {
            if (this.loading) return;
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
