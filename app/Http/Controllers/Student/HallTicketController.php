<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\HallTicketException;
use App\Http\Controllers\Controller;
use App\Models\HallTicket;
use App\Services\HallTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HallTicketController extends Controller
{
    public function __construct(private HallTicketService $hallTickets) {}

    public function index(Request $request): Response
    {
        $user = $request->user()->load('hallTicket');
        $summary = $this->hallTickets->summary($user);

        // Before the first submission an eligible student previews an unsaved draft.
        $ticket = $user->hallTicket
            ?? ($summary['eligibility']['eligible'] ? (new HallTicket(['status' => 'draft']))->setRelation('user', $user)->forceFill(['user_id' => $user->id]) : null);

        return Inertia::render('Student/HallTicket/Index', [
            'summary' => $summary,
            'ticket' => $ticket ? $this->payload($ticket) : null,
        ]);
    }

    /** Submit (or resubmit after a rejection) for admin verification. */
    public function store(Request $request): RedirectResponse
    {
        try {
            $ticket = $this->hallTickets->submit($request->user());
        } catch (HallTicketException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $ticket->isApproved()
            ? 'Your hall ticket is already approved.'
            : 'Profile submitted successfully. Your hall ticket is now awaiting admin verification.');
    }

    /** Print-ready page; only for an approved ticket. */
    public function print(Request $request): Response|RedirectResponse
    {
        $ticket = $request->user()->hallTicket()->first();

        if (! $ticket?->isApproved()) {
            return redirect()->route('student.hall-ticket')->with('error', 'Your hall ticket can be printed once it is approved.');
        }

        return Inertia::render('Student/HallTicket/Print', ['ticket' => $this->payload($ticket)]);
    }

    private function payload(HallTicket $ticket): array
    {
        return $this->hallTickets->ticket($ticket, fn (string $type) => route('student.profile.documents.show', [
            'type' => $type,
            'v' => substr(md5((string) $ticket->user->getAttribute($type)), 0, 8),
        ]));
    }
}
