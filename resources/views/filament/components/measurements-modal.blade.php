<div class="space-y-4">
    @foreach($order->items->whereNotNull('custom_measurements') as $item)
        <div class="border border-gray-200 dark:border-gray-700 p-4 rounded-lg bg-white dark:bg-gray-800">
            <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-2">Item: {{ $item->product->name ?? 'Unknown Product' }}</h3>
            <p class="text-sm text-gray-500 mb-4">Quantity: {{ $item->quantity }}</p>

            <div class="grid grid-cols-2 gap-4 text-sm">
                @foreach(is_string($item->custom_measurements) ? json_decode($item->custom_measurements, true) : $item->custom_measurements as $key => $value)
                    <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded">
                        <span class="block text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-bold mb-1">{{ str_replace('_', ' ', $key) }}</span>
                        <span class="text-base text-gray-900 dark:text-white font-medium">{{ $value }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="mt-6">
        <x-filament::button tag="a" href="{{ route('order.spec_sheet', $order) }}" target="_blank" color="primary" icon="heroicon-o-printer">
            Print Spec Sheet
        </x-filament::button>
    </div>
</div>
