<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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
}
