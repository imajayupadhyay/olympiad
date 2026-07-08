<?php

namespace App\Services;

use App\Models\StudentNotification;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportTicketService
{
    public function __construct(protected ManagedEmailService $emails) {}

    /**
     * Open a new ticket for a student, seeding it with their first message.
     */
    public function createTicket(User $student, array $data): SupportTicket
    {
        return DB::transaction(function () use ($student, $data) {
            $ticket = SupportTicket::create([
                'user_id'       => $student->id,
                'subject'       => $data['subject'],
                'category'      => $data['category'] ?? 'other',
                'priority'      => $data['priority'] ?? 'medium',
                'status'        => 'open',
                'last_reply_by' => 'student',
                'last_reply_at' => now(),
                'admin_unread'  => 1,
            ]);

            $ticket->messages()->create([
                'user_id'     => $student->id,
                'author_role' => 'student',
                'body'        => $data['body'],
            ]);

            return $ticket;
        });
    }

    /**
     * Append a reply from either party and move the ticket into the matching
     * state. When the admin replies, the student is notified (in-app + email).
     */
    public function reply(SupportTicket $ticket, User $author, string $body): TicketMessage
    {
        $isAdmin = $author->isAdmin();

        $message = DB::transaction(function () use ($ticket, $author, $body, $isAdmin) {
            $message = $ticket->messages()->create([
                'user_id'     => $author->id,
                'author_role' => $isAdmin ? 'admin' : 'student',
                'body'        => $body,
            ]);

            $ticket->last_reply_by = $isAdmin ? 'admin' : 'student';
            $ticket->last_reply_at = now();

            if ($isAdmin) {
                $ticket->status = 'answered';
                $ticket->increment('student_unread');
            } else {
                // A student reply reopens a resolved/closed ticket into the queue.
                $ticket->status = 'pending';
                $ticket->increment('admin_unread');
            }

            $ticket->save();

            return $message;
        });

        if ($isAdmin) {
            $this->notifyStudent($ticket, $body);
        }

        return $message;
    }

    /** Clear the student's unread counter when they open the thread. */
    public function markReadForStudent(SupportTicket $ticket): void
    {
        if ($ticket->student_unread > 0) {
            $ticket->update(['student_unread' => 0]);
        }
    }

    /** Clear the admin's unread counter when an admin opens the thread. */
    public function markReadForAdmin(SupportTicket $ticket): void
    {
        if ($ticket->admin_unread > 0) {
            $ticket->update(['admin_unread' => 0]);
        }
    }

    public function setStatus(SupportTicket $ticket, string $status): void
    {
        $ticket->update(['status' => $status]);
    }

    public function assign(SupportTicket $ticket, ?int $adminId): void
    {
        $ticket->update(['assigned_to' => $adminId]);
    }

    /**
     * Deliver an admin reply to the student via the in-app bell + a queued email.
     */
    protected function notifyStudent(SupportTicket $ticket, string $body): void
    {
        $student = $ticket->user;

        if (! $student) {
            return;
        }

        $snippet = Str::limit(trim($body), 140);
        $link    = route('student.support.show', $ticket->id, false);

        StudentNotification::create([
            'user_id'             => $student->id,
            'notification_log_id' => null,
            'title'               => 'Support reply: '.$ticket->subject,
            'message'             => $snippet,
            'link'                => $link,
            'is_read'             => false,
        ]);

        $this->emails->queue(
            'support_ticket_reply',
            $student,
            $this->emails->supportTicketVariables($student, $ticket, $snippet),
            ['related_type' => SupportTicket::class, 'related_id' => $ticket->id]
        );
    }
}
