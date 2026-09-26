        @if(($activeModal === 'receipt_modal' || $activeModal === 'sale_success') && $receiptData)
        <div class="lj-modal-backdrop">
            <div class="lj-modal-card" style="max-width: 440px;">
                <div class="lj-modal-header">
                    <div>
                        <div class="lj-modal-title">Sale Complete! #{{ $receiptData['sale_number'] }}</div>
                        <div style="font-size: 0.75rem; color: var(--lj-success); font-weight: 700;">
                            ✓ Stock decremented & recorded
                        </div>
                    </div>
                    <button type="button" wire:click="closeModal" class="lj-modal-close">✕</button>
                </div>

                <div class="lj-modal-body" style="background: #F1F5F9; padding: 1rem;">
                    @include('filament.pages.offline-sales.print.thermal')
                </div>

                <div class="lj-modal-footer" style="justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <button
                            type="button"
                            wire:click="printViaAgent({{ $receiptData['id'] }})"
                            class="lj-btn-secondary"
                            style="height: 42px; font-weight: 800; color: #059669; border-color: #059669; display: inline-flex; align-items: center; gap: 0.35rem;"
                            title="Direct hardware ESC/POS print via Showroom Local Print Agent">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            <span>Print Agent</span>
                        </button>

                        <button
                            type="button"
                            onclick="window.print()"
                            class="lj-btn-secondary"
                            style="height: 42px; font-weight: 800; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
                            <span>Print Receipt</span>
                        </button>

                        <a
                            href="{{ route('offline_sales.receipt', $receiptData['id']) }}?autoprint=1"
                            target="_blank"
                            class="lj-btn-secondary"
                            style="height: 42px; text-decoration: none; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;"
                            title="Open clean slip in separate window for network thermal printer">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            <span>Network Slip</span>
                        </a>

                        <label style="font-size: 0.75rem; color: var(--lj-text-muted); display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer; user-select: none;">
                            <input type="checkbox" id="lj-pos-autoprint-toggle" onchange="localStorage.setItem('lj_pos_autoprint', this.checked ? '1' : '0')">
                            <span>Auto-print</span>
                        </label>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        @if(!empty($whatsappUrl))
                        <a
                            href="{{ $whatsappUrl }}"
                            target="_blank"
                            class="lj-btn-secondary"
                            style="height: 42px; text-decoration: none; color: #059669; font-weight: 800; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                            <span>WhatsApp</span>
                        </a>
                        @endif

                        <button
                            type="button"
                            wire:click="closeModal"
                            class="lj-checkout-btn"
                            style="width: auto; height: 42px; padding: 0 1.25rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            <span>New Sale</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif
