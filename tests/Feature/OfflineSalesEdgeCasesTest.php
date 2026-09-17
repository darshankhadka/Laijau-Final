<?php

namespace Tests\Feature;

use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\OfflineSaleVoidLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OfflineSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineSalesEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Real-World Sale Simulation:
     * Product → quantity → negotiated price → payment method → save → inventory/cost/profit → history → receipt → dashboard
     */
    public function test_real_world_sale_full_simulation_flow(): void
    {
        $service = app(OfflineSaleService::class);

        // Product with cost catalog landed cost batch
        $product = Product::create([
            'name' => 'The Imperial Pashmina Stole',
            'slug' => 'imperial-pashmina-stole',
            'sku' => 'IPS-001',
            'price_npr' => 1500.00,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'IPS-001-GOLD',
            'color' => 'Royal Gold',
            'size' => '200x70cm',
            'price_npr' => 1500.00,
            'cost_price_npr' => 650.00, // True landed cost from factory & logistics in NPR
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        // Staff records an in-person Kathmandu Showroom sale with a negotiated courtesy price
        $staff = User::factory()->create(['name' => 'Showroom Stylist', 'role' => 'admin']);

        $saleData = [
            'customer_name' => 'Sophia Shrestha',
            'customer_email' => 'sophia@example.com',
            'customer_phone' => '+977 9841223344',
            'currency' => 'npr',
            'discount_amount' => 100.00,
            'discount_reason' => 'Showroom Client Courtesy',
            'payment_method' => 'cash',
            'sales_channel' => 'showroom',
            'customer_notes' => 'Gift packaging included',
            'internal_notes' => 'Private appointment sale',
        ];

        $itemsData = [
            [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 2,
                'unit_price' => 1400.00, // Negotiated from 1500 down to 1400 NPR
            ],
        ];

        // 1. SAVE / ATOMIC TRANSACTION
        $sale = $service->createSale($saleData, $itemsData, $staff);

        // 2. INVENTORY DEDUCTION
        $this->assertEquals(8, $variant->fresh()->stock_quantity); // 10 - 2 = 8

        // 3. FINANCIALS & PROFIT CALCULATION
        // Subtotal: 2 x 1400 = 2800 NPR
        // Discount: 100 NPR
        // Total Amount: 2700 NPR
        $this->assertEquals(2800.00, (float)$sale->subtotal);
        $this->assertEquals(100.00, (float)$sale->discount_amount);
        $this->assertEquals(2700.00, (float)$sale->total_amount);

        // Total Cost: 2 units * Rs. 650.00 landed cost = Rs. 1300.00 NPR
        $this->assertEquals(1300.00, (float)$sale->total_cost_npr);

        // Product Revenue NPR: (2800 - 100) = 2700 NPR
        // Total Profit NPR: 2700.00 - 1300.00 = 1400.00 NPR
        $this->assertEquals(1400.00, (float)$sale->total_profit_npr);
        $this->assertEquals('realized', $sale->profit_status);

        // 4. SALES HISTORY
        $this->assertDatabaseHas('offline_sales', [
            'id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'customer_name' => 'Sophia Shrestha',
            'status' => 'completed',
        ]);

        // 5. RECEIPT / DETAILS
        $this->assertEquals('cash', $sale->payment_method);
        $this->assertEquals('showroom', $sale->sales_channel);

        // 6. DASHBOARD METRICS
        $metrics = $service->getDashboardMetrics();
        $this->assertGreaterThanOrEqual(1, $metrics['today']['count']);
        $this->assertGreaterThanOrEqual(2700.00, $metrics['today']['revenue']);
        $this->assertGreaterThanOrEqual(2, $metrics['today']['units']);
        $this->assertGreaterThanOrEqual(2700.00, $metrics['payments']['cash']['amount']);
        $this->assertGreaterThanOrEqual(2700.00, $metrics['channels']['showroom']['amount']);
    }

    /**
     * 2. Edge Case: One product, quantity 1
     */
    public function test_edge_case_single_product_quantity_one(): void
    {
        $service = app(OfflineSaleService::class);

        $product = Product::create(['name' => 'Silk Dupatta', 'slug' => 'silk-dupatta', 'sku' => 'SD-01', 'price_npr' => 400.00, 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SD-01-RED', 'color' => 'Red', 'stock_quantity' => 5, 'price_npr' => 400.00, 'is_active' => true]);

        $sale = $service->createSale(
            ['customer_name' => 'Clara', 'payment_method' => 'cash'],
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 400.00]]
        );

        $this->assertEquals(400.00, (float)$sale->total_amount);
        $this->assertEquals(4, $variant->fresh()->stock_quantity);
    }

    /**
     * 3. Edge Case: Multiple quantities (e.g. 5 units)
     */
    public function test_edge_case_multiple_quantities(): void
    {
        $service = app(OfflineSaleService::class);

        $product = Product::create(['name' => 'Cotton Shirt', 'slug' => 'cotton-shirt', 'sku' => 'CS-01', 'price_npr' => 300.00, 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'CS-01-WHT', 'stock_quantity' => 20, 'price_npr' => 300.00, 'is_active' => true]);

        $sale = $service->createSale(
            ['customer_name' => 'Wholesale Buyer', 'payment_method' => 'bank_transfer', 'sales_channel' => 'wholesale'],
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 5, 'unit_price' => 250.00]]
        );

        $this->assertEquals(1250.00, (float)$sale->total_amount); // 5 x 250
        $this->assertEquals(15, $variant->fresh()->stock_quantity); // 20 - 5 = 15
    }

    /**
     * 4. Edge Case: Discount / negotiated price significantly below website price
     */
    public function test_edge_case_negotiated_price_below_website_price(): void
    {
        $service = app(OfflineSaleService::class);

        // Website price 2000 npr, negotiated in-person to 1400 npr
        $product = Product::create(['name' => 'Leather Jacket Piece', 'slug' => 'leather-jacket-piece', 'sku' => 'LJP-01', 'price_npr' => 2000.00, 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'LJP-01', 'stock_quantity' => 3, 'price_npr' => 2000.00, 'is_active' => true]);

        $sale = $service->createSale(
            ['customer_name' => 'Amina', 'sales_channel' => 'whatsapp'],
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 1400.00]]
        );

        $item = $sale->items()->first();
        $this->assertEquals(2000.00, (float)$item->website_price);
        $this->assertEquals(1400.00, (float)$item->unit_price);
        $this->assertEquals(1400.00, (float)$sale->total_amount);
    }

    /**
     * 5. Edge Case: Cash sale
     */
    public function test_edge_case_cash_sale(): void
    {
        $service = app(OfflineSaleService::class);

        $product = Product::create(['name' => 'Cash Item', 'slug' => 'cash-item', 'sku' => 'CSH-01', 'price_npr' => 100.00, 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'CSH-01', 'stock_quantity' => 10, 'price_npr' => 100.00, 'is_active' => true]);

        $sale = $service->createSale(
            ['customer_name' => 'Pop-up Buyer', 'payment_method' => 'cash', 'sales_channel' => 'event'],
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 100.00]]
        );

        $this->assertEquals('cash', $sale->payment_method);
        $this->assertEquals('event', $sale->sales_channel);
    }

    /**
     * 6. Edge Case: Bank wire & Card terminal sales
     */
    public function test_edge_case_bank_wire_and_card_sales(): void
    {
        $service = app(OfflineSaleService::class);

        $product = Product::create(['name' => 'Card Item', 'slug' => 'card-item', 'sku' => 'CRD-01', 'price_npr' => 500.00, 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'CRD-01', 'stock_quantity' => 10, 'price_npr' => 500.00, 'is_active' => true]);

        $saleBank = $service->createSale(
            ['customer_name' => 'Invoice Client', 'payment_method' => 'bank_transfer', 'sales_channel' => 'whatsapp'],
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 500.00]]
        );

        $saleCard = $service->createSale(
            ['customer_name' => 'Terminal Client', 'payment_method' => 'card', 'sales_channel' => 'physical'],
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 500.00]]
        );

        $this->assertEquals('bank_transfer', $saleBank->payment_method);
        $this->assertEquals('card', $saleCard->payment_method);
    }

    /**
     * 7. Edge Case: Shipping cost recorded on manual/offline sale
     */
    public function test_edge_case_shipping_cost(): void
    {
        $service = app(OfflineSaleService::class);

        $product = Product::create(['name' => 'Instagram Scarf', 'slug' => 'instagram-scarf', 'sku' => 'IG-01', 'price_npr' => 800.00, 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'IG-01', 'stock_quantity' => 5, 'price_npr' => 800.00, 'is_active' => true]);

        // Sale with 50 npr shipping fee charged to customer
        $sale = $service->createSale(
            [
                'customer_name' => 'Instagram Customer',
                'payment_method' => 'esewa',
                'sales_channel' => 'instagram',
                'shipping_amount' => 50.00,
            ],
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 800.00]]
        );

        $this->assertEquals(800.00, (float)$sale->subtotal);
        $this->assertEquals(50.00, (float)$sale->shipping_amount);
        $this->assertEquals(850.00, (float)$sale->total_amount); // 800 subtotal + 50 shipping
    }

    /**
     * 8. Edge Case: Product with multiple variants (color/size isolation)
     */
    public function test_edge_case_product_with_variants(): void
    {
        $service = app(OfflineSaleService::class);

        $product = Product::create(['name' => 'Multi-Variant Polo', 'slug' => 'multi-variant-polo', 'sku' => 'MVP-01', 'is_active' => true]);
        $varRed = ProductVariant::create(['product_id' => $product->id, 'sku' => 'MVP-RED-M', 'color' => 'Red', 'size' => 'M', 'stock_quantity' => 10, 'price_npr' => 600.00, 'is_active' => true]);
        $varBlue = ProductVariant::create(['product_id' => $product->id, 'sku' => 'MVP-BLU-L', 'color' => 'Blue', 'size' => 'L', 'stock_quantity' => 8, 'price_npr' => 600.00, 'is_active' => true]);

        // Sell Red M
        $service->createSale(
            ['customer_name' => 'Red Lover'],
            [['product_id' => $product->id, 'variant_id' => $varRed->id, 'quantity' => 3, 'unit_price' => 600.00]]
        );

        $this->assertEquals(7, $varRed->fresh()->stock_quantity); // 10 - 3 = 7
        $this->assertEquals(8, $varBlue->fresh()->stock_quantity); // Unchanged
    }

    /**
     * 9. Edge Case: Standalone product without variants
     */
    public function test_edge_case_standalone_product_without_variants(): void
    {
        $service = app(OfflineSaleService::class);

        $product = Product::create([
            'name' => 'Handmade Brass Bell',
            'slug' => 'handmade-brass-bell',
            'sku' => 'BELL-01',
            'price_npr' => 250.00,
            'quantity' => 12, // Quantity stored directly on product
            'is_active' => true,
        ]);

        $sale = $service->createSale(
            ['customer_name' => 'Tourist'],
            [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2, 'unit_price' => 250.00]]
        );

        $this->assertEquals(10, $product->fresh()->quantity); // 12 - 2 = 10

        // Void and verify stock restores to Product model
        $service->voidSale($sale, 'Customer returned item', null, true);
        $this->assertEquals(12, $product->fresh()->quantity); // Restored back to 12
    }

    /**
     * 10. Edge Case: Cancel / void behavior
     */
    public function test_edge_case_cancel_void_behavior(): void
    {
        $service = app(OfflineSaleService::class);

        $product = Product::create(['name' => 'Voidable Item', 'slug' => 'voidable-item', 'sku' => 'VOID-01', 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'VOID-01', 'stock_quantity' => 5, 'price_npr' => 500.00, 'is_active' => true]);

        $sale = $service->createSale(
            ['customer_name' => 'Void Test'],
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => 500.00]]
        );

        $this->assertEquals(3, $variant->fresh()->stock_quantity);

        $service->voidSale($sale, 'Mistake in recording', null, true);

        $this->assertEquals('voided', $sale->fresh()->status);
        $this->assertEquals(5, $variant->fresh()->stock_quantity); // Restored
        $this->assertDatabaseHas('offline_sale_void_logs', [
            'offline_sale_id' => $sale->id,
            'reason' => 'Mistake in recording',
            'restocked' => 1,
        ]);
    }

    /**
     * 11. Edge Case: Same SKU sold repeatedly across multiple transactions
     */
    public function test_edge_case_same_sku_sold_repeatedly(): void
    {
        $service = app(OfflineSaleService::class);

        $product = Product::create(['name' => 'Popular Shawl', 'slug' => 'popular-shawl', 'sku' => 'POP-01', 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'POP-01', 'stock_quantity' => 10, 'price_npr' => 700.00, 'is_active' => true]);

        // Sale 1
        $sale1 = $service->createSale(
            ['customer_name' => 'Customer 1'],
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 700.00]]
        );

        // Sale 2
        $sale2 = $service->createSale(
            ['customer_name' => 'Customer 2'],
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => 700.00]]
        );

        // Sale 3
        $sale3 = $service->createSale(
            ['customer_name' => 'Customer 3'],
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 3, 'unit_price' => 700.00]]
        );

        $num1 = intval(substr($sale1->sale_number, 4));
        $num2 = intval(substr($sale2->sale_number, 4));
        $num3 = intval(substr($sale3->sale_number, 4));
        $this->assertEquals($num1 + 1, $num2);
        $this->assertEquals($num2 + 1, $num3);

        // 10 - 1 - 2 - 3 = 4 left
        $this->assertEquals(4, $variant->fresh()->stock_quantity);
    }

    /**
     * 12. Edge Case: Profit calculation against correct landed cost / batch
     */
    public function test_edge_case_profit_calculation_against_landed_cost_batch(): void
    {
        $service = app(OfflineSaleService::class);

        $product = Product::create([
            'name' => 'Pure Wool Poncho',
            'slug' => 'pure-wool-poncho',
            'sku' => 'PWP-BATCH',
            'price_npr' => 1000.00,
            'cost_price_npr' => 42.00, // Exact Landed Cost
            'is_active' => true,
        ]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'PWP-BATCH', 'stock_quantity' => 10, 'price_npr' => 1000.00, 'is_active' => true]);

        $sale = $service->createSale(
            ['customer_name' => 'Batch Cost Test', 'currency' => 'npr'],
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 1000.00]]
        );

        $item = $sale->items()->first();
        $this->assertEquals(42.00, (float)$item->unit_cost_npr);
        $this->assertEquals('realized', $item->cost_type);
        // Selling price: 1000.00. Unit profit = 1000.00 - 42.00 = 958.00
        $this->assertEquals(round(1000.00 - 42.00, 4), (float)$item->unit_profit_npr);
    }

    /**
     * 13. Absolute Isolation & Clean Reporting Structure:
     * Online revenue/orders vs Offline revenue/sales are strictly separated.
     */
    public function test_reporting_architecture_isolation_website_vs_manual_sales(): void
    {
        $service = app(OfflineSaleService::class);

        $product = Product::create(['name' => 'Separation Test Piece', 'slug' => 'sep-test', 'sku' => 'SEP-01', 'price_npr' => 1000.00, 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SEP-01', 'stock_quantity' => 20, 'price_npr' => 1000.00, 'is_active' => true]);

        $initialOrderCount = Order::count();
        $initialOfflineCount = OfflineSale::count();
        $initialOnlineRev = (float) Order::where('payment_status', 'paid')->sum('total_amount');
        $initialOfflineRev = (float) OfflineSale::where('status', 'completed')->sum('total_amount');

        // 1. Create an Online Order
        $onlineOrder = Order::create([
            'order_number' => 'LJ-260906-ONLINE',
            'email' => 'online@example.com',
            'first_name' => 'Online',
            'last_name' => 'Customer',
            'shipping_address' => 'Bohara Tol, Kageshwori Manahara 09',
            'shipping_country' => 'NP',
            'subtotal' => 1000.00,
            'total_amount' => 1059.00,
            'shipping_fee' => 59.00,
            'currency' => 'NPR',
            'payment_status' => 'paid',
            'status' => 'processing',
        ]);

        // 2. Create an Offline Sale
        $offlineSale = $service->createSale(
            ['customer_name' => 'Offline Customer', 'currency' => 'NPR', 'payment_method' => 'cash', 'sales_channel' => 'showroom'],
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => 1000.00]]
        );

        // Verification of absolute architectural isolation
        $this->assertEquals($initialOrderCount + 1, Order::count());
        $this->assertEquals($initialOfflineCount + 1, OfflineSale::count());

        // Online Metrics (isolated increment)
        $onlineRevenueDelta = (float) Order::where('payment_status', 'paid')->sum('total_amount') - $initialOnlineRev;
        $this->assertEquals(1059.00, $onlineRevenueDelta);

        // Offline Metrics (isolated increment)
        $offlineRevenueDelta = (float) OfflineSale::where('status', 'completed')->sum('total_amount') - $initialOfflineRev;
        $this->assertEquals(2000.00, $offlineRevenueDelta);

        // Business Total
        $totalBusinessDelta = $onlineRevenueDelta + $offlineRevenueDelta;
        $this->assertEquals(3059.00, $totalBusinessDelta);
    }
}
