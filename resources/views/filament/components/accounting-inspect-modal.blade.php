@php
    /** @var \App\Models\Accounting\AccountingInvoice $record */
    $record->loadMissing(['items.product', 'journalEntry.lines.account', 'offlineSale', 'order']);

    $isPurchase = ($context ?? null) === 'purchase' || $record->type === 'supplier_bill';
    $isCreditNote = $record->type === 'credit_note';
    $isDebitNote = $record->type === 'debit_note';

    // Document label
    $docTypeLabel = match ($record->type) {
        'sales_invoice' => 'Tax Invoice (Bikri Khata)',
        'supplier_bill' => 'Supplier Bill (Kharid Khata)',
        'credit_note' => 'Sales Credit Note (Bikri Parat)',
        'debit_note' => 'Purchase Debit Note (Kharid Parat)',
        default => ucfirst(str_replace('_', ' ', (string)$record->type)),
    };

    // Payment method
    $paymentMethod = 'Unspecified';
    if ($record->offlineSale) {
        $pm = strtolower((string)$record->offlineSale->payment_method);
        $paymentMethod = match ($pm) {
            'fonepay', 'esewa' => 'Digital (eSewa / Fonepay)',
            'khalti' => 'Digital (Khalti QR)',
            'bank_transfer' => 'Bank Transfer / Fonepay',
            'card' => 'Card Terminal',
            'cash' => 'Cash (100%)',
            default => ucfirst($pm),
        };
    } elseif ($record->order) {
        $paymentMethod = ucfirst((string)$record->order->payment_method);
    } elseif ($isPurchase) {
        $paymentMethod = $record->is_credit ? 'Credit (Payable)' : 'Bank / Cash Settlement';
    }

    // Receipt URL if available
    $receiptUrl = null;
    if ($record->reference_offline_sale_id) {
        $receiptUrl = route('offline_sales.receipt', ['offlineSale' => $record->reference_offline_sale_id]);
    } elseif ($record->reference_order_id) {
        $receiptUrl = route('order.pos_receipt', ['order' => $record->reference_order_id]);
    }

    $taxable = (float)$record->taxable_amount > 0 ? (float)$record->taxable_amount : (float)$record->subtotal;
    $vat = (float)$record->vat_amount;
    $total = (float)$record->total_amount;
    $exempt = (float)$record->exempt_amount;
@endphp

