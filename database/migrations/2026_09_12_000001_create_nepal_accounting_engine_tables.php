<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Nepal-first Accounting & Finance Engine.
     */
    public function up(): void
    {
        // 1. Nepali Fiscal Years (Bikram Sambat cycle: Shrawan 1 to Ashadh 32)
        if (!Schema::hasTable('accounting_fiscal_years')) {
            Schema::create('accounting_fiscal_years', function (Blueprint $table) {
                $table->id();
                $table->string('fiscal_year', 15)->unique(); // e.g. 2081/82, 2082/83, 2083/84
                $table->date('start_date');                  // Gregorian equivalent e.g. 2025-07-16
                $table->date('end_date');                    // Gregorian equivalent e.g. 2026-07-15
                $table->boolean('is_current')->default(false)->index();
                $table->enum('status', ['open', 'locked', 'closed'])->default('open')->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 2. Extend Accounting Periods with Nepali Month attributes
        Schema::table('accounting_periods', function (Blueprint $table) {
            if (!Schema::hasColumn('accounting_periods', 'fiscal_year')) {
                $table->string('fiscal_year', 15)->nullable()->index()->after('name');
            }
            if (!Schema::hasColumn('accounting_periods', 'nepali_month')) {
                $table->unsignedTinyInteger('nepali_month')->nullable()->index()->after('fiscal_year'); // 1 = Shrawan .. 12 = Ashadh
            }
            if (!Schema::hasColumn('accounting_periods', 'nepali_label')) {
                $table->string('nepali_label', 50)->nullable()->after('nepali_month'); // e.g. "2083-Bhadra"
            }
        });

        // 3. Extend Invoices & Bills for statutory Bikri Khata & Kharid Khata attributes
        Schema::table('accounting_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('accounting_invoices', 'fiscal_year')) {
                $table->string('fiscal_year', 15)->nullable()->index()->after('type');
            }
            if (!Schema::hasColumn('accounting_invoices', 'tax_period_id')) {
                $table->foreignId('tax_period_id')->nullable()->after('fiscal_year')->constrained('accounting_periods')->nullOnDelete();
            }
            if (!Schema::hasColumn('accounting_invoices', 'buyer_pan')) {
                $table->string('buyer_pan', 20)->nullable()->index()->after('contact_cvr');
            }
            if (!Schema::hasColumn('accounting_invoices', 'seller_pan')) {
                $table->string('seller_pan', 20)->nullable()->index()->after('buyer_pan');
            }
            if (!Schema::hasColumn('accounting_invoices', 'customer_type')) {
                $table->string('customer_type', 30)->default('b2c_retail')->index()->after('seller_pan');
            }
            if (!Schema::hasColumn('accounting_invoices', 'purchase_type')) {
                $table->string('purchase_type', 35)->nullable()->index()->after('customer_type');
            }
            if (!Schema::hasColumn('accounting_invoices', 'sales_channel')) {
                $table->string('sales_channel', 30)->default('web_storefront')->index()->after('purchase_type');
            }
            if (!Schema::hasColumn('accounting_invoices', 'taxable_amount')) {
                $table->decimal('taxable_amount', 14, 4)->default(0.0000)->after('subtotal');
            }
            if (!Schema::hasColumn('accounting_invoices', 'exempt_amount')) {
                $table->decimal('exempt_amount', 14, 4)->default(0.0000)->after('taxable_amount');
            }
            if (!Schema::hasColumn('accounting_invoices', 'export_amount')) {
                $table->decimal('export_amount', 14, 4)->default(0.0000)->after('exempt_amount');
            }
            if (!Schema::hasColumn('accounting_invoices', 'discount_amount')) {
                $table->decimal('discount_amount', 14, 4)->default(0.0000)->after('export_amount');
            }
            if (!Schema::hasColumn('accounting_invoices', 'is_credit')) {
                $table->boolean('is_credit')->default(false)->index()->after('payment_status');
            }
            if (!Schema::hasColumn('accounting_invoices', 'reference_offline_sale_id')) {
                $table->foreignId('reference_offline_sale_id')->nullable()->after('reference_order_id')->constrained('offline_sales')->nullOnDelete();
            }
            if (!Schema::hasColumn('accounting_invoices', 'reference_purchase_order_id')) {
                $table->foreignId('reference_purchase_order_id')->nullable()->after('reference_offline_sale_id')->constrained('inventory_purchase_orders')->nullOnDelete();
            }
            if (!Schema::hasColumn('accounting_invoices', 'original_invoice_id')) {
                $table->foreignId('original_invoice_id')->nullable()->after('reference_purchase_order_id')->constrained('accounting_invoices')->nullOnDelete();
            }
            if (!Schema::hasColumn('accounting_invoices', 'tds_applicable')) {
                $table->boolean('tds_applicable')->default(false)->after('original_invoice_id');
            }
            if (!Schema::hasColumn('accounting_invoices', 'tds_rate')) {
                $table->decimal('tds_rate', 5, 2)->default(0.00)->after('tds_applicable');
            }
            if (!Schema::hasColumn('accounting_invoices', 'tds_amount')) {
                $table->decimal('tds_amount', 14, 4)->default(0.0000)->after('tds_rate');
            }
            if (!Schema::hasColumn('accounting_invoices', 'supporting_document_path')) {
                $table->string('supporting_document_path', 255)->nullable()->after('tds_amount');
            }
            if (!Schema::hasColumn('accounting_invoices', 'posted_to_gl')) {
                $table->boolean('posted_to_gl')->default(false)->index()->after('supporting_document_path');
            }
            if (!Schema::hasColumn('accounting_invoices', 'branch')) {
                $table->string('branch', 100)->default('Durbar Marg, Kathmandu')->after('posted_to_gl');
            }
            if (!Schema::hasColumn('accounting_invoices', 'salesperson_id')) {
                $table->foreignId('salesperson_id')->nullable()->after('branch')->constrained('users')->nullOnDelete();
            }
        });

        // 4. Tax Withholding Subsystem (TDS Register)
        if (!Schema::hasTable('accounting_tds_records')) {
            Schema::create('accounting_tds_records', function (Blueprint $table) {
                $table->id();
                $table->string('tds_number', 30)->unique();
                $table->string('fiscal_year', 15)->index();
                $table->string('payee_name');
                $table->string('payee_pan', 20)->nullable()->index();
                $table->string('payment_type', 40)->default('contract_goods')->index(); // rent, consultancy, contract_goods, transport, salary, etc.
                $table->decimal('gross_amount', 14, 4)->default(0.0000);
                $table->decimal('tds_rate', 5, 2)->default(1.50);
                $table->decimal('tds_amount', 14, 4)->default(0.0000);
                $table->date('transaction_date')->index();
                $table->enum('deposit_status', ['pending', 'deposited', 'filed'])->default('pending')->index();
                $table->string('ird_voucher_no', 50)->nullable();
                $table->string('ird_challan_no', 50)->nullable();
                $table->date('deposited_at')->nullable();
                $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
                $table->nullableMorphs('reference'); // Morph to ExpenseClaim, AccountingInvoice, etc.
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 5. COD Courier Settlements (Pathao / NCM Remittance Register)
        if (!Schema::hasTable('accounting_cod_settlements')) {
            Schema::create('accounting_cod_settlements', function (Blueprint $table) {
                $table->id();
                $table->string('settlement_number', 30)->unique();
                $table->string('courier_name', 50)->index(); // Pathao, NCM, Nepal Post
                $table->date('settlement_date')->index();
                $table->string('settlement_reference', 100)->nullable(); // Courier payout batch # / statement ref
                $table->decimal('total_order_amount', 14, 4)->default(0.0000);
                $table->decimal('courier_fee', 14, 4)->default(0.0000);
                $table->decimal('net_bank_deposited', 14, 4)->default(0.0000);
                $table->foreignId('bank_account_id')->nullable()->constrained('accounting_bank_accounts')->nullOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
                $table->enum('status', ['draft', 'verified', 'posted'])->default('draft')->index();
                $table->json('reconciled_order_ids')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_cod_settlements');
        Schema::dropIfExists('accounting_tds_records');

        Schema::table('accounting_invoices', function (Blueprint $table) {
            $cols = [
                'fiscal_year', 'tax_period_id', 'buyer_pan', 'seller_pan',
                'customer_type', 'purchase_type', 'sales_channel',
                'taxable_amount', 'exempt_amount', 'export_amount',
                'discount_amount', 'is_credit', 'reference_offline_sale_id',
                'reference_purchase_order_id', 'original_invoice_id',
                'tds_applicable', 'tds_rate', 'tds_amount',
                'supporting_document_path', 'posted_to_gl', 'branch', 'salesperson_id',
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('accounting_invoices', $c)) {
                    $table->dropColumn($c);
                }
            }
        });

        Schema::table('accounting_periods', function (Blueprint $table) {
            $cols = ['fiscal_year', 'nepali_month', 'nepali_label'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('accounting_periods', $c)) {
                    $table->dropColumn($c);
                }
            }
        });

        Schema::dropIfExists('accounting_fiscal_years');
    }
};
