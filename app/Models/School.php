<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $fillable = [
        'school_code',
        'name',
        'address',
        'state',
        'district',
        'city',
        'pin_code',
        'email',
        'mobile',
        'head_phone',
        'is_active',
        'is_managed',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_managed' => 'boolean',
    ];

    public function coordinators(): HasMany
    {
        return $this->hasMany(SchoolCoordinator::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeManaged(Builder $query): Builder
    {
        return $query->where('is_managed', true);
    }

    /**
     * Autocomplete search: prefix matches rank above "contains" matches, so
     * typing "delhi" surfaces schools starting with Delhi before ones that
     * merely mention it. Case-insensitive; escapes LIKE wildcards in input.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

        return $query
            ->where('is_active', true)
            ->where('name', 'like', "%{$escaped}%")
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', ["{$escaped}%"])
            ->orderBy('name');
    }
}
