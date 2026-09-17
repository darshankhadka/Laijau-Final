<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Additive non-destructive columns for retail POS selling warehouse and cash audit trail.
     */
    public function up(): void
    {
        Schema::table('offline_sales', function (Blueprint $table) {
            if (!Schema::hasColumn('offline_sales', 'warehouse_id')) {
                $table->foreignId('warehouse_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('inventory_warehouses')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('offline_sales', 'cash_received')) {
                $table->decimal('cash_received', 12, 2)
                    ->nullable()
                    ->after('payment_method');
            }

            if (!Schema::hasColumn('offline_sales', 'change_given')) {
                $table->decimal('change_given', 12, 2)
                    ->nullable()
                    ->after('cash_received');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offline_sales', function (Blueprint $table) {
            if (Schema::hasColumn('offline_sales', 'warehouse_id')) {
                $table->dropForeign(['warehouse_id']);
                $table->dropColumn('warehouse_id');
            }

            if (Schema::hasColumn('offline_sales', 'cash_received')) {
                $table->dropColumn('cash_received');
            }

            if (Schema::hasColumn('offline_sales', 'change_given')) {
                $table->dropColumn('change_given');
            }
        });
    }
};
