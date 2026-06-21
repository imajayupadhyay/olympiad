<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminQuestionCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_parent_and_child_question_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $science = $this->subject('Science', 'science');

        $response = $this->actingAs($admin)->post(route('admin.settings.categories.store'), [
            'subject_id' => $science->id,
            'parent_id' => null,
            'name' => 'Physics',
            'description' => 'Physics topics',
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $parent = QuestionCategory::first();

        $this->assertNotNull($parent);
        $this->assertSame('physics', $parent->slug);

        $response = $this->actingAs($admin)->post(route('admin.settings.categories.store'), [
            'subject_id' => $science->id,
            'parent_id' => $parent->id,
            'name' => 'Motion',
            'description' => null,
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('question_categories', [
            'subject_id' => $science->id,
            'parent_id' => $parent->id,
            'name' => 'Motion',
            'slug' => 'motion',
        ]);
    }

    public function test_category_parent_must_belong_to_selected_subject(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $science = $this->subject('Science', 'science');
        $math = $this->subject('Mathematics', 'mathematics');
        $mathParent = QuestionCategory::create([
            'subject_id' => $math->id,
            'name' => 'Algebra',
            'slug' => 'algebra',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.categories.store'), [
            'subject_id' => $science->id,
            'parent_id' => $mathParent->id,
            'name' => 'Motion',
            'description' => null,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('question_categories', ['name' => 'Motion']);
    }

    public function test_question_category_must_belong_to_question_subject(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $science = $this->subject('Science', 'science');
        $math = $this->subject('Mathematics', 'mathematics');
        $classLevel = $this->classLevel(6);
        $mathCategory = QuestionCategory::create([
            'subject_id' => $math->id,
            'name' => 'Algebra',
            'slug' => 'algebra',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.questions.store'), [
            ...$this->questionPayload($admin, $science, $classLevel),
            'question_category_id' => $mathCategory->id,
        ]);

        $response->assertSessionHasErrors('question_category_id');
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_question_bank_category_filter_includes_child_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $science = $this->subject('Science', 'science');
        $classLevel = $this->classLevel(6);
        $parent = QuestionCategory::create([
            'subject_id' => $science->id,
            'name' => 'Physics',
            'slug' => 'physics',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $child = QuestionCategory::create([
            'subject_id' => $science->id,
            'parent_id' => $parent->id,
            'name' => 'Motion',
            'slug' => 'motion',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $sibling = QuestionCategory::create([
            'subject_id' => $science->id,
            'name' => 'Biology',
            'slug' => 'biology',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $matchingQuestion = Question::create([
            ...$this->questionPayload($admin, $science, $classLevel),
            'question_category_id' => $child->id,
        ]);
        $matchingQuestion->classLevels()->attach($classLevel->id);

        $siblingQuestion = Question::create([
            ...$this->questionPayload($admin, $science, $classLevel),
            'question_category_id' => $sibling->id,
            'question_text' => '<p>Biology question?</p>',
        ]);
        $siblingQuestion->classLevels()->attach($classLevel->id);

        $response = $this->actingAs($admin)->get(route('admin.questions.index', [
            'question_category_id' => $parent->id,
        ]));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Questions/Index')
                ->has('questions.data', 1)
                ->where('questions.data.0.id', $matchingQuestion->id)
            );
    }

    private function subject(string $name, string $slug): Subject
    {
        return Subject::create([
            'name' => $name,
            'slug' => $slug,
            'icon' => strtoupper(substr($name, 0, 2)),
            'color' => '#16A34A',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function classLevel(int $level): ClassLevel
    {
        return ClassLevel::create([
            'level' => $level,
            'label' => "Class {$level}",
            'is_active' => true,
            'sort_order' => $level,
        ]);
    }

    private function questionPayload(User $admin, Subject $subject, ClassLevel $classLevel): array
    {
        return [
            'subject_id' => $subject->id,
            'class_level_ids' => [$classLevel->id],
            'difficulty' => 'medium',
            'topic' => 'General',
            'question_text' => '<p>What is the correct answer?</p>',
            'option_a' => '<p>A</p>',
            'option_b' => '<p>B</p>',
            'option_c' => '<p>C</p>',
            'option_d' => '<p>D</p>',
            'question_type' => 'single',
            'correct_options' => ['a'],
            'explanation' => null,
            'marks' => 1,
            'negative_marks' => 0,
            'is_active' => true,
            'created_by' => $admin->id,
        ];
    }
}
