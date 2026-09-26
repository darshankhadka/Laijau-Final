@if($activeModal === 'open_session')
@php
    $posState = $this->sessionState;
    $station = $this->activeStation;
@endphp
<div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
    <div class="lj-modal-card" style="max-width: 580px; width: 100%; max-height: 94vh; display: flex; flex-direction: column; border-radius: 0.75rem; border: 1px solid #E2E8F0; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);">
        <!-- Modal Header -->
        <div class="lj-modal-header" style="border-bottom: 1px solid #E2E8F0; padding: 1rem 1.25rem; background: #FFFFFF; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div style="width: 38px; height: 38px; border-radius: 0.5rem; background: #ECFDF5; border: 1px solid #A7F3D0; display: flex; align-items: center; justify-content: center; color: #059669;">
                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                </div>
                <div>
                    <div class="lj-modal-title" style="font-size: 1.05rem; font-weight: 800; color: #0F172A; letter-spacing: -0.01em;">
                        Open Daily POS Shift
                    </div>
                    <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.15rem;">
                        {{ $station->location ?? 'Showroom' }} · {{ $station->name ?? 'Terminal 1' }}
                    </div>
                </div>
            </div>
            <button type="button" wire:click="closeModal" class="lj-modal-close" style="color: #94A3B8; hover:color: #0F172A; background: none; border: none; cursor: pointer; padding: 0.35rem;" title="Close dialog">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="lj-modal-body" style="padding: 1.25rem; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 1rem; background: #F8FAFC;">
            <!-- Shift Metadata Card -->
            <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.625rem; padding: 0.85rem 1rem; display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.65rem 1rem; font-size: 0.75rem;">
                <div>
                    <div style="color: #64748B; font-weight: 500;">Showroom Location</div>
                    <div style="font-weight: 800; color: #0F172A; display: flex; align-items: center; gap: 0.4rem; margin-top: 0.15rem;">
                        <span>{{ $station->location ?: 'New Showroom' }}</span>
                        <button type="button" wire:click="openSelectShowroomModal" style="font-size: 0.6875rem; font-weight: 700; color: #059669; background: #ECFDF5; border: 1px solid #A7F3D0; padding: 0.1rem 0.4rem; border-radius: 4px; cursor: pointer;">
                            Switch
                        </button>
                    </div>
                </div>
                <div>
                    <div style="color: #64748B; font-weight: 500;">Assigned Terminal</div>
                    <div style="font-weight: 800; color: #0F172A; margin-top: 0.15rem;">
                        {{ $station->name ?? 'Terminal 1' }}
                    </div>
                </div>
                <div>
                    <div style="color: #64748B; font-weight: 500;">Operating Cashier</div>
                    <div style="font-weight: 700; color: #0F172A; margin-top: 0.15rem;">
                        {{ Auth::user()?->name ?? 'Cashier' }}
                    </div>
                </div>
                <div>
                    <div style="color: #64748B; font-weight: 500;">Business Date (NPT)</div>
                    <div style="font-weight: 800; color: #059669; font-family: monospace; margin-top: 0.15rem;">
                        {{ $posState['business_date'] ?? now('Asia/Kathmandu')->toDateString() }}
                    </div>
                </div>
            </div>

            <!-- HERO: PRIMARY OPENING CASH FLOAT INPUT (PROMINENT & NEVER HIDDEN) -->
            <div style="background: #FFFFFF; border: 2px solid #059669; border-radius: 0.625rem; padding: 1rem 1.15rem; box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.08);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <div>
                        <div style="font-size: 0.8125rem; font-weight: 800; color: #065F46; text-transform: uppercase; letter-spacing: 0.04em; display: flex; align-items: center; gap: 0.4rem;">
                            <svg style="width: 18px; height: 18px; color: #059669;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <rect x="2" y="6" width="20" height="12" rx="2" />
                                <circle cx="12" cy="12" r="2" />
                                <path d="M6 12h.01M18 12h.01" />
                            </svg>
                            <span>Count Physical Cash Float</span>
                        </div>
                        <div style="font-size: 0.75rem; color: #047857; margin-top: 0.15rem;">
                            Enter initial cash drawer balance before beginning retail sales
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 1.15rem; font-weight: 900; font-family: monospace; color: #065F46;">
                            Opening Cash Total: Rs. {{ number_format($openingCashInput, 2) }}
                        </div>
                        @if($openingCashInput > 0)
                        <button type="button" wire:click="resetOpeningCash" style="font-size: 0.7rem; font-weight: 700; color: #DC2626; background: #FEF2F2; border: 1px solid #FECACA; padding: 0.15rem 0.45rem; border-radius: 0.25rem; cursor: pointer; margin-top: 0.15rem;">
                            Reset Float
                        </button>
                        @endif
                    </div>
                </div>

                <!-- Large Currency Input Box -->
                <div style="display: flex; align-items: stretch; border: 2px solid #10B981; border-radius: 0.5rem; overflow: hidden; background: #FFFFFF; box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);">
                    <div style="background: #ECFDF5; padding: 0 1.25rem; font-weight: 800; font-size: 1.25rem; color: #065F46; border-right: 1.5px solid #A7F3D0; display: flex; align-items: center; user-select: none;">
                        Rs.
                    </div>
                    <input
                        type="number"
                        step="10"
                        min="0"
                        wire:model.live.debounce.150ms="openingCashInput"
                        placeholder="0.00"
                        id="lj-pos-opening-cash-input"
                        autofocus
                        style="flex: 1; height: 48px; font-size: 1.35rem; font-weight: 800; font-family: monospace; border: none; padding: 0 1rem; outline: none; color: #065F46; background: #FFFFFF; width: 100%; box-sizing: border-box;" />
                </div>

                <!-- Quick Preset Float Chips -->
                <div style="margin-top: 0.65rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem;">
                        <span style="font-size: 0.6875rem; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.03em;">
                            Quick Preset Float:
                        </span>
                        <span style="font-size: 0.6875rem; color: #64748B;">
                            Tap to add to opening till
                        </span>
                    </div>
                    <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                        <button type="button" wire:click="addOpeningCashChip(500)" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #F8FAFC; border: 1.5px solid #CBD5E1; color: #1E293B; cursor: pointer;">
                            +500
                        </button>
                        <button type="button" wire:click="addOpeningCashChip(1000)" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #F8FAFC; border: 1.5px solid #CBD5E1; color: #1E293B; cursor: pointer;">
                            +1,000
                        </button>
                        <button type="button" wire:click="addOpeningCashChip(2000)" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #F8FAFC; border: 1.5px solid #CBD5E1; color: #1E293B; cursor: pointer;">
                            +2,000
                        </button>
                        <button type="button" wire:click="addOpeningCashChip(5000)" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #ECFDF5; border: 1.5px solid #A7F3D0; color: #065F46; cursor: pointer;">
                            +5,000
                        </button>
                        <button type="button" wire:click="addOpeningCashChip(10000)" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #ECFDF5; border: 1.5px solid #A7F3D0; color: #065F46; cursor: pointer;">
                            +10,000
                        </button>
                        <button type="button" wire:click="resetOpeningCash" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #FEF2F2; border: 1.5px solid #FECACA; color: #DC2626; cursor: pointer;">
                            Clear
                        </button>
                    </div>
                </div>
            </div>



            <!-- Optional Opening Remarks -->
            <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                    Shift Remarks / Notes (Optional)
                </label>
                <input
                    type="text"
                    wire:model="openingNotesInput"
                    placeholder="e.g. Standard morning float counted and confirmed..."
                    style="width: 100%; height: 38px; font-size: 0.8125rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; padding: 0 0.75rem; background: #FFFFFF; color: #0F172A; outline: none; box-sizing: border-box;" />
            </div>

            <!-- Error Banner -->
            @if($errors->has('open_session_error'))
            <div style="background: #FEF2F2; border: 1.5px solid #FCA5A5; border-radius: 0.5rem; padding: 0.75rem 1rem; font-size: 0.8125rem; color: #991B1B; display: flex; align-items: center; gap: 0.5rem;">
                <svg style="width: 18px; height: 18px; color: #DC2626; flex-shrink: 0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <span>{{ $errors->first('open_session_error') }}</span>
            </div>
            @endif
        </div>

        <!-- Modal Footer -->
        <div class="lj-modal-footer" style="border-top: 1px solid #E2E8F0; padding: 0.875rem 1.25rem; background: #FFFFFF; display: flex; justify-content: flex-end; gap: 0.5rem;">
            <button type="button" wire:click="closeModal" class="lj-btn-secondary" style="height: 42px; padding: 0 1.25rem; font-size: 0.8125rem; font-weight: 700; border-radius: 0.5rem; background: #FFFFFF; border: 1px solid #CBD5E1; color: #334155; cursor: pointer;">
                Cancel
            </button>
            <button
                type="button"
                wire:click="openDailySession"
                wire:loading.attr="disabled"
                class="lj-checkout-btn"
                style="height: 42px; padding: 0 1.75rem; font-weight: 800; font-size: 0.9375rem; border-radius: 0.5rem; display: inline-flex; align-items: center; gap: 0.45rem; background: #059669; color: #FFFFFF; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(5, 150, 105, 0.2);">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>Confirm & Open Shift (Rs. {{ number_format($openingCashInput, 2) }})</span>
            </button>
        </div>
    </div>
</div>
@endif
