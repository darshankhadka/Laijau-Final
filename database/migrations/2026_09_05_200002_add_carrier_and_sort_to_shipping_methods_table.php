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
        Schema::table('shipping_methods', function (Blueprint $table) {
            if (!Schema::hasColumn('shipping_methods', 'carrier')) {
                $table->string('carrier')->nullable()->after('name');
            }
            if (!Schema::hasColumn('shipping_methods', 'description')) {
                $table->text('description')->nullable()->after('estimated_delivery');
            }
            if (!Schema::hasColumn('shipping_methods', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            if (Schema::hasColumn('shipping_methods', 'carrier')) {
                $table->dropColumn('carrier');
            }
            if (Schema::hasColumn('shipping_methods', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('shipping_methods', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};
