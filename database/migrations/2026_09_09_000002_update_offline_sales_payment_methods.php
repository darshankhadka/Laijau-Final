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
        if (Schema::hasTable('offline_sales')) {
            Schema::table('offline_sales', function (Blueprint $table) {
                // Expand payment_method and sales_channel to flexible varchar to support esewa, khalti, fonepay etc.
                $table->string('payment_method', 50)->default('cash')->change();
                $table->string('sales_channel', 50)->default('physical')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('offline_sales')) {
            Schema::table('offline_sales', function (Blueprint $table) {
                $table->enum('payment_method', ['cash', 'esewa', 'khalti', 'bank_transfer', 'card', 'other'])->default('cash')->change();
                $table->enum('sales_channel', ['physical', 'instagram', 'whatsapp', 'showroom', 'event', 'wholesale', 'referral', 'other'])->default('physical')->change();
            });
        }
    }
};
