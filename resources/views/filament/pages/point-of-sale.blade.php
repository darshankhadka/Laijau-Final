<x-filament-panels::page class="w-full max-w-full !p-0">
    @php
    $totals = $this->calculateTotals();
    $searchResults = $this->searchResults;
    $categories = $this->categories;
    $customersList = $this->customersList;
    $todaysSales = $this->todaysSales;
    $currSymbol = 'Rs. ';
    $staffName = $this->staffName;
    $heldCount = count($heldSales);
    @endphp

    <style>
        /* HIDE DEFAULT FILAMENT PAGE HEADER */
        .fi-header,
        .fi-page-header,
        .fi-header-heading,
        .fi-breadcrumbs {
            display: none !important;
        }

        :root {
            --na-emerald: #0A2E23;
            --na-emerald-light: #124032;
            --na-gold: #C5A059;
            --na-gold-light: #dfc288;
            --na-ivory: #FDFBF7;
            --na-border: #E5E7EB;
        }

        .na-pos-root {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica NNpe", Arial, sans-serif;
            background: #F8F7F4;
            height: calc(100vh - 4.25rem);
            max-height: calc(100vh - 4.25rem);
            color: #1F2937;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            margin: -1.5rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(229, 231, 235, 0.9);
        }

        .dark .na-pos-root {
            background: #0B0F17;
            color: #F3F4F6;
            border-color: rgba(31, 41, 55, 0.9);
        }

        /* TOP COMPACT HEADER */
        .na-pos-header {
            background: #ffffff;
            border-bottom: 1px solid rgba(229, 231, 235, 0.8);
            padding: 0.4rem 1rem;
            height: 3rem;
            max-height: 3rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-shrink: 0;
            box-sizing: border-box;
        }

        .dark .na-pos-header {
            background: #111827;
            border-color: rgba(31, 41, 55, 0.8);
        }

        .na-pos-brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .na-pos-brand-text {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #0A2E23;
        }

        .dark .na-pos-brand-text {
            color: #C5A059;
        }

        /* SEARCH BAR */
        .na-pos-search-wrap {
            flex: 1;
            max-width: 460px;
            position: relative;
        }

        .na-pos-search-input {
            width: 100%;
            height: 2.125rem;
            padding: 0.25rem 2rem 0.25rem 2.125rem;
            font-size: 0.75rem;
            border-radius: 9999px;
            border: 1px solid #D1D5DB;
            background: #F9FAFB;
            color: #111827;
            outline: none;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .dark .na-pos-search-input {
            background: #1F2937;
            border-color: #374151;
            color: #F9FAFB;
        }

        .na-pos-search-input:focus {
            background: #ffffff;
            border-color: #0A2E23;
            box-shadow: 0 0 0 2px rgba(10, 46, 35, 0.12);
        }

        .dark .na-pos-search-input:focus {
            background: #111827;
            border-color: #C5A059;
            box-shadow: 0 0 0 2px rgba(197, 160, 89, 0.2);
        }

        /* 3-ZONE MAIN LAYOUT - PROPORTIONATE & SPACIOUS */
        .na-pos-body {
            display: grid;
            grid-template-columns: 210px 1fr 390px;
            gap: 0;
            flex: 1;
            height: calc(100vh - 7.25rem);
            max-height: calc(100vh - 7.25rem);
            overflow: hidden;
        }

        @media (max-width: 1280px) {
            .na-pos-body {
                grid-template-columns: 190px 1fr 370px;
            }
        }

        @media (max-width: 1023px) {
            .na-pos-root {
                height: calc(100dvh - 3.75rem) !important;
                max-height: calc(100dvh - 3.75rem) !important;
                margin: -1rem !important;
                overflow: hidden !important;
            }

            .na-pos-body {
                grid-template-columns: 1fr !important;
                height: auto !important;
                overflow-y: auto !important;
                padding-bottom: 5.5rem !important;
            }

            .na-pos-zone-categories {
                display: none !important;
            }
        }

        /* ZONE 1: CATEGORIES (LEFT - BIGGER & SPACIOUS) */
        .na-pos-zone-categories {
            background: #ffffff;
            border-right: 1px solid rgba(229, 231, 235, 0.8);
            padding: 1rem 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            height: 100%;
            max-height: 100%;
            overflow-y: auto;
            box-sizing: border-box;
            scrollbar-width: thin;
        }

        .dark .na-pos-zone-categories {
            background: #111827;
            border-color: rgba(31, 41, 55, 0.8);
        }

        .na-pos-cat-btn {
            width: 100%;
            padding: 0.65rem 0.875rem;
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            text-align: left;
            border: none;
            background: transparent;
            color: #4B5563;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.15s ease;
        }

        .dark .na-pos-cat-btn {
            color: #9CA3AF;
        }

        .na-pos-cat-btn:hover {
            background: #F3F4F6;
            color: #111827;
        }

        .dark .na-pos-cat-btn:hover {
            background: #1F2937;
            color: #F9FAFB;
        }

        .na-pos-cat-btn-active {
            background: #0A2E23 !important;
            color: #ffffff !important;
            box-shadow: 0 1px 4px rgba(10, 46, 35, 0.2);
        }

        .dark .na-pos-cat-btn-active {
            background: #C5A059 !important;
            color: #111827 !important;
            box-shadow: 0 1px 4px rgba(197, 160, 89, 0.25);
        }

        /* ZONE 2: PRODUCTS (MIDDLE - 4 ITEMS PER ROW) */
        .na-pos-zone-products {
            padding: 0.875rem 1rem;
            height: 100%;
            max-height: 100%;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            box-sizing: border-box;
            scrollbar-width: thin;
        }

        .na-pos-prod-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
        }

        @media (max-width: 1360px) {
            .na-pos-prod-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            }
        }

        .na-pos-card {
            background: #ffffff;
            border: 1px solid rgba(229, 231, 235, 0.9);
            border-radius: 0.625rem;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.15s ease;
            cursor: pointer;
            position: relative;
        }

        .dark .na-pos-card {
            background: #111827;
            border-color: rgba(31, 41, 55, 0.9);
        }

        .na-pos-card:hover {
            border-color: #C5A059;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .na-pos-card-img-wrap {
            width: 100%;
            height: 120px;
            background: #F3F4F6;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dark .na-pos-card-img-wrap {
            background: #1F2937;
        }

        .na-pos-card-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* ZONE 3: CURRENT SALE (RIGHT) */
        .na-pos-zone-sale {
            background: #ffffff;
            border-left: 1px solid rgba(229, 231, 235, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            max-height: 100%;
            overflow: hidden;
            box-sizing: border-box;
        }

        .dark .na-pos-zone-sale {
            background: #111827;
            border-color: rgba(31, 41, 55, 0.8);
        }

        .na-pos-ticket-header {
            padding: 0.625rem 0.875rem;
            border-bottom: 1px solid rgba(229, 231, 235, 0.8);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .dark .na-pos-ticket-header {
            border-color: rgba(31, 41, 55, 0.8);
        }

        .na-pos-ticket-items {
            flex: 1;
            overflow-y: auto;
            padding: 0.625rem 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            scrollbar-width: thin;
            min-height: 0;
        }

        .na-pos-ticket-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.45rem 0.5rem;
            border-radius: 0.375rem;
            background: #F9FAFB;
            border: 1px solid #F3F4F6;
        }

        .dark .na-pos-ticket-row {
            background: #1F2937;
            border-color: #374151;
        }

        .na-pos-stepper-btn {
            width: 1.25rem;
            height: 1.25rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.25rem;
            background: #E5E7EB;
            color: #1F2937;
            font-size: 0.75rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .dark .na-pos-stepper-btn {
            background: #374151;
            color: #F9FAFB;
        }

        .na-pos-stepper-btn:hover {
            background: #0A2E23;
            color: #ffffff;
        }

        .dark .na-pos-stepper-btn:hover {
            background: #C5A059;
            color: #111827;
        }

        /* PAY BUTTON */
        .na-pos-pay-btn {
            width: 100%;
            background: #0A2E23;
            color: #ffffff;
            font-family: inherit;
            font-size: 0.9375rem;
            font-weight: 700;
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            transition: all 0.15s ease;
            box-shadow: 0 2px 8px rgba(10, 46, 35, 0.25);
        }

        .na-pos-pay-btn:hover {
            background: #124032;
            box-shadow: 0 4px 12px rgba(10, 46, 35, 0.35);
            transform: translateY(-1px);
        }

        .na-pos-pay-btn:active {
            transform: translateY(0);
        }

        /* MODAL BACKDROP & DIALOG */
        .na-pos-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }

        .na-pos-modal-dialog {
            background: #ffffff;
            border-radius: 1rem;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            border: 1px solid rgba(229, 231, 235, 0.8);
            animation: naPosFadeIn 0.15s ease-out;
        }

        .dark .na-pos-modal-dialog {
            background: #111827;
            border-color: rgba(31, 41, 55, 0.8);
        }

        @keyframes naPosFadeIn {
            from {
                opacity: 0;
                transform: scale(0.96);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>

    <div class="na-pos-root">

        {{-- 1. TOP MINIMAL POS HEADER --}}
        <header class="na-pos-header">

            {{-- Left: Brand Logo --}}
            <div class="na-pos-brand">
                <img src="{{ asset('images/logo.png') }}" alt="Laijau" style="height: 1.875rem; width: auto;" class="dark:hidden" onerror="this.onerror=null; this.src='{{ asset('logo.png') }}';" />
                <img src="{{ asset('images/logo-gold.png') }}" alt="Laijau" style="height: 1.875rem; width: auto;" class="hidden dark:block" onerror="this.onerror=null; this.src='{{ asset('logo-gold.png') }}';" />
            </div>

            {{-- Center: Search Bar + Camera Scan Button --}}
            <div style="display: flex; align-items: center; gap: 0.5rem; flex: 1; max-width: 520px;">
                <div class="na-pos-search-wrap" style="flex: 1;">
                    <input
                        type="text"
                        wire:model.live.debounce.150ms="searchQuery"
                        wire:keydown.enter="handleBarcodeScan"
                        placeholder="🔍 Search product, SKU or scan barcode..."
                        class="na-pos-search-input"
                        id="na-pos-search-field" />
                    <div style="position: absolute; left: 0.875rem; top: 0.5rem; color: #9CA3AF; pointer-events: none;">
                        <svg style="width: 1.125rem; height: 1.125rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    @if(strlen($searchQuery) > 0)
                    <button
                        type="button"
                        wire:click="$set('searchQuery', '')"
                        style="position: absolute; right: 0.875rem; top: 0.5rem; color: #9CA3AF; background: none; border: none; font-size: 0.875rem; font-weight: bold; cursor: pointer;">
                        ✕
                    </button>
                    @endif
                </div>
                <button
                    type="button"
                    @click="$dispatch('open-pos-camera')"
                    title="Scan Barcode with Phone Camera"
                    style="display: inline-flex; align-items: center; gap: 0.35rem; height: 2.125rem; padding: 0 0.75rem; background: #0A2E23; color: #ffffff; border: 1px solid #0A2E23; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; cursor: pointer; white-space: nowrap; flex-shrink: 0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
                    class="dark:!bg-[#C5A059] dark:!text-[#111827] dark:!border-[#C5A059]">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                    <span>Camera</span>
                </button>
            </div>

            {{-- Right: Fast Actions & Cashier Dropdown --}}
            <div style="display: flex; align-items: center; gap: 0.75rem;">

                {{-- Currency Switcher --}}
                <div style="display: flex; align-items: center; background: #F3F4F6; border-radius: 9999px; padding: 0.15rem; border: 1px solid #E5E7EB;" class="dark:!bg-gray-800 dark:!border-gray-700">
                    <button
                        type="button"
                        style="padding: 0.2rem 0.625rem; font-size: 0.6875rem; font-weight: 700; border-radius: 9999px; border: none; cursor: pointer;"
                        class="bg-[#0A2E23] text-white dark:!bg-[#C5A059] dark:!text-[#111827]">
                        NPR (Rs.)
                    </button>
                </div>

                {{-- Held Sales Button --}}
                <button
                    type="button"
                    wire:click="openModal('held_sales')"
                    style="display: flex; align-items: center; gap: 0.375rem; padding: 0.45rem 0.75rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; border: 1px solid #E5E7EB; background: #ffffff; color: #111827; cursor: pointer;"
                    class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white hover:border-[#C5A059]">
                    <span>Held</span>
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 1.25rem; height: 1.25rem; border-radius: 9999px; font-size: 0.6875rem; font-weight: 700; background: {{ $heldCount > 0 ? '#0A2E23' : '#E5E7EB' }}; color: {{ $heldCount > 0 ? '#ffffff' : '#6B7280' }};">
                        {{ $heldCount }}
                    </span>
                </button>

                {{-- Today's Sales Summary Button --}}
                <button
                    type="button"
                    wire:click="openModal('todays_sales')"
                    style="padding: 0.45rem 0.75rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; border: 1px solid #E5E7EB; background: #ffffff; color: #111827; cursor: pointer;"
                    class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white hover:border-[#C5A059]">
                    Today's Sales
                </button>

                {{-- Returns Button --}}
                <button
                    type="button"
                    wire:click="openModal('returns')"
                    style="padding: 0.45rem 0.75rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; border: 1px solid #E5E7EB; background: #ffffff; color: #111827; cursor: pointer;"
                    class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white hover:border-[#C5A059]">
                    Returns
                </button>

                {{-- Cashier Profile & Exit --}}
                <div style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.8125rem; font-weight: 600; padding: 0.45rem 0.75rem; border-radius: 0.5rem; background: #F3F4F6;" class="dark:!bg-gray-800">
                    <span>👤</span>
                    <span>{{ $staffName }}</span>
                </div>

                <a
                    href="{{ route('filament.admin.pages.dashboard') }}"
                    style="padding: 0.45rem 0.625rem; font-size: 0.75rem; font-weight: 600; color: #6B7280; text-decoration: none; border-radius: 0.375rem;"
                    class="hover:text-black hover:bg-gray-100"
                    title="Exit to Admin">
                    ✕ Exit
                </a>
            </div>
        </header>

        {{-- SUCCESS BANNER (If sale just completed) --}}
        @if($completedOrder)
        <div style="padding: 1rem 1.5rem; background: #0A2E23; color: #ffffff; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; border-bottom: 2px solid #C5A059;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 2.25rem; height: 2.25rem; border-radius: 9999px; background: #C5A059; color: #0A2E23; display: flex; align-items: center; justify-content: center; font-size: 1.125rem; font-weight: bold;">
                    ✓
                </div>
                <div>
                    <div style="font-size: 1.05rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                        <span>SALE COMPLETE — {{ $completedOrder['total_amount'] }}</span>
                        <span style="font-size: 0.6875rem; padding: 0.15rem 0.45rem; border-radius: 0.25rem; background: #059669; color: #ffffff; font-weight: 600;">{{ $completedOrder['payment_status'] }}</span>
                    </div>
                    <div style="font-size: 0.75rem; color: #E5E7EB; margin-top: 0.125rem;">
                        Order <strong>#{{ $completedOrder['order_number'] }}</strong> · Patron: <strong>{{ $completedOrder['customer_name'] }}</strong> ({{ $completedOrder['phone'] }}) · Method: <strong>{{ $completedOrder['payment_method'] }}</strong>
                    </div>
                </div>
            </div>

            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                @if($whatsappUrl)
                <a
                    href="{{ $whatsappUrl }}"
                    target="_blank"
                    style="padding: 0.45rem 0.875rem; background: #25D366; color: #ffffff; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <span>💬 WhatsApp Receipt</span>
                </a>
                @endif

                <a
                    href="/orders/{{ $completedOrder['id'] }}/receipt?print=1"
                    target="_blank"
                    style="padding: 0.45rem 0.875rem; background: #ffffff; color: #0A2E23; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <span>🖨️ Print Receipt</span>
                </a>

                <a
                    href="/orders/{{ $completedOrder['id'] }}/receipt?download=1"
                    target="_blank"
                    style="padding: 0.45rem 0.875rem; background: #ffffff; color: #0A2E23; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <span>📥 Download Receipt</span>
                </a>

                <a
                    href="/intadmin/orders/{{ $completedOrder['id'] }}/spec-sheet"
                    target="_blank"
                    style="padding: 0.45rem 0.75rem; background: rgba(255,255,255,0.15); color: #ffffff; border: 1px solid rgba(255,255,255,0.3); border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600; text-decoration: none;">
                    <span>📄 Spec Sheet</span>
                </a>

                <button
                    type="button"
                    wire:click="resetPos"
                    style="padding: 0.45rem 1rem; background: #C5A059; color: #111827; border: none; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 700; cursor: pointer;">
                    + New Sale
                </button>
            </div>
        </div>
        @endif

        {{-- 2. THREE PERMANENT ZONES LAYOUT --}}
        <div class="na-pos-body">

            {{-- ZONE 1: CATEGORIES (LEFT) --}}
            <aside class="na-pos-zone-categories">
                <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #9CA3AF; margin-bottom: 0.5rem; padding: 0 0.5rem;">
                    Categories
                </div>

                <button
                    type="button"
                    wire:click="$set('selectedCategoryId', null)"
                    class="na-pos-cat-btn {{ $selectedCategoryId === null ? 'na-pos-cat-btn-active' : '' }}">
                    <span>All Products</span>
                    <span style="font-size: 0.6875rem; opacity: 0.75;">{{ count($searchResults) }}</span>
                </button>

                @foreach($categories as $cat)
                <button
                    type="button"
                    wire:click="$set('selectedCategoryId', {{ $cat['id'] }})"
                    class="na-pos-cat-btn {{ $selectedCategoryId === $cat['id'] ? 'na-pos-cat-btn-active' : '' }}">
                    <span>{{ $cat['name'] }}</span>
                    @if(isset($cat['products_count']))
                    <span style="font-size: 0.6875rem; opacity: 0.75;">{{ $cat['products_count'] }}</span>
                    @endif
                </button>
                @endforeach
            </aside>

            {{-- ZONE 2: PRODUCTS CATALOG (MIDDLE) --}}
            <main class="na-pos-zone-products">
                {{-- Products Grid (4 items per line) --}}
                <div class="na-pos-prod-grid">
                    @forelse($searchResults as $prod)
                    @php
                    $variants = $prod['variants'] ?? [];
                    $totalStock = count($variants) > 0 ? collect($variants)->sum('stock_quantity') : ($prod['quantity'] ?? 0);
                    $priceVal = $prod['price'] ?? 0;
                    $imgUrl = $prod['featured_image'] ?? (is_array($prod['images'] ?? null) ? ($prod['images'][0] ?? null) : null);
                    if ($imgUrl && !str_starts_with($imgUrl, 'http')) {
                    $imgUrl = '/storage/' . ltrim($imgUrl, '/');
                    }
                    @endphp
                    <div
                        wire:key="pos-prod-{{ $prod['id'] }}"
                        wire:click="handleProductClick({{ $prod['id'] }})"
                        class="na-pos-card">
                        {{-- Image --}}
                        <div class="na-pos-card-img-wrap">
                            @if($imgUrl)
                            <img src="{{ $imgUrl }}" alt="{{ $prod['name'] }}" class="na-pos-card-img" loading="lazy" />
                            @else
                            <span style="font-family: 'Playfair Display', Georgia, serif; font-size: 1.5rem; font-weight: 700; color: #0A2E23;">
                                NA
                            </span>
                            @endif

                            @if($totalStock <= 0)
                                <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                                Sold Out
                        </div>
                        @endif
                    </div>

                    {{-- Card Details --}}
                    <div style="padding: 0.875rem; display: flex; flex-direction: column; justify-content: space-between; flex: 1;">
                        <div>
                            <div style="font-size: 0.8125rem; font-weight: 600; color: #111827; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" class="dark:!text-gray-100">
                                {{ $prod['name'] }}
                            </div>
                            <div style="font-size: 0.6875rem; color: #9CA3AF; margin-top: 0.2rem;">
                                {{ $prod['fabric'] ?? $prod['material'] ?? 'Laijau Footwear' }}
                            </div>
                        </div>

                        <div style="margin-top: 0.625rem; display: flex; align-items: flex-end; justify-content: space-between;">
                            <div>
                                <div style="font-size: 0.9375rem; font-family: 'Playfair Display', Georgia, serif; font-weight: 700; color: #0A2E23;" class="dark:!text-[#C5A059]">
                                    {{ $currSymbol }}{{ number_format($priceVal, 2) }}
                                </div>
                                <div style="font-size: 0.6875rem; display: flex; align-items: center; gap: 0.25rem; margin-top: 0.125rem;">
                                    @if($totalStock > 4)
                                    <span style="color: #059669; font-weight: 600;">● {{ $totalStock }} available</span>
                                    @elseif($totalStock > 0)
                                    <span style="color: #D97706; font-weight: 600;">● {{ $totalStock }} left</span>
                                    @else
                                    <span style="color: #DC2626; font-weight: 600;">● Out of stock</span>
                                    @endif
                                </div>
                            </div>

                            <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.25rem 0.5rem; border-radius: 0.375rem; background: #0A2E23; color: #ffffff;" class="dark:!bg-[#C5A059] dark:!text-[#111827]">
                                + Add
                            </span>
                        </div>
                    </div>
                </div>
                @empty
                <div style="grid-column: 1 / -1; padding: 4rem 0; text-align: center; color: #9CA3AF;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔍</div>
                    <p style="font-size: 0.875rem; font-weight: 600;">No products found matching "{{ $searchQuery }}"</p>
                </div>
                @endforelse
        </div>
        </main>

        {{-- ZONE 3: CURRENT SALE (RIGHT) --}}
        <aside class="na-pos-zone-sale">

            {{-- 1. Ticket Header --}}
            <div class="na-pos-ticket-header">
                <div>
                    <div style="font-size: 0.8125rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #111827;" class="dark:!text-white">
                        Current Sale
                    </div>
                    <div style="font-size: 0.6875rem; color: #9CA3AF;">
                        {{ $totals['items_count'] }} {{ $totals['items_count'] === 1 ? 'item' : 'items' }}
                    </div>
                </div>

                @if(count($cart) > 0)
                <button
                    type="button"
                    wire:click="clearCart"
                    style="font-size: 0.75rem; color: #EF4444; background: none; border: none; cursor: pointer; text-decoration: underline;">
                    Clear
                </button>
                @endif
            </div>

            {{-- 2. Line Items Stream --}}
            <div class="na-pos-ticket-items">
                @forelse($cart as $key => $item)
                <div wire:key="cart-row-{{ $key }}" class="na-pos-ticket-row">
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-size: 0.8125rem; font-weight: 600; color: #111827; line-height: 1.25;" class="dark:!text-white">
                            {{ $item['name'] }}
                        </div>
                        <div style="font-size: 0.6875rem; color: #9CA3AF; margin-top: 0.125rem;">
                            {{ $item['color'] ?? 'Standard' }} @if($item['size']) · {{ $item['size'] }} @endif
                        </div>
                        <div style="font-size: 0.75rem; font-family: monospace; font-weight: 600; color: #0A2E23; margin-top: 0.25rem;" class="dark:!text-[#C5A059]">
                            {{ $currSymbol }}{{ number_format($item['price'], 2) }}
                        </div>
                    </div>

                    {{-- Stepper --}}
                    <div style="display: flex; align-items: center; gap: 0.35rem;">
                        <button
                            type="button"
                            wire:click="updateQuantity('{{ $key }}', {{ $item['quantity'] - 1 }})"
                            class="na-pos-stepper-btn">
                            −
                        </button>
                        <span style="width: 1.25rem; text-align: center; font-size: 0.8125rem; font-family: monospace; font-weight: 700;">
                            {{ $item['quantity'] }}
                        </span>
                        <button
                            type="button"
                            wire:click="updateQuantity('{{ $key }}', {{ $item['quantity'] + 1 }})"
                            class="na-pos-stepper-btn">
                            +
                        </button>
                    </div>

                    {{-- Line Total & Delete --}}
                    <div style="text-align: right; min-width: 4rem;">
                        <div style="font-size: 0.8125rem; font-family: monospace; font-weight: 700; color: #111827;" class="dark:!text-white">
                            {{ $currSymbol }}{{ number_format($item['price'] * $item['quantity'], 2) }}
                        </div>
                        <button
                            type="button"
                            wire:click="removeFromCart('{{ $key }}')"
                            style="font-size: 0.625rem; color: #EF4444; background: none; border: none; cursor: pointer; text-decoration: underline;">
                            Remove
                        </button>
                    </div>
                </div>
                @empty
                <div style="padding: 3rem 0; text-align: center; color: #9CA3AF;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🛍️</div>
                    <p style="font-size: 0.875rem; font-weight: 600; color: #6B7280;">Current sale is empty</p>
                    <p style="font-size: 0.75rem; color: #9CA3AF; margin-top: 0.25rem;">Select products from the catalog to add.</p>
                </div>
                @endforelse
            </div>

            {{-- 3. Customer, Discounts & Totals Box (Sticky Bottom) --}}
            <div style="padding: 1.25rem; border-top: 1px solid rgba(229, 231, 235, 0.8); background: #ffffff;" class="dark:!bg-gray-900 dark:!border-gray-800">

                {{-- Customer Row --}}
                <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px dashed #E5E7EB; margin-bottom: 0.75rem;" class="dark:!border-gray-800">
                    <div>
                        <div style="font-size: 0.6875rem; text-transform: uppercase; font-weight: 700; color: #9CA3AF;">Customer</div>
                        <div style="font-size: 0.8125rem; font-weight: 600; color: #111827; display: flex; align-items: center; gap: 0.35rem;" class="dark:!text-white">
                            <span>👤</span>
                            <span>{{ $firstName }} {{ $lastName }}</span>
                        </div>
                        @if($customerLifetimeOrders > 0)
                        <div style="font-size: 0.625rem; color: #059669; font-weight: 600;">
                            {{ $customerLifetimeOrders }} orders · {{ $currSymbol }}{{ number_format($customerLifetimeSpend, 2) }} spent
                        </div>
                        @endif
                    </div>

                    <button
                        type="button"
                        wire:click="openModal('customer_select')"
                        style="padding: 0.35rem 0.625rem; font-size: 0.6875rem; font-weight: 600; border-radius: 0.375rem; border: 1px solid #D1D5DB; background: #F9FAFB; cursor: pointer;"
                        class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white hover:border-[#C5A059]">
                        Change
                    </button>
                </div>

                {{-- Discount Row --}}
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.75rem;">
                    <span style="color: #6B7280;">Discount</span>
                    @if($totals['discount'] > 0)
                    <div style="display: flex; align-items: center; gap: 0.35rem;">
                        <span style="font-family: monospace; font-weight: 700; color: #EF4444;">
                            -{{ $currSymbol }}{{ number_format($totals['discount'], 2) }}
                        </span>
                        <button
                            type="button"
                            wire:click="clearDiscount"
                            style="color: #EF4444; background: none; border: none; font-size: 0.6875rem; cursor: pointer;">
                            ✕
                        </button>
                    </div>
                    @else
                    <button
                        type="button"
                        wire:click="openModal('discount')"
                        style="font-size: 0.6875rem; color: #0A2E23; font-weight: 600; background: none; border: none; cursor: pointer; text-decoration: underline;"
                        class="dark:!text-[#C5A059]">
                        + Add Discount
                    </button>
                    @endif
                </div>

                {{-- Subtotal & VAT --}}
                <div style="display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; color: #6B7280;">
                        <span>Subtotal</span>
                        <span style="font-family: monospace; font-weight: 600; color: #111827;" class="dark:!text-white">
                            {{ $currSymbol }}{{ number_format($totals['subtotal'], 2) }}
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between; color: #6B7280;">
                        <span>VAT (20% included)</span>
                        <span style="font-family: monospace;">{{ $currSymbol }}{{ number_format($totals['vat'], 2) }}</span>
                    </div>
                </div>

                {{-- Grand Total --}}
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0; border-top: 1px solid rgba(229, 231, 235, 0.8); margin-bottom: 0.875rem;" class="dark:!border-gray-800">
                    <span style="font-size: 0.9375rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #111827;" class="dark:!text-white">
                        Total
                    </span>
                    <span style="font-size: 1.5rem; font-family: 'Playfair Display', Georgia, serif; font-weight: 700; color: #0A2E23;" class="dark:!text-[#C5A059]">
                        {{ $currSymbol }}{{ number_format($totals['total'], 2) }}
                    </span>
                </div>

                {{-- Action Buttons --}}
                <div style="display: flex; gap: 0.5rem;">
                    <button
                        type="button"
                        wire:click="holdSale"
                        style="flex: 1; padding: 0.875rem; border-radius: 0.625rem; font-size: 0.8125rem; font-weight: 600; border: 1px solid #D1D5DB; background: #ffffff; color: #111827; cursor: pointer;"
                        class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white hover:bg-gray-50">
                        Hold Sale
                    </button>

                    <button
                        type="button"
                        wire:click="openModal('payment')"
                        style="flex: 2;"
                        class="na-pos-pay-btn"
                        @if(empty($cart)) disabled style="opacity: 0.5; cursor: not-allowed;" @endif>
                        <span>PAY {{ $currSymbol }}{{ number_format($totals['total'], 2) }}</span>
                    </button>
                </div>

            </div>
        </aside>

    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL OVERLAYS --}}
    {{-- ========================================================================= --}}

    {{-- 1. VARIANT SELECTION MODAL --}}
    @if($activeModal === 'variant_select' && $selectedProductForVariant)
    @php
    $prod = $selectedProductForVariant;
    $variants = $prod['variants'] ?? [];
    $colors = collect($variants)->pluck('color')->filter()->unique()->values()->toArray();
    $sizes = collect($variants)->pluck('size')->filter()->unique()->values()->toArray();
    $currVariant = collect($variants)->firstWhere('id', $variantModalSelectedId);
    $vPrice = $currVariant['price'] ?? $prod['price'] ?? 0;
    $vStock = $currVariant['stock_quantity'] ?? 0;
    @endphp
    <div class="na-pos-modal-backdrop" wire:click.self="closeModal">
        <div class="na-pos-modal-dialog">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(229, 231, 235, 0.8); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.125rem; font-family: 'Playfair Display', Georgia, serif; font-weight: 700; color: #0A2E23;" class="dark:!text-[#C5A059]">
                    {{ $prod['name'] }}
                </h3>
                <button type="button" wire:click="closeModal" style="color: #9CA3AF; background: none; border: none; font-size: 1.25rem; cursor: pointer;">✕</button>
            </div>

            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem;">
                {{-- Colour Selector --}}
                @if(count($colors) > 0)
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #6B7280; margin-bottom: 0.5rem;">
                        Colour
                    </label>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        @foreach($colors as $c)
                        @php
                        $vMatch = collect($variants)->firstWhere('color', $c);
                        $hex = $vMatch['color_hex'] ?? '#0A2E23';
                        @endphp
                        <button
                            type="button"
                            wire:click="selectModalVariantColor('{{ $c }}')"
                            style="padding: 0.4rem 0.75rem; font-size: 0.8125rem; font-weight: 600; border-radius: 9999px; border: 1px solid {{ $variantModalColor === $c ? '#0A2E23' : '#D1D5DB' }}; background: {{ $variantModalColor === $c ? '#0A2E23' : '#ffffff' }}; color: {{ $variantModalColor === $c ? '#ffffff' : '#374151' }}; cursor: pointer; display: inline-flex; align-items: center; gap: 0.375rem;"
                            class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-gray-300">
                            <span style="width: 0.625rem; height: 0.625rem; border-radius: 9999px; background-color: {{ $hex }}; border: 1px solid rgba(0,0,0,0.15);"></span>
                            <span>{{ $c }}</span>
                        </button>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Size Selector --}}
                @if(count($sizes) > 0)
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #6B7280; margin-bottom: 0.5rem;">
                        Size
                    </label>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        @foreach($sizes as $s)
                        <button
                            type="button"
                            wire:click="selectModalVariantSize('{{ $s }}')"
                            style="padding: 0.4rem 0.875rem; font-size: 0.8125rem; font-weight: 700; border-radius: 0.5rem; border: 1px solid {{ $variantModalSize === $s ? '#0A2E23' : '#D1D5DB' }}; background: {{ $variantModalSize === $s ? '#0A2E23' : '#ffffff' }}; color: {{ $variantModalSize === $s ? '#ffffff' : '#374151' }}; cursor: pointer;"
                            class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-gray-300">
                            {{ $s }}
                        </button>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Stock Info & Quantity --}}
                <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 0.5rem; border-top: 1px solid #E5E7EB;">
                    <div>
                        <div style="font-size: 0.75rem; color: #6B7280;">Available Stock</div>
                        <div style="font-size: 0.875rem; font-weight: 700; color: {{ $vStock > 0 ? '#059669' : '#DC2626' }};">
                            {{ $vStock > 0 ? "{$vStock} pieces in stock" : 'Out of Stock' }}
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <button
                            type="button"
                            wire:click="$set('variantModalQty', {{ max(1, $variantModalQty - 1) }})"
                            class="na-pos-stepper-btn"
                            style="width: 2rem; height: 2rem;">
                            −
                        </button>
                        <span style="font-size: 1rem; font-weight: 700; font-family: monospace; width: 2rem; text-align: center;">
                            {{ $variantModalQty }}
                        </span>
                        <button
                            type="button"
                            wire:click="$set('variantModalQty', {{ min($vStock, $variantModalQty + 1) }})"
                            class="na-pos-stepper-btn"
                            style="width: 2rem; height: 2rem;">
                            +
                        </button>
                    </div>
                </div>

                {{-- Price & Add CTA --}}
                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 0.5rem;">
                    <div>
                        <div style="font-size: 0.6875rem; color: #9CA3AF;">Total Price</div>
                        <div style="font-size: 1.35rem; font-family: 'Playfair Display', Georgia, serif; font-weight: 700; color: #0A2E23;" class="dark:!text-[#C5A059]">
                            {{ $currSymbol }}{{ number_format($vPrice * $variantModalQty, 2) }}
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="addVariantModalToCart"
                        style="padding: 0.875rem 1.75rem; border-radius: 0.5rem; font-size: 0.9375rem; font-weight: 700; background: #0A2E23; color: #ffffff; border: none; cursor: pointer;"
                        @if($vStock <=0) disabled style="opacity: 0.5; cursor: not-allowed;" @endif>
                        Add to Sale
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- 2. CUSTOMER SELECTION MODAL --}}
    @if($activeModal === 'customer_select')
    <div class="na-pos-modal-backdrop" wire:click.self="closeModal">
        <div class="na-pos-modal-dialog" style="max-width: 520px;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(229, 231, 235, 0.8); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.125rem; font-weight: 700; color: #111827;" class="dark:!text-white">
                    Customer
                </h3>
                <button type="button" wire:click="closeModal" style="color: #9CA3AF; background: none; border: none; font-size: 1.25rem; cursor: pointer;">✕</button>
            </div>

            <div style="padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                {{-- Search Input --}}
                <input
                    type="text"
                    wire:model.live.debounce.150ms="customerSearchQuery"
                    placeholder="🔍 Search customers by name, phone or email..."
                    style="width: 100%; padding: 0.625rem 0.875rem; border-radius: 0.5rem; border: 1px solid #D1D5DB; font-size: 0.875rem;"
                    class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white" />

                {{-- Walk-in Quick Button --}}
                <button
                    type="button"
                    wire:click="selectWalkInCustomer"
                    style="padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #E5E7EB; background: #F9FAFB; text-align: left; font-size: 0.875rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;"
                    class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white hover:border-[#0A2E23]">
                    <span>👤</span>
                    <span>Walk-in Customer</span>
                </button>

                {{-- Customer List --}}
                <div style="max-height: 240px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.5rem;">
                    @foreach($customersList as $c)
                    <div
                        wire:key="cust-item-{{ $c['id'] }}"
                        wire:click="selectCustomer({{ $c['id'] }})"
                        style="padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #E5E7EB; background: #ffffff; cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
                        class="dark:!bg-gray-800/60 dark:!border-gray-700 hover:border-[#C5A059]">
                        <div>
                            <div style="font-size: 0.875rem; font-weight: 600; color: #111827;" class="dark:!text-white">{{ $c['name'] }}</div>
                            <div style="font-size: 0.75rem; color: #9CA3AF;">{{ $c['email'] }} · {{ $c['phone'] ?? 'No phone' }}</div>
                        </div>
                        <div style="text-align: right; font-size: 0.75rem; color: #059669; font-weight: 600;">
                            {{ $c['orders_count'] ?? 0 }} orders
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Create Customer CTA --}}
                <button
                    type="button"
                    wire:click="openModal('create_customer')"
                    style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 700; background: #F3F4F6; color: #0A2E23; border: 1px dashed #0A2E23; cursor: pointer;"
                    class="dark:!bg-gray-800 dark:!text-[#C5A059] dark:!border-[#C5A059]">
                    + Create Customer
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- 3. CREATE CUSTOMER MODAL --}}
    @if($activeModal === 'create_customer')
    <div class="na-pos-modal-backdrop" wire:click.self="closeModal">
        <div class="na-pos-modal-dialog">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(229, 231, 235, 0.8); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.125rem; font-weight: 700; color: #111827;" class="dark:!text-white">
                    Create Customer
                </h3>
                <button type="button" wire:click="closeModal" style="color: #9CA3AF; background: none; border: none; font-size: 1.25rem; cursor: pointer;">✕</button>
            </div>

            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.25rem;">First Name *</label>
                        <input type="text" wire:model="newCustFirstName" placeholder="Aarika" class="na-pos-search-input" style="height: 2.25rem; padding: 0.35rem 0.75rem; border-radius: 0.375rem;" />
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.25rem;">Last Name</label>
                        <input type="text" wire:model="newCustLastName" placeholder="Ghising" class="na-pos-search-input" style="height: 2.25rem; padding: 0.35rem 0.75rem; border-radius: 0.375rem;" />
                    </div>
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.25rem;">Email</label>
                    <input type="email" wire:model="newCustEmail" placeholder="aarika@example.com" class="na-pos-search-input" style="height: 2.25rem; padding: 0.35rem 0.75rem; border-radius: 0.375rem;" />
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.25rem;">WhatsApp / Phone</label>
                    <input type="text" wire:model="newCustPhone" placeholder="+45 31 90 00 00" class="na-pos-search-input" style="height: 2.25rem; padding: 0.35rem 0.75rem; border-radius: 0.375rem;" />
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 0.5rem;">
                    <button
                        type="button"
                        wire:click="openModal('customer_select')"
                        style="flex: 1; padding: 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; border: 1px solid #D1D5DB; background: #ffffff; cursor: pointer;">
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="createAndAttachCustomer"
                        style="flex: 2; padding: 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 700; background: #0A2E23; color: #ffffff; border: none; cursor: pointer;">
                        Create Customer
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- 4. PAYMENT MODAL (FOCUS VIEW) --}}
    @if($activeModal === 'payment')
    <div class="na-pos-modal-backdrop" wire:click.self="closeModal">
        <div class="na-pos-modal-dialog" style="max-width: 500px;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(229, 231, 235, 0.8); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.125rem; font-weight: 700; color: #111827;" class="dark:!text-white">
                    Payment
                </h3>
                <button type="button" wire:click="closeModal" style="color: #9CA3AF; background: none; border: none; font-size: 1.25rem; cursor: pointer;">✕</button>
            </div>

            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; text-align: center;">

                {{-- Large Amount Display --}}
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #9CA3AF;">Total Due</div>
                    <div style="font-size: 2.25rem; font-family: 'Playfair Display', Georgia, serif; font-weight: 700; color: #0A2E23;" class="dark:!text-[#C5A059]">
                        {{ $currSymbol }}{{ number_format($totals['total'], 2) }}
                    </div>
                </div>

                {{-- Payment Method Selection Chips --}}
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <button
                        type="button"
                        wire:click="$set('paymentMethod', 'card')"
                        style="padding: 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 700; border: 2px solid {{ $paymentMethod === 'card' ? '#0A2E23' : '#E5E7EB' }}; background: {{ $paymentMethod === 'card' ? '#0A2E23' : '#ffffff' }}; color: {{ $paymentMethod === 'card' ? '#ffffff' : '#374151' }}; cursor: pointer;"
                        class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white">
                        💳 Card
                    </button>
                    <button
                        type="button"
                        wire:click="$set('paymentMethod', 'cash')"
                        style="padding: 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 700; border: 2px solid {{ $paymentMethod === 'cash' ? '#0A2E23' : '#E5E7EB' }}; background: {{ $paymentMethod === 'cash' ? '#0A2E23' : '#ffffff' }}; color: {{ $paymentMethod === 'cash' ? '#ffffff' : '#374151' }}; cursor: pointer;"
                        class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white">
                        💵 Cash
                    </button>
                    <button
                        type="button"
                        wire:click="$set('paymentMethod', 'esewa')"
                        style="padding: 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 700; border: 2px solid {{ $paymentMethod === 'esewa' ? '#0A2E23' : '#E5E7EB' }}; background: {{ $paymentMethod === 'esewa' ? '#0A2E23' : '#ffffff' }}; color: {{ $paymentMethod === 'esewa' ? '#ffffff' : '#374151' }}; cursor: pointer;"
                        class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white">
                        📱 eSewa / Fonepay
                    </button>
                    <button
                        type="button"
                        wire:click="$set('paymentMethod', 'whatsapp_transfer')"
                        style="padding: 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 700; border: 2px solid {{ $paymentMethod === 'whatsapp_transfer' ? '#0A2E23' : '#E5E7EB' }}; background: {{ $paymentMethod === 'whatsapp_transfer' ? '#0A2E23' : '#ffffff' }}; color: {{ $paymentMethod === 'whatsapp_transfer' ? '#ffffff' : '#374151' }}; cursor: pointer;"
                        class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white">
                        💬 WhatsApp / Bank
                    </button>
                </div>

                {{-- If Cash: Tender Calculator --}}
                @if($paymentMethod === 'cash')
                <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 0.5rem; padding: 1rem; text-align: left;" class="dark:!bg-gray-800 dark:!border-gray-700">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #6B7280; margin-bottom: 0.25rem;">
                        Amount Received
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        wire:model.live.debounce.100ms="cashTendered"
                        placeholder="{{ $totals['total'] }}"
                        style="width: 100%; height: 2.75rem; font-size: 1.25rem; font-family: monospace; font-weight: 700; padding: 0.5rem 0.75rem; border-radius: 0.375rem; border: 1px solid #D1D5DB;"
                        class="dark:!bg-gray-900 dark:!border-gray-700 dark:!text-white" />

                    {{-- Quick Tender Buttons --}}
                    <div style="display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.5rem;">
                        <button
                            type="button"
                            wire:click="setExactCash"
                            style="padding: 0.3rem 0.6rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; border: 1px solid #0A2E23; background: #0A2E23; color: #ffffff; cursor: pointer;">
                            Exact ({{ $currSymbol }}{{ number_format($totals['total'], 2) }})
                        </button>
                        @foreach([50, 100, 200, 500, 1000] as $chip)
                        <button
                            type="button"
                            wire:click="addCashTender({{ $chip }})"
                            style="padding: 0.3rem 0.5rem; font-size: 0.75rem; font-weight: 600; border-radius: 0.375rem; border: 1px solid #D1D5DB; background: #ffffff; color: #374151; cursor: pointer;"
                            class="hover:border-[#0A2E23]">
                            +{{ $currSymbol }}{{ $chip }}
                        </button>
                        @endforeach
                    </div>

                    {{-- Change Due --}}
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 0.75rem; font-size: 0.9375rem; font-weight: 700;">
                        <span style="color: #6B7280;">Change</span>
                        <span style="color: #059669; font-family: monospace; font-size: 1.125rem;">
                            {{ $currSymbol }}{{ number_format($totals['change_due'], 2) }}
                        </span>
                    </div>
                </div>
                @endif

                {{-- If Card: Simulator Feedback --}}
                @if($paymentMethod === 'card')
                <div style="padding: 1rem; background: #F9FAFB; border-radius: 0.5rem; border: 1px solid #E5E7EB; font-size: 0.8125rem; color: #6B7280;" class="dark:!bg-gray-800 dark:!border-gray-700">
                    <div style="font-size: 1.25rem; margin-bottom: 0.25rem;">◌</div>
                    <div>Contactless / Terminal Ready</div>
                </div>
                @endif

                {{-- Complete Sale CTA --}}
                <button
                    type="button"
                    wire:click="completeOrder"
                    wire:loading.attr="disabled"
                    style="width: 100%; padding: 1rem; border-radius: 0.625rem; font-size: 1rem; font-weight: 700; background: #0A2E23; color: #ffffff; border: none; cursor: pointer; box-shadow: 0 4px 14px rgba(10,46,35,0.3);">
                    <span wire:loading.remove wire:target="completeOrder">COMPLETE SALE</span>
                    <span wire:loading wire:target="completeOrder">Processing...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- 5. HELD SALES MODAL --}}
    @if($activeModal === 'held_sales')
    <div class="na-pos-modal-backdrop" wire:click.self="closeModal">
        <div class="na-pos-modal-dialog" style="max-width: 520px;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(229, 231, 235, 0.8); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.125rem; font-weight: 700; color: #111827;" class="dark:!text-white">
                    Held Sales ({{ count($heldSales) }})
                </h3>
                <button type="button" wire:click="closeModal" style="color: #9CA3AF; background: none; border: none; font-size: 1.25rem; cursor: pointer;">✕</button>
            </div>

            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem; max-height: 380px; overflow-y: auto;">
                @forelse($heldSales as $heldId => $held)
                <div style="padding: 1rem; border-radius: 0.5rem; border: 1px solid #E5E7EB; background: #F9FAFB; display: flex; align-items: center; justify-content: space-between;" class="dark:!bg-gray-800 dark:!border-gray-700">
                    <div>
                        <div style="font-size: 0.875rem; font-weight: 700; color: #111827;" class="dark:!text-white">{{ $held['customer_name'] }}</div>
                        <div style="font-size: 0.75rem; color: #6B7280; margin-top: 0.125rem;">
                            {{ $held['items_count'] }} items · {{ $held['currency'] }} {{ number_format($held['total'], 2) }} · Held at {{ $held['timestamp'] }}
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.5rem;">
                        <button
                            type="button"
                            wire:click="resumeHeldSale('{{ $heldId }}')"
                            style="padding: 0.4rem 0.75rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.375rem; background: #0A2E23; color: #ffffff; border: none; cursor: pointer;">
                            Resume
                        </button>
                        <button
                            type="button"
                            wire:click="deleteHeldSale('{{ $heldId }}')"
                            style="padding: 0.4rem 0.5rem; font-size: 0.75rem; color: #EF4444; background: none; border: 1px solid #E5E7EB; border-radius: 0.375rem; cursor: pointer;">
                            ✕
                        </button>
                    </div>
                </div>
                @empty
                <div style="padding: 2.5rem 0; text-align: center; color: #9CA3AF;">
                    No held sales currently.
                </div>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    {{-- 6. TODAY'S SALES SUMMARY MODAL --}}
    @if($activeModal === 'todays_sales')
    <div class="na-pos-modal-backdrop" wire:click.self="closeModal">
        <div class="na-pos-modal-dialog" style="max-width: 500px;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(229, 231, 235, 0.8); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.125rem; font-weight: 700; color: #111827;" class="dark:!text-white">
                    Today's Sales
                </h3>
                <button type="button" wire:click="closeModal" style="color: #9CA3AF; background: none; border: none; font-size: 1.25rem; cursor: pointer;">✕</button>
            </div>

            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem;">
                {{-- Summary KPI --}}
                <div style="text-align: center; padding: 1.25rem; background: #F9FAFB; border-radius: 0.75rem; border: 1px solid #E5E7EB;" class="dark:!bg-gray-800 dark:!border-gray-700">
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #6B7280;">Today's Revenue</div>
                    <div style="font-size: 2rem; font-family: 'Playfair Display', Georgia, serif; font-weight: 700; color: #0A2E23; margin: 0.25rem 0;" class="dark:!text-[#C5A059]">
                        {{ $currSymbol }}{{ number_format($todaysSales['total_revenue'], 2) }}
                    </div>
                    <div style="font-size: 0.8125rem; font-weight: 600; color: #059669;">
                        {{ $todaysSales['total_count'] }} completed sales
                    </div>
                </div>

                {{-- Channel Breakdown --}}
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; font-size: 0.8125rem;">
                    <div style="padding: 0.75rem; border: 1px solid #E5E7EB; border-radius: 0.5rem;" class="dark:!border-gray-700">
                        <div style="color: #6B7280; font-size: 0.6875rem; font-weight: 700;">CARD</div>
                        <div style="font-family: monospace; font-weight: 700; margin-top: 0.25rem;">{{ $currSymbol }}{{ number_format($todaysSales['card_total'], 2) }}</div>
                    </div>
                    <div style="padding: 0.75rem; border: 1px solid #E5E7EB; border-radius: 0.5rem;" class="dark:!border-gray-700">
                        <div style="color: #6B7280; font-size: 0.6875rem; font-weight: 700;">CASH</div>
                        <div style="font-family: monospace; font-weight: 700; margin-top: 0.25rem;">{{ $currSymbol }}{{ number_format($todaysSales['cash_total'], 2) }}</div>
                    </div>
                    <div style="padding: 0.75rem; border: 1px solid #E5E7EB; border-radius: 0.5rem;" class="dark:!border-gray-700">
                        <div style="color: #6B7280; font-size: 0.6875rem; font-weight: 700;">ESEWA / QR</div>
                        <div style="font-family: monospace; font-weight: 700; margin-top: 0.25rem;">{{ $currSymbol }}{{ number_format($todaysSales['esewa_total'] ?? 0, 2) }}</div>
                    </div>
                    <div style="padding: 0.75rem; border: 1px solid #E5E7EB; border-radius: 0.5rem;" class="dark:!border-gray-700">
                        <div style="color: #6B7280; font-size: 0.6875rem; font-weight: 700;">WHATSAPP</div>
                        <div style="font-family: monospace; font-weight: 700; margin-top: 0.25rem;">{{ $currSymbol }}{{ number_format($todaysSales['whatsapp_total'], 2) }}</div>
                    </div>
                </div>

                <a
                    href="{{ route('filament.admin.resources.orders.index') }}"
                    style="text-align: center; font-size: 0.8125rem; font-weight: 600; color: #0A2E23; text-decoration: underline;"
                    class="dark:!text-[#C5A059]">
                    View All Orders in Admin →
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- 7. RETURNS & REFUNDS MODAL --}}
    @if($activeModal === 'returns')
    <div class="na-pos-modal-backdrop" wire:click.self="closeModal">
        <div class="na-pos-modal-dialog" style="max-width: 520px;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(229, 231, 235, 0.8); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.125rem; font-weight: 700; color: #111827;" class="dark:!text-white">
                    Order Return / Refund
                </h3>
                <button type="button" wire:click="closeModal" style="color: #9CA3AF; background: none; border: none; font-size: 1.25rem; cursor: pointer;">✕</button>
            </div>

            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                {{-- Order Search --}}
                <div style="display: flex; gap: 0.5rem;">
                    <input
                        type="text"
                        wire:model="returnSearchNumber"
                        placeholder="Enter Order # (e.g. NA-260904-XXXX)..."
                        style="flex: 1; padding: 0.5rem 0.75rem; border-radius: 0.375rem; border: 1px solid #D1D5DB; font-size: 0.8125rem;"
                        class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white" />
                    <button
                        type="button"
                        wire:click="searchReturnOrder"
                        style="padding: 0.5rem 1rem; border-radius: 0.375rem; background: #0A2E23; color: #ffffff; font-size: 0.8125rem; font-weight: 700; border: none; cursor: pointer;">
                        Lookup
                    </button>
                </div>

                @if($returnOrderData)
                <div style="padding: 1rem; border: 1px solid #E5E7EB; border-radius: 0.5rem; background: #F9FAFB;" class="dark:!bg-gray-800 dark:!border-gray-700">
                    <div style="font-size: 0.875rem; font-weight: 700;">Order #{{ $returnOrderData['order_number'] }}</div>
                    <div style="font-size: 0.75rem; color: #6B7280;">{{ $returnOrderData['first_name'] }} · {{ $returnOrderData['currency'] }} {{ $returnOrderData['total_amount'] }}</div>

                    <div style="margin-top: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem;">
                        @foreach($returnOrderData['items'] as $item)
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; cursor: pointer;">
                            <input type="checkbox" wire:model="returnSelectedItems" value="{{ $item['id'] }}" />
                            <span>{{ $item['product_name'] }} ({{ $item['quantity'] }}x) — {{ $returnOrderData['currency'] }} {{ $item['unit_price'] }}</span>
                        </label>
                        @endforeach
                    </div>

                    <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #E5E7EB;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" wire:model="returnRestock" />
                            <span>Return items to inventory stock</span>
                        </label>
                    </div>

                    <button
                        type="button"
                        wire:click="processReturn"
                        style="width: 100%; margin-top: 1rem; padding: 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 700; background: #DC2626; color: #ffffff; border: none; cursor: pointer;">
                        Process Return & Refund
                    </button>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- 8. DISCOUNT MODAL --}}
    @if($activeModal === 'discount')
    <div class="na-pos-modal-backdrop" wire:click.self="closeModal">
        <div class="na-pos-modal-dialog">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(229, 231, 235, 0.8); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.125rem; font-weight: 700; color: #111827;" class="dark:!text-white">
                    Apply Discount
                </h3>
                <button type="button" wire:click="closeModal" style="color: #9CA3AF; background: none; border: none; font-size: 1.25rem; cursor: pointer;">✕</button>
            </div>

            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem;">
                {{-- Quick Percentage Chips --}}
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.5rem; color: #6B7280;">Quick Percentage</label>
                    <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.5rem;">
                        @foreach([5, 10, 15, 20, 25] as $pct)
                        <button
                            type="button"
                            wire:click="applyDiscountPercent({{ $pct }})"
                            style="padding: 0.65rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 700; border: 1px solid #D1D5DB; background: #ffffff; cursor: pointer;"
                            class="hover:border-[#0A2E23] hover:bg-[#0A2E23] hover:text-white dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white">
                            {{ $pct }}%
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- Custom % Input --}}
                <div style="border-top: 1px solid #E5E7EB; padding-top: 1rem;" class="dark:!border-gray-700">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: #6B7280;">Custom Percentage</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input
                            type="number"
                            min="1"
                            max="100"
                            wire:model="customDiscountPercentInput"
                            placeholder="e.g. 12"
                            style="flex: 1; padding: 0.5rem 0.75rem; border-radius: 0.375rem; border: 1px solid #D1D5DB; font-size: 0.875rem;"
                            class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white" />
                        <button
                            type="button"
                            wire:click="applyCustomDiscount"
                            style="padding: 0.5rem 1rem; border-radius: 0.375rem; background: #0A2E23; color: #ffffff; font-size: 0.8125rem; font-weight: 700; border: none; cursor: pointer;">
                            Apply %
                        </button>
                    </div>
                </div>

                {{-- Custom Fixed Amount Input --}}
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: #6B7280;">Custom Fixed Amount ({{ $currSymbol }})</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input
                            type="number"
                            step="0.01"
                            wire:model="customDiscountInput"
                            placeholder="e.g. 250.00"
                            style="flex: 1; padding: 0.5rem 0.75rem; border-radius: 0.375rem; border: 1px solid #D1D5DB; font-size: 0.875rem;"
                            class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white" />
                        <button
                            type="button"
                            wire:click="applyCustomDiscount"
                            style="padding: 0.5rem 1rem; border-radius: 0.375rem; background: #0A2E23; color: #ffffff; font-size: 0.8125rem; font-weight: 700; border: none; cursor: pointer;">
                            Apply Amount
                        </button>
                    </div>
                </div>

                {{-- Discount Reason --}}
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.35rem; color: #6B7280;">Reason / Reference</label>
                    <input
                        type="text"
                        wire:model="discountReason"
                        placeholder="e.g. VIP Patron, Showroom Exhibition"
                        style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 0.375rem; border: 1px solid #D1D5DB; font-size: 0.8125rem;"
                        class="dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white" />
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- 9. SALE COMPLETE MODAL --}}
    @if($activeModal === 'sale_complete' && $completedOrder)
    <div class="na-pos-modal-backdrop" wire:click.self="closeModal">
        <div class="na-pos-modal-dialog" style="max-width: 500px; text-align: center;">
            <div style="padding: 2rem 1.5rem; display: flex; flex-direction: column; align-items: center; gap: 1.25rem;">

                {{-- Celebration Icon --}}
                <div style="width: 4.5rem; height: 4.5rem; border-radius: 9999px; background: #ECFDF5; border: 3px solid #10B981; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 2.25rem; font-weight: bold; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2);">
                    ✓
                </div>

                <div>
                    <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #059669; background: #D1FAE5; padding: 0.25rem 0.75rem; border-radius: 9999px;">
                        Payment {{ $completedOrder['payment_status'] }}
                    </span>
                    <h2 style="font-size: 1.75rem; font-family: 'Playfair Display', Georgia, serif; font-weight: 700; color: #0A2E23; margin-top: 0.5rem;" class="dark:!text-[#C5A059]">
                        {{ $completedOrder['total_amount'] }}
                    </h2>
                    <div style="font-size: 0.875rem; font-weight: 600; color: #374151; margin-top: 0.25rem;" class="dark:!text-gray-300">
                        Order <strong>#{{ $completedOrder['order_number'] }}</strong>
                    </div>
                    <div style="font-size: 0.8125rem; color: #6B7280; margin-top: 0.25rem;">
                        Patron: <strong>{{ $completedOrder['customer_name'] }}</strong> ({{ $completedOrder['phone'] }}) · {{ $completedOrder['payment_method'] }}
                    </div>
                </div>

                {{-- Actions Grid --}}
                <div style="width: 100%; display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 0.5rem;">

                    @if($whatsappUrl)
                    <a
                        href="{{ $whatsappUrl }}"
                        target="_blank"
                        style="padding: 0.875rem; border-radius: 0.5rem; background: #25D366; color: #ffffff; font-size: 0.8125rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 2px 6px rgba(37, 211, 102, 0.3);">
                        <span>💬 WhatsApp Receipt</span>
                    </a>
                    @endif

                    <a
                        href="/orders/{{ $completedOrder['id'] }}/receipt?print=1"
                        target="_blank"
                        style="padding: 0.875rem; border-radius: 0.5rem; background: #0A2E23; color: #ffffff; font-size: 0.8125rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 2px 6px rgba(10, 46, 35, 0.2);">
                        <span>🖨️ Print Receipt</span>
                    </a>

                    <a
                        href="/orders/{{ $completedOrder['id'] }}/receipt?download=1"
                        target="_blank"
                        style="padding: 0.875rem; border-radius: 0.5rem; background: #ffffff; color: #111827; border: 1px solid #D1D5DB; font-size: 0.8125rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem;"
                        class="hover:border-[#0A2E23] dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white">
                        <span>📥 Download Receipt</span>
                    </a>

                    <a
                        href="/intadmin/orders/{{ $completedOrder['id'] }}/spec-sheet"
                        target="_blank"
                        style="padding: 0.875rem; border-radius: 0.5rem; background: #ffffff; color: #111827; border: 1px solid #D1D5DB; font-size: 0.8125rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem;"
                        class="hover:border-[#0A2E23] dark:!bg-gray-800 dark:!border-gray-700 dark:!text-white">
                        <span>📄 Spec Sheet</span>
                    </a>

                </div>

                {{-- Next Sale CTA --}}
                <button
                    type="button"
                    wire:click="resetPos"
                    style="width: 100%; margin-top: 0.5rem; padding: 0.875rem; border-radius: 0.5rem; background: #C5A059; color: #111827; font-size: 0.9375rem; font-weight: 700; border: none; cursor: pointer; transition: all 0.15s ease;">
                    + Start Next Sale
                </button>

            </div>
        </div>
    </div>
    @endif

    <!-- ========================================================================= -->
    <!-- CAMERA BARCODE SCANNER MODAL -->
    <!-- ========================================================================= -->
    <div
        x-data="posCameraScanner()"
        @open-pos-camera.window="openScanner()"
        x-show="isOpen"
        x-cloak
        class="na-pos-modal-backdrop"
        style="z-index: 1000;"
        @keydown.escape.window="closeScanner()">
        <div class="na-pos-modal-dialog" style="max-width: 500px; width: 95%;">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid rgba(229, 231, 235, 0.8); display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1.25rem;">📷</span>
                    <div>
                        <h3 style="font-size: 1rem; font-weight: 800; color: #111827; margin: 0;" class="dark:!text-white">Camera Barcode Scanner</h3>
                        <p style="font-size: 0.6875rem; color: #6B7280; margin: 0;">Point device camera at item barcode or SKU</p>
                    </div>
                </div>
                <button type="button" @click="closeScanner()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #6B7280;">✕</button>
            </div>

            <div style="padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem;">
                <!-- Insecure Context Warning if HTTP over LAN -->
                <div x-show="isInsecureContext" style="background: #fffbeb; border: 1px solid #f59e0b; border-radius: 0.5rem; padding: 0.75rem; color: #92400e; font-size: 0.75rem; line-height: 1.4;">
                    <div style="font-weight: 800; display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.25rem;">
                        <span>🔒</span> <span>Browser Security Restriction (HTTPS Required)</span>
                    </div>
                    <div>
                        Mobile browsers (iOS Safari & Chrome) block camera access over plain HTTP when accessed via LAN IP.
                        To use phone cameras, serve via HTTPS or an encrypted tunnel, or enter the SKU / barcode manually below.
                    </div>
                </div>

                <!-- Camera Viewport Box -->
                <div style="position: relative; width: 100%; min-height: 260px; background: #0f172a; border-radius: 0.5rem; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                    <div id="na-pos-camera-viewport" style="width: 100%;"></div>

                    <!-- Scanning Reticle / Overlay -->
                    <div x-show="isScanning && !hasError" style="position: absolute; inset: 0; pointer-events: none; display: flex; align-items: center; justify-content: center;">
                        <div style="width: 240px; height: 140px; border: 2px solid #10b981; border-radius: 0.5rem; box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.45); position: relative;">
                            <div style="position: absolute; top: 50%; left: 0; right: 0; height: 2px; background: #ef4444; opacity: 0.85;"></div>
                        </div>
                    </div>

                    <!-- Error Prompt if denied or unavailable -->
                    <div x-show="hasError" style="position: absolute; inset: 0; background: rgba(15, 23, 42, 0.96); color: #ffffff; padding: 1.5rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 0.75rem; z-index: 10;">
                        <span style="font-size: 2.25rem;">📷⚠️</span>
                        <div style="font-size: 0.8125rem; font-weight: 700; color: #fca5a5; max-width: 320px; line-height: 1.4;" x-text="errorMessage"></div>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: center;">
                            <button type="button" @click="startCamera()" style="padding: 0.45rem 0.95rem; font-size: 0.75rem; font-weight: 700; background: #0A2E23; color: #ffffff; border: none; border-radius: 0.375rem; cursor: pointer;">
                                Retry Camera
                            </button>
                            <button type="button" @click="switchCamera()" style="padding: 0.45rem 0.75rem; font-size: 0.75rem; font-weight: 600; background: rgba(255,255,255,0.15); color: #ffffff; border: 1px solid rgba(255,255,255,0.3); border-radius: 0.375rem; cursor: pointer;">
                                Try Other Camera
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Controls Row: Camera Selection & Torch -->
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <template x-if="cameras.length > 1">
                            <select
                                x-model="selectedCameraId"
                                @change="onCameraChange()"
                                style="padding: 0.35rem 0.6rem; font-size: 0.75rem; border-radius: 0.375rem; border: 1px solid #D1D5DB; background: #ffffff; color: #111827; max-width: 170px;">
                                <template x-for="cam in cameras" :key="cam.id">
                                    <option :value="cam.id" x-text="cam.label || ('Camera ' + cam.id.slice(0, 5))"></option>
                                </template>
                            </select>
                        </template>
                        <template x-if="cameras.length <= 1">
                            <button type="button" @click="switchCamera()" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 600; border-radius: 0.375rem; border: 1px solid #D1D5DB; background: #ffffff; color: #374151; cursor: pointer;">
                                🔄 Switch Cam
                            </button>
                        </template>
                        <button type="button" x-show="hasTorch" @click="toggleTorch()" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 600; border-radius: 0.375rem; border: 1px solid #D1D5DB; background: #ffffff; color: #374151; cursor: pointer;">
                            <span x-text="torchOn ? '🔦 Light On' : '💡 Light Off'"></span>
                        </button>
                    </div>
                    <div x-show="scanCount > 0" style="font-size: 0.75rem; font-weight: 700; color: #059669;">
                        Scanned: <span x-text="scanCount"></span> items
                    </div>
                </div>

                <!-- Last Scanned Feedback Pill -->
                <div x-show="lastScanned" style="background: #064e3b; border: 1px solid #10b981; border-radius: 0.375rem; padding: 0.5rem 0.75rem; font-size: 0.75rem; color: #ffffff; display: flex; align-items: center; justify-content: space-between;">
                    <span>Detected Barcode: <strong style="font-family: monospace;" x-text="lastScanned"></strong></span>
                    <span>✓ Processed</span>
                </div>

                <!-- Manual Barcode Fallback Input -->
                <div style="border-top: 1px solid #E5E7EB; padding-top: 0.75rem;">
                    <label style="display: block; font-size: 0.6875rem; font-weight: 700; color: #6B7280; margin-bottom: 0.25rem;">
                        Manual Barcode / SKU Fallback:
                    </label>
                    <div style="display: flex; gap: 0.35rem;">
                        <input
                            type="text"
                            x-model="manualBarcode"
                            @keydown.enter.prevent="submitManualBarcode()"
                            placeholder="Type barcode or SKU..."
                            style="flex: 1; height: 38px; padding: 0.35rem 0.75rem; font-size: 0.8125rem; font-family: monospace; border: 1px solid #D1D5DB; border-radius: 0.375rem;" />
                        <button type="button" @click="submitManualBarcode()" style="padding: 0 0.85rem; font-size: 0.75rem; font-weight: 700; height: 38px; border-radius: 0.375rem; background: #0A2E23; color: #ffffff; border: none; cursor: pointer; flex-shrink: 0;">
                            Add
                        </button>
                    </div>
                </div>
            </div>

            <div style="padding: 0.75rem 1.25rem; border-top: 1px solid #E5E7EB; text-align: right;">
                <button type="button" @click="closeScanner()" style="padding: 0.5rem 1rem; border-radius: 0.375rem; border: 1px solid #D1D5DB; background: #ffffff; font-size: 0.8125rem; font-weight: 600; cursor: pointer; color: #374151;">
                    Close Scanner
                </button>
            </div>
        </div>
    </div>

    </div>

    <script src="/js/html5-qrcode.min.js?v=2.3.8"></script>
    <script>
        function posCameraScanner() {
            return {
                isOpen: false,
                isScanning: false,
                hasError: false,
                errorMessage: '',
                isInsecureContext: false,
                lastScanned: '',
                scanCount: 0,
                manualBarcode: '',
                html5QrCode: null,
                facingMode: "environment",
                torchOn: false,
                hasTorch: false,
                cameras: [],
                selectedCameraId: '',

                async openScanner() {
                    this.isOpen = true;
                    this.hasError = false;
                    this.errorMessage = '';
                    this.lastScanned = '';
                    this.manualBarcode = '';

                    const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
                    const isHttps = window.isSecureContext || window.location.protocol === 'https:';
                    this.isInsecureContext = !isLocal && !isHttps;

                    this.$nextTick(async () => {
                        await this.loadCameras();
                        await this.startCamera();
                    });
                },

                async loadCameras() {
                    if (typeof Html5Qrcode === 'undefined') return;
                    try {
                        const devices = await Html5Qrcode.getCameras();
                        if (devices && devices.length > 0) {
                            this.cameras = devices;
                            const backCam = devices.find(d => /back|rear|environment|wide|main/i.test(d.label));
                            if (backCam) {
                                this.selectedCameraId = backCam.id;
                            } else if (!this.selectedCameraId) {
                                this.selectedCameraId = devices[devices.length - 1].id;
                            }
                        }
                    } catch (e) {
                        console.warn("Could not enumerate camera devices:", e);
                    }
                },

                async startCamera() {
                    if (typeof Html5Qrcode === 'undefined') {
                        this.hasError = true;
                        this.errorMessage = 'Scanner library is loading. Please wait a moment and tap Retry.';
                        return;
                    }

                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        this.hasError = true;
                        this.errorMessage = this.isInsecureContext ?
                            'Camera access blocked: this page must be served over HTTPS when accessed from a phone. Please use HTTPS or enter the SKU manually.' :
                            'Camera API is not supported on this browser.';
                        return;
                    }

                    // Probe for permission first so we get a clean NotAllowedError
                    // instead of a generic html5-qrcode initialisation failure.
                    try {
                        const probe = await navigator.mediaDevices.getUserMedia({ video: true });
                        probe.getTracks().forEach(t => t.stop());
                    } catch (permErr) {
                        this.hasError = true;
                        const n = permErr?.name || '';
                        if (n === 'NotAllowedError' || n === 'PermissionDeniedError') {
                            this.errorMessage = 'Camera permission denied. Please allow camera access in your browser settings, then tap Retry Camera.';
                        } else if (n === 'NotFoundError') {
                            this.errorMessage = 'No camera detected on this device.';
                        } else {
                            this.errorMessage = 'Camera unavailable: ' + (permErr?.message || n);
                        }
                        return;
                    }

                    try {
                        if (this.html5QrCode && this.html5QrCode.isScanning) {
                            await this.stopCamera();
                        }

                        // Re-create the viewport element so html5-qrcode always
                        // gets a fresh, empty container (clear() destroys it on stop).
                        const container = document.getElementById("na-pos-camera-viewport");
                        if (container) {
                            container.innerHTML = '';
                        }

                        this.html5QrCode = new Html5Qrcode("na-pos-camera-viewport", {
                            verbose: false,
                            formatsToSupport: [
                                Html5QrcodeSupportedFormats.EAN_13,
                                Html5QrcodeSupportedFormats.EAN_8,
                                Html5QrcodeSupportedFormats.CODE_128,
                                Html5QrcodeSupportedFormats.CODE_39,
                                Html5QrcodeSupportedFormats.UPC_A,
                                Html5QrcodeSupportedFormats.UPC_E,
                                Html5QrcodeSupportedFormats.QR_CODE
                            ]
                        });
                        this.isScanning = true;
                        this.hasError = false;

                        // NOTE: do NOT put videoConstraints or focusMode inside
                        // the scan config — in html5-qrcode v2.x they override
                        // the camera selection and cause OverconstrainedError.
                        const config = {
                            fps: 15,
                            qrbox: (viewfinderWidth, viewfinderHeight) => {
                                const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                                const qrboxWidth = Math.floor(minEdge * 0.85);
                                const qrboxHeight = Math.floor(qrboxWidth * 0.65);
                                return { width: Math.max(220, qrboxWidth), height: Math.max(140, qrboxHeight) };
                            },
                            aspectRatio: 1.333334
                        };

                        let lastCode = '';
                        let lastTime = 0;

                        const onScanSuccess = (decodedText) => {
                            const now = Date.now();
                            if (decodedText === lastCode && (now - lastTime) < 1500) return;
                            lastCode = decodedText;
                            lastTime = now;
                            this.onBarcodeDetected(decodedText);
                        };

                        let started = false;

                        // Attempt 1: start by specific deviceId (most reliable)
                        if (this.selectedCameraId) {
                            try {
                                await this.html5QrCode.start(
                                    this.selectedCameraId,
                                    config,
                                    onScanSuccess,
                                    () => {}
                                );
                                started = true;
                            } catch (eDevice) {
                                console.warn('Camera start by deviceId failed, trying facingMode:', eDevice);
                            }
                        }

                        // Attempt 2: facingMode with "ideal" (soft preference)
                        if (!started) {
                            try {
                                await this.html5QrCode.start(
                                    { facingMode: { ideal: this.facingMode } },
                                    config,
                                    onScanSuccess,
                                    () => {}
                                );
                                started = true;
                            } catch (eFacing) {
                                console.warn('Camera start with ideal facingMode failed, trying exact:', eFacing);
                            }
                        }

                        // Attempt 3: facingMode exact match
                        if (!started) {
                            try {
                                await this.html5QrCode.start(
                                    { facingMode: { exact: this.facingMode } },
                                    config,
                                    onScanSuccess,
                                    () => {}
                                );
                                started = true;
                            } catch (eExact) {
                                console.warn('Camera start with exact facingMode failed, trying any camera:', eExact);
                            }
                        }

                        // Attempt 4: last resort — let the browser pick any camera
                        if (!started) {
                            await this.html5QrCode.start(
                                { facingMode: 'environment' },
                                config,
                                onScanSuccess,
                                () => {}
                            );
                        }

                        // Torch detection (non-fatal)
                        try {
                            const track = this.html5QrCode.getRunningTrackCapabilities();
                            this.hasTorch = !!track?.torch;
                        } catch (e) {
                            this.hasTorch = false;
                        }

                        // iOS Safari needs these attributes set after stream starts
                        const videoEl = document.querySelector('#na-pos-camera-viewport video');
                        if (videoEl) {
                            videoEl.setAttribute('playsinline', 'true');
                            videoEl.setAttribute('webkit-playsinline', 'true');
                            videoEl.setAttribute('muted', 'true');
                            videoEl.setAttribute('autoplay', 'true');
                            videoEl.style.objectFit = 'cover';
                            videoEl.style.width = '100%';
                            videoEl.style.height = '100%';
                        }

                        if (this.cameras.length === 0) {
                            await this.loadCameras();
                        }

                    } catch (err) {
                        console.error('Camera scanner start error:', err);
                        this.isScanning = false;
                        this.hasError = true;

                        const msg = (err?.message || '').toLowerCase();
                        const name = err?.name || '';

                        if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || msg.includes('permission') || msg.includes('denied')) {
                            this.errorMessage = 'Camera permission denied. Please allow camera access in your browser settings, then tap Retry Camera.';
                        } else if (name === 'NotFoundError' || name === 'DevicesNotFoundError' || msg.includes('not found')) {
                            this.errorMessage = 'No camera device found on this device.';
                        } else if (name === 'NotReadableError' || name === 'TrackStartError' || msg.includes('busy') || msg.includes('in use')) {
                            this.errorMessage = 'Camera is busy or in use by another app. Please close other camera apps and tap Retry.';
                        } else if (name === 'OverconstrainedError' || msg.includes('overconstrained') || msg.includes('constraint')) {
                            this.errorMessage = 'Camera constraint not supported on this device. Tap \'Try Other Camera\' to use a different camera.';
                        } else if (name === 'NotSupportedError' || msg.includes('not supported')) {
                            this.errorMessage = 'Camera not supported in this browser. Try Chrome or Safari.';
                        } else if (this.isInsecureContext) {
                            this.errorMessage = 'Camera blocked: page must be served over HTTPS when accessed from another device. Enter SKU manually below.';
                        } else {
                            this.errorMessage = 'Unable to open camera: ' + (err?.message || 'initialization failed');
                        }
                    }
                },

                async onCameraChange() {
                    if (this.selectedCameraId) {
                        await this.startCamera();
                    }
                },

                async stopCamera() {
                    if (this.html5QrCode) {
                        try {
                            if (this.html5QrCode.isScanning) {
                                await this.html5QrCode.stop();
                            }
                            this.html5QrCode.clear();
                        } catch (e) {
                            console.warn("Error stopping camera:", e);
                        }
                    }
                    this.isScanning = false;
                },

                async closeScanner() {
                    await this.stopCamera();
                    this.isOpen = false;
                },

                async switchCamera() {
                    if (this.cameras.length > 1) {
                        const currentIndex = this.cameras.findIndex(c => c.id === this.selectedCameraId);
                        const nextIndex = (currentIndex + 1) % this.cameras.length;
                        this.selectedCameraId = this.cameras[nextIndex].id;
                    } else {
                        this.facingMode = this.facingMode === "environment" ? "user" : "environment";
                    }
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
                    } catch (e) {
                        console.warn("Torch error:", e);
                    }
                },

                onBarcodeDetected(code) {
                    this.playBeep();
                    if (navigator.vibrate) {
                        try {
                            navigator.vibrate([60, 40, 60]);
                        } catch (e) {}
                    }
                    this.lastScanned = code;
                    this.scanCount++;

                    @this.handleBarcodeScan(code);
                },

                submitManualBarcode() {
                    if (!this.manualBarcode.trim()) return;
                    const code = this.manualBarcode.trim();
                    this.manualBarcode = '';
                    this.onBarcodeDetected(code);
                },

                playBeep() {
                    try {
                        const AudioCtx = window.AudioContext || window.webkitAudioContext;
                        if (!AudioCtx) return;
                        const audioCtx = new AudioCtx();
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