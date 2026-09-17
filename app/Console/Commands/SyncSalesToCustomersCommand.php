<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OfflineSale;
use App\Models\Order;
use App\Services\Customer\CustomerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncSalesToCustomersCommand extends Command
{
    protected $signature = 'laijau:sync-sales-to-customers';
    protected $description = 'Normalize contact numbers strictly to 10 digits and fully sync all sales to unified customer profiles';

    public function handle(CustomerService $customerService): int
    {
        $this->info('Starting authoritative synchronization of sales to customers...');

        $stats = DB::transaction(function () use ($customerService) {
            return $customerService->syncGuestOrdersAndOfflineSales();
        });

        // Calculate repeat customer metrics
        $repeatCustomerSales = OfflineSale::whereNotNull('user_id')
            ->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->having('count', '>', 1)
            ->get();

        $repeatCustomerOrders = Order::whereNotNull('user_id')
            ->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->having('count', '>', 1)
            ->get();

        $this->newLine();
        $this->info('=== SALES TO CUSTOMER SYNCHRONIZATION COMPLETE ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Offline POS Sales Linked to Customer', number_format($stats['sales_linked'])],
                ['Online Orders Linked to Customer', number_format($stats['orders_linked'])],
                ['New Unified Customers Created', number_format($stats['customers_created'])],
                ['Total Customers in System', number_format($stats['total_customers'])],
                ['Repeat POS Customers (>1 sale)', number_format($repeatCustomerSales->count())],
                ['Repeat Online Customers (>1 order)', number_format($repeatCustomerOrders->count())],
            ]
        );

        return self::SUCCESS;
    }
}
