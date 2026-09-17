<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductSkuService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AutoSkuGenerationTest extends TestCase
{
    use DatabaseTransactions;

    protected ProductSkuService $skuService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skuService = app(ProductSkuService::class);
    }

    public function test_new_product_receives_sku_automatically_without_manual_input()
    {
        $product = Product::create([
            'name' => 'Automated Oxford Shoes ' . uniqid(),
            'slug' => 'automated-oxford-shoes-' . uniqid(),
            'price_npr' => 1500.00,
            'quantity' => 10,
            'track_quantity' => true,
            'is_active' => true,
            'is_published' => true,
        ]);

        $this->assertNotNull($product->sku);
        $this->assertNotEmpty($product->sku);
        $this->assertMatchesRegularExpression('/^(LJ|NA)-[A-Z0-9]{3}-\d{5}$/', $product->sku);
    }

    public function test_category_prefix_resolution()
    {
        $shoeCat = Category::firstOrCreate(['slug' => 'test-shoes'], ['name' => 'Footwear & Shoes', 'is_active' => true]);
        $shirtCat = Category::firstOrCreate(['slug' => 'test-shirts'], ['name' => 'Shirts & Tops', 'is_active' => true]);
        $jacketCat = Category::firstOrCreate(['slug' => 'test-jackets'], ['name' => 'Jackets & Outerwear', 'is_active' => true]);
        $shawlCat = Category::firstOrCreate(['slug' => 'test-shawls'], ['name' => 'Shawls & Wraps', 'is_active' => true]);
        $pantCat = Category::firstOrCreate(['slug' => 'test-pants'], ['name' => 'Pants & Trousers', 'is_active' => true]);
        $dressCat = Category::firstOrCreate(['slug' => 'test-dresses'], ['name' => 'Dresses', 'is_active' => true]);
        $handbagCat = Category::firstOrCreate(['slug' => 'test-handbags'], ['name' => 'Handbags', 'is_active' => true]);

        $this->assertEquals('SHO', $this->skuService->resolveCategoryCode($shoeCat));
        $this->assertEquals('SHR', $this->skuService->resolveCategoryCode($shirtCat));
        $this->assertEquals('JKT', $this->skuService->resolveCategoryCode($jacketCat));
        $this->assertEquals('SHW', $this->skuService->resolveCategoryCode($shawlCat));
        $this->assertEquals('PNT', $this->skuService->resolveCategoryCode($pantCat));
        $this->assertEquals('DRS', $this->skuService->resolveCategoryCode($dressCat));
        $this->assertEquals('HAN', $this->skuService->resolveCategoryCode($handbagCat)); // 3-letter abbreviation
        $this->assertEquals('PRD', $this->skuService->resolveCategoryCode(null, 'Mystic Heritage Collection Piece')); // Fallback
    }

    public function test_existing_product_sku_remains_unchanged_on_edit()
    {
        $product = Product::create([
            'name' => 'Original Handcrafted Oxford Shoes',
            'slug' => 'original-handcrafted-oxford-shoes-' . uniqid(),
            'price_npr' => 1899.00,
            'quantity' => 5,
        ]);

        $originalSku = $product->sku;
        $this->assertNotEmpty($originalSku);

        // Update various fields
        $product->update([
            'name' => 'Renamed Handcrafted Oxford Shoes With Luxury Details',
            'price_npr' => 2199.00,
            'description' => 'Updated description content.',
            'short_description' => 'Updated short description.',
        ]);

        $product->refresh();
        $this->assertEquals($originalSku, $product->sku, 'SKU must remain identical after updating product details.');
    }

    public function test_manual_sku_is_preserved_if_explicitly_provided()
    {
        $customSku = 'CUSTOM-SKU-' . strtoupper(uniqid());

        $product = Product::create([
            'name' => 'Custom Tagged Piece',
            'slug' => 'custom-tagged-piece-' . uniqid(),
            'sku' => $customSku,
            'price_npr' => 1100.00,
            'quantity' => 3,
        ]);

        $this->assertEquals($customSku, $product->sku);
    }

    public function test_duplicate_sku_is_rejected_by_unique_constraint()
    {
        $duplicateSku = 'DUP-TEST-SKU-' . strtoupper(uniqid());

        Product::create([
            'name' => 'Piece A',
            'slug' => 'piece-a-' . uniqid(),
            'sku' => $duplicateSku,
            'price_npr' => 750.00,
        ]);

        $this->expectException(QueryException::class);

        Product::create([
            'name' => 'Piece B',
            'slug' => 'piece-b-' . uniqid(),
            'sku' => $duplicateSku,
            'price_npr' => 890.00,
        ]);
    }

    public function test_collision_retry_and_auto_recovery()
    {
        $cat = Category::firstOrCreate(['slug' => 'collision-test-cat'], ['name' => 'Dresses', 'is_active' => true]);

        // Pre-create next expected SKU manually
        $collidingSku = 'NA-DRS-00001';
        Product::create([
            'name' => 'Pre-existing colliding dress',
            'slug' => 'pre-existing-colliding-dress-' . uniqid(),
            'sku' => $collidingSku,
            'price_npr' => 750.00,
        ]);

        // When generating for DRS category, it should detect NA-DRS-00001 is taken and safely allocate NA-DRS-00002
        $allocatedSku = $this->skuService->generate(null, $cat, 'Evening Silk Dress');

        $this->assertNotEquals($collidingSku, $allocatedSku);
        $this->assertFalse(
            Product::where('sku', $allocatedSku)->exists(),
            'Allocated SKU must not already exist in database.'
        );
    }

    public function test_sequential_generation_produces_unique_values()
    {
        $cat = Category::firstOrCreate(['slug' => 'seq-test-cat'], ['name' => 'Shawls & Wraps', 'is_active' => true]);

        $skus = [];
        for ($i = 0; $i < 5; $i++) {
            $sku = $this->skuService->generate(null, $cat, 'Cashmere Wrap ' . $i);
            // Simulate persisting product with this SKU
            Product::create([
                'name' => 'Persisted Cashmere Wrap ' . $i,
                'slug' => 'persisted-cashmere-wrap-' . $i . '-' . uniqid(),
                'sku' => $sku,
                'price_npr' => 2200.00,
            ]);
            $skus[] = $sku;
        }

        // All 5 SKUs must be unique
        $this->assertCount(5, array_unique($skus));
        foreach ($skus as $s) {
            $this->assertTrue(str_starts_with($s, 'LJ-SHW-') || str_starts_with($s, 'NA-SHW-'));
        }
    }

    public function test_variant_auto_sku_generation()
    {
        $product = Product::create([
            'name' => 'Classic Oxford Shoes For Variants',
            'slug' => 'classic-oxford-shoes-for-variants-' . uniqid(),
            'price_npr' => 1899.00,
        ]);

        $variant1 = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Crimson Red',
            'size' => '42',
            'price_npr' => 1899.00,
            'stock_quantity' => 10,
        ]);

        $variant2 = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Imperial Emerald',
            'size' => '43',
            'price_npr' => 1899.00,
            'stock_quantity' => 5,
        ]);

        $this->assertNotNull($variant1->sku);
        $this->assertNotNull($variant2->sku);
        $this->assertNotEquals($variant1->sku, $variant2->sku);
        $this->assertStringStartsWith($product->sku, $variant1->sku);
        $this->assertStringStartsWith($product->sku, $variant2->sku);
    }

    public function test_filament_create_product_page_renders_cleanly_without_manual_sku_or_barcode_inputs()
    {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'admin']);
        $admin = \App\Models\User::firstOrCreate(
            ['email' => 'admin_sku_test@laijau.com'],
            ['name' => 'Admin Sku Test', 'password' => bcrypt('password123')]
        );
        if (!$admin->hasRole('Super Admin', 'admin')) {
            $admin->assignRole($role);
        }

        $response = $this->actingAs($admin, 'admin')->get('/intadmin/products/create');
        $response->assertStatus(200);
        $response->assertDontSee('SKU (Stock Keeping Unit)');
        $response->assertDontSee('Barcode / EAN-13 / GTIN');
        $response->assertSee('Product Title');
    }

    public function test_filament_create_product_mutation_generates_sku_from_category()
    {
        $shoeCat = Category::firstOrCreate(['slug' => 'filament-shoes'], ['name' => 'Shoes', 'is_active' => true]);

        $page = new \App\Filament\Resources\ProductResource\Pages\CreateProduct();
        $reflection = new \ReflectionClass($page);
        $method = $reflection->getMethod('mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $formData = [
            'name' => 'Filament Derby Shoes ' . uniqid(),
            'categories' => [$shoeCat->id],
            'sku' => null,
            'price_npr' => 2250.00,
        ];

        $mutated = $method->invoke($page, $formData);

        $this->assertNotEmpty($mutated['sku']);
        $this->assertTrue(str_starts_with($mutated['sku'], 'LJ-SHO-') || str_starts_with($mutated['sku'], 'NA-SHO-'));
        $this->assertMatchesRegularExpression('/^(LJ|NA)-SHO-\d{5}$/', $mutated['sku']);
    }
}
