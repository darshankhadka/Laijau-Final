@extends('attendance.layout')

@section('title', 'Device Setup — Laijau Attendance')

@section('content')
<div x-data="attendanceSetup()" class="att-setup-wrap" style="display:flex;flex-direction:column;gap:1.25rem;">

    <!-- Progress Indicator -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding-bottom:0.75rem;border-bottom:1px solid var(--att-gray-200);">
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <span class="att-badge att-badge-navy">Step <span x-text="step"></span> of 3</span>
            <span style="font-size:0.75rem;font-weight:700;color:var(--att-gray-500);" x-text="stepTitle"></span>
        </div>
        <span style="font-size:0.6875rem;color:var(--att-gray-500);font-weight:600;">One-Time Pairing</span>
    </div>

    <!-- Banner Info -->
    <div class="att-card" style="background:linear-gradient(135deg,#eff6ff 0%,#f0fdf4 100%);border-color:#bfdbfe;">
        <div style="display:flex;align-items:flex-start;gap:0.75rem;">
            <div style="width:2rem;height:2rem;border-radius:9999px;background:#3b82f6;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            <div>
                <h3 style="font-size:0.875rem;font-weight:800;color:var(--att-navy);margin:0;">Register Attendance Device</h3>
                <p style="font-size:0.75rem;color:var(--att-gray-700);margin-top:0.25rem;line-height:1.4;">
                    Configure this device <strong>once</strong> with your Employee ID, phone, and administrator PIN. After pairing, you will only need your PIN or Face ID / Fingerprint to punch attendance.
                </p>
            </div>
        </div>
    </div>

    <!-- Error Banner -->
    <template x-if="errorMessage">
        <div style="padding:0.75rem 1rem;background:#fee2e2;border:1px solid #f87171;border-radius:var(--att-radius);color:#991b1b;font-size:0.8125rem;font-weight:600;display:flex;align-items:center;gap:0.5rem;">
            <span>⚠️</span>
            <span x-text="errorMessage"></span>
        </div>
    </template>

    <!-- STEP 1: CREDENTIALS VERIFICATION -->
    <div x-show="step === 1" style="display:flex;flex-direction:column;gap:1.125rem;">
        <div class="att-card">
            <h4 style="font-size:0.95rem;font-weight:800;color:var(--att-navy);margin:0;">1. Enter Employee Credentials</h4>
            <p style="font-size:0.75rem;color:var(--att-gray-500);margin:0;">Credentials provided by the showroom administrator</p>

            <div class="att-form-group">
                <label class="att-label">Employee ID *</label>
                <input
                    type="text"
                    x-model="form.employee_number"
                    class="att-input font-mono"
                    placeholder="e.g. LJ-EMP-001 or MED-0001"
                    autocomplete="off"
                    autocapitalize="characters">
                <span class="att-input-error" x-show="errors.employee_number" x-text="errors.employee_number"></span>
            </div>

            <div class="att-form-group">
                <label class="att-label">Registered Phone Number *</label>
                <input
                    type="tel"
                    x-model="form.phone"
                    class="att-input font-mono"
                    placeholder="e.g. 98XXXXXXXX"
                    autocomplete="tel">
                <span class="att-input-error" x-show="errors.phone" x-text="errors.phone"></span>
            </div>

            <div class="att-form-group">
                <label class="att-label">Admin-Created Attendance PIN *</label>
                <input
                    type="password"
                    x-model="form.pin"
                    maxlength="8"
                    class="att-input font-mono"
                    placeholder="••••"
                    inputmode="numeric"
                    autocomplete="current-password">
                <span style="font-size:0.6875rem;color:var(--att-gray-500);">4 to 8 numeric digits</span>
                <span class="att-input-error" x-show="errors.pin" x-text="errors.pin"></span>
            </div>
        </div>

        <button
            type="button"
            @click="verifyCredentials()"
            :disabled="loading"
            class="att-btn att-btn-primary">
            <span x-show="!loading">Verify & Proceed to Photo →</span>
            <span x-show="loading">Verifying Credentials...</span>
        </button>
    </div>

    <!-- STEP 2: VERIFICATION PHOTO CAPTURE -->
    <div x-show="step === 2" style="display:flex;flex-direction:column;gap:1.125rem;">
        <div class="att-card" style="text-align:center;">
            <h4 style="font-size:0.95rem;font-weight:800;color:var(--att-navy);margin:0;">2. Attendance Verification Photo</h4>
            <p style="font-size:0.75rem;color:var(--att-gray-500);margin:0;">Take a clear selfie for device registration evidence</p>

            <!-- Camera Viewfinder / Preview Box -->
            <div style="position:relative;width:100%;max-width:280px;height:280px;margin:0.75rem auto;border-radius:1.25rem;overflow:hidden;background:#0f172a;box-shadow:inset 0 2px 8px rgba(0,0,0,0.4);border:2px solid var(--att-gray-300);">
                <!-- Live Video Feed -->
                <video
                    x-ref="videoElement"
                    autoplay
                    playsinline
                    muted
                    x-show="!capturedPhoto"
                    style="width:100%;height:100%;object-fit:cover;transform:scaleX(-1);">
                </video>

                <!-- Captured Image Preview -->
                <img
                    x-ref="photoPreview"
                    :src="capturedPhoto"
                    x-show="capturedPhoto"
                    style="width:100%;height:100%;object-fit:cover;"
                    alt="Captured Photo Preview">

                <!-- Target Guides Overlay -->
                <div x-show="!capturedPhoto" style="position:absolute;inset:20px;border:2px dashed rgba(255,255,255,0.4);border-radius:9999px;pointer-events:none;"></div>
            </div>

            <!-- Hidden Canvas for compression -->
            <canvas x-ref="canvasElement" style="display:none;"></canvas>

            <!-- Camera Controls -->
            <div style="display:flex;gap:0.5rem;justify-content:center;margin-top:0.5rem;">
                <button
                    type="button"
                    x-show="!capturedPhoto"
                    @click="captureSnapshot()"
                    class="att-btn att-btn-emerald"
                    style="width:auto;min-width:180px;">
                    📸 Take Photo
                </button>

                <button
                    type="button"
                    x-show="capturedPhoto"
                    @click="retakeSnapshot()"
                    class="att-btn att-btn-outline"
                    style="width:auto;">
                    🔄 Retake Photo
                </button>

                <!-- Fallback file picker for restricted browsers -->
                <label class="att-btn att-btn-outline" style="width:auto;cursor:pointer;" title="Upload photo directly">
                    <span>📁 Gallery</span>
                    <input type="file" accept="image/*" capture="user" @change="handleFileFallback($event)" style="display:none;">
                </label>
            </div>
            <span class="att-input-error" x-show="errors.photo" x-text="errors.photo"></span>
        </div>

        <div style="display:flex;gap:0.75rem;">
            <button
                type="button"
                @click="step = 1"
                class="att-btn att-btn-outline"
                style="flex:1;">
                ← Back
            </button>

            <button
                type="button"
                @click="completeRegistration()"
                :disabled="!capturedPhoto || loading"
                class="att-btn att-btn-primary"
                style="flex:2;">
                <span x-show="!loading">Register Device →</span>
                <span x-show="loading">Registering Device...</span>
            </button>
        </div>
    </div>

    <!-- STEP 3: SUCCESS & OPTIONAL BIOMETRIC / PASSKEY REGISTRATION -->
    <div x-show="step === 3" style="display:flex;flex-direction:column;gap:1.125rem;">
        <div class="att-card" style="text-align:center;padding:2rem 1.25rem;">
            <div style="width:3.5rem;height:3.5rem;border-radius:9999px;background:var(--att-emerald-bg);color:var(--att-emerald);display:flex;align-items:center;justify-content:center;font-size:1.75rem;margin:0 auto 0.75rem;">
                ✓
            </div>

            <h3 style="font-size:1.15rem;font-weight:900;color:var(--att-navy);margin:0;">Device Successfully Paired!</h3>
            <p style="font-size:0.8125rem;color:var(--att-gray-700);margin-top:0.35rem;">
                Welcome, <strong x-text="verifiedEmployeeName"></strong>! This phone is now authorized for showroom attendance.
            </p>

            <div style="margin-top:1.5rem;padding:1rem;background:var(--att-gray-50);border:1px solid var(--att-gray-200);border-radius:var(--att-radius);text-align:left;">
                <div style="font-size:0.75rem;font-weight:800;color:var(--att-navy);text-transform:uppercase;letter-spacing:0.04em;">Optional Fast Biometrics</div>
                <p style="font-size:0.75rem;color:var(--att-gray-600);margin-top:0.25rem;line-height:1.4;">
                    Enable <strong>Face ID / Touch ID / Fingerprint</strong> so you can punch in instantly without typing your PIN each time.
                </p>

                <div style="margin-top:1rem;display:flex;flex-direction:column;gap:0.5rem;">
                    <button
                        type="button"
                        @click="registerPasskey()"
                        :disabled="passkeyLoading || passkeySuccess"
                        class="att-btn att-btn-emerald">
                        <span x-show="!passkeyLoading && !passkeySuccess">⚡ Enable Biometrics (WebAuthn)</span>
                        <span x-show="passkeyLoading">Enabling Biometrics...</span>
                        <span x-show="passkeySuccess">✓ Biometrics Enabled!</span>
                    </button>
                </div>
            </div>

            <div style="margin-top:1.25rem;">
                <a
                    href="{{ route('attendance.dashboard') }}"
                    class="att-btn att-btn-primary"
                    style="text-decoration:none;">
                    Enter Attendance Dashboard →
                </a>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
