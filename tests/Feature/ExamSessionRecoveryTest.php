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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExamSessionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Exam $exam;

    /** @var array<int, Question> */
    private array $questions = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 10:00:00');

        $subject = Subject::create(['name' => 'Mathematics', 'slug' => 'mathematics', 'is_active' => true, 'sort_order' => 1]);
        $class = ClassLevel::create(['level' => 6, 'label' => 'Class 6', 'is_active' => true, 'sort_order' => 6]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->exam = Exam::create([
            'subject_id' => $subject->id, 'class_level_id' => $class->id, 'name' => 'Maths Olympiad', 'slug' => 'maths-olympiad',
            'exam_code' => 'NEO-TEST-0001', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHours(3),
            'duration_minutes' => 30, 'fee_amount' => 0, 'fee_currency' => 'INR', 'scoring_mode' => 'question_bank',
            'marks_per_question' => 1, 'status' => 'published', 'created_by' => $admin->id,
        ]);
        $section = $this->exam->sections()->create(['subject_id' => $subject->id, 'name' => 'General', 'sort_order' => 1]);

        foreach (range(1, 4) as $i) {
            $question = Question::create([
                'subject_id' => $subject->id, 'difficulty' => 'easy', 'question_text' => "<p>Q{$i}</p>",
                'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D',
                'question_type' => 'single', 'correct_options' => ['a'], 'marks' => 1, 'negative_marks' => 0,
                'is_active' => true, 'created_by' => $admin->id,
            ]);
            $question->classLevels()->attach($class->id);
            $this->exam->questions()->attach($question->id, ['exam_section_id' => $section->id, 'sort_order' => $i, 'marks' => 1, 'negative_marks' => 0]);
            $this->questions[] = $question;
        }

        $this->student = User::factory()->create(['role' => 'student', 'class_level_id' => $class->id]);
        ExamEnrollment::create(['user_id' => $this->student->id, 'exam_id' => $this->exam->id, 'status' => 'enrolled', 'enrolled_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_starting_fixes_a_personal_deadline_capped_by_the_window(): void
    {
        $this->exam->update(['ends_at' => now()->addMinutes(20)]);   // shorter than the 30 min duration

        $attempt = $this->start();

        $this->assertTrue($attempt->deadline_at->equalTo(now()->addMinutes(20)));
        $this->assertDatabaseHas('exam_session_events', ['exam_attempt_id' => $attempt->id, 'type' => 'started']);
    }

    public function test_room_resumes_where_the_student_left_off_with_answers_kept(): void
    {
        $attempt = $this->start();
        $first = $this->roomProps($attempt);
        $this->assertFalse($first['session']['is_resume']);
        $token = $first['session']['token'];

        $this->answer($attempt, $this->questions[0], ['b'], $token);
        $this->answer($attempt, $this->questions[1], ['a'], $token, currentQuestion: $this->questions[2]);

        $this->get(route('student.exam-room', $attempt))->assertInertia(fn (Assert $page) => $page
            ->where('session.is_resume', true)
            ->where('session.answered', 2)
            ->where('session.current_question_id', $this->questions[2]->id)
            ->where('questions.0.selected', ['b'])
            ->where('questions.1.selected', ['a'])
            ->missing('questions.0.correct_options'));
    }

    public function test_a_new_tab_supersedes_the_old_one(): void
    {
        $attempt = $this->start();
        $oldToken = $this->roomToken($attempt);

        $this->travel(30)->seconds();
        $newToken = $this->roomToken($attempt);   // e.g. browser crashed, reopened on a phone
        $this->assertNotSame($oldToken, $newToken);
        $this->assertDatabaseHas('exam_session_events', ['exam_attempt_id' => $attempt->id, 'type' => 'device_switched']);

        $this->answer($attempt, $this->questions[0], ['a'], $oldToken)->assertStatus(409)->assertJsonPath('reason', 'superseded');
        $this->postJson(route('student.exam-room.heartbeat', $attempt), ['session_token' => $oldToken])->assertStatus(409);
        $this->answer($attempt, $this->questions[0], ['a'], $newToken)->assertOk();
    }

    public function test_heartbeat_reports_server_time_and_logs_gaps_as_lost_time(): void
    {
        $attempt = $this->start();
        $token = $this->roomToken($attempt);

        $this->travel(10)->minutes();   // connection dropped for 10 minutes
        $this->postJson(route('student.exam-room.heartbeat', $attempt), ['session_token' => $token, 'current_question_id' => $this->questions[3]->id])
            ->assertOk()
            ->assertJsonPath('paused', false)
            ->assertJsonPath('remaining_seconds', 20 * 60);

        $attempt->refresh();
        $this->assertSame($this->questions[3]->id, $attempt->current_question_id);
        $this->assertSame(600, app(ExamSessionService::class)->lostSeconds($attempt));
    }

    public function test_a_paused_session_rejects_answers_and_hides_the_paper(): void
    {
        $attempt = $this->start();
        $token = $this->roomToken($attempt);
        $attempt->update(['suspended_at' => now()]);

        $this->answer($attempt, $this->questions[0], ['a'], $token)->assertStatus(409)->assertJsonPath('reason', 'paused');
        $this->get(route('student.exam-room', $attempt))->assertInertia(fn (Assert $page) => $page
            ->has('questions', 0)
            ->where('session.paused.rescheduled', false));
        $this->postJson(route('student.exam-room.heartbeat', $attempt), ['session_token' => $this->roomToken($attempt)])
            ->assertOk()->assertJsonPath('paused', true);
    }

    public function test_server_counts_tab_switches_and_auto_submits_on_the_third(): void
    {
        $attempt = $this->start();
        $token = $this->roomToken($attempt);

        $this->postJson(route('student.exam-room.violation', $attempt), ['session_token' => $token])->assertJsonPath('violation_count', 1);
        $this->postJson(route('student.exam-room.violation', $attempt), ['session_token' => $token])->assertJsonPath('auto_submitted', false);
        // A reload keeps the count.
        $this->assertSame(2, $this->roomProps($attempt)['session']['violation_count']);
        $token = $attempt->fresh()->session_token;
        $this->postJson(route('student.exam-room.violation', $attempt), ['session_token' => $token])->assertJsonPath('auto_submitted', true);

        $this->assertSame('auto_submitted', $attempt->fresh()->status);
    }

    public function test_a_stale_timeout_submit_returns_to_the_room_instead_of_submitting(): void
    {
        $attempt = $this->start();

        $this->post(route('student.exam-room.submit', $attempt), ['reason' => 'timeout'])
            ->assertRedirect(route('student.exam-room', $attempt));
        $this->assertTrue($attempt->fresh()->isOpen());

        $this->post(route('student.exam-room.submit', $attempt), ['reason' => 'manual'])
            ->assertRedirect(route('student.exam-room.submitted', $attempt));
        $this->assertSame('submitted', $attempt->fresh()->status);
    }

    public function test_sweeper_finalises_expired_sessions_but_not_paused_ones(): void
    {
        $expired = $this->start();
        $other = User::factory()->create(['role' => 'student']);
        ExamEnrollment::create(['user_id' => $other->id, 'exam_id' => $this->exam->id, 'status' => 'enrolled']);
        $paused = app(ExamSessionService::class)->begin($other, $this->exam, '127.0.0.1');
        $paused->update(['suspended_at' => now()]);

        $this->travel(33)->minutes();   // 30 min duration + more than the 2 min grace
        $this->artisan('exam-attempts:finalize-expired')->assertSuccessful();

        $this->assertSame('timed_out', $expired->fresh()->status);
        $this->assertSame(1800, $expired->fresh()->time_taken_seconds);   // capped at the duration
        $this->assertTrue($paused->fresh()->isOpen());
        $this->assertDatabaseHas('exam_session_events', ['exam_attempt_id' => $expired->id, 'type' => 'timed_out']);
    }

    public function test_time_used_excludes_paused_time(): void
    {
        $attempt = $this->start();
        $this->travel(5)->minutes();
        $attempt->update(['suspended_at' => now()]);
        $this->travel(15)->minutes();   // paused for 15 minutes
        $attempt->refresh();

        $this->assertSame(25 * 60, $attempt->remainingSeconds());   // frozen
        $this->assertSame(300, $attempt->timeUsedSeconds());
    }

    public function test_a_personal_window_lets_a_student_start_after_the_exam_closed(): void
    {
        $this->exam->update(['ends_at' => now()->subMinute()]);
        $this->actingAs($this->student)->post(route('student.exams.start', $this->exam))->assertRedirect(route('student.exams.show', $this->exam));
        $this->assertDatabaseCount('exam_attempts', 0);

        ExamEnrollment::where('user_id', $this->student->id)->update([
            'window_starts_at' => now()->addDay(), 'window_ends_at' => now()->addDay()->addHours(2),
        ]);
        $this->actingAs($this->student)->get(route('student.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('myExams.0.action', 'rescheduled')
            ->where('myExams.0.window_label', 'Wed, 11 Nov · 10:00 AM – 12:00 PM'));

        $this->travel(1)->day();
        $this->post(route('student.exams.start', $this->exam))->assertRedirect();
        $attempt = ExamAttempt::firstOrFail();
        $this->assertTrue($attempt->deadline_at->equalTo(now()->addMinutes(30)));
    }

    /* ───────────────────────── helpers ───────────────────────── */

    private function start(): ExamAttempt
    {
        $this->actingAs($this->student)->post(route('student.exams.start', $this->exam))->assertRedirect();

        return ExamAttempt::where('user_id', $this->student->id)->firstOrFail();
    }

    private function roomProps(ExamAttempt $attempt): array
    {
        $props = [];
        $this->actingAs($this->student)->get(route('student.exam-room', $attempt))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        return $props;
    }

    private function roomToken(ExamAttempt $attempt): string
    {
        return $this->roomProps($attempt)['session']['token'];
    }

    private function answer(ExamAttempt $attempt, Question $question, array $selected, string $token, ?Question $currentQuestion = null)
    {
        return $this->actingAs($this->student)->postJson(route('student.exam-room.answer', $attempt), [
            'question_id' => $question->id,
            'selected_options' => $selected,
            'session_token' => $token,
            'current_question_id' => $currentQuestion?->id,
        ]);
    }
}
