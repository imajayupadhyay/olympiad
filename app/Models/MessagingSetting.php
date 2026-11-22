<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row messaging configuration (workflow.md §13.6): which provider sends
 * WhatsApp broadcasts. Always read via current() (never firstOrCreate on id).
 */
class MessagingSetting extends Model
{
    protected $fillable = ['whatsapp_provider', 'updated_by', 'switched_at'];

    protected $casts = ['switched_at' => 'datetime'];

    public static function current(): self
    {
        return static::query()->orderBy('id')->first() ?? static::create(['whatsapp_provider' => 'bird']);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
