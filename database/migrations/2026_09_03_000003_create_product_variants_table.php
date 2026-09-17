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
        if (!Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('sku')->unique();
                $table->string('size')->nullable();
                $table->string('color')->nullable();
                $table->string('color_hex')->nullable();
                $table->decimal('price_npr', 10, 2)->nullable();
                $table->integer('stock_quantity')->default(0);
                $table->integer('reserved_quantity')->default(0);
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['product_id', 'size', 'color']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
