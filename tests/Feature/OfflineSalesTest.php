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
            ->assertSee('New Sale')
            ->assertSee('Sales History')
            ->assertSee('Dashboard')
            ->assertSee('Product Metrics')
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
}
