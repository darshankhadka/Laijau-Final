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
            if (!Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
            }
            if (!Schema::hasColumn('orders', 'cancellation_reason')) {
                $table->string('cancellation_reason')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('orders', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('cancellation_reason');
            }
            if (!Schema::hasColumn('orders', 'refunded_amount')) {
                $table->decimal('refunded_amount', 12, 2)->default(0.00)->after('refunded_at');
            }
            if (!Schema::hasColumn('orders', 'restocking_fee')) {
                $table->decimal('restocking_fee', 12, 2)->default(0.00)->after('refunded_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivered_at',
                'cancelled_at',
                'cancellation_reason',
                'refunded_at',
                'refunded_amount',
                'restocking_fee',
            ]);
        });
    }
};
