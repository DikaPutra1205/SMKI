<?php

namespace Tests\Feature;

use App\Exports\ControlsSheet;
use App\Models\Framework;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlApiTest extends TestCase
{
    use RefreshDatabase;

    private function framework(): Framework
    {
        return Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);
    }

    public function test_index_lists_controls(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->framework()->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/controls')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'kode_klausul', 'judul', 'kategori']]]);
    }

    public function test_index_filters_by_framework_id(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $fw->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi']);
        $other = Framework::create(['nama' => 'Other', 'versi' => '1']);
        $other->controls()->create(['kode_klausul' => 'A.6.1', 'judul' => 'Other', 'kategori' => 'teknologi']);

        $this->actingAs($admin)
            ->getJson("/api/controls?framework_id={$fw->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kode_klausul', 'A.5.1');
    }

    public function test_by_framework_returns_controls(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $fw->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi']);

        $this->actingAs($admin)
            ->getJson("/api/frameworks/{$fw->id}/controls")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.framework_id', $fw->id);
    }

    public function test_store_creates_control(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();

        $this->actingAs($admin)
            ->postJson('/api/controls', [
                'framework_id' => $fw->id,
                'kode_klausul' => 'A.5.2',
                'judul' => 'Information security roles',
                'kategori' => 'teknologi',
            ])
            ->assertCreated()
            ->assertJsonPath('data.kode_klausul', 'A.5.2');

        $this->assertDatabaseHas('controls', ['kode_klausul' => 'A.5.2']);
    }

    public function test_store_rejects_bad_kategori(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();

        $this->actingAs($admin)
            ->postJson('/api/controls', [
                'framework_id' => $fw->id,
                'kode_klausul' => 'A.5.2',
                'judul' => 'Bad',
                'kategori' => 'x',
            ])
            ->assertStatus(422);
    }

    public function test_show_update_destroy(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $control = $fw->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi',
        ]);

        $this->actingAs($admin)->getJson("/api/controls/{$control->id}")->assertOk();

        $this->actingAs($admin)
            ->patchJson("/api/controls/{$control->id}", ['judul' => 'Policies v2'])
            ->assertOk();
        $this->assertDatabaseHas('controls', ['id' => $control->id, 'judul' => 'Policies v2']);

        $this->actingAs($admin)->deleteJson("/api/controls/{$control->id}")->assertOk();
        $this->assertSoftDeleted('controls', ['id' => $control->id]);
    }

    // control.create is gated — a pic (no control.create) must be denied.
    public function test_pic_cannot_create_control(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $fw = $this->framework();

        $this->actingAs($pic)
            ->postJson('/api/controls', [
                'framework_id' => $fw->id,
                'kode_klausul' => 'A.5.9',
                'judul' => 'Inventory of information and other assets',
                'kategori' => 'teknologi',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('controls', ['kode_klausul' => 'A.5.9']);
    }

    public function test_index_search_matches_kode_klausul(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $fw->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi']);
        $fw->controls()->create(['kode_klausul' => 'A.7.2', 'judul' => 'Other', 'kategori' => 'teknologi']);

        $this->actingAs($admin)
            ->getJson('/api/controls?search=A.5.1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kode_klausul', 'A.5.1');
    }

    public function test_by_framework_returns_404_for_soft_deleted_framework(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $fw->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi']);
        $fw->delete();

        $this->actingAs($admin)
            ->getJson("/api/frameworks/{$fw->id}/controls")
            ->assertStatus(404);
    }

    public function test_store_accepts_organisasional_kategori(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();

        $this->actingAs($admin)
            ->postJson('/api/controls', [
                'framework_id' => $fw->id,
                'kode_klausul' => '4.2.2',
                'judul' => 'Stakeholder communication',
                'kategori' => 'organisasional',
            ])
            ->assertCreated()
            ->assertJsonPath('data.kategori', 'organisasional');

        $this->assertDatabaseHas('controls', ['kode_klausul' => '4.2.2', 'kategori' => 'organisasional']);
    }

    public function test_store_accepts_domain_peran_values(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();

        foreach (['controller', 'processor'] as $i => $peran) {
            $this->actingAs($admin)
                ->postJson('/api/controls', [
                    'framework_id' => $fw->id,
                    'kode_klausul' => "A.5.{$i}",
                    'judul' => "Control {$peran}",
                    'kategori' => 'teknologi',
                    'domain_peran' => $peran,
                ])
                ->assertCreated()
                ->assertJsonPath('data.domain_peran', $peran);

            $this->assertDatabaseHas('controls', ['kode_klausul' => "A.5.{$i}", 'domain_peran' => $peran]);
        }
    }

    public function test_store_accepts_missing_or_null_domain_peran(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();

        $this->actingAs($admin)
            ->postJson('/api/controls', [
                'framework_id' => $fw->id,
                'kode_klausul' => 'A.5.1',
                'judul' => 'No peran',
                'kategori' => 'teknologi',
            ])
            ->assertCreated();
        $this->assertDatabaseHas('controls', ['kode_klausul' => 'A.5.1', 'domain_peran' => null]);

        $this->actingAs($admin)
            ->postJson('/api/controls', [
                'framework_id' => $fw->id,
                'kode_klausul' => 'A.5.2',
                'judul' => 'Null peran',
                'kategori' => 'teknologi',
                'domain_peran' => null,
            ])
            ->assertCreated();
        $this->assertDatabaseHas('controls', ['kode_klausul' => 'A.5.2', 'domain_peran' => null]);
    }

    public function test_store_rejects_bad_domain_peran(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();

        $this->actingAs($admin)
            ->postJson('/api/controls', [
                'framework_id' => $fw->id,
                'kode_klausul' => 'A.5.2',
                'judul' => 'Bad peran',
                'kategori' => 'teknologi',
                'domain_peran' => 'owner',
            ])
            ->assertStatus(422);
    }

    public function test_update_sets_and_clears_domain_peran(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $control = $fw->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi',
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/controls/{$control->id}", ['domain_peran' => 'processor'])
            ->assertOk();
        $this->assertDatabaseHas('controls', ['id' => $control->id, 'domain_peran' => 'processor']);

        $this->actingAs($admin)
            ->patchJson("/api/controls/{$control->id}", ['domain_peran' => null])
            ->assertOk();
        $this->assertDatabaseHas('controls', ['id' => $control->id, 'domain_peran' => null]);
    }

    public function test_index_filters_by_domain_peran(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw = $this->framework();
        $fw->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'C1', 'kategori' => 'teknologi', 'domain_peran' => 'controller']);
        $fw->controls()->create(['kode_klausul' => 'A.5.2', 'judul' => 'P1', 'kategori' => 'teknologi', 'domain_peran' => 'processor']);

        $this->actingAs($admin)
            ->getJson('/api/controls?domain_peran=controller')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kode_klausul', 'A.5.1');

        $fw->controls()->create(['kode_klausul' => 'A.5.3', 'judul' => 'U1', 'kategori' => 'teknologi', 'domain_peran' => null]);

        $this->actingAs($admin)
            ->getJson('/api/controls?domain_peran=unknown')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kode_klausul', 'A.5.3');
    }

    public function test_controls_export_headings_include_domain_peran(): void
    {
        $this->assertContains('domain_peran', (new ControlsSheet)->headings());
    }

    public function test_update_moves_control_to_another_framework(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $fw1 = $this->framework();
        $fw2 = Framework::create(['nama' => 'ISO 27701:2019', 'versi' => '2019']);
        $control = $fw1->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi',
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/controls/{$control->id}", [
                'framework_id' => $fw2->id,
                'kode_klausul' => 'A.5.1',
                'judul' => 'Policies',
                'kategori' => 'teknologi',
            ])
            ->assertOk();

        $this->assertDatabaseHas('controls', ['id' => $control->id, 'framework_id' => $fw2->id]);
    }
}
