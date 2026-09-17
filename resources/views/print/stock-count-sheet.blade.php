<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Count Sheet - {{ $warehouse->name }} - {{ now()->format('Y-m-d') }} | Laijau ERP</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 11px;
            line-height: 1.35;
        }

        /* Screen Control Bar */
        .screen-toolbar {
            width: 100%;
            max-width: 210mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            background: #ffffff;
            padding: 0.75rem 1.25rem;
            border-radius: 0.5rem;
            border: 1px solid #cbd5e1;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }

        .btn {
            padding: 0.5rem 1.1rem;
            font-size: 0.8125rem;
            font-weight: 700;
            border-radius: 0.375rem;
            cursor: pointer;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #0f172a;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background: #0f3770;
            color: #ffffff;
            border-color: #0c2b57;
        }

        .btn-primary:hover {
            background: #09254d;
        }

        /* Pure A4 Sheet Container */
        .sheet-container {
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            padding: 12mm 12mm 15mm 12mm;
            border-radius: 0.25rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            position: relative;
        }

        /* Sheet Header */
        .sheet-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2.5px solid #0f172a;
            padding-bottom: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .brand-block {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .brand-logo-text {
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 1px;
            color: #0f172a;
            text-transform: uppercase;
        }

        .sheet-title {
            font-size: 13px;
            font-weight: 700;
            color: #0f3770;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 1px;
        }

        .sheet-doc-meta {
            text-align: right;
            font-size: 10px;
            color: #334155;
            line-height: 1.45;
        }

        /* Overview Strip */
        .meta-strip {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 0.5rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 0.5rem 0.75rem;
            margin-bottom: 0.75rem;
        }

        .meta-cell-label {
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.3px;
        }

        .meta-cell-value {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 1px;
        }

        /* Counting Table - Strictly Sized for A4 (Total ~186mm printable) */
        table.count-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
            margin-bottom: 1rem;
        }

        table.count-table thead {
            display: table-header-group;
        }

        table.count-table th {
            background: #e2e8f0;
            color: #0f172a;
            font-weight: 800;
            text-align: left;
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            text-transform: uppercase;
            font-size: 8.5px;
            letter-spacing: 0.2px;
        }

        table.count-table td {
            padding: 4.5px 6px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }

        table.count-table tbody tr {
            page-break-inside: avoid;
        }

        table.count-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* Blank Handwriting Count Box */
        .count-write-box {
            width: 58px;
            height: 22px;
            border: 1.5px solid #0f172a;
            border-radius: 3px;
            background: #ffffff;
            margin: 0 auto;
            position: relative;
        }

        .count-write-box::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 20%;
            right: 20%;
            border-top: 1px dotted #e2e8f0;
        }

        /* Signature Blocks */
        .sign-area {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1.5px solid #cbd5e1;
            page-break-inside: avoid;
        }

        .sign-card {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fafbfc;
            padding: 0.75rem;
            min-height: 85px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .sign-role {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            color: #334155;
            letter-spacing: 0.4px;
        }

        .sign-line {
            border-bottom: 1px solid #0f172a;
            margin-top: 2rem;
            margin-bottom: 0.35rem;
        }

        .sign-hint {
            font-size: 8.5px;
            color: #64748b;
        }

        /* Footer */
        .sheet-footer {
            margin-top: 1rem;
            display: flex;
            justify-content: space-between;
            font-size: 8.5px;
            color: #64748b;
            border-top: 1px solid #f1f5f9;
            padding-top: 0.5rem;
        }

        /* Print Media Overrides */
        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm 8mm 10mm 8mm;
            }

            body {
                background: #ffffff !important;
                padding: 0 !important;
                color: #000000 !important;
            }

            .screen-toolbar {
                display: none !important;
            }

            .sheet-container {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            table.count-table th {
                background: #e2e8f0 !important;
            }
        }
    </style>
</head>

