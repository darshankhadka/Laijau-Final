<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReview extends Model
{
    use HasFactory;

    protected $table = 'hrm_performance_reviews';

    protected $fillable = [
        'employee_id',
        'reviewer_id',
        'review_type',
        'review_date',
        'overall_rating',
        'strengths',
        'growth_areas',
        'goals_json',
        'notes',
        'status',
    ];

    protected $casts = [
        'review_date' => 'date',
        'overall_rating' => 'integer',
        'goals_json' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }
}
