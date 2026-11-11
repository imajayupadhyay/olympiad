<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\HallTicket;
use App\Models\HallTicketSetting;
use App\Models\StudentCustomField;
use App\Services\HallTicketLayoutService;
use App\Services\HallTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Hall Ticket Layout: fields, schedule columns, instructions, text rows,
 * layout options and custom student fields, with a live server-rendered preview.
 */
class HallTicketSettingController extends Controller
{
    private const KEYS = ['fields', 'exam_columns', 'text_rows', 'instructions', 'layout'];

    public function __construct(private HallTicketLayoutService $layout) {}

    public function index(): Response
    {
        $setting = HallTicketSetting::current()->load('updater:id,name');

        return Inertia::render('Admin/HallTicketSettings/Index', [
            'config' => $this->layout->config($setting),
            'catalogue' => $this->layout->catalogue(),
            'customFields' => StudentCustomField::ordered()->withCount(['values' => fn ($q) => $q->whereNotNull('value')])->get()
                ->map(fn (StudentCustomField $f) => [
                    'id' => $f->id, 'key' => $f->key, 'label' => $f->label, 'type' => $f->type, 'options' => $f->options ?? [],
                    'placeholder' => $f->placeholder, 'help_text' => $f->help_text, 'is_required' => $f->is_required,
                    'is_active' => $f->is_active, 'filled_count' => $f->values_count,
                ]),
            'fieldTypes' => StudentCustomField::TYPES,
            'previewTickets' => HallTicket::with('user:id,name')->latest('submitted_at')->limit(30)->get()
                ->map(fn (HallTicket $t) => ['id' => $t->id, 'label' => ($t->user?->name ?? 'Student').' · '.$t->statusLabel()]),
            'defaultInstructions' => HallTicketLayoutService::DEFAULT_INSTRUCTIONS,
            'savedAt' => $setting->updated_at?->format('j M Y, g:i A'),
            'savedBy' => $setting->updater?->name,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // validate() only returns nested keys that have their own rule, which would drop most
        // layout options; validate the shape, then hand the raw input to normalize().
        $request->validate($this->layout->rules());
        $this->layout->save($request->only(self::KEYS), $request->user()->id);

        return back()->with('success', 'Hall ticket layout saved. Every hall ticket now uses it.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->layout->reset($request->user()->id);

        return back()->with('success', 'Hall ticket layout reset to the default.');
    }

    /** Render an unsaved draft for the live preview (JSON; nothing is stored). */
    public function preview(Request $request, HallTicketService $hallTickets): JsonResponse
    {
        $data = $request->validate([
            ...$this->layout->rules(),
            'ticket_id' => ['nullable', 'integer', 'exists:hall_tickets,id'],
            'sample_status' => ['nullable', 'in:approved,pending'],
        ]);

        $context = filled($data['ticket_id'] ?? null)
            ? $hallTickets->context($ticket = HallTicket::findOrFail($data['ticket_id']), fn (string $type) => route('admin.hall-tickets.document', [$ticket->id, $type]))
            : $this->layout->sampleContext($data['sample_status'] ?? 'approved');

        return response()->json(['ticket' => $this->layout->render($context, $this->layout->normalize($request->only(self::KEYS)))]);
    }
}
