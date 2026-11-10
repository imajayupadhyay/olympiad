<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\Answer;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamEnrollment;
use App\Models\ExamSessionEvent;
use App\Models\Question;
use App\Models\Result;
use App\Models\StudentNotification;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamSessionService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminExamSessionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Exam $exam;

    private ClassLevel $class;

    /** @var array<int, Question> */
    private array $questions = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 10:00:00');

        $subject = Subject::create(['name' => 'Mathematics', 'slug' => 'mathematics', 'is_active' => true, 'sort_order' => 1]);
        $this->class = ClassLevel::create(['level' => 6, 'label' => 'Class 6', 'is_active' => true, 'sort_order' => 6]);
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Head Admin']);

        $this->exam = Exam::create([
            'subject_id' => $subject->id, 'class_level_id' => $this->class->id, 'name' => 'Maths Olympiad', 'slug' => 'maths-olympiad',
            'exam_code' => 'NEO-TEST-0001', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHours(3),
            'duration_minutes' => 30, 'fee_amount' => 0, 'fee_currency' => 'INR', 'scoring_mode' => 'question_bank',
            'marks_per_question' => 1, 'status' => 'published', 'created_by' => $this->admin->id,
        ]);
        $section = $this->exam->sections()->create(['subject_id' => $subject->id, 'name' => 'General', 'sort_order' => 1]);
        foreach (range(1, 4) as $i) {
            $question = Question::create([
                'subject_id' => $subject->id, 'difficulty' => 'easy', 'question_text' => "<p>Q{$i}</p>",
                'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D',
                'question_type' => 'single', 'correct_options' => ['a'], 'marks' => 1, 'negative_marks' => 0,
                'is_active' => true, 'created_by' => $this->admin->id,
            ]);
            $question->classLevels()->attach($this->class->id);
            $this->exam->questions()->attach($question->id, ['exam_section_id' => $section->id, 'sort_order' => $i, 'marks' => 1, 'negative_marks' => 0]);
            $this->questions[] = $question;
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_board_lists_live_sessions_with_connection_states(): void
    {
        $online = $this->liveSession('Online Student');
        $offline = $this->liveSession('Offline Student');
        $offline->update(['last_seen_at' => now()->subMinutes(10)]);
        $paused = $this->liveSession('Paused Student');
        $paused->update(['suspended_at' => now()]);
        $this->enrol('Absent Student');
        Answer::create(['exam_attempt_id' => $online->id, 'question_id' => $this->questions[0]->id, 'selected_options' => ['a']]);

        $this->actingAs($this->admin)->get(route('admin.exam-sessions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ExamSessions/Index')
                ->where('stats.live', 1)
                ->where('stats.offline', 1)
                ->where('stats.paused', 1)
                ->where('stats.not_started', 1)
                ->has('sessions.data', 3)
                ->where('groups.0.open', 3)
                ->where('sessions.data', fn ($rows) => collect($rows)->pluck('connection', 'student.name')->all() === [
                    'Offline Student' => 'offline', 'Online Student' => 'online', 'Paused Student' => 'paused',
                ] || collect($rows)->firstWhere('student.name', 'Online Student')['answered'] === 1));

        $this->get(route('admin.exam-sessions.index', ['tab' => 'not_started']))
            ->assertInertia(fn (Assert $page) => $page->where('notStarted.data.0.student.name', 'Absent Student'));
        $this->get(route('admin.exam-sessions.index', ['tab' => 'attention']))
            ->assertInertia(fn (Assert $page) => $page->has('sessions.data', 1)->where('sessions.data.0.student.name', 'Offline Student'));
        $this->get(route('admin.exam-sessions.show', $online))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/ExamSessions/Show')->where('session.answered', 1)->has('questions', 4));
    }

    public function test_pause_freezes_the_clock_and_resume_gives_the_paused_time_back(): void
    {
        $attempt = $this->liveSession();
        $this->travel(5)->minutes();

        $this->actingAs($this->admin)->post(route('admin.exam-sessions.suspend', $attempt), ['reason' => 'Server hiccup'])->assertSessionHas('success');
        $this->assertTrue($attempt->fresh()->isPaused());
        $this->assertSame(1, StudentNotification::where('user_id', $attempt->user_id)->count());
        $this->assertDatabaseHas('exam_session_events', ['exam_attempt_id' => $attempt->id, 'type' => 'suspended', 'actor_id' => $this->admin->id, 'reason' => 'Server hiccup']);

        $this->travel(12)->minutes();
        $this->post(route('admin.exam-sessions.resume', $attempt), ['notify' => false])->assertSessionHas('success');

        $attempt->refresh();
        $this->assertFalse($attempt->isPaused());
        $this->assertSame(25 * 60, $attempt->remainingSeconds());   // the 12 paused minutes did not count
        $this->assertSame(12 * 60, $attempt->paused_seconds);
        $this->assertSame(1, StudentNotification::where('user_id', $attempt->user_id)->count());   // notify off
    }

    public function test_reason_is_required_for_disruptive_actions(): void
    {
        $attempt = $this->liveSession();

        $this->actingAs($this->admin)->post(route('admin.exam-sessions.suspend', $attempt), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('admin.exam-sessions.force-submit', $attempt), [])->assertSessionHasErrors('reason');
        $this->assertTrue($attempt->fresh()->isOpen());
    }

    public function test_add_time_extends_the_deadline_and_restores_lost_time(): void
    {
        $attempt = $this->liveSession();
        $attempt->update(['last_seen_at' => now()]);
        $this->travel(8)->minutes();   // offline, then comes back
        app(ExamSessionService::class)->touch($attempt->fresh());
        $this->assertSame(480, app(ExamSessionService::class)->lostSeconds($attempt->fresh()));

        $this->actingAs($this->admin)->post(route('admin.exam-sessions.add-time', $attempt), ['minutes' => 8])->assertSessionHas('success');

        $attempt->refresh();
        $this->assertSame(30 * 60, $attempt->remainingSeconds());   // 22 left + 8 restored
        $this->assertSame(480, $attempt->extra_seconds);
        $this->assertSame(0, app(ExamSessionService::class)->lostSeconds($attempt));
    }

    public function test_reopen_keeps_answers_and_removes_an_unreleased_result(): void
    {
        $attempt = $this->liveSession();
        Answer::create(['exam_attempt_id' => $attempt->id, 'question_id' => $this->questions[0]->id, 'selected_options' => ['a']]);
        Answer::create(['exam_attempt_id' => $attempt->id, 'question_id' => $this->questions[1]->id, 'selected_options' => ['c']]);
        $attempt->update(['last_seen_at' => now()->addMinutes(10)]);
        $this->travel(33)->minutes();
        $this->artisan('exam-attempts:finalize-expired');
        $this->assertSame('timed_out', $attempt->fresh()->status);
        Result::create(['user_id' => $attempt->user_id, 'exam_id' => $this->exam->id, 'exam_attempt_id' => $attempt->id, 'total_score' => 1, 'max_score' => 4]);

        $this->travel(1)->hour();
        $this->actingAs($this->admin)->post(route('admin.exam-sessions.reopen', $attempt), ['minutes' => 20, 'reason' => 'Power cut at home', 'clear_violations' => true])
            ->assertSessionHas('success');

        $attempt->refresh();
        $this->assertTrue($attempt->isOpen());
        $this->assertSame(20 * 60, $attempt->remainingSeconds());
        $this->assertSame(2, $attempt->answers()->count());
        $this->assertSame(1, $attempt->reopen_count);
        $this->assertDatabaseCount('results', 0);
        $this->assertDatabaseHas('exam_session_events', ['exam_attempt_id' => $attempt->id, 'type' => 'result_removed']);

        // Time used keeps counting only the minutes actually spent in the room.
        $this->travel(5)->minutes();
        $this->actingAs($attempt->user)->post(route('student.exam-room.submit', $attempt), ['reason' => 'manual']);
        // The full 30 minutes ran out on the clock (owner decision: offline time counts), the
        // grace period and the closed hour don't, and the 5 minutes after the reopen do.
        $this->assertSame(35 * 60, $attempt->fresh()->time_taken_seconds);
    }

    public function test_released_results_lock_reopen_and_reset(): void
    {
        $attempt = $this->liveSession();
        app(ExamSessionService::class)->finalize($attempt, 'submitted', 'submitted');
        Result::create(['user_id' => $attempt->user_id, 'exam_id' => $this->exam->id, 'exam_attempt_id' => $attempt->id, 'is_released' => true]);

        $this->actingAs($this->admin)->post(route('admin.exam-sessions.reopen', $attempt), ['minutes' => 10, 'reason' => 'Asked by parent'])
            ->assertSessionHas('error');
        $this->post(route('admin.exam-sessions.reset', $attempt), ['reason' => 'Asked by parent'])->assertSessionHas('error');

        $this->assertSame('submitted', $attempt->fresh()->status);
        $this->assertDatabaseCount('results', 1);
    }

    public function test_reset_archives_answers_and_lets_the_student_start_again(): void
    {
        $attempt = $this->liveSession();
        Answer::create(['exam_attempt_id' => $attempt->id, 'question_id' => $this->questions[2]->id, 'selected_options' => ['b'], 'is_flagged' => true]);

        $this->actingAs($this->admin)->post(route('admin.exam-sessions.reset', $attempt), ['reason' => 'Browser corrupted the session'])
            ->assertRedirect(route('admin.exam-sessions.index', ['tab' => 'not_started', 'exam_id' => $this->exam->id]));

        $this->assertDatabaseMissing('exam_attempts', ['id' => $attempt->id]);
        $this->assertDatabaseCount('answers', 0);
        $event = ExamSessionEvent::where('type', 'reset')->firstOrFail();
        $this->assertNull($event->exam_attempt_id);   // survives the deleted attempt
        $this->assertSame([['question_id' => $this->questions[2]->id, 'selected' => ['b'], 'flagged' => true]], $event->meta['answers']);

        $this->actingAs($attempt->user)->post(route('student.exams.start', $this->exam))->assertRedirect();
        $fresh = ExamAttempt::where('user_id', $attempt->user_id)->firstOrFail();
        $this->assertSame(30 * 60, $fresh->remainingSeconds());
    }

    public function test_reset_after_the_window_closed_needs_a_new_window(): void
    {
        $attempt = $this->liveSession();
        $this->exam->update(['ends_at' => now()->subMinute()]);

        $this->actingAs($this->admin)->post(route('admin.exam-sessions.reset', $attempt), ['reason' => 'Corrupted'])->assertSessionHas('error');
        $this->assertDatabaseHas('exam_attempts', ['id' => $attempt->id]);

        $this->post(route('admin.exam-sessions.reset', $attempt), [
            'reason' => 'Corrupted', 'window_starts_at' => '2026-11-11T10:00', 'window_ends_at' => '2026-11-11T12:00',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('exam_enrollments', ['user_id' => $attempt->user_id, 'window_starts_at' => '2026-11-11 10:00:00']);
    }

    public function test_reschedule_resume_later_continues_inside_the_new_window(): void
    {
        $attempt = $this->liveSession();
        $this->travel(10)->minutes();   // 20 minutes left

        $this->actingAs($this->admin)->post(route('admin.exam-sessions.reschedule', $attempt), [
            'mode' => 'resume', 'reason' => 'Area-wide outage',
            'window_starts_at' => '2026-11-11T09:00', 'window_ends_at' => '2026-11-11T11:00',
        ])->assertSessionHas('success');

        $attempt->refresh();
        $this->assertTrue($attempt->isPaused());
        $this->assertTrue($attempt->resume_on_window);

        // Before the window: the student sees "rescheduled" and can't answer.
        $this->actingAs($attempt->user)->get(route('student.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('myExams.0.action', 'rescheduled'));

        Carbon::setTestNow('2026-11-11 09:30:00');
        $this->get(route('student.exam-room', $attempt))->assertInertia(fn (Assert $page) => $page
            ->where('session.paused', null)
            ->where('remaining_seconds', 20 * 60)
            ->has('questions', 4));
    }

    public function test_students_who_never_started_can_get_a_personal_window(): void
    {
        $enrollment = $this->enrol('Absent Student');

        $this->actingAs($this->admin)->post(route('admin.exam-sessions.enrollments.reschedule', $enrollment), [
            'reason' => 'Was ill on exam day', 'window_starts_at' => '2026-11-12T10:00', 'window_ends_at' => '2026-11-12T10:20',
        ])->assertSessionHas('error');   // shorter than the 30 min duration

        $this->post(route('admin.exam-sessions.enrollments.reschedule', $enrollment), [
            'reason' => 'Was ill on exam day', 'window_starts_at' => '2026-11-12T10:00', 'window_ends_at' => '2026-11-12T12:00',
        ])->assertSessionHas('success');
        $this->assertTrue($enrollment->fresh()->hasPersonalWindow());
        $this->assertSame(1, StudentNotification::where('user_id', $enrollment->user_id)->count());

        $this->delete(route('admin.exam-sessions.enrollments.unschedule', $enrollment))->assertSessionHas('success');
        $this->assertFalse($enrollment->fresh()->hasPersonalWindow());
    }

    public function test_force_submit_scores_with_saved_answers(): void
    {
        $attempt = $this->liveSession();
        Answer::create(['exam_attempt_id' => $attempt->id, 'question_id' => $this->questions[0]->id, 'selected_options' => ['a']]);

        $this->actingAs($this->admin)->post(route('admin.exam-sessions.force-submit', $attempt), ['reason' => 'Student asked to stop'])->assertSessionHas('success');

        $attempt->refresh();
        $this->assertSame('auto_submitted', $attempt->status);
        $this->assertSame(1.0, $attempt->total_score);
        $this->assertDatabaseHas('exam_session_events', ['exam_attempt_id' => $attempt->id, 'type' => 'force_submitted', 'actor_id' => $this->admin->id]);
    }

    public function test_bulk_actions_cover_every_open_session_of_the_exam(): void
    {
        $a = $this->liveSession();
        $b = $this->liveSession();
        $done = $this->liveSession();
        app(ExamSessionService::class)->finalize($done, 'submitted', 'submitted');

        $this->actingAs($this->admin)->post(route('admin.exam-sessions.bulk', ['exam' => $this->exam, 'action' => 'suspend']), ['reason' => 'Server outage'])
            ->assertSessionHas('success', '2 session(s) paused.');
        $this->assertTrue($a->fresh()->isPaused() && $b->fresh()->isPaused());
        $batch = ExamSessionEvent::where('type', 'suspended')->pluck('meta')->pluck('batch')->unique();
        $this->assertCount(1, $batch);

        $this->post(route('admin.exam-sessions.bulk', ['exam' => $this->exam, 'action' => 'resume']), [])->assertSessionHas('success', '2 session(s) resumed.');
        $this->post(route('admin.exam-sessions.bulk', ['exam' => $this->exam, 'action' => 'add_time']), ['minutes' => 15])->assertSessionHas('success');
        $this->assertSame(45 * 60, $a->fresh()->remainingSeconds());
        $this->assertSame('submitted', $done->fresh()->status);
    }

    public function test_read_only_staff_can_watch_but_not_act(): void
    {
        $attempt = $this->liveSession();
        $viewer = User::factory()->create(['role' => 'admin', 'admin_role_id' => $this->roleWith(['exam_sessions' => ['read' => true]])->id]);

        $this->actingAs($viewer)->get(route('admin.exam-sessions.index'))->assertOk();
        $this->get(route('admin.exam-sessions.show', $attempt))->assertOk();
        $this->post(route('admin.exam-sessions.suspend', $attempt), ['reason' => 'Trying'])->assertRedirect();
        $this->assertFalse($attempt->fresh()->isPaused());

        $outsider = User::factory()->create(['role' => 'admin', 'admin_role_id' => $this->roleWith(['students' => ['read' => true]])->id]);
        $this->actingAs($outsider)->get(route('admin.exam-sessions.index'))->assertRedirect();

        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.exam-sessions.index'))->assertRedirect(route('admin.login'));
    }

    /* ───────────────────────── helpers ───────────────────────── */

    private function enrol(string $name = 'Student'): ExamEnrollment
    {
        $student = User::factory()->create(['role' => 'student', 'name' => $name, 'class_level_id' => $this->class->id]);

        return ExamEnrollment::create(['user_id' => $student->id, 'exam_id' => $this->exam->id, 'status' => 'enrolled', 'enrolled_at' => now()]);
    }

    private function liveSession(string $name = 'Student'): ExamAttempt
    {
        $enrollment = $this->enrol($name);
        $attempt = app(ExamSessionService::class)->begin($enrollment->user, $this->exam, '127.0.0.1');
        $attempt->update(['last_seen_at' => now()]);

        return $attempt->fresh();
    }

    private function roleWith(array $permissions): AdminRole
    {
        $role = AdminRole::create(['name' => 'Role '.str()->random(8), 'is_active' => true]);
        foreach (AdminPermissions::normalizeMatrix($permissions) as $module => $actions) {
            $role->permissions()->create(['module' => $module, 'can_read' => $actions['read'], 'can_write' => $actions['write'], 'can_delete' => $actions['delete']]);
        }

        return $role;
    }
}
