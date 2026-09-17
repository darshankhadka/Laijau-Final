<div class="space-y-4 text-xs">
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full divide-y divide-gray-200 text-left dark:divide-gray-700">
            <thead class="bg-gray-50 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2">Fiscal Year</th>
                    <th class="px-3 py-2">Category</th>
                    <th class="px-3 py-2">Section</th>
                    <th class="px-3 py-2 text-right">Standard Rate</th>
                    <th class="px-3 py-2 text-right">Non-PAN Rate</th>
                    <th class="px-3 py-2 text-right">Threshold</th>
                    <th class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white font-mono text-[11px] dark:divide-gray-700 dark:bg-gray-900">
                @forelse($rules as $rule)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-3 py-2 font-bold text-primary-600">{{ $rule->fiscal_year }}</td>
                        <td class="px-3 py-2 font-sans font-medium text-gray-900 dark:text-white">
                            {{ ucwords(str_replace('_', ' ', $rule->payment_type)) }}
                        </td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                Sec {{ $rule->section }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right font-bold text-danger-600">{{ number_format($rule->rate, 2) }}%</td>
                        <td class="px-3 py-2 text-right text-amber-600">{{ $rule->rate_without_pan ? number_format($rule->rate_without_pan, 2) . '%' : 'Same' }}</td>
                        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">
                            {{ $rule->threshold > 0 ? 'Rs. ' . number_format($rule->threshold, 0) : 'None (All)' }}
                        </td>
                        <td class="px-3 py-2">
                            @if($rule->is_active)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                    Inactive
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-3 text-center text-gray-500">No statutory TDS rules seeded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-[11px] text-gray-500 dark:text-gray-400">
        Rates are maintained in accordance with the Nepal Inland Revenue Department (IRD) directives under the Nepal Income Tax Act 2058 and the Finance Act 2082/83 & 2083/84.
    </p>
</div>
