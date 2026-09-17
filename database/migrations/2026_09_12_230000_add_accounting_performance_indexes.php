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
        Schema::table('accounting_journal_entries', function (Blueprint $table) {
            // Add composite index for date range reporting on posted/reversed entries
            if (!collect(Schema::getIndexes('accounting_journal_entries'))->contains('name', 'idx_entries_status_date')) {
                $table->index(['status', 'voucher_date'], 'idx_entries_status_date');
            }
        });

        Schema::table('accounting_journal_entry_lines', function (Blueprint $table) {
            // Add composite index for account aggregations
            if (!collect(Schema::getIndexes('accounting_journal_entry_lines'))->contains('name', 'idx_lines_acc_debit_credit')) {
                $table->index(['account_id', 'debit', 'credit'], 'idx_lines_acc_debit_credit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_journal_entries', function (Blueprint $table) {
            $table->dropIndex('idx_entries_status_date');
        });

        Schema::table('accounting_journal_entry_lines', function (Blueprint $table) {
            $table->dropIndex('idx_lines_acc_debit_credit');
        });
    }
};
