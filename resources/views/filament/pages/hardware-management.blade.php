<x-filament-panels::page class="w-full max-w-full">

    @php
        $station = $this->getStation();
        $summary = $this->getHardwareSummary();
        $discovered = $this->getDiscoveredPrinters();
        $jobs = $this->getPrintJobs();
        $auditLogs = $this->getAuditLogs();
        $printer = $station->receiptPrinter;
    @endphp

    <style>
        /* =====================================================================
           LAIJAU HARDWARE & PRINTING CONTROL CENTER — MASTER STYLESHEET
           ===================================================================== */
        .hw-wrap {
            width: 100%;
            max-width: 100%;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            font-family: inherit;
            box-sizing: border-box;
            color: #0f172a;
        }
        .dark .hw-wrap {
            color: #f8fafc;
        }
        .hw-wrap * {
            box-sizing: border-box;
        }

        /* Strict icon sizing rules (prevent SVG blowout) */
        .hw-wrap svg {
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
            max-width: 100%;
        }
        .hw-icon-xs {
            width: 14px !important;
            height: 14px !important;
            min-width: 14px !important;
            max-width: 14px !important;
        }
        .hw-icon-sm {
            width: 16px !important;
            height: 16px !important;
            min-width: 16px !important;
            max-width: 16px !important;
        }
        .hw-icon-md {
            width: 20px !important;
            height: 20px !important;
            min-width: 20px !important;
            max-width: 20px !important;
        }
        .hw-icon-lg {
            width: 24px !important;
            height: 24px !important;
            min-width: 24px !important;
            max-width: 24px !important;
        }

        /* Pulse animation for live indicators */
        @keyframes hw-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.35; transform: scale(0.85); }
        }
        .hw-pulse-dot {
            animation: hw-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        /* Split grid layout for 2-column tabs */
        .hw-split-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            align-items: start;
        }
        @media (min-width: 1024px) {
            .hw-split-grid {
                grid-template-columns: 1.25fr 1fr;
            }
        }

        /* 1. Executive Hero Banner */
        .hw-hero {
            background: linear-gradient(135deg, #001b48 0%, #002566 50%, #001438 100%);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 1rem;
            padding: 1.5rem 1.75rem;
            color: #ffffff;
            box-shadow: 0 4px 20px rgba(0, 27, 72, 0.25);
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .hw-hero-top {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        @media (min-width: 1024px) {
            .hw-hero-top {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }
        .hw-hero-title-area {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            max-width: 44rem;
        }
        .hw-badge-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }
        .hw-pill-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: #ffffff;
        }
        .hw-pill-tag.emerald {
            background: rgba(16, 185, 129, 0.2);
            border-color: rgba(16, 185, 129, 0.4);
            color: #6ee7b7;
        }
        .hw-hero-heading {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.25;
            color: #ffffff;
            margin: 0;
        }
        @media (min-width: 640px) {
            .hw-hero-heading {
                font-size: 1.75rem;
            }
        }
        .hw-hero-desc {
            font-size: 0.8125rem;
            line-height: 1.5;
            color: #cbd5e1;
            margin: 0;
        }
        .hw-hero-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
        }

        /* Buttons */
        .hw-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.125rem;
            border-radius: 0.625rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
            border: 1px solid transparent;
            text-decoration: none;
            line-height: 1;
            white-space: nowrap;
        }
        .hw-btn:active {
            transform: scale(0.98);
        }
        .hw-btn-emerald {
            background: #10b981;
            color: #ffffff;
            border-color: #059669;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.35);
        }
        .hw-btn-emerald:hover {
            background: #059669;
        }
        .hw-btn-navy {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(8px);
        }
        .hw-btn-navy:hover {
            background: rgba(255, 255, 255, 0.25);
        }
        .hw-btn-primary {
            background: #001b48;
            color: #ffffff;
            border-color: #001438;
        }
        .hw-btn-primary:hover {
            background: #002566;
        }
        .hw-btn-outline {
            background: #ffffff;
            color: #374151;
            border-color: #d1d5db;
        }
        .hw-btn-outline:hover {
            background: #f9fafb;
            color: #111827;
        }
        .dark .hw-btn-outline {
            background: #1f2937;
            color: #e5e7eb;
            border-color: #374151;
        }
        .dark .hw-btn-outline:hover {
            background: #374151;
            color: #ffffff;
        }

        /* Telemetry Status Grid */
        .hw-telemetry-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 0.875rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        @media (min-width: 640px) {
            .hw-telemetry-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (min-width: 1024px) {
            .hw-telemetry-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
        .hw-telem-card {
            background: rgba(0, 15, 43, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 0.875rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.875rem;
        }
        .hw-telem-icon {
            width: 2.25rem;
            height: 2.25rem;
            min-width: 2.25rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
        }
        .hw-telem-icon.emerald { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .hw-telem-icon.amber { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .hw-telem-icon.blue { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .hw-telem-icon.purple { background: rgba(168, 85, 247, 0.2); color: #c084fc; }

        .hw-telem-meta {
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
            min-width: 0;
        }
        .hw-telem-label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #94a3b8;
        }
        .hw-telem-val {
            font-size: 0.875rem;
            font-weight: 700;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .hw-telem-sub {
            font-size: 0.6875rem;
            color: #cbd5e1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* 2. One-Time Setup Card */
        .hw-wizard-card {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            border: 2px solid #818cf8;
            border-radius: 1rem;
            padding: 1.5rem;
            color: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(49, 46, 129, 0.4);
            position: relative;
        }
        .hw-wizard-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 2rem;
            height: 2rem;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.15);
            border: none;
            color: #ffffff;
            cursor: pointer;
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .hw-wizard-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        .hw-wizard-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
            align-items: center;
            margin-top: 1rem;
        }
        @media (min-width: 1024px) {
            .hw-wizard-grid {
                grid-template-columns: 280px 1fr;
            }
        }
        .hw-code-badge {
            background: #090d16;
            border: 1px solid rgba(129, 140, 248, 0.5);
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
        }
        .hw-code-num {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 1.875rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            color: #34d399;
            user-select: all;
        }
        .hw-terminal-box {
            background: #090d16;
            border: 1px solid #1f2937;
            border-radius: 0.75rem;
            padding: 1rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.4);
        }
        .hw-terminal-head {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #1f2937;
            margin-bottom: 0.75rem;
            font-size: 0.6875rem;
            color: #64748b;
        }
        .hw-terminal-dot {
            width: 8px;
            height: 8px;
            border-radius: 9999px;
        }
        .hw-terminal-dot.red { background: #ef4444; }
        .hw-terminal-dot.yellow { background: #f59e0b; }
        .hw-terminal-dot.green { background: #10b981; }
        .hw-terminal-cmd {
            font-size: 0.75rem;
            line-height: 1.5;
            color: #34d399;
            user-select: all;
            word-break: break-all;
        }

        /* 3. Navigation Tab Bar */
        .hw-nav-tabs {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.375rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }
        .dark .hw-nav-tabs {
            background: #111827;
            border-color: #1f2937;
        }
        .hw-tab-btn {
            flex: 1 1 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 0.875rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .dark .hw-tab-btn {
            color: #94a3b8;
        }
        .hw-tab-btn:hover {
            background: #f1f5f9;
            color: #0f172a;
        }
        .dark .hw-tab-btn:hover {
            background: #1f2937;
            color: #f8fafc;
        }
        .hw-tab-btn.active {
            background: #001b48;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(0, 27, 72, 0.25);
        }
        .dark .hw-tab-btn.active {
            background: #1e3a5f;
            color: #ffffff;
        }
        .hw-tab-count {
            padding: 0.125rem 0.45rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 800;
            background: #e2e8f0;
            color: #334155;
        }
        .hw-tab-btn.active .hw-tab-count {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }

        /* 4. Panels & Cards */
        .hw-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.875rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .dark .hw-card {
            background: #111827;
            border-color: #1f2937;
        }
        .hw-card-head {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .dark .hw-card-head {
            border-bottom-color: #1f2937;
        }
        @media (min-width: 640px) {
            .hw-card-head {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }
        .hw-card-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dark .hw-card-title {
            color: #f8fafc;
        }
        .hw-card-sub {
            font-size: 0.75rem;
            color: #64748b;
            margin: 0;
        }
        .dark .hw-card-sub {
            color: #94a3b8;
        }

        /* 3-Col Architecture Cards in Overview */
        .hw-arch-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .hw-arch-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        .hw-arch-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .dark .hw-arch-box {
            background: #0f172a;
            border-color: #1f2937;
        }
        .hw-arch-title {
            font-size: 0.8125rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        .dark .hw-arch-title {
            color: #f8fafc;
        }
        .hw-arch-text {
            font-size: 0.75rem;
            line-height: 1.5;
            color: #475569;
            margin: 0;
            flex-grow: 1;
        }
        .dark .hw-arch-text {
            color: #94a3b8;
        }
        .hw-arch-status {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.6875rem;
            font-weight: 700;
            color: #047857;
        }

        /* 5. Print Rules Toggles */
        .hw-rules-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .hw-rules-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        .hw-rule-item {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.125rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: #ffffff;
            transition: all 0.15s ease;
        }
        .dark .hw-rule-item {
            background: #111827;
            border-color: #1f2937;
        }
        .hw-rule-item.active {
            border-color: #001b48;
            background: #f0f5fe;
        }
        .dark .hw-rule-item.active {
            border-color: #3b82f6;
            background: #0f1f38;
        }
        .hw-rule-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .hw-rule-title {
            font-size: 0.8125rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #0f172a;
            margin: 0;
        }
        .dark .hw-rule-title {
            color: #f8fafc;
        }
        .hw-rule-desc {
            font-size: 0.6875rem;
            line-height: 1.4;
            color: #64748b;
            margin: 0;
        }
        .dark .hw-rule-desc {
            color: #94a3b8;
        }

        /* Toggle Switch UI */
        .hw-switch {
            width: 44px;
            height: 24px;
            min-width: 44px;
            border-radius: 9999px;
            background: #cbd5e1;
            position: relative;
            cursor: pointer;
            border: none;
            padding: 2px;
            transition: background 0.2s ease;
        }
        .hw-switch.active {
            background: #001b48;
        }
        .dark .hw-switch.active {
            background: #2563eb;
        }
        .hw-switch-dot {
            width: 20px;
            height: 20px;
            border-radius: 9999px;
            background: #ffffff;
            position: absolute;
            top: 2px;
            left: 2px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s ease;
        }
        .hw-switch.active .hw-switch-dot {
            transform: translateX(20px);
        }

        /* 6. Form Controls */
        .hw-form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 640px) {
            .hw-form-grid.two-col {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .hw-form-grid.three-col {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        .hw-field {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .hw-label {
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
        }
        .dark .hw-label {
            color: #cbd5e1;
        }
        .hw-input, .hw-select {
            width: 100%;
            height: 2.375rem;
            padding: 0 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            background: #ffffff;
            color: #0f172a;
            font-size: 0.75rem;
            outline: none;
            transition: border-color 0.15s ease;
        }
        .dark .hw-input, .dark .hw-select {
            background: #1f2937;
            border-color: #374151;
            color: #f8fafc;
        }
        .hw-input:focus, .hw-select:focus {
            border-color: #001b48;
            box-shadow: 0 0 0 2px rgba(0, 27, 72, 0.15);
        }
        .dark .hw-input:focus, .dark .hw-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
        }
        .hw-input.font-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .hw-hint {
            font-size: 0.65rem;
            color: #94a3b8;
        }

        /* 7. Real Thermal Receipt Simulation */
        .hw-receipt-preview {
            max-width: 360px;
            margin: 0 auto;
            background: #fdfdfc;
            border: 1px solid #e7e5e4;
            border-radius: 0.5rem;
            padding: 1.5rem 1.25rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.6875rem;
            line-height: 1.35;
            color: #1c1917;
            position: relative;
        }
        .hw-receipt-preview::after {
            content: "";
            position: absolute;
            bottom: -6px;
            left: 0;
            right: 0;
            height: 6px;
            background: radial-gradient(circle, transparent, transparent 50%, #fdfdfc 50%, #fdfdfc 100%);
            background-size: 10px 10px;
        }
        .hw-receipt-sep {
            border-bottom: 1px dashed #78716c;
            margin: 0.5rem 0;
        }
        .hw-receipt-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .hw-statutory-box {
            background: #e7e5e4;
            border: 1px solid #d6d3d1;
            border-radius: 0.35rem;
            padding: 0.5rem;
            text-align: center;
            font-size: 0.5875rem;
            font-weight: 800;
            line-height: 1.25;
            color: #0c0a09;
            margin-top: 0.75rem;
        }

        /* 8. Tables */
        .hw-table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 0.625rem;
        }
        .dark .hw-table-wrap {
            border-color: #1f2937;
        }
        .hw-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.75rem;
        }
        .hw-table th {
            background: #f8fafc;
            padding: 0.625rem 0.875rem;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.65rem;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        .dark .hw-table th {
            background: #111827;
            color: #94a3b8;
            border-bottom-color: #1f2937;
        }
        .hw-table td {
            padding: 0.75rem 0.875rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }
        .dark .hw-table td {
            border-bottom-color: #1f2937;
            color: #cbd5e1;
        }
        .hw-table tr:hover td {
            background: #f8fafc;
        }
        .dark .hw-table tr:hover td {
            background: #1e293b;
        }

        /* Status Badges */
        .hw-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.6875rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .hw-badge.emerald { background: #ecfdf5; color: #047857; }
        .hw-badge.blue { background: #eff6ff; color: #1d4ed8; }
        .hw-badge.amber { background: #fffbeb; color: #b45309; }
        .hw-badge.rose { background: #fff1f2; color: #be123c; }
        .hw-badge.gray { background: #f1f5f9; color: #475569; }

        .dark .hw-badge.emerald { background: #064e3b; color: #a7f3d0; }
        .dark .hw-badge.blue { background: #1e3a8a; color: #bfdbfe; }
        .dark .hw-badge.amber { background: #78350f; color: #fde68a; }
        .dark .hw-badge.rose { background: #881337; color: #fecdd3; }
        .dark .hw-badge.gray { background: #334155; color: #cbd5e1; }
    </style>

    <div class="hw-wrap">

        <!-- ================================================================= -->
        <!-- 1. HERO CONTROL CENTER BANNER & LIVE TELEMETRY -->
        <!-- ================================================================= -->
        <div class="hw-hero">
            <div class="hw-hero-top">
                <div class="hw-hero-title-area">
                    <div class="hw-badge-row">
                        <span class="hw-pill-tag">
                            <svg class="hw-icon-xs" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            Zero-Config Architecture
                        </span>

                        <span class="hw-pill-tag emerald">
                            <span class="hw-pulse-dot" style="width:6px;height:6px;border-radius:9999px;background:#34d399;"></span>
                            Shared-Hosting Mode
                        </span>

                        <span style="font-size:0.6875rem;color:#94a3b8;font-family:monospace;">
                            Station: <strong style="color:#ffffff;">{{ $station->name }}</strong> ({{ $station->code }})
                        </span>
                    </div>

                    <h1 class="hw-hero-heading">Hardware & Printing Control Center</h1>

                    <p class="hw-hero-desc">
                        Configure showroom printing <strong>once</strong>. Any POS device (desktop, tablet, phone) and online customer checkout automatically prints to your physical 80mm thermal printer. No browser dialogs, no cash drawers, no client re-pairing.
                    </p>
                </div>

                <div class="hw-hero-actions">
                    <button
                        type="button"
                        wire:click="testPrint"
                        wire:loading.attr="disabled"
                        class="hw-btn hw-btn-emerald">
                        <svg class="hw-icon-sm" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span wire:loading.remove wire:target="testPrint">Test Print Receipt</span>
                        <span wire:loading wire:target="testPrint">Enqueueing...</span>
                    </button>

                    <button
                        type="button"
                        wire:click="generatePairingCode"
                        class="hw-btn hw-btn-navy">
                        <svg class="hw-icon-sm" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                        <span>Connect Showroom Printer</span>
                    </button>
                </div>
            </div>

            <!-- Telemetry Matrix -->
            <div class="hw-telemetry-grid">
                <!-- 1. Print Agent Status -->
                <div class="hw-telem-card">
                    <div class="hw-telem-icon {{ $station->isOnline() ? 'emerald' : 'amber' }}">
                        <span class="{{ $station->isOnline() ? 'hw-pulse-dot' : '' }}" style="width:8px;height:8px;border-radius:9999px;background:currentColor;"></span>
                    </div>
                    <div class="hw-telem-meta">
                        <div class="hw-telem-label">Print Agent</div>
                        <div class="hw-telem-val">
                            {{ $station->isOnline() ? 'Online & Polling' : ($station->device_token_hash ? 'Agent Offline' : 'Not Paired') }}
                        </div>
                        <div class="hw-telem-sub">
                            {{ $station->last_heartbeat_at ? $station->last_heartbeat_at->diffForHumans() : 'Run pairing once' }}
                        </div>
                    </div>
                </div>

                <!-- 2. Thermal Receipt Printer -->
                <div class="hw-telem-card">
                    <div class="hw-telem-icon blue">
                        <svg class="hw-icon-md" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    </div>
                    <div class="hw-telem-meta">
                        <div class="hw-telem-label">Receipt Printer</div>
                        <div class="hw-telem-val">
                            {{ $printer ? $printer->name : 'Not Configured' }}
                        </div>
                        <div class="hw-telem-sub">
                            {{ $printer ? ($printer->connection_type === 'network' ? "{$printer->network_ip}:{$printer->network_port}" : $printer->usb_device_path) : 'Enter IP below' }}
                        </div>
                    </div>
                </div>

                <!-- 3. Print Queue State -->
                <div class="hw-telem-card">
                    <div class="hw-telem-icon emerald">
                        <svg class="hw-icon-md" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    </div>
                    <div class="hw-telem-meta">
                        <div class="hw-telem-label">Print Queue</div>
                        <div class="hw-telem-val">
                            {{ $summary['queued_jobs'] + $summary['printing_jobs'] }} Pending
                        </div>
                        <div class="hw-telem-sub">
                            {{ $summary['printed_today'] }} printed today
                        </div>
                    </div>
                </div>

                <!-- 4. Cash Drawer Removal Badge -->
                <div class="hw-telem-card">
                    <div class="hw-telem-icon purple">
                        <svg class="hw-icon-md" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <div class="hw-telem-meta">
                        <div class="hw-telem-label">Hardware Scope</div>
                        <div class="hw-telem-val">Printer + Scanner</div>
                        <div class="hw-telem-sub">Cash drawer removed</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- 2. ONE-TIME PAIRING WIZARD -->
        <!-- ================================================================= -->
        @if($showPairingCard)
            <div class="hw-wizard-card">
                <button type="button" wire:click="hidePairingCard" class="hw-wizard-close">✕</button>

                <div class="hw-badge-row">
                    <span class="hw-pill-tag emerald">STEP 1 OF 1 — ONE-TIME SHOWROOM SETUP</span>
                    <span style="font-size:0.6875rem;color:#c7d2fe;">Valid for 10 minutes (expires at <strong>{{ $pairingExpiresAt }}</strong>)</span>
                </div>

                <div class="hw-wizard-grid">
                    <div class="hw-code-badge">
                        <div style="font-size:0.6875rem;font-weight:700;color:#c7d2fe;text-transform:uppercase;letter-spacing:0.06em;">Pairing Code</div>
                        <div class="hw-code-num">{{ $generatedPairingCode }}</div>
                        <div style="font-size:0.65rem;color:#94a3b8;margin-top:0.25rem;">Permanent key will be generated</div>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        <div style="font-size:0.75rem;font-weight:700;color:#e0e7ff;">Execute once in the showroom computer's terminal:</div>
                        <div class="hw-terminal-box">
                            <div class="hw-terminal-head">
                                <span class="hw-terminal-dot red"></span>
                                <span class="hw-terminal-dot yellow"></span>
                                <span class="hw-terminal-dot green"></span>
                                <span style="margin-left:0.5rem;">showroom-pc: ~/laijau_print_agent</span>
                            </div>
                            <div class="hw-terminal-cmd">
                                python3 laijau_print_agent.py --pair {{ $generatedPairingCode }} --server {{ url('/api/v1/print-agent') }}
                            </div>
                        </div>
                        <div style="font-size:0.6875rem;color:#c7d2fe;display:flex;align-items:center;gap:0.35rem;">
                            <span>✓</span>
                            <span>The agent permanently associates with the showroom. Nobody needs to run this again.</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- 3. NAVIGATION TAB BAR -->
        <!-- ================================================================= -->
        <div class="hw-nav-tabs">
            <button
                type="button"
                wire:click="setTab('overview')"
                class="hw-tab-btn {{ $activeTab === 'overview' ? 'active' : '' }}">
                <svg class="hw-icon-sm" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span>Overview & Discovered</span>
                @if($discovered->count() > 0)
                    <span class="hw-tab-count">{{ $discovered->count() }}</span>
                @endif
            </button>

            <button
                type="button"
                wire:click="setTab('rules')"
                class="hw-tab-btn {{ $activeTab === 'rules' ? 'active' : '' }}">
                <svg class="hw-icon-sm" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                <span>Print Rules</span>
            </button>

            <button
                type="button"
                wire:click="setTab('printer')"
                class="hw-tab-btn {{ $activeTab === 'printer' ? 'active' : '' }}">
                <svg class="hw-icon-sm" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>Receipt Printer</span>
            </button>

            <button
                type="button"
                wire:click="setTab('scanner')"
                class="hw-tab-btn {{ $activeTab === 'scanner' ? 'active' : '' }}">
                <svg class="hw-icon-sm" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                <span>USB Scanner</span>
            </button>

            <button
                type="button"
                wire:click="setTab('queue')"
                class="hw-tab-btn {{ $activeTab === 'queue' ? 'active' : '' }}">
                <svg class="hw-icon-sm" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                <span>Print Queue</span>
                <span class="hw-tab-count">{{ $jobs->count() }}</span>
            </button>

            <button
                type="button"
                wire:click="setTab('audit')"
                class="hw-tab-btn {{ $activeTab === 'audit' ? 'active' : '' }}">
                <svg class="hw-icon-sm" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Audit Log</span>
            </button>
        </div>

        <!-- ================================================================= -->
        <!-- TAB 1: OVERVIEW & DISCOVERED PRINTERS -->
        <!-- ================================================================= -->
        @if($activeTab === 'overview')
            <div style="display:flex;flex-direction:column;gap:1.25rem;">
                <div class="hw-arch-grid">
                    <!-- Workflow 1: POS Sales -->
                    <div class="hw-arch-box">
                        <h4 class="hw-arch-title">
                            <span style="font-size:1.1rem;">🛒</span>
                            <span>POS Sales (Any Device)</span>
                        </h4>
                        <p class="hw-arch-text">
                            Cashier completes sale on PC, laptop, or tablet. Laravel commits the transaction and queues an 80mm ESC/POS receipt automatically. No print dialog required.
                        </p>
                        <div class="hw-arch-status">
                            <span style="width:6px;height:6px;border-radius:9999px;background:#10b981;"></span>
                            <span>{{ $station->auto_print_pos_sale ? 'Auto-Printing Active' : 'Manual Reprint Only' }}</span>
                        </div>
                    </div>

                    <!-- Workflow 2: Online Store Orders -->
                    <div class="hw-arch-box">
                        <h4 class="hw-arch-title">
                            <span style="font-size:1.1rem;">📦</span>
                            <span>Online Store Orders</span>
                        </h4>
                        <p class="hw-arch-text">
                            When an online order is placed on laijau.com, an internal fulfillment slip with customer address and product sizes/colors prints automatically in the showroom.
                        </p>
                        <div class="hw-arch-status">
                            <span style="width:6px;height:6px;border-radius:9999px;background:#10b981;"></span>
                            <span>{{ $station->auto_print_online_order ? 'Auto-Printing Active' : 'Manual Print Only' }}</span>
                        </div>
                    </div>

                    <!-- Workflow 3: Showroom Local Print Agent -->
                    <div class="hw-arch-box">
                        <h4 class="hw-arch-title">
                            <span style="font-size:1.1rem;">⚡</span>
                            <span>Showroom Print Agent</span>
                        </h4>
                        <p class="hw-arch-text">
                            Runs silently in the background on the showroom PC. Streams ESC/POS bytes directly to printer socket (Port 9100) or USB without browser dialogs.
                        </p>
                        <div class="hw-arch-status" style="color: {{ $station->isOnline() ? '#047857' : '#b45309' }};">
                            <span style="width:6px;height:6px;border-radius:9999px;background:{{ $station->isOnline() ? '#10b981' : '#f59e0b' }};"></span>
                            <span>{{ $station->isOnline() ? 'Agent Polling (1.5s)' : 'Agent Offline' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Discovered LAN Printers -->
                <div class="hw-card">
                    <div class="hw-card-head">
                        <div>
                            <h3 class="hw-card-title">
                                <svg class="hw-icon-sm" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                                <span>Showroom LAN Discovered Printers</span>
                            </h3>
                            <p class="hw-card-sub">Printers discovered by the local print agent probing Port 9100 on your private showroom LAN.</p>
                        </div>
                    </div>

                    <div class="hw-table-wrap">
                        <table class="hw-table">
                            <thead>
                                <tr>
                                    <th>Printer Device</th>
                                    <th>Connection</th>
                                    <th>Network IP / Path</th>
                                    <th>Status</th>
                                    <th>Last Seen</th>
                                    <th style="text-align:right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($discovered as $disc)
                                    <tr>
                                        <td style="font-weight:700;color:#0f172a;">{{ $disc->name }}</td>
                                        <td style="text-transform:uppercase;font-size:0.6875rem;">{{ $disc->connection_type }}</td>
                                        <td style="font-family:monospace;font-weight:700;">
                                            {{ $disc->network_ip ? "{$disc->network_ip}:{$disc->network_port}" : $disc->usb_device_path }}
                                        </td>
                                        <td>
                                            <span class="hw-badge emerald">
                                                <span style="width:5px;height:5px;border-radius:9999px;background:#10b981;"></span>
                                                Available
                                            </span>
                                        </td>
                                        <td style="color:#64748b;">{{ $disc->last_seen_at?->diffForHumans() }}</td>
                                        <td style="text-align:right;">
                                            <button
                                                type="button"
                                                wire:click="useDiscoveredPrinter({{ $disc->id }})"
                                                class="hw-btn hw-btn-primary"
                                                style="padding:0.35rem 0.75rem;font-size:0.6875rem;">
                                                Use This Printer
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" style="padding:2.5rem;text-align:center;color:#64748b;">
                                            <div style="display:flex;flex-direction:column;align-items:center;gap:0.35rem;">
                                                <span style="font-size:1.5rem;">🔍</span>
                                                <strong style="color:#0f172a;">No LAN Printers Reported Yet</strong>
                                                <span style="font-size:0.6875rem;">The print agent probes Port 9100 periodically. You can also directly enter your printer IP under the Receipt Printer tab.</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 2: AUTOMATIC PRINTING RULES -->
        <!-- ================================================================= -->
        @if($activeTab === 'rules')
            <div class="hw-card">
                <div class="hw-card-head">
                    <div>
                        <h3 class="hw-card-title">
                            <svg class="hw-icon-sm" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            <span>Automatic Printing Rules</span>
                        </h3>
                        <p class="hw-card-sub">Control which commercial documents automatically trigger physical thermal printing upon completion.</p>
                    </div>
                </div>

                <div class="hw-rules-grid">
                    <!-- Rule 1: POS Sales -->
                    <div class="hw-rule-item {{ $station->auto_print_pos_sale ? 'active' : '' }}">
                        <div class="hw-rule-info">
                            <h4 class="hw-rule-title">POS Sales Receipts</h4>
                            <p class="hw-rule-desc">Automatically print an 80mm customer receipt whenever a cashier finishes a sale from any POS device.</p>
                        </div>
                        <button
                            type="button"
                            wire:click="togglePrintRule('auto_print_pos_sale')"
                            class="hw-switch {{ $station->auto_print_pos_sale ? 'active' : '' }}"
                            aria-label="Toggle POS Sales Auto-Print">
                            <span class="hw-switch-dot"></span>
                        </button>
                    </div>

                    <!-- Rule 2: Online Store Orders -->
                    <div class="hw-rule-item {{ $station->auto_print_online_order ? 'active' : '' }}">
                        <div class="hw-rule-info">
                            <h4 class="hw-rule-title">Online Order Fulfillment Slips</h4>
                            <p class="hw-rule-desc">Automatically print fulfillment slip with customer address and product sizes/colors when an online order is placed.</p>
                        </div>
                        <button
                            type="button"
                            wire:click="togglePrintRule('auto_print_online_order')"
                            class="hw-switch {{ $station->auto_print_online_order ? 'active' : '' }}"
                            aria-label="Toggle Online Orders Auto-Print">
                            <span class="hw-switch-dot"></span>
                        </button>
                    </div>

                    <!-- Rule 3: Warehouse Packing Slips -->
                    <div class="hw-rule-item {{ $station->auto_print_packing_slip ? 'active' : '' }}">
                        <div class="hw-rule-info">
                            <h4 class="hw-rule-title">Warehouse Packing Slips</h4>
                            <p class="hw-rule-desc">Automatically print a warehouse packing slip alongside online orders for warehouse packing and verification.</p>
                        </div>
                        <button
                            type="button"
                            wire:click="togglePrintRule('auto_print_packing_slip')"
                            class="hw-switch {{ $station->auto_print_packing_slip ? 'active' : '' }}"
                            aria-label="Toggle Packing Slips Auto-Print">
                            <span class="hw-switch-dot"></span>
                        </button>
                    </div>

                    <!-- Rule 4: Returns & Exchanges -->
                    <div class="hw-rule-item {{ $station->auto_print_returns ? 'active' : '' }}">
                        <div class="hw-rule-info">
                            <h4 class="hw-rule-title">Returns & Exchanges</h4>
                            <p class="hw-rule-desc">Automatically print return documentation when an item exchange or return is logged at the showroom counter.</p>
                        </div>
                        <button
                            type="button"
                            wire:click="togglePrintRule('auto_print_returns')"
                            class="hw-switch {{ $station->auto_print_returns ? 'active' : '' }}"
                            aria-label="Toggle Returns Auto-Print">
                            <span class="hw-switch-dot"></span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 3: RECEIPT PRINTER SETTINGS WITH RECEIPT PREVIEW -->
        <!-- ================================================================= -->
        @if($activeTab === 'printer')
            <div class="hw-split-grid">
                <!-- Left Column: Form Settings -->
                <div class="hw-card">
                    <div class="hw-card-head">
                        <div>
                            <h3 class="hw-card-title">
                                <svg class="hw-icon-sm" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                                <span>Showroom Receipt Printer</span>
                            </h3>
                            <p class="hw-card-sub">Configure your physical thermal printer. Changes sync to the showroom agent automatically.</p>
                        </div>

                        <button
                            type="button"
                            wire:click="testPrint"
                            class="hw-btn hw-btn-outline"
                            style="padding:0.4rem 0.85rem;font-size:0.6875rem;">
                            Test Print Now
                        </button>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:1rem;">
                        <div class="hw-form-grid two-col">
                            <div class="hw-field">
                                <label class="hw-label">Printer Model Name</label>
                                <input type="text" wire:model="printer_name" class="hw-input" placeholder="Epson TM-T82 Thermal 80mm">
                            </div>

                            <div class="hw-field">
                                <label class="hw-label">Connection Interface</label>
                                <select wire:model.live="printer_connection_type" class="hw-select">
                                    <option value="network">Network LAN (Raw TCP Port 9100)</option>
                                    <option value="usb">Direct USB Port (/dev/usb/lp0)</option>
                                </select>
                            </div>
                        </div>

                        @if($printer_connection_type === 'network')
                            <div class="hw-form-grid two-col" style="background:#f8fafc;padding:0.875rem;border-radius:0.625rem;border:1px solid #e2e8f0;">
                                <div class="hw-field">
                                    <label class="hw-label">LAN IP Address *</label>
                                    <input type="text" wire:model="printer_network_ip" class="hw-input font-mono" placeholder="192.168.1.200">
                                    <span class="hw-hint">Showroom LAN static IP of printer</span>
                                </div>
                                <div class="hw-field">
                                    <label class="hw-label">TCP Raw Port *</label>
                                    <input type="number" wire:model="printer_network_port" class="hw-input font-mono" placeholder="9100">
                                    <span class="hw-hint">Standard ESC/POS port is 9100</span>
                                </div>
                            </div>
                        @else
                            <div class="hw-field" style="background:#f8fafc;padding:0.875rem;border-radius:0.625rem;border:1px solid #e2e8f0;">
                                <label class="hw-label">USB Device Node Path *</label>
                                <input type="text" wire:model="printer_usb_path" class="hw-input font-mono" placeholder="/dev/usb/lp0">
                                <span class="hw-hint">e.g. /dev/usb/lp0 on Linux or Windows printer share name</span>
                            </div>
                        @endif

                        <div class="hw-form-grid three-col">
                            <div class="hw-field">
                                <label class="hw-label">Paper Width</label>
                                <select wire:model="printer_paper_width_mm" class="hw-select">
                                    <option value="80">80mm (Standard 48 Col)</option>
                                    <option value="58">58mm (Compact 32 Col)</option>
                                </select>
                            </div>
                            <div class="hw-field">
                                <label class="hw-label">Columns / Line</label>
                                <input type="number" wire:model="printer_characters_per_line" class="hw-input" min="24" max="64">
                            </div>
                            <div class="hw-field">
                                <label class="hw-label">Copies</label>
                                <input type="number" wire:model="printer_copies" class="hw-input" min="1" max="5">
                            </div>
                        </div>

                        <div style="display:flex;align-items:center;gap:1.5rem;padding-top:0.5rem;">
                            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.75rem;cursor:pointer;">
                                <input type="checkbox" wire:model="printer_auto_cut" style="width:16px;height:16px;">
                                <span style="font-weight:600;">Auto Paper Cut (GS V Command)</span>
                            </label>
                            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.75rem;cursor:pointer;">
                                <input type="checkbox" wire:model="printer_is_active" style="width:16px;height:16px;">
                                <span style="font-weight:600;">Printer Active</span>
                            </label>
                        </div>

                        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.75rem;padding-top:1rem;border-top:1px solid #f1f5f9;">
                            <button
                                type="button"
                                wire:click="savePrinterSettings"
                                class="hw-btn hw-btn-primary">
                                Save Printer Settings
                            </button>

                            <button
                                type="button"
                                wire:click="rotateCredentials"
                                wire:confirm="Revoke showroom credentials and regenerate? The showroom PC will require one-time re-pairing."
                                style="background:none;border:none;color:#94a3b8;font-size:0.6875rem;cursor:pointer;text-decoration:underline;">
                                Reset Showroom Agent Token
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Live 80mm Receipt Preview Mockup -->
                <div style="display:flex;flex-direction:column;gap:0.75rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.6875rem;color:#64748b;font-weight:700;text-transform:uppercase;">
                        <span style="display:flex;align-items:center;gap:0.35rem;">
                            <span style="width:6px;height:6px;border-radius:9999px;background:#10b981;"></span>
                            Live 80mm ESC/POS Layout Preview
                        </span>
                        <span>{{ $printer_paper_width_mm }}mm ({{ $printer_characters_per_line }} Cols)</span>
                    </div>

                    <div class="hw-receipt-preview">
                        <div style="text-align:center;display:flex;flex-direction:column;gap:0.25rem;">
                            <div style="font-size:1.125rem;font-weight:900;letter-spacing:0.1em;">LAIJAU</div>
                            <div style="font-weight:700;">SHOWROOM POS RECEIPT</div>
                            <div style="font-size:0.6rem;color:#78716c;">Bohara Tol, Kageshwori Manahara 09</div>
                            <div style="font-size:0.6rem;color:#78716c;">Kathmandu, Nepal | Tel: 9843512095</div>
                            <div style="font-size:0.6rem;color:#78716c;">www.laijau.com</div>
                        </div>

                        <div class="hw-receipt-sep"></div>

                        <div style="display:flex;flex-direction:column;gap:0.2rem;">
                            <div class="hw-receipt-row"><span>Sale No:</span><strong>#LJ-POS-98421</strong></div>
                            <div class="hw-receipt-row"><span>Date/Time:</span><span>{{ now()->format('d M Y, H:i') }}</span></div>
                            <div class="hw-receipt-row"><span>Cashier:</span><span>Showroom Counter</span></div>
                            <div class="hw-receipt-row"><span>Customer:</span><span>Walk-in Customer</span></div>
                        </div>

                        <div class="hw-receipt-sep"></div>

                        <div style="display:flex;flex-direction:column;gap:0.4rem;">
                            <div>
                                <div style="font-weight:700;">Classic Oversized Hoodie (M)</div>
                                <div class="hw-receipt-row" style="color:#57534e;">
                                    <span>  1 x Rs. 3,500.00</span>
                                    <span>Rs. 3,500.00</span>
                                </div>
                            </div>
                            <div>
                                <div style="font-weight:700;">Ribbed Knit Beanie (One Size)</div>
                                <div class="hw-receipt-row" style="color:#57534e;">
                                    <span>  1 x Rs. 1,000.00</span>
                                    <span>Rs. 1,000.00</span>
                                </div>
                            </div>
                        </div>

                        <div class="hw-receipt-sep"></div>

                        <div style="display:flex;flex-direction:column;gap:0.25rem;">
                            <div class="hw-receipt-row"><span>Subtotal:</span><span>Rs. 4,500.00</span></div>
                            <div class="hw-receipt-row" style="font-size:0.8125rem;font-weight:900;margin-top:0.25rem;">
                                <span>TOTAL PAID:</span>
                                <span>Rs. 4,500.00</span>
                            </div>
                        </div>

                        <!-- Statutory Non-Tax Disclaimer -->
                        <div class="hw-statutory-box">
                            THIS IS NOT A TAX INVOICE<br>
                            FOR LAIJAU INTERNAL USE ONLY<br>
                            PLEASE RETAIN YOUR TAX INVOICE FROM THE COUNTER
                        </div>

                        <div style="text-align:center;font-size:0.5875rem;color:#78716c;margin-top:0.5rem;">
                            7-day exchange with original tags. Thank you!
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 4: USB BARCODE SCANNER SETTINGS -->
        <!-- ================================================================= -->
        @if($activeTab === 'scanner')
            <div class="hw-split-grid">
                <div class="hw-card">
                    <div class="hw-card-head">
                        <div>
                            <h3 class="hw-card-title">
                                <svg class="hw-icon-sm" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                                <span>USB HID Barcode Scanner Configuration</span>
                            </h3>
                            <p class="hw-card-sub">Plug & play keyboard emulation scanners. Rapid burst timing automatically distinguishes barcode scans from manual typing.</p>
                        </div>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:1rem;">
                        <div class="hw-form-grid three-col">
                            <div class="hw-field">
                                <label class="hw-label">Burst Threshold (ms)</label>
                                <input type="number" wire:model="scanner_burst_threshold_ms" class="hw-input" min="10" max="300">
                                <span class="hw-hint">Keystroke speed (default 40ms)</span>
                            </div>
                            <div class="hw-field">
                                <label class="hw-label">Min Length</label>
                                <input type="number" wire:model="scanner_min_length" class="hw-input" min="1" max="20">
                                <span class="hw-hint">Minimum SKU length</span>
                            </div>
                            <div class="hw-field">
                                <label class="hw-label">Max Length</label>
                                <input type="number" wire:model="scanner_max_length" class="hw-input" min="10" max="100">
                                <span class="hw-hint">Maximum barcode length</span>
                            </div>
                        </div>

                        <div style="display:flex;align-items:center;gap:1.5rem;padding-top:0.5rem;">
                            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.75rem;cursor:pointer;">
                                <input type="checkbox" wire:model="scanner_ignore_typing" style="width:16px;height:16px;">
                                <span style="font-weight:600;">Ignore Normal Typing</span>
                            </label>
                            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.75rem;cursor:pointer;">
                                <input type="checkbox" wire:model="scanner_global_listener" style="width:16px;height:16px;">
                                <span style="font-weight:600;">Global Scanner Listener in POS</span>
                            </label>
                        </div>

                        <div style="padding-top:1rem;border-top:1px solid #f1f5f9;">
                            <button
                                type="button"
                                wire:click="saveScannerSettings"
                                class="hw-btn hw-btn-primary">
                                Save Scanner Settings
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Live Scanner Tester Box -->
                <div class="hw-card" style="background:#090d16;border-color:#1e293b;color:#ffffff;">
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <h4 style="font-size:0.75rem;font-weight:800;text-transform:uppercase;letter-spacing:0.06em;color:#94a3b8;margin:0;">
                            ⚡ Live USB Scanner Tester
                        </h4>
                        <span class="hw-badge emerald">READY TO SCAN</span>
                    </div>

                    <p style="font-size:0.75rem;color:#94a3b8;line-height:1.4;margin:0;">
                        Click into the input box below and scan any physical product barcode with your USB scanner to test recognition and SKU decoding:
                    </p>

                    <div x-data="{
                        testSku: '',
                        scannedCount: 0,
                        lastScanned: null,
                        scanTimestamp: null,
                        onScan(val) {
                            if (val.trim().length >= 3) {
                                this.scannedCount++;
                                this.lastScanned = val.trim();
                                this.scanTimestamp = new Date().toLocaleTimeString();
                                this.testSku = '';
                            }
                        }
                    }" style="display:flex;flex-direction:column;gap:0.75rem;">
                        <input
                            type="text"
                            x-model="testSku"
                            @keydown.enter.prevent="onScan(testSku)"
                            placeholder="Scan or type barcode here..."
                            style="width:100%;background:#1e293b;border:2px solid #334155;border-radius:0.5rem;padding:0.625rem 0.875rem;font-family:monospace;font-size:0.875rem;color:#34d399;outline:none;">

                        <template x-if="lastScanned">
                            <div style="padding:0.75rem;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:0.5rem;">
                                <div style="font-size:0.6875rem;color:#34d399;font-weight:700;display:flex;justify-content:space-between;">
                                    <span>✓ Barcode Captured</span>
                                    <span x-text="scanTimestamp"></span>
                                </div>
                                <div style="font-family:monospace;font-size:0.9375rem;font-weight:800;color:#ffffff;margin-top:0.25rem;" x-text="lastScanned"></div>
                            </div>
                        </template>

                        <div style="display:flex;justify-content:space-between;font-size:0.6875rem;color:#64748b;">
                            <span>Scanned Items: <strong style="color:#ffffff;" x-text="scannedCount">0</strong></span>
                            <span>HID Scanner Emulation</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 5: PRINT QUEUE MONITOR -->
        <!-- ================================================================= -->
        @if($activeTab === 'queue')
            <div class="hw-card">
                <div class="hw-card-head">
                    <div>
                        <h3 class="hw-card-title">
                            <svg class="hw-icon-sm" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            <span>Showroom Print Job Queue</span>
                        </h3>
                        <p class="hw-card-sub">Live monitoring of automatic receipts, fulfillment slips, and reprints dispatched to the showroom PC.</p>
                    </div>

                    <div style="display:flex;gap:0.5rem;">
                        <span class="hw-badge amber">{{ $summary['queued_jobs'] + $summary['printing_jobs'] }} Pending</span>
                        <span class="hw-badge emerald">{{ $summary['printed_today'] }} Printed Today</span>
                    </div>
                </div>

                <div class="hw-table-wrap">
                    <table class="hw-table">
                        <thead>
                            <tr>
                                <th>Job ID</th>
                                <th>Document Type</th>
                                <th>Target Printer</th>
                                <th>Status</th>
                                <th>Time Log</th>
                                <th>Attempts</th>
                                <th>Notes</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($jobs as $job)
                                <tr>
                                    <td style="font-weight:700;color:#0f172a;">#{{ $job->id }}</td>
                                    <td>
                                        <div style="font-weight:700;color:#0f172a;">{{ strtoupper(str_replace('_', ' ', $job->job_type)) }}</div>
                                        <div style="font-size:0.6rem;color:#94a3b8;font-family:monospace;">{{ $job->idempotency_key }}</div>
                                    </td>
                                    <td style="color:#475569;">{{ $job->printer?->name ?: 'Showroom Default' }}</td>
                                    <td>
                                        @if($job->status === 'printed')
                                            <span class="hw-badge emerald">Printed</span>
                                        @elseif($job->status === 'printing')
                                            <span class="hw-badge blue">Printing...</span>
                                        @elseif($job->status === 'queued')
                                            <span class="hw-badge amber">Queued</span>
                                        @elseif($job->status === 'failed')
                                            <span class="hw-badge rose">Failed</span>
                                        @else
                                            <span class="hw-badge gray">{{ ucfirst($job->status) }}</span>
                                        @endif
                                    </td>
                                    <td style="color:#64748b;font-size:0.6875rem;">
                                        <div>Created: {{ $job->created_at?->format('H:i:s') }}</div>
                                        @if($job->printed_at)
                                            <div style="color:#047857;font-weight:700;">Printed: {{ $job->printed_at->format('H:i:s') }}</div>
                                        @endif
                                    </td>
                                    <td style="font-weight:600;">{{ $job->attempts }} / {{ $job->max_attempts }}</td>
                                    <td style="color:#be123c;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        {{ $job->last_error ?: '-' }}
                                    </td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        @if($job->status === 'failed')
                                            <button type="button" wire:click="retryJob({{ $job->id }})" class="hw-btn hw-btn-emerald" style="padding:0.25rem 0.5rem;font-size:0.6875rem;">Retry</button>
                                        @endif
                                        @if($job->status === 'queued' || $job->status === 'printing')
                                            <button type="button" wire:click="cancelJob({{ $job->id }})" class="hw-btn hw-btn-outline" style="padding:0.25rem 0.5rem;font-size:0.6875rem;">Cancel</button>
                                        @endif
                                        @if($job->source_type === 'offline_sale' || $job->source_type === 'online_order')
                                            <button type="button" wire:click="reprintJob({{ $job->id }})" class="hw-btn hw-btn-outline" style="padding:0.25rem 0.5rem;font-size:0.6875rem;">Reprint</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" style="padding:2.5rem;text-align:center;color:#64748b;">
                                        No print jobs recorded in the queue yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 6: AUDIT HISTORY -->
        <!-- ================================================================= -->
        @if($activeTab === 'audit')
            <div class="hw-card">
                <div class="hw-card-head">
                    <div>
                        <h3 class="hw-card-title">
                            <svg class="hw-icon-sm" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Hardware & Printing Audit Logs</span>
                        </h3>
                        <p class="hw-card-sub">Immutable audit trace of all printer IP updates, pairing actions, and print rule modifications.</p>
                    </div>
                </div>

                <div class="hw-table-wrap">
                    <table class="hw-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Admin</th>
                                <th>Target Entity</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($auditLogs as $log)
                                <tr>
                                    <td style="color:#64748b;white-space:nowrap;">{{ $log->created_at->format('d M Y, H:i:s') }}</td>
                                    <td style="font-weight:700;color:#0f172a;">{{ $log->user_name }}</td>
                                    <td style="font-weight:600;color:#334155;">{{ $log->entity_name }}</td>
                                    <td>
                                        <span class="hw-badge blue">{{ str_replace('_', ' ', $log->action) }}</span>
                                    </td>
                                    <td style="font-family:monospace;font-size:0.6875rem;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        {{ $log->field_name ? "{$log->field_name}: {$log->new_value}" : (json_encode($log->details) ?: '-') }}
                                    </td>
                                    <td style="font-family:monospace;color:#64748b;">{{ $log->ip_address ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding:2.5rem;text-align:center;color:#64748b;">
                                        No audit events recorded yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>

</x-filament-panels::page>
