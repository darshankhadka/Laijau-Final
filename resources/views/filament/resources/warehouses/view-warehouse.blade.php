<x-filament-panels::page>
    <style>
        .lj-wh-grid {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 1.25rem;
            margin-top: 1rem;
        }
        @media (max-width: 1024px) {
            .lj-wh-grid {
                grid-template-columns: 1fr;
            }
        }
        .lj-wh-card {
            background: #ffffff;
            border: 1px solid #e4e4e7;
            border-radius: 0.5rem;
            padding: 1.25rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            margin-bottom: 1.25rem;
        }
        .lj-wh-header {
            font-size: 0.8125rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #71717a;
            margin-bottom: 0.75rem;
            border-bottom: 1px solid #f4f4f5;
            padding-bottom: 0.5rem;
        }
        .lj-wh-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.4rem 0;
            font-size: 0.8125rem;
            border-bottom: 1px dashed #f4f4f5;
        }
        .lj-wh-row:last-child {
            border-bottom: none;
        }
        .lj-wh-label {
            color: #71717a;
            font-weight: 500;
        }
        .lj-wh-val {
            color: #09090b;
            font-weight: 600;
            text-align: right;
        }
        .lj-wh-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        @media (max-width: 768px) {
            .lj-wh-kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        .lj-wh-kpi {
            background: #fafafa;
            border: 1px solid #e4e4e7;
            border-radius: 0.375rem;
            padding: 0.875rem 1rem;
        }
        .lj-wh-kpi-label {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #71717a;
        }
        .lj-wh-kpi-value {
            font-size: 1.375rem;
            font-weight: 700;
            color: #09090b;
            margin-top: 0.25rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .lj-wh-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
            margin-top: 0.5rem;
        }
        .lj-wh-table th {
            background: #fafafa;
            color: #71717a;
            font-weight: 600;
            text-align: left;
            padding: 0.625rem 0.75rem;
            border-bottom: 1px solid #e4e4e7;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .lj-wh-table td {
            padding: 0.625rem 0.75rem;
            border-bottom: 1px solid #f4f4f5;
            color: #27272a;
        }
        .lj-wh-table tr:hover td {
            background: #fafafa;
        }
        .lj-wh-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 600;
        }
        .lj-badge-success { background: #dcfce7; color: #15803d; }
        .lj-badge-warning { background: #fef3c7; color: #b45309; }
        .lj-badge-danger { background: #fee2e2; color: #b91c1c; }
        .lj-badge-info { background: #e0f2fe; color: #0369a1; }
        .lj-badge-gray { background: #f4f4f5; color: #52525b; }
    </style>

    @php
        $wh = $this->record;
        $stockLevels = $wh->stockLevels()->with(['product', 'variant'])->get();
        $totalUnits = $stockLevels->sum('quantity_on_hand');
        $totalReserved = $stockLevels->sum('quantity_reserved');
        $activeLines = $stockLevels->where('quantity_on_hand', '>', 0)->count();
        $lowStockCount = $stockLevels->filter(fn($l) => $l->quantity_on_hand > 0 && $l->quantity_on_hand <= $l->reorder_point)->count();
        
        $recentMovements = $wh->stockMovements()->with(['product', 'variant', 'user'])->orderBy('id', 'desc')->limit(12)->get();
        $recentStock = $stockLevels->where('quantity_on_hand', '>', 0)->sortByDesc('quantity_on_hand')->take(10);
    @endphp

    <div class="lj-wh-grid">
        <!-- Facility Identity Sidebar -->
        <div>
            <div class="lj-wh-card">
                <div class="lj-wh-header">Facility Identity</div>
                
                <div style="margin-bottom: 1rem;">
                    <div style="font-size: 1.125rem; font-weight: 700; color: #09090b;">{{ $wh->name }}</div>
                    <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: 0.35rem; flex-wrap: wrap;">
                        <span style="font-family: monospace; font-weight: 600; color: #52525b; font-size: 0.75rem; background: #f4f4f5; padding: 2px 6px; border-radius: 4px;">{{ $wh->code }}</span>
                        <span class="lj-wh-badge lj-badge-info">{{ ucfirst(str_replace('_', ' ', $wh->type)) }}</span>
                        @if($wh->is_default)
                            <span class="lj-wh-badge lj-badge-success">Default Fulfillment</span>
                        @endif
                    </div>
                </div>

                <div class="lj-wh-row">
                    <span class="lj-wh-label">Facility Manager</span>
                    <span class="lj-wh-val">{{ $wh->manager_name ?: '—' }}</span>
                </div>
                <div class="lj-wh-row">
                    <span class="lj-wh-label">Phone</span>
                    <span class="lj-wh-val">{{ $wh->contact_phone ?: '—' }}</span>
                </div>
                <div class="lj-wh-row">
                    <span class="lj-wh-label">Email</span>
                    <span class="lj-wh-val">{{ $wh->contact_email ?: '—' }}</span>
                </div>
                <div class="lj-wh-row">
                    <span class="lj-wh-label">Location</span>
                    <span class="lj-wh-val">{{ $wh->city ?: '—' }}, {{ $wh->country ?: 'NP' }}</span>
                </div>
                <div class="lj-wh-row">
                    <span class="lj-wh-label">Address</span>
                    <span class="lj-wh-val">{{ $wh->address ?: '—' }}</span>
                </div>
                <div class="lj-wh-row">
                    <span class="lj-wh-label">Allow Sales</span>
                    <span class="lj-wh-val">{{ $wh->allow_sales ? 'Yes (Fulfillable)' : 'No (Hold Only)' }}</span>
                </div>
                <div class="lj-wh-row">
                    <span class="lj-wh-label">Facility Status</span>
                    <span class="lj-wh-val" style="color: {{ $wh->is_active ? '#15803d' : '#71717a' }};">
                        {{ $wh->is_active ? 'Active Operation' : 'Archived / Inactive' }}
                    </span>
                </div>

                @if($wh->notes)
                    <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #f4f4f5;">
                        <div style="font-size: 0.75rem; font-weight: 600; color: #71717a; text-transform: uppercase;">Logistics Notes</div>
                        <div style="font-size: 0.8125rem; color: #3f3f46; margin-top: 0.25rem; line-height: 1.4;">{{ $wh->notes }}</div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Main Workspace -->
        <div>
            <!-- KPIs -->
            <div class="lj-wh-kpi-grid">
                <div class="lj-wh-kpi">
                    <div class="lj-wh-kpi-label">Units On Hand</div>
                    <div class="lj-wh-kpi-value">{{ number_format($totalUnits) }}</div>
                </div>
                <div class="lj-wh-kpi">
                    <div class="lj-wh-kpi-label">Reserved Units</div>
                    <div class="lj-wh-kpi-value" style="color: {{ $totalReserved > 0 ? '#b45309' : '#09090b' }};">{{ number_format($totalReserved) }}</div>
                </div>
                <div class="lj-wh-kpi">
                    <div class="lj-wh-kpi-label">Active SKUs</div>
                    <div class="lj-wh-kpi-value">{{ number_format($activeLines) }}</div>
                </div>
                <div class="lj-wh-kpi">
                    <div class="lj-wh-kpi-label">Low Stock SKUs</div>
                    <div class="lj-wh-kpi-value" style="color: {{ $lowStockCount > 0 ? '#b91c1c' : '#09090b' }};">{{ number_format($lowStockCount) }}</div>
                </div>
            </div>

            <!-- Top Stock Lines in this Facility -->
            <div class="lj-wh-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <div class="lj-wh-header" style="margin-bottom: 0; border-bottom: none; padding-bottom: 0;">Stock Balances (Top 10)</div>
                    <a href="{{ route('filament.admin.resources.stock-levels.index') }}?tableFilters[warehouse_id][value]={{ $wh->id }}" style="font-size: 0.75rem; font-weight: 600; color: #0A2E23; text-decoration: underline;">View All {{ $activeLines }} Items in Overview →</a>
                </div>

                @if($recentStock->isEmpty())
                    <div style="text-align: center; padding: 2rem 1rem; color: #71717a; font-size: 0.8125rem;">
                        No active stock records currently assigned to this facility.
                    </div>
                @else
                    <div style="overflow-x: auto;">
                        <table class="lj-wh-table">
                            <thead>
                                <tr>
                                    <th>Product / SKU</th>
                                    <th>Barcode</th>
                                    <th style="text-align: right;">On Hand</th>
                                    <th style="text-align: right;">Reserved</th>
                                    <th style="text-align: right;">Available</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentStock as $level)
                                    @php
                                        $sku = $level->variant?->sku ?? $level->product?->sku ?? '—';
                                        $barcode = $level->variant?->barcode ?? $level->product?->barcode ?? '—';
                                    @endphp
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: #09090b;">{{ $level->product?->name }}</div>
                                            <div style="font-family: monospace; font-size: 0.7rem; color: #71717a;">{{ $sku }}</div>
                                        </td>
                                        <td style="font-family: monospace; font-size: 0.75rem;">{{ $barcode }}</td>
                                        <td style="text-align: right; font-weight: 700; font-family: monospace;">{{ $level->quantity_on_hand }}</td>
                                        <td style="text-align: right; font-family: monospace; color: #71717a;">{{ $level->quantity_reserved }}</td>
                                        <td style="text-align: right; font-weight: 700; font-family: monospace; color: #15803d;">{{ $level->available_quantity }}</td>
                                        <td>
                                            @if($level->quantity_on_hand <= 0)
                                                <span class="lj-wh-badge lj-badge-danger">Out of Stock</span>
                                            @elseif($level->quantity_on_hand <= $level->reorder_point)
                                                <span class="lj-wh-badge lj-badge-warning">Low Stock</span>
                                            @else
                                                <span class="lj-wh-badge lj-badge-success">In Stock</span>
                                            @endif
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="{{ route('filament.admin.resources.stock-levels.view', $level) }}" style="font-weight: 600; color: #0A2E23; text-decoration: underline; font-size: 0.75rem;">Inspect</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <!-- Recent Facility Ledger Movements -->
            <div class="lj-wh-card">
                <div class="lj-wh-header">Recent Stock Movements Ledger</div>

                @if($recentMovements->isEmpty())
                    <div style="text-align: center; padding: 2rem 1rem; color: #71717a; font-size: 0.8125rem;">
                        No historical stock ledger movements recorded for this facility yet.
                    </div>
                @else
                    <div style="overflow-x: auto;">
                        <table class="lj-wh-table">
                            <thead>
                                <tr>
                                    <th>Timestamp (NPT)</th>
                                    <th>Type</th>
                                    <th>Item / SKU</th>
                                    <th style="text-align: right;">Delta</th>
                                    <th>Reference / Reason</th>
                                    <th>Staff</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentMovements as $m)
                                    @php
                                        $delta = (int)$m->quantity;
                                        $isPositive = $delta > 0;
                                    @endphp
                                    <tr>
                                        <td style="white-space: nowrap; font-size: 0.75rem;">
                                            {{ $m->created_at ? $m->created_at->timezone('Asia/Kathmandu')->format('M d, Y H:i') : '—' }}
                                        </td>
                                        <td>
                                            <span class="lj-wh-badge lj-badge-gray">{{ ucfirst(str_replace('_', ' ', $m->movement_type)) }}</span>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600;">{{ $m->product?->name }}</div>
                                            <div style="font-family: monospace; font-size: 0.7rem; color: #71717a;">{{ $m->variant?->sku ?? $m->product?->sku }}</div>
                                        </td>
                                        <td style="text-align: right; font-family: monospace; font-weight: 700; color: {{ $isPositive ? '#15803d' : '#b91c1c' }};">
                                            {{ $isPositive ? "+{$delta}" : $delta }}
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;">{{ $m->reason ?: '—' }}</div>
                                            <div style="font-family: monospace; font-size: 0.7rem; color: #71717a;">{{ $m->reference_number ?: $m->reference_type }}</div>
                                        </td>
                                        <td style="font-size: 0.75rem; color: #52525b;">
                                            {{ $m->user?->name ?: 'System' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
