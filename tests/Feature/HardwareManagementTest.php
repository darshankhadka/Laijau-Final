<?php

namespace Tests\Feature;

use App\Models\Hardware\PosDiscoveredPrinter;
use App\Models\Hardware\PosHardwareAuditLog;
use App\Models\Hardware\PosPrinter;
use App\Models\Hardware\PosStation;
use App\Models\Order;
use App\Models\PosPrintJob;
use App\Models\User;
use App\Services\PrintAgent\PrintAgentService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class HardwareManagementTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up test records
        PosPrintJob::where('station_id', 'like', 'test_station_%')->delete();
        PosDiscoveredPrinter::whereHas('station', function ($q) {
            $q->where('code', 'like', 'test_station_%');
        })->delete();
        PosPrinter::where('code', 'like', 'test_printer_%')->delete();
        PosStation::where('code', 'like', 'test_station_%')->delete();

        parent::tearDown();
    }

    public function test_cash_drawer_completely_removed_from_database_schema(): void
    {
        // Core requirement 1: Remove cash drawer completely
        $this->assertFalse(
            Schema::hasColumn('pos_stations', 'auto_open_cash_drawer'),
            'auto_open_cash_drawer column must NOT exist on pos_stations'
        );
        $this->assertFalse(
            Schema::hasColumn('pos_stations', 'cash_drawer_enabled'),
            'cash_drawer_enabled column must NOT exist on pos_stations'
        );
        $this->assertFalse(
            Schema::hasColumn('pos_stations', 'cash_drawer_pulse_command'),
            'cash_drawer_pulse_command column must NOT exist on pos_stations'
        );
        $this->assertFalse(
            Schema::hasColumn('pos_printers', 'drawer_kick_enabled'),
            'drawer_kick_enabled column must NOT exist on pos_printers'
        );
    }

    public function test_can_create_pos_station_with_hardware_settings_and_print_rules(): void
    {
        $station = PosStation::create([
            'code' => 'test_station_' . Str::random(6),
            'name' => 'Showroom Counter Test',
            'location' => 'Level 1 Front Entrance',
            'is_active' => true,
            'auto_print_receipt' => true,
            'auto_print_pos_sale' => true,
            'auto_print_online_order' => true,
            'auto_print_packing_slip' => false,
            'auto_print_returns' => true,
            'barcode_scanner_enabled' => true,
            'barcode_scan_burst_threshold_ms' => 40,
            'barcode_min_length' => 3,
            'barcode_max_length' => 64,
            'barcode_ignore_keyboard_typing' => true,
            'barcode_global_listener' => true,
        ]);

        $this->assertDatabaseHas('pos_stations', [
            'id' => $station->id,
            'code' => $station->code,
            'name' => 'Showroom Counter Test',
            'auto_print_pos_sale' => 1,
            'auto_print_online_order' => 1,
            'auto_print_packing_slip' => 0,
            'auto_print_returns' => 1,
            'barcode_scanner_enabled' => 1,
        ]);
    }

    public function test_can_create_network_and_usb_printers(): void
    {
        $station = PosStation::create([
            'code' => 'test_station_' . Str::random(6),
            'name' => 'Counter with Printers',
            'is_active' => true,
        ]);

        // 1. LAN Network Printer
        $lanPrinter = PosPrinter::create([
            'station_id' => $station->id,
            'name' => 'Epson LAN 80mm',
            'code' => 'test_printer_' . Str::random(6),
            'role' => 'receipt',
            'connection_type' => 'network',
            'network_ip' => '192.168.1.220',
            'network_port' => 9100,
            'paper_width_mm' => 80,
            'characters_per_line' => 48,
            'auto_cut_enabled' => true,
            'is_active' => true,
        ]);

        // 2. USB Printer
        $usbPrinter = PosPrinter::create([
            'station_id' => $station->id,
            'name' => 'Xprinter USB 58mm',
            'code' => 'test_printer_' . Str::random(6),
            'role' => 'receipt',
            'connection_type' => 'usb',
            'usb_device_path' => '/dev/usb/lp1',
            'paper_width_mm' => 58,
            'characters_per_line' => 32,
            'auto_cut_enabled' => false,
            'is_active' => true,
        ]);

        $this->assertEquals('network', $lanPrinter->connection_type);
        $this->assertEquals('192.168.1.220', $lanPrinter->network_ip);
        $this->assertEquals('usb', $usbPrinter->connection_type);
        $this->assertEquals('/dev/usb/lp1', $usbPrinter->usb_device_path);

        $lanConfig = $lanPrinter->toHardwareConfig();
        $this->assertEquals('network', $lanConfig['type']);
        $this->assertEquals(9100, $lanConfig['network_port']);
    }

    public function test_pairing_code_generation_and_agent_pairing_workflow(): void
    {
        $station = PosStation::create([
            'code' => 'test_station_' . Str::random(6),
            'name' => 'Counter to Pair',
            'is_active' => true,
        ]);

        // Generate 10-minute pairing code
        $code = $station->generatePairingCode();
        $this->assertNotEmpty($code);
        $this->assertStringStartsWith('LJ-', $code);
        $this->assertNotNull($station->pairing_expires_at);
        $this->assertTrue($station->pairing_expires_at->isFuture());

        // Agent calls POST /api/v1/print-agent/pair
        $pairResponse = $this->postJson('/api/v1/print-agent/pair', [
            'pairing_code' => $code,
            'hostname' => 'TEST-SHOWROOM-PC',
            'os' => 'Linux Ubuntu 22.04',
            'agent_version' => '2.0.0',
        ]);

        $pairResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'station_id' => $station->code,
                'station_name' => $station->name,
            ])
            ->assertJsonStructure([
                'device_token',
                'hardware_config' => [
                    'station_id',
                    'station_name',
                    'auto_print_receipt',
                    'barcode_scanner',
                    'printers',
                ],
            ]);

        $station->refresh();
        $this->assertNull($station->pairing_code);
        $this->assertNotNull($station->device_token_hash);
        $this->assertEquals('TEST-SHOWROOM-PC', $station->last_seen_hostname);

        $token = $pairResponse->json('device_token');
        $this->assertTrue($station->verifyToken($token));

        // Expired or invalid code must be rejected
        $badResponse = $this->postJson('/api/v1/print-agent/pair', [
            'pairing_code' => 'INVALID-CODE',
        ]);
        $badResponse->assertStatus(422);
    }

    public function test_agent_authenticated_config_retrieval_and_heartbeat(): void
    {
        $station = PosStation::create([
            'code' => 'test_station_' . Str::random(6),
            'name' => 'Counter Config Test',
            'is_active' => true,
            'barcode_scan_burst_threshold_ms' => 45,
        ]);

        $printer = PosPrinter::create([
            'station_id' => $station->id,
            'name' => 'Counter Thermal',
            'code' => 'test_printer_' . Str::random(6),
            'role' => 'receipt',
            'connection_type' => 'network',
            'network_ip' => '192.168.1.205',
            'network_port' => 9100,
            'paper_width_mm' => 80,
            'characters_per_line' => 48,
            'is_active' => true,
        ]);

        $station->update(['default_receipt_printer_id' => $printer->id]);
        $token = $station->rotateCredentials();

        // 1. Test GET /api/v1/print-agent/config
        $configResponse = $this->withHeader('X-Print-Agent-Key', $token)
            ->getJson("/api/v1/print-agent/config?station_id={$station->code}");

        $configResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'station_id' => $station->code,
                'hardware_config' => [
                    'station_id' => $station->code,
                    'station_name' => 'Counter Config Test',
                    'barcode_scanner' => [
                        'burst_threshold_ms' => 45,
                    ],
                    'default_receipt_printer' => [
                        'name' => 'Counter Thermal',
                        'type' => 'network',
                        'network_ip' => '192.168.1.205',
                        'network_port' => 9100,
                    ],
                ],
            ]);

        // 2. Test POST /api/v1/print-agent/heartbeat
        $heartbeatResponse = $this->withHeader('X-Print-Agent-Key', $token)
            ->postJson('/api/v1/print-agent/heartbeat', [
                'station_id' => $station->code,
                'hostname' => 'LIVE-SHOWROOM-DESK',
                'os' => 'Windows 11 Pro',
                'agent_version' => '2.0.0',
            ]);

        $heartbeatResponse->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'station_id' => $station->code,
            ]);

        $station->refresh();
        $this->assertTrue($station->isOnline());
        $this->assertEquals('LIVE-SHOWROOM-DESK', $station->last_seen_hostname);
    }

    public function test_printer_test_job_generation_and_hardware_spec_transmission(): void
    {
        $station = PosStation::create([
            'code' => 'test_station_' . Str::random(6),
            'name' => 'Test Hardware Station',
            'is_active' => true,
        ]);

        $printer = PosPrinter::create([
            'station_id' => $station->id,
            'name' => 'Epson Test TM',
            'code' => 'test_printer_' . Str::random(6),
            'role' => 'receipt',
            'connection_type' => 'network',
            'network_ip' => '192.168.1.200',
            'network_port' => 9100,
            'paper_width_mm' => 80,
            'characters_per_line' => 48,
            'is_active' => true,
        ]);

        $station->update(['default_receipt_printer_id' => $printer->id]);
        $service = app(PrintAgentService::class);

        // Enqueue Printer Test
        $job = $service->createPrinterTestJob($printer);
        $this->assertInstanceOf(PosPrintJob::class, $job);
        $this->assertEquals('printer_test', $job->job_type);
        $this->assertEquals($station->code, $job->station_id);
        $this->assertEquals($printer->id, $job->printer_id);
        $this->assertNotEmpty($job->raw_escpos_base64);

        // Agent polls and claims job
        $token = $station->rotateCredentials();
        $pollResponse = $this->withHeader('X-Print-Agent-Key', $token)
            ->getJson("/api/v1/print-agent/jobs/poll?station_id={$station->code}");

        $pollResponse->assertStatus(200)
            ->assertJson([
                'has_job' => true,
                'job' => [
                    'uuid' => $job->uuid,
                    'printer' => [
                        'type' => 'network',
                        'network_ip' => '192.168.1.200',
                        'network_port' => 9100,
                    ],
                ],
            ]);

        // Agent reports successful test print
        $statusResponse = $this->withHeader('X-Print-Agent-Key', $token)
            ->postJson("/api/v1/print-agent/jobs/{$job->uuid}/status", [
                'status' => 'printed',
            ]);

        $statusResponse->assertStatus(200);

        $printer->refresh();
        $this->assertEquals('success', $printer->last_test_status);
        $this->assertNotNull($printer->last_test_printed_at);
    }

    public function test_online_order_automatically_creates_print_job_with_statutory_disclaimer(): void
    {
        $station = PosStation::firstOrCreate(
            ['code' => 'showroom_counter_1'],
            ['name' => 'Showroom Counter 1', 'is_active' => true, 'auto_print_online_order' => true]
        );

        $order = Order::latest()->first();
        $this->assertNotNull($order, 'An order record should exist');

        $service = app(PrintAgentService::class);
        $job = $service->queueOnlineOrderReceipt($order);

        $this->assertInstanceOf(PosPrintJob::class, $job);
        $this->assertEquals('online_order', $job->job_type);
        $this->assertEquals("online_order_{$order->id}_receipt", $job->idempotency_key);

        // Verify ESC/POS content has the statutory disclaimer
        $decoded = base64_decode($job->raw_escpos_base64);
        $this->assertStringContainsString('THIS IS NOT A TAX INVOICE', $decoded);
        $this->assertStringContainsString('ONLINE ORDER FULFILLMENT', $decoded);
        $this->assertStringContainsString((string) $order->order_number, $decoded);

        // Idempotency: second call must return the exact same job, not create a duplicate
        $duplicateJob = $service->queueOnlineOrderReceipt($order);
        $this->assertEquals($job->id, $duplicateJob->id);
        $this->assertEquals($job->uuid, $duplicateJob->uuid);

        // Clean up test job
        $job->delete();
    }

    public function test_agent_reports_discovered_printers_and_stores_them(): void
    {
        $station = PosStation::create([
            'code' => 'test_station_' . Str::random(6),
            'name' => 'Discovery Counter',
            'is_active' => true,
        ]);

        $token = $station->rotateCredentials();

        $response = $this->withHeader('X-Print-Agent-Key', $token)
            ->postJson('/api/v1/print-agent/discovered-printers', [
                'station_id' => $station->code,
                'printers' => [
                    [
                        'name' => 'Discovered Epson TM-T82',
                        'connection_type' => 'network',
                        'network_ip' => '192.168.1.199',
                        'network_port' => 9100,
                        'status' => 'available',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'station_id' => $station->code,
            ]);

        $this->assertDatabaseHas('pos_discovered_printers', [
            'station_id' => $station->id,
            'network_ip' => '192.168.1.199',
            'network_port' => 9100,
        ]);
    }

    public function test_hardware_audit_log_records_changes(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            ['name' => 'Admin Cashier', 'password' => bcrypt('Laijau2026!'), 'role' => 'admin']
        );

        $this->actingAs($admin);

        $log = PosHardwareAuditLog::record(
            'pos_printer',
            999,
            'Test Audit Printer',
            'ip_changed',
            'network_ip',
            '192.168.1.100',
            '192.168.1.200',
            ['reason' => 'DHCP reservation changed']
        );

        $this->assertDatabaseHas('pos_hardware_audit_logs', [
            'id' => $log->id,
            'entity_type' => 'pos_printer',
            'action' => 'ip_changed',
            'old_value' => '192.168.1.100',
            'new_value' => '192.168.1.200',
        ]);
    }

    public function test_admin_can_access_hardware_printing_page(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            ['name' => 'Admin Cashier', 'password' => bcrypt('Laijau2026!'), 'role' => 'admin']
        );

        $response = $this->actingAs($admin, 'admin')->get('/intadmin/settings/hardware-printing');
        $response->assertStatus(200);
        $response->assertSee('Hardware &amp; Printing', false);
        $response->assertSee('Receipt Printer', false);
    }

    public function test_admin_can_interactively_edit_hardware_settings_in_filament(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            ['name' => 'Admin Cashier', 'password' => bcrypt('Laijau2026!'), 'role' => 'admin']
        );

        $station = PosStation::firstOrCreate(
            ['code' => 'showroom_counter_1'],
            ['name' => 'Showroom Counter 1', 'is_active' => true]
        );

        \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Filament\Pages\HardwareManagementPage::class)
            ->set('printer_name', 'Showroom Epson TM-T82')
            ->set('printer_network_ip', '192.168.1.215')
            ->set('printer_network_port', 9100)
            ->call('savePrinterSettings')
            ->assertHasNoErrors()
            ->call('togglePrintRule', 'auto_print_online_order')
            ->call('generatePairingCode')
            ->assertSet('showPairingCard', true);

        $this->assertDatabaseHas('pos_printers', [
            'network_ip' => '192.168.1.215',
            'network_port' => 9100,
        ]);
    }
}
