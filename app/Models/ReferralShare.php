<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recorded copy/share of a referrer's own link. Drives rewards when the
 * program's qualify_on is "link_share". NOT de-duped — every share counts.
 */
class ReferralShare extends Model
{
    protected $fillable = [
        'referrer_id',
        'channel',
        'shared_at',
    ];

    protected $casts = [
        'shared_at' => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }
}
