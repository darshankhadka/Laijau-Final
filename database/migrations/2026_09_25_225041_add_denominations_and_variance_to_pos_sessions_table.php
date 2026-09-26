<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add denomination count and variance tracking to pos_sessions
        Schema::table('pos_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_sessions', 'denominations')) {
                $table->json('denominations')->nullable()->after('closing_cash_counted');
            }
            if (!Schema::hasColumn('pos_sessions', 'variance_reason_code')) {
                $table->string('variance_reason_code', 64)->nullable()->after('cash_variance');
            }
            if (!Schema::hasColumn('pos_sessions', 'variance_reason_text')) {
                $table->text('variance_reason_text')->nullable()->after('variance_reason_code');
            }
            if (!Schema::hasColumn('pos_sessions', 'cash_sales')) {
                $table->decimal('cash_sales', 12, 2)->default(0.00)->after('total_sales_amount');
            }
            if (!Schema::hasColumn('pos_sessions', 'digital_sales')) {
                $table->decimal('digital_sales', 12, 2)->default(0.00)->after('cash_sales');
            }
            if (!Schema::hasColumn('pos_sessions', 'cash_in')) {
                $table->decimal('cash_in', 12, 2)->default(0.00)->after('digital_sales');
            }
            if (!Schema::hasColumn('pos_sessions', 'cash_out')) {
                $table->decimal('cash_out', 12, 2)->default(0.00)->after('cash_in');
            }
            if (!Schema::hasColumn('pos_sessions', 'change_given')) {
                $table->decimal('change_given', 12, 2)->default(0.00)->after('cash_out');
            }
        });

        // 2. Standardize exactly two POS stations:
        // Terminal 1 -> New Showroom (Wh 43)
        // Terminal 2 -> Old Showroom (Wh 43)
        if (Schema::hasTable('pos_stations')) {
            // Update or create Terminal 1 (ID 1 or first)
            $station1 = DB::table('pos_stations')->where('id', 1)->first()
                ?? DB::table('pos_stations')->orderBy('id', 'asc')->first();

            if ($station1) {
                DB::table('pos_stations')->where('id', $station1->id)->update([
                    'code' => 'pos_terminal_1',
                    'name' => 'Terminal 1',
                    'location' => 'New Showroom',
                    'warehouse_id' => 43,
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('pos_stations')->insert([
                    'id' => 1,
                    'code' => 'pos_terminal_1',
                    'name' => 'Terminal 1',
                    'location' => 'New Showroom',
                    'warehouse_id' => 43,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update or create Terminal 2 (ID 151 or second)
            $station2 = DB::table('pos_stations')->where('id', 151)->first()
                ?? DB::table('pos_stations')->where('id', '!=', 1)->orderBy('id', 'asc')->first();

            if ($station2) {
                DB::table('pos_stations')->where('id', $station2->id)->update([
                    'code' => 'pos_terminal_2',
                    'name' => 'Terminal 2',
                    'location' => 'Old Showroom',
                    'warehouse_id' => 43,
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('pos_stations')->insert([
                    'id' => 151,
                    'code' => 'pos_terminal_2',
                    'name' => 'Terminal 2',
                    'location' => 'Old Showroom',
                    'warehouse_id' => 43,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Deactivate any other stations
            DB::table('pos_stations')
                ->whereNotIn('id', [1, 151])
                ->update(['is_active' => false]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'denominations',
                'variance_reason_code',
                'variance_reason_text',
                'cash_sales',
                'digital_sales',
                'cash_in',
                'cash_out',
                'change_given',
            ]);
        });
    }
};
