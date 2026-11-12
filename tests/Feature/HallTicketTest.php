<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Models\HallTicket;
use App\Models\StudentNotification;
use App\Models\Subject;
use App\Models\User;
use App\Services\HallTicketService;
use App\Support\AdminPermissions;
use App\Support\LocalTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HallTicketTest extends TestCase
{
    use RefreshDatabase;

    private ClassLevel $class;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-11 09:00:00');
        Storage::fake('local');

        $this->class = ClassLevel::create(['level' => 6, 'label' => 'Class 6', 'is_active' => true, 'sort_order' => 6]);
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Head Admin']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /* ───────────── completion & eligibility ───────────── */

    public function test_completion_counts_new_fields_and_ignores_the_avatar(): void
    {
        $user = $this->student(['photo' => 'avatars/x.jpg']);
        $completion = $user->profileCompletion();

        $this->assertSame(15, $completion['total']);
        $this->assertSame(10, $completion['filled']);              // core details only
        $this->assertSame(66, $completion['percent']);             // floor(10/15)
        $this->assertContains('Live photo', $completion['missing']);
        $this->assertNotContains('Photo', $completion['missing']);
    }

    public function test_eligibility_needs_ninety_percent_and_every_required_item(): void
    {
        $service = app(HallTicketService::class);

        // 14/15 = 93% but a required document is missing → not eligible.
        $user = $this->student(['coordinator_name' => 'Mrs Rao', 'coordinator_phone' => '+919876543210',
            'live_photo' => 'a.jpg', 'passport_photo' => 'b.jpg']);
        $result = $service->eligibility($user);
        $this->assertSame(93, $result['percent']);
        $this->assertFalse($result['eligible']);
        $this->assertSame(['School ID card'], $result['required_missing']);

        // All required present, one optional detail missing → 93% and eligible.
        $user = $this->completeStudent(['school_address' => null]);
        $result = $service->eligibility($user);
        $this->assertSame(93, $result['percent']);
        $this->assertTrue($result['eligible']);

        // Two optional details missing → 86% → not eligible.
        $user = $this->completeStudent(['school_address' => null, 'pincode' => null]);
        $this->assertFalse($service->eligibility($user)['eligible']);
    }

    public function test_dashboard_exposes_hall_ticket_summary(): void
    {
        $this->actingAs($this->student())
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hallTicket.status', 'none')
                ->where('hallTicket.eligibility.eligible', false)
                ->where('hallTicket.eligibility.min_percent', 90));
    }

    /* ───────────── submission ───────────── */

    public function test_incomplete_profile_cannot_submit(): void
    {
        $user = $this->student();

        $this->actingAs($user)->post(route('student.hall-ticket.store'))->assertSessionHas('error');
        $this->assertDatabaseCount('hall_tickets', 0);
    }

    public function test_complete_profile_submits_once_and_is_idempotent(): void
    {
        $user = $this->completeStudent();

        $this->actingAs($user)->post(route('student.hall-ticket.store'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'awaiting admin verification'));
        $this->actingAs($user)->post(route('student.hall-ticket.store'));

        $ticket = HallTicket::firstOrFail();
        $this->assertSame('pending', $ticket->status);
        $this->assertSame(1, $ticket->submission_count);
        $this->assertNull($ticket->roll_number);
    }

    public function test_hall_ticket_page_shows_preview_for_eligible_student_without_roll_number(): void
    {
        $user = $this->completeStudent();
        $exam = $this->exam('Maths Olympiad', '2026-11-20 10:00:00', '2026-11-20 13:00:00');
        $enrollment = ExamEnrollment::create(['user_id' => $user->id, 'exam_id' => $exam->id, 'status' => 'enrolled', 'enrolled_at' => now()]);

        $this->actingAs($user)->get(route('student.hall-ticket'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/HallTicket/Index')
                ->where('summary.status', 'none')
                ->where('ticket.header.roll_number', null)
                ->where('ticket.watermark', 'PREVIEW')
                ->where('ticket.schedule.rows.0.enrollment_id.text', 'NEO-EN-'.str_pad((string) $enrollment->id, 6, '0', STR_PAD_LEFT))
                ->where('ticket.schedule.rows.0.date.text', 'Fri, 20 Nov 2026')
                ->where('ticket.schedule.rows.0.start_time.text', '10:00 AM')
                ->where('ticket.schedule.rows.0.login_by.text', '9:45 AM')
                ->where('ticket.schedule.rows.0.window_closes.text', '1:00 PM')
                ->where('ticket.details', fn ($details) => collect($details)->firstWhere('key', 'coordinator_name')['value'] === 'Mrs Rao'));
    }

    /* ───────────── lock ───────────── */

    public function test_submitted_profile_locks_verified_fields_but_not_contact_details(): void
    {
        $user = $this->completeStudent();
        app(HallTicketService::class)->submit($user);

        $this->actingAs($user)->put(route('student.profile.update'), [
            'name' => 'Changed Name', 'email' => 'new@example.test', 'class_level_id' => $this->class->id,
            'coordinator_name' => 'Someone Else',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Asha Verma', $user->name);
        $this->assertSame('Mrs Rao', $user->coordinator_name);
        $this->assertSame('new@example.test', $user->email);

        $this->actingAs($user)->post(route('student.profile.documents.store', 'passport_photo'), ['file' => $this->photo()])
            ->assertSessionHas('error');
        $this->actingAs($user)->delete(route('student.profile.documents.delete', 'live_photo'))->assertSessionHas('error');
        $this->assertSame('live.jpg', $user->fresh()->live_photo);
    }

    /* ───────────── documents ───────────── */

    public function test_documents_are_stored_privately_and_re_encoded(): void
    {
        $user = $this->student();

        $this->actingAs($user)->post(route('student.profile.documents.store', 'passport_photo'), ['file' => $this->photo('png')])
            ->assertSessionHasNoErrors();

        $path = $user->fresh()->passport_photo;
        $this->assertStringStartsWith("student-documents/{$user->id}/passport_photo-", $path);
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame('image/jpeg', getimagesizefromstring(Storage::disk('local')->get($path))['mime']);

        $this->actingAs($user)->get(route('student.profile.documents.show', 'passport_photo'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        // Replacing deletes the old file.
        $this->actingAs($user)->post(route('student.profile.documents.store', 'passport_photo'), ['file' => $this->photo()]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_document_validation(): void
    {
        $user = $this->student();

        // Too small for a passport photo.
        $this->actingAs($user)->post(route('student.profile.documents.store', 'passport_photo'), ['file' => UploadedFile::fake()->image('p.jpg', 100, 100)])
            ->assertSessionHasErrors('file', null, 'default');
        // PDF only allowed for the ID card.
        $this->actingAs($user)->post(route('student.profile.documents.store', 'live_photo'), ['file' => UploadedFile::fake()->create('x.pdf', 20, 'application/pdf')])
            ->assertSessionHasErrors('file');
        // Unknown document type.
        $this->actingAs($user)->post('/student/profile/documents/aadhaar', ['file' => $this->photo()])->assertNotFound();

        $this->actingAs($user)->post(route('student.profile.documents.store', 'school_id_card'), [
            'file' => UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"),
        ])->assertSessionHasNoErrors();
        $this->assertStringEndsWith('.pdf', $user->fresh()->school_id_card);
    }

    public function test_students_cannot_reach_other_students_documents(): void
    {
        $owner = $this->completeStudent();
        $ticket = app(HallTicketService::class)->submit($owner);
        $other = $this->student(['email' => 'other@example.test']);

        // The student route only ever serves the signed-in student's own file.
        $this->actingAs($other)->get(route('student.profile.documents.show', 'passport_photo'))->assertNotFound();
        $this->actingAs($other)->get(route('admin.hall-tickets.document', [$ticket->id, 'passport_photo']))->assertRedirect();
    }

    /* ───────────── admin review ───────────── */

    public function test_admin_approves_and_student_can_print(): void
    {
        $user = $this->completeStudent();
        $ticket = app(HallTicketService::class)->submit($user);

        $this->actingAs($user)->get(route('student.hall-ticket.print'))->assertRedirect(route('student.hall-ticket'));

        $this->actingAs($this->admin)->get(route('admin.hall-tickets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/HallTickets/Index')
                ->where('stats.pending', 1)
                ->where('tickets.data.0.student.name', 'Asha Verma'));

        $this->actingAs($this->admin)->post(route('admin.hall-tickets.approve', $ticket))->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame('approved', $ticket->status);
        $this->assertSame('NEO2606'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT), $ticket->roll_number);
        $this->assertSame($this->admin->id, $ticket->reviewed_by);
        $this->assertTrue(StudentNotification::where('user_id', $user->id)->where('title', 'Your hall ticket is ready')->exists());

        $this->actingAs($user)->get(route('student.hall-ticket.print'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Student/HallTicket/Print')->where('ticket.header.roll_number', $ticket->roll_number));
        $this->actingAs($this->admin)->get(route('admin.hall-tickets.print', $ticket))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.hall-tickets.document', [$ticket->id, 'live_photo']))->assertOk();

        // Approving twice is refused.
        $this->actingAs($this->admin)->post(route('admin.hall-tickets.approve', $ticket))->assertSessionHas('error');
    }

    public function test_reject_requires_reason_unlocks_profile_and_resubmission_keeps_roll_number(): void
    {
        $user = $this->completeStudent();
        $service = app(HallTicketService::class);
        $ticket = $service->submit($user);

        $this->actingAs($this->admin)->post(route('admin.hall-tickets.reject', $ticket), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->post(route('admin.hall-tickets.print', $ticket))->assertStatus(405);

        $service->approve($ticket, $this->admin);
        $roll = $ticket->fresh()->roll_number;

        // Revoke the approval.
        $this->actingAs($this->admin)->post(route('admin.hall-tickets.reject', $ticket), ['reason' => 'Passport photo is blurry.'])
            ->assertSessionHas('success');
        $this->assertSame('rejected', $ticket->fresh()->status);
        $this->assertFalse($service->isLocked($user->fresh()));
        $this->actingAs($this->admin)->get(route('admin.hall-tickets.print', $ticket))->assertRedirect();

        // Student fixes and resubmits.
        $this->actingAs($user)->put(route('student.profile.update'), [
            'name' => 'Asha R. Verma', 'email' => $user->email, 'class_level_id' => $this->class->id,
            'school' => 'DPS', 'school_address' => 'Sector 4', 'city' => 'Delhi', 'pincode' => '110001', 'state' => 'Delhi',
            'coordinator_name' => 'Mrs Rao', 'coordinator_phone' => '98765 43210', 'phone' => '9123456780', 'dob' => '2014-05-01',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Asha R. Verma', $user->fresh()->name);
        $this->assertSame('+919876543210', $user->fresh()->coordinator_phone);

        $this->actingAs($user)->post(route('student.hall-ticket.store'))->assertSessionHas('success');
        $ticket->refresh();
        $this->assertSame('pending', $ticket->status);
        $this->assertSame(2, $ticket->submission_count);
        $this->assertNull($ticket->rejection_reason);

        $service->approve($ticket, $this->admin);
        $this->assertSame($roll, $ticket->fresh()->roll_number);
    }

    public function test_hall_tickets_module_is_permission_gated(): void
    {
        $ticket = app(HallTicketService::class)->submit($this->completeStudent());

        $reader = $this->staff(['hall_tickets' => ['read' => true]]);
        $this->actingAs($reader)->get(route('admin.hall-tickets.show', $ticket))->assertOk();
        // The permission middleware bounces to the admin's first allowed page.
        $this->actingAs($reader)->post(route('admin.hall-tickets.approve', $ticket))->assertRedirect()->assertSessionHas('error');

        $outsider = $this->staff(['students' => ['read' => true]]);
        $this->actingAs($outsider)->get(route('admin.hall-tickets.index'))->assertRedirect(route('admin.users.index'));
        $this->actingAs($outsider)->get(route('admin.hall-tickets.document', [$ticket->id, 'passport_photo']))->assertRedirect(route('admin.users.index'));
        $this->actingAs($outsider)->getJson(route('admin.hall-tickets.show', $ticket))->assertForbidden();

        $this->assertSame('pending', $ticket->fresh()->status);
    }

    /* ───────────── helpers ───────────── */

    private function student(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'student', 'is_active' => true, 'name' => 'Asha Verma', 'class_level_id' => $this->class->id,
            'phone' => '9123456780', 'dob' => '2014-05-01', 'school' => 'DPS', 'school_address' => 'Sector 4',
            'city' => 'Delhi', 'pincode' => '110001', 'state' => 'Delhi',
        ], $overrides));
    }

    /** Every item filled. Documents get real files on the fake disk. */
    private function completeStudent(array $overrides = []): User
    {
        foreach (['live.jpg', 'passport.jpg', 'id.jpg'] as $file) {
            Storage::disk('local')->put($file, UploadedFile::fake()->image($file, 300, 400)->getContent());
        }

        return $this->student(array_merge([
            'coordinator_name' => 'Mrs Rao', 'coordinator_phone' => '+919876543210',
            'live_photo' => 'live.jpg', 'passport_photo' => 'passport.jpg', 'school_id_card' => 'id.jpg',
        ], $overrides));
    }

    private function photo(string $ext = 'jpg'): UploadedFile
    {
        return UploadedFile::fake()->image("photo.{$ext}", 400, 500);
    }

    private function exam(string $name, string $starts, string $ends): Exam
    {
        $subject = Subject::firstOrCreate(['slug' => 'mathematics'], ['name' => 'Mathematics', 'is_active' => true, 'sort_order' => 1]);

        return Exam::create([
            'subject_id' => $subject->id, 'class_level_id' => $this->class->id, 'name' => $name, 'slug' => str($name)->slug(),
            'exam_code' => 'NEO-'.str()->random(6), 'starts_at' => LocalTime::parse($starts), 'ends_at' => LocalTime::parse($ends), 'duration_minutes' => 60,
            'fee_amount' => 0, 'fee_currency' => 'INR', 'scoring_mode' => 'question_bank', 'marks_per_question' => 1,
            'status' => 'published', 'created_by' => $this->admin->id,
        ]);
    }

    private function staff(array $permissions): User
    {
        $role = AdminRole::create(['name' => 'Role '.str()->random(8), 'is_active' => true]);
        foreach (AdminPermissions::normalizeMatrix($permissions) as $module => $actions) {
            $role->permissions()->create(['module' => $module, 'can_read' => $actions['read'], 'can_write' => $actions['write'], 'can_delete' => $actions['delete']]);
        }

        return User::factory()->create(['role' => 'admin', 'admin_role_id' => $role->id]);
    }
}
