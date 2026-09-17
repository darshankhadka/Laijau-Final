<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ImageOptimizerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ProductMediaPipelineTest extends TestCase
{
    use DatabaseTransactions;

    public function test_multiple_products_maintain_independent_unique_images()
    {
        Storage::fake('public');

        // Create 3 distinct test image files
        $fileA = UploadedFile::fake()->image('red-shoes.jpg', 600, 800);
        $pathA = Storage::disk('public')->putFileAs('products', $fileA, Str::uuid() . '-red-shoes.jpg');

        $fileB = UploadedFile::fake()->image('blue-boots.jpg', 600, 800);
        $pathB = Storage::disk('public')->putFileAs('products', $fileB, Str::uuid() . '-blue-boots.jpg');

        $fileC = UploadedFile::fake()->image('green-jacket.jpg', 600, 800);
        $pathC = Storage::disk('public')->putFileAs('products', $fileC, Str::uuid() . '-green-jacket.jpg');

        // Create Product A
        $prodA = Product::create([
            'name' => 'Crimson Leather Shoes ' . rand(1000, 9999),
            'slug' => 'crimson-leather-shoes-' . rand(1000, 9999),
            'sku' => 'SKU-A-' . rand(100, 999),
            'price_npr' => 2500,
            'quantity' => 10,
            'is_published' => true,
            'is_active' => true,
            'featured_image' => $pathA,
            'images' => [$pathA],
        ]);

        // Create Product B
        $prodB = Product::create([
            'name' => 'Royal Azure Boots ' . rand(1000, 9999),
            'slug' => 'royal-azure-boots-' . rand(1000, 9999),
            'sku' => 'SKU-B-' . rand(100, 999),
            'price_npr' => 4500,
            'quantity' => 5,
            'is_published' => true,
            'is_active' => true,
            'featured_image' => $pathB,
            'images' => [$pathB],
        ]);

        // Create Product C
        $prodC = Product::create([
            'name' => 'Emerald Linen Jacket ' . rand(1000, 9999),
            'slug' => 'emerald-linen-jacket-' . rand(1000, 9999),
            'sku' => 'SKU-C-' . rand(100, 999),
            'price_npr' => 1200,
            'quantity' => 8,
            'is_published' => true,
            'is_active' => true,
            'featured_image' => $pathC,
            'images' => [$pathC],
        ]);

        // Verify that each product has its own unique image reference
        $this->assertNotEquals($prodA->featured_image, $prodB->featured_image);
        $this->assertNotEquals($prodB->featured_image, $prodC->featured_image);
        $this->assertNotEquals($prodA->featured_image, $prodC->featured_image);

        // Verify API endpoint returns correct independent image for Product A
        $responseA = $this->getJson("/api/products/{$prodA->id}");
        $responseA->assertStatus(200);
        $dataA = $responseA->json('data');
        $this->assertEquals($prodA->featured_image, $dataA['featured_image']);

        // Verify API endpoint returns correct independent image for Product B
        $responseB = $this->getJson("/api/products/{$prodB->id}");
        $responseB->assertStatus(200);
        $dataB = $responseB->json('data');
        $this->assertEquals($prodB->featured_image, $dataB['featured_image']);

        // Clean up
        $prodA->delete();
        $prodB->delete();
        $prodC->delete();
    }

    public function test_primary_image_deterministic_assignment_from_gallery()
    {
        $galleryImages = ['products/gallery/img-1.webp', 'products/gallery/img-2.webp'];

        $product = Product::create([
            'name' => 'Handwoven Pashmina Shawl ' . rand(1000, 9999),
            'slug' => 'handwoven-pashmina-shawl-' . rand(1000, 9999),
            'sku' => 'SKU-SHAWL-' . rand(100, 999),
            'price_npr' => 3200,
            'quantity' => 4,
            'is_published' => true,
            'is_active' => true,
            'featured_image' => null, // Omitted Cover photo
            'images' => $galleryImages, // Supplied gallery
        ]);

        $this->assertEquals('products/gallery/img-1.webp', $product->featured_image);
        $this->assertCount(2, $product->images);

        $product->delete();
    }

    public function test_rich_text_normalization_cleans_empty_markup_and_preserves_legitimate_content()
    {
        $product = Product::create([
            'name' => 'Rich Text Validation Piece ' . rand(1000, 9999),
            'slug' => 'rich-text-validation-piece-' . rand(1000, 9999),
            'sku' => 'SKU-HTML-' . rand(100, 999),
            'price_npr' => 1500,
            'quantity' => 5,
            'is_published' => true,
            'is_active' => true,
            'short_description' => '<p></p>',
            'description' => '<p><br></p>',
        ]);

        // Empty tags must be normalized to null
        $this->assertNull($product->short_description);
        $this->assertNull($product->description);

        // Meaningful formatting must be preserved
        $product->description = '<p>Handcrafted using <strong>100% pure Mulberry silk</strong>.</p>';
        $product->save();

        $this->assertEquals('<p>Handcrafted using <strong>100% pure Mulberry silk</strong>.</p>', $product->description);

        $product->delete();
    }
}
