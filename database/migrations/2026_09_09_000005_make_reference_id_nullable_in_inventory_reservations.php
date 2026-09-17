<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_reservations')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE inventory_reservations MODIFY COLUMN reference_id BIGINT UNSIGNED NULL");
            } else {
                Schema::table('inventory_reservations', function (Blueprint $table) {
                    $table->unsignedBigInteger('reference_id')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_reservations') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_reservations MODIFY COLUMN reference_id BIGINT UNSIGNED NOT NULL");
        }
    }
};
