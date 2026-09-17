<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_suppliers') && !Schema::hasColumn('inventory_suppliers', 'due_balance')) {
            Schema::table('inventory_suppliers', function (Blueprint $table) {
                $table->decimal('due_balance', 14, 2)->default(0.00)->after('currency');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_suppliers') && Schema::hasColumn('inventory_suppliers', 'due_balance')) {
            Schema::table('inventory_suppliers', function (Blueprint $table) {
                $table->dropColumn('due_balance');
            });
        }
    }
};
