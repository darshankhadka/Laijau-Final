<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineSaleVoidLog extends Model
{
    use HasFactory;

    protected $table = 'offline_sale_void_logs';

    protected $fillable = [
        'offline_sale_id',
        'voided_by_user_id',
        'voided_by_name',
        'reason',
        'restocked',
        'snapshot_data',
    ];

    protected function casts(): array
    {
        return [
            'restocked' => 'boolean',
            'snapshot_data' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(OfflineSale::class, 'offline_sale_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }
}
