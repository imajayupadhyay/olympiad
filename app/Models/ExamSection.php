<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExamSection extends Model
{
    protected $fillable = [
        'exam_id',
        'subject_id',
        'name',
        'instructions',
        'sort_order',
        'marks_per_question',
        'negative_marks_per_question',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'marks_per_question' => 'decimal:2',
        'negative_marks_per_question' => 'decimal:2',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot(['exam_id', 'sort_order', 'marks', 'negative_marks'])
            ->withTimestamps()
            ->orderBy('exam_questions.sort_order');
    }
}
