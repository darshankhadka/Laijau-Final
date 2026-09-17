<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentJob extends Model
{
    use HasFactory;

    protected $table = 'hrm_recruitment_jobs';

    protected $fillable = [
        'title',
        'department_id',
        'position_id',
        'employment_type',
        'location',
        'status',
        'deadline',
        'description',
        'requirements',
    ];

    protected $casts = [
        'deadline' => 'date',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(RecruitmentApplicant::class, 'job_id');
    }
}
