<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Laijau Administrator',
                'password' => bcrypt('Laijau2026!'),
                'role' => 'admin',
            ]
        );

        $this->viewer = User::firstOrCreate(
            ['email' => 'viewer_test@laijau.com'],
            [
                'name' => 'Store Viewer',
                'password' => bcrypt('password'),
                'role' => 'viewer',
            ]
        );
    }

    public function test_category_creation_auto_generates_slug(): void
    {
        $category = Category::create([
            'name' => 'Handcrafted Pashmina Shawls ' . Str::random(5),
            'is_active' => true,
        ]);

        $this->assertNotEmpty($category->slug);
        $this->assertStringContainsString('handcrafted-pashmina-shawls', $category->slug);
    }

    public function test_category_custom_slug_is_preserved(): void
    {
        $customSlug = 'custom-luxury-silk-' . Str::random(5);
        $category = Category::create([
            'name' => 'Luxury Silk Fabric',
            'slug' => $customSlug,
            'is_active' => true,
        ]);

        $this->assertEquals($customSlug, $category->slug);
    }

    public function test_category_hierarchy_and_breadcrumbs(): void
    {
        $root = Category::create([
            'name' => 'Women ' . Str::random(4),
            'is_active' => true,
        ]);

        $level1 = Category::create([
            'name' => 'Apparel ' . Str::random(4),
            'parent_id' => $root->id,
            'is_active' => true,
        ]);

        $level2 = Category::create([
            'name' => 'Casual Shirts ' . Str::random(4),
            'parent_id' => $level1->id,
            'is_active' => true,
        ]);

        // Verify parent-child relationships
        $this->assertEquals($root->id, $level1->parent->id);
        $this->assertEquals($level1->id, $level2->parent->id);
        $this->assertTrue($root->children->contains($level1));
        $this->assertTrue($level1->children->contains($level2));

        // Test getAllChildrenIds() recursive resolution
        $childrenIds = $root->getAllChildrenIds();
        $this->assertContains($level1->id, $childrenIds);
        $this->assertContains($level2->id, $childrenIds);

        // Test hierarchy breadcrumb path
        $expectedPath = "{$root->name} > {$level1->name} > {$level2->name}";
        $this->assertEquals($expectedPath, $level2->hierarchy_path);
    }

    public function test_category_scopes(): void
    {
        $activeTop = Category::create([
            'name' => 'Active Top ' . Str::random(4),
            'is_active' => true,
            'is_featured' => true,
            'parent_id' => null,
        ]);

        $inactiveTop = Category::create([
            'name' => 'Inactive Top ' . Str::random(4),
            'is_active' => false,
            'is_featured' => false,
            'parent_id' => null,
        ]);

        $activeSub = Category::create([
            'name' => 'Active Sub ' . Str::random(4),
            'parent_id' => $activeTop->id,
            'is_active' => true,
            'is_featured' => false,
        ]);

        $activeCategories = Category::active()->pluck('id');
        $this->assertContains($activeTop->id, $activeCategories);
        $this->assertNotContains($inactiveTop->id, $activeCategories);

        $featuredCategories = Category::featured()->pluck('id');
        $this->assertContains($activeTop->id, $featuredCategories);
        $this->assertNotContains($activeSub->id, $featuredCategories);

        $topLevelCategories = Category::topLevel()->pluck('id');
        $this->assertContains($activeTop->id, $topLevelCategories);
        $this->assertNotContains($activeSub->id, $topLevelCategories);
    }

    public function test_parent_category_resolves_all_descendant_products(): void
    {
        $parent = Category::create([
            'name' => 'Footwear Collection ' . Str::random(4),
            'is_active' => true,
        ]);

        $child = Category::create([
            'name' => 'Leather Boots ' . Str::random(4),
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        $productDirect = Product::create([
            'name' => 'Classic Derby Shoes ' . Str::random(5),
            'slug' => 'classic-derby-shoes-' . Str::random(5),
            'sku' => 'SKU-DRB-' . Str::random(5),
            'price' => 12500,
            'is_published' => true,
            'is_active' => true,
        ]);
        $productDirect->categories()->attach($parent->id);

        $productNested = Product::create([
            'name' => 'Ankle Leather Boots ' . Str::random(5),
            'slug' => 'ankle-leather-boots-' . Str::random(5),
            'sku' => 'SKU-BOT-' . Str::random(5),
            'price' => 8500,
            'is_published' => true,
            'is_active' => true,
        ]);
        $productNested->categories()->attach($child->id);

        // getAllProductsQuery() on parent must find both direct and nested products
        $resolvedProductIds = $parent->getAllProductsQuery()->pluck('products.id')->toArray();
        $this->assertContains($productDirect->id, $resolvedProductIds);
        $this->assertContains($productNested->id, $resolvedProductIds);

        // Child query should only contain child product
        $childProductIds = $child->getAllProductsQuery()->pluck('products.id')->toArray();
        $this->assertNotContains($productDirect->id, $childProductIds);
        $this->assertContains($productNested->id, $childProductIds);
    }

    public function test_storefront_category_page_displays_hierarchical_products_and_subcategories(): void
    {
        $parent = Category::create([
            'name' => 'Outerwear & Jackets ' . Str::random(4),
            'is_active' => true,
        ]);

        $sub = Category::create([
            'name' => 'Winter Coats ' . Str::random(4),
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Crimson Velvet Bridal Piece ' . Str::random(4),
            'slug' => 'crimson-velvet-' . Str::random(5),
            'sku' => 'SKU-VLV-' . Str::random(5),
            'price' => 25000,
            'is_published' => true,
            'is_active' => true,
            'featured_image' => 'products/velvet-bridal.jpg',
        ]);
        $product->categories()->attach($sub->id);

        $response = $this->get('/categories/' . $parent->slug);
        $response->assertStatus(200);
        $response->assertSee($parent->name);
        $response->assertSee($sub->name); // Subcategory pill
        $response->assertSee($product->name); // Product resolved via descendant query
    }

    public function test_cache_clearing_on_category_lifecycle(): void
    {
        Cache::put('public_categories_list', ['test_data']);

        $category = Category::create([
            'name' => 'Cashmere Sweaters ' . Str::random(4),
            'is_active' => true,
        ]);

        // Creating category should clear public_categories_list
        $this->assertFalse(Cache::has('public_categories_list'));

        // Populate slug cache
        Cache::put('category_slug_' . $category->slug, ['detail_data']);
        $this->assertTrue(Cache::has('category_slug_' . $category->slug));

        // Update category
        $category->update(['name' => 'Updated Cashmere ' . Str::random(4)]);
        $this->assertFalse(Cache::has('category_slug_' . $category->slug));
    }

    public function test_api_categories_endpoints(): void
    {
        $category = Category::create([
            'name' => 'Summer Kurtis ' . Str::random(4),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // 1. Categories Index
        $response = $this->getJson('/api/categories');
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);

        // 2. Category Detail by Slug
        $detailResponse = $this->getJson('/api/categories/' . $category->slug);
        $detailResponse->assertStatus(200);
        $detailResponse->assertJsonPath('data.slug', $category->slug);
        $detailResponse->assertJsonPath('data.name', $category->name);
    }

    public function test_category_policy_authorization(): void
    {
        $category = Category::create([
            'name' => 'Policy Test Category ' . Str::random(4),
            'is_active' => true,
        ]);

        $this->assertTrue($this->admin->can('viewAny', Category::class));
        $this->assertTrue($this->admin->can('create', Category::class));
        $this->assertTrue($this->admin->can('update', $category));
        $this->assertTrue($this->admin->can('delete', $category));

        // Viewer should have view-only access
        $this->assertTrue($this->viewer->can('viewAny', Category::class));
        $this->assertFalse($this->viewer->can('create', Category::class));
        $this->assertFalse($this->viewer->can('update', $category));
        $this->assertFalse($this->viewer->can('delete', $category));
    }
}
