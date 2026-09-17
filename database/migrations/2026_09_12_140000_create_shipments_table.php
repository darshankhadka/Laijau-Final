<?php

declare(strict_types=1);

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
        if (!Schema::hasTable('shipments')) {
            Schema::create('shipments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->string('provider', 50)->default('ncm')->index();
                $table->string('external_tracking_number', 100)->index();
                $table->string('external_reference', 150)->nullable();
                $table->string('source', 50)->default('ncm_csv');
                $table->string('source_file', 255)->nullable();
                $table->dateTime('source_created_at')->nullable()->index();
                $table->string('status', 100)->index();
                $table->string('normalized_status', 50)->index();
                $table->string('source_branch', 100)->nullable();
                $table->string('destination_branch', 100)->nullable()->index();
                $table->string('receiver_name', 150)->nullable();
                $table->string('receiver_phone', 50)->nullable();
                $table->string('normalized_receiver_phone', 30)->nullable()->index();
                $table->decimal('cod_amount', 12, 2)->default(0.00);
                $table->decimal('delivery_charge', 12, 2)->default(0.00);
                $table->text('package_description')->nullable();
                $table->text('remarks')->nullable();
                $table->decimal('weight', 8, 2)->default(1.00);
                $table->dateTime('delivered_at')->nullable()->index();
                $table->boolean('vendor_return')->default(false)->index();
                $table->string('created_by_source', 100)->nullable();
                $table->string('match_status', 50)->default('unmatched')->index();
                $table->string('match_method', 50)->nullable();
                $table->string('match_confidence', 50)->nullable();
                $table->text('match_reason')->nullable();
                $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('matched_at')->nullable();
                $table->json('raw_metadata')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'external_tracking_number'], 'shipments_provider_tracking_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
