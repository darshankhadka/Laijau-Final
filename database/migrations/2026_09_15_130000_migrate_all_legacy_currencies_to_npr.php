<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations to finalize NPR currency across all system tables.
     */
    public function up(): void
    {
        // Standardize currency column defaults to 'NPR' and ensure NPR values
        $tablesWithCurrency = [
            'accounting_accounts',
            'accounting_bank_accounts',
            'accounting_bank_transactions',
            'accounting_journal_entries',
            'accounting_journal_entry_lines',
            'crm_leads',
            'inventory_purchase_orders',
            'inventory_stock_movements',
            'inventory_suppliers',
            'offline_sales',
            'orders',
        ];

        foreach ($tablesWithCurrency as $tName) {
            if (Schema::hasTable($tName) && Schema::hasColumn($tName, 'currency')) {
                try {
                    Schema::table($tName, function (Blueprint $table) {
                        $table->string('currency', 3)->default('NPR')->change();
                    });
                    DB::table($tName)->where('currency', '!=', 'NPR')->update(['currency' => 'NPR']);
                } catch (\Throwable $e) {
                    // Ignore driver-specific change limitations if any
                }
            }
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
