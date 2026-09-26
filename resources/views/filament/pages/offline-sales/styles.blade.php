    <style>
        /* DEDICATED FULLSCREEN POS WORKSPACE RESET */
        html:has(.lj-pos-fullscreen-app),
        body.fi-body:has(.lj-pos-fullscreen-app),
        body:has(.lj-pos-fullscreen-app) {
            overflow: hidden !important;
            width: 100vw !important;
            max-width: 100vw !important;
            height: 100vh !important;
            max-height: 100vh !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #F8FAFC !important;
        }

        .lj-pos-fullscreen-app {
            position: fixed !important;
            inset: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            max-width: 100vw !important;
            max-height: 100vh !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
            box-sizing: border-box !important;
            background: #F8FAFC !important;
            z-index: 50 !important;
        }

        .lj-pos-fullscreen-app .lj-pos-root {
            width: 100% !important;
            height: 100vh !important;
            max-height: 100vh !important;
            min-height: 100vh !important;
            margin: 0 !important;
            padding: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
            border-radius: 0 !important;
            border: none !important;
        }

        .lj-pos-fullscreen-app .lj-pos-topbar {
            flex-shrink: 0 !important;
            border-radius: 0 !important;
            border-left: none !important;
            border-right: none !important;
            border-top: none !important;
            margin: 0 !important;
        }

        .lj-pos-fullscreen-app .lj-pos-main {
            flex: 1 1 auto !important;
            height: calc(100vh - 54px) !important;
            max-height: calc(100vh - 54px) !important;
            min-height: 0 !important;
            overflow: hidden !important;
            padding: 0.75rem !important;
            box-sizing: border-box !important;
        }

        /* 1. HIDE DEFAULT FILAMENT HEADER & RESET CONTAINER ON POS PAGE */
        .fi-header,
        .fi-page-header,
        .fi-header-heading,
        .fi-breadcrumbs {
            display: none !important;
        }

        [x-cloak] {
            display: none !important;
        }

        /* FILAMENT EDGE-TO-EDGE LAYOUT RESET */
        .fi-main:has(.lj-pos-root),
        body:has(.lj-pos-root) .fi-main {
            padding-left: 0 !important;
            padding-right: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            max-width: 100% !important;
        }

        .fi-page:has(.lj-pos-root) {
            padding: 0 !important;
            gap: 0 !important;
        }

        /* 2. SCOPED POS DESIGN SYSTEM (ENFORCED LIGHT MODE) */
        :root,
        .lj-pos-root,
        .dark .lj-pos-root,
        .lj-modal-backdrop,
        .dark .lj-modal-backdrop {
            --lj-emerald: #059669;
            --lj-emerald-hover: #047857;
            --lj-emerald-light: #ECFDF5;
            --lj-emerald-border: #A7F3D0;
            --lj-gold: #D97706;
            --lj-gold-light: #FEF3C7;
            --lj-gold-border: #FCD34D;
            --lj-bg: #F8FAFC;
            --lj-card: #FFFFFF;
            --lj-card-subtle: #F1F5F9;
            --lj-border: #E2E8F0;
            --lj-border-strong: #CBD5E1;
            --lj-text: #0F172A;
            --lj-text-muted: #64748B;
            --lj-rose: #DC2626;
            --lj-rose-light: #FEF2F2;
            --lj-success: #059669;
            --lj-success-light: #ECFDF5;
            --lj-amber: #D97706;
            --lj-amber-light: #FEF3C7;
        }

        .dark .lj-pos-root,
        .dark .lj-modal-card {
            background-color: #FFFFFF !important;
            color: #0F172A !important;
        }

        /* POS Root Container */
        .lj-pos-root {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--lj-text);
            background: var(--lj-bg);
            height: calc(100vh - 4rem);
            max-height: calc(100vh - 4rem);
            min-height: 600px;
            width: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-sizing: border-box;
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

        .lj-warehouse-badge, .lj-terminal-badge {
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

        .lj-warehouse-badge:hover, .lj-terminal-badge:hover {
            border-color: var(--lj-emerald);
        }

        .lj-pos-session-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
            border: 1px solid transparent;
        }

        .lj-pos-session-badge.active {
            background: #ECFDF5;
            color: #065F46;
            border-color: #A7F3D0;
        }

        .lj-pos-session-badge.opening-required {
            background: #FEF3C7;
            color: #92400E;
            border-color: #FCD34D;
            animation: ljPulse 2s infinite;
        }

        .lj-pos-session-badge.closing-required {
            background: #FEE2E2;
            color: #991B1B;
            border-color: #F87171;
            animation: ljPulse 1.5s infinite;
        }

        .lj-pos-session-badge.closed {
            background: #F1F5F9;
            color: #475569;
            border-color: #CBD5E1;
        }

        .lj-pulse-dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
        }

        @keyframes ljPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.75; transform: scale(0.96); }
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--lj-emerald-light);
            color: var(--lj-emerald);
            border: 1px solid var(--lj-emerald-border);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.25rem 0.6rem;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.15s ease;
            user-select: none;
            pointer-events: none;
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

        /* Ensure Filament toast notifications appear above all modals & backdrops */
        .fi-no,
        .fi-no-notification,
        .fi-notifications {
            z-index: 100000 !important;
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
                animation: ljModalSlideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
            }

            @keyframes ljModalSlideUp {
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

        /* PRINT STYLES: 80mm THERMAL & A4 STANDARD ISOLATION */
        @media print {
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

            .lj-a4-printable,
            .lj-a4-printable * {
                visibility: visible !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .lj-a4-printable {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                max-width: 210mm !important;
                margin: 0 auto !important;
                padding: 12mm 15mm !important;
                background: #ffffff !important;
                box-shadow: none !important;
                border: none !important;
            }
        }

        /* ========================================================================= */
        /* UPGRADED POS DASHBOARD ANALYTICS SYSTEM                                  */
        /* ========================================================================= */
        .lj-pos-dash-wrap {
            flex: 1;
            overflow-y: auto;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            box-sizing: border-box;
            background: var(--lj-bg);
            scrollbar-width: thin;
        }

        .lj-pos-dash-header {
            background: var(--lj-card);
            border: 1px solid var(--lj-border);
            border-radius: 0.875rem;
            padding: 1rem 1.25rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .lj-pos-dash-title-wrap {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .lj-pos-dash-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--lj-text);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .lj-pos-dash-subtitle {
            font-size: 0.75rem;
            color: var(--lj-text-muted);
            font-weight: 500;
        }

        .lj-pos-dash-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }

        .lj-pos-period-btn {
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid var(--lj-border);
            background: var(--lj-card-subtle);
            color: var(--lj-text-muted);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .lj-pos-period-btn:hover {
            border-color: var(--lj-emerald);
            color: var(--lj-text);
            background: var(--lj-card);
        }

        .lj-pos-period-btn.active {
            background: var(--lj-emerald) !important;
            color: #ffffff !important;
            border-color: var(--lj-emerald) !important;
            box-shadow: 0 1px 4px rgba(10, 46, 35, 0.25);
        }

        .dark .lj-pos-period-btn.active {
            background: var(--lj-gold) !important;
            color: #0F172A !important;
            border-color: var(--lj-gold) !important;
        }

        .lj-pos-dash-select {
            height: 32px;
            padding: 0 0.65rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.5rem;
            border: 1px solid var(--lj-border);
            background: var(--lj-card);
            color: var(--lj-text);
            cursor: pointer;
            outline: none;
        }

        .lj-pos-dash-select:focus {
            border-color: var(--lj-emerald);
        }

        .lj-pos-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 0.875rem;
        }

        .lj-pos-kpi-card {
            background: var(--lj-card);
            border: 1px solid var(--lj-border);
            border-radius: 0.875rem;
            padding: 1rem 1.15rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .lj-pos-kpi-card:hover {
            transform: translateY(-1px);
            border-color: var(--lj-border-strong);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .lj-pos-kpi-label {
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--lj-text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .lj-pos-kpi-value {
            font-size: 1.65rem;
            font-weight: 900;
            line-height: 1.2;
            margin-top: 0.35rem;
            color: var(--lj-text);
            font-family: inherit;
        }

        .lj-pos-kpi-subtext {
            font-size: 0.7125rem;
            color: var(--lj-text-muted);
            margin-top: 0.35rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .lj-pos-growth-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            font-size: 0.6875rem;
            font-weight: 700;
            padding: 0.125rem 0.4rem;
            border-radius: 9999px;
        }

        .lj-pos-growth-up {
            background: #ECFDF5;
            color: #059669;
        }

        .dark .lj-pos-growth-up {
            background: rgba(16, 185, 129, 0.15);
            color: #34D399;
        }

        .lj-pos-growth-down {
            background: #FFF1F2;
            color: #E11D48;
        }

        .dark .lj-pos-growth-down {
            background: rgba(225, 29, 72, 0.15);
            color: #FB7185;
        }

        .lj-pos-analytics-2col {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 1200px) {
            .lj-pos-analytics-2col {
                grid-template-columns: 1fr;
            }
        }

        .lj-pos-card {
            background: var(--lj-card);
            border: 1px solid var(--lj-border);
            border-radius: 0.875rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
        }

        .lj-pos-card-header {
            padding: 0.875rem 1.15rem;
            border-bottom: 1px solid var(--lj-border);
            background: var(--lj-card-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .lj-pos-card-title {
            font-size: 0.875rem;
            font-weight: 800;
            color: var(--lj-text);
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .lj-pos-card-body {
            padding: 1.15rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .lj-pos-split-bar-wrap {
            display: flex;
            height: 14px;
            width: 100%;
            border-radius: 9999px;
            overflow: hidden;
            background: #E2E8F0;
        }

        .dark .lj-pos-split-bar-wrap {
            background: #334155;
        }

        .lj-pos-split-bar-cash {
            background: #059669;
            transition: width 0.3s ease;
        }

        .lj-pos-split-bar-digital {
            background: #2563EB;
            transition: width 0.3s ease;
        }

        .lj-pos-pm-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.65rem 0.75rem;
            border-radius: 0.5rem;
            background: var(--lj-card-subtle);
            border: 1px solid var(--lj-border);
            transition: background 0.15s ease;
        }

        .lj-pos-pm-row:hover {
            background: var(--lj-card);
            border-color: var(--lj-border-strong);
        }

        .lj-pos-hourly-chart {
            display: flex;
            align-items: flex-end;
            gap: 0.35rem;
            height: 140px;
            padding: 0.75rem 0.25rem 0.25rem 0.25rem;
            border-bottom: 1px solid var(--lj-border);
            overflow-x: auto;
            box-sizing: border-box;
        }

        .lj-pos-hourly-col {
            flex: 1;
            min-width: 28px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            height: 100%;
            position: relative;
        }

        .lj-pos-hourly-bar-fill {
            width: 100%;
            max-width: 22px;
            border-radius: 4px 4px 0 0;
            background: linear-gradient(180deg, #C5A059 0%, #0A2E23 100%);
            transition: height 0.3s ease;
            position: relative;
        }

        .lj-pos-hourly-bar-fill.peak {
            background: linear-gradient(180deg, #F59E0B 0%, #D97706 100%) !important;
            box-shadow: 0 0 8px rgba(245, 158, 11, 0.4);
        }

        .lj-pos-hourly-label {
            font-size: 0.625rem;
            font-weight: 600;
            color: var(--lj-text-muted);
            margin-top: 0.35rem;
            white-space: nowrap;
        }
    </style>
