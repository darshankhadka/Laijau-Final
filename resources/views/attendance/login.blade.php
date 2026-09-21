@extends('attendance.layout')

@section('title', 'Sign In — Laijau Attendance')

@section('content')
<div x-data="attendanceLogin()" class="att-login-wrap" style="display:flex;flex-direction:column;gap:1.25rem;">

    <!-- Device Recognition Banner -->
    @if($device)
        <div class="att-card" style="background:#f8fafc;border-color:var(--att-gray-200);text-align:center;">
            <div style="width:3rem;height:3rem;border-radius:9999px;background:var(--att-navy);color:#ffffff;display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:900;margin:0 auto 0.5rem;">
                {{ substr($device->employee->first_name ?? 'E', 0, 1) }}
            </div>
            <h3 style="font-size:1.05rem;font-weight:800;color:var(--att-navy);margin:0;">
                {{ $device->employee->full_name ?? 'Employee' }}
            </h3>
            <div style="font-size:0.75rem;color:var(--att-gray-500);font-family:monospace;margin-top:0.15rem;">
                {{ $device->employee->employee_number ?? '' }} &bull; {{ $device->device_name }}
            </div>
        </div>
    @else
        <div class="att-card" style="background:#fffbeb;border-color:#fde68a;">
            <div style="display:flex;gap:0.75rem;">
                <span>ℹ️</span>
                <div style="font-size:0.75rem;color:#92400e;">
                    <strong>Device Not Recognized:</strong> If this is your first time using this phone or browser, please complete the <a href="{{ route('attendance.setup') }}" style="color:var(--att-navy);font-weight:700;">one-time device setup</a>.
                </div>
            </div>
        </div>
    @endif

    <!-- Error Banner -->
    <template x-if="errorMessage">
        <div style="padding:0.75rem 1rem;background:#fee2e2;border:1px solid #f87171;border-radius:var(--att-radius);color:#991b1b;font-size:0.8125rem;font-weight:600;display:flex;align-items:center;gap:0.5rem;">
            <span>⚠️</span>
            <span x-text="errorMessage"></span>
        </div>
    </template>

    <!-- PIN Unlock Card -->
    <div class="att-card">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <h4 style="font-size:0.95rem;font-weight:800;color:var(--att-navy);margin:0;">Enter Attendance PIN</h4>
            <span class="att-badge att-badge-navy">Secure</span>
        </div>

        <div class="att-form-group">
            <input
                type="password"
                x-model="pin"
                @keydown.enter.prevent="submitPin()"
                maxlength="8"
                class="att-input font-mono"
                style="text-align:center;font-size:1.5rem;letter-spacing:0.25em;height:3.5rem;"
                placeholder="••••"
                inputmode="numeric"
                autocomplete="current-password"
                autofocus>
        </div>

        <button
            type="button"
            @click="submitPin()"
            :disabled="loading || pin.length < 4"
            class="att-btn att-btn-primary">
            <span x-show="!loading">Unlock Dashboard →</span>
            <span x-show="loading">Authenticating...</span>
        </button>

        @if($device && $device->passkey_credential_id)
            <div style="position:relative;text-align:center;margin:0.25rem 0;">
                <hr style="border:0;border-top:1px solid var(--att-gray-200);">
                <span style="position:relative;top:-10px;background:#fff;padding:0 0.5rem;font-size:0.6875rem;color:var(--att-gray-500);font-weight:700;text-transform:uppercase;">Or</span>
            </div>

            <!-- Native Biometrics Button (WebAuthn) -->
            <button
                type="button"
                @click="loginWithPasskey()"
                :disabled="passkeyLoading"
                class="att-btn att-btn-emerald">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"></path></svg>
                <span x-show="!passkeyLoading">Use Face ID / Fingerprint</span>
                <span x-show="passkeyLoading">Verifying Biometrics...</span>
            </button>
        @endif
    </div>

    <!-- Switch / Setup Link -->
    <div style="text-align:center;padding:0.5rem 0;">
        <a href="{{ route('attendance.setup') }}" style="font-size:0.8125rem;color:var(--att-navy);font-weight:700;text-decoration:none;">
            Register a New Device or Reset Pairing &rarr;
        </a>
    </div>

</div>
@endsection

@section('scripts')
<script>
function attendanceLogin() {
    return {
        pin: '',
        loading: false,
        passkeyLoading: false,
        errorMessage: null,

        async submitPin() {
            if (this.pin.length < 4) return;

            this.loading = true;
            this.errorMessage = null;

            // Retrieve cached device token from localStorage if cookie was cleared
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
                        window.location.href = '/attendance/setup';
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

        async loginWithPasskey() {
            if (!window.PublicKeyCredential) {
                alert('Biometrics are not supported on this browser.');
                return;
            }

            this.passkeyLoading = true;
            this.errorMessage = null;
            const cachedToken = localStorage.getItem('laijau_att_token');

            try {
                // 1. Get assertion options
                const optRes = await fetch('/attendance/api/webauthn/login-options', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        ...(cachedToken ? { 'X-Device-Token': cachedToken } : {})
                    }
                });

                const options = await optRes.json();
                if (!optRes.ok) throw new Error(options.error || 'Passkey authentication unavailable');

                options.challenge = Uint8Array.from(atob(options.challenge), c => c.charCodeAt(0));
                options.allowCredentials = options.allowCredentials.map(c => ({
                    ...c,
                    id: Uint8Array.from(atob(c.id), ch => ch.charCodeAt(0)),
                }));

                // 2. Invoke native biometric prompt
                const assertion = await navigator.credentials.get({ publicKey: options });

                // 3. Verify on server
                const verifyRes = await fetch('/attendance/api/webauthn/login-verify', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        ...(cachedToken ? { 'X-Device-Token': cachedToken } : {})
                    },
                    body: JSON.stringify({
                        id: assertion.id,
                    })
                });

                const verifyData = await verifyRes.json();
                if (verifyRes.ok) {
                    window.location.href = verifyData.redirect || '/attendance';
                } else {
                    this.errorMessage = verifyData.error || 'Biometric verification failed.';
                }
            } catch (err) {
                console.warn('Biometrics login cancelled or error:', err);
            } finally {
                this.passkeyLoading = false;
            }
        }
    };
}
</script>
@endsection
