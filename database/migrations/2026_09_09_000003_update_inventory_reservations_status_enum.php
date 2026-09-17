<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('inventory_reservations') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_reservations MODIFY COLUMN status ENUM('active', 'converted', 'released', 'expired', 'cancelled', 'fulfilled') NOT NULL DEFAULT 'active'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('inventory_reservations') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_reservations MODIFY COLUMN status ENUM('active', 'released', 'fulfilled') NOT NULL DEFAULT 'active'");
        }
    }
};
