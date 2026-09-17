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
        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_published', 'is_active'], 'products_pub_act_idx');
            $table->index('is_featured', 'products_featured_idx');
            $table->index('is_new_arrival', 'products_new_arrival_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('status', 'orders_status_idx');
            $table->index('payment_status', 'orders_payment_status_idx');
            $table->index('email', 'orders_email_idx');
            $table->index('payment_id', 'orders_payment_id_idx');
            $table->index(['status', 'created_at'], 'orders_status_created_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index('is_active', 'categories_is_active_idx');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->index('is_published', 'collections_is_published_idx');
        });

        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->index('is_active', 'shipping_methods_is_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_pub_act_idx');
            $table->dropIndex('products_featured_idx');
            $table->dropIndex('products_new_arrival_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_idx');
            $table->dropIndex('orders_payment_status_idx');
            $table->dropIndex('orders_email_idx');
            $table->dropIndex('orders_payment_id_idx');
            $table->dropIndex('orders_status_created_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_is_active_idx');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex('collections_is_published_idx');
        });

        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropIndex('shipping_methods_is_active_idx');
        });
    }
};
