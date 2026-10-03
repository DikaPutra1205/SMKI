<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_superadmin_can_list_users(): void
    {
        $this->actingAs($this->superadmin())
            ->get('/admin/superadmin/users')
            ->assertOk();
    }

    public function test_superadmin_can_create_user(): void
    {
        $role = Role::where('name', 'pic')->first();

        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/users', [
                'name' => 'New User',
                'email' => 'new@example.com',
                'role_id' => $role->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_superadmin_can_update_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->superadmin())
            ->patch("/admin/superadmin/users/{$user->id}", [
                'name' => 'Updated Name',
                'email' => $user->email,
                'role_id' => $user->role_id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name']);
    }

    public function test_superadmin_can_delete_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->superadmin())
            ->delete("/admin/superadmin/users/{$user->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    // ── Authorization: partial permissions ────────────────────────────────

    public function test_user_with_managementview_but_not_create_can_list_but_not_store(): void
    {
        $user = $this->userWithPermission('user.managementview', 'user.read');

        $this->actingAs($user)
            ->get('/admin/superadmin/users')
            ->assertOk();

        $role = Role::where('name', 'pic')->first();
        $this->actingAs($user)
            ->post('/admin/superadmin/users', [
                'name' => 'Nope',
                'email' => 'nope@example.com',
                'role_id' => $role->id,
            ])
            ->assertForbidden();
    }

    public function test_user_with_managementview_can_list_but_not_update_or_delete(): void
    {
        $user = $this->userWithPermission('user.managementview', 'user.read');
        $target = User::factory()->create();

        $this->actingAs($user)
            ->patch("/admin/superadmin/users/{$target->id}", [
                'name' => 'Hacked',
                'email' => $target->email,
                'role_id' => $target->role_id,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete("/admin/superadmin/users/{$target->id}")
            ->assertForbidden();
    }

    // ── Authorization: no user.* grants ───────────────────────────────────

    public function test_user_without_user_permissions_gets_403_on_index(): void
    {
        $role = Role::create(['name' => 'no_user_perm', 'label' => 'No User']);
        // sync only a non-user permission
        $perm = Permission::where('key', 'dashboard.read')->first();
        $role->permissions()->sync([$perm->id]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->get('/admin/superadmin/users')
            ->assertForbidden();
    }

    // ── Validation ────────────────────────────────────────────────────────

    public function test_store_validation_fails_with_missing_name(): void
    {
        $role = Role::where('name', 'pic')->first();

        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/users', [
                'email' => 'test@example.com',
                'role_id' => $role->id,
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_store_validation_fails_with_missing_email(): void
    {
        $role = Role::where('name', 'pic')->first();

        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/users', [
                'name' => 'Test',
                'role_id' => $role->id,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_store_validation_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);
        $role = Role::where('name', 'pic')->first();

        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/users', [
                'name' => 'Dup',
                'email' => 'dup@example.com',
                'role_id' => $role->id,
            ])
            ->assertSessionHasErrors('email');
    }

    // ── Self-delete guard ────────────────────────────────────────────────

    public function test_superadmin_cannot_delete_own_account(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->delete("/admin/superadmin/users/{$admin->id}")
            ->assertStatus(422);
    }

    // ── Password not required ────────────────────────────────────────────

    public function test_store_works_without_password_field(): void
    {
        $role = Role::where('name', 'pic')->first();

        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/users', [
                'name' => 'No Password',
                'email' => 'nopass@example.com',
                'role_id' => $role->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'nopass@example.com']);
    }

    // ── US-G3: real-role matrix ───────────────────────────────────────────

    public static function nonSuperAdminRoles(): array
    {
        return [
            'admin_kepatuhan' => [User::ROLE_ADMIN_KEPATUHAN],
            'pic' => [User::ROLE_PIC],
        ];
    }

    #[DataProvider('nonSuperAdminRoles')]
    public function test_non_superadmin_cannot_view_users_page(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get('/admin/superadmin/users')
            ->assertForbidden();
    }

    #[DataProvider('nonSuperAdminRoles')]
    public function test_non_superadmin_cannot_create_update_or_delete_user(string $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $picRole = Role::where('name', 'pic')->first();
        $target = User::factory()->create(['role_id' => $picRole->id]);

        $this->actingAs($actor)->post('/admin/superadmin/users', [
            'name' => 'Injected',
            'email' => 'injected@example.com',
            'role_id' => $picRole->id,
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'injected@example.com']);

        $this->actingAs($actor)->patch("/admin/superadmin/users/{$target->id}", [
            'name' => 'Hijacked',
            'email' => $target->email,
            'role_id' => $target->role_id,
        ])->assertForbidden();

        $this->actingAs($actor)->delete("/admin/superadmin/users/{$target->id}")->assertForbidden();

        $target->refresh();
        $this->assertNotSame('Hijacked', $target->name);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    /**
     * koordinator_smki and auditor hold `user.managementview` (config/permissions.php:260,282)
     * so they can open the master user list, but hold no user.create/update/delete
     * grant — writes must still be denied.
     */
    #[DataProvider('readOnlyUserManagerRoles')]
    public function test_read_only_user_managers_cannot_write(string $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $picRole = Role::where('name', 'pic')->first();
        $target = User::factory()->create(['role_id' => $picRole->id]);

        $this->actingAs($actor)->get('/admin/superadmin/users')->assertOk();

        $this->actingAs($actor)->post('/admin/superadmin/users', [
            'name' => 'Nope',
            'email' => 'nope@example.com',
            'role_id' => $picRole->id,
        ])->assertForbidden();

        $this->actingAs($actor)->patch("/admin/superadmin/users/{$target->id}", [
            'name' => 'Nope',
            'email' => $target->email,
            'role_id' => $target->role_id,
        ])->assertForbidden();

        $this->actingAs($actor)->delete("/admin/superadmin/users/{$target->id}")->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'nope@example.com']);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public static function readOnlyUserManagerRoles(): array
    {
        return [
            'koordinator' => [User::ROLE_KOORDINATOR_SMKI],
            'auditor' => [User::ROLE_AUDITOR],
        ];
    }

    public function test_user_store_requires_a_role_id(): void
    {
        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/users', [
                'name' => 'No Role',
                'email' => 'norole@example.com',
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'norole@example.com']);
    }

    public function test_superadmin_gets_404_for_unknown_user(): void
    {
        $this->actingAs($this->superadmin())
            ->patch('/admin/superadmin/users/999999', [
                'name' => 'Ghost',
                'email' => 'ghost@example.com',
                'role_id' => Role::where('name', 'pic')->first()->id,
            ])
            ->assertNotFound();
    }

    /**
     * US-G1: no grant can be evaluated for a guest, so every user-management
     * verb must bounce to /login and leave the table untouched. Only the GET
     * and one POST were covered (MasterDataScopeTest:127) — PATCH/DELETE and the
     * flat /users page were not.
     */
    public function test_anonymous_is_redirected_from_every_user_route_and_no_row_is_written(): void
    {
        $target = User::factory()->create();

        foreach (['/admin/superadmin/users', '/users'] as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }

        $this->post('/admin/superadmin/users', [
            'name' => 'Anon', 'email' => 'anon@example.com', 'role_id' => 1,
        ])->assertRedirect(route('login'));

        $this->patch("/admin/superadmin/users/{$target->id}", ['name' => 'Anon'])->assertRedirect(route('login'));
        $this->delete("/admin/superadmin/users/{$target->id}")->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'anon@example.com']);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    // ── Update-path validation (store counterparts already covered above) ──

    public function test_update_rejects_email_taken_by_another_user(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $target = User::factory()->create(['email' => 'target@example.com']);

        $this->actingAs($this->superadmin())
            ->patch("/admin/superadmin/users/{$target->id}", [
                'name' => 'Rename',
                'email' => 'taken@example.com',
                'role_id' => $target->role_id,
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'email' => 'target@example.com',
            'name' => $target->name,
        ]);
    }

    public function test_update_allows_keeping_its_own_email(): void
    {
        $target = User::factory()->create(['email' => 'self@example.com']);

        $this->actingAs($this->superadmin())
            ->from('/admin/superadmin/users')
            ->patch("/admin/superadmin/users/{$target->id}", [
                'name' => 'Renamed',
                'email' => 'self@example.com',
                'role_id' => $target->role_id,
            ])
            ->assertRedirect('/admin/superadmin/users')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $target->id, 'name' => 'Renamed', 'email' => 'self@example.com',
        ]);
    }

    public function test_store_rejects_unknown_role_id_and_unknown_unit_id(): void
    {
        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/users', [
                'name' => 'Bad FK',
                'email' => 'badfk@example.com',
                'role_id' => 999999,
                'unit_id' => 999999,
            ])
            ->assertSessionHasErrors(['role_id', 'unit_id']);

        $this->assertDatabaseMissing('users', ['email' => 'badfk@example.com']);
    }

    /**
     * Counterpart to MasterDataScopeTest::test_gap_new_user_gets_known_default_password:
     * when the caller DOES send a password it is honoured, so that defect is the
     * silent fallback plus the missing form field — not password handling itself.
     */
    public function test_store_honours_an_admin_supplied_password(): void
    {
        $role = Role::where('name', 'pic')->firstOrFail();

        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/users', [
                'name' => 'Pw User',
                'email' => 'pwuser@example.com',
                'role_id' => $role->id,
                'password' => 'RahasiaKuat123',
            ])
            ->assertRedirect();

        $created = User::where('email', 'pwuser@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('RahasiaKuat123', $created->getAuthPassword()));
    }

    /** US-M3: assigning a unit on update is the other half of the PIC-claim rule. */
    public function test_assigning_a_unit_to_an_existing_pic_claims_that_units_entries(): void
    {
        $unit = WorkUnit::factory()->create();
        $session = ChecklistSession::factory()->create(['unit_id' => $unit->id]);
        ChecklistEntry::factory()->count(2)->create([
            'session_id' => $session->id,
            'unit_id' => $unit->id,
            'pic_id' => null,
        ]);

        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => null]);

        $this->actingAs($this->superadmin())
            ->patch("/admin/superadmin/users/{$pic->id}", [
                'name' => $pic->name,
                'email' => $pic->email,
                'role_id' => $pic->role_id,
                'unit_id' => $unit->id,
            ])
            ->assertRedirect();

        $this->assertSame($unit->id, $pic->fresh()->unit_id);
        $this->assertSame(2, ChecklistEntry::where('unit_id', $unit->id)->where('pic_id', $pic->id)->count());
    }
}
