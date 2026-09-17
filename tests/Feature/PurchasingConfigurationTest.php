<?php

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Inventory\InventoryService;
use App\Services\Settings\ConfigurationImpactService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $inventoryService;
    protected SettingsService $settingsService;
    protected AccountingService $accountingService;

    protected User $adminUser;
    protected User $staffUser;
    protected Supplier $activeSupplier;
    protected Supplier $inactiveSupplier;
    protected Warehouse $mainWarehouse;
    protected Product $cashmereProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ModuleSettingsSeeder::class);

        $this->inventoryService = app(InventoryService::class);
        $this->settingsService = app(SettingsService::class);
        $this->accountingService = app(AccountingService::class);

        $this->accountingService->ensureDefaultChartOfAccounts();
        $this->inventoryService->ensureDefaultWarehousesAndSuppliers();

        $this->adminUser = User::factory()->create([
            'name' => 'Sita Sharma',
            'email' => 'sita@laijau.com',
            'role' => 'admin',
        ]);

        $this->staffUser = User::factory()->create([
            'name' => 'Bikash Thapa',
            'email' => 'bikash@laijau.com',
            'role' => 'staff',
        ]);

        $this->mainWarehouse = Warehouse::where('code', InventoryService::DEFAULT_WH_CODE)->first();

        $this->activeSupplier = Supplier::firstOrCreate([
            'code' => 'SUP-KTM-01',
        ], [
            'name' => 'Kathmandu Himalayan Cashmere Ltd',
            'legal_name' => 'Kathmandu Himalayan Cashmere Pvt Ltd',
            'contact_person' => 'Sunil Thapa',
            'email' => 'contact@himalayancashmere.com',
            'country' => 'Nepal',
            'currency' => 'npr',
            'payment_terms' => 'net_30',
            'lead_time_days' => 14,
            'is_active' => true,
        ]);

        $this->inactiveSupplier = Supplier::create([
            'code' => 'SUP-DISQ-02',
            'name' => 'Disqualified Silk Mills',
            'legal_name' => 'Disqualified Silk Mills LLC',
            'contact_person' => 'B. Sharma',
            'email' => 'silk@disqualified.com',
            'country' => 'India',
            'currency' => 'npr',
            'payment_terms' => 'net_14',
            'lead_time_days' => 30,
            'is_active' => false,
        ]);

        $this->cashmereProduct = Product::create([
            'name' => 'Royal Pashmina Shawl Diamond Weave',
            'sku' => 'PAS-ROY-001',
            'price_npr' => 2400.00,
            'cost_price_npr' => 750.00,
            'quantity' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Authoritative dynamic threshold check (50,000 -> 10,000 -> 50,000 npr).
     */
    public function test_po_approval_threshold_dynamically_enforces_executive_signoff(): void
    {
        // Set threshold to 50,000 npr
        $this->settingsService->set('purchasing', 'po_approval_threshold_npr', '50000.00');

        $po1 = $this->createTestPoWithTotal(15000.00);
        $this->inventoryService->submitPurchaseOrder($po1, $this->staffUser);
        $this->assertEquals('submitted', $po1->fresh()->status);

        // Staff CAN approve 15,000 npr because threshold is 50,000 npr
        $this->inventoryService->approvePurchaseOrder($po1, $this->staffUser);
        $this->assertEquals('approved', $po1->fresh()->status);
        $this->assertEquals($this->staffUser->id, $po1->fresh()->approved_by);

        // Change threshold: 50,000 -> 10,000 npr
        $this->settingsService->set('purchasing', 'po_approval_threshold_npr', '10000.00');

        $po2 = $this->createTestPoWithTotal(15000.00);
        $this->inventoryService->submitPurchaseOrder($po2, $this->staffUser);
        $this->assertEquals('submitted', $po2->fresh()->status);

        // Staff user CANNOT approve 15,000 npr anymore because 15,000 > 10,000 npr threshold
        $staffApprovalFailed = false;
        try {
            $this->inventoryService->approvePurchaseOrder($po2, $this->staffUser);
        } catch (\RuntimeException $e) {
            $staffApprovalFailed = true;
            $this->assertStringContainsString('exceeds your approval threshold of Rs. 10,000.00', $e->getMessage());
            $this->assertStringContainsString('Executive sign-off is required', $e->getMessage());
        }
        $this->assertTrue($staffApprovalFailed, 'Staff approval must fail when PO total exceeds configured threshold.');
        $this->assertEquals('submitted', $po2->fresh()->status);

        // Executive admin user CAN approve the exact same 15,000 npr PO
        $this->inventoryService->approvePurchaseOrder($po2, $this->adminUser);
        $this->assertEquals('approved', $po2->fresh()->status);
        $this->assertEquals($this->adminUser->id, $po2->fresh()->approved_by);

        // Change threshold back: 10,000 -> 50,000 npr
        $this->settingsService->set('purchasing', 'po_approval_threshold_npr', '50000.00');

        $po3 = $this->createTestPoWithTotal(15000.00);
        $this->inventoryService->submitPurchaseOrder($po3, $this->staffUser);
        // Staff CAN approve again!
        $this->inventoryService->approvePurchaseOrder($po3, $this->staffUser);
        $this->assertEquals('approved', $po3->fresh()->status);
    }

    /**
     * Test 2: PO approval required toggle behavior.
     */
    public function test_po_approval_required_toggle_behavior(): void
    {
        // 1. Approval required = true
        $this->settingsService->set('purchasing', 'po_approval_required', '1');
        $po1 = $this->createTestPoWithTotal(3000.00);
        $this->inventoryService->submitPurchaseOrder($po1, $this->staffUser);
        $this->assertEquals('submitted', $po1->fresh()->status);

        // 2. Approval required = false -> auto-approves
        $this->settingsService->set('purchasing', 'po_approval_required', '0');
        $po2 = $this->createTestPoWithTotal(3000.00);
        $this->inventoryService->submitPurchaseOrder($po2, $this->staffUser);
        $this->assertEquals('approved', $po2->fresh()->status);
        $this->assertStringContainsString('Auto-approved', (string)$po2->fresh()->notes);
    }

    /**
     * Test 3: Rejection behavior respects configured policy (rejected, draft, cancelled).
     */
    public function test_rejection_behavior_respects_configured_policy(): void
    {
        // Default: 'rejected'
        $this->settingsService->set('purchasing', 'rejection_behavior', 'rejected');
        $po1 = $this->createTestPoWithTotal(4000.00);
        $this->inventoryService->submitPurchaseOrder($po1, $this->staffUser);
        $this->inventoryService->rejectPurchaseOrder($po1, 'Pricing is higher than agreed contract', $this->adminUser);
        $this->assertEquals('rejected', $po1->fresh()->status);

        // Revert to 'draft' for amendment
        $this->settingsService->set('purchasing', 'rejection_behavior', 'draft');
        $po2 = $this->createTestPoWithTotal(4000.00);
        $this->inventoryService->submitPurchaseOrder($po2, $this->staffUser);
        $this->inventoryService->rejectPurchaseOrder($po2, 'Incorrect delivery destination, please update', $this->adminUser);
        $this->assertEquals('draft', $po2->fresh()->status);
        $this->assertStringContainsString('Rejection (draft)', (string)$po2->fresh()->notes);

        // Permanent 'cancelled'
        $this->settingsService->set('purchasing', 'rejection_behavior', 'cancelled');
        $po3 = $this->createTestPoWithTotal(4000.00);
        $this->inventoryService->submitPurchaseOrder($po3, $this->staffUser);
        $this->inventoryService->rejectPurchaseOrder($po3, 'Project discontinued', $this->adminUser);
        $this->assertEquals('cancelled', $po3->fresh()->status);
    }

    /**
     * Test 4: Receiving tolerance percentage and over-receipt policy enforcement.
     */
    public function test_receiving_tolerance_percentage_and_over_receipt_handling(): void
    {
        // 5% tolerance: ordered 20 units -> max allowed = ceil(20 * 1.05) = 21 units
        $this->settingsService->set('purchasing', 'receiving_tolerance_percentage', '5.00');
        $this->settingsService->set('purchasing', 'over_receipt_handling', 'reject');

        $po = $this->createTestPoWithItemQuantity(20, 500.00);
        $item = $po->items->first();
        $this->inventoryService->submitPurchaseOrder($po, $this->staffUser);
        $this->inventoryService->approvePurchaseOrder($po, $this->adminUser);

        // Receiving 21 units (within 5% tolerance) -> Succeeds!
        $this->inventoryService->receivePurchaseOrder($po, [$item->id => 21], $this->staffUser);
        $this->assertEquals('received', $po->fresh()->status);
        $this->assertEquals(21, $item->fresh()->quantity_received);

        // Create new PO for 10 units: max allowed = ceil(10 * 1.05) = 11 units. Attempting 12 units -> Fails!
        $po2 = $this->createTestPoWithItemQuantity(10, 500.00);
        $item2 = $po2->items->first();
        $this->inventoryService->submitPurchaseOrder($po2, $this->staffUser);
        $this->inventoryService->approvePurchaseOrder($po2, $this->adminUser);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Over-receipt rejected');
        $this->inventoryService->receivePurchaseOrder($po2, [$item2->id => 12], $this->staffUser);
    }

    /**
     * Test 5: Allow partial receiving setting enforcement.
     */
    public function test_allow_partial_receiving_configuration_enforcement(): void
    {
        // Disable partial receiving
        $this->settingsService->set('purchasing', 'allow_partial_receiving', '0');

        $po = $this->createTestPoWithItemQuantity(10, 500.00);
        $item = $po->items->first();
        $this->inventoryService->submitPurchaseOrder($po, $this->staffUser);
        $this->inventoryService->approvePurchaseOrder($po, $this->adminUser);

        $partialFailed = false;
        try {
            $this->inventoryService->receivePurchaseOrder($po, [$item->id => 4], $this->staffUser);
        } catch (\RuntimeException $e) {
            $partialFailed = true;
            $this->assertStringContainsString('Partial goods receiving is prohibited', $e->getMessage());
        }
        $this->assertTrue($partialFailed);

        // Re-enable partial receiving -> Partial receipt succeeds!
        $this->settingsService->set('purchasing', 'allow_partial_receiving', '1');
        $this->inventoryService->receivePurchaseOrder($po, [$item->id => 4], $this->staffUser);
        $this->assertEquals('partially_received', $po->fresh()->status);
        $this->assertEquals(4, $item->fresh()->quantity_received);
    }

    /**
     * Test 6: Inactive supplier enforcement blocks PO creation, submission, and approval.
     */
    public function test_enforce_active_supplier_policy(): void
    {
        $this->settingsService->set('purchasing', 'enforce_active_supplier', '1');

        // Creating PO with inactive supplier fails
        $creationFailed = false;
        try {
            $this->inventoryService->createPurchaseOrder([
                'supplier_id' => $this->inactiveSupplier->id,
                'warehouse_id' => $this->mainWarehouse->id,
            ], $this->staffUser);
        } catch (\RuntimeException $e) {
            $creationFailed = true;
            $this->assertStringContainsString('is marked inactive', $e->getMessage());
        }
        $this->assertTrue($creationFailed);

        // Submitting manually created PO with inactive supplier fails
        $po = PurchaseOrder::create([
            'po_number' => 'PO-INACT-001',
            'supplier_id' => $this->inactiveSupplier->id,
            'warehouse_id' => $this->mainWarehouse->id,
            'order_date' => now()->toDateString(),
            'currency' => 'npr',
            'status' => 'draft',
            'total_amount_npr' => 2000.00,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is marked inactive');
        $this->inventoryService->submitPurchaseOrder($po, $this->staffUser);
    }

    /**
     * Test 7: Unapproved PO cannot be received.
     */
    public function test_unapproved_po_cannot_be_received(): void
    {
        $po = $this->createTestPoWithItemQuantity(5, 400.00);
        $item = $po->items->first();

        // Draft PO receipt blocked
        $draftBlocked = false;
        try {
            $this->inventoryService->receivePurchaseOrder($po, [$item->id => 5], $this->staffUser);
        } catch (\RuntimeException $e) {
            $draftBlocked = true;
            $this->assertStringContainsString('Approval is required before receiving', $e->getMessage());
        }
        $this->assertTrue($draftBlocked);

        // Submitted PO receipt blocked
        $this->inventoryService->submitPurchaseOrder($po, $this->staffUser);
        $submittedBlocked = false;
        try {
            $this->inventoryService->receivePurchaseOrder($po, [$item->id => 5], $this->staffUser);
        } catch (\RuntimeException $e) {
            $submittedBlocked = true;
            $this->assertStringContainsString('Approval is required before receiving', $e->getMessage());
        }
        $this->assertTrue($submittedBlocked);
    }

    /**
     * Test 8: Full Purchasing Lifecycle traced to Stock Ledger & General Ledger Account 2210.
     */
    public function test_full_purchasing_lifecycle_posts_to_stock_ledger_and_gl_2210(): void
    {
        // 1. Create Draft PO
        $po = $this->inventoryService->createPurchaseOrder([
            'supplier_id' => $this->activeSupplier->id,
            'warehouse_id' => $this->mainWarehouse->id,
            'total_amount_npr' => 7500.00,
        ], $this->staffUser);

        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->cashmereProduct->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_cost_currency' => 750.00,
            'unit_cost_npr' => 750.00,
            'total_cost_npr' => 7500.00,
        ]);
        $po->recalculateTotals();
        $po->save();

        $this->assertEquals('draft', $po->status);

        // 2. Submit PO
        $this->inventoryService->submitPurchaseOrder($po, $this->staffUser);
        $this->assertEquals('submitted', $po->fresh()->status);

        // 3. Approve PO
        $this->inventoryService->approvePurchaseOrder($po, $this->adminUser);
        $this->assertEquals('approved', $po->fresh()->status);

        // 4. Goods Receiving
        $this->inventoryService->receivePurchaseOrder($po, [$item->id => 10], $this->staffUser);
        $this->assertEquals('received', $po->fresh()->status);

        // 5. Verify Multi-Location Stock Updated
        $stockLevel = StockLevel::where('warehouse_id', $this->mainWarehouse->id)
            ->where('product_id', $this->cashmereProduct->id)
            ->first();
        $this->assertNotNull($stockLevel);
        $this->assertEquals(10, $stockLevel->quantity_on_hand);

        // 6. Verify Stock Movement Ledger
        $movement = StockMovement::where('reference_type', 'purchase_order')
            ->where('reference_id', $po->id)
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals('purchase_receive', $movement->movement_type);
        $this->assertEquals(10, $movement->quantity);
        $this->assertEquals(750.00, (float)$movement->unit_cost_npr);

        // 7. Verify nepalese Bookkeeping GL Journal Entry (Account 2210 Debit / Account 2710 Credit)
        $this->assertNotNull($po->fresh()->journal_entry_id);
        $voucher = $po->fresh()->journalEntry;
        $this->assertNotNull($voucher);
        $this->assertEquals('posted', $voucher->status);

        $invAccount = Account::where('account_number', '1210')->first() ?: Account::where('account_number', '2210')->first();
        $payablesAccount = Account::where('account_number', '2110')->first() ?: Account::where('account_number', '2710')->first();

        $invLine = $voucher->lines()->where('account_id', $invAccount->id)->first();
        $payLine = $voucher->lines()->where('account_id', $payablesAccount->id)->first();

        $this->assertNotNull($invLine, 'Inventory Asset account (1210) must be debited.');
        $this->assertEquals(7500.00, (float)$invLine->debit);
        $this->assertEquals(0.00, (float)$invLine->credit);

        $this->assertNotNull($payLine, 'Accounts Payable account (2110) must be credited.');
        $this->assertEquals(0.00, (float)$payLine->debit);
        $this->assertEquals(7500.00, (float)$payLine->credit);
    }

    /**
     * Test 9: ConfigurationImpactService flags PO threshold modification as High Severity.
     */
    public function test_configuration_impact_evaluation_for_po_threshold(): void
    {
        $impact = ConfigurationImpactService::evaluateImpact(
            'purchasing',
            'po_approval_threshold_npr',
            '50000.00',
            '10000.00'
        );

        $this->assertEquals('purchasing', $impact['module']);
        $this->assertEquals('po_approval_threshold_npr', $impact['key']);
        $this->assertEquals('high', $impact['severity']);
        $this->assertStringContainsString('HIGH IMPACT', $impact['impact_text']);
        $this->assertStringContainsString('alters the purchasing authorization boundary', $impact['impact_text']);
        $this->assertStringContainsString('Rs. 50,000.00 to Rs. 10,000.00', $impact['impact_text']);
    }

    // --- Helper Methods ---

    protected function createTestPoWithTotal(float $totalnpr): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'po_number' => PurchaseOrder::generateNextPoNumber(),
            'supplier_id' => $this->activeSupplier->id,
            'warehouse_id' => $this->mainWarehouse->id,
            'order_date' => now()->toDateString(),
            'currency' => 'npr',
            'exchange_rate_to_npr' => 1.0,
            'total_amount_npr' => $totalnpr,
            'status' => 'draft',
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->cashmereProduct->id,
            'quantity_ordered' => 1,
            'quantity_received' => 0,
            'unit_cost_currency' => $totalnpr,
            'unit_cost_npr' => $totalnpr,
            'total_cost_npr' => $totalnpr,
        ]);

        return $po;
    }

    protected function createTestPoWithItemQuantity(int $quantity, float $unitCost): PurchaseOrder
    {
        $total = round($quantity * $unitCost, 2);
        $po = PurchaseOrder::create([
            'po_number' => PurchaseOrder::generateNextPoNumber(),
            'supplier_id' => $this->activeSupplier->id,
            'warehouse_id' => $this->mainWarehouse->id,
            'order_date' => now()->toDateString(),
            'currency' => 'npr',
            'exchange_rate_to_npr' => 1.0,
            'total_amount_npr' => $total,
            'status' => 'draft',
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->cashmereProduct->id,
            'quantity_ordered' => $quantity,
            'quantity_received' => 0,
            'unit_cost_currency' => $unitCost,
            'unit_cost_npr' => $unitCost,
            'total_cost_npr' => $total,
        ]);

        return $po;
    }
}
