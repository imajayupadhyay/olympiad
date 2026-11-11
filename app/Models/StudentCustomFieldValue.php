<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCustomFieldValue extends Model
{
    protected $fillable = ['student_custom_field_id', 'user_id', 'value'];

    public function field(): BelongsTo
    {
        return $this->belongsTo(StudentCustomField::class, 'student_custom_field_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
