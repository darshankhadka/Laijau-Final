<?php

namespace Tests\Feature;

use App\Filament\Pages\BarcodeLabelPage;
use App\Filament\Pages\FulfillmentHubPage;
use App\Filament\Pages\PaymentReconciliationPage;
use App\Filament\Pages\QuickStockEntryPage;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Inventory\InventoryService;
use App\Services\Logistics\LogisticsService;
use App\Services\OfflineSaleService;
use App\Services\ProductSkuService;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminV2TransformationTest extends TestCase
{
    protected ProductSkuService $skuService;
    protected InventoryService $inventoryService;
    protected LogisticsService $logisticsService;
    protected AccountingService $accountingService;
    protected OfflineSaleService $posService;
    protected User $admin;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skuService = app(ProductSkuService::class);
        $this->inventoryService = app(InventoryService::class);
        $this->logisticsService = app(LogisticsService::class);
        $this->accountingService = app(AccountingService::class);
        $this->posService = app(OfflineSaleService::class);

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            ['name' => 'Laijau Admin', 'password' => Hash::make('secret'), 'role' => 'admin']
        );

        $this->warehouse = $this->inventoryService->getDefaultWarehouse();
        $this->accountingService->ensureDefaultChartOfAccounts();
    }

    /**
     * TEST 1: Product Architecture & EAN-13 Barcode System with LJ- prefix.
     */
    public function test_product_architecture_and_ean13_barcode_system(): void
    {
        $product = Product::create([
            'name' => 'Nepal Artisanal Cashmere Blazer',
            'slug' => 'nepal-cashmere-blazer-' . uniqid(),
            'price' => 12500,
            'is_active' => true,
        ]);

        $sku = $this->skuService->generateForProduct($product);
        $this->assertStringStartsWith('LJ-', $sku);

        $barcode = $this->skuService->generateBarcodeForProduct($product);
        $this->assertEquals(13, strlen($barcode));
        $this->assertStringStartsWith('200', $barcode);

        $product->update(['sku' => $sku, 'barcode' => $barcode]);

        // Variant generation
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'size' => '42',
            'color' => 'Royal Navy',
            'price' => 12500,
            'is_active' => true,
        ]);

        $variantSku = $this->skuService->generateForVariant($variant);
        $this->assertStringStartsWith('LJ-', $variantSku);

        $variantBarcode = $this->skuService->generateBarcodeForVariant($variant);
        $this->assertEquals(13, strlen($variantBarcode));
        $this->assertStringStartsWith('200', $variantBarcode);

        $variant->update(['sku' => $variantSku, 'barcode' => $variantBarcode]);

        // Lookup test
        $lookupResult = $this->skuService->lookupBarcode($variantBarcode);
        $this->assertNotNull($lookupResult);
        $this->assertEquals('variant', $lookupResult['type']);
        $this->assertEquals($variant->id, $lookupResult['variant']->id);

        // Vector SVG barcode generation
        $svg = $this->skuService->generateBarcodeSvg($variantBarcode, 70, 2);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    /**
     * TEST 2: Barcode Label Printing Page rendering.
     */
    public function test_barcode_label_printing_page_renders_with_thermal_and_sheet_modes(): void
    {
        $this->actingAs($this->admin, 'admin');

        $component = Livewire::test(BarcodeLabelPage::class)
            ->assertSuccessful()
            ->set('labelFormat', 'thermal')
            ->assertSet('labelFormat', 'thermal')
            ->set('labelFormat', 'sheet')
            ->assertSet('labelFormat', 'sheet');

        $this->assertNotNull($component);
    }

    /**
     * TEST 3: Quick Stock Entry - Scanner Mode (Atomic stock increment via barcode).
     */
    public function test_quick_stock_entry_scanner_mode(): void
    {
        $this->actingAs($this->admin, 'admin');

        $dynamicBarcode = '200' . str_pad((string)rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
        $product = Product::create([
            'name' => 'Speed Runner Sneaker',
            'slug' => 'speed-runner-' . uniqid(),
            'sku' => 'LJ-SHO-RUN-' . uniqid(),
            'barcode' => $dynamicBarcode,
            'price' => 6500,
            'is_active' => true,
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        $initialStock = StockLevel::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'unit_cost_npr' => 2500,
        ]);

        Livewire::test(QuickStockEntryPage::class)
            ->set('selectedWarehouseId', $this->warehouse->id)
            ->set('scanInput', $dynamicBarcode)
            ->call('handleScan')
            ->assertSet('scannedItem.product.id', $product->id)
            ->set('quantityToAdd', 5)
            ->call('commitScan');

        $initialStock->refresh();
        $this->assertEquals(15, $initialStock->quantity_on_hand, 'Stock must increment atomically from 10 to 15');

        $movement = StockMovement::where('product_id', $product->id)->latest()->first();
        $this->assertNotNull($movement);
        $this->assertEquals(5, $movement->quantity);
    }

    /**
     * TEST 4: Centralized Logistics Engine - Intelligent Courier Selection.
     */
    public function test_logistics_engine_recommends_courier_based_on_valley_geography(): void
    {
        // Kathmandu Valley Order -> Should route to Pathao
        $valleyOrder = new Order([
            'shipping_city' => 'Kathmandu',
            'district' => 'Kathmandu',
            'is_inside_valley' => true,
        ]);
        $this->assertEquals('pathao', $this->logisticsService->recommendCourier($valleyOrder));

        // Lalitpur Valley Order -> Should route to Pathao
        $lalitpurOrder = new Order([
            'shipping_city' => 'Patan',
            'district' => 'Lalitpur',
            'is_inside_valley' => true,
        ]);
        $this->assertEquals('pathao', $this->logisticsService->recommendCourier($lalitpurOrder));

        // Outside Valley Order (Pokhara / Kaski) -> Should route to NCM
        $outsideOrder = new Order([
            'shipping_city' => 'Pokhara',
            'district' => 'Kaski',
            'is_inside_valley' => false,
        ]);
        $this->assertEquals('ncm', $this->logisticsService->recommendCourier($outsideOrder));
    }

    /**
     * TEST 5: Fulfillment Hub - Interactive Hub & COD Remittance Settlement.
     */
    public function test_fulfillment_hub_page_and_cod_settlement(): void
    {
        $this->actingAs($this->admin, 'admin');

        $order = Order::create([
            'order_number' => 'ORD-TEST-COD-' . uniqid(),
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'email' => 'aarav@example.com',
            'phone' => '9841234567',
            'shipping_address' => 'New Road',
            'shipping_city' => 'Kathmandu',
            'subtotal' => 4500,
            'total_amount' => 4500,
            'payment_method' => 'cod',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'status' => Order::STATUS_PROCESSING,
        ]);

        Livewire::test(FulfillmentHubPage::class)
            ->assertSuccessful()
            ->set('activeTab', 'cod_settlements')
            ->assertSet('activeTab', 'cod_settlements');

        // Settle COD Order via Logistics Service
        $settledResult = $this->logisticsService->settleCodOrder($order, [
            'settled_amount' => 4500,
            'settlement_reference' => 'NCM-SETTLE-8899',
            'notes' => 'Received via NCM remittance cheque',
        ], $this->admin);

        $this->assertEquals(Order::PAYMENT_STATUS_PAID, $settledResult['order']->payment_status);

        // Verify COD Journal Entry generated
        $journal = JournalEntry::where('reference_type', 'cod_settlement')
            ->where('reference_id', $order->id)
            ->first();

        $this->assertNotNull($journal, 'COD settlement must generate a journal entry');
        $this->assertTrue((bool)$journal->is_balanced, 'COD settlement entry must be balanced');
    }

    /**
     * TEST 6: Nepal Accounting & Omnichannel Payment Reconciliation (eSewa & Bank Deposit).
     */
    public function test_nepal_accounting_payment_reconciliation(): void
    {
        $this->actingAs($this->admin, 'admin');

        Livewire::test(PaymentReconciliationPage::class)
            ->assertSuccessful()
            ->set('activeTab', 'esewa')
            ->assertSet('activeTab', 'esewa');

        // Test eSewa Settlement Reconciliation
        $journalEsewa = $this->accountingService->reconcileEsewaSettlement(
            netAmount: 49250.00,
            commissionFee: 750.00,
            reference: 'ESEWA-BATCH-202609'
        );

        $this->assertInstanceOf(JournalEntry::class, $journalEsewa);
        $this->assertTrue((bool)$journalEsewa->is_balanced);
        $this->assertEquals(50000.00, (float)$journalEsewa->total_debit);
        $this->assertEquals(50000.00, (float)$journalEsewa->total_credit);

        // Test Cash Drawer POS Deposit Reconciliation
        $journalCash = $this->accountingService->reconcileCashDeposit(
            amount: 25000.00,
            depositSlipRef: 'NABIL-DEP-7744'
        );

        $this->assertInstanceOf(JournalEntry::class, $journalCash);
        $this->assertTrue((bool)$journalCash->is_balanced);
        $this->assertEquals(25000.00, (float)$journalCash->total_debit);
    }

    /**
     * TEST 7: POS Sale deducts inventory and triggers balanced double-entry accounting.
     */
    public function test_pos_sale_deducts_inventory_and_posts_accounting(): void
    {
        $showroomWh = $this->inventoryService->getShowroomWarehouse();

        $product = Product::create([
            'name' => 'Kathmandu Silk Kurti',
            'slug' => 'kathmandu-silk-kurti-' . uniqid(),
            'sku' => 'LJ-APP-KURTI-' . uniqid(),
            'price' => 3500,
            'is_active' => true,
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        $stock = StockLevel::create([
            'warehouse_id' => $showroomWh->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'unit_cost_npr' => 1500,
        ]);

        $sale = $this->posService->createSale([
            'customer_name' => 'Showroom Walk-in',
            'payment_method' => 'cash',
            'sales_channel' => 'showroom_pos',
            'currency' => 'NPR',
            'discount_amount' => 0.00,
        ], [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 3500,
            ]
        ], $this->admin);

        $this->assertInstanceOf(OfflineSale::class, $sale);
        $this->assertEquals(7000, (float)$sale->total_amount);

        // Verify showroom stock decremented from 10 to 8
        $stock->refresh();
        $this->assertEquals(8, $stock->quantity_on_hand);

        // Verify balanced journal entry
        $journal = JournalEntry::where('reference_type', 'offline_sale')
            ->where('reference_id', $sale->id)
            ->first();

        $this->assertNotNull($journal);
        $this->assertTrue((bool)$journal->is_balanced);
    }

    /**
     * TEST 8: Global Admin Search configured on Product, Order, and Customer.
     */
    public function test_global_search_attributes_configured_on_resources(): void
    {
        $productSearchAttrs = \App\Filament\Resources\ProductResource::getGloballySearchableAttributes();
        $this->assertContains('name', $productSearchAttrs);
        $this->assertContains('sku', $productSearchAttrs);
        $this->assertContains('barcode', $productSearchAttrs);

        $orderSearchAttrs = \App\Filament\Resources\OrderResource::getGloballySearchableAttributes();
        $this->assertContains('order_number', $orderSearchAttrs);
        $this->assertContains('phone', $orderSearchAttrs);
        $this->assertContains('tracking_number', $orderSearchAttrs);

        $customerSearchAttrs = \App\Filament\Resources\CustomerResource::getGloballySearchableAttributes();
        $this->assertContains('name', $customerSearchAttrs);
        $this->assertContains('phone', $customerSearchAttrs);
    }

    /**
     * TEST 9: Nepal Monthly Payroll Run creation and Rs. formatting.
     */
    public function test_nepal_payroll_run_creation(): void
    {
        $runNumber = 'PAY-RUN-' . uniqid();
        $payroll = \App\Models\Hrm\PayrollRun::create([
            'run_number' => $runNumber,
            'name' => 'Salary — September 2026',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'pay_date' => '2026-09-30',
            'status' => 'draft',
            'total_gross_salary_npr' => 125000.00,
            'total_net_payout_npr' => 110000.00,
        ]);

        $this->assertInstanceOf(\App\Models\Hrm\PayrollRun::class, $payroll);
        $this->assertEquals($runNumber, $payroll->run_number);
        $this->assertEquals(125000.00, (float)$payroll->total_gross_salary_npr);
    }
}
