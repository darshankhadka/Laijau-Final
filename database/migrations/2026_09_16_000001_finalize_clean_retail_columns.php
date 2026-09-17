<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations to purge all custom and legacy gateway columns.
     */
    public function up(): void
    {
        // 1. Ensure inventory_stock_levels unique constraint is clean
        if (Schema::hasTable('inventory_stock_levels')) {
            try {
                Schema::table('inventory_stock_levels', function (Blueprint $table) {
                    $table->unique(['warehouse_id', 'product_id', 'variant_id'], 'inv_stock_level_unique');
                });
            } catch (\Throwable $e) {
                // Ignore if index already exists
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
