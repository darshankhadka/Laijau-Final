        @if($activeTab === 'performance')
        <div style="flex: 1; overflow-y: auto; padding: 1.25rem;">
            <div style="font-weight: 800; font-size: 1.125rem; margin-bottom: 0.75rem; color: var(--lj-text);">
                Product-Level Offline Performance
            </div>
            <div style="background: var(--lj-card); border: 1px solid var(--lj-border); border-radius: 0.75rem; overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; text-align: left;">
                    <thead>
                        <tr style="background: var(--lj-card-subtle); border-bottom: 1px solid var(--lj-border); color: var(--lj-text-muted); font-size: 0.6875rem; text-transform: uppercase; font-weight: 700;">
                            <th style="padding: 0.75rem 1rem;">Product Name</th>
                            <th style="padding: 0.75rem 1rem; text-align: center;">Units Sold</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Total Revenue</th>
                            @if($canSeeMargins)
                            <th style="padding: 0.75rem 1rem; text-align: right;">Gross Margin</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($perf as $item)
                        <tr style="border-bottom: 1px solid var(--lj-border);">
                            <td style="padding: 0.75rem 1rem; font-weight: 700;">{{ $item['product_name'] ?? $item['name'] ?? 'Product' }}</td>
                            <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 600;">{{ $item['units_sold'] ?? $item['offline_units'] ?? 0 }}</td>
                            <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 800; color: var(--lj-emerald);">
                                {{ $currSymbol }}{{ number_format($item['revenue_npr'] ?? $item['offline_revenue'] ?? 0, 2) }}
                            </td>
                            @if($canSeeMargins)
                            <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 700; color: #059669;">
                                {{ number_format($item['margin_pct'] ?? $item['margin_percentage'] ?? 0, 1) }}%
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" style="padding: 3rem 1rem; text-align: center; color: var(--lj-text-muted);">
                                No sales data recorded yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif
