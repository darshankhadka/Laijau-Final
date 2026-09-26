<!-- Printable Shift Report & Audit Layout (A4 & Standard PDF) -->
@php
    $closingData = $this->closingSessionDetails;
    $session = $closingData['session'] ?? null;
    $calc = $closingData['calc'] ?? [];
    $station = $this->activeStation;

    $showroomName = $session?->showroom_name ?? ($station?->location ?: 'New Showroom');
    $terminalName = $session?->terminal_name ?? ($station?->name ?: 'Terminal 1');
    $businessDate = $session && $session->business_date ? \Carbon\Carbon::parse($session->business_date)->format('M d, Y') : now('Asia/Kathmandu')->format('M d, Y');
    $openedByName = $session?->opened_by_name ?? Auth::user()?->name ?? 'Cashier';
    $openedAtStr = $session?->opened_at ? \Carbon\Carbon::parse($session->opened_at)->format('h:i A') : 'N/A';
    $closedByName = $session?->closed_by_name ?? ($session?->isClosed() ? 'Closed' : 'Session Open');
    $closedAtStr = $session?->closed_at ? \Carbon\Carbon::parse($session->closed_at)->format('h:i A') : 'In Progress';

    $openingFloat = (float)($session?->opening_balance ?? ($calc['opening_balance'] ?? 0));
    $grossSales = (float)($calc['gross_sales_amount'] ?? (($calc['total_sales_amount'] ?? 0) + ($calc['total_discount_amount'] ?? 0)));
    $totalDiscount = (float)($calc['total_discount_amount'] ?? 0);
    $netSales = (float)($calc['total_sales_amount'] ?? 0);
    $ordersCount = (int)($calc['total_sales_count'] ?? 0);
    $unitsSold = (int)($calc['total_units_sold'] ?? 0);
    $aov = $ordersCount > 0 ? round($netSales / $ordersCount, 2) : 0.00;

    $cashSales = (float)($calc['cash_sales'] ?? 0);
    $digitalSales = (float)($calc['digital_sales'] ?? 0);
    $changeGiven = (float)($calc['change_returned'] ?? 0);
    $expectedCash = (float)($calc['expected_cash'] ?? ($openingFloat + $cashSales));

    $physicalCounted = $session && $session->closing_cash_counted !== null
        ? (float)$session->closing_cash_counted
        : (float)$closingCashInput;

    $variance = $session && $session->cash_variance !== null
        ? (float)$session->cash_variance
        : round($physicalCounted - $expectedCash, 2);

    $varianceReasonCode = $session?->variance_reason_code ?: $this->varianceReasonCode;
    $varianceReasonText = $session?->variance_reason_text ?: $this->varianceReasonText;

    $denoms = $session?->denominations ?: $this->closingDenominations;

    $payments = $calc['payment_breakdown'] ?? [];
    $salesList = $calc['sales_list'] ?? [];
    $voidsList = $calc['voids_list'] ?? [];
    $discountedSales = $calc['discounted_sales'] ?? [];
