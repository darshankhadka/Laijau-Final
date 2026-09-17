<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingFiscalYear;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\BikriKhataEntry;
use App\Models\Accounting\CodSettlement;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\KharidKhataEntry;
use App\Models\Accounting\TdsRecord;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NepalAccountingEngineTest extends TestCase
{
    use DatabaseTransactions;

    protected AccountingService $accountingService;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountingService = app(AccountingService::class);
        $this->accountingService->ensureDefaultChartOfAccounts();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Laijau Administrator',
                'password' => bcrypt('Laijau2026!'),
                'role' => 'admin',
            ]
        );
    }

    protected function createTestOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-TEST-' . uniqid(),
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'email' => 'customer.' . uniqid() . '@example.com',
            'customer_email' => 'customer.' . uniqid() . '@example.com',
            'customer_phone' => '9841234567',
            'shipping_address' => 'Lazimpat, Kathmandu',
            'subtotal' => $attributes['total_amount'] ?? 11300.00,
            'total_amount' => 11300.00,
            'shipping_cost' => 0.00,
            'status' => Order::STATUS_PAYMENT_VERIFIED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_method' => 'esewa',
            'channel' => 'online',
        ], $attributes));
    }

    /**
     * 1. Test statutory Bikram Sambat Fiscal Years and 12 monthly periods are seeded.
     */
    public function test_chart_of_accounts_and_nepali_fiscal_periods_are_seeded(): void
    {
        $this->assertDatabaseHas('accounting_fiscal_years', [
            'fiscal_year' => '2083/84',
            'is_current' => true,
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('accounting_fiscal_years', [
            'fiscal_year' => '2082/83',
            'status' => 'locked',
        ]);

        // Verify periods for FY 2083/84 has 12 monthly periods (Shrawan to Ashadh)
        $periodsCount = AccountingPeriod::where('fiscal_year', '2083/84')->count();
        $this->assertGreaterThanOrEqual(12, $periodsCount);

        // Verify critical Nepal IRD accounts exist
        $this->assertNotNull(Account::where('account_number', '1110')->first(), 'Showroom Cash Account exists');
        $this->assertNotNull(Account::where('account_number', '1120')->first(), 'Operating Bank Account exists');
        $this->assertNotNull(Account::where('account_number', '1210')->first(), 'Merchandise Inventory Asset exists');
        $this->assertNotNull(Account::where('account_number', '2110')->first(), 'Trade Accounts Payable exists');
        $this->assertNotNull(Account::where('account_number', '2120')->first(), 'Output VAT (13%) exists');
        $this->assertNotNull(Account::where('account_number', '2130')->first(), 'Input VAT Credit (13%) exists');
        $this->assertNotNull(Account::where('account_number', '2140')->first(), 'TDS Withholding Payable exists');
    }

    /**
     * 2. Test e-commerce sales order generates statutory Bikri Khata (Annex 7) entry and balanced GL voucher.
     */
    public function test_sales_order_generates_statutory_bikri_khata_and_balanced_gl_voucher(): void
    {
        $order = $this->createTestOrder([
            'total_amount' => 11300.00,
        ]);

        $voucher = $this->accountingService->recordOrderSale($order);

        $this->assertNotNull($voucher);
        $this->assertTrue((bool)$voucher->is_balanced, 'Journal Voucher must strictly balance debit == credit');
        $this->assertEquals(11300.00, (float)$voucher->total_debit);
        $this->assertEquals(11300.00, (float)$voucher->total_credit);

        // Verify Bikri Khata Entry (Single Table Inheritance on accounting_invoices)
        $bikriEntry = BikriKhataEntry::where('reference_order_id', $order->id)->first();
        $this->assertNotNull($bikriEntry, 'Bikri Khata entry must be generated');
        $this->assertEquals('sales_invoice', $bikriEntry->type);
        $this->assertEquals(10000.00, (float)$bikriEntry->taxable_amount);
        $this->assertEquals(1300.00, (float)$bikriEntry->vat_amount);
        $this->assertEquals(11300.00, (float)$bikriEntry->total_amount);
        $this->assertEquals('2083/84', $bikriEntry->fiscal_year);
        $this->assertTrue($bikriEntry->posted_to_gl);
    }

    /**
     * 3. Test showroom POS sale generates Bikri Khata with cashier, branch, and items.
     */
    public function test_offline_showroom_sale_generates_bikri_khata_with_items_and_pan(): void
    {
        $sale = OfflineSale::create([
            'sale_number' => 'OFF-TEST-' . uniqid(),
            'customer_name' => 'Pooja Thapa',
            'customer_phone' => '9812345678',
            'customer_email' => 'pooja.thapa@example.com',
            'payment_method' => 'cash',
            'sales_channel' => 'showroom_pos',
            'subtotal' => 20000.00,
            'total_amount' => 22600.00, // 20,000 + 2,600 (13% VAT)
            'status' => 'completed',
            'sold_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $voucher = $this->accountingService->recordOfflineSale($sale);

        $this->assertNotNull($voucher);
        $this->assertTrue((bool)$voucher->is_balanced);

        $bikriEntry = BikriKhataEntry::where('reference_offline_sale_id', $sale->id)->first();
        $this->assertNotNull($bikriEntry);
        $this->assertEquals('sales_invoice', $bikriEntry->type);
        $this->assertEquals('pos_showroom', $bikriEntry->sales_channel);
        $this->assertEquals(20000.00, (float)$bikriEntry->taxable_amount);
        $this->assertEquals(2600.00, (float)$bikriEntry->vat_amount);
        $this->assertEquals(22600.00, (float)$bikriEntry->total_amount);
        $this->assertTrue($bikriEntry->posted_to_gl);
    }

    /**
     * 4. Test Sales Return / Credit Note reverses output VAT and revenue under Nepal VAT Act.
     */
    public function test_sales_return_credit_note_reverses_output_vat_and_revenue(): void
    {
        $order = $this->createTestOrder([
            'total_amount' => 11300.00,
            'status' => Order::STATUS_DELIVERED,
        ]);

        $this->accountingService->recordOrderSale($order);
        $originalInvoice = BikriKhataEntry::where('reference_order_id', $order->id)->firstOrFail();

        // Issue Credit Note for full return
        $creditNote = $this->accountingService->recordSalesReturn(
            $originalInvoice,
            11300.00,
            'Customer sizing return',
            $this->admin
        );

        $this->assertNotNull($creditNote);
        $this->assertEquals('credit_note', $creditNote->type);
        $this->assertEquals(-10000.00, (float)$creditNote->taxable_amount);
        $this->assertEquals(-1300.00, (float)$creditNote->vat_amount);
        $this->assertEquals(-11300.00, (float)$creditNote->total_amount);

        // Verify balanced reversing voucher
        $cnVoucher = $creditNote->journalEntry;
        $this->assertNotNull($cnVoucher);
        $this->assertTrue((bool)$cnVoucher->is_balanced);
    }

    /**
     * 5. Test Supplier Bill in Kharid Khata (Annex 8) claims 13% Input VAT and withholds TDS.
     */
    public function test_supplier_bill_generates_kharid_khata_with_input_vat_credit_and_tds(): void
    {
        $billData = [
            'invoice_number' => 'BILL-HIMALAYAN-' . uniqid(),
            'issue_date' => date('Y-m-d'),
            'contact_name' => 'Himalayan Silk & Cashmere Ltd.',
            'seller_pan' => '301984512',
            'purchase_type' => 'local_taxable_13',
            'taxable_amount' => 50000.00,
            'vat_amount' => 6500.00, // 13% of 50,000
            'exempt_amount' => 0.00,
            'total_amount' => 56500.00,
            'is_credit' => true,
            'tds_applicable' => true,
            'tds_rate' => 1.50, // 1.5% statutory rate for goods contract
            'tds_amount' => 750.00, // 1.5% of 50,000
            'notes' => 'Raw Cashmere fiber procurement',
        ];

        $items = [
            [
                'description' => 'Grade-A Raw Cashmere (Kg)',
                'quantity' => 10,
                'unit_price' => 5000.00,
                'vat_rate' => 13.00,
                'vat_amount' => 6500.00,
                'total_amount' => 56500.00,
            ]
        ];

        $kharidEntry = $this->accountingService->recordPurchaseBillKharidKhata($billData, $items);

        $this->assertNotNull($kharidEntry);
        $this->assertEquals('supplier_bill', $kharidEntry->type);
        $this->assertEquals(50000.00, (float)$kharidEntry->taxable_amount);
        $this->assertEquals(6500.00, (float)$kharidEntry->vat_amount);
        $this->assertEquals(56500.00, (float)$kharidEntry->total_amount);
        $this->assertEquals(750.00, (float)$kharidEntry->tds_amount);
        $this->assertTrue($kharidEntry->posted_to_gl);

        // Verify balanced GL voucher:
        // Dr Inventory: 50,000
        // Dr Input VAT: 6,500
        // Cr Accounts Payable: 55,750
        // Cr TDS Payable: 750
        // Total Debit = 56,500 == Total Credit = 56,500
        $voucher = $kharidEntry->journalEntry;
        $this->assertNotNull($voucher);
        $this->assertTrue((bool)$voucher->is_balanced);
        $this->assertEquals(56500.00, (float)$voucher->total_debit);
        $this->assertEquals(56500.00, (float)$voucher->total_credit);

        // Verify TDS Register entry was created
        $tdsRecord = TdsRecord::where('reference_id', $kharidEntry->id)->first();
        $this->assertNotNull($tdsRecord);
        $this->assertEquals(750.00, (float)$tdsRecord->tds_amount);
        $this->assertEquals('pending', $tdsRecord->deposit_status);
    }

    /**
     * 6. Test Purchase Return (Debit Note) in Kharid Khata adjusts Input VAT and AP.
     */
    public function test_purchase_return_debit_note_adjusts_kharid_khata_and_gl(): void
    {
        $billData = [
            'invoice_number' => 'BILL-RET-' . uniqid(),
            'issue_date' => date('Y-m-d'),
            'contact_name' => 'Nepal Textiles',
            'seller_pan' => '302485912',
            'purchase_type' => 'local_taxable_13',
            'taxable_amount' => 10000.00,
            'vat_amount' => 1300.00,
            'total_amount' => 11300.00,
            'is_credit' => true,
        ];

        $bill = $this->accountingService->recordPurchaseBillKharidKhata($billData);

        $debitNote = $this->accountingService->recordDebitNote(
            $bill,
            11300.00,
            'Defective yarn return',
            $this->admin
        );

        $this->assertNotNull($debitNote);
        $this->assertEquals('debit_note', $debitNote->type);
        $this->assertEquals(-10000.00, (float)$debitNote->taxable_amount);
        $this->assertEquals(-1300.00, (float)$debitNote->vat_amount);

        $dnVoucher = $debitNote->journalEntry;
        $this->assertNotNull($dnVoucher);
        $this->assertTrue((bool)$dnVoucher->is_balanced);
    }

    /**
     * 7. Test COD courier settlement (Pathao/NCM) separates delivery fees and updates bank.
     */
    public function test_cod_courier_remittance_settlement_flow(): void
    {
        $settlement = $this->accountingService->reconcileCodCourierSettlement(
            remittedAmount: 28500.00,
            courierFee: 1500.00,
            courierName: 'Pathao Express Nepal',
            batchRef: 'PTH-SETTLE-8891',
            date: date('Y-m-d'),
            orderIds: [1, 2, 3]
        );

        $this->assertNotNull($settlement);
        $this->assertEquals('posted', $settlement->status);
        $this->assertEquals(30000.00, (float)$settlement->total_order_amount);
        $this->assertEquals(1500.00, (float)$settlement->courier_fee);
        $this->assertEquals(28500.00, (float)$settlement->net_bank_deposited);

        // Verify balanced double-entry voucher
        $voucher = $settlement->journalEntry;
        $this->assertNotNull($voucher);
        $this->assertTrue((bool)$voucher->is_balanced);
        $this->assertEquals(30000.00, (float)$voucher->total_debit);
        $this->assertEquals(30000.00, (float)$voucher->total_credit);
    }

    /**
     * 8. Test Statutory Nepal VAT Return (Anusuchi 10) calculation and drill-down audit.
     */
    public function test_statutory_nepal_vat_return_anusuchi_10_calculation_and_drilldown(): void
    {
        // Record 1 sale: Taxable 20,000, VAT 2,600
        $order = $this->createTestOrder([
            'order_number' => 'ORD-VAT-TEST-' . uniqid(),
            'total_amount' => 22600.00,
            'status' => Order::STATUS_PAID,
        ]);
        $this->accountingService->recordOrderSale($order);

        // Record 1 purchase: Taxable 10,000, Input VAT 1,300
        $this->accountingService->recordPurchaseBillKharidKhata([
            'invoice_number' => 'BILL-VAT-TEST-' . uniqid(),
            'issue_date' => date('Y-m-d'),
            'contact_name' => 'Supplier VAT Test',
            'seller_pan' => '300123456',
            'purchase_type' => 'local_taxable_13',
            'taxable_amount' => 10000.00,
            'vat_amount' => 1300.00,
            'total_amount' => 11300.00,
        ]);

        $vatReturn = $this->accountingService->generateStatutoryNepalVatReturn('2083/84');

        $this->assertNotNull($vatReturn);
        $this->assertArrayHasKey('sales', $vatReturn);
        $this->assertArrayHasKey('purchases', $vatReturn);
        $this->assertArrayHasKey('reconciliation', $vatReturn);
        $this->assertArrayHasKey('drill_down', $vatReturn);

        // Verify Output VAT > 0 and Input VAT > 0
        $this->assertGreaterThanOrEqual(2600.00, $vatReturn['sales']['output_vat']);
        $this->assertGreaterThanOrEqual(1300.00, $vatReturn['purchases']['input_vat']);

        // Verify line-by-line drill-down collections are present
        $this->assertGreaterThan(0, $vatReturn['drill_down']['sales_count']);
        $this->assertGreaterThan(0, $vatReturn['drill_down']['purchase_count']);
    }

    /**
     * 9. Test Receivables and Payables aging calculation.
     */
    public function test_receivables_and_payables_aging_calculation(): void
    {
        $aging = $this->accountingService->generateReceivablesPayablesAging();

        $this->assertArrayHasKey('receivables', $aging);
        $this->assertArrayHasKey('payables', $aging);
        $this->assertArrayHasKey('current', $aging['receivables']);
        $this->assertArrayHasKey('days_30', $aging['receivables']);
        $this->assertArrayHasKey('days_60', $aging['receivables']);
        $this->assertArrayHasKey('days_90_plus', $aging['receivables']);
    }

    /**
     * 10. Test Commerce vs. Accounting reconciliation audit.
     */
    public function test_commerce_vs_accounting_cross_reconciliation(): void
    {
        $this->createTestOrder();
        OfflineSale::create([
            'sale_number' => 'POS-TEST-' . uniqid(),
            'total_amount' => 5000.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $recon = $this->accountingService->reconcileCommerceVsAccounting();

        $this->assertArrayHasKey('orders', $recon);
        $this->assertArrayHasKey('pos', $recon);
        $this->assertArrayHasKey('credit_notes', $recon);
        $this->assertArrayHasKey('purchases', $recon);
        $this->assertGreaterThan(0, $recon['orders']['total']);
        $this->assertGreaterThan(0, $recon['pos']['total']);
    }
}
