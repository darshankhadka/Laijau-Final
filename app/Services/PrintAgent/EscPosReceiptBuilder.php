<?php

namespace App\Services\PrintAgent;

use App\Models\OfflineSale;
use App\Models\Order;

class EscPosReceiptBuilder
{
    protected string $buffer = '';
    protected int $width = 48; // Standard 80mm thermal receipt width (Font A)

    // ESC/POS Command Constants
    public const ESC = "\x1B";
    public const GS  = "\x1D";

    public function __construct(int $width = 48)
    {
        $this->width = $width;
        $this->init();
    }

    public function init(): self
    {
        // ESC @ (Initialize printer hardware)
        $this->buffer = self::ESC . "@";
        return $this;
    }

    public function alignLeft(): self
    {
        // ESC a 0
        $this->buffer .= self::ESC . "a\x00";
        return $this;
    }

    public function alignCenter(): self
    {
        // ESC a 1
        $this->buffer .= self::ESC . "a\x01";
        return $this;
    }

    public function alignRight(): self
    {
        // ESC a 2
        $this->buffer .= self::ESC . "a\x02";
        return $this;
    }

    public function bold(bool $on = true): self
    {
        // ESC E n
        $this->buffer .= self::ESC . "E" . ($on ? "\x01" : "\x00");
        return $this;
    }

    public function doubleHeight(bool $on = true): self
    {
        // GS ! n (Bit 4: double height)
        $this->buffer .= self::GS . "!" . ($on ? "\x10" : "\x00");
        return $this;
    }

    public function doubleWidth(bool $on = true): self
    {
        // GS ! n (Bit 5: double width)
        $this->buffer .= self::GS . "!" . ($on ? "\x20" : "\x00");
        return $this;
    }

    public function doubleSize(bool $on = true): self
    {
        // GS ! n (Bit 4 + 5: double width & height)
        $this->buffer .= self::GS . "!" . ($on ? "\x30" : "\x00");
        return $this;
    }

    public function line(string $text = ''): self
    {
        $this->buffer .= $this->sanitizeText($text) . "\n";
        return $this;
    }

    public function row(string $left, string $right): self
    {
        $cleanLeft  = $this->sanitizeText($left);
        $cleanRight = $this->sanitizeText($right);

        $lenLeft  = strlen($cleanLeft);
        $lenRight = strlen($cleanRight);

        if ($lenLeft + $lenRight >= $this->width) {
            $spaceAvail = max(0, $this->width - $lenRight - 1);
            $cleanLeft = substr($cleanLeft, 0, $spaceAvail);
            $lenLeft = strlen($cleanLeft);
        }

        $spaces = max(1, $this->width - $lenLeft - $lenRight);
        $this->buffer .= $cleanLeft . str_repeat(' ', $spaces) . $cleanRight . "\n";
        return $this;
    }

    public function divider(string $char = '-'): self
    {
        $this->buffer .= str_repeat($char, $this->width) . "\n";
        return $this;
    }

    public function doubleDivider(): self
    {
        $this->buffer .= str_repeat('=', $this->width) . "\n";
        return $this;
    }

    public function feed(int $lines = 1): self
    {
        // ESC d n
        $this->buffer .= self::ESC . "d" . chr($lines);
        return $this;
    }

    public function cut(): self
    {
        // Feed 3 lines then partial cut (GS V 66 0)
        $this->buffer .= "\n\n\n";
        $this->buffer .= self::GS . "V\x42\x00";
        return $this;
    }

    public function getBinary(): string
    {
        return $this->buffer;
    }

    public function getBase64(): string
    {
        return base64_encode($this->buffer);
    }

    protected function sanitizeText(string $text): string
    {
        // Replace non-ASCII / Nepali symbols with safe representations
        $replacements = [
            'रू' => 'Rs.',
            'रु' => 'Rs.',
            '₹'  => 'Rs.',
            '–'  => '-',
            '—'  => '-',
            '“'  => '"',
            '”'  => '"',
            '‘'  => "'",
            '’'  => "'",
            '•'  => '*',
        ];
        $text = str_replace(array_keys($replacements), array_values($replacements), $text);
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    }

    // =========================================================================
    // POS RECEIPT BUILDER (OFFLINE / SHOWROOM SALES)
    // =========================================================================

