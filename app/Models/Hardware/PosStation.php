<?php

namespace App\Models\Hardware;

use App\Models\Inventory\Warehouse;
use App\Models\PosPrintJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PosStation extends Model
{
    use HasFactory;

    protected $table = 'pos_stations';

    protected $fillable = [
        'code',
        'name',
        'location',
        'warehouse_id',
        'is_active',
        'device_token_hash',
        'device_token_preview',
        'pairing_code',
        'pairing_expires_at',
        'auto_print_receipt',
        'auto_print_pos_sale',
        'auto_print_online_order',
        'auto_print_packing_slip',
        'auto_print_returns',
        'barcode_scanner_enabled',
        'barcode_scan_burst_threshold_ms',
        'barcode_min_length',
        'barcode_max_length',
        'barcode_ignore_keyboard_typing',
        'barcode_global_listener',
        'default_receipt_printer_id',
        'default_label_printer_id',
        'last_heartbeat_at',
        'last_seen_hostname',
        'last_seen_os',
        'last_seen_agent_version',
        'last_successful_print_at',
        'last_failed_print_at',
        'last_error_message',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_print_receipt' => 'boolean',
        'auto_print_pos_sale' => 'boolean',
        'auto_print_online_order' => 'boolean',
        'auto_print_packing_slip' => 'boolean',
        'auto_print_returns' => 'boolean',
        'barcode_scanner_enabled' => 'boolean',
        'barcode_ignore_keyboard_typing' => 'boolean',
        'barcode_global_listener' => 'boolean',
        'barcode_scan_burst_threshold_ms' => 'integer',
        'barcode_min_length' => 'integer',
        'barcode_max_length' => 'integer',
        'pairing_expires_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'last_successful_print_at' => 'datetime',
        'last_failed_print_at' => 'datetime',
    ];


    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function printers(): HasMany
    {
        return $this->hasMany(PosPrinter::class, 'station_id');
    }

    public function receiptPrinter(): BelongsTo
    {
        return $this->belongsTo(PosPrinter::class, 'default_receipt_printer_id');
    }

    public function labelPrinter(): BelongsTo
    {
        return $this->belongsTo(PosPrinter::class, 'default_label_printer_id');
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PosPrintJob::class, 'station_id', 'code');
    }

    public function discoveredPrinters(): HasMany
    {
        return $this->hasMany(PosDiscoveredPrinter::class, 'station_id');
    }


    /**
     * Check if agent is currently online (heartbeat within last 90 seconds).
     */
    public function isOnline(): bool
    {
        if (!$this->last_heartbeat_at) {
            return false;
        }

        return $this->last_heartbeat_at->diffInSeconds(now()) <= 90;
    }

    /**
     * Human-readable connection status string.
     */
    public function getConnectionStatus(): string
    {
        if (!$this->device_token_hash && !$this->last_heartbeat_at) {
            return 'Never Connected';
        }

        if ($this->isOnline()) {
            return 'Online';
        }

        return 'Offline';
    }

    /**
     * Generate an admin-friendly 10-minute pairing code (e.g. LJ-8K4P-29QX).
     */
    public function generatePairingCode(): string
    {
        $code = 'LJ-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));

        $this->update([
            'pairing_code' => $code,
            'pairing_expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }

    /**
     * Complete agent pairing: generates secret token, stores hash, clears pairing code.
     * Returns raw secret token (shown only once to the agent).
     */
    public function pairWithAgent(string $hostname, ?string $os = null, ?string $agentVersion = null): string
    {
        $rawToken = 'lpa_' . Str::random(56);
        $preview = 'lpa_****' . substr($rawToken, -4);

        $this->update([
            'device_token_hash' => hash('sha256', $rawToken),
            'device_token_preview' => $preview,
            'pairing_code' => null,
            'pairing_expires_at' => null,
            'last_heartbeat_at' => now(),
            'last_seen_hostname' => $hostname,
            'last_seen_os' => $os,
            'last_seen_agent_version' => $agentVersion,
            'last_error_message' => null,
        ]);

        return $rawToken;
    }

    /**
     * Rotate station credentials.
     */
    public function rotateCredentials(): string
    {
        $rawToken = 'lpa_' . Str::random(56);
        $preview = 'lpa_****' . substr($rawToken, -4);

        $this->update([
            'device_token_hash' => hash('sha256', $rawToken),
            'device_token_preview' => $preview,
            'pairing_code' => null,
            'pairing_expires_at' => null,
        ]);

        return $rawToken;
    }

    /**
     * Verify incoming agent token against station's device_token_hash.
     */
    public function verifyToken(string $providedToken): bool
    {
        if (empty($this->device_token_hash) || empty($providedToken)) {
            return false;
        }

        $hashed = hash('sha256', $providedToken);
        return hash_equals($this->device_token_hash, $hashed);
    }
}
