<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Additive, non-destructive enhancements for LAIJAU Retail Products Console.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'brand')) {
                $table->string('brand', 150)->nullable()->after('name');
            }
            if (!Schema::hasColumn('products', 'model')) {
                $table->string('model', 150)->nullable()->after('brand');
            }
            if (!Schema::hasColumn('products', 'wholesale_price')) {
                $table->decimal('wholesale_price', 12, 2)->nullable()->after('cost_price');
            }
            if (!Schema::hasColumn('products', 'internal_reference')) {
                $table->string('internal_reference', 100)->nullable()->after('supplier_sku');
            }

            // Indexes for fast retail lookups and barcode scanner inputs
            $table->index('barcode', 'products_barcode_idx');
            $table->index('brand', 'products_brand_idx');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('product_variants', 'wholesale_price')) {
                $table->decimal('wholesale_price', 12, 2)->nullable()->after('cost_price');
            }
            if (!Schema::hasColumn('product_variants', 'weight')) {
                $table->decimal('weight', 8, 2)->nullable()->after('reserved_quantity');
            }

            // Indexes for fast variant barcode scanning
            $table->index('barcode', 'product_variants_barcode_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_barcode_idx');
            $table->dropIndex('products_brand_idx');
            $table->dropColumn(['brand', 'model', 'wholesale_price', 'internal_reference']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('product_variants_barcode_idx');
            $table->dropColumn(['wholesale_price', 'weight']);
        });
    }
};
