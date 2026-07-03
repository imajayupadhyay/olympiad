<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\User;
use App\Services\ManagedEmailService;
use App\Services\ReferralService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'classLevels' => ClassLevel::active(),
            'states'      => User::indianStates(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'email'          => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'phone'          => 'nullable|string|max:15',
            'class_level_id' => 'required|exists:class_levels,id',
            'dob'            => 'nullable|date|before:today',
            'school'         => 'nullable|string|max:200',
            'city'           => 'nullable|string|max:100',
            'state'          => 'nullable|string|max:100',
            'password'       => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name'           => $data['name'],
            'email'          => $data['email'],
            'phone'          => $data['phone'] ?? null,
            'class_level_id' => $data['class_level_id'],
            'dob'            => $data['dob'] ?? null,
            'school'         => $data['school'] ?? null,
            'city'           => $data['city'] ?? null,
            'state'          => $data['state'] ?? null,
            'role'           => 'student',
            'is_active'      => true,
            'password'       => Hash::make($data['password']),
        ]);

        event(new Registered($user));

        app(ManagedEmailService::class)->queue(
            'student_registered',
            $user,
            app(ManagedEmailService::class)->studentRegistrationVariables($user, $data['password']),
            ['related_type' => User::class, 'related_id' => $user->id]
        );

        // Attribute this signup to a referrer if a referral code was carried in.
        if ($code = $request->session()->pull('referral_code')) {
            app(ReferralService::class)->attribute($user, $code);
        }

        Auth::login($user);

        // Step 2 of the registration wizard — pick olympiads to enrol in.
        return redirect(route('register.olympiads', absolute: false));
    }
}
