<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ExamSessionException;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamEnrollment;
use App\Services\ExamSessionBoardService;
use App\Services\ExamSessionService;
use App\Support\LocalTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ongoing Exams: watch live sessions and recover them. Every write goes through
 * ExamSessionService (row lock + audit event); this controller only validates input.
 */
class ExamSessionController extends Controller
{
    public function __construct(
        private ExamSessionService $sessions,
        private ExamSessionBoardService $board,
    ) {}

    public function index(Request $request): Response
    {
        $tab = in_array($request->query('tab'), ExamSessionBoardService::TABS, true) ? $request->query('tab') : 'live';
        $search = $request->query('search');

        $exams = $this->board->relevantExams();
        $examIds = $request->filled('exam_id') && $exams->contains('id', (int) $request->query('exam_id'))
            ? [(int) $request->query('exam_id')]
            : $exams->pluck('id')->all();

        return Inertia::render('Admin/ExamSessions/Index', [
            'tab' => $tab,
            'filters' => ['exam_id' => $request->query('exam_id', ''), 'search' => $search ?? ''],
            'exams' => $exams->map->only(['id', 'name', 'exam_code'])->values(),
            'groups' => fn () => $this->board->groups($exams->whereIn('id', $examIds)),
            'stats' => fn () => $this->board->stats($examIds),
            'sessions' => fn () => $tab === 'not_started' ? null : $this->board->sessions($tab, $examIds, $search),
            'notStarted' => fn () => $tab === 'not_started' ? $this->board->notStarted($examIds, $search) : null,
            'limits' => ['max_minutes' => ExamSessionService::MAX_GRANT_MINUTES, 'max_strikes' => ExamSessionService::MAX_STRIKES],
            'serverTime' => LocalTime::now()->format('g:i:s A').' '.LocalTime::abbreviation(),
        ]);
    }

    public function show(ExamAttempt $attempt): Response
    {
        return Inertia::render('Admin/ExamSessions/Show', [
            ...$this->board->detail($attempt),
            'limits' => ['max_minutes' => ExamSessionService::MAX_GRANT_MINUTES, 'max_strikes' => ExamSessionService::MAX_STRIKES],
        ]);
    }

    /* ───────────────────────── per-session actions ───────────────────────── */

    public function suspend(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $data = $this->input($request, reason: true);

        return $this->run(fn () => $this->sessions->suspend($attempt, $request->user(), $data['reason'], $data['notify']), 'Session paused. The student\'s timer is stopped.');
    }

    public function resume(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $data = $this->input($request);

        return $this->run(fn () => $this->sessions->resume($attempt, $request->user(), $data['reason'], $data['notify']), 'Session resumed.');
    }

    public function addTime(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $data = $this->input($request, minutes: true);

        return $this->run(fn () => $this->sessions->addTime($attempt, $data['minutes'], $request->user(), $data['reason'], $data['notify']), "{$data['minutes']} minutes added.");
    }

    public function reopen(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $data = $this->input($request, reason: true, minutes: true);
        $clear = $request->boolean('clear_violations');

        return $this->run(function () use ($attempt, $data, $clear, $request) {
            $hadResult = (bool) $attempt->result;
            $this->sessions->reopen($attempt, $data['minutes'], $clear, $request->user(), $data['reason'], $data['notify']);

            return $hadResult ? 'Session reopened with answers kept. Its unreleased result was removed — process results again after the student submits.' : null;
        }, 'Session reopened with answers kept.');
    }

    public function reset(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $data = $this->input($request, reason: true, window: 'optional');

        return $this->run(fn () => $this->sessions->reset($attempt, $data['window'], $request->user(), $data['reason'], $data['notify']),
            'Session reset. The student can start again from the beginning; the old answers are archived in the timeline.',
            route('admin.exam-sessions.index', ['tab' => 'not_started', 'exam_id' => $attempt->exam_id]));
    }

