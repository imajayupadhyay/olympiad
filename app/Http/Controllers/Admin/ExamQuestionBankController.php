<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Services\ExamSectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Searchable question-bank feed for the exam builder's "Pick from bank" drawer.
 */
class ExamQuestionBankController extends Controller
{
    public function index(Request $request, ExamSectionService $sections): JsonResponse
    {
        $filters = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'class_level_id' => ['required', 'integer', 'exists:class_levels,id'],
            'question_category_id' => ['nullable', 'integer', 'exists:question_categories,id'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'question_type' => ['nullable', 'in:single,multiple'],
            'search' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = Question::with(['subject:id,name', 'classLevels:id,label', 'questionCategory:id,name'])
            ->where('is_active', true)
            ->where('subject_id', $filters['subject_id'])
            ->whereHas('classLevels', fn ($q) => $q->where('class_levels.id', $filters['class_level_id']))
            ->when($filters['difficulty'] ?? null, fn ($q, $difficulty) => $q->where('difficulty', $difficulty))
            ->when($filters['question_type'] ?? null, fn ($q, $type) => $q->where('question_type', $type))
            ->latest('id');

        if ($categoryId = $filters['question_category_id'] ?? null) {
            $query->whereIn('question_category_id', QuestionCategory::find($categoryId)->idsWithDescendants());
        }

        if ($search = trim($filters['search'] ?? '')) {
            $query->where(fn ($q) => $q->where('question_text', 'like', "%{$search}%")
                ->orWhere('topic', 'like', "%{$search}%")
                ->orWhereHas('tags', fn ($tags) => $tags->where('name', 'like', "%{$search}%")));
        }

        $page = $query->paginate($filters['per_page'] ?? 25);

        return response()->json([
            'data' => collect($page->items())->map(fn (Question $question) => $sections->questionPayload($question))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
