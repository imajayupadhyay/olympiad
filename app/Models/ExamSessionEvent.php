<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit timeline of an exam session (Ongoing Exams). Keyed by exam + student so it
 * survives a reset, which deletes the attempt row.
 */
class ExamSessionEvent extends Model
{
    public const LABELS = [
        'started' => 'Started the exam',
        'room_opened' => 'Opened the exam room',
        'device_switched' => 'Continued on another tab or device',
        'violation' => 'Left the exam tab',
        'reconnected' => 'Came back after being offline',
        'submitted' => 'Submitted',
        'auto_submitted' => 'Auto-submitted',
        'timed_out' => 'Time ran out',
        'force_submitted' => 'Force-submitted by admin',
        'suspended' => 'Paused by admin',
        'resumed' => 'Resumed',
        'time_added' => 'Time added',
        'reopened' => 'Reopened by admin',
        'reset' => 'Reset to start from scratch',
        'rescheduled' => 'Rescheduled',
        'unscheduled' => 'Personal window removed',
        'violations_cleared' => 'Tab-switch warnings cleared',
        'result_removed' => 'Unreleased result removed',
    ];

    protected $fillable = ['exam_id', 'user_id', 'exam_attempt_id', 'actor_id', 'type', 'reason', 'meta'];

    protected $casts = ['meta' => 'array'];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
