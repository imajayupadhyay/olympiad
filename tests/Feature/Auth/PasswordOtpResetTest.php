<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordOtpResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('pw-otp-send:127.0.0.1');
        RateLimiter::clear('pw-otp-verify:127.0.0.1');
    }

    public function test_forgot_password_page_renders_at_email_step(): void
    {
        $this->get(route('password.request'))
            ->assertOk();
    }

    public function test_requesting_a_code_stores_only_a_hash_and_never_exposes_it(): void
    {
        $student = $this->student();

        $response = $this->from(route('password.request'))
            ->post(route('password.otp.send'), ['email' => $student->email]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $otp = PasswordResetOtp::where('email', $student->email)->first();
        $this->assertNotNull($otp);
        // Stored as a bcrypt hash, not the plaintext code.
        $this->assertStringStartsWith('$2y$', $otp->otp_hash);

        // The OTP is emailed, but never persisted anywhere readable: the
        // email_logs subject (the only body-adjacent field that is stored)
        // must not contain a 6-digit code.
        $this->assertDatabaseHas('email_logs', ['template_key' => 'password_reset_otp']);
        $subject = \DB::table('email_logs')->where('template_key', 'password_reset_otp')->value('subject');
        $this->assertDoesNotMatchRegularExpression('/\d{6}/', (string) $subject);

        // And the flow advanced server-side without leaking the code.
        $this->assertSame('otp', session('pw_otp.email') ? 'otp' : 'email');
    }

    public function test_request_is_enumeration_safe_for_unknown_email(): void
    {
        $response = $this->post(route('password.otp.send'), ['email' => 'nobody@example.com']);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        // No code row is created for a non-existent account…
        $this->assertDatabaseCount('password_reset_otps', 0);
        // …but the UI still advances to the code step, so the two cases are
        // indistinguishable to an attacker.
        $this->assertSame('nobody@example.com', session('pw_otp.email'));
    }

    public function test_admin_email_cannot_be_used_to_reset_via_student_flow(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'boss@example.com']);

        $this->post(route('password.otp.send'), ['email' => $admin->email]);

        $this->assertDatabaseCount('password_reset_otps', 0);
    }

    public function test_correct_code_verifies_and_unlocks_reset(): void
    {
        $student = $this->student();
        $this->seedOtp($student->email, '123456');

        $response = $this->withSession(['pw_otp' => ['email' => $student->email, 'sent_at' => now()->timestamp, 'verified' => false]])
            ->post(route('password.otp.verify'), ['otp' => '123456']);

        $response->assertSessionHasNoErrors();
        $this->assertTrue(session('pw_otp.verified'));
        $this->assertNotNull(PasswordResetOtp::where('email', $student->email)->first()->verified_at);
    }

    public function test_wrong_code_is_rejected_and_counts_an_attempt(): void
    {
        $student = $this->student();
        $this->seedOtp($student->email, '123456');

        $this->withSession(['pw_otp' => ['email' => $student->email, 'sent_at' => now()->timestamp, 'verified' => false]])
            ->post(route('password.otp.verify'), ['otp' => '000000'])
            ->assertSessionHasErrors('otp');

        $this->assertSame(1, PasswordResetOtp::where('email', $student->email)->first()->attempts);
    }

    public function test_code_self_destructs_after_max_attempts(): void
    {
        $student = $this->student();
        $this->seedOtp($student->email, '123456', attempts: 4);

        $this->withSession(['pw_otp' => ['email' => $student->email, 'sent_at' => now()->timestamp, 'verified' => false]])
            ->post(route('password.otp.verify'), ['otp' => '000000'])
            ->assertSessionHasErrors('otp');

        $this->assertDatabaseCount('password_reset_otps', 0);
    }

    public function test_expired_code_is_rejected(): void
    {
        $student = $this->student();
        $this->seedOtp($student->email, '123456', expiresAt: now()->subMinute());

        $this->withSession(['pw_otp' => ['email' => $student->email, 'sent_at' => now()->timestamp, 'verified' => false]])
            ->post(route('password.otp.verify'), ['otp' => '123456'])
            ->assertSessionHasErrors('otp');

        $this->assertGuest();
    }

    public function test_reset_requires_a_verified_session(): void
    {
        $student = $this->student();
        $this->seedOtp($student->email, '123456');

        // Session says NOT verified — the reset endpoint must refuse.
        $this->withSession(['pw_otp' => ['email' => $student->email, 'verified' => false]])
            ->post(route('password.otp.reset'), [
                'password' => 'NewPass123!',
                'password_confirmation' => 'NewPass123!',
            ])
            ->assertRedirect(route('password.request'));

        $this->assertGuest();
        $this->assertTrue(Hash::check('old-password', $student->fresh()->password));
    }

    public function test_verified_session_resets_password_and_logs_in(): void
    {
        $student = $this->student();
        $this->seedOtp($student->email, '123456');

        $response = $this->withSession(['pw_otp' => [
            'email' => $student->email,
            'verified' => true,
            'verified_at' => now()->timestamp,
        ]])->post(route('password.otp.reset'), [
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ]);

        $response->assertRedirect(route('student.dashboard', absolute: false));
        $this->assertAuthenticatedAs($student->fresh());

        $fresh = $student->fresh();
        $this->assertTrue(Hash::check('NewPass123!', $fresh->password));
        $this->assertNotNull($fresh->password_changed_at);
        // Code consumed and flow state cleared.
        $this->assertDatabaseCount('password_reset_otps', 0);
        $this->assertNull(session('pw_otp'));
    }

    /* ── helpers ── */

    private function student(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'student',
            'email' => 'student@example.com',
            'password' => Hash::make('old-password'),
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function seedOtp(string $email, string $code, int $attempts = 0, ?\Illuminate\Support\Carbon $expiresAt = null): PasswordResetOtp
    {
        return PasswordResetOtp::create([
            'email' => $email,
            'otp_hash' => Hash::make($code),
            'attempts' => $attempts,
            'expires_at' => $expiresAt ?? now()->addMinutes(10),
        ]);
    }
}