@endphp
<div id="lj-pos-shift-report-content" class="lj-a4-printable" style="background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 0.5rem; padding: 2rem 1.75rem; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 11px; line-height: 1.45; color: #111827; box-shadow: 0 4px 15px rgba(0,0,0,0.06); margin: 0 auto; max-width: 620px;">
    <!-- Store Header -->
    <div style="text-align: center; margin-bottom: 12px;">
        <div style="font-size: 20px; font-weight: 900; letter-spacing: 2px; color: #111827;">LAIJAU</div>
        <div style="font-size: 12px; font-weight: 800; text-transform: uppercase; margin-top: 3px; color: #374151;">POS REGISTER SHIFT REPORT</div>
        <div style="font-size: 10px; font-weight: 700; color: #059669; margin-top: 2px;">{{ $showroomName }} · {{ $terminalName }}</div>
        <div style="font-size: 10px; color: #6B7280;">Bohara Tol, Kageshwori Manahara 09, Kathmandu</div>
        <div style="font-size: 10px; color: #6B7280;">Tel: +977-9843512095 · VAT/PAN: 604335148</div>
    </div>

    <div style="border-top: 1.5px solid #E5E7EB; margin: 10px 0;"></div>

    <!-- Metadata Grid -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px 16px; font-size: 11px; margin-bottom: 8px;">
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #6B7280;">Showroom:</span>
            <strong>{{ $showroomName }}</strong>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #6B7280;">Terminal:</span>
            <strong>{{ $terminalName }}</strong>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #6B7280;">Business Date:</span>
            <strong>{{ $businessDate }} (Nepal Time)</strong>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #6B7280;">Shift Status:</span>
            <strong style="color: {{ $session && $session->isClosed() ? '#4B5563' : '#059669' }};">{{ $session && $session->isClosed() ? 'CLOSED' : 'OPEN / ACTIVE' }}</strong>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #6B7280;">Cashier:</span>
            <strong>{{ $openedByName }} ({{ $openedAtStr }})</strong>
        </div>
        @if($session && $session->isClosed())
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #6B7280;">Closed By:</span>
            <strong>{{ $closedByName }} ({{ $closedAtStr }})</strong>
        </div>
        @else
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #6B7280;">Printed At:</span>
            <span>{{ now()->timezone('Asia/Kathmandu')->format('M d, Y · h:i A') }} NPT</span>
        </div>
        @endif
    </div>

    <div style="border-top: 1.5px solid #E5E7EB; margin: 10px 0;"></div>

    <!-- 1. SALES SUMMARY -->
    <div class="page-break-avoid" style="margin-bottom: 10px;">
        <div style="font-weight: 800; font-size: 11.5px; text-transform: uppercase; color: #111827; margin-bottom: 5px; border-bottom: 1px solid #E5E7EB; padding-bottom: 2px;">
            1. SALES SUMMARY
        </div>
        <div style="display: flex; justify-content: space-between; padding: 2px 0;">
            <span style="color: #4B5563;">Gross Retail Sales:</span>
            <span style="font-family: monospace; font-weight: 600;">Rs. {{ number_format($grossSales, 2) }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; color: #DC2626; padding: 2px 0;">
            <span>Total Discounts Granted:</span>
            <span style="font-family: monospace; font-weight: 600;">-Rs. {{ number_format($totalDiscount, 2) }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; font-weight: 800; border-top: 1px solid #E5E7EB; padding: 4px 0; margin-top: 2px; font-size: 11.5px;">
            <span>Net Shift Sales Revenue:</span>
            <span style="font-family: monospace; color: #059669;">Rs. {{ number_format($netSales, 2) }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 10px; color: #6B7280; padding: 1px 0;">
            <span>Completed Orders Count:</span>
            <span>{{ $ordersCount }} orders ({{ $unitsSold }} items) · AOV: Rs. {{ number_format($aov, 2) }}</span>
        </div>
    </div>

    <!-- 2. CASH DRAWER (TILL RECONCILIATION) -->
    <div class="page-break-avoid" style="margin-bottom: 10px;">
        <div style="font-weight: 800; font-size: 11.5px; text-transform: uppercase; color: #111827; margin-bottom: 5px; border-bottom: 1px solid #E5E7EB; padding-bottom: 2px;">
            2. CASH DRAWER (TILL RECONCILIATION)
        </div>
        <div style="display: flex; justify-content: space-between; padding: 2px 0;">
            <span style="color: #4B5563;">(+) Opening Cash Float:</span>
            <span style="font-family: monospace;">Rs. {{ number_format($openingFloat, 2) }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 2px 0;">
            <span style="color: #4B5563;">(+) Net Cash Sales:</span>
            <span style="font-family: monospace; color: #059669;">+Rs. {{ number_format($cashSales, 2) }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; font-weight: 800; background: #F9FAFB; padding: 4px 6px; margin: 3px 0; border-radius: 4px; border: 1px solid #E5E7EB;">
            <span>(=) EXPECTED IN CASH TILL:</span>
            <span style="font-family: monospace; color: #111827;">Rs. {{ number_format($expectedCash, 2) }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 2px 0;">
            <span style="color: #4B5563;">(~) Physical Cash Counted:</span>
            <span style="font-family: monospace; font-weight: 700; color: #059669;">Rs. {{ number_format($physicalCounted, 2) }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; font-weight: 800; padding: 3px 0; color: {{ $variance < 0 ? '#DC2626' : ($variance > 0 ? '#2563EB' : '#059669') }};">
            <span>Closing Cash Variance:</span>
            <span style="font-family: monospace;">{{ $variance >= 0 ? '+' : '' }}Rs. {{ number_format($variance, 2) }} {{ $variance == 0 ? '(Cash Balanced)' : ($variance < 0 ? '(Cash Short)' : '(Cash Over)') }}</span>
        </div>

        @if(!empty($varianceReasonCode) || !empty($varianceReasonText))
        <div style="background: #FEE2E2; border: 1px solid #FCA5A5; border-radius: 4px; padding: 4px 8px; margin-top: 4px; font-size: 10px; color: #991B1B;">
            <div><strong>Discrepancy Reason:</strong> {{ ucfirst(str_replace('_', ' ', $varianceReasonCode)) }}</div>
            @if(!empty($varianceReasonText))
            <div style="margin-top: 2px;"><strong>Explanation:</strong> {{ $varianceReasonText }}</div>
            @endif
        </div>
        @endif
    </div>

    <!-- 3. PAYMENT BREAKDOWN -->
    <div class="page-break-avoid" style="margin-bottom: 10px;">
        <div style="font-weight: 800; font-size: 11.5px; text-transform: uppercase; color: #111827; margin-bottom: 5px; border-bottom: 1px solid #E5E7EB; padding-bottom: 2px;">
            3. PAYMENT BREAKDOWN
        </div>
        <div style="display: flex; justify-content: space-between; padding: 2px 0;">
            <span>Physical Cash Sales:</span>
            <strong style="font-family: monospace;">Rs. {{ number_format($cashSales, 2) }}</strong>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 2px 0;">
            <span>TOTAL DIGITAL SETTLEMENT:</span>
            <strong style="font-family: monospace; color: #2563EB;">Rs. {{ number_format($digitalSales, 2) }}</strong>
        </div>
        <div style="border-top: 0.5px dotted #D1D5DB; margin: 3px 0;"></div>
        @forelse($payments as $methKey => $pInfo)
        @if((float)($pInfo['amount'] ?? 0) > 0)
        <div style="display: flex; justify-content: space-between; font-size: 10.5px; padding: 1.5px 0;">
            <span style="color: #4B5563;">• {{ $pInfo['label'] ?? ucfirst($methKey) }}:</span>
            <span style="font-family: monospace;">Rs. {{ number_format($pInfo['amount'], 2) }} ({{ $pInfo['count'] ?? 0 }} txns)</span>
        </div>
        @endif
        @empty
        <div style="font-size: 10px; color: #9CA3AF;">No payment transactions recorded in shift.</div>
        @endforelse
    </div>

    <!-- 4. ALL SALES JOURNAL -->
    <div class="page-break-avoid" style="margin-bottom: 10px;">
        <div style="font-weight: 800; font-size: 11.5px; text-transform: uppercase; color: #111827; margin-bottom: 5px; border-bottom: 1px solid #E5E7EB; padding-bottom: 2px;">
            4. ALL SALES JOURNAL ({{ count($salesList) }})
        </div>
        @if(count($salesList) > 0)
        <table style="width: 100%; border-collapse: collapse; font-size: 10px; text-align: left;">
            <thead>
                <tr style="border-bottom: 1.5px solid #D1D5DB; color: #4B5563; font-weight: 700;">
                    <th style="padding: 3px 2px;">Receipt #</th>
                    <th style="padding: 3px 2px;">Time</th>
                    <th style="padding: 3px 2px;">Method</th>
                    <th style="padding: 3px 2px; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($salesList as $sItem)
                <tr style="border-bottom: 0.5px dotted #E5E7EB;">
                    <td style="padding: 3px 2px; font-weight: 700;">{{ $sItem->sale_number }}</td>
                    <td style="padding: 3px 2px; color: #6B7280;">{{ \Carbon\Carbon::parse($sItem->sold_at ?? now())->format('h:i A') }}</td>
                    <td style="padding: 3px 2px; text-transform: capitalize;">{{ $sItem->payment_method }}</td>
                    <td style="padding: 3px 2px; text-align: right; font-family: monospace; font-weight: 700;">Rs. {{ number_format($sItem->total_amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div style="font-size: 10px; color: #9CA3AF;">Zero sales completed during this session.</div>
        @endif
    </div>

    <!-- 5. DISCOUNTS GIVEN AUDIT -->
    <div class="page-break-avoid" style="margin-bottom: 10px;">
        <div style="font-weight: 800; font-size: 11.5px; text-transform: uppercase; color: #111827; margin-bottom: 5px; border-bottom: 1px solid #E5E7EB; padding-bottom: 2px;">
            5. DISCOUNTS GIVEN AUDIT ({{ count($discountedSales) }})
        </div>
        @if(count($discountedSales) > 0)
        <table style="width: 100%; border-collapse: collapse; font-size: 10px; text-align: left;">
            <thead>
                <tr style="border-bottom: 1.5px solid #D1D5DB; color: #4B5563; font-weight: 700;">
                    <th style="padding: 3px 2px;">Receipt #</th>
                    <th style="padding: 3px 2px;">Reason</th>
                    <th style="padding: 3px 2px; text-align: right;">Discount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($discountedSales as $dSale)
                <tr style="border-bottom: 0.5px dotted #E5E7EB;">
                    <td style="padding: 3px 2px; font-weight: 700;">{{ $dSale->sale_number }}</td>
                    <td style="padding: 3px 2px; color: #4B5563;">{{ $dSale->discount_reason ?: 'In-Person / Courtesy' }}</td>
                    <td style="padding: 3px 2px; text-align: right; color: #DC2626; font-family: monospace; font-weight: 600;">-Rs. {{ number_format($dSale->discount_amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div style="font-size: 10px; color: #9CA3AF;">Zero customer discounts granted during this shift.</div>
        @endif
    </div>

    <!-- 6. MAJOR OPERATIONAL EVENTS LOG (VOIDS) -->
    <div class="page-break-avoid" style="margin-bottom: 10px;">
        <div style="font-weight: 800; font-size: 11.5px; text-transform: uppercase; color: #111827; margin-bottom: 5px; border-bottom: 1px solid #E5E7EB; padding-bottom: 2px;">
            6. MAJOR OPERATIONAL EVENTS LOG
        </div>
        @if(count($voidsList) > 0)
        <table style="width: 100%; border-collapse: collapse; font-size: 10px; text-align: left;">
            <thead>
                <tr style="border-bottom: 1.5px solid #D1D5DB; color: #4B5563; font-weight: 700;">
                    <th style="padding: 3px 2px;">Ticket #</th>
                    <th style="padding: 3px 2px;">Time</th>
                    <th style="padding: 3px 2px;">Reason</th>
                    <th style="padding: 3px 2px; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($voidsList as $vSale)
                <tr style="border-bottom: 0.5px dotted #E5E7EB;">
                    <td style="padding: 3px 2px; font-weight: 700; color: #DC2626;">{{ $vSale->sale_number }}</td>
                    <td style="padding: 3px 2px; color: #6B7280;">{{ \Carbon\Carbon::parse($vSale->voided_at ?? $vSale->updated_at)->format('h:i A') }}</td>
                    <td style="padding: 3px 2px;">{{ $vSale->void_reason ?: 'Customer cancellation' }}</td>
                    <td style="padding: 3px 2px; text-align: right; color: #DC2626; font-family: monospace; font-weight: 700;">Rs. {{ number_format($vSale->total_amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="font-size: 9px; color: #6B7280; margin-top: 3px;">
            * Note: Voided transactions do not count towards completed sales and do not inflate shift revenue.
        </div>
        @else
        <div style="font-size: 10px; color: #9CA3AF;">Zero voided transactions recorded during shift.</div>
        @endif
    </div>

    <!-- 7. TILL BALANCING & DENOMINATIONS -->
    <div class="page-break-avoid" style="margin-bottom: 10px;">
        <div style="font-weight: 800; font-size: 11.5px; text-transform: uppercase; color: #111827; margin-bottom: 5px; border-bottom: 1px solid #E5E7EB; padding-bottom: 2px;">
            7. TILL BALANCING
        </div>
        <div style="display: flex; justify-content: space-between; padding: 2px 0;">
            <span style="color: #4B5563;">Expected Cash:</span>
            <span style="font-family: monospace;">Rs. {{ number_format($expectedCash, 2) }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 2px 0;">
            <span style="color: #4B5563;">Physical Cash Counted:</span>
            <strong style="font-family: monospace; color: #059669;">Rs. {{ number_format($physicalCounted, 2) }}</strong>
        </div>
        <div style="display: flex; justify-content: space-between; font-weight: 800; padding: 2px 0; color: {{ $variance < 0 ? '#DC2626' : ($variance > 0 ? '#2563EB' : '#059669') }};">
            <span>Closing Cash Variance:</span>
            <span style="font-family: monospace;">{{ $variance >= 0 ? '+' : '' }}Rs. {{ number_format($variance, 2) }} {{ $variance == 0 ? '(Cash Balanced)' : ($variance < 0 ? '(Cash Short)' : '(Cash Over)') }}</span>
        </div>

        @if(!empty($denoms) && is_array($denoms))
        <div style="margin-top: 6px; font-weight: 700; font-size: 10px; color: #374151;">Counted Denominations Breakdown:</div>
        <table style="width: 100%; border-collapse: collapse; font-size: 9.5px; text-align: left; margin-top: 3px;">
            <thead>
                <tr style="border-bottom: 1px solid #D1D5DB; color: #4B5563;">
                    <th style="padding: 2px 2px;">Note</th>
                    <th style="padding: 2px 2px; text-align: center;">Qty</th>
                    <th style="padding: 2px 2px; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $denomVals = ['1000' => 1000, '500' => 500, '100' => 100, '50' => 50, '20' => 20, '10' => 10, '5' => 5];
                @endphp
                @foreach($denomVals as $nKey => $nVal)
                @php $nQty = (int)($denoms[$nKey] ?? 0); @endphp
                @if($nQty > 0)
                <tr style="border-bottom: 0.5px dotted #E5E7EB;">
                    <td style="padding: 2px 2px;">Rs. {{ number_format($nVal) }}</td>
                    <td style="padding: 2px 2px; text-align: center;">{{ $nQty }}</td>
                    <td style="padding: 2px 2px; text-align: right; font-family: monospace;">Rs. {{ number_format($nQty * $nVal, 2) }}</td>
                </tr>
                @endif
                @endforeach
                @if(!empty($denoms['coins']) && (float)$denoms['coins'] > 0)
                <tr style="border-bottom: 0.5px dotted #E5E7EB;">
                    <td style="padding: 2px 2px;">Coins / Loose</td>
                    <td style="padding: 2px 2px; text-align: center;">-</td>
                    <td style="padding: 2px 2px; text-align: right; font-family: monospace;">Rs. {{ number_format((float)$denoms['coins'], 2) }}</td>
                </tr>
                @endif
            </tbody>
        </table>
        @endif
    </div>

    <div style="border-top: 1.5px solid #E5E7EB; margin: 16px 0 10px 0;"></div>

    <!-- 8. SIGN-OFF AUDIT -->
    <div class="page-break-avoid" style="display: flex; justify-content: space-between; font-size: 10px; margin-top: 18px; padding-top: 4px;">
        <div style="text-align: center; width: 44%;">
            <div style="border-top: 1.5px solid #111827; margin-bottom: 4px;"></div>
            <div style="color: #6B7280;">Cashier Signature</div>
            <div style="font-weight: 700; color: #111827; margin-top: 2px;">{{ $openedByName }}</div>
        </div>
        <div style="text-align: center; width: 44%;">
            <div style="border-top: 1.5px solid #111827; margin-bottom: 4px;"></div>
            <div style="color: #6B7280;">Manager Sign-off</div>
            <div style="font-weight: 700; color: #111827; margin-top: 2px;">{{ $session?->manager_name ?: 'Pending Sign-off' }}</div>
        </div>
    </div>

    <div style="text-align: center; font-size: 9px; color: #9CA3AF; margin-top: 18px;">
        Laijau POS Enterprise System · Nepal Time (NPT) · Shared Central Inventory
    </div>
</div>
