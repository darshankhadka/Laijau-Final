<?php

declare(strict_types=1);

namespace Tests\Feature\Operational;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\StockLevel;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Accounting\AccountingService;
use App\Services\Inventory\InventoryService;
use App\Services\Operational\BackupService;
use Database\Seeders\ModuleSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupRestoreDrillTest extends TestCase
{
    use RefreshDatabase;

    protected BackupService $backupService;
    protected string $tempRestoreDb;
    protected array $createdArchives = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSettingsSeeder::class);
        $this->backupService = app(BackupService::class);
        $this->tempRestoreDb = database_path('test_restore_drill.sqlite');

        if (File::exists($this->tempRestoreDb)) {
            File::delete($this->tempRestoreDb);
        }
        File::put($this->tempRestoreDb, '');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tempRestoreDb)) {
            File::delete($this->tempRestoreDb);
        }

        foreach ($this->createdArchives as $archive) {
            if (File::exists($archive)) {
                File::delete($archive);
            }
        }

        parent::tearDown();
    }

    /**
     * Complete Disaster Recovery Drill:
     * Export running ERP state, restore into an isolated database, and verify record-level integrity.
     */
    public function test_enterprise_backup_and_disaster_recovery_restore_drill(): void
    {
        // 1. Seed realistic ERP data across Commerce, Inventory, and Accounting
        $accounting = app(AccountingService::class);
        $accounting->ensureDefaultChartOfAccounts();

        $product = Product::create([
            'name' => 'Royal Himalayan Pashmina Stole',
            'slug' => 'royal-himalayan-pashmina-stole',
            'sku' => 'RHP-DRILL-01',
            'price' => 1500.00,
            'price_npr' => 1500.00,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'RHP-DRILL-01-GOLD',
            'color' => 'Gold Leaf',
            'size' => 'Standard',
            'price_npr' => 1500.00,
            'stock_quantity' => 12,
            'is_active' => true,
        ]);

        $invService = app(InventoryService::class);
        $wh = $invService->getShowroomWarehouse();
        StockLevel::create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity_on_hand' => 12,
            'unit_cost_npr' => 650.00,
        ]);

        $order = Order::create([
            'first_name' => 'Bikash',
            'last_name' => 'Shrestha',
            'email' => 'bikash.shrestha@test.np',
            'shipping_address' => 'New Road 14',
            'shipping_country' => 'NP',
            'subtotal' => 1500.00,
            'total_amount' => 1500.00,
            'currency' => 'NPR',
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => 'paid',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => 1500.00,
        ]);

        $bank = Account::where('account_number', '1120')->first() ?: Account::where('account_number', '2410')->firstOrFail();
        $sales = Account::where('account_number', '4120')->first() ?: Account::where('account_number', '1010')->firstOrFail();

        $voucher = $accounting->postJournalEntry([
            'voucher_date' => date('Y-m-d'),
            'description' => 'Drill Test Voucher Sale',
            'currency' => 'NPR',
        ], [
            ['account_id' => $bank->id, 'debit' => 1500.00, 'credit' => 0.00],
            ['account_id' => $sales->id, 'debit' => 0.00, 'credit' => 1500.00],
        ]);

        // Baseline Metrics
        $expectedProductsCount = Product::count();
        $expectedOrdersCount = Order::count();
        $expectedVouchersCount = JournalEntry::count();
        $expectedStock = StockLevel::where('product_id', $product->id)->sum('quantity_on_hand');

        // 2. Generate enterprise backup
        $backupResult = $this->backupService->createBackup('all', 'drill_test_archive.zip');
        $this->assertEquals('success', $backupResult['status']);
        $this->assertFileExists($backupResult['archive_path']);
        $this->createdArchives[] = $backupResult['archive_path'];

        // 3. Configure isolated target database connection
        config([
            'database.connections.drill_target' => [
                'driver' => 'sqlite',
                'database' => $this->tempRestoreDb,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        // Migrate isolated database schema
        Artisan::call('migrate', [
            '--database' => 'drill_target',
            '--path' => 'database/migrations',
            '--force' => true,
        ]);

        // 4. Restore backup into the isolated target database
        $restoreResult = $this->backupService->restoreBackup(
            $backupResult['archive_path'],
            targetConnection: 'drill_target',
            dryRun: false
        );

        $this->assertEquals('success', $restoreResult['status']);
        $this->assertGreaterThan(0, $restoreResult['total_rows_restored']);

        // 5. Verify ERP Record-Level Integrity on Isolated Target Database
        $targetConn = DB::connection('drill_target');

        // Verify Products
        $restoredProductsCount = $targetConn->table('products')->count();
        $this->assertEquals($expectedProductsCount, $restoredProductsCount);
        $restoredProduct = $targetConn->table('products')->where('sku', 'RHP-DRILL-01')->first();
        $this->assertNotNull($restoredProduct);
        $this->assertEquals('Royal Himalayan Pashmina Stole', $restoredProduct->name);

        // Verify Orders & Line Items
        $restoredOrdersCount = $targetConn->table('orders')->count();
        $this->assertEquals($expectedOrdersCount, $restoredOrdersCount);
        $restoredOrder = $targetConn->table('orders')->where('order_number', $order->order_number)->first();
        $this->assertNotNull($restoredOrder);
        $this->assertEquals('paid', $restoredOrder->payment_status);

        // Verify Stock Levels
        $restoredStock = $targetConn->table('inventory_stock_levels')
            ->where('product_id', $product->id)
            ->sum('quantity_on_hand');
        $this->assertEquals($expectedStock, $restoredStock);

        // Verify Double-Entry Balanced Journals
        $restoredVouchersCount = $targetConn->table('accounting_journal_entries')->count();
        $this->assertEquals($expectedVouchersCount, $restoredVouchersCount);
        $restoredVoucher = $targetConn->table('accounting_journal_entries')
            ->where('entry_number', $voucher->entry_number)
            ->first();
        $this->assertNotNull($restoredVoucher);
        $this->assertEquals(1, $restoredVoucher->is_balanced);
        $this->assertEquals(1500.00, (float)$restoredVoucher->total_debit);
        $this->assertEquals(1500.00, (float)$restoredVoucher->total_credit);
    }
}
