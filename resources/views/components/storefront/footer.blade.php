@php
    use App\Models\Category;
    use Illuminate\Support\Facades\Cache;

    $store = app(\App\Services\StoreSettingsService::class);
    $brand = $store->getBrandIdentity();
    $socialLinks = $store->getSocialLinks();

    $footerCategories = Category::where('is_active', true)
        ->whereHas('products', fn($q) => $q->where('is_active', true))
        ->withCount(['products' => fn($q) => $q->where('is_active', true)])
        ->orderByDesc('products_count')
        ->take(6)
        ->get();
@endphp

<footer class="bg-white text-slate-600 mt-auto border-t border-slate-200 text-left select-none">
    <!-- Minimalist Trust Reassurance Strip -->
    <div class="border-b border-slate-200 bg-slate-50/80 py-5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6 text-left">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200/60 text-blue-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-900 tracking-tight">Valley Cash on Delivery</h4>
                        <p class="text-[11px] text-slate-500">Kathmandu, Lalitpur & Bhaktapur</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200/60 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-900 tracking-tight">Nationwide Hub Delivery</h4>
                        <p class="text-[11px] text-slate-500">All 77 districts via courier</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200/60 text-amber-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-900 tracking-tight">100% Genuine Stock</h4>
                        <p class="text-[11px] text-slate-500">In Kathmandu warehouse</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-200/60 text-indigo-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-900 tracking-tight">Support on WhatsApp</h4>
                        <p class="text-[11px] text-slate-500">9843512095 • Daily assistance</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Navigation Columns -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-14">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-8 lg:gap-12">
            <!-- Brand Column -->
            <div class="md:col-span-4 space-y-4">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2" aria-label="Laijau Home">
                    <picture>
                        <source srcset="/images/logo.webp" type="image/webp">
                        <img src="/images/logo.png" alt="Laijau" width="130" height="36" class="h-8 sm:h-9 w-auto object-contain" onerror="this.onerror=null; this.src='/logo.png';" decoding="async" />
                    </picture>
                    <span class="sr-only">LAIJAU</span>
                </a>
                <p class="text-xs text-slate-500 leading-relaxed max-w-sm">
                    Nepal’s modern footwear destination. From handcrafted leather boots to everyday sneakers and essentials, delivered directly to your doorstep.
                </p>
                <div class="text-xs text-slate-600 space-y-2 pt-1">
                    <p class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>Kathmandu, Nepal</span>
                    </p>
                    <p class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <a href="mailto:info@laijau.com" class="hover:text-blue-600 transition-colors">info@laijau.com</a>
                    </p>
                    <p class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        <a href="https://wa.me/9779843512095" target="_blank" rel="noopener" class="hover:text-emerald-700 font-semibold transition-colors">9843512095 (WhatsApp Support)</a>
                    </p>
                </div>
            </div>

            <!-- Categories Column -->
            <div class="md:col-span-3 space-y-3">
                <h4 class="text-xs font-bold tracking-wider uppercase text-slate-900">Categories</h4>
                <ul class="space-y-2 text-xs text-slate-600">
                    @foreach($footerCategories as $fCat)
                        <li>
                            <a href="{{ route('storefront.category', $fCat->slug) }}" class="hover:text-blue-600 transition-colors">
                                {{ $fCat->name }}
                            </a>
                        </li>
                    @endforeach
                    <li class="pt-1">
                        <a href="{{ route('storefront.catalogue') }}" class="text-blue-600 hover:text-blue-700 font-semibold transition-colors inline-flex items-center gap-1">
                            <span>Browse All Products</span>
                            <span>&rarr;</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Customer Care Column -->
            <div class="md:col-span-2 space-y-3">
                <h4 class="text-xs font-bold tracking-wider uppercase text-slate-900">Customer Care</h4>
                <ul class="space-y-2 text-xs text-slate-600">
                    <li><a href="{{ route('storefront.track_order') }}" class="text-blue-600 hover:text-blue-700 font-medium transition-colors">Track Order</a></li>
                    <li><a href="{{ route('storefront.account') }}" class="hover:text-blue-600 transition-colors">My Account</a></li>
                    <li><a href="{{ route('storefront.wishlist') }}" class="hover:text-blue-600 transition-colors">Wishlist</a></li>
                    <li><a href="{{ url('/shipping') }}" class="hover:text-blue-600 transition-colors">Shipping & Delivery</a></li>
                    <li><a href="{{ url('/returns') }}" class="hover:text-blue-600 transition-colors">Exchange Policy</a></li>
                    <li><a href="{{ url('/contact') }}" class="hover:text-blue-600 transition-colors">Help & Contact</a></li>
                </ul>
            </div>

            <!-- Delivery & Payment Column -->
            <div class="md:col-span-3 space-y-3">
                <h4 class="text-xs font-bold tracking-wider uppercase text-slate-900">Delivery & Settlement</h4>
                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-2 text-slate-700">
                    <div>
                        <span class="font-bold text-slate-900 block">Kathmandu Valley</span>
                        <span class="text-[11px] text-slate-500">Flat Rs. 100 • Free above Rs. 2,000</span>
                    </div>
                    <div class="pt-2 border-t border-slate-200">
                        <span class="font-bold text-slate-900 block">All 77 Districts</span>
                        <span class="text-[11px] text-slate-500">Flat Rs. 150 • Free above Rs. 3,500</span>
                    </div>
                </div>

                <!-- Sleek Minimalist Payment Pills -->
                <div class="pt-1 flex flex-wrap items-center gap-1.5 text-[10px] text-slate-600">
                    <span class="px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200 font-medium text-slate-700">Cash on Delivery</span>
                    <span class="px-2 py-0.5 rounded-md bg-emerald-50 border border-emerald-200 font-semibold text-emerald-700">eSewa</span>
                    <span class="px-2 py-0.5 rounded-md bg-purple-50 border border-purple-200 font-semibold text-purple-700">Khalti</span>
                    <span class="px-2 py-0.5 rounded-md bg-blue-50 border border-blue-200 font-semibold text-blue-700">Fonepay / Bank</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Minimalist Copyright Bar -->
    <div class="border-t border-slate-200 py-5 bg-slate-50/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-3 text-xs text-slate-500">
            <p>&copy; 2016 Laijau • Kathmandu, Nepal. All rights reserved.</p>

            <div class="flex items-center gap-4 text-xs text-slate-500">
                <a href="{{ url('/privacy') }}" class="hover:text-slate-800 transition-colors">Privacy</a>
                <span>•</span>
                <a href="{{ url('/terms') }}" class="hover:text-slate-800 transition-colors">Terms</a>
                <span>•</span>
                <span class="text-slate-500">Showroom & Delivery Hub</span>
            </div>
        </div>
    </div>
</footer>
