<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\School;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_roles_with_module_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $payload = [
            'name' => 'Data Entry Operator',
            'description' => 'Can maintain imported school rows.',
            'is_active' => true,
            'permissions' => array_replace_recursive(AdminPermissions::emptyMatrix(), [
                'data_entry' => ['read' => true, 'write' => true, 'delete' => false],
            ]),
        ];

        $this->actingAs($admin)
            ->post(route('admin.roles.store'), $payload)
            ->assertRedirect();

        $role = AdminRole::where('slug', 'data-entry-operator')->firstOrFail();

        $this->assertTrue($role->permissionMatrix()['data_entry']['read']);
        $this->assertTrue($role->permissionMatrix()['data_entry']['write']);
        $this->assertFalse($role->permissionMatrix()['students']['read']);
    }

    public function test_restricted_admin_only_sees_and_accesses_allowed_modules(): void
    {
        $role = $this->roleWith([
            'data_entry' => ['read' => true, 'write' => true, 'delete' => false],
        ]);
        $staff = User::factory()->create(['role' => 'admin', 'admin_role_id' => $role->id]);

        $this->actingAs($staff)
            ->get(route('admin.data-entry.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/DataEntry/Index')
                ->where('admin_permissions.data_entry.read', true)
                ->where('admin_permissions.data_entry.write', true)
                ->where('admin_permissions.students.read', false)
                ->where('admin_support_unread', 0)
            );

        $this->actingAs($staff)
            ->get(route('admin.users.index'))
            ->assertRedirect(route('admin.data-entry.index'));

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.data-entry.index'));
    }

    public function test_read_only_permission_blocks_write_endpoints(): void
    {
        $role = $this->roleWith([
            'data_entry' => ['read' => true, 'write' => false, 'delete' => false],
        ]);
        $staff = User::factory()->create(['role' => 'admin', 'admin_role_id' => $role->id]);

        $this->actingAs($staff)
            ->getJson(route('admin.data-entry.rows'))
            ->assertOk();

        $this->actingAs($staff)
            ->patchJson(route('admin.data-entry.rows.update'), ['rows' => []])
            ->assertForbidden();
    }

    public function test_staff_users_can_be_created_with_dynamic_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = $this->roleWith([
            'data_entry' => ['read' => true, 'write' => true, 'delete' => false],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.staff-users.store'), [
                'name' => 'Operator One',
                'email' => 'operator@example.test',
                'admin_role_id' => $role->id,
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'is_active' => true,
            ])
            ->assertRedirect();

        $operator = User::where('email', 'operator@example.test')->firstOrFail();

        $this->assertSame('admin', $operator->role);
        $this->assertSame($role->id, $operator->admin_role_id);
        $this->assertTrue(Hash::check('Password@123', $operator->password));

        $this->post(route('admin.login.submit'), [
            'email' => 'operator@example.test',
            'password' => 'Password@123',
        ])->assertRedirect(route('admin.data-entry.index'));
    }

    public function test_restricted_writer_can_save_allowed_data_entry_rows(): void
    {
        $role = $this->roleWith([
            'data_entry' => ['read' => true, 'write' => true, 'delete' => false],
        ]);
        $staff = User::factory()->create(['role' => 'admin', 'admin_role_id' => $role->id]);
        $school = School::create([
            'school_code' => 'RBAC100',
            'name' => 'RBAC Test School',
            'state' => 'Delhi',
            'is_active' => true,
            'is_managed' => true,
        ]);

        $this->actingAs($staff)->patchJson(route('admin.data-entry.rows.update'), [
            'rows' => [[
                'id' => $school->id,
                'name' => 'RBAC Test School',
                'address' => 'Main Road',
                'state' => 'Delhi',
                'district' => 'New Delhi',
                'city' => 'New Delhi',
                'pin_code' => '110001',
                'email' => 'rbac@example.test',
                'mobile' => '9876543210',
                'head_phone' => '9876543211',
                'is_active' => true,
                'coordinators' => [],
            ]],
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('schools', [
            'id' => $school->id,
            'district' => 'New Delhi',
            'email' => 'rbac@example.test',
        ]);
    }

    private function roleWith(array $permissions): AdminRole
    {
        $role = AdminRole::create([
            'name' => 'Role '.str()->random(8),
            'is_active' => true,
        ]);

        foreach (AdminPermissions::normalizeMatrix($permissions) as $module => $actions) {
            $role->permissions()->create([
                'module' => $module,
                'can_read' => $actions['read'],
                'can_write' => $actions['write'],
                'can_delete' => $actions['delete'],
            ]);
        }

        return $role->load('permissions');
    }
}
