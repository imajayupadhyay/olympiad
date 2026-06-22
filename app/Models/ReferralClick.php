<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recorded open of a referrer's link. Drives rewards when the program's
 * qualify_on is "link_click". De-duped per (referrer_id, ip_address).
 */
class ReferralClick extends Model
{
    protected $fillable = [
        'referrer_id',
        'ip_address',
        'clicked_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }
}
