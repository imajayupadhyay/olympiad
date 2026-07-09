<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamEnrollment extends Model
{
    protected $fillable = [
        'user_id',
        'exam_id',
        'payment_id',
        'status',
        'enrollment_source',
        'assigned_by_admin_id',
        'assigned_at',
        'amount',
        'currency',
        'enrolled_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'assigned_at' => 'datetime',
        'enrolled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function assignedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_admin_id');
    }

    public function isEnrolled(): bool
    {
        return $this->status === 'enrolled';
    }
}
