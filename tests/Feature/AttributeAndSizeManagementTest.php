<?php

namespace Tests\Feature;

use App\Filament\Resources\AttributeResource;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeAndSizeManagementTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'admin']);

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Admin User',
                'password' => \Illuminate\Support\Facades\Hash::make('Dars@@9861'),
                'role' => 'admin',
                'is_admin' => true,
            ]
        );

        if (!$this->admin->hasRole('Super Admin', 'admin')) {
            $this->admin->assignRole($role);
        }
    }

    public function test_product_attributes_table_has_seeded_presets(): void
    {
        $this->assertGreaterThanOrEqual(15, ProductAttribute::count());

        $sizes = ProductAttribute::sizes()->active()->pluck('name');
        $this->assertTrue($sizes->contains('M (Medium)'));
        $this->assertTrue($sizes->contains('S (Small)'));
        $this->assertTrue($sizes->contains('XL (Extra Large)'));

        $dimensions = ProductAttribute::dimensions()->active()->pluck('name');
        $this->assertTrue($dimensions->contains('Standard Scarf (180cm x 30cm)'));

        $colors = ProductAttribute::colors()->active()->pluck('name');
        $this->assertTrue($colors->contains('Imperial Emerald'));
        $this->assertTrue($colors->contains('Crimson Red'));
    }

    public function test_can_create_custom_size_and_color_attribute(): void
    {
        $size = ProductAttribute::create([
            'type' => 'size',
            'name' => '3XL (Triple Extra Large)',
            'value' => 'Bust: 46-48 in / 117-122 cm',
            'category_group' => 'Apparel',
            'description' => 'Extended plus size.',
            'sort_order' => 65,
            'is_active' => true,
        ]);

        $this->assertNotNull($size->code);
        $this->assertStringStartsWith('SZ-', $size->code);

        $color = ProductAttribute::create([
            'type' => 'color',
            'name' => 'Ruby Garnet',
            'value' => '#7B1113',
            'category_group' => 'Heritage Palette',
            'sort_order' => 280,
            'is_active' => true,
        ]);

        $this->assertEquals('#7B1113', $color->color_hex);
        $this->assertStringStartsWith('CLR-', $color->code);
    }

    public function test_attribute_resource_manages_only_attributes_without_product_dependency(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get('/intadmin/attributes-sizes');
        $response->assertSuccessful();

        // Ensure attribute names on page 1 appear
        $response->assertSee('32');
        $response->assertSee('34');

        // Verify resource model is ProductAttribute
        $this->assertEquals(ProductAttribute::class, AttributeResource::getModel());
    }

    public function test_product_resource_loads_dynamic_size_and_color_options(): void
    {
        $sizes = \App\Filament\Resources\ProductResource::getSizeOptions();
        $flatSizes = [];
        foreach ($sizes as $group) {
            if (is_array($group)) {
                foreach ($group as $k => $v) {
                    $flatSizes[(string) $k] = $v;
                }
            } else {
                $flatSizes[(string) $group] = $group;
            }
        }
        $this->assertArrayHasKey('32', $flatSizes);
        $this->assertArrayHasKey('34', $flatSizes);
        $this->assertArrayHasKey('36', $flatSizes);
        $this->assertArrayHasKey('38', $flatSizes);
        $this->assertArrayHasKey('40', $flatSizes);
        $this->assertArrayHasKey('42', $flatSizes);

        $colors = \App\Filament\Resources\ProductResource::getColorOptions();
        $this->assertArrayHasKey('Black', $colors);
        $this->assertArrayHasKey('White', $colors);
        $this->assertArrayHasKey('Navy', $colors);
        $this->assertArrayHasKey('Red', $colors);
    }
}
