<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function __construct(protected SupportTicketService $tickets) {}

    public function index(Request $request): Response
    {
        $tickets = $request->user()->supportTickets()
            ->withCount('messages')
            ->latest('last_reply_at')
            ->paginate(12)
            ->through(fn (SupportTicket $t) => [
                'id'             => $t->id,
                'subject'        => $t->subject,
                'category'       => $t->category,
                'priority'       => $t->priority,
                'status'         => $t->status,
                'messages_count' => $t->messages_count,
                'student_unread' => $t->student_unread,
                'last_reply_at'  => $t->last_reply_at,
                'created_at'     => $t->created_at,
            ]);

        return Inertia::render('Student/Support/Index', [
            'tickets'    => $tickets,
            'categories' => $this->categories(),
            'priorities' => $this->priorities(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject'  => 'required|string|max:150',
            'category' => ['required', Rule::in(array_keys($this->categories()))],
            'priority' => ['required', Rule::in(array_keys($this->priorities()))],
            'body'     => 'required|string|max:5000',
        ]);

        $ticket = $this->tickets->createTicket($request->user(), $data);

        return redirect()
            ->route('student.support.show', $ticket->id)
            ->with('success', 'Your support ticket has been submitted. Our team will respond shortly.');
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        $this->tickets->markReadForStudent($ticket);

        $ticket->load(['messages.author:id,name,role']);

        return Inertia::render('Student/Support/Show', [
            'ticket' => $this->transformThread($ticket),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        abort_if($ticket->status === 'closed', 403, 'This ticket is closed.');

        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $this->tickets->reply($ticket, $request->user(), $data['body']);

        return back()->with('success', 'Reply sent.');
    }

    protected function transformThread(SupportTicket $ticket): array
    {
        return [
            'id'            => $ticket->id,
            'subject'       => $ticket->subject,
            'category'      => $ticket->category,
            'priority'      => $ticket->priority,
            'status'        => $ticket->status,
            'created_at'    => $ticket->created_at,
            'last_reply_at' => $ticket->last_reply_at,
            'messages'      => $ticket->messages->map(fn ($m) => [
                'id'          => $m->id,
                'author_role' => $m->author_role,
                'author_name' => $m->author?->name ?? ($m->author_role === 'admin' ? 'Support Team' : 'You'),
                'body'        => $m->body,
                'created_at'  => $m->created_at,
            ]),
        ];
    }

    protected function categories(): array
    {
        return [
            'payment'   => 'Payment',
            'exam'      => 'Exam',
            'technical' => 'Technical',
            'account'   => 'Account',
            'other'     => 'Other',
        ];
    }

    protected function priorities(): array
    {
        return [
            'low'    => 'Low',
            'medium' => 'Medium',
            'high'   => 'High',
        ];
    }
}
