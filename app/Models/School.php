<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    protected $fillable = ['name', 'address', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

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
