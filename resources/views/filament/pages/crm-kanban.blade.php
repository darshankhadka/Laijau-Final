<x-filament-panels::page class="w-full max-w-full">
@php
    $stages = $this->getStages();
    $boardLeads = $this->boardLeads;
    $stats = $this->pipelineStats;
    $activeLead = $this->activeLead;
    $mobileStage = $this->mobileActiveStage ?? 'all';
    $products = \App\Models\Product::where('is_active', true)->limit(60)->get(['id', 'name', 'price', 'sku']);
    $staffMembers = \App\Models\User::whereIn('role', [
        'admin', 'super_admin', 'workspace_admin', 'store_manager', 'support_agent', 'sales_representative'
    ])->get(['id', 'name', 'email']);
@endphp

<style>
    /* ==========================================================================
       LAIJAU CRM & CLIENTELING STYLESHEET
       ========================================================================== */
    .lj-crm-wrap {
        width: 100%;
        max-width: 100%;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        box-sizing: border-box;
        padding-bottom: 2.5rem;
        color: #0f172a;
    }
    .dark .lj-crm-wrap {
        color: #f8fafc;
    }
    .lj-crm-wrap * {
        box-sizing: border-box;
    }

    /* Top KPI Metric Cards */
    .lj-crm-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
    }
    @media (max-width: 1024px) {
        .lj-crm-kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 640px) {
        .lj-crm-kpi-grid {
            grid-template-columns: 1fr;
        }
    }

    .lj-crm-kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem 1.25rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
        transition: all 0.2s ease;
    }
    .dark .lj-crm-kpi-card {
        background: #1e293b;
        border-color: #334155;
    }
    .lj-crm-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
    }

    .lj-kpi-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    /* Pulse animation for Overdue Follow-ups */
    @keyframes lj-pulse-dot {
        0% { transform: scale(0.95); opacity: 0.8; }
        50% { transform: scale(1.15); opacity: 1; }
        100% { transform: scale(0.95); opacity: 0.8; }
    }
    .lj-pulse-badge {
        animation: lj-pulse-dot 1.8s infinite ease-in-out;
    }

    /* Search & Filter Toolbar */
    .lj-crm-toolbar {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 0.875rem 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.875rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    }
    .dark .lj-crm-toolbar {
        background: #1e293b;
        border-color: #334155;
    }

    .lj-crm-input {
        width: 100%;
        padding: 0.55rem 0.875rem;
        font-size: 0.8125rem;
        border-radius: 0.5rem;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #0f172a;
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .lj-crm-input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    .dark .lj-crm-input {
        background: #0f172a;
        border-color: #334155;
        color: #f8fafc;
    }
    .dark .lj-crm-input:focus {
        border-color: #38bdf8;
        box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
    }

    /* Mobile Stage Pill Bar (< 1024px) */
    .lj-mobile-stage-bar {
        display: none;
        overflow-x: auto;
        padding-bottom: 0.5rem;
        gap: 0.5rem;
        -webkit-overflow-scrolling: touch;
    }
    @media (max-width: 1023px) {
        .lj-mobile-stage-bar {
            display: flex;
        }
    }
    .lj-stage-pill {
        padding: 0.4rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 700;
        border-radius: 9999px;
        white-space: nowrap;
        cursor: pointer;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #475569;
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        transition: all 0.15s ease;
    }
    .dark .lj-stage-pill {
        background: #1e293b;
        border-color: #334155;
        color: #cbd5e1;
    }
    .lj-stage-pill.active {
        background: #0f172a;
        color: #ffffff;
        border-color: #0f172a;
    }
    .dark .lj-stage-pill.active {
        background: #38bdf8;
        color: #0f172a;
        border-color: #38bdf8;
    }

    /* Kanban Horizontal Board Layout */
    .lj-crm-board-scroll {
        width: 100%;
        overflow-x: auto;
        padding-bottom: 1rem;
        -webkit-overflow-scrolling: touch;
    }
    .lj-crm-board {
        display: grid;
        grid-template-columns: repeat(6, 300px);
        gap: 1rem;
        min-width: 1840px;
        align-items: start;
    }
    @media (max-width: 1023px) {
        .lj-crm-board.mobile-filtered {
            grid-template-columns: 1fr !important;
            min-width: 100% !important;
        }
    }

    /* Kanban Stage Column */
    .lj-crm-col {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        display: flex;
        flex-direction: column;
        max-height: 760px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }
    .dark .lj-crm-col {
        background: #0f172a;
        border-color: #334155;
    }

    .lj-crm-col-header {
        padding: 0.875rem 1rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-top-left-radius: 1rem;
        border-top-right-radius: 1rem;
        background: #ffffff;
    }
    .dark .lj-crm-col-header {
        background: #1e293b;
        border-bottom-color: #334155;
    }

    .lj-crm-cards-stream {
        padding: 0.75rem;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        flex: 1;
    }

    /* Individual Lead Card */
    .lj-crm-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.875rem;
        padding: 0.875rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        cursor: pointer;
        position: relative;
    }
    .dark .lj-crm-card {
        background: #1e293b;
        border-color: #334155;
    }
    .lj-crm-card:hover {
        border-color: #0284c7;
        box-shadow: 0 8px 20px rgba(2, 132, 199, 0.12);
        transform: translateY(-2px);
    }
    .lj-crm-card.is-overdue {
        border-left: 4px solid #ef4444;
        background: linear-gradient(to right, #fff5f5 0%, #ffffff 25%);
    }
    .dark .lj-crm-card.is-overdue {
        background: linear-gradient(to right, rgba(239, 68, 68, 0.1) 0%, #1e293b 25%);
    }

    /* Card Micro Elements */
    .lj-crm-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #0f172a;
        color: #ffffff;
        font-weight: 800;
        font-size: 0.6875rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .dark .lj-crm-avatar {
        background: #38bdf8;
        color: #0f172a;
    }

    .lj-channel-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.15rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.625rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        background: #f1f5f9;
        color: #475569;
    }
    .dark .lj-channel-pill {
        background: #334155;
        color: #cbd5e1;
    }

    .lj-btn-whatsapp {
        background: #25D366;
        color: #ffffff;
        font-size: 0.6875rem;
        font-weight: 700;
        padding: 0.25rem 0.6rem;
        border-radius: 0.375rem;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        text-decoration: none;
        transition: background 0.15s ease;
    }
    .lj-btn-whatsapp:hover {
        background: #1eb855;
        color: #ffffff;
    }

    /* Modal / Drawer */
    .lj-crm-modal-bg {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.65);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 1rem;
        backdrop-filter: blur(8px);
    }
    .lj-crm-modal {
        background: #ffffff;
        border-radius: 1.25rem;
        width: 100%;
        max-width: 700px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        border: 1px solid #e2e8f0;
    }
    .dark .lj-crm-modal {
        background: #1e293b;
        border-color: #334155;
        color: #f8fafc;
    }

    /* Quick Follow-up Chips */
    .lj-chip-btn {
        padding: 0.3rem 0.6rem;
        font-size: 0.6875rem;
        font-weight: 600;
        border-radius: 0.375rem;
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #cbd5e1;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .dark .lj-chip-btn {
        background: #334155;
        color: #cbd5e1;
        border-color: #475569;
    }
    .lj-chip-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
    }
    .dark .lj-chip-btn:hover {
        background: #475569;
        color: #ffffff;
    }

    /* Drag & Drop Visual Styles */
    .lj-crm-card[draggable="true"] {
        cursor: grab;
    }
    .lj-crm-card.lj-dragging {
        opacity: 0.45;
        transform: scale(0.97);
        border: 2px dashed #c5a059 !important;
    }
    .lj-crm-cards-stream.lj-drag-hover {
        background: rgba(197, 160, 89, 0.08) !important;
        border: 2px dashed #c5a059 !important;
        border-radius: 0.75rem;
    }
    .dark .lj-crm-cards-stream.lj-drag-hover {
        background: rgba(197, 160, 89, 0.15) !important;
    }
