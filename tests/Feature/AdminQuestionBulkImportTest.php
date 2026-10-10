<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Subject;
use App\Models\User;
use App\Services\QuestionBulkImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AdminQuestionBulkImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Subject $science;
    private ClassLevel $classFive;
    private QuestionCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->science = Subject::create(['name' => 'Science', 'slug' => 'science', 'is_active' => true, 'sort_order' => 1]);
        $this->classFive = ClassLevel::create(['level' => 5, 'label' => 'Class 5', 'is_active' => true, 'sort_order' => 5]);
        $this->category = QuestionCategory::create([
            'subject_id' => $this->science->id, 'name' => 'Physics', 'slug' => 'physics', 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    public function test_admin_can_download_a_question_import_template(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.questions.import.template'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_uploaded_questions_are_previewed_without_being_saved(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.questions.import.upload'), [
            'file' => $this->workbook([
                'Science', 'Class 5', 'Science: Physics', 'medium', 'single', 'What is force?', 'Push', 'Pull', 'Light', 'Sound', 'A', 1, 0, 'A push or pull.', 'Yes',
            ]),
        ]);

        $response->assertRedirect(route('admin.questions.import.preview'));
        $response = $this->actingAs($this->admin)->get(route('admin.questions.import.preview'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Questions/BulkImportPreview')
            ->has('questions', 1)
            ->where('questions.0.subject_id', $this->science->id)
            ->where('questions.0.class_level_ids.0', $this->classFive->id)
            ->where('questions.0.question_category_id', $this->category->id)
        );
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_invalid_rows_are_shown_in_preview_and_cannot_be_imported(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.questions.import.upload'), [
            'file' => $this->workbook([
                'Science', '', '', 'medium', 'single', '', 'A', 'B', 'C', 'D', 'A|B', 0, 0, '', 'Yes',
            ]),
        ]);

        $response->assertRedirect(route('admin.questions.import.preview'));
        $response = $this->actingAs($this->admin)->get(route('admin.questions.import.preview'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Questions/BulkImportPreview')
            ->has('questions.0.errors')
        );
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_confirmed_preview_is_imported_with_classes_and_category(): void
    {
        $payload = $this->questionPayload();

        $this->actingAs($this->admin)->post(route('admin.questions.import.store'), [
            'questions' => [$payload],
        ])->assertRedirect(route('admin.questions.index'));

        $this->assertDatabaseHas('questions', [
            'subject_id' => $this->science->id,
            'question_category_id' => $this->category->id,
            'question_text' => '<p>What is force?</p>',
            'created_by' => $this->admin->id,
        ]);
        $this->assertSame([$this->classFive->id], Question::first()->classLevels->pluck('id')->all());
    }

    public function test_import_defaults_an_omitted_negative_mark_to_zero(): void
    {
        $payload = $this->questionPayload();
        $payload['negative_marks'] = null;

        $this->actingAs($this->admin)->post(route('admin.questions.import.store'), [
            'questions' => [$payload],
        ])->assertRedirect(route('admin.questions.index'));

        $this->assertSame(0.0, (float) Question::first()->negative_marks);
    }

    public function test_workbooks_over_the_row_limit_are_rejected_instead_of_truncated(): void
    {
        $row = $this->sheetRow();
        $rows = array_fill(0, QuestionBulkImportService::MAX_ROWS + 1, $row);

        $this->actingAs($this->admin)
            ->from(route('admin.questions.index'))
            ->post(route('admin.questions.import.upload'), ['file' => $this->workbook(...$rows)])
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Question::count());
    }

    public function test_untouched_demo_row_and_stray_columns_are_ignored(): void
    {
        $demo = $this->sheetRow();
        $demo[5] = '[DEMO] Replace this with your question before importing.';
        $row = [...$this->sheetRow(), 'stray note'];

        $this->actingAs($this->admin)
            ->post(route('admin.questions.import.upload'), ['file' => $this->workbook($demo, $row)])
            ->assertRedirect(route('admin.questions.import.preview'));

        $this->actingAs($this->admin)->get(route('admin.questions.import.preview'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('questions', 1)
                ->where('questions.0.source_row', 6)
                ->where('questions.0.errors', []));
    }

    private function sheetRow(): array
    {
        return ['Science', 'Class 5', 'Science: Physics', 'medium', 'single', 'What is force?', 'Push', 'Pull', 'Light', 'Sound', 'A', 1, 0, 'A push or pull.', 'Yes'];
    }

    private function questionPayload(): array
    {
        return [
            'source_row' => 5,
            'subject_id' => $this->science->id,
            'class_level_ids' => [$this->classFive->id],
            'question_category_id' => $this->category->id,
            'difficulty' => 'medium',
            'question_type' => 'single',
            'question_text' => 'What is force?',
            'option_a' => 'Push',
            'option_b' => 'Pull',
            'option_c' => 'Light',
            'option_d' => 'Sound',
            'correct_options' => ['a'],
            'marks' => 1,
            'negative_marks' => 0,
            'explanation' => 'A push or pull.',
            'is_active' => true,
        ];
    }

    private function workbook(array ...$rows): UploadedFile
    {
        $sheet = (new Spreadsheet)->getActiveSheet();
        $sheet->fromArray([
            ['National Olympiad Hunt - Bulk Question Import'],
            ['Instructions'],
            [],
            ['Subject *', 'Class Levels *', 'Category', 'Difficulty *', 'Question Type *', 'Question Text *', 'Option A *', 'Option B *', 'Option C *', 'Option D *', 'Correct Options *', 'Marks *', 'Negative Marks', 'Explanation', 'Active *'],
            ...$rows,
        ]);
        $path = tempnam(sys_get_temp_dir(), 'question-import-');
        (new Xlsx($sheet->getParent()))->save($path);

        return UploadedFile::fake()->createWithContent('questions.xlsx', file_get_contents($path));
    }
}
