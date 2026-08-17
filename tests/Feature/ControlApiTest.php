<?php

namespace Tests\Feature;

use App\Models\Control;
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
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a',
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
        $fw->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a']);
        $other = Framework::create(['nama' => 'Other', 'versi' => '1']);
        $other->controls()->create(['kode_klausul' => 'A.6.1', 'judul' => 'Other', 'kategori' => 'annex_a']);

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
        $fw->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a']);

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
                'kategori' => 'annex_a',
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
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a',
        ]);

        $this->actingAs($admin)->getJson("/api/controls/{$control->id}")->assertOk();

        $this->actingAs($admin)
            ->patchJson("/api/controls/{$control->id}", ['judul' => 'Policies v2'])
            ->assertOk();
        $this->assertDatabaseHas('controls', ['id' => $control->id, 'judul' => 'Policies v2']);

        $this->actingAs($admin)->deleteJson("/api/controls/{$control->id}")->assertOk();
        $this->assertSoftDeleted('controls', ['id' => $control->id]);
    }

    // D6/D8 — any authenticated role can write master data. Verify, don't fix.
    public function test_pic_can_create_control(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $fw = $this->framework();

        $this->actingAs($pic)
            ->postJson('/api/controls', [
                'framework_id' => $fw->id,
                'kode_klausul' => 'A.5.9',
                'judul' => 'Inventory of information and other assets',
                'kategori' => 'annex_a',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('controls', ['kode_klausul' => 'A.5.9']);
    }
}
