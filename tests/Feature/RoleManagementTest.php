<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    private function superadmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
    }

    private function userWithPermission(string ...$keys): User
    {
        $role = Role::create(['name' => 'test_role_'.Str::random(5), 'label' => 'Test']);
        $role->permissions()->sync(
            Permission::whereIn('key', $keys)->pluck('id')
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    // ── Happy path: superadmin CRUD ───────────────────────────────────────

    public function test_superadmin_can_list_roles(): void
    {
        $this->actingAs($this->superadmin())
            ->get('/admin/superadmin/roles')
            ->assertOk();
    }

    public function test_superadmin_can_create_role(): void
    {
        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/roles', [
                'name' => 'new_role',
                'label' => 'New Role',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('roles', ['name' => 'new_role']);
    }

    public function test_superadmin_can_update_role(): void
    {
        $role = Role::where('name', 'pic')->first();

        $this->actingAs($this->superadmin())
            ->patch("/admin/superadmin/roles/{$role->id}", [
                'label' => 'Updated PIC',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'label' => 'Updated PIC']);
    }

    public function test_superadmin_can_delete_role_with_no_users(): void
    {
        $role = Role::create(['name' => 'deleteme', 'label' => 'Delete Me']);

        $this->actingAs($this->superadmin())
            ->delete("/admin/superadmin/roles/{$role->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    // ── Authorization: no role.* grants ───────────────────────────────────

    public function test_user_without_role_permissions_gets_403_on_index(): void
    {
        $role = Role::create(['name' => 'no_role_perm', 'label' => 'No Role']);
        $perm = Permission::where('key', 'dashboard.read')->first();
        $role->permissions()->sync([$perm->id]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->get('/admin/superadmin/roles')
            ->assertForbidden();
    }

    // ── Grants + role_permission rows + cache flush ───────────────────────

    public function test_create_role_persists_grants_and_permission_rows(): void
    {
        $permKeys = ['dashboard.read', 'checklist.view'];
        $permIds = Permission::whereIn('key', $permKeys)->pluck('id')->toArray();

        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/roles', [
                'name' => 'granted_role',
                'label' => 'Granted',
                'permissions' => $permKeys,
            ])
            ->assertRedirect();

        $role = Role::where('name', 'granted_role')->first();
        $this->assertNotNull($role);
        $this->assertCount(2, $role->permissions);
        $this->assertContains($permIds[0], $role->permissions->pluck('id')->toArray());
    }

    public function test_update_role_syncs_permissions_and_flushes_cache(): void
    {
        $role = Role::create(['name' => 'cache_role', 'label' => 'Cache']);
        $cacheKey = Role::permissionsCacheKey($role->id);

        Cache::put($cacheKey, ['old'], now()->addMinute());
        $this->assertTrue(Cache::has($cacheKey));

        $newPermKeys = ['dashboard.read', 'risk.read'];

        $this->actingAs($this->superadmin())
            ->patch("/admin/superadmin/roles/{$role->id}", [
                'label' => 'Cache Updated',
                'permissions' => $newPermKeys,
            ])
            ->assertRedirect();

        $this->assertFalse(Cache::has($cacheKey));

        $role->refresh();
        $this->assertSame(['dashboard.read', 'risk.read'], $role->permissions->pluck('key')->sort()->values()->all());
    }

    public function test_create_role_flushes_cache(): void
    {
        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/roles', [
                'name' => 'flush_test',
                'label' => 'Flush',
            ])
            ->assertRedirect();

        $role = Role::where('name', 'flush_test')->first();
        $this->assertFalse(Cache::has(Role::permissionsCacheKey($role->id)));
    }

    // ── Delete role with users → 422 ─────────────────────────────────────

    public function test_delete_role_with_assigned_users_fails(): void
    {
        $role = Role::where('name', 'pic')->first();
        User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($this->superadmin())
            ->delete("/admin/superadmin/roles/{$role->id}")
            ->assertStatus(422);
    }

    // ── Validation ────────────────────────────────────────────────────────

    public function test_store_validation_fails_with_duplicate_name(): void
    {
        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/roles', [
                'name' => 'superadmin',
                'label' => 'Dup',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_store_validation_fails_with_invalid_name_format(): void
    {
        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/roles', [
                'name' => 'Invalid Name!',
                'label' => 'Bad',
            ])
            ->assertSessionHasErrors('name');
    }

    // ── Permissions sync correctness ─────────────────────────────────────

    public function test_update_role_adds_new_permission_correctly(): void
    {
        $role = Role::create(['name' => 'sync_role', 'label' => 'Sync']);

        $this->actingAs($this->superadmin())
            ->patch("/admin/superadmin/roles/{$role->id}", [
                'label' => 'Sync',
                'permissions' => ['dashboard.read'],
            ])
            ->assertRedirect();

        $role->refresh();
        $this->assertTrue($role->permissions->contains('key', 'dashboard.read'));

        $this->actingAs($this->superadmin())
            ->patch("/admin/superadmin/roles/{$role->id}", [
                'label' => 'Sync',
                'permissions' => ['dashboard.read', 'risk.read'],
            ])
            ->assertRedirect();

        $role->refresh();
        $this->assertCount(2, $role->permissions);
        $this->assertTrue($role->permissions->contains('key', 'risk.read'));
    }

    // ── US-G3: role CRUD is superadmin-only ───────────────────────────────

    public static function nonSuperAdminRoles(): array
    {
        return [
            'admin_kepatuhan' => [User::ROLE_ADMIN_KEPATUHAN],
            'koordinator' => [User::ROLE_KOORDINATOR_SMKI],
            'auditor' => [User::ROLE_AUDITOR],
            'pic' => [User::ROLE_PIC],
        ];
    }

    #[DataProvider('nonSuperAdminRoles')]
    public function test_non_superadmin_cannot_view_roles_page(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get('/admin/superadmin/roles')
            ->assertForbidden();
    }

    #[DataProvider('nonSuperAdminRoles')]
    public function test_non_superadmin_cannot_create_role(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->post('/admin/superadmin/roles', ['name' => 'hijack_'.$role, 'label' => 'Hijack'])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'hijack_'.$role]);
    }

    #[DataProvider('nonSuperAdminRoles')]
    public function test_non_superadmin_cannot_update_or_delete_role(string $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $target = Role::where('name', 'pic')->first();

        $this->actingAs($actor)
            ->patch("/admin/superadmin/roles/{$target->id}", ['label' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAs($actor)
            ->delete("/admin/superadmin/roles/{$target->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $target->id, 'label' => 'PIC']);
    }

    public function test_role_with_managementview_only_cannot_write(): void
    {
        $user = $this->userWithPermission('role.managementview');

        $this->actingAs($user)->get('/admin/superadmin/roles')->assertOk();

        $this->actingAs($user)
            ->post('/admin/superadmin/roles', ['name' => 'sneaky', 'label' => 'Sneaky'])
            ->assertForbidden();

        $target = Role::where('name', 'pic')->first();
        $this->actingAs($user)
            ->patch("/admin/superadmin/roles/{$target->id}", ['label' => 'Sneaky'])
            ->assertForbidden();
        $this->actingAs($user)
            ->delete("/admin/superadmin/roles/{$target->id}")
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'sneaky']);
        $this->assertDatabaseHas('roles', ['id' => $target->id, 'label' => 'PIC']);
    }

    public function test_superadmin_gets_404_for_unknown_role(): void
    {
        $this->actingAs($this->superadmin())
            ->patch('/admin/superadmin/roles/999999', ['label' => 'Ghost'])
            ->assertNotFound();
    }

    /** US-G1: role CRUD had no anonymous-caller coverage at all. */
    public function test_anonymous_is_redirected_from_every_role_route_and_no_row_is_written(): void
    {
        $target = Role::where('name', 'pic')->firstOrFail();

        foreach (['/admin/superadmin/roles', '/roles'] as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }

        $this->post('/admin/superadmin/roles', ['name' => 'anon_role', 'label' => 'Anon'])
            ->assertRedirect(route('login'));

        $this->patch("/admin/superadmin/roles/{$target->id}", ['label' => 'Anon'])
            ->assertRedirect(route('login'));

        $this->delete("/admin/superadmin/roles/{$target->id}")
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('roles', ['name' => 'anon_role']);
        $this->assertDatabaseHas('roles', ['id' => $target->id, 'label' => 'PIC']);
    }
}
