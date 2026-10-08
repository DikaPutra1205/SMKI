<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_pic_parent_sees_own_subtree_ids(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $parent->id]);

        $this->assertEqualsCanonicalizing([$parent->id, $child->id], $pic->accessibleUnitIds());
    }

    public function test_superadmin_returns_null_scope(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->assertNull($admin->accessibleUnitIds());
    }

    // ── US-G2: scope is subtree-bound, never upward or sideways ─────────────

    public function test_child_pic_cannot_see_parent_sibling_or_grandparent(): void
    {
        $root = WorkUnit::create(['nama' => 'Root']);
        $branchA = WorkUnit::create(['nama' => 'Branch A', 'parent_id' => $root->id]);
        $branchB = WorkUnit::create(['nama' => 'Branch B', 'parent_id' => $root->id]);
        $leafA = WorkUnit::create(['nama' => 'Leaf A', 'parent_id' => $branchA->id]);

        $picA = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $branchA->id]);
        $picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $branchB->id]);

        // Parent-unit PIC supervises downwards only.
        $this->assertEqualsCanonicalizing([$branchA->id, $leafA->id], $picA->accessibleUnitIds());
        $this->assertEqualsCanonicalizing([$branchB->id], $picB->accessibleUnitIds());

        // Isolation in both directions: no upward, no sideways.
        $this->assertNotContains($root->id, $picA->accessibleUnitIds());
        $this->assertNotContains($branchB->id, $picA->accessibleUnitIds());
        $this->assertNotContains($branchA->id, $picB->accessibleUnitIds());
        $this->assertNotContains($leafA->id, $picB->accessibleUnitIds());
    }

    public function test_pic_scope_collects_deep_descendants(): void
    {
        $l1 = WorkUnit::create(['nama' => 'L1']);
        $l2 = WorkUnit::create(['nama' => 'L2', 'parent_id' => $l1->id]);
        $l3 = WorkUnit::create(['nama' => 'L3', 'parent_id' => $l2->id]);
        $l4 = WorkUnit::create(['nama' => 'L4', 'parent_id' => $l3->id]);

        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $l1->id]);

        $ids = $pic->accessibleUnitIds();
        $this->assertEqualsCanonicalizing([$l1->id, $l2->id, $l3->id, $l4->id], $ids);
        $this->assertSame($ids, array_values($ids), 'scope must be a re-indexed list');
    }

    public function test_leaf_pic_scope_is_exactly_own_unit(): void
    {
        $leaf = WorkUnit::create(['nama' => 'Leaf']);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $leaf->id]);

        $this->assertSame([$leaf->id], $pic->accessibleUnitIds());
    }

    public function test_soft_deleted_descendants_leave_the_pic_scope(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $parent->id]);
        $this->assertContains($child->id, $pic->accessibleUnitIds());

        $child->delete();

        // Fresh model + fresh unit relation == what a new request sees.
        $reloaded = User::find($pic->id);
        $reloaded->setRelation('unit', WorkUnit::find($parent->id));

        $this->assertSame([$parent->id], $reloaded->accessibleUnitIds());
    }

    public function test_non_pic_roles_are_global_even_when_they_hold_a_unit(): void
    {
        $unit = WorkUnit::create(['nama' => 'Unit']);

        foreach ([
            User::ROLE_ADMIN_KEPATUHAN,
            User::ROLE_KOORDINATOR_SMKI,
            User::ROLE_AUDITOR,
        ] as $role) {
            $user = User::factory()->create(['role' => $role, 'unit_id' => $unit->id]);
            $this->assertNull($user->accessibleUnitIds(), "{$role} must not be unit-scoped");
        }
    }

    /**
     * A PIC with no unit_id falls into the global branch (User.php:188). This is
     * reachable because StoreUserRequest allows `unit_id => null`, so the seam
     * is documented here: a unit-less PIC sees every unit's data.
     */
    public function test_pic_without_unit_gets_global_scope(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => null]);

        $this->assertNull($pic->accessibleUnitIds());
    }

    // ── US-G1: hasPermissionTo() branches ─────────────────────────────────

    public function test_user_without_role_has_no_permission_keys(): void
    {
        $user = User::factory()->create(['role' => 'pic']);
        $user->role_id = null;

        $this->assertSame([], $user->cachedPermissionKeys());
        $this->assertFalse($user->hasPermissionTo('dashboard.read'));
    }

    public function test_has_permission_to_reflects_the_config_matrix(): void
    {
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);

        $everyKey = collect(config('permissions.permissions'))->flatten()->all();

        foreach ($everyKey as $key) {
            $this->assertTrue($superadmin->hasPermissionTo($key), "superadmin missing {$key}");
            $this->assertSame(
                in_array($key, config('permissions.roles.pic'), true),
                $pic->hasPermissionTo($key),
                "pic grant for {$key} drifted from config"
            );
        }
    }

    /**
     * The sibling test above only ever compared `pic` against config; the other
     * three operational roles were never compared key-by-key at the User-model
     * layer, so a grant added to config but not seeded (or seeded but not in
     * config) would pass the whole suite for admin_kepatuhan, koordinator_smki
     * and auditor.
     */
    public static function roleNames(): array
    {
        return [
            'superadmin' => [User::ROLE_SUPERADMIN],
            'admin_kepatuhan' => [User::ROLE_ADMIN_KEPATUHAN],
            'koordinator_smki' => [User::ROLE_KOORDINATOR_SMKI],
            'auditor' => [User::ROLE_AUDITOR],
            'pic' => [User::ROLE_PIC],
        ];
    }

    #[DataProvider('roleNames')]
    public function test_has_permission_to_matches_the_config_matrix_for_every_role(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $granted = config("permissions.roles.{$role}");

        $this->assertSame(
            $this->sorted($granted),
            $this->sorted($user->cachedPermissionKeys()),
            "effective grants for {$role} drifted from config/permissions.php"
        );

        foreach (collect(config('permissions.permissions'))->flatten() as $key) {
            $this->assertSame(
                in_array($key, $granted, true),
                $user->hasPermissionTo($key),
                "hasPermissionTo('{$key}') wrong for {$role}"
            );
        }
    }

    /**
     * US-G3 restated as an executable invariant: only superadmin holds the
     * master-identity write grants. Controls/Frameworks are deliberately
     * excluded — the spec's own deviation note (config/permissions.php:186)
     * grants them to admin_kepatuhan too.
     */
    #[DataProvider('roleNames')]
    public function test_master_identity_write_grants_are_superadmin_only(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $expected = $role === User::ROLE_SUPERADMIN;

        foreach ([
            'user.create', 'user.update', 'user.delete',
            'work-unit.create', 'work-unit.update', 'work-unit.delete',
            'role.create', 'role.update', 'role.delete',
        ] as $key) {
            $this->assertSame($expected, $user->hasPermissionTo($key), "{$role} / {$key}");
        }
    }

    #[DataProvider('roleNames')]
    public function test_master_data_write_grants_follow_the_superadmin_plus_kepatuhan_deviation(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $expected = in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN_KEPATUHAN], true);

        foreach ([
            'framework.create', 'framework.update', 'framework.delete',
            'control.create', 'control.update', 'control.delete',
        ] as $key) {
            $this->assertSame($expected, $user->hasPermissionTo($key), "{$role} / {$key}");
        }
    }

    private function sorted(array $keys): array
    {
        sort($keys);

        return array_values($keys);
    }

    public function test_has_permission_to_is_false_for_unknown_key(): void
    {
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->assertFalse($superadmin->hasPermissionTo('no.such.permission'));
    }

    public function test_permission_keys_are_rebuilt_after_a_grant_change(): void
    {
        $role = Role::where('name', 'pic')->first();
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->assertFalse($user->hasPermissionTo('role.create'));

        $role->permissions()->sync(
            Permission::where('key', 'role.create')->pluck('id')
        );
        Role::flushPermissionsCache($role->id);

        $this->assertTrue($user->fresh()->hasPermissionTo('role.create'));
    }

    public function test_role_mutator_rejects_unknown_role(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        User::factory()->make(['role' => 'ghost_admin']);
    }

    #[DataProvider('roleNames')]
    public function test_role_mutator_accepts_every_known_role(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->assertSame($role, $user->fresh()->role);
    }
}
