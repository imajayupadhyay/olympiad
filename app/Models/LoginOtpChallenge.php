<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginOtpChallenge extends Model
{
    protected $fillable = [
        'channel',
        'identifier_fingerprint',
        'masked_identifier',
        'candidate_user_ids',
        'otp_hash',
        'attempts',
        'remember',
        'delivery_status',
        'expires_at',
        'verified_at',
        'consumed_at',
        'selected_user_id',
    ];

    protected $hidden = [
        'identifier_fingerprint',
        'candidate_user_ids',
        'otp_hash',
    ];

    protected function casts(): array
    {
        return [
            'candidate_user_ids' => 'array',
            'attempts' => 'integer',
            'remember' => 'boolean',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function selectedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->consumed_at && ! $this->isExpired();
    }
}
