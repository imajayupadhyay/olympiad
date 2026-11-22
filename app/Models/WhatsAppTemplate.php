<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A WhatsApp template registered locally for one provider (MSG91 today). It must
 * mirror a template already approved in that provider's console: name, language
 * and body are sent as-is. Bird templates are never stored here (read live).
 */
class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    public const CATEGORIES = ['utility' => 'Utility', 'marketing' => 'Marketing', 'authentication' => 'Authentication'];

    public const APPROVAL_STATUSES = ['approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected'];

    protected $fillable = [
        'provider', 'name', 'display_name', 'language', 'category', 'external_id', 'header', 'body',
        'footer', 'buttons', 'variable_examples', 'approval_status', 'is_active', 'source',
        'synced_at', 'meta', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'buttons' => 'array',
        'variable_examples' => 'array',
        'meta' => 'array',
        'is_active' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /** Variable keys in the body, in order of first appearance ({{1}}, {{name}}). */
    public function bodyVariables(): array
    {
        preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', (string) $this->body, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    public function headerHasVariables(): bool
    {
        return (bool) preg_match('/{{\s*[a-zA-Z0-9_]+\s*}}/', (string) $this->header);
    }
}
