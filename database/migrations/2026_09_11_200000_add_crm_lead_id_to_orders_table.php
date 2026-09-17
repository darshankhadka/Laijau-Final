<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'crm_lead_id')) {
                $table->foreignId('crm_lead_id')->nullable()->after('user_id')->constrained('crm_leads')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'crm_lead_id')) {
                $table->dropForeign(['crm_lead_id']);
                $table->dropColumn('crm_lead_id');
            }
        });
    }
};
