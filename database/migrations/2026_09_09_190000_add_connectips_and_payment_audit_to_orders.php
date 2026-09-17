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
            if (!Schema::hasColumn('orders', 'connectips_txnid')) {
                $table->string('connectips_txnid', 100)->nullable()->after('payment_id')->index();
            }
            if (!Schema::hasColumn('orders', 'connectips_refid')) {
                $table->string('connectips_refid', 100)->nullable()->after('connectips_txnid');
            }
            if (!Schema::hasColumn('orders', 'payment_rejection_reason')) {
                $table->text('payment_rejection_reason')->nullable()->after('payment_notes');
            }
        });

        if (!Schema::hasTable('payment_audit_logs')) {
            Schema::create('payment_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->string('payment_method', 50);
                $table->string('previous_payment_status', 50)->nullable();
                $table->string('new_payment_status', 50);
                $table->string('transaction_reference', 150)->nullable();
                $table->decimal('amount', 12, 2)->default(0.00);
                $table->string('actor_type', 50)->default('customer'); // customer, admin, system, gateway
                $table->string('actor_id', 100)->nullable();
                $table->json('provider_response')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'created_at']);
                $table->index('transaction_reference');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_audit_logs');

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'payment_rejection_reason')) {
                $table->dropColumn('payment_rejection_reason');
            }
            if (Schema::hasColumn('orders', 'connectips_refid')) {
                $table->dropColumn('connectips_refid');
            }
            if (Schema::hasColumn('orders', 'connectips_txnid')) {
                $table->dropColumn('connectips_txnid');
            }
        });
    }
};
