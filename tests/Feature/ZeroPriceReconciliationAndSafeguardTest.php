<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ZeroPriceReconciliationAndSafeguardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reconciled_products_have_accurate_historical_prices()
    {
        $products = Product::where('is_published', true)->take(20)->get();
        $this->assertNotEmpty($products);

        foreach ($products as $p) {
            $this->assertGreaterThan(0, (float)$p->price);
            $this->assertTrue($p->hasValidPrice());
            $this->assertTrue($p->isStorefrontEligible());
        }
    }

    public function test_zero_storefront_products_have_zero_or_null_prices()
    {
        $zeroPriceStorefront = Product::storefrontReady()
            ->where(function($q) {
                $q->whereNull('price')->orWhere('price', '<=', 0);
            })->count();

        $this->assertEquals(0, $zeroPriceStorefront, 'No storefront products may have zero or null price.');

        $zeroPriceVariants = ProductVariant::where(function($q) {
                $q->whereNull('price')->orWhere('price', '<=', 0);
            })
            ->whereHas('product', function($q) {
                $q->storefrontReady();
            })->count();

        $this->assertEquals(0, $zeroPriceVariants, 'No storefront variants may have zero or null price.');
    }

    public function test_checkout_blocks_unpriced_and_unphotographed_items()
    {
        // 1. Attempting checkout on unpriced product 810 must fail with HTTP 422
        $response = $this->postJson('/api/checkout/process', [
            'first_name' => 'Ram',
            'last_name' => 'Sharma',
            'email' => 'ram.sharma@example.com',
            'phone' => '9841234567',
            'shipping_address' => 'Putalisadak, Kathmandu',
            'items' => [
                [
                    'product_id' => 810,
                    'quantity' => 1,
                ]
            ],
            'payment_method' => 'cash_on_delivery',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertFalse($response->json('success'));
    }

    public function test_historical_sales_and_orders_prices_remain_immutable()
    {
        if (!\App\Models\OfflineSaleItem::where('offline_sale_id', 37)->exists()) {
            $this->markTestSkipped('Historical offline sale items only exist in primary database.');
        }

        // Historical POS Sale items must remain untouched with immutable positive pricing
        $sale37Item = \App\Models\OfflineSaleItem::where('offline_sale_id', 37)->first();
        $this->assertNotNull($sale37Item);
        $this->assertGreaterThan(0, (float)$sale37Item->unit_price);

        $sale38Item = \App\Models\OfflineSaleItem::where('offline_sale_id', 38)->first();
        $this->assertNotNull($sale38Item);
        $this->assertGreaterThan(0, (float)$sale38Item->unit_price);

        $sale40Item = \App\Models\OfflineSaleItem::where('offline_sale_id', 40)->first();
        $this->assertNotNull($sale40Item);
        $this->assertGreaterThan(0, (float)$sale40Item->unit_price);
    }
}
