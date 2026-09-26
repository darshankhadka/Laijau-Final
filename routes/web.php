<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Storefront\StorefrontController;

/*
|--------------------------------------------------------------------------
| Laijau Monolith Web Routes
|--------------------------------------------------------------------------
| Customer storefront routes rendered directly via Laravel Blade & Alpine.js,
| reading directly from the authoritative database with zero API serialization lag.
*/

// Public Storefront Routes
Route::get('/', [StorefrontController::class, 'home'])->name('storefront.home');
Route::get('/products', [StorefrontController::class, 'catalogue'])->name('storefront.catalogue');
Route::get('/products/{product}', [StorefrontController::class, 'product'])->name('storefront.product');
Route::get('/categories/{category:slug}', [StorefrontController::class, 'category'])->name('storefront.category');
Route::get('/category/{category:slug}', [StorefrontController::class, 'category']);
Route::get('/collections', [StorefrontController::class, 'collections'])->name('storefront.collections');
Route::get('/collections/{collection:slug}', [StorefrontController::class, 'collection'])->name('storefront.collection');
Route::get('/search', [StorefrontController::class, 'search'])->middleware('throttle:search')->name('storefront.search');

// Shopping Bag & Checkout
Route::get('/cart', [StorefrontController::class, 'cart'])->name('storefront.cart');
Route::get('/checkout', [StorefrontController::class, 'checkout'])->name('storefront.checkout');
Route::get('/checkout/success', [StorefrontController::class, 'checkoutSuccess'])->name('storefront.checkout.success');
Route::get('/order-confirmation', [StorefrontController::class, 'checkoutSuccess']);
Route::match(['get', 'post'], '/track-order', [StorefrontController::class, 'trackOrder'])->name('storefront.track_order');
Route::match(['get', 'post'], '/track', [StorefrontController::class, 'trackOrder'])->name('storefront.track');

// ConnectIPS Payment Flow
Route::get('/payment/connectips/initiate/{order}', [\App\Http\Controllers\Storefront\ConnectIpsController::class, 'initiate'])->name('payment.connectips.initiate');
Route::match(['get', 'post'], '/payment/connectips/return', [\App\Http\Controllers\Storefront\ConnectIpsController::class, 'returnUrl'])->name('payment.connectips.return');
Route::post('/payment/connectips/callback', [\App\Http\Controllers\Storefront\ConnectIpsController::class, 'callback'])->name('payment.connectips.callback');

// Secure Payment Proof Viewer
Route::get('/orders/{order}/payment-proof', [\App\Http\Controllers\Storefront\PaymentProofController::class, 'show'])->name('orders.payment_proof');
Route::get('/intadmin/orders/{order}/payment-proof', [\App\Http\Controllers\Storefront\PaymentProofController::class, 'show'])->name('admin.orders.payment_proof');
Route::middleware(['web', 'auth'])->get('/intadmin/attendance/employee-summary-data', function (\Illuminate\Http\Request $request) {
    $employeeId = $request->query('employee_id');
    $employee = \App\Models\Hrm\Employee::find($employeeId);
    if (!$employee) {
        return response()->json(['error' => 'Employee not found.'], 404);
    }
    $period = $request->query('period', 'monthly');
    $start = $request->query('start_date') ? \Carbon\Carbon::parse($request->query('start_date')) : null;
    $end = $request->query('end_date') ? \Carbon\Carbon::parse($request->query('end_date')) : null;

    $summary = app(\App\Services\Attendance\AttendanceService::class)->getEmployeeTimesheetSummary($employee, $period, $start, $end);
    return response()->json($summary);
})->name('admin.attendance.employee_summary_data');

// People & HRM Mobile-First Employee Portal
Route::get('/hrm/portal', [\App\Http\Controllers\Hrm\EmployeePortalController::class, 'index'])->name('hrm.portal');
Route::post('/hrm/clock-in', [\App\Http\Controllers\Hrm\EmployeePortalController::class, 'clockIn'])->name('hrm.clock_in');
Route::post('/hrm/clock-out', [\App\Http\Controllers\Hrm\EmployeePortalController::class, 'clockOut'])->name('hrm.clock_out');
Route::get('/hrm/payslip/{item}', [\App\Http\Controllers\Hrm\EmployeePortalController::class, 'payslip'])->name('hrm.payslip');

