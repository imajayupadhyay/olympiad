<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StaffUserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()
            ->where('role', 'admin')
            ->with('adminRole:id,name,slug,is_active')
            ->latest();

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if ($request->filled('role_id')) {
            $request->role_id === 'super'
                ? $query->whereNull('admin_role_id')
                : $query->where('admin_role_id', $request->integer('role_id'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        return Inertia::render('Admin/StaffUsers/Index', [
            'staffUsers' => $query->paginate(20)->withQueryString()->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'is_super_admin' => $user->isSuperAdmin(),
                'admin_role_id' => $user->admin_role_id,
                'admin_role' => $user->adminRole?->only(['id', 'name', 'slug', 'is_active']),
                'created_at' => optional($user->created_at)->toIso8601String(),
                'updated_at' => optional($user->updated_at)->toIso8601String(),
            ]),
            'roles' => AdminRole::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'filters' => $request->only(['search', 'role_id', 'status']),
            'summary' => [
                'total' => User::where('role', 'admin')->count(),
                'active' => User::where('role', 'admin')->where('is_active', true)->count(),
                'inactive' => User::where('role', 'admin')->where('is_active', false)->count(),
                'super' => User::where('role', 'admin')->whereNull('admin_role_id')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $plainPassword = $data['password'];

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($plainPassword),
            'role' => 'admin',
            'admin_role_id' => $data['admin_role_id'],
            'registration_source' => 'admin',
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with('success', 'Admin user created successfully.');
    }

    public function update(Request $request, User $staffUser): RedirectResponse
    {
        $this->assertManageable($staffUser);

        $data = $this->validated($request, $staffUser);
        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'admin_role_id' => $data['admin_role_id'],
            'is_active' => $data['is_active'] ?? true,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $staffUser->update($payload);

        return back()->with('success', 'Admin user updated successfully.');
    }

    public function toggle(Request $request, User $staffUser): RedirectResponse
    {
        $this->assertManageable($staffUser);

        $request->validate(['is_active' => ['required', 'boolean']]);
        $staffUser->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('success', $staffUser->is_active ? 'Admin user enabled.' : 'Admin user disabled.');
    }

    public function destroy(User $staffUser): RedirectResponse
    {
        $this->assertManageable($staffUser);

        $staffUser->delete();

        return back()->with('success', 'Admin user deleted successfully.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'admin_role_id' => [
                'required',
                'integer',
                Rule::exists('admin_roles', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function assertManageable(User $user): void
    {
        abort_if($user->role !== 'admin', 404);

        if ($user->is(auth()->user()) || $user->isSuperAdmin()) {
            abort(403, 'Protected admin accounts cannot be changed from this page.');
        }
    }
}
