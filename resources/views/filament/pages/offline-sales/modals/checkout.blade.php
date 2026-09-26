@if($activeModal === 'checkout_modal')
<div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
    <div class="lj-modal-card" style="max-width: 520px; width: 100%; border-radius: 0.75rem; border: 1px solid #E2E8F0; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);">
        <div class="lj-modal-header" style="border-bottom: 1px solid #E2E8F0; padding: 1rem 1.25rem; background: #FFFFFF; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div class="lj-modal-title" style="font-size: 1.05rem; font-weight: 800; color: #0F172A; letter-spacing: -0.01em;">
                    Complete Showroom Sale
                </div>
                <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.15rem;">
                    {{ count($cart) }} Items · {{ $customerName }}
                </div>
            </div>
            <button type="button" wire:click="closeModal" class="lj-modal-close" style="color: #94A3B8; hover:color: #0F172A; background: none; border: none; cursor: pointer; padding: 0.35rem;" title="Close dialog">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="lj-modal-body" style="padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; background: #F8FAFC;">
            <!-- Total Payable Banner -->
            <div style="background: #FFFFFF; border: 1.5px solid #E2E8F0; border-radius: 0.625rem; padding: 1rem; text-align: center;">
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748B; letter-spacing: 0.05em;">
                    Total Amount Due
                </div>
                <div style="font-size: 2.25rem; font-weight: 900; color: #059669; font-family: monospace; margin-top: 0.25rem;">
                    {{ $currSymbol }}{{ number_format($totals['total'], 2) }}
                </div>
            </div>

            <!-- Payment Method Selection -->
            <div>
                <div style="font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.03em;">
                    Select Payment Method
                </div>
                <div class="lj-tender-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.5rem;">
                    <button
                        type="button"
                        wire:click="$set('paymentMethod', 'cash')"
                        class="lj-pm-pill {{ $paymentMethod === 'cash' ? 'active' : '' }}"
                        style="height: 48px; border-radius: 0.5rem; font-weight: 700; font-size: 0.8125rem; display: flex; align-items: center; justify-content: center; gap: 0.45rem; cursor: pointer; transition: all 0.15s ease; border: 1.5px solid {{ $paymentMethod === 'cash' ? '#059669' : '#E2E8F0' }}; background: {{ $paymentMethod === 'cash' ? '#ECFDF5' : '#FFFFFF' }}; color: {{ $paymentMethod === 'cash' ? '#065F46' : '#1E293B' }};">
                        <svg style="width: 18px; height: 18px; color: {{ $paymentMethod === 'cash' ? '#059669' : '#64748B' }};" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="2" y="6" width="20" height="12" rx="2" />
                            <circle cx="12" cy="12" r="2" />
                            <path d="M6 12h.01M18 12h.01" />
                        </svg>
                        <span>Cash</span>
                    </button>

                    <button
                        type="button"
                        wire:click="$set('paymentMethod', 'bank_transfer')"
                        class="lj-pm-pill {{ $paymentMethod === 'bank_transfer' ? 'active' : '' }}"
                        style="height: 48px; border-radius: 0.5rem; font-weight: 700; font-size: 0.8125rem; display: flex; align-items: center; justify-content: center; gap: 0.45rem; cursor: pointer; transition: all 0.15s ease; border: 1.5px solid {{ $paymentMethod === 'bank_transfer' ? '#059669' : '#E2E8F0' }}; background: {{ $paymentMethod === 'bank_transfer' ? '#ECFDF5' : '#FFFFFF' }}; color: {{ $paymentMethod === 'bank_transfer' ? '#065F46' : '#1E293B' }};">
                        <svg style="width: 18px; height: 18px; color: {{ $paymentMethod === 'bank_transfer' ? '#059669' : '#64748B' }};" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="3" width="7" height="7" />
                            <rect x="14" y="3" width="7" height="7" />
                            <rect x="14" y="14" width="7" height="7" />
                            <rect x="3" y="14" width="7" height="7" />
                        </svg>
                        <span>Fonepay QR</span>
                    </button>

                    <button
                        type="button"
                        wire:click="$set('paymentMethod', 'card')"
                        class="lj-pm-pill {{ $paymentMethod === 'card' ? 'active' : '' }}"
                        style="height: 48px; border-radius: 0.5rem; font-weight: 700; font-size: 0.8125rem; display: flex; align-items: center; justify-content: center; gap: 0.45rem; cursor: pointer; transition: all 0.15s ease; border: 1.5px solid {{ $paymentMethod === 'card' ? '#059669' : '#E2E8F0' }}; background: {{ $paymentMethod === 'card' ? '#ECFDF5' : '#FFFFFF' }}; color: {{ $paymentMethod === 'card' ? '#065F46' : '#1E293B' }};">
                        <svg style="width: 18px; height: 18px; color: {{ $paymentMethod === 'card' ? '#059669' : '#64748B' }};" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="2" y="5" width="20" height="14" rx="2" />
                            <line x1="2" y1="10" x2="22" y2="10" />
                        </svg>
                        <span>POS Card</span>
                    </button>

                    <button
                        type="button"
                        wire:click="$set('paymentMethod', 'esewa')"
                        class="lj-pm-pill {{ $paymentMethod === 'esewa' ? 'active' : '' }}"
                        style="height: 48px; border-radius: 0.5rem; font-weight: 700; font-size: 0.8125rem; display: flex; align-items: center; justify-content: center; gap: 0.45rem; cursor: pointer; transition: all 0.15s ease; border: 1.5px solid {{ $paymentMethod === 'esewa' ? '#059669' : '#E2E8F0' }}; background: {{ $paymentMethod === 'esewa' ? '#ECFDF5' : '#FFFFFF' }}; color: {{ $paymentMethod === 'esewa' ? '#065F46' : '#1E293B' }};">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 9999px; background: #10B981;"></span>
                        <span>eSewa</span>
                    </button>

                    <button
                        type="button"
                        wire:click="$set('paymentMethod', 'khalti')"
                        class="lj-pm-pill {{ $paymentMethod === 'khalti' ? 'active' : '' }}"
                        style="height: 48px; border-radius: 0.5rem; font-weight: 700; font-size: 0.8125rem; display: flex; align-items: center; justify-content: center; gap: 0.45rem; cursor: pointer; transition: all 0.15s ease; border: 1.5px solid {{ $paymentMethod === 'khalti' ? '#059669' : '#E2E8F0' }}; background: {{ $paymentMethod === 'khalti' ? '#ECFDF5' : '#FFFFFF' }}; color: {{ $paymentMethod === 'khalti' ? '#065F46' : '#1E293B' }};">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 9999px; background: #8B5CF6;"></span>
                        <span>Khalti</span>
                    </button>
                </div>
            </div>

            <!-- Cash Tendered & Change Section (When Cash is selected) -->
            @if($paymentMethod === 'cash')
            <div style="display: flex; flex-direction: column; gap: 0.625rem; background: #FFFFFF; border: 1.5px solid #E2E8F0; border-radius: 0.625rem; padding: 1rem;">
                <div style="font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">
                    Cash Received from Customer
                </div>

                <div class="lj-tender-input-wrap" style="position: relative; display: flex; align-items: center;">
                    <span class="lj-tender-prefix" style="position: absolute; left: 0.85rem; font-weight: 800; color: #64748B; font-size: 1.1rem;">Rs.</span>
                    <input
                        type="number"
                        step="0.01"
                        wire:model.live="cashReceived"
                        class="lj-tender-input"
                        placeholder="0.00"
                        style="width: 100%; height: 46px; padding-left: 2.75rem; font-size: 1.25rem; font-weight: 800; font-family: monospace; border: 1.5px solid #CBD5E1; border-radius: 0.375rem; outline: none;"
                        autofocus />
                </div>

                <!-- Quick Cash Helper Chips -->
                <div class="lj-quick-tender-row" style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                    <button type="button" wire:click="setExactCash" class="lj-quick-cash-chip" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; cursor: pointer;">
                        Exact (Rs. {{ number_format($totals['total'], 0) }})
                    </button>
                    <button type="button" wire:click="addCashReceived(100)" class="lj-quick-cash-chip" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #F8FAFC; border: 1px solid #E2E8F0; color: #1E293B; cursor: pointer;">+100</button>
                    <button type="button" wire:click="addCashReceived(500)" class="lj-quick-cash-chip" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #F8FAFC; border: 1px solid #E2E8F0; color: #1E293B; cursor: pointer;">+500</button>
                    <button type="button" wire:click="addCashReceived(1000)" class="lj-quick-cash-chip" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #F8FAFC; border: 1px solid #E2E8F0; color: #1E293B; cursor: pointer;">+1,000</button>
                    <button type="button" wire:click="addCashReceived(5000)" class="lj-quick-cash-chip" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #F8FAFC; border: 1px solid #E2E8F0; color: #1E293B; cursor: pointer;">+5,000</button>
                </div>

                <!-- Real-time Change Due Display -->
                @if($cashReceived >= $totals['total'])
                <div class="lj-change-banner success" style="background: #ECFDF5; border: 1.5px solid #A7F3D0; border-radius: 0.375rem; padding: 0.65rem 0.85rem; display: flex; justify-content: space-between; align-items: center; color: #065F46; font-weight: 800;">
                    <span style="font-size: 0.8125rem;">CHANGE TO RETURN:</span>
                    <span style="font-size: 1.25rem; font-family: monospace;">Rs. {{ number_format($this->cashChange, 2) }}</span>
                </div>
                @else
                <div class="lj-change-banner warning" style="background: #FFFBEB; border: 1.5px solid #FCD34D; border-radius: 0.375rem; padding: 0.65rem 0.85rem; display: flex; justify-content: space-between; align-items: center; color: #92400E; font-weight: 700;">
                    <span style="font-size: 0.8125rem;">AMOUNT SHORT:</span>
                    <span style="font-size: 1.1rem; font-family: monospace;">Rs. {{ number_format(max(0, $totals['total'] - $cashReceived), 2) }}</span>
                </div>
                @endif
            </div>
            @endif

            <!-- Digital / Card Payment Reference -->
            @if($paymentMethod !== 'cash')
            <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.625rem; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.35rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase;">
                        {{ $paymentMethod === 'card' ? 'Terminal Auth / Slip No.' : 'Digital QR / Txn Reference ID' }}
                    </div>
                    <span style="font-size: 0.6875rem; color: #64748B;">Optional</span>
                </div>
                <input
                    type="text"
                    wire:model.defer="internalNotes"
                    placeholder="{{ $paymentMethod === 'card' ? 'e.g. POS Auth #883921 / Card last 4 digits' : 'e.g. eSewa / Khalti Txn ID / Fonepay Trace #' }}"
                    style="width: 100%; height: 38px; font-size: 0.8125rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; padding: 0 0.75rem; background: #FFFFFF; color: #0F172A; outline: none;" />
            </div>
            @endif

            <!-- Sale Notes / Comments -->
            <input
                type="text"
                wire:model.defer="customerNotes"
                placeholder="Customer memo or special instructions (optional)..."
                style="width: 100%; height: 38px; font-size: 0.8125rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; padding: 0 0.75rem; background: #FFFFFF; color: #0F172A; outline: none;" />
        </div>

        <div class="lj-modal-footer" style="border-top: 1px solid #E2E8F0; padding: 0.875rem 1.25rem; background: #FFFFFF; display: flex; justify-content: flex-end; gap: 0.5rem;">
            <button
                type="button"
                wire:click="closeModal"
                class="lj-btn-secondary"
                style="height: 44px; padding: 0 1.25rem; font-size: 0.8125rem; font-weight: 700; border-radius: 0.5rem; background: #FFFFFF; border: 1px solid #CBD5E1; color: #334155; cursor: pointer;">
                Cancel
            </button>

            <button
                type="button"
                wire:click="completeSale"
                wire:loading.attr="disabled"
                @disabled($paymentMethod==='cash' && $cashReceived < $totals['total'])
                class="lj-checkout-btn"
                style="height: 44px; padding: 0 1.75rem; font-size: 0.9375rem; font-weight: 800; border-radius: 0.5rem; display: inline-flex; align-items: center; gap: 0.45rem; background: #059669; color: #FFFFFF; border: none; cursor: pointer;">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span wire:loading.remove>
                    COMPLETE SALE & ISSUE RECEIPT
                </span>
                <span wire:loading>
                    Processing Sale...
                </span>
            </button>
        </div>
    </div>
</div>
@endif
