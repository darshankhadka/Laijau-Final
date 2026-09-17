<x-filament-panels::page>
    <div class="lj-fh-root">

        {{-- SCOPED HIGH-DENSITY LIGHT-MODE OPERATIONAL STYLES --}}
        <style>
            .lj-fh-root {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica NNpe", Arial, sans-serif;
                color: #0f172a;
                display: flex;
                flex-direction: column;
                gap: 1.25rem;
                padding-bottom: 2rem;
                box-sizing: border-box;
            }

            .lj-fh-root *,
            .lj-fh-root *::before,
            .lj-fh-root *::after {
                box-sizing: border-box;
            }

            /* Top KPI 4x2 Grid */
            .lj-kpi-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 0.75rem;
            }

            @media (max-width: 1024px) {
                .lj-kpi-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }

            @media (max-width: 640px) {
                .lj-kpi-grid {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 0.5rem;
                }

                .lj-kpi-card {
                    padding: 0.625rem 0.75rem !important;
                }

                .lj-kpi-value {
                    font-size: 1.25rem !important;
                }
            }

            .lj-kpi-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 0.75rem;
                padding: 0.875rem 1rem;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
                cursor: pointer;
                transition: all 0.15s ease;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                text-align: left;
            }

            .lj-kpi-card:hover {
                border-color: #064e3b;
                box-shadow: 0 4px 6px -1px rgba(6, 78, 59, 0.08);
                transform: translateY(-1px);
            }

            .lj-kpi-card.is-active {
                border-color: #064e3b;
                background: #f0fdf4;
                box-shadow: 0 0 0 2px rgba(6, 78, 59, 0.15);
            }

            .lj-kpi-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.5rem;
            }

            .lj-kpi-label {
                font-size: 0.6875rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #64748b;
            }

            .lj-kpi-count {
                font-size: 1.5rem;
                font-weight: 800;
                color: #0f172a;
                line-height: 1.2;
                margin-top: 0.25rem;
                letter-spacing: -0.02em;
            }

            .lj-kpi-sub {
                font-size: 0.6875rem;
                font-weight: 500;
                color: #64748b;
                margin-top: 0.2rem;
            }

            /* Filter Bar */
            .lj-filter-bar {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 0.75rem;
                padding: 0.75rem 1rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 0.75rem;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
            }

            .lj-filter-inputs {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 0.5rem;
                flex: 1;
            }

            .lj-search-box {
                position: relative;
                min-width: 260px;
                flex: 1;
            }

            .lj-input {
                width: 100%;
                font-size: 0.8125rem;
                padding: 0.45rem 0.75rem;
                border: 1px solid #cbd5e1;
                border-radius: 0.5rem;
                background: #ffffff;
                color: #0f172a;
                outline: none;
                transition: border-color 0.15s;
            }

            .lj-input:focus {
                border-color: #064e3b;
                box-shadow: 0 0 0 2px rgba(6, 78, 59, 0.15);
            }

            .lj-select {
                font-size: 0.8125rem;
                padding: 0.45rem 0.75rem;
                border: 1px solid #cbd5e1;
                border-radius: 0.5rem;
                background: #ffffff;
                color: #0f172a;
                outline: none;
                cursor: pointer;
            }

            .lj-select:focus {
                border-color: #064e3b;
            }

            /* Tabs Strip */
            .lj-tabs-strip {
                display: flex;
                align-items: center;
                gap: 0.35rem;
                overflow-x: auto;
                padding-bottom: 0.25rem;
                border-bottom: 2px solid #e2e8f0;
            }

            .lj-tab-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                padding: 0.5rem 0.875rem;
                font-size: 0.75rem;
                font-weight: 600;
                color: #64748b;
                background: transparent;
                border: none;
                border-bottom: 2px solid transparent;
                margin-bottom: -2px;
                cursor: pointer;
                transition: all 0.15s ease;
                white-space: nowrap;
            }

            .lj-tab-btn:hover {
                color: #0f172a;
                background: #f8fafc;
            }

            .lj-tab-btn.active {
                color: #064e3b;
                border-bottom-color: #064e3b;
                font-weight: 700;
                background: #f0fdf4;
            }

            .lj-tab-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.1rem 0.4rem;
                font-size: 0.6875rem;
                font-weight: 700;
                border-radius: 9999px;
                background: #e2e8f0;
                color: #334155;
            }

            .lj-tab-btn.active .lj-tab-badge {
                background: #064e3b;
                color: #ffffff;
            }

            /* Table Component */
            .lj-table-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 0.75rem;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            }

            .lj-table-wrapper {
                overflow-x: auto;
            }

            .lj-table {
                width: 100%;
                border-collapse: collapse;
                text-align: left;
                font-size: 0.8125rem;
            }

            .lj-table th {
                background: #f8fafc;
                border-bottom: 1px solid #e2e8f0;
                padding: 0.625rem 0.875rem;
                font-size: 0.6875rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #64748b;
                white-space: nowrap;
            }

            .lj-table td {
                padding: 0.75rem 0.875rem;
                border-bottom: 1px solid #f1f5f9;
                vertical-align: middle;
            }

            .lj-table tr:hover td {
                background: #f8fafc;
            }

            /* Operational Action Buttons */
            .lj-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                padding: 0.35rem 0.65rem;
                font-size: 0.75rem;
                font-weight: 600;
                border-radius: 0.375rem;
                border: 1px solid transparent;
                cursor: pointer;
                text-decoration: none;
                transition: all 0.15s ease;
                white-space: nowrap;
            }

            .lj-btn-primary {
                background: #064e3b;
                color: #ffffff;
                border-color: #064e3b;
            }

            .lj-btn-primary:hover {
                background: #043629;
            }

            .lj-btn-secondary {
                background: #ffffff;
                color: #334155;
                border-color: #cbd5e1;
            }

            .lj-btn-secondary:hover {
                background: #f8fafc;
                color: #0f172a;
            }

            .lj-btn-amber {
                background: #f59e0b;
                color: #ffffff;
                border-color: #d97706;
            }

            .lj-btn-amber:hover {
                background: #d97706;
            }

            /* Badges & Status */
            .lj-pill {
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
                padding: 0.15rem 0.5rem;
                font-size: 0.6875rem;
                font-weight: 700;
                border-radius: 9999px;
                white-space: nowrap;
            }

            .lj-pill-emerald {
                background: #d1fae5;
                color: #065f46;
            }

            .lj-pill-blue {
                background: #dbeafe;
                color: #1e40af;
            }

            .lj-pill-amber {
                background: #fef3c7;
                color: #92400e;
            }

            .lj-pill-rose {
                background: #ffe4e6;
                color: #9f1239;
            }

            .lj-pill-gray {
                background: #f1f5f9;
                color: #475569;
            }

            /* Monospace text */
            .lj-mono {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 0.75rem;
            }

            /* Modals */
            .lj-modal-overlay {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.6);
                backdrop-filter: blur(2px);
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
            }

            .lj-modal-container {
                background: #ffffff;
                border-radius: 0.875rem;
                border: 1px solid #e2e8f0;
                max-width: 34rem;
                width: 100%;
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
                display: flex;
                flex-direction: column;
            }

            .lj-modal-header {
                padding: 1rem 1.25rem;
                border-bottom: 1px solid #e2e8f0;
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: #f8fafc;
            }

            .lj-modal-body {
                padding: 1.25rem;
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }

            .lj-modal-footer {
                padding: 0.875rem 1.25rem;
                border-top: 1px solid #e2e8f0;
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 0.5rem;
                background: #f8fafc;
            }

            /* Printable Manifest Specific */
            @media print {
                body * {
                    visibility: hidden;
                }

                #lj-printable-manifest,
                #lj-printable-manifest * {
                    visibility: visible;
                }

                #lj-printable-manifest {
                    position: fixed;
                    left: 0;
                    top: 0;
                    width: 100%;
                    height: 100%;
                    background: #ffffff;
                    padding: 20px;
                    font-size: 11px;
                }
            }
        </style>

        {{-- TOP SUMMARY 8 REAL-TIME DATABASE KPIS --}}
        @php
        $metrics = $this->metrics;
        @endphp
        <div class="lj-kpi-grid">
            {{-- 1. Ready to Pick --}}
            <div class="lj-kpi-card {{ $activeTab === 'ready_pick' ? 'is-active' : '' }}" wire:click="setTab('ready_pick')">
                <div class="lj-kpi-header">
                    <span class="lj-kpi-label">Ready to Pick</span>
                    <span class="lj-pill lj-pill-blue">Step 1</span>
                </div>
                <div class="lj-kpi-count">{{ $metrics['ready_pick'] }}</div>
                <div class="lj-kpi-sub">Verified orders awaiting pick</div>
            </div>

            {{-- 2. Packing & Boxing --}}
            <div class="lj-kpi-card {{ $activeTab === 'packing' ? 'is-active' : '' }}" wire:click="setTab('packing')">
                <div class="lj-kpi-header">
                    <span class="lj-kpi-label">Packing Queue</span>
                    <span class="lj-pill lj-pill-amber">Step 2</span>
                </div>
                <div class="lj-kpi-count">{{ $metrics['packing'] }}</div>
                <div class="lj-kpi-sub">Items picked, packing/boxing</div>
            </div>

            {{-- 3. Ready to Dispatch --}}
            <div class="lj-kpi-card {{ $activeTab === 'ready_dispatch' ? 'is-active' : '' }}" wire:click="setTab('ready_dispatch')">
                <div class="lj-kpi-header">
                    <span class="lj-kpi-label">Ready to Dispatch</span>
                    <span class="lj-pill lj-pill-emerald">Step 3</span>
                </div>
                <div class="lj-kpi-count">{{ $metrics['ready_dispatch'] }}</div>
                <div class="lj-kpi-sub">Boxed, awaiting courier pickup</div>
            </div>

            {{-- 4. Dispatched Today --}}
            <div class="lj-kpi-card">
                <div class="lj-kpi-header">
                    <span class="lj-kpi-label">Dispatched Today</span>
                    <span class="lj-pill lj-pill-gray">NPT</span>
                </div>
                <div class="lj-kpi-count">{{ $metrics['dispatched_today'] }}</div>
                <div class="lj-kpi-sub">Handed to couriers today</div>
            </div>

            {{-- 5. In Transit / Delivery --}}
            <div class="lj-kpi-card {{ $activeTab === 'in_transit' ? 'is-active' : '' }}" wire:click="setTab('in_transit')">
                <div class="lj-kpi-header">
                    <span class="lj-kpi-label">In Transit</span>
                    <span class="lj-pill lj-pill-blue">Active</span>
                </div>
                <div class="lj-kpi-count">{{ $metrics['in_transit'] }}</div>
                <div class="lj-kpi-sub">Parcels out on the road</div>
            </div>

            {{-- 6. Delivered Today --}}
            <div class="lj-kpi-card">
                <div class="lj-kpi-header">
                    <span class="lj-kpi-label">Delivered Today</span>
                    <span class="lj-pill lj-pill-emerald">Done</span>
                </div>
                <div class="lj-kpi-count">{{ $metrics['delivered_today'] }}</div>
                <div class="lj-kpi-sub">Successful customer deliveries</div>
            </div>

            {{-- 7. Exceptions & Returns --}}
            <div class="lj-kpi-card {{ $activeTab === 'exceptions' ? 'is-active' : '' }}" wire:click="setTab('exceptions')">
                <div class="lj-kpi-header">
                    <span class="lj-kpi-label">Exceptions / Returns</span>
                    <span class="lj-pill {{ $metrics['exceptions'] > 0 ? 'lj-pill-rose' : 'lj-pill-gray' }}">Issues</span>
                </div>
                <div class="lj-kpi-count" style="{{ $metrics['exceptions'] > 0 ? 'color: #be123c;' : '' }}">{{ $metrics['exceptions'] }}</div>
                <div class="lj-kpi-sub">Unreachable, refused or failed</div>
            </div>

            {{-- 8. COD Remittances Pending --}}
            <div class="lj-kpi-card {{ $activeTab === 'cod_settlements' ? 'is-active' : '' }}" wire:click="setTab('cod_settlements')">
                <div class="lj-kpi-header">
                    <span class="lj-kpi-label">COD Remittance Due</span>
                    <span class="lj-pill lj-pill-amber">Finance</span>
                </div>
                <div class="lj-kpi-count" style="font-size: 1.15rem; color: #b45309;">
                    {{ \App\Helpers\NepaliNumberHelper::formatCurrency($metrics['cod_amount'], 'Rs. ', 0) }}
                </div>
                <div class="lj-kpi-sub">{{ \App\Helpers\NepaliNumberHelper::format($metrics['cod_count']) }} delivered parcels awaiting settlement</div>
            </div>
        </div>

        {{-- 2B. NCM LOGISTICS RECONCILIATION & FLEET OVERVIEW --}}
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem 1.25rem; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #059669;"></span>
                    <h3 style="font-size: 0.875rem; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.05em; margin: 0;">
                        Nepal Can Move (NCM) Logistics Fleet Overview
                    </h3>
                </div>
                <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">
                    Canonical AWB Tracking Active &bull; Non-Destructive Real Dataset
                </span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.75rem; text-align: left;">
                <div style="background: #f8fafc; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid #e2e8f0;">
                    <div style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #64748b;">Total NCM Shipments</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #0f172a;">{{ \App\Helpers\NepaliNumberHelper::format($metrics['ncm_total_shipments']) }}</div>
                </div>
                <div style="background: #f0fdf4; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid #bbf7d0;">
                    <div style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #166534;">Delivered</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #15803d;">{{ \App\Helpers\NepaliNumberHelper::format($metrics['ncm_delivered']) }}</div>
                </div>
                <div style="background: #eff6ff; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid #bfdbfe;">
                    <div style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #1e40af;">Out for Delivery</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #2563eb;">{{ \App\Helpers\NepaliNumberHelper::format($metrics['ncm_out_for_delivery']) }}</div>
                </div>
                <div style="background: #f8fafc; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid #e2e8f0;">
                    <div style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #64748b;">Dispatched</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #0f172a;">{{ \App\Helpers\NepaliNumberHelper::format($metrics['ncm_dispatched']) }}</div>
                </div>
                <div style="background: #f8fafc; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid #e2e8f0;">
                    <div style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #64748b;">Arrived at Hub</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #0f172a;">{{ \App\Helpers\NepaliNumberHelper::format($metrics['ncm_arrived']) }}</div>
                </div>
                <div style="background: #fefce8; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid #fef08a;">
                    <div style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #854d0e;">Pickup Pending</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #a16207;">{{ \App\Helpers\NepaliNumberHelper::format($metrics['ncm_pickup_pending']) }}</div>
                </div>
                <div style="background: #fff1f2; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid #fecdd3;">
                    <div style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #9f1239;">Vendor Returns</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #be123c;">{{ \App\Helpers\NepaliNumberHelper::format($metrics['ncm_vendor_returns']) }}</div>
                </div>
                <div style="background: #fff7ed; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid #fed7aa;">
                    <div style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #9a3412;">Unmatched/Ambiguous</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #c2410c;">{{ \App\Helpers\NepaliNumberHelper::format($metrics['ncm_unmatched_ambiguous']) }}</div>
                </div>
                <div style="background: #f0fdf4; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid #bbf7d0;">
                    <div style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #166534;">Total NCM COD</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #15803d;">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($metrics['ncm_cod_amount'], 'Rs. ', 2) }}</div>
                </div>
                <div style="background: #f8fafc; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid #e2e8f0;">
                    <div style="font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #64748b;">NCM Delivery Fees</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #0f172a;">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($metrics['ncm_delivery_charge'], 'Rs. ', 2) }}</div>
                </div>
            </div>
        </div>

        {{-- 3. SEARCH & MULTI-FILTER BAR --}}
        <div class="lj-filter-bar">
            <div class="lj-filter-inputs">
                <div class="lj-search-box">
                    <input type="text"
                        wire:model.live.debounce.300ms="searchQuery"
                        placeholder="Search Order #, customer name, phone, or AWB tracking..."
                        class="lj-input">
                </div>

                <select wire:model.live="zoneFilter" class="lj-select">
                    <option value="">All Delivery Zones</option>
                    <option value="kathmandu_valley">Kathmandu Valley</option>
                    <option value="outside_valley">Outside Valley</option>
                </select>

                <select wire:model.live="paymentFilter" class="lj-select">
                    <option value="">All Payment Types</option>
                    <option value="cod">Cash on Delivery (COD)</option>
                    <option value="prepaid">Prepaid (eSewa / Card / ConnectIPS)</option>
                </select>

                <select wire:model.live="courierFilter" class="lj-select">
                    <option value="">All Courier Partners</option>
                    <option value="pathao">Pathao Courier</option>
                    <option value="ncm">Nepal Can Move (NCM)</option>
                    <option value="in_house">In-House Rider</option>
                </select>

                @if(!empty($searchQuery) || !empty($zoneFilter) || !empty($paymentFilter) || !empty($courierFilter))
                <button type="button"
                    wire:click="$set('searchQuery', ''); $set('zoneFilter', null); $set('paymentFilter', null); $set('courierFilter', null);"
                    class="lj-btn lj-btn-secondary" style="font-size: 0.6875rem;">
                    Clear Filters
                </button>
                @endif
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem;">
                @if($activeTab === 'ready_dispatch')
                <button type="button" wire:click="bulkDispatchReady" class="lj-btn lj-btn-primary">
                    <x-heroicon-m-paper-airplane style="width: 0.875rem; height: 0.875rem;" />
                    Bulk Dispatch Ready
                </button>
                @endif

                <button type="button" wire:click="openManifestModal" class="lj-btn lj-btn-secondary">
                    <x-heroicon-m-printer style="width: 0.875rem; height: 0.875rem;" />
                    Dispatch Manifest
                </button>
            </div>
        </div>

        {{-- 4. ACTIONABLE OPERATIONAL QueueS TABS --}}
        <div class="lj-tabs-strip">
            <button type="button" wire:click="setTab('ready_pick')" class="lj-tab-btn {{ $activeTab === 'ready_pick' ? 'active' : '' }}">
                <span>1. Ready to Pick</span>
                <span class="lj-tab-badge">{{ $metrics['ready_pick'] }}</span>
            </button>

            <button type="button" wire:click="setTab('packing')" class="lj-tab-btn {{ $activeTab === 'packing' ? 'active' : '' }}">
                <span>2. Packing Queue</span>
                <span class="lj-tab-badge">{{ $metrics['packing'] }}</span>
            </button>

            <button type="button" wire:click="setTab('ready_dispatch')" class="lj-tab-btn {{ $activeTab === 'ready_dispatch' ? 'active' : '' }}">
                <span>3. Ready to Dispatch</span>
                <span class="lj-tab-badge">{{ $metrics['ready_dispatch'] }}</span>
            </button>

            <button type="button" wire:click="setTab('in_transit')" class="lj-tab-btn {{ $activeTab === 'in_transit' ? 'active' : '' }}">
                <span>4. In Transit / Active Delivery</span>
                <span class="lj-tab-badge">{{ $metrics['in_transit'] }}</span>
            </button>

            <button type="button" wire:click="setTab('exceptions')" class="lj-tab-btn {{ $activeTab === 'exceptions' ? 'active' : '' }}">
                <span>5. Exceptions & Returns</span>
                <span class="lj-tab-badge" style="{{ $metrics['exceptions'] > 0 ? 'background: #ffe4e6; color: #be123c;' : '' }}">{{ $metrics['exceptions'] }}</span>
            </button>

            <button type="button" wire:click="setTab('cod_settlements')" class="lj-tab-btn {{ $activeTab === 'cod_settlements' ? 'active' : '' }}">
                <span>6. COD Remittances</span>
                <span class="lj-tab-badge">{{ $metrics['cod_count'] }}</span>
            </button>
        </div>

        {{-- 5. Queue DATA TABLE --}}
        @php
        $paginatedOrders = $this->paginatedOrders;
        @endphp
        <div class="lj-table-card">
            <div class="lj-table-wrapper">
                <table class="lj-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer & Contact</th>
                            <th>Destination / Zone</th>
                            @if($activeTab === 'ready_pick')
                            <th>Items to Pick</th>
                            <th>Order Age</th>
                            <th>Payment</th>
                            <th style="text-align: right;">Action</th>
                            @elseif($activeTab === 'packing')
                            <th>Picked Items</th>
                            <th>Shipping Method</th>
                            <th>Payment / COD</th>
                            <th style="text-align: right;">Action</th>
                            @elseif($activeTab === 'ready_dispatch')
                            <th>Package Details</th>
                            <th>Courier / Carrier</th>
                            <th>COD Amount</th>
                            <th style="text-align: right;">Action</th>
                            @elseif($activeTab === 'in_transit')
                            <th>Carrier & Tracking (AWB)</th>
                            <th>Dispatched At</th>
                            <th>Courier Status</th>
                            <th style="text-align: right;">Action</th>
                            @elseif($activeTab === 'exceptions')
                            <th>Carrier</th>
                            <th>Reason & Comments</th>
                            <th>Date</th>
                            <th style="text-align: right;">Action</th>
                            @elseif($activeTab === 'cod_settlements')
                            <th>Carrier</th>
                            <th>Delivered At</th>
                            <th>Order Total</th>
                            <th>Net Remittance</th>
                            <th style="text-align: right;">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paginatedOrders as $order)
                        @php
                        $isCod = strtolower((string)$order->payment_method) === 'cod';
                        $orderTotal = (float)($order->total_amount_npr ?: $order->total_amount);
                        $itemsCount = $order->items->sum('quantity');
                        @endphp
                        <tr>
                            {{-- 1. Order # --}}
                            <td>
                                <div style="display: flex; flex-direction: column;">
                                    <a href="/intadmin/orders/{{ $order->id }}" class="lj-mono" style="font-weight: 700; color: #064e3b; text-decoration: none;" target="_blank">
                                        #{{ $order->order_number }}
                                    </a>
                                    <span style="font-size: 0.6875rem; color: #64748b;">
                                        {{ $order->created_at?->timezone('Asia/Kathmandu')->format('M d, H:i') }}
                                    </span>
                                </div>
                            </td>

                            {{-- 2. Customer & Contact --}}
                            <td>
                                <div style="display: flex; flex-direction: column;">
                                    <span style="font-weight: 600; color: #0f172a;">{{ $order->first_name }} {{ $order->last_name }}</span>
                                    <div style="display: flex; align-items: center; gap: 0.35rem; margin-top: 0.15rem;">
                                        <a href="tel:{{ $order->phone }}" class="lj-mono" style="color: #475569; text-decoration: none;">
                                            {{ $order->phone }}
                                        </a>
                                        @if($order->phone)
                                        <a href="https://wa.me/977{{ preg_replace('/[^0-9]/', '', $order->phone) }}" target="_blank" title="WhatsApp Chat" style="color: #16a34a; text-decoration: none; font-size: 0.75rem;">
                                            💬
                                        </a>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- 3. Destination / Zone --}}
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                                    <span style="font-size: 0.75rem; color: #334155;">
                                        {{ $order->tole ? $order->tole . ', ' : '' }}{{ $order->shipping_city ?: $order->district ?: 'Kathmandu' }}
                                    </span>
                                    @if($order->is_inside_valley)
                                    <span class="lj-pill lj-pill-emerald" style="width: fit-content;">Kathmandu Valley</span>
                                    @else
                                    <span class="lj-pill lj-pill-blue" style="width: fit-content;">Outside Valley ({{ $order->district ?: 'Nepal' }})</span>
                                    @endif
                                </div>
                            </td>

                            {{-- 4. Queue-Specific Columns --}}
                            @if($activeTab === 'ready_pick')
                            <td>
                                <div style="display: flex; flex-direction: column;">
                                    <span style="font-weight: 600; color: #0f172a;">{{ $itemsCount }} item(s)</span>
                                    <span style="font-size: 0.6875rem; color: #64748b; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        {{ $order->items->pluck('product_name')->implode(', ') }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 0.75rem; color: #475569;">
                                    {{ $order->created_at?->diffForHumans() }}
                                </span>
                            </td>
                            <td>
                                @if($isCod)
                                <span class="lj-pill lj-pill-amber">COD: Rs. {{ number_format($orderTotal, 0) }}</span>
                                @else
                                <span class="lj-pill lj-pill-emerald">Prepaid ({{ strtoupper($order->payment_method ?: 'Card') }})</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.35rem;">
                                    <button type="button" wire:click="openPickingModal({{ $order->id }})" class="lj-btn lj-btn-primary">
                                        <x-heroicon-m-check-circle style="width: 0.875rem; height: 0.875rem;" />
                                        Pick Items
                                    </button>
                                    <a href="/intadmin/orders/{{ $order->id }}" class="lj-btn lj-btn-secondary" target="_blank">
                                        View
                                    </a>
                                </div>
                            </td>

                            @elseif($activeTab === 'packing')
                            <td>
                                <div style="display: flex; flex-direction: column;">
                                    <span style="font-weight: 600; color: #0f172a;">{{ $itemsCount }} item(s) ready</span>
                                    <span style="font-size: 0.6875rem; color: #16a34a;">✔ Picked & verified</span>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 0.75rem; color: #334155;">
                                    {{ $order->shipping_method ?: ($order->is_inside_valley ? 'Valley Express' : 'Courier') }}
                                </span>
                            </td>
                            <td>
                                @if($isCod)
                                <span class="lj-pill lj-pill-amber">COD: Rs. {{ number_format($orderTotal, 0) }}</span>
                                @else
                                <span class="lj-pill lj-pill-emerald">Paid: Rs. {{ number_format($orderTotal, 0) }}</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.35rem;">
                                    <button type="button" wire:click="openPackingModal({{ $order->id }})" class="lj-btn lj-btn-primary">
                                        <x-heroicon-m-archive-box style="width: 0.875rem; height: 0.875rem;" />
                                        Pack & Seal
                                    </button>
                                    <a href="/intadmin/orders/{{ $order->id }}/packing-slip" class="lj-btn lj-btn-secondary" target="_blank" title="Print Packing Slip">
                                        Slip
                                    </a>
                                </div>
                            </td>

                            @elseif($activeTab === 'ready_dispatch')
                            <td>
                                <div style="display: flex; flex-direction: column;">
                                    <span style="font-size: 0.75rem; font-weight: 600; color: #0f172a;">{{ $itemsCount }} items</span>
                                    <span style="font-size: 0.6875rem; color: #065f46;">Boxed & Ready</span>
                                </div>
                            </td>
                            <td>
                                @php
                                $recommendedCourier = app(\App\Services\Logistics\LogisticsService::class)->recommendCourier($order);
                                @endphp
                                <span class="lj-pill lj-pill-gray">
                                    {{ $order->carrier ?: ($recommendedCourier === 'pathao' ? 'Pathao Courier' : 'Nepal Can Move (NCM)') }}
                                </span>
                            </td>
                            <td>
                                @if($isCod)
                                <span class="lj-pill lj-pill-amber" style="font-weight: 800;">Collect Rs. {{ number_format($orderTotal, 0) }}</span>
                                @else
                                <span class="lj-pill lj-pill-emerald">Prepaid (Collect Rs. 0)</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.35rem;">
                                    <button type="button" wire:click="openDispatchModal({{ $order->id }})" class="lj-btn lj-btn-primary">
                                        <x-heroicon-m-truck style="width: 0.875rem; height: 0.875rem;" />
                                        Dispatch
                                    </button>
                                    <a href="/intadmin/orders/{{ $order->id }}/packing-slip" class="lj-btn lj-btn-secondary" target="_blank">
                                        Slip
                                    </a>
                                </div>
                            </td>

                            @elseif($activeTab === 'in_transit')
                            <td>
                                <div style="display: flex; flex-direction: column;">
                                    <span style="font-weight: 600; color: #0f172a;">{{ $order->carrier ?: $order->courier_name ?: 'Courier' }}</span>
                                    @if($order->tracking_url)
                                    <a href="{{ $order->tracking_url }}" target="_blank" class="lj-mono" style="color: #2563eb; text-decoration: underline;">
                                        {{ $order->tracking_number ?: $order->courier_order_id }} ↗
                                    </a>
                                    @else
                                    <span class="lj-mono" style="color: #475569;">
                                        {{ $order->tracking_number ?: $order->courier_order_id ?: 'Assigned' }}
                                    </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 0.75rem; color: #475569;">
                                    {{ $order->courier_pickup_date ? \Carbon\Carbon::parse($order->courier_pickup_date)->timezone('Asia/Kathmandu')->format('M d, H:i') : ($order->updated_at?->format('M d, H:i') ?? '—') }}
                                </span>
                            </td>
                            <td>
                                <span class="lj-pill lj-pill-blue">
                                    {{ $order->courier_status ?: 'In Transit' }}
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.35rem;">
                                    @if($order->courier_order_id)
                                    <button type="button" wire:click="syncShipment({{ $order->id }})" class="lj-btn lj-btn-secondary" title="Sync Courier API Status">
                                        Sync
                                    </button>
                                    @endif
                                    <button type="button" wire:click="markDelivered({{ $order->id }})" class="lj-btn lj-btn-primary">
                                        Mark Delivered
                                    </button>
                                    <button type="button" wire:click="openExceptionModal({{ $order->id }})" class="lj-btn lj-btn-secondary" title="Log Exception or Issue">
                                        Exception
                                    </button>
                                </div>
                            </td>

                            @elseif($activeTab === 'exceptions')
                            <td>
                                <span class="lj-pill lj-pill-gray">{{ $order->carrier ?: 'Courier' }}</span>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; max-width: 280px;">
                                    <span style="font-weight: 600; color: #be123c;">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                                    <span style="font-size: 0.6875rem; color: #475569; overflow: hidden; text-overflow: ellipsis;">
                                        {{ $order->courier_comments ?: 'Delivery attempt failed or returned' }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 0.75rem; color: #475569;">
                                    {{ $order->updated_at?->timezone('Asia/Kathmandu')->format('M d, H:i') }}
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.35rem;">
                                    <button type="button" wire:click="openExceptionModal({{ $order->id }})" class="lj-btn lj-btn-primary">
                                        Resolve / Retry
                                    </button>
                                    <a href="/intadmin/orders/{{ $order->id }}" class="lj-btn lj-btn-secondary" target="_blank">
                                        View Order
                                    </a>
                                </div>
                            </td>

                            @elseif($activeTab === 'cod_settlements')
                            <td>
                                <span class="lj-pill lj-pill-gray">{{ $order->carrier ?: 'Courier' }}</span>
                            </td>
                            <td>
                                <span style="font-size: 0.75rem; color: #475569;">
                                    {{ $order->delivered_at ? \Carbon\Carbon::parse($order->delivered_at)->timezone('Asia/Kathmandu')->format('M d, H:i') : 'Delivered' }}
                                </span>
                            </td>
                            <td>
                                <span class="lj-mono" style="font-weight: 700;">Rs. {{ number_format($orderTotal, 2) }}</span>
                            </td>
                            <td>
                                <span class="lj-mono" style="font-weight: 700; color: #065f46;">
                                    Rs. {{ number_format(max(0, $orderTotal - 150), 2) }}
                                </span>
                                <span style="font-size: 0.6875rem; color: #64748b; display: block;">(Est. Fee Rs. 150)</span>
                            </td>
                            <td style="text-align: right;">
                                <button type="button" wire:click="openCodModal({{ $order->id }})" class="lj-btn lj-btn-amber">
                                    Settle Remittance
                                </button>
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2.5rem 1rem; color: #64748b;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.5rem;">
                                    <x-heroicon-o-inbox style="width: 2rem; height: 2rem; color: #94a3b8;" />
                                    <span style="font-weight: 600; font-size: 0.875rem; color: #334155;">No orders in this operational queue</span>
                                    <span style="font-size: 0.75rem; color: #64748b;">All orders matching current filter criteria have been processed or moved forward.</span>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($paginatedOrders->hasPages())
            <div style="padding: 0.75rem 1rem; border-top: 1px solid #e2e8f0; background: #f8fafc;">
                <x-filament::pagination :paginator="$paginatedOrders" />
            </div>
            @endif
        </div>

        {{-- ========================================== --}}
        {{-- MODAL 1: ITEMIZED PICKING & VERIFICATION  --}}
        {{-- ========================================== --}}
        @if($pickingOrderId)
        @php
        $pickingOrder = \App\Models\Order::with(['items.product', 'items.variant'])->find($pickingOrderId);
        @endphp
        <div class="lj-modal-overlay">
            <div class="lj-modal-container">
                <div class="lj-modal-header">
                    <div>
                        <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Warehouse Picking Checklist</span>
                        <h3 style="font-size: 1.125rem; font-weight: 800; color: #0f172a; margin-top: 0.15rem;">
                            Order #{{ $pickingOrder?->order_number }}
                        </h3>
                    </div>
                    <button type="button" wire:click="closePickingModal" style="background: none; border: none; cursor: pointer; color: #64748b; font-size: 1.25rem;">✕</button>
                </div>

                <div class="lj-modal-body">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem; display: flex; justify-content: space-between; font-size: 0.75rem;">
                        <div>
                            <span style="color: #64748b; display: block;">Customer</span>
                            <span style="font-weight: 600; color: #0f172a;">{{ $pickingOrder?->first_name }} {{ $pickingOrder?->last_name }}</span>
                        </div>
                        <div>
                            <span style="color: #64748b; display: block;">Destination</span>
                            <span style="font-weight: 600; color: #0f172a;">{{ $pickingOrder?->is_inside_valley ? 'Kathmandu Valley' : 'Outside Valley' }}</span>
                        </div>
                        <div>
                            <span style="color: #64748b; display: block;">Facility</span>
                            <span class="lj-pill lj-pill-emerald">WH-KTM-MAIN</span>
                        </div>
                    </div>

                    <div style="font-size: 0.75rem; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.05em;">
                        Verify & Scan Items:
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        @foreach($pickingOrder?->items ?? [] as $item)
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.625rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; background: #ffffff;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <input type="checkbox"
                                    wire:model.live="pickedChecklist.{{ $item->id }}"
                                    style="width: 1.125rem; height: 1.125rem; accent-color: #064e3b; cursor: pointer;">
                                <div>
                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.8125rem;">{{ $item->product_name }}</div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.15rem;">
                                        <span class="lj-mono" style="color: #64748b;">SKU: {{ $item->sku ?: 'SKU-NONE' }}</span>
                                        @if($item->selected_size || $item->selected_color)
                                        <span class="lj-pill lj-pill-gray">
                                            {{ $item->selected_color }} {{ $item->selected_size ? '· ' . $item->selected_size : '' }}
                                        </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span style="font-size: 1rem; font-weight: 800; color: #064e3b;">x{{ $item->quantity }}</span>
                                <span style="display: block; font-size: 0.6875rem; color: #64748b;">Qty Expected</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closePickingModal" class="lj-btn lj-btn-secondary">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmPicking({{ $pickingOrder->id }})" class="lj-btn lj-btn-primary">
                        <x-heroicon-m-check style="width: 1rem; height: 1rem;" />
                        Confirm Picked & Move to Packing
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- ========================================== --}}
        {{-- MODAL 2: PACKING & BOXING WORKSPACE       --}}
        {{-- ========================================== --}}
        @if($packingOrderId)
        @php
        $packingOrder = \App\Models\Order::with('items')->find($packingOrderId);
        @endphp
        <div class="lj-modal-overlay">
            <div class="lj-modal-container">
                <div class="lj-modal-header">
                    <div>
                        <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Packing & Boxing Workspace</span>
                        <h3 style="font-size: 1.125rem; font-weight: 800; color: #0f172a; margin-top: 0.15rem;">
                            Pack Order #{{ $packingOrder?->order_number }}
                        </h3>
                    </div>
                    <button type="button" wire:click="closePackingModal" style="background: none; border: none; cursor: pointer; color: #64748b; font-size: 1.25rem;">✕</button>
                </div>

                <div class="lj-modal-body">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem; font-size: 0.75rem;">
                        <span style="color: #64748b; font-weight: 700; text-transform: uppercase; display: block; margin-bottom: 0.25rem;">Shipping Address:</span>
                        <div style="font-weight: 600; color: #0f172a;">
                            {{ $packingOrder?->shipping_address ?: $packingOrder?->shipping_city }}
                            ({{ $packingOrder?->district ?: 'Nepal' }})
                        </div>
                        @if($packingOrder?->delivery_notes)
                        <div style="margin-top: 0.35rem; color: #b45309; font-style: italic;">
                            Customer Note: "{{ $packingOrder->delivery_notes }}"
                        </div>
                        @endif
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">Package Box Count & Estimated Weight</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                            <div>
                                <input type="number" wire:model="packageBoxCount" min="1" max="10" class="lj-input" placeholder="Box count (e.g. 1)">
                            </div>
                            <div>
                                <input type="text" wire:model="packageWeight" class="lj-input" placeholder="Weight (e.g. 0.5 kg)">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">Packing Notes / Fragile Instructions</label>
                        <textarea wire:model="packingNotes" rows="2" class="lj-input" placeholder="e.g. Wrapped in premium tissue, bubble envelope attached, invoice sealed."></textarea>
                    </div>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closePackingModal" class="lj-btn lj-btn-secondary">
                        Cancel
                    </button>
                    <a href="/intadmin/orders/{{ $packingOrder->id }}/packing-slip" target="_blank" class="lj-btn lj-btn-secondary">
                        Print Packing Slip
                    </a>
                    <button type="button" wire:click="confirmPacking({{ $packingOrder->id }})" class="lj-btn lj-btn-primary">
                        <x-heroicon-m-archive-box-arrow-down style="width: 1rem; height: 1rem;" />
                        Mark Packed & Ready for Courier
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- ========================================== --}}
        {{-- MODAL 3: COURIER DISPATCH WORKSPACE        --}}
        {{-- ========================================== --}}
        @if($dispatchingOrderId)
        @php
        $dispatchOrder = \App\Models\Order::find($dispatchingOrderId);
        $isCodOrder = strtolower((string)$dispatchOrder?->payment_method) === 'cod';
        $codTotal = (float)($dispatchOrder?->total_amount_npr ?: $dispatchOrder?->total_amount);
        @endphp
        <div class="lj-modal-overlay">
            <div class="lj-modal-container">
                <div class="lj-modal-header">
                    <div>
                        <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Courier Handoff & Dispatch</span>
                        <h3 style="font-size: 1.125rem; font-weight: 800; color: #0f172a; margin-top: 0.15rem;">
                            Dispatch Order #{{ $dispatchOrder?->order_number }}
                        </h3>
                    </div>
                    <button type="button" wire:click="closeDispatchModal" style="background: none; border: none; cursor: pointer; color: #64748b; font-size: 1.25rem;">✕</button>
                </div>

                <div class="lj-modal-body">
                    {{-- Pre-dispatch Summary Pill --}}
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem;">
                        <div>
                            <span style="color: #64748b; display: block;">Recipient</span>
                            <span style="font-weight: 600; color: #0f172a;">{{ $dispatchOrder?->first_name }} {{ $dispatchOrder?->last_name }} ({{ $dispatchOrder?->phone }})</span>
                            <span style="color: #64748b; display: block; margin-top: 0.15rem;">{{ $dispatchOrder?->shipping_city ?: $dispatchOrder?->district }}</span>
                        </div>
                        <div style="text-align: right;">
                            @if($isCodOrder)
                            <span class="lj-pill lj-pill-amber" style="font-weight: 800;">COD: Rs. {{ number_format($codTotal, 0) }}</span>
                            <span style="display: block; font-size: 0.6875rem; color: #92400e; margin-top: 0.15rem;">Courier to collect</span>
                            @else
                            <span class="lj-pill lj-pill-emerald" style="font-weight: 800;">PREPAID</span>
                            <span style="display: block; font-size: 0.6875rem; color: #065f46; margin-top: 0.15rem;">Do NOT collect cash</span>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">Select Logistics Partner</label>
                        <select wire:model.live="selectedCourier" class="lj-select" style="width: 100%;">
                            <option value="pathao">Pathao Courier (Kathmandu Valley & Major Cities)</option>
                            <option value="ncm">Nepal Can Move (NCM - Nationwide Express)</option>
                            <option value="in_house">Laijau In-House Showroom Rider</option>
                            <option value="manual">Other Third-Party Courier</option>
                        </select>
                    </div>

                    @if($selectedCourier === 'manual' || $selectedCourier === 'in_house')
                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">Rider / Carrier Name</label>
                        <input type="text" wire:model="manualCourierName" class="lj-input" placeholder="e.g. Ram Shrestha (In-House) or Sundarban Express">
                    </div>
                    @endif

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">
                            Tracking / Consignment AWB #
                            @if(in_array($selectedCourier, ['pathao', 'ncm']))
                            <span style="font-weight: 500; color: #059669;">(Leave blank — auto-generated via {{ strtoupper($selectedCourier) }} API)</span>
                            @else
                            <span style="font-weight: 500; color: #64748b;">(Optional manual entry)</span>
                            @endif
                        </label>
                        <input type="text" wire:model="manualTrackingNumber" class="lj-input lj-mono" placeholder="{{ in_array($selectedCourier, ['pathao', 'ncm']) ? 'Leave blank for automatic API booking...' : 'e.g. LJ-RIDER-01 or AWB-98213' }}">
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">Handoff / Dispatch Remarks</label>
                        <input type="text" wire:model="dispatchNotes" class="lj-input" placeholder="e.g. Handed over in morning 11 AM courier batch">
                    </div>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closeDispatchModal" class="lj-btn lj-btn-secondary">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmDispatch({{ $dispatchOrder->id }})" class="lj-btn lj-btn-primary">
                        <x-heroicon-m-paper-airplane style="width: 1rem; height: 1rem;" />
                        Dispatch & Handoff to Courier
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- ========================================== --}}
        {{-- MODAL 4: DELIVERY EXCEPTION & RESOLUTION   --}}
        {{-- ========================================== --}}
        @if($exceptionOrderId)
        @php
        $excOrder = \App\Models\Order::find($exceptionOrderId);
        @endphp
        <div class="lj-modal-overlay">
            <div class="lj-modal-container">
                <div class="lj-modal-header">
                    <div>
                        <span style="font-size: 0.75rem; font-weight: 700; color: #be123c; text-transform: uppercase;">Delivery Exception / Issue</span>
                        <h3 style="font-size: 1.125rem; font-weight: 800; color: #0f172a; margin-top: 0.15rem;">
                            Order #{{ $excOrder?->order_number }}
                        </h3>
                    </div>
                    <button type="button" wire:click="closeExceptionModal" style="background: none; border: none; cursor: pointer; color: #64748b; font-size: 1.25rem;">✕</button>
                </div>

                <div class="lj-modal-body">
                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">Exception Reason</label>
                        <select wire:model="exceptionReason" class="lj-select" style="width: 100%;">
                            <option value="customer_unreachable">Customer Phone Switched Off / Unreachable</option>
                            <option value="customer_refused">Customer Refused Delivery at Doorstep</option>
                            <option value="address_incorrect">Wrong / Incomplete Address Given</option>
                            <option value="rescheduled_by_customer">Customer Requested Delivery Reschedule</option>
                            <option value="damaged_in_transit">Package Damaged During Transit</option>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">Resolution Action</label>
                        <select wire:model="exceptionAction" class="lj-select" style="width: 100%;">
                            <option value="retry">Reschedule Delivery (Keep in Transit / Re-dispatch)</option>
                            <option value="fail">Mark as Delivery Failed</option>
                            <option value="return_stock">Cancel & Return Items to Warehouse Stock</option>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">Notes & Follow-up Details</label>
                        <textarea wire:model="exceptionNotes" rows="2" class="lj-input" placeholder="e.g. Called customer twice, agreed to deliver tomorrow afternoon."></textarea>
                    </div>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closeExceptionModal" class="lj-btn lj-btn-secondary">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmException({{ $excOrder->id }})" class="lj-btn lj-btn-primary">
                        Save Exception Record
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- ========================================== --}}
        {{-- MODAL 5: COD REMITTANCE & SETTLEMENT       --}}
        {{-- ========================================== --}}
        @if($settlingOrderId)
        @php
        $settlingOrder = \App\Models\Order::find($settlingOrderId);
        $codGross = (float)($settlingOrder?->total_amount_npr ?: $settlingOrder?->total_amount);
        $codNet = max(0, $codGross - $courierFee);
        @endphp
        <div class="lj-modal-overlay">
            <div class="lj-modal-container">
                <div class="lj-modal-header">
                    <div>
                        <span style="font-size: 0.75rem; font-weight: 700; color: #b45309; text-transform: uppercase;">COD Remittance Reconciliation</span>
                        <h3 style="font-size: 1.125rem; font-weight: 800; color: #0f172a; margin-top: 0.15rem;">
                            Order #{{ $settlingOrder?->order_number }}
                        </h3>
                    </div>
                    <button type="button" wire:click="closeCodModal" style="background: none; border: none; cursor: pointer; color: #64748b; font-size: 1.25rem;">✕</button>
                </div>

                <div class="lj-modal-body">
                    <div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 0.5rem; padding: 0.875rem; display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.8125rem;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #92400e;">Delivered Order Total:</span>
                            <span style="font-weight: 700; color: #78350f;">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($codGross, 'Rs. ', 2) }}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #92400e;">Courier Delivery Fee:</span>
                            <span style="font-weight: 700; color: #be123c;">- {{ \App\Helpers\NepaliNumberHelper::formatCurrency($courierFee, 'Rs. ', 2) }}</span>
                        </div>
                        <div style="border-top: 1px solid #fde68a; padding-top: 0.35rem; display: flex; justify-content: space-between; font-size: 0.9375rem;">
                            <span style="font-weight: 700; color: #92400e;">Net Bank Remittance:</span>
                            <span style="font-weight: 800; color: #065f46;">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($codNet, 'Rs. ', 2) }}</span>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <div>
                            <label style="font-size: 0.75rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">Courier Deducted Fee (Rs.)</label>
                            <input type="number" wire:model.live="courierFee" step="10" class="lj-input" placeholder="150.00">
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">Remittance Date</label>
                            <input type="date" wire:model="settlementDate" class="lj-input">
                        </div>
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #334155; display: block; margin-bottom: 0.35rem;">Courier Settlement Batch Reference</label>
                        <input type="text" wire:model="settlementBatchRef" class="lj-input lj-mono" placeholder="e.g. PATHAO-BATCH-20260911">
                    </div>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closeCodModal" class="lj-btn lj-btn-secondary">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmCodSettlement" class="lj-btn lj-btn-primary">
                        <x-heroicon-m-check-badge style="width: 1rem; height: 1rem;" />
                        Post & Mark Paid
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- ========================================== --}}
        {{-- MODAL 6: COURIER DISPATCH MANIFEST PRINT   --}}
        {{-- ========================================== --}}
        @if($showManifestModal)
        @php
        $manifestOrders = $this->manifestOrders;
        $manifestCodSum = 0;
        @endphp
        <div class="lj-modal-overlay">
            <div class="lj-modal-container" style="max-width: 54rem;">
                <div class="lj-modal-header">
                    <div>
                        <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Courier Dispatch Handover Manifest</span>
                        <h3 style="font-size: 1.125rem; font-weight: 800; color: #0f172a; margin-top: 0.15rem;">
                            Dispatch Manifest: {{ date('d M Y') }}
                        </h3>
                    </div>
                    <button type="button" wire:click="closeManifestModal" style="background: none; border: none; cursor: pointer; color: #64748b; font-size: 1.25rem;">✕</button>
                </div>

                <div class="lj-modal-body" id="lj-printable-manifest">
                    <div style="display: flex; justify-content: space-between; border-bottom: 2px solid #0f172a; padding-bottom: 0.5rem;">
                        <div>
                            <h2 style="font-size: 1.25rem; font-weight: 900; color: #064e3b; margin: 0;">LAIJAU RETAIL</h2>
                            <span style="font-size: 0.75rem; color: #475569;">Central Fulfillment Facility: WH-KTM-MAIN, Kathmandu, Nepal</span>
                        </div>
                        <div style="text-align: right; font-size: 0.75rem;">
                            <div><strong>Date:</strong> {{ date('Y-m-d H:i') }} (NPT)</div>
                            <div><strong>Orders Count:</strong> {{ $manifestOrders->count() }}</div>
                        </div>
                    </div>

                    <table style="width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.75rem;">
                        <thead>
                            <tr style="background: #f1f5f9; border-bottom: 1px solid #cbd5e1;">
                                <th style="padding: 0.4rem; text-align: left;">S.N.</th>
                                <th style="padding: 0.4rem; text-align: left;">Order #</th>
                                <th style="padding: 0.4rem; text-align: left;">Customer & Phone</th>
                                <th style="padding: 0.4rem; text-align: left;">Destination</th>
                                <th style="padding: 0.4rem; text-align: left;">Carrier</th>
                                <th style="padding: 0.4rem; text-align: right;">COD to Collect</th>
                                <th style="padding: 0.4rem; text-align: center;">Driver Sign</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($manifestOrders as $index => $mOrder)
                            @php
                            $isCod = strtolower((string)$mOrder->payment_method) === 'cod';
                            $amt = (float)($mOrder->total_amount_npr ?: $mOrder->total_amount);
                            if ($isCod) {
                            $manifestCodSum += $amt;
                            }
                            @endphp
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 0.45rem;">{{ $index + 1 }}</td>
                                <td style="padding: 0.45rem; font-family: monospace; font-weight: 700;">#{{ $mOrder->order_number }}</td>
                                <td style="padding: 0.45rem;">{{ $mOrder->first_name }} {{ $mOrder->last_name }} ({{ $mOrder->phone }})</td>
                                <td style="padding: 0.45rem;">{{ $mOrder->shipping_city ?: $mOrder->district }}</td>
                                <td style="padding: 0.45rem;">{{ $mOrder->carrier ?: 'Courier' }}</td>
                                <td style="padding: 0.45rem; text-align: right; font-weight: 700;">
                                    {{ $isCod ? \App\Helpers\NepaliNumberHelper::formatCurrency($amt, 'Rs. ', 0) : 'PREPAID' }}
                                </td>
                                <td style="padding: 0.45rem; text-align: center; border-bottom: 1px dashed #cbd5e1;">________</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8fafc; font-weight: 800; border-top: 2px solid #0f172a;">
                                <td colspan="5" style="padding: 0.5rem;">TOTALS</td>
                                <td style="padding: 0.5rem; text-align: right; color: #064e3b;">{{ \App\Helpers\NepaliNumberHelper::formatCurrency($manifestCodSum, 'Rs. ', 0) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>

                    <div style="display: flex; justify-content: space-between; margin-top: 2.5rem; padding-top: 1rem; font-size: 0.75rem;">
                        <div>
                            <div>_________________________________</div>
                            <div><strong>Dispatched By (Warehouse Staff):</strong> {{ auth()->user()?->name ?? 'Staff' }}</div>
                        </div>
                        <div style="text-align: right;">
                            <div>_________________________________</div>
                            <div><strong>Courier Driver / Representative Signature</strong></div>
                        </div>
                    </div>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closeManifestModal" class="lj-btn lj-btn-secondary">
                        Close
                    </button>
                    <button type="button" onclick="window.print()" class="lj-btn lj-btn-primary">
                        <x-heroicon-m-printer style="width: 1rem; height: 1rem;" />
                        Print Manifest (A4)
                    </button>
                </div>
            </div>
        </div>
        @endif

    </div>
</x-filament-panels::page>