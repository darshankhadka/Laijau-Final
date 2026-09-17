<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations to finalize the clean Nepal-only architecture.
     */
    public function up(): void
    {
        // 1. Shipping Methods table refactor to Nepal standard
        if (Schema::hasTable('shipping_methods')) {
            Schema::table('shipping_methods', function (Blueprint $table) {
                if (!Schema::hasColumn('shipping_methods', 'price_npr')) {
                    $table->decimal('price_npr', 10, 2)->default(0.00)->after('zone');
                }
                if (!Schema::hasColumn('shipping_methods', 'free_shipping_threshold_npr')) {
                    $table->decimal('free_shipping_threshold_npr', 10, 2)->nullable()->after('price_npr');
                }
            });

            // Backfill price_npr from legacy price_npr if available
            if (Schema::hasColumn('shipping_methods', 'price_npr')) {
                DB::table('shipping_methods')->whereNull('price_npr')->orWhere('price_npr', 0)->update([
                    'price_npr' => DB::raw('COALESCE(price_npr, 0)'),
                ]);
            }
            if (Schema::hasColumn('shipping_methods', 'free_shipping_threshold_npr')) {
                DB::table('shipping_methods')->whereNull('free_shipping_threshold_npr')->update([
                    'free_shipping_threshold_npr' => DB::raw('free_shipping_threshold_npr'),
                ]);
            }

            // Drop obsolete columns from shipping_methods
            Schema::table('shipping_methods', function (Blueprint $table) {
                $dropCols = [];
                foreach (['countries'] as $col) {
                    if (Schema::hasColumn('shipping_methods', $col)) {
                        $dropCols[] = $col;
                    }
                }
                if (!empty($dropCols)) {
                    $table->dropColumn($dropCols);
                }
            });
        }

        // 2. Products table cleanup

        // 3. Product Variants table cleanup
        if (Schema::hasTable('product_variants')) {
            Schema::table('product_variants', function (Blueprint $table) {
                // Table cleanup if needed
            });
        }

        // 4. Inventory Stock Levels table: add unit_cost_npr
        if (Schema::hasTable('inventory_stock_levels')) {
            Schema::table('inventory_stock_levels', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_stock_levels', 'unit_cost_npr')) {
                    $table->decimal('unit_cost_npr', 12, 2)->default(0.00)->after('maximum_stock');
                }
            });

            if (Schema::hasColumn('inventory_stock_levels', 'unit_cost_npr')) {
                DB::table('inventory_stock_levels')->where('unit_cost_npr', 0)->update([
                    'unit_cost_npr' => DB::raw('COALESCE(unit_cost_npr, 0)'),
                ]);
            }
        }

        // 5. Inventory Stock Movements table: add unit_cost_npr & total_cost_npr
        if (Schema::hasTable('inventory_stock_movements')) {
            Schema::table('inventory_stock_movements', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_stock_movements', 'unit_cost_npr')) {
                    $table->decimal('unit_cost_npr', 12, 2)->default(0.00)->after('quantity_after');
                }
                if (!Schema::hasColumn('inventory_stock_movements', 'total_cost_npr')) {
                    $table->decimal('total_cost_npr', 14, 2)->default(0.00)->after('unit_cost_npr');
                }
            });
        }

        // 6. Inventory Purchase Orders table: add NPR financial columns
        if (Schema::hasTable('inventory_purchase_orders')) {
            Schema::table('inventory_purchase_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_purchase_orders', 'shipping_cost_npr')) {
                    $table->decimal('shipping_cost_npr', 12, 2)->default(0.00)->after('currency');
                }
                if (!Schema::hasColumn('inventory_purchase_orders', 'customs_duty_npr')) {
                    $table->decimal('customs_duty_npr', 12, 2)->default(0.00)->after('shipping_cost_npr');
                }
                if (!Schema::hasColumn('inventory_purchase_orders', 'total_amount_npr')) {
                    $table->decimal('total_amount_npr', 14, 2)->default(0.00)->after('customs_duty_npr');
                }
            });
        }

        // 7. Inventory Purchase Order Items table: add unit_cost_npr & total_cost_npr
        if (Schema::hasTable('inventory_purchase_order_items')) {
            Schema::table('inventory_purchase_order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_purchase_order_items', 'unit_cost_npr')) {
                    $table->decimal('unit_cost_npr', 12, 2)->default(0.00)->after('quantity_received');
                }
                if (!Schema::hasColumn('inventory_purchase_order_items', 'total_cost_npr')) {
                    $table->decimal('total_cost_npr', 14, 2)->default(0.00)->after('unit_cost_npr');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op to preserve clean architecture
    }
};
