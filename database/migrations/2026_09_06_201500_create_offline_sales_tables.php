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
        // 1. Offline Sales Header
        Schema::create('offline_sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name')->default('Walk-in Customer');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();

            // Financials
            $table->string('currency', 3)->default('npr');
            $table->decimal('exchange_rate_to_npr', 12, 6)->default(1.000000);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);

            // Cost & Profitability Intelligence (integrated with Cost Catalog)
            $table->decimal('total_cost_npr', 12, 4)->default(0);
            $table->decimal('total_profit_npr', 12, 4)->default(0);
            $table->decimal('margin_percentage', 8, 2)->default(0);
            $table->enum('profit_status', ['realized', 'estimated', 'cost_pending'])->default('estimated');

            // Metadata
            $table->string('payment_method', 50)->default('cash')->index();
            $table->string('sales_channel', 50)->default('physical')->index();

            $table->enum('status', ['completed', 'voided'])->default('completed')->index();
            $table->timestamp('sold_at')->useCurrent()->index();

            // Staff & Notes
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('staff_name')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();

            // Voiding Audit
            $table->text('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        // 2. Offline Sale Line Items
        Schema::create('offline_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offline_sale_id')->constrained('offline_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('sku')->index();
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->unsignedInteger('quantity')->default(1);

            // Pricing
            $table->decimal('website_price', 12, 2)->default(0); // Reference online price
            $table->decimal('unit_price', 12, 2)->default(0);    // Actual negotiated offline price
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);

            // Unit Cost & Profit
            $table->decimal('unit_cost_npr', 12, 4)->default(0);
            $table->decimal('total_cost_npr', 12, 4)->default(0);
            $table->decimal('unit_profit_npr', 12, 4)->default(0);
            $table->decimal('total_profit_npr', 12, 4)->default(0);
            $table->decimal('margin_percentage', 8, 2)->default(0);
            $table->enum('cost_type', ['realized', 'estimated', 'unknown'])->default('estimated');
            $table->string('notes')->nullable();

            $table->timestamps();
        });

        // 3. Offline Sale Void Audit Logs
        Schema::create('offline_sale_void_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offline_sale_id')->constrained('offline_sales')->cascadeOnDelete();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('voided_by_name');
            $table->text('reason');
            $table->boolean('restocked')->default(true);
            $table->json('snapshot_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offline_sale_void_logs');
        Schema::dropIfExists('offline_sale_items');
        Schema::dropIfExists('offline_sales');
    }
};
