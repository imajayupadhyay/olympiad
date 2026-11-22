<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One messaging provider's admin-entered configuration (workflow.md §13.6).
 * `credentials` is encrypted with APP_KEY and must never be sent to the browser.
 * Read it through MessagingConfigRepository, which falls back to the .env values.
 */
class MessagingProviderConfig extends Model
{
    protected $fillable = [
        'provider', 'settings', 'credentials', 'last_checked_at', 'last_check_ok',
        'last_check_message', 'updated_by',
    ];

    protected $hidden = ['credentials'];

    protected $casts = [
        'settings' => 'array',
        'credentials' => 'encrypted:array',
        'last_checked_at' => 'datetime',
        'last_check_ok' => 'boolean',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
