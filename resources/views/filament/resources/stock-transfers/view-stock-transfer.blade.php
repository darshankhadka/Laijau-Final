<x-filament-panels::page>
    @php
        $transfer = $this->record;
        $transfer->loadMissing([
            'sourceWarehouse',
            'destinationWarehouse',
            'items.product.categories',
            'items.variant',
            'initiatedByUser',
            'receivedByUser',
        ]);

        $totalSent = (int)$transfer->items->sum('quantity_sent');
        $totalReceived = (int)$transfer->items->sum('quantity_received');
        $inTransitUnits = ($transfer->status === 'in_transit') ? $totalSent : max(0, $totalSent - $totalReceived);

        $statusColor = match($transfer->status) {
            'completed' => 'background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;',
            'in_transit' => 'background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;',
            'cancelled' => 'background: #ffe4e6; color: #9f1239; border: 1px solid #fecdd3;',
            default => 'background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;',
        };
    @endphp

    <style>
        .lj-tr-page {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            color: #0f172a;
        }
        .lj-tr-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }
        .lj-tr-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }
        .lj-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 700;
            line-height: 1.2;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .lj-routing-grid {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 1.25rem;
        }
        @media (max-width: 768px) {
            .lj-routing-grid {
                grid-template-columns: 1fr;
            }
        }
        .lj-wh-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem 1.25rem;
        }
        .lj-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        @media (max-width: 900px) {
            .lj-kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        .lj-kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }
        .lj-kpi-label {
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        .lj-kpi-val {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            font-family: monospace;
            line-height: 1.1;
        }
        .lj-kpi-sub {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 0.25rem;
        }
        .lj-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }
        .lj-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.6875rem;
            letter-spacing: 0.05em;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .lj-table td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .lj-table tr:hover {
            background: #fcfcfc;
        }
    </style>

    <div class="lj-tr-page">
        {{-- Header Summary Strip --}}
        <div class="lj-tr-header">
            <div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; font-family: monospace;">
                        {{ $transfer->transfer_number }}
                    </h2>
                    <span class="lj-badge" style="{{ $statusColor }}">
                        {{ ucfirst(str_replace('_', ' ', $transfer->status)) }}
                    </span>
                </div>
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-top: 0.35rem; font-size: 0.75rem; color: #64748b;">
                    <span>Created: <strong>{{ $transfer->created_at->timezone('Asia/Kathmandu')->format('M d, Y · H:i') }} NPT</strong></span>
                    @if($transfer->sent_at)
                        <span>•</span>
                        <span>Dispatched: <strong>{{ $transfer->sent_at->timezone('Asia/Kathmandu')->format('M d, Y · H:i') }} NPT</strong></span>
                    @endif
                    @if($transfer->received_at)
                        <span>•</span>
                        <span>Received: <strong>{{ $transfer->received_at->timezone('Asia/Kathmandu')->format('M d, Y · H:i') }} NPT</strong></span>
                    @endif
                </div>
            </div>

            <div style="font-size: 0.75rem; text-align: right; color: #64748b;">
                @if($transfer->notes)
                    <div style="max-width: 320px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                        📝 {{ $transfer->notes }}
                    </div>
                @endif
            </div>
        </div>

        {{-- In-Transit Stock Alert Banner --}}
        @if($transfer->status === 'in_transit')
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.75rem; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem;">
                <span style="font-size: 1.5rem;">🚚</span>
                <div>
                    <div style="font-weight: 700; color: #1e40af; font-size: 0.875rem;">
                        IN TRANSIT: {{ $totalSent }} physical units dispatched
                    </div>
                    <div style="font-size: 0.75rem; color: #3b82f6; margin-top: 0.15rem;">
                        Deducted from <strong>{{ $transfer->sourceWarehouse?->name }}</strong> on {{ $transfer->sent_at?->timezone('Asia/Kathmandu')->format('M d, Y h:i A') }} NPT. Pending arrival and physical verification at <strong>{{ $transfer->destinationWarehouse?->name }}</strong>.
                    </div>
                </div>
            </div>
        @endif

        {{-- Routing Information Strip --}}
        <div class="lj-tr-card" style="padding: 1.25rem;">
            <div class="lj-routing-grid">
                {{-- Origin Warehouse --}}
                <div class="lj-wh-box">
                    <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.35rem;">
                        ORIGIN FACILITY (FROM)
                    </div>
                    <div style="font-size: 1rem; font-weight: 800; color: #0f172a;">
                        {{ $transfer->sourceWarehouse?->name }}
                    </div>
                    <div style="font-size: 0.75rem; font-family: monospace; color: #475569; margin-top: 0.2rem;">
                        Code: {{ $transfer->sourceWarehouse?->code }}
                    </div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.2rem;">
                        📍 {{ $transfer->sourceWarehouse?->address ?: $transfer->sourceWarehouse?->city }}
                    </div>
                </div>

                {{-- Routing Arrow --}}
                <div style="text-align: center; color: #94a3b8; font-size: 1.5rem; font-weight: 700;">
                    &rarr;
                </div>

                {{-- Destination Warehouse --}}
                <div class="lj-wh-box">
                    <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.35rem;">
                        DESTINATION FACILITY (TO)
                    </div>
                    <div style="font-size: 1rem; font-weight: 800; color: #0f172a;">
                        {{ $transfer->destinationWarehouse?->name }}
                    </div>
                    <div style="font-size: 0.75rem; font-family: monospace; color: #475569; margin-top: 0.2rem;">
                        Code: {{ $transfer->destinationWarehouse?->code }}
                    </div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.2rem;">
                        📍 {{ $transfer->destinationWarehouse?->address ?: $transfer->destinationWarehouse?->city }}
                    </div>
                </div>
            </div>
        </div>

        {{-- 4 Core KPI Cards --}}
        <div class="lj-kpi-grid">
            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Transfer Line Items</div>
                <div class="lj-kpi-val">{{ $transfer->items->count() }}</div>
                <div class="lj-kpi-sub">Unique product / variant lines</div>
            </div>

            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Units Dispatched</div>
                <div class="lj-kpi-val" style="color: #2563eb;">{{ $totalSent }} <span style="font-size: 0.875rem; font-weight: 500; color: #64748b;">pcs</span></div>
                <div class="lj-kpi-sub">Sent from origin warehouse</div>
            </div>

            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Units Received</div>
                <div class="lj-kpi-val" style="color: #047857;">{{ $totalReceived }} <span style="font-size: 0.875rem; font-weight: 500; color: #64748b;">pcs</span></div>
                <div class="lj-kpi-sub">Credited at destination facility</div>
            </div>

            <div class="lj-kpi-card">
                <div class="lj-kpi-label">In-Transit Balance</div>
                <div class="lj-kpi-val" style="{{ $inTransitUnits > 0 ? 'color: #d97706;' : 'color: #64748b;' }}">
                    {{ $inTransitUnits }} <span style="font-size: 0.875rem; font-weight: 500; color: #64748b;">pcs</span>
                </div>
                <div class="lj-kpi-sub">{{ $inTransitUnits > 0 ? 'Currently on the road' : 'All lines accounted for' }}</div>
            </div>
        </div>

        {{-- Transfer Items Table --}}
        <div class="lj-tr-card">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
                <div style="font-size: 0.875rem; font-weight: 800; color: #0f172a;">
                    Package Manifest & Itemized Breakdown
                </div>
                <span style="font-size: 0.75rem; color: #64748b;">
                    {{ $transfer->items->count() }} line items
                </span>
            </div>

            <div style="overflow-x: auto;">
                <table class="lj-table">
                    <thead>
                        <tr>
                            <th>Item Description</th>
                            <th>Variant / Spec</th>
                            <th>SKU / Barcode</th>
                            <th style="text-align: right;">Dispatched Qty</th>
                            <th style="text-align: right;">Received Qty</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfer->items as $item)
                            @php
                                $prod = $item->product;
                                $var = $item->variant;
                                $spec = $var ? implode(' / ', array_filter([$var->color, $var->size])) : 'Standard';
                            @endphp
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #0f172a;">{{ $prod?->name ?: 'Product' }}</div>
                                    @if($prod?->categories?->first())
                                        <div style="font-size: 0.6875rem; color: #64748b;">{{ $prod->categories->first()->name }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="lj-badge" style="background: #f1f5f9; color: #475569;">
                                        {{ $spec ?: 'Standard' }}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-family: monospace; font-weight: 700; color: #0f172a;">
                                        {{ $var?->sku ?: ($prod?->sku ?: '—') }}
                                    </span>
                                </td>
                                <td style="text-align: right; font-family: monospace; font-weight: 700; color: #0f172a;">
                                    {{ $item->quantity_sent }} pcs
                                </td>
                                <td style="text-align: right; font-family: monospace; font-weight: 700; {{ (int)$item->quantity_received > 0 ? 'color: #047857;' : 'color: #64748b;' }}">
                                    {{ (int)$item->quantity_received }} pcs
                                </td>
                                <td>
                                    @if((int)$item->quantity_received >= (int)$item->quantity_sent && (int)$item->quantity_sent > 0)
                                        <span class="lj-badge" style="background: #ecfdf5; color: #065f46;">Verified</span>
                                    @elseif($transfer->status === 'in_transit')
                                        <span class="lj-badge" style="background: #eff6ff; color: #1e40af;">In Transit</span>
                                    @else
                                        <span class="lj-badge" style="background: #f1f5f9; color: #64748b;">Pending</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="padding: 2rem; text-align: center; color: #94a3b8;">
                                    No items attached to this transfer.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Operations Lifecycle Audit Strip --}}
        <div class="lj-tr-card" style="padding: 1.25rem;">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.75rem;">
                Audit Trail & Staff Attribution
            </div>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; font-size: 0.8125rem;">
                <div>
                    <div style="color: #64748b; font-size: 0.7rem; font-weight: 600;">INITIATED BY</div>
                    <div style="font-weight: 700; color: #0f172a; margin-top: 0.2rem;">
                        {{ $transfer->initiatedByUser?->name ?: 'Staff Member' }}
                    </div>
                    <div style="color: #64748b; font-size: 0.75rem;">
                        {{ $transfer->created_at->timezone('Asia/Kathmandu')->format('M d, Y · H:i') }} NPT
                    </div>
                </div>

                <div>
                    <div style="color: #64748b; font-size: 0.7rem; font-weight: 600;">DISPATCH STATUS</div>
                    <div style="font-weight: 700; color: #0f172a; margin-top: 0.2rem;">
                        {{ $transfer->sent_at ? 'Dispatched' : 'Pending Dispatch' }}
                    </div>
                    @if($transfer->sent_at)
                        <div style="color: #64748b; font-size: 0.75rem;">
                            {{ $transfer->sent_at->timezone('Asia/Kathmandu')->format('M d, Y · H:i') }} NPT
                        </div>
                    @endif
                </div>

                <div>
                    <div style="color: #64748b; font-size: 0.7rem; font-weight: 600;">RECEIVED BY</div>
                    <div style="font-weight: 700; color: #0f172a; margin-top: 0.2rem;">
                        {{ $transfer->receivedByUser?->name ?: ($transfer->received_at ? 'Warehouse Staff' : 'Awaiting Receipt') }}
                    </div>
                    @if($transfer->received_at)
                        <div style="color: #64748b; font-size: 0.75rem;">
                            {{ $transfer->received_at->timezone('Asia/Kathmandu')->format('M d, Y · H:i') }} NPT
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
