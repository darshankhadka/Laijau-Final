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
        Schema::create('pos_print_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('station_id', 64)->default('showroom_counter_1')->index();
            $table->string('source_type', 32)->default('offline_sale')->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('idempotency_key', 128)->unique();
            $table->string('job_type', 48)->default('thermal_receipt_80mm');
            $table->enum('status', ['queued', 'printing', 'printed', 'failed', 'cancelled'])->default('queued')->index();
            $table->json('payload_data')->nullable();
            $table->longText('raw_escpos_base64')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->text('last_error')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->index(['station_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_print_jobs');
    }
};
