<div class="print:text-black">
    <div class="mb-4">
        <h3 class="text-lg font-bold">Order #{{ $order->id }}</h3>
        <p class="text-sm">Customer: {{ $order->first_name }} {{ $order->last_name }}</p>
    </div>
    <div class="space-y-4">
        @foreach($order->items as $item)
            @if($item->custom_measurements)
                <div class="border p-4 rounded bg-gray-50 print:bg-white print:border-black">
                    <h4 class="font-bold text-md mb-2">Item: {{ $item->product->name ?? 'Product' }} (Size: Custom)</h4>
                    <ul class="list-disc pl-5">
                        @foreach($item->custom_measurements as $key => $value)
                            <li><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    </div>
</div>
