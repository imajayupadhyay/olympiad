<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSection;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminExamSectionsTest extends TestCase
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

    public function test_exam_is_created_with_ordered_sections_from_several_subjects(): void
    {
        [$r1, $r2] = [$this->question($this->reasoning), $this->question($this->reasoning)];
        [$m1, $m2] = [$this->question($this->math, marks: 2), $this->question($this->math, marks: 2)];

        $this->actingAs($this->admin)->post(route('admin.exams.store'), [
            ...$this->examPayload(),
            'negative_marking_enabled' => true,
            'negative_marks_per_question' => 0.25,
            'sections' => [
                ['name' => 'Reasoning', 'subject_id' => $this->reasoning->id, 'marks_per_question' => 3, 'negative_marks_per_question' => 1, 'question_ids' => [$r2->id, $r1->id]],
                ['name' => 'Mathematics', 'subject_id' => $this->math->id, 'instructions' => 'Show working.', 'question_ids' => [$m1->id, $m2->id]],
            ],
        ])->assertSessionHasNoErrors();

        $exam = Exam::firstOrFail();
        $sections = $exam->sections()->get();
        $this->assertSame(['Reasoning', 'Mathematics'], $sections->pluck('name')->all());
        $this->assertSame('Show working.', $sections[1]->instructions);

        $rows = DB::table('exam_questions')->where('exam_id', $exam->id)->orderBy('sort_order')->get();
        $this->assertSame([$r2->id, $r1->id, $m1->id, $m2->id], $rows->pluck('question_id')->all());
        $this->assertSame([1, 2, 3, 4], $rows->pluck('sort_order')->map(fn ($order) => (int) $order)->all());
        $this->assertSame([$sections[0]->id, $sections[0]->id, $sections[1]->id, $sections[1]->id], $rows->pluck('exam_section_id')->map(fn ($id) => (int) $id)->all());

        // Section override wins for Reasoning; Mathematics falls back to the question bank marks.
        $this->assertEquals([3, 3, 2, 2], $rows->pluck('marks')->map(fn ($marks) => (float) $marks)->all());
        $this->assertEquals([1, 1, 0, 0], $rows->pluck('negative_marks')->map(fn ($marks) => (float) $marks)->all());
    }

    public function test_section_rejects_questions_from_another_subject(): void
    {
        $mathQuestion = $this->question($this->math);

        $this->actingAs($this->admin)->post(route('admin.exams.store'), [
            ...$this->examPayload(),
            'sections' => [
                ['name' => 'Reasoning', 'subject_id' => $this->reasoning->id, 'question_ids' => [$mathQuestion->id]],
            ],
        ])->assertSessionHasErrors('sections.0.question_ids');

        $this->assertDatabaseCount('exams', 0);
    }

    public function test_a_question_cannot_appear_in_two_sections(): void
    {
        $question = $this->question($this->math);

        $this->actingAs($this->admin)->post(route('admin.exams.store'), [
            ...$this->examPayload(),
            'sections' => [
                ['name' => 'Part A', 'subject_id' => $this->math->id, 'question_ids' => [$question->id]],
                ['name' => 'Part B', 'subject_id' => $this->math->id, 'question_ids' => [$question->id]],
            ],
        ])->assertSessionHasErrors('sections.1.question_ids');
    }

    public function test_update_reorders_sections_moves_questions_and_removes_sections(): void
    {
        [$r1, $m1, $m2] = [$this->question($this->reasoning), $this->question($this->math), $this->question($this->math)];
        $exam = $this->examWithSections([
            ['Reasoning', $this->reasoning, [$r1]],
            ['Mathematics', $this->math, [$m1, $m2]],
            ['Spare', $this->math, []],
        ]);
        [$reasoning, $mathematics] = $exam->sections()->get();

        $this->actingAs($this->admin)->put(route('admin.exams.update', $exam), [
            ...$this->examPayload(),
            'sections' => [
                ['id' => $mathematics->id, 'name' => 'Maths', 'subject_id' => $this->math->id, 'question_ids' => [$m2->id, $m1->id]],
                ['id' => $reasoning->id, 'name' => 'Reasoning', 'subject_id' => $this->reasoning->id, 'question_ids' => [$r1->id]],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame([$mathematics->id, $reasoning->id], $exam->sections()->pluck('id')->all());
        $this->assertSame('Maths', $mathematics->fresh()->name);
        $this->assertDatabaseCount('exam_sections', 2);
        $this->assertSame([$m2->id, $m1->id, $r1->id], $exam->questions()->pluck('questions.id')->all());
    }

    public function test_publishing_is_blocked_by_an_empty_section(): void
    {
        $question = $this->question($this->math);

        $this->actingAs($this->admin)->post(route('admin.exams.store'), [
            ...$this->examPayload(),
            'status' => 'published',
            'sections' => [
                ['name' => 'Mathematics', 'subject_id' => $this->math->id, 'question_ids' => [$question->id]],
                ['name' => 'Reasoning', 'subject_id' => $this->reasoning->id, 'question_ids' => []],
            ],
        ])->assertSessionHasErrors('sections.1.question_ids');

        $exam = $this->examWithSections([['Mathematics', $this->math, [$question]], ['Reasoning', $this->reasoning, []]]);

        $this->actingAs($this->admin)->patch(route('admin.exams.publish', $exam))->assertSessionHas('error');
        $this->assertSame('draft', $exam->fresh()->status);
    }

    public function test_edit_page_provides_sections_with_ordered_questions(): void
    {
        [$r1, $m1] = [$this->question($this->reasoning), $this->question($this->math)];
        $exam = $this->examWithSections([['Reasoning', $this->reasoning, [$r1]], ['Mathematics', $this->math, [$m1]]]);

        $this->actingAs($this->admin)->get(route('admin.exams.edit', $exam))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Exams/Edit')
                ->has('exam.sections', 2)
                ->where('exam.sections.0.name', 'Reasoning')
                ->where('exam.sections.0.questions.0.id', $r1->id)
                ->where('exam.sections.1.questions.0.id', $m1->id)
                ->where('exam.sections.1.questions.0.correct_options', ['a'])
            );
    }

    public function test_duplicate_copies_sections_and_question_placement(): void
    {
        [$r1, $m1] = [$this->question($this->reasoning), $this->question($this->math)];
        $exam = $this->examWithSections([['Reasoning', $this->reasoning, [$r1]], ['Mathematics', $this->math, [$m1]]]);

        $this->actingAs($this->admin)->post(route('admin.exams.duplicate', $exam))->assertRedirect();

        $copy = Exam::whereKeyNot($exam->id)->firstOrFail();
        $this->assertSame(['Reasoning', 'Mathematics'], $copy->sections()->pluck('name')->all());
        $this->assertSame(
            $copy->sections()->pluck('id')->all(),
            $copy->questions()->get()->pluck('pivot.exam_section_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_questions_written_in_the_builder_are_saved_to_the_bank_as_json(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.questions.store'), [
            'subject_id' => $this->math->id,
            'class_level_ids' => [$this->classSix->id],
            'difficulty' => 'easy',
            'question_type' => 'single',
            'question_text' => '<p>2 + 2 = ?</p>',
            'option_a' => '<p>3</p>',
            'option_b' => '<p>4</p>',
            'option_c' => '<p>5</p>',
            'option_d' => '<p>6</p>',
            'correct_options' => ['b'],
            'marks' => 1,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('question.subject_id', $this->math->id)
            ->assertJsonPath('question.correct_options', ['b'])
            ->assertJsonPath('question.class_levels.0.id', $this->classSix->id);

        $this->assertDatabaseHas('questions', ['subject_id' => $this->math->id, 'question_text' => '<p>2 + 2 = ?</p>']);
    }

    public function test_exam_room_shuffles_only_within_sections(): void
    {
        $reasoning = collect(range(1, 6))->map(fn () => $this->question($this->reasoning));
        $math = collect(range(1, 6))->map(fn () => $this->question($this->math));
        $exam = $this->examWithSections([['Reasoning', $this->reasoning, $reasoning->all()], ['Mathematics', $this->math, $math->all()]]);
        $exam->update(['status' => 'published', 'randomize_questions' => true, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHours(3)]);

        $student = User::factory()->create(['role' => 'student']);
        $attempt = ExamAttempt::create(['user_id' => $student->id, 'exam_id' => $exam->id, 'status' => 'in_progress', 'started_at' => now()]);

        $this->actingAs($student)->get(route('student.exam-room', $attempt))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/ExamRoom/Index')
                ->has('sections', 2)
                ->where('sections.0.name', 'Reasoning')
                ->where('questions', function ($questions) use ($reasoning, $math, $exam) {
                    $ids = collect($questions)->pluck('id');
                    $sectionIds = $exam->sections()->pluck('id');

                    return $ids->take(6)->sort()->values()->all() === $reasoning->pluck('id')->sort()->values()->all()
                        && $ids->slice(6)->sort()->values()->all() === $math->pluck('id')->sort()->values()->all()
                        && collect($questions)->pluck('section_id')->unique()->values()->all() === $sectionIds->all();
                })
            );
    }

    public function test_migration_moves_existing_exam_questions_into_a_general_section(): void
    {
        $migration = require database_path('migrations/2026_10_10_120000_create_exam_sections_table.php');
        $migration->down();

        $question = $this->question($this->math);
        $exam = Exam::create([...$this->examPayload(), 'slug' => 'legacy', 'exam_code' => 'NEO-LEGACY']);
        $empty = Exam::create([...$this->examPayload(), 'slug' => 'empty', 'exam_code' => 'NEO-EMPTY']);
        DB::table('exam_questions')->insert(['exam_id' => $exam->id, 'question_id' => $question->id, 'sort_order' => 1, 'marks' => 1, 'negative_marks' => 0]);

        $migration->up();

        $section = ExamSection::where('exam_id', $exam->id)->firstOrFail();
        $this->assertSame('General', $section->name);
        $this->assertSame($this->math->id, $section->subject_id);
        $this->assertSame($section->id, (int) DB::table('exam_questions')->where('exam_id', $exam->id)->value('exam_section_id'));
        $this->assertSame(0, ExamSection::where('exam_id', $empty->id)->count());
    }

    private function question(Subject $subject, int $marks = 1): Question
    {
        $question = Question::create([
            'subject_id' => $subject->id,
            'difficulty' => 'medium',
            'question_text' => '<p>Question</p>',
            'option_a' => '<p>A</p>',
            'option_b' => '<p>B</p>',
            'option_c' => '<p>C</p>',
            'option_d' => '<p>D</p>',
            'question_type' => 'single',
            'correct_options' => ['a'],
            'marks' => $marks,
            'negative_marks' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        $question->classLevels()->attach($this->classSix->id);

        return $question;
    }

    /**
     * @param  array<int, array{0: string, 1: Subject, 2: array<int, Question>}>  $sections
     */
    private function examWithSections(array $sections): Exam
    {
        $this->actingAs($this->admin)->post(route('admin.exams.store'), [
            ...$this->examPayload(),
            'sections' => collect($sections)->map(fn (array $section) => [
                'name' => $section[0],
                'subject_id' => $section[1]->id,
                'question_ids' => collect($section[2])->pluck('id')->all(),
            ])->all(),
        ])->assertSessionHasNoErrors();

        return Exam::latest('id')->firstOrFail();
    }

    private function examPayload(): array
    {
        return [
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
        ];
    }
}
