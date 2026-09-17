<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hrm_payroll_runs')) {
            Schema::table('hrm_payroll_runs', function (Blueprint $table) {
                $table->string('status', 32)->default('draft')->change();
            });
        }
    }

    public function down(): void
    {
        // No-op
    }
};
