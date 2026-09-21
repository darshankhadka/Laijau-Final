<?php

namespace App\Models\Hardware;

use App\Models\PosPrintJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosPrinter extends Model
{
    use HasFactory;

    protected $table = 'pos_printers';

    protected $fillable = [
        'station_id',
        'name',
        'code',
        'role',
        'connection_type',
        'network_ip',
        'network_port',
        'usb_device_path',
        'paper_width_mm',
        'characters_per_line',
        'character_encoding',
        'auto_cut_enabled',
        'copies',
        'is_active',
        'last_test_printed_at',
        'last_test_status',
        'last_test_error',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_cut_enabled' => 'boolean',
        'paper_width_mm' => 'integer',
        'characters_per_line' => 'integer',
        'network_port' => 'integer',
        'copies' => 'integer',
        'last_test_printed_at' => 'datetime',
    ];


    public function station(): BelongsTo
    {
        return $this->belongsTo(PosStation::class, 'station_id');
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PosPrintJob::class, 'printer_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeReceiptPrinters($query)
    {
        return $query->where('role', 'receipt');
    }

    public function scopeLabelPrinters($query)
    {
        return $query->where('role', 'label');
    }

    /**
     * Get hardware connection spec array for agent transmission.
     */
    public function toHardwareConfig(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'type' => $this->connection_type,
            'network_ip' => $this->network_ip,
            'network_port' => $this->network_port,
            'usb_device_path' => $this->usb_device_path,
            'paper_width_mm' => $this->paper_width_mm,
            'characters_per_line' => $this->characters_per_line,
            'character_encoding' => $this->character_encoding,
            'auto_cut_enabled' => $this->auto_cut_enabled,
            'copies' => $this->copies,
        ];
    }

}
