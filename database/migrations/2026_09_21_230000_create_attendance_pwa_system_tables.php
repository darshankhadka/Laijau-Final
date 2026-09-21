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
        // 1. Additive columns to hrm_employees for Attendance PWA credentials & lockout
        if (Schema::hasTable('hrm_employees')) {
            $employeeCols = [
                'attendance_pin_hash' => fn (Blueprint $table) => $table->string('attendance_pin_hash', 255)->nullable()->after('status'),
                'attendance_pin_set_at' => fn (Blueprint $table) => $table->timestamp('attendance_pin_set_at')->nullable()->after('attendance_pin_hash'),
                'attendance_failed_attempts' => fn (Blueprint $table) => $table->unsignedTinyInteger('attendance_failed_attempts')->default(0)->after('attendance_pin_set_at'),
                'attendance_locked_until' => fn (Blueprint $table) => $table->timestamp('attendance_locked_until')->nullable()->after('attendance_failed_attempts'),
                'attendance_access_enabled' => fn (Blueprint $table) => $table->boolean('attendance_access_enabled')->default(true)->after('attendance_locked_until'),
            ];

            foreach ($employeeCols as $col => $callback) {
                if (!Schema::hasColumn('hrm_employees', $col)) {
                    Schema::table('hrm_employees', $callback);
                }
            }
        }

        // 2. Attendance Locations (Workplaces & Geofencing)
        if (!Schema::hasTable('attendance_locations')) {
            Schema::create('attendance_locations', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('code', 50)->unique();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->unsignedInteger('radius_meters')->default(100);
                $table->string('address', 255)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_default')->default(false)->index();
                $table->timestamps();
            });

            // Seed default Laijau Showroom location (Bohara Tol, Kageshwori Manahara 09, Kathmandu)
            DB::table('attendance_locations')->insert([
                'name' => 'Laijau Showroom (Headquarters)',
                'code' => 'LOC-KTM-SHOWROOM',
                'latitude' => 27.6976748,
                'longitude' => 85.3664331,
                'radius_meters' => 100,
                'address' => 'Bohara Tol, Kageshwori Manahara 09, Kathmandu, Nepal',
                'is_active' => true,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Attendance Devices (Registered Employee Phones/Browsers)
        if (!Schema::hasTable('attendance_devices')) {
            Schema::create('attendance_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->string('device_token_hash', 64)->unique()->index();
                $table->string('device_name', 120)->nullable(); // e.g. "iPhone 15 Pro", "Samsung Galaxy S24"
                $table->string('platform', 60)->nullable(); // e.g. "iOS", "Android", "Windows"
                $table->string('browser', 60)->nullable(); // e.g. "Safari", "Chrome"
                $table->text('user_agent')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('passkey_credential_id', 500)->nullable()->index();
                $table->text('passkey_public_key')->nullable();
                $table->unsignedInteger('passkey_sign_count')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('registered_at')->useCurrent();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('revocation_reason', 255)->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'is_active']);
            });
        }

        // 4. Attendance Events (Check In & Check Out Records)
        if (!Schema::hasTable('attendance_events')) {
            Schema::create('attendance_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('device_id')->nullable()->constrained('attendance_devices')->nullOnDelete();
                $table->foreignId('attendance_location_id')->nullable()->constrained('attendance_locations')->nullOnDelete();
                $table->enum('type', ['check_in', 'check_out'])->index();
                $table->enum('status', ['verified', 'rejected', 'adjusted'])->default('verified')->index();
                $table->timestamp('server_recorded_at')->index(); // Authoritative server timestamp
                $table->timestamp('client_captured_at')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('accuracy_meters', 8, 2)->nullable();
                $table->decimal('distance_from_location_meters', 10, 2)->nullable();
                $table->boolean('geofence_passed')->default(true)->index();
                $table->string('photo_path', 255)->nullable(); // Path on private disk
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('verification_method', 50)->default('pin'); // 'pin', 'passkey', 'manual_adjustment'
                $table->string('idempotency_key', 64)->unique()->nullable()->index();
                $table->string('rejection_reason', 255)->nullable();
                $table->boolean('is_adjusted')->default(false);
                $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('adjusted_at')->nullable();
                $table->text('adjustment_notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'server_recorded_at']);
                $table->index(['employee_id', 'type', 'status']);
            });
        }

        // 5. Attendance Location Updates (Heartbeats while PWA is active)
        if (!Schema::hasTable('attendance_location_updates')) {
            Schema::create('attendance_location_updates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('device_id')->nullable()->constrained('attendance_devices')->nullOnDelete();
                $table->foreignId('attendance_event_id')->nullable()->constrained('attendance_events')->nullOnDelete();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('accuracy_meters', 8, 2)->nullable();
                $table->timestamp('recorded_at')->index();
                $table->timestamps();

                $table->index(['employee_id', 'recorded_at']);
            });
        }

        // 6. Attendance Audit Logs (Immutable audit trail)
        if (!Schema::hasTable('attendance_audit_logs')) {
            Schema::create('attendance_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('employee_id')->nullable()->constrained('hrm_employees')->nullOnDelete();
                $table->string('action', 100)->index(); // 'pin_created', 'pin_reset', 'device_registered', 'device_revoked', 'passkey_registered', 'attendance_adjusted', 'settings_updated', 'geofence_updated'
                $table->string('entity_type', 100)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->json('previous_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('details')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }

        // 7. Attendance Settings
        if (!Schema::hasTable('attendance_settings')) {
            Schema::create('attendance_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 80)->unique()->index();
                $table->text('value')->nullable();
                $table->string('type', 30)->default('string'); // 'boolean', 'integer', 'float', 'string', 'json'
                $table->string('description', 255)->nullable();
                $table->timestamps();
            });

            // Seed default settings
            $defaults = [
                ['key' => 'attendance_enabled', 'value' => '1', 'type' => 'boolean', 'description' => 'Master switch for employee attendance system'],
                ['key' => 'gps_required', 'value' => '1', 'type' => 'boolean', 'description' => 'Require device GPS coordinates for check-in and check-out'],
                ['key' => 'photo_required', 'value' => '1', 'type' => 'boolean', 'description' => 'Require verification photo for check-in and check-out'],
                ['key' => 'geofencing_enabled', 'value' => '1', 'type' => 'boolean', 'description' => 'Enforce workplace geofence radius'],
                ['key' => 'max_gps_accuracy_meters', 'value' => '100', 'type' => 'integer', 'description' => 'Maximum allowable GPS accuracy in meters'],
                ['key' => 'heartbeat_interval_seconds', 'value' => '180', 'type' => 'integer', 'description' => 'Location update heartbeat interval while PWA is active (seconds)'],
                ['key' => 'max_allowed_devices', 'value' => '2', 'type' => 'integer', 'description' => 'Maximum concurrent active devices per employee'],
                ['key' => 'max_failed_attempts', 'value' => '5', 'type' => 'integer', 'description' => 'Maximum failed PIN attempts before temporary lockout'],
                ['key' => 'lockout_minutes', 'value' => '15', 'type' => 'integer', 'description' => 'Temporary lockout duration after consecutive failed attempts'],
                ['key' => 'require_passkey_optional', 'value' => '1', 'type' => 'boolean', 'description' => 'Offer WebAuthn passkey registration on supported devices'],
            ];

            foreach ($defaults as $d) {
                $d['created_at'] = now();
                $d['updated_at'] = now();
                DB::table('attendance_settings')->insert($d);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
        Schema::dropIfExists('attendance_audit_logs');
        Schema::dropIfExists('attendance_location_updates');
        Schema::dropIfExists('attendance_events');
        Schema::dropIfExists('attendance_devices');
        Schema::dropIfExists('attendance_locations');

        if (Schema::hasTable('hrm_employees')) {
            Schema::table('hrm_employees', function (Blueprint $table) {
                $table->dropColumn([
                    'attendance_pin_hash',
                    'attendance_pin_set_at',
                    'attendance_failed_attempts',
                    'attendance_locked_until',
                    'attendance_access_enabled',
                ]);
            });
        }
    }
};
