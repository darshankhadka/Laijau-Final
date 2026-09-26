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
        if (Schema::hasTable('pos_sessions')) {
            // Check if pos_station_business_date_unique index exists before dropping
            $indices = collect(DB::select("SHOW INDEX FROM pos_sessions"))->pluck('Key_name')->all();

            Schema::table('pos_sessions', function (Blueprint $table) use ($indices) {
                if (in_array('pos_station_business_date_unique', $indices)) {
                    $table->dropUnique('pos_station_business_date_unique');
                }
                if (!in_array('pos_sessions_station_date_index', $indices)) {
                    $table->index(['pos_station_id', 'business_date'], 'pos_sessions_station_date_index');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pos_sessions')) {
            $indices = collect(DB::select("SHOW INDEX FROM pos_sessions"))->pluck('Key_name')->all();

            Schema::table('pos_sessions', function (Blueprint $table) use ($indices) {
                if (in_array('pos_sessions_station_date_index', $indices)) {
                    $table->dropIndex('pos_sessions_station_date_index');
                }
                if (!in_array('pos_station_business_date_unique', $indices)) {
                    $table->unique(['pos_station_id', 'business_date'], 'pos_station_business_date_unique');
                }
            });
        }
    }
};
