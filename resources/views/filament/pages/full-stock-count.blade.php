<x-filament-panels::page>
    <style>
        /* Scoped styles for Full Stock Count & Fast Cycle Terminal */
        [wire\:loading]:not([wire\:target]) {
            display: none !important;
        }
        .lj-metric-card {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .lj-metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
        }
        .lj-scan-input:focus {
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.35), 0 10px 20px -3px rgba(16, 185, 129, 0.25);
        }
        .lj-glow-emerald {
            box-shadow: 0 0 25px -5px rgba(16, 185, 129, 0.35);
        }
        .lj-glow-amber {
            box-shadow: 0 0 25px -5px rgba(245, 158, 11, 0.35);
        }
        .physical-qty-input:focus {
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3);
        }
        @keyframes scanBeam {
            0%, 100% { transform: translateY(-100%); opacity: 0.1; }
            50% { transform: translateY(100%); opacity: 0.8; }
        }
        .lj-scanner-beam {
            animation: scanBeam 2.5s ease-in-out infinite;
        }
        @keyframes laserSweep {
            0% { left: -35%; opacity: 0; }
            50% { opacity: 0.9; }
            100% { left: 100%; opacity: 0; }
        }
        .lj-laser-sweep {
            position: absolute;
            top: 0;
            left: -35%;
            width: 35%;
            height: 2.5px;
            background: linear-gradient(90deg, transparent, #10b981, #34d399, #06b6d4, transparent);
            animation: laserSweep 3s ease-in-out infinite;
            filter: drop-shadow(0 0 6px #10b981);
        }
        .lj-cyber-grid {
            background-image: radial-gradient(rgba(16, 185, 129, 0.1) 1px, transparent 0);
            background-size: 22px 22px;
        }
        .lj-hud-glass {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.92) 0%, rgba(30, 41, 59, 0.85) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .lj-stepper-btn {
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        }
        /* Scoped SVG sizing guards to prevent unconstrained SVG expansion */
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
        svg.w-8 { width: 32px !important; height: 32px !important; min-width: 32px !important; max-width: 32px !important; }
    </style>

    <div class="space-y-4" x-data="stockCountApp()">

        {{-- 1. Operations Header & Top Navigation Hub --}}
        <div class="flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-gray-850 border border-gray-200/90 dark:border-gray-700/80 rounded-2xl px-5 py-3.5 shadow-xs backdrop-blur-md">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-emerald-800 flex items-center justify-center text-white shadow-md flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-base font-black tracking-tight text-gray-950 dark:text-white">
                            {{ $mode === 'cycle' ? 'Cycle Count & Stock Audit' : 'Full Stock Count & Inventory Reconciliation' }}
                        </h1>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10.5px] font-black uppercase tracking-wider {{ $mode === 'cycle' ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30' : 'bg-primary-500/15 text-primary-700 dark:text-primary-300 border border-primary-500/30' }}">
                            {{ $mode === 'cycle' ? 'Cycle Count Active' : 'Sheet Audit' }}
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

            {{-- Mode Switcher & Top Action Buttons --}}
            <div class="flex flex-wrap items-center gap-2">
                {{-- Segmented Mode Toggle --}}
                <div class="inline-flex rounded-xl p-1 bg-gray-100 dark:bg-gray-900 border border-gray-200/80 dark:border-gray-700/80 shadow-xs" role="group">
                    <button type="button"
                        wire:click="setMode('count')"
                        class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all {{ $mode === 'count' ? 'bg-white dark:bg-gray-800 text-primary-600 dark:text-primary-400 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            Full Stock Count Sheet
                        </span>
                    </button>
                    <button type="button"
                        wire:click="setMode('cycle')"
                        class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all {{ $mode === 'cycle' ? 'bg-gradient-to-r from-emerald-600 to-teal-700 text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Cycle Count
                        </span>
                    </button>
                </div>

                {{-- Action Strip --}}
                <div class="flex items-center gap-1.5">
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

                    @if($mode === 'count' || $cycleView === 'catalog')
                    <a href="{{ $this->printUrl }}"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold rounded-xl bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-750 shadow-xs transition">
                        <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print Count Sheet (A4)
                    </a>
                    @endif

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
        </div>

        {{-- 2. Operational KPI Metrics Deck --}}
        @if($mode === 'cycle')
        {{-- Streamlined 1-line KPI summary strip in Cycle mode --}}
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 bg-white dark:bg-gray-850 border border-gray-200/90 dark:border-gray-700/80 rounded-2xl text-xs shadow-xs">
            <div class="flex flex-wrap items-center gap-2.5 sm:gap-4 font-mono text-[11.5px]">
                <span class="inline-flex items-center gap-1.5 font-bold text-gray-800 dark:text-gray-200">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    Counted: <strong class="text-gray-950 dark:text-white">{{ number_format($this->summaryStats['total_counted']) }}</strong> items
                    <span class="text-gray-400">({{ number_format($this->summaryStats['total_units'] ?? 0) }} physical units)</span>
                </span>
                <span class="text-gray-300 dark:text-gray-600 hidden sm:inline">&bull;</span>
                <span class="inline-flex items-center gap-1 font-bold text-emerald-600 dark:text-emerald-400">
                    &check; Match: {{ number_format($this->summaryStats['matching']) }}
                </span>
                <span class="text-gray-300 dark:text-gray-600 hidden sm:inline">&bull;</span>
                <span class="inline-flex items-center gap-1 font-bold text-rose-600 dark:text-rose-400">
                    Shortage: {{ number_format($this->summaryStats['shortage']) }}
                </span>
                <span class="text-gray-300 dark:text-gray-600 hidden sm:inline">&bull;</span>
                <span class="inline-flex items-center gap-1 font-bold text-blue-600 dark:text-blue-400">
                    Excess: {{ number_format($this->summaryStats['excess']) }}
                </span>
            </div>
            <div class="font-mono text-[11.5px] font-bold">
                <span class="text-gray-500 dark:text-gray-400">Net Variance:</span>
                <span class="{{ $this->summaryStats['total_diff_value'] < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    {{ $this->summaryStats['total_diff_value'] < 0 ? '-' : '+' }}Rs. {{ number_format(abs($this->summaryStats['total_diff_value']), 0) }}
                    ({{ $this->summaryStats['total_diff'] > 0 ? '+' : '' }}{{ number_format($this->summaryStats['total_diff']) }} u)
                </span>
            </div>
        </div>

        {{-- 3. Cycle Count View Selector & Quick Locator Bar (Clean & Lightweight) --}}
        <div class="flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-gray-800 p-3 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs text-xs">
            {{-- View Tabs --}}
            <div class="inline-flex rounded-xl p-1 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-xs font-bold">
                <button type="button"
                    wire:click="setCycleView('session')"
                    class="px-3.5 py-1.5 rounded-lg transition {{ $cycleView === 'session' ? 'bg-white dark:bg-gray-800 text-emerald-600 dark:text-emerald-400 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    Counted in Session ({{ count($countedQuantities) }})
                </button>
                <button type="button"
                    wire:click="setCycleView('diff')"
                    class="px-3.5 py-1.5 rounded-lg transition {{ $cycleView === 'diff' ? 'bg-white dark:bg-gray-800 text-rose-600 dark:text-rose-400 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    Discrepancies Only ({{ $this->summaryStats['shortage'] + $this->summaryStats['excess'] }})
                </button>
                <button type="button"
                    wire:click="setCycleView('catalog')"
                    class="px-3.5 py-1.5 rounded-lg transition {{ $cycleView === 'catalog' ? 'bg-white dark:bg-gray-800 text-primary-600 dark:text-primary-400 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    Browse Full Catalog
                </button>
            </div>

            {{-- Quick Filter / SKU Finder --}}
            <div class="relative flex-1 max-w-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text"
                    wire:model.live.debounce.300ms="search"
                    @keydown.enter.prevent
                    placeholder="Search name, SKU, or barcode in table..."
                    class="w-full pl-8 pr-7 py-1.5 text-xs bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 rounded-xl placeholder-gray-400 focus:ring-2 focus:ring-primary-500 transition focus:outline-none" />
                @if(!empty($search))
                <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-gray-600 text-xs">&times;</button>
                @endif
            </div>
        </div>
        @endif

        {{-- 4. Filters Toolbar (Full Count mode OR when browsing Full Catalog in Cycle mode) --}}
        @if($mode === 'count' || $cycleView === 'catalog')
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4.5 shadow-xs space-y-3.5">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3.5">
                {{-- Warehouse --}}
                <div>
                    <label class="block text-[10.5px] font-extrabold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Warehouse</label>
                    <select wire:model.live="selectedWarehouseId"
                        class="w-full text-xs font-medium bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-gray-900 dark:text-white focus:ring-primary-500">
                        @foreach($this->warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- Category --}}
                <div>
                    <label class="block text-[10.5px] font-extrabold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Category</label>
                    <select wire:model.live="selectedCategoryId"
                        class="w-full text-xs bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-gray-900 dark:text-white focus:ring-primary-500">
                        <option value="">All Categories</option>
                        @foreach($this->categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Brand --}}
                <div>
                    <label class="block text-[10.5px] font-extrabold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Brand</label>
                    <select wire:model.live="selectedBrand"
                        class="w-full text-xs bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-gray-900 dark:text-white focus:ring-primary-500">
                        <option value="">All Brands</option>
                        @foreach($this->brands as $b)
                        <option value="{{ $b }}">{{ $b }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Search Box --}}
                <div>
                    <label class="block text-[10.5px] font-extrabold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Search Product / SKU</label>
                    <div class="relative">
                        <input type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Name, SKU, Barcode, Size..."
                            class="w-full text-xs bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-primary-500" />
                        @if(!empty($search))
                        <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 text-xs">&times;</button>
                        @endif
                    </div>
                </div>

                {{-- Per Page --}}
                <div>
                    <label class="block text-[10.5px] font-extrabold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Rows per Page</label>
                    <select wire:model.live="perPage"
                        class="w-full text-xs bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-gray-900 dark:text-white focus:ring-primary-500">
                        <option value="25">25 rows</option>
                        <option value="50">50 rows</option>
                        <option value="100">100 rows</option>
                        <option value="250">250 rows</option>
                    </select>
                </div>
            </div>

            {{-- Status Filter Chips --}}
            <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-gray-100 dark:border-gray-700">
                <span class="text-[10px] uppercase font-bold text-gray-400 mr-1">Filter View:</span>
                @php
                $statuses = [
                'all' => 'All Items',
                'diff' => 'Discrepancies Only',
                'matched' => 'Matching',
                'short' => 'Shortage',
                'excess' => 'Excess',
                'zero_stock' => 'Zero Stock',
                'negative_stock' => 'Negative Stock',
                'uncounted' => 'Uncounted',
                ];
                @endphp
                @foreach($statuses as $k => $label)
                <button type="button"
                    wire:click="$set('statusFilter', '{{ $k }}')"
                    class="px-3 py-1 text-xs font-semibold rounded-lg border transition {{ $statusFilter === $k ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 border-transparent shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700' }}">
                    {{ $label }}
                </button>
                @endforeach
            </div>
        </div>
        @endif

        {{-- 5. Quick Keyboard Navigation Strip --}}
        <div class="flex items-center justify-between px-3 text-[11px] text-gray-500 dark:text-gray-400 font-mono">
            <div class="flex items-center gap-3.5">
                <span><kbd class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 font-bold">Enter</kbd> or <kbd class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 font-bold">&darr;</kbd> Next item</span>
                <span><kbd class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 font-bold">&uarr;</kbd> Previous item</span>
                <span><kbd class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 font-bold">=</kbd> Match system</span>
                <span><kbd class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 font-bold">0</kbd> Zero count</span>
            </div>
            <div>
                Showing <strong>{{ $this->items->firstItem() ?? 0 }} &ndash; {{ $this->items->lastItem() ?? 0 }}</strong> of <strong>{{ number_format($this->items->total()) }}</strong> items
            </div>
        </div>

        {{-- 6. Main High-Performance Counting Table / Session Stream --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200/90 dark:border-gray-700/90 rounded-2xl shadow-xs overflow-hidden">
            <div class="overflow-x-auto max-h-[72vh]">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="sticky top-0 z-10 backdrop-blur-md bg-gray-100/95 dark:bg-gray-900/95 border-b border-gray-300 dark:border-gray-700 shadow-xs">
                        <tr class="text-[11px] uppercase tracking-wider text-gray-600 dark:text-gray-300">
                            <th class="py-3 px-3 w-12 text-center font-extrabold">#</th>
                            <th class="py-3 px-3 min-w-[240px] font-extrabold">Product Details</th>
                            <th class="py-3 px-3 w-32 font-extrabold">SKU</th>
                            <th class="py-3 px-3 w-28 font-extrabold">Barcode</th>
                            <th class="py-3 px-3 w-20 text-center font-extrabold">Size</th>
                            <th class="py-3 px-3 w-20 text-center font-extrabold">Color</th>
                            <th class="py-3 px-3 w-24 text-right font-extrabold">Sys Qty</th>
                            <th class="py-3 px-3 w-36 text-center font-black bg-primary-100/70 dark:bg-primary-950/60 text-primary-950 dark:text-primary-200">
                                Physical Qty
                            </th>
                            <th class="py-3 px-3 w-28 text-center font-extrabold">Difference</th>
                            <th class="py-3 px-3 w-28 text-center font-extrabold">Status</th>
                            <th class="py-3 px-3 w-24 text-center font-extrabold">Quick</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($this->items as $index => $item)
                        @php
                        $key = $item->item_key;
                        $hasCount = array_key_exists($key, $countedQuantities) && $countedQuantities[$key] !== '' && $countedQuantities[$key] !== null;
                        $countedVal = $hasCount ? (int)$countedQuantities[$key] : null;
                        $systemVal = (int)$item->system_qty;
                        $diff = $hasCount ? ($countedVal - $systemVal) : null;
                        $isHighlighted = ($highlightedItemKey === $key);
                        @endphp
                        <tr id="row-{{ $key }}"
                            class="transition-colors hover:bg-gray-50/90 dark:hover:bg-gray-750 {{ $isHighlighted ? 'bg-amber-50/80 dark:bg-amber-950/40 border-l-4 border-amber-500' : '' }}">
                            <td class="py-2.5 px-3 text-center text-gray-400 font-mono text-[11px]">
                                {{ ($this->items->currentPage() - 1) * $this->perPage + $index + 1 }}
                            </td>
                            <td class="py-2.5 px-3">
                                <div class="font-extrabold text-gray-900 dark:text-white leading-snug">
                                    {{ $item->product_name }}
                                </div>
                                @if(!empty($item->brand))
                                <div class="text-[10px] text-gray-500 dark:text-gray-400 font-semibold mt-0.5">
                                    Brand: {{ $item->brand }}
                                </div>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 font-mono font-bold text-xs text-gray-800 dark:text-gray-200">
                                {{ $item->sku }}
                            </td>
                            <td class="py-2.5 px-3 font-mono text-[11px] text-gray-500 dark:text-gray-400">
                                {{ $item->barcode ?: '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-center font-black text-gray-800 dark:text-gray-200">
                                @if(!empty($item->size))
                                <span class="px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-xs">{{ $item->size }}</span>
                                @else
                                <span class="text-gray-400">&mdash;</span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-center text-gray-600 dark:text-gray-300">
                                @if(!empty($item->color))
                                <span class="px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-xs">{{ $item->color }}</span>
                                @else
                                <span class="text-gray-400">&mdash;</span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono font-black text-sm text-gray-900 dark:text-white">
                                {{ number_format($systemVal) }}
                            </td>

                            {{-- Physical Quantity Input Field (with inline quick steppers in cycle mode) --}}
                            <td class="py-2 px-3 text-center bg-primary-50/40 dark:bg-primary-950/20">
                                <div class="inline-flex items-center justify-center gap-1">
                                    @if($mode === 'cycle')
                                    <button type="button"
                                        wire:click="adjustItemCount('{{ $key }}', -1)"
                                        title="Decrease 1"
                                        class="w-6 h-7 flex items-center justify-center text-xs font-black rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-rose-100 dark:hover:bg-rose-950/60 hover:text-rose-600 transition">
                                        &minus;
                                    </button>
                                    @endif

                                    <input type="number"
                                        min="0"
                                        step="1"
                                        data-row-index="{{ $index }}"
                                        data-item-key="{{ $key }}"
                                        wire:model.blur="countedQuantities.{{ $key }}"
                                        placeholder="&mdash;"
                                        class="physical-qty-input {{ $mode === 'cycle' ? 'w-20' : 'w-28' }} text-center font-mono font-black text-sm bg-white dark:bg-gray-900 border-2 {{ $hasCount ? 'border-primary-500 ring-2 ring-primary-500/20 text-gray-900 dark:text-white' : 'border-gray-300 dark:border-gray-600 text-gray-400' }} rounded-xl py-1.5 px-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition" />

                                    @if($mode === 'cycle')
                                    <button type="button"
                                        wire:click="adjustItemCount('{{ $key }}', 1)"
                                        title="Increase 1"
                                        class="w-6 h-7 flex items-center justify-center text-xs font-black rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-emerald-100 dark:hover:bg-emerald-950/60 hover:text-emerald-600 transition">
                                        +
                                    </button>
                                    @endif
                                </div>
                            </td>

                            {{-- Real-Time Calculated Difference --}}
                            <td class="py-2.5 px-3 text-center font-mono font-black">
                                @if($diff === null)
                                <span class="text-gray-400 dark:text-gray-500 font-normal">&mdash;</span>
                                @elseif($diff === 0)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                    0
                                </span>
                                @elseif($diff < 0)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-black bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                    {{ $diff }}
                                </span>
                                @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-black bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                    +{{ $diff }}
                                </span>
                                @endif
                            </td>

                            {{-- Status Badge --}}
                            <td class="py-2.5 px-3 text-center">
                                @if(!$hasCount)
                                <span class="text-[10px] font-semibold text-gray-400 bg-gray-100 dark:bg-gray-700/80 px-2 py-0.5 rounded-md">
                                    Uncounted
                                </span>
                                @elseif($diff === 0)
                                <span class="text-[10px] font-black text-emerald-700 bg-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-300 px-2.5 py-0.5 rounded-md">
                                    Matched
                                </span>
                                @elseif($diff < 0)
                                <span class="text-[10px] font-black text-rose-700 bg-rose-100 dark:bg-rose-900/40 dark:text-rose-300 px-2.5 py-0.5 rounded-md">
                                    Shortage
                                </span>
                                @else
                                <span class="text-[10px] font-black text-blue-700 bg-blue-100 dark:bg-blue-900/40 dark:text-blue-300 px-2.5 py-0.5 rounded-md">
                                    Excess
                                </span>
                                @endif
                            </td>

                            {{-- Quick Actions --}}
                            <td class="py-2.5 px-3 text-center">
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
                            <td colspan="11" class="py-6 text-center text-gray-500 dark:text-gray-400">
                                @if($mode === 'cycle' && $cycleView === 'session')
                                <div class="max-w-lg mx-auto py-4 flex flex-col items-center justify-center gap-2">
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/25 text-xs text-emerald-700 dark:text-emerald-300 font-medium">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        <span>Cycle count session is ready. Enter physical counts in the table or import your count sheet.</span>
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
                                @elseif($mode === 'cycle' && $cycleView === 'diff')
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

        {{-- 7. Dedicated Import Count Sheet (Excel / CSV) Modal --}}
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
                        onclick="document.getElementById('countSheetFileInput').click()">
                        <input type="file"
                            id="countSheetFileInput"
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

        {{-- 8. Review & Bulk Reconciliation Modal --}}
        @if($showReconcileModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/75 backdrop-blur-sm"
            x-transition>
            <div class="bg-white dark:bg-gray-850 rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700 space-y-4 max-h-[90vh] flex flex-col">
                <div class="flex justify-between items-center border-b border-gray-100 dark:border-gray-700/80 pb-3">
                    <div>
                        <h3 class="text-base font-black text-gray-900 dark:text-white">Review &amp; Confirm Stock Reconciliation</h3>
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
                        placeholder="e.g. September 2026 Cycle Count Discrepancy Adjustment"
                        class="w-full text-xs bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-gray-900 dark:text-white focus:ring-primary-500" />
                </div>

                <div class="p-3 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 rounded-xl text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
                    <strong>Notice:</strong> Applying reconciliation creates permanent audited stock movement records (<code>count_reconciliation</code>) adjusting warehouse stock levels. Historical orders, POS sales, and product listings remain 100% untouched.
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

    {{-- Keyboard Navigation & Scanner Alpine.js Controller --}}
    <script>
        function stockCountApp() {
            return {
                init() {
                    // Global form submit protection - completely blocks any browser refresh from enter keypresses
                    window.addEventListener('submit', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                    }, true);

                    // Focus barcode scanner on initial load only once
                    this.$nextTick(() => {
                        this.$refs.barcodeScanner?.focus();
                    });

                    // Global Hotkeys
                    window.addEventListener('keydown', (e) => {
                        if ((e.altKey && (e.key === 's' || e.key === 'S')) || (e.key === '/' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA')) {
                            e.preventDefault();
                            this.$refs.barcodeScanner?.focus();
                            this.$refs.barcodeScanner?.select();
                        }
                    });

                    // Grid Arrow & Enter Navigation (triggers blur smoothly to persist quantity)
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

                    // Audio Feedback on Barcode Scan via Web Audio API (Zero external assets)
                    window.addEventListener('item-scanned', (e) => {
                        try {
                            const detail = e.detail || (Array.isArray(e.detail) ? e.detail[0] : {});
                            if (detail && detail.sound === false) {
                                return;
                            }
                            const ctx = new(window.AudioContext || window.webkitAudioContext)();
                            const gain = ctx.createGain();
                            gain.connect(ctx.destination);

                            if (detail && detail.isMatch) {
                                // Pleasing harmonic chord for perfect match
                                const osc1 = ctx.createOscillator();
                                const osc2 = ctx.createOscillator();
                                osc1.type = 'triangle';
                                osc2.type = 'sine';
                                osc1.frequency.setValueAtTime(880, ctx.currentTime);
                                osc2.frequency.setValueAtTime(1320, ctx.currentTime + 0.08);
                                gain.gain.setValueAtTime(0.14, ctx.currentTime);
                                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.22);
                                osc1.connect(gain);
                                osc2.connect(gain);
                                osc1.start(ctx.currentTime);
                                osc1.stop(ctx.currentTime + 0.08);
                                osc2.start(ctx.currentTime + 0.08);
                                osc2.stop(ctx.currentTime + 0.22);
                            } else if (detail && detail.diff < 0) {
                                // Alert tone for shortage
                                const osc = ctx.createOscillator();
                                osc.type = 'sine';
                                osc.frequency.setValueAtTime(440, ctx.currentTime);
                                gain.gain.setValueAtTime(0.12, ctx.currentTime);
                                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15);
                                osc.connect(gain);
                                osc.start(ctx.currentTime);
                                osc.stop(ctx.currentTime + 0.15);
                            } else {
                                // Standard crisp 880Hz beep
                                const osc = ctx.createOscillator();
                                osc.type = 'sine';
                                osc.frequency.setValueAtTime(880, ctx.currentTime);
                                gain.gain.setValueAtTime(0.12, ctx.currentTime);
                                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.1);
                                osc.connect(gain);
                                osc.start(ctx.currentTime);
                                osc.stop(ctx.currentTime + 0.1);
                            }
                        } catch (err) {
                            // Audio context unsupported or muted
                        }
                    });
                }
            };
        }
    </script>
</x-filament-panels::page>
