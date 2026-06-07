<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user()->load('classLevel');

        return Inertia::render('Student/Profile/Index', [
            'profile'     => $user,
            'classLevels' => ClassLevel::active(),
            'states'      => User::indianStates(),
        ]);
    }

    /**
     * Update personal details (name, email, class, contact, location).
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'email'          => 'required|email|max:255|unique:users,email,' . $user->id,
            'phone'          => 'nullable|string|max:15',
            'class_level_id' => 'required|exists:class_levels,id',
            'dob'            => 'nullable|date|before:today',
            'school'         => 'nullable|string|max:200',
            'city'           => 'nullable|string|max:100',
            'state'          => 'nullable|string|max:100',
        ]);

        // Re-verification flow not enforced — email is editable directly.
        $user->update($data);

        return back()->with('success', 'Your profile has been updated.');
    }

    /**
     * Upload / replace the profile photo.
     */
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:4096',
        ]);

        $user = $request->user();

        if ($user->photo && Storage::disk('public')->exists($user->photo)) {
            Storage::disk('public')->delete($user->photo);
        }

        $path = $request->file('photo')->store('avatars', 'public');
        $user->update(['photo' => $path]);

        return back()->with('success', 'Profile photo updated.');
    }

    /**
     * Remove the current profile photo.
     */
    public function deletePhoto(Request $request)
    {
        $user = $request->user();

        if ($user->photo && Storage::disk('public')->exists($user->photo)) {
            Storage::disk('public')->delete($user->photo);
        }

        $user->update(['photo' => null]);

        return back()->with('success', 'Profile photo removed.');
    }

    /**
     * Change account password.
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password changed successfully.');
    }
}
