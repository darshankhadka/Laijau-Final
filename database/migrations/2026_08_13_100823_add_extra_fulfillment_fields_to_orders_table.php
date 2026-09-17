<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('coupon_discount', 10, 2)->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('payment_status')->default('unpaid');
            $table->decimal('vat_rate', 5, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['coupon_discount', 'tracking_url', 'payment_status', 'vat_rate']);
        });
    }
};
