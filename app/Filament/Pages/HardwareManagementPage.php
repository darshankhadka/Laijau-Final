<?php

namespace App\Filament\Pages;

use App\Models\Hardware\PosDiscoveredPrinter;
use App\Models\Hardware\PosHardwareAuditLog;
use App\Models\Hardware\PosPrinter;
use App\Models\Hardware\PosStation;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\PosPrintJob;
use App\Services\PrintAgent\PrintAgentService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class HardwareManagementPage extends Page
{
    protected string $view = 'filament.pages.hardware-management';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-printer';
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Hardware & Printing';
    protected static ?int $navigationSort = 30;
    protected static ?string $slug = 'settings/hardware-printing';
    protected static ?string $title = 'Showroom Hardware & Printing';

    public static function getAuthenticatedUser()
    {
        return Filament::auth()->user()
            ?? Auth::guard('admin')->user()
            ?? Auth::guard('web')->user()
            ?? Auth::user();
    }

    public static function canAccess(): bool
    {
        $user = static::getAuthenticatedUser();
        if (!$user) {
            return false;
        }

        return method_exists($user, 'canViewSettings') ? $user->canViewSettings() : (bool) ($user->is_admin ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getHeading(): string | Htmlable
    {
        return 'Showroom Hardware & Printing Control Center';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'One-time configuration for showroom 80mm thermal receipt printing, online order fulfillment slips, and USB barcode scanners. Zero config files required.';
    }

    // Active Navigation Tab: 'overview', 'printer', 'rules', 'queue', 'audit'
    public string $activeTab = 'overview';

    // --------------------------------------------------------------------------
    // Printer Configuration State
    // --------------------------------------------------------------------------
    public ?int $selected_printer_id = null;
    public string $printer_name = 'Epson TM-T82 Thermal 80mm';
    public string $printer_connection_type = 'network'; // 'network' or 'usb'
    public string $printer_network_ip = '192.168.1.200';
    public int $printer_network_port = 9100;
    public string $printer_usb_path = '/dev/usb/lp0';
    public int $printer_paper_width_mm = 80;
    public int $printer_characters_per_line = 48;
    public string $printer_character_encoding = 'PC437';
    public bool $printer_auto_cut = true;
    public int $printer_copies = 1;
    public bool $printer_is_active = true;

    // --------------------------------------------------------------------------
    // Barcode Scanner State
    // --------------------------------------------------------------------------
    public bool $scanner_enabled = true;
    public int $scanner_burst_threshold_ms = 40;
    public int $scanner_min_length = 3;
    public int $scanner_max_length = 64;
    public bool $scanner_ignore_typing = true;
    public bool $scanner_global_listener = true;

    // --------------------------------------------------------------------------
    // Pairing State
    // --------------------------------------------------------------------------
    public bool $showPairingCard = false;
    public ?string $generatedPairingCode = null;
    public ?string $pairingExpiresAt = null;

    public function mount(): void
    {
        $station = $this->getStation();
        if ($station) {
            $this->scanner_enabled = (bool) $station->barcode_scanner_enabled;
            $this->scanner_burst_threshold_ms = (int) ($station->barcode_scan_burst_threshold_ms ?: 40);
            $this->scanner_min_length = (int) ($station->barcode_min_length ?: 3);
            $this->scanner_max_length = (int) ($station->barcode_max_length ?: 64);
            $this->scanner_ignore_typing = (bool) $station->barcode_ignore_keyboard_typing;
            $this->scanner_global_listener = (bool) $station->barcode_global_listener;

            $printer = $station->receiptPrinter ?? PosPrinter::where('role', 'receipt')->first();
            if ($printer) {
                $this->loadPrinterData($printer);
            }
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function getStation(): PosStation
    {
        return PosStation::where('is_active', true)->first()
            ?? PosStation::firstOrCreate(
                ['code' => 'showroom_counter_1'],
                [
                    'name' => 'Showroom Counter 1',
                    'location' => 'Bohara Tol, Kageshwori Manahara 09, Kathmandu',
                    'is_active' => true,
                    'auto_print_receipt' => true,
                    'auto_print_pos_sale' => true,
                    'auto_print_online_order' => true,
                    'auto_print_packing_slip' => false,
                    'auto_print_returns' => true,
                    'barcode_scanner_enabled' => true,
                    'barcode_scan_burst_threshold_ms' => 40,
                ]
            );
    }

    protected function loadPrinterData(PosPrinter $printer): void
    {
        $this->selected_printer_id = $printer->id;
        $this->printer_name = $printer->name;
        $this->printer_connection_type = $printer->connection_type;
        $this->printer_network_ip = (string) ($printer->network_ip ?: '192.168.1.200');
        $this->printer_network_port = (int) ($printer->network_port ?: 9100);
        $this->printer_usb_path = (string) ($printer->usb_device_path ?: '/dev/usb/lp0');
        $this->printer_paper_width_mm = (int) ($printer->paper_width_mm ?: 80);
        $this->printer_characters_per_line = (int) ($printer->characters_per_line ?: 48);
        $this->printer_character_encoding = (string) ($printer->character_encoding ?: 'PC437');
        $this->printer_auto_cut = (bool) $printer->auto_cut_enabled;
        $this->printer_copies = (int) ($printer->copies ?: 1);
        $this->printer_is_active = (bool) $printer->is_active;
    }

    // --------------------------------------------------------------------------
    // One-Time Pairing Setup
    // --------------------------------------------------------------------------
    public function generatePairingCode(): void
    {
        $station = $this->getStation();
        $code = $station->generatePairingCode();

        $this->generatedPairingCode = $code;
        $this->pairingExpiresAt = $station->pairing_expires_at?->format('h:i A');
        $this->showPairingCard = true;

        PosHardwareAuditLog::record(
            'pos_station',
            $station->id,
            $station->name,
            'pairing_code_generated',
            'pairing_code',
            null,
            $code
        );

        Notification::make()
            ->title("Showroom Pairing Code: {$code}")
            ->body("Valid for 10 minutes. Run the one-time pairing command on the showroom PC.")
            ->success()
            ->send();
    }

    public function hidePairingCard(): void
    {
        $this->showPairingCard = false;
    }

    public function rotateCredentials(): void
    {
        $station = $this->getStation();
        $station->rotateCredentials();

        PosHardwareAuditLog::record(
            'pos_station',
            $station->id,
            $station->name,
            'credentials_rotated'
        );

        Notification::make()
            ->title("Showroom Agent Credentials Rotated")
            ->body("Old device token revoked. Use 'Connect Showroom Printer' to re-pair.")
            ->warning()
            ->send();
    }

    // --------------------------------------------------------------------------
    // Automatic Printing Rules (1-Click Toggles)
    // --------------------------------------------------------------------------
    public function togglePrintRule(string $rule): void
    {
        $station = $this->getStation();

        $validRules = ['auto_print_pos_sale', 'auto_print_online_order', 'auto_print_packing_slip', 'auto_print_returns'];
        if (!in_array($rule, $validRules)) {
            return;
        }

        $oldVal = (bool) $station->{$rule};
        $newVal = !$oldVal;
        $station->update([$rule => $newVal]);

        // Keep legacy auto_print_receipt aligned with pos_sale
        if ($rule === 'auto_print_pos_sale') {
            $station->update(['auto_print_receipt' => $newVal]);
        }

        $label = match ($rule) {
            'auto_print_pos_sale' => 'POS Sales Auto-Print',
            'auto_print_online_order' => 'Online Orders Auto-Print',
            'auto_print_packing_slip' => 'Packing Slips Auto-Print',
            'auto_print_returns' => 'Returns Auto-Print',
            default => $rule,
        };

        PosHardwareAuditLog::record(
            'pos_station',
            $station->id,
            $station->name,
            'print_rule_toggled',
            $rule,
            $oldVal ? '1' : '0',
            $newVal ? '1' : '0'
        );

        Notification::make()
            ->title("{$label} " . ($newVal ? 'Enabled' : 'Disabled'))
            ->success()
            ->send();
    }

    // --------------------------------------------------------------------------
    // Printer Configuration Save
    // --------------------------------------------------------------------------
    public function savePrinterSettings(): void
    {
        $this->validate([
            'printer_name' => 'required|string|max:100',
            'printer_connection_type' => 'required|in:network,usb',
            'printer_network_ip' => 'nullable|string|max:45',
            'printer_network_port' => 'required_if:printer_connection_type,network|integer|min:1|max:65535',
            'printer_usb_path' => 'nullable|string|max:150',
            'printer_paper_width_mm' => 'required|in:58,80',
            'printer_characters_per_line' => 'required|integer|min:24|max:80',
        ]);

        $station = $this->getStation();

        $data = [
            'station_id' => $station->id,
            'name' => $this->printer_name,
            'role' => 'receipt',
            'connection_type' => $this->printer_connection_type,
            'network_ip' => $this->printer_connection_type === 'network' ? $this->printer_network_ip : null,
            'network_port' => $this->printer_connection_type === 'network' ? $this->printer_network_port : 9100,
            'usb_device_path' => $this->printer_connection_type === 'usb' ? $this->printer_usb_path : null,
            'paper_width_mm' => $this->printer_paper_width_mm,
            'characters_per_line' => $this->printer_characters_per_line,
            'character_encoding' => $this->printer_character_encoding ?: 'PC437',
            'auto_cut_enabled' => $this->printer_auto_cut,
            'copies' => $this->printer_copies,
            'is_active' => $this->printer_is_active,
        ];

        if ($this->selected_printer_id) {
            $printer = PosPrinter::find($this->selected_printer_id);
            if ($printer) {
                $printer->update($data);
                $station->update(['default_receipt_printer_id' => $printer->id]);
            }
        } else {
            $data['code'] = 'printer_showroom_' . Str::random(4);
            $printer = PosPrinter::create($data);
            $station->update(['default_receipt_printer_id' => $printer->id]);
            $this->selected_printer_id = $printer->id;
        }

        PosHardwareAuditLog::record(
            'pos_printer',
            $printer->id,
            $printer->name,
            'settings_updated',
            null,
            null,
            null,
            $data
        );

        Notification::make()
            ->title("Showroom Receipt Printer Settings Saved")
            ->body("Updated to {$this->printer_name} ({$this->printer_network_ip}:{$this->printer_network_port}). The Local Agent will auto-sync.")
            ->success()
            ->send();
    }

    // --------------------------------------------------------------------------
    // Test Print
    // --------------------------------------------------------------------------
    public function testPrint(): void
    {
        $station = $this->getStation();
        $printer = $station->receiptPrinter ?? PosPrinter::where('role', 'receipt')->first();

        if (!$printer) {
            Notification::make()
                ->title("No Receipt Printer Configured")
                ->body("Please enter your printer IP and click Save Printer Settings first.")
                ->danger()
                ->send();
            return;
        }

        $service = app(PrintAgentService::class);
        $job = $service->createPrinterTestJob($printer);

        $agentStatus = $station->isOnline() ? 'Agent is Online' : 'Agent is currently Offline';

        Notification::make()
            ->title("Test Print Job Enqueued (#{$job->id})")
            ->body("Target: {$printer->name} ({$printer->network_ip}:{$printer->network_port}). {$agentStatus}.")
            ->success()
            ->send();
    }

    // --------------------------------------------------------------------------
    // Scanner Settings Save
    // --------------------------------------------------------------------------
    public function saveScannerSettings(): void
    {
        $station = $this->getStation();
        $station->update([
            'barcode_scanner_enabled' => $this->scanner_enabled,
            'barcode_scan_burst_threshold_ms' => $this->scanner_burst_threshold_ms,
            'barcode_min_length' => $this->scanner_min_length,
            'barcode_max_length' => $this->scanner_max_length,
            'barcode_ignore_keyboard_typing' => $this->scanner_ignore_typing,
            'barcode_global_listener' => $this->scanner_global_listener,
        ]);

        PosHardwareAuditLog::record(
            'pos_station',
            $station->id,
            $station->name,
            'scanner_settings_updated'
        );

        Notification::make()
            ->title("USB Barcode Scanner Settings Saved")
            ->success()
            ->send();
    }

    // --------------------------------------------------------------------------
    // Discovered Printer Adoption
    // --------------------------------------------------------------------------
    public function useDiscoveredPrinter(int $discoveredId): void
    {
        $disc = PosDiscoveredPrinter::findOrFail($discoveredId);

        $this->printer_name = $disc->name;
        $this->printer_connection_type = $disc->connection_type;
        $this->printer_network_ip = (string) $disc->network_ip;
        $this->printer_network_port = (int) ($disc->network_port ?: 9100);
        $this->printer_usb_path = (string) ($disc->usb_device_path ?: '/dev/usb/lp0');

        $this->savePrinterSettings();

        Notification::make()
            ->title("Adopted {$disc->name}")
            ->body("Showroom printer configured to {$this->printer_network_ip}:{$this->printer_network_port}.")
            ->success()
            ->send();
    }

    // --------------------------------------------------------------------------
    // Print Job Queue Actions
    // --------------------------------------------------------------------------
    public function retryJob(int $jobId): void
    {
        $job = PosPrintJob::findOrFail($jobId);
        app(PrintAgentService::class)->retryJob($job);

        Notification::make()->title("Job #{$job->id} Re-queued")->success()->send();
    }

    public function cancelJob(int $jobId): void
    {
        $job = PosPrintJob::findOrFail($jobId);
        app(PrintAgentService::class)->cancelJob($job);

        Notification::make()->title("Job #{$job->id} Cancelled")->warning()->send();
    }

    public function reprintJob(int $jobId): void
    {
        $job = PosPrintJob::findOrFail($jobId);

        if ($job->source_type === 'offline_sale' && $job->source_id) {
            $sale = OfflineSale::find($job->source_id);
            if ($sale) {
                app(PrintAgentService::class)->reprintOfflineSaleReceipt($sale, $job->station_id);
                Notification::make()->title("Reprint Enqueued for Sale #{$sale->sale_number}")->success()->send();
                return;
            }
        } elseif ($job->source_type === 'online_order' && $job->source_id) {
            $order = Order::find($job->source_id);
            if ($order) {
                app(PrintAgentService::class)->queueOnlineOrderReceipt($order, $job->station_id);
                Notification::make()->title("Reprint Enqueued for Online Order #{$order->order_number}")->success()->send();
                return;
            }
        }

        Notification::make()->title("Cannot reprint: original source record not found.")->danger()->send();
    }

    // --------------------------------------------------------------------------
    // View Data Providers
    // --------------------------------------------------------------------------
    public function getHardwareSummary(): array
    {
        return app(PrintAgentService::class)->getSystemHardwareSummary();
    }

    public function getDiscoveredPrinters()
    {
        return PosDiscoveredPrinter::latest('last_seen_at')->limit(10)->get();
    }

    public function getPrintJobs()
    {
        return PosPrintJob::with('printer')
            ->latest('id')
            ->limit(30)
            ->get();
    }

    public function getAuditLogs()
    {
        return PosHardwareAuditLog::latest('id')
            ->limit(30)
            ->get();
    }
}
