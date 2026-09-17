<?php

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BikriKhataEntry;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\KharidKhataEntry;
use App\Models\Accounting\TdsRecord;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\Hrm\Employee;
use App\Models\Hrm\PayrollRun;
use App\Models\Hrm\Timesheet;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\Warehouse;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Hrm\PayrollPreparationService;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaijauCrossModuleOperationalAuditTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingService $accountingService;
    protected InventoryService $inventoryService;
    protected PayrollPreparationService $payrollService;
    protected User $adminUser;
    protected Warehouse $warehouse;
    protected BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountingService = app(AccountingService::class);
        $this->inventoryService = app(InventoryService::class);
        $this->payrollService = app(PayrollPreparationService::class);

        $this->accountingService->ensureDefaultChartOfAccounts();
        $this->payrollService->seedNepalPayrollTaxConfigurations();

        $this->adminUser = User::create([
            'name' => 'Operations Auditor',
            'email' => 'operations.audit@laijau.com',
            'password' => bcrypt('Laijau2026!'),
            'role' => 'admin',
        ]);

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'KTM-SHOWROOM'],
            [
                'name' => 'Kathmandu Central Showroom',
                'location' => 'Durbar Marg, Kathmandu',
                'is_active' => true,
            ]
        );

        $this->bankAccount = BankAccount::firstOrCreate(
            ['name' => 'Nabil Bank Operating Account NPR'],
            [
                'bank_name' => 'Nabil Bank Ltd.',
                'account_number' => '01201017500123',
                'currency' => 'NPR',
                'opening_balance' => 1000000.00,
                'current_balance' => 1000000.00,
            ]
        );
        $this->bankAccount->update(['current_balance' => 1000000.00]);
    }

    /**
     * Audit 1: Commerce -> Inventory -> CRM -> Finance (Bikri Khata + Double-Entry GL + COGS)
     */
    public function test_commerce_order_flows_to_inventory_crm_and_finance_gl(): void
    {
        $u = uniqid();
        $product = Product::create([
            'name' => 'Pashmina Shawl — Royal Ivory ' . $u,
            'slug' => 'pashmina-shawl-royal-ivory-' . $u,
            'sku' => 'PAS-IVO-MAIN-' . $u,
            'price' => 15000.00,
            'cost_price' => 6500.00,
            'quantity' => 10,
            'is_active' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'PAS-IVO-001-' . $u,
            'price' => 15000.00,
            'cost_price' => 6500.00,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $stockLevel = StockLevel::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_on_hand' => 10,
            'unit_cost_npr' => 6500.00,
        ]);

        // Customer & CRM Lead
        $customer = User::create([
            'name' => 'Aarati Sharma',
            'email' => 'client.' . $u . '@gmail.com',
            'password' => bcrypt('Laijau2026!'),
            'role' => 'customer',
        ]);

        $lead = CrmLead::create([
            'title' => 'Inquiry for Pashmina Shawls',
            'contact_name' => $customer->name,
            'email' => $customer->email,
            'phone' => '+977 9801234567',
            'stage' => CrmLead::STAGE_QUALIFIED,
            'user_id' => $customer->id,
        ]);

        // Order Placed & Completed
        $order = Order::create([
            'order_number' => 'ORD-AUDIT-' . $u,
            'channel' => 'online',
            'user_id' => $customer->id,
            'crm_lead_id' => $lead->id,
            'first_name' => 'Aarati',
            'last_name' => 'Sharma',
            'email' => $customer->email,
            'phone' => '+977 9801234567',
            'shipping_address' => 'Baluwatar, Kathmandu',
            'subtotal' => 13274.34,
            'vat_amount' => 1725.66, // 13% Output VAT
            'total_amount' => 15000.00,
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'payment_method' => 'connectips',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => 15000.00,
        ]);

        $order->update([
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
        ]);

        // 1. Inventory Decrement & Movement
        $this->inventoryService->recordStockMovement([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => -1,
            'movement_type' => 'sale_order',
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'user_id' => $this->adminUser->id,
        ]);
        $this->assertEquals(9, $stockLevel->fresh()->quantity_on_hand);

        // 2. CRM Activity Logged
        CrmActivity::create([
            'crm_lead_id' => $lead->id,
            'user_id' => $this->adminUser->id,
            'type' => CrmActivity::TYPE_ORDER_LINKED,
            'description' => "Purchased {$order->order_number} for Rs. 15,000",
        ]);
        $this->assertDatabaseHas('crm_activities', [
            'crm_lead_id' => $lead->id,
            'type' => CrmActivity::TYPE_ORDER_LINKED,
        ]);

        // 3. Finance: Bikri Khata Entry (Annex 7) + General Ledger Voucher
        $order->refresh();
        $glEntry = $this->accountingService->recordOrderSale($order);
        $this->assertInstanceOf(JournalEntry::class, $glEntry);
        $this->assertTrue((bool)$glEntry->is_balanced);


        $bikriEntry = BikriKhataEntry::where('reference_order_id', $order->id)->first();
        $this->assertNotNull($bikriEntry);
        $this->assertNotEmpty($bikriEntry->invoice_number);
        $this->assertEquals($order->id, $bikriEntry->reference_order_id);
        $this->assertEquals(13274.34, round((float)$bikriEntry->taxable_amount, 2));
        $this->assertEquals(1725.66, round((float)$bikriEntry->vat_amount, 2));

        // Verify Output VAT Account (2120) credited
        $this->assertGreaterThan(0.0, (float)$glEntry->lines()->where('account_number', '2120')->sum('credit'));

        // Verify COGS (5110) debited and Inventory (1210) credited
        $this->assertGreaterThan(0.0, (float)$glEntry->lines()->where('account_number', '5110')->sum('debit'));
        $this->assertGreaterThan(0.0, (float)$glEntry->lines()->where('account_number', '1210')->sum('credit'));
    }

    /**
     * Audit 2: Sourcing Procurement -> Kharid Khata -> TDS -> Inventory -> Finance GL
     */
    public function test_procurement_flows_to_kharid_khata_tds_inventory_and_gl(): void
    {
        $u = uniqid();
        $supplier = Supplier::create([
            'code' => 'SUP-AUDIT-' . $u,
            'name' => 'Kathmandu Artisan Weavers Co-op ' . $u,
            'contact_person' => 'Prem Gurung',
            'phone' => '+977 9811223344',
            'tax_vat_number' => '300123456',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Yak Wool Blanket ' . $u,
            'slug' => 'yak-wool-blanket-' . $u,
            'sku' => 'YAK-BLK-MAIN-' . $u,
            'price' => 7000.00,
            'cost_price' => 4000.00,
            'quantity' => 5,
            'is_active' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'YAK-BLK-001-' . $u,
            'price' => 7000.00,
            'cost_price' => 4000.00,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $initialStock = StockLevel::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_on_hand' => 5,
            'unit_cost_npr' => 4000.00,
        ]);

        // Sourcing Bill: 20 items @ Rs. 4,000 = Rs. 80,000 (Exceeds Rs. 50,000 threshold -> 1.5% TDS applies!)
        $taxableAmount = 80000.00;
        $vatRate = 13.00;
        $vatAmount = 10400.00;
        $tdsRate = 1.50;
        $tdsAmount = 1200.00; // 1.5% of 80,000
        $totalAmount = 90400.00;

        $po = PurchaseOrder::create([
            'po_number' => 'PO-AUDIT-' . $u,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'received',
            'subtotal_currency' => $taxableAmount,
            'total_amount_npr' => $totalAmount,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => now()->toDateString(),
        ]);

        // Record Kharid Khata (Purchase Book Annex 8)
        $billData = [
            'invoice_number' => 'BILL-ARTISAN-' . $u,
            'issue_date' => now()->toDateString(),
            'contact_name' => $supplier->name,
            'seller_pan' => $supplier->tax_vat_number,
            'purchase_type' => 'goods_local',
            'taxable_amount' => $taxableAmount,
            'vat_amount' => $vatAmount,
            'tds_rate' => $tdsRate,
            'tds_amount' => $tdsAmount,
            'tds_applicable' => true,
            'reference_purchase_order_id' => $po->id,
        ];

        $kharidEntry = $this->accountingService->recordPurchaseBillKharidKhata($billData);
        $this->assertInstanceOf(KharidKhataEntry::class, $kharidEntry);

        // 1. Verify Kharid Khata columns
        $this->assertEquals(80000.00, (float)$kharidEntry->taxable_amount);
        $this->assertEquals(10400.00, (float)$kharidEntry->vat_amount);
        $this->assertEquals(1200.00, (float)$kharidEntry->tds_amount);

        // 2. Verify Statutory TDS Record Created
        $this->assertDatabaseHas('accounting_tds_records', [
            'payee_pan' => '300123456',
            'tds_amount' => 1200.00,
        ]);

        // 3. Verify Balanced GL Voucher
        $glEntry = $kharidEntry->journalEntry;
        $this->assertInstanceOf(JournalEntry::class, $glEntry);
        $this->assertTrue((bool)$glEntry->is_balanced);

        // Inventory Asset 1210 debited (80,000) & Input VAT 2130 debited (10,400)
        $this->assertEquals(80000.00, (float)$glEntry->lines()->where('account_number', '1210')->sum('debit'));
        $this->assertEquals(10400.00, (float)$glEntry->lines()->where('account_number', '2130')->sum('debit'));

        // Accounts Payable 2110 credited (89,200) & TDS Payable 2140 credited (1,200)
        $this->assertEquals(1200.00, (float)$glEntry->lines()->where('account_number', '2140')->sum('credit'));
        $this->assertEquals(89200.00, (float)$glEntry->lines()->where('account_number', '2110')->sum('credit'));
    }

    /**
     * Audit 3: Physical Stock Count Variance -> Inventory Valuation -> Finance GL Adjustment
     */
    public function test_stock_count_variance_flows_to_inventory_and_gl(): void
    {
        $u = uniqid();
        $product = Product::create([
            'name' => 'Handwoven Dhaka Scarf ' . $u,
            'slug' => 'handwoven-dhaka-scarf-' . $u,
            'sku' => 'DKA-SCF-MAIN-' . $u,
            'price' => 2500.00,
            'cost_price' => 1500.00,
            'quantity' => 20,
            'is_active' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'DKA-SCF-001-' . $u,
            'price' => 2500.00,
            'cost_price' => 1500.00,
            'stock_quantity' => 20,
            'is_active' => true,
        ]);

        $stockLevel = StockLevel::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_on_hand' => 20,
            'unit_cost_npr' => 1500.00,
        ]);

        // Physical count discovers 2 damaged/missing scarves (Variance = -2 @ Rs. 1,500 = -Rs. 3,000)
        $adjustment = StockAdjustment::create([
            'adjustment_number' => 'ADJ-AUDIT-' . $u,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => -2,
            'unit_cost_npr' => 1500.00,
            'total_value_npr' => 3000.00,
            'type' => 'damage',
            'reason' => 'Physical stock count variance audit',
        ]);

        // Trigger GL posting
        $glEntry = $this->accountingService->recordStockAdjustmentAccounting($adjustment, $this->adminUser);
        $this->assertInstanceOf(JournalEntry::class, $glEntry);
        $this->assertTrue((bool)$glEntry->is_balanced);

        // Double entry for shrinkage: Dr Inventory Shrinkage & Write-offs (1230/5200) Rs. 3,000, Cr Inventory Asset (1210) Rs. 3,000
        $this->assertEquals(3000.00, (float)$glEntry->lines()->whereIn('account_number', ['1230', '5200', '5110'])->sum('debit'));
        $this->assertEquals(3000.00, (float)$glEntry->lines()->where('account_number', '1210')->sum('credit'));
    }

    /**
     * Audit 4: People / HRM (Attendance + SSF + TDS) -> Finance GL Accrual & Bank Disbursement
     */
    public function test_people_hrm_flows_to_finance_gl_and_bank_disbursement(): void
    {
        $employee = Employee::create([
            'employee_number' => 'LAI-AUDIT-01',
            'first_name' => 'Sunil',
            'last_name' => 'Maharjan',
            'email' => 'sunil.maharjan@laijau.com',
            'pan_number' => '203948576',
            'marital_status' => 'married',
            'basic_salary' => 30000.00,
            'allowance_amount' => 10000.00,
            'ssf_enrolled' => true,
            'status' => 'active',
            'hire_date' => '2026-08-01',
        ]);

        $run = $this->payrollService->createPayrollRun(
            'Salary — Audit Month',
            '2026-09-01',
            '2026-09-30',
            '2026-09-30',
            '2083/84',
            $this->adminUser
        );

        // 1. Accrual GL Voucher
        $accrualEntry = $this->payrollService->postPayrollToAccounting($run, $this->adminUser);
        $this->assertTrue((bool)$accrualEntry->is_balanced);

        // Verify Gross Salary (40,000) debited to 6120/1810 & SSF Employer 20% (6,000) debited to 6130/1830
        $totalDebit = (float)$accrualEntry->lines()->sum('debit');
        $this->assertEquals(46000.00, round($totalDebit, 2));

        // 2. Bank Salary Disbursement
        $initialBalance = (float)$this->bankAccount->current_balance;
        $netSalary = (float)$run->net_salary;

        $payoutEntry = $this->payrollService->recordSalaryDisbursement(
            $run,
            $this->bankAccount,
            $this->adminUser,
            'NCHL-SAL-AUDIT-01'
        );

        $this->assertTrue((bool)$payoutEntry->is_balanced);
        $this->assertEquals('paid', $run->fresh()->status);
        $this->assertEquals(round($initialBalance - $netSalary, 2), round((float)$this->bankAccount->fresh()->current_balance, 2));
    }

    /**
     * Audit 5: Cross-Module 6-Domain Audit Matrix Consistency
     */
    public function test_cross_module_audit_matrix_consistency(): void
    {
        $audit = $this->accountingService->reconcileCommerceVsAccounting();

        $this->assertIsArray($audit);
        $this->assertArrayHasKey('orders', $audit);
        $this->assertArrayHasKey('pos', $audit);
        $this->assertArrayHasKey('purchases', $audit);
        $this->assertArrayHasKey('inventory', $audit);
        $this->assertArrayHasKey('cod', $audit);
        $this->assertArrayHasKey('bank', $audit);

        // Verify unposted metrics are valid integers >= 0
        $this->assertGreaterThanOrEqual(0, $audit['orders']['unposted']);
        $this->assertGreaterThanOrEqual(0, $audit['pos']['unposted']);
        $this->assertGreaterThanOrEqual(0.0, $audit['inventory']['physical_valuation']);
        $this->assertIsArray($audit['bank']);
    }
}
