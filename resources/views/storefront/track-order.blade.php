@extends('layouts.storefront')

@section('title', 'Track Your Order | Laijau')
@section('description', 'Track your Laijau order delivery status, payment verification, and courier details in real-time.')

@section('content')
<div class="bg-gray-50 min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Track Your Order</h1>
            <p class="mt-2 text-base text-gray-600">Enter your order reference and phone number to see live delivery status.</p>
        </div>

        <!-- Tracking Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sm:p-8 mb-8">
            <form action="{{ route('storefront.track_order') }}" method="GET" class="space-y-4 sm:space-y-0 sm:flex sm:gap-4 items-end">
                <div class="flex-1">
                    <label for="order_number" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1">Order Reference</label>
                    <input
                        type="text"
                        name="order_number"
                        id="order_number"
                        value="{{ $orderNumber }}"
                        placeholder="e.g. LJ-001001"
                        required
                        class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm focus:border-black focus:ring-1 focus:ring-black focus:outline-none transition uppercase"
                    >
                </div>
                <div class="flex-1">
                    <label for="phone" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1">Phone Number</label>
                    <input
                        type="text"
                        name="phone"
                        id="phone"
                        value="{{ $phone }}"
                        placeholder="e.g. 98XXXXXXXX"
                        class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm focus:border-blue-600 focus:ring-1 focus:ring-blue-600 focus:outline-none transition"
                    >
                </div>
                <button
                    type="submit"
                    class="w-full sm:w-auto px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-xl transition duration-150 flex items-center justify-center gap-2 cursor-pointer shadow-xs"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Track Status
                </button>
            </form>

            @if(!empty($error))
                <div class="mt-4 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    <span>{{ $error }}</span>
                </div>
            @endif
        </div>

        @if($order)
            <!-- Order Details Box -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-8">
                <!-- Top Status Header -->
                <div class="border-b border-gray-200 bg-gray-50/50 p-6 flex flex-wrap justify-between items-center gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <h2 class="text-xl font-bold text-gray-900">Order #{{ $order->order_number }}</h2>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                                @if($order->status === 'delivered') bg-emerald-100 text-emerald-800
                                @elseif(in_array($order->status, ['cancelled', 'failed_delivery'])) bg-red-100 text-red-800
                                @else bg-amber-100 text-amber-800 @endif">
                                {{ \App\Models\Order::getStatuses()[$order->status] ?? ucfirst(str_replace('_', ' ', $order->status)) }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Placed on {{ $order->created_at->format('M d, Y h:i A') }}</p>
                    </div>

                    <!-- Payment Status Badge -->
                    <div class="text-right">
                        <div class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Payment Status</div>
                        <div class="text-sm font-bold
                            @if($order->payment_status === 'paid') text-emerald-600
                            @elseif($order->payment_status === 'payment_verification_pending') text-amber-600
                            @elseif($order->payment_status === 'rejected') text-red-600
                            @else text-gray-700 @endif">
                            {{ \App\Models\Order::getPaymentStatuses()[$order->payment_status] ?? ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                        </div>
                    </div>
                </div>

                <!-- Cancellation Banner if cancelled -->
                @if($order->status === 'cancelled')
                    <div class="bg-red-50 border-b border-red-200 p-4 sm:p-6">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-red-100 flex items-center justify-center text-red-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-red-900">Order Cancelled</div>
                                <p class="text-xs text-red-700 mt-0.5">
                                    This order has been cancelled and is not active for fulfillment.
                                    @if($order->cancellation_reason)
                                        <br><span class="font-semibold">Reason:</span> {{ $order->cancellation_reason }}
                                    @endif
                                    @if($order->cancelled_at)
                                        &bull; <span class="font-semibold">Cancelled on:</span> {{ $order->cancelled_at->format('M d, Y h:i A') }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Courier Tracking Banner if shipped -->
                @if($order->tracking_number || in_array($order->status, ['handed_to_courier', 'in_transit', 'delivered']))
                    @php $shipment = $order->latestShipment; @endphp
                    <div class="bg-blue-50 border-b border-blue-100 p-4 sm:p-6 flex flex-wrap justify-between items-center gap-4">
                        <div>
                            <div class="text-xs font-bold text-blue-900 uppercase tracking-wider">Courier Dispatch Information</div>
                            <div class="text-sm text-blue-800 mt-1">
                                Courier: <strong>{{ $order->courier_name ?: $order->carrier ?: 'Nepal Courier Partner' }}</strong>
                                @if($order->tracking_number)
                                    &bull; AWB/Tracking: <span class="font-mono font-bold">{{ $order->tracking_number }}</span>
                                @endif
                                @if($shipment && $shipment->destination_branch)
                                    &bull; Hub: <span class="font-semibold">{{ $shipment->destination_branch }}</span>
                                @endif
                                @if($order->courier_status)
                                    &bull; Courier Status: <span class="font-semibold">{{ $order->courier_status }}</span>
                                @endif
                                @if($order->actual_delivery_date)
                                    &bull; Delivered: <span class="font-semibold">{{ \Carbon\Carbon::parse($order->actual_delivery_date)->format('d M Y') }}</span>
                                @endif
                            </div>
                        </div>
                        @if($order->tracking_url || \App\Models\Order::resolveTrackingUrl($order->courier_name ?: $order->carrier, $order->tracking_number))
                            <a
                                href="{{ $order->tracking_url ?: \App\Models\Order::resolveTrackingUrl($order->courier_name ?: $order->carrier, $order->tracking_number) }}"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition"
                            >
                                Track on Courier Website
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        @endif
                    </div>
                @endif

                <!-- Items & Financial Summary -->
                <div class="p-6">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-900 mb-4">Ordered Items</h3>
                    <div class="divide-y divide-gray-100">
                        @foreach($order->items as $item)
                            <div class="py-3 flex justify-between items-center text-sm">
                                <div>
                                    <div class="font-medium text-gray-900">{{ $item->product_name }}</div>
                                    <div class="text-xs text-gray-500">
                                        Qty: {{ $item->quantity }}
                                        @if($item->selected_color) &bull; Color: {{ $item->selected_color }} @endif
                                        @if($item->selected_size) &bull; Size: {{ $item->selected_size }} @endif
                                    </div>
                                </div>
                                <div class="font-medium text-gray-900">
                                    {{ \App\Helpers\StorefrontHelper::formatPrice($item->unit_price * $item->quantity, $order->currency) }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-gray-200 mt-4 pt-4 space-y-2 text-sm text-gray-600">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span>{{ \App\Helpers\StorefrontHelper::formatPrice($order->subtotal, $order->currency) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Shipping ({{ $order->shipping_method }})</span>
                            <span>{{ $order->shipping_fee > 0 ? \App\Helpers\StorefrontHelper::formatPrice($order->shipping_fee, $order->currency) : 'Free' }}</span>
                        </div>
                        @if($order->coupon_discount > 0)
                            <div class="flex justify-between text-emerald-600">
                                <span>Discount</span>
                                <span>-{{ \App\Helpers\StorefrontHelper::formatPrice($order->coupon_discount, $order->currency) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-base font-bold text-gray-900 pt-2 border-t border-gray-100">
                            <span>Total Amount</span>
                            <span>{{ \App\Helpers\StorefrontHelper::formatPrice($order->total_amount, $order->currency) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Delivery Address -->
                <div class="bg-gray-50 p-6 border-t border-gray-200 text-sm">
                    <div class="text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">Delivery Destination</div>
                    <div class="font-medium text-gray-900">{{ $order->customer_full_name }} &bull; {{ $order->phone }}</div>
                    <div class="text-gray-600 mt-1">{{ $order->full_address }}</div>
                    @if($order->is_inside_valley)
                        <span class="inline-block mt-2 px-2 py-0.5 rounded text-xs bg-green-100 text-green-800 font-medium">Inside Kathmandu Valley</span>
                    @else
                        <span class="inline-block mt-2 px-2 py-0.5 rounded text-xs bg-gray-200 text-gray-800 font-medium">Outside Valley Courier Delivery</span>
                    @endif
                </div>
            </div>

            <!-- WhatsApp Help Shortcut -->
            <div class="text-center">
                <a
                    href="https://wa.me/9779843512095?text={{ urlencode('Hello Laijau, I have an inquiry regarding my order #' . $order->order_number . '. Could you please update me on the status?') }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:text-emerald-800 transition"
                >
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824z"/></svg>
                    Have questions? Chat with our team on WhatsApp
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
