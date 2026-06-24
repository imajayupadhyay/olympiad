<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $fillable = [
        'referrer_id',
        'referee_id',
        'referral_code',
        'status',
        'qualified_at',
        'rewarded_at',
        'referrer_reward_coupon_id',
        'referee_welcome_coupon_id',
    ];

    protected $casts = [
        'qualified_at' => 'datetime',
        'rewarded_at'  => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_id');
    }

    public function rewardCoupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'referrer_reward_coupon_id');
    }

    public function welcomeCoupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'referee_welcome_coupon_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeQualified(Builder $query): Builder
    {
        return $query->where('status', 'qualified');
    }

    public function scopeRewarded(Builder $query): Builder
    {
        return $query->where('status', 'rewarded');
    }
}
