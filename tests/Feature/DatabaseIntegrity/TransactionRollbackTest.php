<?php

declare(strict_types=1);

namespace Tests\Feature\DatabaseIntegrity;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Inventory\StockLevel;
use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Accounting\AccountingService;
use App\Services\Inventory\InventoryService;
use App\Services\OfflineSaleService;
use App\Services\Settings\SettingsService;
use Database\Seeders\ModuleSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionRollbackTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingService $accountingService;
    protected OfflineSaleService $posService;
    protected InventoryService $inventoryService;
    protected SettingsService $settingsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSettingsSeeder::class);
        $this->accountingService = app(AccountingService::class);
        $this->posService = app(OfflineSaleService::class);
        $this->inventoryService = app(InventoryService::class);
        $this->settingsService = app(SettingsService::class);
    }

    /**
     * Verify that an unbalance/failed journal voucher aborts completely without leaving half-posted vouchers or lines.
     */
    public function test_failed_journal_voucher_rolls_back_atomically_without_orphan_lines(): void
    {
        $this->accountingService->ensureDefaultChartOfAccounts();
        $bankAcc = Account::where('account_number', '1120')->first() ?: Account::where('account_number', '2410')->firstOrFail();
        $salesAcc = Account::where('account_number', '4120')->first() ?: Account::where('account_number', '1010')->firstOrFail();

        $initialEntriesCount = JournalEntry::count();
        $initialLinesCount = JournalEntryLine::count();

        // Attempt to post an UNBALANCED voucher (Debit 1000, Credit 500)
        try {
            $this->accountingService->postJournalEntry([
                'voucher_date' => date('Y-m-d'),
                'description' => 'Faulty unbalanced entry',
            ], [
                [
                    'account_id' => $bankAcc->id,
                    'debit' => 1000.00,
                    'credit' => 0.00,
                ],
                [
                    'account_id' => $salesAcc->id,
                    'debit' => 0.00,
                    'credit' => 500.00, // Discrepancy of 500 NPR!
                ],
            ]);
            $this->fail("Expected RuntimeException was not thrown for unbalanced voucher.");
        } catch (\RuntimeException $e) {
            $this->assertTrue(
                str_contains($e->getMessage(), 'not balanced') || str_contains($e->getMessage(), 'Bilag er ikke i balance'),
                "Exception should indicate voucher is unbalanced. Got: {$e->getMessage()}"
            );
        }

        // Verify zero new journal entries and zero new lines exist
        $this->assertEquals($initialEntriesCount, JournalEntry::count());
        $this->assertEquals($initialLinesCount, JournalEntryLine::count());
    }

    /**
     * Verify that reversal operations are strictly atomic: reversal voucher and original status flip together.
     */
    public function test_journal_reversal_is_strictly_atomic(): void
    {
        $this->accountingService->ensureDefaultChartOfAccounts();
        $bankAcc = Account::where('account_number', '1120')->first() ?: Account::where('account_number', '2410')->firstOrFail();
        $salesAcc = Account::where('account_number', '4120')->first() ?: Account::where('account_number', '1010')->firstOrFail();

        // 1. Post valid initial voucher
        $original = $this->accountingService->postJournalEntry([
            'voucher_date' => date('Y-m-d'),
            'description' => 'Original Sale Voucher',
        ], [
            ['account_id' => $bankAcc->id, 'debit' => 500.00, 'credit' => 0.00],
            ['account_id' => $salesAcc->id, 'debit' => 0.00, 'credit' => 500.00],
        ]);

        $this->assertEquals('posted', $original->status);

        // 2. Perform reversal
        $reversal = $this->accountingService->reverseJournalEntry($original, 'Correction of erroneous entry');

        $original->refresh();
        $this->assertEquals('reversed', $original->status);
        $this->assertEquals($reversal->id, $original->reversed_by_entry_id);
        $this->assertEquals('posted', $reversal->status);
        $this->assertEquals($original->id, $reversal->reversal_of_entry_id);

        // Verify reversed lines
        $this->assertCount(2, $reversal->lines);
        $revBankLine = $reversal->lines->firstWhere('account_id', $bankAcc->id);
        $this->assertEquals(500.00, (float)$revBankLine->credit);
        $this->assertEquals(0.00, (float)$revBankLine->debit);
    }

    /**
     * Verify that if POS inventory deduction fails, the whole sale rolls back with zero orphan records.
     */
    public function test_pos_sale_rolls_back_completely_if_inventory_deduction_fails(): void
    {
        $this->settingsService->set('inventory', 'negative_stock_policy', 'strictly_forbidden');
        $this->settingsService->set('pos', 'auto_pos_inventory_deduction', true);

        $product = Product::create([
            'name' => 'Cashmere Shawl Limited',
            'slug' => 'cashmere-shawl-limited',
            'sku' => 'CS-LTD-01',
            'price' => 800.00,
            'price_npr' => 800.00,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'CS-LTD-01-RED',
            'color' => 'Red',
            'size' => 'Standard',
            'stock_quantity' => 1,
            'price_npr' => 800.00,
            'is_active' => true,
        ]);

        // Showroom stock level has 1 unit
        $wh = $this->inventoryService->getShowroomWarehouse();
        StockLevel::create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity_on_hand' => 1,
            'unit_cost_npr' => 300.00,
        ]);

        $initialSalesCount = OfflineSale::count();
        $initialItemsCount = OfflineSaleItem::count();

        // Attempt to create a sale for 2 units (available: 1)
        try {
            $this->posService->createSale([
                'customer_name' => 'Greedy Buyer',
                'currency' => 'npr',
                'payment_method' => 'mobilepay',
            ], [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                    'unit_price' => 800.00,
                ],
            ]);
            $this->fail("Expected RuntimeException on insufficient stock.");
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        // Verify complete rollback: zero sales, zero items, stock intact
        $this->assertEquals($initialSalesCount, OfflineSale::count());
        $this->assertEquals($initialItemsCount, OfflineSaleItem::count());

        $variant->refresh();
        $this->assertEquals(1, $variant->stock_quantity);

        $stockLevel = StockLevel::where('warehouse_id', $wh->id)->where('product_id', $product->id)->first();
        $this->assertEquals(1, $stockLevel->quantity_on_hand);
    }
}
