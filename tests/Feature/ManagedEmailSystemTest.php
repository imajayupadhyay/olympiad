<?php

namespace Tests\Feature;

use App\Jobs\SendManagedEmail;
use App\Models\ClassLevel;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\User;
use App\Services\ManagedEmailService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ManagedEmailSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_templates_are_seeded_by_migration(): void
    {
        $this->assertDatabaseHas('email_templates', ['key' => 'student_registered']);
        $this->assertDatabaseHas('email_templates', ['key' => 'payment_success']);
        $this->assertDatabaseHas('email_templates', ['key' => 'exam_reminder']);
        $this->assertDatabaseHas('email_templates', ['key' => 'result_released']);
        $this->assertDatabaseHas('email_templates', ['key' => 'certificate_issued']);
    }

    public function test_template_renderer_replaces_variables(): void
    {
        $template = EmailTemplate::where('key', 'payment_success')->firstOrFail();

        $rendered = app(ManagedEmailService::class)->renderTemplate($template, [
            'student_name' => 'Aarav Sharma',
            'olympiad_name' => 'National Excellence Olympiad',
            'amount_paid' => '₹499.00',
            'transaction_id' => 'pay_123',
            'payment_method' => 'UPI',
            'login_password' => 'Secret@123',
        ]);

        $this->assertStringContainsString('Payment received for National Excellence Olympiad', $rendered['subject']);
        $this->assertStringContainsString('Aarav Sharma', $rendered['html_body']);
        $this->assertStringContainsString('Secret@123', $rendered['text_body']);
    }

    public function test_managed_email_header_uses_original_logo_asset(): void
    {
        $template = EmailTemplate::where('key', 'student_registered')->firstOrFail();

        $rendered = app(ManagedEmailService::class)->renderTemplate($template, [
            'student_name' => 'Aarav Sharma',
            'student_email' => 'aarav@example.com',
            'login_password' => 'Secret@123',
        ]);

        $this->assertStringContainsString('https://neoexam.org/NEO_email_header_logo.png', $rendered['html_body']);
        $this->assertStringContainsString('<img class="email-logo"', $rendered['html_body']);
        $this->assertStringNotContainsString('width:44px;height:44px;border-radius:12px', $rendered['html_body']);
    }

    public function test_disabled_template_is_logged_as_skipped_without_queueing_job(): void
    {
        Queue::fake();
        $student = $this->student();
        EmailTemplate::where('key', 'student_registered')->update(['is_active' => false]);

        app(ManagedEmailService::class)->queue('student_registered', $student, [
            'login_password' => 'Secret@123',
        ]);

        $this->assertDatabaseHas('email_logs', [
            'template_key' => 'student_registered',
            'recipient_user_id' => $student->id,
            'status' => 'skipped',
        ]);
        Queue::assertNotPushed(SendManagedEmail::class);
    }

    public function test_registration_queues_welcome_email(): void
    {
        Queue::fake();
        $classLevel = ClassLevel::create(['level' => 6, 'label' => 'Class 6', 'is_active' => true, 'sort_order' => 6]);

        $this->post('/register', [
            'name' => 'New Student',
            'email' => 'new-student@example.com',
            'class_level_id' => $classLevel->id,
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertRedirect(route('register.olympiads', absolute: false));

        $student = User::where('email', 'new-student@example.com')->firstOrFail();

        $this->assertDatabaseHas('email_logs', [
            'template_key' => 'student_registered',
            'recipient_user_id' => $student->id,
            'status' => 'queued',
        ]);
        Queue::assertPushed(SendManagedEmail::class);
    }

    public function test_payment_fulfilment_queues_payment_success_email(): void
    {
        Queue::fake();
        $student = $this->student();
        $exam = $this->exam(['fee_amount' => 499]);
        $payment = Payment::create([
            'user_id' => $student->id,
            'amount' => 499,
            'gross_amount' => 499,
            'discount_amount' => 0,
            'currency' => 'INR',
            'status' => 'created',
            'gateway' => 'razorpay',
            'notes' => ['exam_ids' => [$exam->id]],
        ]);

        app(PaymentService::class)->enrollFreeByCoupon($payment);

        $this->assertDatabaseHas('email_logs', [
            'template_key' => 'payment_success',
            'recipient_user_id' => $student->id,
            'related_type' => Payment::class,
            'related_id' => $payment->id,
            'status' => 'queued',
        ]);
    }

    public function test_exam_reminder_command_queues_once_per_student_exam(): void
    {
        Queue::fake();
        $student = $this->student();
        $exam = $this->exam(['starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(3)]);

        ExamEnrollment::create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'enrolled',
            'amount' => 0,
            'currency' => 'INR',
            'enrolled_at' => now(),
        ]);

        $this->artisan('emails:send-exam-reminders --hours=24')->assertExitCode(0);
        $this->artisan('emails:send-exam-reminders --hours=24')->assertExitCode(0);

        $this->assertSame(1, EmailLog::where('template_key', 'exam_reminder')
            ->where('recipient_user_id', $student->id)
            ->where('related_type', Exam::class)
            ->where('related_id', $exam->id)
            ->count());
    }

    private function student(array $overrides = []): User
    {
        $classLevel = ClassLevel::first() ?: ClassLevel::create([
            'level' => 5,
            'label' => 'Class 5',
            'is_active' => true,
            'sort_order' => 5,
        ]);

        return User::factory()->create(array_merge([
            'role' => 'student',
            'is_active' => true,
            'class_level_id' => $classLevel->id,
            'school' => 'Delhi Public School',
        ], $overrides));
    }

    private function exam(array $overrides = []): Exam
    {
        $subject = Subject::first() ?: Subject::create([
            'name' => 'Mathematics',
            'slug' => 'mathematics',
            'icon' => 'math',
            'color' => '#2C49A6',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $classLevel = ClassLevel::first() ?: ClassLevel::create([
            'level' => 5,
            'label' => 'Class 5',
            'is_active' => true,
            'sort_order' => 5,
        ]);

        return Exam::create(array_merge([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'name' => 'National Excellence Olympiad',
            'slug' => 'national-excellence-olympiad-'.uniqid(),
            'exam_code' => 'NEO'.random_int(1000, 9999),
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'duration_minutes' => 60,
            'fee_amount' => 0,
            'fee_currency' => 'INR',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }
}
