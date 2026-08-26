<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptSequence extends Model
{
    protected $fillable = [
        'series',
        'financial_year',
        'next_number',
    ];

    protected $casts = [
        'next_number' => 'integer',
    ];
}
