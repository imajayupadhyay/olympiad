<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ReceiptSequence;
use App\Models\ReceiptSetting;
use App\Models\Subject;
use App\Models\User;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminReceiptSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_access_receipts_and_settings(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->get(route('admin.receipts.index'))->assertRedirect(route('login'));
        $this->actingAs($student)->get(route('admin.receipts.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($student)->get(route('admin.settings.receipts'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_update_receipt_settings_logo_and_current_sequence(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.settings.receipts'))
            ->post(route('admin.settings.receipts.update'), $this->settingsPayload([
                'company_name' => 'Neo Education Private Limited',
                'gstin' => '07ABCDE1234F1Z5',
                'state_code' => '7',
                'receipt_prefix' => 'NEO/{FY}/',
                'next_sequence_number' => 25,
                'visible_fields' => ['gstin', 'address', 'hsn_sac', 'tax_breakup'],
                'logo' => UploadedFile::fake()->create('receipt-logo.png', 12, 'image/png'),
            ]))
            ->assertRedirect(route('admin.settings.receipts'));

        $settings = ReceiptSetting::current()->refresh();
        $this->assertSame('Neo Education Private Limited', $settings->company_name);
        $this->assertSame('07ABCDE1234F1Z5', $settings->gstin);
        $this->assertSame('07', $settings->state_code);
        $this->assertSame(['gstin', 'address', 'hsn_sac', 'tax_breakup'], $settings->visible_fields);
        $this->assertNotNull($settings->logo_path);
        Storage::disk('public')->assertExists($settings->logo_path);

        $this->assertDatabaseHas('receipt_sequences', [
            'series' => 'default',
            'financial_year' => $settings->financialYear(now()),
            'next_number' => 25,
        ]);
    }

    public function test_receipt_download_assigns_a_real_sequence_once_and_snapshots_tax_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$subject, $classLevel] = $this->taxonomy();
        $exam = $this->exam($subject, $classLevel, 590);
        $student = User::factory()->create([
            'role' => 'student',
            'name' => 'Receipt Student',
            'state' => 'Maharashtra',
            'class_level_id' => $classLevel->id,
        ]);
        $payment = $this->paidPayment($student, $exam, 590, '2026-07-10 10:00:00');

        ReceiptSetting::current()->update([
            'company_name' => 'Neo Education',
            'gstin' => '27ABCDE1234F1Z5',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'hsn_sac' => '999293',
            'receipt_prefix' => 'INV/{FY}/',
            'receipt_padding' => 3,
            'gst_rate' => 18,
            'prices_include_gst' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.receipts.download', $payment));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        $receipt = Receipt::where('payment_id', $payment->id)->firstOrFail();
        $this->assertSame('INV/2026-27/001', $receipt->receipt_number);
        $this->assertSame('999293', $receipt->line_items[0]['hsn_sac']);
        $this->assertSame(500.0, (float) $receipt->totals['taxable_amount']);
        $this->assertSame(45.0, (float) $receipt->totals['cgst_amount']);
        $this->assertSame(45.0, (float) $receipt->totals['sgst_amount']);
        $this->assertSame(590.0, (float) $receipt->totals['amount_paid']);

        $this->actingAs($admin)->get(route('admin.payments.receipt', $payment))->assertOk();
        $this->assertSame(1, Receipt::where('payment_id', $payment->id)->count());
        $this->assertSame(2, ReceiptSequence::where('financial_year', '2026-27')->value('next_number'));
    }

    public function test_receipts_page_lists_only_completed_payments_with_issue_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$subject, $classLevel] = $this->taxonomy();
        $exam = $this->exam($subject, $classLevel, 250);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Paid Student']);
        $paid = $this->paidPayment($student, $exam, 250, '2026-07-11 10:00:00');
        Payment::create([
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
            ->get(route('admin.receipts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Receipts/Index')
                ->has('payments.data', 1)
                ->where('payments.data.0.id', $paid->id)
                ->where('payments.data.0.receipt', null)
                ->where('summary.paid_count', 1)
                ->where('summary.unissued_count', 1)
            );
    }

    public function test_bulk_receipt_download_and_sales_report_issue_missing_receipts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$subject, $classLevel] = $this->taxonomy();
        $exam = $this->exam($subject, $classLevel, 300);
        $first = $this->paidPayment(User::factory()->create(['role' => 'student', 'name' => 'First']), $exam, 300, '2026-07-01 09:00:00');
        $second = $this->paidPayment(User::factory()->create(['role' => 'student', 'name' => 'Second']), $exam, 300, '2026-07-02 09:00:00');

        $bulk = $this->actingAs($admin)->get(route('admin.receipts.bulk', ['ids' => "{$second->id},{$first->id}"]));
        $bulk->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $bulk->getContent());

        $this->assertSame('NOH/2026-27/0001', Receipt::where('payment_id', $first->id)->first()->receipt_number);
        $this->assertSame('NOH/2026-27/0002', Receipt::where('payment_id', $second->id)->first()->receipt_number);

        $report = $this->actingAs($admin)->get(route('admin.receipts.sales-report', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]));
        $report->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $report->getContent());
        $this->assertStringContainsString('sales-report-01-07-2026-to-31-07-2026.pdf', $report->headers->get('content-disposition'));
        $this->assertSame(2, Receipt::count());
    }

    public function test_existing_receipts_and_reports_render_latest_company_settings(): void
    {
        [$subject, $classLevel] = $this->taxonomy();
        $payment = $this->paidPayment(
            User::factory()->create(['role' => 'student', 'name' => 'Legacy Student']),
            $this->exam($subject, $classLevel, 590),
            590,
            '2026-07-15 09:00:00',
        );

        ReceiptSetting::current()->update([
            'company_name' => 'Old Company Name',
            'gstin' => '27AAAAA1111A1Z5',
            'address' => 'Old Office Road',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'email' => 'old@example.test',
            'phone' => '9000000000',
            'hsn_sac' => '654321',
            'visible_fields' => array_keys(ReceiptSetting::VISIBLE_FIELD_LABELS),
            'footer_note' => 'Old receipt footer.',
        ]);

        $receipt = app(ReceiptService::class)->issueForPayment($payment);
        $this->assertSame('Old Office Road', $receipt->company_snapshot['address']);
        $this->assertSame('654321', $receipt->line_items[0]['hsn_sac']);

        ReceiptSetting::current()->update([
            'company_name' => 'New Company Name',
            'gstin' => '27BBBBB2222B1Z5',
            'address' => 'New Registered Address',
            'email' => 'new@example.test',
            'phone' => '9111111111',
            'hsn_sac' => '999999',
            'footer_note' => 'New receipt footer.',
        ]);

        $renderCompany = ReceiptSetting::current()->refresh()->renderCompanyPayload();
        $receipt = $receipt->refresh();
        $receiptHtml = view('receipts.pdf', [
            'receipts' => collect([$receipt]),
            'company' => $renderCompany,
            'generatedAt' => now(),
        ])->render();

        $this->assertStringContainsString('New Company Name', $receiptHtml);
        $this->assertStringContainsString('New Registered Address', $receiptHtml);
        $this->assertStringContainsString('27BBBBB2222B1Z5', $receiptHtml);
        $this->assertStringContainsString('999999', $receiptHtml);
        $this->assertStringContainsString('New receipt footer.', $receiptHtml);
        $this->assertStringContainsString('Math Challenge', $receiptHtml);
        $this->assertStringNotContainsString('MATH-5', $receiptHtml);
        $this->assertStringNotContainsString('Old Office Road', $receiptHtml);
        $this->assertStringNotContainsString('654321', $receiptHtml);

        $reportHtml = view('receipts.sales-report-pdf', [
            'receipts' => collect([$receipt]),
            'summary' => app(ReceiptService::class)->summary(collect([$receipt])),
            'filters' => [
                'date_from' => '2026-07-01',
                'date_to' => '2026-07-31',
            ],
            'company' => $renderCompany,
            'generatedAt' => now(),
        ])->render();

        $this->assertStringContainsString('New Company Name', $reportHtml);
        $this->assertStringContainsString('New Registered Address', $reportHtml);
        $this->assertStringContainsString('27BBBBB2222B1Z5', $reportHtml);
        $this->assertStringContainsString('999999', $reportHtml);
        $this->assertStringNotContainsString('Old Office Road', $reportHtml);
        $this->assertStringNotContainsString('654321', $reportHtml);
    }

    public function test_sequence_cannot_be_rewound_below_an_issued_receipt(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$subject, $classLevel] = $this->taxonomy();
        $payment = $this->paidPayment(User::factory()->create(['role' => 'student']), $this->exam($subject, $classLevel, 250), 250, '2026-07-03 09:00:00');

        $this->actingAs($admin)->get(route('admin.receipts.download', $payment))->assertOk();

        $this->actingAs($admin)
            ->from(route('admin.settings.receipts'))
            ->post(route('admin.settings.receipts.update'), $this->settingsPayload([
                'next_sequence_number' => 1,
            ]))
            ->assertRedirect(route('admin.settings.receipts'))
            ->assertSessionHasErrors('next_sequence_number');
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'National Olympiad Hunt',
            'gstin' => '27ABCDE1234F1Z5',
            'address' => 'Registered office, Mumbai',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'email' => 'accounts@example.test',
            'phone' => '9999999999',
            'website' => 'https://example.test',
            'hsn_sac' => '999293',
            'service_description' => 'Online Olympiad Exam Registration',
            'gst_rate' => 18,
            'prices_include_gst' => true,
            'receipt_prefix' => 'NOH/{FY}/',
            'receipt_padding' => 4,
            'financial_year_start_month' => 4,
            'next_sequence_number' => 1,
            'visible_fields' => array_keys(ReceiptSetting::VISIBLE_FIELD_LABELS),
            'footer_note' => 'Computer-generated receipt.',
        ], $overrides);
    }

    private function taxonomy(): array
    {
        $subject = Subject::create(['name' => 'Mathematics', 'slug' => 'mathematics', 'is_active' => true, 'sort_order' => 1]);
        $classLevel = ClassLevel::create(['level' => 5, 'label' => 'Class 5', 'is_active' => true, 'sort_order' => 5]);

        return [$subject, $classLevel];
    }

    private function exam(Subject $subject, ClassLevel $classLevel, float $fee): Exam
    {
        return Exam::create([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'name' => 'Math Challenge',
            'slug' => 'math-challenge',
            'exam_code' => 'MATH-5',
            'duration_minutes' => 60,
            'fee_amount' => $fee,
            'fee_currency' => 'INR',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    private function paidPayment(User $student, Exam $exam, float $amount, string $paidAt): Payment
    {
        $payment = Payment::create([
            'user_id' => $student->id,
            'amount' => $amount,
            'gross_amount' => $amount,
            'discount_amount' => 0,
            'currency' => 'INR',
            'status' => 'paid',
            'gateway' => 'manual',
            'source' => 'admin',
            'method' => 'manual_admin',
            'is_manual' => true,
            'manual_reference' => 'UTR-'.$student->id,
            'paid_at' => $paidAt,
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
            'notes' => ['exam_ids' => [$exam->id]],
        ]);

        ExamEnrollment::create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'payment_id' => $payment->id,
            'status' => 'enrolled',
            'enrollment_source' => 'payment',
            'amount' => $amount,
            'currency' => 'INR',
            'enrolled_at' => $paidAt,
        ]);

        return $payment;
    }
}
