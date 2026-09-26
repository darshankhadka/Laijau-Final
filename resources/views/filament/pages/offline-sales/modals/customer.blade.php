        @if($activeModal === 'customer_modal')
        <div class="lj-modal-backdrop" wire:key="customer-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card" wire:key="customer-modal-card">
                <div class="lj-modal-header">
                    <div class="lj-modal-title">Select Customer</div>
                    <button type="button" wire:click="closeModal" class="lj-modal-close" title="Close dialog">✕</button>
                </div>

                <div class="lj-modal-body">
                    <!-- Walk-in Shortcut -->
                    <button
                        type="button"
                        wire:key="cust-walkin-btn"
                        wire:click="selectWalkInCustomer"
                        style="width: 100%; padding: 0.75rem 1rem; background: #F8FAFC; border: 1.5px dashed #CBD5E1; border-radius: 0.5rem; text-align: left; font-weight: 700; color: #0F172A; cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #64748B;"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <span>Walk-in Showroom Customer (Default)</span>
                        </div>
                        <span style="font-size: 0.75rem; color: #64748B;">Reset to Walk-in</span>
                    </button>

                    <!-- Search Existing Customers -->
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.35rem; text-transform: uppercase;">
                            Search Existing Customer
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="customerSearchQuery"
                            wire:keydown.enter.prevent="selectFirstMatchingCustomer"
                            placeholder="Search by name, phone or email... (Press Enter to select)"
                            class="lj-search-input"
                            style="height: 40px;"
                            autofocus />

                        <div style="max-height: 280px; overflow-y: auto; margin-top: 0.5rem; display: flex; flex-direction: column; gap: 0.35rem;">
                            @php
                                $list = $this->customersList;
                            @endphp
                            @forelse($list as $cust)
                            <button
                                type="button"
                                wire:key="cust-btn-{{ $cust['id'] }}"
                                wire:click="selectCustomer({{ $cust['id'] }})"
                                style="padding: 0.6rem 0.75rem; text-align: left; border: 1.5px solid var(--lj-border); border-radius: 0.5rem; background: var(--lj-card); cursor: pointer; display: flex; justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box; transition: all 0.15s ease;"
                                class="lj-cust-row">
                                <div style="pointer-events: none; min-width: 0;">
                                    <div style="font-weight: 700; font-size: 0.875rem; color: var(--lj-text);">{{ $cust['name'] ?: 'Customer #' . $cust['id'] }}</div>
                                    <div style="color: var(--lj-text-muted); font-size: 0.75rem;">{{ $cust['phone'] ?: ($cust['email'] ?: 'No contact details') }}</div>
                                </div>
                                <span style="pointer-events: none; font-size: 0.75rem; font-weight: 700; color: #059669; padding: 0.2rem 0.6rem; background: #ECFDF5; border-radius: 0.375rem; border: 1px solid #A7F3D0; flex-shrink: 0;">
                                    Select &rarr;
                                </span>
                            </button>
                            @empty
                            <div style="font-size: 0.8125rem; color: var(--lj-text-muted); padding: 1rem; text-align: center; background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 0.5rem;">
                                @if(!empty($customerSearchQuery))
                                    No registered customers matching "{{ $customerSearchQuery }}".<br>
                                    <span style="font-size: 0.75rem; color: #64748B;">Use the form below to register and attach.</span>
                                @else
                                    No registered customers found.
                                @endif
                            </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Quick Add Customer -->
                    <div style="background: var(--lj-card-subtle); border: 1px solid var(--lj-border); border-radius: 0.5rem; padding: 0.75rem;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">
                            + Register New Customer
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                            <input type="text" wire:model.defer="newCustName" placeholder="Full Name *" class="lj-search-input" style="height: 36px; font-size: 0.8125rem;" />
                            <input type="text" wire:model.defer="newCustPhone" placeholder="Phone (e.g. 9841234567)" class="lj-search-input" style="height: 36px; font-size: 0.8125rem;" />
                            <input type="email" wire:model.defer="newCustEmail" placeholder="Email (optional)" class="lj-search-input" style="height: 36px; font-size: 0.8125rem;" />
                            <button
                                type="button"
                                wire:click="createAndAttachCustomer"
                                class="lj-checkout-btn"
                                style="height: 36px; font-size: 0.8125rem;">
                                Register & Attach
                            </button>
                        </div>
                    </div>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closeModal" class="lj-btn-secondary" style="height: 38px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
        @endif
