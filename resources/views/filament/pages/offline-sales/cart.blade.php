<section class="lj-cart-panel" :class="mobilePosTab !== 'cart' ? 'mobile-hidden' : ''">
    <!-- Mobile Return to Products Bar -->
    <div class="lj-mobile-back-row lg:hidden">
        <button type="button" @click="mobilePosTab = 'catalog'" class="lj-mobile-back-btn">
            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span>Back to Products Catalog</span>
        </button>
    </div>

    <!-- Customer & Channel Bar -->
    <div class="lj-cart-customer-strip">
        <button
            type="button"
            wire:click="openModal('customer_modal')"
            class="lj-cust-btn"
            title="Change customer">
            <div style="width: 30px; height: 30px; border-radius: 9999px; background: #ECFDF5; border: 1px solid #A7F3D0; display: flex; align-items: center; justify-content: center; color: #059669; flex-shrink: 0;">
                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <div style="min-width: 0; flex: 1;">
                <div class="lj-cust-name">{{ $customerName }}</div>
                <div class="lj-cust-phone">{{ $customerPhone ?: 'Walk-in Customer' }}</div>
            </div>
            <span style="font-size: 0.75rem; color: #059669; font-weight: 700;">Edit</span>
        </button>

        <select
            wire:model.live="salesChannel"
            class="lj-channel-select"
            title="Sales Channel">
            <option value="physical">In-Person Showroom</option>
            <option value="instagram">Instagram DM</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="showroom">Private VIP</option>
            <option value="event">Pop-up Event</option>
            <option value="wholesale">Wholesale</option>
        </select>
    </div>

    <!-- Line Items Scroll Area -->
    <div class="lj-cart-items-wrap">
        @if(empty($cart))
        <div class="lj-empty-cart">
            <div class="lj-empty-cart-icon" style="color: #94A3B8;">
                <svg style="width: 48px; height: 48px; margin: 0 auto;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <circle cx="9" cy="21" r="1" />
                    <circle cx="20" cy="21" r="1" />
                    <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
                </svg>
            </div>
            <div style="font-weight: 700; font-size: 0.9375rem; color: #0F172A; margin-top: 0.5rem;">Cart is Empty</div>
            <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.25rem;">Scan barcode or click items to add to ticket</div>
        </div>
        @else
        @foreach($cart as $cKey => $item)
        <div class="lj-cart-row">
            <div class="lj-cart-row-top">
                <div class="lj-cart-item-info">
                    @if(!empty($item['image']))
                    <img src="/storage/{{ $item['image'] }}" class="lj-cart-thumb" />
                    @else
                    <div class="lj-cart-thumb flex items-center justify-center bg-slate-100 text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    @endif
                    <div style="min-width: 0; flex: 1;">
                        <div class="lj-cart-title" title="{{ $item['name'] }}">{{ $item['name'] }}</div>
                        <div class="lj-cart-spec">
                            {{ $item['sku'] }}
                            @if(!empty($item['color']) || !empty($item['size']))
                            · {{ trim(($item['color'] ?? '') . ' ' . ($item['size'] ?? '')) }}
                            @endif
                        </div>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="removeItem('{{ $cKey }}')"
                    class="lj-cart-remove-btn"
                    title="Remove item">
                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="lj-cart-row-bottom">
                <div class="lj-stepper">
                    <button
                        type="button"
                        wire:click="decrementQuantity('{{ $cKey }}')"
                        class="lj-step-btn"
                        title="Decrease quantity">
                        −
                    </button>
                    <input
                        type="number"
                        min="1"
                        value="{{ $item['quantity'] }}"
                        wire:change="updateQuantity('{{ $cKey }}', $event.target.value)"
                        class="lj-step-input"
                        title="Direct quantity edit" />
                    <button
                        type="button"
                        wire:click="incrementQuantity('{{ $cKey }}')"
                        class="lj-step-btn"
                        title="Increase quantity">
                        +
                    </button>
                </div>

                <div class="text-right">
                    <div class="lj-cart-line-price">
                        {{ $currSymbol }}{{ number_format($item['total'], 2) }}
                    </div>
                    <div style="font-size: 0.6875rem; color: #64748B;">
                        @ {{ $currSymbol }}{{ number_format($item['unit_price'], 2) }}
                    </div>
                </div>
            </div>
        </div>
        @endforeach
        @endif
    </div>

    <!-- Pinned Bottom Checkout Summary Block -->
    <div class="lj-cart-summary">
        <!-- Subtotal & Discount Lines -->
        <div class="lj-summary-line">
            <span>Subtotal ({{ $totals['total_units'] }} items):</span>
            <span style="font-weight: 700; color: #0F172A;">{{ $currSymbol }}{{ number_format($totals['subtotal'], 2) }}</span>
        </div>

        <div class="lj-summary-line">
            <span>
                Discount:
                @if($totals['discount'] > 0)
                <button type="button" wire:click="clearDiscount" style="background: transparent; border: none; color: #DC2626; font-size: 0.6875rem; cursor: pointer; text-decoration: underline;">
                    (Clear)
                </button>
                @endif
            </span>
            <span>
                @if($totals['discount'] > 0)
                <span style="color: #DC2626; font-weight: 700;">-{{ $currSymbol }}{{ number_format($totals['discount'], 2) }}</span>
                @else
                <button type="button" wire:click="openModal('discount_modal')" class="lj-discount-trigger">
                    + Add Discount
                </button>
                @endif
            </span>
        </div>

        <!-- Role-gated Profit Display (Hidden for Cashiers) -->
        @if($canSeeMargins && count($cart) > 0)
        <div class="lj-profit-box">
            <span>Est. Landed: Rs. {{ number_format($totals['est_cost_npr'], 2) }}</span>
            <span>Margin: {{ $totals['est_margin'] }}% (Rs. {{ number_format($totals['est_profit_npr'], 2) }})</span>
        </div>
        @endif

        <!-- Dominant Grand Total Row -->
        <div class="lj-grand-total-row">
            <span class="lj-grand-total-label">Total Due:</span>
            <span class="lj-grand-total-amount">{{ $currSymbol }}{{ number_format($totals['total'], 2) }}</span>
        </div>

        <!-- Huge Primary Action: Proceed to Checkout (F8) -->
        <button
            id="lj-pos-pay-btn"
            type="button"
            wire:click="openCheckoutModal"
            @disabled(empty($cart))
            class="lj-checkout-btn"
            style="min-height: 48px; font-size: 0.9375rem; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 0.5rem; letter-spacing: 0.02em;">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="2" y="5" width="20" height="14" rx="2" />
                <line x1="2" y1="10" x2="22" y2="10" />
            </svg>
            <span>PROCEED TO PAYMENT (F8) →</span>
        </button>

        <!-- Secondary Cart Actions: Hold Sale & Discount -->
        <div class="lj-cart-actions-row">
            <button
                type="button"
                wire:click="holdCurrentSale"
                @disabled(empty($cart))
                class="lj-btn-secondary"
                title="Suspend current ticket to serve next customer (F9)"
                style="height: 38px; display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem; font-size: 0.75rem;">
                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="6" y="4" width="4" height="16" />
                    <rect x="14" y="4" width="4" height="16" />
                </svg>
                <span>Hold Sale (F9)</span>
            </button>

            <button
                type="button"
                wire:click="openModal('discount_modal')"
                @disabled(empty($cart))
                class="lj-btn-secondary"
                title="Apply percentage or flat discount"
                style="height: 38px; display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem; font-size: 0.75rem;">
                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
                <span>Discount</span>
            </button>
        </div>
    </div>
</section>
