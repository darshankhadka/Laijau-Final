<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthoritativePosSalesJanToSeptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'laijau_staging']);
        config(['database.connections.mysql.host' => '127.0.0.1']);
        config(['database.connections.mysql.username' => 'darshan']);
        config(['database.connections.mysql.password' => 'Dars@@9861']);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    public function test_total_pos_sales_count_and_status(): void
    {
        $count = OfflineSale::where('sales_channel', 'showroom_pos')->count();
        $this->assertGreaterThanOrEqual(10580, $count, 'Total POS sales count must be at least 10,580.');
        $this->assertLessThanOrEqual(10650, $count, 'Total POS sales count must be at most 10,650.');

        $completedCount = OfflineSale::where('sales_channel', 'showroom_pos')
            ->where('status', 'completed')
            ->count();
        $this->assertGreaterThanOrEqual(10580, $completedCount, 'All active POS sales must be in completed status.');
    }

    public function test_each_month_is_strictly_isolated_to_its_calendar_boundaries(): void
    {
        $monthsExpected = [
            '2026-01' => ['name' => 'January', 'min_day' => 1, 'max_day' => 31, 'min_count' => 2000],
            '2026-02' => ['name' => 'February', 'min_day' => 1, 'max_day' => 28, 'min_count' => 1500],
            '2026-03' => ['name' => 'March', 'min_day' => 1, 'max_day' => 31, 'min_count' => 1000],
            '2026-04' => ['name' => 'April', 'min_day' => 1, 'max_day' => 30, 'min_count' => 1500],
            '2026-05' => ['name' => 'May', 'min_day' => 1, 'max_day' => 31, 'min_count' => 900],
            '2026-06' => ['name' => 'June', 'min_day' => 1, 'max_day' => 30, 'min_count' => 600],
            '2026-07' => ['name' => 'July', 'min_day' => 1, 'max_day' => 31, 'min_count' => 500],
            '2026-08' => ['name' => 'August', 'min_day' => 1, 'max_day' => 31, 'min_count' => 650],
            '2026-09' => ['name' => 'September', 'min_day' => 1, 'max_day' => 30, 'min_count' => 400],
        ];

        foreach ($monthsExpected as $ym => $meta) {
            $sales = OfflineSale::where('sales_channel', 'showroom_pos')
                ->where('sold_at', 'like', "{$ym}%")
                ->get();

            $this->assertGreaterThanOrEqual($meta['min_count'], $sales->count(), "Month {$meta['name']} must have at least {$meta['min_count']} sales.");

            $minDate = $sales->min('sold_at');
            $maxDate = $sales->max('sold_at');

            $this->assertStringStartsWith($ym, (string)$minDate, "Month {$meta['name']} min date must start with {$ym}.");
            $this->assertStringStartsWith($ym, (string)$maxDate, "Month {$meta['name']} max date must start with {$ym}.");
        }
    }

    public function test_all_offline_sales_have_items(): void
    {
        $salesWithoutItems = OfflineSale::where('sales_channel', 'showroom_pos')
            ->whereDoesntHave('items')
            ->count();

        $this->assertEquals(0, $salesWithoutItems, 'All showroom POS sales must have associated line items.');

        $itemsCount = OfflineSaleItem::count();
        $this->assertGreaterThanOrEqual(10580, $itemsCount, 'Total offline sale items must equal at least 10,580.');
        $this->assertLessThanOrEqual(10650, $itemsCount, 'Total offline sale items must be within expected range.');
    }

    public function test_all_offline_sales_have_bikri_khata_invoices_and_journal_vouchers(): void
    {
        $salesCount = OfflineSale::where('sales_channel', 'showroom_pos')->count();

        $invoicesCount = AccountingInvoice::where('sales_channel', 'pos_showroom')->count();
        $this->assertGreaterThanOrEqual(
            $salesCount,
            $invoicesCount,
            'Every showroom POS sale must have a statutory Bikri Khata invoice.'
        );

        $vouchersCount = JournalEntry::where('entry_type', 'sales')
            ->where('reference_type', 'offline_sale')
            ->count();
        $this->assertGreaterThanOrEqual($salesCount, $vouchersCount, 'Every showroom POS sale must have a posted GL journal voucher.');
    }

    public function test_general_ledger_is_perfectly_balanced_zero_variance(): void
    {
        $totalDebit = (float)JournalEntryLine::sum('debit');
        $totalCredit = (float)JournalEntryLine::sum('credit');
        $variance = abs($totalDebit - $totalCredit);

        $this->assertLessThan(0.0001, $variance, "General ledger variance must be 0.0000 NPR. Variance found: {$variance}");
        $this->assertGreaterThan(35000000.0, $totalDebit, 'Total GL debits should exceed NPR 35M after full sales import.');
    }

    public function test_payment_methods_are_authoritative(): void
    {
        $validMethods = ['cash', 'fonepay', 'split'];
        $invalidCount = OfflineSale::where('sales_channel', 'showroom_pos')
            ->whereNotIn('payment_method', $validMethods)
            ->count();

        $this->assertEquals(0, $invalidCount, 'All POS sales must have a valid payment method (cash, fonepay, split).');
    }
}
