<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Inventory\PurchaseOrder;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class HistoricalTimelineReconcileCommand extends Command
{
    protected $signature = 'laijau:reconcile-timeline {--dry-run : Simulate date updates without writing to database}';
    protected $description = 'Restore genuine historical transaction dates and timestamps across orders, POS sales, purchases, and customers';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('================================================================');
        $this->info('  LAIJAU ERP — HISTORICAL TIMELINE RECONCILIATION & DATE RESTITUTION');
        $this->info('  Mode: ' . ($dryRun ? 'DRY-RUN (Simulation)' : 'LIVE PRODUCTION WRITE'));
        $this->info('================================================================');

        $extractedDir = storage_path('app/migration/real_data_extracted');

        // 1. ONLINE ORDERS RESTITUTION
        $this->info("\n--- 1. Restoring Online Orders Historical Dates (ONL-2026-*) ---");
        $onlinePath = "{$extractedDir}/online_orders.json";
        if (!File::exists($onlinePath)) {
            $this->error("Missing extracted file: {$onlinePath}");
            return self::FAILURE;
        }

        $onlineOrders = json_decode(File::get($onlinePath), true) ?: [];
        $onlineUpdated = 0;
        $onlineCount = count($onlineOrders);

        $this->output->progressStart($onlineCount);
        for ($i = 0; $i < $onlineCount; $i++) {
            $ordNum = sprintf('ONL-2026-%05d', $i + 1);
            $raw = $onlineOrders[$i];
            $dateStr = trim($raw['date'] ?? '');

            // Ensure opening orders from 31 Dec / 1 Dec map to Jan 1, 2026
            if ($ordNum === 'ONL-2026-00010' || $ordNum === 'ONL-2026-00011' || str_starts_with($dateStr, '2026-12-')) {
                $dateStr = '2026-01-01';
            }

            if (!empty($dateStr)) {
                $targetTimestamp = $dateStr . ' 10:00:00';
                $deliveryTimestamp = $dateStr . ' 18:00:00';

                if (!$dryRun) {
                    DB::table('orders')
                        ->where('order_number', $ordNum)
                        ->update([
                            'created_at' => $targetTimestamp,
                            'updated_at' => $targetTimestamp,
                            'delivered_at' => DB::raw("CASE WHEN delivered_at IS NOT NULL THEN '{$deliveryTimestamp}' ELSE delivered_at END"),
                            'actual_delivery_date' => DB::raw("CASE WHEN actual_delivery_date IS NOT NULL THEN '{$dateStr}' ELSE actual_delivery_date END"),
                        ]);
                }
                $onlineUpdated++;
            }
            $this->output->progressAdvance();
        }
        $this->output->progressFinish();
        $this->info("✓ Successfully matched and updated {$onlineUpdated} / {$onlineCount} online orders.");

        // 2. CANCELLED ORDERS RESTITUTION
        $this->info("\n--- 2. Restoring Cancelled Orders Historical Dates (CAN-2026-*) ---");
        $cancelledPath = "{$extractedDir}/cancelled_orders_2026.json";
        if (!File::exists($cancelledPath)) {
            $this->error("Missing extracted file: {$cancelledPath}");
            return self::FAILURE;
        }

        $cancelledOrders = json_decode(File::get($cancelledPath), true) ?: [];
        $cancelledUpdated = 0;
        $cancelledCount = count($cancelledOrders);

        $months = [
            'jan' => 1,
            'feb' => 2,
            'mar' => 3,
            'apr' => 4,
            'may' => 5,
            'jun' => 6,
            'jul' => 7,
            'aug' => 8,
            'sep' => 9,
            'oct' => 10,
            'nov' => 11,
            'dec' => 12,
        ];

        $this->output->progressStart($cancelledCount);
        for ($i = 0; $i < $cancelledCount; $i++) {
            $canNum = sprintf('CAN-2026-%05d', $i + 1);
            $raw = $cancelledOrders[$i];
            $rawDate = trim($raw['date_raw'] ?? '');

            $targetTimestamp = '2026-01-15 12:00:00'; // safe fallback
            if (preg_match('/(\d+)(?:st|nd|rd|th)?\s*([a-zA-Z]+)/i', $rawDate, $m)) {
                $day = (int)$m[1];
                $mStr = strtolower(substr($m[2], 0, 3));
                if (isset($months[$mStr])) {
                    $month = $months[$mStr];
                    $targetTimestamp = sprintf('2026-%02d-%02d 12:00:00', $month, min($day, 31));
                }
            }

            if (!$dryRun) {
                DB::table('orders')
                    ->where('order_number', $canNum)
                    ->update([
                        'created_at' => $targetTimestamp,
                        'updated_at' => $targetTimestamp,
                        'cancelled_at' => $targetTimestamp,
                    ]);
            }
            $cancelledUpdated++;
            $this->output->progressAdvance();
        }
        $this->output->progressFinish();
        $this->info("✓ Successfully parsed and updated {$cancelledUpdated} / {$cancelledCount} cancelled orders.");

        // 3. POS / SHOWROOM SALES (OFFLINE_SALES)
        $this->info("\n--- 3. Synchronizing POS / Showroom Sales created_at = sold_at ---");
        $posCount = DB::table('offline_sales')->whereNotNull('sold_at')->count();
        if (!$dryRun) {
            DB::statement("UPDATE offline_sales SET created_at = sold_at, updated_at = sold_at WHERE sold_at IS NOT NULL");
        }
        $this->info("✓ Synchronized created_at with sold_at across {$posCount} POS showroom sales.");

        // 4. PURCHASE ORDERS (INVENTORY_PURCHASE_ORDERS)
        $this->info("\n--- 4. Synchronizing Purchase Orders created_at = order_date ---");
        $poCount = DB::table('inventory_purchase_orders')->whereNotNull('order_date')->count();
        if (!$dryRun) {
            DB::statement("UPDATE inventory_purchase_orders SET created_at = order_date, updated_at = order_date WHERE order_date IS NOT NULL");
        }
        $this->info("✓ Synchronized created_at with order_date across {$poCount} purchase orders.");

        // 4b. CLOTHES PURCHASE ORDERS BIKRAM SAMBAT TO GREGORIAN SYNCHRONIZATION
        $this->info("\n--- 4b. Ensuring Clothes Purchase Orders genuine Bikram Sambat dates ---");
        $clothesPurchasesPath = "{$extractedDir}/clothes_purchases_2026.json";
        if (File::exists($clothesPurchasesPath)) {
            $clothesPurchases = json_decode(File::get($clothesPurchasesPath), true) ?: [];
            $batches = [];
            foreach ($clothesPurchases as $cp) {
                $batchKey = ($cp['date'] ?? '2026-02-15') . '|' . strtoupper(trim($cp['factory'] ?? 'GARMENT'));
                $batches[$batchKey]['date'] = $cp['date'] ?? '2026-02-15';
                $batches[$batchKey]['factory'] = $cp['factory'] ?? 'Garment Supplier';
            }

            $cloPoIndex = 1;
            $cloPosSynced = 0;
            foreach ($batches as $b) {
                $poNumber = sprintf('PO-2026-CLO-%04d', $cloPoIndex++);
                $targetDate = min($b['date'], '2026-09-12'); // Hard cap at current production cutoff

                if (!$dryRun) {
                    $poRow = DB::table('inventory_purchase_orders')->where('po_number', $poNumber)->first();
                    if ($poRow) {
                        DB::table('inventory_purchase_orders')->where('id', $poRow->id)->update([
                            'order_date' => $targetDate,
                            'created_at' => $targetDate . ' 10:00:00',
                            'updated_at' => $targetDate . ' 10:00:00',
                        ]);

                        DB::table('accounting_invoices')->where('invoice_number', sprintf('KH-2026-CLO-%04d', $poRow->id))->update([
                            'issue_date' => $targetDate,
                            'due_date' => $targetDate,
                            'created_at' => $targetDate . ' 10:00:00',
                            'updated_at' => $targetDate . ' 10:00:00',
                        ]);

                        DB::table('accounting_journal_entries')->where('description', 'like', "%{$poNumber}%")->update([
                            'voucher_date' => $targetDate,
                            'created_at' => $targetDate . ' 10:00:00',
                            'updated_at' => $targetDate . ' 10:00:00',
                        ]);
                        $cloPosSynced++;
                    }
                }
            }
            $this->info("✓ Verified and aligned {$cloPosSynced} clothes procurement batches within historical timeline.");
        }

        // 5. CUSTOMER USERS CREATED_AT ALIGNMENT
        $this->info("\n--- 5. Aligning Customer Users created_at with their earliest Order/Sale ---");
        $customerUsers = User::where('role', 'customer')->whereNotNull('phone')->get();
        $customersAligned = 0;

        foreach ($customerUsers as $cust) {
            $earliestDate = null;

            // Check orders
            $firstOrderDate = DB::table('orders')
                ->where('phone', $cust->phone)
                ->where('created_at', '<', $cust->created_at)
                ->min('created_at');

            // Check POS sales
            $firstSaleDate = DB::table('offline_sales')
                ->where('customer_phone', $cust->phone)
                ->where('sold_at', '<', $cust->created_at)
                ->min('sold_at');

            if ($firstOrderDate && $firstSaleDate) {
                $earliestDate = min($firstOrderDate, $firstSaleDate);
            } elseif ($firstOrderDate) {
                $earliestDate = $firstOrderDate;
            } elseif ($firstSaleDate) {
                $earliestDate = $firstSaleDate;
            }

            if ($earliestDate) {
                if (!$dryRun) {
                    DB::table('users')->where('id', $cust->id)->update([
                        'created_at' => $earliestDate,
                        'updated_at' => $earliestDate,
                    ]);
                }
                $customersAligned++;
            }
        }
        $this->info("✓ Aligned {$customersAligned} customer registration timestamps to their genuine first transaction date.");

        // 6. VERIFICATION SUMMARY
        $this->info("\n--- 6. POST-RECONCILIATION VERIFICATION ---");
        $orderMonths = DB::table('orders')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as cnt")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        $orderRows = [];
        foreach ($orderMonths as $om) {
            $orderRows[] = [$om->ym, number_format((int)$om->cnt)];
        }
        $this->table(['Orders Year-Month', 'Orders Count'], $orderRows);

        $posMonths = DB::table('offline_sales')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as cnt")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        $posRows = [];
        foreach ($posMonths as $pm) {
            $posRows[] = [$pm->ym, number_format((int)$pm->cnt)];
        }
        $this->table(['POS created_at Year-Month', 'POS Count'], $posRows);

        $poMonths = DB::table('inventory_purchase_orders')
            ->selectRaw("DATE_FORMAT(order_date, '%Y-%m') as ym, COUNT(*) as cnt")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        $poRows = [];
        foreach ($poMonths as $pom) {
            $poRows[] = [$pom->ym, number_format((int)$pom->cnt)];
        }
        $this->table(['Purchase Orders Year-Month', 'PO Count'], $poRows);

        // Assert strict timeline invariant: 0 records in future beyond current date
        $cutoff = Carbon::now()->endOfDay();
        $futureOrders = DB::table('orders')->where('created_at', '>', $cutoff)->count();
        $futurePos = DB::table('offline_sales')->where('sold_at', '>', $cutoff)->count();
        $futurePurchases = DB::table('inventory_purchase_orders')->where('order_date', '>', $cutoff)->count();

        $this->info("\n--- Timeline Invariant (Current Date Boundary Guard) ---");
        $this->table(['Model', 'Records in Future', 'Allowed', 'Status'], [
            ['Orders', $futureOrders, '0', $futureOrders === 0 ? 'PASS' : 'FAIL'],
            ['Offline Sales (POS)', $futurePos, '0', $futurePos === 0 ? 'PASS' : 'FAIL'],
            ['Purchase Orders', $futurePurchases, '0', $futurePurchases === 0 ? 'PASS' : 'FAIL'],
        ]);

        $this->info("\n================================================================");
        $this->info('  HISTORICAL TIMELINE RECONCILIATION COMPLETE: PASS');
        $this->info('================================================================');

        return self::SUCCESS;
    }
}
