<?php

namespace Tests\Feature\Auth;

use App\Models\ClassLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_without_choosing_a_password(): void
    {
        $classLevel = ClassLevel::create(['level' => 5, 'label' => 'Class 5', 'is_active' => true, 'sort_order' => 5]);

        // Short form: no password fields — the server generates and emails one.
        $response = $this->post('/register', [
            'name'           => 'Test User',
            'email'          => 'test@example.com',
            'class_level_id' => $classLevel->id,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('register.olympiads', absolute: false));

        // A usable, hashed password was set even though none was submitted.
        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertNotEmpty($user->password);
        $this->assertNotSame('', $user->password);
        $this->assertFalse(Hash::check('', $user->password));
    }
}
