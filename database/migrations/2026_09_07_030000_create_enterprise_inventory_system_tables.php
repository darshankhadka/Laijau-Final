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
        // 1. Warehouses & Physical Locations
        Schema::create('inventory_warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // WH-KTM-MAIN, STORE-KTM-01, STORE-KTM-02
            $table->string('name');
            $table->enum('type', ['warehouse', 'showroom_pos', 'production', 'transit', 'virtual'])->default('warehouse');
            $table->string('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 2)->default('NP');
            $table->string('manager_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('allow_sales')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. Suppliers & Artisan Cooperatives
        Schema::create('inventory_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // SUP-PASHMINA-KTM, SUP-SILK-VARANASI
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('NP');
            $table->string('currency', 3)->default('npr');
            $table->string('payment_terms')->nullable()->default('Net 30');
            $table->unsignedSmallInteger('lead_time_days')->default(14);
            $table->string('tax_vat_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. Multi-location Stock Levels (Source of Truth)
        Schema::create('inventory_stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('quantity_incoming')->default(0);
            $table->integer('reorder_point')->default(3);
            $table->integer('reorder_quantity')->default(10);
            $table->decimal('unit_cost_npr', 12, 2)->default(0.00);
            $table->string('bin_location', 50)->nullable(); // e.g. "Aisle 1 - Shelf B2"
            $table->timestamps();

            $table->unique(['warehouse_id', 'product_id', 'variant_id'], 'inv_stock_level_unique');
            $table->index(['product_id', 'variant_id']);
        });

        // 4. Traceable Stock Ledger (Stock Movements)
        Schema::create('inventory_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_number', 40)->unique(); // MOV-20260907-0001
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->foreignId('target_warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->enum('movement_type', [
                'opening_stock',
                'purchase_receive',
                'sale_order',
                'sale_pos',
                'return_customer',
                'return_supplier',
                'transfer_out',
                'transfer_in',
                'adjustment_gain',
                'adjustment_loss',
                'damage',
                'loss',
                'correction',
                'count_reconciliation',
            ])->index();
            $table->integer('quantity'); // Signed delta (+/-)
            $table->integer('quantity_before')->default(0);
            $table->integer('quantity_after')->default(0);
            $table->decimal('unit_cost_npr', 12, 2)->default(0.00);
            $table->decimal('total_cost_npr', 14, 2)->default(0.00);
            $table->string('reference_type', 40)->nullable()->index(); // order, offline_sale, purchase_order, transfer, count
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->string('reference_number', 60)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'movement_type']);
        });

        // 5. Purchase Orders & Inbound Goods Receiving
        Schema::create('inventory_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 40)->unique(); // PO-2026-09-001
            $table->foreignId('supplier_id')->constrained('inventory_suppliers')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete(); // Destination WH
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('received_date')->nullable();
            $table->string('currency', 3)->default('npr');
            $table->decimal('exchange_rate_to_npr', 12, 6)->default(1.000000);
            $table->decimal('subtotal_currency', 14, 2)->default(0.00);
            $table->decimal('shipping_cost_npr', 12, 2)->default(0.00);
            $table->decimal('customs_duty_npr', 12, 2)->default(0.00);
            $table->decimal('total_amount_npr', 14, 2)->default(0.00);
            $table->enum('status', ['draft', 'ordered', 'in_transit', 'partially_received', 'received', 'cancelled'])->default('draft')->index();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('inventory_purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->integer('quantity_ordered')->default(1);
            $table->integer('quantity_received')->default(0);
            $table->decimal('unit_cost_currency', 12, 2)->default(0.00);
            $table->decimal('unit_cost_npr', 12, 2)->default(0.00);
            $table->decimal('total_cost_npr', 14, 2)->default(0.00);
            $table->timestamps();
        });

        // 6. Inter-Warehouse Transfers
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 40)->unique(); // TRF-2026-001
            $table->foreignId('source_warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->enum('status', ['draft', 'in_transit', 'completed', 'cancelled'])->default('draft')->index();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('tracking_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('inventory_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->integer('quantity_sent')->default(1);
            $table->integer('quantity_received')->default(0);
            $table->timestamps();
        });

        // 7. Stock Adjustments (Damage / Loss / Found / Correction)
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number', 40)->unique(); // ADJ-2026-001
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->enum('type', ['damage', 'loss', 'found', 'correction'])->default('correction');
            $table->integer('quantity'); // delta
            $table->decimal('unit_cost_npr', 12, 2)->default(0.00);
            $table->decimal('total_value_npr', 14, 2)->default(0.00);
            $table->string('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->index();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 8. Physical Stock Counts & Audit Reconciliations
        Schema::create('inventory_stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('count_number', 40)->unique(); // CNT-2026-001
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->date('count_date');
            $table->enum('status', ['draft', 'in_progress', 'completed', 'reconciled'])->default('draft')->index();
            $table->integer('total_expected_items')->default(0);
            $table->integer('total_counted_items')->default(0);
            $table->integer('total_variance_items')->default(0);
            $table->decimal('total_variance_value_npr', 14, 2)->default(0.00);
            $table->foreignId('conducted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('inventory_stock_counts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->integer('expected_quantity')->default(0);
            $table->integer('counted_quantity')->default(0);
            $table->integer('variance_quantity')->default(0); // counted - expected
            $table->decimal('unit_cost_npr', 12, 2)->default(0.00);
            $table->decimal('variance_value_npr', 14, 2)->default(0.00);
            $table->boolean('is_reconciled')->default(false);
            $table->timestamps();
        });

        // 9. Temporary Stock Reservations (Active checkout holds)
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 40)->index(); // order, pos_hold, cart
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('expires_at')->nullable()->index();
            $table->enum('status', ['active', 'released', 'fulfilled'])->default('active')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('inventory_stock_count_items');
        Schema::dropIfExists('inventory_stock_counts');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_transfer_items');
        Schema::dropIfExists('inventory_transfers');
        Schema::dropIfExists('inventory_purchase_order_items');
        Schema::dropIfExists('inventory_purchase_orders');
        Schema::dropIfExists('inventory_stock_movements');
        Schema::dropIfExists('inventory_stock_levels');
        Schema::dropIfExists('inventory_suppliers');
        Schema::dropIfExists('inventory_warehouses');
    }
};
