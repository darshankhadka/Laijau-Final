<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('products')
            ->where('is_published', true)
            ->where('is_active', true)
            ->update(['is_new_arrival' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive; no reverse needed
    }
};
