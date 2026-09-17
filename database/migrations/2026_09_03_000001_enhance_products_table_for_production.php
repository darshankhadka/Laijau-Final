<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'lowest_price_30_days')) {
                $table->decimal('lowest_price_30_days', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('products', 'price_npr')) {
                $table->decimal('price_npr', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('products', 'compare_at_price_npr')) {
                $table->decimal('compare_at_price_npr', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('products', 'is_published')) {
                $table->boolean('is_published')->default(true);
            }
            if (!Schema::hasColumn('products', 'is_new_arrival')) {
                $table->boolean('is_new_arrival')->default(false);
            }
            if (!Schema::hasColumn('products', 'short_description')) {
                $table->text('short_description')->nullable();
            }
            if (!Schema::hasColumn('products', 'low_stock_threshold')) {
                $table->integer('low_stock_threshold')->default(5);
            }
            if (!Schema::hasColumn('products', 'weight')) {
                $table->decimal('weight', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('products', 'dimensions')) {
                $table->string('dimensions')->nullable();
            }
            if (!Schema::hasColumn('products', 'material')) {
                $table->string('material')->nullable();
            }
            if (!Schema::hasColumn('products', 'fabric')) {
                $table->string('fabric')->nullable();
            }
            if (!Schema::hasColumn('products', 'care_instructions')) {
                $table->text('care_instructions')->nullable();
            }
            if (!Schema::hasColumn('products', 'country_of_origin')) {
                $table->string('country_of_origin')->default('Nepal');
            }
        });

        // Safe data backfill: if price exists and price_npr is null, copy price
        DB::table('products')->whereNull('price_npr')->whereNotNull('price')->update([
            'price_npr' => DB::raw('price'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $cols = [
                'price_npr',
                'compare_at_price_npr',
                'is_published',
                'is_new_arrival',
                'short_description',
                'low_stock_threshold',
                'weight',
                'dimensions',
                'material',
                'fabric',
                'care_instructions',
                'country_of_origin'
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('products', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
