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
        // 1. Harden Stock Levels
        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_stock_levels', 'damaged_quantity')) {
                $table->integer('damaged_quantity')->default(0)->after('quantity_incoming');
            }
            if (!Schema::hasColumn('inventory_stock_levels', 'quarantined_quantity')) {
                $table->integer('quarantined_quantity')->default(0)->after('damaged_quantity');
            }
            if (!Schema::hasColumn('inventory_stock_levels', 'safety_stock')) {
                $table->integer('safety_stock')->default(2)->after('reorder_quantity');
            }
            if (!Schema::hasColumn('inventory_stock_levels', 'maximum_stock')) {
                $table->integer('maximum_stock')->default(100)->after('safety_stock');
            }
            if (!Schema::hasColumn('inventory_stock_levels', 'last_counted_at')) {
                $table->timestamp('last_counted_at')->nullable()->after('bin_location');
            }
        });

        // 2. Harden Stock Movements
        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_stock_movements', 'available_before')) {
                $table->integer('available_before')->default(0)->after('quantity_after');
            }
            if (!Schema::hasColumn('inventory_stock_movements', 'available_after')) {
                $table->integer('available_after')->default(0)->after('available_before');
            }
            if (!Schema::hasColumn('inventory_stock_movements', 'currency')) {
                $table->string('currency', 3)->default('NPR')->after('total_cost_npr');
            }
        });

        // 3. Harden Suppliers
        Schema::table('inventory_suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_suppliers', 'legal_name')) {
                $table->string('legal_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('inventory_suppliers', 'postal_code')) {
                $table->string('postal_code', 20)->nullable()->after('address');
            }
        });

        // 4. Harden Purchase Orders
        Schema::table('inventory_purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_purchase_orders', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('inventory_purchase_orders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
        });

        // 5. Harden Transfers
        Schema::table('inventory_transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_transfers', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('initiated_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('inventory_transfers', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('inventory_transfers', 'dispatched_by')) {
                $table->foreignId('dispatched_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('inventory_transfers', 'carrier')) {
                $table->string('carrier')->nullable()->after('tracking_reference');
            }
        });

        // 6. Harden Stock Counts
        Schema::table('inventory_stock_counts', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_stock_counts', 'count_type')) {
                $table->string('count_type', 30)->default('full')->after('warehouse_id'); // full, cycle, blind
            }
            if (!Schema::hasColumn('inventory_stock_counts', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('reconciled_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('inventory_stock_counts', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
        });

        // 7. Harden Stock Reservations
        Schema::table('inventory_reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_reservations', 'reservation_number')) {
                $table->string('reservation_number', 40)->nullable()->after('id');
            }
            if (!Schema::hasColumn('inventory_reservations', 'cart_token')) {
                $table->string('cart_token', 100)->nullable()->after('reference_id');
            }
            if (!Schema::hasColumn('inventory_reservations', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('expires_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('inventory_reservations', 'released_at')) {
                $table->timestamp('released_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('inventory_reservations', 'notes')) {
                $table->text('notes')->nullable()->after('released_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive down method
    }
};
