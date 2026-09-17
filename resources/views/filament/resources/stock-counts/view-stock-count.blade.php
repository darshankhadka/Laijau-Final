<x-filament-panels::page>
    @php
    $count = $this->record;
    $count->loadMissing([
    'warehouse',
    'items.product.categories',
    'items.variant',
    'conductedByUser',
    'reconciledByUser',
    ]);

    $statusColor = match($count->status) {
    'reconciled' => 'background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;',
    'completed' => 'background: #fef3c7; color: #92400e; border: 1px solid #fde68a;',
    'in_progress' => 'background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;',
    default => 'background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;',
    };

    $variance = (int)$count->total_variance_items;
    $varianceColor = match(true) {
    $variance === 0 => 'color: #047857;',
    $variance > 0 => 'color: #2563eb;',
    default => 'color: #e11d48;',
    };
    @endphp

    <style>
        .lj-cnt-page {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            color: #0f172a;
        }

        .lj-cnt-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .lj-cnt-header {
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

    <div class="lj-cnt-page">
        {{-- Header Summary Strip --}}
        <div class="lj-cnt-header">
            <div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; font-family: monospace;">
                        {{ $count->count_number }}
                    </h2>
                    <span class="lj-badge" style="{{ $statusColor }}">
                        {{ ucfirst(str_replace('_', ' ', $count->status)) }}
                    </span>
                    <span class="lj-badge" style="background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe;">
                        📍 {{ $count->warehouse?->name ?: 'Warehouse' }}
                    </span>
                </div>
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-top: 0.35rem; font-size: 0.75rem; color: #64748b;">
                    <span>Count Type: <strong>{{ ucfirst($count->count_type) }} Audit</strong></span>
                    <span>•</span>
                    <span>Audit Date: <strong>{{ $count->count_date ? $count->count_date->format('M d, Y') : $count->created_at->format('M d, Y') }}</strong></span>
                    @if($count->reconciled_at)
                    <span>•</span>
                    <span>Reconciled: <strong>{{ $count->reconciled_at->timezone('Asia/Kathmandu')->format('M d, Y · H:i') }} NPT</strong></span>
                    @endif
                </div>
            </div>

            <div style="font-size: 0.75rem; text-align: right; color: #64748b;">
                @if($count->notes)
                <div style="max-width: 350px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                    📝 {{ $count->notes }}
                </div>
                @endif
            </div>
        </div>

        {{-- 4 Core Audit KPI Cards --}}
        <div class="lj-kpi-grid">
            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Expected Units</div>
                <div class="lj-kpi-val">{{ number_format((int)$count->total_expected_items) }} <span style="font-size: 0.875rem; font-weight: 500; color: #64748b;">pcs</span></div>
                <div class="lj-kpi-sub">System balance at snapshot time</div>
            </div>

            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Counted Units</div>
                <div class="lj-kpi-val">{{ number_format((int)$count->total_counted_items) }} <span style="font-size: 0.875rem; font-weight: 500; color: #64748b;">pcs</span></div>
                <div class="lj-kpi-sub">Physically verified on shelves</div>
            </div>

            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Net Unit Variance</div>
                <div class="lj-kpi-val" style="{{ $varianceColor }}">
                    {{ $variance > 0 ? "+{$variance}" : (string)$variance }} <span style="font-size: 0.875rem; font-weight: 500; color: #64748b;">pcs</span>
                </div>
                <div class="lj-kpi-sub">
                    {{ $variance === 0 ? 'Exact 100% match' : ($variance > 0 ? 'Surplus inventory detected' : 'Inventory shortage / loss') }}
                </div>
            </div>

            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Variance Value (NPR)</div>
                <div class="lj-kpi-val" style="{{ (float)$count->total_variance_value_npr < 0 ? 'color: #e11d48;' : ((float)$count->total_variance_value_npr > 0 ? 'color: #2563eb;' : 'color: #047857;') }}">
                    Rs. {{ number_format((float)$count->total_variance_value_npr, 2) }}
                </div>
                <div class="lj-kpi-sub">Financial impact on cost of goods</div>
            </div>
        </div>

        {{-- Audited Items Breakdown Table --}}
        <div class="lj-cnt-card">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
                <div style="font-size: 0.875rem; font-weight: 800; color: #0f172a;">
                    Audited Line Items & Discrepancy Ledger
                </div>
                <span style="font-size: 0.75rem; color: #64748b;">
                    {{ $count->items->count() }} line items
                </span>
            </div>

            <div style="overflow-x: auto;">
                <table class="lj-table">
                    <thead>
                        <tr>
                            <th>Item Description</th>
                            <th>Variant / Spec</th>
                            <th>SKU</th>
                            <th style="text-align: right;">Expected</th>
                            <th style="text-align: right;">Counted</th>
                            <th style="text-align: right;">Variance</th>
                            <th style="text-align: right;">Unit Cost</th>
                            <th style="text-align: right;">Variance Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($count->items as $item)
                        @php
                        $prod = $item->product;
                        $var = $item->variant;
                        $spec = $var ? implode(' / ', array_filter([$var->color, $var->size])) : 'Standard';
                        $vQty = (int)$item->variance_quantity;
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
                            <td style="text-align: right; font-family: monospace; color: #64748b;">
                                {{ $item->expected_quantity }} pcs
                            </td>
                            <td style="text-align: right; font-family: monospace; font-weight: 700; color: #0f172a;">
                                {{ $item->counted_quantity }} pcs
                            </td>
                            <td style="text-align: right; font-family: monospace; font-weight: 800; {{ $vQty === 0 ? 'color: #047857;' : ($vQty > 0 ? 'color: #2563eb;' : 'color: #e11d48;') }}">
                                {{ $vQty > 0 ? "+{$vQty}" : (string)$vQty }}
                            </td>
                            <td style="text-align: right; font-family: monospace; color: #475569;">
                                Rs. {{ number_format((float)$item->unit_cost_npr, 2) }}
                            </td>
                            <td style="text-align: right; font-family: monospace; font-weight: 700; {{ (float)$item->variance_value_npr < 0 ? 'color: #e11d48;' : ((float)$item->variance_value_npr > 0 ? 'color: #2563eb;' : 'color: #047857;') }}">
                                Rs. {{ number_format((float)$item->variance_value_npr, 2) }}
                            </td>
                            <td>
                                @if($item->is_reconciled)
                                <span class="lj-badge" style="background: #ecfdf5; color: #065f46;">Reconciled</span>
                                @elseif($count->status === 'completed')
                                <span class="lj-badge" style="background: #fef3c7; color: #92400e;">Ready</span>
                                @else
                                <span class="lj-badge" style="background: #f1f5f9; color: #64748b;">Draft</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" style="padding: 2rem; text-align: center; color: #94a3b8;">
                                No items loaded into this count yet. Click "Populate Stock" to snapshot current balances.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Auditor & Reconciliation Audit Trail --}}
        <div class="lj-cnt-card" style="padding: 1.25rem;">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.75rem;">
                Audit Ownership & Verification
            </div>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; font-size: 0.8125rem;">
                <div>
                    <div style="color: #64748b; font-size: 0.7rem; font-weight: 600;">CONDUCTED BY</div>
                    <div style="font-weight: 700; color: #0f172a; margin-top: 0.2rem;">
                        {{ $count->conductedByUser?->name ?: 'Inventory Staff' }}
                    </div>
                    <div style="color: #64748b; font-size: 0.75rem;">
                        Created: {{ $count->created_at->timezone('Asia/Kathmandu')->format('M d, Y · H:i') }} NPT
                    </div>
                </div>

                <div>
                    <div style="color: #64748b; font-size: 0.7rem; font-weight: 600;">RECONCILIATION STATUS</div>
                    <div style="font-weight: 700; color: #0f172a; margin-top: 0.2rem;">
                        {{ $count->reconciledByUser?->name ?: ($count->reconciled_at ? 'System Admin' : 'Unreconciled') }}
                    </div>
                    @if($count->reconciled_at)
                    <div style="color: #64748b; font-size: 0.75rem;">
                        Reconciled on {{ $count->reconciled_at->timezone('Asia/Kathmandu')->format('M d, Y · H:i') }} NPT
                    </div>
                    @else
                    <div style="color: #94a3b8; font-size: 0.75rem;">
                        Awaiting final variance review and journal entry approval
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>