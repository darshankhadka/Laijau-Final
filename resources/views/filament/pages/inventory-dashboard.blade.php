<x-filament-panels::page>
    <div class="na-inventory-root" style="min-height: auto; padding: 0;">
        {{-- HERO BANNER (Clean Minimalist Light Mode) --}}
        <div class="na-header-banner">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; width: 100%;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 22px; border: 1px solid #e2e8f0;">
                        🏛️
                    </div>
                    <div>
                        <h1 class="na-title-main" style="margin: 0;">
                            Enterprise Inventory & Stock Engine
                        </h1>
                        <p class="na-subtitle">
                            Authoritative physical goods ledger, multi-warehouse routing, landed cost valuation & seamless Commerce / POS sync.
                        </p>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <span class="na-badge" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; font-size: 12px; padding: 6px 12px;">
                        ● Double-Entry Ledger Active
                    </span>
                    <span class="na-badge" style="background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; font-size: 12px; padding: 6px 12px;">
                        {{ $summary['total_warehouses'] }} Active Locations
                    </span>
                </div>
            </div>
        </div>

        {{-- QUICK ACTION LAUNCHPAD --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: 24px;">
            <a href="{{ route('filament.admin.resources.purchase-orders.create') }}" class="inv-quick-btn">
                <span style="font-size: 20px;">📥</span>
                <div>
                    <div style="font-weight: 700; color: var(--na-text);">New Purchase Order</div>
                    <div style="font-size: 11px; color: var(--na-text-muted); font-weight: 400;">Inbound receiving</div>
                </div>
            </a>

            <a href="{{ route('filament.admin.resources.stock-transfers.create') }}" class="inv-quick-btn">
                <span style="font-size: 20px;">🔄</span>
                <div>
                    <div style="font-weight: 700; color: var(--na-text);">New Transfer</div>
                    <div style="font-size: 11px; color: var(--na-text-muted); font-weight: 400;">Inter-warehouse route</div>
                </div>
            </a>

            <a href="{{ route('filament.admin.resources.stock-adjustments.create') }}" class="inv-quick-btn">
                <span style="font-size: 20px;">⚖️</span>
                <div>
                    <div style="font-weight: 700; color: var(--na-text);">Stock Adjustment</div>
                    <div style="font-size: 11px; color: var(--na-text-muted); font-weight: 400;">Damage, loss, found</div>
                </div>
            </a>

            <a href="{{ route('filament.admin.resources.stock-counts.create') }}" class="inv-quick-btn">
                <span style="font-size: 20px;">📋</span>
                <div>
                    <div style="font-weight: 700; color: var(--na-text);">Stock Count Audit</div>
                    <div style="font-size: 11px; color: var(--na-text-muted); font-weight: 400;">Physical reconciliation</div>
                </div>
            </a>

            <a href="{{ route('filament.admin.pages.inventory-valuation-page') }}" class="inv-quick-btn">
                <span style="font-size: 20px;">💎</span>
                <div>
                    <div style="font-weight: 700; color: var(--na-text);">Stock Valuation</div>
                    <div style="font-size: 11px; color: var(--na-text-muted); font-weight: 400;">COGS & asset breakdown</div>
                </div>
            </a>

            <a href="{{ route('filament.admin.pages.inventory-reports-page') }}" class="inv-quick-btn">
                <span style="font-size: 20px;">📊</span>
                <div>
                    <div style="font-weight: 700; color: var(--na-text);">Audit Reports</div>
                    <div style="font-size: 11px; color: var(--na-text-muted); font-weight: 400;">CSV exports & reorder</div>
                </div>
            </a>
        </div>

        {{-- TOP KPI SUMMARY CARDS --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
            {{-- Card 1: Total Inventory Valuation --}}
            <div class="na-card na-card-p6">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--na-text-muted);">
                            Stock Asset Value (NPR)
                        </div>
                        <div style="font-size: 24px; font-weight: 800; color: #C5A059; margin-top: 6px; font-family: monospace;">
                            Rs. {{ number_format($summary['total_valuation_npr'], 2) }}
                        </div>
                        <div style="font-size: 12px; color: var(--na-text-secondary); margin-top: 4px;">
                            NAS Compliant Inventory Ledger
                        </div>
                    </div>
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(197, 160, 89, 0.15); display: flex; align-items: center; justify-content: center; font-size: 20px; color: #C5A059;">
                        💎
                    </div>
                </div>
            </div>

            {{-- Card 2: Units On Hand --}}
            <div class="na-card na-card-p6">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--na-text-muted);">
                            Units On Hand (Total)
                        </div>
                        <div style="font-size: 24px; font-weight: 800; color: #10B981; margin-top: 6px; font-family: monospace;">
                            {{ number_format($summary['total_units_on_hand']) }} pcs
                        </div>
                        <div style="font-size: 12px; color: var(--na-text-secondary); margin-top: 4px;">
                            Across {{ $summary['total_warehouses'] }} locations
                        </div>
                    </div>
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); display: flex; align-items: center; justify-content: center; font-size: 20px; color: #10B981;">
                        📦
                    </div>
                </div>
            </div>

            {{-- Card 3: Units Reserved & Incoming --}}
            <div class="na-card na-card-p6">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--na-text-muted);">
                            Reserved & Incoming
                        </div>
                        <div style="font-size: 22px; font-weight: 800; color: #3B82F6; margin-top: 6px; font-family: monospace;">
                            {{ $summary['total_units_reserved'] }} Res / {{ $summary['total_units_incoming'] }} In
                        </div>
                        <div style="font-size: 12px; color: var(--na-text-secondary); margin-top: 4px;">
                            {{ $summary['open_purchase_orders'] }} open POs & {{ $summary['pending_transfers'] }} in-transit
                        </div>
                    </div>
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); display: flex; align-items: center; justify-content: center; font-size: 20px; color: #3B82F6;">
                        🚚
                    </div>
                </div>
            </div>

            {{-- Card 4: Low Stock Alert --}}
            <div class="na-card na-card-p6">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--na-text-muted);">
                            Stock Health Alerts
                        </div>
                        <div style="font-size: 22px; font-weight: 800; color: {{ $summary['low_stock_count'] > 0 ? '#EF4444' : '#10B981' }}; margin-top: 6px; font-family: monospace;">
                            {{ $summary['low_stock_count'] }} Low / {{ $summary['out_of_stock_count'] }} Out
                        </div>
                        <div style="font-size: 12px; color: var(--na-text-secondary); margin-top: 4px;">
                            Thresholds monitored
                        </div>
                    </div>
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: {{ $summary['low_stock_count'] > 0 ? 'rgba(239, 68, 68, 0.15)' : 'rgba(16, 185, 129, 0.15)' }}; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                        {{ $summary['low_stock_count'] > 0 ? '⚠️' : '✅' }}
                    </div>
                </div>
            </div>
        </div>

        {{-- WAREHOUSE DISTRIBUTION BREAKDOWN --}}
        <div style="margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h2 style="font-family: 'Cinzel', serif, Georgia; font-size: 16px; font-weight: 700; color: var(--na-text); margin: 0;">
                    Multi-Location Warehouse Inventory Distribution
                </h2>
                <a href="{{ route('filament.admin.resources.warehouses.index') }}" style="font-size: 12px; font-weight: 600; color: var(--na-gold); text-decoration: none;">
                    Manage Warehouses →
                </a>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                @foreach ($warehouses as $wh)
                <div class="inv-wh-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <div>
                            <span class="na-badge" style="background: rgba(10, 46, 35, 0.12); color: var(--na-emerald); font-family: monospace; font-size: 11px; font-weight: 700;">
                                {{ $wh['code'] }}
                            </span>
                            <h3 style="font-size: 14px; font-weight: 700; color: var(--na-text); margin: 6px 0 0 0;">
                                {{ $wh['name'] }}
                            </h3>
                        </div>
                        <span class="na-badge" style="{{ $wh['type'] === 'warehouse' ? 'background: #E0E7FF; color: #3730A3;' : ($wh['type'] === 'showroom_pos' ? 'background: #D1FAE5; color: #065F46;' : 'background: #FEF3C7; color: #92400E;') }}">
                            {{ ucfirst(str_replace('_', ' ', $wh['type'])) }}
                        </span>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding-top: 10px; border-top: 1px solid var(--na-border);">
                        <div>
                            <div style="font-size: 11px; color: var(--na-text-muted);">Physical Units</div>
                            <div style="font-size: 15px; font-weight: 700; color: var(--na-text); font-family: monospace;">{{ number_format($wh['total_units']) }} pcs</div>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--na-text-muted);">Valuation (NPR)</div>
                            <div style="font-size: 15px; font-weight: 700; color: #C5A059; font-family: monospace;">Rs. {{ number_format($wh['valuation_npr'], 2) }}</div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- TWO COLUMN SECTION: TRACEABLE LEDGER & REORDER ALERTS --}}
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; align-items: flex-start;">
            {{-- COLUMN 1: RECENT TRACEABLE STOCK MOVEMENTS --}}
            <div class="na-card" style="padding: 0; overflow: hidden;">
                <div style="padding: 16px 20px; border-bottom: 1px solid var(--na-border); display: flex; justify-content: space-between; align-items: center; background: var(--na-card-subtle);">
                    <h2 style="font-family: 'Cinzel', serif, Georgia; font-size: 15px; font-weight: 700; margin: 0; color: var(--na-text);">
                        Recent Traceable Stock Ledger Movements
                    </h2>
                    <a href="{{ route('filament.admin.resources.stock-movements.index') }}" style="font-size: 12px; font-weight: 600; color: var(--na-gold); text-decoration: none;">
                        View Complete Ledger →
                    </a>
                </div>

                <div class="na-table-wrap">
                    <table class="inv-table">
                        <thead>
                            <tr>
                                <th>Movement #</th>
                                <th>Type</th>
                                <th>Product / Variant</th>
                                <th>Location</th>
                                <th style="text-align: right;">Delta</th>
                                <th style="text-align: right;">Balance</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentMovements as $mov)
                            <tr>
                                <td style="font-family: monospace; font-weight: 700; color: var(--na-text);">
                                    {{ $mov->movement_number }}
                                </td>
                                <td>
                                    <span class="na-badge" style="{{ $mov->quantity > 0 ? 'background: #D1FAE5; color: #065F46;' : 'background: #FEE2E2; color: #991B1B;' }}">
                                        {{ ucfirst(str_replace('_', ' ', $mov->movement_type)) }}
                                    </span>
                                </td>
                                <td style="color: var(--na-text);">
                                    <strong>{{ $mov->product?->name }}</strong>
                                    @if($mov->variant)
                                    <span style="font-size: 11px; color: var(--na-text-muted);">({{ $mov->variant->color }}/{{ $mov->variant->size }})</span>
                                    @endif
                                </td>
                                <td style="color: var(--na-text-secondary); font-size: 12px;">
                                    {{ $mov->warehouse?->code }}
                                </td>
                                <td style="text-align: right; font-weight: 700; font-family: monospace; color: {{ $mov->quantity > 0 ? '#10B981' : '#EF4444' }};">
                                    {{ $mov->quantity > 0 ? "+{$mov->quantity}" : $mov->quantity }}
                                </td>
                                <td style="text-align: right; font-weight: 600; font-family: monospace; color: var(--na-text);">
                                    {{ $mov->quantity_after }}
                                </td>
                                <td style="color: var(--na-text-muted); font-size: 11px; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    {{ $mov->reason }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" style="padding: 24px; text-align: center; color: var(--na-text-muted);">
                                    No stock movements recorded yet.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- COLUMN 2: LOW STOCK & REORDER ACTION PANEL --}}
            <div class="na-card na-card-p6">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                    <h2 style="font-family: 'Cinzel', serif, Georgia; font-size: 15px; font-weight: 700; margin: 0; color: var(--na-text);">
                        Low Stock & Reorder Triggers
                    </h2>
                    <span class="na-badge" style="background: #FEE2E2; color: #991B1B; font-weight: 700;">
                        {{ count($lowStockItems) }} Items
                    </span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 10px;">
                    @forelse ($lowStockItems as $item)
                    <div style="padding: 10px 12px; background: var(--na-card-subtle); border-radius: 10px; border: 1px solid var(--na-border);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <div style="font-size: 12px; font-weight: 700; color: var(--na-text);">
                                    {{ $item->product?->name }}
                                </div>
                                <div style="font-size: 11px; color: var(--na-text-muted);">
                                    {{ $item->warehouse?->code }}
                                    @if($item->variant)
                                    • {{ $item->variant->sku }}
                                    @endif
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 13px; font-weight: 800; font-family: monospace; color: {{ $item->quantity_on_hand <= 0 ? '#DC2626' : '#D97706' }};">
                                    {{ $item->quantity_on_hand }} / {{ $item->reorder_point }} pcs
                                </div>
                                <div style="font-size: 10px; color: var(--na-text-muted);">
                                    Reorder: +{{ $item->reorder_quantity }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div style="text-align: center; padding: 24px 0; color: #10B981; font-size: 13px; font-weight: 600;">
                        ✨ All stock levels are above reorder thresholds.
                    </div>
                    @endforelse
                </div>

                <div style="margin-top: 16px; border-top: 1px solid var(--na-border); padding-top: 12px; text-align: center;">
                    <a href="{{ route('filament.admin.pages.inventory-reports-page') }}" style="font-size: 12px; font-weight: 600; color: var(--na-gold); text-decoration: none;">
                        View Full Procurement Intelligence →
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>