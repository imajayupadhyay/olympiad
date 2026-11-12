<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamEnrollment;
use App\Models\ExamSessionEvent;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read models for the admin Ongoing Exams page. Writes go through ExamSessionService.
 */
class ExamSessionBoardService
{
    public const TABS = ['live', 'attention', 'ended', 'not_started'];

    public function __construct(private ExamSessionService $sessions) {}

    /** Exams worth showing: live windows, open sessions, recent finishes, personal windows. */
    public function relevantExams(): Collection
    {
        $now = now();
        $recent = $now->copy()->subDays(7);

        $withSessions = ExamAttempt::where(fn ($q) => $q->where('status', 'in_progress')
            ->orWhere('submitted_at', '>=', $recent))->distinct()->pluck('exam_id');
        $withWindows = ExamEnrollment::where('status', 'enrolled')->whereNotNull('window_ends_at')
            ->where('window_ends_at', '>=', $recent)->distinct()->pluck('exam_id');

        return Exam::query()
            ->where('status', 'published')
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('starts_at', '<=', $now)->where(fn ($e) => $e->whereNull('ends_at')->orWhere('ends_at', '>=', $recent)))
                ->orWhereIn('id', $withSessions)
                ->orWhereIn('id', $withWindows))
            ->with('classLevel:id,label')
            ->withCount('questions')
            ->orderByDesc('starts_at')
            ->get(['id', 'name', 'exam_code', 'class_level_id', 'starts_at', 'ends_at', 'duration_minutes']);
    }

    /** Header cards per live exam with counts for bulk actions. */
    public function groups(Collection $exams): array
    {
        $ids = $exams->pluck('id');
        $onlineSince = now()->subSeconds(ExamSessionService::ONLINE_SECONDS);

        $open = ExamAttempt::whereIn('exam_id', $ids)->where('status', 'in_progress')
            ->selectRaw('exam_id, COUNT(*) as open_count')
            ->selectRaw('SUM(CASE WHEN suspended_at IS NOT NULL THEN 1 ELSE 0 END) as paused_count')
            ->selectRaw('SUM(CASE WHEN suspended_at IS NULL AND COALESCE(last_seen_at, started_at) >= ? THEN 1 ELSE 0 END) as online_count', [$onlineSince])
            ->groupBy('exam_id')->get()->keyBy('exam_id');
        $notStarted = $this->notStartedQuery($ids->all())->selectRaw('exam_enrollments.exam_id, COUNT(*) as c')
            ->groupBy('exam_enrollments.exam_id')->pluck('c', 'exam_enrollments.exam_id');

        return $exams->map(function (Exam $exam) use ($open, $notStarted) {
            $row = $open->get($exam->id);

            return [
                'id' => $exam->id,
                'name' => $exam->name,
                'exam_code' => $exam->exam_code,
                'class' => $exam->classLevel?->label,
                'availability' => $exam->availabilityState(),
                'window_label' => $this->sessions->windowLabel($exam->starts_at, $exam->ends_at) ?? (LocalTime::format($exam->starts_at, 'D, j M · g:i A').' onwards'),
                'duration_minutes' => $exam->duration_minutes,
                'open' => (int) ($row->open_count ?? 0),
                'paused' => (int) ($row->paused_count ?? 0),
                'online' => (int) ($row->online_count ?? 0),
                'not_started' => (int) ($notStarted[$exam->id] ?? 0),
            ];
        })->values()->all();
    }

    public function stats(array $examIds): array
    {
        $now = now();
        $open = ExamAttempt::whereIn('exam_id', $examIds)->where('status', 'in_progress');
        $seen = 'COALESCE(last_seen_at, started_at)';

        return [
            'live' => (clone $open)->whereNull('suspended_at')->where('deadline_at', '>', $now)
                ->whereRaw("{$seen} >= ?", [$now->copy()->subSeconds(ExamSessionService::ONLINE_SECONDS)])->count(),
            'offline' => (clone $open)->whereNull('suspended_at')->where('deadline_at', '>', $now)
                ->whereRaw("{$seen} < ?", [$now->copy()->subSeconds(ExamSessionService::ONLINE_SECONDS)])->count(),
            'paused' => (clone $open)->whereNotNull('suspended_at')->count(),
            'time_over' => (clone $open)->whereNull('suspended_at')->where('deadline_at', '<=', $now)->count(),
            'not_started' => $this->notStartedQuery($examIds)->count(),
            'ended_today' => ExamAttempt::whereIn('exam_id', $examIds)->whereIn('status', ExamAttempt::FINISHED)
                ->where('submitted_at', '>=', $now->copy()->startOfDay())->count(),
        ];
    }

    /** Attempt rows for the live / attention / ended tabs. */
    public function sessions(string $tab, array $examIds, ?string $search): LengthAwarePaginator
    {
        $now = now();
        $query = ExamAttempt::query()->whereIn('exam_id', $examIds)
            ->with(['user:id,name,email,phone,school,class_level_id', 'user.classLevel:id,label', 'exam:id,name,exam_code,duration_minutes,status,starts_at,ends_at', 'result:id,exam_attempt_id,is_released'])
            ->withCount(['events as device_switches' => fn ($q) => $q->where('type', 'device_switched')]);

        match ($tab) {
            'attention' => $query->where(fn ($q) => $q
                ->where(fn ($open) => $open->where('status', 'in_progress')->whereNull('suspended_at')->where(fn ($w) => $w
                    ->whereRaw('COALESCE(last_seen_at, started_at) < ?', [$now->copy()->subSeconds(60)])
                    ->orWhere('deadline_at', '<=', $now)
                    ->orWhere('violation_count', '>=', 2)))
                ->orWhere(fn ($ended) => $ended->whereIn('status', ['timed_out', 'auto_submitted'])->where('submitted_at', '>=', $now->copy()->subDay()))),
            'ended' => $query->whereIn('status', ExamAttempt::FINISHED)->where('submitted_at', '>=', $now->copy()->subDays(7)),
            default => $query->where('status', 'in_progress'),
        };

        $this->search($query, $search);

        $page = $query->orderByRaw($tab === 'ended' ? 'submitted_at DESC' : 'COALESCE(last_seen_at, started_at) ASC')
            ->paginate(50)->withQueryString();

        $attempts = $page->getCollection();
        $answered = $this->answeredCounts($attempts->pluck('id')->all());
        $positions = $this->paperPositions($attempts->pluck('exam_id')->unique()->all());
        $windows = ExamEnrollment::whereIn('exam_id', $attempts->pluck('exam_id'))->whereIn('user_id', $attempts->pluck('user_id'))
            ->whereNotNull('window_starts_at')->get()->keyBy(fn ($e) => $e->exam_id.'-'.$e->user_id);

        $page->setCollection($attempts->map(fn (ExamAttempt $attempt) => $this->row(
            $attempt, $answered[$attempt->id] ?? 0, $positions, $windows->get($attempt->exam_id.'-'.$attempt->user_id),
        )));

        return $page;
    }

    /** Enrolled students who never started (for the reschedule tab). */
    public function notStarted(array $examIds, ?string $search): LengthAwarePaginator
    {
        $query = $this->notStartedQuery($examIds)
            ->select('exam_enrollments.*')
            ->with(['user:id,name,email,phone,school,class_level_id', 'user.classLevel:id,label', 'exam:id,name,exam_code,starts_at,ends_at,duration_minutes']);
        $query->whereHas('user', fn ($q) => $this->searchUser($q, $search));

        $page = $query->orderBy('exam_enrollments.exam_id')->orderBy('exam_enrollments.id')->paginate(50)->withQueryString();
        $page->setCollection($page->getCollection()->map(function (ExamEnrollment $enrollment) {
            return [
                'id' => $enrollment->id,
                'student' => $this->student($enrollment->user),
                'exam' => ['id' => $enrollment->exam->id, 'name' => $enrollment->exam->name, 'exam_code' => $enrollment->exam->exam_code, 'duration_minutes' => $enrollment->exam->duration_minutes],
                'window_state' => $this->sessions->windowState($enrollment->exam, $enrollment),
                'personal_window' => $enrollment->hasPersonalWindow()
                    ? $this->sessions->windowLabel($enrollment->window_starts_at, $enrollment->window_ends_at) : null,
                'exam_window' => $this->sessions->windowLabel($enrollment->exam->starts_at, $enrollment->exam->ends_at),
            ];
        }));

        return $page;
    }

    /** Detail page payload: timing breakdown, per-question grid and the full timeline. */
    public function detail(ExamAttempt $attempt): array
    {
        $attempt->load(['user.classLevel:id,label', 'exam.questions', 'exam.sections:id,exam_id,name,sort_order', 'result']);
        $exam = $attempt->exam;
        $answers = $attempt->answers()->get()->keyBy('question_id');
        $sectionNames = $exam->sections->pluck('name', 'id');
        $enrollment = $this->sessions->enrollment($attempt->user_id, $exam->id);
        $lastRoom = ExamSessionEvent::where('exam_attempt_id', $attempt->id)->whereIn('type', ['room_opened', 'device_switched', 'started'])->latest('id')->first();

        $events = ExamSessionEvent::where('exam_id', $exam->id)->where('user_id', $attempt->user_id)
            ->with('actor:id,name')->latest('id')->limit(300)->get()
            ->map(fn (ExamSessionEvent $event) => [
                'id' => $event->id,
                'type' => $event->type,
                'label' => ExamSessionEvent::LABELS[$event->type] ?? $event->type,
                'actor' => $event->actor?->name,
                'reason' => $event->reason,
                'summary' => $this->eventSummary($event),
                'at' => LocalTime::format($event->created_at, 'j M, g:i:s A'),
                'previous_attempt' => $event->exam_attempt_id === null,
            ]);

        $attempt->device_switches = ExamSessionEvent::where('exam_attempt_id', $attempt->id)->where('type', 'device_switched')->count();

        return [
            'session' => $this->row($attempt, $answers->filter(fn ($a) => ! empty($a->selected_options))->count(),
                $this->paperPositions([$exam->id]), $enrollment?->hasPersonalWindow() ? $enrollment : null),
            'timing' => [
                'duration_seconds' => $exam->duration_minutes * 60,
                'extra_seconds' => $attempt->extra_seconds,
                'paused_seconds' => $attempt->paused_seconds + ($attempt->isPaused() ? (int) $attempt->suspended_at->diffInSeconds(now(), true) : 0),
                'used_seconds' => $attempt->isFinished() ? (int) $attempt->time_taken_seconds : $attempt->timeUsedSeconds(),
                'remaining_seconds' => $attempt->isOpen() ? $attempt->remainingSeconds() : 0,
                'started_at' => LocalTime::format($attempt->started_at, 'j M, g:i:s A'),
                'deadline_at' => LocalTime::format($attempt->deadline_at, 'j M, g:i:s A'),
                'submitted_at' => LocalTime::format($attempt->submitted_at, 'j M, g:i:s A'),
            ],
            'device' => [
                'ip' => $lastRoom->meta['ip'] ?? $attempt->ip_address,
                'user_agent' => $lastRoom->meta['user_agent'] ?? null,
            ],
            'questions' => $exam->questions->values()->map(fn ($q, $i) => [
                'number' => $i + 1,
                'id' => $q->id,
                'section' => $sectionNames->get($q->pivot->exam_section_id),
                'selected' => $answers->get($q->id)?->selected_options ?? [],
                'flagged' => (bool) ($answers->get($q->id)?->is_flagged),
                'current' => $attempt->current_question_id === $q->id,
            ]),
            'events' => $events,
        ];
    }

    /* ───────────────────────── internals ───────────────────────── */

    private function row(ExamAttempt $attempt, int $answered, array $positions, ?ExamEnrollment $window): array
    {
        $this->sessions->deadline($attempt);
        $connection = $this->sessions->connection($attempt);
        $total = $positions[$attempt->exam_id]['total'] ?? 0;

        return [
            'id' => $attempt->id,
            'student' => $this->student($attempt->user),
            'exam' => ['id' => $attempt->exam->id, 'name' => $attempt->exam->name, 'exam_code' => $attempt->exam->exam_code, 'duration_minutes' => $attempt->exam->duration_minutes],
            'status' => $attempt->status,
            'is_open' => $attempt->isOpen(),
            'is_paused' => $attempt->isPaused(),
            'connection' => $connection['state'],
            'offline_seconds' => $connection['offline_seconds'],
            'answered' => $answered,
            'total' => $total,
            'current_number' => $attempt->current_question_id ? ($positions[$attempt->exam_id]['map'][$attempt->current_question_id] ?? null) : null,
            'remaining_seconds' => $attempt->isOpen() ? $attempt->remainingSeconds() : 0,
            'last_seen' => ($attempt->last_seen_at ?? $attempt->started_at)?->diffForHumans(short: true),
            'started_at' => LocalTime::format($attempt->started_at, 'j M, g:i A'),
            'submitted_at' => LocalTime::format($attempt->submitted_at, 'j M, g:i A'),
            'violation_count' => $attempt->violation_count,
            'device_switches' => (int) ($attempt->device_switches ?? 0),
            'reopen_count' => $attempt->reopen_count,
            'lost_seconds' => $attempt->isOpen() && ! $attempt->isPaused() ? $this->sessions->lostSeconds($attempt) : 0,
            'suggested_reopen_minutes' => $attempt->isFinished() ? (int) ceil($this->sessions->suggestedReopenSeconds($attempt) / 60) : null,
            'remaining_minutes' => (int) max(1, ceil($attempt->remainingSeconds() / 60)),
            'result' => $attempt->result ? ($attempt->result->is_released ? 'released' : 'processed') : null,
            'personal_window' => $window ? $this->sessions->windowLabel($window->window_starts_at, $window->window_ends_at) : null,
        ];
    }

    private function student($user): array
    {
        return [
            'id' => $user?->id,
            'name' => $user?->name,
            'email' => $user?->email,
            'phone' => $user?->phone,
            'school' => $user?->school,
            'class' => $user?->classLevel?->label,
        ];
    }

    private function notStartedQuery(array $examIds): Builder
    {
        $now = now();
        $recent = $now->copy()->subDays(7);

        return ExamEnrollment::query()
            ->join('exams', 'exams.id', '=', 'exam_enrollments.exam_id')
            ->whereIn('exam_enrollments.exam_id', $examIds)
            ->where('exam_enrollments.status', 'enrolled')
            ->where('exams.status', 'published')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('exam_attempts')
                ->whereColumn('exam_attempts.exam_id', 'exam_enrollments.exam_id')
                ->whereColumn('exam_attempts.user_id', 'exam_enrollments.user_id'))
            ->where(fn ($q) => $q
                // Personal window still relevant…
                ->where('exam_enrollments.window_ends_at', '>=', $recent)
                // …or the exam window has opened and closed less than 7 days ago (or is open).
                ->orWhere(fn ($w) => $w->whereNull('exam_enrollments.window_ends_at')
                    ->where('exams.starts_at', '<=', $now)
                    ->where(fn ($e) => $e->whereNull('exams.ends_at')->orWhere('exams.ends_at', '>=', $recent))));
    }

    private function search(Builder $query, ?string $search): void
    {
        if (filled($search)) {
            $query->whereHas('user', fn ($q) => $this->searchUser($q, $search));
        }
    }

    private function searchUser($query, ?string $search)
    {
        if (blank($search)) {
            return $query;
        }
        $like = '%'.trim($search).'%';

        return $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like)->orWhere('school', 'like', $like));
    }

    /** @return array<int, int> attempt_id => answered */
    private function answeredCounts(array $attemptIds): array
    {
        return DB::table('answers')->whereIn('exam_attempt_id', $attemptIds)->get(['exam_attempt_id', 'selected_options'])
            ->filter(fn ($row) => ! empty(json_decode((string) $row->selected_options, true)))
            ->countBy('exam_attempt_id')->all();
    }

    /** @return array<int, array{total: int, map: array<int, int>}> paper position of each question */
    private function paperPositions(array $examIds): array
    {
        return DB::table('exam_questions')->whereIn('exam_id', $examIds)->orderBy('sort_order')->get(['exam_id', 'question_id'])
            ->groupBy('exam_id')
            ->map(fn ($rows) => [
                'total' => $rows->count(),
                'map' => $rows->values()->mapWithKeys(fn ($row, $i) => [$row->question_id => $i + 1])->all(),
            ])->all();
    }

    private function eventSummary(ExamSessionEvent $event): ?string
    {
        $m = $event->meta ?? [];
        $mins = fn ($s) => (int) round(((int) $s) / 60).' min';

        return match ($event->type) {
            'time_added' => '+'.($m['minutes'] ?? '?').' min',
            'reopened' => ($m['minutes'] ?? '?').' min given'.(! empty($m['violations_cleared']) ? ', warnings cleared' : ''),
            'reconnected' => 'Offline for '.$mins($m['gap_seconds'] ?? 0),
            'resumed' => isset($m['paused_seconds']) ? 'Paused for '.$mins($m['paused_seconds']) : (($m['by'] ?? null) === 'personal_window' ? 'Personal window opened' : null),
            'violation' => 'Warning '.($m['count'] ?? '?').'/'.ExamSessionService::MAX_STRIKES,
            'reset' => count($m['answers'] ?? []).' saved answers archived'.(isset($m['total_score']) && $m['status'] !== 'in_progress' ? ', score was '.$m['total_score'] : ''),
            'rescheduled' => isset($m['window']) ? 'Window: '.$this->sessions->windowLabel(Carbon::parse($m['window'][0]), Carbon::parse($m['window'][1])) : null,
            'room_opened', 'device_switched', 'started' => trim(($m['ip'] ?? '').(isset($m['answered']) ? ' · '.$m['answered'].' answered' : '')) ?: null,
            'submitted', 'auto_submitted', 'timed_out', 'force_submitted' => isset($m['answered']) ? $m['answered'].' answered, '.$mins($m['time_used_seconds'] ?? 0).' used' : null,
            'result_removed' => isset($m['score']) ? 'Score '.$m['score'].', rank '.($m['rank'] ?? '—') : null,
            default => null,
        };
    }
}
