<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_stations', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_stations', 'auto_print_pos_sale')) {
                $table->boolean('auto_print_pos_sale')->default(true)->after('pairing_expires_at');
            }
            if (!Schema::hasColumn('pos_stations', 'auto_print_online_order')) {
                $table->boolean('auto_print_online_order')->default(true)->after('auto_print_pos_sale');
            }
            if (!Schema::hasColumn('pos_stations', 'auto_print_packing_slip')) {
                $table->boolean('auto_print_packing_slip')->default(false)->after('auto_print_online_order');
            }
            if (!Schema::hasColumn('pos_stations', 'auto_print_returns')) {
                $table->boolean('auto_print_returns')->default(true)->after('auto_print_packing_slip');
            }
        });

        if (!Schema::hasTable('pos_discovered_printers')) {
            Schema::create('pos_discovered_printers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('station_id')->nullable()->constrained('pos_stations')->nullOnDelete();
                $table->string('name', 120);
                $table->string('connection_type', 30)->default('network');
                $table->string('network_ip', 45)->nullable();
                $table->unsignedInteger('network_port')->default(9100);
                $table->string('usb_device_path', 150)->nullable();
                $table->string('status', 40)->default('available');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->index(['network_ip', 'network_port']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_discovered_printers');

        Schema::table('pos_stations', function (Blueprint $table) {
            $table->dropColumn([
                'auto_print_pos_sale',
                'auto_print_online_order',
                'auto_print_packing_slip',
                'auto_print_returns',
            ]);
        });
    }
};
