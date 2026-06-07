<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private function meta(): array
    {
        return [
            'classLevels' => ClassLevel::active(),
            'states'      => User::indianStates(),
        ];
    }

    public function index(Request $request): Response
    {
        $query = User::where('role', 'student')->with('classLevel')->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('school', 'like', "%{$s}%")
                  ->orWhere('city', 'like', "%{$s}%");
            });
        }

        if ($request->filled('class_level_id')) {
            $query->where('class_level_id', $request->class_level_id);
        }

        if ($request->filled('state')) {
            $query->where('state', $request->state);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        return Inertia::render('Admin/Users/Index', [
            'students'    => $query->paginate(20)->withQueryString(),
            'classLevels' => ClassLevel::active(),
            'states'      => User::indianStates(),
            'filters'     => $request->only(['search', 'class_level_id', 'state', 'status']),
            'totals'      => [
                'all'      => User::where('role', 'student')->count(),
                'active'   => User::where('role', 'student')->where('is_active', true)->count(),
                'inactive' => User::where('role', 'student')->where('is_active', false)->count(),
                'today'    => User::where('role', 'student')->whereDate('created_at', today())->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', $this->meta());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'email'          => 'required|email|unique:users,email',
            'password'       => 'required|string|min:8|confirmed',
            'class_level_id' => 'nullable|exists:class_levels,id',
            'phone'          => 'nullable|string|max:15',
            'dob'            => 'nullable|date|before:today',
            'school'         => 'nullable|string|max:200',
            'city'           => 'nullable|string|max:100',
            'state'          => 'nullable|string|max:100',
            'is_active'      => 'boolean',
        ]);

        $data['role']     = 'student';
        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return redirect()->route('admin.users.index')->with('success', 'Student account created successfully.');
    }

    public function show(User $user): Response
    {
        $user->load('classLevel');

        return Inertia::render('Admin/Users/Show', ['student' => $user]);
    }

    public function edit(User $user): Response
    {
        $user->load('classLevel');

        return Inertia::render('Admin/Users/Edit', array_merge(
            ['student' => $user],
            $this->meta()
        ));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'email'          => 'required|email|unique:users,email,' . $user->id,
            'password'       => 'nullable|string|min:8|confirmed',
            'class_level_id' => 'nullable|exists:class_levels,id',
            'phone'          => 'nullable|string|max:15',
            'dob'            => 'nullable|date|before:today',
            'school'         => 'nullable|string|max:200',
            'city'           => 'nullable|string|max:100',
            'state'          => 'nullable|string|max:100',
            'is_active'      => 'boolean',
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.show', $user)->with('success', 'Student profile updated successfully.');
    }

    public function toggle(Request $request, User $user)
    {
        $request->validate(['is_active' => 'required|boolean']);

        $user->update(['is_active' => $request->is_active]);

        $action = $request->is_active ? 'enabled' : 'disabled';
        return back()->with('success', "Student account {$action} successfully.");
    }

    public function destroy(User $user)
    {
        if ($user->role === 'admin') {
            return back()->with('error', 'Cannot delete admin accounts.');
        }

        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'Student deleted successfully.');
    }
}
