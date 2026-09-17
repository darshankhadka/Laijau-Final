<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Nepal Finance Hardening: Data-Driven Tax Rules & VAT Configurations.
     */
    public function up(): void
    {
        // 1. Data-Driven TDS Rules by Fiscal Year and Payment Type (Income Tax Act 2058)
        if (!Schema::hasTable('accounting_tds_rules')) {
            Schema::create('accounting_tds_rules', function (Blueprint $table) {
                $table->id();
                $table->string('fiscal_year', 15)->index(); // e.g. 2081/82, 2082/83, 2083/84
                $table->string('payment_type', 40)->index(); // contract_goods, rent, consultancy, transport_freight, salary, interest, commission, audit_fee, other
                $table->string('section', 20)->default('88')->index(); // 87, 88, 88Ka, 89, 90
                $table->decimal('rate', 5, 2)->default(1.50); // e.g. 1.50%, 10.00%, 15.00%
                $table->decimal('rate_without_pan', 5, 2)->nullable(); // Higher rate if payee does not supply a PAN
                $table->decimal('threshold', 14, 4)->default(0.0000); // Minimum amount before TDS triggers (e.g. Rs 50,000 for supply contracts)
                $table->boolean('requires_pan')->default(true);
                $table->text('exemptions')->nullable(); // Statutory descriptions/exemptions
                $table->boolean('is_active')->default(true)->index();
                $table->date('effective_from')->index();
                $table->date('effective_until')->nullable()->index();
                $table->timestamps();
            });
        }

        // 2. Versioned VAT Configurations by Fiscal Year (Nepal VAT Act 2052 / Finance Acts)
        if (!Schema::hasTable('accounting_vat_configurations')) {
            Schema::create('accounting_vat_configurations', function (Blueprint $table) {
                $table->id();
                $table->string('fiscal_year', 15)->unique(); // e.g. 2081/82, 2082/83, 2083/84
                $table->decimal('standard_vat_rate', 5, 2)->default(13.00); // 13.00%
                $table->boolean('taxable_eligible')->default(true);
                $table->boolean('zero_rated_export_eligible')->default(true);
                $table->boolean('exempt_schedule_eligible')->default(true);
                $table->json('input_vat_restricted_categories')->nullable(); // Section 17(2) non-deductible items
                $table->string('filing_frequency', 20)->default('monthly'); // monthly, trimester
                $table->date('effective_from');
                $table->date('effective_until')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 3. Link stock adjustments directly to General Ledger journal entries
        if (Schema::hasTable('inventory_stock_adjustments')) {
            Schema::table('inventory_stock_adjustments', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_stock_adjustments', 'journal_entry_id')) {
                    $table->foreignId('journal_entry_id')->nullable()->after('notes')->constrained('accounting_journal_entries')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('inventory_stock_adjustments')) {
            Schema::table('inventory_stock_adjustments', function (Blueprint $table) {
                if (Schema::hasColumn('inventory_stock_adjustments', 'journal_entry_id')) {
                    $table->dropForeign(['journal_entry_id']);
                    $table->dropColumn('journal_entry_id');
                }
            });
        }

        Schema::dropIfExists('accounting_vat_configurations');
        Schema::dropIfExists('accounting_tds_rules');
    }
};
