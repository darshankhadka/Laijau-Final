<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to modernize HRM enum columns to flexible strings.
     */
    public function up(): void
    {
        if (Schema::hasTable('hrm_leave_requests')) {
            Schema::table('hrm_leave_requests', function (Blueprint $table) {
                $table->string('leave_type', 50)->default('annual')->change();
            });
        }

        if (Schema::hasTable('hrm_employment_contracts')) {
            Schema::table('hrm_employment_contracts', function (Blueprint $table) {
                $table->string('contract_type', 50)->default('full_time')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
