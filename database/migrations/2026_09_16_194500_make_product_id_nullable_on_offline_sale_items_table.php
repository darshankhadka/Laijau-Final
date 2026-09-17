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
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            Schema::table('offline_sale_items', function (Blueprint $table) {
                $table->dropForeign('offline_sale_items_product_id_foreign');
            });
        }

        Schema::table('offline_sale_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->change();
            if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            Schema::table('offline_sale_items', function (Blueprint $table) {
                $table->dropForeign('offline_sale_items_product_id_foreign');
            });
        }

        Schema::table('offline_sale_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable(false)->change();
            if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                $table->foreign('product_id')
                    ->references('id')
                    ->on('products');
            }
        });
    }
};
