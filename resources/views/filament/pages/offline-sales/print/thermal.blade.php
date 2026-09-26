<!-- Printable 80mm Thermal Receipt Layout -->
<div class="lj-thermal-wrap lj-thermal-printable">
    <div class="lj-thermal-center">
        <div class="lj-thermal-brand">LAIJAU</div>
        <div style="font-size: 10px; text-transform: uppercase;">Delta Nine business group</div>
        <div style="font-size: 10px;">Tel: 9843512095 · PAN/VAT: 604335148</div>
        <div style="font-size: 10px; font-weight: 700; margin-top: 3px; letter-spacing: 0.05em;">TAX INVOICE / CASH MEMO</div>
        <div style="font-size: 8.5px; font-weight: 800; text-align: center; border: 1.5px dashed #000000; padding: 4px 2px; margin: 6px 0; line-height: 1.3; text-transform: uppercase; background: #fff5f5; color: #18181b;">
            THIS IS NOT A TAX INVOICE. FOR LAIJAU INTERNAL USE ONLY. PLEASE RETAIN YOUR TAX INVOICE FROM THE COUNTER.
        </div>
    </div>

    <div class="lj-thermal-divider"></div>

    <div class="lj-thermal-row">
        <span>Receipt #:</span>
        <span style="font-weight: 900;">{{ $receiptData['sale_number'] }}</span>
    </div>
    <div class="lj-thermal-row">
        <span>Date & Time:</span>
        <span>{{ $receiptData['date'] }} {{ $receiptData['time'] }}</span>
    </div>
    <div class="lj-thermal-row">
        <span>Cashier:</span>
        <span>{{ $receiptData['cashier_name'] }}</span>
    </div>
    <div class="lj-thermal-row">
        <span>Warehouse:</span>
        <span>{{ $receiptData['warehouse_code'] }}</span>
    </div>
    <div class="lj-thermal-row">
        <span>Customer:</span>
        <span>{{ $receiptData['customer_name'] }}</span>
    </div>

    <div class="lj-thermal-divider"></div>

    <!-- Itemized Table -->
    @foreach($receiptData['items'] as $it)
    <div style="margin-bottom: 4px;">
        <div style="font-weight: 700; font-size: 11px;">
            {{ $it['name'] }}
            @if(!empty($it['color']) || !empty($it['size']))
            ({{ trim(($it['color'] ?? '') . ' ' . ($it['size'] ?? '')) }})
            @endif
        </div>
        <div class="lj-thermal-row">
            <span>{{ $it['quantity'] }} x Rs. {{ number_format($it['unit_price'], 2) }}</span>
            <span style="font-weight: 700;">Rs. {{ number_format($it['total'], 2) }}</span>
        </div>
    </div>
    @endforeach

    <div class="lj-thermal-divider"></div>

    <div class="lj-thermal-row">
        <span>Subtotal:</span>
        <span>Rs. {{ number_format($receiptData['subtotal'], 2) }}</span>
    </div>
    @if($receiptData['discount'] > 0)
    <div class="lj-thermal-row">
        <span>Discount:</span>
        <span>-Rs. {{ number_format($receiptData['discount'], 2) }}</span>
    </div>
    @endif

    <div class="lj-thermal-divider"></div>

    <div class="lj-thermal-row lj-thermal-total">
        <span>TOTAL PAID:</span>
        <span>Rs. {{ number_format($receiptData['total'], 2) }}</span>
    </div>

    @php
    $taxableVal = round($receiptData['total'] / 1.13, 2);
    $vatVal = round($receiptData['total'] - $taxableVal, 2);
    @endphp
    <div class="lj-thermal-row" style="font-size: 9.5px; opacity: 0.8; margin-top: 2px;">
        <span>Taxable Base:</span>
        <span>Rs. {{ number_format($taxableVal, 2) }}</span>
    </div>
    <div class="lj-thermal-row" style="font-size: 9.5px; opacity: 0.8;">
        <span>13% VAT (Inclusive):</span>
        <span>Rs. {{ number_format($vatVal, 2) }}</span>
    </div>

    <div class="lj-thermal-divider"></div>

    <div class="lj-thermal-row">
        <span>Payment Method:</span>
        <span style="font-weight: 700;">{{ $receiptData['payment_method'] }}</span>
    </div>
    @if(!empty($receiptData['cash_received']))
    <div class="lj-thermal-row">
        <span>Cash Tendered:</span>
        <span>Rs. {{ number_format($receiptData['cash_received'], 2) }}</span>
    </div>
    <div class="lj-thermal-row">
        <span>Change Returned:</span>
        <span style="font-weight: 700;">Rs. {{ number_format($receiptData['change_given'], 2) }}</span>
    </div>
    @endif

    <div class="lj-thermal-divider"></div>

    <div class="lj-thermal-center" style="font-size: 10px; margin-top: 8px;">
        <div style="font-weight: 700;">7-day exchange only. No returns.</div>
        <div>Thank you for choosing Laijau!</div>
        <div style="font-size: 9px; margin-top: 4px; opacity: 0.7;">laijau.com • WhatsApp: 9843512095</div>
    </div>
</div>
