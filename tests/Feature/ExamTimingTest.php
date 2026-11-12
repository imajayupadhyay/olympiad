<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamEnrollment;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamSessionService;
use App\Services\ManagedEmailService;
use App\Support\LocalTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Exam timing end to end (workflow.md §5.7).
 *
 * The admin types the availability window in India time. It is stored as a UTC
 * instant, the server decides availability from that instant, and every screen shows
 * the India wall time again. The exam duration runs from the moment a student
 * starts, independent of the window (capped at the window's close).
 */
class ExamTimingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private Subject $subject;

    private ClassLevel $class;

    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday 11 Nov 2026, 6:00 PM in India — the evening before the exam.
        Carbon::setTestNow(LocalTime::parse('2026-11-11 18:00'));

        $this->subject = Subject::create(['name' => 'Mathematics', 'slug' => 'mathematics', 'is_active' => true, 'sort_order' => 1]);
        $this->class = ClassLevel::create(['level' => 6, 'label' => 'Class 6', 'is_active' => true, 'sort_order' => 6]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->student = User::factory()->create(['role' => 'student', 'class_level_id' => $this->class->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_app_stores_utc_and_displays_india_time(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('Asia/Kolkata', LocalTime::timezone());
        $this->assertSame('IST', LocalTime::abbreviation());
        $this->assertSame('2026-11-12 03:30:00', LocalTime::parse('2026-11-12T09:00')->toDateTimeString());
        $this->assertSame('2026-11-12T09:00', LocalTime::forInput(Carbon::parse('2026-11-12 03:30:00')));
        $this->assertNull(LocalTime::parse(''));
        $this->assertNull(LocalTime::forInput(null));
    }

    public function test_admin_window_is_stored_as_the_instant_and_shown_back_exactly_as_typed(): void
    {
        $exam = $this->examFromAdminForm('2026-11-12T09:00', '2026-11-12T21:00');

        // Database: the real instants (9:00 AM / 9:00 PM IST are 03:30 / 15:30 UTC).
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'starts_at' => '2026-11-12 03:30:00', 'ends_at' => '2026-11-12 15:30:00']);

        // Admin edit form: exactly what was typed.
        $this->actingAs($this->admin)->get(route('admin.exams.edit', $exam))->assertInertia(fn (Assert $page) => $page
            ->where('exam.starts_at', '2026-11-12T09:00')
            ->where('exam.ends_at', '2026-11-12T21:00')
            ->where('timezone.name', 'Asia/Kolkata')
            ->where('timezone.abbr', 'IST'));

        // Saving the form again without touching the times must not move them.
        $this->put(route('admin.exams.update', $exam), $this->formPayload('2026-11-12T09:00', '2026-11-12T21:00'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'starts_at' => '2026-11-12 03:30:00', 'ends_at' => '2026-11-12 15:30:00']);
    }

    public function test_students_receive_the_configured_window_on_every_screen(): void
    {
        $exam = $this->publishedExam('2026-11-12T09:00', '2026-11-12T21:00');
        $this->enrol($exam);

        // Student pages get the instant; the browser formats it in the shared display timezone.
        $instantIs = fn (string $utc) => fn ($value) => Carbon::parse($value)->equalTo(Carbon::parse($utc, 'UTC'));

        $this->actingAs($this->student)->get(route('student.exams.show', $exam))->assertInertia(fn (Assert $page) => $page
            ->where('exam.starts_at', $instantIs('2026-11-12 03:30:00'))
            ->where('exam.ends_at', $instantIs('2026-11-12 15:30:00'))
            ->where('exam.availability', 'upcoming')
            ->where('timezone.name', 'Asia/Kolkata'));

        $this->get(route('student.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('myExams.0.starts_at', $instantIs('2026-11-12 03:30:00'))
            ->where('myExams.0.ends_at', $instantIs('2026-11-12 15:30:00'))
            ->where('myExams.0.action', 'upcoming'));

        // Server-formatted places show the India wall time.
        $sessions = app(ExamSessionService::class);
        $this->assertSame('Thu, 12 Nov · 9:00 AM – 9:00 PM', $sessions->windowLabel($exam->starts_at, $exam->ends_at));

        $this->get('/exam-dates')->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.date', '2026-11-12')
            ->where('sessions.0.start_time', '9:00 AM')
            ->where('sessions.0.end_time', '9:00 PM'));

        $variables = app(ManagedEmailService::class)->examReminderVariables($this->student, $exam);
        $this->assertSame('12 Nov 2026, 09:00 AM', $variables['exam_start_datetime']);
        $this->assertSame('12 Nov 2026, 09:00 PM', $variables['exam_end_datetime']);
    }

    public function test_exam_opens_exactly_at_the_configured_time_and_closes_at_the_configured_time(): void
    {
        $exam = $this->publishedExam('2026-11-12T09:00', '2026-11-12T21:00');
        $this->enrol($exam);
        $sessions = app(ExamSessionService::class);

        $stateAt = function (string $indiaTime) use ($exam, $sessions) {
            Carbon::setTestNow(LocalTime::parse($indiaTime));

            return [$exam->fresh()->availabilityState(), $sessions->startBlocker($this->student, $exam->fresh())];
        };

        $this->assertSame(['upcoming', 'This exam has not opened yet.'], $stateAt('2026-11-12 08:59:59'));
        $this->assertSame(['live', null], $stateAt('2026-11-12 09:00:00'));       // opens on the dot
        $this->assertSame(['live', null], $stateAt('2026-11-12 14:30:00'));       // old bug: this was the real opening time
        $this->assertSame(['live', null], $stateAt('2026-11-12 21:00:00'));       // still open at the closing minute
        $this->assertSame(['closed', 'The exam window has closed.'], $stateAt('2026-11-12 21:00:01'));
        $this->assertSame(['closed', 'The exam window has closed.'], $stateAt('2026-11-13 02:00:00'));   // old bug: was still open

        // The real start route agrees: refused before, accepted inside, refused after.
        Carbon::setTestNow(LocalTime::parse('2026-11-12 08:30'));
        $this->actingAs($this->student)->post(route('student.exams.start', $exam))->assertRedirect(route('student.exams.show', $exam));
        $this->assertDatabaseCount('exam_attempts', 0);

        Carbon::setTestNow(LocalTime::parse('2026-11-12 09:00'));
        $this->post(route('student.exams.start', $exam))->assertRedirect();
        $this->assertDatabaseCount('exam_attempts', 1);
    }

    public function test_a_student_cannot_start_after_the_window_closed(): void
    {
        $exam = $this->publishedExam('2026-11-12T09:00', '2026-11-12T21:00');
        $this->enrol($exam);

        Carbon::setTestNow(LocalTime::parse('2026-11-12 21:05'));
        $this->actingAs($this->student)->post(route('student.exams.start', $exam))->assertRedirect(route('student.exams.show', $exam));
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_duration_runs_from_the_moment_the_student_starts_whenever_that_is_in_the_window(): void
    {
        $exam = $this->publishedExam('2026-11-12T09:00', '2026-11-12T21:00', 60);

        foreach (['2026-11-12 09:00', '2026-11-12 13:17', '2026-11-12 19:59'] as $i => $indiaTime) {
            $student = User::factory()->create(['role' => 'student', 'class_level_id' => $this->class->id]);
            $this->enrol($exam, $student);
            Carbon::setTestNow(LocalTime::parse($indiaTime));

            $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect();
            $attempt = ExamAttempt::where('user_id', $student->id)->firstOrFail();

            $this->assertTrue($attempt->started_at->equalTo(now()), "start {$indiaTime}");
            $this->assertTrue($attempt->deadline_at->equalTo(now()->addMinutes(60)), "full 60 minutes from {$indiaTime}");
            $this->assertSame(3600, $attempt->remainingSeconds());
        }
    }

    public function test_a_late_start_is_capped_at_the_window_close(): void
    {
        $exam = $this->publishedExam('2026-11-12T09:00', '2026-11-12T21:00', 60);
        $this->enrol($exam);

        Carbon::setTestNow(LocalTime::parse('2026-11-12 20:40'));   // 20 minutes before the window closes
        $this->actingAs($this->student)->post(route('student.exams.start', $exam))->assertRedirect();
        $attempt = ExamAttempt::firstOrFail();

        $this->assertTrue($attempt->deadline_at->equalTo(LocalTime::parse('2026-11-12 21:00')));
        $this->assertSame(20 * 60, $attempt->remainingSeconds());
    }

    public function test_refreshing_the_room_never_changes_the_deadline_and_the_timer_follows_the_server(): void
    {
        $exam = $this->publishedExam('2026-11-12T09:00', '2026-11-12T21:00', 60);
        $this->enrol($exam);

        Carbon::setTestNow(LocalTime::parse('2026-11-12 10:00'));
        $this->actingAs($this->student)->post(route('student.exams.start', $exam))->assertRedirect();
        $attempt = ExamAttempt::firstOrFail();
        $deadline = $attempt->deadline_at->copy();

        $this->get(route('student.exam-room', $attempt))->assertInertia(fn (Assert $page) => $page->where('remaining_seconds', 3600));

        $this->travel(15)->minutes();
        foreach (range(1, 3) as $reload) {
            $this->get(route('student.exam-room', $attempt))->assertInertia(fn (Assert $page) => $page->where('remaining_seconds', 45 * 60));
        }

        $this->assertTrue($attempt->fresh()->deadline_at->equalTo($deadline));
        $this->assertTrue($attempt->fresh()->started_at->equalTo(LocalTime::parse('2026-11-12 10:00')));
    }

    public function test_changing_the_window_does_not_disturb_an_attempt_in_progress(): void
    {
        $exam = $this->publishedExam('2026-11-12T09:00', '2026-11-12T21:00', 60);
        $this->enrol($exam);

        Carbon::setTestNow(LocalTime::parse('2026-11-12 10:00'));
        $this->actingAs($this->student)->post(route('student.exams.start', $exam))->assertRedirect();
        $attempt = ExamAttempt::firstOrFail();

        // The admin shortens the window while the student is writing.
        $exam->update(['ends_at' => LocalTime::parse('2026-11-12T10:10')]);
        $this->travel(20)->minutes();

        $this->actingAs($this->student)->get(route('student.exam-room', $attempt))
            ->assertInertia(fn (Assert $page) => $page->where('remaining_seconds', 40 * 60));
        $this->assertSame('in_progress', $attempt->fresh()->status);
    }

    public function test_windows_on_other_dates_and_across_midnight_work(): void
    {
        $sessions = app(ExamSessionService::class);

        // An overnight window: 10:00 PM on the 14th to 2:00 AM on the 15th.
        $night = $this->publishedExam('2026-11-14T22:00', '2026-11-15T02:00', 30);
        $this->enrol($night);
        $this->assertDatabaseHas('exams', ['id' => $night->id, 'starts_at' => '2026-11-14 16:30:00', 'ends_at' => '2026-11-14 20:30:00']);
        $this->assertSame('Sat, 14 Nov · 10:00 PM – Sun, 15 Nov · 2:00 AM', $sessions->windowLabel($night->starts_at, $night->ends_at));

        // An early-morning exam whose UTC date is the previous day still lists on its India date.
        $dawn = $this->publishedExam('2026-12-01T02:00', '2026-12-01T05:00', 30);
        $this->assertDatabaseHas('exams', ['id' => $dawn->id, 'starts_at' => '2026-11-30 20:30:00']);
        $dates = collect($this->get('/exam-dates')->viewData('page')['props']['sessions']);
        $this->assertSame(['2026-11-14', '2026-12-01'], $dates->pluck('date')->all());
        $this->assertSame(['10:00 PM', '2:00 AM'], $dates->pluck('start_time')->all());
        $this->assertSame('December 2026', $dates[1]['month_label']);

        $states = [];
        foreach (['2026-11-14 21:59', '2026-11-14 22:00', '2026-11-15 01:59', '2026-11-15 02:01'] as $indiaTime) {
            Carbon::setTestNow(LocalTime::parse($indiaTime));
            $states[] = $sessions->windowState($night->fresh(), null);
        }
        $this->assertSame(['upcoming', 'live', 'live', 'closed'], $states);
    }

    public function test_exam_dates_lists_an_exam_until_the_end_of_its_india_day(): void
    {
        // Opens 12:30 AM India time on the 12th (19:00 UTC on the 11th), no closing time.
        $this->publishedExam('2026-11-12T00:30', null);

        // 11:00 PM India time on the 12th is still "today" for the calendar.
        Carbon::setTestNow(LocalTime::parse('2026-11-12 23:00'));
        $this->assertCount(1, $this->get('/exam-dates')->viewData('page')['props']['sessions']);
    }

    public function test_a_rescheduled_personal_window_is_typed_and_shown_in_india_time(): void
    {
        $exam = $this->publishedExam('2026-11-12T09:00', '2026-11-12T21:00', 30);
        $enrollment = $this->enrol($exam);

        $this->actingAs($this->admin)->post(route('admin.exam-sessions.enrollments.reschedule', $enrollment), [
            'reason' => 'Was ill on exam day', 'window_starts_at' => '2026-11-14T16:00', 'window_ends_at' => '2026-11-14T18:00',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('exam_enrollments', ['id' => $enrollment->id, 'window_starts_at' => '2026-11-14 10:30:00', 'window_ends_at' => '2026-11-14 12:30:00']);

        $this->actingAs($this->student)->get(route('student.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('myExams.0.action', 'rescheduled')
            ->where('myExams.0.window_label', 'Sat, 14 Nov · 4:00 PM – 6:00 PM'));

        // Closed on the exam's own day, open at 4:00 PM India time on the personal day.
        Carbon::setTestNow(LocalTime::parse('2026-11-14 15:59'));
        $this->post(route('student.exams.start', $exam))->assertRedirect(route('student.exams.show', $exam));
        $this->assertDatabaseCount('exam_attempts', 0);

        Carbon::setTestNow(LocalTime::parse('2026-11-14 16:00'));
        $this->post(route('student.exams.start', $exam))->assertRedirect();
        $this->assertTrue(ExamAttempt::firstOrFail()->deadline_at->equalTo(now()->addMinutes(30)));
    }

    public function test_the_migration_converts_previously_typed_times_and_leaves_attempts_alone(): void
    {
        $exam = $this->publishedExam('2026-11-12T09:00', '2026-11-12T21:00');
        $enrollment = $this->enrol($exam);
        $attempt = ExamAttempt::create([
            'user_id' => $this->student->id, 'exam_id' => $exam->id, 'status' => 'in_progress',
            'started_at' => '2026-11-12 04:00:00', 'deadline_at' => '2026-11-12 05:00:00',
        ]);

        // Rows as the old code wrote them: the typed wall time, unconverted.
        DB::table('exams')->where('id', $exam->id)->update(['starts_at' => '2026-11-12 09:00:00', 'ends_at' => '2026-11-12 21:00:00', 'result_release_at' => null, 'updated_at' => '2026-11-01 00:00:00']);
        DB::table('exam_enrollments')->where('id', $enrollment->id)->update(['window_starts_at' => '2026-11-13 10:00:00', 'window_ends_at' => '2026-11-13 12:00:00']);

        $migration = require database_path('migrations/2026_11_12_100000_convert_exam_schedule_times_to_utc.php');
        $migration->up();

        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'starts_at' => '2026-11-12 03:30:00', 'ends_at' => '2026-11-12 15:30:00', 'result_release_at' => null, 'updated_at' => '2026-11-01 00:00:00']);
        $this->assertDatabaseHas('exam_enrollments', ['id' => $enrollment->id, 'window_starts_at' => '2026-11-13 04:30:00', 'window_ends_at' => '2026-11-13 06:30:00']);
        $this->assertDatabaseHas('exam_attempts', ['id' => $attempt->id, 'started_at' => '2026-11-12 04:00:00', 'deadline_at' => '2026-11-12 05:00:00']);
        $this->assertSame('2026-11-12T09:00', LocalTime::forInput($exam->fresh()->starts_at));

        $migration->down();
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'starts_at' => '2026-11-12 09:00:00', 'ends_at' => '2026-11-12 21:00:00']);
        $this->assertDatabaseHas('exam_enrollments', ['id' => $enrollment->id, 'window_starts_at' => '2026-11-13 10:00:00']);
    }

    /* ───────────────────────── helpers ───────────────────────── */

    private function formPayload(string $opens, ?string $closes, int $duration = 60): array
    {
        return [
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->class->id,
            'name' => 'Maths Olympiad - Class 6',
            'starts_at' => $opens,
            'ends_at' => $closes,
            'duration_minutes' => $duration,
            'fee_amount' => 0,
            'fee_currency' => 'INR',
            'scoring_mode' => 'question_bank',
            'marks_per_question' => 1,
            'negative_marking_enabled' => false,
            'negative_marks_per_question' => 0,
            'randomize_questions' => false,
            'randomize_options' => false,
            'show_result_immediately' => false,
            'result_release_at' => null,
            'status' => 'draft',
        ];
    }

    /** Created through the real admin form endpoint, so the typed wall time takes the real path. */
    private function examFromAdminForm(string $opens, ?string $closes, int $duration = 60): Exam
    {
        $before = Exam::max('id') ?? 0;
        $this->actingAs($this->admin)->post(route('admin.exams.store'), $this->formPayload($opens, $closes, $duration))->assertSessionHasNoErrors();

        return Exam::where('id', '>', $before)->latest('id')->firstOrFail();
    }

    private function publishedExam(string $opens, ?string $closes, int $duration = 60): Exam
    {
        $exam = $this->examFromAdminForm($opens, $closes, $duration);
        $section = $exam->sections()->first() ?? $exam->sections()->create(['subject_id' => $this->subject->id, 'name' => 'General', 'sort_order' => 1]);

        $question = Question::create([
            'subject_id' => $this->subject->id, 'difficulty' => 'easy', 'question_text' => '<p>Q</p>',
            'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D',
            'question_type' => 'single', 'correct_options' => ['a'], 'marks' => 1, 'negative_marks' => 0,
            'is_active' => true, 'created_by' => $this->admin->id,
        ]);
        $question->classLevels()->attach($this->class->id);
        $exam->questions()->attach($question->id, ['exam_section_id' => $section->id, 'sort_order' => 1, 'marks' => 1, 'negative_marks' => 0]);
        $exam->forceFill(['status' => 'published', 'published_at' => now()])->save();

        return $exam->fresh();
    }

    private function enrol(Exam $exam, ?User $student = null): ExamEnrollment
    {
        return ExamEnrollment::create([
            'user_id' => ($student ?? $this->student)->id, 'exam_id' => $exam->id, 'status' => 'enrolled', 'enrolled_at' => now(),
        ]);
    }
}
