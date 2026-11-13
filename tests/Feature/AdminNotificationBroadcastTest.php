<?php

namespace Tests\Feature;

use App\Jobs\DeliverNotificationBatch;
use App\Models\AdminRole;
use App\Models\ClassLevel;
use App\Models\EmailLog;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamEnrollment;
use App\Models\HallTicket;
use App\Models\NotificationDelivery;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\StudentNotification;
use App\Models\Subject;
use App\Models\User;
use App\Services\Notifications\NotificationBroadcastService;
use App\Support\AdminPermissions;
use App\Support\LocalTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Admin → Notifications (workflow.md §13.2): shared audience filters, the email,
 * in-app and WhatsApp (Bird) channels, delivery tracking, retry and guards.
 * Bird and Brevo are always faked; stray HTTP requests fail the test.
 */
class AdminNotificationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ClassLevel $class5;

    private ClassLevel $class6;

    private Subject $maths;

    private Subject $science;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'email_verified_at' => now()]);
        $this->class5 = ClassLevel::create(['level' => 5, 'label' => 'Class 5', 'is_active' => true, 'sort_order' => 5]);
        $this->class6 = ClassLevel::create(['level' => 6, 'label' => 'Class 6', 'is_active' => true, 'sort_order' => 6]);
        $this->maths = Subject::create(['name' => 'Mathematics', 'slug' => 'mathematics', 'is_active' => true, 'sort_order' => 1]);
        $this->science = Subject::create(['name' => 'Science', 'slug' => 'science', 'is_active' => true, 'sort_order' => 2]);
    }

    /* ─────────────────────────── audience ─────────────────────────── */

    public function test_payment_filters_split_paid_and_unpaid_students(): void
    {
        $paid = $this->student(['email' => 'paid@example.com']);
        $this->student(['email' => 'unpaid@example.com']);
        $this->paidPaymentFor($paid);

        $this->audience(['payment_status' => 'paid'])
            ->assertJsonPath('summary.recipients', 1)
            ->assertJsonPath('students.data.0.email', 'paid@example.com');

        $this->audience(['payment_status' => 'unpaid'])
            ->assertJsonPath('summary.recipients', 1)
            ->assertJsonPath('students.data.0.email', 'unpaid@example.com');
    }

    public function test_filters_combine_and_multi_select_values_are_ored(): void
    {
        $a = $this->student(['class_level_id' => $this->class5->id, 'school' => 'Delhi Public School', 'city' => 'New Delhi']);
        $b = $this->student(['class_level_id' => $this->class6->id, 'school' => 'Delhi Public School', 'city' => 'Noida']);
        $this->student(['class_level_id' => $this->class6->id, 'school' => 'Kendriya Vidyalaya', 'city' => 'Noida']);
        $this->student(['class_level_id' => $this->class5->id, 'is_active' => false, 'school' => 'Delhi Public School']);

        $this->audience(['class_level_ids' => [$this->class5->id, $this->class6->id], 'school' => 'delhi public'])
            ->assertJsonPath('summary.matched', 2)
            ->assertJsonPath('summary.recipients', 2);

        $this->audience(['class_level_ids' => [$this->class6->id], 'school' => 'Delhi', 'city' => 'noida'])
            ->assertJsonPath('summary.matched', 1)
            ->assertJsonPath('students.data.0.id', $b->id);

        $this->audience(['student_status' => 'all', 'class_level_ids' => [$this->class5->id]])
            ->assertJsonPath('summary.matched', 2);

        // Exclusions remove an individual student from the filtered result.
        $this->audience(['class_level_ids' => [$this->class5->id, $this->class6->id], 'school' => 'Delhi Public', 'excluded_ids' => [$a->id]])
            ->assertJsonPath('summary.matched', 2)
            ->assertJsonPath('summary.excluded', 1)
            ->assertJsonPath('summary.recipients', 1)
            ->assertJsonPath('students.data.0.excluded', true);
    }

    public function test_subject_and_exam_scope_drive_enrollment_and_attempt_filters(): void
    {
        $mathsExam = $this->exam($this->maths);
        $scienceExam = $this->exam($this->science);

        $enrolledNotStarted = $this->student();
        $enrolledAndStarted = $this->student();
        $scienceOnly = $this->student();
        $nobody = $this->student();

        $this->enrol($mathsExam, $enrolledNotStarted);
        $this->enrol($mathsExam, $enrolledAndStarted);
        $this->enrol($scienceExam, $scienceOnly);
        ExamAttempt::create(['user_id' => $enrolledAndStarted->id, 'exam_id' => $mathsExam->id, 'status' => 'submitted', 'started_at' => now()]);

        // A course (subject) alone means "enrolled in it".
        $this->audience(['subject_ids' => [$this->maths->id]])->assertJsonPath('summary.matched', 2);
        $this->audience(['exam_ids' => [$mathsExam->id, $scienceExam->id]])->assertJsonPath('summary.matched', 3);
        $this->audience(['exam_ids' => [$mathsExam->id], 'attempt_status' => 'not_attempted'])
            ->assertJsonPath('summary.matched', 1)
            ->assertJsonPath('students.data.0.id', $enrolledNotStarted->id);
        $this->audience(['exam_ids' => [$mathsExam->id], 'enrollment_status' => 'not_enrolled'])
            ->assertJsonPath('summary.matched', 2); // scienceOnly + nobody
        $this->audience(['enrollment_status' => 'not_enrolled'])
            ->assertJsonPath('summary.matched', 1)
            ->assertJsonPath('students.data.0.id', $nobody->id);
    }

    public function test_hall_ticket_and_registration_source_filters(): void
    {
        $approved = $this->student(['registration_source' => 'marketing']);
        $legacy = $this->student(['registration_source' => null]);
        HallTicket::create(['user_id' => $approved->id, 'status' => 'approved', 'roll_number' => 'NEO260500001', 'submitted_at' => now()]);

        $this->audience(['hall_ticket' => 'approved'])->assertJsonPath('summary.matched', 1)->assertJsonPath('students.data.0.id', $approved->id);
        $this->audience(['hall_ticket' => 'none'])->assertJsonPath('summary.matched', 1)->assertJsonPath('students.data.0.id', $legacy->id);
        $this->audience(['registration_source' => 'website'])->assertJsonPath('summary.matched', 1)->assertJsonPath('students.data.0.id', $legacy->id);
    }

    public function test_selected_mode_and_channel_reachability(): void
    {
        $withPhone = $this->student(['phone' => '9876543210']);
        $noPhone = $this->student(['phone' => null]);
        $this->assertNotNull($withPhone->fresh()->phone_e164);

        $this->actingAs($this->admin)
            ->getJson(route('admin.notifications.students', ['search' => $withPhone->email]))
            ->assertOk()->assertJsonPath('students.0.id', $withPhone->id);

        $selected = ['mode' => 'selected', 'selected_ids' => [$withPhone->id, $noPhone->id]];
        $this->audience($selected, 'email')->assertJsonPath('summary.recipients', 2);
        $this->audience($selected, 'whatsapp')
            ->assertJsonPath('summary.matched', 2)
            ->assertJsonPath('summary.unreachable', 1)
            ->assertJsonPath('summary.recipients', 1);
    }

    /* ─────────────────────────── email ─────────────────────────── */

    public function test_email_broadcast_renders_placeholders_per_student_and_tracks_delivery(): void
    {
        config(['services.brevo.api_key' => 'brevo-test']);
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '<msg-1@brevo>'], 201)]);

        $student = $this->student(['name' => 'Aarav Sharma', 'class_level_id' => $this->class5->id, 'school' => 'Delhi Public School']);
        $this->student(['email' => 'other@example.com', 'class_level_id' => $this->class6->id]);

        $this->send('email', ['class_level_ids' => [$this->class5->id]], [
            'subject' => 'Hello {{first_name}}',
            'body_html' => '<p>Dear {{student_name}} of {{student_class}}, {{school_name}}.</p>',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $log = NotificationLog::latest('id')->firstOrFail();
        $this->assertSame('email', $log->channel);
        $this->assertSame('completed', $log->status);
        $this->assertSame(1, $log->recipient_count);
        $this->assertSame(1, $log->sent_count);

        $delivery = NotificationDelivery::firstOrFail();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame('<msg-1@brevo>', $delivery->provider_message_id);

        $email = EmailLog::findOrFail($delivery->email_log_id);
        $this->assertSame('Hello Aarav', $email->subject);
        $this->assertSame($student->id, $email->recipient_user_id);
        $this->assertSame(NotificationLog::class, $email->related_type);

        // Regression: class and school used to render blank because only id/name/email were loaded.
        Http::assertSent(fn (HttpRequest $r) => str_contains($r['htmlContent'], 'Dear Aarav Sharma of Class 5, Delhi Public School.'));
    }

    public function test_email_is_queued_in_batches_and_failures_can_be_retried(): void
    {
        Queue::fake();
        $this->student();
        $this->student();

        $this->send('email', [], ['subject' => 'Exam update', 'body_html' => '<p>Hi {{first_name}}</p>'])->assertRedirect();

        $log = NotificationLog::latest('id')->firstOrFail();
        $this->assertSame('queued', $log->status);
        $this->assertSame(2, $log->pending_count);
        Queue::assertPushed(DeliverNotificationBatch::class, fn ($job) => $job->notificationLogId === $log->id);

        // Brevo not configured → failed (retryable), never lost.
        app()->call([new DeliverNotificationBatch($log->id), 'handle']);
        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame(2, $log->failed_count);
        $this->assertSame('BREVO_API_KEY is not configured.', NotificationDelivery::first()->error);

        config(['services.brevo.api_key' => 'brevo-test']);
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'ok'], 201)]);

        $this->actingAs($this->admin)->post(route('admin.notifications.retry', $log))->assertSessionHas('success');
        app()->call([new DeliverNotificationBatch($log->id), 'handle']);

        $log->refresh();
        $this->assertSame('completed', $log->status);
        $this->assertSame(2, $log->sent_count);
        $this->assertSame(0, $log->failed_count);
        $this->assertSame(2, NotificationDelivery::where('attempts', 2)->count());
    }

    public function test_email_test_send_goes_only_to_the_typed_address(): void
    {
        config(['services.brevo.api_key' => 'brevo-test']);
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'test'], 201)]);

        $this->actingAs($this->admin)->postJson(route('admin.notifications.test'), [
            'channel' => 'email', 'to' => 'me@example.com',
            'email' => ['subject' => 'Hi {{first_name}}', 'body_html' => '<p>Test</p>'],
        ])->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(fn (HttpRequest $r) => $r['to'][0]['email'] === 'me@example.com' && str_starts_with($r['subject'], '[TEST] Hi Aarav'));
        $this->assertSame(0, NotificationLog::count());
    }

    /* ─────────────────────────── in-app ─────────────────────────── */

    public function test_in_app_notifications_are_written_inline_with_placeholders_and_read_tracking(): void
    {
        $exam = $this->exam($this->maths, ['name' => 'Maths Olympiad – Class 5', 'starts_at' => LocalTime::parse('2026-12-01 09:00'), 'ends_at' => LocalTime::parse('2026-12-01 21:00')]);
        $student = $this->student(['name' => 'Meera Iyer']);
        $this->enrol($exam, $student);
        $outsider = $this->student();

        $this->send('in_app', ['exam_ids' => [$exam->id]], [
            'title' => '{{first_name}}, {{exam_name}} opens soon',
            'message' => "Window: {{exam_window}}\nGood luck!",
            'link' => '/student/exams',
        ], ['exam_context_id' => $exam->id])->assertRedirect();

        $log = NotificationLog::latest('id')->firstOrFail();
        $this->assertSame('completed', $log->status);
        $this->assertSame(1, $log->sent_count);

        $notification = StudentNotification::where('user_id', $student->id)->firstOrFail();
        $this->assertSame('Meera, Maths Olympiad – Class 5 opens soon', $notification->title);
        $this->assertSame("Window: Tue, 1 Dec · 9:00 AM – 9:00 PM\nGood luck!", $notification->message);
        $this->assertSame('/student/exams', $notification->link);
        $this->assertFalse(StudentNotification::where('user_id', $outsider->id)->exists());

        // The student sees it; reading it shows up in the admin counts.
        $this->actingAs($student)->get(route('student.notifications'))
            ->assertInertia(fn (Assert $page) => $page->where('notifications.data.0.title', $notification->title));
        $this->patch(route('student.notifications.read', $notification));

        $this->actingAs($this->admin)->get(route('admin.notifications.show', $log))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Notifications/Show')
                ->where('log.read_count', 1)
                ->where('deliveries.data.0.status', 'read'));
    }

    public function test_placeholder_problems_block_the_send(): void
    {
        $this->student();

        $this->send('in_app', [], ['title' => 'Hi', 'message' => 'Your exam {{exam_name}}'])
            ->assertSessionHasErrors('content');
        $this->send('in_app', [], ['title' => 'Hi {{nickname}}', 'message' => 'x'])
            ->assertSessionHasErrors('content');
        $this->send('in_app', [], ['title' => 'Hi', 'message' => 'x', 'link' => 'https://evil.example'])
            ->assertSessionHasErrors('in_app.link');

        $this->assertSame(0, NotificationLog::count());
    }

    public function test_idempotency_key_and_duplicate_guard_prevent_double_sends(): void
    {
        $this->student();
        $content = ['title' => 'Reminder', 'message' => 'Exam tomorrow'];
        $key = (string) Str::uuid();

        $this->send('in_app', [], $content, ['idempotency_key' => $key])->assertRedirect();
        $this->send('in_app', [], $content, ['idempotency_key' => $key])->assertRedirect()->assertSessionHas('success', 'This broadcast was already submitted. Showing its delivery status.');
        $this->assertSame(1, NotificationLog::count());

        // Same message + audience with a fresh key inside 10 minutes → must be confirmed.
        $this->send('in_app', [], $content)->assertSessionHasErrors('duplicate');
        $this->assertSame(1, NotificationLog::count());

        $this->send('in_app', [], $content, ['allow_duplicate' => true])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, NotificationLog::count());
        $this->assertSame(2, StudentNotification::count());
    }

    public function test_empty_audience_and_read_only_roles_cannot_send(): void
    {
        $this->send('in_app', ['class_level_ids' => [$this->class6->id]], ['title' => 'x', 'message' => 'y'])
            ->assertSessionHasErrors('audience');

        $role = AdminRole::create(['name' => 'Viewer', 'is_active' => true]);
        foreach (AdminPermissions::normalizeMatrix(['notifications' => ['read' => true, 'write' => false, 'delete' => false]]) as $module => $a) {
            $role->permissions()->create(['module' => $module, 'can_read' => $a['read'], 'can_write' => $a['write'], 'can_delete' => $a['delete']]);
        }
        $viewer = User::factory()->create(['role' => 'admin', 'admin_role_id' => $role->id, 'is_active' => true]);
        $this->student();

        $this->actingAs($viewer)->get(route('admin.notifications'))->assertOk();
        $this->actingAs($viewer)->post(route('admin.notifications.send'), $this->payload('in_app', [], ['title' => 'x', 'message' => 'y']))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, NotificationLog::count());
    }

    public function test_deleting_a_broadcast_removes_its_in_app_notifications(): void
    {
        $this->student();
        $this->send('in_app', [], ['title' => 'Oops', 'message' => 'Wrong message'])->assertRedirect();
        $log = NotificationLog::firstOrFail();

        $this->actingAs($this->admin)->delete(route('admin.notifications.destroy', $log))->assertRedirect();
        $this->assertSame(0, StudentNotification::count());
        $this->assertSame(0, NotificationDelivery::count());
    }

    /* ─────────────────────────── WhatsApp (Bird) ─────────────────────────── */

    public function test_whatsapp_templates_come_from_bird_and_otp_templates_are_not_usable(): void
    {
        $this->fakeBird();

        $this->actingAs($this->admin)->getJson(route('admin.notifications.whatsapp.templates'))
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonCount(2, 'templates')
            ->assertJsonPath('templates.0.slug', 'exam-update')
            ->assertJsonPath('templates.0.usable', true)
            ->assertJsonPath('templates.1.slug', 'bird_otp')
            ->assertJsonPath('templates.1.usable', false);

        $this->actingAs($this->admin)->getJson(route('admin.notifications.whatsapp.template', 'wat_exam'))
            ->assertOk()
            ->assertJsonPath('template.body', 'Hi {{variable_1}}, {{variable_2}} opens on {{variable_3}}.')
            ->assertJsonPath('template.variables.1.key', 'variable_2')
            ->assertJsonPath('template.header', 'Exam update');
    }

    public function test_whatsapp_is_not_available_without_bird_credentials(): void
    {
        $this->actingAs($this->admin)->getJson(route('admin.notifications.whatsapp.templates'))
            ->assertOk()->assertJsonPath('configured', false);

        $this->student(['phone' => '9876543210']);
        $this->send('whatsapp', [], ['template_id' => 'wat_exam', 'variables' => []])->assertSessionHasErrors('whatsapp.template_id');
    }

    public function test_whatsapp_broadcast_sends_mapped_variables_and_skips_blank_values(): void
    {
        $this->fakeBird();
        $exam = $this->exam($this->maths, ['name' => 'Maths Olympiad', 'starts_at' => LocalTime::parse('2026-12-01 09:00'), 'ends_at' => LocalTime::parse('2026-12-01 21:00')]);

        $full = $this->student(['name' => 'Aarav Sharma', 'phone' => '9876543210', 'school' => 'DPS']);
        $noSchool = $this->student(['name' => 'Riya Sen', 'phone' => '9876500000', 'school' => null]);
        $noPhone = $this->student(['phone' => null]);
        foreach ([$full, $noSchool, $noPhone] as $s) {
            $this->enrol($exam, $s);
        }

        $this->send('whatsapp', ['exam_ids' => [$exam->id]], [
            'template_id' => 'wat_exam',
            'variables' => [
                'variable_1' => ['source' => 'placeholder', 'value' => 'first_name'],
                'variable_2' => ['source' => 'text', 'value' => "{{exam_name}}\n({{school_name}})"],
                'variable_3' => ['source' => 'placeholder', 'value' => 'exam_date'],
            ],
        ], ['exam_context_id' => $exam->id])->assertRedirect()->assertSessionHasNoErrors();

        $log = NotificationLog::latest('id')->firstOrFail();
        $this->assertSame('whatsapp', $log->channel);
        $this->assertSame('exam-update', $log->whatsapp_template['slug']);
        $this->assertSame(2, $log->recipient_count); // the student without a number is never queued
        $this->assertSame(2, $log->sent_count);

        Http::assertSent(function (HttpRequest $r) {
            if (! str_ends_with($r->url(), '/v1/whatsapp/messages') || $r->method() !== 'POST') {
                return false;
            }
            $params = collect($r['template']['components'][0]['parameters'])->pluck('text', 'name');

            return $r['to'] === '+919876543210'
                && $r['template']['slug'] === 'exam-update'
                && $r['from'] === '+919990000000'
                && $params['variable_1'] === 'Aarav'
                && $params['variable_2'] === 'Maths Olympiad (DPS)'   // newline collapsed
                && $params['variable_3'] === '01 Dec 2026';
        });

        $sent = NotificationDelivery::where('user_id', $full->id)->firstOrFail();
        $this->assertSame('sent', $sent->status);
        $this->assertSame('wam_1', $sent->provider_message_id);
        $this->assertSame('Aarav', $sent->payload['variables']['variable_1']);

        // Riya has no school: "{{exam_name}} ({{school_name}})" still has text, so she is sent too.
        $this->assertSame('sent', NotificationDelivery::where('user_id', $noSchool->id)->value('status'));
    }

    public function test_whatsapp_required_variable_without_value_or_fallback_skips_the_student(): void
    {
        $this->fakeBird();
        $withSchool = $this->student(['phone' => '9876543210', 'school' => 'DPS']);
        $withoutSchool = $this->student(['phone' => '9876500000', 'school' => null]);
        $withFallback = ['source' => 'placeholder', 'value' => 'school_name'];

        $variables = [
            'variable_1' => ['source' => 'placeholder', 'value' => 'student_name'],
            'variable_2' => $withFallback,
            'variable_3' => ['source' => 'text', 'value' => 'soon'],
        ];

        $this->send('whatsapp', [], ['template_id' => 'wat_exam', 'variables' => $variables])->assertRedirect();
        $log = NotificationLog::latest('id')->firstOrFail();
        $this->assertSame('sent', NotificationDelivery::where('user_id', $withSchool->id)->value('status'));
        $skipped = NotificationDelivery::where('user_id', $withoutSchool->id)->firstOrFail();
        $this->assertSame('skipped', $skipped->status);
        $this->assertStringContainsString('{{variable_2}}', $skipped->error);
        $this->assertSame('completed', $log->status);
        $this->assertSame(1, $log->skipped_count);

        // With a fallback the same student gets the message.
        $variables['variable_2'] = $withFallback + ['fallback' => 'your school'];
        $this->send('whatsapp', ['mode' => 'selected', 'selected_ids' => [$withoutSchool->id]], ['template_id' => 'wat_exam', 'variables' => $variables])->assertRedirect();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && $r['to'] === '+919876500000'
            && collect($r['template']['components'][0]['parameters'])->firstWhere('name', 'variable_2')['text'] === 'your school');
    }

    public function test_whatsapp_provider_errors_fail_the_delivery_and_retry_resends(): void
    {
        $this->fakeBird(Http::sequence()
            ->push(['code' => 'InvalidPayload', 'message' => 'Invalid recipient'], 422)
            ->push(['id' => 'wam_retry', 'status' => 'accepted'], 202));
        $student = $this->student(['phone' => '9876543210']);

        $this->send('whatsapp', [], ['template_id' => 'wat_exam', 'variables' => $this->simpleVariables()])->assertRedirect();
        $log = NotificationLog::latest('id')->firstOrFail();
        $delivery = NotificationDelivery::firstOrFail();

        $this->assertSame('failed', $log->status);
        $this->assertSame('failed', $delivery->status);
        $this->assertStringContainsString('Bird HTTP 422', $delivery->error);
        $this->assertFalse($delivery->permanent_failure);

        $this->actingAs($this->admin)->post(route('admin.notifications.deliveries.retry', [$log, $delivery]))->assertSessionHas('success');

        $delivery->refresh();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame('wam_retry', $delivery->provider_message_id);
        $this->assertSame(2, $delivery->attempts);
        $this->assertSame('completed', $log->fresh()->status);
        $this->assertSame($student->id, $delivery->user_id);
    }

    public function test_whatsapp_rate_limit_hands_the_batch_back_as_pending(): void
    {
        $this->fakeBird(Http::response(['message' => 'slow down'], 429, ['Retry-After' => '30']));
        $this->student(['phone' => '9876543210']);
        $this->student(['phone' => '9876500000']);

        $this->send('whatsapp', [], ['template_id' => 'wat_exam', 'variables' => $this->simpleVariables()])->assertRedirect();

        $log = NotificationLog::latest('id')->firstOrFail();
        $this->assertSame(2, $log->pending_count);
        $this->assertSame(0, $log->failed_count);
        $this->assertSame(1, $log->meta['rate_limit_hits']);
        $this->assertSame(2, NotificationDelivery::where('status', 'pending')->whereNull('claim_token')->count());
    }

    public function test_bird_status_sync_marks_delivered_read_and_permanent_failures(): void
    {
        $this->fakeBird(Http::sequence()
            ->push(['id' => 'wam_a', 'status' => 'accepted'], 202)
            ->push(['id' => 'wam_b', 'status' => 'accepted'], 202)
            ->push(['id' => 'wam_c', 'status' => 'accepted'], 202), [
                'wam_a' => ['id' => 'wam_a', 'status' => 'delivered', 'delivered_at' => '2026-11-13T09:00:00Z', 'read_at' => null],
                'wam_b' => ['id' => 'wam_b', 'status' => 'delivered', 'delivered_at' => '2026-11-13T09:00:00Z', 'read_at' => '2026-11-13T09:05:00Z'],
                'wam_c' => ['id' => 'wam_c', 'status' => 'failed', 'last_error' => ['code' => 'undeliverable', 'description' => 'Message Undeliverable.', 'meta_error_code' => '131026', 'occurred_at' => '2026-11-13T09:00:00Z']],
            ]);
        foreach (['9876500001', '9876500002', '9876500003'] as $phone) {
            $this->student(['phone' => $phone]);
        }

        $this->send('whatsapp', [], ['template_id' => 'wat_exam', 'variables' => $this->simpleVariables()])->assertRedirect();
        $log = NotificationLog::latest('id')->firstOrFail();

        $this->artisan('notifications:maintain')->assertSuccessful();

        $statuses = NotificationDelivery::orderBy('id')->pluck('status')->all();
        $this->assertSame(['delivered', 'read', 'failed'], $statuses);
        $failed = NotificationDelivery::where('provider_message_id', 'wam_c')->firstOrFail();
        $this->assertTrue($failed->permanent_failure);
        $this->assertSame('Message Undeliverable.', $failed->error);

        $log->refresh();
        $this->assertSame('partial', $log->status);
        $this->assertSame(2, $log->delivered_count);
        $this->assertSame(1, $log->read_count);
        $this->assertSame(0, app(NotificationBroadcastService::class)->retry($log), 'permanent failures are not retried');
    }

    public function test_maintain_releases_stale_claims_and_resumes_the_broadcast(): void
    {
        Queue::fake();
        $this->student();
        $this->send('email', [], ['subject' => 'S', 'body_html' => '<p>B</p>'])->assertRedirect();
        $log = NotificationLog::firstOrFail();

        NotificationDelivery::query()->update(['status' => 'sending', 'claim_token' => 'dead', 'claimed_at' => now()->subMinutes(30)]);
        DB::table('notification_logs')->where('id', $log->id)->update(['status' => 'sending', 'updated_at' => now()->subMinutes(30)]);

        $this->artisan('notifications:maintain')->assertSuccessful();

        $this->assertSame('pending', NotificationDelivery::first()->status);
        Queue::assertPushed(DeliverNotificationBatch::class, 2); // the original send + the resume
    }

    public function test_index_and_history_pages_render_for_legacy_and_new_broadcasts(): void
    {
        NotificationLog::create(['title' => 'Old blast', 'message' => 'Legacy', 'channel' => 'both', 'audience' => 'all', 'status' => 'completed', 'recipient_count' => 3, 'sent_at' => now()]);

        $this->actingAs($this->admin)->get(route('admin.notifications', ['tab' => 'history']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Notifications/Index')
                ->where('tab', 'history')
                ->where('logs.data.0.title', 'Old blast')
                ->where('logs.data.0.channel', 'both')
                ->has('options.placeholders')
                ->where('options.whatsapp.configured', false));
    }

    /* ─────────────────────────── helpers ─────────────────────────── */

    private function audience(array $audience, string $channel = 'email')
    {
        return $this->actingAs($this->admin)
            ->postJson(route('admin.notifications.audience'), ['channel' => $channel, 'audience' => array_merge(['mode' => 'filters'], $audience)])
            ->assertOk();
    }

    private function payload(string $channel, array $audience, array $content, array $extra = []): array
    {
        return array_merge([
            'channel' => $channel,
            'audience' => array_merge(['mode' => 'filters'], $audience),
            $channel => $content,
            'idempotency_key' => (string) Str::uuid(),
        ], $extra);
    }

    private function send(string $channel, array $audience, array $content, array $extra = [])
    {
        return $this->actingAs($this->admin)->post(route('admin.notifications.send'), $this->payload($channel, $audience, $content, $extra));
    }

    private function simpleVariables(): array
    {
        return [
            'variable_1' => ['source' => 'placeholder', 'value' => 'student_name'],
            'variable_2' => ['source' => 'text', 'value' => 'Olympiad'],
            'variable_3' => ['source' => 'text', 'value' => 'Monday'],
        ];
    }

    /** Fakes the Bird catalogue + version, the send endpoint and optional message lookups. */
    private function fakeBird($send = null, array $messages = []): void
    {
        config([
            'services.bird.api_key' => 'bird-test-key',
            'services.bird.base_url' => 'https://bird.test',
            'services.bird.whatsapp_from' => '+919990000000',
            'services.bird.send_interval_ms' => 0,
        ]);

        $fakes = [
            'bird.test/v1/whatsapp/templates/wat_exam/versions/*' => Http::response([
                'id' => 'wav_exam_1', 'version_number' => 1,
                'variables' => [
                    ['key' => 'variable_1', 'required' => true, 'type' => 'text'],
                    ['key' => 'variable_2', 'required' => true, 'type' => 'text'],
                    ['key' => 'variable_3', 'required' => true, 'type' => 'text'],
                ],
                'languages' => ['en' => ['status' => 'approved', 'components' => [
                    ['type' => 'header', 'format' => 'text', 'text' => 'Exam update'],
                    ['type' => 'body', 'text' => 'Hi {{variable_1}}, {{variable_2}} opens on {{variable_3}}.', 'example_parameters' => [['name' => 'variable_1', 'text' => 'Aarav']]],
                    ['type' => 'footer', 'text' => 'NEO'],
                ]]],
            ]),
            'bird.test/v1/whatsapp/templates*' => Http::response(['data' => [
                ['id' => 'wat_otp', 'slug' => 'bird_otp', 'name' => 'One-time passcode', 'category' => 'authentication', 'status' => 'active', 'default_language' => 'en', 'live_version_id' => 'wav_otp', 'languages' => ['en' => ['status' => 'approved']]],
                ['id' => 'wat_exam', 'slug' => 'exam-update', 'name' => 'Exam update', 'category' => 'utility', 'status' => 'active', 'default_language' => 'en', 'live_version_id' => 'wav_exam_1', 'languages' => ['en' => ['status' => 'approved']]],
            ], 'next_cursor' => null]),
        ];

        foreach ($messages as $id => $body) {
            $fakes["bird.test/v1/whatsapp/messages/{$id}"] = Http::response($body);
        }

        $counter = 0;
        $fakes['bird.test/v1/whatsapp/messages'] = $send ?? function () use (&$counter) {
            $counter++;

            return Http::response(['id' => 'wam_'.$counter, 'status' => 'accepted'], 202);
        };

        Http::fake($fakes);
    }

    private function student(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'student',
            'is_active' => true,
            'email_verified_at' => now(),
            'name' => 'Aarav Sharma',
            'class_level_id' => $this->class5->id,
        ], $overrides));
    }

    private function exam(Subject $subject, array $overrides = []): Exam
    {
        return Exam::create(array_merge([
            'subject_id' => $subject->id, 'class_level_id' => $this->class5->id,
            'name' => $subject->name.' Olympiad', 'slug' => Str::slug($subject->name).'-'.Str::random(5),
            'exam_code' => 'NEO-'.Str::upper(Str::random(6)), 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2),
            'duration_minutes' => 60, 'fee_amount' => 0, 'fee_currency' => 'INR', 'scoring_mode' => 'question_bank',
            'marks_per_question' => 1, 'status' => 'published', 'created_by' => $this->admin->id,
        ], $overrides));
    }

    private function enrol(Exam $exam, User $student): ExamEnrollment
    {
        return ExamEnrollment::create(['user_id' => $student->id, 'exam_id' => $exam->id, 'status' => 'enrolled', 'enrolled_at' => now()]);
    }

    private function paidPaymentFor(User $student): Payment
    {
        return Payment::create([
            'user_id' => $student->id, 'amount' => 499, 'gross_amount' => 499, 'discount_amount' => 0,
            'currency' => 'INR', 'status' => 'paid', 'gateway' => 'razorpay',
            'razorpay_order_id' => 'order_'.uniqid(), 'razorpay_payment_id' => 'pay_'.uniqid(), 'paid_at' => now(),
        ]);
    }
}