    /**
     * Build ESC/POS payload for a completed OfflineSale.
     */
    public static function buildFromOfflineSale(OfflineSale $sale): self
    {
        $builder = new self(48);

        // 1. Header
        $builder->alignCenter()
            ->bold(true)->doubleSize(true)
            ->line('LAIJAU')
            ->doubleSize(false)
            ->bold(true)
            ->line('SHOWROOM POS RECEIPT')
            ->bold(false)
            ->line('Bohara Tol, Kageshwori Manahara 09')
            ->line('Kathmandu, Nepal')
            ->line('WhatsApp: 9843512095')
            ->doubleDivider();

        // 2. Metadata Section
        $saleDate = $sale->completed_at ? $sale->completed_at->format('d M Y, H:i') : now()->format('d M Y, H:i');
        $cashierName = $sale->user ? $sale->user->name : 'Showroom Cashier';
        $customerName = $sale->customer_name ?: 'Walk-in Customer';

        $builder->alignLeft()
            ->row('Sale No:', '#' . $sale->sale_number)
            ->row('Date / Time:', $saleDate)
            ->row('Cashier:', $cashierName)
            ->row('Customer:', $customerName);

        if ($sale->customer_phone) {
            $builder->row('Phone:', (string)$sale->customer_phone);
        }

        // 3. Items Header
        $builder->divider()
            ->bold(true)
            ->row('ITEM / DESCRIPTION', 'QTY x RATE   TOTAL')
            ->bold(false)
            ->divider();

        // 4. Line Items
        $sale->loadMissing(['items.product', 'items.variant']);
        foreach ($sale->items as $item) {
            $title = $item->product ? $item->product->name : ($item->sku ?: 'Showroom Item');

            $varParts = [];
            if ($item->variant) {
                if ($item->variant->color) $varParts[] = $item->variant->color;
                if ($item->variant->size)  $varParts[] = $item->variant->size;
            }
            $varDetails = !empty($varParts) ? ' (' . implode('/', $varParts) . ')' : '';

            $builder->bold(true)->line($title . $varDetails)->bold(false);

            if ($item->sku) {
                $builder->line('  SKU: ' . $item->sku);
            }

            $rateText = $item->quantity . ' x Rs. ' . number_format((float)$item->unit_price, 2);
            $amtText  = 'Rs. ' . number_format((float)$item->total_price, 2);
            $builder->row('  ' . $rateText, $amtText);
        }

        // 5. Totals Section
        $builder->divider()
            ->row('Subtotal:', 'Rs. ' . number_format((float)$sale->subtotal, 2));

        if ((float)$sale->discount_amount > 0) {
            $builder->row('Discount ' . ($sale->discount_reason ? '(' . $sale->discount_reason . ')' : '') . ':', '-Rs. ' . number_format((float)$sale->discount_amount, 2));
        }

        if ((float)$sale->shipping_amount > 0) {
            $builder->row('Delivery / Courier:', 'Rs. ' . number_format((float)$sale->shipping_amount, 2));
        }

        $builder->doubleDivider()
            ->bold(true)->doubleHeight(true)
            ->row('TOTAL PAID:', 'Rs. ' . number_format((float)$sale->total_amount, 2))
            ->doubleHeight(false)->bold(false)
            ->doubleDivider();

        // 13% VAT Inclusive Note
        $vatIncluded = round((float)$sale->total_amount * (13 / 113), 2);
        $taxableBase  = round((float)$sale->total_amount - $vatIncluded, 2);
        $builder->row('Taxable Base (Rs.):', number_format($taxableBase, 2))
            ->row('13% VAT Inclusive (Rs.):', number_format($vatIncluded, 2));

        // 6. Tender Details
        $pm = strtolower((string)$sale->payment_method);
        $pmLabel = match ($pm) {
            'fonepay', 'esewa' => 'Digital (eSewa / Fonepay)',
            'khalti' => 'Digital (Khalti QR)',
            'bank_transfer' => 'Bank Transfer',
            'card' => 'Card Terminal',
            'cash' => 'Cash',
            default => ucfirst(str_replace('_', ' ', $pm)),
        };

        $builder->divider()
            ->row('Payment Method:', $pmLabel);

        if ((float)$sale->cash_received > 0) {
            $builder->row('Cash Tendered:', 'Rs. ' . number_format((float)$sale->cash_received, 2))
                ->bold(true)
                ->row('Change Returned:', 'Rs. ' . number_format((float)$sale->change_given, 2))
                ->bold(false);
        }

        // 7. Footer
        $builder->doubleDivider()
            ->alignCenter()
            ->bold(true)
            ->line('THIS IS NOT A TAX INVOICE')
            ->line('FOR LAIJAU INTERNAL USE ONLY')
            ->line('PLEASE RETAIN YOUR TAX INVOICE FROM THE COUNTER')
            ->bold(false)
            ->line('7-day exchange only. No cash returns.')
            ->line('Items must be unworn with original tags.')
            ->line('Thank you for shopping at Laijau!')
            ->line('WhatsApp Support: 9843512095')
            ->cut();

        return $builder;
    }

