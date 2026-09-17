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
            if (!Schema::hasColumn('orders', 'courier_order_id')) {
                $table->string('courier_order_id')->nullable()->index()->after('carrier');
            }
            if (!Schema::hasColumn('orders', 'courier_status')) {
                $table->string('courier_status')->nullable()->after('courier_order_id');
            }
            if (!Schema::hasColumn('orders', 'courier_comments')) {
                $table->text('courier_comments')->nullable()->after('courier_status');
            }
            if (!Schema::hasColumn('orders', 'courier_last_sync_at')) {
                $table->timestamp('courier_last_sync_at')->nullable()->after('courier_comments');
            }
        });

        if (!Schema::hasTable('logistics_events')) {
            Schema::create('logistics_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->string('provider', 50)->index();
                $table->string('external_order_id', 100)->index();
                $table->string('event', 100)->nullable();
                $table->string('status', 100)->nullable();
                $table->json('payload')->nullable();
                $table->string('processing_status', 50)->default('processed')->index();
                $table->string('idempotency_key', 190)->nullable()->unique();
                $table->text('error')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['provider', 'external_order_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logistics_events');

        Schema::table('orders', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('orders', 'courier_last_sync_at')) {
                $columnsToDrop[] = 'courier_last_sync_at';
            }
            if (Schema::hasColumn('orders', 'courier_comments')) {
                $columnsToDrop[] = 'courier_comments';
            }
            if (Schema::hasColumn('orders', 'courier_status')) {
                $columnsToDrop[] = 'courier_status';
            }
            if (Schema::hasColumn('orders', 'courier_order_id')) {
                $columnsToDrop[] = 'courier_order_id';
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
