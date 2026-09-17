<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'variants')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('variants');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('products', 'variants')) {
            Schema::table('products', function (Blueprint $table) {
                $table->json('variants')->nullable();
            });
        }
    }
};
