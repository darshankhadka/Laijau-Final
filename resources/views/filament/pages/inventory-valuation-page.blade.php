<x-filament-panels::page>
    <div class="na-inventory-root" style="min-height: auto; padding: 0;">
        {{-- HEADER BANNER (Clean Minimalist Light Mode) --}}
        <div class="na-header-banner">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; width: 100%;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 22px; border: 1px solid #e2e8f0;">
                        📊
                    </div>
                    <div>
                        <h1 class="na-title-main" style="margin: 0;">
                            Inventory Valuation & Asset Ledger
                        </h1>
                        <p class="na-subtitle">
                            Landed cost asset valuation, moving average stock cost, and balance sheet inventory reconciliation.
                        </p>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="na-badge" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; font-size: 13px; font-weight: 700; padding: 6px 14px;">
                        Total Asset: Rs. {{ number_format($summary['total_valuation_npr'], 2) }}
                    </span>
                </div>
            </div>
        </div>

        {{-- TOP VALUATION STATS --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <div class="na-card na-card-p6">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--na-text-muted);">Total Stock Value (NPR)</div>
                <div style="font-size: 24px; font-weight: 800; color: #C5A059; margin-top: 6px; font-family: monospace;">
                    Rs. {{ number_format($summary['total_valuation_npr'], 2) }}
                </div>
                <div style="font-size: 12px; color: var(--na-text-secondary); margin-top: 4px;">
                    Landed cost asset basis
                </div>
            </div>

            <div class="na-card na-card-p6">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--na-text-muted);">Accounting Compliance</div>
                <div style="font-size: 20px; font-weight: 800; color: #10B981; margin-top: 6px;">
                    NAS 2 Compliant
                </div>
                <div style="font-size: 12px; color: var(--na-text-secondary); margin-top: 4px;">
                    Authoritative Nepal standard
                </div>
            </div>

            <div class="na-card na-card-p6">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--na-text-muted);">Total Units On Hand</div>
                <div style="font-size: 24px; font-weight: 800; color: #3B82F6; margin-top: 6px; font-family: monospace;">
                    {{ number_format($summary['total_units_on_hand']) }} pcs
                </div>
                <div style="font-size: 12px; color: var(--na-text-secondary); margin-top: 4px;">
                    Across all warehouse nodes
                </div>
            </div>

            <div class="na-card na-card-p6">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--na-text-muted);">Average Landed Unit Cost</div>
                <div style="font-size: 24px; font-weight: 800; color: #8B5CF6; margin-top: 6px; font-family: monospace;">
                    {{ $summary['total_units_on_hand'] > 0 ? 'Rs. ' . number_format($summary['total_valuation_npr'] / $summary['total_units_on_hand'], 2) : 'Rs. 0.00' }}
                </div>
                <div style="font-size: 12px; color: var(--na-text-secondary); margin-top: 4px;">
                    Weighted average
                </div>
            </div>
        </div>

        {{-- GENERAL LEDGER ACCOUNT 2210 RECONCILIATION CARD --}}
        @if(isset($reconciliation))
        <div class="na-card na-card-p6" style="margin-bottom: 24px; border-left: 4px solid {{ $reconciliation['is_balanced'] ? '#10B981' : '#EF4444' }};">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">{{ $reconciliation['is_balanced'] ? '✅' : '⚠️' }}</span>
                        <h2 style="font-family: 'Cinzel', serif, Georgia; font-size: 16px; font-weight: 700; margin: 0; color: var(--na-text);">
                            Nepal Accounting Standards (NAS) Ledger Reconciliation — Account {{ $reconciliation['gl_account_number'] }} ({{ $reconciliation['gl_account_name'] }})
                        </h2>
                    </div>
                    <p style="font-size: 13px; color: var(--na-text-muted); margin: 4px 0 0 30px;">
                        Automated comparison between physical warehouse stock valuation and posted double-entry ledger balance.
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                    <div style="text-align: right;">
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--na-text-muted); font-weight: 700;">GL Account 2210 Balance</div>
                        <div style="font-size: 16px; font-weight: 700; color: var(--na-text); font-family: monospace;">
                            Rs. {{ number_format($reconciliation['gl_balance_npr'], 2) }}
                        </div>
                    </div>

                    <div style="text-align: right;">
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--na-text-muted); font-weight: 700;">Difference (Variance)</div>
                        <div style="font-size: 16px; font-weight: 700; font-family: monospace; color: {{ $reconciliation['is_balanced'] ? '#10B981' : '#EF4444' }};">
                            Rs. {{ number_format($reconciliation['variance_npr'], 2) }}
                        </div>
                    </div>

                    <span class="na-badge" style="background: {{ $reconciliation['is_balanced'] ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' }}; color: {{ $reconciliation['is_balanced'] ? '#059669' : '#DC2626' }}; font-weight: 700; padding: 6px 12px;">
                        {{ $reconciliation['status'] }}
                    </span>
                </div>
            </div>
        </div>
        @endif

        {{-- DETAILED STOCK VALUATION TABLE --}}
        <div class="na-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--na-border); background: var(--na-card-subtle);">
                <h2 style="font-family: 'Cinzel', serif, Georgia; font-size: 16px; font-weight: 700; margin: 0; color: var(--na-text);">
                    SKU & Warehouse Asset Breakdown (FIFO / Specific Identification)
                </h2>
            </div>

            <div class="na-table-wrap">
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>Warehouse</th>
                            <th>Product</th>
                            <th>SKU / Spec</th>
                            <th style="text-align: right;">Quantity On Hand</th>
                            <th style="text-align: right;">Unit Cost (Rs.)</th>
                            <th style="text-align: right;">Asset Valuation (Rs.)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stockValuations as $item)
                        @php
                        $valNpr = $item->quantity_on_hand * (float)$item->unit_cost_npr;
                        @endphp
                        <tr>
                            <td>
                                <span class="na-badge" style="background: rgba(10, 46, 35, 0.12); color: var(--na-emerald); font-family: monospace; font-weight: 700;">
                                    {{ $item->warehouse?->code }}
                                </span>
                            </td>
                            <td style="font-weight: 600; color: var(--na-text);">
                                {{ $item->product?->name }}
                            </td>
                            <td style="color: var(--na-text-secondary); font-family: monospace; font-size: 12px;">
                                @if($item->variant)
                                {{ $item->variant->sku }} ({{ $item->variant->color }}/{{ $item->variant->size }})
                                @else
                                {{ $item->product?->sku ?? 'STANDALONE' }}
                                @endif
                            </td>
                            <td style="text-align: right; font-weight: 700; font-family: monospace; color: var(--na-text);">
                                {{ number_format($item->quantity_on_hand) }} pcs
                            </td>
                            <td style="text-align: right; font-family: monospace; color: var(--na-text-secondary);">
                                Rs. {{ number_format((float)$item->unit_cost_npr, 2) }}
                            </td>
                            <td style="text-align: right; font-weight: 700; font-family: monospace; color: #C5A059;">
                                Rs. {{ number_format($valNpr, 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" style="padding: 30px; text-align: center; color: var(--na-text-muted);">
                                No stock items currently recorded in physical inventory.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-transform: uppercase; color: var(--na-text); font-weight: 800;">
                                Total Inventory Asset (NAS Account 2210)
                            </td>
                            <td style="text-align: right; font-weight: 800; font-family: monospace; color: var(--na-text);">
                                {{ number_format($summary['total_units_on_hand']) }} pcs
                            </td>
                            <td style="text-align: right;">—</td>
                            <td style="text-align: right; color: #C5A059; font-size: 15px; font-weight: 800; font-family: monospace;">
                                Rs. {{ number_format($summary['total_valuation_npr'], 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>