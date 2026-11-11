<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admin-defined student detail (e.g. Father's name). Active fields appear on the
 * student profile; required ones count toward completion and hall-ticket eligibility.
 */
class StudentCustomField extends Model
{
    public const TYPES = ['text' => 'Text', 'date' => 'Date', 'select' => 'Dropdown'];

    protected $fillable = ['key', 'label', 'type', 'options', 'placeholder', 'help_text', 'is_required', 'is_active', 'sort_order'];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(StudentCustomFieldValue::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Key used in profile completion and the ticket config. */
    public function ticketKey(): string
    {
        return 'custom:'.$this->key;
    }
}
