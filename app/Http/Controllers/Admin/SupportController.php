<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
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
        $filters = $request->only(['status', 'category', 'priority', 'search']);

        $tickets = SupportTicket::with('user:id,name,email')
            ->withCount('messages')
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($filters['search'] ?? null, function ($q, $v) {
                $q->where(function ($q) use ($v) {
                    $q->where('subject', 'like', "%{$v}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$v}%")->orWhere('email', 'like', "%{$v}%"));
                });
            })
            ->orderByRaw("CASE WHEN status IN ('resolved','closed') THEN 1 ELSE 0 END")
            ->latest('last_reply_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SupportTicket $t) => [
                'id'             => $t->id,
                'subject'        => $t->subject,
                'category'       => $t->category,
                'priority'       => $t->priority,
                'status'         => $t->status,
                'messages_count' => $t->messages_count,
                'admin_unread'   => $t->admin_unread,
                'last_reply_at'  => $t->last_reply_at,
                'created_at'     => $t->created_at,
                'student'        => $t->user ? ['id' => $t->user->id, 'name' => $t->user->name, 'email' => $t->user->email] : null,
            ]);

        return Inertia::render('Admin/Support/Index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'stats'   => [
                'open'           => SupportTicket::openish()->count(),
                'awaiting_reply' => SupportTicket::whereIn('status', ['open', 'pending'])->count(),
                'resolved_month' => SupportTicket::whereIn('status', ['resolved', 'closed'])
                    ->whereMonth('updated_at', now()->month)
                    ->whereYear('updated_at', now()->year)
                    ->count(),
                'total'          => SupportTicket::count(),
            ],
        ]);
    }

    public function show(SupportTicket $ticket): Response
    {
        $this->tickets->markReadForAdmin($ticket);

        $ticket->load(['user:id,name,email,phone', 'assignee:id,name', 'messages.author:id,name,role']);

        return Inertia::render('Admin/Support/Show', [
            'ticket' => [
                'id'            => $ticket->id,
                'subject'       => $ticket->subject,
                'category'      => $ticket->category,
                'priority'      => $ticket->priority,
                'status'        => $ticket->status,
                'created_at'    => $ticket->created_at,
                'last_reply_at' => $ticket->last_reply_at,
                'assigned_to'   => $ticket->assigned_to,
                'assignee'      => $ticket->assignee?->only(['id', 'name']),
                'student'       => $ticket->user?->only(['id', 'name', 'email', 'phone']),
                'messages'      => $ticket->messages->map(fn ($m) => [
                    'id'          => $m->id,
                    'author_role' => $m->author_role,
                    'author_name' => $m->author?->name ?? ($m->author_role === 'admin' ? 'Support Team' : 'Student'),
                    'body'        => $m->body,
                    'created_at'  => $m->created_at,
                ]),
            ],
            'admins'     => User::where('role', 'admin')->orderBy('name')->get(['id', 'name']),
            'statuses'   => ['open', 'pending', 'answered', 'resolved', 'closed'],
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $this->tickets->reply($ticket, $request->user(), $data['body']);

        return back()->with('success', 'Reply sent to the student.');
    }

    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'status'      => ['required', Rule::in(['open', 'pending', 'answered', 'resolved', 'closed'])],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $this->tickets->setStatus($ticket, $data['status']);

        if (array_key_exists('assigned_to', $data)) {
            $this->tickets->assign($ticket, $data['assigned_to']);
        }

        return back()->with('success', 'Ticket updated.');
    }
}
