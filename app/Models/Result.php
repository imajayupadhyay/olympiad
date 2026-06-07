<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    protected $fillable = [
        'user_id', 'exam_id', 'exam_attempt_id',
        'total_score', 'max_score', 'percentage', 'percentile',
        'rank_national', 'rank_state', 'rank_city', 'rank_school',
        'grade', 'is_released', 'released_at',
        'score_override', 'override_reason', 'override_by',
    ];

    protected $casts = [
        'is_released'  => 'boolean',
        'released_at'  => 'datetime',
        'total_score'  => 'float',
        'max_score'    => 'float',
        'percentage'   => 'float',
        'percentile'   => 'float',
        'score_override' => 'float',
    ];

    public function user()    { return $this->belongsTo(User::class); }
    public function exam()    { return $this->belongsTo(Exam::class); }
    public function attempt() { return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id'); }
    public function overriddenBy() { return $this->belongsTo(User::class, 'override_by'); }

    public function effectiveScore(): float
    {
        return $this->score_override ?? $this->total_score;
    }
}
