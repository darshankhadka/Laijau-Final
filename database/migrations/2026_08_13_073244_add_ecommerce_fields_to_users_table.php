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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->after('password');
            $table->boolean('gdpr_consent')->default(false)->after('role');
            $table->timestamp('gdpr_consented_at')->nullable()->after('gdpr_consent');
            $table->json('saved_measurements')->nullable()->after('gdpr_consented_at');
            $table->dropColumn('is_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
            $table->dropColumn(['role', 'gdpr_consent', 'gdpr_consented_at', 'saved_measurements']);
        });
    }
};
