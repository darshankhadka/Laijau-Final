<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ShippingMethod;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Kathmandu Valley Standard Delivery
        ShippingMethod::updateOrCreate(
            ['code' => 'inside_valley_standard'],
            [
                'name' => 'Kathmandu Valley Standard Delivery',
                'carrier' => 'Pathao / Local Rider',
                'zone' => 'kathmandu_valley',
                'countries' => ['NP'],
                'price_npr' => 100.00,
                'free_shipping_threshold_npr' => 3000.00,
                'estimated_delivery' => '1-2 business days',
                'is_active' => true,
            ]
        );

        // 2. Same-Day Express (Valley)
        ShippingMethod::updateOrCreate(
            ['code' => 'inside_valley_express'],
            [
                'name' => 'Same-Day Express (Valley)',
                'carrier' => 'Pathao Express',
                'zone' => 'kathmandu_valley',
                'countries' => ['NP'],
                'price_npr' => 200.00,
                'free_shipping_threshold_npr' => null,
                'estimated_delivery' => 'Same day (before 2 PM)',
                'is_active' => true,
            ]
        );

        // 3. Nationwide Courier (Outside Valley)
        ShippingMethod::updateOrCreate(
            ['code' => 'outside_valley_courier'],
            [
                'name' => 'Nationwide Courier (Outside Valley)',
                'carrier' => 'Nepal Can Move (NCM) / Pathao Parcel',
                'zone' => 'outside_valley',
                'countries' => ['NP'],
                'price_npr' => 150.00,
                'free_shipping_threshold_npr' => 4000.00,
                'estimated_delivery' => '2-4 business days',
                'is_active' => true,
            ]
        );

        // Remove legacy methods if present
        ShippingMethod::whereIn('code', ['pickup_point_legacy', 'home_delivery_legacy', 'courier_legacy'])->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