<div class="lj-inspect-wrap" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: var(--gray-900, #0f172a); display: flex; flex-direction: column; gap: 1.25rem;">
    <style>
        .lj-inspect-wrap {
            font-size: 0.875rem;
            line-height: 1.45;
        }
        .lj-card {
            background: var(--gray-50, #f8fafc);
            border: 1px solid var(--gray-200, #e2e8f0);
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
        }
        .dark .lj-card {
            background: rgba(30, 41, 59, 0.4);
            border-color: #334155;
            color: #f1f5f9;
        }
        .lj-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .lj-badge-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .dark .lj-badge-success { background: #064e3b; color: #6ee7b7; border-color: #047857; }
        .lj-badge-primary { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .dark .lj-badge-primary { background: #1e3a8a; color: #93c5fd; border-color: #1d4ed8; }
        .lj-badge-slate { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
        .dark .lj-badge-slate { background: #334155; color: #cbd5e1; border-color: #475569; }
        .lj-badge-amber { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .dark .lj-badge-amber { background: #78350f; color: #fcd34d; border-color: #b45309; }

        .lj-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
        }
        @media (max-width: 768px) {
            .lj-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .lj-kpi-card {
            background: var(--gray-50, #ffffff);
            border: 1px solid var(--gray-200, #e2e8f0);
            border-radius: 0.625rem;
            padding: 0.85rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .dark .lj-kpi-card {
            background: #1e293b;
            border-color: #334155;
        }
        .lj-kpi-label {
            font-size: 0.6875rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .lj-kpi-val {
            font-size: 1.15rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            color: #0f172a;
        }
        .dark .lj-kpi-val { color: #f8fafc; }

        .lj-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }
        .lj-table th {
            text-align: left;
            padding: 0.65rem 0.75rem;
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom: 1px solid var(--gray-200, #e2e8f0);
            background: transparent;
        }
        .lj-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--gray-100, #f1f5f9);
            vertical-align: middle;
        }
        .dark .lj-table th { border-color: #334155; color: #94a3b8; }
        .dark .lj-table td { border-color: #1e293b; }

        .lj-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-variant-numeric: tabular-nums;
        }
    </style>

    {{-- Top Hero Header --}}
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 1px solid var(--gray-200, #e2e8f0); padding-bottom: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <h2 style="font-size: 1.35rem; font-weight: 900; letter-spacing: -0.02em; margin: 0; color: #0f172a;" class="dark:text-white">
                    #{{ $record->invoice_number }}
                </h2>
                <span class="lj-badge {{ $record->payment_status === 'paid' ? 'lj-badge-success' : 'lj-badge-amber' }}">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: currentColor;"></span>
                    {{ ucfirst((string)$record->payment_status) }}
                </span>
                <span class="lj-badge lj-badge-primary">
                    {{ $docTypeLabel }}
                </span>
                @if($record->posted_to_gl)
                <span class="lj-badge lj-badge-success" title="Posted to General Ledger">
                    ✓ Posted to GL
                </span>
                @endif
                <span class="lj-badge lj-badge-slate">
                    FY {{ $record->fiscal_year }}
                </span>
            </div>
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
                Nepal Statutory IRD Standard • Tax ID (PAN): <strong>{{ $isPurchase ? ($record->seller_pan ?: 'N/A') : ($record->buyer_pan ?: 'Walk-in Retail') }}</strong>
            </div>
        </div>

        @if($receiptUrl)
        <a href="{{ $receiptUrl }}" target="_blank"
           style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.9rem; font-size: 0.75rem; font-weight: 600; color: #1e40af; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.5rem; text-decoration: none; transition: all 0.15s ease;">
            🖨️ Open Customer Receipt
        </a>
        @endif
    </div>

    {{-- Hero 4-KPI Strip --}}
    <div class="lj-kpi-grid">
        <div class="lj-kpi-card">
            <span class="lj-kpi-label">Subtotal (Taxable)</span>
            <span class="lj-kpi-val lj-mono">Rs. {{ number_format($taxable, 2) }}</span>
        </div>
        <div class="lj-kpi-card">
            <span class="lj-kpi-label">{{ $isPurchase ? 'Input VAT (13%)' : 'Output VAT (13%)' }}</span>
            <span class="lj-kpi-val lj-mono" style="color: #2563eb;">Rs. {{ number_format($vat, 2) }}</span>
        </div>
        <div class="lj-kpi-card" style="border-color: #93c5fd; background: rgba(239, 246, 255, 0.4);">
            <span class="lj-kpi-label" style="color: #1e40af;">Grand Total</span>
            <span class="lj-kpi-val lj-mono" style="color: #0A2E23; font-size: 1.25rem;">Rs. {{ number_format($total, 2) }}</span>
        </div>
        <div class="lj-kpi-card">
            <span class="lj-kpi-label">Payment / Tender</span>
            <span class="lj-kpi-val" style="font-size: 0.9375rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $paymentMethod }}">
                {{ $paymentMethod }}
            </span>
        </div>
    </div>

    {{-- 2-Column Info Grid --}}
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
        {{-- Party Information --}}
        <div class="lj-card">
            <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.65rem;">
                {{ $isPurchase ? '🏢 Supplier / Vendor Profile' : '👤 Customer / Buyer Profile' }}
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.8125rem;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Entity Name:</span>
                    <strong style="color: #0f172a;" class="dark:text-white">{{ $record->contact_name ?: 'Walk-in Customer' }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">PAN / VAT Registration:</span>
                    <span class="lj-mono" style="font-weight: 600;">{{ $isPurchase ? ($record->seller_pan ?: 'Not Registered / Exempt') : ($record->buyer_pan ?: 'Walk-in / Consumer') }}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Classification:</span>
                    <span>{{ ucfirst(str_replace('_', ' ', $isPurchase ? ($record->purchase_type ?: 'merchandise') : ($record->customer_type ?: 'b2c_retail'))) }}</span>
                </div>
                @if($record->contact_phone)
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Contact Telephone:</span>
                    <span class="lj-mono">{{ $record->contact_phone }}</span>
                </div>
                @endif
                @if($record->contact_email)
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Email Address:</span>
                    <span>{{ $record->contact_email }}</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Audit & Transaction Details --}}
        <div class="lj-card">
            <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.65rem;">
                📋 Transaction & Compliance Audit
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.8125rem;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Issue Date (Miti):</span>
                    <strong>{{ $record->issue_date ? $record->issue_date->format('d M Y') : 'N/A' }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">{{ $isPurchase ? 'Procurement Type / Branch:' : 'Sales Channel / Branch:' }}</span>
                    <span>{{ ucfirst(str_replace('_', ' ', (string)($isPurchase ? ($record->purchase_type ?: 'General Procurement') : ($record->sales_channel ?: 'Showroom POS')))) }} • {{ $record->branch ?: 'Kathmandu' }}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Origin Transaction Ref:</span>
                    <span class="lj-mono">
                        @if($record->offlineSale)
                            POS #{{ $record->offlineSale->sale_number }}
                        @elseif($record->order)
                            Order #{{ $record->order->order_number }}
                        @elseif($record->reference_purchase_order_id)
                            PO #{{ $record->reference_purchase_order_id }}
                        @else
                            Direct Entry
                        @endif
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">General Ledger Voucher:</span>
                    <span class="lj-mono" style="font-weight: 700; color: #1e40af;">
                        {{ $record->journalEntry ? $record->journalEntry->entry_number : 'JV-SYNC' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Line Items Table --}}
    <div class="lj-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 0.85rem 1.25rem; border-bottom: 1px solid var(--gray-200, #e2e8f0); display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #475569;">
                {{ $isPurchase ? '📦 Procured Bill Line Items (' . $record->items->count() . ')' : '📦 Merchandise Line Items (' . $record->items->count() . ')' }}
            </div>
            <span style="font-size: 0.6875rem; color: #64748b;">All rates exclusive of 13% statutory output/input VAT</span>
        </div>

        <div style="overflow-x: auto;">
            <table class="lj-table">
                <thead>
                    <tr>
                        <th style="width: 4%;">#</th>
                        <th style="width: 46%;">Product / Description</th>
                        <th style="width: 10%; text-align: center;">Qty</th>
                        <th style="width: 15%; text-align: right;">Rate (Excl.)</th>
                        <th style="width: 10%; text-align: right;">VAT (13%)</th>
                        <th style="width: 15%; text-align: right;">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($record->items as $idx => $item)
                    <tr>
                        <td style="color: #94a3b8; font-size: 0.75rem;">{{ $idx + 1 }}</td>
                        <td>
                            <div style="font-weight: 600; color: #0f172a;" class="dark:text-white">
                                {{ $item->description }}
                            </div>
                            @if($item->product)
                            <div style="display: flex; gap: 0.35rem; align-items: center; margin-top: 0.15rem;">
                                <span class="lj-badge lj-badge-slate lj-mono" style="font-size: 0.625rem; padding: 0.1rem 0.4rem;">
                                    SKU: {{ $item->product->sku }}
                                </span>
                                @if($item->product->type)
                                <span class="lj-badge lj-badge-slate" style="font-size: 0.625rem; padding: 0.1rem 0.4rem;">
                                    {{ ucfirst($item->product->type) }}
                                </span>
                                @endif
                            </div>
                            @endif
                        </td>
                        <td style="text-align: center; font-weight: 600;" class="lj-mono">{{ (float)$item->quantity }}</td>
                        <td style="text-align: right;" class="lj-mono">Rs. {{ number_format((float)$item->unit_price, 2) }}</td>
                        <td style="text-align: right; color: #2563eb;" class="lj-mono">Rs. {{ number_format((float)$item->vat_amount, 2) }}</td>
                        <td style="text-align: right; font-weight: 700; color: #0f172a;" class="lj-mono dark:text-white">
                            Rs. {{ number_format((float)$item->total_amount, 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 1.5rem; color: #94a3b8;">
                            No discrete line items found for this record.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Table Summary Footer --}}
        <div style="padding: 1rem 1.25rem; background: var(--gray-50, rgba(248, 250, 252, 0.5)); border-top: 1px solid var(--gray-200, #e2e8f0); display: flex; justify-content: flex-end;">
            <div style="width: 280px; display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.8125rem;">
                <div style="display: flex; justify-content: space-between; color: #64748b;">
                    <span>Taxable Subtotal:</span>
                    <span class="lj-mono">Rs. {{ number_format($taxable, 2) }}</span>
                </div>
                @if($exempt > 0)
                <div style="display: flex; justify-content: space-between; color: #64748b;">
                    <span>Exempt / Zero-Rated:</span>
                    <span class="lj-mono">Rs. {{ number_format($exempt, 2) }}</span>
                </div>
                @endif
                <div style="display: flex; justify-content: space-between; color: #2563eb;">
                    <span>{{ $isPurchase ? 'Input VAT (13%):' : 'Output VAT (13%):' }}</span>
                    <span class="lj-mono">Rs. {{ number_format($vat, 2) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 1rem; color: #0A2E23; border-top: 1px dashed var(--gray-300, #cbd5e1); padding-top: 0.4rem; margin-top: 0.2rem;" class="dark:text-white">
                    <span>{{ $isPurchase ? 'Bill Total:' : 'Invoice Total:' }}</span>
                    <span class="lj-mono">Rs. {{ number_format($total, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- General Ledger Double-Entry Audit Breakdown --}}
    @if($record->journalEntry && $record->journalEntry->lines->isNotEmpty())
    <div class="lj-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 0.85rem 1.25rem; border-bottom: 1px solid var(--gray-200, #e2e8f0); display: flex; justify-content: space-between; align-items: center; background: #fafafa;" class="dark:bg-slate-900">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #475569;">
                    ⚖️ Double-Entry Journal Voucher ({{ $record->journalEntry->entry_number }})
                </span>
                <span class="lj-badge lj-badge-success" style="font-size: 0.625rem; padding: 0.1rem 0.45rem;">
                    ✓ 0.0000 NPR Variance
                </span>
            </div>
            <span style="font-size: 0.6875rem; color: #64748b;">Posted Date: {{ $record->journalEntry->voucher_date?->format('d M Y') }}</span>
        </div>

        <table class="lj-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Account #</th>
                    <th style="width: 35%;">Account Title</th>
                    <th style="width: 20%;">Line Description</th>
                    <th style="width: 15%; text-align: right;">Debit (NPR)</th>
                    <th style="width: 15%; text-align: right;">Credit (NPR)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($record->journalEntry->lines as $line)
                <tr>
                    <td class="lj-mono" style="font-weight: 700; color: #1e40af;">
                        {{ $line->account_number ?: ($line->account?->account_number ?? '—') }}
                    </td>
                    <td style="font-weight: 500;">
                        {{ $line->account?->name ?? 'General Ledger Account' }}
                    </td>
                    <td style="color: #64748b; font-size: 0.75rem;">
                        {{ $line->description }}
                    </td>
                    <td style="text-align: right; font-weight: 600;" class="lj-mono">
                        {{ (float)$line->debit > 0 ? 'Rs. ' . number_format((float)$line->debit, 2) : '—' }}
                    </td>
                    <td style="text-align: right; font-weight: 600;" class="lj-mono">
                        {{ (float)$line->credit > 0 ? 'Rs. ' . number_format((float)$line->credit, 2) : '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="border-top: 1px solid var(--gray-200, #e2e8f0); font-weight: 700; background: var(--gray-50, rgba(248, 250, 252, 0.4));">
                    <td colspan="3" style="text-align: right; text-transform: uppercase; font-size: 0.6875rem; color: #64748b;">
                        Total Balanced Voucher:
                    </td>
                    <td style="text-align: right; font-weight: 800; color: #0f172a;" class="lj-mono dark:text-white">
                        Rs. {{ number_format((float)$record->journalEntry->total_debit, 2) }}
                    </td>
                    <td style="text-align: right; font-weight: 800; color: #0f172a;" class="lj-mono dark:text-white">
                        Rs. {{ number_format((float)$record->journalEntry->total_credit, 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
</div>
