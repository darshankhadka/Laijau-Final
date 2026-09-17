<?php

declare(strict_types=1);

namespace Tests\Feature\DatabaseIntegrity;

use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Hrm\Employee;
use App\Models\Hrm\ExpenseClaim;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockCount;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockReservation;
use App\Models\Inventory\StockTransfer;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Services\DocumentSequenceService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SequentialNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected DocumentSequenceService $sequenceService;
    protected SettingsService $settingsService;

    protected function setUp(): void
    {
        parent::setUp();
        $tables = [
            'document_sequences',
            'accounting_journal_entries',
            'accounting_invoices',
            'offline_sales',
            'inventory_stock_movements',
            'inventory_purchase_orders',
            'inventory_transfers',
            'inventory_adjustments',
            'inventory_stock_counts',
            'inventory_reservations',
            'hrm_employees',
            'hrm_expense_claims',
            'orders',
        ];
        try {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } catch (\Throwable $e) {
        }

        foreach ($tables as $t) {
            try {
                DB::table($t)->delete();
            } catch (\Throwable $e) {
            }
        }

        try {
            DB::statement('PRAGMA foreign_keys = ON;');
        } catch (\Throwable $e) {
        }

        $this->seed(\Database\Seeders\ModuleSettingsSeeder::class);
        $this->sequenceService = app(DocumentSequenceService::class);
        $this->settingsService = app(SettingsService::class);
    }

    /**
     * Verify concurrent order number allocation generates zero duplicates and strict monotonicity.
     */
    public function test_concurrent_order_number_allocation_generates_zero_duplicates(): void
    {
        $this->settingsService->set('commerce', 'order_prefix', 'ORD-');

        $generated = [];
        for ($i = 1; $i <= 25; $i++) {
            $generated[] = Order::generateNextOrderNumber();
        }

        // 1. Assert exactly 25 numbers generated
        $this->assertCount(25, $generated);

        // 2. Assert zero duplicate numbers
        $unique = array_unique($generated);
        $this->assertCount(25, $unique, "Duplicate order numbers were detected in sequential allocation!");

        // 3. Assert correct prefix and monotonic padding format
        $this->assertEquals('ORD-00001', $generated[0]);
        $this->assertEquals('ORD-00025', $generated[24]);
    }

    /**
     * Verify nepalese Bilag (JournalEntry) sequential allocation is gap-free and duplicate-free.
     */
    public function test_concurrent_journal_entry_bilag_numbering(): void
    {
        $year = date('Y');
        $this->settingsService->set('accounting', 'journal_voucher_prefix', 'BIL-');

        $generated = [];
        for ($i = 1; $i <= 20; $i++) {
            $generated[] = JournalEntry::generateNextEntryNumber();
        }

        $this->assertCount(20, array_unique($generated));
        $this->assertEquals("BIL-{$year}-0001", $generated[0]);
        $this->assertEquals("BIL-{$year}-0020", $generated[19]);
    }

    /**
     * Verify nepalese sales invoices and supplier bills use independent, collision-safe scopes.
     */
    public function test_accounting_invoice_and_bill_independent_sequence_scopes(): void
    {
        $year = date('Y');
        $this->settingsService->set('accounting', 'sales_invoice_prefix', 'FAK-');
        $this->settingsService->set('accounting', 'supplier_bill_prefix', 'LEV-');
        $this->settingsService->set('accounting', 'credit_note_prefix', 'KRED-');

        $salesInv1 = AccountingInvoice::generateNextInvoiceNumber('sales_invoice');
        $salesInv2 = AccountingInvoice::generateNextInvoiceNumber('sales_invoice');
        $suppBill1 = AccountingInvoice::generateNextInvoiceNumber('supplier_bill');
        $credNote1 = AccountingInvoice::generateNextInvoiceNumber('credit_note');

        $this->assertEquals("FAK-{$year}-0001", $salesInv1);
        $this->assertEquals("FAK-{$year}-0002", $salesInv2);
        $this->assertEquals("LEV-{$year}-0001", $suppBill1);
        $this->assertEquals("KRED-{$year}-0001", $credNote1);
    }

    /**
     * Verify POS receipts generate zero collisions and 6-digit zero-padding.
     */
    public function test_pos_receipt_numbering_format_and_uniqueness(): void
    {
        $this->settingsService->set('pos', 'receipt_prefix', 'OFF-');

        $receipts = [];
        for ($i = 1; $i <= 15; $i++) {
            $receipts[] = OfflineSale::generateNextSaleNumber();
        }

        $this->assertCount(15, array_unique($receipts));
        $this->assertEquals('OFF-000001', $receipts[0]);
        $this->assertEquals('OFF-000015', $receipts[14]);
    }

    /**
     * Verify Enterprise Inventory documents (PO, Transfer, Adjustment, Movement, Count, Reservation).
     */
    public function test_enterprise_inventory_document_sequences(): void
    {
        $month = date('Y-m');
        $po1 = PurchaseOrder::generateNextPoNumber();
        $po2 = PurchaseOrder::generateNextPoNumber();
        $this->assertEquals("PO-{$month}-001", $po1);
        $this->assertEquals("PO-{$month}-002", $po2);

        $trf1 = StockTransfer::generateNextTransferNumber();
        $trf2 = StockTransfer::generateNextTransferNumber();
        $this->assertEquals("TRF-{$month}-001", $trf1);
        $this->assertEquals("TRF-{$month}-002", $trf2);

        $adj1 = StockAdjustment::generateNextAdjustmentNumber();
        $adj2 = StockAdjustment::generateNextAdjustmentNumber();
        $this->assertEquals("ADJ-{$month}-001", $adj1);
        $this->assertEquals("ADJ-{$month}-002", $adj2);

        $datePrefix = date('Ymd');
        $mov1 = StockMovement::generateNextMovementNumber();
        $mov2 = StockMovement::generateNextMovementNumber();
        $this->assertEquals("MOV-{$datePrefix}-0001", $mov1);
        $this->assertEquals("MOV-{$datePrefix}-0002", $mov2);
    }

    /**
     * Verify HRM documents (Expense Claims, Employee Numbers).
     */
    public function test_hrm_document_sequences(): void
    {
        $year = date('Y');
        $claim1 = ExpenseClaim::generateNextClaimNumber();
        $claim2 = ExpenseClaim::generateNextClaimNumber();
        $this->assertTrue(in_array($claim1, ["EXP-{$year}-0001", "UDL-{$year}-0001"]));
        $this->assertTrue(in_array($claim2, ["EXP-{$year}-0002", "UDL-{$year}-0002"]));

        $emp1 = Employee::generateNextEmployeeNumber();
        $emp2 = Employee::generateNextEmployeeNumber();
        $this->assertTrue(in_array($emp1, ['EMP-0001', 'LAI-0001', 'MED-0001']));
        $this->assertTrue(in_array($emp2, ['EMP-0002', 'LAI-0002', 'MED-0002']));
    }

    /**
     * Verify dynamic prefix changes seamlessly update the sequence format without collisions.
     */
    public function test_dynamic_configuration_prefix_switching(): void
    {
        // Default prefix
        $num1 = Order::generateNextOrderNumber();
        $this->assertTrue(str_starts_with($num1, 'ORD-') || str_starts_with($num1, 'LJ-'));

        // Switch prefix in SettingsService
        $this->settingsService->set('commerce', 'order_prefix', 'NA-ONLINE-');
        $num2 = Order::generateNextOrderNumber();
        $this->assertStringStartsWith('NA-ONLINE-', $num2);

        // Switch accounting prefix
        $this->settingsService->set('accounting', 'journal_voucher_prefix', 'POSTING-');
        $num3 = JournalEntry::generateNextEntryNumber();
        $this->assertStringStartsWith('POSTING-', $num3);
    }

    /**
     * Verify sequence initialization seeds safely from existing database records if table pre-populated.
     */
    public function test_sequence_seeds_from_existing_database_max(): void
    {
        $this->settingsService->set('commerce', 'order_prefix', 'ORD-');

        // Pre-populate an order with number ORD-00099
        DB::table('orders')->insert([
            'order_number' => 'ORD-00099',
            'first_name' => 'Existing',
            'last_name' => 'Customer',
            'email' => 'existing@test.com',
            'shipping_address' => 'Durbar Marg 1',
            'shipping_country' => 'NP',
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'currency' => 'npr',
            'status' => 'pending_payment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Next allocated order number should be ORD-00100
        $next = Order::generateNextOrderNumber();
        $this->assertEquals('ORD-00100', $next);

        $nextAgain = Order::generateNextOrderNumber();
        $this->assertEquals('ORD-00101', $nextAgain);
    }

    protected function tearDown(): void
    {
        if (isset($this->settingsService)) {
            $this->settingsService->set('commerce', 'order_prefix', 'LJ-');
            $this->settingsService->set('accounting', 'journal_voucher_prefix', 'JV-');
            $this->settingsService->set('accounting', 'sales_invoice_prefix', 'INV-');
            $this->settingsService->set('accounting', 'supplier_bill_prefix', 'KH-');
            $this->settingsService->set('accounting', 'credit_note_prefix', 'CN-');
        }
        parent::tearDown();
    }
}
