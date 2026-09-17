<x-filament-panels::page>
    <style>
        [wire\:loading]:not([wire\:target]) {
            display: none !important;
        }
        .lj-metric-card {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .physical-qty-input:focus {
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.35);
        }
        svg {
            display: inline-block;
            vertical-align: middle;
            max-width: 100%;
        }
        svg.w-3 { width: 12px !important; height: 12px !important; min-width: 12px !important; max-width: 12px !important; }
        svg.w-3\.5 { width: 14px !important; height: 14px !important; min-width: 14px !important; max-width: 14px !important; }
        svg.w-4 { width: 16px !important; height: 16px !important; min-width: 16px !important; max-width: 16px !important; }
        svg.w-5 { width: 20px !important; height: 20px !important; min-width: 20px !important; max-width: 20px !important; }
        svg.w-6 { width: 24px !important; height: 24px !important; min-width: 24px !important; max-width: 24px !important; }
    </style>

    <div class="space-y-3.5" x-data="stockCountApp()">

        {{-- 1. Cycle Count Operations Header & Navigation Bar --}}
        <div class="flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-gray-850 border border-gray-200/90 dark:border-gray-700/80 rounded-2xl px-5 py-3.5 shadow-xs backdrop-blur-md">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-emerald-800 flex items-center justify-center text-white shadow-md flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-base font-black tracking-tight text-gray-950 dark:text-white">
                            Cycle Count &amp; Stock Audit
                        </h1>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10.5px] font-black uppercase tracking-wider bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
                            Cycle Count Active
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        <span class="inline-flex items-center gap-1.5 font-medium">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2" />
                            </svg>
                            Warehouse: <strong class="text-gray-800 dark:text-gray-200">{{ \App\Models\Inventory\Warehouse::find($selectedWarehouseId)?->name ?? 'Hub' }}</strong>
                        </span>
                        <span>&bull;</span>
                        <span class="font-mono text-[11px]">Audit Date: {{ now()->format('Y-m-d') }}</span>
                    </div>
                </div>
            </div>

            {{-- Action Strip --}}
            <div class="flex flex-wrap items-center gap-2">
                {{-- Download Count Sheet (CSV / Excel) --}}
                <a href="{{ $this->exportUrl }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-bold rounded-xl bg-white dark:bg-gray-800 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 shadow-xs transition">
                    <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Download Count Sheet
                </a>

                {{-- Import Counts Button --}}
                <button type="button"
                    wire:click="openImportModal"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-bold rounded-xl bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-750 shadow-xs transition">
                    <svg class="w-3.5 h-3.5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Import Counts
                </button>

                {{-- Link to Full Sheet --}}
                <a href="{{ url('/intadmin/inventory/full-stock-count') }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    Full Stock Sheet
                </a>

                @if(!empty($countedQuantities))
                <button type="button"
                    wire:click="clearAllCounts"
                    wire:confirm="Are you sure you want to reset all current count session entries?"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 border border-rose-200 dark:border-rose-900/60 transition">
                    Reset All ({{ count($countedQuantities) }})
                </button>
                @endif

                <button type="button"
                    wire:click="openReconcileModal"
                    class="inline-flex items-center gap-2 px-4 py-1.5 text-xs font-black rounded-xl bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-500 hover:to-teal-600 text-white shadow-sm transition transform active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Review &amp; Reconcile ({{ count($countedQuantities) }})
                </button>
            </div>
        </div>

        {{-- 2. Compact Operational KPI Metrics Deck --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5">
            {{-- Metric 1: Distinct Counted --}}
            <div class="lj-metric-card bg-white dark:bg-gray-850 border border-gray-200/90 dark:border-gray-700/80 rounded-xl p-2.5 sm:p-3 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                        Counted
                    </span>
                    <span class="text-[10px] font-mono font-bold text-gray-500 dark:text-gray-400">
                        {{ number_format($this->summaryStats['total_units'] ?? 0) }} units
                    </span>
                </div>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-xl font-black text-gray-950 dark:text-white">{{ number_format($this->summaryStats['total_counted']) }}</span>
                    <span class="text-[11px] font-medium text-gray-400">distinct lines</span>
                </div>
                <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                    Physical stock items entered
                </div>
            </div>

            {{-- Metric 2: Matching --}}
            <div class="lj-metric-card bg-emerald-50/20 dark:bg-emerald-950/15 border border-emerald-200/80 dark:border-emerald-800/50 rounded-xl p-2.5 sm:p-3 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-700 dark:text-emerald-400 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Matching
                    </span>
                    <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono font-black bg-emerald-100/80 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">
                        &check; 100% OK
                    </span>
                </div>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-xl font-black text-emerald-600 dark:text-emerald-400">{{ number_format($this->summaryStats['matching']) }}</span>
                    <span class="text-[11px] font-medium text-emerald-600/70">items</span>
                </div>
                <div class="text-[10px] text-emerald-600/80 dark:text-emerald-400/80 mt-0.5 truncate">
                    Zero variance detected
                </div>
            </div>

            {{-- Metric 3: Shortage --}}
            <div class="lj-metric-card bg-rose-50/20 dark:bg-rose-950/15 border border-rose-200/80 dark:border-rose-800/50 rounded-xl p-2.5 sm:p-3 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider text-rose-700 dark:text-rose-400 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                        Shortage
                    </span>
                    <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono font-black bg-rose-100/80 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300">
                        Deficit
                    </span>
                </div>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-xl font-black text-rose-600 dark:text-rose-400">{{ number_format($this->summaryStats['shortage']) }}</span>
                    <span class="text-[11px] font-medium text-rose-600/70">items</span>
                </div>
                <div class="text-[10px] text-rose-600/80 dark:text-rose-400/80 mt-0.5 truncate">
                    Count &lt; system qty
                </div>
            </div>

            {{-- Metric 4: Excess --}}
            <div class="lj-metric-card bg-blue-50/20 dark:bg-blue-950/15 border border-blue-200/80 dark:border-blue-800/50 rounded-xl p-2.5 sm:p-3 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider text-blue-700 dark:text-blue-400 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                        Excess
                    </span>
                    <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono font-black bg-blue-100/80 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300">
                        Surplus
                    </span>
                </div>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-xl font-black text-blue-600 dark:text-blue-400">{{ number_format($this->summaryStats['excess']) }}</span>
                    <span class="text-[11px] font-medium text-blue-600/70">items</span>
                </div>
                <div class="text-[10px] text-blue-600/80 dark:text-blue-400/80 mt-0.5 truncate">
                    Count &gt; system qty
                </div>
            </div>

            {{-- Metric 5: Net Discrepancy Valuation --}}
            <div class="lj-metric-card bg-amber-50/20 dark:bg-amber-950/15 border border-amber-200/80 dark:border-amber-800/50 rounded-xl p-2.5 sm:p-3 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        Net Valuation
                    </span>
                    <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono font-black {{ $this->summaryStats['total_diff_value'] < 0 ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' }}">
                        {{ $this->summaryStats['total_diff'] > 0 ? '+' : '' }}{{ number_format($this->summaryStats['total_diff']) }} u
                    </span>
                </div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span class="text-xl font-black font-mono {{ $this->summaryStats['total_diff_value'] < 0 ? 'text-rose-600 dark:text-rose-400' : ($this->summaryStats['total_diff_value'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-900 dark:text-white') }}">
                        {{ $this->summaryStats['total_diff_value'] < 0 ? '-' : '+' }}Rs. {{ number_format(abs($this->summaryStats['total_diff_value']), 0) }}
                    </span>
                </div>
                <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                    Net inventory cost delta
                </div>
            </div>
        </div>

        {{-- 3. Cycle Count View Sub-Tabs & Filter Toolbar --}}
        <div class="flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-gray-800 p-3 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs text-xs">
            {{-- Sub-Tabs --}}
            <div class="inline-flex rounded-xl p-1 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-xs font-bold">
                <button type="button"
                    wire:click="setCycleView('session')"
                    class="px-3.5 py-1.5 rounded-lg transition {{ $cycleView === 'session' ? 'bg-white dark:bg-gray-800 text-emerald-600 dark:text-emerald-400 shadow-xs font-black' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    Counted in Session ({{ count($countedQuantities) }})
                </button>
                <button type="button"
                    wire:click="setCycleView('diff')"
                    class="px-3.5 py-1.5 rounded-lg transition {{ $cycleView === 'diff' ? 'bg-white dark:bg-gray-800 text-rose-600 dark:text-rose-400 shadow-xs font-black' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    Discrepancies Only ({{ $this->summaryStats['shortage'] + $this->summaryStats['excess'] }})
                </button>
                <button type="button"
                    wire:click="setCycleView('catalog')"
                    class="px-3.5 py-1.5 rounded-lg transition {{ $cycleView === 'catalog' ? 'bg-white dark:bg-gray-800 text-primary-600 dark:text-primary-400 shadow-xs font-black' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    Browse Full Catalog
                </button>
            </div>

            {{-- Quick Search & Filters --}}
            <div class="flex items-center gap-2 flex-1 justify-end max-w-xl">
                @if($cycleView === 'catalog')
                <div class="w-36 hidden sm:block">
                    <select wire:model.live="selectedCategoryId"
                        class="w-full text-xs bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-2.5 py-1.5 text-gray-900 dark:text-white">
                        <option value="">All Categories</option>
                        @foreach($this->categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-32 hidden md:block">
                    <select wire:model.live="selectedBrand"
                        class="w-full text-xs bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-2.5 py-1.5 text-gray-900 dark:text-white">
                        <option value="">All Brands</option>
                        @foreach($this->brands as $b)
                        <option value="{{ $b }}">{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="relative flex-1 max-w-xs">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text"
                        wire:model.live.debounce.300ms="search"
                        @keydown.enter.prevent
                        placeholder="Search SKU, barcode, or name..."
                        class="w-full pl-8 pr-7 py-1.5 text-xs bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 rounded-xl placeholder-gray-400 focus:ring-2 focus:ring-primary-500 transition focus:outline-none" />
                    @if(!empty($search))
                    <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-gray-600 text-xs">&times;</button>
                    @endif
                </div>
            </div>
        </div>

        {{-- 4. Keyboard Navigation Hint Strip --}}
        <div class="flex items-center justify-between px-3 text-[11px] text-gray-500 dark:text-gray-400 font-mono">
            <div class="flex items-center gap-3.5">
                <span><kbd class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 font-bold">Enter</kbd> or <kbd class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 font-bold">&darr;</kbd> Next</span>
                <span><kbd class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 font-bold">&uarr;</kbd> Prev</span>
                <span><kbd class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 font-bold">=</kbd> Match Sys</span>
                <span><kbd class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 font-bold">0</kbd> Zero</span>
            </div>
            <div>
                Showing <strong>{{ $this->items->firstItem() ?? 0 }} &ndash; {{ $this->items->lastItem() ?? 0 }}</strong> of <strong>{{ number_format($this->items->total()) }}</strong> items
            </div>
        </div>

        {{-- 5. Cycle Count Main Inventory Table --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200/90 dark:border-gray-700/90 rounded-2xl shadow-xs overflow-hidden">
            <div class="overflow-x-auto max-h-[72vh]">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="sticky top-0 z-10 backdrop-blur-md bg-gray-100/95 dark:bg-gray-900/95 border-b border-gray-300 dark:border-gray-700 shadow-xs">
                        <tr class="text-[11px] uppercase tracking-wider text-gray-600 dark:text-gray-300">
                            <th class="py-2.5 px-3 w-12 text-center font-extrabold">#</th>
                            <th class="py-2.5 px-3 min-w-[240px] font-extrabold">Product Details</th>
                            <th class="py-2.5 px-3 w-32 font-extrabold">SKU</th>
                            <th class="py-2.5 px-3 w-28 font-extrabold">Barcode</th>
                            <th class="py-2.5 px-2.5 w-16 text-center font-extrabold">Size</th>
                            <th class="py-2.5 px-2.5 w-24 text-center font-extrabold">Color</th>
                            <th class="py-2.5 px-2.5 w-24 font-extrabold">Brand</th>
                            <th class="py-2.5 px-3 w-24 text-right font-extrabold">Sys Qty</th>
                            <th class="py-2.5 px-3 w-32 text-center font-extrabold text-emerald-700 dark:text-emerald-400">Physical Qty</th>
                            <th class="py-2.5 px-3 w-28 text-center font-extrabold">Difference</th>
                            <th class="py-2.5 px-3 w-24 text-center font-extrabold">Quick</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 font-medium text-gray-800 dark:text-gray-200">
                        @forelse($this->items as $index => $item)
                        @php
                        $key = $item->item_key;
                        $hasCount = isset($countedQuantities[$key]) && $countedQuantities[$key] !== '' && $countedQuantities[$key] !== null;
                        $countedVal = $hasCount ? (int)$countedQuantities[$key] : null;
                        $systemVal = (int)$item->system_qty;
                        $diff = $hasCount ? ($countedVal - $systemVal) : null;
                        $unitCost = (float)($item->unit_cost ?: 0);
                        $diffValue = $diff !== null ? ($diff * $unitCost) : 0;
                        $rowIndex = ($this->items->currentPage() - 1) * $this->items->perPage() + $index + 1;
                        @endphp
                        <tr wire:key="cycle-row-{{ $key }}" class="hover:bg-emerald-50/35 dark:hover:bg-emerald-950/20 transition-colors {{ $hasCount ? ($diff === 0 ? 'bg-emerald-50/15 dark:bg-emerald-950/10' : ($diff < 0 ? 'bg-rose-50/20 dark:bg-rose-950/15' : 'bg-blue-50/20 dark:bg-blue-950/15')) : '' }}">
                            <td class="py-2 px-3 text-center text-gray-400 font-mono text-[11px]">{{ $rowIndex }}</td>

                            <td class="py-2 px-3">
                                <div class="font-bold text-gray-950 dark:text-white">{{ $item->product_name }}</div>
                                @if($item->item_type === 'variant')
                                <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">Variant</span>
                                @endif
                            </td>

                            <td class="py-2 px-3 font-mono font-bold text-primary-600 dark:text-primary-400 text-xs">{{ $item->sku ?: '—' }}</td>
                            <td class="py-2 px-3 font-mono text-gray-500 dark:text-gray-400 text-xs">{{ $item->barcode ?: '—' }}</td>
                            <td class="py-2 px-2.5 text-center font-mono text-xs">{{ $item->size ?: '—' }}</td>
                            <td class="py-2 px-2.5 text-center text-xs">{{ $item->color ?: '—' }}</td>
                            <td class="py-2 px-2.5 text-xs text-gray-500">{{ $item->brand ?: '—' }}</td>

                            <td class="py-2 px-3 text-right font-mono font-bold text-gray-900 dark:text-white text-xs">
                                {{ number_format($systemVal) }}
                            </td>

                            <td class="py-2 px-3 text-center">
                                <input type="number"
                                    min="0"
                                    step="1"
                                    data-row-index="{{ $rowIndex }}"
                                    wire:model.blur="countedQuantities.{{ $key }}"
                                    placeholder="—"
                                    class="physical-qty-input w-24 text-center font-mono font-black text-xs sm:text-sm py-1 px-2 rounded-xl border transition-all {{ $hasCount ? ($diff === 0 ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/40 text-emerald-950 dark:text-emerald-200' : ($diff < 0 ? 'border-rose-400 bg-rose-50/50 dark:bg-rose-950/40 text-rose-950 dark:text-rose-200 font-black' : 'border-blue-400 bg-blue-50/50 dark:bg-blue-950/40 text-blue-950 dark:text-blue-200 font-black')) : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400' }}" />
                            </td>

                            <td class="py-2 px-3 text-center">
                                @if($hasCount)
                                <div class="inline-flex flex-col items-center">
                                    <span class="px-2 py-0.5 rounded-md font-mono text-[11px] font-black {{ $diff === 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-300' : ($diff < 0 ? 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300') }}">
                                        {{ $diff === 0 ? '✓ MATCH' : ($diff > 0 ? "+{$diff}" : $diff) }}
                                    </span>
                                    @if($diff !== 0 && $unitCost > 0)
                                    <span class="text-[9.5px] font-mono text-gray-400 mt-0.5">
                                        {{ $diffValue < 0 ? '-' : '+' }}Rs. {{ number_format(abs($diffValue), 0) }}
                                    </span>
                                    @endif
                                </div>
                                @else
                                <span class="text-gray-300 dark:text-gray-600 text-xs font-mono">—</span>
                                @endif
                            </td>

                            <td class="py-2 px-3 text-center">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button"
                                        title="Match System Quantity (=)"
                                        wire:click="matchSystemQty('{{ $key }}', {{ $systemVal }})"
                                        class="px-2 py-0.5 text-xs font-black rounded-md bg-gray-100 dark:bg-gray-700 hover:bg-emerald-100 dark:hover:bg-emerald-950/60 text-gray-700 dark:text-gray-200 hover:text-emerald-700 transition">
                                        =
                                    </button>
                                    <button type="button"
                                        title="Set Zero Stock (0)"
                                        wire:click="setZeroStock('{{ $key }}')"
                                        class="px-2 py-0.5 text-xs font-black rounded-md bg-gray-100 dark:bg-gray-700 hover:bg-amber-100 dark:hover:bg-amber-950/60 text-gray-700 dark:text-gray-200 hover:text-amber-700 transition">
                                        0
                                    </button>
                                    @if($hasCount)
                                    <button type="button"
                                        title="Clear Count"
                                        wire:click="clearItemCount('{{ $key }}')"
                                        class="px-1.5 py-0.5 text-xs font-black rounded-md bg-gray-100 dark:bg-gray-700 hover:bg-rose-100 dark:hover:bg-rose-950/60 text-gray-400 hover:text-rose-600 transition">
                                        &times;
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                @if($cycleView === 'session')
                                <div class="max-w-lg mx-auto py-4 flex flex-col items-center justify-center gap-2">
                                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/25 text-xs text-emerald-700 dark:text-emerald-300 font-medium">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        <span>Cycle count session is active. Enter physical counts in the table or import your count sheet.</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs mt-1">
                                        <button type="button" wire:click="openImportModal" class="px-3.5 py-1.5 text-xs font-bold rounded-xl bg-primary-600 hover:bg-primary-700 text-white shadow-xs transition">
                                            Import Counts (Excel/CSV)
                                        </button>
                                        <button type="button" wire:click="setCycleView('catalog')" class="px-3.5 py-1.5 text-xs font-bold rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-700 transition">
                                            Browse Full Catalog
                                        </button>
                                    </div>
                                </div>
                                @elseif($cycleView === 'diff')
                                <div class="max-w-md mx-auto py-4 text-center space-y-1.5">
                                    <div class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-black text-sm">
                                        <span class="text-base">&check;</span> 100% Matching &bull; Zero Discrepancies
                                    </div>
                                    <p class="text-xs text-gray-500">All counted items in this active session match system inventory.</p>
                                    <button type="button" wire:click="setCycleView('session')" class="mt-1 px-3 py-1 text-xs font-bold rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-700">
                                        View Counted Session ({{ count($countedQuantities) }})
                                    </button>
                                </div>
                                @else
                                <div class="text-sm font-bold text-gray-700 dark:text-gray-300 py-4">No inventory items found matching your filters.</div>
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Server-Side Pagination Links --}}
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/40">
                {{ $this->items->links() }}
            </div>
        </div>

        {{-- 6. Dedicated Import Count Sheet (Excel / CSV) Modal --}}
        @if($showImportModal || $showPasteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/75 backdrop-blur-sm"
            x-transition>
            <div class="bg-white dark:bg-gray-850 rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700 space-y-4">
                {{-- Modal Header --}}
                <div class="flex justify-between items-center border-b border-gray-100 dark:border-gray-700/80 pb-3">
                    <div>
                        <h3 class="text-base font-black text-gray-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            Import Count Sheet (Excel / CSV)
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">Target Warehouse: <strong class="text-gray-800 dark:text-gray-200">{{ \App\Models\Inventory\Warehouse::find($selectedWarehouseId)?->name }}</strong></p>
                    </div>
                    <button type="button" wire:click="$set('showImportModal', false); $set('showPasteModal', false); $set('importFile', null);" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
                </div>

                {{-- Explanatory Instructions --}}
                <div class="p-3.5 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60 text-xs text-emerald-900 dark:text-emerald-200 space-y-1">
                    <p class="font-bold flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Upload your edited count sheet spreadsheet:
                    </p>
                    <p class="text-[11.5px] leading-relaxed text-emerald-800/90 dark:text-emerald-300">
                        Upload the file downloaded from <strong>Download Count Sheet</strong> (or Print Sheet). The importer reads the <strong>Physical Qty</strong> column, matches each item by <strong>Item Key</strong>, <strong>SKU</strong>, or <strong>Barcode</strong>, and instantly populates your session. Blank rows remain uncounted.
                    </p>
                </div>

                {{-- File Upload Dropzone --}}
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300">Select Spreadsheet File (.xlsx, .csv)</label>

                    @if(!$importFile)
                    <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-emerald-500 dark:hover:border-emerald-400 rounded-2xl p-6 text-center transition cursor-pointer bg-gray-50/60 dark:bg-gray-900/40"
                        onclick="document.getElementById('cycleCountSheetFileInput').click()">
                        <input type="file"
                            id="cycleCountSheetFileInput"
                            wire:model="importFile"
                            accept=".xlsx,.xls,.csv,.tsv,.txt"
                            class="hidden" />
                        <div class="flex flex-col items-center justify-center gap-2">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-300 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </div>
                            <div class="text-xs font-bold text-gray-800 dark:text-gray-200">
                                Click to choose file or drag and drop
                            </div>
                            <div class="text-[11px] text-gray-400">
                                Microsoft Excel (.xlsx, .xls) or CSV (.csv, .tsv) up to 25MB
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="flex items-center justify-between p-3.5 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-700 rounded-2xl">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center flex-shrink-0 font-bold text-xs">
                                XLS
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs font-black text-gray-900 dark:text-white truncate">
                                    {{ $importFile->getClientOriginalName() }}
                                </div>
                                <div class="text-[11px] font-mono text-gray-500 dark:text-gray-400">
                                    {{ number_format($importFile->getSize() / 1024, 1) }} KB &bull; Ready to import
                                </div>
                            </div>
                        </div>
                        <button type="button"
                            wire:click="$set('importFile', null)"
                            class="px-2.5 py-1 text-xs font-bold rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-950/60 transition">
                            Change
                        </button>
                    </div>
                    @endif

                    {{-- Loading state for upload --}}
                    <div wire:loading wire:target="importFile" class="text-xs text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1.5 pt-1">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Uploading and reading spreadsheet...</span>
                    </div>
                </div>

                {{-- Collapsible Direct Text / Barcode Scanner Dump Section --}}
                <details class="text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-xl p-2.5">
                    <summary class="font-bold cursor-pointer hover:text-gray-900 dark:hover:text-gray-200">
                        Optional: Or paste raw SKU / Barcode lines directly
                    </summary>
                    <div class="mt-2.5 space-y-2">
                        <textarea wire:model.live="pasteText"
                            rows="4"
                            placeholder="e.g.&#10;CW2288-111, 25&#10;DD1391-100, 18"
                            class="w-full font-mono text-xs bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl p-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>
                </details>

                {{-- Modal Action Buttons --}}
                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-700/80">
                    <button type="button"
                        wire:click="$set('showImportModal', false); $set('showPasteModal', false); $set('importFile', null);"
                        class="px-4 py-2 text-xs font-semibold rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200">
                        Cancel
                    </button>
                    <button type="button"
                        wire:click="processImport"
                        wire:loading.attr="disabled"
                        class="px-5 py-2 text-xs font-black rounded-xl bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-500 hover:to-teal-600 text-white shadow-sm transition inline-flex items-center gap-2">
                        <span wire:loading.remove wire:target="processImport">Import &amp; Apply to Working Session</span>
                        <span wire:loading wire:target="processImport">Processing Import...</span>
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- 7. Review & Bulk Reconciliation Modal --}}
        @if($showReconcileModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/75 backdrop-blur-sm"
            x-transition>
            <div class="bg-white dark:bg-gray-850 rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700 space-y-4 max-h-[90vh] flex flex-col">
                <div class="flex justify-between items-center border-b border-gray-100 dark:border-gray-700/80 pb-3">
                    <div>
                        <h3 class="text-base font-black text-gray-900 dark:text-white">Review &amp; Confirm Cycle Stock Reconciliation</h3>
                        <div class="text-xs text-gray-500">Warehouse: <strong class="text-gray-800 dark:text-gray-200">{{ \App\Models\Inventory\Warehouse::find($selectedWarehouseId)?->name }}</strong></div>
                    </div>
                    <button type="button" wire:click="$set('showReconcileModal', false)" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
                </div>

                {{-- Summary Stats Pills --}}
                <div class="grid grid-cols-4 gap-2 bg-gray-50 dark:bg-gray-900/60 p-3 rounded-xl border border-gray-200 dark:border-gray-700 text-center">
                    <div>
                        <div class="text-[10px] font-extrabold text-gray-400 uppercase">Items Counted</div>
                        <div class="text-xl font-black text-gray-900 dark:text-white">{{ $this->summaryStats['total_counted'] }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-extrabold text-emerald-600 uppercase">Matching</div>
                        <div class="text-xl font-black text-emerald-600">{{ $this->summaryStats['matching'] }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-extrabold text-rose-600 uppercase">Shortage</div>
                        <div class="text-xl font-black text-rose-600">{{ $this->summaryStats['shortage'] }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-extrabold text-blue-600 uppercase">Excess</div>
                        <div class="text-xl font-black text-blue-600">{{ $this->summaryStats['excess'] }}</div>
                    </div>
                </div>

                {{-- Discrepancy Preview List --}}
                <div class="flex-1 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-xl p-2.5 max-h-60 space-y-1.5 text-xs">
                    <div class="font-bold text-gray-700 dark:text-gray-300 px-2.5 py-1.5 bg-gray-100 dark:bg-gray-700/50 rounded-lg flex justify-between items-center">
                        <span>Items with Stock Adjustments:</span>
                        <span class="font-mono text-xs font-black text-amber-600">Net Delta: {{ $this->summaryStats['total_diff'] > 0 ? "+{$this->summaryStats['total_diff']}" : $this->summaryStats['total_diff'] }} units</span>
                    </div>

                    @php $hasDiscrepancies = false; @endphp
                    @foreach($countedQuantities as $k => $cVal)
                    @php
                    $cVal = (int)$cVal;
                    $meta = $itemMetadata[$k] ?? null;
                    $expected = $meta ? (int)$meta['system_qty'] : 0;
                    $diff = $cVal - $expected;
                    if ($diff === 0) continue;
                    $hasDiscrepancies = true;
                    @endphp
                    <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-900/40 border border-gray-100 dark:border-gray-800">
                        <div>
                            <span class="font-black text-gray-900 dark:text-white">{{ $meta['product_name'] ?? $k }}</span>
                            <span class="text-[11px] font-mono text-gray-500 ml-1">({{ $meta['sku'] ?? $k }})</span>
                        </div>
                        <div class="font-mono text-xs">
                            <span class="text-gray-400">Sys: {{ $expected }}</span>
                            <span class="mx-1 text-gray-400">&rarr;</span>
                            <span class="font-black text-gray-900 dark:text-white">Count: {{ $cVal }}</span>
                            <span class="ml-2 px-2 py-0.5 rounded font-black {{ $diff < 0 ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' }}">
                                {{ $diff > 0 ? "+{$diff}" : $diff }}
                            </span>
                        </div>
                    </div>
                    @endforeach

                    @if(!$hasDiscrepancies)
                    <div class="text-center py-6 text-emerald-600 font-bold">
                        &check; Perfect Match! All {{ count($countedQuantities) }} counted items exactly match system stock.
                    </div>
                    @endif
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Reconciliation Reference Notes</label>
                    <input type="text"
                        wire:model="reconciliationNotes"
                        placeholder="e.g. Cycle Count Discrepancy Adjustment"
                        class="w-full text-xs bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-gray-900 dark:text-white focus:ring-primary-500" />
                </div>

                <div class="p-3 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 rounded-xl text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
                    <strong>Notice:</strong> Applying reconciliation creates audited stock movement records (<code>count_reconciliation</code>) adjusting warehouse stock levels. Historical orders, POS sales, and product listings remain 100% untouched.
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-700/80">
                    <button type="button"
                        wire:click="$set('showReconcileModal', false)"
                        class="px-4 py-2 text-xs font-semibold rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200">
                        Cancel
                    </button>
                    <button type="button"
                        wire:click="confirmReconciliation"
                        wire:loading.attr="disabled"
                        class="px-5 py-2 text-xs font-black rounded-xl bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-500 hover:to-teal-600 text-white shadow-sm transition inline-flex items-center gap-2">
                        <span wire:loading.remove wire:target="confirmReconciliation">Confirm &amp; Apply Stock Adjustments</span>
                        <span wire:loading wire:target="confirmReconciliation">Processing Adjustments...</span>
                    </button>
                </div>
            </div>
        </div>
        @endif

    </div>

    {{-- Keyboard Navigation Alpine.js Controller --}}
    <script>
        function stockCountApp() {
            return {
                init() {
                    window.addEventListener('submit', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                    }, true);

                    this.$el.addEventListener('keydown', (e) => {
                        if (!e.target.classList.contains('physical-qty-input')) {
                            return;
                        }

                        const currentIndex = parseInt(e.target.getAttribute('data-row-index'), 10);
                        if (isNaN(currentIndex)) return;

                        if (e.key === 'Enter' || e.key === 'ArrowDown') {
                            e.preventDefault();
                            e.target.blur();
                            const nextInput = document.querySelector(`.physical-qty-input[data-row-index="${currentIndex + 1}"]`);
                            if (nextInput) {
                                nextInput.focus();
                                nextInput.select();
                            }
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            e.target.blur();
                            const prevInput = document.querySelector(`.physical-qty-input[data-row-index="${currentIndex - 1}"]`);
                            if (prevInput) {
                                prevInput.focus();
                                prevInput.select();
                            }
                        }
                    });
                }
            };
        }
    </script>
</x-filament-panels::page>
