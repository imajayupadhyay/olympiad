<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = AdminRole::query()
            ->with(['permissions', 'users:id,admin_role_id'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (AdminRole $role) => $this->payload($role));

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'modules' => AdminPermissions::moduleOptions(),
            'actions' => AdminPermissions::ACTIONS,
            'emptyPermissions' => AdminPermissions::emptyMatrix(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $role = AdminRole::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->syncPermissions($role, $data['permissions'] ?? []);
        });

        return back()->with('success', 'Role created successfully.');
    }

    public function update(Request $request, AdminRole $role): RedirectResponse
    {
        $data = $this->validated($request, $role);

        DB::transaction(function () use ($role, $data) {
            $role->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->syncPermissions($role, $data['permissions'] ?? []);
        });

        return back()->with('success', 'Role updated successfully.');
    }

    public function destroy(AdminRole $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'This role is assigned to users. Reassign them before deleting it.');
        }

        $role->delete();

        return back()->with('success', 'Role deleted successfully.');
    }

    private function validated(Request $request, ?AdminRole $role = null): array
    {
        $request->merge([
            'name' => is_scalar($request->input('name')) ? trim((string) $request->input('name')) : null,
            'description' => is_scalar($request->input('description')) ? trim((string) $request->input('description')) : null,
            'permissions' => AdminPermissions::normalizeMatrix((array) $request->input('permissions', [])),
        ]);

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('admin_roles', 'name')->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'permissions' => ['required', 'array'],
        ]);
    }

    private function syncPermissions(AdminRole $role, array $permissions): void
    {
        $permissions = AdminPermissions::normalizeMatrix($permissions);

        foreach ($permissions as $module => $actions) {
            $role->permissions()->updateOrCreate(
                ['module' => $module],
                [
                    'can_read' => $actions['read'],
                    'can_write' => $actions['write'],
                    'can_delete' => $actions['delete'],
                ],
            );
        }
    }

    private function payload(AdminRole $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'is_active' => $role->is_active,
            'users_count' => $role->users->count(),
            'permissions' => $role->permissionMatrix(),
            'updated_at' => optional($role->updated_at)->toIso8601String(),
        ];
    }
}
