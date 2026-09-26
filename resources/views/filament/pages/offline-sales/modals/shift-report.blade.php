@if($activeModal === 'shift_report')
@php
    $closingData = $this->closingSessionDetails;
    $sessDetail = $closingData['calc'] ?? [];
    $session = $closingData['session'] ?? null;
    $station = $this->activeStation;
@endphp
<div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
    <div class="lj-modal-card" style="max-width: 660px; width: 100%; max-height: 94vh; display: flex; flex-direction: column; border-radius: 0.75rem; border: 1px solid #E2E8F0; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);">
        <!-- Modal Header -->
        <div class="lj-modal-header" style="border-bottom: 1px solid #E2E8F0; padding: 1rem 1.25rem; background: #FFFFFF; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div style="width: 38px; height: 38px; border-radius: 0.5rem; background: #ECFDF5; border: 1px solid #A7F3D0; display: flex; align-items: center; justify-content: center; color: #059669;">
                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <div class="lj-modal-title" style="font-size: 1.05rem; font-weight: 800; color: #0F172A; letter-spacing: -0.01em;">
                        POS Shift Closing & Audit Report
                    </div>
                    <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.15rem;">
                        <strong>{{ $session?->showroom_name ?? $station?->location ?? 'New Showroom' }} · {{ $session?->terminal_name ?? $station?->name ?? 'Terminal 1' }}</strong>
                        · Date: <strong>{{ $sessDetail['business_date'] ?? now('Asia/Kathmandu')->toDateString() }} (NPT)</strong>
                    </div>
                </div>
            </div>
            <button type="button" wire:click="closeModal" class="lj-modal-close" style="color: #94A3B8; hover:color: #0F172A; background: none; border: none; cursor: pointer; padding: 0.35rem;" title="Close dialog">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Modal Body: A4 Preview and Thermal Container -->
        <div class="lj-modal-body" style="background: #F1F5F9; padding: 1.25rem 1rem; overflow-y: auto; flex: 1;">
            @include('filament.pages.offline-sales.print.a4')
            @include('filament.pages.offline-sales.print.thermal-report')
        </div>

        <!-- Modal Footer: Print Actions & Close -->
        <div class="lj-modal-footer" style="border-top: 1px solid #E2E8F0; padding: 0.875rem 1.25rem; background: #FFFFFF; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <button
                    type="button"
                    onclick="printPosShiftReport()"
                    class="lj-checkout-btn"
                    style="height: 42px; padding: 0 1.25rem; font-weight: 800; font-size: 0.875rem; border-radius: 0.5rem; display: inline-flex; align-items: center; gap: 0.45rem; background: #059669; color: #FFFFFF; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(5, 150, 105, 0.2);">
                    <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <span>Print Thermal (80mm)</span>
                </button>
                <button
                    type="button"
                    onclick="printPosShiftReportA4()"
                    class="lj-btn-secondary"
                    style="height: 42px; padding: 0 1.25rem; font-size: 0.8125rem; font-weight: 700; border-radius: 0.5rem; display: inline-flex; align-items: center; gap: 0.45rem; background: #FFFFFF; border: 1.5px solid #CBD5E1; color: #1E293B; cursor: pointer;">
                    <svg style="width: 18px; height: 18px; color: #2563EB;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    <span>Standard A4 / PDF</span>
                </button>
            </div>
            <button
                type="button"
                wire:click="closeModal"
                class="lj-btn-secondary"
                style="height: 42px; padding: 0 1.25rem; font-size: 0.8125rem; font-weight: 700; border-radius: 0.5rem; background: #FFFFFF; border: 1px solid #CBD5E1; color: #475569; cursor: pointer;">
                Close
            </button>
        </div>
    </div>
</div>
@endif
