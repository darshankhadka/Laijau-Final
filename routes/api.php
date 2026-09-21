<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\GdprController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\RestockRequestController;

// Public Store Settings
Route::get('/settings', [SettingsController::class, 'index']);

// Session & Cookie Middleware for API statefulness
$sessionMiddleware = [
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
];

// Authentication Endpoints (Session Enabled & Rate Limited)
Route::middleware($sessionMiddleware)->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirect']);
    Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);
    Route::post('/auth/google', [AuthController::class, 'googleLogin']);
});

// Products & Catalog
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/search/suggestions', [\App\Http\Controllers\Api\SearchSuggestionController::class, 'index'])->middleware('throttle:search');
Route::post('/products/{product}/restock-request', [RestockRequestController::class, 'store'])->middleware('throttle:15,1');
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);
Route::get('/collections', [CollectionController::class, 'index']);
Route::get('/collections/{slug}', [CollectionController::class, 'show']);

// System Health & Observability Probe
Route::get('/health', [\App\Http\Controllers\Api\HealthController::class, 'status']);

// Contact Form (Rate limited)
Route::post('/contact', [ContactController::class, 'submit'])->middleware('throttle:15,1');

// Cart, Checkout & Payments (Session Enabled & Rate Limited)
Route::middleware($sessionMiddleware)->group(function () {
    Route::post('/cart/validate', [CartController::class, 'validateCart']);
    Route::post('/checkout', [CheckoutController::class, 'process'])->middleware('throttle:checkout');
    Route::post('/checkout/process', [CheckoutController::class, 'process'])->middleware('throttle:checkout');
    Route::get('/orders/lookup/{order_number}', [CheckoutController::class, 'lookup'])->middleware('throttle:30,1');
    Route::post('/orders/{order_number}/cancel', [CheckoutController::class, 'cancel'])->middleware('throttle:10,1');
    Route::post('/orders/{order_number}/return', [CheckoutController::class, 'returnOrder'])->middleware('throttle:10,1');

});

// Webhooks (Rate limited against abuse)
Route::middleware('throttle:webhooks')->group(function () {
    Route::post('/webhooks/ncm', [\App\Http\Controllers\Api\LogisticsWebhookController::class, 'handleNcm']);
    Route::post('/webhooks/pathao', [\App\Http\Controllers\Api\LogisticsWebhookController::class, 'handlePathao']);
});

// Authenticated Customer Routes (Session & Sanctum Guarded)
Route::middleware(array_merge($sessionMiddleware, ['auth:web,sanctum']))->group(function () {
    Route::get('/user', [UserController::class, 'profile']);
    Route::match(['put', 'patch', 'post'], '/user/profile', [UserController::class, 'updateProfile']);
    Route::post('/user/change-password', [UserController::class, 'changePassword']);
    Route::get('/user/orders', [UserController::class, 'orders']);
    Route::get('/user/orders/{order_number}', [UserController::class, 'showOrder']);

    // GDPR Right to Erasure
    Route::delete('/gdpr/erasure', [GdprController::class, 'erasure']);
});

// ==============================================================================
// LAIJAU LOCAL PRINT AGENT API (Shared-Hosting Showroom Hardware Streaming)
// ==============================================================================
Route::prefix('v1/print-agent')->group(function () {
    Route::post('/pair', [\App\Http\Controllers\Api\PrintAgentController::class, 'pair'])->middleware('throttle:10,1');
    Route::get('/config', [\App\Http\Controllers\Api\PrintAgentController::class, 'config']);
    Route::post('/heartbeat', [\App\Http\Controllers\Api\PrintAgentController::class, 'heartbeat']);
    Route::get('/health', [\App\Http\Controllers\Api\PrintAgentController::class, 'health']);
    Route::get('/jobs/poll', [\App\Http\Controllers\Api\PrintAgentController::class, 'poll']);
    Route::post('/jobs/{uuid}/status', [\App\Http\Controllers\Api\PrintAgentController::class, 'updateStatus']);
    Route::post('/test-job', [\App\Http\Controllers\Api\PrintAgentController::class, 'testJob']);
    Route::post('/discovered-printers', [\App\Http\Controllers\Api\PrintAgentController::class, 'reportDiscoveredPrinters']);
});


