        @if($activeModal === 'void_modal')
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card" style="max-width: 440px;">
                <div class="lj-modal-header">
                    <div class="lj-modal-title" style="color: var(--lj-rose);">Void Sale #{{ $voidSaleNumber }}</div>
                    <button type="button" wire:click="closeModal" class="lj-modal-close">✕</button>
                </div>

                <div class="lj-modal-body">
                    <div style="font-size: 0.8125rem; color: var(--lj-text-muted);">
                        Are you sure you want to void this offline sale? This action reverses the revenue transaction.
                    </div>

                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.35rem;">
                            Reason for Void:
                        </div>
                        <input
                            type="text"
                            wire:model.defer="voidReason"
                            class="lj-search-input"
                            style="height: 40px;" />
                    </div>

                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" wire:model.defer="voidRestockInventory" />
                        <span>Restock inventory items back to Showroom warehouse</span>
                    </label>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closeModal" class="lj-btn-secondary" style="height: 38px;">
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="processVoidSale"
                        style="background: var(--lj-rose); color: #FFFFFF; border: none; border-radius: 0.5rem; padding: 0.5rem 1.25rem; font-weight: 800; cursor: pointer;">
                        Confirm Void
                    </button>
                </div>
            </div>
        </div>
        @endif
