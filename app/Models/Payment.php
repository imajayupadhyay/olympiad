<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'gross_amount',
        'discount_amount',
        'currency',
        'status',
        'gateway',
        'coupon_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'method',
        'is_manual',
        'recorded_by_admin_id',
        'manually_recorded_at',
        'manual_reference',
        'manual_note',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'gross_amount'    => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'is_manual'       => 'boolean',
        'manually_recorded_at' => 'datetime',
        'notes'           => 'array',
        'paid_at'         => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function recordedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_admin_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ExamEnrollment::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
