<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'title', 'message', 'channel', 'audience',
        'exam_id', 'class_level_id', 'audience_filters',
        'recipient_count', 'status', 'sent_by', 'sent_at',
    ];

    protected $casts = [
        'sent_at'         => 'datetime',
        'recipient_count' => 'integer',
        'audience_filters' => 'array',
    ];

    public function sentBy()     { return $this->belongsTo(User::class, 'sent_by'); }
    public function exam()       { return $this->belongsTo(Exam::class); }
    public function classLevel() { return $this->belongsTo(ClassLevel::class); }
    public function studentNotifications() { return $this->hasMany(StudentNotification::class, 'notification_log_id'); }
    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class, 'related_id')
            ->where('related_type', self::class);
    }
}
