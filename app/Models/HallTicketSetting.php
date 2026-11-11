<?php

namespace App\Models;

use App\Services\HallTicketLayoutService;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row hall ticket layout configuration. Always read through current(); the
 * JSON shape is owned (defaulted and normalised) by HallTicketLayoutService.
 */
class HallTicketSetting extends Model
{
    protected $fillable = ['fields', 'exam_columns', 'text_rows', 'instructions', 'layout', 'updated_by'];

    protected $casts = [
        'fields' => 'array',
        'exam_columns' => 'array',
        'text_rows' => 'array',
        'instructions' => 'array',
        'layout' => 'array',
    ];

    public static function current(): self
    {
        // Same singleton rule as ReferralSetting: earliest row, else create (never firstOrCreate on id).
        return static::orderBy('id')->first() ?? static::create(app(HallTicketLayoutService::class)->defaults());
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
