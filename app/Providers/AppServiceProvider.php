<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        require_once app_path('Helpers/helpers.php');
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(!app()->isProduction());
        \Illuminate\Support\Facades\DB::prohibitDestructiveCommands(app()->isProduction() || config('database.connections.mysql.database') === 'LAIJAU');

        if ((app()->isProduction() || str_starts_with((string) config('app.url'), 'https://')) && !str_contains((string) config('app.url'), '127.0.0.1') && !str_contains((string) config('app.url'), 'localhost')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Section 18: Layered Production Bot & Abuse Rate Limiters
        RateLimiter::for('login', function (Request $request) {
            $key = Str::transliterate(Str::lower($request->input('email', $request->input('phone', ''))) . '|' . $request->ip());
            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(40)->by($request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        \App\Models\Product::observe(\App\Observers\ProductObserver::class);
        \App\Models\Category::observe(\App\Observers\CategoryObserver::class);
        \App\Models\Collection::observe(\App\Observers\CollectionObserver::class);
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);

        // Global Nepali Numbering Blade Directives
        \Illuminate\Support\Facades\Blade::directive('nepaliNumber', function ($expression) {
            return "<?php echo \App\Helpers\NepaliNumberHelper::format($expression); ?>";
        });
        \Illuminate\Support\Facades\Blade::directive('nepaliCurrency', function ($expression) {
            return "<?php echo \App\Helpers\NepaliNumberHelper::formatCurrency($expression); ?>";
        });

        // Super Admin Universal Bypass (Gate::before)
        Gate::before(function ($user, string $ability) {
            // Allow policies to enforce critical self-deletion and separation of duties guards
            if (in_array($ability, ['delete', 'approve'], true)) {
                return null;
            }

            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }
            return null;
        });

        // Explicit Policy Mappings
        Gate::policy(\App\Models\Accounting\JournalEntry::class, \App\Policies\Accounting\JournalEntryPolicy::class);
        Gate::policy(\App\Models\Accounting\AccountingInvoice::class, \App\Policies\Accounting\AccountingInvoicePolicy::class);
        Gate::policy(\App\Models\Accounting\Account::class, \App\Policies\Accounting\AccountPolicy::class);

        Gate::policy(\App\Models\Hrm\Employee::class, \App\Policies\Hrm\EmployeePolicy::class);
        Gate::policy(\App\Models\Hrm\PayrollRun::class, \App\Policies\Hrm\PayrollRunPolicy::class);
        Gate::policy(\App\Models\Hrm\ExpenseClaim::class, \App\Policies\Hrm\ExpenseClaimPolicy::class);
        Gate::policy(\App\Models\Hrm\LeaveRequest::class, \App\Policies\Hrm\LeaveRequestPolicy::class);
        Gate::policy(\App\Models\Hrm\Timesheet::class, \App\Policies\Hrm\TimesheetPolicy::class);
        Gate::policy(\App\Models\Hrm\Department::class, \App\Policies\Hrm\DepartmentPolicy::class);
        Gate::policy(\App\Models\Hrm\RecruitmentJob::class, \App\Policies\Hrm\RecruitmentJobPolicy::class);

        Gate::policy(\App\Models\Inventory\PurchaseOrder::class, \App\Policies\Inventory\PurchaseOrderPolicy::class);
        Gate::policy(\App\Models\Inventory\StockAdjustment::class, \App\Policies\Inventory\StockAdjustmentPolicy::class);
        Gate::policy(\App\Models\Inventory\StockTransfer::class, \App\Policies\Inventory\StockTransferPolicy::class);
        Gate::policy(\App\Models\Inventory\StockCount::class, \App\Policies\Inventory\StockCountPolicy::class);
        Gate::policy(\App\Models\Inventory\StockReservation::class, \App\Policies\Inventory\StockReservationPolicy::class);
        Gate::policy(\App\Models\Inventory\StockLevel::class, \App\Policies\Inventory\StockLevelPolicy::class);
        Gate::policy(\App\Models\Inventory\StockMovement::class, \App\Policies\Inventory\StockMovementPolicy::class);
        Gate::policy(\App\Models\Inventory\Warehouse::class, \App\Policies\Inventory\WarehousePolicy::class);
        Gate::policy(\App\Models\Inventory\Supplier::class, \App\Policies\Inventory\SupplierPolicy::class);

        Gate::policy(\App\Models\Order::class, \App\Policies\OrderPolicy::class);
        Gate::policy(\App\Models\Product::class, \App\Policies\ProductPolicy::class);
        Gate::policy(\App\Models\Category::class, \App\Policies\CategoryPolicy::class);
        Gate::policy(\App\Models\Collection::class, \App\Policies\CollectionPolicy::class);
        Gate::policy(\App\Models\Coupon::class, \App\Policies\CouponPolicy::class);
        Gate::policy(\App\Models\ShippingMethod::class, \App\Policies\ShippingMethodPolicy::class);
        Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class);
        Gate::policy(\App\Models\CrmLead::class, \App\Policies\CrmLeadPolicy::class);
        Gate::policy(\App\Models\ContactMessage::class, \App\Policies\ContactMessagePolicy::class);
        Gate::policy(\App\Models\RestockRequest::class, \App\Policies\RestockRequestPolicy::class);
        Gate::policy(\App\Models\ProductAttribute::class, \App\Policies\ProductAttributePolicy::class);
        Gate::policy(\App\Models\OfflineSale::class, \App\Policies\OfflineSalePolicy::class);



        // Queue Worker Failure & Dead-Letter Event Hook
        \Illuminate\Support\Facades\Queue::failing(function (\Illuminate\Queue\Events\JobFailed $event) {
            try {
                app(\App\Services\Operational\AuditLoggerService::class)->queueAlert(
                    $event->job->resolveName(),
                    $event->exception->getMessage(),
                    [
                        'connection' => $event->connectionName,
                        'queue' => $event->job->getQueue(),
                        'attempts' => $event->job->attempts(),
                    ]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::emergency("Failed to log queue failure: " . $e->getMessage());
            }
        });
    }
}
