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
        // 1. Cost Catalog Products
        Schema::create('cost_catalog_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('internal_reference')->nullable();
            $table->string('sku')->nullable()->index();
            $table->string('supplier')->nullable()->index();
            $table->string('supplier_sku')->nullable();
            $table->string('category')->nullable()->index();
            $table->string('product_type')->nullable();
            $table->string('brand')->nullable();
            $table->string('country_of_origin')->nullable();
            $table->string('currency', 3)->default('npr');
            $table->enum('status', ['draft', 'active', 'archived'])->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. Cost Catalog Batches
        Schema::create('cost_catalog_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_catalog_product_id')
                ->constrained('cost_catalog_products')
                ->cascadeOnDelete();
            $table->string('batch_reference')->default('Batch #1');
            $table->date('purchase_date')->nullable();
            $table->enum('status', ['draft', 'active', 'archived', 'finalized'])->default('active');

            // Purchase economics
            $table->unsignedInteger('quantity')->default(1);
            $table->string('purchase_currency', 3)->default('NPR');
            $table->decimal('exchange_rate', 12, 6)->default(1.000000);
            $table->decimal('unit_purchase_cost', 12, 4)->default(0);
            $table->decimal('unit_purchase_cost_npr', 12, 4)->default(0);
            $table->decimal('total_purchase_cost_npr', 12, 4)->default(0);

            // Landed Logistics & Prep Summary Totals
            $table->decimal('total_international_shipping_npr', 12, 4)->default(0);
            $table->decimal('total_customs_duties_npr', 12, 4)->default(0);
            $table->decimal('total_local_logistics_npr', 12, 4)->default(0);
            $table->decimal('total_preparation_costs_npr', 12, 4)->default(0);
            $table->decimal('total_other_costs_npr', 12, 4)->default(0);
            $table->decimal('total_landed_cost_npr', 12, 4)->default(0);
            $table->decimal('unit_landed_cost_npr', 12, 4)->default(0);

            // Selling economics & Tax / Payment fees
            $table->boolean('selling_price_includes_vat')->default(true);
            $table->decimal('vat_rate', 5, 2)->default(13.00);
            $table->string('payment_provider')->default('esewa');
            $table->decimal('payment_fee_percent', 5, 2)->default(2.90);
            $table->decimal('payment_fee_fixed_npr', 12, 4)->default(0.30);

            // Selling price scenarios
            $table->decimal('target_selling_price_npr', 12, 4)->nullable();
            $table->decimal('current_selling_price_npr', 12, 4)->nullable();
            $table->decimal('promotional_price_npr', 12, 4)->nullable();
            $table->decimal('minimum_acceptable_price_npr', 12, 4)->nullable();
            $table->decimal('maximum_planned_price_npr', 12, 4)->nullable();

            // Calculated Output Profitability Metrics (for current/target price)
            $table->decimal('unit_vat_amount_npr', 12, 4)->default(0);
            $table->decimal('unit_net_sales_revenue_npr', 12, 4)->default(0);
            $table->decimal('unit_payment_fee_npr', 12, 4)->default(0);
            $table->decimal('unit_net_revenue_after_fees_npr', 12, 4)->default(0);
            $table->decimal('unit_gross_profit_npr', 12, 4)->default(0);
            $table->decimal('unit_actual_profit_npr', 12, 4)->default(0);
            $table->decimal('profit_percentage', 8, 2)->default(0);
            $table->decimal('gross_margin_percentage', 8, 2)->default(0);
            $table->decimal('markup_percentage', 8, 2)->default(0);
            $table->decimal('roi_percentage', 8, 2)->default(0);
            $table->decimal('breakeven_selling_price_npr', 12, 4)->default(0);
            $table->unsignedInteger('breakeven_quantity')->default(0);

            // Batch Totals
            $table->decimal('batch_total_revenue_npr', 12, 4)->default(0);
            $table->decimal('batch_total_vat_npr', 12, 4)->default(0);
            $table->decimal('batch_total_payment_fees_npr', 12, 4)->default(0);
            $table->decimal('batch_total_profit_npr', 12, 4)->default(0);

            $table->enum('profitability_status', ['loss', 'very_low', 'acceptable', 'profitable', 'high_margin'])->default('acceptable')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. Cost Catalog Cost Items
        Schema::create('cost_catalog_cost_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_catalog_batch_id')
                ->constrained('cost_catalog_batches')
                ->cascadeOnDelete();
            $table->enum('category', [
                'international_shipping',
                'customs_duties',
                'local_logistics',
                'preparation',
                'other'
            ])->index();
            $table->string('cost_type')->nullable();
            $table->string('subcategory')->nullable();
            $table->string('description')->nullable();
            $table->enum('allocation_type', ['batch_total', 'per_unit'])->default('batch_total');
            $table->decimal('amount', 12, 4)->default(0);
            $table->string('currency', 3)->default('npr');
            $table->decimal('exchange_rate', 12, 6)->default(1.000000);
            $table->decimal('converted_amount_npr', 12, 4)->default(0);
            $table->decimal('effective_per_unit_cost_npr', 12, 4)->default(0);
            $table->decimal('effective_batch_cost_npr', 12, 4)->default(0);
            $table->decimal('percentage_of_landed_cost', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. Cost Catalog Price Scenarios
        Schema::create('cost_catalog_price_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_catalog_batch_id')
                ->constrained('cost_catalog_batches')
                ->cascadeOnDelete();
            $table->string('scenario_name');
            $table->decimal('selling_price_npr', 12, 4)->default(0);
            $table->decimal('vat_amount_npr', 12, 4)->default(0);
            $table->decimal('payment_fee_npr', 12, 4)->default(0);
            $table->decimal('net_revenue_npr', 12, 4)->default(0);
            $table->decimal('actual_profit_npr', 12, 4)->default(0);
            $table->decimal('margin_percentage', 8, 2)->default(0);
            $table->decimal('markup_percentage', 8, 2)->default(0);
            $table->decimal('roi_percentage', 8, 2)->default(0);
            $table->decimal('batch_total_profit_npr', 12, 4)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // 5. Cost Catalog Snapshots
        Schema::create('cost_catalog_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_catalog_product_id')
                ->nullable()
                ->constrained('cost_catalog_products')
                ->cascadeOnDelete();
            $table->foreignId('cost_catalog_batch_id')
                ->constrained('cost_catalog_batches')
                ->cascadeOnDelete();
            $table->string('snapshot_title')->nullable();
            $table->string('snapshot_reference')->nullable()->index();
            $table->date('snapshot_date')->nullable();
            $table->json('calculated_data')->nullable();
            $table->json('financial_data')->nullable();
            $table->string('finalized_by')->nullable();
            $table->string('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_catalog_snapshots');
        Schema::dropIfExists('cost_catalog_price_scenarios');
        Schema::dropIfExists('cost_catalog_cost_items');
        Schema::dropIfExists('cost_catalog_batches');
        Schema::dropIfExists('cost_catalog_products');
    }
};
