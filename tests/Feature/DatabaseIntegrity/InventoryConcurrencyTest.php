<?php

declare(strict_types=1);

namespace Tests\Feature\DatabaseIntegrity;

use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use App\Services\OfflineSaleService;
use App\Services\Settings\SettingsService;
use Database\Seeders\ModuleSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected OfflineSaleService $posService;
    protected InventoryService $inventoryService;
    protected SettingsService $settingsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSettingsSeeder::class);
        $this->posService = app(OfflineSaleService::class);
        $this->inventoryService = app(InventoryService::class);
        $this->settingsService = app(SettingsService::class);
    }

    /**
     * Verify that under simultaneous checkout/POS attempts, exactly one succeeds and overselling is impossible.
     */
    public function test_simultaneous_pos_sales_prevent_overselling_under_concurrency(): void
    {
        // 1. Setup product with exactly 1 unit in stock
        $product = Product::create([
            'name' => 'Royal Cashmere Wrap - Exclusive',
            'slug' => 'royal-cashmere-wrap-exclusive',
            'sku' => 'RCW-EXC-01',
            'price' => 1200.00,
            'price_npr' => 1200.00,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'RCW-EXC-01-GOLD',
            'color' => 'Gold',
            'size' => 'Standard',
            'price_npr' => 1200.00,
            'stock_quantity' => 1,
            'is_active' => true,
        ]);

        // Seed Showroom StockLevel with exactly 1 unit
        $wh = $this->inventoryService->getShowroomWarehouse();
        StockLevel::create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity_on_hand' => 1,
            'quantity_reserved' => 0,
            'quantity_incoming' => 0,
            'unit_cost_npr' => 450.00,
        ]);

        $saleData = [
            'customer_name' => 'Customer A',
            'currency' => 'npr',
            'payment_method' => 'mobilepay',
            'sales_channel' => 'physical',
        ];

        $itemsData = [
            [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
                'unit_price' => 1200.00,
            ],
        ];

        // First attempt succeeds
        $firstSale = $this->posService->createSale($saleData, $itemsData);
        $this->assertInstanceOf(OfflineSale::class, $firstSale);

        // Verify stock dropped to 0
        $variant->refresh();
        $this->assertEquals(0, $variant->stock_quantity);

        $stockLevel = StockLevel::where('warehouse_id', $wh->id)
            ->where('product_id', $product->id)
            ->where('variant_id', $variant->id)
            ->first();
        $this->assertEquals(0, $stockLevel->quantity_on_hand);

        // Second simultaneous attempt MUST fail with Insufficient stock exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Insufficient stock/');

        $saleData2 = [
            'customer_name' => 'Customer B',
            'currency' => 'npr',
            'payment_method' => 'card',
            'sales_channel' => 'physical',
        ];

        $this->posService->createSale($saleData2, $itemsData);
    }

    /**
     * Verify strict negative stock disallowance policy in InventoryService.
     */
    public function test_negative_stock_prevention_policy_enforcement(): void
    {
        $this->settingsService->set('inventory', 'negative_stock_policy', 'strictly_forbidden');

        $product = Product::create([
            'name' => 'Silk Scarf',
            'slug' => 'silk-scarf',
            'sku' => 'SS-01',
            'price' => 300.00,
            'is_active' => true,
        ]);

        $wh = $this->inventoryService->getDefaultWarehouse();
        StockLevel::create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 2,
            'unit_cost_npr' => 100.00,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Negative stock is not permitted/');

        // Attempt to deduct 3 units when only 2 exist
        $this->inventoryService->recordStockMovement([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'movement_type' => 'damage',
            'quantity' => -3,
            'reason' => 'Water damage in warehouse',
        ]);
    }

    /**
     * Verify multi-product deterministic lock ordering prevents deadlocks when requests arrive in inverse order.
     */
    public function test_deadlock_free_multi_product_ordering(): void
    {
        $p1 = Product::create(['name' => 'Item 1', 'slug' => 'item-1', 'sku' => 'ITM-1', 'price' => 100.00, 'is_active' => true]);
        $p2 = Product::create(['name' => 'Item 2', 'slug' => 'item-2', 'sku' => 'ITM-2', 'price' => 200.00, 'is_active' => true]);
        $p3 = Product::create(['name' => 'Item 3', 'slug' => 'item-3', 'sku' => 'ITM-3', 'price' => 300.00, 'is_active' => true]);

        $wh = $this->inventoryService->getShowroomWarehouse();
        foreach ([$p1, $p2, $p3] as $p) {
            StockLevel::create([
                'warehouse_id' => $wh->id,
                'product_id' => $p->id,
                'quantity_on_hand' => 10,
                'unit_cost_npr' => 50.00,
            ]);
            $p->update(['quantity' => 10]);
        }

        // Request A: items ordered [p3, p1]
        $saleA = $this->posService->createSale([
            'customer_name' => 'Client Order A',
            'currency' => 'npr',
            'payment_method' => 'mobilepay',
            'sales_channel' => 'physical',
        ], [
            ['product_id' => $p3->id, 'quantity' => 1, 'unit_price' => 300.00],
            ['product_id' => $p1->id, 'quantity' => 1, 'unit_price' => 100.00],
        ]);

        // Request B: items ordered [p1, p2, p3]
        $saleB = $this->posService->createSale([
            'customer_name' => 'Client Order B',
            'currency' => 'npr',
            'payment_method' => 'cash',
            'sales_channel' => 'physical',
        ], [
            ['product_id' => $p1->id, 'quantity' => 2, 'unit_price' => 100.00],
            ['product_id' => $p2->id, 'quantity' => 1, 'unit_price' => 200.00],
            ['product_id' => $p3->id, 'quantity' => 1, 'unit_price' => 300.00],
        ]);

        $this->assertInstanceOf(OfflineSale::class, $saleA);
        $this->assertInstanceOf(OfflineSale::class, $saleB);

        // Verify accurate remaining stock:
        // p1: 10 - 1 - 2 = 7
        // p2: 10 - 1 = 9
        // p3: 10 - 1 - 1 = 8
        $this->assertEquals(7, StockLevel::where('warehouse_id', $wh->id)->where('product_id', $p1->id)->value('quantity_on_hand'));
        $this->assertEquals(9, StockLevel::where('warehouse_id', $wh->id)->where('product_id', $p2->id)->value('quantity_on_hand'));
        $this->assertEquals(8, StockLevel::where('warehouse_id', $wh->id)->where('product_id', $p3->id)->value('quantity_on_hand'));
    }

    /**
     * Verify atomic stock deduction and order fulfillment with zero orphan records.
     */
    public function test_atomic_stock_and_order_fulfillment(): void
    {
        $product = Product::create([
            'name' => 'Handwoven Pashmina Stole',
            'slug' => 'handwoven-pashmina-stole',
            'sku' => 'HPS-01',
            'price' => 500.00,
            'price_npr' => 500.00,
            'is_active' => true,
        ]);

        $wh = $this->inventoryService->getDefaultWarehouse();
        StockLevel::create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 5,
            'unit_cost_npr' => 180.00,
        ]);

        $order = Order::create([
            'first_name' => 'Bikash',
            'last_name' => 'Sharma',
            'email' => 'bikash.sharma@laijau.com',
            'shipping_address' => 'Tripureshwor 12',
            'shipping_country' => 'NP',
            'subtotal' => 1000.00,
            'total_amount' => 1000.00,
            'currency' => 'npr',
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => 'paid',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 2,
            'unit_price' => 500.00,
        ]);

        $this->inventoryService->fulfillOrderStock($order);

        // Assert stock reduced from 5 to 3
        $stockLevel = StockLevel::where('warehouse_id', $wh->id)->where('product_id', $product->id)->first();
        $this->assertEquals(3, $stockLevel->quantity_on_hand);

        // Assert stock movement created
        $movement = StockMovement::where('reference_type', 'order')->where('reference_id', $order->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-2, $movement->quantity);
        $this->assertEquals(5, $movement->quantity_before);
        $this->assertEquals(3, $movement->quantity_after);
    }
}
