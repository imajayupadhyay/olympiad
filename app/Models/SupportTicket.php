<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id', 'subject', 'category', 'priority', 'status',
        'assigned_to', 'last_reply_by', 'last_reply_at',
        'student_unread', 'admin_unread',
    ];

    protected $casts = [
        'last_reply_at'  => 'datetime',
        'student_unread' => 'integer',
        'admin_unread'   => 'integer',
    ];

    public function user()     { return $this->belongsTo(User::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id')->orderBy('created_at');
    }

    public function latestMessage()
    {
        return $this->hasOne(TicketMessage::class, 'ticket_id')->latestOfMany();
    }

    /** Tickets still awaiting work (not resolved/closed). */
    public function scopeOpenish($query)
    {
        return $query->whereNotIn('status', ['resolved', 'closed']);
    }
}
