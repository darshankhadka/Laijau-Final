<x-filament-panels::page>
@php
    $entry = $this->record;
    $entry->loadMissing(['lines.account', 'createdByUser', 'reversedByEntry', 'reversalOfEntry', 'period']);
    $lines = $entry->lines;

    $totalDebit = (float)$entry->total_debit;
    $totalCredit = (float)$entry->total_credit;
    $isBalanced = $entry->is_balanced;

    $typeColors = [
        'sales' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'purchase' => 'bg-blue-50 text-blue-700 border-blue-200',
        'bank' => 'bg-sky-50 text-sky-700 border-sky-200',
        'settlement' => 'bg-purple-50 text-purple-700 border-purple-200',
        'cogs' => 'bg-amber-50 text-amber-700 border-amber-200',
        'depreciation' => 'bg-slate-50 text-slate-700 border-slate-200',
        'closing' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'reversal' => 'bg-rose-50 text-rose-700 border-rose-200',
        'manual' => 'bg-gray-50 text-gray-700 border-gray-200',
    ];

    $typeBadgeClass = $typeColors[$entry->entry_type] ?? 'bg-gray-50 text-gray-700 border-gray-200';
@endphp

<div class="space-y-6 text-left font-sans">

    {{-- Top Action Bar / Print Button --}}
    <div class="flex items-center justify-between gap-4 print:hidden">
        <div class="flex items-center gap-2">
            <a
                href="{{ route('filament.admin.resources.journal-entries.index') }}"
                class="px-3.5 py-2 bg-white hover:bg-gray-50 text-gray-700 text-xs font-bold rounded-xl border border-gray-200 shadow-2xs transition-colors flex items-center gap-1.5"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>All Journal Vouchers</span>
            </a>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                onclick="window.print()"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors flex items-center gap-1.5"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Print Voucher</span>
            </button>
        </div>
    </div>

    {{-- Reversal Alerts if applicable --}}
    @if($entry->reversed_by_entry_id && $entry->reversedByEntry)
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl flex items-start gap-3 print:border-red-500">
            <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <h4 class="text-xs font-black text-rose-900 uppercase tracking-wider">This voucher has been reversed</h4>
                <p class="text-xs text-rose-700 mt-0.5">
                    Reversal entry:
                    <a href="{{ route('filament.admin.resources.journal-entries.view', $entry->reversed_by_entry_id) }}" class="font-bold underline">
                        {{ $entry->reversedByEntry->entry_number }}
                    </a>
                    @if($entry->reversal_reason)
                        &mdash; Reason: <em>{{ $entry->reversal_reason }}</em>
                    @endif
                </p>
            </div>
        </div>
    @endif

    @if($entry->reversal_of_entry_id && $entry->reversalOfEntry)
        <div class="p-4 bg-blue-50 border border-blue-200 rounded-2xl flex items-start gap-3">
            <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <h4 class="text-xs font-black text-blue-900 uppercase tracking-wider">Reversal Voucher</h4>
                <p class="text-xs text-blue-700 mt-0.5">
                    This voucher reverses original transaction:
                    <a href="{{ route('filament.admin.resources.journal-entries.view', $entry->reversal_of_entry_id) }}" class="font-bold underline">
                        {{ $entry->reversalOfEntry->entry_number }}
                    </a>
                    @if($entry->reversal_reason)
                        &mdash; Reason: <em>{{ $entry->reversal_reason }}</em>
                    @endif
                </p>
            </div>
        </div>
    @endif

    {{-- STATUTORY VOUCHER PAPER CARD --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 shadow-xs print:border-none print:shadow-none print:p-0">

        {{-- Header & Corporate Details --}}
        <div class="border-b border-gray-200 pb-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-gray-900 tracking-tight">LAIJAU RETAIL & COMMERCE</h2>
                    <p class="text-xs text-gray-500 font-medium">Kathmandu, Nepal &bull; VAT/PAN Registered &bull; Double-Entry General Ledger</p>
                </div>

                <div class="text-left sm:text-right">
                    <span class="inline-block px-3 py-1 rounded-lg text-xs font-black uppercase tracking-wider border {{ $typeBadgeClass }}">
                        {{ strtoupper(str_replace('_', ' ', $entry->entry_type ?? 'Manual')) }}
                    </span>
                    <div class="text-xl font-black font-mono text-gray-900 mt-1">
                        {{ $entry->entry_number }}
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-4 text-xs font-mono">
                <div>
                    <span class="text-gray-500 font-sans">Voucher Date:</span>
                    <span class="font-bold text-gray-900 ml-1">{{ $entry->voucher_date?->format('d F Y') }}</span>
                </div>

                <div>
                    <span class="text-gray-500 font-sans">Currency:</span>
                    <span class="font-bold text-gray-900 ml-1">{{ $entry->currency ?? 'NPR' }}</span>
                </div>

                <div>
                    <span class="text-gray-500 font-sans">Status:</span>
                    @if($entry->status === 'posted')
                        <span class="inline-flex items-center gap-1 text-emerald-700 font-bold ml-1 font-sans">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Posted
                        </span>
                    @elseif($entry->status === 'reversed')
                        <span class="text-rose-700 font-bold ml-1 font-sans">Reversed</span>
                    @else
                        <span class="text-amber-700 font-bold ml-1 font-sans">Draft</span>
                    @endif
                </div>

                <div>
                    <span class="text-gray-500 font-sans">Balance Audit:</span>
                    @if($isBalanced)
                        <span class="inline-flex items-center gap-1 text-emerald-700 font-bold ml-1 font-sans">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Strictly Balanced
                        </span>
                    @else
                        <span class="text-rose-600 font-bold ml-1 font-sans">&Delta; {{ \App\Helpers\NepaliNumberHelper::formatCurrency(abs($totalDebit - $totalCredit), 'Rs. ', 2) }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Voucher Narration / Explanation --}}
        <div class="my-5 p-4 bg-gray-50/75 rounded-xl border border-gray-100">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block mb-1">Narration / Description</span>
            <p class="text-sm font-medium text-gray-800 leading-relaxed">
                {{ $entry->description ?: 'No narration provided.' }}
            </p>
        </div>

        {{-- DOUBLE ENTRY LINES TABLE --}}
        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-gray-600 font-bold uppercase tracking-wider border-b border-gray-200">
                    <tr>
                        <th class="py-3 px-4 w-12 text-center">#</th>
                        <th class="py-3 px-4 w-32 font-mono">Account Code</th>
                        <th class="py-3 px-4">Account Title & Classification</th>
                        <th class="py-3 px-4">Line Narration</th>
                        <th class="py-3 px-4 text-right w-36">Debit (NPR)</th>
                        <th class="py-3 px-4 text-right w-36">Credit (NPR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-mono">
                    @forelse($lines as $idx => $line)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-3 px-4 text-center text-gray-400 font-sans">
                                {{ $line->line_number ?? ($idx + 1) }}
                            </td>
                            <td class="py-3 px-4 font-bold text-gray-800">
                                {{ $line->account_number ?: ($line->account?->account_number ?? '—') }}
                            </td>
                            <td class="py-3 px-4 font-sans">
                                <div class="font-bold text-gray-900">
                                    {{ $line->account?->name ?? 'Account #' . $line->account_id }}
                                </div>
                                @if($line->account?->account_type)
                                    <div class="text-[10px] uppercase font-bold text-gray-400">
                                        {{ str_replace('_', ' ', $line->account->account_type) }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-sans text-gray-600">
                                {{ $line->description ?: '—' }}
                            </td>
                            <td class="py-3 px-4 text-right font-bold {{ (float)$line->debit > 0 ? 'text-gray-900' : 'text-gray-300' }}">
                                @if((float)$line->debit > 0)
                                    {{ \App\Helpers\NepaliNumberHelper::formatCurrency((float)$line->debit, '', 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-bold {{ (float)$line->credit > 0 ? 'text-gray-900' : 'text-gray-300' }}">
                                @if((float)$line->credit > 0)
                                    {{ \App\Helpers\NepaliNumberHelper::formatCurrency((float)$line->credit, '', 2) }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-gray-400 font-sans italic">
                                No ledger lines recorded on this journal voucher.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 border-t-2 border-gray-300 font-mono font-bold text-sm">
                    <tr>
                        <td colspan="4" class="py-3 px-4 text-right font-sans text-xs uppercase tracking-wider text-gray-700">
                            Total Voucher Debits & Credits:
                        </td>
                        <td class="py-3 px-4 text-right text-gray-900 text-sm">
                            Rs. {{ \App\Helpers\NepaliNumberHelper::formatCurrency($totalDebit, '', 2) }}
                        </td>
                        <td class="py-3 px-4 text-right text-gray-900 text-sm">
                            Rs. {{ \App\Helpers\NepaliNumberHelper::formatCurrency($totalCredit, '', 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Signatory & Audit Authorization Block --}}
        <div class="mt-12 pt-8 border-t border-gray-200 grid grid-cols-2 sm:grid-cols-4 gap-6 text-center text-xs">
            <div class="space-y-4">
                <div class="border-b border-gray-300 pb-1 h-12 flex items-end justify-center font-medium text-gray-700">
                    {{ $entry->createdByUser?->name ?? 'System Automated' }}
                </div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                    Prepared By
                </div>
            </div>

            <div class="space-y-4">
                <div class="border-b border-gray-300 pb-1 h-12 flex items-end justify-center font-medium text-gray-700">
                    Accounts Team
                </div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                    Checked / Verified By
                </div>
            </div>

            <div class="space-y-4">
                <div class="border-b border-gray-300 pb-1 h-12 flex items-end justify-center font-medium text-gray-700">
                    Finance Manager
                </div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                    Approved By
                </div>
            </div>

            <div class="space-y-4">
                <div class="border-b border-gray-300 pb-1 h-12 flex items-end justify-center font-mono text-[11px] text-gray-600">
                    {{ $entry->posted_at ? $entry->posted_at->format('d/m/Y H:i') : ($entry->created_at ? $entry->created_at->format('d/m/Y H:i') : '—') }}
                </div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                    System Audit Timestamp
                </div>
            </div>
        </div>

    </div>

</div>
</x-filament-panels::page>
