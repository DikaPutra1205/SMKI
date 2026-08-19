<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class RbacDynamismTest extends TestCase
{
    public function test_granting_a_permission_flips_access_after_cache_flush(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $this->assertFalse($pic->hasPermissionTo('report.export'));

        $reportExport = Permission::where('key', 'report.export')->firstOrFail();
        $pic->role()->first()->permissions()->attach($reportExport);
        Role::flushPermissionsCache($pic->role_id);

        $this->assertTrue($pic->fresh()->hasPermissionTo('report.export'));
    }

    public function test_revoking_a_permission_flips_access_after_cache_flush(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $this->assertTrue($admin->hasPermissionTo('checklist.bulk-verify'));

        $admin->role()->first()->permissions()->detach(
            Permission::where('key', 'checklist.bulk-verify')->firstOrFail()
        );
        Role::flushPermissionsCache($admin->role_id);

        $this->assertFalse($admin->fresh()->hasPermissionTo('checklist.bulk-verify'));
    }

    public function test_deleting_a_permission_cascades_grants(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $perm = Permission::where('key', 'control.import')->firstOrFail();

        $this->assertDatabaseHas('role_permission', [
            'role_id' => $admin->role_id,
            'permission_id' => $perm->id,
        ]);

        $perm->delete();

        $this->assertDatabaseMissing('role_permission', [
            'role_id' => $admin->role_id,
            'permission_id' => $perm->id,
        ]);
        $this->assertFalse($admin->fresh()->hasPermissionTo('control.import'));
    }

    public function test_renaming_a_role_propagates_to_user_accessor(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PIC]);
        $this->assertSame('pic', $user->role);

        $user->role()->first()->update(['name' => 'pic_pertanahan']);

        // Accessor reflects the rename; grants survive because the cache is
        // keyed by role id, not by role name.
        $this->assertSame('pic_pertanahan', $user->fresh()->role);
        $this->assertContains('checklist.view', $user->fresh()->cachedPermissionKeys());
    }

    public function test_permission_check_on_user_without_role_denies(): void
    {
        $user = User::factory()->create(['role_id' => null]);
        $this->assertFalse($user->hasPermissionTo('dashboard.read'));
    }
}
