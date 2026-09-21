<x-filament-panels::page class="w-full max-w-full !p-0">
    @php
    $totals = $this->calculateTotals();
    $searchResults = $this->searchResults;
    $categories = !empty($this->categoriesList) ? $this->categoriesList : $this->categories;
    $customersList = $activeModal === 'customer_modal' ? $this->customersList : [];
    $salesHistory = $activeTab === 'history' ? $this->salesHistory : [];
    $currSymbol = 'Rs. ';
    $dash = $activeTab === 'dashboard' ? $this->dashboardData : [];
    $perf = $activeTab === 'performance' ? $this->productPerformanceData : [];
    $canSeeMargins = $this->canViewFinancialMargins();
    @endphp

    <style>
        /* 1. HIDE DEFAULT FILAMENT HEADER ON POS PAGE */
        .fi-header,
        .fi-page-header,
        .fi-header-heading,
        .fi-breadcrumbs {
            display: none !important;
        }

        /* 2. SCOPED POS DESIGN SYSTEM */
        :root {
            --lj-emerald: #0A2E23;
            --lj-emerald-hover: #134637;
            --lj-emerald-light: #EBF5F0;
            --lj-emerald-border: #B7D9C7;
            --lj-gold: #C5A059;
            --lj-gold-light: #FAF5EB;
            --lj-gold-border: #E8D7B5;
            --lj-bg: #F4F6F8;
            --lj-card: #FFFFFF;
            --lj-card-subtle: #F8FAFC;
            --lj-border: #E2E8F0;
            --lj-border-strong: #CBD5E1;
            --lj-text: #0F172A;
            --lj-text-muted: #64748B;
            --lj-rose: #E11D48;
            --lj-rose-light: #FFF1F2;
            --lj-success: #10B981;
            --lj-success-light: #ECFDF5;
            --lj-amber: #D97706;
            --lj-amber-light: #FEF3C7;
        }

        .dark {
            --lj-bg: #0F172A;
            --lj-card: #1E293B;
            --lj-card-subtle: #334155;
            --lj-border: #334155;
            --lj-border-strong: #475569;
            --lj-text: #F8FAFC;
            --lj-text-muted: #94A3B8;
            --lj-emerald-light: #16382C;
            --lj-emerald-border: #1E4E3D;
            --lj-gold-light: #2E2514;
        }

        /* POS Root Container */
        .lj-pos-root {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica NNpe", Arial, sans-serif;
            color: var(--lj-text);
            background: var(--lj-bg);
            height: calc(100vh - 4rem);
            min-height: 650px;
            margin: -1.5rem;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-sizing: border-box;
        }

        @media (max-width: 768px) {
            .lj-pos-root {
                height: auto;
                min-height: 100vh;
                margin: -1rem;
                overflow-y: auto;
            }
        }

        /* Top Navigation Bar */
        .lj-pos-topbar {
            background: var(--lj-card);
            border-bottom: 1px solid var(--lj-border);
            height: 56px;
            flex-shrink: 0;
            padding: 0 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            z-index: 10;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        .lj-pos-brand {
            display: flex;
            align-items: center;
            gap: 0.625rem;
        }

        .lj-brand-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            color: var(--lj-emerald);
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .dark .lj-brand-title {
            color: var(--lj-gold);
        }

        .lj-pos-pill {
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            background: var(--lj-emerald-light);
            color: var(--lj-emerald);
            border: 1px solid var(--lj-emerald-border);
        }

        .lj-warehouse-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: var(--lj-card-subtle);
            border: 1px solid var(--lj-border);
            padding: 0.3rem 0.65rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--lj-text);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .lj-warehouse-badge:hover {
            border-color: var(--lj-emerald);
        }

        .lj-tab-nav {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .lj-tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.4rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            background: transparent;
            color: var(--lj-text-muted);
            transition: all 0.15s ease;
        }

        .lj-tab-btn:hover {
            color: var(--lj-text);
            background: var(--lj-card-subtle);
        }

        .lj-tab-btn.active {
            background: var(--lj-emerald);
            color: #FFFFFF;
            box-shadow: 0 1px 3px rgba(10, 46, 35, 0.2);
        }

        .lj-topbar-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .lj-btn-held {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.7rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            background: var(--lj-amber-light);
            color: var(--lj-amber);
            border: 1px solid #FCD34D;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .lj-btn-held:hover {
            background: #FDE68A;
        }

        .lj-shortcut-hint {
            font-size: 0.6875rem;
            font-weight: 600;
            color: var(--lj-text-muted);
            background: var(--lj-card-subtle);
            border: 1px solid var(--lj-border);
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
        }

        /* Main Split Workspace */
        .lj-pos-main {
            flex: 1;
            display: flex;
            min-height: 0;
            gap: 0.875rem;
            padding: 0.75rem;
            box-sizing: border-box;
            overflow: hidden;
        }

        @media (max-width: 1023px) {
            .lj-pos-root {
                height: calc(100dvh - 3.75rem) !important;
                max-height: calc(100dvh - 3.75rem) !important;
                min-height: unset !important;
                margin: -1rem !important;
                overflow: hidden !important;
            }

            .lj-pos-topbar {
                height: auto !important;
                min-height: 48px !important;
                padding: 0.35rem 0.75rem 0 0.75rem !important;
                display: flex !important;
                flex-wrap: wrap !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 0.35rem !important;
            }

            .lj-pos-brand {
                flex: 1 1 auto !important;
                display: flex !important;
                align-items: center !important;
                gap: 0.4rem !important;
                min-width: 0 !important;
            }

            .lj-brand-title {
                font-size: 1.05rem !important;
                flex-shrink: 0 !important;
            }

            .lj-pos-pill {
                display: none !important;
            }

            .lj-pos-cashier-label {
                display: none !important;
            }

            .lj-warehouse-badge {
                max-width: 130px !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
                padding: 0.25rem 0.45rem !important;
                font-size: 0.7rem !important;
            }

            .lj-topbar-actions {
                flex-shrink: 0 !important;
                display: flex !important;
                align-items: center !important;
                gap: 0.35rem !important;
            }

            .lj-tab-nav {
                order: 3 !important;
                display: flex !important;
                width: 100% !important;
                overflow-x: auto !important;
                white-space: nowrap !important;
                -webkit-overflow-scrolling: touch !important;
                padding: 0.35rem 0 !important;
                margin: 0 -0.75rem !important;
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
                border-top: 1px solid var(--lj-border) !important;
                background: var(--lj-card) !important;
                gap: 0.35rem !important;
                scrollbar-width: none;
            }

            .lj-tab-nav::-webkit-scrollbar {
                display: none;
            }

            .lj-tab-btn {
                padding: 0.35rem 0.65rem !important;
                font-size: 0.75rem !important;
                white-space: nowrap !important;
                flex-shrink: 0 !important;
                border-radius: 0.5rem !important;
            }

            .lj-shortcut-hint {
                display: none !important;
            }

            .lj-pos-main {
                flex-direction: column !important;
                padding: 0.5rem !important;
                overflow: hidden !important;
                flex: 1 1 0% !important;
                height: auto !important;
                min-height: 0 !important;
                position: relative !important;
                gap: 0 !important;
            }

            .lj-catalog-panel {
                height: 100% !important;
                max-height: 100% !important;
                padding-bottom: 4.5rem !important;
            }

            .lj-catalog-panel.mobile-hidden {
                display: none !important;
            }

            .lj-cart-panel {
                height: 100% !important;
                max-height: 100% !important;
                width: 100% !important;
                min-width: 100% !important;
                max-width: 100% !important;
            }

            .lj-cart-panel.mobile-hidden {
                display: none !important;
            }

            .lj-step-btn {
                min-width: 44px !important;
                min-height: 44px !important;
                font-size: 1.25rem !important;
            }

            .lj-step-input {
                min-height: 44px !important;
                font-size: 1rem !important;
                width: 52px !important;
            }

            .lj-cart-remove-btn {
                min-width: 44px !important;
                min-height: 44px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }

            .lj-product-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.5rem !important;
            }
        }

        /* Mobile Segmented View Switcher (< 1024px) */
        .lj-pos-mobile-toggle {
            display: none;
            background: var(--lj-card-subtle);
            border: 1px solid var(--lj-border);
            border-radius: 0.625rem;
            padding: 0.2rem;
            margin: 0.4rem 0.5rem 0.25rem 0.5rem;
            gap: 0.25rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        @media (max-width: 1023px) {
            .lj-pos-mobile-toggle {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }
        }

        .lj-pos-mobile-toggle-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.5rem 0.35rem;
            min-height: 42px;
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            border: none;
            background: transparent;
            color: var(--lj-text-muted);
            cursor: pointer;
            transition: all 0.15s ease;
            touch-action: manipulation;
        }

        .lj-pos-mobile-toggle-btn.active {
            background: var(--lj-card);
            color: var(--lj-emerald);
            font-weight: 700;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .dark .lj-pos-mobile-toggle-btn.active {
            color: var(--lj-gold);
        }

        .lj-pos-mobile-toggle-badge {
            font-size: 0.6875rem;
            background: var(--lj-emerald);
            color: #ffffff;
            padding: 0.15rem 0.45rem;
            border-radius: 9999px;
            font-weight: 700;
        }

        /* Sticky Floating Cart Summary on Mobile (Strictly hidden on desktop >= 1024px) */
        .lj-pos-mobile-floating-bar {
            display: none !important;
        }

        @media (max-width: 1023px) {
            .lj-pos-mobile-floating-bar {
                display: flex !important;
                position: fixed;
                bottom: calc(4.75rem + env(safe-area-inset-bottom, 0px));
                left: 0.75rem;
                right: 0.75rem;
                z-index: 40;
                background: var(--lj-emerald);
                color: #ffffff;
                border-radius: 0.875rem;
                padding: 0.75rem 1rem;
                align-items: center;
                justify-content: space-between;
                box-shadow: 0 8px 24px rgba(10, 46, 35, 0.35), 0 2px 6px rgba(0, 0, 0, 0.1);
                cursor: pointer;
                touch-action: manipulation;
                animation: ljBounceIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            }
        }

        @media (min-width: 1024px) {

            .lj-pos-mobile-floating-bar,
            .lj-pos-mobile-toggle,
            .lj-mobile-bottom-nav,
            #lj-mobile-bottom-dock,
            .lg\:hidden {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                pointer-events: none !important;
                height: 0 !important;
                max-height: 0 !important;
                overflow: hidden !important;
            }
        }

        .dark .lj-pos-mobile-floating-bar {
            background: #064e3b;
            border: 1px solid #10b981;
        }

        .lj-pos-floating-details {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
        }

        .lj-pos-floating-count {
            font-size: 0.75rem;
            font-weight: 600;
            opacity: 0.9;
        }

        .lj-pos-floating-total {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .lj-pos-floating-btn {
            background: #ffffff;
            color: var(--lj-emerald);
            border: none;
            border-radius: 0.625rem;
            padding: 0.55rem 1rem;
            min-height: 40px;
            font-size: 0.8125rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .lj-mobile-back-row {
            margin-bottom: 0.5rem;
        }

        .lj-mobile-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.8125rem;
            font-weight: 700;
            color: var(--lj-emerald);
            background: var(--lj-card-subtle);
            border: 1px solid var(--lj-border);
            padding: 0.45rem 0.75rem;
            min-height: 40px;
            border-radius: 0.5rem;
            cursor: pointer;
            touch-action: manipulation;
        }

        .dark .lj-mobile-back-btn {
            color: var(--lj-gold);
        }

        /* Left Panel: Product Catalog */
        .lj-catalog-panel {
            flex: 1 1 0%;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
            overflow: hidden;
        }

        /* Search & Filter Bar */
        .lj-search-box {
            position: relative;
            width: 100%;
        }

        .lj-search-input {
            width: 100%;
            height: 44px;
            padding: 0.5rem 2.5rem 0.5rem 2.75rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.625rem;
            border: 1.5px solid var(--lj-border);
            background: var(--lj-card);
            color: var(--lj-text);
            box-sizing: border-box;
            outline: none;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
            transition: all 0.2s ease;
        }

        .lj-search-input:focus {
            border-color: var(--lj-emerald);
            box-shadow: 0 0 0 3px rgba(10, 46, 35, 0.12);
        }

        .lj-search-icon {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--lj-text-muted);
            pointer-events: none;
            display: flex;
            align-items: center;
        }

        .lj-search-clear {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--lj-text-muted);
            cursor: pointer;
            padding: 0.25rem;
            font-size: 0.875rem;
        }

        .lj-search-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
        }

        .lj-pos-camera-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            height: 44px;
            padding: 0 0.875rem;
            background: #0A2E23;
            color: #ffffff;
            border: 1px solid #0A2E23;
            border-radius: 0.625rem;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }

        .lj-pos-camera-btn:hover {
            background: #124032;
        }

        @media (max-width: 640px) {
            .lj-pos-camera-btn span {
                display: none;
            }

            .lj-pos-camera-btn {
                padding: 0 0.75rem;
            }
        }

        /* Category Filter Chips */
        .lj-cat-bar {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            overflow-x: auto;
            padding-bottom: 0.25rem;
            scrollbar-width: none;
            flex-shrink: 0;
        }

        .lj-cat-bar::-webkit-scrollbar {
            display: none;
        }

        .lj-cat-chip {
            padding: 0.35rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--lj-border);
            background: var(--lj-card);
            color: var(--lj-text-muted);
            white-space: nowrap;
            transition: all 0.15s ease;
        }

        .lj-cat-chip:hover {
            border-color: var(--lj-emerald);
            color: var(--lj-emerald);
        }

        .lj-cat-chip.active {
            background: var(--lj-emerald);
            color: #FFFFFF;
            border-color: var(--lj-emerald);
        }

        /* Product Grid */
        .lj-grid-wrap {
            flex: 1;
            overflow-y: auto;
            padding-right: 0.25rem;
        }

        .lj-product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.625rem;
        }

        @media (max-width: 640px) {
            .lj-product-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }
        }

        .lj-prod-card {
            background: var(--lj-card);
            border: 1px solid var(--lj-border);
            border-radius: 0.75rem;
            padding: 0.625rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
            user-select: none;
            position: relative;
        }

        .lj-prod-card:hover {
            border-color: var(--lj-emerald-border);
            box-shadow: 0 4px 12px rgba(10, 46, 35, 0.08);
            transform: translateY(-1px);
        }

        .lj-prod-img-wrap {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 0.5rem;
            overflow: hidden;
            background: var(--lj-card-subtle);
            position: relative;
        }

        .lj-prod-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.2s ease;
        }

        .lj-prod-card:hover .lj-prod-img {
            transform: scale(1.03);
        }

        .lj-prod-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .lj-prod-title {
            font-size: 0.8125rem;
            font-weight: 700;
            line-height: 1.25;
            color: var(--lj-text);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2rem;
        }

        .lj-prod-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.6875rem;
            color: var(--lj-text-muted);
        }

        .lj-prod-sku {
            font-family: monospace;
            font-weight: 600;
            background: var(--lj-card-subtle);
            padding: 0.1rem 0.35rem;
            border-radius: 0.25rem;
        }

        .lj-opts-badge {
            background: var(--lj-gold-light);
            color: #92400E;
            font-weight: 700;
            padding: 0.1rem 0.4rem;
            border-radius: 0.25rem;
            border: 1px solid var(--lj-gold-border);
        }

        .lj-prod-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.25rem;
            margin-top: 0.25rem;
            padding-top: 0.35rem;
            border-top: 1px solid var(--lj-border);
        }

        .lj-prod-price {
            font-size: 0.9375rem;
            font-weight: 800;
            color: var(--lj-emerald);
        }

        .dark .lj-prod-price {
            color: var(--lj-gold);
        }

        .lj-stock-pill {
            font-size: 0.6875rem;
            font-weight: 600;
        }

        .lj-stock-pill.in-stock {
            color: var(--lj-success);
        }

        .lj-stock-pill.low-stock {
            color: var(--lj-amber);
        }

        .lj-stock-pill.out-stock {
            color: var(--lj-rose);
        }

        .lj-add-btn {
            background: var(--lj-emerald-light);
            color: var(--lj-emerald);
            border: 1px solid var(--lj-emerald-border);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.25rem 0.6rem;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .lj-prod-card:hover .lj-add-btn {
            background: var(--lj-emerald);
            color: #FFFFFF;
        }

        /* Right Panel: POS Cart & Checkout Terminal */
        .lj-cart-panel {
            width: 420px;
            flex-shrink: 0;
            background: var(--lj-card);
            border: 1px solid var(--lj-border);
            border-radius: 0.875rem;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        @media (max-width: 1024px) {
            .lj-cart-panel {
                width: 100%;
                height: auto;
            }
        }

        /* Customer / Channel Strip */
        .lj-cart-customer-strip {
            padding: 0.625rem 0.75rem;
            background: var(--lj-card-subtle);
            border-bottom: 1px solid var(--lj-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .lj-cust-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--lj-card);
            border: 1px solid var(--lj-border);
            border-radius: 0.5rem;
            padding: 0.35rem 0.625rem;
            cursor: pointer;
            text-align: left;
            flex: 1;
            transition: all 0.15s ease;
        }

        .lj-cust-btn:hover {
            border-color: var(--lj-emerald);
        }

        .lj-cust-name {
            font-size: 0.8125rem;
            font-weight: 700;
            color: var(--lj-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .lj-cust-phone {
            font-size: 0.6875rem;
            color: var(--lj-text-muted);
        }

        .lj-channel-select {
            height: 36px;
            padding: 0 0.5rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid var(--lj-border);
            background: var(--lj-card);
            color: var(--lj-text);
            outline: none;
        }

        /* Cart Items Container */
        .lj-cart-items-wrap {
            flex: 1;
            overflow-y: auto;
            padding: 0.625rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-height: 180px;
        }

        .lj-empty-cart {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            text-align: center;
            color: var(--lj-text-muted);
        }

        .lj-empty-cart-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            opacity: 0.6;
        }

        .lj-cart-row {
            background: var(--lj-card-subtle);
            border: 1px solid var(--lj-border);
            border-radius: 0.625rem;
            padding: 0.5rem 0.625rem;
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            transition: all 0.15s ease;
        }

        .lj-cart-row:hover {
            border-color: var(--lj-border-strong);
        }

        .lj-cart-row-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .lj-cart-item-info {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex: 1;
            min-width: 0;
        }

        .lj-cart-thumb {
            width: 40px;
            height: 40px;
            border-radius: 0.375rem;
            object-fit: cover;
            background: var(--lj-card);
            border: 1px solid var(--lj-border);
            flex-shrink: 0;
        }

        .lj-cart-title {
            font-size: 0.8125rem;
            font-weight: 700;
            color: var(--lj-text);
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .lj-cart-spec {
            font-size: 0.6875rem;
            font-weight: 600;
            color: var(--lj-text-muted);
        }

        .lj-cart-remove-btn {
            background: transparent;
            border: none;
            color: var(--lj-rose);
            cursor: pointer;
            padding: 0.2rem;
            font-size: 0.875rem;
            opacity: 0.7;
            transition: opacity 0.15s ease;
        }

        .lj-cart-remove-btn:hover {
            opacity: 1;
        }

        .lj-cart-row-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        /* Quantity Stepper */
        .lj-stepper {
            display: inline-flex;
            align-items: center;
            background: var(--lj-card);
            border: 1px solid var(--lj-border);
            border-radius: 0.375rem;
            overflow: hidden;
        }

        .lj-step-btn {
            width: 28px;
            height: 28px;
            background: transparent;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--lj-text);
            cursor: pointer;
            transition: background 0.1s ease;
        }

        .lj-step-btn:hover {
            background: var(--lj-card-subtle);
        }

        .lj-step-qty {
            width: 32px;
            text-align: center;
            font-size: 0.8125rem;
            font-weight: 700;
            color: var(--lj-text);
            border-left: 1px solid var(--lj-border);
            border-right: 1px solid var(--lj-border);
            line-height: 28px;
        }

        .lj-step-input {
            width: 38px;
            height: 28px;
            text-align: center;
            font-size: 0.8125rem;
            font-weight: 700;
            color: var(--lj-text);
            background: transparent;
            border: none;
            border-left: 1px solid var(--lj-border);
            border-right: 1px solid var(--lj-border);
            padding: 0;
            outline: none;
            -moz-appearance: textfield;
        }

        .lj-step-input::-webkit-outer-spin-button,
        .lj-step-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .lj-step-input:focus {
            background: #FFFFFF;
            box-shadow: inset 0 0 0 1px var(--lj-emerald);
        }

        .lj-cart-line-price {
            font-size: 0.875rem;
            font-weight: 800;
            color: var(--lj-text);
        }

        /* Pinned Bottom Checkout Summary */
        .lj-cart-summary {
            background: var(--lj-card);
            border-top: 1px solid var(--lj-border);
            padding: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.02);
        }

        .lj-summary-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8125rem;
            color: var(--lj-text-muted);
        }

        .lj-discount-trigger {
            background: transparent;
            border: none;
            color: var(--lj-emerald);
            font-weight: 700;
            font-size: 0.75rem;
            cursor: pointer;
            text-decoration: underline;
        }

        /* Profit Box (Only for Managers & Admins) */
        .lj-profit-box {
            background: var(--lj-emerald-light);
            border: 1px dashed var(--lj-emerald-border);
            border-radius: 0.5rem;
            padding: 0.35rem 0.625rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.6875rem;
            color: var(--lj-emerald);
            font-weight: 600;
        }

        /* Grand Total Big Display */
        .lj-grand-total-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            padding: 0.5rem 0.625rem;
            background: var(--lj-card-subtle);
            border-radius: 0.5rem;
            border: 1px solid var(--lj-border);
        }

        .lj-grand-total-label {
            font-size: 0.875rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--lj-text);
        }

        .lj-grand-total-amount {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--lj-emerald);
            letter-spacing: -0.02em;
        }

        .dark .lj-grand-total-amount {
            color: var(--lj-gold);
        }

        /* Big Checkout Primary CTA Button */
        .lj-checkout-btn {
            width: 100%;
            height: 52px;
            border: none;
            border-radius: 0.625rem;
            background: var(--lj-emerald);
            color: #FFFFFF;
            font-size: 0.9375rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(10, 46, 35, 0.25);
            transition: all 0.15s ease;
        }

        .lj-checkout-btn:hover:not(:disabled) {
            background: var(--lj-emerald-hover);
            box-shadow: 0 6px 16px rgba(10, 46, 35, 0.35);
            transform: translateY(-1px);
        }

        .lj-checkout-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }

        .lj-cart-actions-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .lj-btn-secondary {
            flex: 1;
            height: 36px;
            background: var(--lj-card-subtle);
            border: 1px solid var(--lj-border);
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--lj-text-muted);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            transition: all 0.15s ease;
        }

        .lj-btn-secondary:hover {
            color: var(--lj-text);
            border-color: var(--lj-border-strong);
        }

        /* Modal Overlay & Card System */
        .lj-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            box-sizing: border-box;
        }

        .lj-modal-card {
            background: var(--lj-card);
            border-radius: 1rem;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: ljModalPop 0.18s ease-out;
        }

        @keyframes ljModalPop {
            from {
                opacity: 0;
                transform: scale(0.96);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .lj-modal-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--lj-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--lj-card-subtle);
        }

        .lj-modal-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--lj-text);
        }

        .lj-modal-close {
            background: transparent;
            border: none;
            font-size: 1.25rem;
            color: var(--lj-text-muted);
            cursor: pointer;
            padding: 0.25rem;
        }

        .lj-modal-body {
            padding: 1.25rem;
            overflow-y: auto;
            max-height: 75vh;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .lj-modal-footer {
            padding: 0.875rem 1.25rem;
            border-top: 1px solid var(--lj-border);
            background: var(--lj-card-subtle);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.625rem;
        }

        @media (max-width: 767px) {
            .lj-modal-backdrop {
                padding: 0 !important;
                align-items: flex-end !important;
            }

            .lj-modal-card {
                max-width: 100% !important;
                border-radius: 1.25rem 1.25rem 0 0 !important;
                max-height: 90vh !important;
                animation: ljModalSlidNpp 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
            }

            @keyframes ljModalSlidNpp {
                from {
                    transform: translateY(100%);
                }

                to {
                    transform: translateY(0);
                }
            }

            .lj-modal-footer {
                flex-direction: column-reverse !important;
                gap: 0.5rem !important;
                padding: 0.75rem 1rem !important;
            }

            .lj-modal-footer button {
                width: 100% !important;
                height: 48px !important;
            }

            .lj-tender-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.5rem !important;
            }

            .lj-quick-tender-row {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 0.35rem !important;
            }

            .lj-quick-cash-chip {
                flex: 1 1 calc(33.333% - 0.35rem) !important;
                min-height: 40px !important;
                font-size: 0.75rem !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
        }

        /* Checkout Modal Elements */
        .lj-tender-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
        }

        .lj-pm-pill {
            border: 1.5px solid var(--lj-border);
            background: var(--lj-card);
            border-radius: 0.5rem;
            padding: 0.625rem 0.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--lj-text);
            transition: all 0.15s ease;
        }

        .lj-pm-pill:hover {
            border-color: var(--lj-emerald);
        }

        .lj-pm-pill.active {
            border-color: var(--lj-emerald);
            background: var(--lj-emerald-light);
            color: var(--lj-emerald);
        }

        .lj-tender-input-wrap {
            position: relative;
            width: 100%;
        }

        .lj-tender-input {
            width: 100%;
            height: 52px;
            font-size: 1.5rem;
            font-weight: 900;
            padding: 0.5rem 1rem 0.5rem 3rem;
            border-radius: 0.625rem;
            border: 2px solid var(--lj-border);
            background: var(--lj-card);
            color: var(--lj-text);
            box-sizing: border-box;
            outline: none;
        }

        .lj-tender-input:focus {
            border-color: var(--lj-emerald);
        }

        .lj-tender-prefix {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--lj-text-muted);
        }

        .lj-quick-tender-row {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            flex-wrap: wrap;
        }

        .lj-quick-cash-chip {
            background: var(--lj-card-subtle);
            border: 1px solid var(--lj-border);
            border-radius: 0.375rem;
            padding: 0.35rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.1s ease;
        }

        .lj-quick-cash-chip:hover {
            background: var(--lj-emerald-light);
            border-color: var(--lj-emerald-border);
            color: var(--lj-emerald);
        }

        .lj-change-banner {
            padding: 0.75rem 1rem;
            border-radius: 0.625rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 800;
        }

        .lj-change-banner.success {
            background: var(--lj-success-light);
            border: 1px solid #A7F3D0;
            color: #065F46;
        }

        .lj-change-banner.warning {
            background: var(--lj-rose-light);
            border: 1px solid #FECDD3;
            color: #9F1239;
        }

        /* Thermal Receipt View (80mm) */
        .lj-thermal-wrap {
            background: #FFFFFF;
            color: #000000;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            padding: 1.5rem 1rem;
            width: 100%;
            max-width: 340px;
            margin: 0 auto;
            border: 1px dashed #CBD5E1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .lj-thermal-center {
            text-align: center;
        }

        .lj-thermal-brand {
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 2px;
        }

        .lj-thermal-divider {
            border-top: 1px dashed #000000;
            margin: 8px 0;
        }

        .lj-thermal-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            line-height: 1.4;
        }

        .lj-thermal-total {
            font-size: 14px;
            font-weight: 900;
        }

        /* PRINT STYLES: ONLY PRINT RECEIPT (80mm THERMAL) */
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }

            body * {
                visibility: hidden !important;
            }

            .lj-thermal-printable,
            .lj-thermal-printable * {
                visibility: visible !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .lj-thermal-printable {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 80mm !important;
                max-width: 80mm !important;
                padding: 3mm 2mm !important;
                margin: 0 !important;
                border: none !important;
                box-shadow: none !important;
                background: #ffffff !important;
            }
        }
    </style>

    <div class="lj-pos-root" x-data="{ mobilePosTab: 'catalog' }">
        <!-- ========================================================================= -->
        <!-- TOP NAVIGATION & CONTROL BAR -->
        <!-- ========================================================================= -->
        <header class="lj-pos-topbar">
            <div class="lj-pos-brand">
                <span class="lj-brand-title">LAIJAU</span>
                <span class="lj-pos-pill">POS Terminal</span>

                <!-- Warehouse Selection Dropdown -->
                <div class="relative" x-data="{ open: false }">
                    <button
                        type="button"
                        @click="open = !open"
                        class="lj-warehouse-badge"
                        title="Switch showroom/warehouse location">
                        <span>📍</span>
                        <span>
                            @php
                            $curWh = collect($warehousesList)->firstWhere('id', $selectedWarehouseId);
                            @endphp
                            {{ $curWh['name'] ?? 'Showroom POS' }}
                        </span>
                        <span style="font-size: 0.65rem;">▼</span>
                    </button>

                    <div
                        x-show="open"
                        @click.away="open = false"
                        x-transition
                        style="display: none; position: absolute; left: 0; top: 100%; margin-top: 4px; width: 260px; background: var(--lj-card); border: 1px solid var(--lj-border); border-radius: 0.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 50; padding: 0.25rem;">
                        <div style="font-size: 0.6875rem; font-weight: 700; color: var(--lj-text-muted); padding: 0.35rem 0.5rem; text-transform: uppercase;">
                            Selling Warehouse
                        </div>
                        @foreach($warehousesList as $wh)
                        <button
                            type="button"
                            wire:click="setWarehouse({{ $wh['id'] }})"
                            @click="open = false"
                            style="width: 100%; text-align: left; padding: 0.4rem 0.5rem; font-size: 0.75rem; font-weight: 600; border: none; background: {{ $selectedWarehouseId === $wh['id'] ? 'var(--lj-emerald-light)' : 'transparent' }}; color: {{ $selectedWarehouseId === $wh['id'] ? 'var(--lj-emerald)' : 'var(--lj-text)' }}; border-radius: 0.375rem; cursor: pointer; display: flex; justify-content: space-between;">
                            <span>{{ $wh['name'] }}</span>
                            <span style="font-family: monospace; opacity: 0.7;">{{ $wh['code'] }}</span>
                        </button>
                        @endforeach
                    </div>
                </div>

                <!-- Cashier Identity -->
                <span class="lj-pos-cashier-label" style="font-size: 0.75rem; color: var(--lj-text-muted); display: inline-flex; align-items: center; gap: 0.25rem;">
                    👤 {{ Auth::user()?->name ?? 'Cashier' }}
                </span>
            </div>

            <!-- Center View Navigation -->
            <nav class="lj-tab-nav">
                <button
                    type="button"
                    wire:click="setTab('new_sale')"
                    class="lj-tab-btn {{ $activeTab === 'new_sale' ? 'active' : '' }}">
                    🛒 New Sale
                </button>

                <button
                    type="button"
                    wire:click="setTab('history')"
                    class="lj-tab-btn {{ $activeTab === 'history' ? 'active' : '' }}">
                    📜 Sales History
                </button>

                <button
                    type="button"
                    wire:click="setTab('dashboard')"
                    class="lj-tab-btn {{ $activeTab === 'dashboard' ? 'active' : '' }}">
                    📊 Dashboard
                </button>

                <button
                    type="button"
                    wire:click="setTab('performance')"
                    class="lj-tab-btn {{ $activeTab === 'performance' ? 'active' : '' }}">
                    📈 Product Metrics
                </button>
            </nav>

            <!-- Right Quick Actions -->
            <div class="lj-topbar-actions">
                @if(count($heldSales) > 0)
                <button
                    type="button"
                    wire:click="openModal('held_sales')"
                    class="lj-btn-held"
                    title="View suspended sales on hold">
                    ⏸️ Held ({{ count($heldSales) }})
                </button>
                @endif

                @if(count($cart) > 0)
                <button
                    type="button"
                    wire:click="confirmClearCart"
                    class="lj-btn-secondary"
                    style="height: 32px; padding: 0 0.6rem; color: var(--lj-rose);"
                    title="Clear current cart ticket">
                    Clear Cart
                </button>
                @endif

                <span class="lj-shortcut-hint">
                    F8: Pay · F9: Hold · /: Scan
                </span>
            </div>
        </header>

        <!-- MOBILE TOP VIEW SWITCHER (< 1024px) -->
        @if($activeTab === 'new_sale')
        <div class="lj-pos-mobile-toggle">
            <button
                type="button"
                @click="mobilePosTab = 'catalog'"
                class="lj-pos-mobile-toggle-btn"
                :class="mobilePosTab === 'catalog' ? 'active' : ''">
                <span>📦</span>
                <span>Products Catalog</span>
            </button>
            <button
                type="button"
                @click="mobilePosTab = 'cart'"
                class="lj-pos-mobile-toggle-btn"
                :class="mobilePosTab === 'cart' ? 'active' : ''">
                <span>🛒</span>
                <span>Cart ({{ count($cart) }})</span>
                @if(count($cart) > 0)
                <span class="lj-pos-mobile-toggle-badge">Rs. {{ number_format($totals['total'] ?? 0) }}</span>
                @endif
            </button>
        </div>
        @endif

        <!-- ========================================================================= -->
        <!-- TAB 1: NEW SALE WORKSPACE (TWO-PANEL TERMINAL) -->
        <!-- ========================================================================= -->
        @if($activeTab === 'new_sale')
        <main class="lj-pos-main">
            <!-- LEFT PANEL: PRODUCT SEARCH & CATALOG -->
            <section class="lj-catalog-panel" :class="mobilePosTab !== 'catalog' ? 'mobile-hidden' : ''">
                <!-- Barcode & Search Bar with Mobile Camera Scanner -->
                <div class="lj-search-wrapper">
                    <div class="lj-search-box">
                        <span class="lj-search-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </span>
                        <input
                            id="lj-pos-search-input"
                            type="text"
                            wire:model.live.debounce.250ms="searchQuery"
                            wire:keydown.enter="handleBarcodeScan"
                            placeholder="Scan barcode or search product / SKU / size... (Press Enter or / to focus)"
                            class="lj-search-input"
                            autofocus />
                        @if(!empty($searchQuery))
                        <button
                            type="button"
                            wire:click="$set('searchQuery', '')"
                            class="lj-search-clear"
                            title="Clear search">
                            ✕
                        </button>
                        @endif
                    </div>
                    <button
                        type="button"
                        @click="$dispatch('open-pos-camera')"
                        class="lj-pos-camera-btn"
                        title="Scan Barcode with Phone Camera">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle cx="12" cy="13" r="4"></circle>
                        </svg>
                        <span>Scan Camera</span>
                    </button>
                </div>

                <!-- Category Chips Horizontal Bar -->
                <div class="lj-cat-bar">
                    <button
                        type="button"
                        wire:click="$set('selectedCategoryId', null)"
                        class="lj-cat-chip {{ is_null($selectedCategoryId) ? 'active' : '' }}">
                        All Categories
                    </button>
                    @foreach($categories as $cat)
                    <button
                        type="button"
                        wire:click="$set('selectedCategoryId', {{ $cat['id'] }})"
                        class="lj-cat-chip {{ $selectedCategoryId === $cat['id'] ? 'active' : '' }}">
                        {{ $cat['name'] }}
                    </button>
                    @endforeach
                </div>

                <!-- Product Catalog Grid -->
                <div class="lj-grid-wrap">
                    <div class="lj-product-grid">
                        @forelse($searchResults as $prod)
                        @php
                        $pStock = collect($prod['variants'])->sum('stock_quantity');
                        $hasVariants = count($prod['variants']) > 1;
                        $firstVar = $prod['variants'][0] ?? null;
                        $refPrice = $firstVar && !empty($firstVar['price'])
                        ? $firstVar['price']
                        : ($prod['price'] ?? 0);
                        $img = $firstVar['image'] ?? ($prod['featured_image'] ?? null);
                        @endphp
                        <div
                            wire:click="handleProductClick({{ $prod['id'] }})"
                            class="lj-prod-card">
                            <div class="lj-prod-img-wrap">
                                @if($img)
                                <img src="/storage/{{ $img }}" alt="{{ $prod['name'] }}" class="lj-prod-img" />
                                @else
                                <div class="lj-prod-img flex items-center justify-center bg-slate-100 text-slate-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                @endif
                            </div>

                            <div class="lj-prod-info">
                                <div class="lj-prod-title" title="{{ $prod['name'] }}">{{ $prod['name'] }}</div>
                                <div class="lj-prod-meta">
                                    <span class="lj-prod-sku">{{ $prod['sku'] }}</span>
                                    @if($hasVariants)
                                    <span class="lj-opts-badge">{{ count($prod['variants']) }} opts</span>
                                    @endif
                                </div>
                            </div>

                            <div class="lj-prod-bottom">
                                <div>
                                    <div class="lj-prod-price">{{ $currSymbol }}{{ number_format($refPrice, 2) }}</div>
                                    <div class="lj-stock-pill {{ $pStock > 5 ? 'in-stock' : ($pStock > 0 ? 'low-stock' : 'out-stock') }}">
                                        ● {{ $pStock }} in stock
                                    </div>
                                </div>
                                <button type="button" class="lj-add-btn">
                                    {{ $hasVariants ? '+ Options' : '+ Add' }}
                                </button>
                            </div>
                        </div>
                        @empty
                        <div style="grid-column: 1 / -1; padding: 4rem 1rem; text-align: center; color: var(--lj-text-muted); background: var(--lj-card); border-radius: 0.75rem; border: 1px dashed var(--lj-border);">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔍</div>
                            <div style="font-weight: 700; font-size: 0.9375rem;">No products found</div>
                            <div style="font-size: 0.8125rem; margin-top: 0.25rem;">Try a different keyword or scan barcode</div>
                        </div>
                        @endforelse

                        @if(count($searchResults) >= $catalogLimit)
                        <div style="grid-column: 1 / -1; text-align: center; padding: 0.75rem 0;">
                            <button
                                type="button"
                                wire:click="loadMoreProducts"
                                class="lj-btn-secondary"
                                style="padding: 0.5rem 1.25rem; font-size: 0.8125rem; font-weight: 600; border-radius: 0.5rem; cursor: pointer;">
                                + Load More Products (Showing {{ count($searchResults) }})
                            </button>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- STICKY FLOATING CART BAR ON MOBILE (WHEN IN CATALOG VIEW & CART HAS ITEMS) -->
                @if(count($cart) > 0)
                <div
                    class="lj-pos-mobile-floating-bar lg:hidden"
                    x-show="mobilePosTab === 'catalog'"
                    @click="mobilePosTab = 'cart'">
                    <div class="lj-pos-floating-details">
                        <div class="lj-pos-floating-count">
                            🛒 {{ count($cart) }} {{ Str::plural('item', count($cart)) }}
                        </div>
                        <div class="lj-pos-floating-total">
                            Total: Rs. {{ number_format($totals['total'] ?? 0, 2) }}
                        </div>
                    </div>
                    <button type="button" class="lj-pos-floating-btn" @click.stop="mobilePosTab = 'cart'">
                        <span>View Cart & Pay</span>
                        <span>➔</span>
                    </button>
                </div>
                @endif
            </section>

            <!-- RIGHT PANEL: ALWAYS-VISIBLE CART & CHECKOUT TERMINAL -->
            <section class="lj-cart-panel" :class="mobilePosTab !== 'cart' ? 'mobile-hidden' : ''">
                <!-- Mobile Return to Products Bar -->
                <div class="lj-mobile-back-row lg:hidden">
                    <button type="button" @click="mobilePosTab = 'catalog'" class="lj-mobile-back-btn">
                        <span>← Back to Products Catalog</span>
                    </button>
                </div>

                <!-- Customer & Channel Bar -->
                <div class="lj-cart-customer-strip">
                    <button
                        type="button"
                        wire:click="openModal('customer_modal')"
                        class="lj-cust-btn"
                        title="Change customer">
                        <span style="font-size: 1.1rem;">👤</span>
                        <div style="min-width: 0; flex: 1;">
                            <div class="lj-cust-name">{{ $customerName }}</div>
                            <div class="lj-cust-phone">{{ $customerPhone ?: 'Walk-in Showroom Sale' }}</div>
                        </div>
                        <span style="font-size: 0.75rem; color: var(--lj-emerald); font-weight: 700;">Edit</span>
                    </button>

                    <select
                        wire:model.live="salesChannel"
                        class="lj-channel-select"
                        title="Sales Channel">
                        <option value="physical">📍 In-Person Showroom</option>
                        <option value="instagram">📸 Instagram DM</option>
                        <option value="whatsapp">💬 WhatsApp</option>
                        <option value="showroom">🏛️ Private VIP</option>
                        <option value="event">🎪 Pop-up Event</option>
                        <option value="wholesale">📦 Wholesale</option>
                    </select>
                </div>

                <!-- Line Items Scroll Area -->
                <div class="lj-cart-items-wrap">
                    @if(empty($cart))
                    <div class="lj-empty-cart">
                        <div class="lj-empty-cart-icon">🛒</div>
                        <div style="font-weight: 700; font-size: 0.9375rem; color: var(--lj-text);">Cart is Empty</div>
                        <div style="font-size: 0.75rem; margin-top: 0.25rem;">Scan barcode or click items to add</div>
                    </div>
                    @else
                    @foreach($cart as $cKey => $item)
                    <div class="lj-cart-row">
                        <div class="lj-cart-row-top">
                            <div class="lj-cart-item-info">
                                @if(!empty($item['image']))
                                <img src="/storage/{{ $item['image'] }}" class="lj-cart-thumb" />
                                @else
                                <div class="lj-cart-thumb flex items-center justify-center bg-slate-100 text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                @endif
                                <div style="min-width: 0; flex: 1;">
                                    <div class="lj-cart-title" title="{{ $item['name'] }}">{{ $item['name'] }}</div>
                                    <div class="lj-cart-spec">
                                        {{ $item['sku'] }}
                                        @if(!empty($item['color']) || !empty($item['size']))
                                        · {{ trim(($item['color'] ?? '') . ' ' . ($item['size'] ?? '')) }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <button
                                type="button"
                                wire:click="removeItem('{{ $cKey }}')"
                                class="lj-cart-remove-btn"
                                title="Remove item">
                                ✕
                            </button>
                        </div>

                        <div class="lj-cart-row-bottom">
                            <div class="lj-stepper">
                                <button
                                    type="button"
                                    wire:click="decrementQuantity('{{ $cKey }}')"
                                    class="lj-step-btn"
                                    title="Decrease quantity">
                                    −
                                </button>
                                <input
                                    type="number"
                                    min="1"
                                    value="{{ $item['quantity'] }}"
                                    wire:change="updateQuantity('{{ $cKey }}', $event.target.value)"
                                    class="lj-step-input"
                                    title="Direct quantity edit" />
                                <button
                                    type="button"
                                    wire:click="incrementQuantity('{{ $cKey }}')"
                                    class="lj-step-btn"
                                    title="Increase quantity">
                                    +
                                </button>
                            </div>

                            <div class="text-right">
                                <div class="lj-cart-line-price">
                                    {{ $currSymbol }}{{ number_format($item['total'], 2) }}
                                </div>
                                <div style="font-size: 0.6875rem; color: var(--lj-text-muted);">
                                    @ {{ $currSymbol }}{{ number_format($item['unit_price'], 2) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                    @endif
                </div>

                <!-- Pinned Bottom Checkout Summary Block -->
                <div class="lj-cart-summary">
                    <!-- Subtotal & Discount Lines -->
                    <div class="lj-summary-line">
                        <span>Subtotal ({{ $totals['total_units'] }} items):</span>
                        <span style="font-weight: 700; color: var(--lj-text);">{{ $currSymbol }}{{ number_format($totals['subtotal'], 2) }}</span>
                    </div>

                    <div class="lj-summary-line">
                        <span>
                            Discount:
                            @if($totals['discount'] > 0)
                            <button type="button" wire:click="clearDiscount" style="background: transparent; border: none; color: var(--lj-rose); font-size: 0.6875rem; cursor: pointer; text-decoration: underline;">
                                (Clear)
                            </button>
                            @endif
                        </span>
                        <span>
                            @if($totals['discount'] > 0)
                            <span style="color: var(--lj-rose); font-weight: 700;">-{{ $currSymbol }}{{ number_format($totals['discount'], 2) }}</span>
                            @else
                            <button type="button" wire:click="openModal('discount_modal')" class="lj-discount-trigger">
                                + Add Discount
                            </button>
                            @endif
                        </span>
                    </div>

                    <!-- Role-gated Profit Display (Hidden for Cashiers) -->
                    @if($canSeeMargins && count($cart) > 0)
                    <div class="lj-profit-box">
                        <span>Landed: Rs. {{ number_format($totals['est_cost_npr'], 2) }}</span>
                        <span>Margin: {{ $totals['est_margin'] }}% (Rs. {{ number_format($totals['est_profit_npr'], 2) }})</span>
                    </div>
                    @endif

                    <!-- Dominant Grand Total Row -->
                    <div class="lj-grand-total-row">
                        <span class="lj-grand-total-label">Total Due:</span>
                        <span class="lj-grand-total-amount">{{ $currSymbol }}{{ number_format($totals['total'], 2) }}</span>
                    </div>

                    <!-- Huge Primary Action: Proceed to Checkout (F8) -->
                    <button
                        id="lj-pos-pay-btn"
                        type="button"
                        wire:click="openCheckoutModal"
                        @disabled(empty($cart))
                        class="lj-checkout-btn">
                        <span>💳</span>
                        <span>PROCEED TO PAYMENT (F8) →</span>
                    </button>

                    <!-- Secondary Cart Actions: Hold Sale & Notes -->
                    <div class="lj-cart-actions-row">
                        <button
                            type="button"
                            wire:click="holdCurrentSale"
                            @disabled(empty($cart))
                            class="lj-btn-secondary"
                            title="Suspend current ticket to serve next customer (F9)">
                            <span>⏸️</span>
                            <span>Hold Sale (F9)</span>
                        </button>

                        <button
                            type="button"
                            wire:click="openModal('discount_modal')"
                            @disabled(empty($cart))
                            class="lj-btn-secondary"
                            title="Apply percentage or flat discount">
                            <span>🏷️</span>
                            <span>Discount</span>
                        </button>
                    </div>
                </div>
            </section>
        </main>
        @endif

        <!-- ========================================================================= -->
        <!-- TAB 2: SALES HISTORY VIEW -->
        <!-- ========================================================================= -->
        @if($activeTab === 'history')
        <div style="flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 0.875rem;">
            <!-- Filter Bar -->
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; background: var(--lj-card); padding: 0.75rem; border-radius: 0.75rem; border: 1px solid var(--lj-border);">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="historySearch"
                    placeholder="Search by sale #, customer name, phone, product..."
                    class="lj-search-input"
                    style="flex: 1; min-width: 250px;" />

                <select wire:model.live="historyChannelFilter" class="lj-channel-select" style="min-width: 150px;">
                    <option value="">All Channels</option>
                    <option value="physical">📍 Showroom</option>
                    <option value="instagram">📸 Instagram</option>
                    <option value="whatsapp">💬 WhatsApp</option>
                    <option value="event">🎪 Pop-up</option>
                    <option value="wholesale">📦 Wholesale</option>
                </select>

                <select wire:model.live="historyPaymentFilter" class="lj-channel-select" style="min-width: 150px;">
                    <option value="">All Payments</option>
                    <option value="cash">💵 Cash</option>
                    <option value="esewa">🟢 eSewa</option>
                    <option value="khalti">🟣 Khalti</option>
                    <option value="bank_transfer">🏦 Fonepay / Bank</option>
                    <option value="card">💳 Card</option>
                </select>

                <select wire:model.live="historyDateFilter" class="lj-channel-select" style="min-width: 160px;">
                    <option value="all_time">📅 All Time (2026)</option>
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="this_month">This Month</option>
                    <option value="last_month">Last Month</option>
                    <option value="jan_2026">January 2026</option>
                    <option value="feb_2026">February 2026</option>
                    <option value="mar_2026">March 2026</option>
                    <option value="apr_2026">April 2026</option>
                    <option value="may_2026">May 2026</option>
                    <option value="jun_2026">June 2026</option>
                    <option value="jul_2026">July 2026</option>
                    <option value="aug_2026">August 2026</option>
                    <option value="sep_2026">September 2026</option>
                </select>

                <select wire:model.live="historyStatusFilter" class="lj-channel-select" style="min-width: 130px;">
                    <option value="">All Statuses</option>
                    <option value="completed">Completed</option>
                    <option value="voided">Voided</option>
                </select>
            </div>

            <!-- History Table -->
            <div style="background: var(--lj-card); border-radius: 0.75rem; border: 1px solid var(--lj-border); overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; text-align: left;">
                    <thead>
                        <tr style="background: var(--lj-card-subtle); border-bottom: 1px solid var(--lj-border); color: var(--lj-text-muted); font-size: 0.6875rem; text-transform: uppercase; font-weight: 700;">
                            <th style="padding: 0.75rem 1rem;">Sale #</th>
                            <th style="padding: 0.75rem 1rem;">Date</th>
                            <th style="padding: 0.75rem 1rem;">Customer</th>
                            <th style="padding: 0.75rem 1rem;">Channel</th>
                            <th style="padding: 0.75rem 1rem;">Payment</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Total</th>
                            <th style="padding: 0.75rem 1rem;">Status</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salesHistory as $sale)
                        <tr style="border-bottom: 1px solid var(--lj-border);">
                            <td style="padding: 0.75rem 1rem; font-family: monospace; font-weight: 700;">
                                #{{ $sale['sale_number'] }}
                            </td>
                            <td style="padding: 0.75rem 1rem; color: var(--lj-text-muted);">
                                {{ \Carbon\Carbon::parse($sale['sold_at'])->format('d M Y, h:i A') }}
                            </td>
                            <td style="padding: 0.75rem 1rem; font-weight: 600;">
                                {{ $sale['customer_name'] }}
                                @if(!empty($sale['customer_phone']))
                                <div style="font-size: 0.6875rem; color: var(--lj-text-muted);">{{ $sale['customer_phone'] }}</div>
                                @endif
                            </td>
                            <td style="padding: 0.75rem 1rem;">
                                <span style="font-size: 0.75rem; background: var(--lj-card-subtle); padding: 0.2rem 0.5rem; border-radius: 0.25rem;">
                                    {{ ucfirst($sale['sales_channel']) }}
                                </span>
                            </td>
                            <td style="padding: 0.75rem 1rem;">
                                <span style="font-size: 0.75rem; font-weight: 600;">
                                    {{ ucfirst(str_replace('_', ' ', $sale['payment_method'])) }}
                                </span>
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 800; color: var(--lj-emerald);">
                                {{ \App\Helpers\NepaliNumberHelper::formatCurrency($sale['total_amount'], 'Rs. ', 2) }}
                            </td>
                            <td style="padding: 0.75rem 1rem;">
                                @if($sale['status'] === 'completed')
                                <span style="background: var(--lj-success-light); color: var(--lj-success); font-weight: 700; font-size: 0.6875rem; padding: 0.2rem 0.5rem; border-radius: 9999px;">
                                    Completed
                                </span>
                                @else
                                <span style="background: var(--lj-rose-light); color: var(--lj-rose); font-weight: 700; font-size: 0.6875rem; padding: 0.2rem 0.5rem; border-radius: 9999px;">
                                    Voided
                                </span>
                                @endif
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: right;">
                                <div style="display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center;">
                                    <a
                                        href="{{ route('offline_sales.receipt', ['offlineSale' => $sale['id']]) }}"
                                        target="_blank"
                                        class="lj-btn-secondary"
                                        style="height: 30px; padding: 0 0.5rem; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center;"
                                        title="Open printable receipt">
                                        🖨️ Receipt
                                    </a>
                                    <button
                                        type="button"
                                        wire:click="printViaAgent({{ $sale['id'] }})"
                                        class="lj-btn-secondary"
                                        style="height: 30px; padding: 0 0.5rem; font-size: 0.75rem; font-weight: 700; color: #059669;"
                                        title="Send directly to Showroom Print Agent">
                                        ⚡ Print
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="reprintReceipt({{ $sale['id'] }})"
                                        class="lj-btn-secondary"
                                        style="height: 30px; padding: 0 0.5rem; font-size: 0.75rem; font-weight: 700;"
                                        title="Thermal receipt popup and WhatsApp share">
                                        💬 WA
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="openSaleDetail({{ $sale['id'] }})"
                                        class="lj-btn-secondary"
                                        style="height: 30px; padding: 0 0.5rem; font-size: 0.75rem;">
                                        Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" style="padding: 3rem 1rem; text-align: center; color: var(--lj-text-muted);">
                                No offline sales recorded matching your search.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- ========================================================================= -->
        <!-- TAB 3: DASHBOARD METRICS VIEW -->
        <!-- ========================================================================= -->
        @if($activeTab === 'dashboard')
        <div style="flex: 1; overflow-y: auto; padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                <div style="background: var(--lj-card); border: 1px solid var(--lj-border); border-radius: 0.75rem; padding: 1.25rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); text-transform: uppercase;">Total Offline Revenue</div>
                    <div style="font-size: 1.75rem; font-weight: 900; color: var(--lj-emerald); margin-top: 0.25rem;">
                        {{ $currSymbol }}{{ number_format($dash['total_revenue_npr'] ?? 0, 2) }}
                    </div>
                    <div style="font-size: 0.75rem; color: var(--lj-text-muted); margin-top: 0.25rem;">From {{ $dash['total_sales_count'] ?? 0 }} completed transactions</div>
                </div>

                <div style="background: var(--lj-card); border: 1px solid var(--lj-border); border-radius: 0.75rem; padding: 1.25rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); text-transform: uppercase;">Units Sold</div>
                    <div style="font-size: 1.75rem; font-weight: 900; color: var(--lj-text); margin-top: 0.25rem;">
                        {{ number_format($dash['total_units_sold'] ?? 0) }} pcs
                    </div>
                </div>

                @if($canSeeMargins)
                <div style="background: var(--lj-card); border: 1px solid var(--lj-border); border-radius: 0.75rem; padding: 1.25rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); text-transform: uppercase;">Realized Gross Profit</div>
                    <div style="font-size: 1.75rem; font-weight: 900; color: #059669; margin-top: 0.25rem;">
                        {{ $currSymbol }}{{ number_format($dash['total_profit_npr'] ?? 0, 2) }}
                    </div>
                    <div style="font-size: 0.75rem; color: #059669; font-weight: 700; margin-top: 0.25rem;">
                        {{ number_format($dash['overall_margin_pct'] ?? 0, 1) }}% Margin
                    </div>
                </div>
                @endif

                <div style="background: var(--lj-card); border: 1px solid var(--lj-border); border-radius: 0.75rem; padding: 1.25rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); text-transform: uppercase;">Payment Method Distribution</div>
                    <div style="font-size: 0.8125rem; color: var(--lj-text-muted); margin-top: 0.5rem;">
                        Showroom offline revenue breakdown across Cash, QR, and Card payments.
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- ========================================================================= -->
        <!-- TAB 4: PRODUCT METRICS VIEW -->
        <!-- ========================================================================= -->
        @if($activeTab === 'performance')
        <div style="flex: 1; overflow-y: auto; padding: 1.25rem;">
            <div style="font-weight: 800; font-size: 1.125rem; margin-bottom: 0.75rem; color: var(--lj-text);">
                Product-Level Offline Performance
            </div>
            <div style="background: var(--lj-card); border: 1px solid var(--lj-border); border-radius: 0.75rem; overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; text-align: left;">
                    <thead>
                        <tr style="background: var(--lj-card-subtle); border-bottom: 1px solid var(--lj-border); color: var(--lj-text-muted); font-size: 0.6875rem; text-transform: uppercase; font-weight: 700;">
                            <th style="padding: 0.75rem 1rem;">Product Name</th>
                            <th style="padding: 0.75rem 1rem; text-align: center;">Units Sold</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Total Revenue</th>
                            @if($canSeeMargins)
                            <th style="padding: 0.75rem 1rem; text-align: right;">Gross Margin</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($perf as $item)
                        <tr style="border-bottom: 1px solid var(--lj-border);">
                            <td style="padding: 0.75rem 1rem; font-weight: 700;">{{ $item['product_name'] ?? $item['name'] ?? 'Product' }}</td>
                            <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 600;">{{ $item['units_sold'] ?? $item['offline_units'] ?? 0 }}</td>
                            <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 800; color: var(--lj-emerald);">
                                {{ $currSymbol }}{{ number_format($item['revenue_npr'] ?? $item['offline_revenue'] ?? 0, 2) }}
                            </td>
                            @if($canSeeMargins)
                            <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 700; color: #059669;">
                                {{ number_format($item['margin_pct'] ?? $item['margin_percentage'] ?? 0, 1) }}%
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" style="padding: 3rem 1rem; text-align: center; color: var(--lj-text-muted);">
                                No sales data recorded yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- ========================================================================= -->
        <!-- MODAL 1: CHECKOUT & TENDER MODAL (F8) -->
        <!-- ========================================================================= -->
        @if($activeModal === 'checkout_modal')
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card">
                <div class="lj-modal-header">
                    <div>
                        <div class="lj-modal-title">Complete Showroom Sale</div>
                        <div style="font-size: 0.75rem; color: var(--lj-text-muted);">
                            {{ count($cart) }} Items · {{ $customerName }}
                        </div>
                    </div>
                    <button type="button" wire:click="closeModal" class="lj-modal-close">✕</button>
                </div>

                <div class="lj-modal-body">
                    <!-- Total Payable Banner -->
                    <div style="background: var(--lj-card-subtle); border: 1px solid var(--lj-border); border-radius: 0.75rem; padding: 1rem; text-align: center;">
                        <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--lj-text-muted); letter-spacing: 0.05em;">
                            Total Amount Payable
                        </div>
                        <div style="font-size: 2.25rem; font-weight: 900; color: var(--lj-emerald); margin-top: 0.25rem;">
                            {{ $currSymbol }}{{ number_format($totals['total'], 2) }}
                        </div>
                    </div>

                    <!-- Payment Method Selection -->
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">
                            Select Payment Method
                        </div>
                        <div class="lj-tender-grid">
                            @foreach([
                            'cash' => ['label' => 'Cash', 'icon' => '💵'],
                            'esewa' => ['label' => 'eSewa QR', 'icon' => '🟢'],
                            'khalti' => ['label' => 'Khalti QR', 'icon' => '🟣'],
                            'bank_transfer' => ['label' => 'Fonepay / Bank', 'icon' => '🏦'],
                            'card' => ['label' => 'Card Terminal', 'icon' => '💳'],
                            ] as $pmKey => $pm)
                            <button
                                type="button"
                                wire:click="$set('paymentMethod', '{{ $pmKey }}')"
                                class="lj-pm-pill {{ $paymentMethod === $pmKey ? 'active' : '' }}">
                                <span style="font-size: 1.25rem;">{{ $pm['icon'] }}</span>
                                <span>{{ $pm['label'] }}</span>
                            </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Cash Tendered & Change Section (When Cash is selected) -->
                    @if($paymentMethod === 'cash')
                    <div style="display: flex; flex-direction: column; gap: 0.625rem; background: var(--lj-card-subtle); border: 1px solid var(--lj-border); border-radius: 0.75rem; padding: 1rem;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); text-transform: uppercase;">
                            Cash Received from Customer
                        </div>

                        <div class="lj-tender-input-wrap">
                            <span class="lj-tender-prefix">Rs.</span>
                            <input
                                type="number"
                                step="0.01"
                                wire:model.live="cashReceived"
                                class="lj-tender-input"
                                placeholder="0.00"
                                autofocus />
                        </div>

                        <!-- Quick Cash Helper Chips -->
                        <div class="lj-quick-tender-row">
                            <button type="button" wire:click="setExactCash" class="lj-quick-cash-chip">
                                Exact (Rs. {{ number_format($totals['total'], 0) }})
                            </button>
                            <button type="button" wire:click="addCashReceived(100)" class="lj-quick-cash-chip">+100</button>
                            <button type="button" wire:click="addCashReceived(500)" class="lj-quick-cash-chip">+500</button>
                            <button type="button" wire:click="addCashReceived(1000)" class="lj-quick-cash-chip">+1,000</button>
                            <button type="button" wire:click="addCashReceived(5000)" class="lj-quick-cash-chip">+5,000</button>
                        </div>

                        <!-- Real-time Change Due Display -->
                        @if($cashReceived >= $totals['total'])
                        <div class="lj-change-banner success">
                            <span>🪙 CHANGE TO RETURN:</span>
                            <span style="font-size: 1.25rem;">Rs. {{ number_format($this->cashChange, 2) }}</span>
                        </div>
                        @else
                        <div class="lj-change-banner warning">
                            <span>⚠️ AMOUNT SHORT:</span>
                            <span>Rs. {{ number_format(max(0, $totals['total'] - $cashReceived), 2) }}</span>
                        </div>
                        @endif
                    </div>
                    @endif

                    <!-- Digital / Card Payment Reference -->
                    @if($paymentMethod !== 'cash')
                    <div style="background: var(--lj-card-subtle); border: 1px solid var(--lj-border); border-radius: 0.75rem; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.35rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); text-transform: uppercase;">
                                {{ $paymentMethod === 'card' ? 'Terminal Auth / Slip No.' : 'Digital QR / Txn Reference ID' }}
                            </div>
                            <span style="font-size: 0.6875rem; color: var(--lj-text-muted);">Optional</span>
                        </div>
                        <input
                            type="text"
                            wire:model.defer="internalNotes"
                            placeholder="{{ $paymentMethod === 'card' ? 'e.g. POS Auth #883921 / Card last 4 digits' : 'e.g. eSewa / Khalti Txn ID / Fonepay Trace #' }}"
                            class="lj-search-input"
                            style="height: 38px; font-size: 0.8125rem;" />
                    </div>
                    @endif

                    <!-- Sale Notes / Comments -->
                    <input
                        type="text"
                        wire:model.defer="customerNotes"
                        placeholder="Customer memo or special instructions (optional)..."
                        class="lj-search-input"
                        style="height: 38px; font-size: 0.8125rem;" />
                </div>

                <div class="lj-modal-footer">
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="lj-btn-secondary"
                        style="height: 44px; padding: 0 1.25rem;">
                        Cancel
                    </button>

                    <button
                        type="button"
                        wire:click="completeSale"
                        wire:loading.attr="disabled"
                        @disabled($paymentMethod==='cash' && $cashReceived < $totals['total'])
                        class="lj-checkout-btn"
                        style="width: auto; height: 44px; padding: 0 1.5rem;">
                        <span wire:loading.remove>
                            ✓ COMPLETE SALE & ISSUE RECEIPT
                        </span>
                        <span wire:loading>
                            Processing Sale...
                        </span>
                    </button>
                </div>
            </div>
        </div>
        @endif

        <!-- ========================================================================= -->
        <!-- MODAL 2: THERMAL RECEIPT & PRINT MODAL (80mm) -->
        <!-- ========================================================================= -->
        @if(($activeModal === 'receipt_modal' || $activeModal === 'sale_success') && $receiptData)
        <div class="lj-modal-backdrop">
            <div class="lj-modal-card" style="max-width: 440px;">
                <div class="lj-modal-header">
                    <div>
                        <div class="lj-modal-title">Sale Complete! #{{ $receiptData['sale_number'] }}</div>
                        <div style="font-size: 0.75rem; color: var(--lj-success); font-weight: 700;">
                            ✓ Stock decremented & recorded
                        </div>
                    </div>
                    <button type="button" wire:click="closeModal" class="lj-modal-close">✕</button>
                </div>

                <div class="lj-modal-body" style="background: #F1F5F9; padding: 1rem;">
                    <!-- Printable 80mm Thermal Receipt Layout -->
                    <div class="lj-thermal-wrap lj-thermal-printable">
                        <div class="lj-thermal-center">
                            <div class="lj-thermal-brand">LAIJAU</div>
                            <div style="font-size: 10px; text-transform: uppercase;">Delta Nine business group</div>
                            <div style="font-size: 10px;">Bohara Tol, Kageshwori Manahara 09, Kathmandu</div>
                            <div style="font-size: 10px;">Tel: 9843512095 · PAN/VAT: 604335148</div>
                            <div style="font-size: 10px; font-weight: 700; margin-top: 3px; letter-spacing: 0.05em;">SHOWROOM POS RECEIPT</div>
                            <div style="font-size: 8.5px; font-weight: 800; text-align: center; border: 1.5px dashed #000000; padding: 4px 2px; margin: 6px 0; line-height: 1.3; text-transform: uppercase; background: #fff5f5; color: #18181b;">
                                THIS IS NOT A TAX INVOICE. FOR LAIJAU INTERNAL USE ONLY. PLEASE RETAIN YOUR TAX INVOICE FROM THE COUNTER.
                            </div>
                        </div>

                        <div class="lj-thermal-divider"></div>

                        <div class="lj-thermal-row">
                            <span>Receipt #:</span>
                            <span style="font-weight: 900;">{{ $receiptData['sale_number'] }}</span>
                        </div>
                        <div class="lj-thermal-row">
                            <span>Date & Time:</span>
                            <span>{{ $receiptData['date'] }} {{ $receiptData['time'] }}</span>
                        </div>
                        <div class="lj-thermal-row">
                            <span>Cashier:</span>
                            <span>{{ $receiptData['cashier_name'] }}</span>
                        </div>
                        <div class="lj-thermal-row">
                            <span>Warehouse:</span>
                            <span>{{ $receiptData['warehouse_code'] }}</span>
                        </div>
                        <div class="lj-thermal-row">
                            <span>Customer:</span>
                            <span>{{ $receiptData['customer_name'] }}</span>
                        </div>

                        <div class="lj-thermal-divider"></div>

                        <!-- Itemized Table -->
                        @foreach($receiptData['items'] as $it)
                        <div style="margin-bottom: 4px;">
                            <div style="font-weight: 700; font-size: 11px;">
                                {{ $it['name'] }}
                                @if(!empty($it['color']) || !empty($it['size']))
                                ({{ trim(($it['color'] ?? '') . ' ' . ($it['size'] ?? '')) }})
                                @endif
                            </div>
                            <div class="lj-thermal-row">
                                <span>{{ $it['quantity'] }} x Rs. {{ number_format($it['unit_price'], 2) }}</span>
                                <span style="font-weight: 700;">Rs. {{ number_format($it['total'], 2) }}</span>
                            </div>
                        </div>
                        @endforeach

                        <div class="lj-thermal-divider"></div>

                        <div class="lj-thermal-row">
                            <span>Subtotal:</span>
                            <span>Rs. {{ number_format($receiptData['subtotal'], 2) }}</span>
                        </div>
                        @if($receiptData['discount'] > 0)
                        <div class="lj-thermal-row">
                            <span>Discount:</span>
                            <span>-Rs. {{ number_format($receiptData['discount'], 2) }}</span>
                        </div>
                        @endif

                        <div class="lj-thermal-divider"></div>

                        <div class="lj-thermal-row lj-thermal-total">
                            <span>TOTAL PAID:</span>
                            <span>Rs. {{ number_format($receiptData['total'], 2) }}</span>
                        </div>

                        @php
                        $taxableVal = round($receiptData['total'] / 1.13, 2);
                        $vatVal = round($receiptData['total'] - $taxableVal, 2);
                        @endphp
                        <div class="lj-thermal-row" style="font-size: 9.5px; opacity: 0.8; margin-top: 2px;">
                            <span>Taxable Base:</span>
                            <span>Rs. {{ number_format($taxableVal, 2) }}</span>
                        </div>
                        <div class="lj-thermal-row" style="font-size: 9.5px; opacity: 0.8;">
                            <span>13% VAT (Inclusive):</span>
                            <span>Rs. {{ number_format($vatVal, 2) }}</span>
                        </div>

                        <div class="lj-thermal-divider"></div>

                        <div class="lj-thermal-row">
                            <span>Payment Method:</span>
                            <span style="font-weight: 700;">{{ $receiptData['payment_method'] }}</span>
                        </div>
                        @if(!empty($receiptData['cash_received']))
                        <div class="lj-thermal-row">
                            <span>Cash Tendered:</span>
                            <span>Rs. {{ number_format($receiptData['cash_received'], 2) }}</span>
                        </div>
                        <div class="lj-thermal-row">
                            <span>Change Returned:</span>
                            <span style="font-weight: 700;">Rs. {{ number_format($receiptData['change_given'], 2) }}</span>
                        </div>
                        @endif

                        <div class="lj-thermal-divider"></div>

                        <div class="lj-thermal-center" style="font-size: 10px; margin-top: 8px;">
                            <div style="font-weight: 700;">7-day exchange only. No returns.</div>
                            <div>Thank you for choosing Laijau!</div>
                            <div style="font-size: 9px; margin-top: 4px; opacity: 0.7;">laijau.com • WhatsApp: 9843512095</div>
                        </div>
                    </div>
                </div>

                <div class="lj-modal-footer" style="justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <button
                            type="button"
                            wire:click="printViaAgent({{ $receiptData['id'] }})"
                            class="lj-btn-secondary"
                            style="height: 42px; font-weight: 800; color: #059669; border-color: #059669;"
                            title="Direct hardware ESC/POS print via Showroom Local Print Agent">
                            ⚡ Print Agent
                        </button>

                        <button
                            type="button"
                            onclick="window.print()"
                            class="lj-btn-secondary"
                            style="height: 42px; font-weight: 800;">
                            🖨️ Print Receipt
                        </button>

                        <a
                            href="{{ route('offline_sales.receipt', $receiptData['id']) }}?autoprint=1"
                            target="_blank"
                            class="lj-btn-secondary"
                            style="height: 42px; text-decoration: none; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.25rem;"
                            title="Open clean slip in separate window for network thermal printer">
                            <span>📄 Network Slip</span>
                        </a>

                        <label style="font-size: 0.75rem; color: var(--lj-text-muted); display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer; user-select: none;">
                            <input type="checkbox" id="lj-pos-autoprint-toggle" onchange="localStorage.setItem('lj_pos_autoprint', this.checked ? '1' : '0')">
                            <span>Auto-print</span>
                        </label>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        @if(!empty($whatsappUrl))
                        <a
                            href="{{ $whatsappUrl }}"
                            target="_blank"
                            class="lj-btn-secondary"
                            style="height: 42px; text-decoration: none; color: #059669; font-weight: 800;">
                            💬 WhatsApp
                        </a>
                        @endif

                        <button
                            type="button"
                            wire:click="closeModal"
                            class="lj-checkout-btn"
                            style="width: auto; height: 42px; padding: 0 1.25rem;">
                            ➕ New Sale
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- ========================================================================= -->
        <!-- MODAL 3: HELD / SUSPENDED SALES (F9) -->
        <!-- ========================================================================= -->
        @if($activeModal === 'held_sales')
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card">
                <div class="lj-modal-header">
                    <div class="lj-modal-title">Held / Suspended Sales ({{ count($heldSales) }})</div>
                    <button type="button" wire:click="closeModal" class="lj-modal-close">✕</button>
                </div>

                <div class="lj-modal-body">
                    @forelse($heldSales as $idx => $held)
                    <div style="background: var(--lj-card-subtle); border: 1px solid var(--lj-border); border-radius: 0.625rem; padding: 0.75rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                        <div>
                            <div style="font-weight: 800; font-size: 0.875rem; color: var(--lj-text);">
                                {{ $held['customer_name'] }}
                            </div>
                            <div style="font-size: 0.6875rem; color: var(--lj-text-muted);">
                                Held at {{ $held['held_at'] }} · {{ $held['items_count'] }} items
                            </div>
                            <div style="font-size: 0.9375rem; font-weight: 900; color: var(--lj-emerald); margin-top: 0.25rem;">
                                Rs. {{ number_format($held['total'], 2) }}
                            </div>
                        </div>

                        <div style="display: flex; gap: 0.35rem;">
                            <button
                                type="button"
                                wire:click="restoreHeldSale({{ $idx }})"
                                class="lj-add-btn"
                                style="padding: 0.4rem 0.75rem;">
                                Restore to Cart
                            </button>
                            <button
                                type="button"
                                wire:click="discardHeldSale({{ $idx }})"
                                style="background: transparent; border: 1px solid var(--lj-border); color: var(--lj-rose); border-radius: 0.375rem; padding: 0.4rem 0.5rem; cursor: pointer;"
                                title="Discard held sale">
                                ✕
                            </button>
                        </div>
                    </div>
                    @empty
                    <div style="text-align: center; padding: 2rem 1rem; color: var(--lj-text-muted);">
                        No held sales found.
                    </div>
                    @endforelse
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closeModal" class="lj-btn-secondary" style="height: 38px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
        @endif

        <!-- ========================================================================= -->
        <!-- MODAL 4: UNKNOWN BARCODE ALERT -->
        <!-- ========================================================================= -->
        @if($activeModal === 'unknown_barcode')
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card" style="max-width: 440px; text-align: center;">
                <div class="lj-modal-body" style="padding: 2rem 1.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.75rem;">
                    <div style="width: 56px; height: 56px; border-radius: 9999px; background: var(--lj-rose-light); color: var(--lj-rose); display: flex; align-items: center; justify-content: center; font-size: 1.75rem;">
                        ⚠️
                    </div>

                    <div style="font-size: 1.25rem; font-weight: 800; color: var(--lj-text);">
                        Product not found
                    </div>

                    <div style="font-size: 0.8125rem; color: var(--lj-text-muted);">
                        The scanned barcode or SKU does not exist in active sellable inventory:
                    </div>

                    <div style="font-family: monospace; font-size: 1.1rem; font-weight: 900; background: var(--lj-card-subtle); border: 1px dashed var(--lj-border-strong); padding: 0.5rem 1rem; border-radius: 0.5rem; letter-spacing: 0.05em; color: var(--lj-text);">
                        {{ $unknownBarcode }}
                    </div>

                    <div style="font-size: 0.75rem; color: var(--lj-text-muted);">
                        Never silently ignored. You can search manually by name, add this product, or cancel.
                    </div>
                </div>

                <div class="lj-modal-footer" style="flex-direction: column; gap: 0.5rem;">
                    <button
                        type="button"
                        wire:click="searchManuallyWithBarcode"
                        class="lj-checkout-btn"
                        style="width: 100%; height: 42px;">
                        🔍 Search manually
                    </button>
                    <div style="display: flex; gap: 0.5rem; width: 100%;">
                        <a
                            href="/intadmin/products/create"
                            target="_blank"
                            class="lj-btn-secondary"
                            style="flex: 1; height: 38px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                            ➕ Add product
                        </a>
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="lj-btn-secondary"
                            style="flex: 1; height: 38px;">
                            ✕ Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- ========================================================================= -->
        <!-- MODAL 4A: MOBILE CAMERA BARCODE SCANNER -->
        <!-- ========================================================================= -->
        <div
            x-data="posCameraScanner()"
            @open-pos-camera.window="openScanner()"
            x-show="isOpen"
            x-cloak
            class="lj-modal-backdrop"
            style="z-index: 1000;"
            @keydown.escape.window="closeScanner()">
            <div class="lj-modal-card" style="max-width: 500px; width: 95%;">
                <div class="lj-modal-header" style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 1.25rem;">📷</span>
                        <div>
                            <h3 style="font-size: 1rem; font-weight: 800; color: var(--lj-text); margin: 0;">Camera Barcode Scanner</h3>
                            <p style="font-size: 0.6875rem; color: var(--lj-text-muted); margin: 0;">Point device camera at item barcode or SKU</p>
                        </div>
                    </div>
                    <button type="button" @click="closeScanner()" class="lj-search-clear" style="position: static; font-size: 1.25rem;">✕</button>
                </div>

                <div class="lj-modal-body" style="padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem;">
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
                        <div id="lj-pos-camera-viewport" style="width: 100%;"></div>

                        <!-- Scanning Reticle / Overlay -->
                        <div x-show="isScanning && !hasError" style="position: absolute; inset: 0; pointer-events: none; display: flex; align-items: center; justify-content: center;">
                            <div style="width: 240px; height: 140px; border: 2px solid #10b981; border-radius: 0.5rem; box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.45); position: relative;">
                                <div style="position: absolute; top: 50%; left: 0; right: 0; height: 2px; background: #ef4444; opacity: 0.85; animation: ljLaserPulse 1.8s ease-in-out infinite;"></div>
                            </div>
                        </div>

                        <!-- Error Prompt if denied or unavailable -->
                        <div x-show="hasError" style="position: absolute; inset: 0; background: rgba(15, 23, 42, 0.96); color: #ffffff; padding: 1.5rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 0.75rem; z-index: 10;">
                            <span style="font-size: 2.25rem;">📷⚠️</span>
                            <div style="font-size: 0.8125rem; font-weight: 700; color: #fca5a5; max-width: 320px; line-height: 1.4;" x-text="errorMessage"></div>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: center;">
                                <button type="button" @click="startCamera()" class="lj-btn-primary" style="padding: 0.45rem 0.95rem; font-size: 0.75rem;">
                                    Retry Camera
                                </button>
                                <button type="button" @click="switchCamera()" class="lj-cat-chip" style="color: #ffffff; border-color: rgba(255,255,255,0.25);">
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
                                    class="lj-cat-chip"
                                    style="padding: 0.35rem 0.6rem; font-size: 0.75rem; border-radius: 0.375rem; max-width: 170px; background: var(--lj-card); color: var(--lj-text);">
                                    <template x-for="cam in cameras" :key="cam.id">
                                        <option :value="cam.id" x-text="cam.label || ('Camera ' + cam.id.slice(0, 5))"></option>
                                    </template>
                                </select>
                            </template>
                            <template x-if="cameras.length <= 1">
                                <button type="button" @click="switchCamera()" class="lj-cat-chip" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                    🔄 Switch Cam
                                </button>
                            </template>
                            <button type="button" x-show="hasTorch" @click="toggleTorch()" class="lj-cat-chip" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                <span x-text="torchOn ? '🔦 Light On' : '💡 Light Off'"></span>
                            </button>
                        </div>
                        <div x-show="scanCount > 0" style="font-size: 0.75rem; font-weight: 700; color: var(--lj-emerald);">
                            Scanned: <span x-text="scanCount"></span> items
                        </div>
                    </div>

                    <!-- Last Scanned Feedback Pill -->
                    <div x-show="lastScanned" style="background: #064e3b; border: 1px solid #10b981; border-radius: 0.375rem; padding: 0.5rem 0.75rem; font-size: 0.75rem; color: #ffffff; display: flex; align-items: center; justify-content: space-between;">
                        <span>Detected Barcode: <strong class="lj-mono" x-text="lastScanned"></strong></span>
                        <span>✓ Processed</span>
                    </div>

                    <!-- Manual Barcode Fallback Input -->
                    <div style="border-top: 1px solid var(--lj-border); padding-top: 0.75rem;">
                        <label style="display: block; font-size: 0.6875rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.25rem;">
                            Manual Barcode / SKU Fallback:
                        </label>
                        <div style="display: flex; gap: 0.35rem;">
                            <input
                                type="text"
                                x-model="manualBarcode"
                                @keydown.enter.prevent="submitManualBarcode()"
                                placeholder="Type barcode or SKU..."
                                class="lj-search-input"
                                style="height: 38px; font-size: 0.8125rem; font-family: monospace;" />
                            <button type="button" @click="submitManualBarcode()" class="lj-btn-primary" style="padding: 0 0.85rem; font-size: 0.75rem; height: 38px; border-radius: 0.5rem; flex-shrink: 0;">
                                Add
                            </button>
                        </div>
                    </div>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" @click="closeScanner()" class="lj-btn-default" style="width: 100%; justify-content: center;">
                        Close Scanner
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 4B: CLEAR CART CONFIRMATION -->
        <!-- ========================================================================= -->
        @if($activeModal === 'clear_cart_confirm')
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card" style="max-width: 420px; text-align: center;">
                <div class="lj-modal-body" style="padding: 1.75rem 1.5rem; display: flex; flex-direction: column; align-items: center; gap: 0.75rem;">
                    <div style="width: 52px; height: 52px; border-radius: 9999px; background: var(--lj-rose-light); color: var(--lj-rose); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        🗑️
                    </div>

                    <div style="font-size: 1.15rem; font-weight: 800; color: var(--lj-text);">
                        Clear Active Cart Ticket?
                    </div>

                    <div style="font-size: 0.8125rem; color: var(--lj-text-muted); line-height: 1.4;">
                        Are you sure you want to discard all items currently in this cart ticket?
                        <br>
                        <strong>Physical inventory will NOT be modified</strong> because this sale is uncompleted.
                    </div>
                </div>

                <div class="lj-modal-footer" style="justify-content: center; gap: 0.75rem;">
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="lj-btn-secondary"
                        style="flex: 1; height: 40px;">
                        Keep Cart
                    </button>
                    <button
                        type="button"
                        wire:click="clearCart"
                        style="flex: 1; height: 40px; background: var(--lj-rose); color: #FFFFFF; border: none; border-radius: 0.5rem; font-weight: 800; cursor: pointer;">
                        Yes, Clear Cart
                    </button>
                </div>
            </div>
        </div>
        @endif

        <!-- ========================================================================= -->
        <!-- MODAL 5: VARIANT SELECTOR (RAPID SELECTION) -->
        <!-- ========================================================================= -->
        @if($activeModal === 'variant_select' && $selectedProductForVariant)
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card">
                <div class="lj-modal-header">
                    <div>
                        <div class="lj-modal-title">Select Variant</div>
                        <div style="font-size: 0.75rem; color: var(--lj-text-muted);">
                            {{ $selectedProductForVariant['name'] }}
                        </div>
                    </div>
                    <button type="button" wire:click="closeModal" class="lj-modal-close">✕</button>
                </div>

                <div class="lj-modal-body">
                    @php
                    $vars = $selectedProductForVariant['variants'] ?? [];
                    $colors = collect($vars)->pluck('color')->filter()->unique()->values()->all();
                    $sizes = collect($vars)->pluck('size')->filter()->unique()->values()->all();
                    @endphp

                    <!-- Color Selector -->
                    @if(count($colors) > 0)
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.35rem; text-transform: uppercase;">
                            Color / Pattern
                        </div>
                        <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                            @foreach($colors as $col)
                            <button
                                type="button"
                                wire:click="selectModalVariantColor('{{ $col }}')"
                                class="lj-cat-chip {{ $modalSelectedColor === $col ? 'active' : '' }}">
                                {{ $col }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Size Selector -->
                    @if(count($sizes) > 0)
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.35rem; text-transform: uppercase;">
                            Size
                        </div>
                        <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                            @foreach($sizes as $sz)
                            <button
                                type="button"
                                wire:click="selectModalVariantSize('{{ $sz }}')"
                                class="lj-cat-chip {{ $modalSelectedSize === $sz ? 'active' : '' }}">
                                {{ $sz }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Quantity Stepper -->
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.35rem; text-transform: uppercase;">
                            Quantity
                        </div>
                        <div class="lj-stepper">
                            <button
                                type="button"
                                wire:click="$set('modalSelectedQty', {{ max(1, $modalSelectedQty - 1) }})"
                                class="lj-step-btn">
                                −
                            </button>
                            <span class="lj-step-qty">{{ $modalSelectedQty }}</span>
                            <button
                                type="button"
                                wire:click="$set('modalSelectedQty', {{ $modalSelectedQty + 1 }})"
                                class="lj-step-btn">
                                +
                            </button>
                        </div>
                    </div>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closeModal" class="lj-btn-secondary" style="height: 40px;">
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="addModalVariantToCart"
                        class="lj-checkout-btn"
                        style="width: auto; height: 40px; padding: 0 1.5rem;">
                        + Add to Sale
                    </button>
                </div>
            </div>
        </div>
        @endif

        <!-- ========================================================================= -->
        <!-- MODAL 6: DISCOUNT MODAL -->
        <!-- ========================================================================= -->
        @if($activeModal === 'discount_modal')
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card" style="max-width: 440px;">
                <div class="lj-modal-header">
                    <div class="lj-modal-title">Apply Sale Discount</div>
                    <button type="button" wire:click="closeModal" class="lj-modal-close">✕</button>
                </div>

                <div class="lj-modal-body" x-data="{ fixedAmt: 0, reason: 'Courtesy Discount' }">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">
                            Quick Percentage Discount
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem;">
                            @foreach([5, 10, 15, 20] as $pct)
                            <button
                                type="button"
                                wire:click="applyDiscountPercent({{ $pct }})"
                                class="lj-btn-secondary"
                                style="height: 42px; font-size: 0.9375rem; font-weight: 800; color: var(--lj-emerald);">
                                {{ $pct }}%
                            </button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.35rem; text-transform: uppercase;">
                            Or Custom Fixed Amount (Rs.)
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <input
                                type="number"
                                x-model="fixedAmt"
                                placeholder="e.g. 500"
                                class="lj-search-input"
                                style="height: 42px;" />
                            <button
                                type="button"
                                @click="$wire.applyFixedDiscount(fixedAmt, reason)"
                                class="lj-checkout-btn"
                                style="width: auto; height: 42px; padding: 0 1rem;">
                                Apply
                            </button>
                        </div>
                    </div>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closeModal" class="lj-btn-secondary" style="height: 38px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
        @endif

        <!-- ========================================================================= -->
        <!-- MODAL 7: CUSTOMER SELECTOR / REGISTRATION -->
        <!-- ========================================================================= -->
        @if($activeModal === 'customer_modal')
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card">
                <div class="lj-modal-header">
                    <div class="lj-modal-title">Select Customer</div>
                    <button type="button" wire:click="closeModal" class="lj-modal-close">✕</button>
                </div>

                <div class="lj-modal-body">
                    <!-- Walk-in Shortcut -->
                    <button
                        type="button"
                        wire:click="selectWalkInCustomer"
                        style="width: 100%; padding: 0.75rem; background: var(--lj-card-subtle); border: 1.5px dashed var(--lj-border); border-radius: 0.5rem; text-align: left; font-weight: 700; cursor: pointer;">
                        🚶 Walk-in Showroom Customer (Default)
                    </button>

                    <!-- Search Existing Customers -->
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.35rem; text-transform: uppercase;">
                            Search Existing Customer
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="customerSearchQuery"
                            placeholder="Search by name, phone or email..."
                            class="lj-search-input"
                            style="height: 40px;" />

                        <div style="max-height: 280px; overflow-y: auto; margin-top: 0.5rem; display: flex; flex-direction: column; gap: 0.25rem;">
                            @forelse($customersList as $cust)
                            <button
                                type="button"
                                wire:click="selectCustomer({{ $cust['id'] }})"
                                style="padding: 0.5rem; text-align: left; border: 1px solid var(--lj-border); border-radius: 0.375rem; background: var(--lj-card); cursor: pointer; display: flex; justify-content: space-between;">
                                <span style="font-weight: 700;">{{ $cust['name'] }}</span>
                                <span style="color: var(--lj-text-muted); font-size: 0.75rem;">{{ $cust['phone'] ?? $cust['email'] }}</span>
                            </button>
                            @empty
                            <div style="font-size: 0.75rem; color: var(--lj-text-muted); padding: 0.5rem; text-align: center;">
                                No registered customers found.
                            </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Quick Add Customer -->
                    <div style="background: var(--lj-card-subtle); border: 1px solid var(--lj-border); border-radius: 0.5rem; padding: 0.75rem;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">
                            + Register New Customer
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                            <input type="text" wire:model.defer="newCustName" placeholder="Full Name *" class="lj-search-input" style="height: 36px; font-size: 0.8125rem;" />
                            <input type="text" wire:model.defer="newCustPhone" placeholder="Phone (e.g. 9841234567)" class="lj-search-input" style="height: 36px; font-size: 0.8125rem;" />
                            <input type="email" wire:model.defer="newCustEmail" placeholder="Email (optional)" class="lj-search-input" style="height: 36px; font-size: 0.8125rem;" />
                            <button
                                type="button"
                                wire:click="createAndAttachCustomer"
                                class="lj-checkout-btn"
                                style="height: 36px; font-size: 0.8125rem;">
                                Register & Attach
                            </button>
                        </div>
                    </div>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closeModal" class="lj-btn-secondary" style="height: 38px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
        @endif

        <!-- ========================================================================= -->
        <!-- MODAL 8: SALE DETAIL MODAL -->
        <!-- ========================================================================= -->
        @if($activeModal === 'sale_detail' && $selectedSaleDetail)
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card" style="max-width: 580px;">
                <div class="lj-modal-header">
                    <div>
                        <div class="lj-modal-title">Sale #{{ $selectedSaleDetail['sale_number'] }}</div>
                        <div style="font-size: 0.75rem; color: var(--lj-text-muted);">
                            {{ \Carbon\Carbon::parse($selectedSaleDetail['sold_at'])->format('d M Y, h:i A') }}
                        </div>
                    </div>
                    <button type="button" wire:click="closeModal" class="lj-modal-close">✕</button>
                </div>

                <div class="lj-modal-body">
                    <div style="display: flex; justify-content: space-between; background: var(--lj-card-subtle); padding: 0.75rem; border-radius: 0.5rem;">
                        <div>
                            <div style="font-size: 0.75rem; color: var(--lj-text-muted);">Customer</div>
                            <div style="font-weight: 700;">{{ $selectedSaleDetail['customer_name'] }}</div>
                            <div style="font-size: 0.75rem;">{{ $selectedSaleDetail['customer_phone'] }}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; color: var(--lj-text-muted);">Payment & Channel</div>
                            <div style="font-weight: 700;">{{ ucfirst(str_replace('_', ' ', $selectedSaleDetail['payment_method'])) }}</div>
                            <div style="font-size: 0.75rem;">{{ ucfirst($selectedSaleDetail['sales_channel']) }}</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.75rem; color: var(--lj-text-muted);">Total Amount</div>
                            <div style="font-size: 1.25rem; font-weight: 900; color: var(--lj-emerald);">
                                Rs. {{ number_format($selectedSaleDetail['total_amount'], 2) }}
                            </div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); text-transform: uppercase;">
                            Line Items ({{ count($selectedSaleDetail['items'] ?? []) }})
                        </div>
                        @foreach($selectedSaleDetail['items'] ?? [] as $it)
                        <div style="display: flex; justify-content: space-between; padding: 0.4rem 0.5rem; background: var(--lj-card); border: 1px solid var(--lj-border); border-radius: 0.375rem; font-size: 0.8125rem;">
                            <div>
                                <span style="font-weight: 700;">{{ $it['product_name'] }}</span>
                                @if(!empty($it['color']) || !empty($it['size']))
                                <span style="color: var(--lj-text-muted);">({{ trim(($it['color'] ?? '') . ' ' . ($it['size'] ?? '')) }})</span>
                                @endif
                                <span style="font-family: monospace; font-size: 0.7rem; opacity: 0.7;">· {{ $it['sku'] }}</span>
                            </div>
                            <div>
                                <span>{{ $it['quantity'] }} x Rs. {{ number_format($it['unit_price'], 2) }}</span>
                                <span style="font-weight: 800; margin-left: 0.5rem;">Rs. {{ number_format($it['total_price'], 2) }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="lj-modal-footer" style="justify-content: space-between;">
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        @if($selectedSaleDetail['status'] === 'completed')
                        <button
                            type="button"
                            wire:click="openVoidModal({{ $selectedSaleDetail['id'] }})"
                            style="background: transparent; border: 1px solid var(--lj-rose); color: var(--lj-rose); font-size: 0.75rem; font-weight: 700; padding: 0.4rem 0.75rem; border-radius: 0.375rem; cursor: pointer;">
                            Void / Return Sale
                        </button>
                        @else
                        <span style="color: var(--lj-rose); font-weight: 700; font-size: 0.75rem;">
                            Voided on {{ \Carbon\Carbon::parse($selectedSaleDetail['updated_at'])->format('d M Y') }}
                        </span>
                        @endif

                        <button
                            type="button"
                            wire:click="reprintReceipt({{ $selectedSaleDetail['id'] }})"
                            class="lj-btn-secondary"
                            style="height: 38px; font-weight: 700;">
                            🖨️ Thermal Receipt
                        </button>
                    </div>

                    <button type="button" wire:click="closeModal" class="lj-btn-secondary" style="height: 38px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
        @endif

        <!-- ========================================================================= -->
        <!-- MODAL 9: VOID SALE CONFIRMATION -->
        <!-- ========================================================================= -->
        @if($activeModal === 'void_modal')
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card" style="max-width: 440px;">
                <div class="lj-modal-header">
                    <div class="lj-modal-title" style="color: var(--lj-rose);">Void Sale #{{ $voidSaleNumber }}</div>
                    <button type="button" wire:click="closeModal" class="lj-modal-close">✕</button>
                </div>

                <div class="lj-modal-body">
                    <div style="font-size: 0.8125rem; color: var(--lj-text-muted);">
                        Are you sure you want to void this offline sale? This action reverses the revenue transaction.
                    </div>

                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.35rem;">
                            Reason for Void:
                        </div>
                        <input
                            type="text"
                            wire:model.defer="voidReason"
                            class="lj-search-input"
                            style="height: 40px;" />
                    </div>

                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" wire:model.defer="voidRestockInventory" />
                        <span>Restock inventory items back to Showroom warehouse</span>
                    </label>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closeModal" class="lj-btn-secondary" style="height: 38px;">
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="processVoidSale"
                        style="background: var(--lj-rose); color: #FFFFFF; border: none; border-radius: 0.5rem; padding: 0.5rem 1.25rem; font-weight: 800; cursor: pointer;">
                        Confirm Void
                    </button>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- ========================================================================= -->
    <!-- POS KEYBOARD SHORTCUTS & HARDWARE SCANNER INTEGRATION -->
    <!-- ========================================================================= -->
    <script>
        let barcodeBuffer = '';
        let lastKeyTime = Date.now();

        window.addEventListener('focus-search', () => {
            setTimeout(() => {
                const searchEl = document.getElementById('lj-pos-search-input');
                if (searchEl) {
                    searchEl.focus();
                    searchEl.select();
                }
            }, 50);
        });

        document.addEventListener('keydown', function(e) {
            const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
            const isInput = activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select';
            const isSearchInput = document.activeElement && document.activeElement.id === 'lj-pos-search-input';

            // F8: Trigger Payment & Checkout
            if (e.key === 'F8') {
                e.preventDefault();
                const payBtn = document.getElementById('lj-pos-pay-btn');
                if (payBtn && !payBtn.disabled) {
                    payBtn.click();
                }
                return;
            }

            // F9: Hold Current Sale
            if (e.key === 'F9') {
                e.preventDefault();
                @this.holdCurrentSale();
                return;
            }

            // Slash '/' or Ctrl+K: Focus Search Input
            if ((e.key === '/' && !isInput) || ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K'))) {
                e.preventDefault();
                const searchEl = document.getElementById('lj-pos-search-input');
                if (searchEl) {
                    searchEl.focus();
                    searchEl.select();
                }
                return;
            }

            // Escape: Close active modal
            if (e.key === 'Escape') {
                @this.closeModal();
                return;
            }

            // 'P' key when receipt modal is active triggers print
            const isReceiptOpen = document.querySelector('.lj-thermal-printable') !== null;
            if (isReceiptOpen && !isInput && (e.key === 'p' || e.key === 'P')) {
                e.preventDefault();
                window.print();
                return;
            }

            // Hardware USB Barcode Scanner Buffer:
            // Hardware scanners emit characters at rapid bursts (< 120ms) terminated by 'Enter'
            const currentTime = Date.now();
            const timeDiff = currentTime - lastKeyTime;
            lastKeyTime = currentTime;

            if (e.key === 'Enter') {
                if (isSearchInput) {
                    const searchInputEl = document.getElementById('lj-pos-search-input');
                    const code = searchInputEl ? searchInputEl.value.trim() : '';
                    if (code.length >= 2) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (searchInputEl) searchInputEl.value = '';
                        barcodeBuffer = '';
                        @this.handleBarcodeScan(code);
                        return;
                    }
                } else if (barcodeBuffer.length >= 2) {
                    e.preventDefault();
                    e.stopPropagation();
                    const code = barcodeBuffer.trim();
                    barcodeBuffer = '';
                    if (code.length > 0) {
                        @this.handleBarcodeScan(code);
                    }
                    return;
                }
                barcodeBuffer = '';
            } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                if (timeDiff > 120) {
                    // Gap too long for hardware scanner burst, reset buffer
                    barcodeBuffer = '';
                }
                if (!isInput || isSearchInput) {
                    barcodeBuffer += e.key;
                }
            }
        });

        // Initialize auto-print checkbox state from localStorage
        function syncAutoPrintCheckbox() {
            const cb = document.getElementById('lj-pos-autoprint-toggle');
            if (cb) {
                cb.checked = localStorage.getItem('lj_pos_autoprint') === '1';
            }
        }
        document.addEventListener('DOMContentLoaded', syncAutoPrintCheckbox);
        document.addEventListener('livewire:navigated', syncAutoPrintCheckbox);

        // Auto-print event listener dispatched from Livewire completeSale
        window.addEventListener('sale-completed-print', () => {
            syncAutoPrintCheckbox();
            if (localStorage.getItem('lj_pos_autoprint') === '1') {
                setTimeout(() => {
                    window.print();
                }, 350);
            }
        });
    </script>

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

                    // Check secure context (HTTPS or localhost)
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
                            // Default to back/environment camera if available
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
                            'Camera access blocked by browser: Camera requires HTTPS when accessed from a phone/remote device. Please open via HTTPS or type barcode manually.' :
                            'Camera API is not supported on this browser or permission is disabled.';
                        return;
                    }

                    try {
                        if (this.html5QrCode && this.html5QrCode.isScanning) {
                            await this.stopCamera();
                        }

                        const viewport = document.getElementById("lj-pos-camera-viewport");
                        if (viewport) {
                            viewport.innerHTML = '';
                        }

                        this.html5QrCode = new Html5Qrcode("lj-pos-camera-viewport", {
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

                        const config = {
                            fps: 15,
                            qrbox: (viewfinderWidth, viewfinderHeight) => {
                                const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                                const qrboxWidth = Math.floor(minEdge * 0.85);
                                const qrboxHeight = Math.floor(qrboxWidth * 0.65);
                                return { width: Math.max(220, qrboxWidth), height: Math.max(140, qrboxHeight) };
                            },
                            aspectRatio: 1.333334,
                            videoConstraints: {
                                facingMode: { ideal: this.facingMode },
                                focusMode: "continuous"
                            }
                        };

                        let lastCode = '';
                        let lastTime = 0;

                        const onScanSuccess = (decodedText) => {
                            const now = Date.now();
                            if (decodedText === lastCode && (now - lastTime) < 1500) {
                                return;
                            }
                            lastCode = decodedText;
                            lastTime = now;
                            this.onBarcodeDetected(decodedText);
                        };

                        // 1. Try selected deviceId if present
                        let started = false;
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
                                console.warn("Failed starting camera by deviceId, falling back to facingMode:", eDevice);
                            }
                        }

                        // 2. Fallback to ideal facingMode
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
                                console.warn("Failed with ideal facingMode, falling back to default camera:", eFacing);
                            }
                        }

                        // 3. Fallback to basic constraints if mobile browser rejects facingMode constraint
                        if (!started) {
                            await this.html5QrCode.start(
                                { facingMode: this.facingMode },
                                config,
                                onScanSuccess,
                                () => {}
                            );
                        }

                        // Check torch capability
                        try {
                            const track = this.html5QrCode.getRunningTrackCapabilities();
                            this.hasTorch = !!track?.torch;
                        } catch (e) {
                            this.hasTorch = false;
                        }

                        // Ensure video element has playsinline for iOS Safari
                        const videoEl = document.querySelector("#lj-pos-camera-viewport video");
                        if (videoEl) {
                            videoEl.setAttribute("playsinline", "true");
                            videoEl.setAttribute("webkit-playsinline", "true");
                            videoEl.setAttribute("muted", "true");
                            videoEl.setAttribute("autoplay", "true");
                            videoEl.style.objectFit = "cover";
                            videoEl.style.width = "100%";
                            videoEl.style.height = "100%";
                        }

                        // Reload camera list once permission is granted
                        if (this.cameras.length === 0) {
                            await this.loadCameras();
                        }
                    } catch (err) {
                        console.error("Camera scanner start error:", err);
                        this.isScanning = false;
                        this.hasError = true;

                        const msg = (err?.message || '').toLowerCase();
                        const name = err?.name || '';

                        if (name === 'NotAllowedError' || msg.includes('permission') || msg.includes('denied')) {
                            this.errorMessage = 'Camera permission denied. Please allow camera access in your phone browser settings, then tap Retry Camera.';
                        } else if (name === 'NotFoundError' || msg.includes('not found') || msg.includes('devicesnotfound')) {
                            this.errorMessage = 'No camera device detected on this mobile phone.';
                        } else if (name === 'NotReadableError' || msg.includes('busy') || msg.includes('in use')) {
                            this.errorMessage = 'Camera is in use by another app. Please close other camera apps and retry.';
                        } else if (this.isInsecureContext) {
                            this.errorMessage = 'Camera blocked by browser: Accessing over HTTP from another device is restricted by iOS/Chrome. Please use HTTPS or type SKU manually.';
                        } else {
                            this.errorMessage = 'Unable to open camera: ' + (err?.message || 'Camera initialization failed');
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