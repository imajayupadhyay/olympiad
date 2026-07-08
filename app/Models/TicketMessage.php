<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    protected $fillable = [
        'ticket_id', 'user_id', 'author_role', 'body',
    ];

    public function ticket() { return $this->belongsTo(SupportTicket::class, 'ticket_id'); }
    public function author() { return $this->belongsTo(User::class, 'user_id'); }
}
