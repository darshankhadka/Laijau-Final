<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artisan Spec Sheet - {{ $order->order_number ?: ('#' . $order->id) }}</title>
    <style>
        body {
            font-family: 'Helvetica NNpe', Helvetica, Arial, sans-serif;
            padding: 2.5rem;
            background: #fff;
            color: #111;
            line-height: 1.5;
        }

        .header {
            border-bottom: 3px double #0A2E23;
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        h1 {
            margin: 0;
            font-size: 26px;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: #0A2E23;
        }

        .subhead {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #777;
            margin-top: 4px;
        }

        .order-meta {
            text-align: right;
            font-size: 13px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.5rem;
            background: #faf9f6;
            border: 1px solid #e5e5e0;
            padding: 1.25rem;
            margin-bottom: 2rem;
            font-size: 13px;
            border-radius: 4px;
        }

        .meta-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #666;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .meta-value {
            font-size: 14px;
            font-weight: 600;
            color: #111;
        }

        .item {
            border: 1px solid #0A2E23;
            border-radius: 4px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            page-break-inside: avoid;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e0dfd5;
            padding-bottom: 0.75rem;
            margin-bottom: 1.25rem;
        }

        .item-title {
            font-weight: bold;
            font-size: 18px;
            color: #0A2E23;
        }

        .item-badge {
            background: #0A2E23;
            color: #fff;
            padding: 3px 8px;
            border-radius: 2px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .measurements {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .measurement-box {
            border: 1px dashed #b5b3a4;
            background: #fff;
            padding: 1rem;
            border-radius: 4px;
            text-align: center;
        }

        .m-label {
            text-transform: uppercase;
            font-size: 11px;
            font-weight: bold;
            color: #666;
            letter-spacing: 1px;
        }

        .m-value {
            font-size: 24px;
            font-weight: 700;
            margin-top: 4px;
            color: #0A2E23;
        }

        .notes-box {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #fffbe6;
            border-left: 4px solid #d4b106;
            font-size: 13px;
        }

        .footer {
            margin-top: 3rem;
            border-top: 1px solid #ddd;
            padding-top: 1rem;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #888;
        }

        @media print {
            body {
                padding: 0.5cm;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="no-print" style="margin-bottom: 2rem; display: flex; gap: 10px;">
        <button onclick="window.print()" style="padding: 10px 24px; background: #0A2E23; color: #FDFBF7; border: none; cursor: pointer; text-transform: uppercase; font-weight: bold; letter-spacing: 1px; border-radius: 4px;">Print Order Specification Sheet</button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #eee; color: #333; border: 1px solid #ccc; cursor: pointer; text-transform: uppercase; font-weight: bold; border-radius: 4px;">Close</button>
    </div>

    <div class="header">
        <div>
            <h1>LAIJAU</h1>
            <div class="subhead">Fulfillment Center &bull; Order Specification Sheet</div>
        </div>
        <div class="order-meta">
            <div class="meta-label">Reference Number</div>
            <div class="meta-value">{{ $order->order_number ?: ('#' . $order->id) }}</div>
            <div style="margin-top: 4px; color: #666;">Placed: {{ $order->created_at->format('d M Y') }}</div>
        </div>
    </div>

    <div class="details-grid">
        <div>
            <div class="meta-label">Client Name</div>
            <div class="meta-value">{{ $order->first_name }} {{ $order->last_name }}</div>
            <div style="color: #666; font-size: 12px; margin-top: 2px;">{{ $order->email }} &bull; {{ $order->phone ?: 'No phone' }}</div>
        </div>
        <div>
            <div class="meta-label">Target Fulfillment Deadline</div>
            <div class="meta-value" style="color: #b91c1c;">{{ $order->created_at->addDays(14)->format('d M Y') }}</div>
            <div style="color: #666; font-size: 12px; margin-top: 2px;">Target Dispatch Date</div>
        </div>
        <div>
            <div class="meta-label">Fulfillment Status</div>
            <div class="meta-value" style="text-transform: capitalize;">{{ str_replace('_', ' ', $order->status) }}</div>
            <div style="color: #666; font-size: 12px; margin-top: 2px;">Destination: {{ $order->shipping_country }}</div>
        </div>
    </div>

    @if($order->customer_notes)
    <div class="notes-box">
        <strong>Customer Request Notes:</strong> {{ $order->customer_notes }}
    </div>
    <div style="height: 1.5rem;"></div>
    @endif

    @php
    $customSpecItems = $order->items->filter(fn($i) => !empty($i->custom_measurements));
    @endphp

    @if($customSpecItems->isEmpty())
    <div class="item">
        <p>No custom specifications recorded for this order. All items standard size.</p>
    </div>
    @else
    @foreach($customSpecItems as $item)
    <div class="item">
        <div class="item-header">
            <div>
                <span class="item-title">{{ $item->product_name ?? $item->product?->name ?? 'Standard Item' }}</span>
                <div style="font-size: 12px; color: #666; margin-top: 2px;">
                    SKU: <strong>{{ $item->sku ?: 'BASE' }}</strong> | Color: <strong>{{ $item->selected_color ?: 'Standard' }}</strong> | Size: <strong>{{ $item->selected_size ?: 'Standard' }}</strong>
                </div>
            </div>
            <div>
                <span class="item-badge">Qty: {{ $item->quantity }}</span>
            </div>
        </div>

        @php
        $measurements = is_string($item->custom_measurements) ? json_decode($item->custom_measurements, true) : $item->custom_measurements;
        @endphp

        @if(!empty($measurements) && is_array($measurements))
        <div class="measurements">
            @foreach($measurements as $key => $value)
            <div class="measurement-box">
                <div class="m-label">{{ str_replace('_', ' ', $key) }}</div>
                <div class="m-value">
                    {{ $value }}
                    @if(is_numeric($value))
                    <span style="font-size: 14px; font-weight: normal; color: #888;">cm</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p style="color: #666; font-style: italic;">No specific specifications parsed.</p>
        @endif
    </div>
    @endforeach
    @endif

    @if($order->internal_notes)
    <div style="margin-top: 1rem; border: 1px solid #ddd; padding: 1rem; border-radius: 4px; background: #fafafa;">
        <div class="meta-label">Workshop Internal Notes</div>
        <div style="font-size: 13px; margin-top: 4px;">{{ $order->internal_notes }}</div>
    </div>
    @endif

    <div class="footer">
        <div>Laijau Logistics Verification &bull; Quality Assurance Standard</div>
        <div>Inspector Signature: _______________________ &bull; Date: ____________</div>
    </div>
</body>

</html>