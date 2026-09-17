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
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'order_number')) {
                $table->string('order_number')->nullable()->unique();
            }
            if (!Schema::hasColumn('orders', 'billing_address')) {
                $table->text('billing_address')->nullable();
            }
            if (!Schema::hasColumn('orders', 'billing_country')) {
                $table->string('billing_country')->nullable();
            }
            if (!Schema::hasColumn('orders', 'shipping_city')) {
                $table->string('shipping_city')->nullable();
            }
            if (!Schema::hasColumn('orders', 'shipping_postal_code')) {
                $table->string('shipping_postal_code')->nullable();
            }
            if (!Schema::hasColumn('orders', 'shipping_method')) {
                $table->string('shipping_method')->nullable();
            }
            if (!Schema::hasColumn('orders', 'shipping_fee')) {
                $table->decimal('shipping_fee', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('orders', 'internal_notes')) {
                $table->text('internal_notes')->nullable();
            }
            if (!Schema::hasColumn('orders', 'customer_notes')) {
                $table->text('customer_notes')->nullable();
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'variant_id')) {
                $table->foreignId('variant_id')->nullable();
            }
            if (!Schema::hasColumn('order_items', 'product_name')) {
                $table->string('product_name')->nullable();
            }
            if (!Schema::hasColumn('order_items', 'sku')) {
                $table->string('sku')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $cols = [
                'order_number', 'billing_address', 'billing_country',
                'shipping_city', 'shipping_postal_code', 'shipping_method',
                'shipping_fee', 'internal_notes', 'customer_notes'
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('orders', $c)) {
                    $table->dropColumn($c);
                }
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            $cols = ['variant_id', 'product_name', 'sku'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('order_items', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
