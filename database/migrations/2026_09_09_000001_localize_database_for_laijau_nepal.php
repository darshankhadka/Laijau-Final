<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Laijau Nepal localization.
     */
    public function up(): void
    {
        // 1. Orders table localization & operational tracking
        $orderCols = [
            'province' => fn (Blueprint $table) => $table->string('province', 100)->nullable()->after('shipping_country'),
            'district' => fn (Blueprint $table) => $table->string('district', 100)->nullable()->after('province'),
            'municipality' => fn (Blueprint $table) => $table->string('municipality', 150)->nullable()->after('district'),
            'ward' => fn (Blueprint $table) => $table->string('ward', 20)->nullable()->after('municipality'),
            'tole' => fn (Blueprint $table) => $table->string('tole', 150)->nullable()->after('ward'),
            'landmark' => fn (Blueprint $table) => $table->string('landmark', 255)->nullable()->after('tole'),
            'alt_phone' => fn (Blueprint $table) => $table->string('alt_phone', 50)->nullable()->after('phone'),
            'is_inside_valley' => fn (Blueprint $table) => $table->boolean('is_inside_valley')->default(false)->after('landmark'),
            'payment_reference' => fn (Blueprint $table) => $table->string('payment_reference', 150)->nullable()->after('payment_id'),
            'payment_receipt_image' => fn (Blueprint $table) => $table->string('payment_receipt_image', 255)->nullable()->after('payment_reference'),
            'payment_verified_at' => fn (Blueprint $table) => $table->timestamp('payment_verified_at')->nullable()->after('payment_receipt_image'),
            'payment_verified_by' => fn (Blueprint $table) => $table->unsignedBigInteger('payment_verified_by')->nullable()->after('payment_verified_at'),
            'payment_notes' => fn (Blueprint $table) => $table->text('payment_notes')->nullable()->after('payment_verified_by'),
            'courier_name' => fn (Blueprint $table) => $table->string('courier_name', 100)->nullable()->after('carrier'),
            'courier_pickup_date' => fn (Blueprint $table) => $table->date('courier_pickup_date')->nullable()->after('courier_name'),
            'estimated_delivery_date' => fn (Blueprint $table) => $table->date('estimated_delivery_date')->nullable()->after('courier_pickup_date'),
            'actual_delivery_date' => fn (Blueprint $table) => $table->date('actual_delivery_date')->nullable()->after('estimated_delivery_date'),
            'delivery_notes' => fn (Blueprint $table) => $table->text('delivery_notes')->nullable()->after('actual_delivery_date'),
            'guest_access_token' => fn (Blueprint $table) => $table->string('guest_access_token', 64)->nullable()->unique()->after('order_number'),
        ];

        foreach ($orderCols as $col => $cb) {
            if (Schema::hasTable('orders') && !Schema::hasColumn('orders', $col)) {
                Schema::table('orders', $cb);
            }
        }

        // 2. Users table Nepal address and contact fields
        $userCols = [
            'province' => fn (Blueprint $table) => $table->string('province', 100)->nullable()->after('country'),
            'district' => fn (Blueprint $table) => $table->string('district', 100)->nullable()->after('province'),
            'municipality' => fn (Blueprint $table) => $table->string('municipality', 150)->nullable()->after('district'),
            'ward' => fn (Blueprint $table) => $table->string('ward', 20)->nullable()->after('municipality'),
            'tole' => fn (Blueprint $table) => $table->string('tole', 150)->nullable()->after('ward'),
            'landmark' => fn (Blueprint $table) => $table->string('landmark', 255)->nullable()->after('tole'),
            'alt_phone' => fn (Blueprint $table) => $table->string('alt_phone', 50)->nullable()->after('phone'),
            'whatsapp_phone' => fn (Blueprint $table) => $table->string('whatsapp_phone', 50)->nullable()->after('alt_phone'),
        ];

        foreach ($userCols as $col => $cb) {
            if (Schema::hasTable('users') && !Schema::hasColumn('users', $col)) {
                Schema::table('users', $cb);
            }
        }

        // 3. Product variants NPR pricing & costing
        if (Schema::hasTable('product_variants') && !Schema::hasColumn('product_variants', 'price')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->decimal('price', 10, 2)->nullable()->after('barcode');
            });
        }
        if (Schema::hasTable('product_variants') && !Schema::hasColumn('product_variants', 'cost_price')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->decimal('cost_price', 10, 2)->nullable()->after('price');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $cols = [
                'province', 'district', 'municipality', 'ward', 'tole', 'landmark',
                'alt_phone', 'is_inside_valley', 'payment_reference', 'payment_receipt_image',
                'payment_verified_at', 'payment_verified_by', 'payment_notes',
                'courier_name', 'courier_pickup_date', 'estimated_delivery_date',
                'actual_delivery_date', 'delivery_notes', 'guest_access_token'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $cols = ['province', 'district', 'municipality', 'ward', 'tole', 'landmark', 'alt_phone', 'whatsapp_phone'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $cols = ['price', 'cost_price'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('product_variants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