function attendanceSetup() {
    return {
        step: 1,
        stepTitle: 'Employee Verification',
        loading: false,
        passkeyLoading: false,
        passkeySuccess: false,
        errorMessage: null,
        verifiedEmployeeName: '',
        capturedPhoto: null,
        mediaStream: null,
        form: {
            employee_number: '',
            phone: '',
            pin: '',
        },
        errors: {},

        init() {
            this.$watch('step', (val) => {
                if (val === 1) this.stepTitle = 'Employee Verification';
                if (val === 2) {
                    this.stepTitle = 'Photo Verification';
                    this.$nextTick(() => this.startCamera());
                }
                if (val === 3) {
                    this.stepTitle = 'Device Registered';
                    this.stopCamera();
                }
            });
        },

        async verifyCredentials() {
            this.errors = {};
            this.errorMessage = null;

            if (!this.form.employee_number.trim()) {
                this.errors.employee_number = 'Please enter your Employee ID.';
                return;
            }
            if (!this.form.phone.trim()) {
                this.errors.phone = 'Please enter your registered phone number.';
                return;
            }
            if (!this.form.pin || this.form.pin.length < 4) {
                this.errors.pin = 'Please enter your 4 to 8-digit attendance PIN.';
                return;
            }

            this.loading = true;
            try {
                // Test credentials against API
                const res = await fetch('/attendance/api/setup', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        employee_number: this.form.employee_number.trim(),
                        phone: this.form.phone.trim(),
                        pin: this.form.pin,
                        device_name: navigator.userAgentData?.platform || navigator.platform || 'Mobile Device',
                        platform: /iPhone|iPad|iPod/.test(navigator.userAgent) ? 'iOS' : (/Android/.test(navigator.userAgent) ? 'Android' : 'Desktop'),
                        browser: navigator.userAgent.includes('Chrome') ? 'Chrome' : (navigator.userAgent.includes('Safari') ? 'Safari' : 'Browser'),
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    if (data.errors) {
                        this.errors = Object.fromEntries(Object.entries(data.errors).map(([k, v]) => [k, v[0]]));
                    } else {
                        this.errorMessage = data.error || data.message || 'Verification failed. Please check credentials.';
                    }
                    return;
                }

                this.verifiedEmployeeName = data.employee?.name || 'Employee';
                // Proceed to Step 2 for photo capture
                this.step = 2;
            } catch (e) {
                this.errorMessage = 'Network error while connecting to server. Please try again.';
            } finally {
                this.loading = false;
            }
        },

        async startCamera() {
            try {
                if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    this.mediaStream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'user',
                            width: { ideal: 640 },
                            height: { ideal: 640 },
                        },
                        audio: false,
                    });
                    if (this.$refs.videoElement) {
                        this.$refs.videoElement.srcObject = this.mediaStream;
                    }
                }
            } catch (err) {
                console.warn('Camera access denied or unavailable:', err);
            }
        },

        stopCamera() {
            if (this.mediaStream) {
                this.mediaStream.getTracks().forEach(t => t.stop());
                this.mediaStream = null;
            }
        },

        captureSnapshot() {
            const video = this.$refs.videoElement;
            const canvas = this.$refs.canvasElement;
            if (!video || !canvas) return;

            const size = Math.min(video.videoWidth || 480, video.videoHeight || 480, 600);
            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');

            // Draw centered crop (mirror horizontally for front camera)
            ctx.translate(size, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0, size, size);

            // Compress client-side to JPEG 0.75 quality (~30-50KB)
            this.capturedPhoto = canvas.toDataURL('image/jpeg', 0.75);
            this.errors.photo = null;
        },

        retakeSnapshot() {
            this.capturedPhoto = null;
            this.startCamera();
        },

        handleFileFallback(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = this.$refs.canvasElement;
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
                    this.capturedPhoto = canvas.toDataURL('image/jpeg', 0.75);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        },

        async completeRegistration() {
            if (!this.capturedPhoto) {
                this.errors.photo = 'Please capture a photo first.';
                return;
            }

            this.loading = true;
            this.errorMessage = null;

            try {
                const res = await fetch('/attendance/api/setup', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        employee_number: this.form.employee_number.trim(),
                        phone: this.form.phone.trim(),
                        pin: this.form.pin,
                        device_name: navigator.userAgentData?.platform || navigator.platform || 'Mobile Device',
                        platform: /iPhone|iPad|iPod/.test(navigator.userAgent) ? 'iOS' : (/Android/.test(navigator.userAgent) ? 'Android' : 'Desktop'),
                        browser: navigator.userAgent.includes('Chrome') ? 'Chrome' : (navigator.userAgent.includes('Safari') ? 'Safari' : 'Browser'),
                        photo: this.capturedPhoto,
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    this.errorMessage = data.error || data.message || 'Registration failed.';
                    return;
                }

                // Save device token to localStorage as reliable backup
                if (data.device_token) {
                    localStorage.setItem('laijau_att_token', data.device_token);
                }

                this.verifiedEmployeeName = data.employee?.name || 'Employee';
                this.step = 3;
            } catch (e) {
                this.errorMessage = 'Failed to submit registration. Please retry.';
            } finally {
                this.loading = false;
            }
        },

        async registerPasskey() {
            if (!window.PublicKeyCredential) {
                alert('Biometrics / Passkeys are not supported on this browser. You can always use your PIN!');
                return;
            }

            this.passkeyLoading = true;
            try {
                // 1. Get options from server
                const optRes = await fetch('/attendance/api/webauthn/register-options', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    }
                });
                const options = await optRes.json();
                if (!optRes.ok) throw new Error(options.error || 'Could not initialize biometrics');

                // Decode base64 challenge and user ID
                options.challenge = Uint8Array.from(atob(options.challenge), c => c.charCodeAt(0));
                options.user.id = Uint8Array.from(options.user.id, c => c.charCodeAt(0));

                // 2. Invoke native platform authenticator (Face ID / Fingerprint / Device PIN)
                const credential = await navigator.credentials.create({ publicKey: options });

                // 3. Send back assertion to server
                const verifyRes = await fetch('/attendance/api/webauthn/register-verify', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        id: credential.id,
                        rawId: btoa(String.fromCharCode(...new Uint8Array(credential.rawId))),
                        type: credential.type,
                    })
                });

                const verifyData = await verifyRes.json();
                if (verifyRes.ok) {
                    this.passkeySuccess = true;
                } else {
                    alert(verifyData.error || 'Biometric registration could not be verified.');
                }
            } catch (err) {
                console.warn('Biometrics setup aborted:', err);
            } finally {
                this.passkeyLoading = false;
            }
        }
    };
}
</script>
@endsection
