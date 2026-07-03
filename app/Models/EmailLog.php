<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    protected $fillable = [
        'email_template_id',
        'template_key',
        'recipient_user_id',
        'related_type',
        'related_id',
        'recipient_email',
        'recipient_name',
        'subject',
        'status',
        'brevo_message_id',
        'error_message',
        'meta',
        'queued_at',
        'sent_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
