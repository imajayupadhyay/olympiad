<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactLeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_submit_the_contact_form(): void
    {
        $response = $this->post(route('contact.store'), [
            'name'    => 'Aarav Mehta',
            'email'   => 'aarav@example.com',
            'phone'   => '9876543210',
            'message' => 'I need help enrolling for the maths olympiad.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('leads', [
            'name'   => 'Aarav Mehta',
            'email'  => 'aarav@example.com',
            'phone'  => '9876543210',
            'source' => 'homepage_contact',
        ]);
    }

    public function test_phone_is_optional(): void
    {
        $this->post(route('contact.store'), [
            'name'    => 'No Phone',
            'email'   => 'nophone@example.com',
            'message' => 'Just a question.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', ['email' => 'nophone@example.com']);
    }

    public function test_contact_form_validates_required_fields(): void
    {
        $response = $this->post(route('contact.store'), [
            'name'    => '',
            'email'   => 'not-an-email',
            'message' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'message']);
        $this->assertSame(0, Lead::count());
    }

    public function test_guest_cannot_access_admin_forms(): void
    {
        // Guests hit the `auth` middleware first, which redirects to the login screen.
        $this->get(route('admin.forms.index'))->assertRedirect('/login');
    }

    public function test_admin_can_list_and_delete_leads(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'email_verified_at' => now()]);
        $lead  = Lead::create([
            'name' => 'Test', 'email' => 't@example.com', 'message' => 'Hello', 'source' => 'homepage_contact',
        ]);

        $this->actingAs($admin)->get(route('admin.forms.index'))->assertOk();

        $this->actingAs($admin)
            ->delete(route('admin.forms.destroy', $lead->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }
}
