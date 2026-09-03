<?php

namespace App\Models;

use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AdminRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AdminRole $role) {
            $role->name = trim((string) $role->name);
            $role->slug = Str::slug($role->slug ?: $role->name);
        });
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(AdminRolePermission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'admin_role_id');
    }

    public function permissionMatrix(): array
    {
        $rows = $this->relationLoaded('permissions')
            ? $this->permissions
            : $this->permissions()->get();

        $matrix = AdminPermissions::emptyMatrix();

        foreach ($rows as $row) {
            if (! isset($matrix[$row->module])) {
                continue;
            }

            $matrix[$row->module] = [
                'read' => (bool) $row->can_read,
                'write' => (bool) $row->can_write,
                'delete' => (bool) $row->can_delete,
            ];
        }

        return $matrix;
    }
}
