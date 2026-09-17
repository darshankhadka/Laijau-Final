<x-filament-panels::page>
    @php
        $customer = $this->record;
        $service = app(\App\Services\Customer\CustomerService::class);
        $metrics = $service->getCustomerMetrics($customer);
        $history = $service->getCustomerOrderHistory($customer);
        $crm = $service->getCustomerCrmSummary($customer);
    @endphp

    {{-- SELF-CONTAINED EMBEDDED RETAIL STYLES FOR CUSTOMER DOSSIER --}}
    <style>
        .lj-cust-page {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            box-sizing: border-box;
        }
        .lj-cust-page * {
            box-sizing: border-box;
        }
        .lj-cust-page svg {
            width: 15px !important;
            height: 15px !important;
            min-width: 15px !important;
            max-width: 15px !important;
            flex-shrink: 0 !important;
            display: inline-block !important;
            vertical-align: middle !important;
        }
        .lj-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }
        .lj-card-p5 {
            padding: 1.25rem;
        }
        .lj-header-strip {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }
        .lj-avatar-lg {
            width: 48px;
            height: 48px;
            min-width: 48px;
            max-width: 48px;
            border-radius: 50%;
            background: #0f172a;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.125rem;
            flex-shrink: 0;
        }
        .lj-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 600;
            line-height: 1.2;
        }
        .lj-btn-wa {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.45rem 0.85rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.5rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
            background: #059669;
            border: 1px solid #059669;
            color: #ffffff;
            line-height: 1.2;
        }
        .lj-btn-wa:hover {
            background: #047857;
            color: #ffffff;
        }
        .lj-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        @media (max-width: 900px) {
            .lj-kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        .lj-kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }
        .lj-kpi-label {
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }
        .lj-kpi-value {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
            margin-top: 0.25rem;
            line-height: 1.2;
        }
        .lj-kpi-sub {
            font-size: 0.6875rem;
            color: #64748b;
            margin-top: 0.25rem;
        }
        .lj-cust-layout {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 1.25rem;
            align-items: start;
        }
        @media (max-width: 1024px) {
            .lj-cust-layout {
                grid-template-columns: 1fr;
            }
        }
        .lj-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            background: #f8fafc;
        }
        .lj-card-title {
            font-size: 0.875rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        .lj-table {
            width: 100%;
            text-align: left;
            font-size: 0.75rem;
            border-collapse: collapse;
        }
        .lj-table th {
            padding: 0.625rem 0.875rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
        }
        .lj-table td {
            padding: 0.75rem 0.875rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .lj-table tr:hover td {
            background: #f8fafc;
        }
        .lj-table tr:last-child td {
            border-bottom: none;
        }
    </style>

    <div class="lj-cust-page">
        {{-- Customer Profile Header Card --}}
        <div class="lj-header-strip">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="lj-avatar-lg">
                    {{ strtoupper(substr($customer->name ?: 'C', 0, 1)) }}
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 0.625rem;">
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
                            {{ $customer->name }}
                        </h2>
                        
                        {{-- Customer Segment Badge --}}
                        @php
                            $segmentStyles = [
                                'VIP' => 'background: #fef3c7; color: #92400e; border: 1px solid #fde68a; font-weight: 700;',
                                'Returning' => 'background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe;',
                                'New' => 'background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;',
                                'Inactive' => 'background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;',
                            ];
                            $segStyle = $segmentStyles[$metrics['segment']] ?? 'background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;';
                        @endphp
                        <span class="lj-badge" style="{{ $segStyle }}">
                            @if($metrics['segment'] === 'VIP') ★ @endif
                            {{ $metrics['segment'] }}
                        </span>
                    </div>

                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-top: 0.35rem; font-size: 0.75rem; color: #64748b;">
                        @if($customer->phone)
                            <span style="font-family: monospace; font-weight: 600; color: #334155;">
                                📞 {{ $customer->phone }}
                            </span>
                        @endif
                        @if($customer->email)
                            <span style="color: #475569;">
                                ✉️ {{ $customer->email }}
                            </span>
                        @endif
                        <span>•</span>
                        <span>Customer Since: <strong style="color: #1e293b;">{{ $customer->created_at ? $customer->created_at->format('M d, Y') : '—' }}</strong></span>
                    </div>
                </div>
            </div>

            {{-- Direct Contact & Action Chips --}}
            <div>
                @if($customer->phone)
                @php
                    $cleanP = preg_replace('/[^0-9]/', '', (string)$customer->phone);
                    if (strlen($cleanP) === 10) $cleanP = '977' . $cleanP;
                    $waMsg = "Namaste {$customer->name}! Laijau Customer Care here. How may we assist you today?";
                @endphp
                <a href="https://wa.me/{{ $cleanP }}?text={{ urlencode($waMsg) }}" target="_blank" class="lj-btn-wa">
                    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                    WhatsApp Contact
                </a>
                @endif
            </div>
        </div>

        {{-- 4 Core KPI Metric Cards --}}
        <div class="lj-kpi-grid">
            {{-- KPI 1: Total Orders --}}
            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Total Orders</div>
                <div class="lj-kpi-value">{{ $metrics['total_orders'] }}</div>
                <div class="lj-kpi-sub" style="display: flex; gap: 0.5rem;">
                    <span style="color: #2563eb; font-weight: 600;">{{ $metrics['online_orders_count'] }} Online</span>
                    <span>•</span>
                    <span style="color: #d97706; font-weight: 600;">{{ $metrics['pos_sales_count'] }} POS</span>
                </div>
            </div>

            {{-- KPI 2: Lifetime Spend --}}
            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Lifetime Spend</div>
                <div class="lj-kpi-value" style="color: #047857;">
                    Rs. {{ number_format((float)$metrics['lifetime_spend'], 0) }}
                </div>
                <div class="lj-kpi-sub">
                    Net Paid Revenue (NPR)
                </div>
            </div>

            {{-- KPI 3: Average Order Value --}}
            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Average Order (AOV)</div>
                <div class="lj-kpi-value">
                    Rs. {{ number_format((float)$metrics['average_order_value'], 0) }}
                </div>
                <div class="lj-kpi-sub">
                    Across all channels
                </div>
            </div>

            {{-- KPI 4: Last Purchase Date --}}
            <div class="lj-kpi-card">
                <div class="lj-kpi-label">Last Order</div>
                <div style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin-top: 0.35rem;">
                    @if($metrics['last_order_date'])
                        {{ $metrics['last_order_date']->format('M d, Y') }}
                    @else
                        <span style="color: #94a3b8;">No orders yet</span>
                    @endif
                </div>
                <div class="lj-kpi-sub">
                    @if($metrics['last_order_date'])
                        {{ $metrics['last_order_date']->diffForHumans() }}
                    @else
                        Registered patron
                    @endif
                </div>
            </div>
        </div>

        {{-- 2-Column Layout: Order History vs Addresses & Internal Notes --}}
        <div class="lj-cust-layout">
            
            {{-- LEFT: Unified Order History Table --}}
            <div class="lj-card">
                <div class="lj-card-header">
                    <div class="lj-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span>Combined Order & Sales History</span>
                    </div>
                    <span style="font-size: 0.6875rem; color: #64748b; font-weight: 500;">Storefront + POS</span>
                </div>

                <div style="overflow-x: auto;">
                    <table class="lj-table">
                        <thead>
                            <tr>
                                <th>Order / Sale #</th>
                                <th>Date</th>
                                <th>Channel</th>
                                <th>Items</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Fulfillment & Courier</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($history as $rec)
                                <tr>
                                    <td>
                                        @if($rec['view_url'])
                                            <a href="{{ $rec['view_url'] }}" style="font-family: monospace; font-weight: 700; color: #047857; text-decoration: none;">
                                                {{ $rec['number'] }}
                                            </a>
                                        @else
                                            <span style="font-family: monospace; font-weight: 700; color: #1e293b;">
                                                {{ $rec['number'] }}
                                            </span>
                                        @endif
                                    </td>
                                    <td style="color: #475569; white-space: nowrap;">
                                        @php
                                            $d = $rec['date'] instanceof \Carbon\Carbon ? $rec['date'] : \Carbon\Carbon::parse($rec['date']);
                                        @endphp
                                        {{ $d->timezone('Asia/Kathmandu')->format('M d, Y') }}
                                    </td>
                                    <td>
                                        <span class="lj-badge" style="{{ $rec['type'] === 'pos' ? 'background: #fef3c7; color: #92400e;' : 'background: #eff6ff; color: #1e40af;' }}">
                                            {{ $rec['channel'] }}
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: #0f172a;">{{ $rec['items_count'] }} items</div>
                                        @if(!empty($rec['items_summary']))
                                            <div style="font-size: 0.6875rem; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 160px;">{{ $rec['items_summary'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span style="font-weight: 500; color: #334155;">{{ $rec['payment_method'] }}</span>
                                        <span class="lj-badge" style="margin-left: 0.25rem; {{ $rec['payment_status'] === 'paid' ? 'background: #ecfdf5; color: #065f46;' : 'background: #fef3c7; color: #92400e;' }}">
                                            {{ ucfirst($rec['payment_status']) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-weight: 600; color: #1e293b;">
                                            {{ ucfirst(str_replace('_', ' ', $rec['status'])) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if(!empty($rec['tracking_number']))
                                            <div style="font-family: monospace; font-weight: 700; color: #2563eb; font-size: 0.75rem;">
                                                {{ $rec['carrier'] ?: 'Courier' }}: {{ $rec['tracking_number'] }}
                                            </div>
                                            <div style="font-size: 0.6875rem; color: #059669; font-weight: 600;">
                                                ● {{ ucfirst(str_replace('_', ' ', $rec['courier_status'] ?: 'dispatched')) }}
                                            </div>
                                        @elseif($rec['type'] === 'pos')
                                            <span style="color: #64748b; font-size: 0.6875rem; font-weight: 500;">
                                                🏬 Showroom Direct Handover
                                            </span>
                                        @else
                                            <span style="color: #64748b; font-size: 0.6875rem;">
                                                Fulfillment Hub ({{ ucfirst(str_replace('_', ' ', $rec['status'])) }})
                                            </span>
                                        @endif
                                    </td>
                                    <td style="text-align: right; font-weight: 700; color: #0f172a; white-space: nowrap;">
                                        Rs. {{ number_format((float)$rec['total_amount'], 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" style="padding: 2rem; text-align: center; color: #94a3b8;">
                                        No orders or showroom sales found for this customer.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- RIGHT: Addresses & Internal Staff Notes --}}
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                
                {{-- Delivery Destination & Address Card --}}
                <div class="lj-card lj-card-p5">
                    <div class="lj-card-title" style="margin-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>Primary Address</span>
                    </div>

                    <div style="font-size: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem;">
                        <div style="color: #0f172a; font-weight: 500; background: #f8fafc; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; line-height: 1.4;">
                            {{ $customer->primary_address }}
                        </div>
                        @if($customer->district)
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: #64748b;">District:</span>
                                <strong style="color: #0f172a;">{{ $customer->district }}</strong>
                            </div>
                        @endif
                        @if($customer->province)
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: #64748b;">Province:</span>
                                <span style="color: #334155;">{{ $customer->province }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Internal Staff Notes Card (Inline Save) --}}
                <div class="lj-card lj-card-p5">
                    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; margin-bottom: 0.75rem;">
                        <div class="lj-card-title">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            <span>Internal Staff Notes</span>
                        </div>
                        <span class="lj-badge" style="background: #fef3c7; color: #92400e;">Staff Only</span>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.75rem;">
                        <textarea wire:model="internalNotes" rows="4"
                                  style="width: 100%; font-size: 0.75rem; color: #1e293b; border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.625rem; outline: none; font-family: inherit; resize: vertical;"
                                  placeholder="Enter private notes (e.g. Call before delivery, WhatsApp preferred, sizing preference)..."></textarea>

                        <button type="button" wire:click="saveNotes"
                                style="width: 100%; padding: 0.5rem 0.75rem; font-size: 0.75rem; font-weight: 600; border-radius: 0.5rem; background: #0f172a; color: #ffffff; border: none; cursor: pointer;">
                            Save Internal Notes
                        </button>
                    </div>
                </div>

            </div>
        </div>

        {{-- CUSTOMER 360: CRM INQUIRIES, LEADS, OUTSTANDING FOLLOW-UPS & TIMELINE --}}
        <div class="lj-card">
            <div class="lj-card-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                <div class="lj-card-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span>Customer 360 Dossier: CRM Clienteling & Inquiries</span>
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <span class="lj-badge" style="background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe;">
                        {{ $crm['active_leads_count'] }} Active Leads
                    </span>
                    <span class="lj-badge" style="{{ $crm['outstanding_follow_ups_count'] > 0 ? 'background: #fef2f2; color: #991b1b; border: 1px solid #fecaca;' : 'background: #f1f5f9; color: #475569;' }}">
                        {{ $crm['outstanding_follow_ups_count'] }} Follow-ups Due
                    </span>
                    <span class="lj-badge" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;">
                        {{ $crm['inquiries_count'] }} Inquiries
                    </span>
                </div>
            </div>

            {{-- 1. Outstanding Follow-ups Alert Strip (if any) --}}
            @if($crm['follow_ups']->isNotEmpty())
                <div style="background: #fffbeb; border-bottom: 1px solid #fef3c7; padding: 0.875rem 1.25rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #92400e; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.375rem;">
                        <span>⚠️ Active Follow-ups Required for {{ $customer->name }}:</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        @foreach($crm['follow_ups'] as $fu)
                            @php
                                $isOverdue = $fu->isOverdue();
                            @endphp
                            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; background: #ffffff; border: 1px solid {{ $isOverdue ? '#fca5a5' : '#fed7aa' }}; border-radius: 0.5rem; padding: 0.625rem 0.875rem; font-size: 0.75rem;">
                                <div>
                                    <span style="font-weight: 700; color: #0f172a;">{{ $fu->title }}</span>
                                    <span class="lj-badge" style="margin-left: 0.375rem; {{ $isOverdue ? 'background: #fee2e2; color: #991b1b;' : 'background: #fef3c7; color: #92400e;' }}">
                                        {{ $isOverdue ? 'OVERDUE' : 'DUE' }}: {{ $fu->follow_up_date->format('M d, h:i A') }}
                                    </span>
                                    @if($fu->follow_up_notes)
                                        <div style="color: #64748b; font-size: 0.6875rem; margin-top: 0.25rem;">
                                            Directive: {{ $fu->follow_up_notes }}
                                        </div>
                                    @endif
                                </div>
                                <div style="display: flex; gap: 0.375rem; margin-top: 0.25rem;">
                                    @if($fu->phone)
                                        <a href="{{ $fu->whats_app_url }}" target="_blank" class="lj-btn-wa" style="padding: 0.25rem 0.6rem; font-size: 0.6875rem;">
                                            Open WhatsApp
                                        </a>
                                    @endif
                                    <a href="/intadmin/crm-leads/{{ $fu->id }}/edit" target="_blank" style="padding: 0.25rem 0.6rem; font-size: 0.6875rem; background: #f1f5f9; color: #334155; border-radius: 0.375rem; text-decoration: none; font-weight: 600;">
                                        View Lead
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 1rem; padding: 1.25rem;">
                {{-- LEFT SUBPANEL: Inquiries & Leads --}}
                <div>
                    <h3 style="font-size: 0.8125rem; font-weight: 700; color: #0f172a; margin: 0 0 0.75rem 0; display: flex; align-items: center; justify-content: space-between;">
                        <span>👗 Pipeline Inquiries & Leads ({{ $crm['leads']->count() }})</span>
                        <a href="/intadmin/crm-kanban" target="_blank" style="font-size: 0.6875rem; color: #2563eb; text-decoration: none; font-weight: 600;">Open Kanban →</a>
                    </h3>

                    <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 0.5rem;">
                        <table class="lj-table">
                            <thead>
                                <tr>
                                    <th>Lead / Garment</th>
                                    <th>Stage</th>
                                    <th>Value</th>
                                    <th>Next Follow-up</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($crm['leads'] as $lead)
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; color: #0f172a;">{{ $lead->title }}</div>
                                            @if($lead->product)
                                                <div style="font-size: 0.6875rem; color: #64748b;">👗 {{ $lead->product->name }}</div>
                                            @endif
                                            @if($lead->order)
                                                <div style="font-size: 0.6875rem; color: #059669; font-weight: 600;">
                                                    📦 Converted: #{{ $lead->order->order_number }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="lj-badge" style="
                                                {{ $lead->stage === 'won' ? 'background: #ecfdf5; color: #065f46;' : '' }}
                                                {{ $lead->stage === 'lost' ? 'background: #fef2f2; color: #991b1b;' : '' }}
                                                {{ !in_array($lead->stage, ['won', 'lost']) ? 'background: #eff6ff; color: #1e40af;' : '' }}
                                            ">
                                                {{ ucfirst($lead->stage) }}
                                            </span>
                                        </td>
                                        <td style="font-weight: 600; color: #0f172a; white-space: nowrap;">
                                            Rs. {{ number_format((float)$lead->estimated_value, 0) }}
                                        </td>
                                        <td style="white-space: nowrap; color: #475569;">
                                            @if($lead->follow_up_date)
                                                <span style="{{ $lead->isOverdue() ? 'color: #dc2626; font-weight: 700;' : '' }}">
                                                    {{ $lead->follow_up_date->format('M d, H:i') }}
                                                </span>
                                            @else
                                                <span style="color: #94a3b8;">None</span>
                                            @endif
                                        </td>
                                        <td style="white-space: nowrap;">
                                            @if($lead->phone)
                                                <a href="{{ $lead->whats_app_url }}" target="_blank" title="Open Pre-filled WhatsApp" style="color: #059669; font-size: 0.8125rem; text-decoration: none; font-weight: 700; margin-right: 0.35rem;">
                                                    💬
                                                </a>
                                            @endif
                                            <a href="/intadmin/crm-leads/{{ $lead->id }}/edit" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 0.6875rem;">
                                                Edit
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" style="padding: 1.5rem; text-align: center; color: #94a3b8;">
                                            No CRM leads or bespoke inquiries recorded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- RIGHT SUBPANEL: Contact Form & Support Inquiries --}}
                <div>
                    <h3 style="font-size: 0.8125rem; font-weight: 700; color: #0f172a; margin: 0 0 0.75rem 0; display: flex; align-items: center; justify-content: space-between;">
                        <span>💬 Customer Support & Web Inquiries ({{ $crm['inquiries']->count() }})</span>
                        <a href="/intadmin/contact-messages" target="_blank" style="font-size: 0.6875rem; color: #2563eb; text-decoration: none; font-weight: 600;">View All →</a>
                    </h3>

                    <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 0.5rem;">
                        <table class="lj-table">
                            <thead>
                                <tr>
                                    <th>Subject & Classification</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($crm['inquiries'] as $inq)
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: #0f172a;">{{ $inq->subject }}</div>
                                            <div style="font-size: 0.6875rem; color: #64748b; margin-top: 0.15rem;">
                                                <span class="lj-badge" style="background: #f1f5f9; color: #475569; padding: 0.1rem 0.35rem; font-size: 0.625rem;">
                                                    {{ ucfirst($inq->inquiry_type ?? 'general') }}
                                                </span>
                                                {{ Str::limit($inq->message, 45) }}
                                            </div>
                                        </td>
                                        <td style="color: #64748b; white-space: nowrap; font-size: 0.6875rem;">
                                            {{ $inq->created_at->format('M d, Y') }}
                                        </td>
                                        <td>
                                            <span class="lj-badge" style="{{ $inq->is_resolved ? 'background: #ecfdf5; color: #065f46;' : 'background: #fef3c7; color: #92400e;' }}">
                                                {{ $inq->is_resolved ? 'Resolved' : 'Pending' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" style="padding: 1.5rem; text-align: center; color: #94a3b8;">
                                            No storefront inquiries logged for this patron.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
