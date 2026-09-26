        @if($activeTab === 'history')
        <div style="flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 0.875rem;">
            <!-- Filter Bar -->
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; background: var(--lj-card); padding: 0.75rem; border-radius: 0.75rem; border: 1px solid var(--lj-border);">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="historySearch"
                    placeholder="Search by sale #, customer name, phone, product..."
                    class="lj-search-input"
                    style="flex: 1; min-width: 250px;" />

                <select wire:model.live="historyChannelFilter" class="lj-channel-select" style="min-width: 150px;">
                    <option value="">All Channels</option>
                    <option value="physical">Showroom</option>
                    <option value="instagram">Instagram</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="event">Pop-up</option>
                    <option value="wholesale">Wholesale</option>
                </select>

                <select wire:model.live="historyPaymentFilter" class="lj-channel-select" style="min-width: 150px;">
                    <option value="">All Payments</option>
                    <option value="cash">Cash</option>
                    <option value="esewa">eSewa</option>
                    <option value="khalti">Khalti</option>
                    <option value="bank_transfer">Fonepay / Bank</option>
                    <option value="card">Card</option>
                </select>

                <select wire:model.live="historyDateFilter" class="lj-channel-select" style="min-width: 160px;">
                    <option value="all_time">All Time (2026)</option>
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="this_month">This Month</option>
                    <option value="last_month">Last Month</option>
                    <option value="jan_2026">January 2026</option>
                    <option value="feb_2026">February 2026</option>
                    <option value="mar_2026">March 2026</option>
                    <option value="apr_2026">April 2026</option>
                    <option value="may_2026">May 2026</option>
                    <option value="jun_2026">June 2026</option>
                    <option value="jul_2026">July 2026</option>
                    <option value="aug_2026">August 2026</option>
                    <option value="sep_2026">September 2026</option>
                </select>

                <select wire:model.live="historyStatusFilter" class="lj-channel-select" style="min-width: 130px;">
                    <option value="">All Statuses</option>
                    <option value="completed">Completed</option>
                    <option value="voided">Voided</option>
                </select>
            </div>

            <!-- History Table -->
            <div style="background: var(--lj-card); border-radius: 0.75rem; border: 1px solid var(--lj-border); overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; text-align: left;">
                    <thead>
                        <tr style="background: var(--lj-card-subtle); border-bottom: 1px solid var(--lj-border); color: var(--lj-text-muted); font-size: 0.6875rem; text-transform: uppercase; font-weight: 700;">
                            <th style="padding: 0.75rem 1rem;">Sale #</th>
                            <th style="padding: 0.75rem 1rem;">Date</th>
                            <th style="padding: 0.75rem 1rem;">Customer</th>
                            <th style="padding: 0.75rem 1rem;">Channel</th>
                            <th style="padding: 0.75rem 1rem;">Payment</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Total</th>
                            <th style="padding: 0.75rem 1rem;">Status</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salesHistory as $sale)
                        <tr style="border-bottom: 1px solid var(--lj-border);">
                            <td style="padding: 0.75rem 1rem; font-family: monospace; font-weight: 700;">
                                #{{ $sale['sale_number'] }}
                            </td>
                            <td style="padding: 0.75rem 1rem; color: var(--lj-text-muted);">
                                {{ \Carbon\Carbon::parse($sale['sold_at'])->timezone('Asia/Kathmandu')->format('d M Y, h:i A') }}
                            </td>
                            <td style="padding: 0.75rem 1rem; font-weight: 600;">
                                {{ $sale['customer_name'] }}
                                @if(!empty($sale['customer_phone']))
                                <div style="font-size: 0.6875rem; color: var(--lj-text-muted);">{{ $sale['customer_phone'] }}</div>
                                @endif
                            </td>
                            <td style="padding: 0.75rem 1rem;">
                                <span style="font-size: 0.75rem; background: var(--lj-card-subtle); padding: 0.2rem 0.5rem; border-radius: 0.25rem;">
                                    {{ ucfirst($sale['sales_channel']) }}
                                </span>
                            </td>
                            <td style="padding: 0.75rem 1rem;">
                                <span style="font-size: 0.75rem; font-weight: 600;">
                                    {{ ucfirst(str_replace('_', ' ', $sale['payment_method'])) }}
                                </span>
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 800; color: var(--lj-emerald);">
                                {{ \App\Helpers\NepaliNumberHelper::formatCurrency($sale['total_amount'], 'Rs. ', 2) }}
                            </td>
                            <td style="padding: 0.75rem 1rem;">
                                @if($sale['status'] === 'completed')
                                <span style="background: var(--lj-success-light); color: var(--lj-success); font-weight: 700; font-size: 0.6875rem; padding: 0.2rem 0.5rem; border-radius: 9999px;">
                                    Completed
                                </span>
                                @else
                                <span style="background: var(--lj-rose-light); color: var(--lj-rose); font-weight: 700; font-size: 0.6875rem; padding: 0.2rem 0.5rem; border-radius: 9999px;">
                                    Voided
                                </span>
                                @endif
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: right;">
                                <div style="display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center;">
                                    <a
                                        href="{{ route('offline_sales.receipt', ['offlineSale' => $sale['id']]) }}"
                                        target="_blank"
                                        class="lj-btn-secondary"
                                        style="height: 30px; padding: 0 0.5rem; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;"
                                        title="Open printable receipt">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
                                        <span>Receipt</span>
                                    </a>
                                    <button
                                        type="button"
                                        wire:click="printViaAgent({{ $sale['id'] }})"
                                        class="lj-btn-secondary"
                                        style="height: 30px; padding: 0 0.5rem; font-size: 0.75rem; font-weight: 700; color: #059669; display: inline-flex; align-items: center; gap: 0.25rem;"
                                        title="Send directly to Showroom Print Agent">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                        <span>Print</span>
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="reprintReceipt({{ $sale['id'] }})"
                                        class="lj-btn-secondary"
                                        style="height: 30px; padding: 0 0.5rem; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.25rem;"
                                        title="Thermal receipt popup and WhatsApp share">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                                        <span>WA</span>
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="openSaleDetail({{ $sale['id'] }})"
                                        class="lj-btn-secondary"
                                        style="height: 30px; padding: 0 0.5rem; font-size: 0.75rem;">
                                        Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" style="padding: 3rem 1rem; text-align: center; color: var(--lj-text-muted);">
                                No offline sales recorded matching your search.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif
