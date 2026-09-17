<?php

namespace Tests\Feature;

use App\Filament\Pages\CycleCountPage;
use App\Filament\Pages\FullStockCountPage;
use App\Models\Inventory\StockCount;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class FullStockCountTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'laijau_staging']);
        config(['database.connections.mysql.host' => '127.0.0.1']);
        config(['database.connections.mysql.username' => 'darshan']);
        config(['database.connections.mysql.password' => 'Dars@@9861']);
        \Illuminate\Support\Facades\DB::purge('mysql');
        \Illuminate\Support\Facades\DB::reconnect('mysql');

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Laijau Admin',
                'password' => 'Laijau2026!',
                'role' => 'admin',
            ]
        );
        $this->admin->update(['role' => 'admin']);

        $this->warehouse = Warehouse::where('is_active', true)->first() ?: Warehouse::firstOrCreate(
            ['code' => 'WH-KTM-MAIN'],
            [
                'name' => 'Laijau Central Fulfillment Hub',
                'is_active' => true,
                'is_default' => true,
            ]
        );

        if (Product::where('is_active', true)->count() === 0) {
            Product::create([
                'name' => 'Nike Air Force 1 07',
                'sku' => 'CW2288-111',
                'barcode' => '194955848271',
                'cost_price' => 7500.00,
                'price_npr' => 12500.00,
                'is_active' => true,
                'brand' => 'Nike',
            ]);
            Product::create([
                'name' => 'Nike Dunk Low Panda',
                'sku' => 'DD1391-100',
                'barcode' => '195244589214',
                'cost_price' => 8500.00,
                'price_npr' => 14500.00,
                'is_active' => true,
                'brand' => 'Nike',
            ]);
        }
    }

    public function test_full_stock_count_page_renders_successfully(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get('/intadmin/inventory/full-stock-count');
        $response->assertStatus(200);
        $response->assertSee('Full Stock Count Sheet');
        $response->assertSee('Cycle Count');
        $response->assertSee('Download Count Sheet');
        $response->assertSee('Import Counts');
        $response->assertSee('Print Count Sheet');
    }

    public function test_full_stock_count_livewire_component_functions_and_filters(): void
    {
        $this->actingAs($this->admin, 'admin');

        Livewire::test(FullStockCountPage::class)
            ->assertSet('selectedWarehouseId', $this->warehouse->id)
            ->assertSet('mode', 'count')
            ->set('search', 'Dunk')
            ->assertStatus(200)
            ->set('statusFilter', 'zero_stock')
            ->assertStatus(200)
            ->set('statusFilter', 'all')
            ->assertStatus(200);
    }

    public function test_barcode_scan_in_cycle_mode_increments_count(): void
    {
        $this->actingAs($this->admin, 'admin');

        $product = Product::where('is_active', true)->firstOrFail();

        Livewire::test(FullStockCountPage::class)
            ->set('mode', 'cycle')
            ->set('barcodeInput', $product->sku)
            ->call('handleBarcodeScan')
            ->assertSet("countedQuantities.p_{$product->id}", 1)
            ->assertSet('lastScannedItem.sku', $product->sku)
            ->call('adjustLastScanned', 5)
            ->assertSet("countedQuantities.p_{$product->id}", 6)
            ->call('adjustLastScanned', -2)
            ->assertSet("countedQuantities.p_{$product->id}", 4)
            ->call('setLastScannedCount', 10)
            ->assertSet("countedQuantities.p_{$product->id}", 10);
    }

    public function test_bulk_paste_and_reconciliation_workflow(): void
    {
        $this->actingAs($this->admin, 'admin');

        $product = Product::where('is_active', true)->firstOrFail();
        $key = "p_{$product->id}";

        // Ensure stock level exists
        $stockLevel = StockLevel::firstOrCreate(
            [
                'warehouse_id' => $this->warehouse->id,
                'product_id' => $product->id,
                'variant_id' => null,
            ],
            [
                'quantity_on_hand' => 15,
                'unit_cost_npr' => 1000.00,
            ]
        );
        $initialQty = (int) $stockLevel->quantity_on_hand;
        $targetQty = $initialQty + 8; // variance = +8

        $pasteData = "{$product->sku}, {$targetQty}";

        Livewire::test(FullStockCountPage::class)
            ->set('selectedWarehouseId', $this->warehouse->id)
            ->set('pasteText', $pasteData)
            ->call('processPaste')
            ->assertSet("countedQuantities.{$key}", $targetQty)
            ->set('reconciliationNotes', 'Audit test reconciliation')
            ->call('confirmReconciliation');

        // Verify StockLevel is updated to exactly physical quantity
        $stockLevel->refresh();
        $this->assertEquals($targetQty, (int) $stockLevel->quantity_on_hand);

        // Verify StockCount audit record was created
        $stockCount = StockCount::latest('id')->firstOrFail();
        $this->assertEquals('reconciled', $stockCount->status);
        $this->assertEquals($this->warehouse->id, $stockCount->warehouse_id);

        // Verify StockMovement was created with count_reconciliation
        $movement = StockMovement::where('reference_type', 'stock_count')
            ->where('reference_id', $stockCount->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals('count_reconciliation', $movement->movement_type);
        $this->assertEquals(8, (int) $movement->quantity);
        $this->assertEquals($initialQty, (int) $movement->quantity_before);
        $this->assertEquals($targetQty, (int) $movement->quantity_after);
    }

    public function test_quick_actions_and_difference_calculations(): void
    {
        $this->actingAs($this->admin, 'admin');

        $product = Product::where('is_active', true)->firstOrFail();
        $key = "p_{$product->id}";

        Livewire::test(FullStockCountPage::class)
            ->set('selectedWarehouseId', $this->warehouse->id)
            ->call('matchSystemQty', $key, 25)
            ->assertSet("countedQuantities.{$key}", 25)
            ->call('setZeroStock', $key)
            ->assertSet("countedQuantities.{$key}", 0)
            ->call('clearItemCount', $key)
            ->assertSet("countedQuantities.{$key}", null);
    }

    public function test_reconciliation_preserves_historical_sales_and_does_not_delete_products(): void
    {
        $this->actingAs($this->admin, 'admin');

        $initialProductCount = Product::count();
        $initialOfflineSalesCount = \App\Models\OfflineSale::count();
        $initialOrdersCount = \App\Models\Order::count();

        $product = Product::where('is_active', true)->firstOrFail();

        // Count to zero
        Livewire::test(FullStockCountPage::class)
            ->set('selectedWarehouseId', $this->warehouse->id)
            ->set("countedQuantities.p_{$product->id}", 0)
            ->set('reconciliationNotes', 'Zero count audit')
            ->call('confirmReconciliation');

        // Verify product was NOT deleted even when physical count is 0
        $this->assertEquals($initialProductCount, Product::count());
        $this->assertDatabaseHas('products', ['id' => $product->id]);

        // Verify historical sales and orders were completely untouched
        $this->assertEquals($initialOfflineSalesCount, \App\Models\OfflineSale::count());
        $this->assertEquals($initialOrdersCount, \App\Models\Order::count());
    }

    public function test_print_count_sheet_endpoint_returns_clean_a4_view(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.full-stock-count.print', [
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Physical Stock Count Sheet');
        $response->assertSee($this->warehouse->name);
        $response->assertSee('Physical Counter');
        $response->assertSee('Verification Auditor');
        $response->assertSee('Warehouse Manager');
        $response->assertDontSee('price_npr');
        $response->assertDontSee('selling_price');
    }

    public function test_multi_format_paste_support_including_scanner_dumps_and_excel_tabs(): void
    {
        $this->actingAs($this->admin, 'admin');

        $p1 = Product::where('is_active', true)->firstOrFail();
        $p2 = Product::where('is_active', true)->where('id', '!=', $p1->id)->first() ?: $p1;

        // 1. Tab separated from Excel
        $tabPaste = "SKU\tQuantity\n{$p1->sku}\t18";
        Livewire::test(FullStockCountPage::class)
            ->set('selectedWarehouseId', $this->warehouse->id)
            ->set('pasteText', $tabPaste)
            ->call('processPaste')
            ->assertSet("countedQuantities.p_{$p1->id}", 18);

        // 2. Colon separated
        $colonPaste = "{$p1->sku}: 24";
        Livewire::test(FullStockCountPage::class)
            ->set('selectedWarehouseId', $this->warehouse->id)
            ->set('pasteText', $colonPaste)
            ->call('processPaste')
            ->assertSet("countedQuantities.p_{$p1->id}", 24);

        // 3. Space separated
        $spacePaste = "{$p1->sku} 30";
        Livewire::test(FullStockCountPage::class)
            ->set('selectedWarehouseId', $this->warehouse->id)
            ->set('pasteText', $spacePaste)
            ->call('processPaste')
            ->assertSet("countedQuantities.p_{$p1->id}", 30);

        // 4. Bare barcode scanner dump list (repeated entries group and increment count)
        $scannerDump = "{$p1->sku}\n{$p1->sku}\n{$p1->sku}";
        Livewire::test(FullStockCountPage::class)
            ->set('selectedWarehouseId', $this->warehouse->id)
            ->set('pasteText', $scannerDump)
            ->call('processPaste')
            ->assertSet("countedQuantities.p_{$p1->id}", 3);
    }

    public function test_cycle_mode_session_views_and_discrepancy_filtering(): void
    {
        $this->actingAs($this->admin, 'admin');

        $p1 = Product::where('is_active', true)->whereDoesntHave('variants')->firstOrFail();

        // Ensure stock level is 10
        StockLevel::updateOrCreate(
            [
                'warehouse_id' => $this->warehouse->id,
                'product_id' => $p1->id,
                'variant_id' => null,
            ],
            [
                'quantity_on_hand' => 10,
                'unit_cost_npr' => 500.00,
            ]
        );

        $component = Livewire::test(FullStockCountPage::class)
            ->set('selectedWarehouseId', $this->warehouse->id)
            ->set('mode', 'cycle')
            ->assertSet('cycleView', 'session');

        // Initially session is empty
        $this->assertCount(0, $component->get('items'));

        // Count p1 with variance (15 != 10)
        $component->set('barcodeInput', $p1->sku)
            ->call('handleBarcodeScan') // count = 1
            ->call('setLastScannedCount', 15) // count = 15
            ->assertSet("countedQuantities.p_{$p1->id}", 15);

        // In session view, p1 appears!
        $sessionItems = $component->get('items');
        $this->assertTrue($sessionItems->contains('item_key', "p_{$p1->id}"));

        // In diff view, p1 appears because 15 != 10
        $component->set('cycleView', 'diff');
        $diffItems = $component->get('items');
        $this->assertTrue($diffItems->contains('item_key', "p_{$p1->id}"));

        // Match system (10 == 10) -> in diff view it should now disappear
        $component->call('matchSystemQty', "p_{$p1->id}", 10);
        $component->set('cycleView', 'diff');
        $this->assertCount(0, $component->get('items'));

        // But in session view, p1 still appears
        $component->set('cycleView', 'session');
        $this->assertCount(1, $component->get('items'));
    }

    public function test_export_stock_count_sheet_endpoint_streams_csv_with_correct_columns(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.full-stock-count.export', [
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        $content = $response->streamedContent();
        $this->assertStringContainsString('Item Key', $content);
        $this->assertStringContainsString('Product Description', $content);
        $this->assertStringContainsString('SKU', $content);
        $this->assertStringContainsString('Physical Qty', $content);
        $this->assertStringContainsString('System Qty', $content);
    }

    public function test_upload_and_import_edited_downloaded_count_sheet_file(): void
    {
        $this->actingAs($this->admin, 'admin');

        $p1 = Product::where('is_active', true)->firstOrFail();
        $key = "p_{$p1->id}";

        // Simulate the exact CSV file downloaded from Print/Download count sheet, with Physical Qty filled in
        $csvContent = "#,Item Key,Product Description,SKU,Barcode,Size,Color,Brand,System Qty,Physical Qty,Unit Cost\n"
            . "1,{$key},\"{$p1->name}\",{$p1->sku},{$p1->barcode},,,Nike,10,35,7500\n";

        $file = UploadedFile::fake()->createWithContent('stock-count-sheet-edited.csv', $csvContent);

        Livewire::test(FullStockCountPage::class)
            ->set('selectedWarehouseId', $this->warehouse->id)
            ->set('importFile', $file)
            ->call('processImport')
            ->assertSet("countedQuantities.{$key}", 35);
    }

    public function test_upload_and_import_xlsx_spreadsheet(): void
    {
        $this->actingAs($this->admin, 'admin');

        $p1 = Product::where('is_active', true)->firstOrFail();
        $key = "p_{$p1->id}";

        // Create a minimal valid .xlsx archive in temp storage
        $tempPath = tempnam(sys_get_temp_dir(), 'test_xlsx_') . '.xlsx';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));

        // Shared strings
        $sharedXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="4" uniqueCount="4">'
            . '<si><t>Item Key</t></si>'
            . '<si><t>SKU</t></si>'
            . '<si><t>Physical Qty</t></si>'
            . '<si><t>' . $p1->sku . '</t></si>'
            . '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $sharedXml);

        // Sheet data: Row 1 has headers (Item Key, SKU, Physical Qty), Row 2 has values (key, sku, 42)
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>'
            . '<row r="1">'
            . '<c r="A1" t="s"><v>0</v></c>'
            . '<c r="B1" t="s"><v>1</v></c>'
            . '<c r="C1" t="s"><v>2</v></c>'
            . '</row>'
            . '<row r="2">'
            . '<c r="A2" t="inlineStr"><is><t>' . $key . '</t></is></c>'
            . '<c r="B2" t="s"><v>3</v></c>'
            . '<c r="C2"><v>42</v></c>'
            . '</row>'
            . '</sheetData>'
            . '</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $xlsxContent = file_get_contents($tempPath);
        $file = UploadedFile::fake()->createWithContent('stock-count-sheet-edited.xlsx', $xlsxContent);

        Livewire::test(FullStockCountPage::class)
            ->set('selectedWarehouseId', $this->warehouse->id)
            ->set('importFile', $file)
            ->call('processImport')
            ->assertSet("countedQuantities.{$key}", 42);

        @unlink($tempPath);
    }

    public function test_cycle_count_dedicated_page_renders_and_functions(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get('/intadmin/inventory/cycle-count');
        $response->assertStatus(200);
        $response->assertSee('Cycle Count & Stock Audit');
        $response->assertSee('Download Count Sheet');
        $response->assertSee('Import Counts');
        $response->assertSee('Full Stock Sheet');

        Livewire::test(CycleCountPage::class)
            ->assertSet('selectedWarehouseId', $this->warehouse->id)
            ->assertSet('mode', 'cycle')
            ->assertSet('cycleView', 'session')
            ->set('cycleView', 'diff')
            ->assertStatus(200)
            ->set('cycleView', 'catalog')
            ->assertStatus(200);
    }
}
