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
        if (!Schema::hasTable('shipping_methods')) {
            Schema::create('shipping_methods', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('carrier')->nullable();
                $table->string('code')->unique();
                $table->string('zone'); // 'kathmandu_valley', 'outside_valley', 'all_nepal'
                $table->json('countries')->nullable();
                $table->decimal('price_npr', 10, 2)->default(0);
                $table->decimal('free_shipping_threshold_npr', 10, 2)->nullable();
                $table->string('estimated_delivery')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
