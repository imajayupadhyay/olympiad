<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function student(array $overrides = []): User
    {
        $class = ClassLevel::firstOrCreate(
            ['level' => 5],
            ['label' => 'Class 5', 'is_active' => true, 'sort_order' => 5],
        );

        return User::factory()->create(array_merge([
            'role'           => 'student',
            'is_active'      => true,
            'class_level_id' => $class->id,
        ], $overrides));
    }

    public function test_dashboard_nudges_password_change_while_on_generated_password(): void
    {
        $user = $this->student(['password_changed_at' => null]);

        $this->actingAs($user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Dashboard/Index')
                ->where('onboarding.must_change_password', true)
                ->where('onboarding.profile.percent', fn ($p) => is_int($p) && $p >= 0 && $p <= 100)
            );
    }

    public function test_dashboard_does_not_nudge_after_password_changed(): void
    {
        $user = $this->student(['password_changed_at' => now()]);

        $this->actingAs($user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('onboarding.must_change_password', false)
            );
    }

    public function test_changing_password_stamps_the_flag(): void
    {
        $user = $this->student([
            'password'            => Hash::make('Generated@123'),
            'password_changed_at' => null,
        ]);

        $this->actingAs($user)
            ->put(route('student.profile.password'), [
                'current_password'      => 'Generated@123',
                'password'              => 'MyOwnPass@456',
                'password_confirmation' => 'MyOwnPass@456',
            ]);

        $this->assertNotNull($user->fresh()->password_changed_at);
    }

    public function test_profile_completion_reflects_filled_fields(): void
    {
        $bare = $this->student([
            'phone' => null, 'dob' => null, 'school' => null, 'school_address' => null,
            'city' => null, 'pincode' => null, 'state' => null, 'photo' => null,
        ]);
        // name + email + class filled → 3 of 11.
        $this->assertSame(3, $bare->profileCompletion()['filled']);
        $this->assertSame(11, $bare->profileCompletion()['total']);
        $this->assertSame(27, $bare->profileCompletion()['percent']);

        $full = $this->student([
            'phone' => '9990001112', 'dob' => '2012-01-01', 'school' => 'DPS', 'school_address' => 'MG Road',
            'city' => 'Pune', 'pincode' => '411001', 'state' => 'Maharashtra', 'photo' => 'photos/x.jpg',
        ]);
        $this->assertSame(100, $full->profileCompletion()['percent']);
        $this->assertEmpty($full->profileCompletion()['missing']);
    }
}
