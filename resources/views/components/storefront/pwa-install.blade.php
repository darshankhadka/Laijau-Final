<div
    x-data="{
        deferredPrompt: null,
        showInstallBanner: false,
        dismissed: localStorage.getItem('laijau_pwa_dismissed') === 'true',
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
        async installPwa() {
            if (!this.deferredPrompt) return;
            this.deferredPrompt.prompt();
            const { outcome } = await this.deferredPrompt.userChoice;
            if (outcome === 'accepted') {
                this.showInstallBanner = false;
            }
            this.deferredPrompt = null;
        },
        dismiss() {
            this.showInstallBanner = false;
            this.dismissed = true;
            localStorage.setItem('laijau_pwa_dismissed', 'true');
        }
    }"
    x-show="showInstallBanner"
    x-cloak
    class="fixed bottom-20 sm:bottom-6 left-4 right-4 sm:left-auto sm:right-6 sm:w-96 z-40 bg-white border border-slate-200 rounded-2xl shadow-xl p-4 transition-all"
>
    <div class="flex items-start gap-3">
        <img src="{{ asset('android-chrome-192x192.png') }}" alt="Laijau" width="40" height="40" loading="lazy" decoding="async" class="w-10 h-10 rounded-xl object-contain shadow-2xs shrink-0" />
        <div class="flex-1 min-w-0">
            <h4 class="text-xs font-bold text-slate-900">Install Laijau App</h4>
            <p class="text-[11px] text-slate-500 mt-0.5 leading-tight">Install our app for faster shopping, shoe size guides, and instant order tracking.</p>
            <div class="flex items-center gap-2 mt-2.5">
                <button
                    type="button"
                    @click="installPwa()"
                    class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-lg transition-colors cursor-pointer"
                >
                    Install App
                </button>
                <button
                    type="button"
                    @click="dismiss()"
                    class="px-3 py-1.5 text-slate-500 hover:text-slate-700 text-xs font-medium cursor-pointer"
                >
                    Not now
                </button>
            </div>
        </div>
        <button
            type="button"
            @click="dismiss()"
            class="text-slate-400 hover:text-slate-600 text-sm leading-none p-1 cursor-pointer"
        >
            ✕
        </button>
    </div>
</div>
