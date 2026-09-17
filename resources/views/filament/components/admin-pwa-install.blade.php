@if(auth('admin')->check())
<div
    x-data="{
        deferredPrompt: null,
        showInstallBanner: false,
        dismissed: localStorage.getItem('laijau_admin_pwa_dismissed') === 'true',
        init() {
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                if (!this.dismissed) {
                    this.showInstallBanner = true;
                }
            });

            window.addEventListener('appinstalled', () => {
                this.showInstallBanner = false;
                this.deferredPrompt = null;
            });
        },
        async installApp() {
            if (!this.deferredPrompt) return;
            this.deferredPrompt.prompt();
            const { outcome } = await this.deferredPrompt.userChoice;
            if (outcome === 'accepted') {
                this.showInstallBanner = false;
            }
            this.deferredPrompt = null;
        },
        dismissBanner() {
            this.showInstallBanner = false;
            this.dismissed = true;
            localStorage.setItem('laijau_admin_pwa_dismissed', 'true');
        }
    }"
    x-show="showInstallBanner"
    x-cloak
    style="position: fixed; bottom: 4.5rem; right: 1rem; z-index: 999; max-width: 380px; width: calc(100% - 2rem); background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); padding: 0.875rem; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;"
>
    <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
        <div style="width: 40px; height: 40px; border-radius: 0.5rem; background: #0A2E23; color: #ffffff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 800; font-size: 1.125rem;">
            LJ
        </div>
        <div style="flex: 1; min-width: 0;">
            <div style="font-size: 0.8125rem; font-weight: 700; color: #0f172a; margin-bottom: 0.15rem;">
                Install Laijau ERP
            </div>
            <div style="font-size: 0.6875rem; color: #64748b; line-height: 1.35; margin-bottom: 0.6rem;">
                Add to your home screen for rapid POS, barcode scanning, and order fulfillment.
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <button
                    type="button"
                    @click="installApp()"
                    style="background: #0A2E23; color: #ffffff; border: none; border-radius: 0.375rem; padding: 0.35rem 0.75rem; font-size: 0.75rem; font-weight: 700; cursor: pointer;"
                >
                    Install App
                </button>
                <button
                    type="button"
                    @click="dismissBanner()"
                    style="background: transparent; color: #64748b; border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.35rem 0.6rem; font-size: 0.75rem; font-weight: 600; cursor: pointer;"
                >
                    Later
                </button>
            </div>
        </div>
        <button
            type="button"
            @click="dismissBanner()"
            style="background: none; border: none; font-size: 1.125rem; color: #94a3b8; cursor: pointer; padding: 0; line-height: 1;"
        >
            ✕
        </button>
    </div>
</div>
@endif
