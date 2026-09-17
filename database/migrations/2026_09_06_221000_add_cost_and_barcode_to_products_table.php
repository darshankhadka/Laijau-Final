<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'cost_price_npr')) {
                $table->decimal('cost_price_npr', 10, 2)->nullable()->after('price_npr');
            }
            if (!Schema::hasColumn('products', 'supplier_name')) {
                $table->string('supplier_name', 150)->nullable()->after('sku');
            }
            if (!Schema::hasColumn('products', 'supplier_sku')) {
                $table->string('supplier_sku', 100)->nullable()->after('supplier_name');
            }
            if (!Schema::hasColumn('products', 'barcode')) {
                $table->string('barcode', 100)->nullable()->after('supplier_sku');
            }
        });

        Schema::table('product_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('product_variants', 'cost_price_npr')) {
                $table->decimal('cost_price_npr', 10, 2)->nullable()->after('price_npr');
            }
            if (!Schema::hasColumn('product_variants', 'barcode')) {
                $table->string('barcode', 100)->nullable()->after('sku');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['cost_price_npr', 'supplier_name', 'supplier_sku', 'barcode']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['cost_price_npr', 'barcode']);
        });
    }
};
