<?php

namespace App\Filament\Pages;

use App\Filament\Pages\OfflineSales\Concerns\InteractsWithPosSession;
use App\Models\OfflineSale;
use App\Models\Setting;
use App\Services\OfflineSaleService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class SalesHistory extends Page
{
    use InteractsWithPosSession;

    protected string $view = 'filament.pages.offline-sales.history-page';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';
    protected static string | \UnitEnum | null $navigationGroup = 'Sales';
    protected static ?string $navigationLabel = 'Sales History';
    protected static ?int $navigationSort = 11;
    protected static ?string $title = 'Sales History';
    protected static ?string $slug = 'offline-sales/history';

    public static function getRelativeRouteName(\Filament\Panel $panel): string
    {
        return 'offline-sales.history';
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public string $activeTab = 'history';
    public string $currency = 'NPR';

    // Sales History Filters & Detail State
    public string $historySearch = '';
    public string $historyDateFilter = 'all_time';
    public string $historyChannelFilter = '';
    public string $historyPaymentFilter = '';
    public string $historyStatusFilter = '';
    public ?array $selectedSaleDetail = null;

    // Voiding Modal State
    public ?int $voidSaleId = null;
    public string $voidSaleNumber = '';
    public string $voidReason = 'Customer cancellation / return';
    public bool $voidRestockInventory = true;

    // Receipt Data for Reprinting
    public ?array $receiptData = null;
    public ?string $whatsappUrl = null;

    public function mount(): void
    {
        $this->currency = Setting::get('default_currency', 'NPR');
        $this->initPosSession();
    }

    public function getSalesHistoryProperty(): array
    {
        $query = OfflineSale::with(['items', 'customer', 'creator', 'voidLogs', 'station', 'warehouse'])
            ->latest('sold_at');

        if (!empty($this->historySearch)) {
            $term = trim($this->historySearch);
            $query->where(function ($q) use ($term) {
                $q->where('sale_number', 'like', "%{$term}%")
                    ->orWhere('customer_name', 'like', "%{$term}%")
                    ->orWhere('customer_phone', 'like', "%{$term}%")
                    ->orWhereHas('items', function ($iq) use ($term) {
                        $iq->where('product_name', 'like', "%{$term}%")
                            ->orWhere('sku', 'like', "%{$term}%");
                    });
            });
        }

        if (!empty($this->historyChannelFilter)) {
            $query->where('sales_channel', $this->historyChannelFilter);
        }

        if (!empty($this->historyPaymentFilter)) {
            $query->where('payment_method', $this->historyPaymentFilter);
        }

        if (!empty($this->historyStatusFilter)) {
            $query->where('status', $this->historyStatusFilter);
        }

        // Date Filter
        $ktmNow = now('Asia/Kathmandu');
        if ($this->historyDateFilter === 'today') {
            $query->whereDate('sold_at', $ktmNow->toDateString());
        } elseif ($this->historyDateFilter === 'yesterday') {
            $query->whereDate('sold_at', $ktmNow->copy()->subDay()->toDateString());
        } elseif ($this->historyDateFilter === 'this_month') {
            $query->whereYear('sold_at', $ktmNow->year)->whereMonth('sold_at', $ktmNow->month);
        } elseif ($this->historyDateFilter === 'last_month') {
            $lastMo = $ktmNow->copy()->subMonth();
            $query->whereYear('sold_at', $lastMo->year)->whereMonth('sold_at', $lastMo->month);
        } elseif (preg_match('/^([a-z]{3})_2026$/', $this->historyDateFilter, $m)) {
            $moNum = date('n', strtotime("1 {$m[1]} 2026"));
            $query->whereYear('sold_at', 2026)->whereMonth('sold_at', $moNum);
        }

        return $query->limit(100)->get()->toArray();
    }

    public function openSaleDetail(int $saleId): void
    {
        $sale = OfflineSale::with(['items', 'customer', 'creator', 'voidLogs', 'warehouse'])->find($saleId);
        if ($sale) {
            $this->selectedSaleDetail = $sale->toArray();
            $this->activeModal = 'sale_detail';
        }
    }

    public function reprintReceipt(int $saleId): void
    {
        $sale = OfflineSale::with(['items', 'warehouse', 'creator'])->find($saleId);
        if (!$sale) return;

        $items = [];
        foreach ($sale->items as $it) {
            $items[] = [
                'name' => $it->product_name,
                'color' => $it->color,
                'size' => $it->size,
                'quantity' => $it->quantity,
                'unit_price' => (float)$it->unit_price,
                'total' => (float)$it->total_price,
            ];
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', (string)$sale->customer_phone);
        $currSymbol = 'Rs. ';

        $msg = "✨ *LAIJAU — SALES RECEIPT* ✨\n\n";
        $msg .= "Dear " . $sale->customer_name . ",\n";
        $msg .= "Thank you for shopping at Laijau Showroom!\n\n";
        $msg .= "🔖 *Sale Reference:* #" . $sale->sale_number . "\n";
        $msg .= "📅 *Date:* " . ($sale->sold_at ? $sale->sold_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A')) . "\n";
        $msg .= "💳 *Payment:* " . ucfirst(str_replace('_', ' ', $sale->payment_method)) . "\n";
        $msg .= "📍 *Channel:* " . ucfirst(str_replace('_', ' ', $sale->sales_channel)) . "\n";
        $msg .= "-------------------------------------------\n";

        foreach ($items as $item) {
            $varStr = !empty($item['color']) || !empty($item['size']) ? " (" . trim(($item['color'] ?? '') . ' ' . ($item['size'] ?? '')) . ")" : "";
            $msg .= "• {$item['quantity']}x {$item['name']}{$varStr} — " . $currSymbol . number_format($item['unit_price'] * $item['quantity'], 2) . "\n";
        }

        $msg .= "-------------------------------------------\n";
        if ($sale->discount_amount > 0) {
            $msg .= "Discount: -" . $currSymbol . number_format($sale->discount_amount, 2) . "\n";
        }
        $msg .= "💰 *TOTAL PAID:* *" . $currSymbol . number_format($sale->total_amount, 2) . "*\n";
        if ($sale->payment_method === 'cash' && $sale->cash_received) {
            $msg .= "💵 Cash Tendered: " . $currSymbol . number_format($sale->cash_received, 2) . "\n";
            $msg .= "🪙 Change Returned: " . $currSymbol . number_format($sale->change_given, 2) . "\n";
        }
        $msg .= "\nWarm regards,\n*Laijau Kathmandu*";

        $this->whatsappUrl = !empty($cleanPhone)
            ? "https://wa.me/{$cleanPhone}?text=" . urlencode($msg)
            : "https://api.whatsapp.com/send?text=" . urlencode($msg);

        $this->receiptData = [
            'id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'date' => $sale->sold_at ? $sale->sold_at->timezone('Asia/Kathmandu')->format('d M Y') : now('Asia/Kathmandu')->format('d M Y'),
            'time' => $sale->sold_at ? $sale->sold_at->timezone('Asia/Kathmandu')->format('h:i A') : now('Asia/Kathmandu')->format('h:i A'),
            'cashier_name' => $sale->creator ? $sale->creator->name : ($sale->staff_name ?? 'Showroom Cashier'),
            'warehouse_name' => $sale->warehouse ? $sale->warehouse->name : 'Laijau Showroom',
            'warehouse_code' => $sale->warehouse ? $sale->warehouse->code : 'STORE-KTM-01',
            'customer_name' => $sale->customer_name,
            'customer_phone' => $sale->customer_phone,
            'payment_method' => ucfirst(str_replace('_', ' ', $sale->payment_method)),
            'sales_channel' => ucfirst(str_replace('_', ' ', $sale->sales_channel)),
            'items' => $items,
            'subtotal' => (float)$sale->subtotal,
            'discount' => (float)$sale->discount_amount,
            'total' => (float)$sale->total_amount,
            'cash_received' => $sale->cash_received ? (float)$sale->cash_received : null,
            'change_given' => $sale->change_given ? (float)$sale->change_given : null,
        ];

        $this->activeModal = 'receipt_modal';
        $this->dispatch('open-receipt-modal');
    }

    public function printViaAgent(int $saleId): void
    {
        $sale = OfflineSale::with(['items', 'warehouse', 'creator'])->find($saleId);
        if (!$sale) return;

        try {
            app(\App\Services\PrintAgent\PrintAgentService::class)->reprintOfflineSaleReceipt($sale);
            Notification::make()
                ->title('Receipt Sent to Print Agent')
                ->body("Print job queued for Showroom 80mm thermal printer.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Print Agent Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function openVoidModal(int $saleId): void
    {
        $sale = OfflineSale::find($saleId);
        if ($sale) {
            $this->voidSaleId = $sale->id;
            $this->voidSaleNumber = $sale->sale_number;
            $this->voidReason = 'Customer cancellation / return';
            $this->voidRestockInventory = true;
            $this->activeModal = 'void_modal';
        }
    }

    public function processVoidSale(): void
    {
        if (!$this->voidSaleId) return;

        $sale = OfflineSale::find($this->voidSaleId);
        if (!$sale) return;

        try {
            $service = app(OfflineSaleService::class);
            $service->voidSale($sale, $this->voidReason, Auth::user(), $this->voidRestockInventory);

            $this->closeModal();
            $this->voidSaleId = null;

            Notification::make()
                ->title("Sale #{$sale->sale_number} Voided")
                ->body($this->voidRestockInventory ? "Sale marked as voided & inventory restocked." : "Sale marked as voided.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title("Void Failed")
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function closeModal(): void
    {
        $this->activeModal = null;
        $this->selectedSaleDetail = null;
    }
}
