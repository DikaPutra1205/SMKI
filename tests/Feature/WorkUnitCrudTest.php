<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkUnitCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $pic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->pic = User::factory()->create(['role' => User::ROLE_PIC]);
    }

    // ── Authz ────────────────────────────────────────────────────────────

    public function test_anonymous_redirected_to_login(): void
    {
        $this->get('/admin/superadmin/units')->assertRedirect(route('login'));
    }

    public function test_pic_cannot_view_units_page(): void
    {
        $this->actingAs($this->pic)->get('/admin/superadmin/units')->assertForbidden();
    }

    public function test_pic_cannot_create_unit(): void
    {
        $this->actingAs($this->pic)->post('/admin/superadmin/units', ['nama' => 'X'])
            ->assertForbidden();
    }

    public function test_pic_cannot_update_unit(): void
    {
        $unit = WorkUnit::create(['nama' => 'U']);
        $this->actingAs($this->pic)->patch("/admin/superadmin/units/{$unit->id}", ['nama' => 'H'])
            ->assertForbidden();
    }

    public function test_pic_cannot_delete_unit(): void
    {
        $unit = WorkUnit::create(['nama' => 'U']);
        $this->actingAs($this->pic)->delete("/admin/superadmin/units/{$unit->id}")
            ->assertForbidden();
    }

    // ── Happy path ───────────────────────────────────────────────────────

    public function test_superadmin_can_list_units(): void
    {
        WorkUnit::create(['nama' => 'Unit A']);

        $this->actingAs($this->superadmin)->get('/admin/superadmin/units')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('superadmin/units')
                ->has('units'));
    }

    public function test_superadmin_can_create_unit(): void
    {
        $this->actingAs($this->superadmin)
            ->from('/admin/superadmin/units')
            ->post('/admin/superadmin/units', ['nama' => 'New Unit'])
            ->assertRedirect('/admin/superadmin/units')
            ->assertSessionHas('flash.type', 'success');

        $this->assertDatabaseHas('work_units', ['nama' => 'New Unit']);
    }

    public function test_superadmin_can_update_unit(): void
    {
        $unit = WorkUnit::create(['nama' => 'Old']);

        $this->actingAs($this->superadmin)
            ->from('/admin/superadmin/units')
            ->patch("/admin/superadmin/units/{$unit->id}", ['nama' => 'New'])
            ->assertRedirect();

        $this->assertDatabaseHas('work_units', ['id' => $unit->id, 'nama' => 'New']);
    }

    public function test_superadmin_can_delete_unit(): void
    {
        $unit = WorkUnit::create(['nama' => 'Gone']);

        $this->actingAs($this->superadmin)
            ->delete("/admin/superadmin/units/{$unit->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('work_units', ['id' => $unit->id]);
    }

    // ── Cycle detection ──────────────────────────────────────────────────

    public function test_cannot_set_parent_to_self(): void
    {
        $unit = WorkUnit::create(['nama' => 'Self']);

        $this->actingAs($this->superadmin)
            ->patch("/admin/superadmin/units/{$unit->id}", ['parent_id' => $unit->id])
            ->assertStatus(422);
    }

    public function test_cannot_create_parent_cycle(): void
    {
        $a = WorkUnit::create(['nama' => 'A']);
        $b = WorkUnit::create(['nama' => 'B', 'parent_id' => $a->id]);
        $c = WorkUnit::create(['nama' => 'C', 'parent_id' => $b->id]);

        // A → B → C; try C as parent of A → cycle
        $this->actingAs($this->superadmin)
            ->patch("/admin/superadmin/units/{$a->id}", ['parent_id' => $c->id])
            ->assertStatus(422);
    }

    // ── Delete guard ─────────────────────────────────────────────────────

    public function test_cannot_delete_unit_with_children(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);

        $this->actingAs($this->superadmin)
            ->delete("/admin/superadmin/units/{$parent->id}")
            ->assertStatus(422);
    }

    public function test_cannot_delete_unit_with_users(): void
    {
        $unit = WorkUnit::create(['nama' => 'HasUser']);
        User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);

        $this->actingAs($this->superadmin)
            ->delete("/admin/superadmin/units/{$unit->id}")
            ->assertStatus(422);
    }

    // ── Validation ───────────────────────────────────────────────────────

    public function test_store_rejects_empty_nama(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/admin/superadmin/units', ['nama' => ''])
            ->assertSessionHasErrors('nama');
    }

    public function test_store_rejects_missing_nama(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/admin/superadmin/units', [])
            ->assertSessionHasErrors('nama');
    }

    public function test_update_rejects_invalid_parent_id(): void
    {
        $unit = WorkUnit::create(['nama' => 'U']);

        $this->actingAs($this->superadmin)
            ->patch("/admin/superadmin/units/{$unit->id}", ['parent_id' => 999999])
            ->assertSessionHasErrors('parent_id');
    }

    // ── US-G3: real-role matrix (only superadmin touches org structure) ────

    public static function nonSuperAdminRoles(): array
    {
        return [
            'admin_kepatuhan' => [User::ROLE_ADMIN_KEPATUHAN],
            'koordinator' => [User::ROLE_KOORDINATOR_SMKI],
            'auditor' => [User::ROLE_AUDITOR],
        ];
    }

    #[DataProvider('nonSuperAdminRoles')]
    public function test_non_superadmin_cannot_view_units_page(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get('/admin/superadmin/units')
            ->assertForbidden();
    }

    #[DataProvider('nonSuperAdminRoles')]
    public function test_non_superadmin_cannot_create_unit(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->post('/admin/superadmin/units', ['nama' => 'Unit'.$role])
            ->assertForbidden();

        $this->assertDatabaseMissing('work_units', ['nama' => 'Unit'.$role]);
    }

    #[DataProvider('nonSuperAdminRoles')]
    public function test_non_superadmin_cannot_update_or_delete_unit(string $role): void
    {
        $unit = WorkUnit::create(['nama' => 'Keep']);
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->patch("/admin/superadmin/units/{$unit->id}", ['nama' => 'Hijacked'])->assertForbidden();
        $this->actingAs($user)->delete("/admin/superadmin/units/{$unit->id}")->assertForbidden();

        $unit->refresh();
        $this->assertSame('Keep', $unit->nama);
        $this->assertNotSoftDeleted('work_units', ['id' => $unit->id]);
    }

    // ── Not-found vs forbidden ordering ──────────────────────────────────

    public function test_superadmin_gets_404_for_unknown_unit(): void
    {
        $this->actingAs($this->superadmin)
            ->patch('/admin/superadmin/units/999999', ['nama' => 'Ghost'])
            ->assertNotFound();
    }

    public function test_unknown_unit_is_404_even_for_unauthorized_role(): void
    {
        $this->actingAs($this->pic)
            ->patch('/admin/superadmin/units/999999', ['nama' => 'Ghost'])
            ->assertNotFound();
    }

    // ── US-M4 tree edits: DB effect, not just the status code ───────────────

    /** Detaching a child back to the root is a legitimate edit, not a cycle. */
    public function test_update_can_detach_a_child_unit_back_to_root(): void
    {
        $parent = WorkUnit::create(['nama' => 'Induk']);
        $child = WorkUnit::create(['nama' => 'Anak', 'parent_id' => $parent->id]);

        $this->actingAs($this->superadmin)
            ->from('/admin/superadmin/units')
            ->patch("/admin/superadmin/units/{$child->id}", ['parent_id' => null])
            ->assertRedirect('/admin/superadmin/units')
            ->assertSessionHasNoErrors();

        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_update_can_reparent_a_unit_under_an_unrelated_parent(): void
    {
        $a = WorkUnit::create(['nama' => 'A']);
        $b = WorkUnit::create(['nama' => 'B']);
        $child = WorkUnit::create(['nama' => 'Anak', 'parent_id' => $a->id]);

        $this->actingAs($this->superadmin)
            ->patch("/admin/superadmin/units/{$child->id}", ['parent_id' => $b->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($b->id, $child->fresh()->parent_id);
    }

    /**
     * The cycle guard is walked recursively, but only the two- and three-level
     * shapes were asserted. A four-level chain must be rejected too, and the
     * rejected write must not partially persist.
     */
    public function test_deep_descendant_cycle_is_rejected_and_leaves_the_row_unchanged(): void
    {
        $a = WorkUnit::create(['nama' => 'L1']);
        $b = WorkUnit::create(['nama' => 'L2', 'parent_id' => $a->id]);
        $c = WorkUnit::create(['nama' => 'L3', 'parent_id' => $b->id]);
        $d = WorkUnit::create(['nama' => 'L4', 'parent_id' => $c->id]);

        $this->actingAs($this->superadmin)
            ->patch("/admin/superadmin/units/{$a->id}", ['parent_id' => $d->id])
            ->assertStatus(422);

        $this->assertNull($a->fresh()->parent_id, 'Rejected cycle was still written.');
        $this->assertSame($c->id, $d->fresh()->parent_id);
    }

    public function test_cycle_rejection_keeps_the_submitted_name_unchanged(): void
    {
        $a = WorkUnit::create(['nama' => 'Asli']);
        $b = WorkUnit::create(['nama' => 'Anak', 'parent_id' => $a->id]);

        $this->actingAs($this->superadmin)
            ->patch("/admin/superadmin/units/{$a->id}", ['nama' => 'Diubah', 'parent_id' => $b->id])
            ->assertStatus(422);

        $this->assertSame('Asli', $a->fresh()->nama);
    }

    public function test_delete_guard_leaves_a_unit_with_children_intact(): void
    {
        $parent = WorkUnit::create(['nama' => 'Induk']);
        $child = WorkUnit::create(['nama' => 'Anak', 'parent_id' => $parent->id]);

        $this->actingAs($this->superadmin)
            ->delete("/admin/superadmin/units/{$parent->id}")
            ->assertStatus(422);

        $this->assertNotSoftDeleted('work_units', ['id' => $parent->id]);
        $this->assertSame($parent->id, $child->fresh()->parent_id);
    }

    public function test_delete_guard_leaves_a_unit_with_users_intact(): void
    {
        $unit = WorkUnit::create(['nama' => 'Berisi']);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);

        $this->actingAs($this->superadmin)
            ->delete("/admin/superadmin/units/{$unit->id}")
            ->assertStatus(422);

        $this->assertNotSoftDeleted('work_units', ['id' => $unit->id]);
        $this->assertSame($unit->id, $pic->fresh()->unit_id);
    }
}
