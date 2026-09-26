<section class="lj-catalog-panel" :class="mobilePosTab !== 'catalog' ? 'mobile-hidden' : ''">
    <!-- Barcode & Search Bar with Mobile Camera Scanner -->
    <div class="lj-search-wrapper">
        <div class="lj-search-box">
            <span class="lj-search-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </span>
            <input
                id="lj-pos-search-input"
                type="text"
                wire:model.live.debounce.250ms="searchQuery"
                wire:keydown.enter="handleBarcodeScan"
                placeholder="Scan barcode or search product / SKU / size... (Press Enter or / to focus)"
                class="lj-search-input"
                autofocus />
            @if(!empty($searchQuery))
            <button
                type="button"
                wire:click="$set('searchQuery', '')"
                class="lj-search-clear"
                title="Clear search">
                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            @endif
        </div>
        <button
            type="button"
            @click="$dispatch('open-pos-camera')"
            class="lj-pos-camera-btn"
            title="Scan Barcode with Camera">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                <circle cx="12" cy="13" r="4"></circle>
            </svg>
            <span>Scan Camera</span>
        </button>
    </div>

    <!-- Category Chips Horizontal Bar -->
    <div class="lj-cat-bar">
        <button
            type="button"
            wire:click="$set('selectedCategoryId', null)"
            class="lj-cat-chip {{ is_null($selectedCategoryId) ? 'active' : '' }}">
            All Categories
        </button>
        @foreach($categories as $cat)
        <button
            type="button"
            wire:click="$set('selectedCategoryId', {{ $cat['id'] }})"
            class="lj-cat-chip {{ $selectedCategoryId === $cat['id'] ? 'active' : '' }}">
            {{ $cat['name'] }}
        </button>
        @endforeach
    </div>

    <!-- Product Catalog Grid -->
    <div class="lj-grid-wrap">
        <div class="lj-product-grid">
            @forelse($searchResults as $prod)
            @php
            $pStock = count($prod['variants']) > 0
                ? collect($prod['variants'])->sum('stock_quantity')
                : ($prod['quantity'] ?? 0);
            $hasVariants = count($prod['variants']) > 1;
            $firstVar = $prod['variants'][0] ?? null;
            $refPrice = $firstVar && !empty($firstVar['price'])
            ? $firstVar['price']
            : ($prod['price'] ?? 0);
            $img = $firstVar['image'] ?? ($prod['featured_image'] ?? null);
            @endphp
            <div
                wire:key="pos-catalog-prod-{{ $prod['id'] }}"
                wire:click="handleProductClick({{ $prod['id'] }})"
                wire:keydown.enter="handleProductClick({{ $prod['id'] }})"
                class="lj-prod-card"
                role="button"
                tabindex="0">
                <div class="lj-prod-img-wrap">
                    @if($img)
                    <img src="/storage/{{ $img }}" alt="{{ $prod['name'] }}" class="lj-prod-img" />
                    @else
                    <div class="lj-prod-img flex items-center justify-center bg-slate-100 text-slate-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    @endif
                </div>

                <div class="lj-prod-info">
                    <div class="lj-prod-title" title="{{ $prod['name'] }}">{{ $prod['name'] }}</div>
                    <div class="lj-prod-meta">
                        <span class="lj-prod-sku">{{ $prod['sku'] }}</span>
                        @if($hasVariants)
                        <span class="lj-opts-badge">{{ count($prod['variants']) }} opts</span>
                        @endif
                    </div>
                </div>

                <div class="lj-prod-bottom">
                    <div>
                        <div class="lj-prod-price">{{ $currSymbol }}{{ number_format($refPrice, 2) }}</div>
                        <div class="lj-stock-pill {{ $pStock > 5 ? 'in-stock' : ($pStock > 0 ? 'low-stock' : 'out-stock') }}">
                            ● {{ $pStock }} in stock
                        </div>
                    </div>
                    <span class="lj-add-btn">
                        {{ $hasVariants ? '+ Options' : '+ Add' }}
                    </span>
                </div>
            </div>
            @empty
            <div style="grid-column: 1 / -1; padding: 4rem 1rem; text-align: center; color: var(--lj-text-muted); background: var(--lj-card); border-radius: 0.75rem; border: 1px dashed var(--lj-border);">
                <div style="color: #94A3B8; margin-bottom: 0.5rem;">
                    <svg style="width: 36px; height: 36px; margin: 0 auto;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </div>
                <div style="font-weight: 700; font-size: 0.9375rem; color: #0F172A;">No products found</div>
                <div style="font-size: 0.8125rem; margin-top: 0.25rem; color: #64748B;">Try a different keyword or scan barcode</div>
            </div>
            @endforelse

            @if(count($searchResults) >= $catalogLimit)
            <div style="grid-column: 1 / -1; text-align: center; padding: 0.75rem 0;">
                <button
                    type="button"
                    wire:click="loadMoreProducts"
                    class="lj-btn-secondary"
                    style="padding: 0.5rem 1.25rem; font-size: 0.8125rem; font-weight: 600; border-radius: 0.5rem; cursor: pointer;">
                    + Load More Products (Showing {{ count($searchResults) }})
                </button>
            </div>
            @endif
        </div>
    </div>

    <!-- STICKY FLOATING CART BAR ON MOBILE (WHEN IN CATALOG VIEW & CART HAS ITEMS) -->
    @if(count($cart) > 0)
    <div
        class="lj-pos-mobile-floating-bar lg:hidden"
        x-show="mobilePosTab === 'catalog'"
        @click="mobilePosTab = 'cart'">
        <div class="lj-pos-floating-details">
            <div class="lj-pos-floating-count">
                {{ count($cart) }} {{ Str::plural('item', count($cart)) }}
            </div>
            <div class="lj-pos-floating-total">
                Total: Rs. {{ number_format($totals['total'] ?? 0, 2) }}
            </div>
        </div>
        <button type="button" class="lj-pos-floating-btn" @click.stop="mobilePosTab = 'cart'">
            <span>View Cart & Pay</span>
            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
        </button>
    </div>
    @endif
</section>
