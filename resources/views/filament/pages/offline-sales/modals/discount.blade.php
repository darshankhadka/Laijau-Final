        @if($activeModal === 'discount_modal')
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card" style="max-width: 440px;">
                <div class="lj-modal-header">
                    <div class="lj-modal-title">Apply Sale Discount</div>
                    <button type="button" wire:click="closeModal" class="lj-modal-close">✕</button>
                </div>

                <div class="lj-modal-body" x-data="{ fixedAmt: 0, reason: 'Courtesy Discount' }">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">
                            Quick Percentage Discount
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem;">
                            @foreach([5, 10, 15, 20] as $pct)
                            <button
                                type="button"
                                wire:click="applyDiscountPercent({{ $pct }})"
                                class="lj-btn-secondary"
                                style="height: 42px; font-size: 0.9375rem; font-weight: 800; color: var(--lj-emerald);">
                                {{ $pct }}%
                            </button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.35rem; text-transform: uppercase;">
                            Or Custom Fixed Amount (Rs.)
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <input
                                type="number"
                                x-model="fixedAmt"
                                placeholder="e.g. 500"
                                class="lj-search-input"
                                style="height: 42px;" />
                            <button
                                type="button"
                                @click="$wire.applyFixedDiscount(fixedAmt, reason)"
                                class="lj-checkout-btn"
                                style="width: auto; height: 42px; padding: 0 1rem;">
                                Apply
                            </button>
                        </div>
                    </div>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closeModal" class="lj-btn-secondary" style="height: 38px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
        @endif
