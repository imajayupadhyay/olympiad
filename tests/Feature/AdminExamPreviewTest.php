<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamEnrollment;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminExamPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Subject $math;

    private Subject $reasoning;

    private ClassLevel $classSix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->math = Subject::create(['name' => 'Mathematics', 'slug' => 'mathematics', 'is_active' => true, 'sort_order' => 1]);
        $this->reasoning = Subject::create(['name' => 'Logical Reasoning', 'slug' => 'logical-reasoning', 'is_active' => true, 'sort_order' => 2]);
        $this->classSix = ClassLevel::create(['level' => 6, 'label' => 'Class 6', 'is_active' => true, 'sort_order' => 6]);
    }

    public function test_admin_previews_a_draft_exam_in_the_student_room_without_answer_keys(): void
    {
        [$r1, $m1] = [$this->question($this->reasoning), $this->question($this->math, marks: 2)];
        $exam = $this->exam([['Reasoning', $this->reasoning, [$r1]], ['Mathematics', $this->math, [$m1]]]);
        $this->assertSame('draft', $exam->status);

        $this->actingAs($this->admin)->get(route('admin.exams.preview', $exam))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Exams/Preview')
                ->where('exam.name', $exam->name)
                ->where('remaining_seconds', 60 * 60)
                ->has('sections', 2)
                ->where('sections.0.name', 'Reasoning')
                ->has('questions', 2)
                ->where('questions.0.id', $r1->id)
                ->where('questions.1.marks', 2)
                ->missing('questions.0.correct_options')
                ->missing('questions.0.explanation')
                ->where('back_url', route('admin.exams.edit', ['exam' => $exam, 'step' => 'review'])));

        $this->get(route('admin.exams.preview', ['exam' => $exam, 'from' => 'list']))
            ->assertInertia(fn (Assert $page) => $page->where('back_url', route('admin.exams.index')));

        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_preview_needs_at_least_one_question(): void
    {
        $exam = $this->exam([['Empty', $this->math, []]]);

        $this->actingAs($this->admin)->get(route('admin.exams.preview', $exam))
            ->assertRedirect(route('admin.exams.edit', ['exam' => $exam, 'step' => 'questions']))
            ->assertSessionHas('error');
    }

    public function test_scoring_a_preview_uses_the_real_marking_rules_and_records_nothing(): void
    {
        $right = $this->question($this->reasoning);
        $wrong = $this->question($this->math, marks: 2, negative: 0.5);
        $multi = $this->question($this->math, marks: 2, type: 'multiple', correct: ['b', 'c']);
        $skip = $this->question($this->math);
        $exam = $this->exam([['Reasoning', $this->reasoning, [$right]], ['Mathematics', $this->math, [$wrong, $multi, $skip]]], [
            'negative_marking_enabled' => true,   // question_bank mode: each question's own negative marks
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('admin.exams.preview.score', $exam), [
            'answers' => [
                ['question_id' => $right->id, 'selected_options' => ['a']],
                ['question_id' => $wrong->id, 'selected_options' => ['d']],
                ['question_id' => $multi->id, 'selected_options' => ['c', 'b']],
                ['question_id' => $skip->id, 'selected_options' => []],
                ['question_id' => 999999, 'selected_options' => ['a']],   // not in this exam: ignored
            ],
            'time_taken_seconds' => 95,
        ])->assertOk();

        // 1 (right) − 0.5 (wrong) + 2 (multi, order-independent) + 0 (skipped) = 2.5 of 6.
        $response->assertJsonPath('summary.score', 2.5)
            ->assertJsonPath('summary.max_score', 6)
            ->assertJsonPath('summary.correct', 2)
            ->assertJsonPath('summary.wrong', 1)
            ->assertJsonPath('summary.skipped', 1)
            ->assertJsonPath('summary.attempted', 3)
            ->assertJsonPath('summary.total', 4)
            ->assertJsonPath('summary.time_taken_seconds', 95)
            ->assertJsonCount(2, 'sections')
            ->assertJsonPath('sections.1.name', 'Mathematics')
            ->assertJsonPath('sections.1.score', 1.5)
            ->assertJsonCount(4, 'review')
            ->assertJsonPath('review.1.state', 'wrong')
            ->assertJsonPath('review.1.marks_awarded', -0.5)
            ->assertJsonPath('review.1.correct', ['a'])
            ->assertJsonPath('review.1.selected', ['d'])
            ->assertJsonPath('review.0.section', 'Reasoning');

        $this->assertDatabaseCount('exam_attempts', 0);
        $this->assertDatabaseCount('answers', 0);
        $this->assertDatabaseCount('results', 0);
        $this->assertDatabaseCount('email_logs', 0);
    }

    public function test_preview_score_rejects_unknown_option_letters(): void
    {
        $question = $this->question($this->math);
        $exam = $this->exam([['Mathematics', $this->math, [$question]]]);

        $this->actingAs($this->admin)->postJson(route('admin.exams.preview.score', $exam), [
            'answers' => [['question_id' => $question->id, 'selected_options' => ['e']]],
        ])->assertUnprocessable()->assertJsonValidationErrors('answers.0.selected_options.0');
    }

    public function test_preview_is_admin_only_and_follows_exam_read_permission(): void
    {
        $question = $this->question($this->math);
        $exam = $this->exam([['Mathematics', $this->math, [$question]]]);

        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.exams.preview', $exam))->assertRedirect(route('admin.login'));
        $this->actingAs($student)->postJson(route('admin.exams.preview.score', $exam), ['answers' => []])->assertRedirect(route('admin.login'));

        $reader = User::factory()->create(['role' => 'admin', 'admin_role_id' => $this->roleWith(['exams' => ['read' => true]])->id]);
        $this->actingAs($reader)->get(route('admin.exams.preview', $exam))->assertOk();
        $this->actingAs($reader)->postJson(route('admin.exams.preview.score', $exam), ['answers' => []])->assertOk();

        $outsider = User::factory()->create(['role' => 'admin', 'admin_role_id' => $this->roleWith(['students' => ['read' => true]])->id]);
        // Pages bounce to the first area the role can read; JSON gets a hard 403.
        $this->actingAs($outsider)->get(route('admin.exams.preview', $exam))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($outsider)->postJson(route('admin.exams.preview.score', $exam), ['answers' => []])->assertForbidden();
    }

    public function test_student_exam_room_still_serves_the_same_sanitized_paper(): void
    {
        $question = $this->question($this->math);
        $exam = $this->exam([['Mathematics', $this->math, [$question]]]);
        $exam->update(['status' => 'published', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHours(3)]);

        $student = User::factory()->create(['role' => 'student', 'class_level_id' => $this->classSix->id]);
        ExamEnrollment::create(['user_id' => $student->id, 'exam_id' => $exam->id, 'status' => 'enrolled', 'enrolled_at' => now()]);

        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect();
        $attempt = ExamAttempt::firstOrFail();

        $this->get(route('student.exam-room', $attempt))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/ExamRoom/Index')
                ->where('attempt.id', $attempt->id)
                ->has('questions', 1)
                ->where('questions.0.id', $question->id)
                ->where('questions.0.options.0.key', 'a')
                ->missing('questions.0.correct_options')
                ->missing('questions.0.explanation')
                ->missing('preview'));
    }

    private function question(Subject $subject, int $marks = 1, string $type = 'single', array $correct = ['a'], float $negative = 0): Question
    {
        $question = Question::create([
            'subject_id' => $subject->id,
            'difficulty' => 'medium',
            'question_text' => '<p>Question</p>',
            'option_a' => '<p>A</p>',
            'option_b' => '<p>B</p>',
            'option_c' => '<p>C</p>',
            'option_d' => '<p>D</p>',
            'question_type' => $type,
            'correct_options' => $correct,
            'explanation' => '<p>Because.</p>',
            'marks' => $marks,
            'negative_marks' => $negative,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        $question->classLevels()->attach($this->classSix->id);

        return $question;
    }

    /**
     * @param  array<int, array{0: string, 1: Subject, 2: array<int, Question>}>  $sections
     */
    private function exam(array $sections, array $overrides = []): Exam
    {
        $this->actingAs($this->admin)->post(route('admin.exams.store'), [
            'subject_id' => $this->math->id,
            'class_level_id' => $this->classSix->id,
            'name' => 'Mathematics Olympiad - Class 6',
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'fee_amount' => 0,
            'fee_currency' => 'INR',
            'scoring_mode' => 'question_bank',
            'marks_per_question' => 1,
            'negative_marking_enabled' => false,
            'negative_marks_per_question' => 0,
            'randomize_questions' => false,
            'randomize_options' => false,
            'show_result_immediately' => true,
            'status' => 'draft',
            ...$overrides,
            'sections' => collect($sections)->map(fn (array $section) => [
                'name' => $section[0],
                'subject_id' => $section[1]->id,
                'question_ids' => collect($section[2])->pluck('id')->all(),
            ])->all(),
        ])->assertSessionHasNoErrors();

        return Exam::latest('id')->firstOrFail();
    }

    private function roleWith(array $permissions): AdminRole
    {
        $role = AdminRole::create(['name' => 'Role '.str()->random(8), 'is_active' => true]);

        foreach (AdminPermissions::normalizeMatrix($permissions) as $module => $actions) {
            $role->permissions()->create([
                'module' => $module,
                'can_read' => $actions['read'],
                'can_write' => $actions['write'],
                'can_delete' => $actions['delete'],
            ]);
        }

        return $role;
    }
}
