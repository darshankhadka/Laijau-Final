<!-- Printable Shift Report & Audit Layout (80mm Thermal Receipt) -->
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
<div id="lj-pos-shift-report-thermal-content" class="lj-thermal-wrap" style="display: none; background: #FFFFFF; font-family: 'Courier New', Courier, monospace; font-size: 10.5px; line-height: 1.35; color: #000000; width: 76mm; padding: 2mm 1mm; margin: 0 auto;">
    <div style="text-align: center;">
        <div style="font-size: 16px; font-weight: 900; letter-spacing: 2px;">LAIJAU</div>
        <div style="font-size: 10px; font-weight: 800; text-transform: uppercase;">POS REGISTER SHIFT REPORT</div>
        <div style="font-size: 9.5px; margin-top: 1px;">{{ $showroomName }} · {{ $terminalName }}</div>
        <div style="font-size: 9px;">Kathmandu Showroom & Delivery Hub</div>
        <div style="font-size: 9px;">Bohara Tol, Kageshwori Manahara 09</div>
        <div style="font-size: 9px;">Tel: +977-9843512095 · PAN: 604335148</div>
    </div>

    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

    <div style="display: flex; justify-content: space-between; font-size: 10px;">
        <span>Showroom:</span>
        <strong>{{ $showroomName }}</strong>
    </div>
    <div style="display: flex; justify-content: space-between; font-size: 10px;">
        <span>Terminal:</span>
        <strong>{{ $terminalName }}</strong>
    </div>
    <div style="display: flex; justify-content: space-between; font-size: 10px;">
        <span>Business Date:</span>
        <strong>{{ $businessDate }}</strong>
    </div>
    <div style="display: flex; justify-content: space-between; font-size: 10px;">
        <span>Cashier:</span>
        <strong>{{ $openedByName }} ({{ $openedAtStr }})</strong>
    </div>
    @if($session && $session->isClosed())
    <div style="display: flex; justify-content: space-between; font-size: 10px;">
        <span>Closed By:</span>
        <strong>{{ $closedByName }} ({{ $closedAtStr }})</strong>
    </div>
    @endif
    <div style="display: flex; justify-content: space-between; font-size: 10px;">
        <span>Printed:</span>
        <span>{{ now()->timezone('Asia/Kathmandu')->format('d M h:i A') }} NPT</span>
    </div>

    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

    <!-- 1. SALES SUMMARY -->
    <div style="font-weight: 800; text-transform: uppercase; margin-bottom: 2px;">1. SALES SUMMARY</div>
    <div style="display: flex; justify-content: space-between;">
        <span>Gross Sales:</span>
        <span>Rs. {{ number_format($grossSales, 2) }}</span>
    </div>
    <div style="display: flex; justify-content: space-between;">
        <span>Discounts Given:</span>
        <span>-Rs. {{ number_format($totalDiscount, 2) }}</span>
    </div>
    <div style="display: flex; justify-content: space-between; font-weight: 800; border-top: 0.5px solid #000; margin-top: 1px; padding-top: 1px;">
        <span>Net Shift Sales:</span>
        <span>Rs. {{ number_format($netSales, 2) }}</span>
    </div>
    <div style="display: flex; justify-content: space-between; font-size: 9.5px;">
        <span>Completed Orders:</span>
        <span>{{ $ordersCount }} orders ({{ $unitsSold }} pcs)</span>
    </div>

    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

    <!-- 2. CASH DRAWER (TILL RECONCILIATION) -->
    <div style="font-weight: 800; text-transform: uppercase; margin-bottom: 2px;">2. CASH DRAWER (TILL RECONCILIATION)</div>
    <div style="display: flex; justify-content: space-between;">
        <span>(+) Opening Float:</span>
        <span>Rs. {{ number_format($openingFloat, 2) }}</span>
    </div>
    <div style="display: flex; justify-content: space-between;">
        <span>(+) Cash Sales:</span>
        <span>Rs. {{ number_format($cashSales, 2) }}</span>
    </div>
    <div style="display: flex; justify-content: space-between; font-weight: 800;">
        <span>(=) EXPECTED IN CASH TILL:</span>
        <span>Rs. {{ number_format($expectedCash, 2) }}</span>
    </div>
    <div style="display: flex; justify-content: space-between;">
        <span>(~) Physical Counted:</span>
        <span>Rs. {{ number_format($physicalCounted, 2) }}</span>
    </div>
    <div style="display: flex; justify-content: space-between; font-weight: 800;">
        <span>Variance:</span>
        <span>{{ $variance >= 0 ? '+' : '' }}Rs. {{ number_format($variance, 2) }}</span>
    </div>

    @if(!empty($varianceReasonCode))
    <div style="font-size: 9px; margin-top: 2px;">
        Reason: {{ ucfirst(str_replace('_', ' ', $varianceReasonCode)) }}
        @if(!empty($varianceReasonText))
        <br>Note: {{ $varianceReasonText }}
        @endif
    </div>
    @endif

    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

    <!-- 3. PAYMENT BREAKDOWN -->
    <div style="font-weight: 800; text-transform: uppercase; margin-bottom: 2px;">3. PAYMENT BREAKDOWN</div>
    <div style="display: flex; justify-content: space-between;">
        <span>Cash Sales:</span>
        <strong>Rs. {{ number_format($cashSales, 2) }}</strong>
    </div>
    <div style="display: flex; justify-content: space-between;">
        <span>TOTAL DIGITAL SETTLEMENT:</span>
        <strong>Rs. {{ number_format($digitalSales, 2) }}</strong>
    </div>
    @foreach($payments as $methKey => $pInfo)
    @if((float)($pInfo['amount'] ?? 0) > 0)
    <div style="display: flex; justify-content: space-between; font-size: 9.5px;">
        <span>• {{ $pInfo['label'] ?? ucfirst($methKey) }}:</span>
        <span>Rs. {{ number_format($pInfo['amount'], 2) }}</span>
    </div>
    @endif
    @endforeach

    <!-- 4. ALL SALES JOURNAL -->
    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
    <div style="font-weight: 800; text-transform: uppercase; margin-bottom: 2px;">4. ALL SALES JOURNAL ({{ count($salesList) }})</div>
    @foreach($salesList as $sItem)
    <div style="display: flex; justify-content: space-between; font-size: 9px;">
        <span>{{ $sItem->sale_number }} · {{ \Carbon\Carbon::parse($sItem->sold_at ?? now())->format('h:i A') }}</span>
        <span>{{ substr($sItem->payment_method, 0, 4) }} Rs. {{ number_format($sItem->total_amount, 0) }}</span>
    </div>
    @endforeach

    <!-- 5. DISCOUNTS GIVEN AUDIT -->
    @if(count($discountedSales) > 0)
    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
    <div style="font-weight: 800; text-transform: uppercase; margin-bottom: 2px;">5. DISCOUNTS GIVEN AUDIT ({{ count($discountedSales) }})</div>
    @foreach($discountedSales as $dSale)
    <div style="display: flex; justify-content: space-between; font-size: 9px;">
        <span>{{ $dSale->sale_number }}</span>
        <span>-Rs. {{ number_format($dSale->discount_amount, 2) }}</span>
    </div>
    @endforeach
    @endif

    <!-- 6. MAJOR OPERATIONAL EVENTS LOG (VOIDS) -->
    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
    <div style="font-weight: 800; text-transform: uppercase; margin-bottom: 2px;">6. MAJOR OPERATIONAL EVENTS LOG</div>
    @if(count($voidsList) > 0)
    @foreach($voidsList as $vSale)
    <div style="display: flex; justify-content: space-between; font-size: 9px;">
        <span>VOID {{ $vSale->sale_number }}</span>
        <span>Rs. {{ number_format($vSale->total_amount, 2) }}</span>
    </div>
    <div style="font-size: 8.5px; opacity: 0.8;">Reason: {{ $vSale->void_reason ?: 'Cancelled' }}</div>
    @endforeach
    @else
    <div style="font-size: 9px; opacity: 0.7;">Zero voids in shift.</div>
    @endif

    <!-- 7. TILL BALANCING -->
    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
    <div style="font-weight: 800; text-transform: uppercase; margin-bottom: 2px;">7. TILL BALANCING</div>
    <div style="display: flex; justify-content: space-between;">
        <span>Expected:</span>
        <span>Rs. {{ number_format($expectedCash, 2) }}</span>
    </div>
    <div style="display: flex; justify-content: space-between;">
        <span>Counted:</span>
        <span>Rs. {{ number_format($physicalCounted, 2) }}</span>
    </div>
    @if(!empty($denoms) && is_array($denoms))
    <div style="font-size: 9px; margin-top: 2px;">
        Notes:
        @foreach(['1000', '500', '100', '50', '20', '10', '5'] as $nk)
            @if(!empty($denoms[$nk]) && (int)$denoms[$nk] > 0)
                {{ $nk }}x{{ $denoms[$nk] }}
            @endif
        @endforeach
        @if(!empty($denoms['coins']) && (float)$denoms['coins'] > 0)
            Coins: Rs. {{ number_format((float)$denoms['coins'], 0) }}
        @endif
    </div>
    @endif

    <div style="border-top: 1px dashed #000; margin: 10px 0 6px 0;"></div>

    <!-- 8. SIGN-OFF AUDIT -->
    <div style="display: flex; justify-content: space-between; font-size: 9px; margin-top: 10px;">
        <div style="text-align: center; width: 45%;">
            <div style="border-top: 1px solid #000; margin-bottom: 2px;"></div>
            <div>Cashier Signature</div>
            <div>{{ $openedByName }}</div>
        </div>
        <div style="text-align: center; width: 45%;">
            <div style="border-top: 1px solid #000; margin-bottom: 2px;"></div>
            <div>Manager Sign-off</div>
            <div>{{ $session?->manager_name ?: 'Pending Sign-off' }}</div>
        </div>
    </div>

    <div style="text-align: center; font-size: 8px; opacity: 0.7; margin-top: 8px;">
        Laijau POS Enterprise System · Nepal Time (NPT)
    </div>
</div>
