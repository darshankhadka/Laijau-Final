<x-filament-panels::page>
    @php
    $order = $this->record;
    $order->loadMissing(['items.product', 'items.variant', 'user', 'paymentAuditLogs', 'logisticsEvents']);
    $items = $order->items;
    $customer = $order->resolveCustomer();
    $timeline = $order->getTimelineEvents();
    @endphp

    {{-- SELF-CONTAINED EMBEDDED RETAIL STYLES FOR HIGH-DENSITY WORKSPACE --}}
    <style>
        .lj-order-page {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            box-sizing: border-box;
        }

        .lj-order-page * {
            box-sizing: border-box;
        }

        .lj-order-page svg {
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

        .lj-header-title-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.625rem;
        }

        .lj-header-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin: 0;
        }

        .lj-header-sub {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.625rem;
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.35rem;
        }

        .lj-header-sub strong {
            color: #1e293b;
        }

        .lj-actions-strip {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }

        .lj-btn {
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
            line-height: 1.2;
        }

        .lj-btn-default {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #334155;
        }

        .lj-btn-default:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .lj-btn-wa {
            background: #059669;
            border: 1px solid #059669;
            color: #ffffff;
        }

        .lj-btn-wa:hover {
            background: #047857;
            color: #ffffff;
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

        .lj-grid-workspace {
            display: grid;
            grid-template-columns: 1.45fr 1fr;
            gap: 1.25rem;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .lj-grid-workspace {
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

        .lj-item-row {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s ease;
        }

        .lj-item-row:last-child {
            border-bottom: none;
        }

        .lj-item-row:hover {
            background: #f8fafc;
        }

        .lj-item-thumb {
            width: 52px;
            height: 52px;
            min-width: 52px;
            max-width: 52px;
            border-radius: 0.5rem;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .lj-item-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .lj-avatar {
            width: 40px;
            height: 40px;
            min-width: 40px;
            max-width: 40px;
            border-radius: 50%;
            background: #0f172a;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .lj-data-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.35rem 0;
            font-size: 0.75rem;
            border-bottom: 1px solid #f8fafc;
        }

        .lj-data-row:last-child {
            border-bottom: none;
        }

        .lj-timeline {
            position: relative;
            padding-left: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .lj-timeline::before {
            content: '';
            position: absolute;
            left: 0.375rem;
            top: 0.5rem;
            bottom: 0.5rem;
            width: 2px;
            background: #e2e8f0;
        }

        .lj-tl-item {
            position: relative;
        }

        .lj-tl-dot {
            position: absolute;
            left: -1.5rem;
            top: 0.25rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #ffffff;
            box-shadow: 0 0 0 1px #cbd5e1;
        }
    </style>

    <div class="lj-order-page">
        {{-- High-Density Operations Header Strip --}}
        <div class="lj-header-strip">
            <div>
                <div class="lj-header-title-row">
                    <h2 class="lj-header-title">
                        Order #{{ $order->order_number }}
                    </h2>

                    {{-- Channel Badge --}}
                    <span class="lj-badge" style="{{ $order->channel === 'pos' ? 'background: #fef3c7; color: #92400e; border: 1px solid #fde68a;' : ($order->channel === 'manual' ? 'background: #f3e8ff; color: #6b21a8; border: 1px solid #e9d5ff;' : 'background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe;') }}">
                        {{ $order->channel === 'pos' ? 'Showroom POS' : ($order->channel === 'manual' ? 'Phone Order' : 'Online Store') }}
                    </span>

                    {{-- Fulfillment Status Badge --}}
                    @php
                    $statusStyles = [
                    'pending' => 'background: #fef3c7; color: #92400e; border: 1px solid #fde68a;',
                    'processing' => 'background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe;',
                    'packing' => 'background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe;',
                    'ready_for_delivery' => 'background: #ecfeff; color: #155e75; border: 1px solid #a5f3fc;',
                    'handed_to_courier' => 'background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd;',
                    'in_transit' => 'background: #ccfbf1; color: #115e59; border: 1px solid #99f6e4;',
                    'delivered' => 'background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;',
                    'cancelled' => 'background: #ffe4e6; color: #9f1239; border: 1px solid #fecdd3;',
                    ];
                    $stStyle = $statusStyles[$order->status] ?? 'background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0;';
                    @endphp
                    <span class="lj-badge" style="{{ $stStyle }}">
                        {{ \App\Models\Order::getStatuses()[$order->status] ?? ucfirst(str_replace('_', ' ', $order->status)) }}
                    </span>

                    {{-- Payment Status Badge --}}
                    @php
                    $paymentStyles = [
                    'paid' => 'background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;',
                    'payment_verification_pending' => 'background: #fef3c7; color: #92400e; border: 1px solid #fde68a;',
                    'unpaid' => 'background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;',
                    'failed' => 'background: #ffe4e6; color: #9f1239; border: 1px solid #fecdd3;',
                    'refunded' => 'background: #ffedd5; color: #9a3412; border: 1px solid #fed7aa;',
                    ];
                    $payStyle = $paymentStyles[$order->payment_status] ?? 'background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;';
                    @endphp
                    <span class="lj-badge" style="{{ $payStyle }}">
                        Payment: {{ ucfirst(str_replace('_', ' ', $order->payment_status ?? 'unpaid')) }}
                    </span>
                </div>

                <div class="lj-header-sub">
                    <span>Placed: <strong>{{ $order->created_at->timezone('Asia/Kathmandu')->format('M d, Y · h:i A') }}</strong></span>
                    <span>•</span>
                    <span>Destination: <strong>{{ $order->district ?: 'Kathmandu' }}</strong> ({{ $order->is_inside_valley ? 'Inside Valley' : 'Outside Valley' }})</span>
                    <span>•</span>
                    <span>Total Items: <strong>{{ $items->sum('quantity') ?: $items->count() }}</strong></span>
                </div>
            </div>

            {{-- Fast Retail Actions Bar --}}
            <div class="lj-actions-strip">
                <a href="{{ route('admin.orders.invoice', $order) }}" target="_blank" class="lj-btn lj-btn-default">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Tax Invoice
                </a>

                <a href="{{ route('admin.orders.packing_slip', $order) }}" target="_blank" class="lj-btn lj-btn-default">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    Packing Slip
                </a>

                @if(!empty($order->phone))
                @php
                $cleanP = preg_replace('/[^0-9]/', '', (string)$order->phone);
                if (strlen($cleanP) === 10) $cleanP = '977' . $cleanP;
                $waMsg = "Namaste {$order->first_name}! Laijau Support here regarding Order #{$order->order_number}.";
                @endphp
                <a href="https://wa.me/{{ $cleanP }}?text={{ urlencode($waMsg) }}" target="_blank" class="lj-btn lj-btn-wa">
                    <svg fill="currentColor" viewBox="0 0 24 24">
                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z" />
                    </svg>
                    WhatsApp
                </a>
                @endif
            </div>
        </div>

        {{-- 2-Column Retail Workspace Layout --}}
        <div class="lj-grid-workspace">

            {{-- LEFT COLUMN: Order Items & Chronological Timeline --}}
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">

                {{-- Order Items Table Card --}}
                <div class="lj-card">
                    <div class="lj-card-header">
                        <div class="lj-card-title">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <span>Order Items</span>
                            <span class="lj-badge" style="background: #e2e8f0; color: #334155; margin-left: 0.5rem;">
                                {{ $items->count() }} line {{ Str::plural('item', $items->count()) }}
                            </span>
                        </div>
                        <span style="font-size: 0.6875rem; color: #64748b; font-weight: 500;">Verified SKU & Details</span>
                    </div>

                    <div>
                        @forelse($items as $item)
                        <div class="lj-item-row">
                            {{-- Product Thumbnail --}}
                            <div class="lj-item-thumb">
                                @php
                                $imgUrl = null;
                                if ($item->variant && !empty($item->variant->image_url)) {
                                $imgUrl = \App\Helpers\StorefrontHelper::getImageUrl($item->variant->image_url);
                                } elseif ($item->product) {
                                $imgUrl = \App\Helpers\StorefrontHelper::getProductPrimaryImage($item->product);
                                }
                                @endphp

                                @if(!empty($imgUrl))
                                <img src="{{ $imgUrl }}" alt="{{ $item->product_name }}">
                                @else
                                <svg style="width: 22px !important; height: 22px !important; color: #cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                @endif
                            </div>

                            {{-- Details --}}
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem;">
                                    <div>
                                        <h4 style="font-size: 0.8125rem; font-weight: 600; color: #0f172a; margin: 0; line-height: 1.3;">
                                            {{ $item->product_name }}
                                        </h4>
                                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; margin-top: 0.35rem;">
                                            @if($item->sku)
                                            <span style="font-family: monospace; font-size: 0.6875rem; color: #475569; background: #f1f5f9; padding: 0.15rem 0.4rem; border-radius: 0.25rem;">
                                                SKU: {{ $item->sku }}
                                            </span>
                                            @endif
                                            @if($item->selected_color)
                                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6875rem; color: #334155; background: #f1f5f9; padding: 0.15rem 0.45rem; border-radius: 0.25rem;">
                                                <span style="width: 8px; height: 8px; border-radius: 50%; border: 1px solid #cbd5e1; background-color: {{ strtolower($item->selected_color) }};"></span>
                                                {{ $item->selected_color }}
                                            </span>
                                            @endif
                                            @if($item->selected_size)
                                            <span style="font-size: 0.6875rem; font-weight: 600; color: #334155; background: #f1f5f9; padding: 0.15rem 0.45rem; border-radius: 0.25rem;">
                                                Size: {{ $item->selected_size }}
                                            </span>
                                            @endif
                                            @if($item->is_preorder)
                                            <span style="font-size: 0.6875rem; font-weight: 600; color: #b45309; background: #fef3c7; border: 1px solid #fde68a; padding: 0.15rem 0.45rem; border-radius: 0.25rem;">
                                                Pre-order
                                            </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Price Breakdown --}}
                                    <div style="text-align: right; flex-shrink: 0;">
                                        <div style="font-size: 0.8125rem; font-weight: 700; color: #0f172a;">
                                            Rs. {{ number_format((float)($item->unit_price * $item->quantity), 2) }}
                                        </div>
                                        <div style="font-size: 0.6875rem; color: #64748b;">
                                            Rs. {{ number_format((float)$item->unit_price, 2) }} × {{ $item->quantity }}
                                        </div>
                                    </div>
                                </div>

                                {{-- Custom Product Specs --}}
                                @if(!empty($item->custom_measurements))
                                <div style="margin-top: 0.625rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.5rem 0.625rem; font-size: 0.6875rem; color: #334155;">
                                    <div style="font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">
                                        📦 Custom Specifications:
                                    </div>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.35rem;">
                                        @foreach($item->custom_measurements as $specK => $specV)
                                        <div>
                                            <span style="color: #64748b;">{{ ucfirst(str_replace('_', ' ', $specK)) }}:</span>
                                            <strong style="color: #0f172a; margin-left: 0.25rem;">{{ $specV }}</strong>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                        @empty
                        <div style="padding: 2rem; text-align: center; color: #94a3b8; font-size: 0.8125rem;">
                            No items recorded for this order.
                        </div>
                        @endforelse
                    </div>

                    {{-- Items Subtotal Summary Bar --}}
                    <div style="padding: 0.75rem 1.25rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: #475569;">
                        <span>Line Items Total ({{ $items->sum('quantity') }} items)</span>
                        <strong style="color: #0f172a; font-weight: 700;">Rs. {{ number_format((float)$order->subtotal, 2) }}</strong>
                    </div>
                </div>

                {{-- Chronological Operations Timeline --}}
                <div class="lj-card lj-card-p5">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                        <div class="lj-card-title">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Order Operations Timeline</span>
                        </div>
                        <span style="font-size: 0.6875rem; color: #94a3b8;">Nepal Time (NPT)</span>
                    </div>

                    @if(empty($timeline))
                    <div style="font-size: 0.75rem; color: #94a3b8; padding: 1rem; text-align: center;">No lifecycle events recorded yet.</div>
                    @else
                    <div class="lj-timeline">
                        @foreach($timeline as $event)
                        <div class="lj-tl-item">
                            {{-- Dot indicator --}}
                            <div class="lj-tl-dot" style="{{ $event['color'] === 'success' ? 'background: #10b981;' : ($event['color'] === 'info' ? 'background: #3b82f6;' : ($event['color'] === 'danger' ? 'background: #ef4444;' : 'background: #475569;')) }}"></div>

                            <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 0.75rem;">
                                <h5 style="font-size: 0.75rem; font-weight: 700; color: #0f172a; margin: 0;">
                                    {{ $event['title'] }}
                                </h5>
                                <time style="font-size: 0.6875rem; font-family: monospace; color: #64748b;">
                                    @php
                                    $t = $event['timestamp'] instanceof \Carbon\Carbon ? $event['timestamp'] : \Carbon\Carbon::parse($event['timestamp']);
                                    @endphp
                                    {{ $t->timezone('Asia/Kathmandu')->format('M d, H:i') }}
                                </time>
                            </div>
                            @if(!empty($event['description']))
                            <p style="font-size: 0.75rem; color: #475569; margin: 0.25rem 0 0 0; line-height: 1.4;">
                                {{ $event['description'] }}
                            </p>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

            </div>

            {{-- RIGHT COLUMN: Customer Dossier, Financials, Courier, Notes --}}
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">

                {{-- Customer Dossier Card --}}
                <div class="lj-card lj-card-p5">
                    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; margin-bottom: 0.875rem;">
                        <div class="lj-card-title">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>Customer Profile</span>
                        </div>
                        @if($customer)
                        <a href="{{ route('filament.admin.resources.customers.view', ['record' => $customer->id]) }}"
                            style="font-size: 0.6875rem; font-weight: 600; color: #047857; text-decoration: none; display: flex; align-items: center; gap: 0.25rem;">
                            View Profile &rarr;
                        </a>
                        @endif
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div class="lj-avatar">
                                {{ strtoupper(substr($order->first_name ?: 'C', 0, 1) . substr($order->last_name ?: '', 0, 1)) }}
                            </div>
                            <div style="min-width: 0;">
                                <div style="font-weight: 700; color: #0f172a; font-size: 0.8125rem;">
                                    {{ $order->customer_full_name ?: 'Retail Customer' }}
                                </div>
                                <div style="font-size: 0.6875rem; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    {{ $order->email ?: 'No email on record' }}
                                </div>
                            </div>
                        </div>

                        <div style="padding-top: 0.5rem; border-top: 1px solid #f1f5f9; display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.75rem;">
                            <div class="lj-data-row">
                                <span style="color: #64748b;">Mobile Phone:</span>
                                <span style="font-family: monospace; font-weight: 700; color: #0f172a;">{{ $order->phone ?: '—' }}</span>
                            </div>
                            @if($order->alt_phone)
                            <div class="lj-data-row">
                                <span style="color: #64748b;">Alternate Phone:</span>
                                <span style="font-family: monospace; color: #334155;">{{ $order->alt_phone }}</span>
                            </div>
                            @endif
                            <div style="padding-top: 0.5rem; border-top: 1px solid #f1f5f9;">
                                <div style="color: #64748b; margin-bottom: 0.35rem;">Delivery Destination:</div>
                                <div style="color: #0f172a; font-weight: 500; line-height: 1.4; background: #f8fafc; padding: 0.625rem; border-radius: 0.5rem; border: 1px solid #e2e8f0;">
                                    {{ $order->full_address }}
                                    @if($order->landmark)
                                    <div style="color: #047857; font-weight: 600; margin-top: 0.25rem;">
                                        Landmark: Near {{ $order->landmark }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Payment & Nepal VAT Financials Card --}}
                <div class="lj-card lj-card-p5">
                    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; margin-bottom: 0.875rem;">
                        <div class="lj-card-title">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Financials & Payment</span>
                        </div>
                        <span style="font-size: 0.6875rem; font-weight: 700; color: #64748b;">NPR (Rs.)</span>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.75rem;">
                        <div class="lj-data-row">
                            <span style="color: #64748b;">Subtotal:</span>
                            <span style="font-weight: 600; color: #0f172a;">Rs. {{ number_format((float)$order->subtotal, 2) }}</span>
                        </div>
                        @if((float)$order->coupon_discount > 0)
                        <div class="lj-data-row" style="color: #e11d48;">
                            <span>Discount / Coupon:</span>
                            <span style="font-weight: 600;">- Rs. {{ number_format((float)$order->coupon_discount, 2) }}</span>
                        </div>
                        @endif
                        <div class="lj-data-row">
                            <span style="color: #64748b;">Shipping Fee:</span>
                            <span style="font-weight: 600; color: #0f172a;">
                                @if((float)$order->shipping_fee > 0)
                                Rs. {{ number_format((float)$order->shipping_fee, 2) }}
                                @else
                                <span style="color: #059669;">Free</span>
                                @endif
                            </span>
                        </div>
                        @if((float)$order->vat_amount > 0)
                        <div class="lj-data-row">
                            <span style="color: #64748b;">Statutory 13% VAT:</span>
                            <span style="font-weight: 600; color: #0f172a;">Rs. {{ number_format((float)$order->vat_amount, 2) }}</span>
                        </div>
                        @endif
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.625rem 0; font-size: 0.875rem; font-weight: 700; color: #0f172a; border-top: 1px solid #e2e8f0; border-bottom: 2px solid #0f172a; margin-top: 0.25rem;">
                            <span>Grand Total:</span>
                            <span style="font-size: 1rem; color: #047857;">Rs. {{ number_format((float)$order->total_amount, 2) }}</span>
                        </div>

                        {{-- Payment Method Details --}}
                        <div style="padding-top: 0.5rem; display: flex; flex-direction: column; gap: 0.35rem;">
                            <div class="lj-data-row">
                                <span style="color: #64748b;">Method:</span>
                                <strong style="color: #1e293b;">{{ strtoupper($order->payment_method ?? 'COD') }}</strong>
                            </div>
                            @if($order->payment_reference)
                            <div class="lj-data-row">
                                <span style="color: #64748b;">Ref / TXN ID:</span>
                                <span style="font-family: monospace; color: #334155;">{{ $order->payment_reference }}</span>
                            </div>
                            @endif
                            @if($order->connectips_txnid)
                            <div class="lj-data-row">
                                <span style="color: #64748b;">ConnectIPS TXN:</span>
                                <span style="font-family: monospace; color: #334155;">{{ $order->connectips_txnid }}</span>
                            </div>
                            @endif
                            @if($order->payment_verified_at)
                            <div class="lj-data-row">
                                <span style="color: #64748b;">Verified On:</span>
                                <span style="color: #059669; font-weight: 500;">{{ $order->payment_verified_at->timezone('Asia/Kathmandu')->format('M d, H:i') }}</span>
                            </div>
                            @endif
                        </div>

                        {{-- Receipt screenshot preview if uploaded --}}
                        @if($order->payment_receipt_image)
                        <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #f1f5f9;">
                            <a href="{{ route('admin.orders.payment_proof', $order) }}" target="_blank"
                                style="font-size: 0.6875rem; font-weight: 600; color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                View Attached Payment Receipt
                            </a>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Courier & Fulfillment Logistics Card --}}
                <div class="lj-card lj-card-p5">
                    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; margin-bottom: 0.875rem;">
                        <div class="lj-card-title">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                            <span>Nepal Courier & Dispatch</span>
                        </div>
                        <span class="lj-badge" style="{{ $order->is_inside_valley ? 'background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;' : 'background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe;' }}">
                            {{ $order->is_inside_valley ? 'Kathmandu Valley' : 'Outside Valley' }}
                        </span>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.75rem;">
                        <div class="lj-data-row">
                            <span style="color: #64748b;">Carrier Partner:</span>
                            <strong style="color: #0f172a;">{{ $order->carrier ?: ($order->courier_name ?: 'Unassigned') }}</strong>
                        </div>
                        <div class="lj-data-row">
                            <span style="color: #64748b;">Tracking / AWB #:</span>
                            <span style="font-family: monospace; font-weight: 700; color: #0f172a;">{{ $order->tracking_number ?: 'Pending Generation' }}</span>
                        </div>
                        @if($order->tracking_url)
                        <div style="padding-top: 0.25rem;">
                            <a href="{{ $order->tracking_url }}" target="_blank"
                                style="font-size: 0.6875rem; font-weight: 600; color: #2563eb; text-decoration: none;">
                                Open Courier Live Tracking &rarr;
                            </a>
                        </div>
                        @endif
                        @if($order->courier_status)
                        <div class="lj-data-row">
                            <span style="color: #64748b;">Carrier Status:</span>
                            <span style="font-weight: 500; color: #334155;">{{ $order->courier_status }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Operational Notes Card --}}
                <div class="lj-card lj-card-p5">
                    <div class="lj-card-title" style="margin-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                        <span>Operational Notes</span>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        @if($order->customer_notes)
                        <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 0.5rem; padding: 0.75rem; font-size: 0.75rem; color: #78350f;">
                            <div style="font-weight: 700; color: #92400e; margin-bottom: 0.25rem;">
                                💬 Customer Special Instructions:
                            </div>
                            <p style="margin: 0; line-height: 1.4;">{{ $order->customer_notes }}</p>
                        </div>
                        @endif

                        @if($order->internal_notes)
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem; font-size: 0.75rem; color: #334155;">
                            <div style="font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Internal Staff Notes:</div>
                            <p style="margin: 0; line-height: 1.4;">{{ $order->internal_notes }}</p>
                        </div>
                        @else
                        <div style="font-size: 0.75rem; color: #94a3b8;">No internal operational notes entered yet.</div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-filament-panels::page>