<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(): Response
    {
        // If already authenticated as admin, skip to dashboard
        if (Auth::check() && Auth::user()->isAdmin() && Auth::user()->is_active !== false) {
            return Inertia::location(AdminPermissions::firstAllowedRoute(Auth::user()) ?? route('admin.dashboard'));
        }

        return Inertia::render('Admin/Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Rate limit: 5 attempts per minute per IP+email combo
        $key = 'admin-login:'.Str::lower($request->email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            if (! Auth::user()->isAdmin() || Auth::user()->is_active === false) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                RateLimiter::hit($key);

                return back()->withErrors([
                    'email' => 'Access denied. Administrator credentials required.',
                ]);
            }

            RateLimiter::clear($key);
            $request->session()->regenerate();

            $route = AdminPermissions::firstAllowedRoute(Auth::user());

            if (! $route) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'No admin permissions are assigned to this account.',
                ]);
            }

            return redirect($route);
        }

        RateLimiter::hit($key);

        return back()->withErrors([
            'email' => 'These credentials do not match our records.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
