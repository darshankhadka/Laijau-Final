<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('offline_sales') && !Schema::hasColumn('offline_sales', 'shipping_amount')) {
            Schema::table('offline_sales', function (Blueprint $table) {
                $table->decimal('shipping_amount', 12, 2)->default(0)->after('discount_reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('offline_sales') && Schema::hasColumn('offline_sales', 'shipping_amount')) {
            Schema::table('offline_sales', function (Blueprint $table) {
                $table->dropColumn('shipping_amount');
            });
        }
    }
};
