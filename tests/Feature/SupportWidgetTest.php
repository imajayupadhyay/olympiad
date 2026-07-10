<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SupportWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_lists_the_students_tickets_with_options_and_unread(): void
    {
        $student = $this->student();
        $ticket  = $this->ticketFor($student, ['student_unread' => 2]);

        $this->actingAs($student)
            ->getJson(route('student.support.widget'))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('tickets.0.id', $ticket->id)
            ->assertJsonPath('tickets.0.messages_count', 1)
            ->assertJsonPath('categories.exam', 'Exam')
            ->assertJsonPath('priorities.high', 'High');
    }

    public function test_widget_opens_a_thread_and_clears_student_unread(): void
    {
        $student = $this->student();
        $ticket  = $this->ticketFor($student, ['student_unread' => 3]);

        $this->actingAs($student)
            ->getJson(route('student.support.widget.show', $ticket->id))
            ->assertOk()
            ->assertJsonPath('ticket.id', $ticket->id)
            ->assertJsonPath('ticket.messages.0.body', 'Initial message.');

        $this->assertSame(0, $ticket->fresh()->student_unread);
    }

    public function test_widget_rejects_viewing_another_students_ticket(): void
    {
        $owner = $this->student();
        $other = $this->student(['email' => 'other@example.com']);
        $ticket = $this->ticketFor($owner);

        $this->actingAs($other)
            ->getJson(route('student.support.widget.show', $ticket->id))
            ->assertForbidden();
    }

    public function test_widget_creates_a_ticket_and_returns_its_thread(): void
    {
        Queue::fake();
        $student = $this->student();

        $this->actingAs($student)
            ->postJson(route('student.support.widget.store'), [
                'subject'  => 'Payment failed but money deducted',
                'category' => 'payment',
                'priority' => 'high',
                'body'     => 'I paid but the exam is still locked.',
            ])
            ->assertCreated()
            ->assertJsonPath('ticket.subject', 'Payment failed but money deducted')
            ->assertJsonPath('ticket.messages.0.body', 'I paid but the exam is still locked.');

        $ticket = SupportTicket::first();
        $this->assertSame('open', $ticket->status);
        $this->assertSame(1, $ticket->admin_unread);
    }

    public function test_widget_reply_appends_message_and_sets_pending(): void
    {
        $student = $this->student();
        // Admin has answered, so a student reply should reopen it as pending.
        $ticket  = $this->ticketFor($student, ['status' => 'answered']);

        $this->actingAs($student)
            ->postJson(route('student.support.widget.reply', $ticket->id), [
                'body' => 'Thanks, that worked!',
            ])
            ->assertOk()
            ->assertJsonPath('ticket.messages.1.body', 'Thanks, that worked!');

        $ticket->refresh();
        $this->assertSame('pending', $ticket->status);
        $this->assertSame('student', $ticket->last_reply_by);
    }

    public function test_widget_reply_blocked_on_closed_ticket(): void
    {
        $student = $this->student();
        $ticket  = $this->ticketFor($student, ['status' => 'closed']);

        $this->actingAs($student)
            ->postJson(route('student.support.widget.reply', $ticket->id), [
                'body' => 'Reopen please',
            ])
            ->assertForbidden();
    }

    public function test_widget_validates_new_ticket_input(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->postJson(route('student.support.widget.store'), [
                'subject'  => '',
                'category' => 'nope',
                'priority' => 'medium',
                'body'     => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subject', 'category', 'body']);
    }

    /* ── helpers ── */

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

    private function ticketFor(User $student, array $overrides = []): SupportTicket
    {
        $ticket = SupportTicket::create(array_merge([
            'user_id'       => $student->id,
            'subject'       => 'Cannot access my exam',
            'category'      => 'exam',
            'priority'      => 'high',
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
