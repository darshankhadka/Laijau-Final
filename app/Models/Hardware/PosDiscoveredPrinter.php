<?php

namespace App\Models\Hardware;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosDiscoveredPrinter extends Model
{
    use HasFactory;

    protected $table = 'pos_discovered_printers';

    protected $fillable = [
        'station_id',
        'name',
        'connection_type',
        'network_ip',
        'network_port',
        'usb_device_path',
        'status',
        'last_seen_at',
    ];

    protected $casts = [
        'network_port' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(PosStation::class, 'station_id');
    }
}
