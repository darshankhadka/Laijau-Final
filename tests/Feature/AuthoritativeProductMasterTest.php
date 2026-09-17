<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\OfflineSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AuthoritativeProductMasterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'laijau_staging']);
        config(['database.connections.mysql.host' => '127.0.0.1']);
        config(['database.connections.mysql.username' => 'darshan']);
        config(['database.connections.mysql.password' => 'Dars@@9861']);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    public function test_zero_duplicate_skus_in_product_catalog(): void
    {
        $totalCount = Product::count();
        $uniqueSkuCount = Product::select('sku')->distinct()->count();

        $this->assertGreaterThanOrEqual(500, $totalCount, 'Catalog must contain complete authoritative products.');
        $this->assertLessThanOrEqual(1000, $totalCount, 'Catalog must not contain inflated unverified scratchpad products.');
        $this->assertEquals($totalCount, $uniqueSkuCount, 'Zero duplicate SKUs allowed: every product must have a strictly unique code.');
    }

    public function test_zero_unverified_scratchpad_or_dash_products_in_master(): void
    {
        $dashProductsCount = Product::where('name', 'LIKE', '-%')
            ->orWhere('name', 'LIKE', '--%')
            ->orWhere('sku', 'LIKE', '-%')
            ->count();
        $this->assertEquals(0, $dashProductsCount, 'Zero products starting with dash or double-dash allowed.');

        $scratchpadJunkCount = Product::where('name', 'LIKE', '%Nani Ko%')
            ->orWhere('name', 'LIKE', '%Anup sir%')
            ->orWhere('name', 'LIKE', '-1-%')
            ->orWhere('name', 'LIKE', '-2-%')
            ->orWhere('name', 'LIKE', '-3-%')
            ->orWhere('name', 'LIKE', '-4-%')
            ->orWhere('name', 'LIKE', '-Addidas%')
            ->count();
        $this->assertEquals(0, $scratchpadJunkCount, 'Zero cashier scratchpad notes allowed as catalog products.');
    }

    public function test_only_authorized_products_with_physical_photos_are_published(): void
    {
        $publishedProducts = Product::where('is_published', true)->get();

        $this->assertGreaterThanOrEqual(500, $publishedProducts->count(), 'At least 500 live products must have authorized pictures.');
        $this->assertLessThanOrEqual(550, $publishedProducts->count(), 'Only products with authorized photos must be published (approx 532).');

        $verifiedOnDisk = 0;
        foreach ($publishedProducts as $p) {
            $this->assertNotNull($p->featured_image, "Published product #{$p->sku} must have a featured image.");
            $this->assertStringStartsWith('product/thumbnail/', $p->featured_image, "Product #{$p->sku} must have an authorized thumbnail.");
            $this->assertTrue($p->is_active, "Product #{$p->sku} with picture must be active.");

            $path = storage_path('app/public/' . $p->featured_image);
            $this->assertFileExists($path, "Thumbnail for product #{$p->sku} must physically exist on disk.");
            $verifiedOnDisk++;
        }

        $this->assertEquals($publishedProducts->count(), $verifiedOnDisk, 'Every published product must have a verified physical file.');

        // Verify that all products without photos are disabled from storefront
        $unpublishedWithoutPhotos = Product::whereNull('featured_image')->where('is_published', true)->count();
        $this->assertEquals(0, $unpublishedWithoutPhotos, 'No product without photo may be published to storefront.');

        // Verify zero placeholder/invented images
        $inventedImages = Product::where('featured_image', 'LIKE', 'products/%')->count();
        $this->assertEquals(0, $inventedImages, 'Zero invented placeholder images allowed.');
    }

    public function test_all_products_have_realistic_cost_prices_and_no_fake_profit(): void
    {
        $nullOrZeroCostCount = Product::whereNull('cost_price')->orWhere('cost_price', '<=', 0)->count();
        $this->assertEquals(0, $nullOrZeroCostCount, 'Every product must have a valid positive cost price.');

        $fakeHighProfitCount = Product::where('price', '>', 0)
            ->whereRaw('(price - cost_price) / price > 0.65')
            ->count();
        $this->assertEquals(0, $fakeHighProfitCount, 'Zero products may show fake excessive profit margin (>65%).');
    }

    public function test_all_products_have_valid_selling_prices(): void
    {
        $zeroPriceCount = Product::whereNull('price')->orWhere('price', '<=', 0)->count();
        $this->assertEquals(0, $zeroPriceCount, 'Every product must have a valid positive selling price.');
    }

    public function test_all_products_are_attached_to_categories(): void
    {
        $uncategorizedCount = Product::whereDoesntHave('categories')->count();
        $this->assertEquals(0, $uncategorizedCount, 'Every product must be linked to at least one category.');
    }

    public function test_storefront_ready_scope_returns_active_catalog(): void
    {
        $storefrontCount = Product::storefrontReady()->count();
        $this->assertGreaterThanOrEqual(500, $storefrontCount, 'Storefront ready scope must return at least 500 items.');
        $this->assertLessThanOrEqual(550, $storefrontCount, 'Storefront ready scope must only return items with authorized photos.');
    }

    public function test_product_variants_have_valid_stock_and_sizes(): void
    {
        $variantsCount = ProductVariant::count();
        $this->assertGreaterThan(500, $variantsCount, 'Product variants should be created for shoe/apparel sizes.');

        $invalidVariantPriceCount = ProductVariant::where('price', '<=', 0)->count();
        $this->assertEquals(0, $invalidVariantPriceCount, 'All product variants must have valid prices.');
    }

    public function test_offline_sales_and_items_are_fully_costed_without_fake_profit(): void
    {
        $totalSales = DB::table('offline_sales')->count();
        $this->assertGreaterThanOrEqual(10580, $totalSales, 'All authentic POS sales must be present.');
        $this->assertLessThanOrEqual(10650, $totalSales, 'POS sales count should reflect verified sales.');

        // Zero sales with zero cost
        $zeroCostSales = DB::table('offline_sales')->where('total_amount', '>', 0)->where('total_cost_npr', '<=', 0)->count();
        $this->assertEquals(0, $zeroCostSales, 'Zero POS sales should have 0 cost.');

        // Zero sales with fake high margin (>65%)
        $fakeHighMarginSales = DB::table('offline_sales')->where('total_amount', '>', 0)->where('margin_percentage', '>', 65)->count();
        $this->assertEquals(0, $fakeHighMarginSales, 'Zero POS sales should show fake >65% profit margin.');

        // Average sales margin must be authentic retail benchmark (~35% to 45%)
        $avgMargin = (float)DB::table('offline_sales')->where('total_amount', '>', 0)->avg('margin_percentage');
        $this->assertGreaterThanOrEqual(30.0, $avgMargin, 'Average retail margin must be >= 30%.');
        $this->assertLessThanOrEqual(45.0, $avgMargin, 'Average retail margin must be <= 45%.');

        // Offline sale items checks
        $zeroCostItems = DB::table('offline_sale_items')->where('total_price', '>', 0)->where('total_cost_npr', '<=', 0)->count();
        $this->assertEquals(0, $zeroCostItems, 'Zero POS sale items should have 0 cost.');

        $fakeHighMarginItems = DB::table('offline_sale_items')->where('total_price', '>', 0)->where('margin_percentage', '>', 65)->count();
        $this->assertEquals(0, $fakeHighMarginItems, 'Zero POS sale items should show fake >65% profit margin.');

        $relinkedCount = OfflineSaleItem::where('product_id', '>', 2)->count();
        $this->assertGreaterThan(5000, $relinkedCount, 'Over 5,000 POS sale items must be relinked to authoritative products.');
    }
}
