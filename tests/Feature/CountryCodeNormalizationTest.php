<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\TaxCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryCodeNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_calculator_service_normalizes_all_country_variants()
    {
        $this->assertEquals('NP', TaxCalculatorService::normalizeCountryCode('Nepal'));
        $this->assertEquals('NP', TaxCalculatorService::normalizeCountryCode('NP'));
        $this->assertEquals('NP', TaxCalculatorService::normalizeCountryCode('Lalitpur'));
        $this->assertEquals('NP', TaxCalculatorService::normalizeCountryCode('Bhaktapur'));
        $this->assertEquals('NP', TaxCalculatorService::normalizeCountryCode('Pokhara'));
        $this->assertEquals('NP', TaxCalculatorService::normalizeCountryCode(null));
        $this->assertEquals('NP', TaxCalculatorService::normalizeCountryCode(''));
    }

    public function test_user_model_country_accessor_and_mutator_normalize_automatically()
    {
        $user = User::factory()->create([
            'name' => 'Aarav Shrestha',
            'email' => 'aarav.shrestha@example.com',
            'country' => 'Nepal',
        ]);

        $this->assertEquals('NP', $user->country);

        $user->country = 'NP';
        $user->save();

        $this->assertEquals('NP', $user->fresh()->country);
    }

    public function test_cart_validate_accepts_full_country_names_and_normalizes_without_422()
    {
        $product = Product::create([
            'name' => 'Royal Pashmina',
            'slug' => 'royal-pashmina-' . uniqid(),
            'price' => 4500,
            'quantity' => 10,
            'is_published' => true,
            'is_active' => true,
        ]);

        $res1 = $this->postJson('/api/cart/validate', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ],
            'shipping_country' => 'Nepal',
            'district' => 'Kathmandu',
            'currency' => 'NPR'
        ]);

        $res1->assertStatus(200);
        $res1->assertJsonPath('currency', 'NPR');
        $this->assertTrue($res1->json('is_inside_valley'));
    }

    public function test_user_profile_update_normalizes_country()
    {
        /** @var User $user */
        $user = User::factory()->create([
            'country' => 'NP',
        ]);

        $res = $this->actingAs($user, 'web')->postJson('/api/user/profile', [
            'name' => 'Aarav Shrestha',
            'country' => 'Nepal',
            'address' => 'Lazimpat',
            'city' => 'Kathmandu',
            'postal_code' => '44600',
        ]);

        $res->assertStatus(200);
        $this->assertEquals('NP', $user->fresh()->country);
    }

    public function test_checkout_process_normalizes_country_and_succeeds()
    {
        $product = Product::create([
            'name' => 'Royal Cashmere Shawl',
            'slug' => 'royal-cashmere-shawl-' . uniqid(),
            'price' => 5000,
            'quantity' => 15,
            'is_published' => true,
            'is_active' => true,
        ]);

        $res = $this->postJson('/api/checkout', [
            'customer' => [
                'first_name' => 'Aarav',
                'last_name' => 'Shrestha',
                'email' => 'aarav.norm@example.com',
                'phone' => '9841234567',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '03',
                'tole' => 'Lazimpat',
                'country' => 'Nepal',
            ],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ],
            'payment_method' => 'cod',
            'currency' => 'NPR'
        ]);

        $this->assertTrue(in_array($res->status(), [200, 201]));
        $this->assertDatabaseHas('orders', [
            'email' => 'aarav.norm@example.com',
            'shipping_country' => 'NP',
        ]);
    }
}
