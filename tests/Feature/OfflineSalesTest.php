<?php

namespace Tests\Feature;

use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\OfflineSaleVoidLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OfflineSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineSalesTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /**
     * Test creating a single-item offline sale with stock decrement.
     */
    public function test_create_single_product_offline_sale(): void
    {
        $service = app(OfflineSaleService::class);

        $u = uniqid();
        $product = Product::create([
            'name' => 'Cashmere Shawl Classic',
            'slug' => 'cashmere-shawl-classic-' . $u,
            'sku' => 'CS-CLASSIC-' . $u,
            'price' => 800.00,
            'price_npr' => 800.00,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'CS-CLASSIC-NAVY-' . $u,
            'color' => 'Navy Blue',
            'size' => 'Standard',
            'price_npr' => 800.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $saleData = [
            'customer_name' => 'Aarav Shrestha',
            'customer_phone' => '+977 9841234567',
            'currency' => 'npr',
            'payment_method' => 'esewa',
            'sales_channel' => 'physical',
        ];

        $itemsData = [
            [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 2,
                'unit_price' => 750.00, // Negotiated offline price (vs 800 website price)
            ],
        ];

        $sale = $service->createSale($saleData, $itemsData);

        $this->assertInstanceOf(OfflineSale::class, $sale);
        $this->assertStringStartsWith('OFF-', $sale->sale_number);
        $this->assertEquals(1500.00, (float)$sale->total_amount);
        $this->assertEquals('esewa', $sale->payment_method);
        $this->assertEquals('physical', $sale->sales_channel);
        $this->assertEquals('completed', $sale->status);

        // Verify stock was decremented from 10 -> 8
        $variant->refresh();
        $this->assertEquals(8, $variant->stock_quantity);

        // Verify line item stored reference price and actual price
        $item = $sale->items()->first();
        $this->assertEquals(800.00, (float)$item->website_price);
        $this->assertEquals(750.00, (float)$item->unit_price);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(1500.00, (float)$item->total_price);
    }

    /**
     * Test multi-item offline sale with landed cost integration and profit tracking.
     */
    public function test_multi_item_offline_sale_with_landed_cost_integration(): void
    {
        $service = app(OfflineSaleService::class);

        $u1 = uniqid();
        $u2 = uniqid();

        // 1. Setup Product 1 with true landed cost
        $prod1 = Product::create([
            'name' => 'Royal Oxford Shoes',
            'slug' => 'royal-oxford-shoes-' . $u1,
            'sku' => 'LJ-SHO-' . $u1,
            'price_npr' => 1100.00,
            'cost_price_npr' => 52.50, // True Landed Cost
            'is_active' => true,
        ]);

        $var1 = ProductVariant::create([
            'product_id' => $prod1->id,
            'sku' => 'LJ-SHO-VAR-' . $u1,
            'stock_quantity' => 15,
            'price_npr' => 1100.00,
            'cost_price_npr' => 52.50,
            'is_active' => true,
        ]);

        // 2. Setup Product 2
        $prod2 = Product::create([
            'name' => 'Handcrafted Silk Scarf',
            'slug' => 'handcrafted-silk-scarf-' . $u2,
            'sku' => 'NA-SCF-' . $u2,
            'price_npr' => 450.00,
            'is_active' => true,
        ]);

        $var2 = ProductVariant::create([
            'product_id' => $prod2->id,
            'sku' => 'NA-SCF-VAR-' . $u2,
            'stock_quantity' => 20,
            'price_npr' => 450.00,
            'is_active' => true,
        ]);

        $saleData = [
            'customer_name' => 'Aarav Sharma',
            'customer_phone' => '+977 9841234567',
            'currency' => 'npr',
            'discount_amount' => 50.00,
            'discount_reason' => 'VIP Showroom Courtesy',
            'payment_method' => 'card',
            'sales_channel' => 'showroom',
        ];

        $itemsData = [
            [
                'product_id' => $prod1->id,
                'variant_id' => $var1->id,
                'quantity' => 1,
                'unit_price' => 1000.00, // NPR 1000.00
            ],
            [
                'product_id' => $prod2->id,
                'variant_id' => $var2->id,
                'quantity' => 2,
                'unit_price' => 400.00, // NPR 400.00 each
            ],
        ];

        $sale = $service->createSale($saleData, $itemsData);

        // Subtotal: 1000 + (2 x 400) = 1800. Discount = 50. Total = 1750 NPR
        $this->assertEquals(1800.00, (float)$sale->subtotal);
        $this->assertEquals(50.00, (float)$sale->discount_amount);
        $this->assertEquals(1750.00, (float)$sale->total_amount);

        // Check realized cost on item 1: unit_cost_npr should be exactly 52.50
        $shoeItem = $sale->items()->where('sku', 'LJ-SHO-VAR-' . $u1)->first();
        $this->assertNotNull($shoeItem);
        $this->assertEquals(52.50, (float)$shoeItem->unit_cost_npr);
        $this->assertEquals('realized', $shoeItem->cost_type);

        // Check stock decrements
        $this->assertEquals(14, $var1->fresh()->stock_quantity);
        $this->assertEquals(18, $var2->fresh()->stock_quantity);
    }

    /**
     * Test insufficient stock rejection.
     */
    public function test_insufficient_stock_rejection(): void
    {
        $this->expectException(\RuntimeException::class);

        $service = app(OfflineSaleService::class);

        $uLim = uniqid();
        $product = Product::create(['name' => 'Limited Leather Boots', 'slug' => 'limited-boots-' . $uLim, 'sku' => 'LIM-' . $uLim, 'is_active' => true]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'LIM-VAR-' . $uLim,
            'stock_quantity' => 1,
            'price_npr' => 2000.00,
            'is_active' => true,
        ]);

        $service->createSale(
            ['customer_name' => 'Test'],
            [
                ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 5, 'unit_price' => 2000.00],
            ]
        );
    }

    /**
     * Test safe voiding mechanism with inventory restoration and audit logging.
     */
    public function test_void_sale_with_inventory_restoration_and_audit(): void
    {
        $service = app(OfflineSaleService::class);

        $uBowl = uniqid();
        $product = Product::create(['name' => 'Singing Bowl Gift', 'slug' => 'singing-bowl-gift-' . $uBowl, 'sku' => 'BOWL-' . $uBowl, 'is_active' => true]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'BOWL-VAR-' . $uBowl,
            'stock_quantity' => 10,
            'price_npr' => 600.00,
            'is_active' => true,
        ]);

        $sale = $service->createSale(
            ['customer_name' => 'Karin', 'currency' => 'npr'],
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 3, 'unit_price' => 600.00]]
        );

        $this->assertEquals(7, $variant->fresh()->stock_quantity);

        // Perform safe void
        $service->voidSale($sale, 'Customer changed mind before leaving showroom', null, true);
        $sale->refresh();

        $this->assertEquals('voided', $sale->status);
        $this->assertEquals('Customer changed mind before leaving showroom', $sale->void_reason);
        $this->assertNotNull($sale->voided_at);

        // Verify stock was restored from 7 back to 10
        $this->assertEquals(10, $variant->fresh()->stock_quantity);

        // Verify audit log entry exists
        $this->assertDatabaseHas('offline_sale_void_logs', [
            'offline_sale_id' => $sale->id,
            'reason' => 'Customer changed mind before leaving showroom',
            'restocked' => 1,
        ]);
    }

    /**
     * Test sequential sale numbering (OFF-000001, OFF-000002).
     */
    public function test_sequential_sale_numbering(): void
    {
        $service = app(OfflineSaleService::class);

        $uItm = uniqid();
        $prod = Product::create(['name' => 'Item 1', 'slug' => 'item-1-' . $uItm, 'sku' => 'ITM-' . $uItm, 'is_active' => true]);
        $var = ProductVariant::create([
            'product_id' => $prod->id,
            'sku' => 'VAR-SEQ-' . $uItm,
            'stock_quantity' => 50,
            'price_npr' => 100.00,
            'is_active' => true,
        ]);

        $sale1 = $service->createSale(
            ['customer_name' => 'First'],
            [['product_id' => $prod->id, 'variant_id' => $var->id, 'quantity' => 1, 'unit_price' => 100.00]]
        );

        $sale2 = $service->createSale(
            ['customer_name' => 'Second'],
            [['product_id' => $prod->id, 'variant_id' => $var->id, 'quantity' => 1, 'unit_price' => 100.00]]
        );

        $num1 = intval(substr($sale1->sale_number, 4));
        $num2 = intval(substr($sale2->sale_number, 4));
        $this->assertEquals($num1 + 1, $num2);
        $this->assertTrue($sale2->id > $sale1->id);
    }

    /**
     * Test dashboard KPIs calculation.
     */
    public function test_dashboard_kpi_metrics(): void
    {
        $service = app(OfflineSaleService::class);

        $uDash = uniqid();
        $prod = Product::create(['name' => 'Item Dash', 'slug' => 'item-dash-' . $uDash, 'sku' => 'ITM-DASH-' . $uDash, 'is_active' => true]);
        $var = ProductVariant::create([
            'product_id' => $prod->id,
            'sku' => 'VAR-DASH-' . $uDash,
            'stock_quantity' => 50,
            'price_npr' => 500.00,
            'is_active' => true,
        ]);

        // Sale 1 via eSewa / Instagram
        $service->createSale(
            ['customer_name' => 'User 1', 'payment_method' => 'esewa', 'sales_channel' => 'instagram'],
            [['product_id' => $prod->id, 'variant_id' => $var->id, 'quantity' => 2, 'unit_price' => 500.00]]
        );

        // Sale 2 via Card / Showroom
        $service->createSale(
            ['customer_name' => 'User 2', 'payment_method' => 'card', 'sales_channel' => 'showroom'],
            [['product_id' => $prod->id, 'variant_id' => $var->id, 'quantity' => 1, 'unit_price' => 500.00]]
        );

        $metrics = $service->getDashboardMetrics();

        $this->assertGreaterThanOrEqual(2, $metrics['today']['count']);
        $this->assertGreaterThanOrEqual(1500.00, $metrics['today']['revenue']);
        $this->assertGreaterThanOrEqual(3, $metrics['today']['units']);
        $this->assertGreaterThanOrEqual(1000.00, $metrics['payments']['esewa']['amount']);
        $this->assertGreaterThanOrEqual(500.00, $metrics['payments']['card']['amount']);
        $this->assertGreaterThanOrEqual(1000.00, $metrics['channels']['instagram']['amount']);
        $this->assertGreaterThanOrEqual(500.00, $metrics['channels']['showroom']['amount']);
    }

    /**
     * Test upgraded POS dashboard analytics with cash/digital split, drawer reconciliation, and Livewire controls.
     */
    public function test_pos_dashboard_upgraded_analytics(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            ['name' => 'Admin User', 'password' => bcrypt('password')]
        );

        $service = app(OfflineSaleService::class);
        $uDash = uniqid();
        $prod = Product::create(['name' => 'POS Item ' . $uDash, 'slug' => 'pos-item-' . $uDash, 'sku' => 'POS-' . $uDash, 'is_active' => true]);
        $var = ProductVariant::create([
            'product_id' => $prod->id,
            'sku' => 'POS-VAR-' . $uDash,
            'stock_quantity' => 100,
            'price_npr' => 1000.00,
            'is_active' => true,
        ]);

        // Sale with cash and change returned: 1000 sale, 2000 tendered, 1000 change
        $service->createSale(
            [
                'customer_name' => 'Till Test Cash',
                'payment_method' => 'cash',
                'sales_channel' => 'showroom',
                'cash_received' => 2000.00,
                'change_given' => 1000.00,
                'staff_name' => 'Cashier Alpha',
            ],
            [['product_id' => $prod->id, 'variant_id' => $var->id, 'quantity' => 1, 'unit_price' => 1000.00]]
        );

        // Sale with split payment: 1000 sale, 400 cash, 600 fonepay
        $service->createSale(
            [
                'customer_name' => 'Till Test Split',
                'payment_method' => 'split',
                'sales_channel' => 'showroom',
                'cash_received' => 400.00,
                'staff_name' => 'Cashier Beta',
            ],
            [['product_id' => $prod->id, 'variant_id' => $var->id, 'quantity' => 1, 'unit_price' => 1000.00]]
        );

        // Verify metrics service
        $metrics = $service->getDashboardMetrics(['period' => 'today']);
        $this->assertGreaterThanOrEqual(1400.00, $metrics['payment_analytics']['cash_vs_digital']['physical_cash_total']);
        $this->assertGreaterThanOrEqual(600.00, $metrics['payment_analytics']['cash_vs_digital']['digital_total']);
        $this->assertGreaterThanOrEqual(2400.00, $metrics['payment_analytics']['drawer_reconciliation']['cash_tendered']);
        $this->assertGreaterThanOrEqual(1000.00, $metrics['payment_analytics']['drawer_reconciliation']['change_returned']);
        $this->assertGreaterThanOrEqual(1400.00, $metrics['payment_analytics']['drawer_reconciliation']['net_cash_in_drawer']);
        $this->assertCount(14, $metrics['hourly_distribution']);
        $this->assertIsArray($metrics['all_sales']);
        $this->assertIsArray($metrics['discounted_sales']);
        $this->assertIsArray($metrics['major_events']);
        $this->assertNotEmpty($metrics['major_events']);

        // Verify Livewire dashboard UI rendering
        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Pages\OfflineSales::class)
            ->call('setTab', 'dashboard')
            ->assertSee('Physical Cash in Till')
            ->assertSee('CASH DRAWER BALANCING')
            ->assertSee('Net Till Cash')
            ->assertSee('Payment Method Distribution')
            ->assertSee('Hourly Showroom Traffic')
            ->assertSee('Top Performing Products in POS')
            ->assertSee('Print Shift Report')
            // Test opening and closing finalized Shift Report Modal with all sales journal, discounts audit, and major events
            ->call('openModal', 'shift_report')
            ->assertSet('activeModal', 'shift_report')
            ->assertSee('POS REGISTER SHIFT REPORT')
            ->assertSee('1. SALES SUMMARY')
            ->assertSee('2. CASH DRAWER (TILL RECONCILIATION)')
            ->assertSee('EXPECTED IN CASH TILL')
            ->assertSee('3. PAYMENT BREAKDOWN')
            ->assertSee('TOTAL DIGITAL SETTLEMENT')
            ->assertSee('4. ALL SALES JOURNAL')
            ->assertSee('5. DISCOUNTS GIVEN AUDIT')
            ->assertSee('6. MAJOR OPERATIONAL EVENTS LOG')
            ->assertSee('7. TILL BALANCING')
            ->assertSee('Cashier Signature')
            ->assertSee('Manager Sign-off')
            ->assertSee('Print Thermal (80mm)')
            ->assertSee('Standard (A4 / PDF)')
            ->call('closeModal')
            ->assertSet('activeModal', null)
            ->call('setDashboardPeriod', 'month')
            ->assertSet('dashboardPeriod', 'month')
            ->set('dashboardPaymentMethod', 'cash')
            ->assertSet('dashboardPaymentMethod', 'cash')
            ->call('resetDashboardFilters')
            ->assertSet('dashboardPeriod', 'today')
            ->assertSet('dashboardPaymentMethod', '');
    }

    /**
     * Test admin authorization for Offline Sales routes.
     */
    public function test_admin_authorization(): void
    {
        $guestResponse = $this->get('/intadmin/offline-sales');
        $guestResponse->assertRedirect('/intadmin/login');

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $adminResponse = $this->actingAs($admin, 'admin')->get('/intadmin/offline-sales');
        $this->assertTrue(in_array($adminResponse->getStatusCode(), [200, 302]));
    }

    /**
     * Test Livewire Offline Sales component rendering, tabs, cart and completion.
     */
    public function test_offline_sales_livewire_page_rendering_and_workflows(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $uRhs = uniqid();
        $product = Product::create([
            'name' => 'Royal Himalayan Shawl',
            'slug' => 'royal-himalayan-shawl-' . $uRhs,
            'sku' => 'NA-RHS-' . $uRhs,
            'price_npr' => 950.00,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'NA-RHS-EMERALD-' . $uRhs,
            'color' => 'Emerald Green',
            'size' => 'Standard',
            'price_npr' => 950.00,
            'stock_quantity' => 15,
            'is_active' => true,
        ]);

        \Livewire\Livewire::test(\App\Filament\Pages\OfflineSales::class)
            ->assertSuccessful()
            ->assertSee('LAIJAU')
            // Open session with opening cash float
            ->set('openingCashInput', 1000.00)
            ->call('openDailySession')
            // Add product to cart
            ->call('addToCart', $product->id, $variant->id, 1)
            ->assertSee('Royal Himalayan Shawl')
            ->assertSee('950.00')
            // Update quantity
            ->call('updateQuantity', $product->id . '-' . $variant->id, 2)
            ->assertSee('1,900.00')
            // Apply quick discount 10%
            ->call('applyItemQuickDiscount', $product->id . '-' . $variant->id, 10)
            ->assertSee('1,710.00')
            // Switch tabs
            ->call('setTab', 'history')
            ->assertSee('All Channels')
            ->call('setTab', 'dashboard')
            ->assertSee('Revenue')
            ->assertSee('Payment Method Distribution')
            ->call('setTab', 'performance')
            ->assertSee('Product-Level Offline Performance')
            // Switch back and complete sale
            ->call('setTab', 'new_sale')
            ->set('paymentMethod', 'esewa')
            ->set('salesChannel', 'showroom')
            ->call('completeSale')
            ->assertSet('activeModal', 'sale_success');

        $this->assertDatabaseHas('offline_sales', [
            'payment_method' => 'esewa',
            'sales_channel' => 'showroom',
            'total_amount' => 1710.00,
        ]);
    }

    /**
     * Test Open Shift cash float input and live calculation.
     */
    public function test_open_shift_denomination_counting_and_live_calculation(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Pages\OfflineSales::class)
            ->call('openModal', 'open_session')
            ->assertSet('activeModal', 'open_session')
            ->assertSee('Open Daily POS Shift')
            ->assertSee('Count Physical Cash Float')
            ->assertSee('Opening Cash Total: Rs.')
            // Enter direct cash float input
            ->set('openingCashInput', 6350.00)
            ->assertSet('openingCashInput', 6350.00)
            ->assertSee('Opening Cash Total: Rs. 6,350.00');
    }

    /**
     * Test Close Shift cash float reconciliation and shortage handling.
     */
    public function test_close_shift_denomination_reconciliation_and_shortage_handling(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $test = \Livewire\Livewire::test(\App\Filament\Pages\OfflineSales::class)
            ->call('openCloseSessionModal')
            ->assertSet('activeModal', 'close_session')
            ->assertSee('Count Physical Cash Float')
            ->assertSee('Expected Cash')
            ->assertSee('Physical Counted')
            ->assertSee('Difference / Variance');

        $expected = (float)($test->instance()->closingSessionDetails['calc']['expected_cash'] ?? 5000.0);

        // Set exact counted cash = expected (Balanced)
        $test->set('closingCashInput', $expected)
            ->assertSet('closingCashInput', $expected)
            ->assertSee('Balanced')
            // Set short (Short by 1000)
            ->set('closingCashInput', max(0, $expected - 1000))
            ->assertSee('Cash Short')
            ->assertSee('Reason for Shortage')
            // Set over (Over by 1000)
            ->set('closingCashInput', $expected + 1000)
            ->assertSee('Cash Over');
    }

    /**
     * Test Shift Report modal display and print touch targets.
     */
    public function test_shift_report_modal_and_print_touch_targets(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Pages\OfflineSales::class)
            ->call('openModal', 'shift_report')
            ->assertSet('activeModal', 'shift_report')
            ->assertSee('Print Thermal (80mm)')
            ->assertSee('Standard A4 / PDF');
    }

    /**
     * Test handleProductClick directly adds simple products and opens variant selection for multi-variant items.
     */
    public function test_handle_product_click_and_variant_selection_in_pos(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        // Ensure active POS station and session
        $station = \App\Models\Hardware\PosStation::firstOrCreate(
            ['id' => 1],
            ['name' => 'Terminal 1', 'code' => 'pos_terminal_1', 'warehouse_id' => 43, 'is_active' => true]
        );

        $today = app(\App\Services\Pos\PosSessionService::class)->getNepalToday();
        \App\Models\Pos\PosSession::updateOrCreate(
            ['pos_station_id' => $station->id, 'business_date' => $today],
            [
                'warehouse_id' => 43,
                'terminal_code' => $station->code,
                'terminal_name' => $station->name,
                'showroom_name' => 'Laijau Showroom',
                'status' => 'open',
                'opening_balance' => 2000.00,
                'opened_by_user_id' => $admin->id,
                'opened_by_name' => $admin->name,
                'opened_at' => now(),
                'expected_cash' => 2000.00,
            ]
        );

        // Create simple product
        $u1 = uniqid();
        $simple = Product::create([
            'name' => 'Simple Shawl ' . $u1,
            'slug' => 'simple-shawl-' . $u1,
            'sku' => 'SIMP-' . $u1,
            'price' => 1200.00,
            'quantity' => 15,
            'is_active' => true,
        ]);

        // Create multi-variant product
        $u2 = uniqid();
        $multi = Product::create([
            'name' => 'Multi Kurtha ' . $u2,
            'slug' => 'multi-kurtha-' . $u2,
            'sku' => 'MULT-' . $u2,
            'price' => 2500.00,
            'is_active' => true,
        ]);
        $var1 = ProductVariant::create([
            'product_id' => $multi->id,
            'sku' => 'MULT-RED-' . $u2,
            'color' => 'Red',
            'size' => 'M',
            'price' => 2500.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
        $var2 = ProductVariant::create([
            'product_id' => $multi->id,
            'sku' => 'MULT-BLU-' . $u2,
            'color' => 'Blue',
            'size' => 'L',
            'price' => 2500.00,
            'stock_quantity' => 8,
            'is_active' => true,
        ]);

        $test = \Livewire\Livewire::test(\App\Filament\Pages\OfflineSales::class);

        // 1. Click simple product -> directly added to cart
        $test->call('handleProductClick', $simple->id)
            ->assertCount('cart', 1)
            ->assertSet("cart.{$simple->id}-default.name", $simple->name)
            ->assertSet("cart.{$simple->id}-default.quantity", 1);

        // 2. Click multi-variant product -> opens variant modal
        $test->call('handleProductClick', $multi->id)
            ->assertSet('activeModal', 'variant_select')
            ->assertSet('selectedProductForVariant.id', $multi->id)
            ->call('selectModalVariantColor', 'Blue')
            ->call('selectModalVariantSize', 'L')
            ->call('addModalVariantToCart')
            ->assertSet('activeModal', null)
            ->assertCount('cart', 2);
    }
}


