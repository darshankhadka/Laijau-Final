        @if($activeModal === 'unknown_barcode')
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card" style="max-width: 440px; text-align: center;">
                <div class="lj-modal-body" style="padding: 2rem 1.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.75rem;">
                    <div style="width: 56px; height: 56px; border-radius: 9999px; background: #FEF2F2; color: #DC2626; display: flex; align-items: center; justify-content: center;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>

                    <div style="font-size: 1.25rem; font-weight: 800; color: #0F172A;">
                        Product not found
                    </div>

                    <div style="font-size: 0.8125rem; color: #64748B;">
                        The scanned barcode or SKU does not exist in active sellable inventory:
                    </div>

                    <div style="font-family: monospace; font-size: 1.1rem; font-weight: 900; background: #F8FAFC; border: 1px dashed #CBD5E1; padding: 0.5rem 1rem; border-radius: 0.5rem; letter-spacing: 0.05em; color: #0F172A;">
                        {{ $unknownBarcode }}
                    </div>

                    <div style="font-size: 0.75rem; color: #64748B;">
                        Never silently ignored. You can search manually by name, add this product, or cancel.
                    </div>
                </div>

                <div class="lj-modal-footer" style="flex-direction: column; gap: 0.5rem;">
                    <button
                        type="button"
                        wire:click="searchManuallyWithBarcode"
                        class="lj-checkout-btn"
                        style="width: 100%; height: 42px; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <span>Search Manually</span>
                    </button>
                    <div style="display: flex; gap: 0.5rem; width: 100%;">
                        <a
                            href="/intadmin/products/create"
                            target="_blank"
                            class="lj-btn-secondary"
                            style="flex: 1; height: 38px; display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem; text-decoration: none;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            <span>Add Product</span>
                        </a>
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="lj-btn-secondary"
                            style="flex: 1; height: 38px;">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif
