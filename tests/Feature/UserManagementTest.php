<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;
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
}
