<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\HallTicketException;
use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\HallTicket;
use App\Models\StudentCustomField;
use App\Services\HallTicketLayoutService;
use App\Services\HallTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hall Tickets: review submitted student details and documents, approve or reject,
 * and print approved tickets. Every write goes through HallTicketService.
 */
class HallTicketController extends Controller
{
    public function __construct(private HallTicketService $hallTickets) {}

    public function index(Request $request): Response
    {
        $filters = [
            'status' => (string) $request->query('status', ''),
            'class_level_id' => (string) $request->query('class_level_id', ''),
            'search' => (string) $request->query('search', ''),
        ];

        $tickets = $this->hallTickets->paginate($filters)->through(fn (HallTicket $t) => [
            'id' => $t->id,
            'status' => $t->status,
            'status_label' => $t->statusLabel(),
            'roll_number' => $t->roll_number,
            'submitted_at' => $t->submitted_at?->format('j M Y, g:i A'),
            'reviewed_at' => $t->reviewed_at?->format('j M Y'),
            'reviewer' => $t->reviewer?->name,
            'submission_count' => $t->submission_count,
            'student' => [
                'name' => $t->user?->name,
                'email' => $t->user?->email,
                'phone' => $t->user?->phone,
                'class' => $t->user?->classLevel?->label,
                'school' => $t->user?->school,
                'city' => collect([$t->user?->city, $t->user?->state])->filter()->implode(', '),
                'photo_url' => route('admin.hall-tickets.document', [$t->id, 'passport_photo']),
            ],
        ]);

        return Inertia::render('Admin/HallTickets/Index', [
            'tickets' => $tickets,
            'stats' => $this->hallTickets->stats(),
            'filters' => $filters,
            'classLevels' => ClassLevel::active(),
        ]);
    }

    public function show(HallTicket $hallTicket): Response
    {
        $user = $hallTicket->user()->with('classLevel')->firstOrFail();
        $eligibility = $this->hallTickets->eligibility($user);

        return Inertia::render('Admin/HallTickets/Show', [
            'ticketId' => $hallTicket->id,
            'ticket' => $this->payload($hallTicket),
            'review' => [
                'status' => $hallTicket->status,
                'status_label' => $hallTicket->statusLabel(),
                'submitted_at' => $hallTicket->submitted_at?->format('j M Y, g:i A'),
                'submission_count' => $hallTicket->submission_count,
                'reviewed_at' => $hallTicket->reviewed_at?->format('j M Y, g:i A'),
                'reviewer' => $hallTicket->reviewer?->name,
                'rejection_reason' => $hallTicket->rejection_reason,
                'percent' => $eligibility['percent'],
                'missing' => $eligibility['missing'],
            ],
            // Everything the student submitted, independent of what the layout prints.
            'details' => $this->submittedDetails($hallTicket),
            'student' => [
                'id' => $user->id,
                'name' => $user->name,
                'registered_at' => $user->created_at?->format('j M Y'),
                'email_verified' => (bool) $user->email_verified_at,
                'phone_verified' => (bool) $user->phone_verified_at,
            ],
            'documents' => collect(HallTicketService::DOCUMENTS)->map(fn (array $doc, string $type) => [
                'type' => $type,
                'label' => $doc['label'],
                'url' => $user->getAttribute($type) ? route('admin.hall-tickets.document', [$hallTicket->id, $type]) : null,
                'is_pdf' => str_ends_with((string) $user->getAttribute($type), '.pdf'),
            ])->values(),
        ]);
    }

    public function approve(Request $request, HallTicket $hallTicket): RedirectResponse
    {
        try {
            $ticket = $this->hallTickets->approve($hallTicket, $request->user());
        } catch (HallTicketException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Hall ticket approved. Roll No. {$ticket->roll_number} assigned and the student has been notified.");
    }

    public function reject(Request $request, HallTicket $hallTicket): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);

        try {
            $this->hallTickets->reject($hallTicket, $request->user(), trim($data['reason']));
        } catch (HallTicketException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Hall ticket rejected. The student can correct their details and submit again.');
    }

    public function print(HallTicket $hallTicket): Response|RedirectResponse
    {
        if (! $hallTicket->isApproved()) {
            return redirect()->route('admin.hall-tickets.show', $hallTicket)->with('error', 'Only an approved hall ticket can be printed.');
        }

        return Inertia::render('Admin/HallTickets/Print', ['ticket' => $this->payload($hallTicket), 'ticketId' => $hallTicket->id]);
    }

    public function document(HallTicket $hallTicket, string $type)
    {
        return $this->hallTickets->documentResponse($hallTicket->user, $type);
    }

    /** @return list<array{label:string, value:?string, mono:bool}> */
    private function submittedDetails(HallTicket $ticket): array
    {
        $values = $this->hallTickets->context($ticket, fn () => null)['values'];
        $labels = collect(HallTicketLayoutService::FIELDS)->map(fn ($m) => $m['label']);
        foreach (StudentCustomField::active()->ordered()->get() as $field) {
            $labels[$field->ticketKey()] = $field->label;
        }

        return $labels->except(['roll_number', 'olympiads', 'exam_centre', 'city', 'state', 'pincode'])
            ->map(fn ($label, $key) => [
                'label' => $label,
                'value' => $values[$key] ?? null,
                'mono' => (bool) (HallTicketLayoutService::FIELDS[$key]['mono'] ?? false),
            ])->values()->all();
    }

    private function payload(HallTicket $ticket): array
    {
        return $this->hallTickets->ticket($ticket, fn (string $type) => route('admin.hall-tickets.document', [$ticket->id, $type]));
    }
}
