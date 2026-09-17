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
        if (!Schema::hasTable('sku_sequences')) {
            Schema::create('sku_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('prefix', 32)->unique();
                $table->unsignedBigInteger('next_number')->default(1);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sku_sequences');
    }
};
