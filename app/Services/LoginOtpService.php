<?php

namespace App\Services;

use App\Jobs\SendLoginOtp;
use App\Models\LoginOtpChallenge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginOtpService
{
    public const SESSION_KEY = 'login_otp';

    public const OTP_LENGTH = 6;

    public const EXPIRY_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        protected PhoneNumberService $phones,
        protected AuthenticationOtpDeliveryService $delivery,
    ) {}

    /** @return array{channel:string,normalized:string,masked:string,fingerprint:string} */
    public function parseIdentifier(string $identifier): array
    {
        $value = trim($identifier);

        if (str_contains($value, '@')) {
            $email = Str::lower($value);

            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || Str::length($email) > 255) {
                throw ValidationException::withMessages([
                    'identifier' => 'Enter a valid email address or WhatsApp number.',
                ]);
            }

            return [
                'channel' => 'email',
                'normalized' => $email,
                'masked' => $this->maskEmail($email),
                'fingerprint' => $this->fingerprint($email),
            ];
        }

        try {
            $phone = $this->phones->normalize($value);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'identifier' => 'Enter a valid email address or WhatsApp number.',
            ]);
        }

        return [
            'channel' => 'whatsapp',
            'normalized' => $phone,
            'masked' => $this->phones->mask($phone),
            'fingerprint' => $this->fingerprint($phone),
        ];
    }

    public function channelIsConfigured(string $channel): bool
    {
        return $this->delivery->isConfigured($channel);
    }

    public function issue(array $identity, bool $remember = false): LoginOtpChallenge
    {
        $candidates = $this->candidateUsers($identity['channel'], $identity['normalized']);
        $code = str_pad((string) random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);

        LoginOtpChallenge::where('identifier_fingerprint', $identity['fingerprint'])
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $challenge = LoginOtpChallenge::create([
            'channel' => $identity['channel'],
            'identifier_fingerprint' => $identity['fingerprint'],
            'masked_identifier' => $identity['masked'],
            'candidate_user_ids' => $candidates->pluck('id')->all(),
            'otp_hash' => Hash::make($this->codeDigest($code)),
            'attempts' => 0,
            'remember' => $remember,
            'delivery_status' => $candidates->isEmpty() ? 'suppressed' : 'queued',
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);

        if ($candidates->isNotEmpty()) {
            $name = $candidates->count() === 1
                ? Str::before(trim((string) $candidates->first()->name), ' ')
                : 'Parent/Student';

            SendLoginOtp::dispatch(
                $challenge->id,
                $identity['channel'],
                $identity['normalized'],
                $name,
                $code,
            );
        }

        return $challenge;
    }

    public function verify(LoginOtpChallenge $challenge, string $submittedCode): bool
    {
        $code = preg_replace('/\D+/', '', $submittedCode) ?? '';

        return DB::transaction(function () use ($challenge, $code): bool {
            $locked = LoginOtpChallenge::whereKey($challenge->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isUsable() || $locked->attempts >= self::MAX_ATTEMPTS) {
                return false;
            }

            if ($locked->verified_at) {
                return true;
            }

            $valid = strlen($code) === self::OTP_LENGTH
                && Hash::check($this->codeDigest($code), $locked->otp_hash);

            if (! $valid) {
                $attempts = $locked->attempts + 1;
                $locked->update([
                    'attempts' => $attempts,
                    'consumed_at' => $attempts >= self::MAX_ATTEMPTS ? now() : null,
                ]);

                return false;
            }

            $locked->update(['verified_at' => now()]);

            return true;
        });
    }

    public function consume(LoginOtpChallenge $challenge, int $userId): ?User
    {
        return DB::transaction(function () use ($challenge, $userId): ?User {
            $locked = LoginOtpChallenge::whereKey($challenge->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isUsable() || ! $locked->verified_at) {
                return null;
            }

            if (! in_array($userId, array_map('intval', $locked->candidate_user_ids), true)) {
                return null;
            }

            $user = User::whereKey($userId)
                ->where('role', 'student')
                ->where('is_active', true)
                ->first();

            if (! $user) {
                return null;
            }

            $locked->update([
                'selected_user_id' => $user->id,
                'consumed_at' => now(),
            ]);

            if ($locked->channel === 'email' && ! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->saveQuietly();
            }

            if ($locked->channel === 'whatsapp') {
                $user->forceFill(['phone_verified_at' => now()])->saveQuietly();
            }

            return $user;
        });
    }

    public function candidates(LoginOtpChallenge $challenge): Collection
    {
        if (! $challenge->verified_at || ! $challenge->isUsable()) {
            return collect();
        }

        $ids = array_map('intval', $challenge->candidate_user_ids);

        return User::whereIn('id', $ids)
            ->where('role', 'student')
            ->where('is_active', true)
            ->with('classLevel:id,label')
            ->get()
            ->sortBy(fn (User $user) => array_search($user->id, $ids, true))
            ->values();
    }

    public function challengeFrom(Request $request): ?LoginOtpChallenge
    {
        $id = $request->session()->get(self::SESSION_KEY.'.challenge_id');

        if (! $id) {
            return null;
        }

        $challenge = LoginOtpChallenge::find($id);

        if (! $challenge || ! $challenge->isUsable()) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $challenge;
    }

    public function pageState(Request $request): array
    {
        $challenge = $this->challengeFrom($request);
        $candidates = $challenge ? $this->candidates($challenge) : collect();

        return [
            'step' => $challenge?->verified_at && $candidates->count() > 1 ? 'select' : ($challenge ? 'otp' : 'identifier'),
            'channel' => $challenge?->channel,
            'maskedIdentifier' => $challenge?->masked_identifier,
            'otpLength' => self::OTP_LENGTH,
            'expiryMinutes' => self::EXPIRY_MINUTES,
            'resendIn' => $challenge ? max(
                0,
                self::RESEND_COOLDOWN_SECONDS - now()->diffInSeconds($challenge->created_at, true)
            ) : 0,
            'candidates' => $candidates->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'class' => $user->classLevel?->label ?: 'Class not set',
                'school' => $user->school,
            ])->all(),
            'availability' => [
                'email' => $this->channelIsConfigured('email'),
                'whatsapp' => $this->channelIsConfigured('whatsapp'),
            ],
        ];
    }

    public function abandon(Request $request): void
    {
        if ($challenge = $this->challengeFrom($request)) {
            $challenge->update(['consumed_at' => now()]);
        }

        $request->session()->forget(self::SESSION_KEY);
    }

    public function fingerprint(string $normalizedIdentifier): string
    {
        return hash_hmac('sha256', $normalizedIdentifier, $this->pepper());
    }

    private function candidateUsers(string $channel, string $normalized): Collection
    {
        return User::query()
            ->where('role', 'student')
            ->where('is_active', true)
            ->when(
                $channel === 'email',
                fn ($query) => $query->whereRaw('LOWER(email) = ?', [$normalized]),
                fn ($query) => $query->where('phone_e164', $normalized),
            )
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    private function codeDigest(string $code): string
    {
        return hash_hmac('sha256', 'login|'.$code, $this->pepper());
    }

    private function pepper(): string
    {
        return (string) (config('services.auth_otp.pepper') ?: config('app.key'));
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $visible = Str::substr($local, 0, min(2, Str::length($local)));

        return $visible.str_repeat('•', max(1, Str::length($local) - Str::length($visible))).'@'.$domain;
    }
}
