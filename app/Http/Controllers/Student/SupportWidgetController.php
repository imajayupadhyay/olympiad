<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * JSON backend for the floating support chat widget (bottom-right launcher).
 * Mirrors the full Support page flow but returns JSON so the widget can drive
 * the whole conversation without a full Inertia page navigation.
 */
class SupportWidgetController extends Controller
{
    public function __construct(protected SupportTicketService $tickets) {}

    /** Ticket list + form options + live unread count for the panel. */
    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()->supportTickets()
            ->withCount('messages')
            ->latest('last_reply_at')
            ->limit(30)
            ->get()
            ->map(fn (SupportTicket $t) => $this->summary($t));

        return response()->json([
            'tickets'    => $tickets,
            'categories' => $this->categories(),
            'priorities' => $this->priorities(),
            'unread'     => (int) $request->user()->supportTickets()->where('student_unread', '>', 0)->count(),
        ]);
    }

    /** Full thread for one ticket; clears the student's unread counter. */
    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        $this->tickets->markReadForStudent($ticket);
        $ticket->load(['messages.author:id,name,role']);

        return response()->json(['ticket' => $this->thread($ticket)]);
    }

    /** Open a new ticket and return its freshly-seeded thread. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject'  => 'required|string|max:150',
            'category' => ['required', Rule::in(array_keys($this->categories()))],
            'priority' => ['required', Rule::in(array_keys($this->priorities()))],
            'body'     => 'required|string|max:5000',
        ]);

        $ticket = $this->tickets->createTicket($request->user(), $data);
        $ticket->load(['messages.author:id,name,role']);

        return response()->json(['ticket' => $this->thread($ticket)], 201);
    }

    /** Append a student reply and return the updated thread. */
    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        abort_if($ticket->status === 'closed', 403, 'This ticket is closed.');

        $data = $request->validate(['body' => 'required|string|max:5000']);

        $this->tickets->reply($ticket, $request->user(), $data['body']);
        $ticket->load(['messages.author:id,name,role']);

        return response()->json(['ticket' => $this->thread($ticket->fresh())]);
    }

    protected function summary(SupportTicket $t): array
    {
        return [
            'id'             => $t->id,
            'subject'        => $t->subject,
            'category'       => $t->category,
            'priority'       => $t->priority,
            'status'         => $t->status,
            'messages_count' => $t->messages_count,
            'student_unread' => $t->student_unread,
            'last_reply_at'  => $t->last_reply_at,
            'created_at'     => $t->created_at,
        ];
    }

    protected function thread(SupportTicket $ticket): array
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
