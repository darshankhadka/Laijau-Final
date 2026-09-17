<x-filament-panels::page>
    <div class="lj-bc-root">

        {{-- SELF-CONTAINED EMBEDDED RETAIL STYLES --}}
        <style>
            .lj-bc-root {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                color: #111827;
                display: flex;
                flex-direction: column;
                gap: 1.25rem;
                padding-bottom: 3rem;
            }

            /* Card Container */
            .lj-bc-card {
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 0.75rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
                padding: 1.25rem 1.5rem;
                box-sizing: border-box;
            }

            /* Top Header */
            .lj-bc-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 1rem;
            }

            .lj-bc-header-title {
                font-size: 1.35rem;
                font-weight: 800;
                color: #051b14;
                letter-spacing: -0.02em;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin: 0;
            }

            .lj-bc-header-sub {
                font-size: 0.8125rem;
                color: #6b7280;
                margin-top: 0.25rem;
                margin-bottom: 0;
            }

            /* Buttons */
            .lj-bc-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.375rem;
                font-size: 0.8125rem;
                font-weight: 700;
                padding: 0.5rem 0.875rem;
                border-radius: 0.5rem;
                cursor: pointer;
                transition: all 0.15s ease;
                border: 1px solid transparent;
                text-decoration: none;
                line-height: 1.25;
            }

            .lj-bc-btn-outline {
                background: #f9fafb;
                border-color: #d1d5db;
                color: #374151;
            }

            .lj-bc-btn-outline:hover {
                background: #f3f4f6;
                border-color: #9ca3af;
                color: #111827;
            }

            .lj-bc-btn-emerald {
                background: #0a2e23;
                color: #ffffff;
                border-color: #0a2e23;
            }

            .lj-bc-btn-emerald:hover {
                background: #17654e;
                border-color: #17654e;
            }

            .lj-bc-btn-secondary {
                background: #f3f4f6;
                color: #4b5563;
                border-color: #e5e7eb;
            }

            .lj-bc-btn-secondary:hover {
                background: #e5e7eb;
            }

            .lj-bc-btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }

            /* Badge */
            .lj-bc-badge {
                display: inline-flex;
                align-items: center;
                padding: 0.2rem 0.5rem;
                border-radius: 0.375rem;
                font-size: 0.6875rem;
                font-weight: 700;
                line-height: 1;
            }

            .lj-bc-badge-emerald {
                background: #ecfdf5;
                color: #065f46;
                border: 1px solid #a7f3d0;
            }

            .lj-bc-badge-amber {
                background: #fffbeb;
                color: #92400e;
                border: 1px solid #fde68a;
            }

            .lj-bc-badge-gray {
                background: #f3f4f6;
                color: #4b5563;
                border: 1px solid #e5e7eb;
            }

            .lj-bc-badge-red {
                background: #fef2f2;
                color: #991b1b;
                border: 1px solid #fecaca;
            }

            /* Toolbar */
            .lj-bc-toolbar {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }

            .lj-bc-toolbar-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 0.75rem;
            }

            .lj-bc-search-wrap {
                position: relative;
                flex: 1;
                min-width: 260px;
            }

            .lj-bc-search-input {
                width: 100%;
                padding: 0.5rem 3.5rem 0.5rem 2.25rem;
                font-size: 0.8125rem;
                border: 1px solid #d1d5db;
                border-radius: 0.5rem;
                background: #ffffff;
                color: #111827;
                outline: none;
                box-sizing: border-box;
            }

            .lj-bc-search-input:focus {
                border-color: #0a2e23;
                box-shadow: 0 0 0 2px rgba(10, 46, 35, 0.15);
            }

            .lj-bc-search-icon {
                position: absolute;
                left: 0.75rem;
                top: 50%;
                transform: translateY(-50%);
                color: #9ca3af;
                width: 1rem;
                height: 1rem;
                pointer-events: none;
            }

            .lj-bc-kbd {
                position: absolute;
                right: 0.625rem;
                top: 50%;
                transform: translateY(-50%);
                font-size: 0.625rem;
                font-family: ui-monospace, monospace;
                font-weight: 600;
                color: #6b7280;
                background: #f3f4f6;
                border: 1px solid #e5e7eb;
                border-radius: 0.25rem;
                padding: 0.125rem 0.375rem;
            }

            .lj-bc-select {
                padding: 0.5rem 0.75rem;
                font-size: 0.8125rem;
                border: 1px solid #d1d5db;
                border-radius: 0.5rem;
                background: #ffffff;
                color: #374151;
                outline: none;
                cursor: pointer;
            }

            .lj-bc-select:focus {
                border-color: #0a2e23;
            }

            /* Batch Sub-bar */
            .lj-bc-batch-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 0.5rem;
                padding-top: 0.625rem;
                border-top: 1px solid #f3f4f6;
                font-size: 0.75rem;
            }

            /* Table */
            .lj-bc-table-card {
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 0.75rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
                overflow: hidden;
            }

            .lj-bc-table-scroll {
                overflow-x: auto;
                width: 100%;
            }

            .lj-bc-table {
                width: 100%;
                border-collapse: collapse;
                text-align: left;
                font-size: 0.8125rem;
            }

            .lj-bc-table th {
                background: #f9fafb;
                color: #4b5563;
                font-size: 0.6875rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                padding: 0.625rem 0.875rem;
                border-bottom: 1px solid #e5e7eb;
                white-space: nowrap;
            }

            .lj-bc-table td {
                padding: 0.625rem 0.875rem;
                border-bottom: 1px solid #f3f4f6;
                vertical-align: middle;
            }

            .lj-bc-table tr:hover td {
                background: #fbfdfb;
            }

            .lj-bc-table tr.selected td {
                background: #ecfdf5;
            }

            /* Stepper */
            .lj-bc-stepper {
                display: inline-flex;
                align-items: center;
                border: 1px solid #d1d5db;
                border-radius: 0.5rem;
                background: #ffffff;
                overflow: hidden;
            }

            .lj-bc-stepper-btn {
                background: none;
                border: none;
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
                font-weight: 700;
                color: #4b5563;
                cursor: pointer;
                line-height: 1;
            }

            .lj-bc-stepper-btn:hover {
                background: #f3f4f6;
                color: #111827;
            }

            .lj-bc-stepper-input {
                width: 2.75rem;
                text-align: center;
                font-size: 0.8125rem;
                font-weight: 700;
                border: none;
                outline: none;
                padding: 0.25rem 0;
                color: #111827;
                background: transparent;
            }

            /* Modal */
            .lj-bc-modal-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.45);
                backdrop-filter: blur(3px);
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                box-sizing: border-box;
            }

            .lj-bc-modal-card {
                background: #ffffff;
                border-radius: 1rem;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                width: 100%;
                max-width: 48rem;
                max-height: 90vh;
                overflow-y: auto;
                border: 1px solid #e5e7eb;
                box-sizing: border-box;
            }

            .lj-bc-modal-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 1.125rem 1.5rem;
                border-bottom: 1px solid #e5e7eb;
            }

            .lj-bc-modal-body {
                padding: 1.5rem;
                display: grid;
                grid-template-columns: 1.1fr 0.9fr;
                gap: 1.5rem;
            }

            @media (max-width: 640px) {
                .lj-bc-modal-body {
                    grid-template-columns: 1fr;
                }
            }

            .lj-bc-modal-footer {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 0.75rem;
                padding: 1rem 1.5rem;
                background: #f9fafb;
                border-top: 1px solid #e5e7eb;
            }

            /* Drawer */
            .lj-bc-drawer-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.45);
                backdrop-filter: blur(3px);
                z-index: 9999;
                display: flex;
                justify-content: flex-end;
            }

            .lj-bc-drawer-panel {
                background: #ffffff;
                width: 100%;
                max-width: 32rem;
                height: 100%;
                display: flex;
                flex-direction: column;
                box-shadow: -10px 0 25px -5px rgba(0, 0, 0, 0.1);
                border-left: 1px solid #e5e7eb;
                box-sizing: border-box;
            }

            .lj-bc-drawer-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 1.125rem 1.25rem;
                border-bottom: 1px solid #e5e7eb;
                background: #f9fafb;
            }

            .lj-bc-drawer-body {
                flex: 1;
                overflow-y: auto;
                padding: 1rem;
            }

            .lj-bc-drawer-footer {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 1rem 1.25rem;
                border-top: 1px solid #e5e7eb;
                background: #f9fafb;
            }

            /* Live Preview Physical Box */
            .lj-bc-preview-wrap {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
                background: #f3f4f6;
                border-radius: 0.75rem;
                border: 1px solid #e5e7eb;
            }

            .lj-bc-preview-box {
                background: #ffffff;
                color: #000000;
                border: 1px solid #d1d5db;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                box-sizing: border-box;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                padding: 0.625rem;
                user-select: none;
                text-align: center;
            }

            /* PRINT STYLES */
            @media screen {
                .print-only {
                    display: none !important;
                }
            }

            @media print {
                body * {
                    visibility: hidden !important;
                }

                .fi-sidebar,
                .fi-topbar,
                .fi-breadcrumbs,
                .no-print,
                header,
                nav,
                aside,
                footer {
                    display: none !important;
                }

                #printable-area,
                #printable-area * {
                    visibility: visible !important;
                }

                #printable-area {
                    position: absolute !important;
                    left: 0 !important;
                    top: 0 !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #fff !important;
                }

                @page {
                    margin: 0;
                }

                /* Thermal 50mm x 25mm */
                .print-thermal-50x25 {
                    width: 50mm;
                    height: 25mm;
                    page-break-after: always;
                    break-after: page;
                    box-sizing: border-box;
                    padding: 1.2mm 2.2mm;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background: #fff;
                    color: #000;
                    overflow: hidden;
                }

                /* Thermal 50mm x 30mm */
                .print-thermal-50x30 {
                    width: 50mm;
                    height: 30mm;
                    page-break-after: always;
                    break-after: page;
                    box-sizing: border-box;
                    padding: 2mm 2.5mm;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background: #fff;
                    color: #000;
                    overflow: hidden;
                }

                /* A4 Sheet 3x8 (24-Up) */
                .print-sheet-3x8 {
                    display: grid !important;
                    grid-template-columns: repeat(3, 65mm);
                    grid-auto-rows: 34mm;
                    grid-gap: 2.5mm;
                    padding: 8mm 6mm;
                    box-sizing: border-box;
                    background: #fff;
                }

                .print-sheet-3x8 .sheet-label-item {
                    width: 65mm;
                    height: 34mm;
                    border: 0.5px dashed #ccc;
                    box-sizing: border-box;
                    padding: 2mm 2.5mm;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    page-break-inside: avoid;
                    break-inside: avoid;
                    background: #fff;
                    color: #000;
                    overflow: hidden;
                }

                /* A4 Sheet 4x10 (40-Up) */
                .print-sheet-4x10 {
                    display: grid !important;
                    grid-template-columns: repeat(4, 48.5mm);
                    grid-auto-rows: 26.5mm;
                    grid-gap: 2mm;
                    padding: 6mm 4mm;
                    box-sizing: border-box;
                    background: #fff;
                }

                .print-sheet-4x10 .sheet-label-item {
                    width: 48.5mm;
                    height: 26.5mm;
                    border: 0.5px dashed #ccc;
                    box-sizing: border-box;
                    padding: 1.5mm 2mm;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    page-break-inside: avoid;
                    break-inside: avoid;
                    background: #fff;
                    color: #000;
                    overflow: hidden;
                }
            }
        </style>

        {{-- 1. MAIN CONSOLE HEADER --}}
        <div class="no-print lj-bc-card">
            <div class="lj-bc-header">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <h1 class="lj-bc-header-title">
                            Barcodes & Labels
                        </h1>
                        <span class="lj-bc-badge lj-bc-badge-gray">
                            V2 Retail Console
                        </span>
                    </div>
                    <p class="lj-bc-header-sub">
                        Create, manage and print product labels.
                    </p>
                </div>

                {{-- Header Actions --}}
                <div style="display: flex; align-items: center; gap: 0.625rem;">
                    <button type="button"
                        wire:click="$toggle('showConfigModal')"
                        class="lj-bc-btn lj-bc-btn-outline">
                        <x-heroicon-o-cog-6-tooth style="width: 1rem; height: 1rem; color: #6b7280;" />
                        <span>+ Generate Labels</span>
                    </button>

                    <button type="button"
                        wire:click="$toggle('showQueueDrawer')"
                        class="lj-bc-btn {{ count($printQueue) > 0 ? 'lj-bc-btn-emerald' : 'lj-bc-btn-outline' }}">
                        <x-heroicon-o-queue-list style="width: 1rem; height: 1rem;" />
                        <span>Print Queue</span>
                        <span style="display: inline-flex; align-items: center; justify-content: center; padding: 0.1rem 0.45rem; border-radius: 9999px; font-size: 0.6875rem; font-weight: 800; {{ count($printQueue) > 0 ? 'background: #ffffff; color: #065f46;' : 'background: #e5e7eb; color: #374151;' }}">
                            {{ $this->totalLabelsCount }}
                        </span>
                    </button>
                </div>
            </div>
        </div>

        {{-- 2. COMPACT TOOLBAR --}}
        <div class="no-print lj-bc-card" style="padding: 1rem 1.25rem;">
            <div class="lj-bc-toolbar">
                <div class="lj-bc-toolbar-row">
                    {{-- Search Box with Ctrl+K helper --}}
                    <div class="lj-bc-search-wrap">
                        <x-heroicon-o-magnifying-glass class="lj-bc-search-icon" />
                        <input type="text"
                            id="retail-barcode-search"
                            wire:model.live.debounce.300ms="searchQuery"
                            placeholder="Search products / SKU / barcode (Ctrl+K)..."
                            class="lj-bc-search-input">
                        <kbd class="lj-bc-kbd">Ctrl K</kbd>
                    </div>

                    {{-- Filters & Options --}}
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        {{-- Category Filter --}}
                        <select wire:model.live="selectedCategoryId" class="lj-bc-select">
                            <option value="">All Categories</option>
                            @foreach($this->categories as $catId => $catName)
                            <option value="{{ $catId }}">{{ $catName }}</option>
                            @endforeach
                        </select>

                        {{-- Missing Barcodes Only Filter --}}
                        <button type="button"
                            wire:click="$toggle('missingBarcodeOnly')"
                            class="lj-bc-btn {{ $missingBarcodeOnly ? 'lj-bc-badge-amber' : 'lj-bc-btn-outline' }}"
                            style="font-size: 0.75rem; padding: 0.5rem 0.75rem;">
                            <span>⚠ Missing Barcode</span>
                        </button>

                        {{-- Clear Filters --}}
                        @if(!empty($searchQuery) || $selectedCategoryId || $missingBarcodeOnly)
                        <button type="button"
                            wire:click="$set('searchQuery', ''); $set('selectedCategoryId', null); $set('missingBarcodeOnly', false);"
                            style="background: none; border: none; font-size: 0.75rem; font-weight: 600; color: #6b7280; cursor: pointer; padding: 0.5rem;">
                            Clear filters
                        </button>
                        @endif
                    </div>
                </div>

                {{-- Batch Selection Sub-bar --}}
                <div class="lj-bc-batch-bar">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <button type="button"
                            wire:click="toggleSelectAllOnPage"
                            style="background: none; border: none; font-weight: 700; color: #065f46; cursor: pointer; padding: 0;">
                            Select all on page
                        </button>
                        <span style="color: #d1d5db;">|</span>
                        <button type="button"
                            wire:click="clearSelection"
                            style="background: none; border: none; font-weight: 600; color: #6b7280; cursor: pointer; padding: 0;">
                            Clear selection
                        </button>
                        @if(count($selectedRows) > 0)
                        <span class="lj-bc-badge lj-bc-badge-emerald">
                            {{ count($selectedRows) }} selected
                        </span>
                        @endif
                    </div>

                    @if(count($selectedRows) > 0)
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <button type="button"
                            wire:click="bulkGenerateMissingBarcodesForSelection"
                            class="lj-bc-btn lj-bc-badge-amber lj-bc-btn-sm" style="border: 1px solid #fde68a;">
                            Generate missing barcodes
                        </button>

                        <button type="button"
                            wire:click="addSelectedToQueue"
                            class="lj-bc-btn lj-bc-btn-emerald lj-bc-btn-sm">
                            + Add {{ count($selectedRows) }} to Queue
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- 3. PRODUCT / VARIANT SELECTION TABLE (Variants as first-class items) --}}
        <div class="no-print lj-bc-table-card">
            <div class="lj-bc-table-scroll">
                <table class="lj-bc-table">
                    <thead>
                        <tr>
                            <th style="width: 2.5rem; text-align: center;">
                                <span class="sr-only">Select</span>
                            </th>
                            <th style="min-width: 180px;">Product</th>
                            <th style="min-width: 120px;">Variant</th>
                            <th style="font-family: ui-monospace, monospace;">SKU</th>
                            <th>Barcode</th>
                            <th>Price</th>
                            <th style="text-align: center;">Stock</th>
                            <th style="text-align: center; width: 8rem;">Labels</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $hasRenderedRows = false; @endphp
                        @foreach($this->products as $product)
                        @if($product->variants->isNotEmpty())
                        {{-- Variable Product: Each Variant is a discrete first-class row --}}
                        @foreach($product->variants as $variant)
                        @php
                        $hasRenderedRows = true;
                        $itemKey = 'v_' . $variant->id;
                        $isSelected = in_array($itemKey, $selectedRows);
                        $variantLabel = trim(($variant->color ?? '') . ($variant->color && $variant->size ? ' / ' : '') . ($variant->size ?? ''));
                        if (empty($variantLabel)) { $variantLabel = 'Standard'; }
                        $copies = $rowCopies[$itemKey] ?? $defaultCopies;
                        $sku = $variant->sku ?: ($product->sku ?: 'LJ-VAR-00000');
                        $barcode = $variant->barcode ?: '';
                        $isValidBarcode = !empty($barcode) && app(\App\Services\ProductSkuService::class)->validateEan13($barcode);
                        $price = (float) ($variant->price ?: $product->price ?: 0);
                        $stock = (int) $variant->stock_quantity;
                        @endphp
                        <tr class="{{ $isSelected ? 'selected' : '' }}">
                            {{-- Checkbox --}}
                            <td style="text-align: center;">
                                <input type="checkbox"
                                    wire:model.live="selectedRows"
                                    value="{{ $itemKey }}"
                                    style="border-radius: 0.25rem; border: 1px solid #d1d5db; width: 1rem; height: 1rem; cursor: pointer;">
                            </td>

                            {{-- Product --}}
                            <td>
                                <div style="font-weight: 700; color: #111827; line-height: 1.2;">
                                    {{ $product->name }}
                                </div>
                                @if($product->brand)
                                <div style="font-size: 0.6875rem; color: #6b7280; margin-top: 0.125rem;">
                                    {{ $product->brand }}
                                </div>
                                @endif
                            </td>

                            {{-- Variant --}}
                            <td>
                                <span class="lj-bc-badge lj-bc-badge-gray">
                                    {{ $variantLabel }}
                                </span>
                            </td>

                            {{-- SKU --}}
                            <td style="font-family: ui-monospace, monospace; color: #374151; font-size: 0.75rem;">
                                {{ $sku }}
                            </td>

                            {{-- Barcode --}}
                            <td style="font-family: ui-monospace, monospace; font-size: 0.75rem;">
                                @if($isValidBarcode)
                                <div style="display: flex; align-items: center; gap: 0.25rem; color: #111827;">
                                    <x-heroicon-m-qr-code style="width: 0.875rem; height: 0.875rem; color: #9ca3af;" />
                                    <span>{{ $barcode }}</span>
                                </div>
                                @else
                                <div style="display: flex; align-items: center; gap: 0.375rem;">
                                    <span class="lj-bc-badge lj-bc-badge-amber">
                                        ⚠ Missing barcode
                                    </span>
                                    <button type="button"
                                        wire:click="generateBarcodeForMissingItem('v', {{ $variant->id }})"
                                        style="background: none; border: none; font-size: 0.6875rem; font-weight: 700; color: #065f46; text-decoration: underline; cursor: pointer; padding: 0;">
                                        Generate EAN-13
                                    </button>
                                </div>
                                @endif
                            </td>

                            {{-- Price --}}
                            <td style="font-weight: 800; color: #111827;">
                                Rs. {{ number_format($price) }}
                            </td>

                            {{-- Stock --}}
                            <td style="text-align: center;">
                                <span class="lj-bc-badge {{ $stock > 5 ? 'lj-bc-badge-emerald' : ($stock > 0 ? 'lj-bc-badge-amber' : 'lj-bc-badge-red') }}">
                                    {{ $stock }}
                                </span>
                            </td>

                            {{-- Labels Stepper (- Qty +) --}}
                            <td style="text-align: center;">
                                <div class="lj-bc-stepper">
                                    <button type="button"
                                        wire:click="decrementRowCopies('{{ $itemKey }}')"
                                        class="lj-bc-stepper-btn">
                                        −
                                    </button>
                                    <input type="number" min="1" max="999"
                                        value="{{ $copies }}"
                                        wire:change="setRowCopies('{{ $itemKey }}', $event.target.value)"
                                        class="lj-bc-stepper-input">
                                    <button type="button"
                                        wire:click="incrementRowCopies('{{ $itemKey }}')"
                                        class="lj-bc-stepper-btn">
                                        +
                                    </button>
                                </div>
                            </td>

                            {{-- Action --}}
                            <td style="text-align: right;">
                                <button type="button"
                                    wire:click="addItemToQueue('v', {{ $variant->id }})"
                                    class="lj-bc-btn lj-bc-btn-outline lj-bc-btn-sm"
                                    style="color: #065f46; border-color: #a7f3d0; background: #ecfdf5;">
                                    + Queue
                                </button>
                            </td>
                        </tr>
                        @endforeach
                        @else
                        {{-- Simple Product (No variants) --}}
                        @php
                        $hasRenderedRows = true;
                        $itemKey = 'p_' . $product->id;
                        $isSelected = in_array($itemKey, $selectedRows);
                        $copies = $rowCopies[$itemKey] ?? $defaultCopies;
                        $sku = $product->sku ?: 'LJ-PRD-00000';
                        $barcode = $product->barcode ?: '';
                        $isValidBarcode = !empty($barcode) && app(\App\Services\ProductSkuService::class)->validateEan13($barcode);
                        $price = (float) ($product->price ?: 0);
                        $stock = (int) $product->quantity;
                        @endphp
                        <tr class="{{ $isSelected ? 'selected' : '' }}">
                            {{-- Checkbox --}}
                            <td style="text-align: center;">
                                <input type="checkbox"
                                    wire:model.live="selectedRows"
                                    value="{{ $itemKey }}"
                                    style="border-radius: 0.25rem; border: 1px solid #d1d5db; width: 1rem; height: 1rem; cursor: pointer;">
                            </td>

                            {{-- Product --}}
                            <td>
                                <div style="font-weight: 700; color: #111827; line-height: 1.2;">
                                    {{ $product->name }}
                                </div>
                                @if($product->brand)
                                <div style="font-size: 0.6875rem; color: #6b7280; margin-top: 0.125rem;">
                                    {{ $product->brand }}
                                </div>
                                @endif
                            </td>

                            {{-- Variant --}}
                            <td>
                                <span class="lj-bc-badge lj-bc-badge-gray">
                                    Standard
                                </span>
                            </td>

                            {{-- SKU --}}
                            <td style="font-family: ui-monospace, monospace; color: #374151; font-size: 0.75rem;">
                                {{ $sku }}
                            </td>

                            {{-- Barcode --}}
                            <td style="font-family: ui-monospace, monospace; font-size: 0.75rem;">
                                @if($isValidBarcode)
                                <div style="display: flex; align-items: center; gap: 0.25rem; color: #111827;">
                                    <x-heroicon-m-qr-code style="width: 0.875rem; height: 0.875rem; color: #9ca3af;" />
                                    <span>{{ $barcode }}</span>
                                </div>
                                @else
                                <div style="display: flex; align-items: center; gap: 0.375rem;">
                                    <span class="lj-bc-badge lj-bc-badge-amber">
                                        ⚠ Missing barcode
                                    </span>
                                    <button type="button"
                                        wire:click="generateBarcodeForMissingItem('p', {{ $product->id }})"
                                        style="background: none; border: none; font-size: 0.6875rem; font-weight: 700; color: #065f46; text-decoration: underline; cursor: pointer; padding: 0;">
                                        Generate EAN-13
                                    </button>
                                </div>
                                @endif
                            </td>

                            {{-- Price --}}
                            <td style="font-weight: 800; color: #111827;">
                                Rs. {{ number_format($price) }}
                            </td>

                            {{-- Stock --}}
                            <td style="text-align: center;">
                                <span class="lj-bc-badge {{ $stock > 5 ? 'lj-bc-badge-emerald' : ($stock > 0 ? 'lj-bc-badge-amber' : 'lj-bc-badge-red') }}">
                                    {{ $stock }}
                                </span>
                            </td>

                            {{-- Labels Stepper (- Qty +) --}}
                            <td style="text-align: center;">
                                <div class="lj-bc-stepper">
                                    <button type="button"
                                        wire:click="decrementRowCopies('{{ $itemKey }}')"
                                        class="lj-bc-stepper-btn">
                                        −
                                    </button>
                                    <input type="number" min="1" max="999"
                                        value="{{ $copies }}"
                                        wire:change="setRowCopies('{{ $itemKey }}', $event.target.value)"
                                        class="lj-bc-stepper-input">
                                    <button type="button"
                                        wire:click="incrementRowCopies('{{ $itemKey }}')"
                                        class="lj-bc-stepper-btn">
                                        +
                                    </button>
                                </div>
                            </td>

                            {{-- Action --}}
                            <td style="text-align: right;">
                                <button type="button"
                                    wire:click="addItemToQueue('p', {{ $product->id }})"
                                    class="lj-bc-btn lj-bc-btn-outline lj-bc-btn-sm"
                                    style="color: #065f46; border-color: #a7f3d0; background: #ecfdf5;">
                                    + Queue
                                </button>
                            </td>
                        </tr>
                        @endif
                        @endforeach

                        @if(!$hasRenderedRows)
                        <tr>
                            <td colspan="9" style="padding: 3rem; text-align: center; color: #6b7280;">
                                <x-heroicon-o-qr-code style="width: 2.5rem; height: 2.5rem; color: #d1d5db; margin: 0 auto 0.5rem auto;" />
                                <p style="font-weight: 700; color: #374151; font-size: 0.875rem; margin: 0;">No products or variants found</p>
                                <p style="font-size: 0.75rem; color: #9ca3af; margin: 0.25rem 0 0 0;">Try adjusting your search query or clear active filters.</p>
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            {{-- Table Pagination --}}
            @if($this->products->hasPages())
            <div style="padding: 0.75rem 1rem; border-top: 1px solid #e5e7eb; background: #f9fafb;">
                <x-filament::pagination :paginator="$this->products" />
            </div>
            @endif
        </div>

        {{-- 4. LABEL CONFIGURATION & LIVE PREVIEW MODAL --}}
        @if($showConfigModal)
        <div class="no-print lj-bc-modal-backdrop">
            <div class="lj-bc-modal-card">

                {{-- Modal Header --}}
                <div class="lj-bc-modal-header">
                    <div>
                        <h3 style="font-size: 1.125rem; font-weight: 800; color: #111827; margin: 0;">
                            Label Configuration
                        </h3>
                        <p style="font-size: 0.75rem; color: #6b7280; margin: 0.125rem 0 0 0;">
                            Customize printer format, layout and displayed details
                        </p>
                    </div>
                    <button type="button"
                        wire:click="$set('showConfigModal', false)"
                        style="background: none; border: none; padding: 0.375rem; color: #9ca3af; cursor: pointer;">
                        <x-heroicon-o-x-mark style="width: 1.25rem; height: 1.25rem;" />
                    </button>
                </div>

                {{-- Modal Body: 2 Columns (Config on Left, Live Preview on Right) --}}
                <div class="lj-bc-modal-body">

                    {{-- Left Column: Controls --}}
                    <div style="display: flex; flex-direction: column; gap: 1.25rem;">

                        {{-- Format Selector --}}
                        <div>
                            <label style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; display: block; margin-bottom: 0.5rem;">
                                Label Format
                            </label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                {{-- Thermal Roll --}}
                                <button type="button"
                                    wire:click="$set('labelFormat', 'thermal')"
                                    style="padding: 0.75rem; text-align: left; border-radius: 0.625rem; border: 1px solid {{ $labelFormat === 'thermal' ? '#0a2e23' : '#e5e7eb' }}; background: {{ $labelFormat === 'thermal' ? '#ecfdf5' : '#ffffff' }}; cursor: pointer;">
                                    <div style="font-size: 0.75rem; font-weight: 800; color: {{ $labelFormat === 'thermal' ? '#065f46' : '#111827' }};">🏷️ Thermal Roll</div>
                                    <div style="font-size: 0.6875rem; color: #6b7280; margin-top: 0.125rem;">High-speed barcode printer</div>
                                </button>

                                {{-- A4 Sheet --}}
                                <button type="button"
                                    wire:click="$set('labelFormat', 'sheet')"
                                    style="padding: 0.75rem; text-align: left; border-radius: 0.625rem; border: 1px solid {{ $labelFormat === 'sheet' ? '#0a2e23' : '#e5e7eb' }}; background: {{ $labelFormat === 'sheet' ? '#ecfdf5' : '#ffffff' }}; cursor: pointer;">
                                    <div style="font-size: 0.75rem; font-weight: 800; color: {{ $labelFormat === 'sheet' ? '#065f46' : '#111827' }};">📄 A4 Sheet</div>
                                    <div style="font-size: 0.6875rem; color: #6b7280; margin-top: 0.125rem;">Standard laser/inkjet sheet</div>
                                </button>
                            </div>

                            {{-- Sub-format Options --}}
                            @if($labelFormat === 'thermal')
                            <div style="margin-top: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                <button type="button"
                                    wire:click="$set('thermalSize', '50x25')"
                                    class="lj-bc-btn lj-bc-btn-sm {{ $thermalSize === '50x25' ? 'lj-bc-btn-emerald' : 'lj-bc-btn-outline' }}">
                                    50 × 25 mm
                                </button>
                                <button type="button"
                                    wire:click="$set('thermalSize', '50x30')"
                                    class="lj-bc-btn lj-bc-btn-sm {{ $thermalSize === '50x30' ? 'lj-bc-btn-emerald' : 'lj-bc-btn-outline' }}">
                                    50 × 30 mm
                                </button>
                            </div>
                            @else
                            <div style="margin-top: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                <button type="button"
                                    wire:click="$set('sheetLayout', '3x8')"
                                    class="lj-bc-btn lj-bc-btn-sm {{ $sheetLayout === '3x8' ? 'lj-bc-btn-emerald' : 'lj-bc-btn-outline' }}">
                                    3 × 8 (24-up)
                                </button>
                                <button type="button"
                                    wire:click="$set('sheetLayout', '4x10')"
                                    class="lj-bc-btn lj-bc-btn-sm {{ $sheetLayout === '4x10' ? 'lj-bc-btn-emerald' : 'lj-bc-btn-outline' }}">
                                    4 × 10 (40-up)
                                </button>
                            </div>
                            @endif
                        </div>

                        {{-- Content Toggles --}}
                        <div>
                            <label style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; display: block; margin-bottom: 0.5rem;">
                                Label Contents
                            </label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.75rem;">
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" wire:model.live="includeProductName">
                                    <span style="font-weight: 600;">Product name</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" wire:model.live="includeVariant">
                                    <span style="font-weight: 600;">Variant</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" wire:model.live="includeSku">
                                    <span style="font-weight: 600;">SKU</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" wire:model.live="includeBarcode">
                                    <span style="font-weight: 600;">Barcode</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" wire:model.live="includePrice">
                                    <span style="font-weight: 600;">Price (Rs.)</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" wire:model.live="includeBrand">
                                    <span style="font-weight: 600;">Brand (LAIJAU)</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" wire:model.live="includeInternalRef">
                                    <span style="font-weight: 600;">Internal reference</span>
                                </label>
                            </div>
                        </div>

                        {{-- Barcode Type --}}
                        <div>
                            <label style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; display: block; margin-bottom: 0.375rem;">
                                Barcode Type
                            </label>
                            <span class="lj-bc-badge lj-bc-badge-gray" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                EAN-13 — default
                            </span>
                        </div>
                    </div>

                    {{-- Right Column: Dimensionally Representative Live Preview --}}
                    <div class="lj-bc-preview-wrap">
                        <span style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 0.75rem;">
                            Live Preview ({{ $labelFormat === 'thermal' ? ($thermalSize === '50x25' ? '50 × 25 mm' : '50 × 30 mm') : ($sheetLayout === '3x8' ? '3 × 8 Sheet' : '4 × 10 Sheet') }})
                        </span>

                        {{-- Physical Dimensional Box --}}
                        @php $preview = $this->livePreviewItem; @endphp
                        <div class="lj-bc-preview-box" style="{{ $labelFormat === 'thermal' ? ($thermalSize === '50x25' ? 'width: 240px; height: 120px;' : 'width: 240px; height: 144px;') : 'width: 250px; height: 130px;' }}">
                            <div>
                                @if($includeBrand)
                                <div style="font-size: 7px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; color: #065f46; line-height: 1; margin-bottom: 2px;">
                                    {{ $preview['brand'] ?: 'LAIJAU' }}
                                </div>
                                @endif
                                @if($includeProductName)
                                <div style="font-size: 9px; font-weight: 700; color: #000; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ $preview['product_name'] }}
                                </div>
                                @endif
                                @if($includeVariant)
                                <div style="font-size: 8px; color: #4b5563; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ $preview['variant_label'] }}
                                </div>
                                @endif
                            </div>

                            {{-- Barcode Section --}}
                            @if($includeBarcode)
                            <div style="margin: 2px 0; padding: 0 4px;">
                                {!! $preview['svg'] !!}
                                <div style="font-family: ui-monospace, monospace; font-size: 8px; letter-spacing: 1px; color: #000; margin-top: 1px;">
                                    {{ $preview['barcode'] }}
                                </div>
                            </div>
                            @endif

                            {{-- Bottom Row --}}
                            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 8px; padding-top: 3px; border-top: 0.5px solid #d1d5db;">
                                @if($includeSku)
                                <span style="font-family: ui-monospace, monospace; font-size: 7px; color: #374151; max-width: 55%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ $preview['sku'] }}
                                </span>
                                @endif
                                @if($includePrice)
                                <span style="font-weight: 900; font-size: 8px; color: #000; margin-left: auto;">
                                    Rs. {{ number_format($preview['price']) }}
                                </span>
                                @endif
                            </div>
                        </div>
                        <span style="font-size: 0.625rem; color: #9ca3af; margin-top: 0.75rem;">Representative vector preview</span>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="lj-bc-modal-footer">
                    <button type="button"
                        wire:click="$set('showConfigModal', false)"
                        class="lj-bc-btn lj-bc-btn-emerald">
                        Done
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- 5. PRINT Queue SLIDE-OVER DRAWER --}}
        @if($showQueueDrawer)
        <div class="no-print lj-bc-drawer-backdrop">
            <div class="lj-bc-drawer-panel">

                {{-- Drawer Header --}}
                <div class="lj-bc-drawer-header">
                    <div>
                        <h2 style="font-size: 1rem; font-weight: 800; color: #111827; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                            <x-heroicon-o-queue-list style="width: 1.25rem; height: 1.25rem; color: #065f46;" />
                            Print Queue — {{ $this->totalLabelsCount }} labels
                        </h2>
                        <p style="font-size: 0.75rem; color: #6b7280; margin: 0.125rem 0 0 0;">{{ count($printQueue) }} distinct items Queued</p>
                    </div>
                    <button type="button"
                        wire:click="$set('showQueueDrawer', false)"
                        style="background: none; border: none; padding: 0.375rem; color: #9ca3af; cursor: pointer;">
                        <x-heroicon-o-x-mark style="width: 1.25rem; height: 1.25rem;" />
                    </button>
                </div>

                {{-- Drawer Body / Queue Items --}}
                <div class="lj-bc-drawer-body">
                    @if(empty($printQueue))
                    <div style="padding: 4rem 1rem; text-align: center;">
                        <x-heroicon-o-qr-code style="width: 3rem; height: 3rem; color: #d1d5db; margin: 0 auto 0.5rem auto;" />
                        <p style="font-size: 0.875rem; font-weight: 700; color: #374151; margin: 0;">Print Queue is Empty</p>
                        <p style="font-size: 0.75rem; color: #9ca3af; margin: 0.25rem 0 0 0;">Select items or click "+ Queue" from the catalog table.</p>
                    </div>
                    @else
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.75rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 0.6875rem; text-transform: uppercase;">
                                <th style="padding: 0.5rem;">Product</th>
                                <th style="padding: 0.5rem;">Variant</th>
                                <th style="padding: 0.5rem; text-align: center;">Qty</th>
                                <th style="padding: 0.5rem;">Status</th>
                                <th style="padding: 0.5rem; text-align: right;"><span class="sr-only">Delete</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($printQueue as $idx => $item)
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 0.625rem 0.5rem; font-weight: 700; color: #111827;">
                                    {{ $item['product_name'] }}
                                    <div style="font-family: ui-monospace, monospace; font-size: 0.6875rem; color: #9ca3af;">{{ $item['sku'] }}</div>
                                </td>
                                <td style="padding: 0.625rem 0.5rem;">
                                    <span class="lj-bc-badge lj-bc-badge-gray">
                                        {{ $item['variant_label'] }}
                                    </span>
                                </td>
                                <td style="padding: 0.625rem 0.5rem; text-align: center;">
                                    <input type="number" min="1" max="999"
                                        value="{{ $item['copies'] }}"
                                        wire:change="updateCopies({{ $idx }}, $event.target.value)"
                                        style="width: 3rem; text-align: center; font-weight: 700; font-size: 0.75rem; padding: 0.25rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                                </td>
                                <td style="padding: 0.625rem 0.5rem;">
                                    @if($item['status'] === 'ready')
                                    <span class="lj-bc-badge lj-bc-badge-emerald">Ready</span>
                                    @else
                                    <span class="lj-bc-badge lj-bc-badge-amber">Missing</span>
                                    @endif
                                </td>
                                <td style="padding: 0.625rem 0.5rem; text-align: right;">
                                    <button type="button"
                                        wire:click="removeQueueItem({{ $idx }})"
                                        style="background: none; border: none; color: #9ca3af; cursor: pointer; padding: 0.25rem;">
                                        <x-heroicon-o-trash style="width: 1rem; height: 1rem;" />
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>

                {{-- Drawer Footer Actions --}}
                <div class="lj-bc-drawer-footer">
                    <button type="button"
                        wire:click="clearQueue"
                        style="background: none; border: none; font-size: 0.75rem; font-weight: 700; color: #dc2626; cursor: pointer;">
                        Clear Queue
                    </button>

                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <button type="button"
                            wire:click="$set('showQueueDrawer', false)"
                            class="lj-bc-btn lj-bc-btn-secondary">
                            Close
                        </button>

                        <button type="button"
                            onclick="window.print()"
                            {{ empty($printQueue) ? 'disabled' : '' }}
                            class="lj-bc-btn lj-bc-btn-emerald" style="{{ empty($printQueue) ? 'opacity: 0.5; pointer-events: none;' : '' }}">
                            <x-heroicon-o-printer style="width: 1rem; height: 1rem;" />
                            Print {{ $this->totalLabelsCount }} Labels
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- 6. PRINTABLE AREA (Hidden on screen, calibrated for thermal rolls and A4 sheets) --}}
        <div id="printable-area" class="print-only">
            @if($labelFormat === 'thermal')
            {{-- Thermal Roll Format (50x25 or 50x30) --}}
            @foreach($printQueue as $item)
            @for($c = 0; $c < $item['copies']; $c++)
                <div class="{{ $thermalSize === '50x25' ? 'print-thermal-50x25' : 'print-thermal-50x30' }}">
                {{-- Top brand & titles --}}
                <div style="text-align: center;">
                    @if($includeBrand)
                    <div style="font-size: 6.5pt; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; line-height: 1; color: #000;">
                        {{ $item['brand'] ?: 'LAIJAU' }}
                    </div>
                    @endif
                    @if($includeProductName)
                    <div style="font-size: 7.5pt; font-weight: bold; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #000; margin-top: 1px;">
                        {{ $item['product_name'] }}
                    </div>
                    @endif
                    @if($includeVariant)
                    <div style="font-size: 6.5pt; color: #333; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $item['variant_label'] }}
                    </div>
                    @endif
                </div>

                {{-- SVG Barcode --}}
                @if($includeBarcode)
                <div style="text-align: center; margin: 0.5mm 0;">
                    {!! $item['svg'] !!}
                    <div style="font-family: monospace; font-size: 6.5pt; letter-spacing: 1px; color: #000; margin-top: 0.5px;">
                        {{ $item['barcode'] }}
                    </div>
                </div>
                @endif

                {{-- SKU & Price Footer --}}
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 0.5px solid #000; padding-top: 0.8mm; font-size: 6.5pt;">
                    @if($includeSku)
                    <span style="font-family: monospace; font-size: 6pt; color: #000;">
                        {{ $item['sku'] }}
                    </span>
                    @endif
                    @if($includePrice)
                    <span style="font-weight: 900; font-size: 7.5pt; color: #000; margin-left: auto;">
                        Rs. {{ number_format($item['price']) }}
                    </span>
                    @endif
                </div>
        </div>
        @endfor
        @endforeach
        @else
        {{-- A4 Sheet Format (3x8 or 4x10) --}}
        <div class="{{ $sheetLayout === '3x8' ? 'print-sheet-3x8' : 'print-sheet-4x10' }}">
            @foreach($printQueue as $item)
            @for($c = 0; $c < $item['copies']; $c++)
                <div class="sheet-label-item">
                <div style="text-align: center;">
                    @if($includeBrand)
                    <div style="font-size: 6.5pt; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; color: #000; line-height: 1;">
                        {{ $item['brand'] ?: 'LAIJAU' }}
                    </div>
                    @endif
                    @if($includeProductName)
                    <div style="font-size: 7.5pt; font-weight: bold; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #000; margin-top: 1px;">
                        {{ $item['product_name'] }}
                    </div>
                    @endif
                    @if($includeVariant)
                    <div style="font-size: 6.5pt; color: #333; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $item['variant_label'] }}
                    </div>
                    @endif
                </div>

                @if($includeBarcode)
                <div style="text-align: center; margin: 1mm 0;">
                    {!! $item['svg'] !!}
                    <div style="font-family: monospace; font-size: 6.5pt; letter-spacing: 1px; color: #000; margin-top: 1px;">
                        {{ $item['barcode'] }}
                    </div>
                </div>
                @endif

                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 0.5px solid #000; padding-top: 1mm; font-size: 6.5pt;">
                    @if($includeSku)
                    <span style="font-family: monospace; font-size: 6pt; color: #000;">
                        {{ $item['sku'] }}
                    </span>
                    @endif
                    @if($includePrice)
                    <span style="font-weight: 900; font-size: 7.5pt; color: #000; margin-left: auto;">
                        Rs. {{ number_format($item['price']) }}
                    </span>
                    @endif
                </div>
        </div>
        @endfor
        @endforeach
    </div>
    @endif
    </div>

    </div>
</x-filament-panels::page>