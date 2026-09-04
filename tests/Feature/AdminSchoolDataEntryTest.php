<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolDesignation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSchoolDataEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_access_school_data_entry(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->get(route('admin.data-entry.index'))->assertRedirect(route('login'));
        $this->actingAs($student)->get(route('admin.data-entry.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($student)->getJson(route('admin.data-entry.rows'))->assertRedirect(route('admin.login'));
        $this->actingAs($student)->patchJson(route('admin.data-entry.rows.update'), ['rows' => []])->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_load_incomplete_school_rows_for_spreadsheet_entry(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        SchoolDesignation::firstOrCreate(['name' => 'Olympiad Coordinator'], ['is_active' => true, 'sort_order' => 1]);
        $incomplete = School::create([
            'external_school_id' => '22709',
            'school_code' => '72282',
            'name' => 'DAV Sushil Kedia Vishwa Bharti Higher Secondary School',
            'address' => 'Dhobighat Jwalakhel',
            'state' => 'Kathmandu',
            'city' => 'Lalitpur Nepal',
            'is_active' => true,
            'is_managed' => true,
        ]);
        $complete = School::create([
            'external_school_id' => '21221',
            'school_code' => '37348',
            'name' => 'Guru Nanak Public School',
            'address' => 'Sector 21',
            'state' => 'Odisha',
            'district' => 'Sundargarh',
            'city' => 'Sundargarh',
            'pin_code' => '769001',
            'email' => 'office@guru.test',
            'mobile' => '9876543210',
            'head_phone' => '9876543211',
            'is_active' => true,
            'is_managed' => true,
        ]);
        $complete->coordinators()->create([
            'name' => 'Coordinator',
            'phone' => '9876543212',
            'sort_order' => 1,
        ]);
        $collision = School::create([
            'external_school_id' => '37348',
            'school_code' => '99999',
            'name' => 'Code Collision School',
            'state' => 'Delhi',
            'district' => 'New Delhi',
            'city' => 'New Delhi',
            'pin_code' => '110001',
            'email' => 'collision@example.test',
            'mobile' => '9876543213',
            'head_phone' => '9876543214',
            'is_active' => true,
            'is_managed' => true,
        ]);
        $collision->coordinators()->create([
            'name' => 'Collision Coordinator',
            'phone' => '9876543215',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.data-entry.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/DataEntry/Index')
                ->where('initialRows.data.0.id', $incomplete->id)
                ->where('initialRows.data.0.external_school_id', '22709')
                ->where('initialRows.meta.total', 1)
                ->where('summary.total', 3)
                ->where('summary.incomplete', 1)
                ->where('summary.categories', [])
                ->has('schoolDesignations')
                ->has('queues')
                ->has('categories')
            );

        $data = $this->actingAs($admin)
            ->getJson(route('admin.data-entry.rows', ['search' => '37348', 'queue' => 'all']))
            ->assertOk()
            ->json('rows.data');

        $this->assertSame($complete->id, $data[0]['id']);
        $this->assertSame('37348', $data[0]['school_code']);
        $this->assertCount(1, $data);

        $data = $this->actingAs($admin)
            ->getJson(route('admin.data-entry.rows', ['search' => 'sid:37348', 'queue' => 'all']))
            ->assertOk()
            ->json('rows.data');

        $this->assertSame($collision->id, $data[0]['id']);
        $this->assertSame('99999', $data[0]['school_code']);
        $this->assertCount(1, $data);
    }

    public function test_data_entry_orders_and_filters_rows_by_school_category_priority(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        School::create([
            'school_code' => '10001',
            'category' => 'B',
            'name' => 'B Category School',
            'state' => 'Delhi',
            'is_active' => true,
            'is_managed' => true,
        ]);
        $top = School::create([
            'school_code' => '99999',
            'category' => 'A+',
            'name' => 'A Plus Category School',
            'state' => 'Delhi',
            'is_active' => true,
            'is_managed' => true,
        ]);
        School::create([
            'school_code' => '10000',
            'category' => 'C',
            'name' => 'C Category School',
            'state' => 'Delhi',
            'is_active' => true,
            'is_managed' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.data-entry.index', ['queue' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('initialRows.data.0.id', $top->id)
                ->where('initialRows.data.0.category', 'A+')
                ->where('categories', ['A+', 'B', 'C'])
                ->where('summary.categories.0.category', 'A+')
            );

        $data = $this->actingAs($admin)
            ->getJson(route('admin.data-entry.rows', ['queue' => 'all']))
            ->assertOk()
            ->json('rows.data');

        $this->assertSame(['A+', 'B', 'C'], array_column($data, 'category'));

        $data = $this->actingAs($admin)
            ->getJson(route('admin.data-entry.rows', ['queue' => 'all', 'category' => 'B']))
            ->assertOk()
            ->json('rows.data');

        $this->assertCount(1, $data);
        $this->assertSame('B', $data[0]['category']);
        $this->assertSame('10001', $data[0]['school_code']);
    }

    public function test_admin_can_bulk_update_school_rows_and_coordinators(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $school = School::create([
            'external_school_id' => '9207',
            'school_code' => '15292',
            'name' => 'Nashik Cambridge School',
            'address' => 'Wadala Road',
            'state' => 'Maharashtra',
            'city' => 'Nashik',
            'pin_code' => '422009',
            'is_active' => true,
            'is_managed' => true,
        ]);

        $response = $this->actingAs($admin)->patchJson(route('admin.data-entry.rows.update'), [
            'rows' => [[
                'id' => $school->id,
                'category' => 'b',
                'name' => 'Nashik Cambridge School',
                'address' => 'Indra Nagar, Wadala Road',
                'state' => 'Maharashtra',
                'district' => 'Nashik',
                'city' => 'Nashik',
                'pin_code' => '422009',
                'email' => 'office@nashik.test',
                'mobile' => '+91 9876543210',
                'head_phone' => '9876543211',
                'is_active' => true,
                'coordinators' => [
                    ['name' => 'Anita Sharma', 'designation' => 'Olympiad Coordinator', 'phone' => '9876543212', 'email' => 'anita@nashik.test'],
                    ['name' => 'Rahul Verma', 'designation' => 'Academic Head', 'phone' => '9876543213', 'email' => 'rahul@nashik.test'],
                    ['name' => 'Meera Iyer', 'designation' => 'Principal', 'phone' => '9876543214', 'email' => 'meera@nashik.test'],
                ],
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('rows.0.category', 'B')
            ->assertJsonPath('rows.0.district', 'Nashik')
            ->assertJsonPath('rows.0.coordinators.0.name', 'Anita Sharma')
            ->assertJsonPath('rows.0.coordinators.2.name', 'Meera Iyer');

        $school->refresh();
        $this->assertSame('B', $school->category);
        $this->assertSame('Nashik', $school->district);
        $this->assertSame('office@nashik.test', $school->email);
        $this->assertSame(3, $school->coordinators()->count());
        $this->assertDatabaseHas('school_coordinators', [
            'school_id' => $school->id,
            'name' => 'Anita Sharma',
            'designation' => 'Olympiad Coordinator',
        ]);
        $this->assertDatabaseHas('school_coordinators', [
            'school_id' => $school->id,
            'name' => 'Meera Iyer',
            'designation' => 'Principal',
        ]);
    }

    public function test_data_entry_validates_cells_per_row(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $school = School::create([
            'school_code' => '10949',
            'name' => 'Delhi Public School Bangalore South',
            'state' => 'Karnataka',
            'is_active' => true,
            'is_managed' => true,
        ]);

        $this->actingAs($admin)->patchJson(route('admin.data-entry.rows.update'), [
            'rows' => [[
                'id' => $school->id,
                'name' => '',
                'state' => 'Karnataka',
                'pin_code' => '123',
                'email' => 'not-an-email',
                'mobile' => 'abc',
                'is_active' => true,
                'coordinators' => [
                    ['name' => '', 'phone' => '9876543212'],
                ],
            ]],
        ])->assertJsonValidationErrors([
            'rows.0.name',
            'rows.0.pin_code',
            'rows.0.email',
            'rows.0.mobile',
            'rows.0.coordinators.0.name',
        ]);
    }

    public function test_school_visit_category_sync_backfills_existing_rows_and_removes_source_samples(): void
    {
        School::create([
            'external_school_id' => '22709',
            'school_code' => '72282',
            'name' => 'DAV Sushil Kedia Vishwa Bharti Higher Secondary School',
            'state' => 'Kathmandu',
            'is_active' => true,
            'is_managed' => true,
        ]);
        School::create([
            'external_school_id' => '1',
            'school_code' => '10000',
            'name' => 'Sample School teste',
            'state' => 'Delhi',
            'is_active' => true,
            'is_managed' => true,
        ]);
        School::create([
            'external_school_id' => '28442',
            'school_code' => '69092',
            'name' => 'VIBGYOR HIGH',
            'address' => 'NEAR POWER GRID AND RTO OFFICE DRIVING TEST TRACK',
            'state' => 'Karnataka',
            'is_active' => true,
            'is_managed' => true,
        ]);
        School::create([
            'external_school_id' => '47573',
            'school_code' => '10154',
            'name' => 'Real Name In Database',
            'state' => 'Delhi',
            'is_active' => false,
            'is_managed' => true,
        ]);
        School::create([
            'school_code' => 'SCH-DEMO-001',
            'name' => 'Demo National Public School',
            'state' => 'Delhi',
            'is_active' => true,
            'is_managed' => true,
        ]);

        $source = tempnam(sys_get_temp_dir(), 'school-visit-').'.jsonl';
        file_put_contents($source, implode("\n", [
            json_encode(['external_school_id' => '22709', 'school_code' => '72282', 'name' => 'DAV SUSHIL KEDIA VISHWA BHARTI HIGHER SECONDARY SCHOOL', 'state' => 'Kathmandu', 'category' => 'a+']),
            json_encode(['external_school_id' => '1', 'school_code' => '10000', 'name' => 'Sample School teste', 'state' => 'Delhi', 'category' => 'c']),
            json_encode(['external_school_id' => '28442', 'school_code' => '69092', 'name' => 'VIBGYOR HIGH', 'address' => 'NEAR POWER GRID AND RTO OFFICE DRIVING TEST TRACK', 'state' => 'Karnataka', 'category' => 'UE']),
            json_encode(['external_school_id' => '47573', 'school_code' => '10154', 'name' => 'Test 1 school', 'state' => 'Delhi', 'category' => 'UE', 'blacklisted_remarks' => 'Test School']),
        ])."\n");

        $this->artisan('schools:sync-visit-categories', ['--source' => $source])
            ->assertExitCode(0);

        $this->assertDatabaseHas('schools', [
            'school_code' => '72282',
            'category' => 'A+',
        ]);
        $this->assertDatabaseHas('schools', [
            'school_code' => '69092',
            'category' => 'UE',
        ]);
        $this->assertDatabaseMissing('schools', ['school_code' => '10000']);
        $this->assertDatabaseMissing('schools', ['school_code' => '10154']);
        $this->assertDatabaseMissing('schools', ['school_code' => 'SCH-DEMO-001']);
    }
}
