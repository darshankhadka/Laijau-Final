<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('type')->default('apparel')->nullable()->change();
            $table->json('prices')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Additive safeguard
    }
};
