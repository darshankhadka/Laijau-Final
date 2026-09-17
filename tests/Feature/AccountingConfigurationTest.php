<?php

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Settings\ConfigurationImpactService;
use App\Services\Settings\SettingsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class AccountingConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected SettingsService $settingsService;
    protected AccountingService $accountingService;
    protected ConfigurationImpactService $impactService;
    protected User $adminUser;
    protected User $accountantUser;
    protected User $cashierUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsService = app(SettingsService::class);
        $this->accountingService = app(AccountingService::class);
        $this->impactService = app(ConfigurationImpactService::class);

        // Seed chart of accounts and module settings
        $this->accountingService->seedDefaultChartOfAccounts();
        $this->artisan('db:seed', ['--class' => 'ModuleSettingsSeeder']);

        $this->adminUser = User::firstOrCreate(
            ['email' => 'finans.admin@laijau.com'],
            ['name' => 'CFO / Finance Director', 'password' => bcrypt('secret'), 'role' => 'admin']
        );

        $this->accountantUser = User::firstOrCreate(
            ['email' => 'accountant@laijau.com'],
            ['name' => 'Chief Accountant', 'password' => bcrypt('secret'), 'role' => 'accountant']
        );

        $this->cashierUser = User::firstOrCreate(
            ['email' => 'cashier@laijau.com'],
            ['name' => 'Showroom Cashier', 'password' => bcrypt('secret'), 'role' => 'cashier']
        );
    }

    /**
     * Helper to get or create open account
     */
    protected function getAccount(string $accountNumber): Account
    {
        return Account::where('account_number', $accountNumber)->firstOrFail();
    }

    public function test_backdated_postings_policy_enforcement(): void
    {
        $bank = $this->getAccount('1120');
        $rent = $this->getAccount('6110');

        // Disallow backdated postings beyond 30 days
        $this->settingsService->set('accounting', 'allow_backdated_postings', false);
        $this->settingsService->set('accounting', 'max_backdated_days', 30);

        // 45 days in the past
        $pastDate = now()->subDays(45)->toDateString();

        // 1. Attempt posting 45 days back -> should fail
        $lines = [
            ['account_id' => $rent->id, 'debit' => 5000.00, 'credit' => 0.00],
            ['account_id' => $bank->id, 'debit' => 0.00, 'credit' => 5000.00],
        ];

        try {
            $this->accountingService->postJournalEntry([
                'voucher_date' => $pastDate,
                'entry_type' => 'manual',
                'description' => 'Late rent voucher',
            ], $lines, $this->adminUser);
            $this->fail("Posting a 45-day backdated voucher should have thrown RuntimeException under policy");
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Backdating rejected', $e->getMessage());
        }

        // 2. Extend grace window to 60 days via settings
        $this->settingsService->set('accounting', 'max_backdated_days', 60);

        $entry = $this->accountingService->postJournalEntry([
            'voucher_date' => $pastDate,
            'entry_type' => 'manual',
            'description' => 'Late rent voucher - approved under 60-day policy',
        ], $lines, $this->adminUser);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertEquals($pastDate, $entry->voucher_date->toDateString());
        $this->assertTrue($entry->is_balanced);

        // 3. Date within 30 days succeeds even when max_backdated_days is reset to 30
        $this->settingsService->set('accounting', 'max_backdated_days', 30);
        $recentPast = now()->subDays(10)->toDateString();

        $entryRecent = $this->accountingService->postJournalEntry([
            'voucher_date' => $recentPast,
            'entry_type' => 'manual',
            'description' => 'Recent transaction within 30 days',
        ], $lines, $this->adminUser);

        $this->assertInstanceOf(JournalEntry::class, $entryRecent);
        $this->assertEquals($recentPast, $entryRecent->voucher_date->toDateString());
    }

    public function test_locked_fiscal_period_strictly_rejects_postings(): void
    {
        $bank = $this->getAccount('1120');
        $sales = $this->getAccount('4120');

        $testDate = '2026-02-15';
        $period = $this->accountingService->resolvePeriodForDate($testDate);
        $period->lock();
        $this->assertEquals('locked', $period->status);

        $lines = [
            ['account_id' => $bank->id, 'debit' => 1000.00, 'credit' => 0.00],
            ['account_id' => $sales->id, 'debit' => 0.00, 'credit' => 1000.00],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('locked or closed');

        $this->accountingService->postJournalEntry([
            'voucher_date' => $testDate,
            'entry_type' => 'manual',
            'description' => 'Post attempt in locked period',
        ], $lines, $this->adminUser);
    }

    public function test_manual_journal_role_authorization(): void
    {
        $bank = $this->getAccount('1120');
        $office = $this->getAccount('6140');

        $lines = [
            ['account_id' => $office->id, 'debit' => 450.00, 'credit' => 0.00],
            ['account_id' => $bank->id, 'debit' => 0.00, 'credit' => 450.00],
        ];

        // 1. Cashier role should be rejected by default ('admin,accountant')
        try {
            $this->accountingService->postJournalEntry([
                'voucher_date' => date('Y-m-d'),
                'entry_type' => 'manual',
                'description' => 'Office supplies bought by cashier',
            ], $lines, $this->cashierUser);
            $this->fail("Cashier should not be allowed to post manual journal entries");
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('lacks authorization', $e->getMessage());
        }

        // 2. Accountant role succeeds
        $entry = $this->accountingService->postJournalEntry([
            'voucher_date' => date('Y-m-d'),
            'entry_type' => 'manual',
            'description' => 'Office supplies approved by accountant',
        ], $lines, $this->accountantUser);

        $this->assertInstanceOf(JournalEntry::class, $entry);

        // 3. Dynamically grant 'cashier' role permission via SettingsService
        $this->settingsService->set('accounting', 'manual_journal_roles', 'admin,accountant,cashier');

        $entryByCashier = $this->accountingService->postJournalEntry([
            'voucher_date' => date('Y-m-d'),
            'entry_type' => 'manual',
            'description' => 'Office supplies posted after role expansion',
        ], $lines, $this->cashierUser);

        $this->assertInstanceOf(JournalEntry::class, $entryByCashier);
    }

    public function test_double_entry_balanced_equality_and_tolerance(): void
    {
        $bank = $this->getAccount('1120');
        $sales = $this->getAccount('4120');

        // 1. Significantly unbalanced entry throws
        $unbalancedLines = [
            ['account_id' => $bank->id, 'debit' => 1000.00, 'credit' => 0.00],
            ['account_id' => $sales->id, 'debit' => 0.00, 'credit' => 900.00],
        ];

        try {
            $this->accountingService->postJournalEntry([
                'voucher_date' => date('Y-m-d'),
                'entry_type' => 'manual',
                'description' => 'Unbalanced voucher test',
            ], $unbalancedLines, $this->adminUser);
            $this->fail("Unbalanced journal should fail");
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('is not balanced!', $e->getMessage());
        }

        // 2. Tiny difference within tolerance (0.005 with tolerance 0.01) succeeds
        $this->settingsService->set('accounting', 'max_journal_line_difference_tolerance', 0.01);
        $toleratedLines = [
            ['account_id' => $bank->id, 'debit' => 100.000, 'credit' => 0.000],
            ['account_id' => $sales->id, 'debit' => 0.000, 'credit' => 100.005],
        ];

        $toleratedEntry = $this->accountingService->postJournalEntry([
            'voucher_date' => date('Y-m-d'),
            'entry_type' => 'manual',
            'description' => 'Tolerance test voucher',
        ], $toleratedLines, $this->adminUser);

        $this->assertInstanceOf(JournalEntry::class, $toleratedEntry);

        // 3. Stricter tolerance (0.001) rejects 0.005 difference
        $this->settingsService->set('accounting', 'max_journal_line_difference_tolerance', 0.001);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not balanced!');

        $this->accountingService->postJournalEntry([
            'voucher_date' => date('Y-m-d'),
            'entry_type' => 'manual',
            'description' => 'Strict tolerance test',
        ], $toleratedLines, $this->adminUser);
    }

    public function test_dynamic_vat_rate_and_gl_account_routing_on_order_sale(): void
    {
        // 1. Standard Nepal 13% VAT
        $order1 = Order::create([
            'order_number' => 'ORD-VAT-13',
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'email' => 'aarav@laijau.com',
            'shipping_address' => 'Lazimpat 12',
            'shipping_city' => 'Kathmandu',
            'shipping_postal_code' => '44600',
            'shipping_country' => 'NP',
            'subtotal' => 1000.00,
            'vat_amount' => 130.00,
            'vat_rate' => 13.00,
            'shipping_fee' => 0.00,
            'total_amount' => 1130.00,
            'currency' => 'NPR',
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'connectips',
        ]);

        $entry1 = $this->accountingService->recordOrderSale($order1);
        $this->assertNotNull($entry1);
        $this->assertTrue($entry1->is_balanced);

        // Verify lines: Clearing 1130/1140 debit, Sales 1000 credit, VAT 130 credit
        $vatLine1 = $entry1->lines->firstWhere('account_number', '2120');
        $salesLine1 = $entry1->lines->firstWhere('account_number', '4120');
        $this->assertNotNull($vatLine1);
        $this->assertEquals(130.00, (float)$vatLine1->credit);
        $this->assertEquals(13.00, (float)$vatLine1->vat_rate);
        $this->assertEquals(1000.00, (float)$salesLine1->credit);

        // 2. Change statutory VAT rate to 10.00%
        $this->settingsService->set('accounting', 'standard_vat_rate', 10.00);

        $order2 = Order::create([
            'order_number' => 'ORD-VAT-10',
            'first_name' => 'Bikash',
            'last_name' => 'Thapa',
            'email' => 'bikash@laijau.com',
            'shipping_address' => 'Durbar Marg 40',
            'shipping_city' => 'Kathmandu',
            'shipping_postal_code' => '44600',
            'shipping_country' => 'NP',
            'subtotal' => 1000.00,
            'vat_amount' => 100.00,
            'vat_rate' => 10.00,
            'shipping_fee' => 0.00,
            'total_amount' => 1100.00,
            'currency' => 'NPR',
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'connectips',
        ]);

        $entry2 = $this->accountingService->recordOrderSale($order2);
        $this->assertNotNull($entry2);
        $this->assertTrue($entry2->is_balanced);

        // 1100 / 1.10 = 1000 net, 100 VAT
        $vatLine2 = $entry2->lines->firstWhere('account_number', '2120');
        $salesLine2 = $entry2->lines->firstWhere('account_number', '4120');
        $this->assertNotNull($vatLine2);
        $this->assertEquals(100.00, (float)$vatLine2->credit);
        $this->assertEquals(10.00, (float)$vatLine2->vat_rate);
        $this->assertEquals(1000.00, (float)$salesLine2->credit);
    }

    public function test_inventory_gl_synchronization_toggle(): void
    {
        $product = Product::create([
            'name' => 'Himalayan Yak Cashmere Cardigan',
            'slug' => 'himalayan-yak-cashmere-cardigan',
            'sku' => 'YAK-CARD-001',
            'price' => 2260.00,
            'cost_price' => 800.00,
            'stock_quantity' => 20,
            'is_active' => true,
        ]);

        // 1. With enable_inventory_gl_sync = true (default)
        $this->settingsService->set('accounting', 'enable_inventory_gl_sync', true);

        $order1 = Order::create([
            'order_number' => 'ORD-SYNC-ON',
            'first_name' => 'Sunil',
            'last_name' => 'Shrestha',
            'email' => 'sunil@laijau.com',
            'shipping_address' => 'New Road 2',
            'shipping_city' => 'Kathmandu',
            'shipping_postal_code' => '44600',
            'shipping_country' => 'NP',
            'subtotal' => 2000.00,
            'vat_amount' => 260.00,
            'vat_rate' => 13.00,
            'shipping_fee' => 0.00,
            'total_amount' => 2260.00,
            'currency' => 'NPR',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'connectips',
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 2260.00,
            'total_price' => 2260.00,
        ]);

        $order1->update(['payment_status' => 'paid', 'status' => 'paid']);

        $entry1 = JournalEntry::where('reference_type', 'order')->where('reference_id', $order1->id)->first();
        $this->assertNotNull($entry1);

        // Assert Account 5110 (COGS) and Account 1210 (Inventory) exist
        $cogsLine = $entry1->lines->firstWhere('account_number', '5110');
        $invLine = $entry1->lines->firstWhere('account_number', '1210');
        $this->assertNotNull($cogsLine, "COGS line must be present when GL sync is enabled");
        $this->assertNotNull($invLine, "Inventory reduction line must be present when GL sync is enabled");
        $this->assertEquals(800.00, (float)$cogsLine->debit);
        $this->assertEquals(800.00, (float)$invLine->credit);

        // 2. Disable inventory GL synchronization
        $this->settingsService->set('accounting', 'enable_inventory_gl_sync', false);

        $order2 = Order::create([
            'order_number' => 'ORD-SYNC-OFF',
            'first_name' => 'Prabhat',
            'last_name' => 'Adhikari',
            'email' => 'prabhat@laijau.com',
            'shipping_address' => 'Patan Dhoka 17',
            'shipping_city' => 'Lalitpur',
            'shipping_postal_code' => '44700',
            'shipping_country' => 'NP',
            'subtotal' => 2000.00,
            'vat_amount' => 260.00,
            'vat_rate' => 13.00,
            'shipping_fee' => 0.00,
            'total_amount' => 2260.00,
            'currency' => 'NPR',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'connectips',
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 2260.00,
            'total_price' => 2260.00,
        ]);

        $order2->update(['payment_status' => 'paid', 'status' => 'paid']);

        $entry2 = JournalEntry::where('reference_type', 'order')->where('reference_id', $order2->id)->first();
        $this->assertNotNull($entry2);

        $cogsLine2 = $entry2->lines->firstWhere('account_number', '5110');
        $invLine2 = $entry2->lines->firstWhere('account_number', '1210');
        $this->assertNull($cogsLine2, "COGS line should NOT be present when GL sync is disabled");
        $this->assertNull($invLine2, "Inventory line should NOT be present when GL sync is disabled");
        $this->assertTrue($entry2->is_balanced);
    }

    public function test_custom_numbering_prefixes_for_vouchers_and_invoices(): void
    {
        // 1. Journal Voucher Prefix
        $year = date('Y');
        $defaultVoucherNumber = JournalEntry::generateNextEntryNumber();
        $this->assertStringStartsWith("JV-{$year}-", $defaultVoucherNumber);

        $this->settingsService->set('accounting', 'journal_voucher_prefix', 'VOUCH-');
        $customVoucherNumber = JournalEntry::generateNextEntryNumber();
        $this->assertStringStartsWith("VOUCH-{$year}-", $customVoucherNumber);

        // 2. Sales Invoice Prefix
        $defaultInvoiceNumber = AccountingInvoice::generateNextInvoiceNumber('sales_invoice');
        $this->assertStringStartsWith("INV-{$year}-", $defaultInvoiceNumber);

        $this->settingsService->set('accounting', 'sales_invoice_prefix', 'INV-NP-');
        $customInvoiceNumber = AccountingInvoice::generateNextInvoiceNumber('sales_invoice');
        $this->assertStringStartsWith("INV-NP-{$year}-", $customInvoiceNumber);

        // 3. Credit Note Prefix
        $defaultCreditNoteNumber = AccountingInvoice::generateNextInvoiceNumber('credit_note');
        $this->assertStringStartsWith("CN-{$year}-", $defaultCreditNoteNumber);

        $this->settingsService->set('accounting', 'credit_note_prefix', 'CREDIT-');
        $customCreditNoteNumber = AccountingInvoice::generateNextInvoiceNumber('credit_note');
        $this->assertStringStartsWith("CREDIT-{$year}-", $customCreditNoteNumber);
    }

    public function test_reversal_reason_enforcement_and_closed_period_restrictions(): void
    {
        $bank = $this->getAccount('1120');
        $rent = $this->getAccount('6110');

        $lines = [
            ['account_id' => $rent->id, 'debit' => 3000.00, 'credit' => 0.00],
            ['account_id' => $bank->id, 'debit' => 0.00, 'credit' => 3000.00],
        ];

        $entry = $this->accountingService->postJournalEntry([
            'voucher_date' => date('Y-m-d'),
            'entry_type' => 'manual',
            'description' => 'Husleje forudbetalt',
        ], $lines, $this->adminUser);

        // 1. Reversal without reason fails when require_reversal_reason is true
        $this->settingsService->set('accounting', 'require_reversal_reason', true);

        try {
            $this->accountingService->reverseJournalEntry($entry, '', $this->adminUser);
            $this->fail("Empty reversal reason should fail");
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('valid reason', $e->getMessage());
        }

        // 2. Reversal with valid reason succeeds
        $reversal = $this->accountingService->reverseJournalEntry($entry, 'Korrigeret husleje differens', $this->adminUser);
        $this->assertInstanceOf(JournalEntry::class, $reversal);
        $this->assertEquals('reversed', $entry->fresh()->status);
        $this->assertEquals('Korrigeret husleje differens', $entry->fresh()->reversal_reason);
        $this->assertTrue($reversal->is_balanced);

        // 3. Attempting reversal in closed period when allow_reversals_in_closed_periods is false
        $this->settingsService->set('accounting', 'allow_reversals_in_closed_periods', false);

        $entry2 = $this->accountingService->postJournalEntry([
            'voucher_date' => '2026-03-10',
            'entry_type' => 'manual',
            'description' => 'Marts transaktion',
        ], $lines, $this->adminUser);

        $periodMarch = $entry2->period;
        $periodMarch->lock();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('locked or closed');

        // Backdating reversal into the closed March period
        $this->accountingService->reverseJournalEntry($entry2, 'Fejlagtig bogføring i marts', $this->adminUser, '2026-03-15');
    }

    public function test_configuration_impact_evaluation_for_accounting(): void
    {
        // 1. Statutory VAT rate impact
        $vatImpact = $this->impactService->evaluate('accounting', 'standard_vat_rate', 22.00);
        $this->assertEquals('high', $vatImpact['severity']);
        $this->assertStringContainsString('CRITICAL FISCAL POLICY', $vatImpact['impact_text']);
        $this->assertStringContainsString('22%', $vatImpact['impact_text']);

        // 2. Backdated postings impact
        $backdateImpact = $this->impactService->evaluate('accounting', 'allow_backdated_postings', false);
        $this->assertEquals('high', $backdateImpact['severity']);
        $this->assertStringContainsString('Strict fiscal policy enforced', $backdateImpact['impact_text']);

        // 3. Balanced journal enforcement impact
        $balancedImpact = $this->impactService->evaluate('accounting', 'enforce_balanced_journals', false);
        $this->assertEquals('high', $balancedImpact['severity']);
        $this->assertStringContainsString('CRITICAL AUDIT WARNING', $balancedImpact['impact_text']);

        // 4. Inventory GL sync impact
        $glSyncImpact = $this->impactService->evaluate('accounting', 'enable_inventory_gl_sync', false);
        $this->assertEquals('high', $glSyncImpact['severity']);
        $this->assertStringContainsString('WARNING: Inventory transactions will not synchronize', $glSyncImpact['impact_text']);

        // 5. Inventory valuation method
        $valuationImpact = $this->impactService->evaluate('accounting', 'inventory_valuation_method', 'wac');
        $this->assertEquals('high', $valuationImpact['severity']);
        $this->assertStringContainsString('Changes inventory valuation authority', $valuationImpact['impact_text']);
    }
}
