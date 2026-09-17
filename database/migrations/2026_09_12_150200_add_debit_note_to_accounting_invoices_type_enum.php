<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Safe, non-destructive enum expansion to support statutory Nepal IRD debit notes
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `accounting_invoices` MODIFY COLUMN `type` ENUM('sales_invoice', 'supplier_bill', 'credit_note', 'debit_note') NOT NULL DEFAULT 'sales_invoice'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `accounting_invoices` MODIFY COLUMN `type` ENUM('sales_invoice', 'supplier_bill', 'credit_note') NOT NULL DEFAULT 'sales_invoice'");
        }
    }
};
