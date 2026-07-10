<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamEnrollment;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUserEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_exposes_assignable_exams_and_existing_enrollments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        [$subject, $classLevel] = $this->taxonomy();
        $exam = $this->exam($subject, $classLevel);

        ExamEnrollment::create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'enrolled',
            'enrollment_source' => 'admin',
            'assigned_by_admin_id' => $admin->id,
            'assigned_at' => now(),
            'amount' => 0,
            'currency' => 'INR',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Edit')
                ->has('assignableExams', 1)
                ->where('assignableExams.0.id', $exam->id)
                ->has('enrollments', 1)
                ->where('enrollments.0.exam.id', $exam->id)
                ->where('enrollments.0.enrollment_source', 'admin')
                ->where('enrollments.0.assigned_by.name', $admin->name)
            );
    }

    public function test_admin_can_manually_assign_a_published_exam_to_student(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        [$subject, $classLevel] = $this->taxonomy();
        $exam = $this->exam($subject, $classLevel);

        $this->actingAs($admin)
            ->post(route('admin.users.enrollments.store', $student), [
            'exam_id' => $exam->id,
            'manual_reference' => 'UPI12345',
            'manual_note' => 'Payment received offline.',
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('exam_enrollments', [
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'enrolled',
            'enrollment_source' => 'manual_payment',
            'assigned_by_admin_id' => $admin->id,
            'amount' => 250,
        ]);

        $payment = Payment::where('user_id', $student->id)->firstOrFail();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('manual', $payment->gateway);
        $this->assertTrue($payment->is_manual);
        $this->assertSame($admin->id, $payment->recorded_by_admin_id);
        $this->assertSame('UPI12345', $payment->manual_reference);
        $this->assertEquals(250.00, (float) $payment->amount);

        $this->assertDatabaseHas('exam_enrollments', [
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'payment_id' => $payment->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.totalRevenue', 250)
                ->where('stats.revenueMonth', 250)
            );

        $this->actingAs($admin)
            ->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Show')
                ->has('enrollments', 1)
                ->where('enrollments.0.exam.name', $exam->name)
                ->where('enrollments.0.status', 'enrolled')
            );
    }

    public function test_assignment_is_idempotent_and_rejects_unpublished_exams(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        [$subject, $classLevel] = $this->taxonomy();
        $published = $this->exam($subject, $classLevel);
        $draft = $this->exam($subject, $classLevel, ['status' => 'draft', 'published_at' => null]);

        $this->actingAs($admin)->post(route('admin.users.enrollments.store', $student), [
            'exam_id' => $published->id,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.users.enrollments.store', $student), [
            'exam_id' => $published->id,
        ])->assertRedirect();

        $this->assertSame(1, ExamEnrollment::where('user_id', $student->id)->where('exam_id', $published->id)->count());

        $this->actingAs($admin)->post(route('admin.users.enrollments.store', $student), [
            'exam_id' => $draft->id,
        ])->assertSessionHasErrors('exam_id');
    }

    public function test_admin_can_cancel_assignment_until_student_attempts_exam(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        [$subject, $classLevel] = $this->taxonomy();
        $exam = $this->exam($subject, $classLevel);

        $enrollment = ExamEnrollment::create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'enrolled',
            'enrollment_source' => 'admin',
            'assigned_by_admin_id' => $admin->id,
            'assigned_at' => now(),
            'amount' => 0,
            'currency' => 'INR',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.enrollments.cancel', [$student, $enrollment]))
            ->assertRedirect();

        $this->assertSame('cancelled', $enrollment->fresh()->status);

        $enrollment = $enrollment->fresh();
        $enrollment->update(['status' => 'enrolled']);
        ExamAttempt::create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'submitted',
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.enrollments.cancel', [$student, $enrollment]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('enrolled', $enrollment->fresh()->status);
    }

    public function test_admin_can_reconcile_pending_payment_and_grant_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        [$subject, $classLevel] = $this->taxonomy();
        $exam = $this->exam($subject, $classLevel);

        $payment = Payment::create([
            'user_id' => $student->id,
            'amount' => 250,
            'gross_amount' => 250,
            'discount_amount' => 0,
            'currency' => 'INR',
            'status' => 'created',
            'gateway' => 'razorpay',
            'method' => null,
            'notes' => ['exam_ids' => [$exam->id]],
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.payments.reconcile', $payment), [
                'manual_reference' => 'RZP_VERIFIED_123',
                'manual_note' => 'Verified in Razorpay dashboard.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $payment->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertTrue($payment->is_manual);
        $this->assertSame('manual_reconcile', $payment->method);
        $this->assertSame($admin->id, $payment->recorded_by_admin_id);
        $this->assertSame('RZP_VERIFIED_123', $payment->manual_reference);

        $this->assertDatabaseHas('exam_enrollments', [
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'payment_id' => $payment->id,
            'status' => 'enrolled',
            'enrollment_source' => 'manual_reconcile',
            'amount' => 250,
        ]);
    }

    public function test_admin_can_downgrade_paid_payment_and_remove_linked_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        [$subject, $classLevel] = $this->taxonomy();
        $exam = $this->exam($subject, $classLevel);

        $this->actingAs($admin)
            ->post(route('admin.users.enrollments.store', $student), [
                'exam_id' => $exam->id,
                'manual_reference' => 'TEST_USER',
            ])
            ->assertRedirect();

        $payment = Payment::where('user_id', $student->id)->firstOrFail();
        $enrollment = ExamEnrollment::where('payment_id', $payment->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.payments.refund', $payment), [
                'manual_note' => 'Testing access removed.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('cancelled', $enrollment->fresh()->status);
        $this->assertSame('Testing access removed.', $payment->fresh()->manual_note);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.totalRevenue', 0)
                ->where('stats.revenueMonth', 0)
            );
    }

    public function test_admin_can_downgrade_pending_payment_without_granting_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        [$subject, $classLevel] = $this->taxonomy();
        $exam = $this->exam($subject, $classLevel);

        $payment = Payment::create([
            'user_id' => $student->id,
            'amount' => 250,
            'gross_amount' => 250,
            'discount_amount' => 0,
            'currency' => 'INR',
            'status' => 'created',
            'gateway' => 'razorpay',
            'notes' => ['exam_ids' => [$exam->id]],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.refund', $payment), [
                'manual_note' => 'Student cancelled before paying.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertDatabaseMissing('exam_enrollments', [
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_admin_cannot_downgrade_payment_after_linked_exam_attempt(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        [$subject, $classLevel] = $this->taxonomy();
        $exam = $this->exam($subject, $classLevel);

        $this->actingAs($admin)
            ->post(route('admin.users.enrollments.store', $student), ['exam_id' => $exam->id])
            ->assertRedirect();

        $payment = Payment::where('user_id', $student->id)->firstOrFail();
        $enrollment = ExamEnrollment::where('payment_id', $payment->id)->firstOrFail();

        ExamAttempt::create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'submitted',
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.refund', $payment))
            ->assertRedirect()
            ->assertSessionHasErrors('payment');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('enrolled', $enrollment->fresh()->status);
    }

    private function taxonomy(): array
    {
        $subject = Subject::create([
            'name' => 'Mathematics',
            'slug' => 'mathematics',
            'icon' => 'MT',
            'color' => '#2C49A6',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $classLevel = ClassLevel::create([
            'level' => 5,
            'label' => 'Class 5',
            'is_active' => true,
            'sort_order' => 5,
        ]);

        return [$subject, $classLevel];
    }

    private function exam(Subject $subject, ClassLevel $classLevel, array $overrides = []): Exam
    {
        return Exam::create(array_merge([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'name' => 'National Mathematics Olympiad',
            'slug' => fake()->unique()->slug(),
            'exam_code' => 'NMO'.fake()->unique()->numberBetween(1000, 9999),
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeeks(2),
            'duration_minutes' => 60,
            'fee_amount' => 250,
            'fee_currency' => 'INR',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }
}
