<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ClassLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('student.profile'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $classLevel = ClassLevel::create([
            'level' => 6,
            'label' => 'Class 6',
            'is_active' => true,
            'sort_order' => 6,
        ]);
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->put(route('student.profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'class_level_id' => $classLevel->id,
                'phone' => '+919876543210',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame($classLevel->id, $user->class_level_id);
        $this->assertSame('+919876543210', $user->phone_e164);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $classLevel = ClassLevel::create([
            'level' => 6,
            'label' => 'Class 6',
            'is_active' => true,
            'sort_order' => 6,
        ]);
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->put(route('student.profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
                'class_level_id' => $classLevel->id,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_photo_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $photo = UploadedFile::fake()->image('avatar.jpg', 300, 300);

        $response = $this
            ->actingAs($user)
            ->post(route('student.profile.photo'), [
                'photo' => $photo,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $path = $user->refresh()->photo;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this
            ->actingAs($user)
            ->delete(route('student.profile.photo.delete'))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertNull($user->refresh()->photo);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_current_password_must_be_provided_to_change_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('student.profile'))
            ->put(route('student.profile.password'), [
                'current_password' => 'wrong-password',
                'password' => 'NewPassword@123',
                'password_confirmation' => 'NewPassword@123',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('student.profile'));

        $this->assertNotNull($user->fresh());
    }
}
