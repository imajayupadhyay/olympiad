<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\ExamPaperService;
use App\Services\ExamScoringService;
use App\Services\ExamSectionScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin exam simulator: the real student exam room, rendered for an admin, with nothing
 * recorded. No attempt, answer, result, rank or email is ever written. Works for draft,
 * published and archived exams and ignores the exam window.
 */
class ExamPreviewController extends Controller
{
    public function __construct(
        private ExamPaperService $paper,
        private ExamScoringService $scoring,
        private ExamSectionScoreService $sectionScores,
    ) {}

    public function show(Request $request, Exam $exam): Response|RedirectResponse
    {
        $this->paper->load($exam);

        if ($exam->questions->isEmpty()) {
            return redirect()->route('admin.exams.edit', ['exam' => $exam, 'step' => 'questions'])
                ->with('error', 'Add at least one question before previewing this exam.');
        }

        // A fresh seed per visit shows the admin one of the orders a student could get.
        $questions = $this->paper->questions($exam, 'preview-'.Str::random(12));

        return Inertia::render('Admin/Exams/Preview', [
            'attempt' => ['id' => null, 'started_at' => null],
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'exam_code' => $exam->exam_code,
                'status' => $exam->status,
                'duration_minutes' => $exam->duration_minutes,
                'negative_marking_enabled' => (bool) $exam->negative_marking_enabled,
                'total_questions' => $questions->count(),
            ],
            'sections' => $this->paper->sections($exam),
            'questions' => $questions,
            // A student starting at the open time gets the full duration.
            'remaining_seconds' => (int) $exam->duration_minutes * 60,
            'back_url' => $this->backUrl($request, $exam),
        ]);
    }

    /**
     * Score the admin's answers in memory and return the review. Nothing is persisted.
     */
    public function score(Request $request, Exam $exam): JsonResponse
    {
        $data = $request->validate([
            'answers' => 'array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.selected_options' => 'array',
            'answers.*.selected_options.*' => 'string|in:a,b,c,d',
            'time_taken_seconds' => 'nullable|integer|min:0',
        ]);

        $this->paper->load($exam);

        // Unknown question ids are ignored; preview() only walks this exam's questions.
        $selections = collect($data['answers'] ?? [])
            ->mapWithKeys(fn (array $row) => [(int) $row['question_id'] => $row['selected_options'] ?? []])
            ->all();

        $marked = $this->scoring->preview($exam, $selections);
        $sectionNames = $exam->sections->pluck('name', 'id');

        $review = $exam->questions->map(function ($q) use ($marked, $sectionNames) {
            $row = $marked['questions'][$q->id];

            return [
                'id' => $q->id,
                'section' => $sectionNames->get($q->pivot->exam_section_id),
                'question_text' => $q->question_text,
                'question_image_url' => $q->question_image_url,
                'question_type' => $q->question_type,
                // Review lists options A–D; letters are the same ones shown in the room.
                'options' => collect(['a', 'b', 'c', 'd'])
                    ->map(fn ($k) => ['key' => $k, 'text' => $q->{'option_'.$k}])
                    ->filter(fn ($o) => filled($o['text']))
                    ->values()->all(),
                'correct' => array_values($q->correct_options ?? []),
                'selected' => $row['selected_options'],
                'state' => $row['state'],
                'marks_awarded' => $row['marks_awarded'],
                'max_marks' => $row['max_marks'],
                'explanation' => $q->explanation,
            ];
        })->values();

        return response()->json([
            'summary' => collect($marked)->except('questions')->all()
                + ['time_taken_seconds' => (int) ($data['time_taken_seconds'] ?? 0)],
            'sections' => $this->sectionScores->rollUp($exam, collect($marked['questions'])),
            'review' => $review,
        ]);
    }

    /**
     * Where "Exit preview" goes: back to the list or the builder, never off-site.
     */
    private function backUrl(Request $request, Exam $exam): string
    {
        return $request->query('from') === 'list'
            ? route('admin.exams.index')
            : route('admin.exams.edit', ['exam' => $exam, 'step' => 'review']);
    }
}
