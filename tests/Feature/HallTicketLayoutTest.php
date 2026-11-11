<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\ClassLevel;
use App\Models\HallTicket;
use App\Models\HallTicketSetting;
use App\Models\StudentCustomField;
use App\Models\StudentCustomFieldValue;
use App\Models\User;
use App\Services\HallTicketLayoutService;
use App\Services\HallTicketService;
use App\Services\StudentCustomFieldService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HallTicketLayoutTest extends TestCase
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

    /* ───────────── settings page + RBAC ───────────── */

    public function test_settings_page_shows_config_catalogue_and_seeded_custom_fields(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.hall-ticket'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/HallTicketSettings/Index')
                ->where('config.layout.title', 'Hall Ticket')
                ->has('config.instructions', 9)
                ->where('customFields', fn ($fields) => collect($fields)->pluck('key')->all() === ['father_name', 'mother_name', 'gender', 'school_admission_no'])
                ->where('catalogue.fields', fn ($fields) => collect($fields)->contains('key', 'custom:father_name')));
    }

    public function test_settings_are_permission_gated(): void
    {
        $reader = $this->staff(['settings_hall_ticket' => ['read' => true]]);
        $this->actingAs($reader)->get(route('admin.settings.hall-ticket'))->assertOk();
        // Same URL as the page, so the middleware can't bounce back to it and answers 403.
        $this->actingAs($reader)->put(route('admin.settings.hall-ticket.update'), $this->payload())->assertForbidden();
        $this->actingAs($reader)->post(route('admin.settings.hall-ticket.fields.store'), ['label' => 'X', 'type' => 'text'])->assertSessionHas('error');

        $outsider = $this->staff(['students' => ['read' => true]]);
        $this->actingAs($outsider)->get(route('admin.settings.hall-ticket'))->assertRedirect(route('admin.users.index'));
        $this->actingAs($outsider)->postJson(route('admin.settings.hall-ticket.preview'), $this->payload())->assertForbidden();
        $this->assertSame(0, StudentCustomField::where('label', 'X')->count());
    }

    /* ───────────── normalisation ───────────── */

    public function test_saving_normalises_hostile_or_stale_input(): void
    {
        $payload = $this->payload();
        $payload['fields'] = [
            ['key' => 'dob', 'enabled' => true, 'label' => '  Birth date  ', 'wide' => false],
            ['key' => 'dob', 'enabled' => false],                       // duplicate → dropped
            ['key' => 'password', 'enabled' => true],                   // unknown → dropped
            ['key' => 'name', 'enabled' => true, 'wide' => true],
        ];
        $payload['instructions'] = ['  First rule  ', '', '   ', 'Second rule'];
        $payload['layout'] = ['detail_columns' => 7, 'theme' => 'neon', 'login_offset_minutes' => 500, 'title' => '', 'show_stamp' => 'false', 'photo' => 'left'];

        $this->actingAs($this->admin)->put(route('admin.settings.hall-ticket.update'), $payload)->assertSessionHasNoErrors();

        $config = app(HallTicketLayoutService::class)->config();
        $keys = collect($config['fields'])->pluck('key');
        $this->assertSame(['dob', 'name'], $keys->take(2)->all());
        $this->assertSame('Birth date', $config['fields'][0]['label']);
        $this->assertNotContains('password', $keys);
        $this->assertSame($keys->unique()->count(), $keys->count());
        $this->assertContains('custom:gender', $keys);                // appended, off
        $this->assertFalse(collect($config['fields'])->firstWhere('key', 'custom:gender')['enabled']);
        $this->assertSame(['First rule', 'Second rule'], $config['instructions']);
        $this->assertSame(2, $config['layout']['detail_columns']);
        $this->assertSame('brand', $config['layout']['theme']);
        $this->assertSame(120, $config['layout']['login_offset_minutes']);
        $this->assertSame('Hall Ticket', $config['layout']['title']);
        $this->assertFalse($config['layout']['show_stamp']);
        $this->assertSame('left', $config['layout']['photo']);
        $this->assertSame($this->admin->id, HallTicketSetting::current()->updated_by);
    }

    /* ───────────── rendering follows the saved config ───────────── */

    public function test_printed_ticket_follows_the_saved_configuration(): void
    {
        [$student, $ticket] = $this->approvedStudent();
        StudentCustomFieldValue::create(['student_custom_field_id' => StudentCustomField::where('key', 'father_name')->value('id'), 'user_id' => $student->id, 'value' => 'Rajesh Verma']);

        $payload = $this->payload();
        $payload['fields'] = [
            ['key' => 'custom:father_name', 'enabled' => true, 'label' => 'Father', 'wide' => false],
            ['key' => 'name', 'enabled' => true, 'label' => 'Student name', 'wide' => true],
            ['key' => 'dob', 'enabled' => false],
            ['key' => 'text:centre1', 'enabled' => true],
        ];
        $payload['text_rows'] = [['id' => 'centre1', 'label' => 'Reporting centre', 'value' => 'Online — from home']];
        $payload['exam_columns'] = [['key' => 'exam', 'enabled' => true, 'label' => 'Paper'], ['key' => 'timing', 'enabled' => true]];
        $payload['instructions'] = ['Bring your ID.'];
        $payload['layout'] = ['photo' => 'hidden', 'show_signatures' => false, 'title' => 'Admit Card', 'detail_columns' => 3, 'theme' => 'classic'];
        $this->actingAs($this->admin)->put(route('admin.settings.hall-ticket.update'), $payload)->assertSessionHasNoErrors();

        $this->actingAs($student)->get(route('student.hall-ticket.print'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ticket.header.title', 'Admit Card')
                ->where('ticket.theme', 'classic')
                ->where('ticket.detail_columns', 3)
                ->where('ticket.photo', null)
                ->where('ticket.signatures', [])
                ->where('ticket.instructions.items', ['Bring your ID.'])
                ->where('ticket.details', fn ($details) => collect($details)->map(fn ($d) => $d['label'].'='.$d['value'])->take(3)->all() === [
                    'Father=Rajesh Verma', 'Student name=Asha Verma', 'Reporting centre=Online — from home',
                ] && ! collect($details)->contains('key', 'dob'))
                ->where('ticket.schedule.columns', fn ($cols) => collect($cols)->pluck('label')->all() === ['Paper', 'Timing'])
                ->where('ticket.stamp.text', 'Verified & approved'));
    }

    public function test_preview_renders_a_draft_without_saving_it(): void
    {
        [, $ticket] = $this->approvedStudent();
        $before = HallTicketSetting::current()->fresh()->layout;

        $payload = $this->payload();
        $payload['layout']['title'] = 'Draft Title';

        $this->actingAs($this->admin)->postJson(route('admin.settings.hall-ticket.preview'), [...$payload, 'sample_status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('ticket.header.title', 'Draft Title')
            ->assertJsonPath('ticket.watermark', 'AWAITING VERIFICATION')
            ->assertJsonPath('ticket.header.roll_number', null);

        $this->actingAs($this->admin)->postJson(route('admin.settings.hall-ticket.preview'), [...$payload, 'ticket_id' => $ticket->id])
            ->assertOk()
            ->assertJsonPath('ticket.header.roll_number', $ticket->roll_number)
            ->assertJsonPath('ticket.details.0.value', 'Asha Verma');

        $this->assertSame($before, HallTicketSetting::current()->fresh()->layout);
    }

    public function test_reset_restores_the_default_layout(): void
    {
        $payload = $this->payload();
        $payload['instructions'] = ['Only one'];
        $this->actingAs($this->admin)->put(route('admin.settings.hall-ticket.update'), $payload);

        $this->actingAs($this->admin)->post(route('admin.settings.hall-ticket.reset'))->assertSessionHas('success');
        $this->assertCount(9, app(HallTicketLayoutService::class)->config()['instructions']);
    }

    /* ───────────── custom student fields ───────────── */

    public function test_admin_manages_custom_fields_and_keys_stay_unique(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.hall-ticket.fields.store'), ['label' => 'Blood group', 'type' => 'select', 'options' => ['A+', 'B+', 'O+'], 'is_required' => true])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.settings.hall-ticket.fields.store'), ['label' => 'Blood Group!', 'type' => 'text']);
        $this->actingAs($this->admin)->post(route('admin.settings.hall-ticket.fields.store'), ['label' => 'Name', 'type' => 'text']);
        $this->actingAs($this->admin)->post(route('admin.settings.hall-ticket.fields.store'), ['label' => 'Choice', 'type' => 'select'])->assertSessionHasErrors('options');

        $this->assertSame(['blood_group', 'blood_group_2', 'name_custom'], StudentCustomField::whereIn('label', ['Blood group', 'Blood Group!', 'Name'])->orderBy('id')->pluck('key')->all());

        $field = StudentCustomField::where('key', 'blood_group')->first();
        $this->actingAs($this->admin)->put(route('admin.settings.hall-ticket.fields.update', $field), ['label' => 'Blood type', 'type' => 'text', 'options' => ['A+', 'B+'], 'is_required' => false, 'is_active' => true])
            ->assertSessionHasNoErrors();
        $field->refresh();
        $this->assertSame(['Blood type', 'select', 'blood_group', false], [$field->label, $field->type, $field->key, $field->is_required]); // type + key immutable

        $ids = StudentCustomField::ordered()->pluck('id')->reverse()->values()->all();
        $this->actingAs($this->admin)->post(route('admin.settings.hall-ticket.fields.reorder'), ['ids' => $ids]);
        $this->assertSame($ids, StudentCustomField::ordered()->pluck('id')->all());
    }

    public function test_students_fill_custom_fields_and_required_ones_gate_submission(): void
    {
        $student = $this->completeStudent();
        $gender = StudentCustomField::where('key', 'gender')->first();
        $gender->update(['is_required' => true]);

        $service = app(HallTicketService::class);
        $eligibility = $service->eligibility($student);
        $this->assertFalse($eligibility['eligible']);
        $this->assertSame(['Gender'], $eligibility['required_missing']);
        $this->assertSame(16, $eligibility['total']);       // 15 built-in + 1 required custom; optional ones don't count

        $this->actingAs($student)->get(route('student.profile'))
            ->assertInertia(fn (Assert $page) => $page->where('customFields', fn ($f) => collect($f)->firstWhere('key', 'gender')['required'] === true));

        // Invalid choice rejected, valid one saved, blank clears.
        $this->actingAs($student)->put(route('student.profile.update'), $this->profileInput($student, ['gender' => 'Robot']))->assertSessionHasErrors('custom.gender');
        $this->actingAs($student)->put(route('student.profile.update'), $this->profileInput($student, ['gender' => 'Female', 'father_name' => '  Rajesh  ']))->assertSessionHasNoErrors();
        $this->assertSame(['father_name' => 'Rajesh', 'gender' => 'Female'], collect(app(StudentCustomFieldService::class)->valuesFor($student))->sortKeys()->all());

        $this->assertTrue($service->eligibility($student->fresh())['eligible']);
        $this->actingAs($student)->post(route('student.hall-ticket.store'))->assertSessionHas('success');

        // Locked while pending: custom values can't change.
        $this->actingAs($student)->put(route('student.profile.update'), $this->profileInput($student, ['gender' => 'Male']));
        $this->assertSame('Female', app(StudentCustomFieldService::class)->valuesFor($student)['gender']);
    }

    public function test_deleting_a_custom_field_removes_values_and_its_ticket_slot(): void
    {
        [$student] = $this->approvedStudent();
        $field = StudentCustomField::where('key', 'mother_name')->first();
        StudentCustomFieldValue::create(['student_custom_field_id' => $field->id, 'user_id' => $student->id, 'value' => 'Priya']);

        $payload = $this->payload();
        $payload['fields'][] = ['key' => 'custom:mother_name', 'enabled' => true];
        $this->actingAs($this->admin)->put(route('admin.settings.hall-ticket.update'), $payload);

        $this->actingAs($this->admin)->delete(route('admin.settings.hall-ticket.fields.destroy', $field))->assertSessionHas('success');

        $this->assertDatabaseMissing('student_custom_field_values', ['user_id' => $student->id, 'value' => 'Priya']);
        $this->assertNotContains('custom:mother_name', collect(app(HallTicketLayoutService::class)->config()['fields'])->pluck('key'));
        $this->actingAs($student)->get(route('student.hall-ticket.print'))->assertOk();
    }

    /* ───────────── helpers ───────────── */

    private function payload(): array
    {
        return app(HallTicketLayoutService::class)->defaults();
    }

    private function student(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'student', 'is_active' => true, 'name' => 'Asha Verma', 'class_level_id' => $this->class->id,
            'phone' => '9123456780', 'dob' => '2014-05-01', 'school' => 'DPS', 'school_address' => 'Sector 4',
            'city' => 'Delhi', 'pincode' => '110001', 'state' => 'Delhi',
        ], $overrides));
    }

    private function completeStudent(): User
    {
        foreach (['live.jpg', 'passport.jpg', 'id.jpg'] as $file) {
            Storage::disk('local')->put($file, UploadedFile::fake()->image($file, 300, 400)->getContent());
        }

        return $this->student([
            'coordinator_name' => 'Mrs Rao', 'coordinator_phone' => '+919876543210',
            'live_photo' => 'live.jpg', 'passport_photo' => 'passport.jpg', 'school_id_card' => 'id.jpg',
        ]);
    }

    /** @return array{0: User, 1: HallTicket} */
    private function approvedStudent(): array
    {
        $student = $this->completeStudent();
        $service = app(HallTicketService::class);

        return [$student, $service->approve($service->submit($student), $this->admin)];
    }

    private function profileInput(User $student, array $custom): array
    {
        return [
            'name' => $student->name, 'email' => $student->email, 'class_level_id' => $this->class->id, 'phone' => '9123456780',
            'dob' => '2014-05-01', 'school' => 'DPS', 'school_address' => 'Sector 4', 'city' => 'Delhi', 'pincode' => '110001', 'state' => 'Delhi',
            'coordinator_name' => 'Mrs Rao', 'coordinator_phone' => '+919876543210', 'custom' => $custom,
        ];
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
