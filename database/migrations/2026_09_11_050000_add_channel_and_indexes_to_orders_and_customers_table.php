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
        // 1. Add channel to orders if not present
        if (!Schema::hasColumn('orders', 'channel')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('channel', 32)->default('online')->after('order_number')->index();
            });
        }

        // Performance indexes on orders
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasIndex('orders', 'orders_phone_index')) {
                $table->index('phone', 'orders_phone_index');
            }
            if (!Schema::hasIndex('orders', 'orders_carrier_index')) {
                $table->index('carrier', 'orders_carrier_index');
            }
            if (!Schema::hasIndex('orders', 'orders_tracking_number_index')) {
                $table->index('tracking_number', 'orders_tracking_number_index');
            }
        });

        // 2. Add notes to users (customers) if not present
        if (!Schema::hasColumn('users', 'notes')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('remember_token');
            });
        }

        // Performance indexes on users
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasIndex('users', 'users_role_index')) {
                $table->index('role', 'users_role_index');
            }
            if (!Schema::hasIndex('users', 'users_phone_index')) {
                $table->index('phone', 'users_phone_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'channel')) {
                $table->dropColumn('channel');
            }
            if (Schema::hasIndex('orders', 'orders_phone_index')) {
                $table->dropIndex('orders_phone_index');
            }
            if (Schema::hasIndex('orders', 'orders_carrier_index')) {
                $table->dropIndex('orders_carrier_index');
            }
            if (Schema::hasIndex('orders', 'orders_tracking_number_index')) {
                $table->dropIndex('orders_tracking_number_index');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasIndex('users', 'users_role_index')) {
                $table->dropIndex('users_role_index');
            }
            if (Schema::hasIndex('users', 'users_phone_index')) {
                $table->dropIndex('users_phone_index');
            }
        });
    }
};
