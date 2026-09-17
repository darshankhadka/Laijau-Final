<x-filament-panels::page>
    <div class="na-inventory-root" style="min-height: auto; padding: 0;">
        {{-- HEADER BANNER (Clean Minimalist Light Mode) --}}
        <div class="na-header-banner">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; width: 100%;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 22px; border: 1px solid #e2e8f0;">
                        📑
                    </div>
                    <div>
                        <h1 class="na-title-main" style="margin: 0;">
                            Enterprise Inventory Intelligence & Reporting
                        </h1>
                        <p class="na-subtitle">
                            Audited reports, stock velocity, aging, and procurement intelligence with instant CSV streaming.
                        </p>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="na-badge" style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; font-size: 13px; font-weight: 700; padding: 6px 14px;">
                        {{ count($reorder['recommendations']) }} SKUs Need Reorder
                    </span>
                </div>
            </div>
        </div>

        {{-- REPORT DOWNLOAD CARDS --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-bottom: 24px;">
            {{-- Card 1: Stock On Hand --}}
            <div class="na-card na-card-p6" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 22px;">📦</span>
                        <h2 style="font-family: 'Cinzel', serif, Georgia; font-size: 16px; font-weight: 700; margin: 0; color: var(--na-text);">
                            Stock On Hand Matrix
                        </h2>
                    </div>
                    <p style="font-size: 13px; color: var(--na-text-muted); margin: 8px 0 16px 0; line-height: 1.4;">
                        Complete snapshot of physical, reserved, available, damaged, and quarantined stock across all locations.
                    </p>
                </div>
                <div>
                    <a href="{{ route('filament.admin.pages.inventory-reports-page') }}?action=downloadStockOnHandCsv"
                        wire:click.prevent="downloadStockOnHandCsv"
                        class="na-btn na-btn-emerald" style="width: 100%; justify-content: center; padding: 12px 16px;">
                        📥 Export Stock On Hand (CSV)
                    </a>
                </div>
            </div>

            {{-- Card 2: Traceable Stock Movements --}}
            <div class="na-card na-card-p6" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 22px;">📜</span>
                        <h2 style="font-family: 'Cinzel', serif, Georgia; font-size: 16px; font-weight: 700; margin: 0; color: var(--na-text);">
                            Traceable Movement Ledger
                        </h2>
                    </div>
                    <p style="font-size: 13px; color: var(--na-text-muted); margin: 8px 0 16px 0; line-height: 1.4;">
                        Immutable double-entry stock audit log with signed deltas, before/after balances, user stamps, and reasons.
                    </p>
                </div>
                <div>
                    <a href="{{ route('filament.admin.pages.inventory-reports-page') }}?action=downloadMovementsCsv"
                        wire:click.prevent="downloadMovementsCsv"
                        class="na-btn na-btn-emerald" style="width: 100%; justify-content: center; padding: 12px 16px;">
                        📥 Export Movement Ledger (CSV)
                    </a>
                </div>
            </div>

            {{-- Card 3: Reorder Intelligence --}}
            <div class="na-card na-card-p6" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 22px;">⚡</span>
                        <h2 style="font-family: 'Cinzel', serif, Georgia; font-size: 16px; font-weight: 700; margin: 0; color: var(--na-text);">
                            Reorder & Procurement Report
                        </h2>
                    </div>
                    <p style="font-size: 13px; color: var(--na-text-muted); margin: 8px 0 16px 0; line-height: 1.4;">
                        AI-powered procurement recommendations based on safety thresholds, lead times, and current stockout risks.
                    </p>
                </div>
                <div>
                    <a href="{{ route('filament.admin.pages.inventory-reports-page') }}?action=downloadReorderCsv"
                        wire:click.prevent="downloadReorderCsv"
                        class="na-btn" style="width: 100%; justify-content: center; padding: 12px 16px; background: #C5A059; color: #0A2E23; font-weight: 700; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        📥 Export Reorder Report (CSV)
                    </a>
                </div>
            </div>
        </div>

        {{-- REORDER INTELLIGENCE RECOMMENDATIONS TABLE --}}
        <div class="na-card" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--na-border); background: var(--na-card-subtle); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 style="font-family: 'Cinzel', serif, Georgia; font-size: 16px; font-weight: 700; margin: 0; color: var(--na-text);">
                        Automated Procurement & Reorder Recommendations
                    </h2>
                    <p style="font-size: 12px; color: var(--na-text-muted); margin: 2px 0 0 0;">
                        Items that have reached or breached safety stock thresholds without active incoming purchase orders.
                    </p>
                </div>
                <span class="na-badge" style="background: rgba(239, 68, 68, 0.15); color: #DC2626; font-weight: 700; padding: 6px 12px;">
                    {{ count($reorder['recommendations']) }} SKUs At Risk
                </span>
            </div>

            <div class="na-table-wrap">
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>Warehouse</th>
                            <th>Product / SKU</th>
                            <th style="text-align: right;">On Hand</th>
                            <th style="text-align: right;">Available</th>
                            <th style="text-align: right;">Reorder Point</th>
                            <th style="text-align: right; color: #059669;">Recommended Qty</th>
                            <th style="text-align: right;">Est. Landed Cost</th>
                            <th>Primary Supplier</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reorder['recommendations'] as $rec)
                        <tr>
                            <td>
                                <span class="na-badge" style="background: rgba(10, 46, 35, 0.12); color: var(--na-emerald); font-family: monospace; font-weight: 700;">
                                    {{ $rec['warehouse_name'] }}
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--na-text);">{{ $rec['product_name'] }}</div>
                                <div style="font-size: 11px; color: var(--na-text-muted); font-family: monospace;">{{ $rec['sku'] }}</div>
                            </td>
                            <td style="text-align: right; font-weight: 700; font-family: monospace; color: var(--na-text);">
                                {{ $rec['on_hand'] }}
                            </td>
                            <td style="text-align: right; font-weight: 700; font-family: monospace; color: {{ $rec['available'] <= 0 ? '#DC2626' : '#D97706' }};">
                                {{ $rec['available'] }}
                            </td>
                            <td style="text-align: right; font-family: monospace; color: var(--na-text-secondary);">
                                {{ $rec['reorder_point'] }}
                            </td>
                            <td style="text-align: right; font-weight: 800; font-family: monospace; color: #059669; font-size: 14px;">
                                +{{ $rec['recommended_qty'] }} pcs
                            </td>
                            <td style="text-align: right; font-weight: 600; font-family: monospace; color: #C5A059;">
                                Rs. {{ number_format($rec['estimated_cost_npr'], 2) }}
                            </td>
                            <td style="color: var(--na-text-secondary);">
                                {{ $rec['supplier_name'] }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" style="padding: 30px; text-align: center; color: #10B981; font-weight: 600;">
                                ✨ All stock levels are currently healthy and above safety thresholds.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>