// =========================================================================
// LAIJAU EMPLOYEE ATTENDANCE PWA ROUTES
// =========================================================================
Route::prefix('attendance')->group(function () {
    // PWA Manifest & Service Worker
    Route::get('/manifest.webmanifest', [\App\Http\Controllers\Attendance\AttendancePwaController::class, 'manifest'])->name('attendance.manifest');
    Route::get('/sw.js', [\App\Http\Controllers\Attendance\AttendancePwaController::class, 'serviceWorker'])->name('attendance.sw');
    Route::get('/offline', [\App\Http\Controllers\Attendance\AttendancePwaController::class, 'offline'])->name('attendance.offline');

    Route::get('/login', [\App\Http\Controllers\Attendance\AttendancePwaController::class, 'login'])->name('attendance.login');

    // Public Attendance API endpoint (Rate limited)
    Route::prefix('api')->group(function () {
        Route::post('/auth/pin', [\App\Http\Controllers\Attendance\AttendanceApiController::class, 'authenticatePin'])->middleware('throttle:20,1')->name('attendance.api.auth.pin');
    });

    // Authenticated Attendance Routes (Employee & Device Session Verified)
    Route::middleware([\App\Http\Middleware\AttendanceEmployeeAuth::class])->group(function () {
        Route::get('/', [\App\Http\Controllers\Attendance\AttendancePwaController::class, 'dashboard'])->name('attendance.dashboard');

        Route::prefix('api')->group(function () {
            Route::post('/check-in', [\App\Http\Controllers\Attendance\AttendanceApiController::class, 'checkIn'])->name('attendance.api.check_in');
            Route::post('/check-out', [\App\Http\Controllers\Attendance\AttendanceApiController::class, 'checkOut'])->name('attendance.api.check_out');
            Route::post('/heartbeat', [\App\Http\Controllers\Attendance\AttendanceApiController::class, 'heartbeat'])->name('attendance.api.heartbeat');
            Route::get('/status', [\App\Http\Controllers\Attendance\AttendanceApiController::class, 'status'])->name('attendance.api.status');
            Route::get('/history', [\App\Http\Controllers\Attendance\AttendanceApiController::class, 'history'])->name('attendance.api.history');
            Route::get('/summary', [\App\Http\Controllers\Attendance\AttendanceApiController::class, 'summary'])->name('attendance.api.summary');
            Route::post('/auth/change-pin', [\App\Http\Controllers\Attendance\AttendanceApiController::class, 'changePin'])->name('attendance.api.auth.change_pin');
            Route::post('/log-gps-failure', [\App\Http\Controllers\Attendance\AttendanceApiController::class, 'logGpsFailure'])->name('attendance.api.log_gps_failure');
            Route::post('/logout', [\App\Http\Controllers\Attendance\AttendanceApiController::class, 'logout'])->name('attendance.api.logout');
        });
    });
});


// Customer Portal & Wishlist
Route::get('/account', [StorefrontController::class, 'account'])->name('storefront.account');
Route::get('/wishlist', [StorefrontController::class, 'wishlist'])->name('storefront.wishlist');

// Customer Authentication (Web Form & Redirect Endpoints)
Route::get('/login', fn() => redirect()->route('storefront.account'))->name('login');
Route::get('/register', fn() => redirect()->route('storefront.account'))->name('register');
Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login'])->middleware('throttle:10,1')->name('customer.login');
Route::post('/register', [\App\Http\Controllers\Api\AuthController::class, 'register'])->middleware('throttle:10,1')->name('customer.register');
Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout'])->name('customer.logout');
Route::get('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout'])->name('customer.logout.get');

