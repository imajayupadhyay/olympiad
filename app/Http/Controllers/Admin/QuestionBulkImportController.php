<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Subject;
use App\Services\QuestionBulkImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionBulkImportController extends Controller
{
    public function template(QuestionBulkImportService $imports): StreamedResponse
    {
        return $imports->template();
    }

    public function upload(Request $request, QuestionBulkImportService $imports)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $result = $imports->parse($request->file('file'));
        if ($result['fileErrors'] !== []) {
            return back()->withErrors(['file' => $result['fileErrors']]);
        }

        $token = (string) Str::uuid();
        Cache::put($this->cacheKey($request, $token), $imports->validateRows($result['questions']), now()->addHour());
        $request->session()->put('question_bulk_import_token', $token);

        return redirect()->route('admin.questions.import.preview');
    }

    public function preview(Request $request)
    {
        $token = $request->session()->get('question_bulk_import_token');
        $questions = $token ? Cache::get($this->cacheKey($request, $token)) : null;

        if (! is_array($questions)) {
            return redirect()->route('admin.questions.index')
                ->with('error', 'Your bulk import preview has expired. Please upload the workbook again.');
        }

        return Inertia::render('Admin/Questions/BulkImportPreview', [
            ...$this->metadata(),
            'questions' => $questions,
        ]);
    }

    public function store(Request $request, QuestionBulkImportService $imports)
    {
        $data = $request->validate([
            'questions' => ['required', 'array', 'min:1', 'max:'.QuestionBulkImportService::MAX_ROWS],
            'questions.*' => ['array'],
        ]);
        $count = $imports->commit($data['questions'], $request->user()->id);
        $token = $request->session()->pull('question_bulk_import_token');
        if ($token) {
            Cache::forget($this->cacheKey($request, $token));
        }

        return redirect()->route('admin.questions.index')->with('success', "{$count} questions imported successfully.");
    }

    private function metadata(): array
    {
        return [
            'subjects' => Subject::active(),
            'categories' => QuestionCategory::active()->orderBy('subject_id')->orderBy('sort_order')->orderBy('name')->get(['id', 'subject_id', 'name']),
            'classLevels' => ClassLevel::active(),
            'difficulties' => Question::difficulties(),
            'types' => Question::types(),
        ];
    }

    private function cacheKey(Request $request, string $token): string
    {
        return 'question-bulk-import:'.$request->user()->id.':'.$token;
    }
}
