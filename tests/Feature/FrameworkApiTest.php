<?php

namespace Tests\Feature;

use App\Models\Framework;
use App\Models\User;
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
}
