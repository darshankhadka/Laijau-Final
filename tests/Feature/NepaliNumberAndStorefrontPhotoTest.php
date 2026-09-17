<?php

namespace Tests\Feature;

use App\Helpers\NepaliNumberHelper;
use App\Helpers\StorefrontHelper;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NepaliNumberAndStorefrontPhotoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_formats_numbers_strictly_in_nepali_numbering_system()
    {
        $this->assertEquals('1,000', NepaliNumberHelper::format(1000));
        $this->assertEquals('10,000', NepaliNumberHelper::format(10000));
        $this->assertEquals('1,00,00,000', nepali_number(10000000));
        $this->assertEquals('1,00,00,000.50', NepaliNumberHelper::format(10000000.50, 2));

        // Test currency format
        $this->assertEquals('Rs. 1,00,000', NepaliNumberHelper::formatCurrency(100000));
        $this->assertEquals('Rs. 12,50,000', NepaliNumberHelper::formatCurrency(1250000));
        $this->assertEquals('Rs. 1,00,00,000', nepali_currency(10000000));
        $this->assertEquals('1,00,00,000', nepali_number(10000000));
        $this->assertEquals('Rs. 1,00,000', StorefrontHelper::formatPrice(100000));
    }

    public function test_storefront_only_exposes_products_with_genuine_physical_photos()
    {
        if (Product::storefrontReady()->count() === 0) {
            Product::create([
                'name' => 'Authentic Nepali Dhaka Topi',
                'slug' => 'authentic-nepali-dhaka-topi',
                'sku' => 'NEP-TOPI-001',
                'price' => 1200,
                'is_published' => true,
                'is_active' => true,
                'featured_image' => 'products/nepali-dhaka-topi.jpg',
            ]);
        }

        // 1. All products returned by Product::storefrontReady() must have a non-empty, non-placeholder featured_image
        $storefrontProducts = Product::storefrontReady()->get();
        $this->assertGreaterThan(0, $storefrontProducts->count());

        foreach ($storefrontProducts as $product) {
            $this->assertNotEmpty($product->featured_image);
            $this->assertStringNotContainsStringIgnoringCase('placeholder', $product->featured_image);
            $this->assertTrue($product->is_published);
            $this->assertTrue($product->is_active);
        }

        // 2. Unphotographed or unpublished products must NOT appear in storefrontReady
        $unpublished = Product::where('is_published', false)->first();
        if ($unpublished) {
            $this->assertFalse(Product::storefrontReady()->where('id', $unpublished->id)->exists());
        }
    }

    public function test_storefront_helper_never_returns_placeholder_garment_fallback()
    {
        $this->assertEquals('', StorefrontHelper::getImageUrl(null));
        $this->assertEquals('', StorefrontHelper::getImageUrl(''));
        $this->assertEquals('', StorefrontHelper::getImageUrl('/placeholder-garment.jpg'));
        $this->assertEquals('', StorefrontHelper::getImageUrl('placeholder.png'));

        $resolved = StorefrontHelper::resolveImageList(null);
        $this->assertEmpty($resolved);
    }
}
