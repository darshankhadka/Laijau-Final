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
        // 1. Drop legacy Cost Catalog tables completely
        Schema::dropIfExists('cost_catalog_snapshots');
        Schema::dropIfExists('cost_catalog_price_scenarios');
        Schema::dropIfExists('cost_catalog_cost_items');
        Schema::dropIfExists('cost_catalog_batches');
        Schema::dropIfExists('cost_catalog_products');

        // 2. Chart of Accounts
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number', 10)->unique();
            $table->string('name');
            $table->enum('category', [
                'revenue',
                'cogs',
                'payment_fees',
                'opex',
                'personnel',
                'financial',
                'fixed_assets',
                'inventory',
                'receivables',
                'cash_bank',
                'equity',
                'vat_tax',
                'payables',
            ])->index();
            $table->enum('account_type', ['asset', 'liability', 'equity', 'income', 'expense'])->index();
            $table->enum('normal_balance', ['debit', 'credit'])->default('debit');
            $table->decimal('default_vat_rate', 5, 2)->default(0.00);
            $table->string('currency', 3)->default('npr');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->text('description')->nullable();
            $table->decimal('current_balance', 14, 4)->default(0.0000);
            $table->timestamps();
        });

        // 3. Accounting Periods (Regnskabsperioder)
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('period_type', ['month', 'quarter', 'year'])->default('month');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'locked', 'closed'])->default('open');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. Journal Entries (Vouchers)
        Schema::create('accounting_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number', 30)->unique();
            $table->date('voucher_date');
            $table->foreignId('accounting_period_id')->nullable()->constrained('accounting_periods')->nullOnDelete();
            $table->enum('entry_type', [
                'sales',
                'purchase',
                'bank',
                'settlement',
                'cogs',
                'depreciation',
                'closing',
                'reversal',
                'manual',
            ])->default('manual')->index();
            $table->string('reference_type')->nullable()->index();
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->string('description');
            $table->string('currency', 3)->default('npr');
            $table->decimal('exchange_rate_to_npr', 12, 6)->default(1.000000);
            $table->decimal('total_debit', 14, 4)->default(0.0000);
            $table->decimal('total_credit', 14, 4)->default(0.0000);
            $table->boolean('is_balanced')->default(true);
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('posted')->index();
            $table->foreignId('reversed_by_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->foreignId('reversal_of_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. Journal Entry Lines (Posteringslinjer)
        Schema::create('accounting_journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('accounting_journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounting_accounts');
            $table->string('account_number', 10);
            $table->unsignedInteger('line_number')->default(1);
            $table->string('description')->nullable();
            $table->decimal('debit', 14, 4)->default(0.0000);
            $table->decimal('credit', 14, 4)->default(0.0000);
            $table->string('currency', 3)->default('npr');
            $table->decimal('amount_currency', 14, 4)->default(0.0000);
            $table->string('vat_code')->nullable();
            $table->decimal('vat_rate', 5, 2)->default(0.00);
            $table->decimal('vat_amount', 14, 4)->default(0.0000);
            $table->boolean('is_reconciled')->default(false);
            $table->timestamps();
        });

        // 6. Invoices, Supplier Bills & Credit Notes
        Schema::create('accounting_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();
            $table->enum('type', ['sales_invoice', 'supplier_bill', 'credit_note'])->default('sales_invoice')->index();
            $table->string('contact_name');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_cvr')->nullable();
            $table->text('contact_address')->nullable();
            $table->string('contact_country', 2)->default('NP');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('NPR');
            $table->decimal('exchange_rate_to_npr', 12, 6)->default(1.000000);
            $table->decimal('subtotal', 14, 4)->default(0.0000);
            $table->decimal('vat_amount', 14, 4)->default(0.0000);
            $table->decimal('total_amount', 14, 4)->default(0.0000);
            $table->decimal('paid_amount', 14, 4)->default(0.0000);
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'overdue', 'cancelled'])->default('unpaid')->index();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->foreignId('reference_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('payment_terms')->nullable()->default('Immediate / Net 15');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 7. Invoice Line Items
        Schema::create('accounting_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_invoice_id')->constrained('accounting_invoices')->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price', 14, 4)->default(0.0000);
            $table->decimal('vat_rate', 5, 2)->default(13.00);
            $table->decimal('vat_amount', 14, 4)->default(0.0000);
            $table->decimal('total_amount', 14, 4)->default(0.0000);
            $table->timestamps();
        });

        // 8. Bank Accounts & Liquidity Registers
        Schema::create('accounting_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('bank_name')->default('Nabil Bank / NIMB');
            $table->string('account_number')->nullable();
            $table->string('reg_number', 10)->nullable();
            $table->string('iban')->nullable();
            $table->string('bic_swift')->nullable();
            $table->string('currency', 3)->default('NPR');
            $table->foreignId('ledger_account_id')->constrained('accounting_accounts');
            $table->decimal('opening_balance', 14, 4)->default(0.0000);
            $table->decimal('current_balance', 14, 4)->default(0.0000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 9. Bank Transactions & Reconciliation
        Schema::create('accounting_bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('accounting_bank_accounts')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->decimal('amount', 14, 4);
            $table->string('currency', 3)->default('NPR');
            $table->string('description');
            $table->string('external_reference')->nullable()->index();
            $table->boolean('is_reconciled')->default(false)->index();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->enum('match_type', [
                'direct_voucher',
                'connectips_settlement',
                'pos_card_settlement',
                'supplier_payment',
                'manual',
            ])->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 10. Nepal Statutory VAT Declarations (IRD Anusuchi 10)
        Schema::create('accounting_vat_declarations', function (Blueprint $table) {
            $table->id();
            $table->string('declaration_number', 30)->unique();
            $table->foreignId('period_id')->nullable()->constrained('accounting_periods')->nullOnDelete();
            $table->string('period_name');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('declaration_type', ['nepal_vat_return'])->default('nepal_vat_return');
            $table->decimal('sales_vat_13', 14, 4)->default(0.0000);
            $table->decimal('purchase_vat_13', 14, 4)->default(0.0000);
            $table->decimal('net_vat_payable', 14, 4)->default(0.0000);
            $table->decimal('exempt_sales', 14, 4)->default(0.0000);
            $table->decimal('taxable_sales', 14, 4)->default(0.0000);
            $table->decimal('taxable_purchases', 14, 4)->default(0.0000);
            $table->enum('status', ['draft', 'calculated', 'submitted', 'paid'])->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->date('payment_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_vat_declarations');
        Schema::dropIfExists('accounting_bank_transactions');
        Schema::dropIfExists('accounting_bank_accounts');
        Schema::dropIfExists('accounting_invoice_items');
        Schema::dropIfExists('accounting_invoices');
        Schema::dropIfExists('accounting_journal_entry_lines');
        Schema::dropIfExists('accounting_journal_entries');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounting_accounts');
    }
};
