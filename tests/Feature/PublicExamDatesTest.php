<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * /exam-dates — the display-only calendar of upcoming olympiad sessions,
 * built from the published exams' schedules.
 */
class PublicExamDatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function subject(string $name, int $sortOrder = 1, bool $active = true): Subject
    {
        return Subject::create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'is_active' => $active,
            'sort_order' => $sortOrder,
        ]);
    }

    private function classLevel(int $level): ClassLevel
    {
        return ClassLevel::firstOrCreate(
            ['level' => $level],
            ['label' => "Class {$level}", 'is_active' => true, 'sort_order' => $level],
        );
    }

    private function exam(Subject $subject, int $level, array $overrides = []): Exam
    {
        static $n = 0;
        $n++;

        return Exam::create(array_merge([
            'subject_id' => $subject->id,
            'class_level_id' => $this->classLevel($level)->id,
            'name' => "{$subject->name} Olympiad Class {$level}",
            'slug' => 'date-exam-'.$n,
            'exam_code' => 'ED'.(1000 + $n),
            'starts_at' => '2026-11-15 10:00:00',
            'ends_at' => '2026-11-15 13:00:00',
            'duration_minutes' => 60,
            'fee_amount' => 299,
            'fee_currency' => 'INR',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    /** @return array<int, array<string, mixed>> */
    private function sessions(): array
    {
        return $this->get('/exam-dates')->assertOk()->viewData('page')['props']['sessions'];
    }

    public function test_page_renders_and_is_indexable(): void
    {
        $response = $this->get('/exam-dates');

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Public/ExamDates/Index')
                ->has('sessions', 0));

        $this->assertFalse($response->headers->has('X-Robots-Tag'));
    }

    public function test_classes_sitting_the_same_session_share_one_card(): void
    {
        $maths = $this->subject('Mathematics');
        foreach (range(1, 5) as $level) {
            $this->exam($maths, $level);
        }

        $sessions = $this->sessions();

        $this->assertCount(1, $sessions);
        $this->assertSame('Mathematics', $sessions[0]['subject']['name']);
        $this->assertSame('Class 1–5', $sessions[0]['class_range']);
        $this->assertSame('2026-11-15', $sessions[0]['date']);
        $this->assertSame('Sunday, 15 November 2026', $sessions[0]['date_label']);
        $this->assertSame('10:00 AM', $sessions[0]['start_time']);
        $this->assertSame('1:00 PM', $sessions[0]['end_time']);
        $this->assertNull($sessions[0]['closes_label']);
        $this->assertSame('upcoming', $sessions[0]['status']);
    }

    public function test_different_times_split_into_separate_cards_in_date_order(): void
    {
        $science = $this->subject('Science');
        $this->exam($science, 6, ['starts_at' => '2026-12-02 09:30:00', 'ends_at' => null]);
        $this->exam($science, 1, ['starts_at' => '2026-11-20 14:00:00', 'ends_at' => null]);
        $this->exam($science, 2, ['starts_at' => '2026-11-20 14:00:00', 'ends_at' => null]);

        $sessions = $this->sessions();

        $this->assertCount(2, $sessions);
        $this->assertSame(['Class 1–2', 'Class 6'], array_column($sessions, 'class_range'));
        $this->assertSame(['2:00 PM', '9:30 AM'], array_column($sessions, 'start_time'));
        $this->assertSame(['November 2026', 'December 2026'], array_column($sessions, 'month_label'));
    }

    public function test_gaps_in_classes_are_spelled_out(): void
    {
        $english = $this->subject('English');
        foreach ([1, 2, 3, 6, 7, 10] as $level) {
            $this->exam($english, $level);
        }

        $this->assertSame('Class 1–3, 6–7, 10', $this->sessions()[0]['class_range']);
    }

    public function test_multi_day_window_reports_when_it_closes(): void
    {
        $gk = $this->subject('General Knowledge');
        $this->exam($gk, 4, ['starts_at' => '2026-11-15 10:00:00', 'ends_at' => '2026-11-18 18:00:00']);

        $session = $this->sessions()[0];

        $this->assertNull($session['end_time']);
        $this->assertSame('Wed, 18 Nov · 6:00 PM', $session['closes_label']);
    }

    public function test_only_published_upcoming_exams_of_active_subjects_are_listed(): void
    {
        $maths = $this->subject('Mathematics', 1);
        $hidden = $this->subject('Retired Subject', 2, false);

        $this->exam($maths, 1, ['status' => 'draft']);
        $this->exam($maths, 2, ['starts_at' => '2026-10-01 10:00:00', 'ends_at' => '2026-10-02 10:00:00']); // closed
        $this->exam($maths, 3, ['starts_at' => '2026-10-01 10:00:00', 'ends_at' => null]); // started, no window
        $this->exam($hidden, 4);
        $this->exam($maths, 5, ['starts_at' => '2026-10-10 08:00:00', 'ends_at' => '2026-10-10 12:00:00']); // live today
        $this->exam($maths, 6);

        $sessions = $this->sessions();

        $this->assertSame(['Class 5', 'Class 6'], array_column($sessions, 'class_range'));
        $this->assertSame(['live', 'upcoming'], array_column($sessions, 'status'));
    }

    public function test_rescheduling_an_exam_moves_it_on_the_calendar(): void
    {
        $maths = $this->subject('Mathematics');
        $exam = $this->exam($maths, 8);

        $exam->update(['starts_at' => '2027-01-09 11:15:00', 'ends_at' => '2027-01-09 12:15:00']);

        $session = $this->sessions()[0];
        $this->assertSame('2027-01-09', $session['date']);
        $this->assertSame('11:15 AM', $session['start_time']);
        $this->assertSame('January 2027', $session['month_label']);
    }

}
