<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Result;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExamSectionResultsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Exam $exam;
    /** @var array<string, array<int, Question>> */
    private array $questions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $math = Subject::create(['name' => 'Mathematics', 'slug' => 'mathematics', 'is_active' => true, 'sort_order' => 1]);
        $reasoning = Subject::create(['name' => 'Logical Reasoning', 'slug' => 'logical-reasoning', 'is_active' => true, 'sort_order' => 2]);
        $classSix = ClassLevel::create(['level' => 6, 'label' => 'Class 6', 'is_active' => true, 'sort_order' => 6]);

        $make = function (Subject $subject) use ($classSix): Question {
            $question = Question::create([
                'subject_id' => $subject->id, 'difficulty' => 'easy', 'question_text' => '<p>Q</p>',
                'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D',
                'question_type' => 'single', 'correct_options' => ['a'], 'marks' => 1, 'negative_marks' => 0,
                'is_active' => true, 'created_by' => $this->admin->id,
            ]);
            $question->classLevels()->attach($classSix->id);

            return $question;
        };
        $this->questions = [
            'reasoning' => [$make($reasoning), $make($reasoning)],
            'math' => [$make($math), $make($math), $make($math)],
        ];

        $this->actingAs($this->admin)->post(route('admin.exams.store'), [
            'subject_id' => $math->id,
            'class_level_id' => $classSix->id,
            'name' => 'Sectioned Olympiad',
            'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'fee_amount' => 0,
            'fee_currency' => 'INR',
            'scoring_mode' => 'question_bank',
            'marks_per_question' => 1,
            'negative_marking_enabled' => true,
            'negative_marks_per_question' => 0,
            'status' => 'draft',
            'sections' => [
                ['name' => 'Reasoning', 'subject_id' => $reasoning->id, 'marks_per_question' => 3, 'negative_marks_per_question' => 1, 'question_ids' => collect($this->questions['reasoning'])->pluck('id')->all()],
                ['name' => 'Mathematics', 'subject_id' => $math->id, 'marks_per_question' => 2, 'question_ids' => collect($this->questions['math'])->pluck('id')->all()],
            ],
        ])->assertSessionHasNoErrors();
        $this->exam = Exam::firstOrFail();
        $this->exam->update(['status' => 'published']);
    }

    public function test_processing_stores_a_per_section_breakdown(): void
    {
        // Reasoning: 1 correct (+3), 1 wrong (-1). Mathematics: 2 correct (+2 each), 1 skipped.
        $result = $this->scoredResult([
            'reasoning' => [['a'], ['b']],
            'math' => [['a'], ['a'], []],
        ]);

        $this->assertEquals(6.0, $result->total_score);
        $this->assertSame([
            ['section_id' => $this->exam->sections[0]->id, 'name' => 'Reasoning', 'score' => 2, 'max_score' => 6, 'percentage' => 33.33, 'total' => 2, 'correct' => 1, 'wrong' => 1, 'skipped' => 0],
            ['section_id' => $this->exam->sections[1]->id, 'name' => 'Mathematics', 'score' => 4, 'max_score' => 6, 'percentage' => 66.67, 'total' => 3, 'correct' => 2, 'wrong' => 0, 'skipped' => 1],
        ], $result->section_scores);
    }

    public function test_student_scorecard_shows_sections_and_groups_the_review(): void
    {
        $result = $this->scoredResult(['reasoning' => [['a'], ['a']], 'math' => [['a'], ['b'], ['a']]]);
        $result->update(['is_released' => true, 'released_at' => now()]);

        $this->actingAs($result->user)->get(route('student.results.show', $result))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Results/Show')
                ->has('sections', 2)
                ->where('sections.0.name', 'Reasoning')
                ->where('sections.0.score', 6)
                ->where('review.0.section', 'Reasoning')
                ->where('review.2.section', 'Mathematics')
            );
    }

    public function test_admin_results_page_includes_section_stats(): void
    {
        $this->scoredResult(['reasoning' => [['a'], ['a']], 'math' => [[], [], []]]);
        $this->scoredResult(['reasoning' => [['b'], []], 'math' => [['a'], ['a'], ['a']]]);

        $this->actingAs($this->admin)->get(route('admin.results.show', $this->exam))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Results/Show')
                ->where('sectionStats.0.name', 'Reasoning')
                ->where('sectionStats.0.highest', 6)
                ->where('sectionStats.0.average', 2.5)
                ->where('sectionStats.0.accuracy', 66.7)
                ->where('sectionStats.1.average', 3)
                ->has('results.data.0.section_scores', 2)
            );
    }

    public function test_results_processed_before_snapshots_are_computed_live(): void
    {
        $result = $this->scoredResult(['reasoning' => [['a'], []], 'math' => [[], [], []]]);
        $result->forceFill(['section_scores' => null, 'is_released' => true])->save();

        $this->actingAs($result->user)->get(route('student.results.show', $result))
            ->assertInertia(fn (Assert $page) => $page->where('sections.0.score', 3)->where('sections.1.skipped', 3));
    }

    /**
     * Submit one attempt with the given selections per section, score it and process results.
     */
    private function scoredResult(array $selections): Result
    {
        $student = User::factory()->create(['role' => 'student']);
        $attempt = ExamAttempt::create(['user_id' => $student->id, 'exam_id' => $this->exam->id, 'status' => 'in_progress', 'started_at' => now()]);

        foreach ($selections as $section => $answers) {
            foreach ($answers as $index => $selected) {
                Answer::create(['exam_attempt_id' => $attempt->id, 'question_id' => $this->questions[$section][$index]->id, 'selected_options' => $selected]);
            }
        }

        app(ExamScoringService::class)->score($attempt);
        $this->actingAs($this->admin)->post(route('admin.results.process', $this->exam))->assertSessionHas('success');

        return Result::where('exam_attempt_id', $attempt->id)->firstOrFail();
    }
}
