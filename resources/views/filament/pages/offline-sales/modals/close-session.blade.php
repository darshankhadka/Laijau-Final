@if($activeModal === 'close_session')
@php
    $closingData = $this->closingSessionDetails;
    $sessionToClose = $closingData['session'] ?? null;
    $calc = $closingData['calc'] ?? null;
    $counted = (float)$closingCashInput;
    $expected = (float)($calc['expected_cash'] ?? 0);
    $variance = round($counted - $expected, 2);
    $bDateStr = $sessionToClose && $sessionToClose->business_date ? \Carbon\Carbon::parse($sessionToClose->business_date)->toDateString() : null;
    $isOverdue = $bDateStr && $bDateStr < now('Asia/Kathmandu')->toDateString();

    $notesMap = [];
@endphp
<div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
    <div class="lj-modal-card" style="max-width: 680px; width: 100%; max-height: 94vh; display: flex; flex-direction: column; border-radius: 0.75rem; border: 1px solid #E2E8F0; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);">
        <!-- Modal Header -->
        <div class="lj-modal-header" style="border-bottom: 1px solid #E2E8F0; padding: 1rem 1.25rem; background: #FFFFFF; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div style="width: 38px; height: 38px; border-radius: 0.5rem; background: {{ $isOverdue ? '#FEF2F2' : '#F1F5F9' }}; border: 1px solid {{ $isOverdue ? '#FCA5A5' : '#E2E8F0' }}; display: flex; align-items: center; justify-content: center; color: {{ $isOverdue ? '#DC2626' : '#0F172A' }};">
                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0110 0v4" />
                    </svg>
                </div>
                <div>
                    <div class="lj-modal-title" style="font-size: 1.05rem; font-weight: 800; color: {{ $isOverdue ? '#DC2626' : '#0F172A' }}; letter-spacing: -0.01em;">
                        {{ $isOverdue ? 'Resolve Overdue Shift Closing' : 'POS Register Shift Closing' }}
                    </div>
                    <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.15rem;">
                        <strong>{{ $sessionToClose?->showroom_name ?? $sessionToClose?->station?->location ?? 'New Showroom' }} · {{ $sessionToClose?->station?->name ?? 'Terminal 1' }}</strong>
                        · Date: <strong>{{ $sessionToClose && $sessionToClose->business_date ? \Carbon\Carbon::parse($sessionToClose->business_date)->format('M d, Y') : now('Asia/Kathmandu')->format('M d, Y') }} (NPT)</strong>
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
            @if($isOverdue)
            <div style="background: #FEF2F2; border: 1.5px solid #FCA5A5; border-radius: 0.5rem; padding: 0.85rem 1rem; font-size: 0.8125rem; color: #991B1B; display: flex; gap: 0.65rem; align-items: flex-start;">
                <svg style="width: 20px; height: 20px; flex-shrink: 0; color: #DC2626; margin-top: 0.1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <div style="font-weight: 800; font-size: 0.875rem;">UNCLOSED SHIFT REQUIRING RECONCILIATION</div>
                    <div style="font-size: 0.75rem; margin-top: 0.2rem; line-height: 1.4;">
                        This register has an unclosed session from <strong>{{ $sessionToClose && $sessionToClose->business_date ? \Carbon\Carbon::parse($sessionToClose->business_date)->format('M d, Y') : '' }}</strong>.
                        Count physical cash and finalize yesterday's closing before opening today's sales.
                    </div>
                </div>
            </div>
            @endif

            <!-- 1. CASH RECONCILIATION OVERVIEW (LIVE COMPARISON) -->
            <div style="background: #FFFFFF; border: 1.5px solid #E2E8F0; border-radius: 0.625rem; padding: 0.85rem 1rem;">
                <div style="font-size: 0.75rem; font-weight: 800; color: #0F172A; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.65rem;">
                    Till Cash Reconciliation
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.65rem; text-align: center;">
                    <!-- Expected Cash -->
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 0.5rem; padding: 0.65rem 0.5rem;">
                        <div style="font-size: 0.6875rem; font-weight: 700; color: #64748B; text-transform: uppercase;">
                            Expected Cash
                        </div>
                        <div style="font-size: 1.15rem; font-weight: 800; font-family: monospace; color: #0F172A; margin-top: 0.2rem;">
                            Rs. {{ number_format($expected, 2) }}
                        </div>
                        <div style="font-size: 0.65rem; color: #64748B; margin-top: 0.15rem;">
                            Opening Float + Cash Sales
                        </div>
                    </div>

                    <!-- Physical Counted -->
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 0.5rem; padding: 0.65rem 0.5rem;">
                        <div style="font-size: 0.6875rem; font-weight: 700; color: #64748B; text-transform: uppercase;">
                            Physical Counted
                        </div>
                        <div style="font-size: 1.15rem; font-weight: 800; font-family: monospace; color: #059669; margin-top: 0.2rem;">
                            Rs. {{ number_format($counted, 2) }}
                        </div>
                        <div style="font-size: 0.65rem; color: #64748B; margin-top: 0.15rem;">
                            Actual physical cash
                        </div>
                    </div>

                    <!-- Variance -->
                    <div style="background: {{ $variance == 0 ? '#ECFDF5' : ($variance > 0 ? '#EFF6FF' : '#FEF2F2') }}; border: 1.5px solid {{ $variance == 0 ? '#A7F3D0' : ($variance > 0 ? '#BFDBFE' : '#FECACA') }}; border-radius: 0.5rem; padding: 0.65rem 0.5rem;">
                        <div style="font-size: 0.6875rem; font-weight: 800; color: {{ $variance == 0 ? '#065F46' : ($variance > 0 ? '#1E40AF' : '#991B1B') }}; text-transform: uppercase;">
                            Difference / Variance
                        </div>
                        <div style="font-size: 1.15rem; font-weight: 900; font-family: monospace; color: {{ $variance == 0 ? '#065F46' : ($variance > 0 ? '#1E40AF' : '#DC2626') }}; margin-top: 0.2rem;">
                            {{ $variance >= 0 ? ($variance > 0 ? '+' : '') : '' }}Rs. {{ number_format($variance, 2) }}
                        </div>
                        <div style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6875rem; font-weight: 800; color: {{ $variance == 0 ? '#065F46' : ($variance > 0 ? '#1E40AF' : '#DC2626') }}; margin-top: 0.15rem;">
                            @if($variance == 0)
                                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                <span>Balanced</span>
                            @elseif($variance > 0)
                                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                <span>Cash Over</span>
                            @else
                                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                <span>Cash Short</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. PRIMARY PHYSICAL COUNTED CASH INPUT (DIRECT INPUT & AUTO-MATCH) -->
            <div style="background: #FFFFFF; border: 2px solid {{ $variance == 0 ? '#059669' : ($variance > 0 ? '#2563EB' : '#DC2626') }}; border-radius: 0.625rem; padding: 1rem 1.15rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <div style="font-size: 0.8125rem; font-weight: 800; color: #0F172A; text-transform: uppercase; letter-spacing: 0.04em; display: flex; align-items: center; gap: 0.4rem;">
                            <svg style="width: 18px; height: 18px; color: {{ $variance == 0 ? '#059669' : ($variance > 0 ? '#2563EB' : '#DC2626') }};" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <rect x="2" y="6" width="20" height="12" rx="2" />
                                <circle cx="12" cy="12" r="2" />
                                <path d="M6 12h.01M18 12h.01" />
                            </svg>
                            <span>Count Physical Cash Float</span>
                        </div>
                        <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.15rem;">
                            Enter total physical cash counted in drawer or tap Match Expected below
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.35rem; align-items: center;">
                        <button
                            type="button"
                            wire:click="matchExpectedClosingCash"
                            style="padding: 0.35rem 0.75rem; font-size: 0.75rem; font-weight: 800; background: #ECFDF5; border: 1.5px solid #A7F3D0; color: #065F46; border-radius: 0.375rem; cursor: pointer; display: flex; align-items: center; gap: 0.3rem;">
                            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            <span>Match Expected (Rs. {{ number_format($expected, 2) }})</span>
                        </button>
                        @if($closingCashInput > 0)
                        <button
                            type="button"
                            wire:click="resetClosingCash"
                            style="padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 700; background: #FEF2F2; border: 1px solid #FECACA; color: #DC2626; border-radius: 0.375rem; cursor: pointer;">
                            Clear
                        </button>
                        @endif
                    </div>
                </div>

                <!-- Large Currency Input Box -->
                <div style="display: flex; align-items: stretch; border: 2px solid {{ $variance == 0 ? '#10B981' : ($variance > 0 ? '#93C5FD' : '#FCA5A5') }}; border-radius: 0.5rem; overflow: hidden; background: #FFFFFF; box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);">
                    <div style="background: {{ $variance == 0 ? '#ECFDF5' : ($variance > 0 ? '#EFF6FF' : '#FEF2F2') }}; padding: 0 1.25rem; font-weight: 800; font-size: 1.25rem; color: {{ $variance == 0 ? '#065F46' : ($variance > 0 ? '#1E40AF' : '#991B1B') }}; border-right: 1.5px solid {{ $variance == 0 ? '#A7F3D0' : ($variance > 0 ? '#BFDBFE' : '#FECACA') }}; display: flex; align-items: center; user-select: none;">
                        Rs.
                    </div>
                    <input
                        type="number"
                        step="10"
                        min="0"
                        wire:model.live.debounce.150ms="closingCashInput"
                        placeholder="0.00"
                        id="lj-pos-closing-cash-input"
                        autofocus
                        style="flex: 1; height: 48px; font-size: 1.35rem; font-weight: 800; font-family: monospace; border: none; padding: 0 1rem; outline: none; color: #0F172A; background: #FFFFFF; width: 100%; box-sizing: border-box;" />
                </div>

                <!-- Quick adjustment chips -->
                <div style="display: flex; gap: 0.35rem; flex-wrap: wrap; margin-top: 0.65rem;">
                    <button type="button" wire:click="addClosingCashChip(500)" style="padding: 0.3rem 0.65rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #F8FAFC; border: 1.5px solid #CBD5E1; color: #1E293B; cursor: pointer;">
                        +500
                    </button>
                    <button type="button" wire:click="addClosingCashChip(1000)" style="padding: 0.3rem 0.65rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #F8FAFC; border: 1.5px solid #CBD5E1; color: #1E293B; cursor: pointer;">
                        +1,000
                    </button>
                    <button type="button" wire:click="addClosingCashChip(2000)" style="padding: 0.3rem 0.65rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #F8FAFC; border: 1.5px solid #CBD5E1; color: #1E293B; cursor: pointer;">
                        +2,000
                    </button>
                    <button type="button" wire:click="addClosingCashChip(5000)" style="padding: 0.3rem 0.65rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #F8FAFC; border: 1.5px solid #CBD5E1; color: #1E293B; cursor: pointer;">
                        +5,000
                    </button>
                </div>
            </div>

            <!-- 4. MANDATORY SHORTAGE REASON (if Physical < Expected) -->
            @if($variance < 0)
            <div style="background: #FEF2F2; border: 1.5px solid #F87171; border-radius: 0.625rem; padding: 0.85rem 1rem; display: flex; flex-direction: column; gap: 0.65rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 0.8125rem; font-weight: 800; color: #991B1B; display: flex; align-items: center; gap: 0.35rem;">
                        <svg style="width: 16px; height: 16px; color: #DC2626;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Cash Shortage: Rs. {{ number_format(abs($variance), 2) }}</span>
                    </div>
                    <span style="font-size: 0.6875rem; font-weight: 800; color: #DC2626; background: #FFFFFF; border: 1px solid #FCA5A5; padding: 0.15rem 0.5rem; border-radius: 9999px;">
                        REASON REQUIRED *
                    </span>
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #991B1B; margin-bottom: 0.25rem;">
                        Reason for Shortage <span style="color: #DC2626;">*</span>
                    </label>
                    <select
                        wire:model.live="varianceReasonCode"
                        style="width: 100%; height: 38px; font-size: 0.8125rem; font-weight: 600; border: 1.5px solid {{ $errors->has('varianceReasonCode') ? '#DC2626' : '#FCA5A5' }}; border-radius: 0.375rem; background: #FFFFFF; color: #0F172A; padding: 0 0.75rem; outline: none;">
                        <option value="">-- Select a valid reason for cash shortage --</option>
                        <option value="customer_change_error">Customer change error</option>
                        <option value="cash_handling_error">Cash handling error</option>
                        <option value="previous_cash_discrepancy">Previous cash discrepancy</option>
                        <option value="authorized_adjustment">Authorized adjustment</option>
                        <option value="other">Other</option>
                    </select>
                    @if($errors->has('varianceReasonCode'))
                    <div style="font-size: 0.75rem; color: #DC2626; font-weight: 700; margin-top: 0.25rem;">
                        {{ $errors->first('varianceReasonCode') }}
                    </div>
                    @endif
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #991B1B; margin-bottom: 0.25rem;">
                        {{ $varianceReasonCode === 'other' ? 'Detailed Explanation (Required for Other) *' : 'Additional Notes / Explanation' }}
                    </label>
                    <textarea
                        wire:model="varianceReasonText"
                        rows="2"
                        placeholder="Provide details on why physical cash is short..."
                        style="width: 100%; font-size: 0.8125rem; border: 1.5px solid {{ $errors->has('varianceReasonText') ? '#DC2626' : ($varianceReasonCode === 'other' ? '#DC2626' : '#FCA5A5') }}; border-radius: 0.375rem; padding: 0.45rem 0.65rem; background: #FFFFFF; color: #0F172A; outline: none; box-sizing: border-box;"></textarea>
                    @if($errors->has('varianceReasonText'))
                    <div style="font-size: 0.75rem; color: #DC2626; font-weight: 700; margin-top: 0.25rem;">
                        {{ $errors->first('varianceReasonText') }}
                    </div>
                    @endif
                </div>
            </div>
            @elseif($variance > 0)
            <!-- OVERAGE REASON (if Physical > Expected) -->
            <div style="background: #EFF6FF; border: 1.5px solid #93C5FD; border-radius: 0.625rem; padding: 0.85rem 1rem; display: flex; flex-direction: column; gap: 0.65rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 0.8125rem; font-weight: 800; color: #1E40AF; display: flex; align-items: center; gap: 0.35rem;">
                        <svg style="width: 16px; height: 16px; color: #2563EB;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 16v-4m0-4h.01" />
                        </svg>
                        <span>Cash Surplus (Over): Rs. {{ number_format($variance, 2) }}</span>
                    </div>
                    <span style="font-size: 0.6875rem; font-weight: 800; color: #2563EB; background: #FFFFFF; border: 1px solid #BFDBFE; padding: 0.15rem 0.5rem; border-radius: 9999px;">
                        AUDIT NOTE
                    </span>
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #1E40AF; margin-bottom: 0.25rem;">
                        Reason for Overage
                    </label>
                    <select
                        wire:model.live="varianceReasonCode"
                        style="width: 100%; height: 38px; font-size: 0.8125rem; font-weight: 600; border: 1.5px solid #BFDBFE; border-radius: 0.375rem; background: #FFFFFF; color: #0F172A; padding: 0 0.75rem; outline: none;">
                        <option value="">-- Select reason for cash overage --</option>
                        <option value="customer_change_error">Customer change error</option>
                        <option value="cash_handling_error">Cash handling error</option>
                        <option value="previous_cash_discrepancy">Previous cash discrepancy</option>
                        <option value="authorized_adjustment">Authorized adjustment</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div>
                    <textarea
                        wire:model="varianceReasonText"
                        rows="2"
                        placeholder="Notes on cash surplus..."
                        style="width: 100%; font-size: 0.8125rem; border: 1.5px solid #BFDBFE; border-radius: 0.375rem; padding: 0.45rem 0.65rem; background: #FFFFFF; color: #0F172A; outline: none; box-sizing: border-box;"></textarea>
                </div>
            </div>
            @endif

            <!-- 5. CLOSING CONFIRMATION SUMMARY -->
            <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.625rem; padding: 0.85rem 1rem;">
                <div style="font-size: 0.75rem; font-weight: 800; color: #0F172A; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.5rem;">
                    Shift Register Summary
                </div>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.35rem 1.25rem; font-size: 0.78125rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748B;">Opening Cash Float:</span>
                        <span style="font-family: monospace; font-weight: 600;">Rs. {{ number_format($calc['opening_balance'] ?? 0, 2) }}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748B;">Net Cash Sales:</span>
                        <span style="font-family: monospace; color: #059669; font-weight: 700;">+Rs. {{ number_format($calc['cash_sales'] ?? 0, 2) }}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748B;">Digital Settlements:</span>
                        <span style="font-family: monospace; color: #2563EB; font-weight: 600;">Rs. {{ number_format($calc['digital_sales'] ?? 0, 2) }}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748B;">Total Net Revenue:</span>
                        <span style="font-family: monospace; font-weight: 700;">Rs. {{ number_format($calc['total_sales_amount'] ?? 0, 2) }}</span>
                    </div>
                    <div style="border-top: 1px dashed #E2E8F0; grid-column: span 2; margin: 0.15rem 0;"></div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748B;">Expected in Till:</span>
                        <span style="font-family: monospace; font-weight: 700; color: #0F172A;">Rs. {{ number_format($expected, 2) }}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748B;">Physical Counted:</span>
                        <span style="font-family: monospace; font-weight: 800; color: #059669;">Rs. {{ number_format($counted, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Dual Sign-off (Cashier & Manager) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                        Manager / Supervisor Sign-off
                    </label>
                    <input
                        type="text"
                        wire:model="closingManagerInput"
                        placeholder="Manager full name"
                        style="width: 100%; height: 38px; font-size: 0.8125rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; padding: 0 0.75rem; background: #FFFFFF; color: #0F172A; outline: none; box-sizing: border-box;" />
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                        Shift Closing Remarks
                    </label>
                    <input
                        type="text"
                        wire:model="closingNotesInput"
                        placeholder="General remarks or notes..."
                        style="width: 100%; height: 38px; font-size: 0.8125rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; padding: 0 0.75rem; background: #FFFFFF; color: #0F172A; outline: none; box-sizing: border-box;" />
                </div>
            </div>

            <!-- Error Banner -->
            @if($errors->has('close_session_error'))
            <div style="background: #FEF2F2; border: 1.5px solid #FCA5A5; border-radius: 0.5rem; padding: 0.75rem 1rem; font-size: 0.8125rem; color: #991B1B; display: flex; align-items: center; gap: 0.5rem;">
                <svg style="width: 18px; height: 18px; color: #DC2626; flex-shrink: 0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <span>{{ $errors->first('close_session_error') }}</span>
            </div>
            @endif
        </div>

        <!-- Modal Footer -->
        <div class="lj-modal-footer" style="border-top: 1px solid #E2E8F0; padding: 0.875rem 1.25rem; background: #FFFFFF; display: flex; justify-content: space-between; align-items: center;">
            <button
                type="button"
                wire:click="openModal('shift_report')"
                class="lj-btn-secondary"
                style="height: 42px; padding: 0 1rem; font-size: 0.8125rem; font-weight: 700; border-radius: 0.5rem; background: #F8FAFC; border: 1px solid #CBD5E1; color: #334155; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;">
                <svg style="width: 18px; height: 18px; color: #059669;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                <span>Preview Shift Report</span>
            </button>
            <div style="display: flex; gap: 0.5rem;">
                <button type="button" wire:click="closeModal" class="lj-btn-secondary" style="height: 42px; padding: 0 1.25rem; font-size: 0.8125rem; font-weight: 700; border-radius: 0.5rem; background: #FFFFFF; border: 1px solid #CBD5E1; color: #334155; cursor: pointer;">
                    Cancel
                </button>
                <button
                    type="button"
                    wire:click="closeDailySession"
                    wire:loading.attr="disabled"
                    class="lj-checkout-btn"
                    style="height: 42px; padding: 0 1.5rem; font-weight: 800; font-size: 0.9375rem; border-radius: 0.5rem; display: inline-flex; align-items: center; gap: 0.45rem; background: {{ $isOverdue ? '#DC2626' : '#0F172A' }}; color: #FFFFFF; border: none; cursor: pointer;">
                    <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0110 0v4" />
                    </svg>
                    <span>Confirm & Close Shift (Rs. {{ number_format($counted, 2) }})</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif
