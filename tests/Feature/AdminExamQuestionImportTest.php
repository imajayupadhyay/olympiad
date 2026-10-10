<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AdminExamQuestionImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Subject $math;
    private Subject $reasoning;
    private ClassLevel $classSix;
    private ClassLevel $classSeven;
    private Exam $exam;
    private Question $existingMath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->math = Subject::create(['name' => 'Mathematics', 'slug' => 'mathematics', 'is_active' => true, 'sort_order' => 1]);
        $this->reasoning = Subject::create(['name' => 'Logical Reasoning', 'slug' => 'logical-reasoning', 'is_active' => true, 'sort_order' => 2]);
        $this->classSix = ClassLevel::create(['level' => 6, 'label' => 'Class 6', 'is_active' => true, 'sort_order' => 6]);
        $this->classSeven = ClassLevel::create(['level' => 7, 'label' => 'Class 7', 'is_active' => true, 'sort_order' => 7]);
        $this->existingMath = $this->bankQuestion($this->math, 'What is 7 x 8?');

        $this->actingAs($this->admin)->post(route('admin.exams.store'), [
            'subject_id' => $this->math->id,
            'class_level_id' => $this->classSix->id,
            'name' => 'Mathematics Olympiad - Class 6',
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'fee_amount' => 0,
            'fee_currency' => 'INR',
            'scoring_mode' => 'question_bank',
            'marks_per_question' => 1,
            'negative_marks_per_question' => 0,
            'status' => 'draft',
            'sections' => [['name' => 'Mathematics', 'subject_id' => $this->math->id, 'question_ids' => [$this->existingMath->id]]],
        ])->assertSessionHasNoErrors();
        $this->exam = Exam::firstOrFail();
    }

    public function test_save_and_import_lands_in_the_builder_with_the_import_dialog(): void
    {
        $this->actingAs($this->admin)->post(route('admin.exams.store'), [
            'subject_id' => $this->math->id,
            'class_level_id' => $this->classSix->id,
            'name' => 'Imported Olympiad',
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'fee_amount' => 0,
            'fee_currency' => 'INR',
            'scoring_mode' => 'question_bank',
            'marks_per_question' => 1,
            'negative_marks_per_question' => 0,
            'status' => 'draft',
            'sections' => [],
            'then' => 'import',
        ])->assertRedirect(route('admin.exams.edit', ['exam' => Exam::latest('id')->first(), 'step' => 'questions', 'import' => 1]));
    }

    public function test_template_downloads_for_the_exam(): void
    {
        $this->actingAs($this->admin)->get(route('admin.exams.import.template', $this->exam))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_upload_previews_rows_without_saving_anything(): void
    {
        $this->actingAs($this->admin)->post(route('admin.exams.import.upload', $this->exam), [
            'file' => $this->workbook(
                $this->demoRow(),
                $this->row('Reasoning', 'Logical Reasoning', 'Which shape comes next?'),
                $this->row('Mathematics', 'Mathematics', 'What is 12 + 5?'),
            ),
        ])->assertRedirect(route('admin.exams.import.preview', $this->exam));

        $this->actingAs($this->admin)->get(route('admin.exams.import.preview', $this->exam))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Exams/ImportPreview')
                ->has('questions', 2)
                ->where('questions.0.section', 'Reasoning')
                ->where('questions.0.subject_id', $this->reasoning->id)
                ->where('questions.0.class_level_ids', [$this->classSix->id])
                ->where('questions.0.source_row', 6)
                ->where('questions.0.errors', [])
                ->where('exam.sections.0.name', 'Mathematics')
            );

        $this->assertSame(1, Question::count());
        $this->assertSame(1, $this->exam->questions()->count());
    }

    public function test_rows_are_checked_for_mixed_subjects_and_unusable_bank_ids(): void
    {
        $otherClass = $this->bankQuestion($this->math, 'Class 7 only', $this->classSeven);

        $this->actingAs($this->admin)->post(route('admin.exams.import.upload', $this->exam), [
            'file' => $this->workbook(
                $this->row('Part A', 'Mathematics', 'What is 2 + 2?'),
                $this->row('Part A', 'Logical Reasoning', 'Odd one out?'),
                $this->bankRow('Part B', 999999),
                $this->bankRow('Part B', $otherClass->id),
                $this->bankRow('Part C', $this->existingMath->id),
            ),
        ]);

        $this->actingAs($this->admin)->get(route('admin.exams.import.preview', $this->exam))
            ->assertInertia(fn (Assert $page) => $page
                ->where('questions.0.errors', [])
                ->has('questions.1.errors.subject_id')
                ->where('questions.2.errors.question_bank_id', 'No question with this ID exists in the Question Bank.')
                ->where('questions.3.errors.question_bank_id', 'This bank question is not offered to Class 6.')
                ->where('questions.4.in_exam', true)
                ->missing('questions.4.errors.question_bank_id')
            );
    }

    public function test_identical_bank_questions_are_flagged_as_likely_duplicates(): void
    {
        $this->actingAs($this->admin)->post(route('admin.exams.import.upload', $this->exam), [
            'file' => $this->workbook($this->row('Mathematics', 'Mathematics', '  what is 7 X 8? ')),
        ]);

        $this->actingAs($this->admin)->get(route('admin.exams.import.preview', $this->exam))
            ->assertInertia(fn (Assert $page) => $page->where('questions.0.duplicate_of', $this->existingMath->id));
    }

    public function test_append_mode_fills_matching_sections_and_creates_new_ones_in_order(): void
    {
        $existingReasoning = $this->bankQuestion($this->reasoning, 'Find the missing number');

        $this->actingAs($this->admin)->post(route('admin.exams.import.store', $this->exam), [
            'mode' => 'append',
            'questions' => [
                $this->payload('Reasoning', $this->reasoning, 'Which shape comes next?'),
                $this->payload('mathematics', $this->math, 'What is 12 + 5?'),
                ['section' => 'Reasoning', 'question_bank_id' => $existingReasoning->id],
            ],
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.exams.edit', ['exam' => $this->exam, 'step' => 'questions']));

        $sections = $this->exam->sections()->get();
        $this->assertSame(['Mathematics', 'Reasoning'], $sections->pluck('name')->all());

        $newMath = Question::where('question_text', '<p>What is 12 + 5?</p>')->firstOrFail();
        $newReasoning = Question::where('question_text', '<p>Which shape comes next?</p>')->firstOrFail();
        $this->assertSame(
            [$this->existingMath->id, $newMath->id, $newReasoning->id, $existingReasoning->id],
            $this->exam->questions()->pluck('questions.id')->all(),
        );
        $this->assertSame([$this->classSix->id], $newReasoning->classLevels()->pluck('class_levels.id')->all());
        $this->assertTrue($newReasoning->is_active);
        $this->assertSame(4, Question::count());
    }

    public function test_replace_mode_rebuilds_the_paper_from_the_workbook(): void
    {
        $this->actingAs($this->admin)->post(route('admin.exams.import.store', $this->exam), [
            'mode' => 'replace',
            'questions' => [
                $this->payload('Reasoning', $this->reasoning, 'Which shape comes next?'),
                ['section' => 'Mathematics', 'question_bank_id' => $this->existingMath->id],
            ],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(['Reasoning', 'Mathematics'], $this->exam->sections()->pluck('name')->all());
        $this->assertSame(2, $this->exam->questions()->count());
        $this->assertSame($this->existingMath->id, $this->exam->questions()->get()->last()->id);
    }

    public function test_append_mode_rejects_a_bank_question_already_in_the_exam(): void
    {
        $this->actingAs($this->admin)->post(route('admin.exams.import.store', $this->exam), [
            'mode' => 'append',
            'questions' => [['section' => 'Mathematics', 'question_bank_id' => $this->existingMath->id]],
        ])->assertSessionHasErrors(['questions.0.question_bank_id' => 'This question is already in the exam.']);
    }

    public function test_invalid_rows_block_the_import_and_save_nothing(): void
    {
        $payload = $this->payload('Reasoning', $this->reasoning, 'Which shape comes next?');
        $payload['correct_options'] = ['a', 'b'];

        $this->actingAs($this->admin)->post(route('admin.exams.import.store', $this->exam), [
            'mode' => 'append',
            'questions' => [$payload],
        ])->assertSessionHasErrors('questions.0.correct_options');

        $this->assertSame(1, Question::count());
        $this->assertSame(1, $this->exam->sections()->count());
    }

    private function bankQuestion(Subject $subject, string $text, ?ClassLevel $classLevel = null): Question
    {
        $question = Question::create([
            'subject_id' => $subject->id,
            'difficulty' => 'medium',
            'question_text' => "<p>{$text}</p>",
            'option_a' => '<p>A</p>',
            'option_b' => '<p>B</p>',
            'option_c' => '<p>C</p>',
            'option_d' => '<p>D</p>',
            'question_type' => 'single',
            'correct_options' => ['a'],
            'marks' => 1,
            'negative_marks' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        $question->classLevels()->attach(($classLevel ?? $this->classSix)->id);

        return $question;
    }

    private function payload(string $section, Subject $subject, string $text): array
    {
        return [
            'section' => $section,
            'question_bank_id' => null,
            'subject_id' => $subject->id,
            'class_level_ids' => [$this->classSix->id],
            'question_category_id' => '',
            'difficulty' => 'easy',
            'question_type' => 'single',
            'question_text' => $text,
            'option_a' => '1',
            'option_b' => '2',
            'option_c' => '3',
            'option_d' => '4',
            'correct_options' => ['a'],
            'marks' => 1,
            'negative_marks' => null,
            'explanation' => '',
        ];
    }

    private function row(string $section, string $subject, string $text): array
    {
        return [$section, $subject, '', '', 'easy', 'single', $text, '1', '2', '3', '4', 'A', 1, '', '', ''];
    }

    private function bankRow(string $section, int $id): array
    {
        return [$section, '', '', '', '', '', '', '', '', '', '', '', '', '', '', $id];
    }

    private function demoRow(): array
    {
        return ['Section 1', 'Mathematics', 'Class 6', '', 'medium', 'single', '[DEMO] Replace this with your question before importing.', 'Option A', 'Option B', 'Option C', 'Option D', 'A', 1, 0, '', ''];
    }

    private function workbook(array ...$rows): UploadedFile
    {
        $sheet = (new Spreadsheet)->getActiveSheet();
        $sheet->fromArray([
            ['Exam Question Import'],
            ['Instructions'],
            [],
            ['Section *', 'Subject *', 'Class Levels', 'Category', 'Difficulty *', 'Question Type *', 'Question Text *', 'Option A *', 'Option B *', 'Option C *', 'Option D *', 'Correct Options *', 'Marks *', 'Negative Marks', 'Explanation', 'Question Bank ID'],
            ...$rows,
        ]);
        $path = tempnam(sys_get_temp_dir(), 'exam-import-');
        (new Xlsx($sheet->getParent()))->save($path);

        return UploadedFile::fake()->createWithContent('exam.xlsx', file_get_contents($path));
    }
}
