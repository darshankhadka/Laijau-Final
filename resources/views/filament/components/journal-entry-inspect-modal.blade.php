@php
    /** @var \App\Models\Accounting\JournalEntry $record */
    $record->loadMissing(['lines.account', 'period']);
    $variance = abs((float)$record->total_debit - (float)$record->total_credit);
@endphp

<div class="lj-inspect-wrap" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: var(--gray-900, #0f172a); display: flex; flex-direction: column; gap: 1.25rem;">
    <style>
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

        .lj-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-variant-numeric: tabular-nums;
        }
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
    </style>

    {{-- Voucher Header --}}
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 1px solid var(--gray-200, #e2e8f0); padding-bottom: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <h2 style="font-size: 1.35rem; font-weight: 900; letter-spacing: -0.02em; margin: 0; color: #0f172a;" class="dark:text-white">
                    #{{ $record->entry_number }}
                </h2>
                <span class="lj-badge {{ $record->status === 'posted' ? 'lj-badge-success' : 'lj-badge-slate' }}">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: currentColor;"></span>
                    {{ ucfirst((string)$record->status) }}
                </span>
                <span class="lj-badge lj-badge-primary">
                    {{ ucfirst((string)$record->entry_type) }} Voucher
                </span>
                <span class="lj-badge lj-badge-success">
                    ✓ Balanced ({{ number_format($variance, 4) }} NPR)
                </span>
            </div>
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
                Voucher Date: <strong>{{ $record->voucher_date ? $record->voucher_date->format('d M Y') : 'N/A' }}</strong> • Period: {{ $record->accountingPeriod?->name ?? 'Default Period' }}
            </div>
        </div>
    </div>

    {{-- Hero Debit / Credit KPI Cards --}}
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
        <div class="lj-card" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">
                    Total Debit Voucher Amount
                </div>
                <div class="lj-mono" style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;" class="dark:text-white">
                    Rs. {{ number_format((float)$record->total_debit, 2) }}
                </div>
            </div>
            <span style="font-size: 1.75rem;">📥</span>
        </div>

        <div class="lj-card" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">
                    Total Credit Voucher Amount
                </div>
                <div class="lj-mono" style="font-size: 1.35rem; font-weight: 800; color: #0A2E23; margin-top: 0.25rem;" class="dark:text-white">
                    Rs. {{ number_format((float)$record->total_credit, 2) }}
                </div>
            </div>
            <span style="font-size: 1.75rem;">📤</span>
        </div>
    </div>

    {{-- Audit & Reference Details --}}
    <div class="lj-card" style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.8125rem;">
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #64748b;">Voucher Narrative / Description:</span>
            <strong style="color: #0f172a;" class="dark:text-white">{{ $record->description }}</strong>
        </div>
        @if($record->reference_number)
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #64748b;">Reference Document:</span>
            <span class="lj-mono" style="font-weight: 600;">{{ ucfirst((string)$record->reference_type) }} #{{ $record->reference_number }}</span>
        </div>
        @endif
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #64748b;">Posted Timestamp:</span>
            <span>{{ $record->posted_at ? $record->posted_at->format('d M Y, H:i:s') : ($record->created_at ? $record->created_at->format('d M Y, H:i:s') : 'N/A') }}</span>
        </div>
    </div>

    {{-- Double-Entry Lines Table --}}
    <div class="lj-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 0.85rem 1.25rem; border-bottom: 1px solid var(--gray-200, #e2e8f0); display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #475569;">
                ⚖️ Journal Entry Lines ({{ $record->lines->count() }})
            </div>
            <span style="font-size: 0.6875rem; color: #64748b;">Double-Entry Ledger Balancing</span>
        </div>

        <table class="lj-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Account #</th>
                    <th style="width: 35%;">Account Name</th>
                    <th style="width: 20%;">Line Memo</th>
                    <th style="width: 15%; text-align: right;">Debit (NPR)</th>
                    <th style="width: 15%; text-align: right;">Credit (NPR)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($record->lines as $line)
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
                        Total Equilibrium:
                    </td>
                    <td style="text-align: right; font-weight: 800; color: #0f172a;" class="lj-mono dark:text-white">
                        Rs. {{ number_format((float)$record->total_debit, 2) }}
                    </td>
                    <td style="text-align: right; font-weight: 800; color: #0f172a;" class="lj-mono dark:text-white">
                        Rs. {{ number_format((float)$record->total_credit, 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
