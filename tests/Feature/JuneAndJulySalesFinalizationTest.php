<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\OfflineSale;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JuneAndJulySalesFinalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'LAIJAU']);
        DB::purge('mysql');
    }

    public function test_june_and_july_showroom_sales_count_and_channels(): void
    {
        // June: IDs 7067 to 7830 (764 sales)
        $juneSales = OfflineSale::whereBetween('id', [7067, 7830])->get();
        $this->assertEquals(764, $juneSales->count(), 'All 764 June sales must exist.');
        foreach ($juneSales as $sale) {
            $this->assertEquals('showroom_pos', $sale->sales_channel, "June Sale #{$sale->sale_number} must be showroom_pos.");
        }

        // July: IDs 7831 to 8470 (640 sales)
        $julySales = OfflineSale::whereBetween('id', [7831, 8470])->get();
        $this->assertEquals(640, $julySales->count(), 'All 640 July sales must exist.');
        foreach ($julySales as $sale) {
            $this->assertEquals('showroom_pos', $sale->sales_channel, "July Sale #{$sale->sale_number} must be showroom_pos.");
        }
    }

    public function test_june_dates_cover_all_30_days_continuously(): void
    {
        $distinctDays = OfflineSale::whereBetween('id', [7067, 7830])
            ->selectRaw('COUNT(DISTINCT DATE(sold_at)) as days')
            ->value('days');

        $this->assertEquals(30, (int)$distinctDays, 'June sales must span all 30 days from Jun 1 to Jun 30.');

        for ($day = 1; $day <= 30; $day++) {
            $dateStr = sprintf('2026-06-%02d', $day);
            $dayCount = OfflineSale::whereBetween('id', [7067, 7830])
                ->whereDate('sold_at', $dateStr)
                ->count();

            $this->assertGreaterThan(0, $dayCount, "June {$dateStr} must have active showroom sales.");
        }
    }

    public function test_july_dates_cover_all_31_days_continuously(): void
    {
        $distinctDays = OfflineSale::whereBetween('id', [7831, 8470])
            ->selectRaw('COUNT(DISTINCT DATE(sold_at)) as days')
            ->value('days');

        $this->assertEquals(31, (int)$distinctDays, 'July sales must span all 31 days from Jul 1 to Jul 31.');

        for ($day = 1; $day <= 31; $day++) {
            $dateStr = sprintf('2026-07-%02d', $day);
            $dayCount = OfflineSale::whereBetween('id', [7831, 8470])
                ->whereDate('sold_at', $dateStr)
                ->count();

            $this->assertGreaterThan(0, $dayCount, "July {$dateStr} must have active showroom sales.");
        }
    }

    public function test_customer_names_standardized_to_walk_in_customer_with_authentic_names_preserved(): void
    {
        $noNameCount = OfflineSale::whereBetween('id', [7067, 8470])
            ->where(function ($q) {
                $q->where('customer_name', 'like', '%no name%')
                  ->orWhere('customer_name', 'like', '%cash added%')
                  ->orWhere('customer_name', 'like', '%opening balance%')
                  ->orWhere('customer_name', 'like', '%khaja%')
                  ->orWhere('customer_name', 'like', '%online halako%')
                  ->orWhereNull('customer_name')
                  ->orWhere('customer_name', '');
            })
            ->count();

        $this->assertEquals(0, $noNameCount, 'Zero sales should have "No Name" or raw operational placeholders.');

        // Verify authentic customer names are preserved
        $sampleAuthentic = [
            'Mamta Bhandari', 'Sarwan Shrestha', 'Arjun Shrestha', 'Sunny Lama',
            'Bigyan Paudel', 'Rojan', 'Kushal Magar', 'Shankhar', 'Sanjay Shah'
        ];
        $actualNames = OfflineSale::whereBetween('id', [7067, 8470])
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
        $invalidPmCount = OfflineSale::whereBetween('id', [7067, 8470])
            ->whereNotIn('payment_method', ['cash', 'fonepay'])
            ->count();

        $this->assertEquals(0, $invalidPmCount, 'All payment methods must be strictly cash or fonepay.');

        $cashCount = OfflineSale::whereBetween('id', [7067, 8470])->where('payment_method', 'cash')->count();
        $fonepayCount = OfflineSale::whereBetween('id', [7067, 8470])->where('payment_method', 'fonepay')->count();

        $this->assertGreaterThan(400, $cashCount, 'Cash payments must be accurately recorded.');
        $this->assertGreaterThan(900, $fonepayCount, 'Fonepay payments must be accurately recorded.');
    }

    public function test_june_and_july_general_ledger_and_bikri_khata_synchronized_and_balanced(): void
    {
        $unmatchedInvoices = DB::table('offline_sales')
            ->join('accounting_invoices', 'accounting_invoices.reference_offline_sale_id', '=', 'offline_sales.id')
            ->whereBetween('offline_sales.id', [7067, 8470])
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
            ->whereBetween('offline_sales.id', [7067, 8470])
            ->whereRaw('DATE(offline_sales.sold_at) != DATE(accounting_journal_entries.voucher_date)')
            ->count();

        $this->assertEquals(0, $unmatchedVouchers, 'All GL journal entries must match offline sale date.');

        // General Ledger Balance
        $totDebit = (float)DB::table('accounting_journal_entry_lines')->sum('debit');
        $totCredit = (float)DB::table('accounting_journal_entry_lines')->sum('credit');
        $this->assertEquals(0.0, round(abs($totDebit - $totCredit), 4), 'General ledger must have 0.0000 variance.');
    }
}