</style>

<div class="lj-crm-wrap">
    {{-- 1. LUXURY RETAIL KPI METRIC CARDS --}}
    <div class="lj-crm-kpi-grid">
        {{-- KPI 1: Active Inquiries --}}
        <div class="lj-crm-kpi-card">
            <div>
                <span style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Active Inquiries</span>
                <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;" class="dark:!text-white">
                    {{ $stats['active_count'] }}
                </div>
                <div style="font-size: 0.6875rem; color: #64748b; margin-top: 0.25rem;">
                    Storefront & WhatsApp leads
                </div>
            </div>
            <div class="lj-kpi-icon-box" style="background: #eff6ff; color: #2563eb;">
                👥
            </div>
        </div>

        {{-- KPI 2: Active Pipeline Value --}}
        <div class="lj-crm-kpi-card">
            <div>
                <span style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Active Pipeline Value</span>
                <div style="font-size: 1.5rem; font-weight: 800; color: #1d4ed8; margin-top: 0.25rem;" class="dark:!text-sky-400">
                    {{ $stats['pipeline_value'] }}
                </div>
                <div style="font-size: 0.6875rem; color: #64748b; margin-top: 0.25rem;">
                    Active Sales & Quotation proposals
                </div>
            </div>
            <div class="lj-kpi-icon-box" style="background: #f0fdf4; color: #059669;">
                💎
            </div>
        </div>

        {{-- KPI 3: Converted Orders (Won) --}}
        <div class="lj-crm-kpi-card">
            <div>
                <span style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Won / Ready for Dispatch</span>
                <div style="font-size: 1.5rem; font-weight: 800; color: #059669; margin-top: 0.25rem;" class="dark:!text-emerald-400">
                    {{ $stats['converted_value'] }}
                </div>
                <div style="font-size: 0.6875rem; color: #059669; font-weight: 600; margin-top: 0.25rem;">
                    Handoff to Fulfillment Hub
                </div>
            </div>
            <div class="lj-kpi-icon-box" style="background: #ecfdf5; color: #059669;">
                🏆
            </div>
        </div>

        {{-- KPI 4: Overdue Follow-ups (Alert) --}}
        <div class="lj-crm-kpi-card cursor-pointer hover:border-red-400 transition-colors" wire:click="toggleOverdueFilter">
            <div>
                <div style="display: flex; align-items: center; gap: 0.375rem;">
                    <span style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Overdue Follow-ups</span>
                    @if($stats['overdue_count'] > 0)
                        <span class="lj-pulse-badge" style="width: 7px; height: 7px; border-radius: 50%; background: #ef4444; display: inline-block;"></span>
                    @endif
                </div>
                <div style="font-size: 1.5rem; font-weight: 800; color: {{ $stats['overdue_count'] > 0 ? '#dc2626' : '#334155' }}; margin-top: 0.25rem;" class="dark:!text-white">
                    {{ $stats['overdue_count'] }}
                </div>
                <div style="font-size: 0.6875rem; font-weight: 600; margin-top: 0.25rem; color: {{ $this->filterOverdueOnly ? '#dc2626' : '#64748b' }};">
                    {{ $this->filterOverdueOnly ? '● Filter Applied (Click to reset)' : 'Click to show overdue only' }}
                </div>
            </div>
            <div class="lj-kpi-icon-box" style="{{ $stats['overdue_count'] > 0 ? 'background: #fef2f2; color: #dc2626;' : 'background: #f1f5f9; color: #64748b;' }}">
                ⏰
            </div>
        </div>
    </div>

    {{-- 2. SEARCH, CONTROLS & NEW LEAD TRIGGER TOOLBAR --}}
    <div class="lj-crm-toolbar">
        <div style="flex: 1; min-width: 260px;">
            <input
                type="text"
                wire:model.live.debounce.300ms="searchQuery"
                placeholder="🔍 Search patron name, WhatsApp, product title, SKU, or notes..."
                class="lj-crm-input"
            />
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            {{-- Lead Source Filter --}}
            <select wire:model.live="filterChannel" class="lj-crm-input" style="width: auto; font-size: 0.75rem;">
                <option value="all">All Channels</option>
                @foreach(\App\Models\CrmLead::getChannels() as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>

            {{-- Priority Filter --}}
            <select wire:model.live="filterPriority" class="lj-crm-input" style="width: auto; font-size: 0.75rem;">
                <option value="all">All Priorities</option>
                @foreach(\App\Models\CrmLead::getPriorities() as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>

            {{-- Overdue Only Toggle Button --}}
            <button
                type="button"
                wire:click="toggleOverdueFilter"
                class="lj-chip-btn"
                style="{{ $this->filterOverdueOnly ? 'background: #dc2626; color: #ffffff; border-color: #dc2626;' : '' }}"
            >
                {{ $this->filterOverdueOnly ? '✕ Clear Overdue' : '⏰ Overdue Only' }}
            </button>

            {{-- New Lead Primary Action Button --}}
            <button
                type="button"
                wire:click="openNewLeadModal"
                style="padding: 0.5rem 0.875rem; background: #0f172a; color: #ffffff; font-size: 0.75rem; font-weight: 700; border-radius: 0.5rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.375rem;"
                class="dark:!background-blue-600 hover:opacity-90"
            >
                <span style="font-size: 1rem; line-height: 1;">+</span> New Inquiry
            </button>
        </div>
    </div>

    {{-- 3. MOBILE STAGE FILTER PILLS (< 1024px) --}}
    <div class="lj-mobile-stage-bar">
        <button
            type="button"
            wire:click="setMobileActiveStage('all')"
            class="lj-stage-pill {{ $mobileStage === 'all' ? 'active' : '' }}"
        >
            All Stages ({{ $stats['total_leads'] }})
        </button>
        @foreach($stages as $stageKey => $meta)
            @php
                $colCount = ($boardLeads[$stageKey] ?? collect())->count();
            @endphp
            <button
                type="button"
                wire:click="setMobileActiveStage('{{ $stageKey }}')"
                class="lj-stage-pill {{ $mobileStage === $stageKey ? 'active' : '' }}"
            >
                <span>{{ $meta['icon'] }}</span>
                <span>{{ $meta['name'] }}</span>
                <span style="background: rgba(0,0,0,0.06); padding: 0.1rem 0.35rem; border-radius: 9999px; font-size: 0.6875rem;">
                    {{ $colCount }}
                </span>
            </button>
        @endforeach
    </div>

    {{-- 4. 6-STAGE VISUAL KANBAN PIPELINE BOARD --}}
    <div class="lj-crm-board-scroll">
        <div class="lj-crm-board {{ $mobileStage !== 'all' ? 'mobile-filtered' : '' }}">
            @foreach($stages as $stageKey => $meta)
                @php
                    // If filtered to a specific stage on mobile, hide others
                    if ($mobileStage !== 'all' && $mobileStage !== $stageKey) {
                        continue;
                    }
                    $colLeads = $boardLeads[$stageKey] ?? collect();
                    $colValue = $colLeads->sum('estimated_value');
                @endphp
                <div class="lj-crm-col">
                    {{-- Column Header --}}
                    <div class="lj-crm-col-header" style="border-top: 3px solid {{ $meta['color'] }};">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 1rem;">{{ $meta['icon'] }}</span>
                            <span style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; color: #0f172a;" class="dark:!text-white">
                                {{ $meta['name'] }}
                            </span>
                        </div>
                        <span style="padding: 0.15rem 0.55rem; border-radius: 9999px; font-size: 0.6875rem; font-weight: 700; background: #f1f5f9; color: #475569;" class="dark:!bg-slate-800 dark:!text-slate-300">
                            {{ $colLeads->count() }}
                        </span>
                    </div>

                    {{-- Cumulative Column Value Bar --}}
                    @if($colValue > 0)
                        <div style="padding: 0.3rem 0.875rem; background: #ffffff; border-bottom: 1px solid #f1f5f9; font-size: 0.6875rem; color: #64748b; font-weight: 600; text-align: right;" class="dark:!bg-slate-900/40 dark:!border-slate-800">
                            Total: <strong style="color: #0f172a;" class="dark:!text-slate-200">Rs. {{ number_format($colValue) }}</strong>
                        </div>
                    @endif

                    {{-- Card Stream with Drag & Drop Target Dropzone --}}
                    <div
                        class="lj-crm-cards-stream"
                        ondragover="event.preventDefault(); this.classList.add('lj-drag-hover');"
                        ondragleave="this.classList.remove('lj-drag-hover');"
                        ondrop="event.preventDefault(); this.classList.remove('lj-drag-hover'); const leadId = event.dataTransfer.getData('text/plain'); if (leadId) { @this.call('moveStage', parseInt(leadId), '{{ $stageKey }}'); }"
                    >
                        @forelse($colLeads as $lead)
                            @php
                                $isOverdue = $lead->isOverdue();
                                $initial = strtoupper(substr($lead->contact_name ?: 'C', 0, 1));
                            @endphp
                            <div
                                class="lj-crm-card {{ $isOverdue ? 'is-overdue' : '' }}"
                                wire:click="openLeadDetail({{ $lead->id }})"
                                draggable="true"
                                ondragstart="event.dataTransfer.setData('text/plain', '{{ $lead->id }}'); this.classList.add('lj-dragging');"
                                ondragend="this.classList.remove('lj-dragging');"
                            >
                                {{-- Card Top: Inquirer Avatar & Title --}}
                                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; overflow: hidden;">
                                        <div class="lj-crm-avatar">
                                            {{ $initial }}
                                        </div>
                                        <div style="overflow: hidden;">
                                            <h4 style="font-size: 0.8125rem; font-weight: 700; margin: 0; color: #0f172a; line-height: 1.3;" class="dark:!text-white truncate">
                                                {{ $lead->title }}
                                            </h4>
                                            <div style="font-size: 0.6875rem; color: #64748b; font-weight: 500;" class="dark:!text-slate-400 truncate">
                                                {{ $lead->contact_name }}
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Priority Badge --}}
                                    @php
                                        $prioStyles = [
                                            'urgent' => 'background: #fee2e2; color: #991b1b;',
                                            'high' => 'background: #fef3c7; color: #92400e;',
                                            'medium' => 'background: #eff6ff; color: #1e40af;',
                                            'low' => 'background: #f1f5f9; color: #475569;',
                                        ];
                                    @endphp
                                    <span style="padding: 0.1rem 0.45rem; border-radius: 9999px; font-size: 0.625rem; font-weight: 700; text-transform: uppercase; {{ $prioStyles[$lead->priority] ?? 'background: #f1f5f9; color: #475569;' }}">
                                        {{ $lead->priority }}
                                    </span>
                                </div>

                                {{-- Channel & Event Date Row --}}
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                                    <span class="lj-channel-pill">
                                        @if($lead->channel === 'whatsapp') 💬 WhatsApp
                                        @elseif($lead->channel === 'concierge') ✨ Concierge
                                        @elseif($lead->channel === 'website') 🌐 Storefront
                                        @elseif($lead->channel === 'showroom') 🏬 Walk-in
                                        @else 📞 Direct
                                        @endif
                                    </span>

                                    @if($lead->event_date)
                                        <span style="font-size: 0.625rem; color: #9333ea; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem;">
                                            💍 {{ $lead->event_date->format('M d') }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Inquired Garment Reference --}}
                                @if($lead->product)
                                    <div style="font-size: 0.6875rem; color: #1e40af; background: #eff6ff; border: 1px solid #dbeafe; padding: 0.25rem 0.5rem; border-radius: 0.375rem; display: flex; align-items: center; gap: 0.375rem;" class="dark:!bg-blue-950/40 dark:!border-blue-900 dark:!text-blue-300">
                                        <span>👗</span>
                                        <span class="truncate font-medium">{{ $lead->product->name }}</span>
                                    </div>
                                @endif

                                {{-- Estimated Value & Customer Spend Info --}}
                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem; padding-top: 0.25rem;">
                                    <span style="font-weight: 800; color: #047857;" class="dark:!text-emerald-400">
                                        {{ $lead->estimated_value > 0 ? 'Rs. ' . number_format((float)$lead->estimated_value, 0) : 'Custom Quote' }}
                                    </span>

                                    @if($lead->customer)
                                        <span style="font-size: 0.625rem; font-weight: 700; color: #b45309; background: #fef3c7; padding: 0.1rem 0.4rem; border-radius: 9999px;">
                                            ★ VIP Patron
                                        </span>
                                    @endif
                                </div>

                                {{-- Follow-up Due Row --}}
                                @if($lead->follow_up_date)
                                    <div style="font-size: 0.6875rem; display: flex; align-items: center; justify-content: space-between; color: {{ $isOverdue ? '#dc2626' : '#64748b' }};">
                                        <span style="font-weight: {{ $isOverdue ? '800' : '500' }};">
                                            ⏰ {{ $lead->follow_up_date->format('M d, h:i A') }}
                                        </span>
                                        @if($isOverdue)
                                            <span style="background: #fee2e2; color: #991b1b; padding: 0.1rem 0.4rem; border-radius: 9999px; font-size: 0.625rem; font-weight: 800;">
                                                OVERDUE
                                            </span>
                                        @endif
                                    </div>
                                @endif

                                {{-- Quick Action Footer (WhatsApp & Stage Mover) --}}
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #f1f5f9;" class="dark:!border-slate-800" onclick="event.stopPropagation()">
                                    @if($lead->phone)
                                        <a href="{{ $lead->whats_app_url }}" target="_blank" class="lj-btn-whatsapp">
                                            <span>💬 WhatsApp</span>
                                        </a>
                                    @endif

                                    <select
                                        style="font-size: 0.6875rem; padding: 0.25rem 0.5rem; border-radius: 0.375rem; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; margin-left: auto; outline: none;"
                                        class="dark:!bg-slate-800 dark:!border-slate-700 dark:!text-slate-300"
                                        wire:change="moveStage({{ $lead->id }}, $event.target.value)"
                                    >
                                        @foreach($stages as $sk => $sm)
                                            <option value="{{ $sk }}" {{ $lead->stage === $sk ? 'selected' : '' }}>
                                                → {{ $sm['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @empty
                            <div style="padding: 2.5rem 1rem; text-align: center; color: #94a3b8; font-size: 0.75rem; font-style: italic;">
                                No inquiries in this stage
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- 5. INTERACTIVE LEAD DETAIL & CLIENTELING ACTIVITY DRAWER --}}
    @if($this->activeLeadId && $activeLead)
        <div class="lj-crm-modal-bg" wire:click.self="closeLeadDetail">
            <div class="lj-crm-modal">
                {{-- Drawer Header with In-Drawer Stage Switcher --}}
                <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #f8fafc;" class="dark:!bg-slate-800 dark:!border-slate-700">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <select
                            style="font-size: 0.6875rem; font-weight: 800; text-transform: uppercase; padding: 0.25rem 0.6rem; border-radius: 9999px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; cursor: pointer; outline: none;"
                            class="dark:!bg-blue-950 dark:!border-blue-900 dark:!text-blue-300"
                            wire:change="moveStage({{ $activeLead->id }}, $event.target.value)"
                        >
                            @foreach($stages as $sk => $sm)
                                <option value="{{ $sk }}" {{ $activeLead->stage === $sk ? 'selected' : '' }}>
                                    {{ $sm['icon'] }} {{ $sm['name'] }}
                                </option>
                            @endforeach
                            <option value="{{ \App\Models\CrmLead::STAGE_LOST }}" {{ $activeLead->stage === \App\Models\CrmLead::STAGE_LOST ? 'selected' : '' }}>
                                ❌ Lost / Closed
                            </option>
                        </select>
                        <div>
                            <h3 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin: 0;" class="dark:!text-white">
                                {{ $activeLead->title }}
                            </h3>
                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                                Patron: <strong style="color: #0f172a;" class="dark:!text-slate-200">{{ $activeLead->contact_name }}</strong>
                                @if($activeLead->phone)
                                    • <span style="font-family: monospace;">{{ $activeLead->phone }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        @if($activeLead->phone)
                            <a href="{{ $activeLead->whats_app_url }}" target="_blank" class="lj-btn-whatsapp" style="padding: 0.4rem 0.75rem; font-size: 0.75rem;">
                                💬 Clienteling WhatsApp
                            </a>
                        @endif
                        <button wire:click="closeLeadDetail" style="background: none; border: none; font-size: 1.25rem; color: #94a3b8; cursor: pointer; padding: 0.25rem 0.5rem;">✕</button>
                    </div>
                </div>

                {{-- Drawer Body --}}
                <div style="padding: 1.25rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1rem; max-height: 72vh;">
                    {{-- 2-Column Overview: Patron Profile vs Garment --}}
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.875rem;">
                        {{-- Patron Profile Box --}}
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.875rem;" class="dark:!bg-slate-800/50 dark:!border-slate-700">
                            <span style="font-size: 0.625rem; font-weight: 800; text-transform: uppercase; color: #64748b; display: block;">Patron Dossier</span>
                            <div style="font-size: 0.875rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;" class="dark:!text-white">
                                {{ $activeLead->contact_name }}
                            </div>
                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                                {{ $activeLead->email ?: 'No email registered' }}
                            </div>
                            @if($activeLead->customer)
                                <div style="margin-top: 0.5rem; font-size: 0.6875rem; font-weight: 700; color: #059669; background: #ecfdf5; padding: 0.25rem 0.5rem; border-radius: 0.375rem; border: 1px solid #a7f3d0;">
                                    ★ Registered Patron | Spend: Rs. {{ number_format($activeLead->customer->lifetime_spend) }} ({{ $activeLead->customer->total_orders_count }} orders)
                                </div>
                            @endif
                        </div>

                        {{-- Inquired Garment Box --}}
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.875rem;" class="dark:!bg-slate-800/50 dark:!border-slate-700">
                            <span style="font-size: 0.625rem; font-weight: 800; text-transform: uppercase; color: #64748b; display: block;">Garment Reference</span>
                            @if($activeLead->product)
                                <div style="font-size: 0.875rem; font-weight: 800; color: #1e40af; margin-top: 0.25rem;" class="dark:!text-blue-300">
                                    📦 {{ $activeLead->product->name }}
                                </div>
                                <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                                    SKU: {{ $activeLead->product->sku }} | Price: Rs. {{ number_format($activeLead->product->price) }}
                                </div>
                            @else
                                <div style="font-size: 0.8125rem; font-style: italic; color: #64748b; margin-top: 0.25rem;">
                                    General Product Inquiry
                                </div>
                                <div style="font-size: 0.8125rem; font-weight: 800; color: #0f172a; margin-top: 0.15rem;" class="dark:!text-white">
                                    Value: Rs. {{ number_format((float)$activeLead->estimated_value) }}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Customer Order / Sizing Notes --}}
                    @if($activeLead->bespoke_notes)
                        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.75rem; padding: 0.875rem; font-size: 0.75rem;" class="dark:!bg-blue-950/30 dark:!border-blue-900">
                            <strong style="color: #1e40af;" class="dark:!text-blue-300">Client Sizing & Design Preferences:</strong>
                            <p style="margin: 0.35rem 0 0 0; color: #334155; white-space: pre-line;" class="dark:!text-slate-300">
                                {{ $activeLead->bespoke_notes }}
                            </p>
                        </div>
                    @endif

                    {{-- Follow-up Scheduling Section with Quick Chips --}}
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.875rem; display: flex; flex-direction: column; gap: 0.5rem;" class="dark:!bg-slate-800/40 dark:!border-slate-700">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-size: 0.75rem; font-weight: 800; color: #0f172a;" class="dark:!text-white">⏰ Schedule / Update Follow-up SLA</span>
                            {{-- Quick Days Chips --}}
                            <div style="display: flex; gap: 0.375rem;">
                                <button type="button" wire:click="setQuickFollowUpDays(1, 'Tomorrow follow-up on design choice')" class="lj-chip-btn">+1 Day</button>
                                <button type="button" wire:click="setQuickFollowUpDays(2, 'Auto 48h WhatsApp clienteling follow-up')" class="lj-chip-btn" style="background: #dbeafe; color: #1e40af; border-color: #bfdbfe;">+2 Days (SLA)</button>
                                <button type="button" wire:click="setQuickFollowUpDays(7, 'Weekly follow-up')" class="lj-chip-btn">+7 Days</button>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 0.5rem;">
                            <input type="datetime-local" wire:model="quickFollowUpDate" class="lj-crm-input" style="font-size: 0.75rem;" />
                            <input type="text" wire:model="quickFollowUpNotes" placeholder="Directive (e.g. Call regarding color swatch video)" class="lj-crm-input" style="font-size: 0.75rem;" />
                        </div>

                        <button type="button" wire:click="updateLeadFollowUp" style="align-self: flex-start; padding: 0.4rem 0.875rem; background: #0f172a; color: #ffffff; border-radius: 0.5rem; font-size: 0.75rem; font-weight: 700; border: none; cursor: pointer;">
                            Save Follow-up Date
                        </button>
                    </div>

                    {{-- Converted Order & Fulfillment Hub Handoff Banner --}}
                    @if($activeLead->order)
                        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.75rem; padding: 0.875rem; font-size: 0.75rem;" class="dark:!bg-emerald-950/30 dark:!border-emerald-800">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem;">
                                <strong style="color: #166534; font-size: 0.8125rem;" class="dark:!text-emerald-300">
                                    📦 Converted to Order #{{ $activeLead->order->order_number }}
                                </strong>
                                <span style="background: #dcfce7; color: #166534; padding: 0.15rem 0.5rem; border-radius: 9999px; font-weight: 800; font-size: 0.6875rem; text-transform: uppercase;">
                                    {{ $activeLead->order->status }}
                                </span>
                            </div>
                            <div style="font-size: 0.6875rem; color: #374151; margin-bottom: 0.625rem;" class="dark:!text-slate-400">
                                Operational Source of Truth: <strong>Fulfillment Hub</strong> | Courier: <strong>{{ $activeLead->order->carrier ?: 'Unassigned' }}</strong> | Status: <strong>{{ ucfirst($activeLead->order->courier_status ?: 'Pending') }}</strong>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="/intadmin/fulfillment-hub" target="_blank" style="padding: 0.35rem 0.75rem; background: #059669; color: #ffffff; border-radius: 0.375rem; font-weight: 700; text-decoration: none; font-size: 0.6875rem;">
                                    Open in Fulfillment Hub →
                                </a>
                                <a href="/intadmin/orders/{{ $activeLead->order->id }}" target="_blank" style="padding: 0.35rem 0.75rem; background: #ffffff; border: 1px solid #cbd5e1; color: #334155; border-radius: 0.375rem; font-weight: 700; text-decoration: none; font-size: 0.6875rem;" class="dark:!bg-slate-800 dark:!text-slate-200 dark:!border-slate-700">
                                    View Order Details
                                </a>
                            </div>
                        </div>
                    @elseif(empty($activeLead->order_id) && $activeLead->stage !== \App\Models\CrmLead::STAGE_LOST)
                        {{-- 1-Click Order Conversion Section --}}
                        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.75rem; padding: 0.875rem; display: flex; flex-direction: column; gap: 0.625rem;" class="dark:!bg-emerald-950/20 dark:!border-emerald-800">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span style="font-size: 0.75rem; font-weight: 800; color: #166534;" class="dark:!text-emerald-300">Convert Lead to Sales Order</span>
                                <span style="font-size: 0.6875rem; color: #059669; font-weight: 600;">Dispatches to Fulfillment Hub</span>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr 1.5fr; gap: 0.5rem;">
                                <div>
                                    <label style="font-size: 0.625rem; font-weight: 700; color: #475569; display: block; margin-bottom: 0.2rem;">Agreed NPR (Rs.)</label>
                                    <input type="number" wire:model="orderAgreedAmount" class="lj-crm-input" style="font-size: 0.75rem;" />
                                </div>
                                <div>
                                    <label style="font-size: 0.625rem; font-weight: 700; color: #475569; display: block; margin-bottom: 0.2rem;">Payment Arrangement</label>
                                    <select wire:model="orderPaymentMethod" class="lj-crm-input" style="font-size: 0.75rem;">
                                        <option value="cod">Cash on Delivery (COD)</option>
                                        <option value="manual_bank_transfer">Bank Transfer / QR</option>
                                        <option value="connectips">ConnectIPS</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size: 0.625rem; font-weight: 700; color: #475569; display: block; margin-bottom: 0.2rem;">Delivery Address</label>
                                    <input type="text" wire:model="orderDeliveryAddress" class="lj-crm-input" style="font-size: 0.75rem;" />
                                </div>
                            </div>

                            <button type="button" wire:click="convertActiveLeadToOrder" style="align-self: flex-start; padding: 0.5rem 1rem; background: #059669; color: #ffffff; font-size: 0.75rem; font-weight: 800; border-radius: 0.5rem; border: none; cursor: pointer;">
                                ✓ Generate Order & Move to Won
                            </button>
                        </div>
                    @endif

                    {{-- Activity & Audit Timeline Stream --}}
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <span style="font-size: 0.6875rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">
                            Activity & Clienteling Timeline
                        </span>

                        {{-- Quick Add Note Input --}}
                        <div style="display: flex; gap: 0.5rem;">
                            <input
                                type="text"
                                wire:model="quickNoteText"
                                placeholder="Add clienteling note (e.g. Size 42 confirmed, delivery time verified)..."
                                class="lj-crm-input"
                                style="font-size: 0.75rem;"
                                wire:keydown.enter="addQuickNote"
                            />
                            <button
                                type="button"
                                wire:click="addQuickNote"
                                style="padding: 0.45rem 0.875rem; background: #2563eb; color: #ffffff; font-size: 0.75rem; font-weight: 700; border-radius: 0.5rem; border: none; cursor: pointer; white-space: nowrap;"
                            >
                                Add Note
                            </button>
                        </div>

                        {{-- Chronological Activity Stream --}}
                        <div style="border-left: 2px solid #e2e8f0; margin-left: 0.5rem; padding-left: 0.75rem; display: flex; flex-direction: column; gap: 0.75rem; padding-top: 0.5rem;" class="dark:!border-slate-700">
                            @forelse($activeLead->activities as $act)
                                <div style="font-size: 0.75rem; position: relative;">
                                    <div style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.6875rem; color: #64748b;">
                                        <strong style="color: #0f172a;" class="dark:!text-slate-200">{{ $act->type_label }}</strong>
                                        <span>•</span>
                                        <span>{{ $act->created_at->format('d M Y, h:i A') }}</span>
                                        @if($act->staff)
                                            <span>by <strong>{{ $act->staff->name }}</strong></span>
                                        @endif
                                    </div>
                                    <p style="margin: 0.2rem 0 0 0; color: #334155; line-height: 1.4;" class="dark:!text-slate-300">
                                        {{ $act->description }}
                                    </p>
                                </div>
                            @empty
                                <div style="color: #94a3b8; font-size: 0.75rem; font-style: italic;">
                                    No activity logged yet.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 6. CREATE NEW INQUIRY / LEAD MODAL --}}
    @if($isCreating)
        <div class="lj-crm-modal-bg" wire:click.self="closeNewLeadModal">
            <div class="lj-crm-modal" style="max-width: 580px;">
                <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #f8fafc;" class="dark:!bg-slate-800 dark:!border-slate-700">
                    <h3 style="font-size: 0.9375rem; font-weight: 800; color: #0f172a; margin: 0;" class="dark:!text-white">
                        Create Customer Inquiry / Lead
                    </h3>
                    <button wire:click="closeNewLeadModal" style="background: none; border: none; font-size: 1.25rem; color: #94a3b8; cursor: pointer;">✕</button>
                </div>

                <div style="padding: 1.25rem; overflow-y: auto; display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.75rem; max-height: 75vh;">
                    <div>
                        <label style="font-weight: 700; display: block; margin-bottom: 0.25rem;">Inquiry Title *</label>
                        <input type="text" wire:model="newTitle" placeholder="e.g. Leather Oxford Shoes Inquiry" class="lj-crm-input" />
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.625rem;">
                        <div>
                            <label style="font-weight: 700; display: block; margin-bottom: 0.25rem;">Customer Name *</label>
                            <input type="text" wire:model="newContactName" placeholder="Priya Sharma" class="lj-crm-input" />
                        </div>
                        <div>
                            <label style="font-weight: 700; display: block; margin-bottom: 0.25rem;">WhatsApp / Mobile</label>
                            <input type="text" wire:model="newPhone" placeholder="9841234567 or +977..." class="lj-crm-input" />
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.625rem;">
                        <div>
                            <label style="font-weight: 700; display: block; margin-bottom: 0.25rem;">Email Address</label>
                            <input type="email" wire:model="newEmail" placeholder="priya@example.com" class="lj-crm-input" />
                        </div>
                        <div>
                            <label style="font-weight: 700; display: block; margin-bottom: 0.25rem;">Lead Source Channel</label>
                            <select wire:model="newChannel" class="lj-crm-input">
                                @foreach(\App\Models\CrmLead::getChannels() as $k => $l)
                                    <option value="{{ $k }}">{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.625rem;">
                        <div>
                            <label style="font-weight: 700; display: block; margin-bottom: 0.25rem;">Priority</label>
                            <select wire:model="newPriority" class="lj-crm-input">
                                @foreach(\App\Models\CrmLead::getPriorities() as $pk => $pl)
                                    <option value="{{ $pk }}">{{ $pl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="font-weight: 700; display: block; margin-bottom: 0.25rem;">Estimated Budget (NPR)</label>
                            <input type="number" wire:model="newEstimatedValue" placeholder="Rs. 0.00" class="lj-crm-input" />
                        </div>
                    </div>

                    <div>
                        <label style="font-weight: 700; display: block; margin-bottom: 0.25rem;">Inquired Product (Optional)</label>
                        <select wire:model="newProductId" class="lj-crm-input">
                            <option value="">None / General Inquiry</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} (Rs. {{ number_format($p->price) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.625rem;">
                        <div>
                            <label style="font-weight: 700; display: block; margin-bottom: 0.25rem;">Initial Follow-up Due</label>
                            <input type="datetime-local" wire:model="newFollowUpDate" class="lj-crm-input" />
                        </div>
                        <div>
                            <label style="font-weight: 700; display: block; margin-bottom: 0.25rem;">Required By Date</label>
                            <input type="date" wire:model="newEventDate" class="lj-crm-input" />
                        </div>
                    </div>

                    <div>
                        <label style="font-weight: 700; display: block; margin-bottom: 0.25rem;">Customer Notes / Sizing Details</label>
                        <textarea wire:model="newBespokeNotes" rows="2" placeholder="Color preference, sizing notes, or delivery instructions..." class="lj-crm-input"></textarea>
                    </div>
                </div>

                <div style="padding: 0.875rem 1.25rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 0.5rem; background: #f8fafc;" class="dark:!bg-slate-800 dark:!border-slate-700">
                    <button type="button" wire:click="closeNewLeadModal" class="lj-chip-btn">Cancel</button>
                    <button type="button" wire:click="saveNewLead" style="padding: 0.45rem 1rem; background: #0f172a; color: #ffffff; font-size: 0.75rem; font-weight: 800; border-radius: 0.5rem; border: none; cursor: pointer;">
                        Save Lead
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
</x-filament-panels::page>
