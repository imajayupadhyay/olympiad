<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolCoordinator extends Model
{
    protected $fillable = [
        'school_id',
        'name',
        'email',
        'phone',
        'designation',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
