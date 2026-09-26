<script src="/js/html5-qrcode.min.js?v=2.3.8"></script>
<script>
    if (typeof window.posCameraScanner === 'undefined') {
        window.posCameraScanner = function() {
            return {
                isOpen: false,
                isScanning: false,
                hasError: false,
                errorMessage: '',
                isInsecureContext: false,
                lastScanned: '',
                scanCount: 0,
                manualBarcode: '',
                html5QrCode: null,
                facingMode: "environment",
                torchOn: false,
                hasTorch: false,
                cameras: [],
                selectedCameraId: '',

                async openScanner() {
                    this.isOpen = true;
                    this.hasError = false;
                    this.errorMessage = '';
                    this.lastScanned = '';
                    this.manualBarcode = '';

                    const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
                    const isHttps = window.isSecureContext || window.location.protocol === 'https:';
                    this.isInsecureContext = !isLocal && !isHttps;

                    this.$nextTick(async () => {
                        await this.loadCameras();
                        await this.startCamera();
                    });
                },

                async loadCameras() {
                    if (typeof Html5Qrcode === 'undefined') return;
                    try {
                        const devices = await Html5Qrcode.getCameras();
                        if (devices && devices.length > 0) {
                            this.cameras = devices;
                            const backCam = devices.find(d => /back|rear|environment|wide|main/i.test(d.label));
                            if (backCam) {
                                this.selectedCameraId = backCam.id;
                            } else if (!this.selectedCameraId) {
                                this.selectedCameraId = devices[devices.length - 1].id;
                            }
                        }
                    } catch (e) {
                        console.warn("Could not enumerate camera devices:", e);
                    }
                },

                async startCamera() {
                    if (typeof Html5Qrcode === 'undefined') {
                        this.hasError = true;
                        this.errorMessage = 'Scanner library is loading. Please wait a moment and tap Retry.';
                        return;
                    }

                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        this.hasError = true;
                        this.errorMessage = this.isInsecureContext ?
                            'Camera access blocked by browser: Camera requires HTTPS when accessed from a phone/remote device. Please open via HTTPS or type barcode manually.' :
                            'Camera API is not supported on this browser or permission is disabled.';
                        return;
                    }

                    try {
                        if (this.html5QrCode && this.html5QrCode.isScanning) {
                            await this.stopCamera();
                        }

                        const viewport = document.getElementById("lj-pos-camera-viewport");
                        if (viewport) {
                            viewport.innerHTML = '';
                        }

                        this.html5QrCode = new Html5Qrcode("lj-pos-camera-viewport", {
                            verbose: false,
                            formatsToSupport: [
                                Html5QrcodeSupportedFormats.EAN_13,
                                Html5QrcodeSupportedFormats.EAN_8,
                                Html5QrcodeSupportedFormats.CODE_128,
                                Html5QrcodeSupportedFormats.CODE_39,
                                Html5QrcodeSupportedFormats.UPC_A,
                                Html5QrcodeSupportedFormats.UPC_E,
                                Html5QrcodeSupportedFormats.QR_CODE
                            ]
                        });
                        this.isScanning = true;
                        this.hasError = false;

                        const config = {
                            fps: 15,
                            qrbox: (viewfinderWidth, viewfinderHeight) => {
                                const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                                const qrboxWidth = Math.floor(minEdge * 0.85);
                                const qrboxHeight = Math.floor(qrboxWidth * 0.65);
                                return { width: Math.max(220, qrboxWidth), height: Math.max(140, qrboxHeight) };
                            },
                            aspectRatio: 1.333334
                        };

                        let lastCode = '';
                        let lastTime = 0;

                        const onScanSuccess = (decodedText) => {
                            const now = Date.now();
                            if (decodedText === lastCode && (now - lastTime) < 1500) {
                                return;
                            }
                            lastCode = decodedText;
                            lastTime = now;
                            this.onBarcodeDetected(decodedText);
                        };

                        let started = false;
                        if (this.selectedCameraId && this.cameras.some(c => c.id === this.selectedCameraId)) {
                            try {
                                await this.html5QrCode.start(
                                    this.selectedCameraId,
                                    config,
                                    onScanSuccess,
                                    () => {}
                                );
                                started = true;
                            } catch (eDevice) {
                                console.warn("Failed starting camera by deviceId, resetting for facingMode fallback:", eDevice);
                                await this.stopCamera();
                                const vp = document.getElementById("lj-pos-camera-viewport");
                                if (vp) vp.innerHTML = '';
                                this.selectedCameraId = '';
                                this.html5QrCode = new Html5Qrcode("lj-pos-camera-viewport", { verbose: false });
                            }
                        }

                        if (!started) {
                            try {
                                await this.html5QrCode.start(
                                    { facingMode: { ideal: this.facingMode } },
                                    config,
                                    onScanSuccess,
                                    () => {}
                                );
                                started = true;
                            } catch (eFacing) {
                                console.warn("Failed with ideal facingMode, retrying with simple constraint:", eFacing);
                                await this.stopCamera();
                                const vp = document.getElementById("lj-pos-camera-viewport");
                                if (vp) vp.innerHTML = '';
                                this.html5QrCode = new Html5Qrcode("lj-pos-camera-viewport", { verbose: false });
                                await this.html5QrCode.start(
                                    { facingMode: this.facingMode },
                                    config,
                                    onScanSuccess,
                                    () => {}
                                );
                                started = true;
                            }
                        }

                        // Check torch capability
                        try {
                            const track = this.html5QrCode.getRunningTrackCapabilities();
                            this.hasTorch = !!track?.torch;
                        } catch (e) {
                            this.hasTorch = false;
                        }

                        const videoEl = document.querySelector("#lj-pos-camera-viewport video");
                        if (videoEl) {
                            videoEl.setAttribute("playsinline", "true");
                            videoEl.setAttribute("webkit-playsinline", "true");
                            videoEl.setAttribute("muted", "true");
                            videoEl.setAttribute("autoplay", "true");
                            videoEl.style.objectFit = "cover";
                            videoEl.style.width = "100%";
                            videoEl.style.height = "100%";
                        }

                        await this.loadCameras();
                    } catch (err) {
                        console.error("Camera scanner start error:", err);
                        this.isScanning = false;
                        this.hasError = true;
                        await this.stopCamera();

                        const msg = (err?.message || '').toLowerCase();
                        const name = err?.name || '';

                        if (name === 'NotAllowedError' || msg.includes('permission') || msg.includes('denied')) {
                            this.errorMessage = 'Camera permission is blocked. Allow camera access for Laijau and try again.';
                        } else if (name === 'NotFoundError' || msg.includes('not found') || msg.includes('devicesnotfound')) {
                            this.errorMessage = 'No camera was found on this device.';
                        } else if (name === 'NotReadableError' || msg.includes('busy') || msg.includes('in use') || msg.includes('could not start')) {
                            this.errorMessage = 'The camera is currently being used by another application.';
                        } else if (name === 'OverconstrainedError' || msg.includes('overconstrained') || msg.includes('constraint')) {
                            this.errorMessage = 'Camera constraint not supported on this device. Tap \'Try Other Camera\' to use a different camera.';
                        } else if (this.isInsecureContext) {
                            this.errorMessage = 'Camera blocked by browser: Accessing over HTTP from another device is restricted by iOS/Chrome. Please use HTTPS or type SKU manually.';
                        } else if (name === 'NotSupportedError' || msg.includes('not supported')) {
                            this.errorMessage = 'Camera scanning is not supported by this browser.';
                        } else {
                            this.errorMessage = 'Unable to start the camera. Please try again.';
                        }
                    }
                },

                async onCameraChange() {
                    if (this.selectedCameraId) {
                        await this.startCamera();
                    }
                },

                async stopCamera() {
                    if (this.html5QrCode) {
                        try {
                            if (this.html5QrCode.isScanning) {
                                await this.html5QrCode.stop();
                            }
                            this.html5QrCode.clear();
                        } catch (e) {
                            console.warn("Error stopping camera:", e);
                        }
                        this.html5QrCode = null;
                    }

                    const viewport = document.getElementById("lj-pos-camera-viewport");
                    if (viewport) {
                        viewport.querySelectorAll('video').forEach(v => {
                            if (v.srcObject && v.srcObject.getTracks) {
                                v.srcObject.getTracks().forEach(t => { try { t.stop(); } catch(e) {} });
                            }
                            v.srcObject = null;
                        });
                        viewport.innerHTML = '';
                    }
                    this.isScanning = false;
                    this.hasTorch = false;
                    this.torchOn = false;
                },

                async closeScanner() {
                    await this.stopCamera();
                    this.isOpen = false;
                },

                async switchCamera() {
                    if (this.cameras.length > 1) {
                        const currentIndex = this.cameras.findIndex(c => c.id === this.selectedCameraId);
                        const nextIndex = (currentIndex + 1) % this.cameras.length;
                        this.selectedCameraId = this.cameras[nextIndex].id;
                    } else {
                        this.facingMode = this.facingMode === "environment" ? "user" : "environment";
                    }
                    await this.startCamera();
                },

                async toggleTorch() {
                    if (!this.html5QrCode || !this.hasTorch) return;
                    try {
                        this.torchOn = !this.torchOn;
                        await this.html5QrCode.applyVideoConstraints({
                            advanced: [{
                                torch: this.torchOn
                            }]
                        });
                    } catch (e) {
                        console.warn("Torch error:", e);
                    }
                },

                onBarcodeDetected(code) {
                    this.playBeep();
                    if (navigator.vibrate) {
                        try {
                            navigator.vibrate([60, 40, 60]);
                        } catch (e) {}
                    }
                    this.lastScanned = code;
                    this.scanCount++;

                    @this.handleBarcodeScan(code);
                },

                submitManualBarcode() {
                    if (!this.manualBarcode.trim()) return;
                    const code = this.manualBarcode.trim();
                    this.manualBarcode = '';
                    this.onBarcodeDetected(code);
                },

                playBeep() {
                    try {
                        const AudioCtx = window.AudioContext || window.webkitAudioContext;
                        if (!AudioCtx) return;
                        const audioCtx = new AudioCtx();
                        const osc = audioCtx.createOscillator();
                        const gain = audioCtx.createGain();
                        osc.type = 'sine';
                        osc.frequency.value = 1900;
                        gain.gain.value = 0.25;
                        osc.connect(gain);
                        gain.connect(audioCtx.destination);
                        osc.start();
                        gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.12);
                        setTimeout(() => {
                            osc.stop();
                            audioCtx.close();
                        }, 150);
                    } catch (e) {}
                }
            };
        };
    }
