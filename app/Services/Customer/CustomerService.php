<?php

namespace App\Services\Customer;

use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerService
{
    /**
     * Normalize Nepal phone numbers strictly to 10 digits (stripping +977, 977, spaces, dashes).
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $clean = preg_replace('/\D/', '', (string)$phone);

        // Strip Nepal country code 977 if present (e.g. 9779841234567 -> 9841234567)
        if (str_starts_with($clean, '977') && strlen($clean) >= 13) {
            $clean = substr($clean, 3);
        } elseif (strlen($clean) > 10) {
            $clean = substr($clean, -10);
        }

        // Strictly enforce 10 digits allowed
        if (strlen($clean) === 10) {
            return $clean;
        }

        return null;
    }

    /**
     * Resolve or create a unified customer record using contact number as the primary link key.
     */
    public function resolveOrCreateCustomer(
        ?string $name,
        ?string $phone = null,
        ?string $email = null,
        array $addressData = []
    ): User {
        $cleanPhone = self::normalizePhone($phone);
        $cleanEmail = !empty($email) ? strtolower(trim($email)) : null;

        $customer = null;

        // 1. Primary link key: Match by authoritative 10-digit normalized phone
        if ($cleanPhone) {
            $customer = User::where('role', 'customer')
                ->where(function (Builder $query) use ($cleanPhone) {
                    $query->where('phone', $cleanPhone)
                        ->orWhere('phone', 'like', "%{$cleanPhone}");
                })
                ->first();
        }

        // 2. Secondary fallback: Match by email (excluding generic guest/offline placeholders)
        if (!$customer && $cleanEmail && !str_starts_with($cleanEmail, 'guest_') && !str_starts_with($cleanEmail, 'customer_') && !str_starts_with($cleanEmail, 'offline-')) {
            $customer = User::where('role', 'customer')
                ->where('email', $cleanEmail)
                ->first();
        }

        // 3. If found, update missing or unnormalized details non-destructively
        if ($customer) {
            $updates = [];
            if ($cleanPhone && $customer->phone !== $cleanPhone) {
                $updates['phone'] = $cleanPhone;
            }
            $cleanName = trim((string)$name);
            if (!empty($cleanName) && $cleanName !== 'Walk-in Customer' && (empty($customer->name) || str_starts_with($customer->name, 'Customer ') || $customer->name === 'Retail Customer')) {
                $updates['name'] = $cleanName;
            }
            if ($cleanEmail && (empty($customer->email) || str_contains($customer->email, '@laijau.com'))) {
                if (!User::where('email', $cleanEmail)->where('id', '!=', $customer->id)->exists()) {
                    $updates['email'] = $cleanEmail;
                }
            }
            if (empty($customer->district) && !empty($addressData['district'])) {
                $updates['district'] = $addressData['district'];
            }
            if (empty($customer->municipality) && !empty($addressData['municipality'])) {
                $updates['municipality'] = $addressData['municipality'];
            }
            if (empty($customer->province) && !empty($addressData['province'])) {
                $updates['province'] = $addressData['province'];
            }
            if (empty($customer->ward) && !empty($addressData['ward'])) {
                $updates['ward'] = $addressData['ward'];
            }
            if (empty($customer->tole) && !empty($addressData['tole'])) {
                $updates['tole'] = $addressData['tole'];
            }
            if (!empty($updates)) {
                $customer->updateQuietly($updates);
            }
            return $customer;
        }

        // 4. Create new customer user record
        $generatedEmail = $cleanEmail;
        if (!$generatedEmail || User::where('email', $generatedEmail)->exists()) {
            $basePhone = $cleanPhone ?: Str::random(8);
            $generatedEmail = "customer_{$basePhone}@laijau.com";
            $suffix = 1;
            while (User::where('email', $generatedEmail)->exists()) {
                $generatedEmail = "customer_{$basePhone}_{$suffix}@laijau.com";
                $suffix++;
            }
        }

        $customerName = trim((string)$name);
        if (empty($customerName) || strtolower($customerName) === 'walk-in customer') {
            $customerName = $cleanPhone ? "Customer {$cleanPhone}" : 'Retail Customer';
        }

        return User::create([
            'name' => $customerName,
            'email' => $generatedEmail,
            'phone' => $cleanPhone,
            'role' => 'customer',
            'password' => Hash::make(Str::random(32)),
            'province' => $addressData['province'] ?? null,
            'district' => $addressData['district'] ?? null,
            'municipality' => $addressData['municipality'] ?? null,
            'ward' => $addressData['ward'] ?? null,
            'tole' => $addressData['tole'] ?? null,
            'address' => $addressData['shipping_address'] ?? null,
        ]);
    }

    /**
     * Link historical guest orders and offline sales to unified customer records by contact number.
     * Groups multiple sales under the same customer record.
     */
    public function syncGuestOrdersAndOfflineSales(): array
    {
        $ordersLinked = 0;
        $salesLinked = 0;
        $customersCreated = 0;

        // 1. Normalize all existing customer phone numbers in users table to strict 10 digits
        $users = User::where('role', 'customer')->whereNotNull('phone')->get();
        foreach ($users as $u) {
            $clean = self::normalizePhone($u->phone);
            if ($clean && $clean !== $u->phone) {
                if (!User::where('phone', $clean)->where('id', '!=', $u->id)->exists()) {
                    $u->updateQuietly(['phone' => $clean]);
                }
            }
        }

        // 2. Link offline sales where customer phone is available
        $salesWithPhone = OfflineSale::whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '')
            ->get();

        foreach ($salesWithPhone as $sale) {
            $cleanPhone = self::normalizePhone($sale->customer_phone);
            if (!$cleanPhone) {
                continue;
            }

            $wasCount = User::where('role', 'customer')->count();
            $customer = $this->resolveOrCreateCustomer(
                $sale->customer_name,
                $cleanPhone,
                $sale->customer_email
            );

            if (User::where('role', 'customer')->count() > $wasCount) {
                $customersCreated++;
            }

            $saleUpdates = [
                'user_id' => $customer->id,
                'customer_phone' => $cleanPhone,
            ];
            if ($sale->customer_name === 'Walk-in Customer' && !empty($customer->name)) {
                $saleUpdates['customer_name'] = $customer->name;
            }
            $sale->updateQuietly($saleUpdates);
            $salesLinked++;
        }

        // 3. Link online orders where phone is available
        $ordersWithPhone = Order::whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get();

        foreach ($ordersWithPhone as $order) {
            $cleanPhone = self::normalizePhone($order->phone);
            if (!$cleanPhone) {
                continue;
            }

            $wasCount = User::where('role', 'customer')->count();
            $custName = trim("{$order->first_name} {$order->last_name}");
            $addressData = [
                'province' => $order->province,
                'district' => $order->district,
                'municipality' => $order->municipality,
                'ward' => $order->ward,
                'tole' => $order->tole,
                'shipping_address' => $order->shipping_address,
            ];

            $customer = $this->resolveOrCreateCustomer(
                $custName,
                $cleanPhone,
                $order->email,
                $addressData
            );

            if (User::where('role', 'customer')->count() > $wasCount) {
                $customersCreated++;
            }

            $order->updateQuietly([
                'user_id' => $customer->id,
                'phone' => $cleanPhone,
            ]);
            $ordersLinked++;
        }

        return [
            'orders_linked' => $ordersLinked,
            'sales_linked' => $salesLinked,
            'customers_created' => $customersCreated,
            'total_customers' => User::where('role', 'customer')->count(),
        ];
    }

    /**
     * Get 360-degree aggregated metrics for a customer.
     */
    public function getCustomerMetrics(User $customer): array
    {
        $onlineOrdersQuery = $customer->orders()->whereNotIn('status', [Order::STATUS_CANCELLED, 'failed_delivery']);
        $onlineOrdersCount = (int) $onlineOrdersQuery->count();
        $onlineOrdersSpend = (float) $onlineOrdersQuery->sum('total_amount');

        $posSalesQuery = $customer->offlineSales()->where('status', 'completed');
        $posSalesCount = (int) $posSalesQuery->count();
        $posSalesSpend = (float) $posSalesQuery->sum('total_amount');

        $totalOrders = $onlineOrdersCount + $posSalesCount;
        $lifetimeSpend = round($onlineOrdersSpend + $posSalesSpend, 2);
        $averageOrderValue = $totalOrders > 0 ? round($lifetimeSpend / $totalOrders, 2) : 0.00;

        $latestOrder = $customer->orders()->latest('created_at')->first();
        $latestSale = $customer->offlineSales()->latest('sold_at')->first();

        $lastOrderDate = null;
        if ($latestOrder && $latestSale) {
            $lastOrderDate = $latestOrder->created_at->gt($latestSale->sold_at)
                ? $latestOrder->created_at
                : $latestSale->sold_at;
        } elseif ($latestOrder) {
            $lastOrderDate = $latestOrder->created_at;
        } elseif ($latestSale) {
            $lastOrderDate = $latestSale->sold_at;
        }

        // Customer Segment classification
        $segment = 'No Orders';
        if ($lifetimeSpend >= 50000 || $totalOrders >= 5) {
            $segment = 'VIP';
        } elseif ($totalOrders > 1) {
            $segment = ($lastOrderDate && $lastOrderDate->lt(now()->subDays(90))) ? 'Inactive' : 'Returning';
        } elseif ($totalOrders === 1) {
            $segment = ($lastOrderDate && $lastOrderDate->lt(now()->subDays(90))) ? 'Inactive' : 'New';
        }

        return [
            'total_orders' => $totalOrders,
            'online_orders_count' => $onlineOrdersCount,
            'pos_sales_count' => $posSalesCount,
            'lifetime_spend' => $lifetimeSpend,
            'average_order_value' => $averageOrderValue,
            'last_order_date' => $lastOrderDate,
            'segment' => $segment,
            'currency' => 'NPR',
        ];
    }

    /**
     * Get combined chronological order history for a customer profile.
     */
    public function getCustomerOrderHistory(User $customer): array
    {
        $history = [];

        // 1. Online Orders
        $orders = $customer->orders()->with('items')->latest('created_at')->get();
        foreach ($orders as $order) {
            $itemCount = $order->items->sum('quantity') ?: $order->items->count();
            $history[] = [
                'type' => 'online',
                'id' => $order->id,
                'number' => $order->order_number,
                'date' => $order->created_at,
                'channel' => 'Online Storefront',
                'items_count' => $itemCount,
                'items_summary' => $order->items->pluck('product_name')->take(2)->implode(', '),
                'total_amount' => (float)$order->total_amount,
                'payment_method' => strtoupper($order->payment_method ?? 'COD'),
                'payment_status' => $order->payment_status,
                'status' => $order->status,
                'carrier' => $order->carrier ?: $order->courier_name,
                'tracking_number' => $order->tracking_number,
                'courier_status' => $order->courier_status,
                'view_url' => route('filament.admin.resources.orders.view', ['record' => $order->id]),
            ];
        }

        // 2. Showroom POS Offline Sales
        $sales = $customer->offlineSales()->with('items')->latest('sold_at')->get();
        foreach ($sales as $sale) {
            $itemCount = $sale->items->sum('quantity') ?: $sale->items->count();
            $history[] = [
                'type' => 'pos',
                'id' => $sale->id,
                'number' => $sale->sale_number,
                'date' => $sale->sold_at ?: $sale->created_at,
                'channel' => 'Showroom POS',
                'items_count' => $itemCount,
                'items_summary' => $sale->items->pluck('product_name')->take(2)->implode(', '),
                'total_amount' => (float)$sale->total_amount,
                'payment_method' => strtoupper($sale->payment_method ?? 'CASH'),
                'payment_status' => $sale->status === 'completed' ? 'paid' : $sale->status,
                'status' => $sale->status,
                'carrier' => null,
                'tracking_number' => null,
                'courier_status' => null,
                'view_url' => null,
            ];
        }

        // Sort descending by date
        usort($history, function ($a, $b) {
            $tA = $a['date'] instanceof Carbon ? $a['date']->timestamp : strtotime((string)$a['date']);
            $tB = $b['date'] instanceof Carbon ? $b['date']->timestamp : strtotime((string)$b['date']);
            return $tB <=> $tA;
        });

        return $history;
    }

    /**
     * Compile 360-degree CRM summary for a customer profile.
     */
    public function getCustomerCrmSummary(User $customer): array
    {
        $leads = $customer->crmLeads()->with(['product', 'assignedStaff', 'activities'])->get();
        $inquiries = $customer->contactMessages()->latest()->get();
        $followUps = $customer->outstanding_follow_ups;

        return [
            'leads_count' => $leads->count(),
            'active_leads_count' => $leads->whereNotIn('stage', [\App\Models\CrmLead::STAGE_WON, \App\Models\CrmLead::STAGE_LOST])->count(),
            'inquiries_count' => $inquiries->count(),
            'outstanding_follow_ups_count' => $followUps->count(),
            'leads' => $leads,
            'inquiries' => $inquiries,
            'follow_ups' => $followUps,
        ];
    }
}
