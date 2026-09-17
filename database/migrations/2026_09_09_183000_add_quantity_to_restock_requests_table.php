<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restock_requests') && !Schema::hasColumn('restock_requests', 'quantity')) {
            Schema::table('restock_requests', function (Blueprint $table) {
                $table->integer('quantity')->default(1)->after('phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('restock_requests') && Schema::hasColumn('restock_requests', 'quantity')) {
            Schema::table('restock_requests', function (Blueprint $table) {
                $table->dropColumn('quantity');
            });
        }
    }
};
