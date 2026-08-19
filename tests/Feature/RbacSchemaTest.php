<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class RbacSchemaTest extends TestCase
{
    public function test_role_has_unique_name_and_permissions_relation(): void
    {
        $role = Role::create(['name' => 'superadmin', 'label' => 'Super Admin']);
        $perm = Permission::create(['key' => 'dashboard.read', 'module' => 'dashboard']);
        $role->permissions()->attach($perm);

        $this->assertTrue($role->permissions()->where('key', 'dashboard.read')->exists());

        $this->expectException(QueryException::class);
        Role::create(['name' => 'superadmin']);
    }

    public function test_permission_has_unique_key_and_roles_relation(): void
    {
        $permission = Permission::create(['key' => 'checklist.verify', 'module' => 'checklist']);
        $role = Role::create(['name' => 'admin_kepatuhan', 'label' => 'Admin Kepatuhan']);

        $this->assertTrue($permission->roles()->count() === 0);
        $permission->roles()->attach($role);
        $this->assertTrue($permission->roles()->where('name', 'admin_kepatuhan')->exists());

        $this->expectException(QueryException::class);
        Permission::create(['key' => 'checklist.verify', 'module' => 'checklist']);
    }

    public function test_user_role_relation_reads_name_string(): void
    {
        $role = Role::create(['name' => 'pic', 'label' => 'PIC']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertSame('pic', $user->role);
    }
}
