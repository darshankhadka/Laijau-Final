        @if($activeModal === 'clear_cart_confirm')
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card" style="max-width: 420px; text-align: center;">
                <div class="lj-modal-body" style="padding: 1.75rem 1.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.75rem;">
                    <div style="width: 52px; height: 52px; border-radius: 9999px; background: #FEF2F2; color: #DC2626; display: flex; align-items: center; justify-content: center;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    </div>

                    <div style="font-size: 1.15rem; font-weight: 800; color: var(--lj-text);">
                        Clear Active Cart Ticket?
                    </div>

                    <div style="font-size: 0.8125rem; color: var(--lj-text-muted); line-height: 1.4;">
                        Are you sure you want to discard all items currently in this cart ticket?
                        <br>
                        <strong>Physical inventory will NOT be modified</strong> because this sale is uncompleted.
                    </div>
                </div>

                <div class="lj-modal-footer" style="justify-content: center; gap: 0.75rem;">
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="lj-btn-secondary"
                        style="flex: 1; height: 40px;">
                        Keep Cart
                    </button>
                    <button
                        type="button"
                        wire:click="clearCart"
                        style="flex: 1; height: 40px; background: var(--lj-rose); color: #FFFFFF; border: none; border-radius: 0.5rem; font-weight: 800; cursor: pointer;">
                        Yes, Clear Cart
                    </button>
                </div>
            </div>
        </div>
        @endif
