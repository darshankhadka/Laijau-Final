<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('vat_amount', 10, 2)->default(0.00)->change();
            $table->decimal('vat_rate', 5, 2)->default(0.00)->change();
            $table->decimal('shipping_fee', 10, 2)->default(0.00)->change();
            $table->decimal('coupon_discount', 10, 2)->default(0.00)->change();
        });
    }

    public function down(): void
    {
        // No down needed for default value enhancements
    }
};
