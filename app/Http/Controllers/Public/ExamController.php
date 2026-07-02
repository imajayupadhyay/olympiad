<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    /** Public catalogue of all published exams (any class), with filters. */
    public function index(Request $request): Response
    {
        $query = Exam::query()
            ->where('status', 'published')
            ->with('subject:id,name,icon,color', 'classLevel:id,label')
            ->withCount('questions')
            ->orderBy('starts_at');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }
        if ($request->filled('class_level_id')) {
            $query->where('class_level_id', $request->class_level_id);
        }

        $user = $request->user();
        $enrolledIds = $user
            ? $user->enrollments()->where('status', 'enrolled')->pluck('exam_id')
            : collect();

        $exams = $query->get()->map(fn (Exam $e) => [
            'id'               => $e->id,
            'name'             => $e->name,
            'description'      => $e->description,
            'subject'          => $e->subject?->only(['id', 'name', 'icon', 'color']),
            'class_level'      => $e->classLevel?->only(['id', 'label']),
            'questions_count'  => $e->questions_count,
            'duration_minutes' => $e->duration_minutes,
            'starts_at'        => $e->starts_at,
            'ends_at'          => $e->ends_at,
            'fee_amount'       => (float) $e->fee_amount,
            'is_free'          => $e->isFree(),
            'availability'     => $e->availabilityState(),
            'is_enrolled'      => $enrolledIds->contains($e->id),
        ]);

        return Inertia::render('Public/Exams/Index', [
            'exams'       => $exams,
            'subjects'    => Subject::active(),
            'classLevels' => ClassLevel::active(),
            'filters'     => $request->only(['search', 'subject_id', 'class_level_id']),
        ]);
    }

    /**
     * Begin enrolment from the public catalogue. We stash the selection and route to
     * the same enrol/pay pipeline used inside the portal — logging in first if needed.
     */
    public function enroll(Request $request)
    {
        $data = $request->validate([
            'exam_ids'   => 'required|array|min:1',
            'exam_ids.*' => 'integer|exists:exams,id',
        ]);

        $request->session()->put('pending_enroll_ids', $data['exam_ids']);

        if (! Auth::check()) {
            return redirect()->route('login')->with('info', 'Please log in to complete your enrolment.');
        }

        return redirect()->route('student.exams.resume-enroll');
    }
}
