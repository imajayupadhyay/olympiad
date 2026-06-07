<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\QuestionCategory;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class QuestionCategoryController extends Controller
{
    public function index()
    {
        $categories = QuestionCategory::with(['subject:id,name,icon,color,sort_order', 'parent:id,name,parent_id'])
            ->withCount(['children', 'questions'])
            ->orderBy('subject_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Settings/Categories/Index', [
            'subjects' => Subject::active(),
            'categories' => $this->categoryPayloads($categories),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $this->ensureParentMatchesSubject($data['subject_id'], $data['parent_id'] ?? null);

        $data['slug'] = $this->uniqueSlug($data['name'], (int) $data['subject_id']);
        $data['sort_order'] = QuestionCategory::where('subject_id', $data['subject_id'])
            ->where('parent_id', $data['parent_id'] ?? null)
            ->max('sort_order') + 1;

        QuestionCategory::create($data);

        return back()->with('success', "Category \"{$data['name']}\" added.");
    }

    public function update(Request $request, QuestionCategory $category)
    {
        $data = $this->validatedData($request, true);
        $this->ensureParentMatchesSubject($data['subject_id'], $data['parent_id'] ?? null);

        if ((int) ($data['parent_id'] ?? 0) === $category->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be its own parent.',
            ]);
        }

        if ($data['parent_id'] && in_array((int) $data['parent_id'], $category->descendantIds(), true)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be moved below one of its child categories.',
            ]);
        }

        if ((int) $data['subject_id'] !== (int) $category->subject_id && ($category->children()->exists() || $category->questions()->exists())) {
            throw ValidationException::withMessages([
                'subject_id' => 'Move or remove child categories and linked questions before changing this category subject.',
            ]);
        }

        $data['slug'] = $this->uniqueSlug($data['name'], (int) $data['subject_id'], $category->id);
        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(QuestionCategory $category)
    {
        if ($category->children()->exists()) {
            return back()->with('error', "Cannot delete \"{$category->name}\" because it has child categories.");
        }

        if ($category->questions()->exists()) {
            return back()->with('error', "Cannot delete \"{$category->name}\" because it has questions linked to it.");
        }

        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    private function validatedData(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'parent_id' => ['nullable', 'exists:question_categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'sort_order' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:0'],
        ]);
    }

    private function ensureParentMatchesSubject(int $subjectId, ?int $parentId): void
    {
        if (! $parentId) {
            return;
        }

        $parentMatches = QuestionCategory::where('id', $parentId)
            ->where('subject_id', $subjectId)
            ->exists();

        if (! $parentMatches) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent category must belong to the selected subject.',
            ]);
        }
    }

    private function uniqueSlug(string $name, int $subjectId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;

        while (QuestionCategory::where('subject_id', $subjectId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function categoryPayloads(Collection $categories): array
    {
        $byId = $categories->keyBy('id');

        return $categories->map(function (QuestionCategory $category) use ($byId) {
            $path = $this->categoryPath($category, $byId);

            return [
                'id' => $category->id,
                'subject_id' => $category->subject_id,
                'parent_id' => $category->parent_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'is_active' => $category->is_active,
                'sort_order' => $category->sort_order,
                'children_count' => $category->children_count,
                'questions_count' => $category->questions_count,
                'depth' => max(count($path) - 1, 0),
                'path' => implode(' / ', $path),
                'subject' => $category->subject,
            ];
        })->sortBy([
            ['subject.sort_order', 'asc'],
            ['path', 'asc'],
        ])->values()->all();
    }

    private function categoryPath(QuestionCategory $category, Collection $byId): array
    {
        $path = [$category->name];
        $parentId = $category->parent_id;
        $seen = [$category->id => true];

        while ($parentId && $byId->has($parentId) && ! isset($seen[$parentId])) {
            $parent = $byId->get($parentId);
            array_unshift($path, $parent->name);
            $seen[$parent->id] = true;
            $parentId = $parent->parent_id;
        }

        return $path;
    }
}