<body>

    {{-- Screen Action Bar --}}
    <div class="screen-toolbar">
        <div>
            <strong>Physical Stock Count Sheet &bull; {{ $warehouse->name }}</strong>
            <span style="color: #64748b; font-size: 12px; margin-left: 0.5rem;">({{ number_format(count($items)) }} items listed)</span>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="{{ route('admin.full-stock-count.export', request()->query()) }}" class="btn" style="background: #10b981; color: #ffffff; border-color: #059669;">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Download Excel / CSV Sheet
            </a>
            <button class="btn btn-primary" onclick="window.print()">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Print Count Sheet (Ctrl+P)
            </button>
            <button class="btn" onclick="window.close()">Close Window</button>
        </div>
    </div>

    {{-- Sheet Body --}}
    <div class="sheet-container">

        {{-- Header --}}
        <div class="sheet-header">
            <div>
                <div class="brand-block">
                    <span class="brand-logo-text">LAIJAU</span>
                    <span style="color: #cbd5e1; font-size: 16px;">|</span>
                    <span class="sheet-title">Physical Stock Count Sheet</span>
                </div>
                <div style="font-size: 10px; color: #64748b; margin-top: 2px;">
                    Authoritative Warehouse Inventory Audit & Cycle Verification Record
                </div>
            </div>

            <div class="sheet-doc-meta">
                <div><strong>Audit Date:</strong> {{ now()->format('Y-m-d') }}</div>
                <div><strong>Warehouse:</strong> {{ $warehouse->name }} ({{ $warehouse->code }})</div>
                <div><strong>Control #:</strong> {{ 'CNT-' . date('Ym') . '-' . str_pad((string)$warehouse->id, 3, '0', STR_PAD_LEFT) }}</div>
            </div>
        </div>

        {{-- Meta Summary Strip --}}
        <div class="meta-strip">
            <div>
                <div class="meta-cell-label">Warehouse Location</div>
                <div class="meta-cell-value">{{ $warehouse->name }}</div>
            </div>
            <div>
                <div class="meta-cell-label">Items Listed</div>
                <div class="meta-cell-value">{{ number_format(count($items)) }} lines</div>
            </div>
            <div>
                <div class="meta-cell-label">Filter Criteria</div>
                <div class="meta-cell-value">{{ $filterLabel ?: 'All Active Stock' }}</div>
            </div>
            <div>
                <div class="meta-cell-label">Auditor in Charge</div>
                <div class="meta-cell-value">{{ auth()->user()->name ?? 'Warehouse Lead' }}</div>
            </div>
        </div>

        {{-- Inventory Table --}}
        <table class="count-table">
            <thead>
                <tr>
                    <th style="width: 24px; text-align: center;">#</th>
                    <th style="width: 220px;">Product Description</th>
                    <th style="width: 82px;">SKU</th>
                    <th style="width: 78px;">Barcode</th>
                    <th style="width: 40px; text-align: center;">Size</th>
                    <th style="width: 55px; text-align: center;">Color</th>
                    <th style="width: 48px; text-align: right;">Sys Qty</th>
                    <th style="width: 68px; text-align: center; background: #cbd5e1;">Physical Qty</th>
                    <th style="width: 46px; text-align: center;">Diff</th>
                    <th>Auditor Notes / Bin</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $index => $item)
                <tr>
                    <td style="text-align: center; color: #64748b; font-weight: 700; font-size: 8.5px;">
                        {{ $index + 1 }}
                    </td>
                    <td>
                        <strong style="color: #0f172a; font-size: 10px;">{{ $item->product_name }}</strong>
                        @if(!empty($item->brand))
                        <span style="color: #64748b; font-size: 8.5px;">&bull; {{ $item->brand }}</span>
                        @endif
                    </td>
                    <td style="font-family: monospace; font-size: 9.5px; font-weight: 700; color: #1e293b;">
                        {{ $item->sku }}
                    </td>
                    <td style="font-family: monospace; font-size: 9px; color: #475569;">
                        {{ $item->barcode ?: '-' }}
                    </td>
                    <td style="text-align: center; font-weight: 700;">
                        {{ $item->size ?: '-' }}
                    </td>
                    <td style="text-align: center; color: #334155;">
                        {{ $item->color ?: '-' }}
                    </td>
                    <td style="text-align: right; font-weight: 800; font-family: monospace; font-size: 10px;">
                        {{ (int)$item->system_qty }}
                    </td>
                    <td style="text-align: center; background: #fafbfc;">
                        <div class="count-write-box"></div>
                    </td>
                    <td style="text-align: center; color: #94a3b8; font-size: 9px;">
                        &plusmn; ____
                    </td>
                    <td style="border-bottom: 1px dashed #cbd5e1;"></td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" style="text-align: center; padding: 2rem; color: #64748b;">
                        No items match the selected warehouse and filter criteria.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        {{-- 3-Tier Sign-Off Verification Area --}}
        <div class="sign-area">
            <div class="sign-card">
                <div class="sign-role">1. Physical Counter</div>
                <div>
                    <div class="sign-line"></div>
                    <div class="sign-hint">Counted By: Name, Signature &amp; Date</div>
                </div>
            </div>

            <div class="sign-card">
                <div class="sign-role">2. Verification Auditor</div>
                <div>
                    <div class="sign-line"></div>
                    <div class="sign-hint">Verified By: Name, Signature &amp; Date</div>
                </div>
            </div>

            <div class="sign-card">
                <div class="sign-role">3. Warehouse Manager Authorization</div>
                <div>
                    <div class="sign-line"></div>
                    <div class="sign-hint">Approved By: Ledger Reconciliation Authorization</div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="sheet-footer">
            <div>Laijau ERP &bull; Official Inventory Audit Control &bull; Confidential</div>
            <div>Generated on {{ now()->format('Y-m-d H:i') }} &bull; Printed by {{ auth()->user()->name ?? 'Warehouse Team' }}</div>
        </div>

    </div>

</body>

</html>
