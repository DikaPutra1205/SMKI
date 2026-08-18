<?php

namespace Tests\Feature;

use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrameworkApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_frameworks_with_controls_count(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $framework = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);
        $framework->controls()->create([
            'kode_klausul' => 'A.5.1',
            'judul' => 'Policies for information security',
            'kategori' => 'annex_a',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/frameworks')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'nama', 'versi', 'controls_count']]])
            ->assertJsonPath('data.0.controls_count', 1);

        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO 27001:2022']);
    }

    // SoftDeletes global scope — deleted frameworks must be excluded from index.
    public function test_index_excludes_soft_deleted_frameworks(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        Framework::create(['nama' => 'Active', 'versi' => '1']);
        $gone = Framework::create(['nama' => 'Deleted', 'versi' => '1']);
        $gone->delete();

        $this->actingAs($admin)
            ->getJson('/api/frameworks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Active');
    }

    public function test_store_creates_framework(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs($admin)
            ->postJson('/api/frameworks', ['nama' => 'ISO 27701:2019', 'versi' => '2019'])
            ->assertCreated()
            ->assertJsonPath('data.nama', 'ISO 27701:2019');

        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO 27701:2019', 'versi' => '2019']);
    }

    // url_file is nullable|url — valid URL must persist.
    public function test_store_accepts_and_persists_valid_url_file(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs($admin)
            ->postJson('/api/frameworks', [
                'nama' => 'ISO 27001:2022',
                'versi' => '2022',
                'url_file' => 'https://example.com/iso27001.pdf',
            ])
            ->assertCreated()
            ->assertJsonPath('data.url_file', 'https://example.com/iso27001.pdf');

        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO 27001:2022', 'url_file' => 'https://example.com/iso27001.pdf']);
    }

    public function test_store_rejects_missing_nama(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs($admin)
            ->postJson('/api/frameworks', ['versi' => '2019'])
            ->assertStatus(422);
    }

    public function test_store_rejects_missing_versi(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs($admin)
            ->postJson('/api/frameworks', ['nama' => 'ISO 27001:2022'])
            ->assertStatus(422);
    }

    public function test_store_rejects_invalid_url_file(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs($admin)
            ->postJson('/api/frameworks', [
                'nama' => 'ISO 27001:2022',
                'versi' => '2022',
                'url_file' => 'not-a-url',
            ])
            ->assertStatus(422);
    }

    public function test_store_rejects_overlong_nama_and_versi(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs($admin)
            ->postJson('/api/frameworks', [
                'nama' => str_repeat('a', 256),
                'versi' => str_repeat('b', 51),
            ])
            ->assertStatus(422);
    }

    public function test_anonymous_cannot_access_framework_endpoints(): void
    {
        $framework = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);

        $this->getJson('/api/frameworks')->assertStatus(401);
        $this->postJson('/api/frameworks', ['nama' => 'X', 'versi' => '1'])->assertStatus(401);
        $this->getJson("/api/frameworks/{$framework->id}")->assertStatus(401);
        $this->patchJson("/api/frameworks/{$framework->id}", ['nama' => 'Y'])->assertStatus(401);
        $this->deleteJson("/api/frameworks/{$framework->id}")->assertStatus(401);
    }

    public function test_show_returns_framework_with_controls(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $framework = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);

        $this->actingAs($admin)
            ->getJson("/api/frameworks/{$framework->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $framework->id)
            ->assertJsonStructure(['data' => ['controls']]);
    }

    // Implicit route-model binding excludes trashed → 404 for deleted framework.
    public function test_show_returns_404_for_soft_deleted_framework(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $framework = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);
        $framework->delete();

        $this->actingAs($admin)
            ->getJson("/api/frameworks/{$framework->id}")
            ->assertStatus(404);
    }

    public function test_update_modifies_framework(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $framework = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);

        $this->actingAs($admin)
            ->patchJson("/api/frameworks/{$framework->id}", ['nama' => 'ISO 27001 rev'])
            ->assertOk();

        $this->assertDatabaseHas('frameworks', ['id' => $framework->id, 'nama' => 'ISO 27001 rev']);
    }

    // sometimes rules — partial update must not clear unspecified fields.
    public function test_update_partial_keeps_unspecified_fields(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $framework = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022', 'url_file' => 'https://x.test/a.pdf']);

        $this->actingAs($admin)
            ->patchJson("/api/frameworks/{$framework->id}", ['versi' => '2023'])
            ->assertOk();

        $this->assertDatabaseHas('frameworks', [
            'id' => $framework->id,
            'nama' => 'ISO 27001:2022',
            'versi' => '2023',
            'url_file' => 'https://x.test/a.pdf',
        ]);
    }

    // sometimes|url — update must reject a non-URL url_file.
    public function test_update_rejects_invalid_url_file(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $framework = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);

        $this->actingAs($admin)
            ->patchJson("/api/frameworks/{$framework->id}", ['url_file' => 'broken'])
            ->assertStatus(422);
    }

    public function test_destroy_soft_deletes_framework(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $framework = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);

        $this->actingAs($admin)
            ->deleteJson("/api/frameworks/{$framework->id}")
            ->assertOk();

        $this->assertSoftDeleted('frameworks', ['id' => $framework->id]);
    }

    // D6/D8 — auth is permissive; non-admin roles also mutate master data.
    public function test_admin_kepatuhan_role_can_create_framework(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $this->actingAs($user)
            ->postJson('/api/frameworks', ['nama' => 'AK Framework', 'versi' => '1'])
            ->assertCreated();

        $this->assertDatabaseHas('frameworks', ['nama' => 'AK Framework']);
    }

    // D6/D8 — auditor role also mutates master data (no policy/gate). Verify, don't fix.
    public function test_auditor_role_can_create_framework(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_AUDITOR]);

        $this->actingAs($user)
            ->postJson('/api/frameworks', ['nama' => 'Auditor Framework', 'versi' => '1'])
            ->assertCreated();

        $this->assertDatabaseHas('frameworks', ['nama' => 'Auditor Framework']);
    }

    // D6/D8 — any authenticated role can write master data (no policy/gate). Verify, don't fix.
    public function test_pic_role_can_crud_framework(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);

        $created = $this->actingAs($pic)
            ->postJson('/api/frameworks', ['nama' => 'PIC Framework', 'versi' => '1'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($pic)
            ->deleteJson("/api/frameworks/{$created}")
            ->assertOk();

        $this->assertSoftDeleted('frameworks', ['id' => $created]);
    }

    // API store now uses StoreFrameworkRequest which enforces unique:frameworks,nama.
    public function test_store_rejects_duplicate_nama_via_api(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs($admin)
            ->postJson('/api/frameworks', ['nama' => 'ISO 27001:2022', 'versi' => '2022'])
            ->assertCreated();

        $this->actingAs($admin)
            ->postJson('/api/frameworks', ['nama' => 'ISO 27001:2022', 'versi' => '2022'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nama']);

        $this->assertDatabaseCount('frameworks', 1);
    }

    // ── Work Units API (no dedicated file in the feature's legal test set) ───
    public function test_work_units_index_lists_flat_with_parent(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $root = WorkUnit::factory()->create(['nama' => 'Root Unit']);
        $child = WorkUnit::factory()->create(['nama' => 'Child Unit', 'parent_id' => $root->id]);

        $this->actingAs($admin)
            ->getJson('/api/work-units')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $child->id)
            ->assertJsonPath('data.0.parent.id', $root->id)
            ->assertJsonPath('data.1.id', $root->id)
            ->assertJsonPath('data.1.parent', null);
    }

    public function test_work_units_tree_returns_nested_roots(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $root = WorkUnit::factory()->create(['nama' => 'Akar']);
        $child = WorkUnit::factory()->create(['nama' => 'Anak', 'parent_id' => $root->id]);
        $grandchild = WorkUnit::factory()->create(['nama' => 'Cucu', 'parent_id' => $child->id]);

        $this->actingAs($admin)
            ->getJson('/api/work-units-tree')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $root->id)
            ->assertJsonPath('data.0.children.0.id', $child->id)
            ->assertJsonPath('data.0.children.0.children.0.id', $grandchild->id);
    }

    public function test_work_units_tree_excludes_soft_deleted_children(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $root = WorkUnit::factory()->create(['nama' => 'Akar']);
        $gone = WorkUnit::factory()->create(['nama' => 'Hilang', 'parent_id' => $root->id]);
        $gone->delete();

        $this->actingAs($admin)
            ->getJson('/api/work-units-tree')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(0, 'data.0.children');
    }

    public function test_work_units_store_validates_nama_and_parent(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs($admin)
            ->postJson('/api/work-units', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nama']);

        $this->actingAs($admin)
            ->postJson('/api/work-units', ['nama' => 'Unit', 'parent_id' => 99999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_work_units_store_creates_with_parent(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $parent = WorkUnit::factory()->create(['nama' => 'Root']);

        $this->actingAs($admin)
            ->postJson('/api/work-units', ['nama' => 'Sub Unit', 'parent_id' => $parent->id])
            ->assertCreated()
            ->assertJsonPath('data.parent.id', $parent->id);

        $this->assertDatabaseHas('work_units', ['nama' => 'Sub Unit', 'parent_id' => $parent->id]);
    }

    public function test_work_units_show_returns_parent_and_children(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $root = WorkUnit::factory()->create(['nama' => 'Root']);
        $child = WorkUnit::factory()->create(['nama' => 'Child', 'parent_id' => $root->id]);

        $this->actingAs($admin)
            ->getJson("/api/work-units/{$child->id}")
            ->assertOk()
            ->assertJsonPath('data.parent.id', $root->id)
            ->assertJsonCount(0, 'data.children');
    }

    public function test_work_units_show_returns_404_for_soft_deleted(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $unit = WorkUnit::factory()->create();
        $unit->delete();

        $this->actingAs($admin)->getJson("/api/work-units/{$unit->id}")->assertStatus(404);
    }

    public function test_work_units_update_partial_and_parent_validation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $unit = WorkUnit::factory()->create(['nama' => 'Unit A']);

        $this->actingAs($admin)
            ->patchJson("/api/work-units/{$unit->id}", ['nama' => 'Unit A Renamed'])
            ->assertOk();

        $this->assertDatabaseHas('work_units', ['id' => $unit->id, 'nama' => 'Unit A Renamed']);

        $this->actingAs($admin)
            ->patchJson("/api/work-units/{$unit->id}", ['parent_id' => 99999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_work_units_destroy_soft_deletes(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $unit = WorkUnit::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/work-units/{$unit->id}")->assertOk();

        $this->assertSoftDeleted('work_units', ['id' => $unit->id]);
    }

    // Documents a gap: parent_id may point at the unit itself, creating a cycle.
    public function test_work_units_update_allows_self_parent_cycle(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $unit = WorkUnit::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/work-units/{$unit->id}", ['parent_id' => $unit->id])
            ->assertOk();

        $this->assertDatabaseHas('work_units', ['id' => $unit->id, 'parent_id' => $unit->id]);
    }

    public function test_work_units_anonymous_cannot_access(): void
    {
        $this->getJson('/api/work-units')->assertStatus(401);
        $this->getJson('/api/work-units-tree')->assertStatus(401);
        $this->postJson('/api/work-units', ['nama' => 'X'])->assertStatus(401);
    }

    // ── Users API (dev panel switcher) ───────────────────────────────────────
    public function test_users_index_returns_safe_attributes_with_unit(): void
    {
        $unit = WorkUnit::factory()->create(['nama' => 'Unit A']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);

        $this->actingAs($admin)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.role', 'admin_kepatuhan')
            ->assertJsonPath('data.1.role', 'pic')
            ->assertJsonPath('data.1.unit.id', $unit->id)
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.remember_token');
    }

    public function test_users_index_anonymous_cannot_access(): void
    {
        $this->getJson('/api/users')->assertStatus(401);
    }
}