</script>

<div
    x-data="posCameraScanner()"
    @open-pos-camera.window="openScanner()"
    x-show="isOpen"
    x-cloak
    class="lj-modal-backdrop"
    style="display: none; z-index: 1000;"
    @keydown.escape.window="closeScanner()">
            <div class="lj-modal-card" style="max-width: 500px; width: 95%;">
                <div class="lj-modal-header" style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--lj-emerald);"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
                        <div>
                            <h3 style="font-size: 1rem; font-weight: 800; color: var(--lj-text); margin: 0;">Camera Barcode Scanner</h3>
                            <p style="font-size: 0.6875rem; color: var(--lj-text-muted); margin: 0;">Point device camera at item barcode or SKU</p>
                        </div>
                    </div>
                    <button type="button" @click="closeScanner()" class="lj-search-clear" style="position: static; font-size: 1.25rem;">✕</button>
                </div>

                <div class="lj-modal-body" style="padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem;">
                    <!-- Insecure Context Warning if HTTP over LAN -->
                    <div x-show="isInsecureContext" style="background: #fffbeb; border: 1px solid #f59e0b; border-radius: 0.5rem; padding: 0.75rem; color: #92400e; font-size: 0.75rem; line-height: 1.4;">
                        <div style="font-weight: 800; display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.25rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <span>Browser Security Restriction (HTTPS Required)</span>
                        </div>
                        <div>
                            Mobile browsers (iOS Safari & Chrome) block camera access over plain HTTP when accessed via LAN IP.
                            To use phone cameras, serve via HTTPS or an encrypted tunnel, or enter the SKU / barcode manually below.
                        </div>
                    </div>

                    <!-- Camera Viewport Box -->
                    <div style="position: relative; width: 100%; min-height: 260px; background: #0f172a; border-radius: 0.5rem; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                        <div id="lj-pos-camera-viewport" style="width: 100%;"></div>

                        <!-- Scanning Reticle / Overlay -->
                        <div x-show="isScanning && !hasError" style="position: absolute; inset: 0; pointer-events: none; display: flex; align-items: center; justify-content: center;">
                            <div style="width: 240px; height: 140px; border: 2px solid #10b981; border-radius: 0.5rem; box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.45); position: relative;">
                                <div style="position: absolute; top: 50%; left: 0; right: 0; height: 2px; background: #ef4444; opacity: 0.85; animation: ljLaserPulse 1.8s ease-in-out infinite;"></div>
                            </div>
                        </div>

                        <!-- Error Prompt if denied or unavailable -->
                        <div x-show="hasError" style="position: absolute; inset: 0; background: rgba(15, 23, 42, 0.96); color: #ffffff; padding: 1.5rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 0.75rem; z-index: 10;">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <div style="font-size: 0.8125rem; font-weight: 700; color: #fca5a5; max-width: 320px; line-height: 1.4;" x-text="errorMessage"></div>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: center;">
                                <button type="button" @click="startCamera()" class="lj-btn-primary" style="padding: 0.45rem 0.95rem; font-size: 0.75rem;">
                                    Retry Camera
                                </button>
                                <button type="button" @click="switchCamera()" class="lj-cat-chip" style="color: #ffffff; border-color: rgba(255,255,255,0.25);">
                                    Try Other Camera
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Controls Row: Camera Selection & Torch -->
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <template x-if="cameras.length > 1">
                                <select
                                    x-model="selectedCameraId"
                                    @change="onCameraChange()"
                                    class="lj-cat-chip"
                                    style="padding: 0.35rem 0.6rem; font-size: 0.75rem; border-radius: 0.375rem; max-width: 170px; background: var(--lj-card); color: var(--lj-text);">
                                    <template x-for="cam in cameras" :key="cam.id">
                                        <option :value="cam.id" x-text="cam.label || ('Camera ' + cam.id.slice(0, 5))"></option>
                                    </template>
                                </select>
                            </template>
                            <template x-if="cameras.length <= 1">
                                <button type="button" @click="switchCamera()" class="lj-cat-chip" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21h5v-5"/></svg>
                                    <span>Switch Cam</span>
                                </button>
                            </template>
                            <button type="button" x-show="hasTorch" @click="toggleTorch()" class="lj-cat-chip" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                <span x-text="torchOn ? 'Torch On' : 'Torch Off'"></span>
                            </button>
                        </div>
                        <div x-show="scanCount > 0" style="font-size: 0.75rem; font-weight: 700; color: var(--lj-emerald);">
                            Scanned: <span x-text="scanCount"></span> items
                        </div>
                    </div>

                    <!-- Last Scanned Feedback Pill -->
                    <div x-show="lastScanned" style="background: #064e3b; border: 1px solid #10b981; border-radius: 0.375rem; padding: 0.5rem 0.75rem; font-size: 0.75rem; color: #ffffff; display: flex; align-items: center; justify-content: space-between;">
                        <span>Detected Barcode: <strong class="lj-mono" x-text="lastScanned"></strong></span>
                        <span>✓ Processed</span>
                    </div>

                    <!-- Manual Barcode Fallback Input -->
                    <div style="border-top: 1px solid var(--lj-border); padding-top: 0.75rem;">
                        <label style="display: block; font-size: 0.6875rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.25rem;">
                            Manual Barcode / SKU Fallback:
                        </label>
                        <div style="display: flex; gap: 0.35rem;">
                            <input
                                type="text"
                                x-model="manualBarcode"
                                @keydown.enter.prevent="submitManualBarcode()"
                                placeholder="Type barcode or SKU..."
                                class="lj-search-input"
                                style="height: 38px; font-size: 0.8125rem; font-family: monospace;" />
                            <button type="button" @click="submitManualBarcode()" class="lj-btn-primary" style="padding: 0 0.85rem; font-size: 0.75rem; height: 38px; border-radius: 0.5rem; flex-shrink: 0;">
                                Add
                            </button>
                        </div>
                    </div>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" @click="closeScanner()" class="lj-btn-default" style="width: 100%; justify-content: center;">
                        Close Scanner
                    </button>
                </div>
            </div>
        </div>
