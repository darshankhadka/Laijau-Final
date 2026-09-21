<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_stations')) {
            Schema::table('pos_stations', function (Blueprint $table) {
                $columnsToDrop = [];
                if (Schema::hasColumn('pos_stations', 'auto_open_cash_drawer')) {
                    $columnsToDrop[] = 'auto_open_cash_drawer';
                }
                if (Schema::hasColumn('pos_stations', 'cash_drawer_enabled')) {
                    $columnsToDrop[] = 'cash_drawer_enabled';
                }
                if (Schema::hasColumn('pos_stations', 'cash_drawer_pulse_command')) {
                    $columnsToDrop[] = 'cash_drawer_pulse_command';
                }
                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }

        if (Schema::hasTable('pos_printers')) {
            Schema::table('pos_printers', function (Blueprint $table) {
                if (Schema::hasColumn('pos_printers', 'drawer_kick_enabled')) {
                    $table->dropColumn('drawer_kick_enabled');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_stations')) {
            Schema::table('pos_stations', function (Blueprint $table) {
                if (!Schema::hasColumn('pos_stations', 'auto_open_cash_drawer')) {
                    $table->boolean('auto_open_cash_drawer')->default(false);
                }
                if (!Schema::hasColumn('pos_stations', 'cash_drawer_enabled')) {
                    $table->boolean('cash_drawer_enabled')->default(false);
                }
                if (!Schema::hasColumn('pos_stations', 'cash_drawer_pulse_command')) {
                    $table->string('cash_drawer_pulse_command', 64)->default('ESC p 0 25 250');
                }
            });
        }

        if (Schema::hasTable('pos_printers')) {
            Schema::table('pos_printers', function (Blueprint $table) {
                if (!Schema::hasColumn('pos_printers', 'drawer_kick_enabled')) {
                    $table->boolean('drawer_kick_enabled')->default(false);
                }
            });
        }
    }
};
