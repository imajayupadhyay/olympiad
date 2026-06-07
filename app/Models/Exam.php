<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Exam extends Model
{
    protected $fillable = [
        'subject_id',
        'class_level_id',
        'name',
        'slug',
        'exam_code',
        'description',
        'syllabus',
        'eligibility',
        'instructions',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'fee_amount',
        'fee_currency',
        'scoring_mode',
        'marks_per_question',
        'negative_marking_enabled',
        'negative_marks_per_question',
        'randomize_questions',
        'randomize_options',
        'show_result_immediately',
        'result_release_at',
        'status',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'result_release_at' => 'datetime',
        'published_at' => 'datetime',
        'duration_minutes' => 'integer',
        'fee_amount' => 'decimal:2',
        'marks_per_question' => 'decimal:2',
        'negative_marking_enabled' => 'boolean',
        'negative_marks_per_question' => 'decimal:2',
        'randomize_questions' => 'boolean',
        'randomize_options' => 'boolean',
        'show_result_immediately' => 'boolean',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot(['sort_order', 'marks', 'negative_marks'])
            ->withTimestamps()
            ->orderBy('exam_questions.sort_order');
    }

    public function attempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function results()
    {
        return $this->hasMany(Result::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    public function template()
    {
        return $this->hasOne(Certificate::class)->where('type', 'template')->where('is_active', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function statuses(): array
    {
        return [
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived',
        ];
    }

    public static function scoringModes(): array
    {
        return [
            'question_bank' => 'Use question bank marks',
            'uniform' => 'Use exam-wide marks',
        ];
    }
}
