<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\NotificationLog;
use App\Models\Question;
use App\Models\Result;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $students = User::where('role', 'student');

        /* ── KPI stats ─────────────────────────────────────────────── */
        $stats = [
            'totalStudents'    => (clone $students)->count(),
            'newToday'         => (clone $students)->whereDate('created_at', today())->count(),
            'newWeek'          => (clone $students)->where('created_at', '>=', today()->subDays(7))->count(),
            'activeStudents'   => (clone $students)->where('is_active', true)->count(),
            'totalQuestions'   => Question::count(),
            'activeQuestions'  => Question::where('is_active', true)->count(),
            'totalExams'       => Exam::count(),
            'activeExams'      => Exam::where('status', 'published')->count(),
            'totalAttempts'    => ExamAttempt::whereIn('status', ['submitted', 'timed_out', 'auto_submitted'])->count(),
            'attemptsWeek'     => ExamAttempt::whereIn('status', ['submitted', 'timed_out', 'auto_submitted'])->where('created_at', '>=', today()->subDays(7))->count(),
            'resultsReleased'  => Result::where('is_released', true)->count(),
            'avgScore'         => round((float) Result::avg('percentage'), 1),
            'certsIssued'      => Certificate::where('type', 'student')->count(),
            'notificationsSent'=> NotificationLog::count(),
            'totalReach'       => (int) NotificationLog::sum('recipient_count'),
            'totalRevenue'     => 0, // payments module pending
        ];

        /* ── Registrations trend (last 14 days) ────────────────────── */
        $since = today()->subDays(13);
        $byDay = (clone $students)->where('created_at', '>=', $since)
            ->get(['created_at'])
            ->groupBy(fn ($u) => $u->created_at->format('Y-m-d'))
            ->map->count();

        $registrations = collect(range(13, 0))->map(function ($i) use ($byDay) {
            $d = today()->subDays($i);
            return [
                'label' => $d->format('d M'),
                'short' => $d->format('d'),
                'value' => (int) ($byDay[$d->format('Y-m-d')] ?? 0),
            ];
        })->values();

        /* ── Questions by subject ──────────────────────────────────── */
        $qBySubject = Question::selectRaw('subject_id, count(*) as c')->groupBy('subject_id')->pluck('c', 'subject_id');
        $questionsBySubject = Subject::active()->map(fn ($s) => [
            'name'  => $s->name,
            'icon'  => $s->icon,
            'color' => $s->color ?: '#2C49A6',
            'count' => (int) ($qBySubject[$s->id] ?? 0),
        ])->sortByDesc('count')->values();

        /* ── Questions by difficulty ───────────────────────────────── */
        $byDiff = Question::selectRaw('difficulty, count(*) as c')->groupBy('difficulty')->pluck('c', 'difficulty');
        $difficulty = [
            ['label' => 'Easy',   'value' => (int) ($byDiff['easy']   ?? 0), 'color' => '#168A66'],
            ['label' => 'Medium', 'value' => (int) ($byDiff['medium'] ?? 0), 'color' => '#D6991F'],
            ['label' => 'Hard',   'value' => (int) ($byDiff['hard']   ?? 0), 'color' => '#DC2626'],
        ];

        /* ── Exam status breakdown ─────────────────────────────────── */
        $byStatus = Exam::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $examStatus = [
            ['label' => 'Published', 'value' => (int) ($byStatus['published'] ?? 0), 'color' => '#168A66'],
            ['label' => 'Draft',     'value' => (int) ($byStatus['draft']     ?? 0), 'color' => '#D6991F'],
            ['label' => 'Archived',  'value' => (int) ($byStatus['archived']  ?? 0), 'color' => '#5B6373'],
        ];

        /* ── Students by class ─────────────────────────────────────── */
        $byClass = (clone $students)->whereNotNull('class_level_id')
            ->selectRaw('class_level_id, count(*) as c')->groupBy('class_level_id')->pluck('c', 'class_level_id');
        $studentsByClass = ClassLevel::active()->map(fn ($cl) => [
            'label' => $cl->label,
            'short' => preg_replace('/[^0-9]/', '', $cl->label) ?: $cl->label,
            'value' => (int) ($byClass[$cl->id] ?? 0),
        ])->values();

        /* ── Top states ────────────────────────────────────────────── */
        $topStates = (clone $students)->whereNotNull('state')->where('state', '!=', '')
            ->selectRaw('state, count(*) as c')->groupBy('state')->orderByDesc('c')->limit(5)
            ->get()->map(fn ($r) => ['state' => $r->state, 'count' => (int) $r->c]);

        /* ── Recent students + upcoming exams ──────────────────────── */
        $recentStudents = (clone $students)->latest()->take(6)->get(['id', 'name', 'email', 'created_at']);

        $upcomingExams = Exam::with(['subject:id,name,icon,color', 'classLevel:id,label'])
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '>=', now());
            })
            ->orderByRaw('starts_at IS NULL, starts_at ASC')
            ->take(5)->get();

        return Inertia::render('Admin/Dashboard/Index', [
            'stats'  => $stats,
            'charts' => [
                'registrations'      => $registrations,
                'questionsBySubject' => $questionsBySubject,
                'difficulty'         => $difficulty,
                'examStatus'         => $examStatus,
                'studentsByClass'    => $studentsByClass,
                'topStates'          => $topStates,
            ],
            'recentStudents' => $recentStudents,
            'upcomingExams'  => $upcomingExams,
        ]);
    }
}
