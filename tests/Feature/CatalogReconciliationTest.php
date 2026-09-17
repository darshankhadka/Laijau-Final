<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Operational\CatalogSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CatalogReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    protected CatalogSyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncService = app(CatalogSyncService::class);
    }

    public function test_publication_eligibility_validation_blocks_missing_photo_or_data(): void
    {
        // 1. Photo-less product
        $photoLess = Product::create([
            'sku' => 'TEST-NO-PHOTO',
            'name' => 'Citizen Shoes – Test No Photo',
            'price' => 2500.00,
            'cost_price' => 1800.00,
            'is_active' => true,
            'is_published' => false,
        ]);
        $cat = Category::firstOrCreate(['name' => 'Men Footwear', 'slug' => 'men-footwear']);
        $photoLess->categories()->sync([$cat->id]);

        $result = $this->syncService->validatePublicationEligibility($photoLess);
        $this->assertFalse($result['eligible']);
        $this->assertContains('Product photo (physical file verified on disk)', $result['missing']);

        // 2. Product with zero price
        $zeroPriceProduct = new Product(['sku' => 'ZERO-01', 'name' => 'Zero Item', 'price' => 0]);
        $zeroResult = $this->syncService->validatePublicationEligibility($zeroPriceProduct);
        $this->assertFalse($zeroResult['eligible']);
        $this->assertContains('Valid selling price (> Rs. 0)', $zeroResult['missing']);

        // 3. Staging payment test product
        $stagingProduct = new Product(['sku' => 'COD-KTM-01', 'name' => 'Payment Test', 'price' => 1000]);
        $stagingResult = $this->syncService->validatePublicationEligibility($stagingProduct);
        $this->assertFalse($stagingResult['eligible']);
        $this->assertContains('Staging payment test product cannot be published', $stagingResult['missing']);
    }

    public function test_catalog_sync_unpublishes_photo_less_products_while_keeping_pos_active(): void
    {
        // Create an active product without a photo that was mistakenly published
        $product = Product::create([
            'sku' => 'SHOES-STOCK-TEST-1',
            'name' => 'Citizen Factory Shoes | 9999 Broshof',
            'price' => 3000.00,
            'cost_price' => 2000.00,
            'is_active' => true,
            'is_published' => true, // Mistakenly published
        ]);
        $cat = Category::firstOrCreate(['name' => 'Test Shoes', 'slug' => 'test-shoes']);
        $product->categories()->sync([$cat->id]);

        // Run sync
        $this->syncService->sync(false, false);

        $product->refresh();

        // Must be unpublished from webshop
        $this->assertFalse($product->is_published, "Product without photo must have is_published set to false.");
        // Must remain active for ERP/POS!
        $this->assertTrue($product->is_active, "Product must remain is_active for POS and inventory.");
        // Customer title must be normalized
        $this->assertEquals('Citizen Shoes – 9999 Broshof', $product->name);

        // POS query searchability
        $posResults = Product::where('is_active', true)->where('id', $product->id)->count();
        $this->assertEquals(1, $posResults, "Unpublished product must be searchable in POS.");

        // Storefront exclusion
        $storefrontResults = Product::where('is_published', true)->where('id', $product->id)->count();
        $this->assertEquals(0, $storefrontResults, "Unpublished product must be hidden from storefront.");
    }

    public function test_title_cleaning_preserves_sku_and_removes_unverified_claims(): void
    {
        $raw = 'RUN Shoes genuine leather handmade - 1234 Black';
        $cleaned = $this->syncService->cleanCustomerFacingTitle($raw);

        $this->assertStringNotContainsString('genuine leather', strtolower($cleaned));
        $this->assertStringNotContainsString('handmade', strtolower($cleaned));
        $this->assertStringContainsString('RUN Shoes', $cleaned);
        $this->assertStringContainsString('1234 Black', $cleaned);
    }
}
