<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminExamManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_exam_with_assigned_questions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $subject = Subject::create([
            'name' => 'Science',
            'slug' => 'science',
            'icon' => 'SC',
            'color' => '#16A34A',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $classLevel = ClassLevel::create([
            'level' => 6,
            'label' => 'Class 6',
            'is_active' => true,
            'sort_order' => 6,
        ]);
        $question = $this->createQuestion($admin, $subject, $classLevel);

        $response = $this->actingAs($admin)->post(route('admin.exams.store'), [
            ...$this->examPayload($subject, $classLevel),
            'scoring_mode' => 'uniform',
            'marks_per_question' => 2,
            'negative_marking_enabled' => true,
            'negative_marks_per_question' => 0.5,
            'question_ids' => [$question->id],
        ]);

        $response->assertRedirect(route('admin.exams.index'));
        $response->assertSessionHasNoErrors();

        $exam = Exam::first();

        $this->assertNotNull($exam);
        $this->assertSame('National Science Olympiad - Class 6', $exam->name);
        $this->assertSame('draft', $exam->status);
        $this->assertDatabaseHas('exams', [
            'id' => $exam->id,
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
        ]);

        $pivot = DB::table('exam_questions')->where('exam_id', $exam->id)->first();

        $this->assertSame($question->id, $pivot->question_id);
        $this->assertSame(1, $pivot->sort_order);
        $this->assertEquals(2.00, (float) $pivot->marks);
        $this->assertEquals(0.50, (float) $pivot->negative_marks);
    }

    public function test_exam_rejects_questions_from_another_subject_or_class(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $science = Subject::create([
            'name' => 'Science',
            'slug' => 'science',
            'icon' => 'SC',
            'color' => '#16A34A',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $math = Subject::create([
            'name' => 'Mathematics',
            'slug' => 'mathematics',
            'icon' => 'MT',
            'color' => '#1E3A8A',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $classSix = ClassLevel::create([
            'level' => 6,
            'label' => 'Class 6',
            'is_active' => true,
            'sort_order' => 6,
        ]);
        $classSeven = ClassLevel::create([
            'level' => 7,
            'label' => 'Class 7',
            'is_active' => true,
            'sort_order' => 7,
        ]);
        $mismatchedQuestion = $this->createQuestion($admin, $math, $classSeven);

        $response = $this->actingAs($admin)->post(route('admin.exams.store'), [
            ...$this->examPayload($science, $classSix),
            'question_ids' => [$mismatchedQuestion->id],
        ]);

        $response->assertSessionHasErrors('question_ids');
        $this->assertDatabaseCount('exams', 0);
        $this->assertDatabaseCount('exam_questions', 0);
    }

    public function test_create_page_loads_matching_active_questions_for_selected_subject_and_class(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $science = Subject::create([
            'name' => 'Science',
            'slug' => 'science',
            'icon' => 'SC',
            'color' => '#16A34A',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $math = Subject::create([
            'name' => 'Mathematics',
            'slug' => 'mathematics',
            'icon' => 'MT',
            'color' => '#1E3A8A',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $classSix = ClassLevel::create([
            'level' => 6,
            'label' => 'Class 6',
            'is_active' => true,
            'sort_order' => 6,
        ]);
        $classSeven = ClassLevel::create([
            'level' => 7,
            'label' => 'Class 7',
            'is_active' => true,
            'sort_order' => 7,
        ]);

        $matchingQuestion = $this->createQuestion($admin, $science, $classSix);
        $this->createQuestion($admin, $math, $classSix);
        $this->createQuestion($admin, $science, $classSeven);
        $this->createQuestion($admin, $science, $classSix)->update(['is_active' => false]);

        $response = $this->actingAs($admin)->get(route('admin.exams.create', [
            'question_subject_id' => $science->id,
            'question_class_level_id' => $classSix->id,
        ]));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Exams/Create')
                ->has('availableQuestions.data', 1)
                ->where('availableQuestions.data.0.id', $matchingQuestion->id)
                ->where('questionFilters.search', '')
                ->where('questionFilters.difficulty', '')
            );
    }

    public function test_create_page_can_filter_available_questions_by_parent_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $science = Subject::create([
            'name' => 'Science',
            'slug' => 'science',
            'icon' => 'SC',
            'color' => '#16A34A',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $classSix = ClassLevel::create([
            'level' => 6,
            'label' => 'Class 6',
            'is_active' => true,
            'sort_order' => 6,
        ]);
        $physics = QuestionCategory::create([
            'subject_id' => $science->id,
            'name' => 'Physics',
            'slug' => 'physics',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $motion = QuestionCategory::create([
            'subject_id' => $science->id,
            'parent_id' => $physics->id,
            'name' => 'Motion',
            'slug' => 'motion',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $biology = QuestionCategory::create([
            'subject_id' => $science->id,
            'name' => 'Biology',
            'slug' => 'biology',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $matchingQuestion = $this->createQuestion($admin, $science, $classSix, $motion);
        $this->createQuestion($admin, $science, $classSix, $biology);

        $response = $this->actingAs($admin)->get(route('admin.exams.create', [
            'question_subject_id' => $science->id,
            'question_class_level_id' => $classSix->id,
            'question_category_id' => $physics->id,
        ]));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Exams/Create')
                ->has('availableQuestions.data', 1)
                ->where('availableQuestions.data.0.id', $matchingQuestion->id)
                ->where('questionFilters.category_id', (string) $physics->id)
            );
    }

    public function test_exam_category_must_belong_to_exam_subject(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $science = Subject::create([
            'name' => 'Science',
            'slug' => 'science',
            'icon' => 'SC',
            'color' => '#16A34A',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $math = Subject::create([
            'name' => 'Mathematics',
            'slug' => 'mathematics',
            'icon' => 'MT',
            'color' => '#1E3A8A',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $classSix = ClassLevel::create([
            'level' => 6,
            'label' => 'Class 6',
            'is_active' => true,
            'sort_order' => 6,
        ]);
        $mathCategory = QuestionCategory::create([
            'subject_id' => $math->id,
            'name' => 'Algebra',
            'slug' => 'algebra',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.exams.store'), [
            ...$this->examPayload($science, $classSix),
            'question_category_id' => $mathCategory->id,
        ]);

        $response->assertSessionHasErrors('question_category_id');
        $this->assertDatabaseCount('exams', 0);
    }

    public function test_exam_with_category_rejects_questions_outside_category_scope(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $science = Subject::create([
            'name' => 'Science',
            'slug' => 'science',
            'icon' => 'SC',
            'color' => '#16A34A',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $classSix = ClassLevel::create([
            'level' => 6,
            'label' => 'Class 6',
            'is_active' => true,
            'sort_order' => 6,
        ]);
        $physics = QuestionCategory::create([
            'subject_id' => $science->id,
            'name' => 'Physics',
            'slug' => 'physics',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $biology = QuestionCategory::create([
            'subject_id' => $science->id,
            'name' => 'Biology',
            'slug' => 'biology',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $biologyQuestion = $this->createQuestion($admin, $science, $classSix, $biology);

        $response = $this->actingAs($admin)->post(route('admin.exams.store'), [
            ...$this->examPayload($science, $classSix),
            'question_category_id' => $physics->id,
            'question_ids' => [$biologyQuestion->id],
        ]);

        $response->assertSessionHasErrors('question_ids');
        $this->assertDatabaseCount('exams', 0);
    }

    public function test_admin_can_create_exam_scoped_to_parent_category_with_child_questions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $science = Subject::create([
            'name' => 'Science',
            'slug' => 'science',
            'icon' => 'SC',
            'color' => '#16A34A',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $classSix = ClassLevel::create([
            'level' => 6,
            'label' => 'Class 6',
            'is_active' => true,
            'sort_order' => 6,
        ]);
        $physics = QuestionCategory::create([
            'subject_id' => $science->id,
            'name' => 'Physics',
            'slug' => 'physics',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $motion = QuestionCategory::create([
            'subject_id' => $science->id,
            'parent_id' => $physics->id,
            'name' => 'Motion',
            'slug' => 'motion',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $question = $this->createQuestion($admin, $science, $classSix, $motion);

        $response = $this->actingAs($admin)->post(route('admin.exams.store'), [
            ...$this->examPayload($science, $classSix),
            'question_category_id' => $physics->id,
            'question_ids' => [$question->id],
        ]);

        $response->assertRedirect(route('admin.exams.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('exams', [
            'subject_id' => $science->id,
            'class_level_id' => $classSix->id,
            'question_category_id' => $physics->id,
        ]);
        $this->assertDatabaseHas('exam_questions', [
            'question_id' => $question->id,
        ]);
    }

    public function test_exam_cannot_be_published_without_questions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $subject = Subject::create([
            'name' => 'Science',
            'slug' => 'science',
            'icon' => 'SC',
            'color' => '#16A34A',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $classLevel = ClassLevel::create([
            'level' => 6,
            'label' => 'Class 6',
            'is_active' => true,
            'sort_order' => 6,
        ]);
        $exam = Exam::create([
            ...$this->examPayload($subject, $classLevel),
            'slug' => 'national-science-olympiad-class-6',
            'exam_code' => 'NOH-2606-TEST',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.exams.publish', $exam));

        $response->assertSessionHas('error');
        $this->assertSame('draft', $exam->fresh()->status);
    }

    private function createQuestion(User $admin, Subject $subject, ClassLevel $classLevel, ?QuestionCategory $category = null): Question
    {
        return Question::create([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'question_category_id' => $category?->id,
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
        ]);
    }

    private function examPayload(Subject $subject, ClassLevel $classLevel): array
    {
        return [
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'name' => 'National Science Olympiad - Class 6',
            'description' => 'Class 6 science exam.',
            'syllabus' => 'Physics, chemistry, biology basics.',
            'eligibility' => 'Open to class 6 students.',
            'instructions' => 'Answer every question carefully.',
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'fee_amount' => 150,
            'fee_currency' => 'INR',
            'scoring_mode' => 'question_bank',
            'marks_per_question' => 1,
            'negative_marking_enabled' => false,
            'negative_marks_per_question' => 0,
            'randomize_questions' => true,
            'randomize_options' => true,
            'show_result_immediately' => true,
            'result_release_at' => null,
            'status' => 'draft',
        ];
    }
}
