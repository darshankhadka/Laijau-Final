<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingInvoiceItem;
use App\Models\Accounting\BikriKhataEntry;
use App\Models\Order;
use App\Models\OfflineSale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ReconcileBikriKhataCommand extends Command
{
    protected $signature = 'laijau:reconcile-bikri-khata {--dry-run : Run simulation without writing to database} {--live : Run live database updates}';
    protected $description = 'Reconcile statutory Bikri Khata (Sales Book - IRD Annex 7): compute 13% VAT & taxable amounts and assign WhatsApp / Web channels from authoritative sources';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('========================================================================');
        $this->info('  LAIJAU ERP — BIKRI KHATA STATUTORY RECONCILIATION (IRD ANNEX 7)');
        $this->info('  Mode: ' . ($dryRun ? 'DRY-RUN (Simulation)' : 'LIVE DATABASE UPDATE'));
        $this->info('========================================================================');

        // 1. RECONCILE SHOWROOM SALES (13% VAT & Taxable Amount)
        $this->info("\n--- 1. Recalculating Showroom POS Taxable & 13% Output VAT ---");
        $posInvoices = AccountingInvoice::where('type', 'sales_invoice')
            ->where(function ($q) {
                $q->where('sales_channel', 'pos_showroom')
                  ->orWhereNotNull('reference_offline_sale_id');
            })
            ->where('total_amount', '>', 0)
            ->where(function ($q) {
                $q->where('taxable_amount', '<=', 0)
                  ->orWhere('vat_amount', '<=', 0);
            })
            ->get();

        $this->info("Found {$posInvoices->count()} showroom invoices requiring statutory VAT calculation.");
        $posUpdated = 0;

        if (!$dryRun) {
            DB::beginTransaction();
            try {
                foreach ($posInvoices as $inv) {
                    $gross = (float)$inv->total_amount;
                    $taxable = round($gross / 1.13, 2);
                    $vat = round($gross - $taxable, 2);

                    $inv->subtotal = $taxable;
                    $inv->taxable_amount = $taxable;
                    $inv->vat_amount = $vat;
                    $inv->currency = 'NPR';
                    $inv->contact_country = 'NP';
                    $inv->sales_channel = 'pos_showroom';
                    $inv->save();

                    // Update or create line item
                    $item = $inv->items()->first();
                    if ($item) {
                        $item->unit_price = $taxable;
                        $item->vat_rate = 13.00;
                        $item->vat_amount = $vat;
                        $item->total_amount = $gross;
                        $item->save();
                    } else {
                        $inv->items()->create([
                            'description' => 'Showroom Retail Item',
                            'quantity' => 1,
                            'unit_price' => $taxable,
                            'vat_rate' => 13.00,
                            'vat_amount' => $vat,
                            'total_amount' => $gross,
                        ]);
                    }
                    $posUpdated++;
                }
                DB::commit();
                $this->info("✓ Successfully updated {$posUpdated} Showroom POS invoices with 13% VAT and taxable base.");
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Failed updating showroom invoices: " . $e->getMessage());
                return self::FAILURE;
            }
        } else {
            $this->info("[Dry Run] Would update {$posInvoices->count()} showroom invoices.");
        }

        // 2. RECONCILE ONLINE & WHATSAPP ORDERS FROM NCM SOURCE LIST
        $this->info("\n--- 2. Reconciling Website vs WhatsApp Sales Channels ---");
        $onlinePath = storage_path('app/migration/real_data_extracted/online_orders.json');
        if (!File::exists($onlinePath)) {
            $this->error("Missing extracted file: {$onlinePath}");
            return self::FAILURE;
        }

        $onlineOrders = json_decode(File::get($onlinePath), true) ?: [];
        $nwCount = 0;
        $webCount = 0;

        if (!$dryRun) {
            DB::beginTransaction();
            try {
                foreach ($onlineOrders as $idx => $row) {
                    $ordNum = sprintf('ONL-2026-%05d', $idx + 1);
                    $source = strtolower(trim((string)($row['source'] ?? '')));
                    $isWhatsapp = ($source === 'nw');

                    $targetOrderChannel = $isWhatsapp ? 'whatsapp' : 'online';
                    $targetInvoiceChannel = $isWhatsapp ? 'concierge_whatsapp' : 'web_storefront';

                    // Update order
                    $order = Order::where('order_number', $ordNum)->first();
                    if ($order) {
                        $order->channel = $targetOrderChannel;
                        $order->save();

                        // Update invoice
                        $inv = AccountingInvoice::where('reference_order_id', $order->id)->first();
                        if ($inv) {
                            $inv->sales_channel = $targetInvoiceChannel;

                            // Ensure taxable & vat populated
                            $gross = (float)$inv->total_amount;
                            if ($gross > 0 && ((float)$inv->taxable_amount <= 0 || (float)$inv->vat_amount <= 0)) {
                                $taxable = round($gross / 1.13, 2);
                                $vat = round($gross - $taxable, 2);
                                $inv->subtotal = $taxable;
                                $inv->taxable_amount = $taxable;
                                $inv->vat_amount = $vat;
                            }
                            $inv->save();
                        }

                        if ($isWhatsapp) {
                            $nwCount++;
                        } else {
                            $webCount++;
                        }
                    }
                }
                DB::commit();
                $this->info("✓ Successfully classified {$nwCount} orders as 'WhatsApp Clienteling' (source: nw).");
                $this->info("✓ Successfully classified {$webCount} orders as 'E-Commerce Webshop' (source: web/social).");
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Failed updating order channels: " . $e->getMessage());
                return self::FAILURE;
            }
        } else {
            foreach ($onlineOrders as $row) {
                $source = strtolower(trim((string)($row['source'] ?? '')));
                if ($source === 'nw') {
                    $nwCount++;
                } else {
                    $webCount++;
                }
            }
            $this->info("[Dry Run] Would classify {$nwCount} as WhatsApp and {$webCount} as Webshop.");
        }

        // 3. RECONCILE ANY REMAINING ZERO TAXABLE SALES INVOICES
        $this->info("\n--- 3. Verifying All Sales Invoices in Bikri Khata ---");
        $remainingZero = AccountingInvoice::where('type', 'sales_invoice')
            ->where('total_amount', '>', 0)
            ->where(function ($q) {
                $q->where('taxable_amount', '<=', 0)
                  ->orWhere('vat_amount', '<=', 0);
            })
            ->count();

        $this->info("Remaining Sales Invoices with 0 Taxable / VAT: {$remainingZero}");

        $channelBreakdown = AccountingInvoice::where('type', 'sales_invoice')
            ->groupBy('sales_channel')
            ->selectRaw('sales_channel, count(*) as count, sum(taxable_amount) as total_taxable, sum(vat_amount) as total_vat, sum(total_amount) as total_gross')
            ->get();

        $this->table(
            ['Sales Channel', 'Invoice Count', 'Total Taxable (Rs.)', 'Total VAT 13% (Rs.)', 'Gross Sales (Rs.)'],
            $channelBreakdown->map(function ($row) {
                return [
                    $row->sales_channel,
                    number_format((float)$row->count),
                    number_format((float)$row->total_taxable, 2),
                    number_format((float)$row->total_vat, 2),
                    number_format((float)$row->total_gross, 2),
                ];
            })->toArray()
        );

        return self::SUCCESS;
    }
}
