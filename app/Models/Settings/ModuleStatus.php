<?php

namespace App\Models\Settings;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleStatus extends Model
{
    use HasFactory;

    protected $table = 'module_statuses';

    protected $fillable = [
        'module',
        'is_enabled',
        'disabled_reason',
        'disabled_at',
        'disabled_by',
        'metadata',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'disabled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function disabledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by');
    }
}
