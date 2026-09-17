<?php

namespace Tests\Feature;

use App\Filament\Pages\OfflineSales;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\CollectionResource;
use App\Filament\Resources\StockLevelResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PreorderResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\RestockRequestResource;
use App\Filament\Resources\AttributeResource;
use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OfflineSaleService;
use App\Services\ProductSkuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommerceDeepAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ModuleSettingsSeeder::class);

        $this->adminUser = User::factory()->create([
            'email' => 'store-admin@laijau.com',
            'role' => 'admin',
        ]);
    }

    /**
     * Test 1: Offline Sales POS scans base product EAN-13 barcode successfully.
     */
    public function test_offline_sales_scans_base_product_barcode(): void
    {
        $product = Product::create([
            'name' => 'Royal Heritage Pashmina',
            'slug' => 'royal-heritage-pashmina',
            'sku' => 'PAS-ROYAL-001',
            'barcode' => '5701234999991',
            'price' => 1500.00,
            'price_npr' => 1500.00,
            'quantity' => 8,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(OfflineSales::class)
            ->set('searchQuery', '5701234999991')
            ->call('handleBarcodeOrSkuScan')
            ->assertSet('searchQuery', '')
            ->assertCount('cart', 1);
    }

    /**
     * Test 2: Offline Sales POS scans variant EAN-13 barcode successfully.
     */
    public function test_offline_sales_scans_variant_barcode(): void
    {
        $product = Product::create([
            'name' => 'Crimson Cotton Polo Shirt',
            'slug' => 'crimson-cotton-polo-shirt',
            'sku' => 'POL-CRIM-001',
            'barcode' => '5701234888882',
            'price' => 2200.00,
            'price_npr' => 2200.00,
            'quantity' => 15,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'POL-CRIM-001-M',
            'barcode' => '5701234777773',
            'color' => 'Crimson Red',
            'size' => 'M',
            'stock_quantity' => 5,
            'price' => 2200.00,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(OfflineSales::class)
            ->set('searchQuery', '5701234777773')
            ->call('handleBarcodeOrSkuScan')
            ->assertSet('searchQuery', '')
            ->assertCount('cart', 1);
    }

    /**
     * Test 3: Offline sale variant selection decrements variant stock accurately.
     */
    public function test_offline_sale_shoe_variant_selection_and_atomic_decrement(): void
    {
        $product = Product::create([
            'name' => 'Classic Leather Oxford Shoes',
            'slug' => 'classic-leather-oxford-shoes-' . uniqid(),
            'sku' => 'SHO-OXF-001',
            'price_npr' => 3200.00,
            'quantity' => 10,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SHO-OXF-001-42',
            'size' => '42',
            'price_npr' => 3200.00,
            'stock_quantity' => 3,
            'is_active' => true,
        ]);

        $service = app(OfflineSaleService::class);

        $saleData = [
            'customer_name' => 'Anjali Shrestha',
            'currency' => 'npr',
            'payment_method' => 'card',
            'sales_channel' => 'physical',
        ];

        $itemsData = [
            [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
                'unit_price' => 3200.00,
                'size' => '42',
            ],
        ];

        $sale = $service->createSale($saleData, $itemsData, $this->adminUser);

        $this->assertEquals(3200.00, (float)$sale->total_amount);
        $this->assertEquals('completed', $sale->status);

        $variant->refresh();
        $this->assertEquals(2, $variant->stock_quantity);
    }

    /**
     * Test 4: Voiding an offline sale restocks both product quantity and variant stock.
     */
    public function test_offline_sale_void_restocks_shoe_inventory(): void
    {
        $product = Product::create([
            'name' => 'Minimalist Leather Sneakers',
            'slug' => 'minimalist-leather-sneakers-' . uniqid(),
            'sku' => 'SHO-SNK-001',
            'price_npr' => 2800.00,
            'quantity' => 8,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SHO-SNK-001-41',
            'size' => '41',
            'price_npr' => 2800.00,
            'stock_quantity' => 3,
            'is_active' => true,
        ]);

        $service = app(OfflineSaleService::class);

        $sale = $service->createSale(
            ['customer_name' => 'Test Customer', 'currency' => 'npr'],
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 2800.00, 'size' => '41']],
            $this->adminUser
        );

        $variant->refresh();
        $this->assertEquals(2, $variant->stock_quantity);

        // Void the sale with restock
        $service->voidSale($sale, 'Customer change of mind', $this->adminUser, true);

        $variant->refresh();
        $this->assertEquals(3, $variant->stock_quantity);
    }

    /**
     * Test 5: Offline sales printable receipt route is functional and accessible by admin.
     */
    public function test_offline_sales_printable_receipt_route_loads_for_admin(): void
    {
        $product = Product::create([
            'name' => 'Emerald Green Silk Scarf',
            'sku' => 'SCF-EME-001',
            'price' => 1200.00,
            'price_npr' => 1200.00,
            'quantity' => 10,
            'is_active' => true,
        ]);

        $service = app(OfflineSaleService::class);
        $sale = $service->createSale(
            ['customer_name' => 'Karin Jensen', 'currency' => 'npr'],
            [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1200.00]],
            $this->adminUser
        );

        $response = $this->actingAs($this->adminUser, 'admin')->get(route('offline_sales.receipt', $sale));
        $response->assertStatus(200);
        $response->assertSee('LAIJAU');
        $response->assertSee('#' . $sale->sale_number);
        $response->assertSee('Karin Jensen');
        $response->assertSee('Emerald Green Silk Scarf');
        $response->assertSee('Print Receipt');
    }

    /**
     * Test 6: Verify all Commerce navigation items have unique, sequential sort order.
     */
    public function test_all_commerce_navigation_sorts_are_unique_and_sequential(): void
    {
        $resources = [
            'ProductResource' => ProductResource::getNavigationSort(),
            'OrderResource' => OrderResource::getNavigationSort(),
            'CouponResource' => \App\Filament\Resources\Coupons\CouponResource::getNavigationSort(),
            'PreorderResource' => PreorderResource::getNavigationSort(),
            'RestockRequestResource' => RestockRequestResource::getNavigationSort(),
        ];

        // Verify none are null
        foreach ($resources as $name => $sort) {
            $this->assertNotNull($sort, "Navigation sort for {$name} should not be null.");
        }

        // Verify all sort numbers are unique
        $values = array_values($resources);
        $this->assertCount(count($resources), array_unique($values), 'Commerce navigation sort numbers must be unique without collisions: ' . json_encode($resources));
    }
}
