        @if($activeModal === 'sale_detail' && $selectedSaleDetail)
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card" style="max-width: 580px;">
                <div class="lj-modal-header">
                    <div>
                        <div class="lj-modal-title">Sale #{{ $selectedSaleDetail['sale_number'] }}</div>
                        <div style="font-size: 0.75rem; color: var(--lj-text-muted);">
                            {{ \Carbon\Carbon::parse($selectedSaleDetail['sold_at'])->format('d M Y, h:i A') }}
                        </div>
                    </div>
                    <button type="button" wire:click="closeModal" class="lj-modal-close">✕</button>
                </div>

                <div class="lj-modal-body">
                    <div style="display: flex; justify-content: space-between; background: var(--lj-card-subtle); padding: 0.75rem; border-radius: 0.5rem;">
                        <div>
                            <div style="font-size: 0.75rem; color: var(--lj-text-muted);">Customer</div>
                            <div style="font-weight: 700;">{{ $selectedSaleDetail['customer_name'] }}</div>
                            <div style="font-size: 0.75rem;">{{ $selectedSaleDetail['customer_phone'] }}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; color: var(--lj-text-muted);">Payment & Channel</div>
                            <div style="font-weight: 700;">{{ ucfirst(str_replace('_', ' ', $selectedSaleDetail['payment_method'])) }}</div>
                            <div style="font-size: 0.75rem;">{{ ucfirst($selectedSaleDetail['sales_channel']) }}</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.75rem; color: var(--lj-text-muted);">Total Amount</div>
                            <div style="font-size: 1.25rem; font-weight: 900; color: var(--lj-emerald);">
                                Rs. {{ number_format($selectedSaleDetail['total_amount'], 2) }}
                            </div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); text-transform: uppercase;">
                            Line Items ({{ count($selectedSaleDetail['items'] ?? []) }})
                        </div>
                        @foreach($selectedSaleDetail['items'] ?? [] as $it)
                        <div style="display: flex; justify-content: space-between; padding: 0.4rem 0.5rem; background: var(--lj-card); border: 1px solid var(--lj-border); border-radius: 0.375rem; font-size: 0.8125rem;">
                            <div>
                                <span style="font-weight: 700;">{{ $it['product_name'] }}</span>
                                @if(!empty($it['color']) || !empty($it['size']))
                                <span style="color: var(--lj-text-muted);">({{ trim(($it['color'] ?? '') . ' ' . ($it['size'] ?? '')) }})</span>
                                @endif
                                <span style="font-family: monospace; font-size: 0.7rem; opacity: 0.7;">· {{ $it['sku'] }}</span>
                            </div>
                            <div>
                                <span>{{ $it['quantity'] }} x Rs. {{ number_format($it['unit_price'], 2) }}</span>
                                <span style="font-weight: 800; margin-left: 0.5rem;">Rs. {{ number_format($it['total_price'], 2) }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="lj-modal-footer" style="justify-content: space-between;">
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        @if($selectedSaleDetail['status'] === 'completed')
                        <button
                            type="button"
                            wire:click="openVoidModal({{ $selectedSaleDetail['id'] }})"
                            style="background: transparent; border: 1px solid var(--lj-rose); color: var(--lj-rose); font-size: 0.75rem; font-weight: 700; padding: 0.4rem 0.75rem; border-radius: 0.375rem; cursor: pointer;">
                            Void / Return Sale
                        </button>
                        @else
                        <span style="color: var(--lj-rose); font-weight: 700; font-size: 0.75rem;">
                            Voided on {{ \Carbon\Carbon::parse($selectedSaleDetail['updated_at'])->format('d M Y') }}
                        </span>
                        @endif

                        <button
                            type="button"
                            wire:click="reprintReceipt({{ $selectedSaleDetail['id'] }})"
                            class="lj-btn-secondary"
                            style="height: 38px; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
                            <span>Thermal Receipt</span>
                        </button>
                    </div>

                    <button type="button" wire:click="closeModal" class="lj-btn-secondary" style="height: 38px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
        @endif
