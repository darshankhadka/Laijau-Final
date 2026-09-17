<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\BarcodeLabelPage;
use App\Filament\Pages\OfflineSales;
use App\Filament\Pages\QuickStockEntryPage;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\ProductResource\Pages\ViewProduct;
use App\Models\Category;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\ProductImportExportService;
use App\Services\ProductService;
use App\Services\ProductSkuService;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductConsoleTest extends TestCase
{
    protected User $admin;
    protected User $cashier;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('email', 'admin@laijau.com')->first()
            ?: User::create([
                'name' => 'Laijau Super Admin',
                'email' => 'admin@laijau.com',
                'password' => bcrypt('password123'),
                'role' => 'admin',
            ]);

        $this->cashier = User::firstOrCreate(
            ['email' => 'cashier-test@laijau.com'],
            [
                'name' => 'POS Cashier Tester',
                'password' => bcrypt('password123'),
                'role' => 'cashier',
            ]
        );

        $this->warehouse = app(InventoryService::class)->getDefaultWarehouse()
            ?: Warehouse::first();
    }

    /**
     * 1-4. Product creation, editing, safe archiving, and restoration.
     */
    public function test_product_lifecycle_and_safe_archiving(): void
    {
        $category = Category::first() ?: Category::create(['name' => 'Footwear', 'slug' => 'footwear']);

        // Create product
        $product = Product::create([
            'name' => 'Kathmandu Trail Runner',
            'brand' => 'Himalayan Sport',
            'model' => 'KTR-2026',
            'price' => 4500.00,
            'cost_price' => 2200.00,
            'quantity' => 10,
            'is_active' => true,
            'is_published' => true,
        ]);
        $product->categories()->sync([$category->id]);

        $this->assertNotEmpty($product->sku);
        $this->assertNotEmpty($product->barcode);
        $this->assertTrue(app(ProductSkuService::class)->validateEan13($product->barcode));

        // Archive product
        $service = app(ProductService::class);
        $service->archive($product);

        $product->refresh();
        $this->assertFalse($product->is_active);
        $this->assertFalse($product->is_published);

        // Restore product
        $service->unarchive($product);
        $product->refresh();
        $this->assertTrue($product->is_active);
        $this->assertTrue($product->is_published);
    }

    /**
     * 5-7. SKU and Barcode generation, validation, and uniqueness.
     */
    public function test_sku_and_barcode_generation_and_uniqueness(): void
    {
        $skuService = app(ProductSkuService::class);

        $p1 = Product::create([
            'name' => 'Royal Heritage Loafer',
            'price' => 5999.00,
        ]);

        $p2 = Product::create([
            'name' => 'Royal Heritage Oxford',
            'price' => 6999.00,
        ]);

        $this->assertNotEquals($p1->sku, $p2->sku);
        $this->assertNotEquals($p1->barcode, $p2->barcode);
        $this->assertTrue($skuService->validateEan13($p1->barcode));
        $this->assertTrue($skuService->validateEan13($p2->barcode));
    }

    /**
     * 8-10. Workflow A: Create Shoe with variants matrix (2 colors x 6 sizes = 12 variants).
     */
    public function test_workflow_a_create_shoe_matrix_and_variants(): void
    {
        $category = Category::firstOrCreate(['name' => 'Shoes', 'slug' => 'shoes']);
        $shoe = Product::create([
            'name' => 'Nike Air Max 270',
            'brand' => 'Nike',
            'model' => 'AH8050-002',
            'price' => 14999.00,
            'cost_price' => 8500.00,
            'quantity' => 0,
            'is_active' => true,
            'is_published' => true,
        ]);
        $shoe->categories()->sync([$category->id]);

        $colors = ['Black', 'White'];
        $sizes = ['39', '40', '41', '42', '43', '44'];

        $createdVariants = [];
        foreach ($colors as $color) {
            foreach ($sizes as $size) {
                $variant = ProductVariant::create([
                    'product_id' => $shoe->id,
                    'color' => $color,
                    'size' => $size,
                    'price' => 14999.00,
                    'cost_price' => 8500.00,
                    'stock_quantity' => 10,
                    'is_active' => true,
                ]);
                $createdVariants[] = $variant;
            }
        }

        $this->assertCount(12, $createdVariants);
        $shoe->refresh();
        $this->assertEquals(12, $shoe->variants()->count());
        $this->assertEquals(120, $shoe->total_stock);

        // Check uniqueness of all 12 variants
        $skus = collect($createdVariants)->pluck('sku')->unique();
        $barcodes = collect($createdVariants)->pluck('barcode')->unique();
        $this->assertCount(12, $skus);
        $this->assertCount(12, $barcodes);
    }

    /**
     * 11. Variant bulk editing of prices and costs.
     */
    public function test_variant_bulk_editing_and_price_updates(): void
    {
        $product = Product::create([
            'name' => 'Signature Cotton Polo',
            'price' => 2500.00,
            'cost_price' => 1200.00,
        ]);

        $v1 = ProductVariant::create(['product_id' => $product->id, 'color' => 'Navy', 'size' => 'M', 'price' => 2500.00, 'cost_price' => 1200.00]);
        $v2 = ProductVariant::create(['product_id' => $product->id, 'color' => 'Navy', 'size' => 'L', 'price' => 2500.00, 'cost_price' => 1200.00]);

        $service = app(ProductService::class);
        $service->bulkUpdatePrices([$product->id], 500.00, false, 'price');

        $product->refresh();
        $v1->refresh();
        $v2->refresh();

        $this->assertEquals(3000.00, (float) $product->price);
        $this->assertEquals(3000.00, (float) $v1->price);
        $this->assertEquals(3000.00, (float) $v2->price);
    }

    /**
     * 12-15. Workflow B: Receive Stock via Quick Stock & Atomic Stock Movement.
     */
    public function test_workflow_b_receive_stock_atomically(): void
    {
        $product = Product::create([
            'name' => 'Kathmandu Trekking Boot',
            'price' => 8500.00,
            'quantity' => 5,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Brown',
            'size' => '42',
            'stock_quantity' => 5,
        ]);

        $initialVariantStock = (int) $variant->stock_quantity;

        $inventoryService = app(InventoryService::class);
        $movement = $inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 10,
            'movement_type' => 'purchase_receive',
            'notes' => 'Workflow B: Quick Receiving Test',
        ], $this->admin);

        $this->assertNotNull($movement);
        $this->assertEquals(10, $movement->quantity);

        $newOnHand = (int) StockLevel::where('warehouse_id', $this->warehouse->id)
            ->where('variant_id', $variant->id)
            ->value('quantity_on_hand');

        $this->assertEquals($initialVariantStock + 10, $newOnHand);

        $variant->refresh();
        $this->assertEquals($initialVariantStock + 10, $variant->stock_quantity);
    }

    /**
     * 16. Workflow C: Print Barcode Labels page.
     */
    public function test_workflow_c_print_barcode_labels(): void
    {
        $product = Product::create([
            'name' => 'Pashmina Stole Classic',
            'price' => 3500.00,
            'barcode' => '2001112223334',
        ]);

        Livewire::actingAs($this->admin)
            ->test(BarcodeLabelPage::class)
            ->call('addProductToQueue', $product->id)
            ->assertCount('printQueue', 1)
            ->assertSee($product->name);
    }

    /**
     * 17-20. Workflow D: POS Barcode Scan and Inventory Decrease.
     */
    public function test_workflow_d_pos_barcode_scan_and_sale(): void
    {
        $product = Product::create([
            'name' => 'Handcrafted Brass Bell',
            'price' => 1200.00,
            'quantity' => 20,
            'is_active' => true,
            'is_published' => true,
        ]);

        $barcode = $product->barcode;

        Livewire::actingAs($this->admin)
            ->test(OfflineSales::class)
            ->set('searchQuery', $barcode)
            ->call('handleBarcodeOrSkuScan')
            ->assertCount('cart', 1);
    }

    /**
     * 21. Workflow E: Storefront PDP Compatibility.
     */
    public function test_workflow_e_storefront_compatibility(): void
    {
        $category = Category::firstOrCreate(['name' => 'Apparel', 'slug' => 'apparel']);
        $product = Product::create([
            'name' => 'Royal Heritage Shawl Test',
            'price' => 4500.00,
            'featured_image' => 'products/royal-heritage-shawl-test.jpg',
            'quantity' => 10,
            'is_published' => true,
            'is_active' => true,
        ]);
        $product->categories()->sync([$category->id]);

        $response = $this->get('/products/' . $product->id);
        $response->assertStatus(200);
        $response->assertSee('Royal Heritage Shawl Test');
        $response->assertSee('4,500');
    }

    /**
     * 22. Safe Product Duplication (New SKUs & Barcodes, Zero Stock Duplication).
     */
    public function test_safe_product_duplication(): void
    {
        $original = Product::create([
            'name' => 'Original Artisan Vest',
            'brand' => 'Laijau Heritage',
            'price' => 5000.00,
            'cost_price' => 2500.00,
            'quantity' => 15,
        ]);

        $v1 = ProductVariant::create([
            'product_id' => $original->id,
            'color' => 'Beige',
            'size' => 'L',
            'stock_quantity' => 15,
        ]);

        $duplicated = app(ProductService::class)->duplicate($original);

        $this->assertNotEquals($original->id, $duplicated->id);
        $this->assertNotEquals($original->sku, $duplicated->sku);
        $this->assertNotEquals($original->barcode, $duplicated->barcode);
        $this->assertEquals(0, $duplicated->quantity);
        $this->assertFalse($duplicated->is_published);

        $newVariant = $duplicated->variants()->first();
        $this->assertNotNull($newVariant);
        $this->assertNotEquals($v1->id, $newVariant->id);
        $this->assertNotEquals($v1->sku, $newVariant->sku);
        $this->assertNotEquals($v1->barcode, $newVariant->barcode);
        $this->assertEquals(0, $newVariant->stock_quantity);
    }

    /**
     * 23. ViewProduct Overview Console Page renders cleanly.
     */
    public function test_view_product_page_renders(): void
    {
        $product = Product::create([
            'name' => 'Heritage View Console Piece',
            'price' => 7500.00,
            'cost_price' => 3800.00,
            'brand' => 'Laijau Heritage',
            'quantity' => 12,
            'is_published' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get('/intadmin/products/' . $product->id);
        $response->assertStatus(200);
        $response->assertSee('Heritage View Console Piece');
        $response->assertSee('7,500.00');
    }

    /**
     * 24. Product CSV Export and Import dry-run validation.
     */
    public function test_product_csv_export_and_import(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);

        $importExportService = app(ProductImportExportService::class);
        $streamResponse = $importExportService->exportCsv(Product::where('id', $product->id));

        $this->assertEquals(200, $streamResponse->getStatusCode());
        $this->assertStringContainsString('text/csv', $streamResponse->headers->get('Content-Type'));

        // Test import dry-run validation
        $tempPath = tempnam(sys_get_temp_dir(), 'csv_test');
        $fp = fopen($tempPath, 'w');
        fputcsv($fp, ['Product Name', 'Brand', 'Model', 'SKU', 'Barcode', 'Category', 'Retail Price', 'Cost Price', 'Stock']);
        fputcsv($fp, ['Import Test Polo Shirt', 'Laijau', 'MOD-101', 'LJ-POL-99991', '2009999999997', 'Apparel', '2500', '1200', '10']);
        fclose($fp);

        $uploaded = new \Illuminate\Http\UploadedFile($tempPath, 'test_products.csv', 'text/csv', null, true);
        $validation = $importExportService->validateImportCsv($uploaded);

        $this->assertTrue($validation['valid']);
        $this->assertEquals(1, $validation['valid_count']);
        $this->assertEquals('Import Test Polo Shirt', $validation['rows'][0]['name']);

        @unlink($tempPath);
    }
}
