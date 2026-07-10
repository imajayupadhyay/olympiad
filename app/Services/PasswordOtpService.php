<?php

namespace App\Services;

use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * OTP-based password reset for students.
 *
 * Security posture:
 *  - The 6-digit code is generated with a CSPRNG (random_int) and stored ONLY
 *    as a bcrypt hash. The plaintext exists solely inside the delivered email.
 *  - Verification is constant-time (Hash::check) and never reveals whether the
 *    email belongs to an account (enumeration-safe: request() is a no-op for
 *    unknown emails, and verify() fails identically).
 *  - Codes expire (EXPIRY_MINUTES) and self-destruct after MAX_ATTEMPTS failed
 *    tries, defeating online brute force.
 *  - No method ever returns the code to the caller.
 */
class PasswordOtpService
{
    public const OTP_LENGTH = 6;
    public const EXPIRY_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;

    public function __construct(protected ManagedEmailService $emails) {}

    /**
     * Issue and email a fresh code for the given email address.
     * Silently does nothing for a non-existent / non-student account so the
     * response is identical either way (no user enumeration).
     */
    public function request(string $email): void
    {
        $user = $this->studentByEmail($email);

        if (! $user) {
            return;
        }

        // Cryptographically-secure, zero-padded 6-digit code.
        $code = str_pad((string) random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);

        PasswordResetOtp::updateOrCreate(
            ['email' => $user->email],
            [
                'otp_hash'    => Hash::make($code),
                'attempts'    => 0,
                'verified_at' => null,
                'expires_at'  => now()->addMinutes(self::EXPIRY_MINUTES),
            ],
        );

        // queue() wraps these through variablesForUser() internally, adding
        // student_name/app_name/etc. The code is deliberately NOT placed in meta
        // (meta is persisted; the rendered body is not).
        $this->emails->queue(
            'password_reset_otp',
            $user,
            [
                'otp'                => $code,
                'otp_expiry_minutes' => self::EXPIRY_MINUTES,
            ],
            ['related_type' => User::class, 'related_id' => $user->id],
        );
    }

    /**
     * Verify a submitted code. Returns true only on an exact, unexpired match.
     * Increments the attempt counter and destroys the record once the cap is
     * reached. Always returns false (never throws) for unknown/expired codes.
     */
    public function verify(string $email, string $code): bool
    {
        $record = PasswordResetOtp::where('email', $email)->first();

        if (! $record || $record->isExpired() || $record->attempts >= self::MAX_ATTEMPTS) {
            // Clean up anything stale/exhausted so a new request starts fresh.
            $record?->delete();

            return false;
        }

        if (! Hash::check($code, $record->otp_hash)) {
            $record->increment('attempts');

            if ($record->attempts >= self::MAX_ATTEMPTS) {
                $record->delete();
            }

            return false;
        }

        $record->update(['verified_at' => now()]);

        return true;
    }

    /**
     * Finalise the reset: the caller has already proven possession of a verified
     * code (via the server-side session). Sets the new password, stamps the
     * change, invalidates the code, and returns the user for login.
     */
    public function reset(string $email, string $newPassword): ?User
    {
        $user = $this->studentByEmail($email);

        if (! $user) {
            return null;
        }

        $user->forceFill([
            'password'            => Hash::make($newPassword),
            'password_changed_at' => now(),
            'remember_token'      => \Illuminate\Support\Str::random(60),
        ])->save();

        PasswordResetOtp::where('email', $user->email)->delete();

        return $user;
    }

    protected function studentByEmail(string $email): ?User
    {
        return User::where('email', $email)->where('role', 'student')->first();
    }
}
