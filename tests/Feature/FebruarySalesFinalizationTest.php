<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\JournalEntry;
use App\Models\OfflineSale;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FebruarySalesFinalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'LAIJAU']);
        DB::purge('mysql');
    }

    public function test_all_february_sales_are_showroom_pos_sales(): void
    {
        $sales = OfflineSale::whereBetween('id', [9472, 11234])->get();
        $this->assertEquals(1763, $sales->count(), 'All 1,763 February sales must exist in the database.');

        foreach ($sales as $sale) {
            $this->assertEquals('showroom_pos', $sale->sales_channel, "Sale #{$sale->sale_number} must be showroom_pos.");
        }
    }

    public function test_february_sales_span_all_28_days_continuously(): void
    {
        $distinctDays = OfflineSale::whereBetween('id', [9472, 11234])
            ->selectRaw('COUNT(DISTINCT DATE(sold_at)) as days')
            ->value('days');

        $this->assertEquals(28, (int)$distinctDays, 'February sales must span all 28 days from Feb 1 to Feb 28.');

        for ($day = 1; $day <= 28; $day++) {
            $dateStr = sprintf('2026-02-%02d', $day);
            $dayCount = OfflineSale::whereBetween('id', [9472, 11234])
                ->whereDate('sold_at', $dateStr)
                ->count();

            $this->assertGreaterThan(0, $dayCount, "Day {$dateStr} must have active showroom sales.");
        }
    }

    public function test_no_name_is_standardized_to_walk_in_customer_and_named_preserved(): void
    {
        $noNameCount = OfflineSale::whereBetween('id', [9472, 11234])
            ->where(function ($q) {
                $q->where('customer_name', 'like', '%no name%')
                  ->orWhere('customer_name', 'like', '%walk-in showroom customer%')
                  ->orWhereNull('customer_name')
                  ->orWhere('customer_name', '');
            })
            ->count();

        $this->assertEquals(0, $noNameCount, 'Zero sales should have "No Name" or "Walk-in Showroom Customer".');

        $walkInCount = OfflineSale::whereBetween('id', [9472, 11234])
            ->where('customer_name', 'Walk-in Customer')
            ->count();
        $this->assertGreaterThanOrEqual(1750, $walkInCount, 'Most sales without specific names must be "Walk-in Customer".');

        $namedCount = OfflineSale::whereBetween('id', [9472, 11234])
            ->where('customer_name', '!=', 'Walk-in Customer')
            ->count();
        $this->assertGreaterThanOrEqual(5, $namedCount, 'Authentic named customer records must be preserved.');
    }

    public function test_payment_methods_are_strictly_cash_and_fonepay(): void
    {
        $invalidPmCount = OfflineSale::whereBetween('id', [9472, 11234])
            ->whereNotIn('payment_method', ['cash', 'fonepay'])
            ->count();

        $this->assertEquals(0, $invalidPmCount, 'All payment methods must be strictly cash or fonepay.');

        $cashCount = OfflineSale::whereBetween('id', [9472, 11234])->where('payment_method', 'cash')->count();
        $fonepayCount = OfflineSale::whereBetween('id', [9472, 11234])->where('payment_method', 'fonepay')->count();

        $this->assertGreaterThan(600, $cashCount, 'Cash payments must be accurately recorded.');
        $this->assertGreaterThan(1000, $fonepayCount, 'Fonepay payments must be accurately recorded.');
    }

    public function test_bikri_khata_and_general_ledger_are_100_percent_synced(): void
    {
        $unmatchedInvoices = DB::table('offline_sales')
            ->join('accounting_invoices', 'accounting_invoices.reference_offline_sale_id', '=', 'offline_sales.id')
            ->whereBetween('offline_sales.id', [9472, 11234])
            ->where(function ($q) {
                $q->whereRaw('DATE(offline_sales.sold_at) != accounting_invoices.issue_date')
                  ->orWhereRaw('offline_sales.customer_name != accounting_invoices.contact_name')
                  ->orWhere('accounting_invoices.sales_channel', '!=', 'pos_showroom')
                  ->orWhere('accounting_invoices.posted_to_gl', '!=', 1);
            })
            ->count();

        $this->assertEquals(0, $unmatchedInvoices, 'All Bikri Khata invoices must match the offline sales date, name, channel, and GL status.');

        $unmatchedVouchers = DB::table('offline_sales')
            ->join('accounting_journal_entries', function ($j) {
                $j->on('accounting_journal_entries.reference_id', '=', 'offline_sales.id')
                  ->where('accounting_journal_entries.reference_type', '=', 'offline_sale');
            })
            ->whereBetween('offline_sales.id', [9472, 11234])
            ->whereRaw('DATE(offline_sales.sold_at) != DATE(accounting_journal_entries.voucher_date)')
            ->count();

        $this->assertEquals(0, $unmatchedVouchers, 'All GL journal entries must match the offline sale date.');

        // General Ledger Balance
        $totDebit = (float)DB::table('accounting_journal_entry_lines')->sum('debit');
        $totCredit = (float)DB::table('accounting_journal_entry_lines')->sum('credit');
        $this->assertEquals(0.0, round(abs($totDebit - $totCredit), 4), 'General ledger must have 0.0000 variance.');
    }
}
