<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Subject;
use App\Services\ExamQuestionImportService;
use App\Services\ExamSectionService;
use App\Services\QuestionBulkImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamQuestionImportController extends Controller
{
    public function template(Exam $exam, ExamQuestionImportService $imports): StreamedResponse
    {
        return $imports->template($exam);
    }

    public function upload(Request $request, Exam $exam, ExamQuestionImportService $imports)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $result = $imports->parse($request->file('file'), $exam);
        if ($result['fileErrors'] !== []) {
            return back()->withErrors(['file' => $result['fileErrors']]);
        }

        $token = (string) Str::uuid();
        // Staged without the mode-dependent "already in this exam" check: rows carry an in_exam flag and
        // the review page applies it for the mode the admin picks. commit() re-validates with that mode.
        Cache::put($this->cacheKey($request, $exam, $token), $imports->validateRows($result['questions'], $exam, 'replace'), now()->addHour());
        $request->session()->put($this->sessionKey($exam), $token);

        return redirect()->route('admin.exams.import.preview', $exam);
    }

    public function preview(Request $request, Exam $exam, ExamSectionService $sections)
    {
        $token = $request->session()->get($this->sessionKey($exam));
        $questions = $token ? Cache::get($this->cacheKey($request, $exam, $token)) : null;

        if (! is_array($questions)) {
            return redirect()->route('admin.exams.edit', ['exam' => $exam, 'step' => 'questions'])
                ->with('error', 'Your import preview has expired. Please upload the workbook again.');
        }

        $exam->load('classLevel:id,label')->loadCount('attempts');

        return Inertia::render('Admin/Exams/ImportPreview', [
            'exam' => [
                ...$exam->only(['id', 'name', 'exam_code', 'subject_id', 'class_level_id', 'question_category_id', 'attempts_count']),
                'class_label' => $exam->classLevel?->label,
                'sections' => collect($sections->currentSections($exam))
                    ->map(fn (array $section) => [
                        'name' => $section['name'],
                        'subject_id' => $section['subject_id'],
                        'question_count' => count($section['question_ids']),
                    ])->values(),
                'question_ids' => $exam->questions()->pluck('questions.id'),
            ],
            'questions' => $questions,
            'subjects' => Subject::active(),
            'categories' => QuestionCategory::active()->orderBy('subject_id')->orderBy('sort_order')->orderBy('name')->get(['id', 'subject_id', 'name']),
            'classLevels' => ClassLevel::active(),
            'difficulties' => Question::difficulties(),
            'types' => Question::types(),
        ]);
    }

    public function store(Request $request, Exam $exam, ExamQuestionImportService $imports)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(ExamQuestionImportService::MODES)],
            'questions' => ['required', 'array', 'min:1', 'max:'.QuestionBulkImportService::MAX_ROWS],
            'questions.*' => ['array'],
        ]);

        $result = $imports->commit($exam, $data['questions'], $data['mode'], $request->user()->id);
        $token = $request->session()->pull($this->sessionKey($exam));
        if ($token) {
            Cache::forget($this->cacheKey($request, $exam, $token));
        }

        return redirect()->route('admin.exams.edit', ['exam' => $exam, 'step' => 'questions'])->with('success', sprintf(
            'Imported %d question%s into %d section%s (%d new in the Question Bank, %d reused).',
            $result['created'] + $result['reused'], $result['created'] + $result['reused'] === 1 ? '' : 's',
            $result['sections'], $result['sections'] === 1 ? '' : 's',
            $result['created'], $result['reused'],
        ));
    }

    private function sessionKey(Exam $exam): string
    {
        return "exam_question_import.{$exam->id}";
    }

    private function cacheKey(Request $request, Exam $exam, string $token): string
    {
        return "exam-question-import:{$request->user()->id}:{$exam->id}:{$token}";
    }
}
