<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasFactory;

    protected $table = 'hrm_positions';

    protected $fillable = [
        'department_id',
        'title',
        'code',
        'description',
        'employment_type',
        'min_salary_npr',
        'max_salary_npr',
        'hourly_rate_npr',
        'is_active',
    ];

    protected $casts = [
        'min_salary_npr' => 'decimal:2',
        'max_salary_npr' => 'decimal:2',
        'hourly_rate_npr' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'position_id');
    }
}
