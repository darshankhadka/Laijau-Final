<header class="lj-pos-topbar">
    <div class="lj-pos-brand">
        <span class="lj-brand-title">LAIJAU</span>

        <!-- Operational Terminal & Showroom Selector -->
        @php
            $activeTerminal = $this->activeStation;
            $sessionStatus = $this->sessionState;
            $opTerminals = $this->operationalTerminals;
            $showroomTitle = ($activeTerminal?->id == 151 || $activeTerminal?->location === 'Old Showroom')
                ? 'Old Showroom · Terminal 2'
                : 'New Showroom · Terminal 1';
        @endphp
        <button
            type="button"
            wire:click="openSelectShowroomModal"
            class="lj-terminal-badge"
            title="Active Location: {{ $showroomTitle }} (Click to switch showroom)">
            <svg style="width: 15px; height: 15px; color: #059669;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                <line x1="8" y1="21" x2="16" y2="21" />
                <line x1="12" y1="17" x2="12" y2="21" />
            </svg>
            <span style="font-weight: 800; font-size: 0.8125rem; color: #0F172A;">
                {{ $showroomTitle }}
            </span>
            <svg style="width: 13px; height: 13px; color: #64748B;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Session Status Badge -->
        @if($sessionStatus['state'] === 'active')
        <button
            type="button"
            wire:click="openCloseSessionModal"
            class="lj-pos-session-badge active"
            title="Session is Open. Click to close register.">
            <span class="lj-pulse-dot" style="background: #10B981;"></span>
            <span style="font-weight: 700;">Open</span>
        </button>
        @elseif($sessionStatus['state'] === 'opening_required')
        <button
            type="button"
            wire:click="openSessionModal"
            class="lj-pos-session-badge opening-required"
            title="Mandatory opening cash balance required before sales">
            <span class="lj-pulse-dot" style="background: #F59E0B;"></span>
            <span style="font-weight: 700;">Not Opened</span>
        </button>
        @elseif($sessionStatus['state'] === 'closing_required')
        <button
            type="button"
            wire:click="openCloseSessionModal({{ $sessionStatus['session']?->id }})"
            class="lj-pos-session-badge closing-required"
            title="Yesterday's session was not closed. Click to finalize closing reconciliation.">
            <span class="lj-pulse-dot" style="background: #EF4444;"></span>
            <span style="font-weight: 800; color: #DC2626;">Closing Required</span>
        </button>
        @elseif($sessionStatus['state'] === 'closed')
        <div class="lj-pos-session-badge closed" title="Register closed for today">
            <span style="width: 6px; height: 6px; border-radius: 9999px; background: #94A3B8;"></span>
            <span style="font-weight: 700;">Closed</span>
        </div>
        @endif

        <!-- Opening Cash & Current Session Sales -->
        @if($sessionStatus['state'] === 'active')
        <div style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.75rem; background: #F8FAFC; padding: 0.25rem 0.65rem; border-radius: 0.375rem; border: 1px solid #E2E8F0;">
            <span style="color: #64748B;">Float:</span>
            <strong style="font-family: monospace; color: #0F172A;">Rs. {{ number_format($sessionStatus['session']->opening_balance, 0) }}</strong>
            <span style="color: #CBD5E1;">|</span>
            <span style="color: #64748B;">Sales:</span>
            <strong style="font-family: monospace; color: #059669;">Rs. {{ number_format($this->currentSessionSalesTotal, 0) }}</strong>
        </div>
        @endif

        <!-- Authoritative Nepal Date & Live Clock -->
        <div class="lj-pos-ktm-clock" style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.75rem; font-weight: 600; background: #FFFFFF; padding: 0.25rem 0.65rem; border-radius: 9999px; border: 1px solid #E2E8F0; color: #0F172A;" x-data="{ ktmTime: '{{ now()->timezone('Asia/Kathmandu')->format('h:i:s A') }}' }" x-init="setInterval(() => { const d = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Kathmandu' })); ktmTime = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }); }, 1000)">
            <svg style="width: 14px; height: 14px; color: #059669;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
            </svg>
            <span style="font-weight: 700;">{{ $sessionStatus['business_date'] ?? now('Asia/Kathmandu')->toDateString() }}</span>
            <span style="color: #CBD5E1;">·</span>
            <span style="font-family: monospace; font-weight: 700; color: #059669;" x-text="ktmTime"></span>
            <span style="font-size: 0.625rem; font-weight: 800; color: #64748B; background: #F1F5F9; padding: 0.1rem 0.35rem; border-radius: 3px;">NPT</span>
        </div>

        <!-- Cashier Identity -->
        <div class="lj-pos-cashier-label" style="font-size: 0.75rem; color: #475569; display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 600;">
            <svg style="width: 14px; height: 14px; color: #64748B;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span>{{ Auth::user()?->name ?? 'Cashier' }}</span>
        </div>
    </div>
    <!-- Right Quick Actions -->
    <div class="lj-topbar-actions">
        <!-- POS Shift Closing / Opening Trigger Button -->
        @if($sessionStatus['state'] === 'active')
        <button
            type="button"
            wire:click="openCloseSessionModal"
            class="lj-btn-secondary"
            style="height: 34px; padding: 0 0.85rem; font-size: 0.75rem; font-weight: 700; border-color: #CBD5E1; color: #0F172A; display: inline-flex; align-items: center; gap: 0.4rem; background: #FFFFFF; border-radius: 0.375rem; cursor: pointer;"
            title="End shift and close daily POS session">
            <svg style="width: 14px; height: 14px; color: #059669;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0110 0v4" />
            </svg>
            <span>Close Shift</span>
        </button>
        @elseif($sessionStatus['state'] === 'opening_required')
        <button
            type="button"
            wire:click="openSessionModal"
            class="lj-btn-secondary"
            style="height: 34px; padding: 0 0.85rem; font-size: 0.75rem; font-weight: 700; background: #FEF3C7; color: #92400E; border-color: #FCD34D; display: inline-flex; align-items: center; gap: 0.4rem; border-radius: 0.375rem; cursor: pointer;"
            title="Open session with cash float">
            <svg style="width: 14px; height: 14px; color: #D97706;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
            <span>Open Register</span>
        </button>
        @elseif($sessionStatus['state'] === 'closing_required')
        <button
            type="button"
            wire:click="openCloseSessionModal({{ $sessionStatus['session']?->id }})"
            class="lj-btn-secondary"
            style="height: 34px; padding: 0 0.85rem; font-size: 0.75rem; font-weight: 700; background: #FEE2E2; color: #991B1B; border-color: #F87171; display: inline-flex; align-items: center; gap: 0.4rem; border-radius: 0.375rem; cursor: pointer;"
            title="Resolve overdue closing">
            <svg style="width: 14px; height: 14px; color: #DC2626;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>Resolve Overdue</span>
        </button>
        @endif

        @if(count($heldSales) > 0)
        <button
            type="button"
            wire:click="openModal('held_sales')"
            class="lj-btn-held"
            title="View suspended sales on hold"
            style="display: inline-flex; align-items: center; gap: 0.35rem;">
            <svg style="width: 13px; height: 13px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="6" y="4" width="4" height="16" />
                <rect x="14" y="4" width="4" height="16" />
            </svg>
            <span>Held ({{ count($heldSales) }})</span>
        </button>
        @endif

        @if(count($cart) > 0)
        <button
            type="button"
            wire:click="confirmClearCart"
            class="lj-btn-secondary"
            style="height: 34px; padding: 0 0.65rem; color: #DC2626; border-color: #FECACA; background: #FFFFFF; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer; border-radius: 0.375rem;"
            title="Clear current cart ticket">
            <svg style="width: 13px; height: 13px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            <span>Clear Cart</span>
        </button>
        @endif

        <span class="lj-shortcut-hint" style="color: #94A3B8; font-size: 0.6875rem; font-weight: 500;">
            F8: Pay · F9: Hold
        </span>
    </div>
</header>
