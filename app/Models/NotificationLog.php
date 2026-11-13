<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One admin broadcast on one channel (workflow.md §13.2). Per-student delivery
 * status lives in notification_deliveries; the *_count columns are a cached
 * roll-up refreshed by NotificationBroadcastService::refreshCounts().
 */
class NotificationLog extends Model
{
    public const CHANNELS = [
        'email' => 'Email',
        'in_app' => 'In-App',
        'whatsapp' => 'WhatsApp',
        'both' => 'Email + In-App',   // legacy broadcasts only
    ];

    public const STATUSES = [
        'queued' => 'Queued',
        'sending' => 'Sending',
        'completed' => 'Completed',
        'partial' => 'Partially failed',
        'failed' => 'Failed',
    ];

    protected $fillable = [
        'title', 'subject', 'message', 'body_html', 'link', 'whatsapp_template',
        'channel', 'audience', 'exam_id', 'class_level_id', 'audience_filters',
        'recipient_count', 'pending_count', 'sent_count', 'delivered_count', 'read_count',
        'failed_count', 'skipped_count', 'status', 'sent_by', 'sent_at', 'completed_at',
        'idempotency_key', 'fingerprint', 'meta',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'recipient_count' => 'integer',
        'pending_count' => 'integer',
        'sent_count' => 'integer',
        'delivered_count' => 'integer',
        'read_count' => 'integer',
        'failed_count' => 'integer',
        'skipped_count' => 'integer',
        'audience_filters' => 'array',
        'whatsapp_template' => 'array',
        'meta' => 'array',
    ];

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function classLevel()
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function studentNotifications()
    {
        return $this->hasMany(StudentNotification::class, 'notification_log_id');
    }

    public function deliveries()
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class, 'related_id')
            ->where('related_type', self::class);
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, ['queued', 'sending'], true);
    }
}
