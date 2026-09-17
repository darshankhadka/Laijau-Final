<?php

namespace App\Filament\Resources\AccountingInvoiceResource\Pages;

use App\Filament\Resources\AccountingInvoiceResource;
use App\Models\Accounting\AccountingInvoice;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAccountingInvoice extends CreateRecord
{
    protected static string $resource = AccountingInvoiceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $type = $data['type'] ?? 'sales_invoice';
        $issueDate = $data['issue_date'] ?? date('Y-m-d');
        $data['invoice_number'] = AccountingInvoice::generateNextInvoiceNumber($type, $issueDate);

        $items = $data['items'] ?? [];
        unset($data['items']);

        $invoice = AccountingInvoice::create($data);

        $subtotal = 0.0;
        $totalVat = 0.0;
        foreach ($items as $item) {
            $qty = (float)($item['quantity'] ?? 1);
            $price = (float)($item['unit_price'] ?? 0);
            $vatRate = (float)($item['vat_rate'] ?? 13.00);

            $lineSub = round($qty * $price, 4);
            $lineVat = round($lineSub * ($vatRate / 100), 4);
            $lineTot = round($lineSub + $lineVat, 4);

            $invoice->items()->create([
                'account_id' => $item['account_id'] ?? null,
                'description' => $item['description'] ?? 'Item',
                'quantity' => $qty,
                'unit_price' => $price,
                'vat_rate' => $vatRate,
                'vat_amount' => $lineVat,
                'total_amount' => $lineTot,
            ]);

            $subtotal += $lineSub;
            $totalVat += $lineVat;
        }

        $invoice->update([
            'subtotal' => $subtotal,
            'vat_amount' => $totalVat,
            'total_amount' => $subtotal + $totalVat,
        ]);

        return $invoice;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
