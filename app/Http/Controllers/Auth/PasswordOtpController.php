<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * OTP-based "forgot password" flow for students. Three server-driven steps
 * (email -> code -> new password) share one page; the "verified" hand-off lives
 * in the httpOnly session, so no token that authorises a reset is ever exposed
 * to the browser.
 */
class PasswordOtpController extends Controller
{
    private const SESSION_KEY = 'pw_otp';
    private const RESEND_COOLDOWN = 45;      // seconds between sends
    private const RESET_WINDOW_MINUTES = 15; // how long a verified code stays usable

    public function __construct(protected PasswordOtpService $otp) {}

    /** Render the wizard at whichever step the session is currently in. */
    public function create(Request $request): Response
    {
        $state = $request->session()->get(self::SESSION_KEY, []);
        $step = $this->currentStep($state);

        return Inertia::render('Auth/ForgotPassword', [
            'step'          => $step,
            'email'         => $state['email'] ?? '',
            'maskedEmail'   => isset($state['email']) ? $this->maskEmail($state['email']) : '',
            'otpLength'     => PasswordOtpService::OTP_LENGTH,
            'expiryMinutes' => PasswordOtpService::EXPIRY_MINUTES,
            'resendIn'      => $step === 'otp' ? $this->resendRemaining($state) : 0,
            'status'        => $request->session()->get('status'),
        ]);
    }

    /** Step 1 — email entered; issue + email a code, advance to the OTP step. */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email']);
        $email = Str::lower(trim($data['email']));

        $this->throttle("pw-otp-send:{$request->ip()}", 15, 60);
        $this->throttle('pw-otp-send:'.sha1($email), 5, 60);

        // Enforce a resend cooldown from the session (defence-in-depth alongside
        // the rate limiter, and drives the UI countdown).
        $state = $request->session()->get(self::SESSION_KEY, []);
        if (($state['email'] ?? null) === $email && $this->resendRemaining($state) > 0) {
            throw ValidationException::withMessages([
                'email' => "Please wait {$this->resendRemaining($state)}s before requesting another code.",
            ]);
        }

        // No-op for unknown/non-student emails (enumeration-safe).
        $this->otp->request($email);

        $request->session()->put(self::SESSION_KEY, [
            'email'     => $email,
            'sent_at'   => now()->timestamp,
            'verified'  => false,
        ]);

        return back()->with('status', 'If an account exists for that email, we\'ve sent a 6-digit code to it.');
    }

    /** Step 2 — verify the submitted code; on success unlock the reset step. */
    public function verify(Request $request): RedirectResponse
    {
        $state = $request->session()->get(self::SESSION_KEY, []);
        $email = $state['email'] ?? null;

        if (! $email) {
            return redirect()->route('password.request');
        }

        $data = $request->validate([
            'otp' => 'required|string',
        ]);
        $code = preg_replace('/\D/', '', $data['otp']);

        $this->throttle("pw-otp-verify:{$request->ip()}", 30, 60);

        if (! $this->otp->verify($email, $code)) {
            throw ValidationException::withMessages([
                'otp' => 'That code is invalid or has expired. Please check and try again.',
            ]);
        }

        $state['verified'] = true;
        $state['verified_at'] = now()->timestamp;
        $request->session()->put(self::SESSION_KEY, $state);

        return back();
    }

    /** Step 3 — set the new password, then log the student straight in. */
    public function reset(Request $request): RedirectResponse
    {
        $state = $request->session()->get(self::SESSION_KEY, []);

        // Must hold a verified, unexpired session hand-off.
        if (empty($state['verified']) || $this->verifyWindowExpired($state)) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('password.request')
                ->withErrors(['email' => 'Your reset session has expired. Please start again.']);
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $this->otp->reset($state['email'], $request->input('password'));

        if (! $user) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('password.request')
                ->withErrors(['email' => 'We could not complete the reset. Please start again.']);
        }

        // Clear the flow state, rotate the session, and sign in.
        $request->session()->forget(self::SESSION_KEY);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('student.dashboard', absolute: false))
            ->with('status', 'Your password has been reset. Welcome back!');
    }

    /** Abandon the flow and return to step 1. */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('password.request');
    }

    /* ── helpers ── */

    private function currentStep(array $state): string
    {
        if (! empty($state['verified']) && ! $this->verifyWindowExpired($state)) {
            return 'reset';
        }

        if (! empty($state['email'])) {
            return 'otp';
        }

        return 'email';
    }

    private function verifyWindowExpired(array $state): bool
    {
        if (empty($state['verified_at'])) {
            return true;
        }

        return now()->timestamp - (int) $state['verified_at'] > self::RESET_WINDOW_MINUTES * 60;
    }

    private function resendRemaining(array $state): int
    {
        $sentAt = (int) ($state['sent_at'] ?? 0);

        return max(0, self::RESEND_COOLDOWN - (now()->timestamp - $sentAt));
    }

    private function throttle(string $key, int $max, int $decaySeconds): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Too many attempts. Please try again in {$seconds}s.",
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = Str::substr($name, 0, 2);
        $masked = $visible.str_repeat('•', max(1, Str::length($name) - 2));

        return $domain ? "{$masked}@{$domain}" : $masked;
    }
}
