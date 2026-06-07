<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Question extends Model
{
    protected $fillable = [
        'subject_id', 'class_level_id', 'question_category_id', 'difficulty', 'topic',
        'question_text', 'question_image',
        'option_a', 'option_b', 'option_c', 'option_d',
        'question_type', 'correct_options',
        'explanation', 'marks', 'negative_marks',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'marks' => 'integer',
        'negative_marks' => 'float',
        'correct_options' => 'array',
    ];

    protected $appends = ['question_image_url'];

    public function getQuestionImageUrlAttribute(): ?string
    {
        return $this->question_image
            ? Storage::url($this->question_image)
            : null;
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function questionCategory(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'exam_questions')
            ->withPivot(['sort_order', 'marks', 'negative_marks'])
            ->withTimestamps();
    }

    public static function difficulties(): array
    {
        return ['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'];
    }

    public static function types(): array
    {
        return ['single' => 'Single Correct', 'multiple' => 'Multiple Correct'];
    }
}
