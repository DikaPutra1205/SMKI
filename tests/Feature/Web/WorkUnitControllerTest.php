<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkUnitControllerTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
    }

    private function userWithPermission(string ...$keys): User
    {
        $role = Role::create(['name' => 'test_role_'.Str::random(5), 'label' => 'Test']);
        $role->permissions()->sync(Permission::whereIn('key', $keys)->pluck('id'));

        return User::factory()->create(['role_id' => $role->id]);
    }

    // ── Happy path: CRUD ────────────────────────────────────────────────

    public function test_superadmin_can_list_units(): void
    {
        WorkUnit::factory()->count(2)->create();

        $this->actingAs($this->superadmin())
            ->get('/admin/superadmin/units')
            ->assertOk();
    }

    public function test_superadmin_can_create_unit(): void
    {
        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/units', ['nama' => 'Biro Humas'])
            ->assertRedirect();

        $this->assertDatabaseHas('work_units', ['nama' => 'Biro Humas']);
    }

    public function test_superadmin_can_create_nested_unit(): void
    {
        $parent = WorkUnit::factory()->create(['nama' => 'Biro Induk']);

        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/units', ['nama' => 'Sub Bagian', 'parent_id' => $parent->id])
            ->assertRedirect();

        $this->assertDatabaseHas('work_units', ['nama' => 'Sub Bagian', 'parent_id' => $parent->id]);
    }

    public function test_superadmin_can_update_unit(): void
    {
        $unit = WorkUnit::factory()->create(['nama' => 'Lama']);

        $this->actingAs($this->superadmin())
            ->patch("/admin/superadmin/units/{$unit->id}", ['nama' => 'Baru'])
            ->assertRedirect();

        $this->assertDatabaseHas('work_units', ['id' => $unit->id, 'nama' => 'Baru']);
    }

    public function test_superadmin_can_delete_unit(): void
    {
        $unit = WorkUnit::factory()->create();

        $this->actingAs($this->superadmin())
            ->delete("/admin/superadmin/units/{$unit->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('work_units', ['id' => $unit->id]);
    }

    // ── Authorization ─────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_index(): void
    {
        $this->get('/admin/superadmin/units')
            ->assertRedirect('/login');
    }

    public function test_user_without_view_permission_gets_403(): void
    {
        $user = $this->userWithPermission('dashboard.read');

        $this->actingAs($user)
            ->get('/admin/superadmin/units')
            ->assertForbidden();
    }

    public function test_user_without_create_permission_gets_403(): void
    {
        $user = $this->userWithPermission('work-unit.view');

        $this->actingAs($user)
            ->post('/admin/superadmin/units', ['nama' => 'X'])
            ->assertForbidden();

        $this->assertDatabaseMissing('work_units', ['nama' => 'X']);
    }

    // ── Business rules ─────────────────────────────────────────────────────

    public function test_update_rejects_self_reference(): void
    {
        $unit = WorkUnit::factory()->create();

        $this->actingAs($this->superadmin())
            ->patch("/admin/superadmin/units/{$unit->id}", ['parent_id' => $unit->id])
            ->assertStatus(422);
    }

    public function test_update_rejects_descendant_as_parent(): void
    {
        $parent = WorkUnit::factory()->create();
        $child = WorkUnit::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($this->superadmin())
            ->patch("/admin/superadmin/units/{$parent->id}", ['parent_id' => $child->id])
            ->assertStatus(422);
    }

    public function test_delete_fails_when_unit_has_children(): void
    {
        $parent = WorkUnit::factory()->create();
        WorkUnit::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($this->superadmin())
            ->delete("/admin/superadmin/units/{$parent->id}")
            ->assertStatus(422);
    }

    public function test_delete_fails_when_unit_has_users(): void
    {
        $unit = WorkUnit::factory()->create();
        User::factory()->create(['unit_id' => $unit->id]);

        $this->actingAs($this->superadmin())
            ->delete("/admin/superadmin/units/{$unit->id}")
            ->assertStatus(422);
    }

    // ── Validation ────────────────────────────────────────────────────────

    public function test_store_requires_nama(): void
    {
        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/units', [])
            ->assertSessionHasErrors('nama');
    }

    public function test_store_rejects_invalid_parent(): void
    {
        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/units', ['nama' => 'X', 'parent_id' => 999999])
            ->assertSessionHasErrors('parent_id');
    }

    // ── Reciprocal parenting rule ─────────────────────────────────────────
    // A unit cannot become the parent of its own parent (A→B ⇒ B↛A), since
    // that forms a 2-cycle. The backend cycle guard rejects it (422) and the
    // page dropdown hides it via the descendantsWithSelf() filter.

    public function test_update_rejects_reciprocal_parenting(): void
    {
        $a = WorkUnit::factory()->create(); // A's parent is B…
        $b = WorkUnit::factory()->create(['parent_id' => $a->id]); // …so B cannot parent A.

        $this->actingAs($this->superadmin())
            ->patch("/admin/superadmin/units/{$a->id}", ['parent_id' => $b->id])
            ->assertStatus(422);
    }
}
