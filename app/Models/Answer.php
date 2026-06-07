<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    protected $fillable = [
        'exam_attempt_id', 'question_id', 'selected_options', 'is_correct', 'marks_awarded', 'is_flagged',
    ];

    protected $casts = [
        'selected_options' => 'array',
        'is_correct'       => 'boolean',
        'is_flagged'       => 'boolean',
        'marks_awarded'    => 'float',
    ];

    public function attempt()  { return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id'); }
    public function question() { return $this->belongsTo(Question::class); }
}
