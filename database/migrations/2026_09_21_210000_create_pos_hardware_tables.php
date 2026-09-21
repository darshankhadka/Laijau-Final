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
        // 1. POS Physical Stations
        Schema::create('pos_stations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->string('location', 255)->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();

            // Security & Pairing Credentials
            $table->string('device_token_hash', 64)->nullable()->index();
            $table->string('device_token_preview', 32)->nullable();
            $table->string('pairing_code', 32)->nullable()->unique();
            $table->timestamp('pairing_expires_at')->nullable();

            // POS Hardware Automation Features
            $table->boolean('auto_print_receipt')->default(true);
            $table->boolean('auto_open_cash_drawer')->default(true);
            $table->boolean('cash_drawer_enabled')->default(true);
            $table->string('cash_drawer_pulse_command', 64)->default('ESC p 0 25 250');

            // Barcode Scanner Configuration (Keyboard/HID standard)
            $table->boolean('barcode_scanner_enabled')->default(true);
            $table->unsignedSmallInteger('barcode_scan_burst_threshold_ms')->default(120);
            $table->unsignedSmallInteger('barcode_min_length')->default(2);
            $table->unsignedSmallInteger('barcode_max_length')->default(64);
            $table->boolean('barcode_ignore_keyboard_typing')->default(true);
            $table->boolean('barcode_global_listener')->default(true);

            // Default Printer Assignments (foreign keys set after pos_printers table creation)
            $table->unsignedBigInteger('default_receipt_printer_id')->nullable()->index();
            $table->unsignedBigInteger('default_label_printer_id')->nullable()->index();

            // Heartbeat & Telemetry from Showroom Local Print Agent
            $table->timestamp('last_heartbeat_at')->nullable()->index();
            $table->string('last_seen_hostname', 128)->nullable();
            $table->string('last_seen_os', 64)->nullable();
            $table->string('last_seen_agent_version', 32)->nullable();
            $table->timestamp('last_successful_print_at')->nullable();
            $table->timestamp('last_failed_print_at')->nullable();
            $table->text('last_error_message')->nullable();

            $table->timestamps();
        });

        // 2. POS Printers (LAN Network & USB)
        Schema::create('pos_printers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('station_id')->index();
            $table->string('name', 128);
            $table->string('code', 64)->nullable()->index();
            $table->enum('role', ['receipt', 'label', 'kitchen'])->default('receipt')->index();
            $table->enum('connection_type', ['network', 'usb', 'system'])->default('network')->index();

            // Network Configuration
            $table->string('network_ip', 64)->nullable();
            $table->unsignedSmallInteger('network_port')->default(9100);

            // USB Configuration
            $table->string('usb_device_path', 128)->nullable()->default('/dev/usb/lp0');

            // Print Specifications
            $table->unsignedSmallInteger('paper_width_mm')->default(80);
            $table->unsignedSmallInteger('characters_per_line')->default(48);
            $table->string('character_encoding', 32)->default('PC437');
            $table->boolean('auto_cut_enabled')->default(true);
            $table->boolean('drawer_kick_enabled')->default(true);
            $table->unsignedTinyInteger('copies')->default(1);
            $table->boolean('is_active')->default(true)->index();

            // Diagnostic & Health Monitoring
            $table->timestamp('last_test_printed_at')->nullable();
            $table->string('last_test_status', 32)->nullable();
            $table->text('last_test_error')->nullable();

            $table->timestamps();

            $table->foreign('station_id')->references('id')->on('pos_stations')->onDelete('cascade');
        });

        // Add foreign keys back to pos_stations for default printer references
        Schema::table('pos_stations', function (Blueprint $table) {
            $table->foreign('default_receipt_printer_id')->references('id')->on('pos_printers')->onDelete('set null');
            $table->foreign('default_label_printer_id')->references('id')->on('pos_printers')->onDelete('set null');
        });

        // 3. POS Hardware Audit Logs
        Schema::create('pos_hardware_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 128)->nullable();
            $table->string('entity_type', 32)->index(); // 'station', 'printer', 'agent'
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('entity_name', 128)->nullable();
            $table->string('action', 48)->index(); // 'created', 'updated', 'paired', 'tested_printer', etc.
            $table->string('field_name', 64)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        // 4. Update pos_print_jobs with optional printer_id and claimed_at
        if (Schema::hasTable('pos_print_jobs')) {
            Schema::table('pos_print_jobs', function (Blueprint $table) {
                if (!Schema::hasColumn('pos_print_jobs', 'printer_id')) {
                    $table->unsignedBigInteger('printer_id')->nullable()->after('station_id')->index();
                }
                if (!Schema::hasColumn('pos_print_jobs', 'claimed_at')) {
                    $table->timestamp('claimed_at')->nullable()->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pos_print_jobs')) {
            Schema::table('pos_print_jobs', function (Blueprint $table) {
                if (Schema::hasColumn('pos_print_jobs', 'printer_id')) {
                    $table->dropColumn('printer_id');
                }
                if (Schema::hasColumn('pos_print_jobs', 'claimed_at')) {
                    $table->dropColumn('claimed_at');
                }
            });
        }

        Schema::table('pos_stations', function (Blueprint $table) {
            $table->dropForeign(['default_receipt_printer_id']);
            $table->dropForeign(['default_label_printer_id']);
        });

        Schema::dropIfExists('pos_hardware_audit_logs');
        Schema::dropIfExists('pos_printers');
        Schema::dropIfExists('pos_stations');
    }
};
