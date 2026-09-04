<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolDesignation;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AdminSchoolManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_access_school_management(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->get(route('admin.schools.index'))->assertRedirect(route('login'));
        $this->actingAs($student)->get(route('admin.schools.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($student)->get(route('admin.schools.excel'))->assertRedirect(route('admin.login'));
        $this->actingAs($student)->getJson(route('admin.schools.search', ['q' => 'Delhi']))->assertRedirect(route('admin.login'));
    }

    public function test_admin_school_name_autocomplete_uses_seed_schools_and_promotes_selection(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seeded = School::create([
            'name' => 'Delhi Public School, Panvel',
            'address' => '27 Sangurli, Panvel',
            'is_active' => true,
            'is_managed' => false,
        ]);
        $managed = School::create([
            'school_code' => 'SCH-MANAGED',
            'name' => 'Delhi Managed School',
            'state' => 'Delhi',
            'is_active' => true,
            'is_managed' => true,
        ]);

        $data = $this->actingAs($admin)
            ->getJson(route('admin.schools.search', ['q' => 'Delhi']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertSame($seeded->id, $data[0]['id']);
        $this->assertSame('Delhi Public School, Panvel', $data[0]['name']);
        $this->assertNotContains($managed->id, array_column($data, 'id'));

        $this->actingAs($admin)->post(route('admin.schools.store'), [
            'source_school_id' => $seeded->id,
            'school_code' => 'SCH-DPS-PNVL',
            'category' => 'a',
            'name' => 'Delhi Public School, Panvel',
            'address' => '27 Sangurli, Panvel',
            'state' => 'Maharashtra',
            'district' => 'Raigad',
            'city' => 'Panvel',
            'pin_code' => '410206',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, School::count());
        $seeded->refresh();
        $this->assertTrue($seeded->is_managed);
        $this->assertSame('SCH-DPS-PNVL', $seeded->school_code);
        $this->assertSame('A', $seeded->category);
        $this->assertSame('Maharashtra', $seeded->state);

        $this->actingAs($admin)
            ->getJson(route('admin.schools.search', ['q' => 'Delhi Public']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_can_manage_school_records_with_multiple_coordinators(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        SchoolDesignation::firstOrCreate(['name' => 'Olympiad Coordinator'], ['is_active' => true, 'sort_order' => 1]);
        SchoolDesignation::firstOrCreate(['name' => 'Academic Head'], ['is_active' => true, 'sort_order' => 2]);

        $response = $this->actingAs($admin)->post(route('admin.schools.store'), [
            'school_code' => 'sch-001',
            'category' => 'a+',
            'name' => 'National Public School',
            'address' => 'Sector 12, Dwarka',
            'state' => 'Delhi',
            'district' => 'South West Delhi',
            'city' => 'New Delhi',
            'pin_code' => '110075',
            'email' => 'office@national.test',
            'mobile' => '+91 9876543210',
            'head_phone' => '9876543211',
            'is_active' => true,
            'coordinators' => [
                ['name' => 'Anita Sharma', 'email' => 'anita@example.test', 'phone' => '9876543212', 'designation' => 'Olympiad Coordinator'],
                ['name' => 'Rahul Verma', 'email' => 'rahul@example.test', 'phone' => '9876543213', 'designation' => 'Academic Head'],
                ['name' => '', 'email' => '', 'phone' => '', 'designation' => ''],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $school = School::where('school_code', 'SCH-001')->firstOrFail();
        $this->assertTrue($school->is_managed);
        $this->assertSame(2, $school->coordinators()->count());

        $this->actingAs($admin)
            ->get(route('admin.schools.index', [
                'search' => 'Anita',
                'state' => 'Delhi',
                'has_coordinators' => 'yes',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Schools/Index')
                ->has('schools.data', 1)
                ->where('schools.data.0.id', $school->id)
                ->where('schools.data.0.school_code', 'SCH-001')
                ->where('schools.data.0.category', 'A+')
                ->where('schools.data.0.coordinators_count', 2)
                ->where('schools.data.0.coordinators.0.name', 'Anita Sharma')
                ->where('categories', ['A+'])
                ->has('schoolDesignations')
                ->where('summary.matched', 1)
            );

        $this->actingAs($admin)->put(route('admin.schools.update', $school), [
            'school_code' => 'SCH-001',
            'category' => 'B',
            'name' => 'National Public School Updated',
            'address' => 'Updated campus address',
            'state' => 'Delhi',
            'district' => 'New Delhi',
            'city' => 'New Delhi',
            'pin_code' => '110001',
            'email' => 'updated@national.test',
            'mobile' => '9876543210',
            'head_phone' => '9876543211',
            'is_active' => false,
            'coordinators' => [
                ['name' => 'Meera Iyer', 'email' => 'meera@example.test', 'phone' => '9876543214', 'designation' => 'Principal'],
            ],
        ])->assertSessionHasNoErrors();

        $school->refresh();
        $this->assertSame('National Public School Updated', $school->name);
        $this->assertSame('B', $school->category);
        $this->assertFalse($school->is_active);
        $this->assertSame(['Meera Iyer'], $school->coordinators()->pluck('name')->all());

        $this->actingAs($admin)
            ->patch(route('admin.schools.toggle', $school), ['is_active' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($school->refresh()->is_active);

        $this->actingAs($admin)
            ->delete(route('admin.schools.destroy', $school))
            ->assertRedirect(route('admin.schools.index', absolute: false));

        $this->assertDatabaseMissing('schools', ['id' => $school->id]);
        $this->assertDatabaseMissing('school_coordinators', ['school_id' => $school->id]);
    }

    public function test_admin_can_manage_school_designations(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertDatabaseHas('school_designations', ['name' => 'Principal', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.school-designations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/SchoolDesignations/Index')
                ->has('designations')
                ->where('summary.active', SchoolDesignation::where('is_active', true)->count())
            );

        $this->actingAs($admin)
            ->post(route('admin.school-designations.store'), ['name' => 'Senior Coordinator'])
            ->assertSessionHasNoErrors();

        $designation = SchoolDesignation::where('name', 'Senior Coordinator')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.school-designations.update', $designation), [
                'name' => 'Regional Coordinator',
                'is_active' => false,
                'sort_order' => 25,
            ])
            ->assertSessionHasNoErrors();

        $designation->refresh();
        $this->assertSame('Regional Coordinator', $designation->name);
        $this->assertFalse($designation->is_active);
        $this->assertSame(25, $designation->sort_order);

        $this->actingAs($admin)
            ->delete(route('admin.school-designations.destroy', $designation))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('school_designations', ['id' => $designation->id]);
    }

    public function test_validation_and_seeded_school_protection(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $managed = School::create([
            'school_code' => 'SCH-EXISTING',
            'name' => 'Managed School',
            'state' => 'Delhi',
            'is_active' => true,
            'is_managed' => true,
        ]);
        $seeded = School::create([
            'name' => 'Seeded Autocomplete School',
            'address' => 'Seeded address',
            'is_active' => true,
            'is_managed' => false,
        ]);

        $this->actingAs($admin)->post(route('admin.schools.store'), [
            'school_code' => 'SCH-EXISTING',
            'name' => '',
            'state' => '',
            'pin_code' => '123',
            'mobile' => 'abc',
            'coordinators' => [
                ['name' => '', 'email' => 'coordinator@example.test', 'phone' => '', 'designation' => 'Teacher'],
            ],
        ])->assertSessionHasErrors(['school_code', 'name', 'state', 'pin_code', 'mobile', 'coordinators.0.name']);

        $this->actingAs($admin)->put(route('admin.schools.update', $seeded), [
            'school_code' => 'SCH-SEEDED',
            'name' => 'Should Not Update',
            'state' => 'Delhi',
        ])->assertNotFound();

        $this->actingAs($admin)->delete(route('admin.schools.destroy', $seeded))->assertNotFound();
        $this->assertDatabaseHas('schools', ['id' => $seeded->id, 'name' => 'Seeded Autocomplete School']);

        $this->seed(SchoolSeeder::class);
        $this->assertDatabaseHas('schools', ['id' => $managed->id, 'school_code' => 'SCH-EXISTING']);
        $this->assertDatabaseMissing('schools', ['id' => $seeded->id]);
    }

    public function test_excel_export_is_real_workbook_with_school_and_coordinator_sheets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $school = School::create([
            'school_code' => 'SCH-XLSX',
            'category' => 'A+',
            'name' => '=2+2',
            'address' => 'Formula campus',
            'state' => 'Maharashtra',
            'district' => 'Pune',
            'city' => 'Pune',
            'pin_code' => '411001',
            'email' => 'xlsx@example.test',
            'mobile' => '9876543210',
            'head_phone' => '9876543211',
            'is_active' => true,
            'is_managed' => true,
        ]);
        $school->coordinators()->create([
            'name' => 'Coordinator One',
            'email' => 'coord@example.test',
            'phone' => '9876543212',
            'designation' => 'Teacher',
            'sort_order' => 1,
        ]);
        School::create([
            'school_code' => 'SCH-EXCLUDED',
            'name' => 'Excluded School',
            'state' => 'Delhi',
            'is_active' => true,
            'is_managed' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.schools.excel', ['state' => 'Maharashtra']));
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'noh-schools-').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);

        $schools = $spreadsheet->getSheetByName('Schools');
        $this->assertNotNull($schools);
        $this->assertSame('SCH-XLSX', $schools->getCell('C7')->getValue());
        $this->assertSame('A+', $schools->getCell('D7')->getValue());
        $this->assertSame('=2+2', $schools->getCell('E7')->getValue());
        $this->assertSame('s', $schools->getCell('E7')->getDataType());
        $this->assertSame('', (string) $schools->getCell('C8')->getValue());

        $coordinators = $spreadsheet->getSheetByName('Coordinators');
        $this->assertNotNull($coordinators);
        $this->assertSame('SCH-XLSX', $coordinators->getCell('B2')->getValue());
        $this->assertSame('Coordinator One', $coordinators->getCell('D2')->getValue());

        $spreadsheet->disconnectWorksheets();
        unlink($path);
    }
}
