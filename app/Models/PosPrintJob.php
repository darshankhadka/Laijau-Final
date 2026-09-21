<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosPrintJob extends Model
{
    use HasFactory;

    protected $table = 'pos_print_jobs';

    protected $fillable = [
        'uuid',
        'station_id',
        'printer_id',
        'source_type',
        'source_id',
        'idempotency_key',
        'job_type',
        'status',
        'payload_data',
        'raw_escpos_base64',
        'attempts',
        'max_attempts',
        'last_error',
        'claimed_at',
        'printed_at',
    ];

    protected $casts = [
        'payload_data' => 'array',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'claimed_at' => 'datetime',
        'printed_at' => 'datetime',
    ];


    protected static function booted(): void
    {
        static::creating(function (PosPrintJob $job) {
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    public function offlineSale(): BelongsTo
    {
        return $this->belongsTo(OfflineSale::class, 'source_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'source_id');
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Hardware\PosPrinter::class, 'printer_id');
    }


    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    public function scopeForStation($query, string $stationId)
    {
        return $query->where('station_id', $stationId);
    }

    public function markAsPrinting(): bool
    {
        return $this->update([
            'status' => 'printing',
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function markAsPrinted(): bool
    {
        return $this->update([
            'status' => 'printed',
            'printed_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markAsFailed(?string $error = null): bool
    {
        $newStatus = ($this->attempts >= $this->max_attempts) ? 'failed' : 'queued';

        return $this->update([
            'status' => $newStatus,
            'last_error' => $error ? Str::limit($error, 1000) : null,
        ]);
    }
}