    public function reschedule(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $data = $this->input($request, reason: true, window: 'required');
        $mode = $request->validate(['mode' => 'required|in:resume,fresh'])['mode'];

        return $this->run(fn () => $this->sessions->rescheduleAttempt($attempt, $data['window'], $mode, $request->user(), $data['reason'], $data['notify']),
            $mode === 'fresh' ? 'Rescheduled: the student starts again from scratch in the new window.' : 'Rescheduled: the student continues where they left off in the new window.',
            $mode === 'fresh' ? route('admin.exam-sessions.index', ['tab' => 'not_started', 'exam_id' => $attempt->exam_id]) : null);
    }

    public function forceSubmit(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $data = $this->input($request, reason: true);

        return $this->run(fn () => $this->sessions->forceSubmit($attempt, $request->user(), $data['reason'], $data['notify']), 'Session submitted with the answers saved so far.');
    }

    public function clearViolations(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $data = $this->input($request, reason: true);

        return $this->run(fn () => $this->sessions->clearViolations($attempt, $request->user(), $data['reason']), 'Tab-switch warnings cleared.');
    }

    /* ───────────────────────── students who never started ───────────────────────── */

    public function rescheduleEnrollment(Request $request, ExamEnrollment $enrollment): RedirectResponse
    {
        $data = $this->input($request, reason: true, window: 'required');

        return $this->run(fn () => $this->sessions->rescheduleEnrollment($enrollment, $data['window'], $request->user(), $data['reason'], $data['notify']), 'Personal window set. The student can start during it.');
    }

    public function unscheduleEnrollment(Request $request, ExamEnrollment $enrollment): RedirectResponse
    {
        $data = $this->input($request);

        return $this->run(fn () => $this->sessions->unschedule($enrollment, $request->user(), $data['reason']), 'Personal window removed. The exam\'s own window applies again.');
    }

    /* ───────────────────────── exam-wide incident controls ───────────────────────── */

    public function bulk(Request $request, Exam $exam, string $action): RedirectResponse
    {
        abort_unless(in_array($action, ['suspend', 'resume', 'add_time'], true), 404);
        $data = $this->input($request, reason: $action === 'suspend', minutes: $action === 'add_time');

        $count = $this->sessions->bulk($exam, $action, $data['minutes'], $request->user(), $data['reason'], $data['notify']);
        $what = ['suspend' => 'paused', 'resume' => 'resumed', 'add_time' => "given {$data['minutes']} more minutes"][$action];

        return back()->with($count ? 'success' : 'info', $count ? "{$count} session(s) {$what}." : 'No sessions needed that change.');
    }

    /* ───────────────────────── helpers ───────────────────────── */

    /**
     * @return array{reason: ?string, notify: bool, minutes: ?int, window: ?array}
     */
    private function input(Request $request, bool $reason = false, bool $minutes = false, ?string $window = null): array
    {
        $max = ExamSessionService::MAX_GRANT_MINUTES;
        $data = $request->validate([
            'reason' => [$reason ? 'required' : 'nullable', 'string', 'min:3', 'max:500'],
            'notify' => ['boolean'],
            'minutes' => [$minutes ? 'required' : 'nullable', 'integer', 'min:1', "max:{$max}"],
            'window_starts_at' => [$window === 'required' ? 'required' : 'nullable', 'date'],
            'window_ends_at' => [$window === 'required' || $request->filled('window_starts_at') ? 'required' : 'nullable', 'date', 'after:window_starts_at'],
        ], [
            'reason.required' => 'Add a short reason. It is kept in the session timeline.',
            'window_ends_at.after' => 'The window must end after it starts.',
        ]);

        return [
            'reason' => isset($data['reason']) ? trim($data['reason']) : null,
            'notify' => $request->boolean('notify', true),
            'minutes' => isset($data['minutes']) ? (int) $data['minutes'] : null,
            // datetime-local values are wall time on the display clock; store the instant (§5.7).
            'window' => ! empty($data['window_starts_at']) && ! empty($data['window_ends_at'])
                ? [LocalTime::parse($data['window_starts_at']), LocalTime::parse($data['window_ends_at'])]
                : null,
        ];
    }

    private function run(callable $action, string $success, ?string $redirect = null): RedirectResponse
    {
        try {
            $message = $action();
        } catch (ExamSessionException $e) {
            return back()->with('error', $e->getMessage());
        }

        $flash = is_string($message) ? $message : $success;

        return ($redirect ? redirect($redirect) : back())->with('success', $flash);
    }
}