// Google OAuth (Web Routes with full session handling)
Route::get('/auth/google/redirect', [\App\Http\Controllers\Api\AuthController::class, 'googleRedirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [\App\Http\Controllers\Api\AuthController::class, 'googleCallback'])->name('auth.google.callback');

// Informational & Policy Pages
Route::get('/about', [StorefrontController::class, 'about'])->name('storefront.about');
Route::get('/contact', [StorefrontController::class, 'contact'])->name('storefront.contact');
Route::get('/shipping', [StorefrontController::class, 'shipping'])->name('storefront.shipping');
Route::get('/returns', [StorefrontController::class, 'returns'])->name('storefront.returns');
Route::get('/privacy', [StorefrontController::class, 'privacy'])->name('storefront.privacy');
Route::get('/terms', [StorefrontController::class, 'terms'])->name('storefront.terms');

// Livewire Standard & Dynamic Path Compatibility Aliases
Route::group(['middleware' => ['web']], function () {
    // Dynamic Hash paths (e.g. /livewire-0eba89d1/...)
    Route::get('/livewire-{hash}/livewire.js', [\App\Http\Controllers\LivewireAssetController::class, 'script'])
        ->withoutMiddleware([\App\Http\Middleware\StorefrontMaintenanceMiddleware::class]);
    Route::get('/livewire-{hash}/livewire.min.js', [\App\Http\Controllers\LivewireAssetController::class, 'script'])
        ->withoutMiddleware([\App\Http\Middleware\StorefrontMaintenanceMiddleware::class]);
    Route::get('/livewire-{hash}/livewire.min.js.map', [\App\Http\Controllers\LivewireAssetController::class, 'maps'])
        ->withoutMiddleware([\App\Http\Middleware\StorefrontMaintenanceMiddleware::class]);
    Route::post('/livewire-{hash}/update', [\App\Http\Controllers\LivewireAssetController::class, 'update'])
        ->withoutMiddleware([\App\Http\Middleware\StorefrontMaintenanceMiddleware::class]);
    Route::post('/livewire-{hash}/upload-file', [\App\Http\Controllers\LivewireAssetController::class, 'upload'])
        ->withoutMiddleware([\App\Http\Middleware\StorefrontMaintenanceMiddleware::class]);
    Route::get('/livewire-{hash}/preview-file/{filename}', [\App\Http\Controllers\LivewireAssetController::class, 'preview'])
        ->withoutMiddleware([\App\Http\Middleware\StorefrontMaintenanceMiddleware::class]);

    // Standard static paths (e.g. /livewire/...)
    Route::get('/livewire/livewire.js', [\App\Http\Controllers\LivewireAssetController::class, 'script'])
        ->withoutMiddleware([\App\Http\Middleware\StorefrontMaintenanceMiddleware::class]);
    Route::get('/livewire/livewire.min.js', [\App\Http\Controllers\LivewireAssetController::class, 'script'])
        ->withoutMiddleware([\App\Http\Middleware\StorefrontMaintenanceMiddleware::class]);
    Route::get('/livewire/livewire.min.js.map', [\App\Http\Controllers\LivewireAssetController::class, 'maps'])
        ->withoutMiddleware([\App\Http\Middleware\StorefrontMaintenanceMiddleware::class]);
    Route::post('/livewire/update', [\App\Http\Controllers\LivewireAssetController::class, 'update'])
        ->withoutMiddleware([\App\Http\Middleware\StorefrontMaintenanceMiddleware::class]);
    Route::post('/livewire/upload-file', [\App\Http\Controllers\LivewireAssetController::class, 'upload'])
        ->withoutMiddleware([\App\Http\Middleware\StorefrontMaintenanceMiddleware::class]);
    Route::get('/livewire/preview-file/{filename}', [\App\Http\Controllers\LivewireAssetController::class, 'preview'])
        ->withoutMiddleware([\App\Http\Middleware\StorefrontMaintenanceMiddleware::class]);
});

// Fail-Safe Public Storage Asset Streamer (guarantees product images load on all hosts)
Route::get('/storage/{path}', [\App\Http\Controllers\StorageAssetController::class, 'show'])
    ->where('path', '.*')
    ->withoutMiddleware([\App\Http\Middleware\StorefrontMaintenanceMiddleware::class])
    ->name('storage.asset');

// Dynamic SEO Sitemap
Route::get('/sitemap.xml', function () {
    $xml = \Illuminate\Support\Facades\Cache::remember('sitemap_xml_cached', 86400, function () {
        $baseUrl = rtrim(\App\Models\Setting::get('canonical_base_url', 'https://laijau.com'), '/');
        $products = \App\Models\Product::where('is_published', true)
            ->where('is_active', true)
            ->select(['id', 'slug', 'updated_at'])
            ->get();

        $categories = \App\Models\Category::select(['id', 'slug', 'updated_at'])->get();
        $collections = \App\Models\Collection::select(['id', 'slug', 'updated_at'])->get();

        $out = '<?xml version="1.0" encoding="UTF-8"?>';
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        // Key landing pages
        $staticPages = [
            ['path' => '/', 'changefreq' => 'daily', 'priority' => '1.0'],
            ['path' => '/products', 'changefreq' => 'daily', 'priority' => '0.9'],
            ['path' => '/collections', 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['path' => '/track-order', 'changefreq' => 'daily', 'priority' => '0.8'],
            ['path' => '/about', 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['path' => '/contact', 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['path' => '/shipping', 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['path' => '/returns', 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['path' => '/privacy', 'changefreq' => 'monthly', 'priority' => '0.3'],
            ['path' => '/terms', 'changefreq' => 'monthly', 'priority' => '0.3'],
        ];

        foreach ($staticPages as $page) {
            $out .= '<url>';
            $out .= '<loc>' . $baseUrl . $page['path'] . '</loc>';
            $out .= '<changefreq>' . $page['changefreq'] . '</changefreq>';
            $out .= '<priority>' . $page['priority'] . '</priority>';
            $out .= '</url>';
        }

        foreach ($categories as $cat) {
            if ($cat->slug) {
                $out .= '<url>';
                $out .= '<loc>' . $baseUrl . '/categories/' . $cat->slug . '</loc>';
                if ($cat->updated_at) {
                    $out .= '<lastmod>' . $cat->updated_at->tz('UTC')->toAtomString() . '</lastmod>';
                }
                $out .= '<changefreq>weekly</changefreq>';
                $out .= '<priority>0.8</priority>';
                $out .= '</url>';
            }
        }

        foreach ($collections as $col) {
            if ($col->slug) {
                $out .= '<url>';
                $out .= '<loc>' . $baseUrl . '/collections/' . $col->slug . '</loc>';
                if ($col->updated_at) {
                    $out .= '<lastmod>' . $col->updated_at->tz('UTC')->toAtomString() . '</lastmod>';
                }
                $out .= '<changefreq>weekly</changefreq>';
                $out .= '<priority>0.8</priority>';
                $out .= '</url>';
            }
        }

        foreach ($products as $product) {
            $identifier = $product->slug ?: $product->id;
            $out .= '<url>';
            $out .= '<loc>' . $baseUrl . '/products/' . $identifier . '</loc>';
            if ($product->updated_at) {
                $out .= '<lastmod>' . $product->updated_at->tz('UTC')->toAtomString() . '</lastmod>';
            }
            $out .= '<changefreq>weekly</changefreq>';
            $out .= '<priority>0.8</priority>';
            $out .= '</url>';
        }

        $out .= '</urlset>';
        return $out;
    });

    return response($xml, 200)
        ->header('Content-Type', 'text/xml')
        ->header('Cache-Control', 'public, max-age=86400');
});

// Admin & Order Printing
Route::get('/intadmin/orders/{order}/invoice', function (\App\Models\Order $order) {
    $user = auth('admin')->user() ?? auth('web')->user();
    if (!$user) abort(403, 'Unauthorized access to invoice.');
    $order->load(['items.product', 'items.variant', 'user']);
    return view('print.invoice', compact('order'));
})->name('admin.orders.invoice')->middleware('web');

Route::get('/intadmin/orders/{order}/packing-slip', function (\App\Models\Order $order) {
    $user = auth('admin')->user() ?? auth('web')->user();
    if (!$user) abort(403, 'Unauthorized access to packing slip.');
    $order->load(['items.product', 'items.variant', 'user']);
    return view('print.packing-slip', compact('order'));
})->name('admin.orders.packing_slip')->middleware('web');

Route::get('/intadmin/orders/{order}/spec-sheet', function (\App\Models\Order $order) {
    $user = auth('admin')->user() ?? auth('web')->user();
    if (!$user) abort(403);
    $order->load(['items.product', 'items.variant', 'user']);
    return view('spec-sheet', compact('order'));
})->name('order.spec_sheet')->middleware('web');

Route::get('/intadmin/orders/{order}/receipt', function (\App\Models\Order $order) {
    $user = auth('admin')->user() ?? auth('web')->user();
    if (!$user) abort(403);
    return view('pos-receipt', compact('order'));
})->name('order.pos_receipt')->middleware('web');

Route::get('/intadmin/offline-sales/{offlineSale}/receipt', function (\App\Models\OfflineSale $offlineSale) {
    $user = auth('admin')->user() ?? auth('web')->user();
    if (!$user) abort(403);
    $offlineSale->load(['items', 'customer']);
    return view('offline-receipt', ['sale' => $offlineSale]);
})->name('offline_sales.receipt')->middleware('web');

// Clean named route aliases and fallbacks for Laijau POS
Route::redirect('/intadmin/offline-sales/pos', '/intadmin/offline-sales/POS');
Route::get('/intadmin/offline-sales', \App\Filament\Pages\OfflineSales::class)->middleware(['web', 'auth:admin'])->name('intadmin.offline-sales.legacy');





Route::get('/intadmin/purchase-orders/{purchaseOrder}/print', function (\App\Models\Inventory\PurchaseOrder $purchaseOrder) {
    $user = auth('admin')->user() ?? auth('web')->user();
    if (!$user) abort(403, 'Unauthorized access to purchase order voucher.');
    $purchaseOrder->load(['supplier', 'warehouse', 'items.product.categories', 'items.variant', 'createdByUser', 'approvedByUser']);
    return view('print.purchase-order', compact('purchaseOrder'));
})->name('admin.purchase-orders.print')->middleware('web');

Route::get('/intadmin/inventory/full-stock-count/print', [\App\Http\Controllers\Admin\FullStockCountPrintController::class, 'print'])
    ->name('admin.full-stock-count.print')
    ->middleware('web');

Route::get('/intadmin/inventory/full-stock-count/export', [\App\Http\Controllers\Admin\FullStockCountPrintController::class, 'export'])
    ->name('admin.full-stock-count.export')
    ->middleware('web');

Route::get('/orders/{order}/receipt', function (\App\Models\Order $order, \Illuminate\Http\Request $request) {
    if (auth('admin')->check()) {
        return view('pos-receipt', compact('order'));
    }
    $user = auth('web')->user();
    if ($user && $order->user_id && (int)$user->id === (int)$order->user_id) {
        return view('pos-receipt', compact('order'));
    }
    if ($user && !empty($user->email) && strtolower((string)$user->email) === strtolower((string)$order->email)) {
        return view('pos-receipt', compact('order'));
    }
    $session = $request->query('session_id');
    if ($session && $order->payment_id === $session) {
        return view('pos-receipt', compact('order'));
    }
    $placedOrders = (array) ($request->hasSession() ? $request->session()->get('placed_orders', []) : []);
    if (in_array($order->order_number, $placedOrders) || in_array((string)$order->id, $placedOrders)) {
        return view('pos-receipt', compact('order'));
    }
    abort(403, 'Unauthorized access to order receipt.');
})->name('order.public_receipt')->middleware('web');

// Direct Logistics Webhook Endpoints (Rate Limited)
Route::post('/webhooks/ncm', [\App\Http\Controllers\Api\LogisticsWebhookController::class, 'handleNcm'])->middleware('throttle:webhooks');
Route::post('/webhooks/pathao', [\App\Http\Controllers\Api\LogisticsWebhookController::class, 'handlePathao'])->middleware('throttle:webhooks');

// Unadvertised Admin Shield — Redirect /admin to Homepage
Route::any('/admin', fn() => redirect('/'));
Route::any('/admin/{any}', fn() => redirect('/'))->where('any', '.*');
