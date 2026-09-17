<?php

namespace Tests\Feature;

use App\Filament\Pages\BarcodeLabelPage;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductSkuService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class BarcodeLabelV2Test extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('email', 'admin@laijau.com')->first()
            ?: User::create([
                'name' => 'Laijau Super Admin',
                'email' => 'admin@laijau.com',
                'password' => bcrypt('password123'),
                'role' => 'admin',
                'is_active' => true,
            ]);
    }

    /**
     * 1. Console Header and Retail Toolbar Rendering
     */
    public function test_barcode_page_renders_with_retail_console_and_toolbar(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BarcodeLabelPage::class)
            ->assertSuccessful()
            ->assertSee('Barcodes & Labels')
            ->assertSee('Create, manage and print product labels.')
            ->assertSee('+ Generate Labels')
            ->assertSee('Print Queue');
    }

    /**
     * 2. First-Class Variant Rows in Table
     */
    public function test_first_class_variant_rows_rendered_in_table(): void
    {
        $uid = substr(uniqid(), -5);
        $product = Product::create([
            'name' => "Everest Trekking Jacket {$uid}",
            'brand' => 'Laijau Heritage',
            'price' => 8500.00,
            'is_active' => true,
        ]);

        $v1 = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Navy',
            'size' => 'L',
            'sku' => "LJ-JKT-{$uid}-NAV-L",
            'barcode' => '2001112223330',
            'price' => 8500.00,
            'stock_quantity' => 15,
            'is_active' => true,
        ]);

        $v2 = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Navy',
            'size' => 'XL',
            'sku' => "LJ-JKT-{$uid}-NAV-XL",
            'barcode' => '2001112223347',
            'price' => 8900.00,
            'stock_quantity' => 8,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(BarcodeLabelPage::class)
            ->assertSee("Everest Trekking Jacket {$uid}")
            ->assertSee('Navy / L')
            ->assertSee('Navy / XL')
            ->assertSee("LJ-JKT-{$uid}-NAV-L")
            ->assertSee("LJ-JKT-{$uid}-NAV-XL")
            ->assertSee('2001112223330')
            ->assertSee('2001112223347')
            ->assertSee('Rs. 8,500')
            ->assertSee('Rs. 8,900');
    }

    /**
     * 3. Server-Side Search matching Product, SKU, Barcode, and Variant Attributes
     */
    public function test_server_side_search_matches_product_and_variants(): void
    {
        $uid = substr(uniqid(), -5);
        $product = Product::create([
            'name' => "Kathmandu Cashmere Shawl {$uid}",
            'brand' => 'Laijau Luxe',
            'sku' => "LJ-SHW-{$uid}",
            'price' => 12000.00,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => "Ruby{$uid}",
            'size' => 'Standard',
            'sku' => "LJ-SHW-{$uid}-RUB-STD",
            'barcode' => '2007778889996',
            'price' => 12000.00,
            'is_active' => true,
        ]);

        // Search by color
        Livewire::actingAs($this->admin)
            ->test(BarcodeLabelPage::class)
            ->set('searchQuery', "Ruby{$uid}")
            ->assertSee("Kathmandu Cashmere Shawl {$uid}")
            ->assertSee("Ruby{$uid}");

        // Search by barcode
        Livewire::actingAs($this->admin)
            ->test(BarcodeLabelPage::class)
            ->set('searchQuery', '2007778889996')
            ->assertSee("Kathmandu Cashmere Shawl {$uid}")
            ->assertSee('2007778889996');
    }

    /**
     * 4. Adding Variant to Queue with Custom Copies
     */
    public function test_add_variant_to_Queue_with_copies(): void
    {
        $uid = substr(uniqid(), -5);
        $product = Product::create([
            'name' => "Handmade Leather Loafers {$uid}",
            'price' => 4500.00,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Tan',
            'size' => '42',
            'sku' => "LJ-SHO-{$uid}-TAN-42",
            'barcode' => '2004445556667',
            'price' => 4500.00,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(BarcodeLabelPage::class)
            ->call('addVariantToQueue', $variant->id, 5)
            ->assertCount('printQueue', 1)
            ->assertSet('totalLabelsCount', 5);
    }

    /**
     * 5. Adding Simple Product to Queue
     */
    public function test_add_simple_product_to_Queue(): void
    {
        $uid = substr(uniqid(), -5);
        $product = Product::create([
            'name' => "Organic Hemp Tote Bag {$uid}",
            'sku' => "LJ-BAG-{$uid}",
            'barcode' => '2003332221115',
            'price' => 950.00,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(BarcodeLabelPage::class)
            ->call('addProductToQueue', $product->id)
            ->assertCount('printQueue', 1)
            ->assertSee("Organic Hemp Tote Bag {$uid}");
    }

    /**
     * 6. Thermal and Sheet Format Configuration
     */
    public function test_label_format_switching_thermal_and_sheet(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BarcodeLabelPage::class)
            ->assertSet('labelFormat', 'thermal')
            ->set('thermalSize', '50x25')
            ->assertSet('thermalSize', '50x25')
            ->set('thermalSize', '50x30')
            ->assertSet('thermalSize', '50x30')
            ->set('labelFormat', 'sheet')
            ->assertSet('labelFormat', 'sheet')
            ->set('sheetLayout', '3x8')
            ->assertSet('sheetLayout', '3x8')
            ->set('sheetLayout', '4x10')
            ->assertSet('sheetLayout', '4x10');
    }

    /**
     * 7. Missing Barcode Detection & Safe 1-Click Generation
     */
    public function test_missing_barcode_detection_and_safe_generation(): void
    {
        $uid = substr(uniqid(), -5);
        $product = Product::create([
            'name' => "Heritage Brass Butter Lamp {$uid}",
            'price' => 1500.00,
            'is_active' => true,
        ]);
        DB::table('products')->where('id', $product->id)->update(['barcode' => null]);
        $product->refresh();

        $component = Livewire::actingAs($this->admin)
            ->test(BarcodeLabelPage::class)
            ->set('searchQuery', "Heritage Brass Butter Lamp {$uid}")
            ->assertSee('⚠ Missing barcode')
            ->call('generateBarcodeForMissingItem', 'p', $product->id);

        $product->refresh();
        $this->assertNotEmpty($product->barcode);
        $this->assertTrue(app(ProductSkuService::class)->validateEan13($product->barcode));
    }

    /**
     * 8. Absolute Non-Destructive Barcode Policy: Existing barcodes are NEVER overwritten
     */
    public function test_existing_barcodes_are_never_overwritten(): void
    {
        $uid = substr(uniqid(), -5);
        $fixedBarcode = '2009876543213'; // Valid EAN-13

        $product = Product::create([
            'name' => "Patan Silver Brooch {$uid}",
            'price' => 3200.00,
            'barcode' => $fixedBarcode,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(BarcodeLabelPage::class)
            ->call('generateBarcodeForMissingItem', 'p', $product->id);

        $product->refresh();
        $this->assertEquals($fixedBarcode, $product->barcode, 'Existing barcode must NEVER be modified or overwritten.');
    }

    /**
     * 9. Bulk Missing Barcodes Generation on Selection
     */
    public function test_bulk_generate_missing_barcodes(): void
    {
        $uid = substr(uniqid(), -5);
        $p1 = Product::create(['name' => "Item Alpha {$uid}", 'barcode' => null, 'is_active' => true]);
        $p2 = Product::create(['name' => "Item Beta {$uid}", 'barcode' => null, 'is_active' => true]);

        Livewire::actingAs($this->admin)
            ->test(BarcodeLabelPage::class)
            ->set('selectedRows', ['p_' . $p1->id, 'p_' . $p2->id])
            ->call('bulkGenerateMissingBarcodesForSelection');

        $p1->refresh();
        $p2->refresh();

        $this->assertNotEmpty($p1->barcode);
        $this->assertNotEmpty($p2->barcode);
        $this->assertTrue(app(ProductSkuService::class)->validateEan13($p1->barcode));
        $this->assertTrue(app(ProductSkuService::class)->validateEan13($p2->barcode));
    }

    /**
     * 10. Bulk Inbound from Products Module (?product_ids=...)
     */
    public function test_bulk_inbound_from_products_module(): void
    {
        $uid = substr(uniqid(), -5);
        $p1 = Product::create(['name' => "Silk Scarf 1 {$uid}", 'price' => 2000, 'barcode' => '2001111111115', 'is_active' => true]);
        $p2 = Product::create(['name' => "Silk Scarf 2 {$uid}", 'price' => 2200, 'barcode' => '2002222222222', 'is_active' => true]);

        Livewire::actingAs($this->admin)
            ->test(BarcodeLabelPage::class, ['product_ids' => $p1->id . ',' . $p2->id])
            ->assertSuccessful()
            ->assertSee("Silk Scarf 1 {$uid}")
            ->assertSee("Silk Scarf 2 {$uid}")
            ->assertCount('printQueue', 2);
    }

    /**
     * 11. Print Queue State Operations (update copies, remove, clear)
     */
    public function test_Queue_state_operations(): void
    {
        $uid = substr(uniqid(), -5);
        $product = Product::create([
            'name' => "Dhaka Topi Traditional {$uid}",
            'barcode' => '2005554443338',
            'price' => 800.00,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(BarcodeLabelPage::class)
            ->call('addProductToQueue', $product->id)
            ->assertCount('printQueue', 1)
            ->call('updateCopies', 0, 10)
            ->assertSet('totalLabelsCount', 10)
            ->call('removeQueueItem', 0)
            ->assertCount('printQueue', 0)
            ->call('addProductToQueue', $product->id)
            ->assertCount('printQueue', 1)
            ->call('clearQueue')
            ->assertCount('printQueue', 0);
    }

    /**
     * 12. Safety & Presentation Check: Zero Stock Impact
     */
    public function test_printing_is_pure_presentation_zero_stock_mutations(): void
    {
        $uid = substr(uniqid(), -5);
        $initialMovementsCount = DB::table('inventory_stock_movements')->count();

        $product = Product::create([
            'name' => "Singing Bowl Bronze {$uid}",
            'barcode' => '2006665554441',
            'quantity' => 12,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(BarcodeLabelPage::class)
            ->call('addProductToQueue', $product->id)
            ->call('updateCopies', 0, 50);

        $finalMovementsCount = DB::table('inventory_stock_movements')->count();
        $this->assertEquals($initialMovementsCount, $finalMovementsCount, 'Barcode quNping and printing must never produce inventory movements.');

        $product->refresh();
        $this->assertEquals(12, $product->quantity, 'Product physical stock on hand must remain unaffected by label printing operations.');
    }
}