    // =========================================================================
    // ONLINE ORDER BUILDERS
    // =========================================================================

    /**
     * Build ESC/POS payload for an Online Order Fulfillment Slip.
     */
    public static function buildFromOnlineOrder(Order $order, int $paperWidth = 48): self
    {
        $builder = new self($paperWidth);

        // 1. Header
        $builder->alignCenter()
            ->bold(true)->doubleSize(true)
            ->line('LAIJAU')
            ->doubleSize(false)
            ->bold(true)
            ->line('ONLINE ORDER FULFILLMENT SLIP')
            ->bold(false)
            ->line('Showroom & Dispatch Hub — Kathmandu')
            ->line('Tel: 9843512095 | www.laijau.com')
            ->doubleDivider();

        // 2. Order Metadata
        $orderDate = $order->created_at ? $order->created_at->format('d M Y, H:i') : now()->format('d M Y, H:i');
        $pm = strtoupper((string) $order->payment_method);
        $ps = strtoupper((string) $order->payment_status);

        $builder->alignLeft()
            ->row('Order Number:', '#' . $order->order_number)
            ->row('Order Date:', $orderDate)
            ->row('Channel:', 'Online Storefront')
            ->row('Payment Method:', $pm . " ({$ps})")
            ->divider();

        // 3. Customer & Delivery Info
        $fullName = trim($order->first_name . ' ' . $order->last_name);
        $phone = $order->phone . ($order->alt_phone ? ' / ' . $order->alt_phone : '');
        $city = $order->shipping_city ?: $order->municipality ?: 'Kathmandu';
        $courier = $order->courier_name ?: ($order->is_inside_valley ? 'Inside Valley Courier' : 'Outside Valley Courier (NCM/Pathao)');

        $builder->bold(true)
            ->line('DELIVERY DETAILS:')
            ->bold(false)
            ->row('Recipient:', substr($fullName, 0, $paperWidth - 12))
            ->row('Phone:', $phone)
            ->row('Destination:', $city)
            ->line('Address: ' . $order->shipping_address)
            ->row('Courier:', $courier);

        if (!empty($order->customer_notes)) {
            $builder->divider()
                ->bold(true)->line('CUSTOMER NOTE:')->bold(false)
                ->line($order->customer_notes);
        }

        $builder->doubleDivider();

        // 4. Order Items
        $builder->bold(true)
            ->row('ITEM / DETAILS', 'QTY x PRICE')
            ->bold(false)
            ->divider();

        $order->loadMissing('items');
        foreach ($order->items as $item) {
            $name = $item->product_name;
            $builder->bold(true)->line($name)->bold(false);

            $specs = [];
            if ($item->selected_color) $specs[] = 'Color: ' . $item->selected_color;
            if ($item->selected_size) $specs[] = 'Size: ' . $item->selected_size;
            if ($item->sku) $specs[] = 'SKU: ' . $item->sku;

            if (!empty($specs)) {
                $builder->line('  ' . implode(' | ', $specs));
            }

            $rateText = $item->quantity . ' x Rs. ' . number_format((float)$item->unit_price, 2);
            $totalText = 'Rs. ' . number_format((float)($item->quantity * $item->unit_price), 2);
            $builder->row('  ' . $rateText, $totalText);
        }

        // 5. Financial Summary
        $builder->divider()
            ->row('Subtotal:', 'Rs. ' . number_format((float)$order->subtotal, 2));

        if ((float)$order->coupon_discount > 0) {
            $builder->row('Coupon Discount:', '-Rs. ' . number_format((float)$order->coupon_discount, 2));
        }

        if ((float)$order->shipping_fee > 0) {
            $builder->row('Delivery Fee:', 'Rs. ' . number_format((float)$order->shipping_fee, 2));
        }

        $builder->doubleDivider()
            ->bold(true)->doubleHeight(true)
            ->row('TOTAL AMOUNT:', 'Rs. ' . number_format((float)$order->total_amount, 2))
            ->doubleHeight(false)->bold(false)
            ->doubleDivider();

        if (strtolower($order->payment_method) === 'cod' || strtolower($order->payment_status) === 'unpaid') {
            $builder->alignCenter()
                ->bold(true)
                ->line('*** CASH ON DELIVERY — COLLECT AMOUNT UPON DISPATCH ***')
                ->bold(false)
                ->divider();
        }

        // 6. Mandatory Internal Non-Tax Disclaimer
        $builder->alignCenter()
            ->bold(true)
            ->line('THIS IS NOT A TAX INVOICE')
            ->line('FOR LAIJAU INTERNAL USE ONLY')
            ->line('PLEASE RETAIN YOUR TAX INVOICE FROM THE COUNTER')
            ->bold(false)
            ->line('Showroom & Warehouse Dispatch Verified')
            ->line('Laijau Garments & Fashion Store')
            ->cut();

        return $builder;
    }

