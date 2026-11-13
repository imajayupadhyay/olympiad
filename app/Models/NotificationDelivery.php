<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One broadcast × student × channel. The only source of delivery status.
 *
 * Status flow: pending → sending (claimed by a batch job) → sent → delivered → read,
 * or → failed / skipped. `permanent_failure` marks failures a retry can't fix
 * (no phone number, number not on WhatsApp).
 */
class NotificationDelivery extends Model
{
    public const STATUSES = [
        'pending' => 'Pending',
        'sending' => 'Sending',
        'sent' => 'Sent',
        'delivered' => 'Delivered',
        'read' => 'Read',
        'failed' => 'Failed',
        'skipped' => 'Skipped',
    ];

    /** Statuses that mean the provider accepted the message. */
    public const SUCCESS = ['sent', 'delivered', 'read'];

    protected $fillable = [
        'notification_log_id', 'user_id', 'channel', 'recipient_name', 'destination', 'status',
        'provider_message_id', 'email_log_id', 'payload', 'error', 'error_code', 'permanent_failure',
        'attempts', 'claim_token', 'claimed_at', 'sent_at', 'delivered_at', 'read_at', 'failed_at',
        'status_checked_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'permanent_failure' => 'boolean',
        'attempts' => 'integer',
        'claimed_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'status_checked_at' => 'datetime',
    ];

    public function log()
    {
        return $this->belongsTo(NotificationLog::class, 'notification_log_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function emailLog()
    {
        return $this->belongsTo(EmailLog::class);
    }

    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('status', 'failed')->where('permanent_failure', false);
    }

    public function markFailed(string $error, ?string $code = null, bool $permanent = false): void
    {
        $this->update([
            'status' => 'failed',
            'error' => mb_substr($error, 0, 1000),
            'error_code' => $code ? mb_substr($code, 0, 40) : null,
            'permanent_failure' => $permanent,
            'failed_at' => now(),
            'claim_token' => null,
        ]);
    }

    public function markSkipped(string $reason, ?string $code = null): void
    {
        $this->update([
            'status' => 'skipped',
            'error' => mb_substr($reason, 0, 1000),
            'error_code' => $code,
            'permanent_failure' => true,
            'claim_token' => null,
        ]);
    }
}
