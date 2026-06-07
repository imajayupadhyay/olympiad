<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassLevel extends Model
{
    protected $fillable = ['level', 'label', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public static function active()
    {
        return static::where('is_active', true)->orderBy('sort_order')->get();
    }
}
