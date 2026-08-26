<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    protected $fillable = [
        'payment_id',
        'receipt_number',
        'series',
        'financial_year',
        'sequence_number',
        'issued_at',
        'created_by_admin_id',
        'company_snapshot',
        'customer_snapshot',
        'payment_snapshot',
        'line_items',
        'totals',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'company_snapshot' => 'array',
        'customer_snapshot' => 'array',
        'payment_snapshot' => 'array',
        'line_items' => 'array',
        'totals' => 'array',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    public function filename(): string
    {
        $safeNumber = preg_replace('/[^A-Za-z0-9._-]+/', '-', $this->receipt_number);

        return 'receipt-'.$safeNumber.'.pdf';
    }
}
