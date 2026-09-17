<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\OfflineSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NepalAccountingSystemTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingService $accountingService;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountingService = app(AccountingService::class);
        $this->accountingService->seedDefaultChartOfAccounts();

        $this->adminUser = User::factory()->create([
            'email' => 'finance-admin@laijau.com',
            'role' => 'admin',
        ]);
    }

    /**
     * Test 1: Standard Nepal Chart of Accounts compliant with NAS is seeded.
     */
    public function test_nepal_chart_of_accounts_and_bank_registers_are_seeded(): void
    {
        // 1. Check Standard Nepal NAS accounts
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '1110', 'category' => 'cash_bank', 'currency' => 'NPR']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '1120', 'category' => 'cash_bank', 'currency' => 'NPR']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '1130', 'category' => 'receivables', 'currency' => 'NPR']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '1140', 'category' => 'receivables', 'currency' => 'NPR']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '1150', 'category' => 'receivables', 'currency' => 'NPR']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '1160', 'category' => 'receivables', 'currency' => 'NPR']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '1210', 'category' => 'inventory', 'currency' => 'NPR']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '2110', 'category' => 'payables', 'currency' => 'NPR']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '2120', 'name' => 'VAT Payable (13% Nepal Output VAT)', 'category' => 'vat_tax', 'currency' => 'NPR']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '2130', 'name' => 'VAT Receivable / Input Tax Credit (13%)', 'category' => 'vat_tax', 'currency' => 'NPR']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '3110', 'category' => 'equity', 'currency' => 'NPR']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '3120', 'category' => 'equity', 'currency' => 'NPR']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '4110', 'category' => 'revenue', 'currency' => 'NPR']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '4120', 'category' => 'revenue', 'currency' => 'NPR']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '5110', 'category' => 'cogs', 'currency' => 'NPR']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '6110', 'category' => 'opex', 'currency' => 'NPR']);

        // 2. Strict Purge Assertion: Zero legacy accounts exist
        $this->assertDatabaseMissing('accounting_accounts', ['account_number' => '2610']);
        $this->assertDatabaseMissing('accounting_accounts', ['account_number' => '2620']);
        $this->assertDatabaseHas('accounting_accounts', ['account_number' => '2120']);

        // 3. Check Nepal Bank Account
        $this->assertDatabaseHas('accounting_bank_accounts', ['currency' => 'NPR']);
    }

    /**
     * Test 2: Enforce Double-Entry Bookkeeping equality under Nepal Accounting Standards (NAS).
     */
    public function test_double_entry_equality_enforcement_under_nas(): void
    {
        $accBank = Account::where('account_number', '1120')->firstOrFail();
        $accSales = Account::where('account_number', '4110')->firstOrFail();
        $initialBank = (float)$accBank->current_balance;

        // 1. Balanced Voucher posts successfully with JV- prefix
        $entry = $this->accountingService->postJournalEntry([
            'voucher_date' => '2026-09-01',
            'description' => 'Showroom sales cash deposit',
        ], [
            ['account_id' => $accBank->id, 'debit' => 5000.00, 'credit' => 0.00],
            ['account_id' => $accSales->id, 'debit' => 0.00, 'credit' => 5000.00],
        ]);

        $this->assertNotNull($entry);
        $this->assertTrue($entry->is_balanced);
        $this->assertStringStartsWith('JV-2026-', $entry->entry_number);
        $this->assertEquals($initialBank + 5000.00, (float)$accBank->fresh()->current_balance);

        // 2. Unbalanced Voucher throws RuntimeException
        $this->expectException(\RuntimeException::class);
        $this->accountingService->postJournalEntry([
            'voucher_date' => '2026-09-01',
            'description' => 'Unbalanced voucher test',
        ], [
            ['account_id' => $accBank->id, 'debit' => 5000.00, 'credit' => 0.00],
            ['account_id' => $accSales->id, 'debit' => 0.00, 'credit' => 4500.00],
        ]);
    }

    /**
     * Test 3: Reversal of Journal Vouchers under NAS.
     */
    public function test_voucher_reversal_under_nas(): void
    {
        $accRent = Account::where('account_number', '6110')->firstOrFail();
        $accBank = Account::where('account_number', '1120')->firstOrFail();
        $initialRent = (float)$accRent->current_balance;

        // Post rent expense
        $entry = $this->accountingService->postJournalEntry([
            'voucher_date' => '2026-09-01',
            'description' => 'Kathmandu Showroom Rent',
        ], [
            ['account_id' => $accRent->id, 'debit' => 25000.00, 'credit' => 0.00],
            ['account_id' => $accBank->id, 'debit' => 0.00, 'credit' => 25000.00],
        ]);

        $this->assertEquals($initialRent + 25000.00, (float)$accRent->fresh()->current_balance);

        // Reverse the entry
        $reversal = $this->accountingService->reverseJournalEntry($entry, 'Erroneous rent entry correction', $this->adminUser);

        $this->assertEquals('reversed', $entry->fresh()->status);
        $this->assertEquals('posted', $reversal->status);
        $this->assertEquals($entry->id, $reversal->reversal_of_entry_id);
        $this->assertEquals($initialRent, (float)$accRent->fresh()->current_balance);
    }

    /**
     * Test 4: Locked accounting period blocks posting.
     */
    public function test_locked_accounting_period_blocks_posting(): void
    {
        $period = AccountingPeriod::where('name', '2026-01')->firstOrFail();
        $period->lock($this->adminUser);

        $accBank = Account::where('account_number', '1120')->firstOrFail();
        $accSales = Account::where('account_number', '4110')->firstOrFail();

        $this->expectException(\RuntimeException::class);

        $this->accountingService->postJournalEntry([
            'voucher_date' => '2026-01-15',
            'description' => 'Late voucher for locked month',
        ], [
            ['account_id' => $accBank->id, 'debit' => 500.00, 'credit' => 0.00],
            ['account_id' => $accSales->id, 'debit' => 0.00, 'credit' => 500.00],
        ]);
    }

    /**
     * Test 5: Online Order auto-posting creates balanced voucher with 13% Nepal VAT and Account 2120.
     */
    public function test_online_order_auto_posting_integrates_nepal_13_percent_vat(): void
    {
        $product = Product::create([
            'name' => 'Authentic Chyangra Pashmina Shawl',
            'slug' => 'authentic-chyangra-pashmina-shawl',
            'sku' => 'PAS-NEP-001',
            'price' => 2260.00,
            'cost_price' => 800.00,
            'stock_quantity' => 20,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-2026-NP-001',
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'email' => 'aarav@laijau.com',
            'shipping_address' => 'Lazimpat, Ward 2',
            'shipping_city' => 'Kathmandu',
            'shipping_postal_code' => '44600',
            'shipping_country' => 'NP',
            'subtotal' => 2000.00,
            'vat_amount' => 260.00,
            'vat_rate' => 13.00,
            'shipping_cost' => 0.00,
            'total_amount' => 2260.00,
            'currency' => 'NPR',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'connectips',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 2260.00,
            'total_price' => 2260.00,
        ]);

        // Customer pays: triggers auto-posting
        $order->update(['payment_status' => 'paid', 'status' => 'paid']);

        $voucher = $this->accountingService->recordOrderSale($order);

        $this->assertNotNull($voucher);
        $this->assertTrue($voucher->is_balanced);
        $this->assertStringStartsWith('JV-2026-', $voucher->entry_number);

        // Check Nepal Output VAT 13% (Account 2120) credited
        $vatLine = $voucher->lines->firstWhere('account_number', '2120');
        $this->assertNotNull($vatLine, 'Journal voucher must have credit line for Account 2120 (VAT Payable)');
        $this->assertEquals(260.00, (float)$vatLine->credit);

        // Check Online Sales Revenue (Account 4120) credited net amount
        $salesLine = $voucher->lines->firstWhere('account_number', '4120');
        $this->assertNotNull($salesLine, 'Journal voucher must have credit line for Account 4120 (Online Revenue)');
        $this->assertEquals(2000.00, (float)$salesLine->credit);

        // Check COGS (5110) debited 800 NPR
        $cogsLine = $voucher->lines->firstWhere('account_number', '5110');
        $this->assertNotNull($cogsLine, 'Journal voucher must debit Account 5110 (COGS)');
        $this->assertEquals(800.00, (float)$cogsLine->debit);

        // Check Inventory (1210) credited 800 NPR
        $invLine = $voucher->lines->firstWhere('account_number', '1210');
        $this->assertNotNull($invLine, 'Journal voucher must credit Account 1210 (Inventory)');
        $this->assertEquals(800.00, (float)$invLine->credit);

        // Check Tax Invoice record generated with INV- prefix and NPR currency
        $invoice = AccountingInvoice::where('reference_order_id', $order->id)->first();
        $this->assertNotNull($invoice);
        $this->assertStringStartsWith('INV-2026-', $invoice->invoice_number);
        $this->assertEquals('NPR', $invoice->currency);
        $this->assertEquals(260.00, (float)$invoice->vat_amount);
        $this->assertEquals(13.00, (float)$invoice->vat_rate);
    }

    /**
     * Test 6: Offline Showroom Sale auto-posting and Nepal VAT treatment.
     */
    public function test_offline_showroom_sale_auto_posting_and_nepal_vat(): void
    {
        $service = app(OfflineSaleService::class);

        $product = Product::create([
            'name' => 'Kathmandu Dhaka Topi & Scarf',
            'slug' => 'kathmandu-dhaka-topi-scarf',
            'sku' => 'DHK-001',
            'price' => 1130.00,
            'cost_price' => 400.00,
            'quantity' => 10,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        // Create POS Sale via eSewa
        $sale = $service->createSale(
            ['customer_name' => 'Binod Thapa', 'payment_method' => 'esewa', 'currency' => 'NPR'],
            [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1130.00]],
            $this->adminUser
        );

        $voucher = JournalEntry::where('reference_type', 'offline_sale')->where('reference_id', $sale->id)->first();
        $this->assertNotNull($voucher);
        $this->assertTrue($voucher->is_balanced);
        $this->assertStringStartsWith('JV-2026-', $voucher->entry_number);

        // Verify Output VAT 13% credited to 2120
        $vatLine = $voucher->lines->firstWhere('account_number', '2120');
        $this->assertNotNull($vatLine);
        $this->assertEquals(130.00, (float)$vatLine->credit);

        // Verify POS Sales credited to 4110
        $posLine = $voucher->lines->firstWhere('account_number', '4110');
        $this->assertNotNull($posLine);
        $this->assertEquals(1000.00, (float)$posLine->credit);
    }

    /**
     * Test 7: Nepal IRD Anusuchi 10 VAT Return generation.
     */
    public function test_nepal_ird_anusuchi_10_vat_return_generation(): void
    {
        $vatReport = $this->accountingService->generateVatReturn('2026-01-01', '2026-12-31');

        $this->assertIsArray($vatReport);
        $this->assertArrayHasKey('taxable_sales_13', $vatReport);
        $this->assertArrayHasKey('output_vat_13', $vatReport);
        $this->assertArrayHasKey('taxable_purchases_13', $vatReport);
        $this->assertArrayHasKey('input_vat_13', $vatReport);
        $this->assertArrayHasKey('net_vat_position', $vatReport);
    }
}
