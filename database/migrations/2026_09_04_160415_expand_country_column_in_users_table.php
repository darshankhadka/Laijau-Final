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
        if (Schema::hasTable('users')) {
            if (Schema::hasColumn('users', 'country')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('country', 255)->nullable()->default('Nepal')->change();
                });
            } else {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('country', 255)->nullable()->default('Nepal');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'country')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('country', 2)->nullable()->default('NP')->change();
            });
        }
    }
};
