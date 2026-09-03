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
                ->has('schoolDesignations')
                ->has('queues')
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
                    ['name' => '', 'designation' => '', 'phone' => '', 'email' => ''],
                ],
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('rows.0.district', 'Nashik')
            ->assertJsonPath('rows.0.coordinators.0.name', 'Anita Sharma');

        $school->refresh();
        $this->assertSame('Nashik', $school->district);
        $this->assertSame('office@nashik.test', $school->email);
        $this->assertSame(1, $school->coordinators()->count());
        $this->assertDatabaseHas('school_coordinators', [
            'school_id' => $school->id,
            'name' => 'Anita Sharma',
            'designation' => 'Olympiad Coordinator',
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
}
