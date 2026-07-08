<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_support_reply_template_is_seeded(): void
    {
        $this->assertDatabaseHas('email_templates', ['key' => 'support_ticket_reply']);
    }

    public function test_student_can_raise_a_ticket(): void
    {
        Queue::fake();
        $student = $this->student();

        $response = $this->actingAs($student)->post(route('student.support.store'), [
            'subject'  => 'Cannot access my exam',
            'category' => 'exam',
            'priority' => 'high',
            'body'     => 'The start button does nothing.',
        ]);

        $ticket = SupportTicket::first();
        $response->assertRedirect(route('student.support.show', $ticket->id));

        $this->assertSame('open', $ticket->status);
        $this->assertSame('student', $ticket->last_reply_by);
        $this->assertSame(1, $ticket->admin_unread);
        $this->assertSame(0, $ticket->student_unread);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id'   => $ticket->id,
            'author_role' => 'student',
            'body'        => 'The start button does nothing.',
        ]);
    }

    public function test_student_cannot_view_another_students_ticket(): void
    {
        $owner  = $this->student();
        $other  = $this->student(['email' => 'other@example.com']);
        $ticket = $this->ticketFor($owner);

        $this->actingAs($other)
            ->get(route('student.support.show', $ticket->id))
            ->assertForbidden();
    }

    public function test_admin_reply_answers_ticket_notifies_student_and_queues_email(): void
    {
        Queue::fake();
        $student = $this->student();
        $admin   = $this->admin();
        $ticket  = $this->ticketFor($student);

        $this->actingAs($admin)
            ->post(route('admin.support.reply', $ticket->id), ['body' => 'We are on it — try again now.'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('answered', $ticket->status);
        $this->assertSame('admin', $ticket->last_reply_by);
        $this->assertSame(1, $ticket->student_unread);

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id'   => $ticket->id,
            'author_role' => 'admin',
        ]);
        $this->assertDatabaseHas('student_notifications', [
            'user_id' => $student->id,
            'is_read' => false,
        ]);
        $this->assertDatabaseHas('email_logs', [
            'template_key'      => 'support_ticket_reply',
            'recipient_user_id' => $student->id,
        ]);
    }

    public function test_student_reply_sets_pending_and_bumps_admin_unread(): void
    {
        Queue::fake();
        $student = $this->student();
        $ticket  = $this->ticketFor($student, ['status' => 'answered', 'admin_unread' => 0]);

        $this->actingAs($student)
            ->post(route('student.support.reply', $ticket->id), ['body' => 'Still broken.'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('pending', $ticket->status);
        $this->assertSame('student', $ticket->last_reply_by);
        $this->assertSame(1, $ticket->admin_unread);
    }

    public function test_opening_a_ticket_clears_the_viewers_unread_counter(): void
    {
        $student = $this->student();
        $ticket  = $this->ticketFor($student, ['student_unread' => 3, 'admin_unread' => 2]);

        // Student opening clears their own counter only.
        $this->actingAs($student)->get(route('student.support.show', $ticket->id))->assertOk();
        $ticket->refresh();
        $this->assertSame(0, $ticket->student_unread);
        $this->assertSame(2, $ticket->admin_unread);

        // Admin opening clears the admin counter only.
        $this->actingAs($this->admin())->get(route('admin.support.show', $ticket->id))->assertOk();
        $ticket->refresh();
        $this->assertSame(0, $ticket->admin_unread);
    }

    public function test_student_cannot_reply_to_a_closed_ticket(): void
    {
        $student = $this->student();
        $ticket  = $this->ticketFor($student, ['status' => 'closed']);

        $this->actingAs($student)
            ->post(route('student.support.reply', $ticket->id), ['body' => 'Hello?'])
            ->assertForbidden();
    }

    public function test_admin_can_update_status_and_assignee(): void
    {
        $student = $this->student();
        $admin   = $this->admin();
        $ticket  = $this->ticketFor($student);

        $this->actingAs($admin)
            ->patch(route('admin.support.status', $ticket->id), [
                'status'      => 'resolved',
                'assigned_to' => $admin->id,
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertSame($admin->id, $ticket->assigned_to);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function classLevel(): ClassLevel
    {
        return ClassLevel::first() ?: ClassLevel::create([
            'level' => 5, 'label' => 'Class 5', 'is_active' => true, 'sort_order' => 5,
        ]);
    }

    private function student(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role'              => 'student',
            'is_active'         => true,
            'class_level_id'    => $this->classLevel()->id,
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function admin(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role'              => 'admin',
            'is_active'         => true,
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function ticketFor(User $student, array $overrides = []): SupportTicket
    {
        $ticket = SupportTicket::create(array_merge([
            'user_id'       => $student->id,
            'subject'       => 'Test ticket',
            'category'      => 'other',
            'priority'      => 'medium',
            'status'        => 'open',
            'last_reply_by' => 'student',
            'last_reply_at' => now(),
            'admin_unread'  => 1,
        ], $overrides));

        $ticket->messages()->create([
            'user_id'     => $student->id,
            'author_role' => 'student',
            'body'        => 'Initial message.',
        ]);

        return $ticket;
    }
}
