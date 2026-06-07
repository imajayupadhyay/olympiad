<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Result;
use App\Models\Subject;
use App\Services\EnrollmentService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    public function __construct(
        protected EnrollmentService $enrollments,
        protected PaymentService $payments,
    ) {
    }

    /**
     * Two groups: the student's enrolled exams (always shown, with action state) and a
     * filterable list of available exams to browse + enrol in.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $enrolledIds = $user->enrollments()->where('status', 'enrolled')->pluck('exam_id');
        $attempts = ExamAttempt::where('user_id', $user->id)->get()->keyBy('exam_id');
        $results = Result::where('user_id', $user->id)->get()->keyBy('exam_id');

        // ── Enrolled section (always, unfiltered) ──
        $enrolledExams = Exam::whereIn('id', $enrolledIds)
            ->with('subject', 'classLevel')
            ->withCount('questions')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Exam $exam) => $this->enrolledCardData($exam, $attempts, $results));

        // ── Available section (filtered, excludes enrolled) ──
        $classFilter = $request->has('class_level_id')
            ? $request->input('class_level_id')
            : $user->class_level_id;

        $query = Exam::query()
            ->where('status', 'published')
            ->whereNotIn('id', $enrolledIds)
            ->with('subject', 'classLevel')
            ->withCount('questions')
            ->orderBy('starts_at');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }
        if ($classFilter && $classFilter !== 'all') {
            $query->where('class_level_id', $classFilter);
        }

        $available = $query->get()->map(fn (Exam $exam) => $this->cardData($exam, false));

        return Inertia::render('Student/Exams/Index', [
            'enrolledExams' => $enrolledExams,
            'available'     => $available,
            'subjects'      => Subject::active(),
            'classLevels'   => ClassLevel::active(),
            'filters'       => [
                'search'         => $request->input('search', ''),
                'subject_id'     => $request->input('subject_id', ''),
                'class_level_id' => (string) ($classFilter ?? ''),
            ],
        ]);
    }

    /**
     * Enrolled-card payload with the resolved single CTA (start/continue/result/awaiting/…).
     */
    protected function enrolledCardData(Exam $exam, $attempts, $results): array
    {
        $attempt = $attempts->get($exam->id);
        $result = $results->get($exam->id);
        $avail = $exam->availabilityState();

        if ($attempt && $attempt->status === 'in_progress') {
            $action = 'continue';
        } elseif ($attempt) {
            $action = ($result && $result->is_released) ? 'result' : 'awaiting';
        } else {
            $action = match ($avail) {
                'live'     => 'start',
                'upcoming' => 'upcoming',
                default    => 'closed',
            };
        }

        return array_merge($this->cardData($exam, true), [
            'action'     => $action,
            'attempt_id' => $attempt?->id,
            'result_id'  => ($result && $result->is_released) ? $result->id : null,
        ]);
    }

    /**
     * Full exam detail with the correct CTA state for this student.
     */
    public function show(Request $request, Exam $exam): Response
    {
        abort_unless($exam->status === 'published', 404);

        $exam->loadCount('questions')->load('subject', 'classLevel');

        $isEnrolled = $request->user()->isEnrolledIn($exam->id);

        return Inertia::render('Student/Exams/Detail', [
            'exam' => array_merge($this->cardData($exam, $isEnrolled), [
                'description'  => $exam->description,
                'syllabus'     => $exam->syllabus,
                'eligibility'  => $exam->eligibility,
                'instructions' => $exam->instructions,
                'negative_marking_enabled'    => (bool) $exam->negative_marking_enabled,
                'negative_marks_per_question' => (float) $exam->negative_marks_per_question,
                'marks_per_question'          => (float) $exam->marks_per_question,
            ]),
        ]);
    }

    /**
     * Multi-select enrolment. Free exams enrol instantly; paid exams are deferred to the
     * payment phase (Razorpay — §6), so for now they are reported, not enrolled.
     */
    public function enroll(Request $request)
    {
        $data = $request->validate([
            'exam_ids'   => 'required|array|min:1',
            'exam_ids.*' => 'integer|exists:exams,id',
        ]);

        return $this->processEnroll($request->user(), $data['exam_ids']);
    }

    /**
     * Resume a selection started from the public catalogue (stashed before login).
     */
    public function resumeEnroll(Request $request)
    {
        $ids = $request->session()->pull('pending_enroll_ids', []);

        if (empty($ids)) {
            return redirect()->route('student.exams');
        }

        return $this->processEnroll($request->user(), $ids);
    }

    /**
     * Shared enrol pipeline: free exams enrol instantly; paid ones go to the demo gateway.
     */
    protected function processEnroll(\App\Models\User $user, array $ids)
    {
        $exams = Exam::whereIn('id', $ids)->where('status', 'published')->get();

        $free = $exams->filter->isFree();
        $paid = $exams->reject->isFree();

        $enrolled = $this->enrollments->enrollFree($user, $free->pluck('id')->all());

        if ($paid->isNotEmpty()) {
            $payment = $this->payments->createDemoOrder($user, $paid->pluck('id')->all());

            return redirect()->route('student.payments.show', $payment);
        }

        if ($enrolled->isNotEmpty()) {
            return redirect()->route('student.exams')
                ->with('success', "Enrolled in {$enrolled->count()} exam(s) successfully.");
        }

        return redirect()->route('student.exams')->with('info', 'No new exams to enrol.');
    }

    /**
     * Shared card payload for index + detail.
     *
     * @return array<string, mixed>
     */
    protected function cardData(Exam $exam, bool $isEnrolled): array
    {
        return [
            'id'               => $exam->id,
            'name'             => $exam->name,
            'exam_code'        => $exam->exam_code,
            'subject'          => $exam->subject?->only(['id', 'name', 'icon', 'color']),
            'class_level'      => $exam->classLevel?->only(['id', 'label']),
            'starts_at'        => $exam->starts_at,
            'ends_at'          => $exam->ends_at,
            'duration_minutes' => $exam->duration_minutes,
            'questions_count'  => $exam->questions_count,
            'fee_amount'       => (float) $exam->fee_amount,
            'fee_currency'     => $exam->fee_currency,
            'is_free'          => $exam->isFree(),
            'availability'     => $exam->availabilityState(),
            'is_enrolled'      => $isEnrolled,
        ];
    }
}