    /**
     * Build ESC/POS payload for an Order Packing Slip.
     */
    public static function buildPackingSlipFromOnlineOrder(Order $order, int $paperWidth = 48): self
    {
        $builder = new self($paperWidth);

        $builder->alignCenter()
            ->bold(true)->doubleSize(true)
            ->line('LAIJAU')
            ->doubleSize(false)
            ->bold(true)
            ->line('WAREHOUSE PACKING SLIP')
            ->bold(false)
            ->doubleDivider()
            ->alignLeft()
            ->row('Order Ref:', '#' . $order->order_number)
            ->row('Packed Date:', now()->format('d M Y, H:i'))
            ->row('Customer:', $order->first_name . ' ' . $order->last_name)
            ->row('Phone:', (string) $order->phone)
            ->row('Destination:', (string) ($order->shipping_city ?: 'Kathmandu'))
            ->line('Address: ' . $order->shipping_address)
            ->divider()
            ->bold(true)
            ->row('ITEM / SKU', 'QTY')
            ->bold(false)
            ->divider();

        $order->loadMissing('items');
        foreach ($order->items as $item) {
            $builder->bold(true)->line($item->product_name)->bold(false);
            $spec = 'SKU: ' . ($item->sku ?: 'N/A');
            if ($item->selected_color) $spec .= ' | Col: ' . $item->selected_color;
            if ($item->selected_size) $spec .= ' | Sz: ' . $item->selected_size;
            $builder->row('  ' . $spec, 'x ' . $item->quantity);
        }

        $builder->doubleDivider()
            ->alignCenter()
            ->bold(true)
            ->line('QUALITY CHECK & VERIFICATION PASSED')
            ->bold(false)
            ->line('Thank you for shopping at Laijau!')
            ->line('THIS IS NOT A TAX INVOICE')
            ->line('FOR LAIJAU INTERNAL USE ONLY')
            ->cut();

        return $builder;
    }

    // =========================================================================
    // HARDWARE TEST RECEIPT BUILDERS
    // =========================================================================

    /**
     * Build ESC/POS payload for a Hardware/Connection Test Job.
     */
    public static function buildTestReceipt(string $stationId): self
    {
        $builder = new self(48);

        $builder->alignCenter()
            ->bold(true)->doubleSize(true)
            ->line('LAIJAU')
            ->doubleSize(false)
            ->line('LOCAL PRINT AGENT TEST')
            ->bold(false)
            ->doubleDivider()
            ->alignLeft()
            ->row('Station ID:', $stationId)
            ->row('Test Timestamp:', now()->format('d M Y, H:i:s'))
            ->row('Server Status:', 'CONNECTED (HTTP 200)')
            ->row('Hardware Mode:', 'ESC/POS 80mm Thermal')
            ->divider()
            ->alignCenter()
            ->bold(true)
            ->line('PRINTER HARDWARE OPERATIONAL')
            ->bold(false)
            ->line('Raw socket and USB streaming verified.')
            ->doubleDivider()
            ->line('THIS IS NOT A TAX INVOICE')
            ->line('FOR LAIJAU INTERNAL USE ONLY')
            ->cut();

        return $builder;
    }

    /**
     * Build ESC/POS payload for a specific Printer Hardware Test.
     */
    public static function buildPrinterTestReceipt(string $stationName, string $printerName, string $connectionDetails, int $paperWidth = 48): self
    {
        $builder = new self($paperWidth);

        $builder->alignCenter()
            ->bold(true)->doubleSize(true)
            ->line('LAIJAU')
            ->doubleSize(false)
            ->bold(true)
            ->line('PRINTER CONNECTION TEST')
            ->bold(false)
            ->doubleDivider()
            ->alignLeft()
            ->row('POS Station:', $stationName)
            ->row('Target Printer:', $printerName)
            ->row('Connection:', $connectionDetails)
            ->row('Paper Spec:', $paperWidth === 32 ? '58mm (32 Col)' : '80mm (48 Col)')
            ->row('Timestamp:', now()->format('d M Y, H:i:s'))
            ->divider()
            ->alignCenter()
            ->bold(true)
            ->line('SUCCESSFUL HARDWARE RESPONSE')
            ->bold(false)
            ->line('Thermal print head & cutter verified.')
            ->doubleDivider()
            ->line('THIS IS NOT A TAX INVOICE')
            ->line('FOR LAIJAU INTERNAL USE ONLY')
            ->cut();

        return $builder;
    }
}
