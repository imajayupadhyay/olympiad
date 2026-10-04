<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Class + subject scoped tags for the question bank.
 *
 * These are deliberately separate from question_categories: categories stay
 * subject-wide and drive exam composition, while tags are the fine-grained
 * "topic" labels an operator adds per class while uploading a question.
 */
class QuestionTagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_id'        => ['required', 'integer', 'exists:subjects,id'],
            'class_level_ids'   => ['required', 'array', 'min:1'],
            'class_level_ids.*' => ['integer', 'distinct', 'exists:class_levels,id'],
        ]);

        return response()->json([
            'tags' => $this->tagsFor($data['subject_id'], $data['class_level_ids']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:60'],
            'subject_id'        => ['required', 'integer', 'exists:subjects,id'],
            'class_level_ids'   => ['required', 'array', 'min:1'],
            'class_level_ids.*' => ['integer', 'distinct', 'exists:class_levels,id'],
        ]);

        $name = trim(preg_replace('/\s+/', ' ', $data['name']));
        $slug = QuestionTag::slugFor($name);

        if ($slug === '') {
            return response()->json([
                'message' => 'Give the tag a name that contains at least one letter or number.',
            ], 422);
        }

        $created = DB::transaction(function () use ($data, $name, $slug) {
            $ids = [];

            foreach ($data['class_level_ids'] as $classLevelId) {
                $tag = QuestionTag::firstOrNew([
                    'subject_id'     => $data['subject_id'],
                    'class_level_id' => $classLevelId,
                    'slug'           => $slug,
                ]);

                // An existing tag keeps its original casing; re-adding a
                // disabled tag brings it back rather than failing on the
                // subject + class + slug unique index.
                if (! $tag->exists) {
                    $tag->name = $name;
                    $tag->created_by = Auth::id();
                }

                $tag->is_active = true;
                $tag->save();

                $ids[] = $tag->id;
            }

            return $ids;
        });

        return response()->json([
            'tags'        => $this->tagsFor($data['subject_id'], $data['class_level_ids']),
            'created_ids' => $created,
        ], 201);
    }

    private function tagsFor(int $subjectId, array $classLevelIds): array
    {
        return QuestionTag::query()
            ->active()
            ->where('subject_id', $subjectId)
            ->whereIn('class_level_id', $classLevelIds)
            ->with('classLevel:id,label,sort_order')
            ->get()
            // Class order first (so a multi-class question reads class by
            // class), then the tag name within each class.
            ->sortBy(fn (QuestionTag $tag) => sprintf(
                '%05d|%s',
                $tag->classLevel?->sort_order ?? 0,
                mb_strtolower($tag->name),
            ), SORT_STRING)
            ->values()
            ->map(fn (QuestionTag $tag) => [
                'id'             => $tag->id,
                'name'           => $tag->name,
                'slug'           => $tag->slug,
                'subject_id'     => $tag->subject_id,
                'class_level_id' => $tag->class_level_id,
                'class_label'    => $tag->classLevel?->label,
            ])
            ->all();
    }
}
