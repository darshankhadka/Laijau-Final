<div
    x-data
    x-cloak
    x-show="$store.store.isSearchOpen"
    x-transition:enter="transition-opacity duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    id="search-modal"
    class="fixed inset-0 z-50 overflow-y-auto text-left"
    style="display: none;">
    <!-- Backdrop -->
    <div
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
        @click="$store.store.isSearchOpen = false"
        aria-hidden="true"></div>

    <div class="relative min-h-screen flex items-start justify-center p-4 pt-10 sm:pt-16">
        <div
            x-show="$store.store.isSearchOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-200 p-5 sm:p-6 overflow-hidden"
            @click.outside="$store.store.isSearchOpen = false">
            <!-- Header Bar -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">
                    Search Laijau Footwear & Essentials
                </span>
                <button
                    type="button"
                    @click="$store.store.isSearchOpen = false"
                    class="p-1.5 text-slate-400 hover:text-slate-700 rounded-full hover:bg-slate-100 transition-colors cursor-pointer"
                    aria-label="Close search">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Search Input Form -->
            <form
                action="{{ route('storefront.search') }}"
                method="GET"
                @submit="$store.store.isSearchOpen = false"
                x-init="$watch('$store.store.isSearchOpen', (val) => { if (val) { setTimeout(() => $refs.modalSearchInput?.focus(), 80); } })"
                class="mt-4 relative">
                <div class="relative flex items-center bg-slate-100 rounded-2xl px-4 py-2 border border-slate-300 focus-within:border-blue-600 focus-within:bg-white focus-within:ring-2 focus-within:ring-blue-600/20 transition-all">
                    <svg class="w-5 h-5 text-slate-400 shrink-0 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        type="search"
                        name="q"
                        x-ref="modalSearchInput"
                        x-model="$store.store.searchQuery"
                        @input.debounce.250ms="$store.store.fetchSearchSuggestions($store.store.searchQuery)"
                        placeholder="Search shoes, sneakers, boots, clothing, slippers..."
                        class="w-full bg-transparent text-sm sm:text-base text-slate-900 placeholder-slate-400 focus:outline-none"
                        autocomplete="off" />
                    <template x-if="$store.store.isSearching">
                        <div class="w-5 h-5 border-2 border-blue-600 border-t-transparent rounded-full animate-spin shrink-0"></div>
                    </template>
                </div>
            </form>

            <!-- Search Content & Suggestions -->
            <div class="mt-4 max-h-[60vh] overflow-y-auto divide-y divide-slate-100">
                <!-- Matching Categories -->
                <template x-if="$store.store.searchSuggestions.categories && $store.store.searchSuggestions.categories.length > 0">
                    <div class="py-3">
                        <span class="text-[10px] font-bold tracking-wider uppercase text-slate-400 block mb-2">Categories</span>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="cat in $store.store.searchSuggestions.categories" :key="cat.id">
                                <a
                                    :href="'/categories/' + cat.slug"
                                    @click="$store.store.isSearchOpen = false"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-700 rounded-full text-xs font-medium transition-colors">
                                    <span x-text="cat.name"></span>
                                    <span class="text-[10px] text-slate-400" x-text="'(' + cat.count + ')'"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                </template>

                <!-- Trending / Suggested Searches -->
                <template x-if="$store.store.searchSuggestions.popular_searches && $store.store.searchSuggestions.popular_searches.length > 0">
                    <div class="py-3">
                        <span class="text-[10px] font-bold tracking-wider uppercase text-slate-400 block mb-2">Trending Searches</span>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="term in $store.store.searchSuggestions.popular_searches" :key="term">
                                <a
                                    :href="'/search?q=' + encodeURIComponent(term)"
                                    @click="$store.store.isSearchOpen = false"
                                    class="px-2.5 py-1 bg-slate-50 hover:bg-slate-200 text-slate-700 rounded-lg text-xs transition-colors"
                                    x-text="term"></a>
                            </template>
                        </div>
                    </div>
                </template>

                <!-- Product Results -->
                <template x-if="$store.store.searchSuggestions.products && $store.store.searchSuggestions.products.length > 0">
                    <div class="pt-3">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-[10px] font-bold tracking-wider uppercase text-slate-400">Products</span>
                            <span class="text-xs font-semibold text-blue-600" x-text="$store.store.searchSuggestions.total_products_count + ' items found'"></span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <template x-for="prod in $store.store.searchSuggestions.products" :key="prod.id">
                                <a
                                    :href="prod.url"
                                    @click="$store.store.isSearchOpen = false"
                                    class="flex items-center gap-3 p-2 rounded-xl hover:bg-slate-50 border border-transparent hover:border-slate-200 transition-colors group">
                                    <img
                                        :src="prod.image"
                                        :alt="prod.name"
                                        width="56"
                                        height="56"
                                        loading="lazy"
                                        decoding="async"
                                        class="w-14 h-14 object-cover rounded-lg border border-slate-200 shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-slate-900 group-hover:text-blue-600 truncate" x-text="prod.name"></p>
                                        <p class="text-[10px] text-slate-400 mt-0.5" x-text="'SKU: ' + prod.sku"></p>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs font-bold text-slate-900" x-text="prod.formatted_price"></span>
                                            <span x-show="prod.compare_at_price" class="text-[10px] text-slate-400 line-through" x-text="prod.formatted_compare_at_price"></span>
                                        </div>
                                    </div>
                                </a>
                            </template>
                        </div>

                        <div class="pt-4 mt-2 text-center border-t border-slate-100">
                            <a
                                :href="'/search?q=' + encodeURIComponent($store.store.searchQuery)"
                                @click="$store.store.isSearchOpen = false"
                                class="inline-flex items-center text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors">
                                <span>View all results for "<span x-text="$store.store.searchQuery"></span>"</span>
                                <span class="ml-1.5">&rarr;</span>
                            </a>
                        </div>
                    </div>
                </template>

                <!-- No Results State -->
                <template x-if="$store.store.searchQuery.length >= 2 && !$store.store.isSearching && $store.store.searchSuggestions.products.length === 0 && $store.store.searchSuggestions.categories.length === 0">
                    <div class="py-10 text-center text-slate-600">
                        <p class="text-sm font-semibold text-slate-900 mb-1">No products found matching "<span x-text="$store.store.searchQuery"></span>"</p>
                        <p class="text-xs text-slate-400 mb-4">Try checking for typos or searching general terms like shoes, sneakers, boots</p>
                        <a
                            href="{{ route('storefront.catalogue') }}"
                            @click="$store.store.isSearchOpen = false"
                            class="inline-block px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-colors">
                            Browse All Products
                        </a>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>