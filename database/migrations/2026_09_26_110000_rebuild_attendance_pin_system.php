<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add attendance_pin_lookup_hash to hrm_employees for deterministic O(1) PIN lookup
        if (Schema::hasTable('hrm_employees')) {
            if (!Schema::hasColumn('hrm_employees', 'attendance_pin_lookup_hash')) {
                Schema::table('hrm_employees', function (Blueprint $table) {
                    $table->string('attendance_pin_lookup_hash', 64)->nullable()->index()->after('attendance_pin_hash');
                });
            }
        }

        // 2. Create persistent attendance authentication tokens table
        if (!Schema::hasTable('attendance_auth_tokens')) {
            Schema::create('attendance_auth_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->string('token_hash', 64)->unique()->index();
                $table->string('device_name', 120)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();

                $table->index(['employee_id', 'expires_at']);
            });
        }

        // 3. Disable photo requirement in attendance settings
        if (Schema::hasTable('attendance_settings')) {
            DB::table('attendance_settings')->where('key', 'photo_required')->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendance_auth_tokens')) {
            Schema::dropIfExists('attendance_auth_tokens');
        }

        if (Schema::hasTable('hrm_employees')) {
            if (Schema::hasColumn('hrm_employees', 'attendance_pin_lookup_hash')) {
                Schema::table('hrm_employees', function (Blueprint $table) {
                    $table->dropColumn('attendance_pin_lookup_hash');
                });
            }
        }
    }
};
