<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlApiCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function framework(): Framework
    {
        return Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);
    }

    private function makeControl(Framework $fw, string $kode = 'A.5.1', string $judul = 'Policies', string $kat = 'annex_a'): Control
    {
        return $fw->controls()->create(['kode_klausul' => $kode, 'judul' => $judul, 'kategori' => $kat]);
    }

    // ── Store validation paths ────────────────────────────────────────────────
    public function test_store_requires_framework_id(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->actingAs($admin)
            ->postJson('/api/controls', ['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['framework_id']);
    }

    public function test_store_rejects_missing_kode_klausul(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $this->actingAs($admin)
            ->postJson('/api/controls', ['framework_id' => $fw->id, 'judul' => 'Policies', 'kategori' => 'annex_a'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kode_klausul']);
    }

    public function test_store_rejects_missing_judul(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $this->actingAs($admin)
            ->postJson('/api/controls', ['framework_id' => $fw->id, 'kode_klausul' => 'A.5.1', 'kategori' => 'annex_a'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['judul']);
    }

    public function test_store_rejects_nonexistent_framework(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->actingAs($admin)
            ->postJson('/api/controls', [
                'framework_id' => 99999, 'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['framework_id']);
    }

    public function test_store_rejects_kode_klausul_over_20_chars(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $this->actingAs($admin)
            ->postJson('/api/controls', [
                'framework_id' => $fw->id, 'kode_klausul' => str_repeat('X', 21),
                'judul' => 'Policies', 'kategori' => 'annex_a',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kode_klausul']);
    }

    // Unique (framework_id, kode_klausul) — blocked within, allowed across.
    public function test_store_rejects_duplicate_kode_within_framework(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $this->makeControl($fw, 'A.5.1');

        $this->actingAs($admin)
            ->postJson('/api/controls', [
                'framework_id' => $fw->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Dup', 'kategori' => 'annex_a',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kode_klausul']);
    }

    public function test_store_allows_same_kode_across_frameworks(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw1 = $this->framework();
        $fw2 = Framework::create(['nama' => 'ISO 27701:2019', 'versi' => '2019']);
        $this->makeControl($fw1, 'A.5.1');

        $this->actingAs($admin)
            ->postJson('/api/controls', [
                'framework_id' => $fw2->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Cross', 'kategori' => 'annex_a',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('controls', ['framework_id' => $fw2->id, 'kode_klausul' => 'A.5.1']);
    }

    // ── Update validation ─────────────────────────────────────────────────────
    public function test_update_rejects_bad_kategori(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $control = $this->makeControl($this->framework(), 'A.5.1');

        $this->actingAs($admin)
            ->patchJson("/api/controls/{$control->id}", ['kategori' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kategori']);
    }

    public function test_update_rejects_duplicate_kode_in_same_framework(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $this->makeControl($fw, 'A.5.1');
        $c2 = $this->makeControl($fw, 'A.5.2');

        $this->actingAs($admin)
            ->patchJson("/api/controls/{$c2->id}", [
                'framework_id' => $fw->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Roles',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kode_klausul']);
    }

    public function test_update_allows_reusing_own_kode(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $control = $this->makeControl($fw, 'A.5.1');

        $this->actingAs($admin)
            ->patchJson("/api/controls/{$control->id}", [
                'framework_id' => $fw->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Policies v2',
            ])
            ->assertOk();

        $this->assertDatabaseHas('controls', ['id' => $control->id, 'judul' => 'Policies v2']);
    }

    // ── Show / byFramework scoping ────────────────────────────────────────────
    public function test_show_returns_404_for_soft_deleted(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $control = $this->makeControl($this->framework(), 'A.5.1');
        $control->delete();

        $this->actingAs($admin)->getJson("/api/controls/{$control->id}")->assertNotFound();
    }

    public function test_by_framework_only_returns_that_framework(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw1 = $this->framework();
        $fw2 = Framework::create(['nama' => 'ISO 27701:2019', 'versi' => '2019']);
        $this->makeControl($fw1, 'A.5.1', 'P1');
        $this->makeControl($fw2, 'A.5.1', 'P2');

        $this->actingAs($admin)
            ->getJson("/api/frameworks/{$fw1->id}/controls")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.judul', 'P1');
    }

    public function test_by_framework_excludes_soft_deleted(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $c = $this->makeControl($fw, 'A.5.1');
        $this->makeControl($fw, 'A.5.2', 'P2');
        $c->delete();

        $this->actingAs($admin)
            ->getJson("/api/frameworks/{$fw->id}/controls")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kode_klausul', 'A.5.2');
    }

    // ── Index kategori + search filters (untested branches) ───────────────────
    public function test_index_filters_by_kategori(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $this->makeControl($fw, 'A.5.1', 'P1', 'annex_a');
        $this->makeControl($fw, 'A.5.2', 'P2', 'klausul_4_10');

        $this->actingAs($admin)
            ->getJson('/api/controls?kategori=klausul_4_10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kategori', 'klausul_4_10');
    }

    public function test_index_search_matches_judul(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $this->makeControl($fw, 'A.5.1', 'Unique Title X');
        $this->makeControl($fw, 'A.5.2', 'Other');

        $this->actingAs($admin)
            ->getJson('/api/controls?search=Unique')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.judul', 'Unique Title X');
    }

    // ── Auth gate (anonymous) ─────────────────────────────────────────────────
    public function test_anonymous_cannot_list_controls(): void
    {
        $this->getJson('/api/controls')->assertStatus(401);
    }

    public function test_anonymous_cannot_create_control(): void
    {
        $fw = $this->framework();
        $this->postJson('/api/controls', [
            'framework_id' => $fw->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a',
        ])->assertStatus(401);

        $this->assertDatabaseMissing('controls', ['kode_klausul' => 'A.5.1']);
    }

    public function test_anonymous_cannot_delete_control(): void
    {
        $control = $this->makeControl($this->framework(), 'A.5.1');
        $this->deleteJson("/api/controls/{$control->id}")->assertStatus(401);
        $this->assertDatabaseHas('controls', ['id' => $control->id]);
    }

    // ── Framework cascade delete enforces parent/child integrity ──────────────
    public function test_deleting_framework_soft_deletes_its_controls(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $control = $this->makeControl($fw, 'A.5.1');

        $this->actingAs($admin)->deleteJson("/api/frameworks/{$fw->id}")->assertOk();
        $this->assertSoftDeleted('controls', ['id' => $control->id]);
    }
}
