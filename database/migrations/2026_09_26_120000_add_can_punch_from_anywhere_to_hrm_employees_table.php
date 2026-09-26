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
        if (Schema::hasTable('hrm_employees')) {
            if (!Schema::hasColumn('hrm_employees', 'can_punch_from_anywhere')) {
                Schema::table('hrm_employees', function (Blueprint $table) {
                    $table->boolean('can_punch_from_anywhere')->default(false)->after('attendance_access_enabled');
                });
            }

            // Explicitly enable for Darshan Jung Khadka
            DB::table('hrm_employees')
                ->where('first_name', 'like', '%Darshan%')
                ->where('last_name', 'like', '%Khadka%')
                ->update(['can_punch_from_anywhere' => true]);

            DB::table('hrm_employees')
                ->where('employee_number', 'LJ-EMP-001')
                ->update(['can_punch_from_anywhere' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('hrm_employees')) {
            if (Schema::hasColumn('hrm_employees', 'can_punch_from_anywhere')) {
                Schema::table('hrm_employees', function (Blueprint $table) {
                    $table->dropColumn('can_punch_from_anywhere');
                });
            }
        }
    }
};
