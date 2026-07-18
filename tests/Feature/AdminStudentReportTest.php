<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AdminStudentReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_access_reports_and_exports(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
        $this->actingAs($student)->get(route('admin.reports.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($student)->get(route('admin.reports.excel'))->assertRedirect(route('admin.login'));
        $this->actingAs($student)->get(route('admin.reports.pdf'))->assertRedirect(route('admin.login'));
    }

    public function test_report_page_returns_rich_student_data_and_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$math, , $classFive] = $this->taxonomy();
        $exam = $this->exam($math, $classFive, 'Math Challenge', 'MATH-5');
        $paid = User::factory()->create([
            'role' => 'student', 'class_level_id' => $classFive->id, 'name' => 'Paid Student',
            'phone' => '9999999999', 'school' => 'National School', 'city' => 'Pune', 'state' => 'Maharashtra',
            'is_active' => true,
        ]);
        User::factory()->create([
            'role' => 'student', 'class_level_id' => $classFive->id,
            'name' => 'Unpaid Student', 'is_active' => false,
        ]);
        $this->paidEnrollment($paid, $exam, 375);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['sort' => 'name', 'direction' => 'asc']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Index')
                ->has('students.data', 2)
                ->where('summary.matched', 2)
                ->where('summary.paid', 1)
                ->where('summary.unpaid', 1)
                ->where('summary.enrolled', 1)
                ->where('summary.collected', 375)
                ->where('students.data.0.name', 'Paid Student')
                ->where('students.data.0.payment_label', 'Paid')
                ->where('students.data.0.olympiads.0.name', 'Math Challenge')
                ->where('students.data.0.subjects.0', 'Mathematics')
                ->where('students.data.0.paid_total', 375)
                ->where('students.data.1.payment_label', 'Unpaid')
            );

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'class_level_id' => $classFive->id,
                'payment_status' => 'unpaid',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.name', 'Unpaid Student')
                ->where('students.data.0.payment_label', 'Unpaid')
                ->where('students.data.0.paid_total', 0)
                ->where('summary.paid', 0)
                ->where('summary.unpaid', 1)
            );
    }

    public function test_payment_filters_are_scoped_to_the_selected_olympiad_including_pending_carts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$math, $science, $classFive] = $this->taxonomy();
        $mathExam = $this->exam($math, $classFive, 'Math Challenge', 'MATH-5');
        $scienceExam = $this->exam($science, $classFive, 'Science Challenge', 'SCI-5');
        $mathPaid = User::factory()->create(['role' => 'student', 'name' => 'Math Paid']);
        $sciencePaid = User::factory()->create(['role' => 'student', 'name' => 'Science Paid']);
        $pending = User::factory()->create(['role' => 'student', 'name' => 'Math Pending']);
        $neverPaid = User::factory()->create(['role' => 'student', 'name' => 'Never Paid']);
        $this->paidEnrollment($mathPaid, $mathExam, 250);
        $this->paidEnrollment($sciencePaid, $scienceExam, 300);
        Payment::create([
            'user_id' => $pending->id, 'amount' => 250, 'gross_amount' => 250,
            'discount_amount' => 0, 'currency' => 'INR', 'status' => 'created',
            'gateway' => 'razorpay', 'notes' => ['exam_ids' => [$mathExam->id]],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['exam_id' => $mathExam->id, 'payment_status' => 'paid']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.name', 'Math Paid')
            );

        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['exam_id' => $mathExam->id, 'payment_status' => 'pending']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.name', 'Math Pending')
            );

        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['exam_id' => $mathExam->id, 'payment_status' => 'unpaid', 'sort' => 'name', 'direction' => 'asc']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 3)
                ->where('students.data.0.name', 'Math Pending')
                ->where('students.data.0.payment_label', 'Unpaid')
                ->where('students.data.0.paid_total', 0)
                ->where('students.data.1.name', 'Never Paid')
                ->where('students.data.1.payment_label', 'Unpaid')
                ->where('students.data.2.name', 'Science Paid')
                ->where('students.data.2.payment_label', 'Unpaid')
                ->where('students.data.2.paid_total', 0)
            );
    }

    public function test_subject_class_and_course_filters_combine(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$math, $science, $classFive, $classSix] = $this->taxonomy();
        $mathExam = $this->exam($math, $classFive, 'Math Challenge', 'MATH-5');
        $scienceExam = $this->exam($science, $classSix, 'Science Challenge', 'SCI-6');
        $mathStudent = User::factory()->create(['role' => 'student', 'name' => 'Math Five', 'class_level_id' => $classFive->id]);
        $scienceStudent = User::factory()->create(['role' => 'student', 'name' => 'Science Six', 'class_level_id' => $classSix->id]);
        User::factory()->create(['role' => 'student', 'name' => 'Other Five', 'class_level_id' => $classFive->id]);
        $this->freeEnrollment($mathStudent, $mathExam);
        $this->freeEnrollment($scienceStudent, $scienceExam);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'subject_id' => $math->id,
                'class_level_id' => $classFive->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.name', 'Math Five')
            );

        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['subject_id' => $science->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.name', 'Science Six')
            );

        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['exam_id' => $mathExam->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.name', 'Math Five')
                ->where('students.data.0.olympiads.0.name', 'Math Challenge')
            );
    }

    public function test_report_rejects_invalid_ranges_and_mismatched_subject_olympiad(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$math, $science, $classFive] = $this->taxonomy();
        $exam = $this->exam($math, $classFive, 'Math Challenge', 'MATH-5');

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'subject_id' => $science->id,
                'exam_id' => $exam->id,
                'date_from' => '2026-07-20',
                'date_to' => '2026-07-01',
            ]))
            ->assertSessionHasErrors(['exam_id', 'date_to']);
    }

    public function test_single_joined_date_range_combines_with_class_and_payment_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, , $classFive, $classSix] = $this->taxonomy();
        User::factory()->create([
            'role' => 'student', 'name' => 'In Range', 'class_level_id' => $classFive->id,
            'created_at' => '2026-07-10 10:00:00',
        ]);
        User::factory()->create([
            'role' => 'student', 'name' => 'Too Early', 'class_level_id' => $classFive->id,
            'created_at' => '2026-06-30 10:00:00',
        ]);
        User::factory()->create([
            'role' => 'student', 'name' => 'Wrong Class', 'class_level_id' => $classSix->id,
            'created_at' => '2026-07-10 10:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'class_level_id' => $classFive->id,
                'payment_status' => 'unpaid',
                'date_from' => '2026-07-01',
                'date_to' => '2026-07-15',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.name', 'In Range')
                ->where('students.data.0.payment_label', 'Unpaid')
            );
    }

    public function test_excel_export_is_a_real_workbook_and_keeps_formula_like_values_as_text(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'student', 'name' => '=2+2', 'email' => 'formula@example.test', 'state' => 'Maharashtra']);
        User::factory()->create(['role' => 'student', 'name' => 'Excluded Student', 'state' => 'Delhi']);

        $response = $this->actingAs($admin)->get(route('admin.reports.excel', ['state' => 'Maharashtra']));
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'noh-report-').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);

        $this->assertSame('=2+2', $spreadsheet->getActiveSheet()->getCell('B7')->getValue());
        $this->assertSame('s', $spreadsheet->getActiveSheet()->getCell('B7')->getDataType());
        $this->assertSame('formula@example.test', $spreadsheet->getActiveSheet()->getCell('C7')->getValue());
        $this->assertSame('', (string) $spreadsheet->getActiveSheet()->getCell('B8')->getValue());

        $spreadsheet->disconnectWorksheets();
        unlink($path);
    }

    public function test_pdf_export_is_a_real_filtered_pdf(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'student', 'name' => 'PDF Student', 'state' => 'Maharashtra']);
        User::factory()->create(['role' => 'student', 'name' => 'Excluded Student', 'state' => 'Delhi']);

        $response = $this->actingAs($admin)->get(route('admin.reports.pdf', ['state' => 'Maharashtra']));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('attachment; filename="student-report-', $response->headers->get('content-disposition'));
    }

    private function taxonomy(): array
    {
        $math = Subject::create(['name' => 'Mathematics', 'slug' => 'mathematics', 'is_active' => true, 'sort_order' => 1]);
        $science = Subject::create(['name' => 'Science', 'slug' => 'science', 'is_active' => true, 'sort_order' => 2]);
        $classFive = ClassLevel::create(['level' => 5, 'label' => 'Class 5', 'is_active' => true, 'sort_order' => 5]);
        $classSix = ClassLevel::create(['level' => 6, 'label' => 'Class 6', 'is_active' => true, 'sort_order' => 6]);

        return [$math, $science, $classFive, $classSix];
    }

    private function exam(Subject $subject, ClassLevel $classLevel, string $name, string $code): Exam
    {
        return Exam::create([
            'subject_id' => $subject->id, 'class_level_id' => $classLevel->id,
            'name' => $name, 'slug' => strtolower($code), 'exam_code' => $code,
            'duration_minutes' => 60, 'fee_amount' => 250, 'fee_currency' => 'INR',
            'status' => 'published', 'published_at' => now(),
        ]);
    }

    private function paidEnrollment(User $student, Exam $exam, float $amount): void
    {
        $payment = Payment::create([
            'user_id' => $student->id, 'amount' => $amount, 'gross_amount' => $amount,
            'discount_amount' => 0, 'currency' => 'INR', 'status' => 'paid',
            'gateway' => 'manual', 'paid_at' => now(), 'notes' => ['exam_ids' => [$exam->id]],
        ]);

        ExamEnrollment::create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'payment_id' => $payment->id,
            'status' => 'enrolled', 'enrollment_source' => 'payment', 'amount' => $amount,
            'currency' => 'INR', 'enrolled_at' => now(),
        ]);
    }

    private function freeEnrollment(User $student, Exam $exam): void
    {
        ExamEnrollment::create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'status' => 'enrolled',
            'enrollment_source' => 'free', 'amount' => 0, 'currency' => 'INR', 'enrolled_at' => now(),
        ]);
    }
}
