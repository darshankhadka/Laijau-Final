<?php

namespace Tests\Feature;

use App\Filament\Pages\QuickStockEntryPage;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuickStockEntryUiTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    protected User $admin;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Laijau Admin',
                'password' => 'Laijau2026!',
                'role' => 'admin',
            ]
        );
        $this->admin->update(['role' => 'admin']);

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'WH-KTM-TEST'],
            [
                'name' => 'Kathmandu Test Warehouse',
                'is_active' => true,
                'is_default' => true,
            ]
        );
    }

    public function test_quick_stock_page_renders_with_retail_console_ui(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get('/intadmin/quick-stock-entry');
        $response->assertStatus(200);
        $response->assertSee('lj-qs-root');
        $response->assertSee('Quick Stock Entry');
        $response->assertSee('Barcode Scanner Mode');
        $response->assertSee('Size Matrix Grid Mode');
    }

    public function test_barcode_scanner_lookup_and_quantity_presets(): void
    {
        $this->actingAs($this->admin, 'admin');

        $testBarcode = '999' . str_pad((string) rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
        $product = Product::create([
            'name' => 'Linen Retail Shirt',
            'slug' => 'linen-retail-shirt-' . uniqid(),
            'sku' => 'LJ-SHI-' . uniqid(),
            'barcode' => $testBarcode,
            'price' => 2999,
            'is_active' => true,
            'track_quantity' => true,
            'quantity' => 20,
        ]);

        StockLevel::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 0,
        ]);

        Livewire::test(QuickStockEntryPage::class)
            ->set('selectedWarehouseId', $this->warehouse->id)
            ->set('scanInput', $testBarcode)
            ->call('handleScan')
            ->assertSee('Linen Retail Shirt')
            ->assertSee('SKU: ' . $product->sku)
            ->assertSet('quantityToAdd', 1)
            ->call('setQuantityPreset', 10)
            ->assertSet('quantityToAdd', 10)
            ->call('adjustQuantity', 5)
            ->assertSet('quantityToAdd', 15)
            ->call('commitScan')
            ->assertSet('scannedItem', null)
            ->assertCount('recentScans', 1);

        $stock = StockLevel::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertEquals(35, $stock->quantity_on_hand, 'Stock must increment atomically from 20 to 35');
    }

    public function test_matrix_grid_mode_renders_variant_sizes(): void
    {
        $this->actingAs($this->admin, 'admin');

        $product = Product::create([
            'name' => 'Woolen Knit Sweater',
            'slug' => 'woolen-knit-sweater-' . uniqid(),
            'sku' => 'LJ-SWT-' . uniqid(),
            'price' => 4500,
            'is_active' => true,
            'track_quantity' => true,
            'quantity' => 0,
        ]);

        $v1 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $product->sku . '-M',
            'size' => 'M',
            'color' => 'Navy',
            'price' => 4500,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $v2 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $product->sku . '-L',
            'size' => 'L',
            'color' => 'Navy',
            'price' => 4500,
            'stock_quantity' => 8,
            'is_active' => true,
        ]);

        Livewire::test(QuickStockEntryPage::class)
            ->set('mode', 'matrix')
            ->set('matrixProductId', $product->id)
            ->set('matrixColor', 'Navy')
            ->assertSee('M')
            ->assertSee('L')
            ->assertSee($product->sku . '-M')
            ->assertSee($product->sku . '-L')
            ->assertSee('Receive Stock Across All Sizes');
    }
}
