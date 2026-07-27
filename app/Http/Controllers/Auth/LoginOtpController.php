<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginOtpChallenge;
use App\Models\User;
use App\Services\LoginOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginOtpController extends Controller
{
    public function __construct(protected LoginOtpService $otp) {}

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $identity = $this->otp->parseIdentifier($data['identifier']);
        $this->ensureChannelAvailable($identity['channel']);
        $this->ensureCooldownElapsed($request, $identity['fingerprint']);
        $this->throttleSend($request, $identity['fingerprint']);

        $challenge = $this->otp->issue($identity, $request->boolean('remember'));
        $request->session()->put(LoginOtpService::SESSION_KEY, [
            'challenge_id' => $challenge->id,
            'identifier' => $identity['normalized'],
            'fingerprint' => $identity['fingerprint'],
            'sent_at' => now()->timestamp,
        ]);

        return back()->with('status', 'If an active student account matches, a secure login code is on its way.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $state = $request->session()->get(LoginOtpService::SESSION_KEY, []);

        if (empty($state['identifier'])) {
            return redirect()->route('login');
        }

        $identity = $this->otp->parseIdentifier($state['identifier']);
        $this->ensureChannelAvailable($identity['channel']);
        $this->ensureCooldownElapsed($request, $identity['fingerprint']);
        $this->throttleSend($request, $identity['fingerprint']);

        $challenge = $this->otp->issue($identity, (bool) ($this->otp->challengeFrom($request)?->remember));
        $request->session()->put(LoginOtpService::SESSION_KEY, [
            'challenge_id' => $challenge->id,
            'identifier' => $identity['normalized'],
            'fingerprint' => $identity['fingerprint'],
            'sent_at' => now()->timestamp,
        ]);

        return back()->with('status', 'If an active student account matches, a new login code is on its way.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $challenge = $this->otp->challengeFrom($request);

        if (! $challenge) {
            return redirect()->route('login');
        }

        $data = $request->validate(['otp' => ['required', 'string', 'max:20']]);
        $this->throttleVerify($request, $challenge);

        if (! $this->otp->verify($challenge, $data['otp'])) {
            throw ValidationException::withMessages([
                'otp' => 'That code is invalid or has expired. Please check it or request a new one.',
            ]);
        }

        RateLimiter::clear('login-otp-verify:challenge:'.$challenge->id);
        $challenge->refresh();
        $candidates = $this->otp->candidates($challenge);

        if ($candidates->count() !== 1) {
            return back();
        }

        return $this->login($request, $challenge, $candidates->first());
    }

    public function select(Request $request): RedirectResponse
    {
        $challenge = $this->otp->challengeFrom($request);
        $data = $request->validate(['user_id' => ['required', 'integer']]);

        if (! $challenge || ! $challenge->verified_at) {
            return redirect()->route('login');
        }

        $user = $this->otp->consume($challenge, (int) $data['user_id']);

        if (! $user) {
            throw ValidationException::withMessages(['user_id' => 'That student account is not available.']);
        }

        return $this->finishLogin($request, $user, $challenge->remember);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->otp->abandon($request);

        return redirect()->route('login');
    }

    private function login(Request $request, LoginOtpChallenge $challenge, User $candidate): RedirectResponse
    {
        $user = $this->otp->consume($challenge, $candidate->id);

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'identifier' => 'We could not complete that login. Please request a new code.',
            ]);
        }

        return $this->finishLogin($request, $user, $challenge->remember);
    }

    private function finishLogin(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->forget(LoginOtpService::SESSION_KEY);
        $request->session()->regenerate();

        if ($request->session()->has('pending_enroll_ids')) {
            return redirect()->route('student.exams.resume-enroll');
        }

        return redirect()->intended(route('student.dashboard', absolute: false));
    }

    private function throttleSend(Request $request, string $fingerprint): void
    {
        $this->hitOrFail('login-otp-send:ip:'.$request->ip(), 10, 900, 'identifier');
        $this->hitOrFail('login-otp-send:id:'.$fingerprint, 5, 900, 'identifier');
        $this->hitOrFail('login-otp-send:daily:'.$fingerprint, 15, 86400, 'identifier');
    }

    private function throttleVerify(Request $request, LoginOtpChallenge $challenge): void
    {
        $this->hitOrFail('login-otp-verify:ip:'.$request->ip(), 30, 900, 'otp');
        $this->hitOrFail('login-otp-verify:challenge:'.$challenge->id, LoginOtpService::MAX_ATTEMPTS, 900, 'otp');
    }

    private function hitOrFail(string $key, int $max, int $decay, string $field): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                $field => "Too many attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        RateLimiter::hit($key, $decay);
    }

    private function ensureCooldownElapsed(Request $request, string $fingerprint): void
    {
        $state = $request->session()->get(LoginOtpService::SESSION_KEY, []);
        $elapsed = now()->timestamp - (int) ($state['sent_at'] ?? 0);

        if (($state['fingerprint'] ?? null) === $fingerprint && $elapsed < LoginOtpService::RESEND_COOLDOWN_SECONDS) {
            $remaining = LoginOtpService::RESEND_COOLDOWN_SECONDS - $elapsed;
            throw ValidationException::withMessages([
                'identifier' => "Please wait {$remaining} seconds before requesting another code.",
            ]);
        }
    }

    private function ensureChannelAvailable(string $channel): void
    {
        if (! $this->otp->channelIsConfigured($channel)) {
            $label = $channel === 'whatsapp' ? 'WhatsApp' : 'Email';
            throw ValidationException::withMessages([
                'identifier' => "{$label} login is temporarily unavailable. Please use your password instead.",
            ]);
        }
    }
}
