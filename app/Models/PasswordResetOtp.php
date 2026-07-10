<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    protected $fillable = [
        'email', 'otp_hash', 'attempts', 'expires_at', 'verified_at',
    ];

    protected $hidden = [
        'otp_hash',
    ];

    protected $casts = [
        'attempts'    => 'integer',
        'expires_at'  => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
