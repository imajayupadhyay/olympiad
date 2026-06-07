<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentNotification extends Model
{
    protected $fillable = [
        'user_id', 'notification_log_id', 'title', 'message', 'is_read', 'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function log()  { return $this->belongsTo(NotificationLog::class, 'notification_log_id'); }
}
