<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportNcmOnlineSalesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'laijau:import-ncm-sales
                            {--file= : Optional path to NCM sales CSV file}
                            {--dry-run : Simulate execution without persisting database changes}
                            {--force : Bypass confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Import authoritative NCM courier sales CSV as WhatsApp Clienteling online orders with tracking, shipments, and customer profiles';

    /**
     * Known Kathmandu Valley branches for inside-valley detection.
     */
    protected array $valleyBranches = [
        'TINKUNE', 'SATDOBATO', 'KAPAN', 'THANKOT', 'NEWROAD', 'CHABAHIL', 'KALANKI',
        'KRITIPUR', 'KIRTIPUR', 'BHAKTAPUR', 'LALITPUR', 'KATHMANDU', 'THAMEL', 'BALAJU',
        'NAYA BUSPARK', 'MAHARAJGUNJ', 'BANESHWOR', 'JORPATI', 'BUDHANILKANTHA', 'PATAN',
        'SANEPA', 'SURYABINAYAK', 'THIMI', 'LOKANTHALI', 'GOTHATAR', 'KANDAGHARI',
        'PEPSICOLA', 'CHOBHAR', 'DHOBIGHAT', 'SITAPAILA', 'SWAYAMBHU', 'BOUDHA',
        'CHHETRAPATI', 'ASAN', 'SUNDHARA', 'TRIPURESHWOR', 'KUPANDOLE', 'PULCHOWK',
        'JHAMSikhel', 'KUMARIPATI', 'LUBHU', 'IMADOL', 'TIKATHALI', 'HARISIDDHI',
    ];

    public function handle(): int
    {
        $this->info('========================================================================');
        $this->info('  LAIJAU ERP — NCM ONLINE / WHATSAPP SALES IMPORT ROUTINE');
        $this->info('========================================================================');

        $filePath = $this->option('file') ?: base_path('private_docs/Real Laijau Data/Sales Jan to Sept 2026/NCM Sales.csv');

        if (!file_exists($filePath)) {
            $this->error("NCM Sales CSV file not found at: {$filePath}");
            return self::FAILURE;
        }

        $isDryRun = (bool)$this->option('dry-run');

        if (!$isDryRun && !$this->option('force')) {
            if (!$this->confirm('Proceed with LIVE import of NCM sales as WhatsApp online orders?')) {
                $this->warn('Import cancelled by operator.');
                return self::SUCCESS;
            }
        }

        $this->line("Target File: {$filePath}");
        $this->line('Mode:        ' . ($isDryRun ? '<comment>DRY-RUN (SIMULATION)</comment>' : '<info>LIVE TRANSACTIONAL IMPORT</info>'));
        $this->newLine();

        // 1. Read and parse CSV rows
        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $header = fgetcsv($handle);
            if (!$header) {
                $this->error('CSV file header is empty.');
                fclose($handle);
                return self::FAILURE;
            }

            // Clean BOM and trim headers
            $header = array_map(function ($h) {
                return trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', (string)$h));
            }, $header);

            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) < count($header)) {
                    continue;
                }
                $row = array_combine($header, array_slice($data, 0, count($header)));
                if (!empty($row['Order ID'])) {
                    $rows[] = $row;
                }
            }
            fclose($handle);
        }

        $totalRows = count($rows);
        $this->line("Found {$totalRows} valid NCM records in CSV.");

        // 2. Sort chronologically: Created Date ASC, then Order ID ASC
        usort($rows, function ($a, $b) {
            $dateA = $a['Created Date'] ?? '';
            $dateB = $b['Created Date'] ?? '';
            if ($dateA !== $dateB) {
                return strcmp($dateA, $dateB);
            }
            return strcmp((string)$a['Order ID'], (string)$b['Order ID']);
        });

        // 3. Ensure Anchor Catalog Product exists
        $anchorProduct = null;
        if (!$isDryRun) {
            $category = Category::where('slug', 'apparel')->first()
                ?? Category::where('slug', 'footwear')->first()
                ?? Category::first();

            $anchorProduct = Product::firstOrCreate(
                ['sku' => 'LJ-WA-CATALOG'],
                [
                    'name' => 'Laijau WhatsApp Clienteling Catalog Item',
                    'slug' => 'laijau-whatsapp-clienteling-catalog-item',
                    'type' => 'simple',
                    'price' => 1500.00,
                    'quantity' => 10000,
                    'track_quantity' => false,
                    'is_active' => true,
                    'is_published' => false,
                    'description' => 'System anchor catalog product for authentic WhatsApp Clienteling courier orders.',
                ]
            );

            if ($category && !$anchorProduct->categories()->where('categories.id', $category->id)->exists()) {
                $anchorProduct->categories()->attach($category->id);
            }
        }

        // Metrics tracking
        $metrics = [
            'total_rows' => $totalRows,
            'orders_created' => 0,
            'delivered_orders' => 0,
            'cancelled_orders' => 0,
            'in_transit_orders' => 0,
            'customers_created' => 0,
            'existing_customers_linked' => 0,
            'shipments_created' => 0,
            'total_cod_npr' => 0.0,
            'total_delivery_fee_npr' => 0.0,
            'total_gross_npr' => 0.0,
        ];

        $startTime = microtime(true);

        if (!$isDryRun) {
            DB::beginTransaction();
        }

        try {
            $userCache = [];
            $orderSequence = 1;

            $bar = $this->output->createProgressBar($totalRows);
            $bar->start();

            foreach ($rows as $row) {
                $orderId = trim((string)$row['Order ID']);
                $createdDate = trim((string)$row['Created Date']);
                $deliveredDate = trim((string)($row['Delivered Date'] ?? ''));
                $sourceBranch = strtoupper(trim((string)($row['Source Branch'] ?? 'TINKUNE')));
                $destBranch = strtoupper(trim((string)($row['Destination Branch'] ?? 'KATHMANDU')));
                $receiverRaw = trim((string)($row['Receiver'] ?? 'Online Customer'));
                $phoneRaw = trim((string)($row['Receiver Phone'] ?? ''));
                $cod = (float)($row['COD Charge'] ?? 0.0);
                $fee = (float)($row['Delivery Charge'] ?? 0.0);
                $statusRaw = trim((string)($row['Status'] ?? 'Delivered'));
                $isReturn = strtolower(trim((string)($row['Vendor Return'] ?? 'false'))) === 'true';
                $packageDesc = trim((string)($row['Package Description'] ?? ''));
                $remarks = trim((string)($row['Remarks'] ?? ''));
                $weight = (float)($row['Weight'] ?? 1.0);
                if ($weight <= 0.0) {
                    $weight = 1.0;
                }
                $refId = trim((string)($row['Reference ID'] ?? ''));
                $createdBy = trim((string)($row['Created By'] ?? 'Self'));

                // Metrics
                $metrics['total_cod_npr'] += $cod;
                $metrics['total_delivery_fee_npr'] += $fee;
                $metrics['total_gross_npr'] += ($cod + $fee);

                // Customer Name Split
                $nameParts = preg_split('/\s+/', $receiverRaw, 2);
                $firstName = !empty($nameParts[0]) ? $nameParts[0] : 'Online';
                $lastName = !empty($nameParts[1]) ? $nameParts[1] : 'Customer';

                // Phone Normalization
                $normalizedPhone = $this->normalizePhone($phoneRaw);

                // Valley detection
                $isValley = in_array($destBranch, $this->valleyBranches, true);
                $shippingMethod = $isValley ? 'Inside Kathmandu Valley Standard' : 'Outside Valley Courier (NCM / Pathao)';

                // Timestamps
                $createdAt = Carbon::parse($createdDate)->setTime(10, 0, 0);
                $actualDeliveredAt = !empty($deliveredDate) ? Carbon::parse($deliveredDate)->setTime(16, 0, 0) : null;

                // Status Logic
                if ($isReturn) {
                    $orderStatus = Order::STATUS_CANCELLED;
                    $paymentStatus = Order::PAYMENT_STATUS_UNPAID;
                    $cancellationReason = 'Vendor Return (NCM Delivery Cancelled / Returned)';
                    $cancelledAt = $actualDeliveredAt ?: $createdAt;
                    $paymentMethod = 'cash_on_delivery';
                    $metrics['cancelled_orders']++;
                } elseif ($statusRaw === 'Delivered') {
                    $orderStatus = Order::STATUS_DELIVERED;
                    $paymentStatus = Order::PAYMENT_STATUS_PAID;
                    $cancellationReason = null;
                    $cancelledAt = null;
                    $paymentMethod = ($cod > 0) ? 'cash_on_delivery' : 'fonepay';
                    $metrics['delivered_orders']++;
                } else {
                    $orderStatus = Order::STATUS_IN_TRANSIT;
                    $paymentStatus = Order::PAYMENT_STATUS_UNPAID;
                    $cancellationReason = null;
                    $cancelledAt = null;
                    $paymentMethod = 'cash_on_delivery';
                    $metrics['in_transit_orders']++;
                }

                $orderNumber = sprintf('ONL-2026-%05d', $orderSequence++);
                $customerEmail = !empty($normalizedPhone)
                    ? "cust_{$normalizedPhone}@laijau.com"
                    : "cust_ncm_{$orderId}@laijau.com";

                if (!$isDryRun) {
                    // Resolve Customer User
                    $userId = null;
                    if (!empty($normalizedPhone)) {
                        if (isset($userCache[$normalizedPhone])) {
                            $userId = $userCache[$normalizedPhone];
                            $metrics['existing_customers_linked']++;
                        } else {
                            $existingUser = User::where('phone', $normalizedPhone)->first();
                            if ($existingUser) {
                                $userId = $existingUser->id;
                                $userCache[$normalizedPhone] = $userId;
                                $metrics['existing_customers_linked']++;
                            } else {
                                $newUser = User::create([
                                    'name' => $receiverRaw,
                                    'email' => $customerEmail,
                                    'phone' => $normalizedPhone,
                                    'role' => 'customer',
                                    'password' => Hash::make(Str::random(32)),
                                    'country' => 'NP',
                                    'city' => $destBranch,
                                    'created_at' => $createdAt,
                                    'updated_at' => $actualDeliveredAt ?: $createdAt,
                                ]);
                                $userId = $newUser->id;
                                $userCache[$normalizedPhone] = $userId;
                                $metrics['customers_created']++;
                            }
                        }
                    }

                    // Create Order
                    $order = Order::create([
                        'order_number' => $orderNumber,
                        'channel' => Order::CHANNEL_WHATSAPP,
                        'user_id' => $userId,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $customerEmail,
                        'phone' => $normalizedPhone ?: $phoneRaw,
                        'shipping_address' => "{$destBranch}, Nepal",
                        'shipping_city' => $destBranch,
                        'shipping_country' => 'NP',
                        'is_inside_valley' => $isValley,
                        'shipping_method' => $shippingMethod,
                        'subtotal' => $cod,
                        'shipping_fee' => $fee,
                        'total_amount' => $cod + $fee,
                        'currency' => 'NPR',
                        'status' => $orderStatus,
                        'payment_status' => $paymentStatus,
                        'payment_method' => $paymentMethod,
                        'tracking_number' => $orderId,
                        'courier_order_id' => $orderId,
                        'courier_status' => $statusRaw,
                        'carrier' => 'Nepal Can Move (NCM)',
                        'courier_name' => 'Nepal Can Move (NCM)',
                        'delivered_at' => ($orderStatus === Order::STATUS_DELIVERED) ? ($actualDeliveredAt ?: $createdAt) : null,
                        'actual_delivery_date' => $deliveredDate ?: null,
                        'cancelled_at' => $cancelledAt,
                        'cancellation_reason' => $cancellationReason,
                        'internal_notes' => "NCM Order ID: {$orderId} | Weight: {$weight}kg | Ref: {$refId} | Route: {$sourceBranch} -> {$destBranch} | Package: {$packageDesc}",
                        'delivery_notes' => $remarks ?: null,
                        'created_at' => $createdAt,
                        'updated_at' => $actualDeliveredAt ?: $createdAt,
                    ]);

                    // Create Order Item
                    $itemName = !empty($packageDesc) ? $packageDesc : 'WhatsApp Clienteling Item';
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $anchorProduct->id,
                        'product_name' => $itemName,
                        'sku' => 'WA-NCM-' . substr(md5($orderId), 0, 8),
                        'quantity' => 1,
                        'unit_price' => $cod,
                        'created_at' => $createdAt,
                        'updated_at' => $actualDeliveredAt ?: $createdAt,
                    ]);

                    // Create Shipment
                    Shipment::create([
                        'order_id' => $order->id,
                        'provider' => Shipment::PROVIDER_NCM,
                        'external_tracking_number' => $orderId,
                        'external_reference' => $refId ?: null,
                        'source' => 'ncm_csv_import',
                        'source_file' => basename($filePath),
                        'source_created_at' => $createdAt,
                        'status' => $statusRaw,
                        'normalized_status' => $isReturn ? Shipment::STATUS_RETURNED_TO_VENDOR : ($statusRaw === 'Delivered' ? Shipment::STATUS_DELIVERED : Shipment::STATUS_OUT_FOR_DELIVERY),
                        'source_branch' => $sourceBranch,
                        'destination_branch' => $destBranch,
                        'receiver_name' => $receiverRaw,
                        'receiver_phone' => $phoneRaw,
                        'normalized_receiver_phone' => $normalizedPhone,
                        'cod_amount' => $cod,
                        'delivery_charge' => $fee,
                        'package_description' => $packageDesc ?: null,
                        'remarks' => $remarks ?: null,
                        'weight' => $weight,
                        'delivered_at' => $actualDeliveredAt,
                        'vendor_return' => $isReturn,
                        'created_by_source' => $createdBy,
                        'match_status' => Shipment::MATCH_STATUS_MATCHED,
                        'match_method' => 'direct_order_id_import',
                        'match_confidence' => 100,
                        'match_reason' => "Authentic NCM tracking #{$orderId} imported directly as WhatsApp Order {$orderNumber}",
                        'matched_at' => now(),
                        'created_at' => $createdAt,
                        'updated_at' => $actualDeliveredAt ?: $createdAt,
                    ]);

                    $metrics['orders_created']++;
                    $metrics['shipments_created']++;
                } else {
                    $metrics['orders_created']++;
                    $metrics['shipments_created']++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            if (!$isDryRun) {
                DB::commit();
            }
        } catch (\Throwable $e) {
            if (!$isDryRun) {
                DB::rollBack();
            }
            $this->error('IMPORT FAILED: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("Import routine completed in {$duration}s!");
        $this->newLine();

        $this->table(
            ['Operational Metric', 'Count / Value'],
            [
                ['CSV Records Processed', number_format($metrics['total_rows'])],
                ['Orders Created', number_format($metrics['orders_created'])],
                ['Sales Channel', 'WhatsApp Clienteling (Order::CHANNEL_WHATSAPP)'],
                ['Delivered Orders', number_format($metrics['delivered_orders'])],
                ['Vendor Returns / Cancelled', number_format($metrics['cancelled_orders'])],
                ['In Transit / Out for Delivery', number_format($metrics['in_transit_orders'])],
                ['Shipments Created & 100% Matched', number_format($metrics['shipments_created'])],
                ['Unique Customers Created', number_format($metrics['customers_created'])],
                ['Existing Customers Linked', number_format($metrics['existing_customers_linked'])],
                ['Total COD Collected (NPR)', 'NPR ' . number_format($metrics['total_cod_npr'], 2)],
                ['Total Delivery Fees (NPR)', 'NPR ' . number_format($metrics['total_delivery_fee_npr'], 2)],
                ['Total Gross Online Revenue', 'NPR ' . number_format($metrics['total_gross_npr'], 2)],
            ]
        );

        $this->newLine();
        $this->info('✓ SUCCESS: NCM sales dataset imported cleanly as WhatsApp online orders!');
        $this->info('========================================================================');

        return self::SUCCESS;
    }

    /**
     * Normalize Nepal phone numbers (extract 10-digit mobile).
     */
    protected function normalizePhone(?string $phone): string
    {
        if (!$phone) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '977') && strlen($digits) === 13) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        return (strlen($digits) === 10) ? $digits : '';
    }
}
