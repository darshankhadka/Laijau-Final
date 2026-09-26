@if($activeModal === 'select_showroom')
<div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
    <div class="lj-modal-card" style="max-width: 480px; width: 100%; border-radius: 0.75rem; border: 1px solid #E2E8F0; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);">
        <div class="lj-modal-header" style="border-bottom: 1px solid #E2E8F0; padding: 1.25rem 1.5rem; background: #FFFFFF; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div class="lj-modal-title" style="font-size: 1.15rem; font-weight: 800; color: #0F172A; letter-spacing: -0.01em;">
                    Select Showroom
                </div>
                <div style="font-size: 0.78125rem; color: #64748B; margin-top: 0.2rem;">
                    Bind this terminal register to a physical showroom location
                </div>
            </div>
            @if(session('pos_selected_station_id'))
            <button type="button" wire:click="closeModal" class="lj-modal-close" style="color: #94A3B8; hover:color: #0F172A; background: none; border: none; cursor: pointer; padding: 0.35rem;" title="Close dialog">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            @endif
        </div>

        <div class="lj-modal-body" style="padding: 1.25rem; display: flex; flex-direction: column; gap: 0.85rem; background: #F8FAFC;">
            <!-- New Showroom -> Terminal 1 -->
            <button
                type="button"
                wire:click="selectShowroom(1)"
                style="width: 100%; padding: 1rem 1.25rem; text-align: left; background: #FFFFFF; border: 2px solid {{ $selectedStationId === 1 ? '#059669' : '#CBD5E1' }}; border-radius: 0.625rem; cursor: pointer; transition: all 0.15s ease; display: flex; align-items: center; justify-content: space-between; box-shadow: {{ $selectedStationId === 1 ? '0 0 0 1px #059669' : 'none' }};">
                <div style="display: flex; align-items: center; gap: 0.85rem;">
                    <div style="width: 42px; height: 42px; border-radius: 0.5rem; background: #ECFDF5; border: 1px solid #A7F3D0; display: flex; align-items: center; justify-content: center; color: #059669;">
                        <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 0.95rem; font-weight: 800; color: #0F172A;">
                            New Showroom
                        </div>
                        <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.15rem;">
                            Assigned to: <strong>Terminal 1</strong> · Shared Central Inventory
                        </div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    @if($selectedStationId === 1)
                    <span style="font-size: 0.7rem; font-weight: 800; color: #059669; background: #ECFDF5; padding: 0.2rem 0.55rem; border-radius: 9999px; border: 1px solid #A7F3D0;">Active</span>
                    @endif
                    <svg style="width: 18px; height: 18px; color: #94A3B8;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </button>

            <!-- Old Showroom -> Terminal 2 -->
            <button
                type="button"
                wire:click="selectShowroom(151)"
                style="width: 100%; padding: 1rem 1.25rem; text-align: left; background: #FFFFFF; border: 2px solid {{ $selectedStationId === 151 ? '#059669' : '#CBD5E1' }}; border-radius: 0.625rem; cursor: pointer; transition: all 0.15s ease; display: flex; align-items: center; justify-content: space-between; box-shadow: {{ $selectedStationId === 151 ? '0 0 0 1px #059669' : 'none' }};">
                <div style="display: flex; align-items: center; gap: 0.85rem;">
                    <div style="width: 42px; height: 42px; border-radius: 0.5rem; background: #EFF6FF; border: 1px solid #BFDBFE; display: flex; align-items: center; justify-content: center; color: #2563EB;">
                        <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 0.95rem; font-weight: 800; color: #0F172A;">
                            Old Showroom
                        </div>
                        <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.15rem;">
                            Assigned to: <strong>Terminal 2</strong> · Shared Central Inventory
                        </div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    @if($selectedStationId === 151)
                    <span style="font-size: 0.7rem; font-weight: 800; color: #059669; background: #ECFDF5; padding: 0.2rem 0.55rem; border-radius: 9999px; border: 1px solid #A7F3D0;">Active</span>
                    @endif
                    <svg style="width: 18px; height: 18px; color: #94A3B8;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </button>

            <!-- Operational Notice -->
            <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.5rem; padding: 0.65rem 0.85rem; font-size: 0.75rem; color: #64748B; display: flex; align-items: center; gap: 0.5rem;">
                <svg style="width: 16px; height: 16px; color: #059669; flex-shrink: 0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <span>Both showrooms sell from the same shared central inventory pool (<strong>WH-KTM-MAIN</strong>).</span>
            </div>
        </div>
    </div>
</div>
@endif
