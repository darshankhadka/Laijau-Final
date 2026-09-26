        @if($activeModal === 'held_sales')
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card">
                <div class="lj-modal-header">
                    <div class="lj-modal-title">Held / Suspended Sales ({{ count($heldSales) }})</div>
                    <button type="button" wire:click="closeModal" class="lj-modal-close">✕</button>
                </div>

                <div class="lj-modal-body">
                    @forelse($heldSales as $idx => $held)
                    <div style="background: var(--lj-card-subtle); border: 1px solid var(--lj-border); border-radius: 0.625rem; padding: 0.75rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                        <div>
                            <div style="font-weight: 800; font-size: 0.875rem; color: var(--lj-text);">
                                {{ $held['customer_name'] }}
                            </div>
                            <div style="font-size: 0.6875rem; color: var(--lj-text-muted);">
                                Held at {{ $held['held_at'] }} · {{ $held['items_count'] }} items
                            </div>
                            <div style="font-size: 0.9375rem; font-weight: 900; color: var(--lj-emerald); margin-top: 0.25rem;">
                                Rs. {{ number_format($held['total'], 2) }}
                            </div>
                        </div>

                        <div style="display: flex; gap: 0.35rem;">
                            <button
                                type="button"
                                wire:click="restoreHeldSale({{ $idx }})"
                                class="lj-add-btn"
                                style="padding: 0.4rem 0.75rem;">
                                Restore to Cart
                            </button>
                            <button
                                type="button"
                                wire:click="discardHeldSale({{ $idx }})"
                                style="background: transparent; border: 1px solid var(--lj-border); color: var(--lj-rose); border-radius: 0.375rem; padding: 0.4rem 0.5rem; cursor: pointer;"
                                title="Discard held sale">
                                ✕
                            </button>
                        </div>
                    </div>
                    @empty
                    <div style="text-align: center; padding: 2rem 1rem; color: var(--lj-text-muted);">
                        No held sales found.
                    </div>
                    @endforelse
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closeModal" class="lj-btn-secondary" style="height: 38px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
        @endif
