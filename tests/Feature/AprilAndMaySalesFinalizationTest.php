<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\OfflineSale;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AprilAndMaySalesFinalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'LAIJAU']);
        DB::purge('mysql');
    }

    public function test_april_and_may_showroom_sales_count_and_channels(): void
    {
        // April: IDs 3962 to 5992 (2,031 sales)
        $aprSales = OfflineSale::whereBetween('id', [3962, 5992])->get();
        $this->assertEquals(2031, $aprSales->count(), 'All 2,031 April sales must exist.');
        foreach ($aprSales as $sale) {
            $this->assertEquals('showroom_pos', $sale->sales_channel, "April Sale #{$sale->sale_number} must be showroom_pos.");
        }

        // May: IDs 5993 to 7066 (1,074 sales)
        $maySales = OfflineSale::whereBetween('id', [5993, 7066])->get();
        $this->assertEquals(1074, $maySales->count(), 'All 1,074 May sales must exist.');
        foreach ($maySales as $sale) {
            $this->assertEquals('showroom_pos', $sale->sales_channel, "May Sale #{$sale->sale_number} must be showroom_pos.");
        }
    }

    public function test_april_dates_cover_all_30_days_continuously(): void
    {
        $distinctDays = OfflineSale::whereBetween('id', [3962, 5992])
            ->selectRaw('COUNT(DISTINCT DATE(sold_at)) as days')
            ->value('days');

        $this->assertEquals(30, (int)$distinctDays, 'April sales must span all 30 days from Apr 1 to Apr 30.');

        for ($day = 1; $day <= 30; $day++) {
            $dateStr = sprintf('2026-04-%02d', $day);
            $dayCount = OfflineSale::whereBetween('id', [3962, 5992])
                ->whereDate('sold_at', $dateStr)
                ->count();

            $this->assertGreaterThan(0, $dayCount, "April {$dateStr} must have active showroom sales.");
        }
    }

    public function test_may_dates_cover_all_31_days_continuously(): void
    {
        $distinctDays = OfflineSale::whereBetween('id', [5993, 7066])
            ->selectRaw('COUNT(DISTINCT DATE(sold_at)) as days')
            ->value('days');

        $this->assertEquals(31, (int)$distinctDays, 'May sales must span all 31 days from May 1 to May 31.');

        for ($day = 1; $day <= 31; $day++) {
            $dateStr = sprintf('2026-05-%02d', $day);
            $dayCount = OfflineSale::whereBetween('id', [5993, 7066])
                ->whereDate('sold_at', $dateStr)
                ->count();

            $this->assertGreaterThan(0, $dayCount, "May {$dateStr} must have active showroom sales.");
        }
    }

    public function test_customer_names_standardized_to_walk_in_customer_with_authentic_names_preserved(): void
    {
        $noNameCount = OfflineSale::whereBetween('id', [3962, 7066])
            ->where(function ($q) {
                $q->where('customer_name', 'like', '%no name%')
                  ->orWhere('customer_name', 'like', '%cash added%')
                  ->orWhere('customer_name', 'like', '%opening balance%')
                  ->orWhere('customer_name', 'like', '%khaja%')
                  ->orWhereNull('customer_name')
                  ->orWhere('customer_name', '');
            })
            ->count();

        $this->assertEquals(0, $noNameCount, 'Zero sales should have "No Name" or raw operational placeholders.');

        // Verify authentic customer names are preserved
        $sampleAuthentic = [
            'Anil Yadav', 'Binod KC', 'Bibek Rawal', 'Tina Tamang', 'Sushila Madhikarmi',
            'Rabi Prajapati', 'Namraj Shahi', 'Srijan Thapa', 'Khagendra Thapa', 'Rajan Bishal'
        ];
        $actualNames = OfflineSale::whereBetween('id', [3962, 7066])
            ->pluck('customer_name')
            ->unique()
            ->values()
            ->all();

        foreach ($sampleAuthentic as $name) {
            $this->assertContains($name, $actualNames, "Authentic customer {$name} must be preserved.");
        }
    }

    public function test_payment_methods_are_strictly_cash_and_fonepay(): void
    {
        $invalidPmCount = OfflineSale::whereBetween('id', [3962, 7066])
            ->whereNotIn('payment_method', ['cash', 'fonepay'])
            ->count();

        $this->assertEquals(0, $invalidPmCount, 'All payment methods must be strictly cash or fonepay.');

        $cashCount = OfflineSale::whereBetween('id', [3962, 7066])->where('payment_method', 'cash')->count();
        $fonepayCount = OfflineSale::whereBetween('id', [3962, 7066])->where('payment_method', 'fonepay')->count();

        $this->assertGreaterThan(1000, $cashCount, 'Cash payments must be accurately recorded.');
        $this->assertGreaterThan(1500, $fonepayCount, 'Fonepay payments must be accurately recorded.');
    }

    public function test_april_and_may_general_ledger_and_bikri_khata_synchronized_and_balanced(): void
    {
        $unmatchedInvoices = DB::table('offline_sales')
            ->join('accounting_invoices', 'accounting_invoices.reference_offline_sale_id', '=', 'offline_sales.id')
            ->whereBetween('offline_sales.id', [3962, 7066])
            ->where(function ($q) {
                $q->whereRaw('DATE(offline_sales.sold_at) != accounting_invoices.issue_date')
                  ->orWhereRaw('offline_sales.customer_name != accounting_invoices.contact_name')
                  ->orWhere('accounting_invoices.sales_channel', '!=', 'pos_showroom')
                  ->orWhere('accounting_invoices.posted_to_gl', '!=', 1);
            })
            ->count();

        $this->assertEquals(0, $unmatchedInvoices, 'All Bikri Khata invoices must match offline sales date, name, channel, and GL status.');

        $unmatchedVouchers = DB::table('offline_sales')
            ->join('accounting_journal_entries', function ($j) {
                $j->on('accounting_journal_entries.reference_id', '=', 'offline_sales.id')
                  ->where('accounting_journal_entries.reference_type', '=', 'offline_sale');
            })
            ->whereBetween('offline_sales.id', [3962, 7066])
            ->whereRaw('DATE(offline_sales.sold_at) != DATE(accounting_journal_entries.voucher_date)')
            ->count();

        $this->assertEquals(0, $unmatchedVouchers, 'All GL journal entries must match offline sale date.');

        // General Ledger Balance
        $totDebit = (float)DB::table('accounting_journal_entry_lines')->sum('debit');
        $totCredit = (float)DB::table('accounting_journal_entry_lines')->sum('credit');
        $this->assertEquals(0.0, round(abs($totDebit - $totCredit), 4), 'General ledger must have 0.0000 variance.');
    }
}
