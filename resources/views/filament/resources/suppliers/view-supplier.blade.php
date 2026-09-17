<x-filament-panels::page>
    <style>
        .lj-sup-grid {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 1.25rem;
            margin-top: 1rem;
        }

        @media (max-width: 1024px) {
            .lj-sup-grid {
                grid-template-columns: 1fr;
            }
        }

        .lj-sup-card {
            background: #ffffff;
            border: 1px solid #e4e4e7;
            border-radius: 0.5rem;
            padding: 1.25rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        .lj-sup-header {
            font-size: 0.8125rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #71717a;
            margin-bottom: 0.75rem;
            border-bottom: 1px solid #f4f4f5;
            padding-bottom: 0.5rem;
        }

        .lj-sup-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.4rem 0;
            font-size: 0.8125rem;
            border-bottom: 1px dashed #f4f4f5;
        }

        .lj-sup-row:last-child {
            border-bottom: none;
        }

        .lj-sup-label {
            color: #71717a;
            font-weight: 500;
        }

        .lj-sup-val {
            color: #09090b;
            font-weight: 600;
            text-align: right;
        }

        .lj-sup-kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        @media (max-width: 768px) {
            .lj-sup-kpi-grid {
                grid-template-columns: 1fr;
            }
        }

        .lj-sup-kpi {
            background: #fafafa;
            border: 1px solid #e4e4e7;
            border-radius: 0.375rem;
            padding: 0.875rem 1rem;
        }

        .lj-sup-kpi-label {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #71717a;
        }

        .lj-sup-kpi-value {
            font-size: 1.375rem;
            font-weight: 700;
            color: #09090b;
            margin-top: 0.25rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        .lj-sup-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
            margin-top: 0.5rem;
        }

        .lj-sup-table th {
            background: #fafafa;
            color: #71717a;
            font-weight: 600;
            text-align: left;
            padding: 0.625rem 0.75rem;
            border-bottom: 1px solid #e4e4e7;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .lj-sup-table td {
            padding: 0.625rem 0.75rem;
            border-bottom: 1px solid #f4f4f5;
            color: #27272a;
        }

        .lj-sup-table tr:hover td {
            background: #fafafa;
        }

        .lj-sup-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 600;
        }

        .lj-badge-success {
            background: #dcfce7;
            color: #15803d;
        }

        .lj-badge-warning {
            background: #fef3c7;
            color: #b45309;
        }

        .lj-badge-info {
            background: #e0f2fe;
            color: #0369a1;
        }

        .lj-badge-gray {
            background: #f4f4f5;
            color: #52525b;
        }

        .lj-badge-danger {
            background: #fee2e2;
            color: #b91c1c;
        }
    </style>

    @php
    $supplier = $this->record;
    $pos = $supplier->purchaseOrders()->with(['warehouse', 'items'])->orderBy('order_date', 'desc')->get();
    $totalPurchases = $pos->whereIn('status', ['received', 'partially_received', 'closed'])->sum('total_amount_npr');
    $activePosCount = $pos->whereIn('status', ['ordered', 'submitted', 'approved', 'in_transit'])->count();
    @endphp

    <div class="lj-sup-grid">
        <!-- Sidebar: Supplier Info -->
        <div>
            <div class="lj-sup-card">
                <div class="lj-sup-header">Supplier Dossier</div>

                <div style="margin-bottom: 1rem;">
                    <div style="font-size: 1.125rem; font-weight: 700; color: #09090b;">{{ $supplier->name }}</div>
                    <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: 0.25rem;">
                        <span style="font-family: monospace; font-weight: 600; color: #52525b; font-size: 0.75rem; background: #f4f4f5; padding: 2px 6px; border-radius: 4px;">{{ $supplier->code }}</span>
                        @if($supplier->is_active)
                        <span class="lj-sup-badge lj-badge-success">Active</span>
                        @else
                        <span class="lj-sup-badge lj-badge-gray">Inactive</span>
                        @endif
                    </div>
                </div>

                <div class="lj-sup-row">
                    <span class="lj-sup-label">Contact Person</span>
                    <span class="lj-sup-val">{{ $supplier->contact_person ?: '—' }}</span>
                </div>
                <div class="lj-sup-row">
                    <span class="lj-sup-label">Phone</span>
                    <span class="lj-sup-val">{{ $supplier->phone ?: '—' }}</span>
                </div>
                <div class="lj-sup-row">
                    <span class="lj-sup-label">Email</span>
                    <span class="lj-sup-val">{{ $supplier->email ?: '—' }}</span>
                </div>
                <div class="lj-sup-row">
                    <span class="lj-sup-label">PAN / VAT #</span>
                    <span class="lj-sup-val" style="font-family: monospace;">{{ $supplier->tax_vat_number ?: '—' }}</span>
                </div>
                <div class="lj-sup-row">
                    <span class="lj-sup-label">Location</span>
                    <span class="lj-sup-val">{{ $supplier->city ?: '—' }}, {{ $supplier->country ?: 'NP' }}</span>
                </div>
                <div class="lj-sup-row">
                    <span class="lj-sup-label">Address</span>
                    <span class="lj-sup-val">{{ $supplier->address ?: '—' }}</span>
                </div>
                <div class="lj-sup-row">
                    <span class="lj-sup-label">Payment Terms</span>
                    <span class="lj-sup-val">{{ $supplier->payment_terms ?: 'Net 30' }}</span>
                </div>
                <div class="lj-sup-row">
                    <span class="lj-sup-label">Billing Currency</span>
                    <span class="lj-sup-val">{{ $supplier->currency ?: 'NPR' }}</span>
                </div>
                <div class="lj-sup-row">
                    <span class="lj-sup-label">Lead Time</span>
                    <span class="lj-sup-val">{{ $supplier->lead_time_days }} days</span>
                </div>

                @if($supplier->notes)
                <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #f4f4f5;">
                    <div style="font-size: 0.75rem; font-weight: 600; color: #71717a; text-transform: uppercase;">Notes / Specialization</div>
                    <div style="font-size: 0.8125rem; color: #3f3f46; margin-top: 0.25rem; line-height: 1.4;">{{ $supplier->notes }}</div>
                </div>
                @endif
            </div>
        </div>

        <!-- Main Workspace: KPIs & PO History -->
        <div>
            <!-- KPIs -->
            <div class="lj-sup-kpi-grid">
                <div class="lj-sup-kpi">
                    <div class="lj-sup-kpi-label">Completed Purchases</div>
                    <div class="lj-sup-kpi-value">Rs. {{ number_format($totalPurchases, 2) }}</div>
                </div>
                <div class="lj-sup-kpi">
                    <div class="lj-sup-kpi-label">Active Inbound POs</div>
                    <div class="lj-sup-kpi-value" style="color: {{ $activePosCount > 0 ? '#0284c7' : '#09090b' }};">{{ $activePosCount }}</div>
                </div>
                <div class="lj-sup-kpi">
                    <div class="lj-sup-kpi-label">Total Purchase Orders</div>
                    <div class="lj-sup-kpi-value">{{ $pos->count() }}</div>
                </div>
            </div>

            <!-- PO History Table -->
            <div class="lj-sup-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <div class="lj-sup-header" style="margin-bottom: 0; border-bottom: none; padding-bottom: 0;">Purchase Order History</div>
                    <a href="{{ route('filament.admin.resources.purchase-orders.create') }}?supplier_id={{ $supplier->id }}" style="font-size: 0.75rem; font-weight: 600; color: #0A2E23; text-decoration: underline;">+ Create PO</a>
                </div>

                @if($pos->isEmpty())
                <div style="text-align: center; padding: 2.5rem 1rem; color: #71717a;">
                    <svg style="width: 32px; height: 32px; margin: 0 auto 0.5rem; color: #a1a1aa;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <div style="font-size: 0.875rem; font-weight: 600; color: #3f3f46;">No Purchase Orders Generated</div>
                    <div style="font-size: 0.75rem; color: #71717a; margin-top: 0.25rem;">Initiate procurement orders to record inbound artisan shipments and supplier invoices.</div>
                </div>
                @else
                <div style="overflow-x: auto;">
                    <table class="lj-sup-table">
                        <thead>
                            <tr>
                                <th>PO #</th>
                                <th>Order Date</th>
                                <th>Warehouse</th>
                                <th style="text-align: right;">Lines</th>
                                <th style="text-align: right;">Landed Value</th>
                                <th>Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pos as $po)
                            <tr>
                                <td style="font-family: monospace; font-weight: 700;">{{ $po->po_number }}</td>
                                <td>{{ $po->order_date ? $po->order_date->format('M d, Y') : '—' }}</td>
                                <td>
                                    <span style="font-family: monospace; font-size: 0.75rem; font-weight: 600; background: #f4f4f5; padding: 2px 6px; border-radius: 4px;">{{ $po->warehouse?->code ?? '—' }}</span>
                                </td>
                                <td style="text-align: right;">{{ $po->items->count() }}</td>
                                <td style="text-align: right; font-weight: 600;">Rs. {{ number_format($po->total_amount_npr, 2) }}</td>
                                <td>
                                    @php
                                    $badgeClass = match($po->status) {
                                    'received' => 'lj-badge-success',
                                    'partially_received', 'in_transit', 'ordered' => 'lj-badge-warning',
                                    'approved' => 'lj-badge-info',
                                    'cancelled', 'rejected' => 'lj-badge-danger',
                                    default => 'lj-badge-gray',
                                    };
                                    @endphp
                                    <span class="lj-sup-badge {{ $badgeClass }}">{{ ucfirst(str_replace('_', ' ', $po->status)) }}</span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="{{ route('filament.admin.resources.purchase-orders.edit', $po) }}" style="font-weight: 600; color: #0A2E23; text-decoration: underline; font-size: 0.75rem;">Manage</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>