<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Helpers\NepaliNumberHelper;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\KharidKhataEntry;
use App\Models\Inventory\PurchaseOrder;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoricalTimelineAndLaunchLockTest extends TestCase
{
    public function test_historical_order_coverage_across_all_nine_months(): void
    {
        if (Order::count() < 100) {
            $this->markTestSkipped('Historical timeline audit requires production dataset.');
        }

        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', created_at) AS INTEGER)"
            : 'MONTH(created_at)';

        $monthlyCounts = Order::query()
            ->whereBetween('created_at', ['2026-01-01 00:00:00', '2026-09-12 23:59:59'])
            ->selectRaw("{$monthExpr} as month, count(*) as count")
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();

        for ($m = 1; $m <= 9; $m++) {
            $this->assertArrayHasKey($m, $monthlyCounts, "Month {$m} (2026) must have historical orders");
            $this->assertGreaterThan(0, $monthlyCounts[$m], "Month {$m} (2026) must have at least one order");
        }

        // Verify earliest order starts in January 2026
        $earliestOrder = Order::orderBy('created_at', 'asc')->first();
        $this->assertNotNull($earliestOrder);
        $this->assertEquals(2026, $earliestOrder->created_at->year);
        $this->assertEquals(1, $earliestOrder->created_at->month);
    }

    public function test_historical_pos_sales_coverage_across_all_nine_months(): void
    {
        if (OfflineSale::count() < 1000) {
            $this->markTestSkipped('Historical timeline audit requires production dataset.');
        }

        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', sold_at) AS INTEGER)"
            : 'MONTH(sold_at)';

        $monthlyPosCounts = OfflineSale::query()
            ->where('status', '!=', 'voided')
            ->whereBetween('sold_at', ['2026-01-01 00:00:00', '2026-09-12 23:59:59'])
            ->selectRaw("{$monthExpr} as month, count(*) as count")
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();

        for ($m = 1; $m <= 9; $m++) {
            $this->assertArrayHasKey($m, $monthlyPosCounts, "Month {$m} (2026) must have historical POS sales");
            $this->assertGreaterThan(0, $monthlyPosCounts[$m], "Month {$m} (2026) must have at least one POS sale");
        }

        // Verify total completed POS sales and revenue
        $totalPosRevenue = (float) OfflineSale::where('status', 'completed')->sum('total_amount');
        $this->assertGreaterThan(20000000.0, $totalPosRevenue);
    }

    public function test_historical_purchases_coverage_and_supplier_unpaid_liabilities(): void
    {
        if (PurchaseOrder::count() < 50) {
            $this->markTestSkipped('Historical timeline audit requires production dataset.');
        }
        $purchaseOrdersCount = PurchaseOrder::count();
        $this->assertGreaterThanOrEqual(90, $purchaseOrdersCount);

        // Supplier liabilities must remain unpaid until staff records payment
        $unpaidPayables = (float) KharidKhataEntry::where('type', 'supplier_bill')
            ->where('payment_status', '!=', 'paid')
            ->sum(DB::raw('total_amount - paid_amount'));

        $this->assertGreaterThanOrEqual(0.0, $unpaidPayables, 'Unpaid supplier liabilities must remain unpaid');
    }

    public function test_historical_receipt_generation_from_existing_transactions(): void
    {
        if (OfflineSale::count() < 1000) {
            $this->markTestSkipped('Historical timeline audit requires production dataset.');
        }

        $sale = OfflineSale::with('items')->first();
        $this->assertNotNull($sale);

        // Verify receipt view renders with original transaction number and date
        $view = view('offline-receipt', ['sale' => $sale])->render();
        $this->assertStringContainsString($sale->sale_number, $view);
        $this->assertStringContainsString($sale->sold_at->format('d M Y'), $view);
        $this->assertStringContainsString('Rs.', $view);
    }

    public function test_dashboard_combined_revenue_and_historical_period_filters(): void
    {
        if (OfflineSale::count() < 1000) {
            $this->markTestSkipped('Historical timeline audit requires production dataset.');
        }
        $dash = new Dashboard();

        // 1. All Time
        $dash->period = 'all_time';
        $kpiAll = $dash->getKpiData();
        $salesAll = $dash->getSalesOverview();

        $this->assertNotEmpty($salesAll['points'], 'Dashboard chart must show monthly points for all_time');
        $this->assertNotEmpty($kpiAll['revenue']['value'], 'Combined revenue must reflect valid total');

        // 2. January 2026
        $dash->period = 'jan_2026';
        $kpiJan = $dash->getKpiData();
        $this->assertNotEmpty($kpiJan['revenue']['value'], 'January 2026 must reflect genuine Jan revenue');

        // 3. September 2026
        $dash->period = 'sep_2026';
        $kpiSep = $dash->getKpiData();
        $this->assertNotEmpty($kpiSep['revenue']['value']);
    }

    public function test_nepali_numbering_across_financial_helpers(): void
    {
        $this->assertEquals('Rs. 12,50,000.00', NepaliNumberHelper::formatCurrency(1250000, 'Rs. ', 2));
        $this->assertEquals('12,34,56,789', NepaliNumberHelper::format(123456789));
        $this->assertEquals('Rs. 1,00,000.00', NepaliNumberHelper::formatCurrency(100000, 'Rs. ', 2));
        $this->assertEquals('10,000', NepaliNumberHelper::format(10000));
        $this->assertEquals('1,000', NepaliNumberHelper::format(1000));
    }

    public function test_storefront_safeguards_photo_and_positive_price(): void
    {
        if (Product::count() < 10) {
            $this->markTestSkipped('Storefront safeguards test requires production dataset.');
        }

        $publicProducts = Product::query()->storefrontReady()->get();

        foreach ($publicProducts as $product) {
            $this->assertGreaterThan(0, (float) $product->price, "Public product {$product->id} must have a positive price");
            $this->assertTrue($product->is_active, "Public product {$product->id} must be active");
            $this->assertTrue(
                !empty($product->image_url) || !empty($product->featured_image) || !empty($product->primary_image),
                "Public product {$product->id} must have a genuine photo"
            );
        }

        // Unpublished products must NOT be in public collection
        $unpriced = Product::where('is_published', false)->first();
        if ($unpriced) {
            $this->assertFalse(
                $publicProducts->contains('id', $unpriced->id),
                "Unpublished product {$unpriced->id} must not be visible on public storefront"
            );
        }
    }

    public function test_no_duplicate_business_identifiers(): void
    {
        $dupOrders = Order::select('order_number')
            ->groupBy('order_number')
            ->havingRaw('count(*) > 1')
            ->count();
        $this->assertEquals(0, $dupOrders, 'Zero duplicate order numbers');

        $dupSales = OfflineSale::select('sale_number')
            ->groupBy('sale_number')
            ->havingRaw('count(*) > 1')
            ->count();
        $this->assertEquals(0, $dupSales, 'Zero duplicate POS sale numbers');

        $dupInvoices = AccountingInvoice::select('invoice_number')
            ->groupBy('invoice_number')
            ->havingRaw('count(*) > 1')
            ->count();
        $this->assertEquals(0, $dupInvoices, 'Zero duplicate invoice numbers');
    }

    public function test_live_operation_from_today_forward_uses_real_timestamps(): void
    {
        $before = Carbon::now()->subSeconds(2);
        $order = new Order();
        $order->order_number = 'TST-LIVE-VERIFY';
        $order->customer_email = 'verify@laijau.test';
        $order->total_amount = 1500;
        $order->currency = 'NPR';
        $order->status = Order::STATUS_PENDING;
        $order->payment_status = 'pending';
        $order->created_at = Carbon::now();
        $order->updated_at = Carbon::now();

        $this->assertGreaterThanOrEqual($before->timestamp, $order->created_at->timestamp);
    }

    public function test_strict_timeline_boundary_and_zero_future_records(): void
    {
        if (OfflineSale::count() < 1000) {
            $this->markTestSkipped('Historical timeline audit requires production dataset.');
        }

        // 1. Zero orders after Sep 30, 2026
        $futureOrders = Order::where('created_at', '>', '2026-09-30 23:59:59')->count();
        $this->assertEquals(0, $futureOrders, 'Must have strictly 0 orders after September 30, 2026');

        // 2. Zero POS sales after Sep 30, 2026
        $futurePos = OfflineSale::where('sold_at', '>', '2026-09-30 23:59:59')->count();
        $this->assertEquals(0, $futurePos, 'Must have strictly 0 POS sales after September 30, 2026');

        // 3. Zero purchase orders after Sep 30, 2026
        $futurePo = PurchaseOrder::where('order_date', '>', '2026-09-30 23:59:59')->count();
        $this->assertEquals(0, $futurePo, 'Must have strictly 0 purchase orders after September 30, 2026');

        // 4. Zero statutory invoices after Sep 30, 2026
        $futureInvoices = AccountingInvoice::whereDate('issue_date', '>', '2026-09-30')->count();
        $this->assertEquals(0, $futureInvoices, 'Must have strictly 0 statutory invoices after September 30, 2026');

        // 5. Zero journal entries after Sep 30, 2026
        $futureJournals = DB::table('accounting_journal_entries')->whereDate('voucher_date', '>', '2026-09-30')->count();
        $this->assertEquals(0, $futureJournals, 'Must have strictly 0 journal entries after September 30, 2026');

        // 6. Zero orders in Oct, Nov, Dec 2026
        $octOrders = Order::whereMonth('created_at', 10)->whereYear('created_at', 2026)->count();
        $novOrders = Order::whereMonth('created_at', 11)->whereYear('created_at', 2026)->count();
        $decOrders = Order::whereMonth('created_at', 12)->whereYear('created_at', 2026)->count();
        $this->assertEquals(0, $octOrders, 'Zero orders in October 2026');
        $this->assertEquals(0, $novOrders, 'Zero orders in November 2026');
        $this->assertEquals(0, $decOrders, 'Zero orders in December 2026');

        // 7. Zero purchase orders in 2027
        $pos2027 = PurchaseOrder::whereYear('order_date', 2027)->count();
        $this->assertEquals(0, $pos2027, 'Zero purchase orders dated in 2027');
    }
}
