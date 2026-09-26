<!-- MOBILE TOP VIEW SWITCHER (< 1024px) -->
<div class="lj-pos-mobile-toggle">
    <button
        type="button"
        @click="mobilePosTab = 'catalog'"
        class="lj-pos-mobile-toggle-btn"
        :class="mobilePosTab === 'catalog' ? 'active' : ''">
        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
        <span>Products Catalog</span>
    </button>
    <button
        type="button"
        @click="mobilePosTab = 'cart'"
        class="lj-pos-mobile-toggle-btn"
        :class="mobilePosTab === 'cart' ? 'active' : ''">
        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="9" cy="21" r="1" />
            <circle cx="20" cy="21" r="1" />
            <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
        </svg>
        <span>Cart ({{ count($cart) }})</span>
        @if(count($cart) > 0)
        <span class="lj-pos-mobile-toggle-badge">Rs. {{ number_format($totals['total'] ?? 0) }}</span>
        @endif
    </button>
</div>

@php
    $posState = $this->sessionState;
    $station = $this->activeStation;
@endphp

@if($posState['state'] === 'closing_required')
<div style="background: #FEF2F2; border: 1.5px solid #F87171; border-radius: 0.5rem; padding: 0.85rem 1.25rem; margin-bottom: 0.75rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 1px 3px rgba(220,38,38,0.08);">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <div style="width: 36px; height: 36px; border-radius: 0.375rem; background: #FFFFFF; border: 1px solid #FCA5A5; display: flex; align-items: center; justify-content: center; color: #DC2626;">
            <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>
        <div>
            <div style="font-size: 0.9rem; font-weight: 800; color: #991B1B;">
                CLOSING REQUIRED: {{ $station->location ?? 'Showroom' }} · {{ $station->name }}
            </div>
            <div style="font-size: 0.75rem; color: #B91C1C; margin-top: 0.15rem;">
                Unclosed session detected from <strong>{{ $posState['session']?->business_date }}</strong>. Normal POS sales are disabled until yesterday's cash is counted and reconciled.
            </div>
        </div>
    </div>
    <button
        type="button"
        wire:click="openCloseSessionModal({{ $posState['session']?->id }})"
        style="padding: 0.5rem 1rem; font-size: 0.8125rem; font-weight: 800; background: #DC2626; color: #FFFFFF; border: none; border-radius: 0.375rem; cursor: pointer; white-space: nowrap;">
        Finalize Closing Now
    </button>
</div>
@elseif($posState['state'] === 'opening_required')
<div style="background: #FFFBEB; border: 1.5px solid #FCD34D; border-radius: 0.5rem; padding: 0.75rem 1.25rem; margin-bottom: 0.75rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <div style="width: 36px; height: 36px; border-radius: 0.375rem; background: #FFFFFF; border: 1px solid #FDE68A; display: flex; align-items: center; justify-content: center; color: #D97706;">
            <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
        </div>
        <div>
            <div style="font-size: 0.875rem; font-weight: 800; color: #92400E;">
                Session Not Opened: {{ $station->location ?? 'Showroom' }} · {{ $station->name }}
            </div>
            <div style="font-size: 0.75rem; color: #B45309; margin-top: 0.1rem;">
                Today's session ({{ $posState['business_date'] }} NPT) requires an opening cash float to begin sales.
            </div>
        </div>
    </div>
    <button
        type="button"
        wire:click="openSessionModal"
        style="padding: 0.45rem 1rem; font-size: 0.75rem; font-weight: 800; background: #D97706; color: #FFFFFF; border: none; border-radius: 0.375rem; cursor: pointer; white-space: nowrap;">
        Open POS Session
    </button>
</div>
@endif

<!-- ========================================================================= -->
<!-- TAB 1: NEW SALE WORKSPACE (TWO-PANEL TERMINAL) -->
<!-- ========================================================================= -->
<main class="lj-pos-main">
    <!-- LEFT PANEL: PRODUCT SEARCH & CATALOG -->
    @include('filament.pages.offline-sales.catalog')

    <!-- RIGHT PANEL: CART & TRANSACTION SUMMARY -->
    @include('filament.pages.offline-sales.cart')
</main>
