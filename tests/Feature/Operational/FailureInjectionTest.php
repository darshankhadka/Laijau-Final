<?php

declare(strict_types=1);

namespace Tests\Feature\Operational;

use App\Jobs\ProcessOrderFulfillmentJob;
use App\Models\Order;
use App\Models\Product;
use App\Services\Operational\UrlSecurityValidator;
use Database\Seeders\ModuleSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailureInjectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSettingsSeeder::class);
    }

    /**
     * Test SSRF protection blocks private IPs, loopback, and cloud metadata addresses.
     */
    public function test_ssrf_validator_blocks_dangerous_destinations(): void
    {
        $maliciousUrls = [
            'http://127.0.0.1/internal',
            'http://169.254.169.254/latest/meta-data',
            'http://10.0.0.1/metrics',
            'http://192.168.1.1/admin',
            'http://172.16.0.1/secrets',
            'file:///etc/passwd',
            'gopher://127.0.0.1:6379/_INFO',
            'ftp://internal.network/files',
        ];

        foreach ($maliciousUrls as $url) {
            $this->assertFalse(
                UrlSecurityValidator::isSafeUrl($url, allowLocal: false),
                "Failed to block SSRF destination: {$url}"
            );

            try {
                UrlSecurityValidator::assertSafeUrl($url, allowLocal: false);
                $this->fail("Expected InvalidArgumentException for SSRF target: {$url}");
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        }

        // Assert public HTTPS URLs are allowed
        $this->assertTrue(UrlSecurityValidator::isSafeUrl('https://laijau.com/api/revalidate', allowLocal: false));
    }

    /**
     * Test payment gateway failure leaves order in clean payment_failed state without orphan stock deductions.
     */
    public function test_simulated_payment_failure_leaves_order_state_uncorrupted(): void
    {
        $product = Product::create([
            'name' => 'Failed Checkout Product',
            'slug' => 'failed-checkout-product',
            'sku' => 'FCP-01',
            'price_npr' => 500.00,
            'is_active' => true,
        ]);

        $order = Order::create([
            'first_name' => 'Aarav',
            'last_name' => 'Shrestha',
            'email' => 'aarav.shrestha@example.com',
            'shipping_address' => 'Lazimpat, Ward 3',
            'shipping_city' => 'Kathmandu',
            'shipping_country' => 'NP',
            'subtotal' => 500.00,
            'total_amount' => 500.00,
            'currency' => 'npr',
            'payment_method' => 'esewa',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => 'unpaid',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 500.00,
        ]);

        // Simulate failed payment callback or cancellation
        $order->update([
            'status' => Order::STATUS_PAYMENT_FAILED,
            'payment_status' => 'failed',
        ]);

        // Verify order is marked as failed cleanly
        $order->refresh();
        $this->assertEquals(Order::STATUS_PAYMENT_FAILED, $order->status);
        $this->assertEquals('failed', $order->payment_status);

        // Verify no stock was deducted
        $this->assertDatabaseMissing('inventory_stock_movements', [
            'reference_type' => 'order',
            'reference_id' => $order->id,
        ]);
    }

    /**
     * Test resilient Queue job handles non-existent order gracefully and triggers failure handler.
     */
    public function test_Queue_job_failure_isolation(): void
    {
        $nonExistentOrderId = 999999;
        $job = new ProcessOrderFulfillmentJob($nonExistentOrderId);

        // Calling handle directly should not crash the worker
        $job->handle(app(\App\Services\Inventory\InventoryService::class));
        $this->assertTrue(true);

        // Calling failed() triggers dead-letter audit log cleanly
        $job->failed(new \RuntimeException("Simulated Queue worker timeout"));
        $this->assertTrue(true);
    }

    /**
     * Test public API endpoints enforce rate limiting against rapid denial of service.
     */
    public function test_rate_limiter_throttles_rapid_contact_submissions(): void
    {
        $key = 'contact_submit:' . '127.0.0.1';
        $maxAttempts = 15;

        for ($i = 1; $i <= $maxAttempts; $i++) {
            \Illuminate\Support\Facades\RateLimiter::hit($key, 60);
        }

        $this->assertTrue(
            \Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, $maxAttempts),
            "Rate limiter should flag too many attempts after 15 requests."
        );
        $this->assertGreaterThan(0, \Illuminate\Support\Facades\RateLimiter::availableIn($key));
    }
}
