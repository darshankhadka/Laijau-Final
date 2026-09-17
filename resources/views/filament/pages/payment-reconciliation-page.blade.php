<x-filament-panels::page>
    <div class="lj-pr-root">

        {{-- SCOPED RETAIL & FINANCIAL RECONCILIATION STYLES --}}
        <style>
            .lj-pr-root {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica NNpe", Arial, sans-serif;
                color: #111827;
                display: flex;
                flex-direction: column;
                gap: 1.25rem;
                padding-bottom: 3rem;
                box-sizing: border-box;
            }

            .lj-pr-root *,
            .lj-pr-root *::before,
            .lj-pr-root *::after {
                box-sizing: border-box;
            }

            /* Card Containers */
            .lj-pr-card {
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 0.875rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
                padding: 1.25rem 1.5rem;
            }

            /* 5-Column Balances Grid */
            .lj-pr-balances-grid {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 1rem;
            }

            @media (max-width: 1280px) {
                .lj-pr-balances-grid {
                    grid-template-columns: repeat(3, 1fr);
                }
            }

            @media (max-width: 768px) {
                .lj-pr-balances-grid {
                    grid-template-columns: 1fr;
                }
            }

            .lj-pr-balance-card {
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 0.75rem;
                padding: 1rem 1.25rem;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
                transition: all 0.15s ease;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }

            .lj-pr-balance-card.clickable {
                cursor: pointer;
            }

            .lj-pr-balance-card.clickable:hover {
                border-color: #059669;
                transform: translateY(-1px);
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.06);
            }

            .lj-pr-balance-card.active {
                border-color: #059669;
                box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.2);
                background: #f0fdf4;
            }

            .lj-pr-balance-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.5rem;
            }

            .lj-pr-balance-title {
                font-size: 0.6875rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #6b7280;
            }

            .lj-pr-balance-dot {
                width: 0.5rem;
                height: 0.5rem;
                border-radius: 9999px;
            }

            .lj-pr-balance-num {
                font-size: 1.35rem;
                font-weight: 900;
                color: #111827;
                margin-top: 0.375rem;
                letter-spacing: -0.02em;
            }

            .lj-pr-balance-sub {
                font-size: 0.6875rem;
                color: #9ca3af;
                margin-top: 0.25rem;
            }

            /* Horizontal Tabs Navigation */
            .lj-pr-tabs {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                border-bottom: 1px solid #e5e7eb;
                overflow-x: auto;
                padding-bottom: 0.25rem;
            }

            .lj-pr-tab-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.625rem 1rem;
                font-size: 0.8125rem;
                font-weight: 600;
                color: #6b7280;
                background: transparent;
                border: none;
                border-bottom: 2px solid transparent;
                cursor: pointer;
                transition: all 0.15s ease;
                white-space: nowrap;
                border-top-left-radius: 0.5rem;
                border-top-right-radius: 0.5rem;
            }

            .lj-pr-tab-btn:hover {
                color: #111827;
                background: #f9fafb;
            }

            .lj-pr-tab-btn.active {
                color: #065f46;
                border-bottom-color: #059669;
                font-weight: 700;
                background: #ecfdf5;
            }

            /* Settlement Forms */
            .lj-pr-form-card {
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 0.875rem;
                padding: 1.5rem;
                max-width: 44rem;
                display: flex;
                flex-direction: column;
                gap: 1.25rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            }

            .lj-pr-form-header {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }

            .lj-pr-form-title {
                font-size: 1.125rem;
                font-weight: 800;
                color: #111827;
                margin: 0;
            }

            .lj-pr-form-sub {
                font-size: 0.8125rem;
                color: #6b7280;
                margin: 0;
            }

            .lj-pr-grid-2 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }

            @media (max-width: 640px) {
                .lj-pr-grid-2 {
                    grid-template-columns: 1fr;
                }
            }

            .lj-pr-field {
                display: flex;
                flex-direction: column;
                gap: 0.375rem;
            }

            .lj-pr-label {
                font-size: 0.75rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: #374151;
            }

            .lj-pr-input,
            .lj-pr-select {
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

            .lj-pr-input:focus,
            .lj-pr-select:focus {
                border-color: #059669;
                box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
            }

            /* Double Entry Ledger Preview */
            .lj-pr-ledger-box {
                background: #f9fafb;
                border: 1px solid #e5e7eb;
                border-radius: 0.625rem;
                padding: 0.875rem 1rem;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 0.75rem;
                color: #4b5563;
                display: flex;
                flex-direction: column;
                gap: 0.375rem;
            }

            .lj-pr-ledger-box div {
                display: flex;
                justify-content: space-between;
            }

            /* Submit Buttons */
            .lj-pr-btn {
                background: #0a2e23;
                color: #ffffff;
                border: none;
                border-radius: 0.5rem;
                font-size: 0.875rem;
                font-weight: 700;
                padding: 0.75rem 1.5rem;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                align-self: flex-start;
                transition: background 0.15s;
            }

            .lj-pr-btn:hover {
                background: #17654e;
            }

            .lj-pr-btn.blue {
                background: #1d4ed8;
            }

            .lj-pr-btn.blue:hover {
                background: #1e40af;
            }

            /* Audit Table */
            .lj-pr-table-card {
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 0.875rem;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            }

            .lj-pr-table-header {
                padding: 1rem 1.25rem;
                background: #f9fafb;
                border-bottom: 1px solid #e5e7eb;
                font-size: 0.8125rem;
                font-weight: 600;
                color: #4b5563;
            }

            .lj-pr-table-wrapper {
                overflow-x: auto;
            }

            .lj-pr-table {
                width: 100%;
                border-collapse: collapse;
                text-align: left;
                font-size: 0.8125rem;
            }

            .lj-pr-table th {
                background: #f9fafb;
                border-bottom: 1px solid #e5e7eb;
                padding: 0.75rem 1rem;
                font-size: 0.6875rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #6b7280;
            }

            .lj-pr-table td {
                padding: 0.875rem 1rem;
                border-bottom: 1px solid #f3f4f6;
            }

            .lj-pr-table tr:hover td {
                background: #f9fafb;
            }

            /* Dark Mode Adaptations */
            .dark .lj-pr-card,
            .dark .lj-pr-balance-card,
            .dark .lj-pr-form-card,
            .dark .lj-pr-table-card {
                background: #1f2937;
                border-color: #374151;
            }

            .dark .lj-pr-balance-num,
            .dark .lj-pr-form-title {
                color: #f9fafb;
            }

            .dark .lj-pr-balance-card.active {
                background: #064e3b;
                border-color: #059669;
            }

            .dark .lj-pr-tabs {
                border-bottom-color: #374151;
            }

            .dark .lj-pr-tab-btn {
                color: #9ca3af;
            }

            .dark .lj-pr-tab-btn.active {
                background: #064e3b;
                color: #34d399;
                border-bottom-color: #10b981;
            }

            .dark .lj-pr-input,
            .dark .lj-pr-select {
                background: #111827;
                border-color: #4b5563;
                color: #f9fafb;
            }

            .dark .lj-pr-label {
                color: #d1d5db;
            }

            .dark .lj-pr-ledger-box {
                background: #111827;
                border-color: #374151;
                color: #9ca3af;
            }

            .dark .lj-pr-table-header,
            .dark .lj-pr-table th {
                background: #111827;
                border-color: #374151;
                color: #9ca3af;
            }

            .dark .lj-pr-table td {
                border-bottom-color: #374151;
            }

            .dark .lj-pr-table tr:hover td {
                background: #111827;
            }
        </style>

        {{-- TOP CLEARING BALANCES CARDS --}}
        <div class="lj-pr-balances-grid">
            {{-- 1. Operating Bank (NPR) --}}
            <div class="lj-pr-balance-card">
                <div class="lj-pr-balance-header">
                    <span class="lj-pr-balance-title">Operating Bank (NPR)</span>
                    <span class="lj-pr-balance-dot" style="background: #059669;"></span>
                </div>
                <div class="lj-pr-balance-num" style="color: #047857;">
                    {{ \App\Helpers\NepaliNumberHelper::formatCurrency($this->balances['bank'], 'Rs. ', 0) }}
                </div>
                <div class="lj-pr-balance-sub">Primary Nabil / NIMB</div>
            </div>

            {{-- 2. eSewa Wallet --}}
            <div wire:click="$set('activeTab', 'esewa')"
                class="lj-pr-balance-card clickable {{ $activeTab === 'esewa' ? 'active' : '' }}">
                <div class="lj-pr-balance-header">
                    <span class="lj-pr-balance-title" style="color: #15803d;">eSewa Wallet</span>
                    <span class="lj-pr-balance-dot" style="background: #22c55e;"></span>
                </div>
                <div class="lj-pr-balance-num">
                    {{ \App\Helpers\NepaliNumberHelper::formatCurrency($this->balances['esewa'], 'Rs. ', 0) }}
                </div>
                <div class="lj-pr-balance-sub">Awaiting Bank Transfer</div>
            </div>

            {{-- 3. ConnectIPS --}}
            <div wire:click="$set('activeTab', 'connectips')"
                class="lj-pr-balance-card clickable {{ $activeTab === 'connectips' ? 'active' : '' }}">
                <div class="lj-pr-balance-header">
                    <span class="lj-pr-balance-title" style="color: #1d4ed8;">ConnectIPS / NCHL</span>
                    <span class="lj-pr-balance-dot" style="background: #3b82f6;"></span>
                </div>
                <div class="lj-pr-balance-num">
                    {{ \App\Helpers\NepaliNumberHelper::formatCurrency($this->balances['connectips'], 'Rs. ', 0) }}
                </div>
                <div class="lj-pr-balance-sub">Gateway In-Transit</div>
            </div>

            {{-- 4. COD Receivables --}}
            <div wire:click="$set('activeTab', 'cod')"
                class="lj-pr-balance-card clickable {{ $activeTab === 'cod' ? 'active' : '' }}">
                <div class="lj-pr-balance-header">
                    <span class="lj-pr-balance-title" style="color: #b45309;">COD Receivables</span>
                    <span class="lj-pr-balance-dot" style="background: #f59e0b;"></span>
                </div>
                <div class="lj-pr-balance-num">
                    {{ \App\Helpers\NepaliNumberHelper::formatCurrency($this->balances['cod'], 'Rs. ', 0) }}
                </div>
                <div class="lj-pr-balance-sub">Held by Couriers (NCM/Pathao)</div>
            </div>

            {{-- 5. POS Register Cash --}}
            <div wire:click="$set('activeTab', 'cash_deposit')"
                class="lj-pr-balance-card clickable {{ $activeTab === 'cash_deposit' ? 'active' : '' }}">
                <div class="lj-pr-balance-header">
                    <span class="lj-pr-balance-title">POS Register Cash</span>
                    <span class="lj-pr-balance-dot" style="background: #9ca3af;"></span>
                </div>
                <div class="lj-pr-balance-num">
                    {{ \App\Helpers\NepaliNumberHelper::formatCurrency($this->balances['cash'], 'Rs. ', 0) }}
                </div>
                <div class="lj-pr-balance-sub">Physical Cash Drawer</div>
            </div>
        </div>

        {{-- HORIZONTAL RECONCILIATION TABS --}}
        <div class="lj-pr-tabs">
            <button type="button" wire:click="$set('activeTab', 'esewa')"
                class="lj-pr-tab-btn {{ $activeTab === 'esewa' ? 'active' : '' }}">
                <x-filament::icon icon="heroicon-o-wallet" class="w-4 h-4" />
                eSewa Wallet Reconciliation
            </button>
            <button type="button" wire:click="$set('activeTab', 'connectips')"
                class="lj-pr-tab-btn {{ $activeTab === 'connectips' ? 'active' : '' }}">
                <x-filament::icon icon="heroicon-o-credit-card" class="w-4 h-4" />
                ConnectIPS / NCHL Gateway
            </button>
            <button type="button" wire:click="$set('activeTab', 'cod')"
                class="lj-pr-tab-btn {{ $activeTab === 'cod' ? 'active' : '' }}">
                <x-filament::icon icon="heroicon-o-truck" class="w-4 h-4" />
                Courier COD Remittances
            </button>
            <button type="button" wire:click="$set('activeTab', 'cash_deposit')"
                class="lj-pr-tab-btn {{ $activeTab === 'cash_deposit' ? 'active' : '' }}">
                <x-filament::icon icon="heroicon-o-banknotes" class="w-4 h-4" />
                POS Cash Deposit to Bank
            </button>
            <button type="button" wire:click="$set('activeTab', 'audit')"
                class="lj-pr-tab-btn {{ $activeTab === 'audit' ? 'active' : '' }}">
                <x-filament::icon icon="heroicon-o-document-text" class="w-4 h-4" />
                Reconciled Ledger & Audit
            </button>
        </div>

        {{-- TAB 1: ESEWA SETTLEMENT FORM --}}
        @if($activeTab === 'esewa')
        <div class="lj-pr-form-card">
            <div class="lj-pr-form-header">
                <h3 class="lj-pr-form-title">eSewa Merchant Wallet Settlement</h3>
                <p class="lj-pr-form-sub">
                    Record accumulated customer eSewa digital wallet settlements transferred into your commercial bank account.
                </p>
            </div>

            <div class="lj-pr-grid-2">
                <div class="lj-pr-field">
                    <label class="lj-pr-label">Net Deposited into Bank (Rs.) *</label>
                    <input type="number" step="0.01" wire:model="esewaNetPayout" placeholder="e.g. 50000"
                        class="lj-pr-input" style="font-weight: 800; font-size: 1.125rem;">
                </div>
                <div class="lj-pr-field">
                    <label class="lj-pr-label">eSewa Commission Fee (Rs.)</label>
                    <input type="number" step="0.01" wire:model="esewaFee" placeholder="0.00"
                        class="lj-pr-input">
                </div>
                <div class="lj-pr-field">
                    <label class="lj-pr-label">Bank Statement Reference / Txn ID</label>
                    <input type="text" wire:model="esewaRef" placeholder="e.g. ESEWA-WITHDRAW-2609"
                        class="lj-pr-input">
                </div>
                <div class="lj-pr-field">
                    <label class="lj-pr-label">Settlement Date</label>
                    <input type="date" wire:model="esewaDate"
                        class="lj-pr-input">
                </div>
            </div>

            {{-- Live Double-Entry Preview --}}
            <div class="lj-pr-ledger-box">
                <div>
                    <span>Dr. 1120 Operating Bank Account:</span>
                    <strong>{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float) ($esewaNetPayout ?: 0), 'Rs. ', 2) }}</strong>
                </div>
                <div>
                    <span>Dr. 6190 Payment Processing Commission:</span>
                    <strong>{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float) ($esewaFee ?: 0), 'Rs. ', 2) }}</strong>
                </div>
                <div style="border-top: 1px dashed #d1d5db; padding-top: 0.25rem; color: #111827;">
                    <span>Cr. 1130 eSewa Merchant Clearing Account:</span>
                    <strong>{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float) (($esewaNetPayout ?: 0) + ($esewaFee ?: 0)), 'Rs. ', 2) }}</strong>
                </div>
            </div>

            <button type="button" wire:click="reconcileEsewa" class="lj-pr-btn">
                <x-filament::icon icon="heroicon-o-check" class="w-5 h-5" />
                Reconcile eSewa Remittance
            </button>
        </div>
        @endif

        {{-- TAB 2: CONNECTIPS SETTLEMENT FORM --}}
        @if($activeTab === 'connectips')
        <div class="lj-pr-form-card">
            <div class="lj-pr-form-header">
                <h3 class="lj-pr-form-title">ConnectIPS / NCHL Gateway Reconciliation</h3>
                <p class="lj-pr-form-sub">
                    Reconcile daily automated NCHL ConnectIPS direct bank clearing batches into your primary account.
                </p>
            </div>

            <div class="lj-pr-grid-2">
                <div class="lj-pr-field">
                    <label class="lj-pr-label">Net Settled to Bank (Rs.) *</label>
                    <input type="number" step="0.01" wire:model="connectIpsNetPayout" placeholder="0.00"
                        class="lj-pr-input" style="font-weight: 800; font-size: 1.125rem;">
                </div>
                <div class="lj-pr-field">
                    <label class="lj-pr-label">Gateway Transaction Fee (Rs.)</label>
                    <input type="number" step="0.01" wire:model="connectIpsFee" placeholder="0.00"
                        class="lj-pr-input">
                </div>
                <div class="lj-pr-field">
                    <label class="lj-pr-label">NCHL Settlement Reference</label>
                    <input type="text" wire:model="connectIpsRef" placeholder="e.g. NCHL-SETTLE-4091"
                        class="lj-pr-input">
                </div>
                <div class="lj-pr-field">
                    <label class="lj-pr-label">Settlement Date</label>
                    <input type="date" wire:model="connectIpsDate"
                        class="lj-pr-input">
                </div>
            </div>

            {{-- Live Double-Entry Preview --}}
            <div class="lj-pr-ledger-box">
                <div>
                    <span>Dr. 1120 Operating Bank Account:</span>
                    <strong>{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float) ($connectIpsNetPayout ?: 0), 'Rs. ', 2) }}</strong>
                </div>
                <div>
                    <span>Dr. 6190 Payment Processing Commission:</span>
                    <strong>{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float) ($connectIpsFee ?: 0), 'Rs. ', 2) }}</strong>
                </div>
                <div style="border-top: 1px dashed #d1d5db; padding-top: 0.25rem; color: #111827;">
                    <span>Cr. 1140 ConnectIPS Clearing Account:</span>
                    <strong>{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float) (($connectIpsNetPayout ?: 0) + ($connectIpsFee ?: 0)), 'Rs. ', 2) }}</strong>
                </div>
            </div>

            <button type="button" wire:click="reconcileConnectIps" class="lj-pr-btn blue">
                <x-filament::icon icon="heroicon-o-check" class="w-5 h-5" />
                Reconcile ConnectIPS Payout
            </button>
        </div>
        @endif

        {{-- TAB 3: COD COURIER REMITTANCE FORM --}}
        @if($activeTab === 'cod')
        <div class="lj-pr-form-card">
            <div class="lj-pr-form-header">
                <h3 class="lj-pr-form-title">Courier COD Remittance Reconciliation</h3>
                <p class="lj-pr-form-sub">
                    Reconcile courier cash remittances (Nepal Can Move, Pathao, Delhivery) minus delivery fees into your operating bank.
                </p>
            </div>

            <div class="lj-pr-grid-2">
                <div class="lj-pr-field">
                    <label class="lj-pr-label">Net Bank Credit Received (Rs.) *</label>
                    <input type="number" step="0.01" wire:model="codRemitted" placeholder="0.00"
                        class="lj-pr-input" style="font-weight: 800; font-size: 1.125rem;">
                </div>
                <div class="lj-pr-field">
                    <label class="lj-pr-label">Courier Service Fee Deducted (Rs.)</label>
                    <input type="number" step="0.01" wire:model="codCourierFee" placeholder="0.00"
                        class="lj-pr-input">
                </div>
                <div class="lj-pr-field">
                    <label class="lj-pr-label">Courier Partner Name</label>
                    <select wire:model="codCourierName" class="lj-pr-input">
                        <option value="Nepal Can Move (NCM)">Nepal Can Move (NCM)</option>
                        <option value="Pathao Logistics">Pathao Logistics</option>
                        <option value="Sundar Courier">Sundar Courier</option>
                        <option value="Direct Express">Direct Express</option>
                    </select>
                </div>
                <div class="lj-pr-field">
                    <label class="lj-pr-label">Bank Statement / Courier Batch Ref</label>
                    <input type="text" wire:model="codBatchRef" placeholder="e.g. NCM-REMIT-WEEK-36"
                        class="lj-pr-input">
                </div>
            </div>

            {{-- Live Double-Entry Preview --}}
            <div class="lj-pr-ledger-box">
                <div>
                    <span>Dr. 1120 Operating Bank Account:</span>
                    <strong>{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float) ($codRemitted ?: 0), 'Rs. ', 2) }}</strong>
                </div>
                <div>
                    <span>Dr. 6150 Courier Delivery Expense:</span>
                    <strong>{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float) ($codCourierFee ?: 0), 'Rs. ', 2) }}</strong>
                </div>
                <div style="border-top: 1px dashed #d1d5db; padding-top: 0.25rem; color: #111827;">
                    <span>Cr. 1150 COD Receivables Clearing:</span>
                    <strong>{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float) (($codRemitted ?: 0) + ($codCourierFee ?: 0)), 'Rs. ', 2) }}</strong>
                </div>
            </div>

            <button type="button" wire:click="reconcileCod" class="lj-pr-btn">
                <x-filament::icon icon="heroicon-o-check" class="w-5 h-5" />
                Reconcile COD Remittance
            </button>
        </div>
        @endif

        {{-- TAB 4: POS CASH DEPOSIT --}}
        @if($activeTab === 'cash_deposit')
        <div class="lj-pr-form-card">
            <div class="lj-pr-form-header">
                <h3 class="lj-pr-form-title">POS Cash Drawer Deposit to Bank</h3>
                <p class="lj-pr-form-sub">
                    Record physical showroom cash drawer deposits into your commercial bank account.
                </p>
            </div>

            <div class="lj-pr-grid-2">
                <div class="lj-pr-field">
                    <label class="lj-pr-label">Cash Deposit Amount (Rs.) *</label>
                    <input type="number" step="0.01" wire:model="cashDepositAmount" placeholder="0.00"
                        class="lj-pr-input" style="font-weight: 800; font-size: 1.125rem;">
                </div>
                <div class="lj-pr-field">
                    <label class="lj-pr-label">Bank Deposit Voucher / Slip #</label>
                    <input type="text" wire:model="cashDepositSlipRef" placeholder="e.g. SLIP-NABIL-9842"
                        class="lj-pr-input">
                </div>
            </div>

            {{-- Live Double-Entry Preview --}}
            <div class="lj-pr-ledger-box">
                <div>
                    <span>Dr. 1120 Operating Bank Account:</span>
                    <strong>{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float) ($cashDepositAmount ?: 0), 'Rs. ', 2) }}</strong>
                </div>
                <div style="border-top: 1px dashed #d1d5db; padding-top: 0.25rem; color: #111827;">
                    <span>Cr. 1110 Cash on Hand (Showroom POS):</span>
                    <strong>{{ \App\Helpers\NepaliNumberHelper::formatCurrency((float) ($cashDepositAmount ?: 0), 'Rs. ', 2) }}</strong>
                </div>
            </div>

            <button type="button" wire:click="recordCashDeposit" class="lj-pr-btn">
                <x-filament::icon icon="heroicon-o-check" class="w-5 h-5" />
                Record Cash Deposit
            </button>
        </div>
        @endif

        {{-- TAB 5: AUDIT TRAIL / RECONCILED SETTLEMENTS LEDGER --}}
        @if($activeTab === 'audit')
        <div class="lj-pr-table-card">
            <div class="lj-pr-table-header">
                Immutable audit log of all reconciled settlements, clearing payouts, and bank cash deposits.
            </div>

            <div class="lj-pr-table-wrapper">
                <table class="lj-pr-table">
                    <thead>
                        <tr>
                            <th>Voucher #</th>
                            <th>Type & Date</th>
                            <th>Description</th>
                            <th>Debit Account</th>
                            <th>Credit Account</th>
                            <th style="text-align: right;">Amount (Rs.)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->recentSettlements as $voucher)
                        <tr>
                            <td style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace; font-weight: 700; color: #111827;">
                                {{ $voucher->entry_number }}
                            </td>
                            <td>
                                <span style="display: inline-block; font-size: 0.6875rem; font-weight: 800; text-transform: uppercase; background: #f3f4f6; color: #374151; padding: 0.15rem 0.5rem; border-radius: 0.375rem;">
                                    {{ str_replace('_', ' ', $voucher->entry_type) }}
                                </span>
                                <div style="font-size: 0.6875rem; color: #9ca3af; margin-top: 0.25rem;">
                                    {{ $voucher->voucher_date }}
                                </div>
                            </td>
                            <td style="font-weight: 600; color: #111827;">
                                {{ $voucher->description }}
                            </td>
                            <td style="font-family: ui-monospace, monospace; font-size: 0.75rem; color: #4b5563;">
                                {{ $voucher->lines->where('debit', '>', 0)->first()?->account?->name ?? 'Bank' }}
                            </td>
                            <td style="font-family: ui-monospace, monospace; font-size: 0.75rem; color: #4b5563;">
                                {{ $voucher->lines->where('credit', '>', 0)->first()?->account?->name ?? 'Clearing' }}
                            </td>
                            <td style="text-align: right; font-weight: 900; color: #111827;">
                                {{ \App\Helpers\NepaliNumberHelper::formatCurrency($voucher->total_debit, 'Rs. ', 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 3rem 1rem; color: #9ca3af; font-size: 0.8125rem;">
                                No settlement vouchers recorded yet.
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