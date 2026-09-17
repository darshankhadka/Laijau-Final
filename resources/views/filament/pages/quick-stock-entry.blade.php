<x-filament-panels::page>
    <div class="lj-qs-root" x-data="{
        init() {
            this.$nextTick(() => {
                if (this.$refs.scannerInput) this.$refs.scannerInput.focus();
            });
            window.addEventListener('focus-scanner-input', () => {
                this.$nextTick(() => {
                    if (this.$refs.scannerInput) {
                        this.$refs.scannerInput.focus();
                        this.$refs.scannerInput.select();
                    }
                });
            });
            window.addEventListener('focus-quantity-input', () => {
                this.$nextTick(() => {
                    if (this.$refs.quantityInput) {
                        this.$refs.quantityInput.focus();
                        this.$refs.quantityInput.select();
                    }
                });
            });
        }
    }">

        {{-- SCOPED RETAIL STYLES FOR QUICK STOCK ENTRY --}}
        <style>
            .lj-qs-root {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica NNpe", Arial, sans-serif;
                color: #111827;
                display: flex;
                flex-direction: column;
                gap: 1.25rem;
                padding-bottom: 2.5rem;
                box-sizing: border-box;
            }

            .lj-qs-root *,
            .lj-qs-root *::before,
            .lj-qs-root *::after {
                box-sizing: border-box;
            }

            /* Card Containers */
            .lj-qs-card {
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 0.875rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
                padding: 1.25rem 1.5rem;
            }

            /* Header Section */
            .lj-qs-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 1rem;
            }

            .lj-qs-header-info {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }

            .lj-qs-header-title {
                font-size: 1.35rem;
                font-weight: 800;
                color: #051b14;
                letter-spacing: -0.02em;
                display: flex;
                align-items: center;
                gap: 0.625rem;
                margin: 0;
            }

            .lj-qs-header-sub {
                font-size: 0.8125rem;
                color: #6b7280;
                margin: 0;
            }

            /* Mode Switcher Tabs */
            .lj-qs-tabs {
                display: inline-flex;
                align-items: center;
                background: #f3f4f6;
                padding: 0.25rem;
                border-radius: 0.625rem;
                border: 1px solid #e5e7eb;
                gap: 0.25rem;
            }

            .lj-qs-tab-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.5rem 1rem;
                font-size: 0.8125rem;
                font-weight: 600;
                color: #4b5563;
                background: transparent;
                border: none;
                border-radius: 0.5rem;
                cursor: pointer;
                transition: all 0.15s ease;
            }

            .lj-qs-tab-btn:hover {
                color: #111827;
            }

            .lj-qs-tab-btn.active {
                background: #ffffff;
                color: #0a2e23;
                font-weight: 700;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            }

            /* Operational Context Grid */
            .lj-qs-context-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 0.875rem;
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid #f3f4f6;
            }

            @media (max-width: 1024px) {
                .lj-qs-context-grid {
                    grid-template-columns: 1fr 1fr;
                }
            }

            @media (max-width: 640px) {
                .lj-qs-context-grid {
                    grid-template-columns: 1fr;
                }
            }

            /* Form Controls */
            .lj-qs-field {
                display: flex;
                flex-direction: column;
                gap: 0.375rem;
            }

            .lj-qs-label {
                font-size: 0.75rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: #374151;
            }

            .lj-qs-select,
            .lj-qs-input {
                width: 100%;
                font-size: 0.875rem;
                padding: 0.625rem 0.875rem;
                border: 1px solid #d1d5db;
                border-radius: 0.5rem;
                background: #ffffff;
                color: #111827;
                outline: none;
                transition: border-color 0.15s, box-shadow 0.15s;
            }

            .lj-qs-select:focus,
            .lj-qs-input:focus {
                border-color: #17654e;
                box-shadow: 0 0 0 3px rgba(23, 101, 78, 0.12);
            }

            /* Mode 1: 2-Column Scanner Layout */
            .lj-qs-scanner-layout {
                display: grid;
                grid-template-columns: 7fr 5fr;
                gap: 1.25rem;
                align-items: start;
            }

            @media (max-width: 1024px) {
                .lj-qs-scanner-layout {
                    grid-template-columns: 1fr;
                }
            }

            /* Barcode Scanner Box */
            .lj-qs-scan-hero {
                display: flex;
                flex-direction: column;
                gap: 1.25rem;
            }

            .lj-qs-scan-input-wrapper {
                position: relative;
                display: flex;
                align-items: center;
            }

            .lj-qs-scan-input {
                width: 100%;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 1.125rem;
                font-weight: 600;
                padding: 0.875rem 6.5rem 0.875rem 1rem;
                border: 2px solid #d1d5db;
                border-radius: 0.75rem;
                background: #ffffff;
                color: #111827;
                letter-spacing: 0.05em;
                outline: none;
                transition: all 0.15s ease;
            }

            .lj-qs-scan-input:focus {
                border-color: #059669;
                box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.15);
            }

            .lj-qs-scan-lookup-btn {
                position: absolute;
                right: 0.375rem;
                top: 0.375rem;
                bottom: 0.375rem;
                background: #0a2e23;
                color: #ffffff;
                border: none;
                border-radius: 0.5rem;
                font-size: 0.8125rem;
                font-weight: 700;
                padding: 0 1.25rem;
                cursor: pointer;
                transition: background 0.15s;
                display: flex;
                align-items: center;
                gap: 0.375rem;
            }

            .lj-qs-scan-lookup-btn:hover {
                background: #17654e;
            }

            /* Matched Product Card */
            .lj-qs-matched {
                background: #f0fdf4;
                border: 2px solid #86efac;
                border-radius: 0.875rem;
                padding: 1.25rem;
                display: flex;
                flex-direction: column;
                gap: 1rem;
                animation: ljQsFadeIn 0.2s ease-out;
            }

            @keyframes ljQsFadeIn {
                from {
                    opacity: 0;
                    transform: translateY(-4px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .lj-qs-matched-top {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 1rem;
            }

            .lj-qs-matched-title {
                font-size: 1.125rem;
                font-weight: 800;
                color: #064e3b;
                margin: 0.25rem 0 0 0;
            }

            .lj-qs-tags {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                margin-top: 0.375rem;
            }

            .lj-qs-tag {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
                font-size: 0.6875rem;
                padding: 0.2rem 0.5rem;
                border-radius: 0.375rem;
                background: #dcfce7;
                color: #14532d;
                border: 1px solid #bbf7d0;
                font-weight: 600;
            }

            .lj-qs-stock-badge {
                text-align: right;
                background: #ffffff;
                border: 1px solid #bbf7d0;
                border-radius: 0.625rem;
                padding: 0.5rem 0.875rem;
                min-width: 110px;
            }

            .lj-qs-stock-label {
                font-size: 0.6875rem;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: #6b7280;
                font-weight: 700;
            }

            .lj-qs-stock-num {
                font-size: 1.75rem;
                font-weight: 900;
                color: #065f46;
                line-height: 1.1;
                margin-top: 0.125rem;
            }

            /* Quantity Adjustment Area */
            .lj-qs-qty-box {
                background: #ffffff;
                border: 1px solid #bbf7d0;
                border-radius: 0.75rem;
                padding: 1rem;
                display: flex;
                flex-direction: column;
                gap: 0.875rem;
            }

            .lj-qs-qty-presets {
                display: flex;
                align-items: center;
                gap: 0.375rem;
                flex-wrap: wrap;
            }

            .lj-qs-preset-btn {
                background: #f3f4f6;
                border: 1px solid #e5e7eb;
                border-radius: 0.375rem;
                padding: 0.25rem 0.625rem;
                font-size: 0.75rem;
                font-weight: 700;
                color: #374151;
                cursor: pointer;
                transition: all 0.12s;
            }

            .lj-qs-preset-btn:hover {
                background: #0a2e23;
                color: #ffffff;
                border-color: #0a2e23;
            }

            .lj-qs-qty-row {
                display: grid;
                grid-template-columns: 140px 1fr auto;
                gap: 0.75rem;
                align-items: center;
            }

            @media (max-width: 640px) {
                .lj-qs-qty-row {
                    grid-template-columns: 1fr;
                }
            }

            .lj-qs-qty-input {
                font-size: 1.5rem;
                font-weight: 900;
                text-align: center;
                padding: 0.5rem;
                border: 2px solid #059669;
                border-radius: 0.5rem;
                background: #ffffff;
                color: #064e3b;
                outline: none;
                width: 100%;
            }

            .lj-qs-qty-input:focus {
                box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.2);
            }

            .lj-qs-btn-confirm {
                background: #0a2e23;
                color: #ffffff;
                border: none;
                border-radius: 0.5rem;
                font-size: 0.875rem;
                font-weight: 700;
                padding: 0.75rem 1.25rem;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                transition: background 0.15s;
                height: 100%;
            }

            .lj-qs-btn-confirm:hover {
                background: #17654e;
            }

            .lj-qs-btn-cancel {
                background: #f3f4f6;
                color: #4b5563;
                border: 1px solid #d1d5db;
                border-radius: 0.5rem;
                font-size: 0.875rem;
                font-weight: 600;
                padding: 0.75rem 1rem;
                cursor: pointer;
                transition: all 0.15s;
                height: 100%;
            }

            .lj-qs-btn-cancel:hover {
                background: #e5e7eb;
                color: #111827;
            }

            /* Session Inbound Feed */
            .lj-qs-feed-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 0.875rem;
            }

            .lj-qs-feed-title {
                font-size: 0.875rem;
                font-weight: 700;
                color: #111827;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin: 0;
            }

            .lj-qs-feed-total {
                font-size: 0.75rem;
                font-weight: 800;
                color: #065f46;
                background: #ecfdf5;
                border: 1px solid #a7f3d0;
                padding: 0.2rem 0.5rem;
                border-radius: 0.375rem;
            }

            .lj-qs-feed-list {
                max-height: 480px;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                padding-right: 0.25rem;
            }

            .lj-qs-feed-item {
                background: #f9fafb;
                border: 1px solid #e5e7eb;
                border-radius: 0.625rem;
                padding: 0.75rem 0.875rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                transition: background 0.12s;
            }

            .lj-qs-feed-item:hover {
                background: #f3f4f6;
            }

            .lj-qs-feed-name {
                font-size: 0.8125rem;
                font-weight: 700;
                color: #111827;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 220px;
            }

            .lj-qs-feed-meta {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
                font-size: 0.6875rem;
                color: #6b7280;
                margin-top: 0.125rem;
            }

            .lj-qs-feed-right {
                text-align: right;
                display: flex;
                flex-direction: column;
                align-items: flex-end;
            }

            .lj-qs-feed-pill {
                display: inline-block;
                font-size: 0.75rem;
                font-weight: 800;
                padding: 0.15rem 0.5rem;
                border-radius: 0.375rem;
            }

            .lj-qs-feed-pill.pos {
                background: #dcfce7;
                color: #166534;
            }

            .lj-qs-feed-pill.neg {
                background: #fee2e2;
                color: #991b1b;
            }

            .lj-qs-feed-stock-flow {
                font-size: 0.6875rem;
                color: #9ca3af;
                margin-top: 0.125rem;
            }

            .lj-qs-feed-stock-flow strong {
                color: #374151;
            }

            .lj-qs-feed-empty {
                text-align: center;
                padding: 3rem 1rem;
                color: #9ca3af;
                font-size: 0.8125rem;
                line-height: 1.5;
            }

            /* Mode 2: Size Matrix Styles */
            .lj-qs-matrix-steps {
                display: flex;
                flex-direction: column;
                gap: 1.25rem;
            }

            .lj-qs-color-pills {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                margin-top: 0.375rem;
            }

            .lj-qs-color-btn {
                font-size: 0.8125rem;
                font-weight: 600;
                padding: 0.375rem 0.875rem;
                border-radius: 0.5rem;
                border: 1px solid #d1d5db;
                background: #f9fafb;
                color: #374151;
                cursor: pointer;
                transition: all 0.12s;
            }

            .lj-qs-color-btn:hover {
                border-color: #0a2e23;
            }

            .lj-qs-color-btn.active {
                background: #0a2e23;
                color: #ffffff;
                border-color: #0a2e23;
                font-weight: 700;
            }

            /* Matrix Grid Table */
            .lj-qs-table-wrapper {
                overflow-x: auto;
                border: 1px solid #e5e7eb;
                border-radius: 0.75rem;
                margin-top: 1rem;
            }

            .lj-qs-matrix-table {
                width: 100%;
                border-collapse: collapse;
                text-align: center;
            }

            .lj-qs-matrix-table th {
                background: #f9fafb;
                border-bottom: 1px solid #e5e7eb;
                border-right: 1px solid #e5e7eb;
                padding: 0.75rem 1rem;
            }

            .lj-qs-matrix-table th:last-child {
                border-right: none;
            }

            .lj-qs-matrix-table td {
                border-right: 1px solid #e5e7eb;
                padding: 0.625rem 0.75rem;
            }

            .lj-qs-matrix-table td:last-child {
                border-right: none;
            }

            .lj-qs-matrix-table .stock-row {
                background: #fdfdfd;
                border-bottom: 1px solid #e5e7eb;
                font-size: 0.75rem;
                color: #6b7280;
            }

            .lj-qs-matrix-table .input-row {
                background: #ffffff;
            }

            .lj-qs-matrix-input {
                width: 80px;
                font-size: 1.125rem;
                font-weight: 800;
                text-align: center;
                padding: 0.5rem;
                border: 2px solid #a7f3d0;
                border-radius: 0.5rem;
                background: #f0fdf4;
                color: #064e3b;
                outline: none;
                margin: 0 auto;
                display: block;
            }

            .lj-qs-matrix-input:focus {
                border-color: #059669;
                box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.2);
            }

            .lj-qs-matrix-actions {
                display: flex;
                justify-content: flex-end;
                margin-top: 1rem;
            }

            /* Dark Mode Adaptations */
            .dark .lj-qs-card {
                background: #1f2937;
                border-color: #374151;
            }

            .dark .lj-qs-header-title {
                color: #f9fafb;
            }

            .dark .lj-qs-header-sub {
                color: #9ca3af;
            }

            .dark .lj-qs-tabs {
                background: #111827;
                border-color: #374151;
            }

            .dark .lj-qs-tab-btn {
                color: #9ca3af;
            }

            .dark .lj-qs-tab-btn.active {
                background: #1f2937;
                color: #34d399;
            }

            .dark .lj-qs-context-grid {
                border-top-color: #374151;
            }

            .dark .lj-qs-label {
                color: #d1d5db;
            }

            .dark .lj-qs-select,
            .dark .lj-qs-input {
                background: #111827;
                border-color: #4b5563;
                color: #f9fafb;
            }

            .dark .lj-qs-scan-input {
                background: #111827;
                border-color: #4b5563;
                color: #f9fafb;
            }

            .dark .lj-qs-matched {
                background: #064e3b;
                border-color: #059669;
            }

            .dark .lj-qs-matched-title {
                color: #ecfdf5;
            }

            .dark .lj-qs-qty-box {
                background: #111827;
                border-color: #059669;
            }

            .dark .lj-qs-feed-item {
                background: #111827;
                border-color: #374151;
            }

            .dark .lj-qs-feed-name {
                color: #f9fafb;
            }

            .dark .lj-qs-table-wrapper {
                border-color: #374151;
            }

            .dark .lj-qs-matrix-table th {
                background: #111827;
                border-color: #374151;
                color: #d1d5db;
            }

            .dark .lj-qs-matrix-table td {
                border-color: #374151;
            }

            .dark .lj-qs-matrix-table .stock-row {
                background: #1f2937;
                border-bottom-color: #374151;
            }

            .dark .lj-qs-matrix-table .input-row {
                background: #111827;
            }
        </style>

        {{-- TOP CARD: TITLE, MODE SWITCHER & OPERATIONAL CONTEXT --}}
        <div class="lj-qs-card">
            <div class="lj-qs-header">
                <div class="lj-qs-header-info">
                    <h2 class="lj-qs-header-title">
                        <x-heroicon-o-bolt style="width: 1.5rem; height: 1.5rem; color: #059669;" />
                        Quick Stock Entry & Inbound Receiving
                    </h2>
                    <p class="lj-qs-header-sub">
                        High-speed barcode scanner receiving and multi-variant size grid updates for showroom and warehouse.
                    </p>
                </div>

                {{-- Mode Switcher Tabs --}}
                <div class="lj-qs-tabs">
                    <button type="button"
                        wire:click="$set('mode', 'scanner')"
                        class="lj-qs-tab-btn {{ $mode === 'scanner' ? 'active' : '' }}">
                        <x-heroicon-o-qr-code style="width: 1rem; height: 1rem;" />
                        Barcode Scanner Mode
                    </button>
                    <button type="button"
                        wire:click="$set('mode', 'matrix')"
                        class="lj-qs-tab-btn {{ $mode === 'matrix' ? 'active' : '' }}">
                        <x-heroicon-o-table-cells style="width: 1rem; height: 1rem;" />
                        Size Matrix Grid Mode
                    </button>
                </div>
            </div>

            {{-- Operational Context (Destination Warehouse, Movement Reason, Supplier, Reference) --}}
            <div class="lj-qs-context-grid">
                <div class="lj-qs-field">
                    <label class="lj-qs-label">
                        Destination Warehouse:
                    </label>
                    <select wire:model.live="selectedWarehouseId" class="lj-qs-select">
                        @foreach($this->warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="lj-qs-field">
                    <label class="lj-qs-label">
                        Movement Reason:
                    </label>
                    <select wire:model.live="movementReason" class="lj-qs-select">
                        <option value="purchase_receive">Inbound Purchase / Receiving</option>
                        <option value="adjustment_gain">Found Stock (Surplus)</option>
                        <option value="return_restock">Customer Return Restock</option>
                        <option value="production_receive">Received from Workshop / Manufacturer</option>
                    </select>
                </div>

                <div class="lj-qs-field">
                    <label class="lj-qs-label">
                        Supplier / Source (Optional):
                    </label>
                    <select wire:model.live="selectedSupplierId" class="lj-qs-select">
                        <option value="">— Direct / None —</option>
                        @foreach($this->suppliers as $sup)
                        <option value="{{ $sup->id }}">{{ $sup->name }} ({{ $sup->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="lj-qs-field">
                    <label class="lj-qs-label">
                        Reference / Waybill / Note:
                    </label>
                    <input type="text" wire:model.live="referenceNumber"
                        placeholder="e.g. PO-8912, WB-KTM-44"
                        class="lj-qs-input">
                </div>
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- MODE 1: BARCODE SCANNER FAST RECEIVING     --}}
        {{-- ========================================== --}}
        @if($mode === 'scanner')
        <div class="lj-qs-scanner-layout">

            {{-- Left: Scanner Console (7 cols) --}}
            <div class="lj-qs-card lj-qs-scan-hero">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                        <label class="lj-qs-label" style="display: flex; align-items: center; gap: 0.375rem; margin: 0;">
                            <x-heroicon-o-viewfinder-circle style="width: 1.125rem; height: 1.125rem; color: #059669;" />
                            Scan Barcode or Type SKU (Press Enter):
                        </label>
                        <span style="font-size: 0.6875rem; font-weight: 700; color: #065f46; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 0.15rem 0.5rem; border-radius: 0.375rem;">
                            Ready for POS / USB Scanner
                        </span>
                    </div>

                    <div class="lj-qs-scan-input-wrapper">
                        <input type="text"
                            x-ref="scannerInput"
                            wire:model="scanInput"
                            wire:keydown.enter="handleScan"
                            placeholder="Scan item barcode with handheld reader or type SKU..."
                            autofocus
                            class="lj-qs-scan-input">

                        <button type="button"
                            wire:click="handleScan"
                            class="lj-qs-scan-lookup-btn">
                            Lookup
                        </button>

                        <button type="button"
                            @click="$dispatch('open-stock-camera')"
                            class="lj-qs-scan-lookup-btn"
                            style="background: #065f46; display: inline-flex; align-items: center; gap: 0.35rem;"
                            title="Scan barcode with phone camera">
                            <x-heroicon-o-camera style="width: 1.125rem; height: 1.125rem;" />
                            <span>Camera</span>
                        </button>
                    </div>
                    <div style="font-size: 0.6875rem; color: #9ca3af; margin-top: 0.375rem;">
                        Supports 13-digit EAN barcodes, variant SKUs (e.g. LJ-SHOE-001-41), and master product SKUs.
                    </div>
                </div>

                {{-- Active Scanned Product / Variant Match --}}
                @if($scannedItem)
                <div class="lj-qs-matched">
                    <div class="lj-qs-matched-top">
                        <div>
                            <span style="font-size: 0.6875rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; background: #bbf7d0; color: #14532d; padding: 0.2rem 0.5rem; border-radius: 0.375rem;">
                                {{ $scannedItem['type'] === 'variant' ? 'Variant Matched' : 'Product Matched' }}
                            </span>
                            <h3 class="lj-qs-matched-title">
                                {{ $scannedItem['label'] }}
                            </h3>
                            <div class="lj-qs-tags">
                                <span class="lj-qs-tag">SKU: {{ $scannedItem['sku'] }}</span>
                                <span class="lj-qs-tag">Barcode: {{ $scannedItem['barcode'] }}</span>
                            </div>
                        </div>

                        <div class="lj-qs-stock-badge">
                            <div class="lj-qs-stock-label">Current On Hand</div>
                            <div class="lj-qs-stock-num">
                                {{ $scannedItem['warehouse_stock'] }}
                            </div>
                        </div>
                    </div>

                    {{-- Quantity Adjustment Bar --}}
                    <div class="lj-qs-qty-box">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                            <label class="lj-qs-label" style="margin: 0;">
                                Quantity to Receive (+) or Deduct (-):
                            </label>
                            {{-- Quick Presets --}}
                            <div class="lj-qs-qty-presets">
                                <span style="font-size: 0.6875rem; color: #6b7280; font-weight: 600;">Presets:</span>
                                <button type="button" wire:click="setQuantityPreset(1)" class="lj-qs-preset-btn">+1</button>
                                <button type="button" wire:click="setQuantityPreset(5)" class="lj-qs-preset-btn">+5</button>
                                <button type="button" wire:click="setQuantityPreset(10)" class="lj-qs-preset-btn">+10</button>
                                <button type="button" wire:click="setQuantityPreset(25)" class="lj-qs-preset-btn">+25</button>
                                <button type="button" wire:click="setQuantityPreset(50)" class="lj-qs-preset-btn">+50</button>
                                <button type="button" wire:click="setQuantityPreset(-1)" class="lj-qs-preset-btn" style="color: #991b1b;">-1</button>
                            </div>
                        </div>

                        <div class="lj-qs-qty-row">
                            <input type="number"
                                x-ref="quantityInput"
                                wire:model="quantityToAdd"
                                wire:keydown.enter="commitScan"
                                class="lj-qs-qty-input">

                            <button type="button"
                                wire:click="commitScan"
                                class="lj-qs-btn-confirm">
                                <x-heroicon-o-check style="width: 1.25rem; height: 1.25rem;" />
                                Confirm & Receive (Enter)
                            </button>

                            <button type="button"
                                wire:click="cancelScan"
                                class="lj-qs-btn-cancel">
                                Cancel (Esc)
                            </button>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Right: Current Session Inbound Scans (5 cols) --}}
            <div class="lj-qs-card">
                <div class="lj-qs-feed-header">
                    <h3 class="lj-qs-feed-title">
                        <x-heroicon-o-clock style="width: 1rem; height: 1rem; color: #059669;" />
                        Current Session Scans ({{ count($recentScans) }})
                    </h3>
                    @if(count($recentScans) > 0)
                    <span class="lj-qs-feed-total">
                        +{{ array_sum(array_column($recentScans, 'delta')) }} Total Units
                    </span>
                    @endif
                </div>

                <div class="lj-qs-feed-list">
                    @forelse($recentScans as $scan)
                    <div class="lj-qs-feed-item">
                        <div style="min-width: 0;">
                            <div class="lj-qs-feed-name">
                                {{ $scan['name'] }}
                            </div>
                            <div class="lj-qs-feed-meta">
                                {{ $scan['sku'] }} • {{ $scan['time'] }}
                            </div>
                        </div>
                        <div class="lj-qs-feed-right">
                            <span class="lj-qs-feed-pill {{ $scan['delta'] > 0 ? 'pos' : 'neg' }}">
                                {{ $scan['delta'] > 0 ? '+' : '' }}{{ $scan['delta'] }}
                            </span>
                            <div class="lj-qs-feed-stock-flow">
                                {{ $scan['previous_stock'] }} → <strong>{{ $scan['new_stock'] }}</strong>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="lj-qs-feed-empty">
                        <x-heroicon-o-arrow-path style="width: 2rem; height: 2rem; margin: 0 auto 0.5rem auto; color: #d1d5db; display: block;" />
                        No inbound scans completed yet this session.<br>
                        Scan an item barcode or type SKU to begin.
                    </div>
                    @endforelse
                </div>
            </div>

        </div>
        @endif

        {{-- ========================================== --}}
        {{-- MODE 2: SIZE MATRIX GRID RECEIVING         --}}
        {{-- ========================================== --}}
        @if($mode === 'matrix')
        <div class="lj-qs-card lj-qs-matrix-steps">
            <div>
                <h3 style="font-size: 1.125rem; font-weight: 800; color: #111827; margin: 0;">
                    Batch Size Matrix Receiving
                </h3>
                <p style="font-size: 0.8125rem; color: #6b7280; margin: 0.25rem 0 0 0;">
                    Receive bulk footwear sizes or garment variations in a single atomic update.
                </p>
            </div>

            {{-- Product & Colorway Selection --}}
            <div class="lj-qs-context-grid" style="margin-top: 0; padding-top: 0; border-top: none;">
                <div class="lj-qs-field">
                    <label class="lj-qs-label">
                        1. Select Product to Receive:
                    </label>
                    <select wire:model.live="matrixProductId" class="lj-qs-select">
                        <option value="">-- Choose Product --</option>
                        @foreach($this->products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                        @endforeach
                    </select>
                </div>

                @if(!empty($matrixAvailableColors))
                <div class="lj-qs-field">
                    <label class="lj-qs-label">
                        2. Select Colorway:
                    </label>
                    <div class="lj-qs-color-pills">
                        @foreach($matrixAvailableColors as $color)
                        <button type="button"
                            wire:click="$set('matrixColor', '{{ $color }}')"
                            class="lj-qs-color-btn {{ $matrixColor === $color ? 'active' : '' }}">
                            {{ $color }}
                        </button>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            {{-- Horizontal Size Matrix Table --}}
            @if(count($matrixGrid) > 0)
            <div style="border-top: 1px solid #e5e7eb; padding-top: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <h4 style="font-size: 0.875rem; font-weight: 700; color: #111827; margin: 0;">
                        3. Enter Receiving Quantities for Each Size:
                    </h4>
                    <span style="font-size: 0.75rem; color: #6b7280;">
                        Positive number increments stock on hand
                    </span>
                </div>

                <div class="lj-qs-table-wrapper">
                    <table class="lj-qs-matrix-table">
                        <thead>
                            <tr>
                                @foreach($matrixGrid as $cell)
                                <th>
                                    <div style="font-size: 0.875rem; font-weight: 800; color: #111827;">{{ $cell['size'] }}</div>
                                    <div style="font-family: monospace; font-size: 0.6875rem; color: #9ca3af;">{{ $cell['sku'] }}</div>
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="stock-row">
                                @foreach($matrixGrid as $cell)
                                <td>
                                    Current: <strong style="color: #111827;">{{ $cell['current_stock'] }}</strong>
                                </td>
                                @endforeach
                            </tr>
                            <tr class="input-row">
                                @foreach($matrixGrid as $idx => $cell)
                                <td>
                                    <input type="number" min="0" max="1000"
                                        wire:model.defer="matrixGrid.{{ $idx }}.add_qty"
                                        placeholder="0"
                                        class="lj-qs-matrix-input">
                                </td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="lj-qs-matrix-actions">
                    <button type="button"
                        wire:click="commitMatrixReceiving"
                        class="lj-qs-btn-confirm"
                        style="padding: 0.75rem 1.75rem; font-size: 0.875rem;">
                        <x-heroicon-o-check-circle style="width: 1.25rem; height: 1.25rem;" />
                        Receive Stock Across All Sizes
                    </button>
                </div>
            </div>
            @elseif($matrixProductId)
            <div style="text-align: center; padding: 2.5rem 1rem; color: #9ca3af; font-size: 0.8125rem; border: 1px dashed #e5e7eb; border-radius: 0.75rem;">
                No active variants found for this product colorway.
            </div>
            @endif
        </div>
        @endif

        {{-- MOBILE CAMERA SCANNER MODAL FOR QUICK STOCK ENTRY --}}
        <div
            x-data="stockCameraScanner()"
            @open-stock-camera.window="openScanner()"
            x-show="isOpen"
            x-cloak
            class="lj-qs-modal-overlay"
            style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1rem;"
            @keydown.escape.window="closeScanner()">
            <div style="background: #ffffff; border-radius: 0.75rem; max-width: 480px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e5e7eb;">
                <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 1.25rem;">📷</span>
                        <div>
                            <h3 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin: 0;">Inventory Camera Scanner</h3>
                            <p style="font-size: 0.6875rem; color: #64748b; margin: 0;">Scan item barcode to receive stock directly</p>
                        </div>
                    </div>
                    <button type="button" @click="closeScanner()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #64748b;">✕</button>
                </div>

                <div style="padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem;">
                    <div style="position: relative; width: 100%; min-height: 260px; background: #0f172a; border-radius: 0.5rem; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                        <div id="lj-stock-camera-viewport" style="width: 100%;"></div>

                        <div x-show="isScanning && !hasError" style="position: absolute; inset: 0; pointer-events: none; display: flex; align-items: center; justify-content: center;">
                            <div style="width: 220px; height: 140px; border: 2px solid #10b981; border-radius: 0.5rem; box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.45); position: relative;">
                                <div style="position: absolute; top: 50%; left: 0; right: 0; height: 2px; background: #ef4444; opacity: 0.75;"></div>
                            </div>
                        </div>

                        <div x-show="hasError" style="position: absolute; inset: 0; background: rgba(15, 23, 42, 0.95); color: #ffffff; padding: 1.5rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 0.75rem;">
                            <span style="font-size: 2rem;">📷⚠️</span>
                            <div style="font-size: 0.8125rem; font-weight: 700; color: #fca5a5;" x-text="errorMessage"></div>
                            <button type="button" @click="startCamera()" style="background: #059669; color: #ffffff; padding: 0.4rem 0.85rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; border: none; cursor: pointer;">
                                Retry Camera
                            </button>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="button" @click="switchCamera()" style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                                🔄 Switch Camera
                            </button>
                            <button type="button" x-show="hasTorch" @click="toggleTorch()" style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                                <span x-text="torchOn ? '🔦 Light On' : '💡 Light Off'"></span>
                            </button>
                        </div>
                        <div x-show="scanCount > 0" style="font-size: 0.75rem; font-weight: 700; color: #059669;">
                            Scanned: <span x-text="scanCount"></span> items
                        </div>
                    </div>

                    <div x-show="lastScanned" style="background: #064e3b; border: 1px solid #10b981; border-radius: 0.375rem; padding: 0.5rem 0.75rem; font-size: 0.75rem; color: #ffffff; display: flex; align-items: center; justify-content: space-between;">
                        <span>Scanned Barcode: <strong style="font-family: monospace;" x-text="lastScanned"></strong></span>
                        <span>✓ Loaded for Receiving</span>
                    </div>
                </div>

                <div style="padding: 0.75rem 1.25rem; border-top: 1px solid #e5e7eb; background: #f8fafc; text-align: right;">
                    <button type="button" @click="closeScanner()" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.5rem 1rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; color: #475569;">
                        Close Scanner
                    </button>
                </div>
            </div>
        </div>

        <script src="/js/html5-qrcode.min.js?v=2.3.8"></script>
        <script>
            function stockCameraScanner() {
                return {
                    isOpen: false,
                    isScanning: false,
                    hasError: false,
                    errorMessage: '',
                    lastScanned: '',
                    scanCount: 0,
                    html5QrCode: null,
                    facingMode: "environment",
                    torchOn: false,
                    hasTorch: false,

                    openScanner() {
                        this.isOpen = true;
                        this.hasError = false;
                        this.errorMessage = '';
                        this.lastScanned = '';
                        this.$nextTick(() => {
                            this.startCamera();
                        });
                    },

                    async startCamera() {
                        if (typeof Html5Qrcode === 'undefined') {
                            this.hasError = true;
                            this.errorMessage = 'Scanner library is loading. Please wait...';
                            return;
                        }

                        try {
                            if (this.html5QrCode && this.html5QrCode.isScanning) {
                                await this.stopCamera();
                            }

                            this.html5QrCode = new Html5Qrcode("lj-stock-camera-viewport");
                            this.isScanning = true;
                            this.hasError = false;

                            const config = {
                                fps: 15,
                                qrbox: {
                                    width: 250,
                                    height: 160
                                },
                                aspectRatio: 1.333334,
                            };

                            let lastCode = '';
                            let lastTime = 0;

                            await this.html5QrCode.start({
                                    facingMode: this.facingMode
                                },
                                config,
                                (decodedText) => {
                                    const now = Date.now();
                                    if (decodedText === lastCode && (now - lastTime) < 1500) {
                                        return;
                                    }
                                    lastCode = decodedText;
                                    lastTime = now;
                                    this.onBarcodeDetected(decodedText);
                                },
                                () => {}
                            );

                            try {
                                const track = this.html5QrCode.getRunningTrackCapabilities();
                                this.hasTorch = !!track?.torch;
                            } catch (e) {
                                this.hasTorch = false;
                            }
                        } catch (err) {
                            this.isScanning = false;
                            this.hasError = true;
                            this.errorMessage = (err?.name === 'NotAllowedError' || err?.message?.toLowerCase().includes('permission')) ?
                                'Camera permission denied. Please allow camera permissions in browser settings.' :
                                'Could not access device camera: ' + (err?.message || 'Camera not available');
                        }
                    },

                    async stopCamera() {
                        if (this.html5QrCode && this.html5QrCode.isScanning) {
                            try {
                                await this.html5QrCode.stop();
                                this.html5QrCode.clear();
                            } catch (e) {}
                        }
                        this.isScanning = false;
                    },

                    async closeScanner() {
                        await this.stopCamera();
                        this.isOpen = false;
                    },

                    async switchCamera() {
                        this.facingMode = this.facingMode === "environment" ? "user" : "environment";
                        await this.startCamera();
                    },

                    async toggleTorch() {
                        if (!this.html5QrCode || !this.hasTorch) return;
                        try {
                            this.torchOn = !this.torchOn;
                            await this.html5QrCode.applyVideoConstraints({
                                advanced: [{
                                    torch: this.torchOn
                                }]
                            });
                        } catch (e) {}
                    },

                    onBarcodeDetected(code) {
                        this.playBeep();
                        if (navigator.vibrate) {
                            navigator.vibrate([60, 40, 60]);
                        }
                        this.lastScanned = code;
                        this.scanCount++;

                        @this.set('scanInput', code);
                        @this.handleScan();
                    },

                    playBeep() {
                        try {
                            const audioCtx = new(window.AudioContext || window.webkitAudioContext)();
                            const osc = audioCtx.createOscillator();
                            const gain = audioCtx.createGain();
                            osc.type = 'sine';
                            osc.frequency.value = 1900;
                            gain.gain.value = 0.25;
                            osc.connect(gain);
                            gain.connect(audioCtx.destination);
                            osc.start();
                            gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.12);
                            setTimeout(() => {
                                osc.stop();
                                audioCtx.close();
                            }, 150);
                        } catch (e) {}
                    }
                };
            }
        </script>
</x-filament-panels::page>