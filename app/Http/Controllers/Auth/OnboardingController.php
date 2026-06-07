<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\EnrollmentService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Post-registration wizard: Step 2 (select olympiads) → Step 3 (checkout) → enrol.
 * Step 1 is the registration form itself (RegisteredUserController).
 */
class OnboardingController extends Controller
{
    public function __construct(
        protected EnrollmentService $enrollments,
        protected PaymentService $payments,
    ) {
    }

    /** Step 2 — pick olympiads (defaults to the student's class). */
    public function olympiads(Request $request): Response
    {
        $user = $request->user();

        $enrolledIds = $user->enrollments()->where('status', 'enrolled')->pluck('exam_id');

        $exams = Exam::where('status', 'published')
            ->when($user->class_level_id, fn ($q) => $q->where('class_level_id', $user->class_level_id))
            ->whereNotIn('id', $enrolledIds)
            ->with('subject:id,name,icon,color', 'classLevel:id,label')
            ->withCount('questions')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Exam $e) => [
                'id'               => $e->id,
                'name'             => $e->name,
                'subject'          => $e->subject?->only(['name', 'icon', 'color']),
                'class_level'      => $e->classLevel?->only(['label']),
                'questions_count'  => $e->questions_count,
                'duration_minutes' => $e->duration_minutes,
                'fee_amount'       => (float) $e->fee_amount,
                'is_free'          => $e->isFree(),
            ]);

        return Inertia::render('Auth/Onboarding/Olympiads', [
            'exams'    => $exams,
            'selected' => session('onboarding_exam_ids', []),
        ]);
    }

    /** Persist the selection and move to checkout. */
    public function storeOlympiads(Request $request)
    {
        $data = $request->validate([
            'exam_ids'   => 'array',
            'exam_ids.*' => 'integer|exists:exams,id',
        ]);

        $ids = $data['exam_ids'] ?? [];

        if (empty($ids)) {
            return redirect()->route('student.dashboard')
                ->with('info', 'You can enrol in olympiads anytime from the Exams page.');
        }

        session(['onboarding_exam_ids' => $ids]);

        return redirect()->route('register.checkout');
    }

    /** Step 3 — order summary + checkout. */
    public function checkout(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        $ids = session('onboarding_exam_ids', []);

        if (empty($ids)) {
            return redirect()->route('register.olympiads');
        }

        return Inertia::render('Auth/Onboarding/Checkout', [
            'summary' => $this->enrollments->selectionSummary($ids, $request->user()),
        ]);
    }

    /** Finalise: enrol free exams now; paid exams await Razorpay (Phase E). */
    public function complete(Request $request)
    {
        $user = $request->user();
        $ids = session('onboarding_exam_ids', []);

        $exams = Exam::whereIn('id', $ids)->where('status', 'published')->get();
        $free = $exams->filter->isFree();
        $paid = $exams->reject->isFree();

        $enrolled = $this->enrollments->enrollFree($user, $free->pluck('id')->all());

        session()->forget('onboarding_exam_ids');

        // Paid exams → demo checkout (free ones already enrolled above).
        if ($paid->isNotEmpty()) {
            $payment = $this->payments->createDemoOrder($user, $paid->pluck('id')->all());

            return redirect()->route('student.payments.show', $payment);
        }

        return redirect()->route('student.dashboard')
            ->with('success', "Welcome aboard! You're enrolled in {$enrolled->count()} exam(s).");
    }
}
