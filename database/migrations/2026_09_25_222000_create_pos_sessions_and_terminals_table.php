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
        // 1. Create pos_sessions table
        if (!Schema::hasTable('pos_sessions')) {
            Schema::create('pos_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pos_station_id')->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index(); // Showroom ID
                $table->string('terminal_code', 64)->index();
                $table->string('terminal_name', 128);
                $table->string('showroom_name', 128);
                $table->date('business_date')->index(); // Nepal Date (Asia/Kathmandu)
                $table->string('status', 32)->default('open')->index(); // open, closing_required, closed

                // Opening Details
                $table->decimal('opening_balance', 12, 2)->default(0.00);
                $table->text('opening_notes')->nullable();
                $table->unsignedBigInteger('opened_by_user_id')->nullable()->index();
                $table->string('opened_by_name', 128)->nullable();
                $table->timestamp('opened_at')->nullable();

                // Closing Reconciliation
                $table->decimal('expected_cash', 12, 2)->default(0.00);
                $table->decimal('closing_cash_counted', 12, 2)->nullable();
                $table->decimal('cash_variance', 12, 2)->default(0.00);
                $table->decimal('total_sales_amount', 12, 2)->default(0.00);
                $table->unsignedInteger('total_sales_count')->default(0);
                $table->unsignedInteger('total_units_sold')->default(0);
                $table->decimal('total_discount_amount', 12, 2)->default(0.00);
                $table->decimal('total_void_amount', 12, 2)->default(0.00);
                $table->unsignedInteger('total_void_count')->default(0);
                $table->json('payment_breakdown')->nullable();

                // Closing Metadata
                $table->unsignedBigInteger('closed_by_user_id')->nullable()->index();
                $table->string('closed_by_name', 128)->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->text('closing_notes')->nullable();
                $table->string('manager_name', 128)->nullable();
                $table->timestamp('manager_signed_at')->nullable();

                $table->timestamps();

                $table->unique(['pos_station_id', 'business_date'], 'pos_station_business_date_unique');
            });
        }

        // 2. Add session & terminal references to offline_sales table
        Schema::table('offline_sales', function (Blueprint $table) {
            if (!Schema::hasColumn('offline_sales', 'pos_session_id')) {
                $table->unsignedBigInteger('pos_session_id')->nullable()->after('warehouse_id')->index();
            }
            if (!Schema::hasColumn('offline_sales', 'pos_station_id')) {
                $table->unsignedBigInteger('pos_station_id')->nullable()->after('pos_session_id')->index();
            }
            if (!Schema::hasColumn('offline_sales', 'business_date')) {
                $table->date('business_date')->nullable()->after('pos_station_id')->index();
            }
        });

        // 3. Standardize the two physical POS terminals
        if (Schema::hasTable('pos_stations') && Schema::hasTable('inventory_warehouses')) {
            $showroom1 = DB::table('inventory_warehouses')->where('code', 'STORE-KTM-01')->first()
                ?? DB::table('inventory_warehouses')->where('type', 'showroom_pos')->first();

            $showroom2 = DB::table('inventory_warehouses')->where('code', 'STORE-KTM-02')->first()
                ?? DB::table('inventory_warehouses')->where('type', 'showroom_pos')->orderBy('id', 'desc')->first();

            $wh1Id = $showroom1 ? $showroom1->id : null;
            $wh2Id = $showroom2 ? $showroom2->id : null;

            // Terminal 1 -> Showroom 1
            $firstStation = DB::table('pos_stations')->orderBy('id', 'asc')->first();
            if ($firstStation) {
                DB::table('pos_stations')->where('id', $firstStation->id)->update([
                    'code' => 'pos_terminal_1',
                    'name' => 'POS Terminal 1 (Showroom 1)',
                    'location' => 'Bohara Tol, Kageshwori Manahara 09, Kathmandu (Ground Floor Showroom)',
                    'warehouse_id' => $wh1Id,
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('pos_stations')->insert([
                    'code' => 'pos_terminal_1',
                    'name' => 'POS Terminal 1 (Showroom 1)',
                    'location' => 'Bohara Tol, Kageshwori Manahara 09, Kathmandu (Ground Floor Showroom)',
                    'warehouse_id' => $wh1Id,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Terminal 2 -> Showroom 2
            $station2 = DB::table('pos_stations')->where('code', 'pos_terminal_2')->first();
            if (!$station2) {
                DB::table('pos_stations')->insert([
                    'code' => 'pos_terminal_2',
                    'name' => 'POS Terminal 2 (Showroom 2)',
                    'location' => 'Bohara Tol, Kageshwori Manahara 09, Kathmandu (Showroom 2)',
                    'warehouse_id' => $wh2Id,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('pos_stations')->where('id', $station2->id)->update([
                    'warehouse_id' => $wh2Id,
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offline_sales', function (Blueprint $table) {
            if (Schema::hasColumn('offline_sales', 'business_date')) {
                $table->dropColumn('business_date');
            }
            if (Schema::hasColumn('offline_sales', 'pos_station_id')) {
                $table->dropColumn('pos_station_id');
            }
            if (Schema::hasColumn('offline_sales', 'pos_session_id')) {
                $table->dropColumn('pos_session_id');
            }
        });

        Schema::dropIfExists('pos_sessions');
    }
};
