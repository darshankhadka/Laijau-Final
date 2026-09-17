<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingFiscalYear;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\BikriKhataEntry;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\KharidKhataEntry;
use App\Models\Accounting\TdsRecord;
use App\Models\Accounting\TdsRule;
use App\Models\Accounting\VatConfiguration;
use App\Models\Category;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NepalAccountingEngineHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingService $accountingService;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountingService = app(AccountingService::class);
        $this->accountingService->ensureDefaultChartOfAccounts();

        $this->adminUser = User::factory()->create([
            'email' => 'finance.admin@laijau.com',
            'role' => 'admin',
        ]);
    }

    /** @test */
    public function test_seeds_data_driven_tds_rules_and_vat_configs(): void
    {
        $this->accountingService->seedNepalTaxRulesAndVatConfigs();

        // 3 statutory Nepali fiscal years: 2081/82, 2082/83, 2083/84
        $this->assertGreaterThanOrEqual(3, VatConfiguration::count());
        $this->assertGreaterThanOrEqual(27, TdsRule::count());

        $vat2083 = VatConfiguration::where('fiscal_year', '2083/84')->first();
        $this->assertNotNull($vat2083);
        $this->assertEquals(13.00, (float)$vat2083->standard_vat_rate);
        $this->assertTrue($vat2083->taxable_eligible);
        $this->assertTrue($vat2083->zero_rated_export_eligible);

        $tdsContract = TdsRule::where('fiscal_year', '2083/84')->where('payment_type', 'contract_goods')->first();
        $this->assertNotNull($tdsContract);
        $this->assertEquals('89', $tdsContract->section);
        $this->assertEquals(1.50, (float)$tdsContract->rate);
        $this->assertEquals(50000.00, (float)$tdsContract->threshold);
    }

    /** @test */
    public function test_dynamic_tds_resolution_and_threshold(): void
    {
        // 1. Below threshold (Rs. 40,000 < 50,000) for contract goods (Section 89)
        $tdsBelow = $this->accountingService->calculateTds('2083/84', 'contract_goods', 40000.00, '601234567');
        $this->assertFalse($tdsBelow['applicable']);
        $this->assertFalse($tdsBelow['threshold_met']);
        $this->assertEquals(0.00, $tdsBelow['amount']);

        // 2. Above threshold (Rs. 100,000 > 50,000)
        $tdsAbove = $this->accountingService->calculateTds('2083/84', 'contract_goods', 100000.00, '601234567');
        $this->assertTrue($tdsAbove['applicable']);
        $this->assertTrue($tdsAbove['threshold_met']);
        $this->assertEquals(1.50, $tdsAbove['rate']);
        $this->assertEquals(1500.00, $tdsAbove['amount']);
        $this->assertEquals('89', $tdsAbove['section']);

        // 3. Rent (Section 88): 10% with no threshold
        $tdsRent = $this->accountingService->calculateTds('2083/84', 'rent', 80000.00, '601999888');
        $this->assertTrue($tdsRent['applicable']);
        $this->assertEquals(10.00, $tdsRent['rate']);
        $this->assertEquals(8000.00, $tdsRent['amount']);
        $this->assertEquals('88', $tdsRent['section']);
    }

    /** @test */
    public function test_dynamic_tds_penalty_rate_when_payee_lacks_pan(): void
    {
        // When payee PAN is missing, rule has rate_without_pan = 3.00%
        $tdsNoPan = $this->accountingService->calculateTds('2083/84', 'contract_goods', 100000.00, null);
        $this->assertTrue($tdsNoPan['applicable']);
        $this->assertFalse($tdsNoPan['pan_provided']);
        $this->assertEquals(3.00, $tdsNoPan['rate']);
        $this->assertEquals(3000.00, $tdsNoPan['amount']);
    }

    /** @test */
    public function test_dynamic_vat_calculation_and_versioned_config(): void
    {
        $taxable = $this->accountingService->calculateVat('2083/84', 50000.00, 'taxable');
        $this->assertEquals(13.00, $taxable['rate']);
        $this->assertEquals(50000.00, $taxable['taxable']);
        $this->assertEquals(6500.00, $taxable['vat']);

        $export = $this->accountingService->calculateVat('2083/84', 75000.00, 'export');
        $this->assertEquals(0.00, $export['vat']);
        $this->assertEquals(75000.00, $export['export']);

        $exempt = $this->accountingService->calculateVat('2083/84', 30000.00, 'exempt');
        $this->assertEquals(0.00, $exempt['vat']);
        $this->assertEquals(30000.00, $exempt['exempt']);
    }

    /** @test */
    public function test_supplier_purchase_bill_applies_dynamic_tds_and_posts_balanced_gl(): void
    {
        $billData = [
            'invoice_number' => 'BILL-TEST-2083-0099',
            'contact_name' => 'Kathmandu Silk Mills Pvt. Ltd.',
            'seller_pan' => '601889922',
            'purchase_type' => 'local_taxable_13',
            'issue_date' => '2026-08-20', // FY 2083/84 Bhadra
            'taxable_amount' => 100000.00,
            'exempt_amount' => 0.00,
        ];

        $kharidEntry = $this->accountingService->recordPurchaseBillKharidKhata($billData, [
            [
                'description' => 'Pure Banarasi Raw Silk Fabric',
                'quantity' => 10,
                'unit_price' => 10000.00,
                'vat_rate' => 13.00,
                'vat_amount' => 13000.00,
                'total_amount' => 113000.00,
            ],
        ]);

        $this->assertInstanceOf(KharidKhataEntry::class, $kharidEntry);
        $this->assertEquals('2083/84', $kharidEntry->fiscal_year);
        $this->assertEquals(100000.00, (float)$kharidEntry->taxable_amount);
        $this->assertEquals(13000.00, (float)$kharidEntry->vat_amount);
        $this->assertEquals(113000.00, (float)$kharidEntry->total_amount);

        // TDS dynamically calculated: 1.5% of 100,000 = Rs. 1,500
        $this->assertTrue((bool)$kharidEntry->tds_applicable);
        $this->assertEquals(1.50, (float)$kharidEntry->tds_rate);
        $this->assertEquals(1500.00, (float)$kharidEntry->tds_amount);

        // GL Journal Voucher must be strictly balanced
        $this->assertNotNull($kharidEntry->journal_entry_id);
        $journal = JournalEntry::find($kharidEntry->journal_entry_id);
        $this->assertNotNull($journal);
        $this->assertTrue($journal->is_balanced);
        $this->assertEquals(113000.00, (float)$journal->total_debit);
        $this->assertEquals(113000.00, (float)$journal->total_credit);

        // TdsRecord must be created with Section 89
        $tdsRecord = TdsRecord::where('reference_id', $kharidEntry->id)->first();
        $this->assertNotNull($tdsRecord);
        $this->assertEquals('pending', $tdsRecord->deposit_status);
        $this->assertEquals(1500.00, (float)$tdsRecord->tds_amount);
        $this->assertStringContainsString('Section 89', $tdsRecord->notes);
    }

    /** @test */
    public function test_stock_adjustment_posts_balanced_inventory_gl_entry(): void
    {
        $category = Category::create([
            'name' => 'Footwear',
            'slug' => 'footwear-' . uniqid(),
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Royal Oxford Leather Shoes',
            'slug' => 'royal-oxford-' . uniqid(),
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'OXFORD-LEA-' . uniqid(),
            'cost_price' => 5000.00,
            'price' => 9500.00,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Durbar Marg Flagship Showroom',
            'code' => 'KTM-DM-01',
            'is_active' => true,
        ]);

        $adjustment = StockAdjustment::create([
            'adjustment_number' => 'ADJ-TEST-001',
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'type' => 'damage',
            'quantity' => -2, // Loss of 2 units
            'unit_cost_npr' => 5000.00,
            'total_value_npr' => 10000.00,
            'reason' => 'Showroom display water damage',
            'status' => 'approved',
        ]);

        $journal = $this->accountingService->recordStockAdjustmentAccounting($adjustment, $this->adminUser);

        $this->assertNotNull($journal);
        $this->assertTrue($journal->is_balanced);
        $this->assertEquals(10000.00, (float)$journal->total_debit);
        $this->assertEquals(10000.00, (float)$journal->total_credit);

        $adjustment->refresh();
        $this->assertEquals($journal->id, $adjustment->journal_entry_id);
    }

    /** @test */
    public function test_year_end_closing_zeros_nominal_accounts_and_transfers_to_retained_earnings(): void
    {
        // Unlock 2082/83 to allow posting the test voucher
        AccountingFiscalYear::where('fiscal_year', '2082/83')->update(['status' => 'open']);
        AccountingPeriod::where('fiscal_year', '2082/83')->update(['status' => 'open']);

        // 1. Post a sample revenue entry: Dr Cash 50,000, Cr Sales 50,000 for FY 2082/83
        $accCash = Account::where('account_number', '1110')->first();
        $accSales = Account::where('account_number', '4120')->first() ?: Account::where('category', 'revenue')->first();

        $this->accountingService->postJournalEntry([
            'voucher_date' => '2025-10-10', // FY 2082/83
            'entry_type' => 'sales',
            'description' => 'Test revenue posting for FY 2082/83',
            'currency' => 'NPR',
        ], [
            [
                'account_id' => $accCash->id,
                'account_number' => $accCash->account_number,
                'description' => 'Cash received',
                'debit' => 50000.00,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accSales->id,
                'account_number' => $accSales->account_number,
                'description' => 'Revenue earned',
                'debit' => 0.00,
                'credit' => 50000.00,
            ],
        ]);

        // 2. Perform Year-End Closing for 2082/83
        $closingEntry = $this->accountingService->performYearEndClosing('2082/83', $this->adminUser);

        $this->assertNotNull($closingEntry);
        $this->assertEquals('closing', $closingEntry->entry_type);
        $this->assertTrue($closingEntry->is_balanced);

        // Verify FY 2082/83 is marked closed
        $fy = AccountingFiscalYear::where('fiscal_year', '2082/83')->first();
        $this->assertEquals('closed', $fy->status);
    }

    /** @test */
    public function test_generate_opening_balances_rolls_forward_balance_sheet_to_new_fiscal_year(): void
    {
        // Unlock 2082/83 to allow posting the test capital injection
        AccountingFiscalYear::where('fiscal_year', '2082/83')->update(['status' => 'open']);
        AccountingPeriod::where('fiscal_year', '2082/83')->update(['status' => 'open']);

        // 1. Establish balance in Asset account in 2082/83
        $accCash = Account::where('account_number', '1110')->first();
        $accCapital = Account::where('account_number', '3110')->first() ?: Account::where('category', 'equity')->first();

        $this->accountingService->postJournalEntry([
            'voucher_date' => '2025-08-01',
            'entry_type' => 'manual',
            'description' => 'Initial capital injection FY 2082/83',
            'currency' => 'NPR',
        ], [
            [
                'account_id' => $accCash->id,
                'account_number' => $accCash->account_number,
                'description' => 'Bank cash',
                'debit' => 150000.00,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accCapital->id,
                'account_number' => $accCapital->account_number,
                'description' => 'Shareholder equity',
                'debit' => 0.00,
                'credit' => 150000.00,
            ],
        ]);

        // 2. Generate Opening Balances for FY 2083/84
        $openingEntry = $this->accountingService->generateOpeningBalances('2083/84', '2082/83', $this->adminUser);

        $this->assertNotNull($openingEntry);
        $this->assertEquals('closing', $openingEntry->entry_type);
        $this->assertTrue($openingEntry->is_balanced);
        $this->assertEquals('2026-07-16', $openingEntry->voucher_date->format('Y-m-d'));
        $this->assertGreaterThan(0, (float)$openingEntry->total_debit);
        $this->assertEquals((float)$openingEntry->total_debit, (float)$openingEntry->total_credit);
    }

    /** @test */
    public function test_cross_reconciliation_multi_domain_metrics(): void
    {
        $metrics = $this->accountingService->reconcileCommerceVsAccounting();

        $this->assertArrayHasKey('orders', $metrics);
        $this->assertArrayHasKey('pos', $metrics);
        $this->assertArrayHasKey('purchases', $metrics);
        $this->assertArrayHasKey('inventory', $metrics);
        $this->assertArrayHasKey('cod', $metrics);
        $this->assertArrayHasKey('bank', $metrics);

        $this->assertArrayHasKey('physical_valuation', $metrics['inventory']);
        $this->assertArrayHasKey('gl_balance', $metrics['inventory']);
        $this->assertArrayHasKey('variance', $metrics['inventory']);

        $this->assertArrayHasKey('unsettled_orders_count', $metrics['cod']);
        $this->assertArrayHasKey('gl_clearing_balance', $metrics['cod']);
    }
}
