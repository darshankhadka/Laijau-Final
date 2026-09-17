<div
    x-data="{
        visible: false,
        init() {
            setTimeout(() => {
                if (!localStorage.getItem('laijau_cookie_consent')) {
                    this.visible = true;
                }
            }, 800);
        },
        acceptAll() {
            localStorage.setItem('laijau_cookie_consent', JSON.stringify({ analytics: true, timestamp: Date.now() }));
            this.visible = false;
        },
        acceptEssential() {
            localStorage.setItem('laijau_cookie_consent', JSON.stringify({ analytics: false, timestamp: Date.now() }));
            this.visible = false;
        }
    }"
    x-show="visible"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-4"
    class="fixed bottom-20 lg:bottom-6 left-4 right-4 sm:left-6 sm:max-w-md z-50 bg-white text-slate-700 p-5 rounded-2xl shadow-xl border border-slate-200 text-left select-none"
    style="display: none;"
>
    <div class="flex items-start gap-3">
        <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 text-sm font-bold">
            🍪
        </div>
        <div class="flex-1 min-w-0">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-1">
                Cookie & Session Notice
            </h4>
            <p class="text-xs text-slate-500 leading-relaxed mb-3">
                We use cookies to save your shopping cart, maintain your login session, and provide fast product search on Laijau.
            </p>
            <div class="flex flex-wrap gap-2 items-center">
                <button
                    type="button"
                    @click="acceptAll()"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors cursor-pointer"
                >
                    Accept
                </button>
                <button
                    type="button"
                    @click="acceptEssential()"
                    class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 transition-colors cursor-pointer"
                >
                    Essential Only
                </button>
                <a
                    href="{{ url('/privacy') }}"
                    class="text-[11px] text-slate-500 hover:text-slate-900 underline ml-auto"
                >
                    Privacy
                </a>
            </div>
        </div>
    </div>
</div>
