<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Task B: checklist_entries.level_maturity (0-5, NULL = belum dinilai).
class ChecklistMaturityTest extends TestCase
{
    use RefreshDatabase;

    private function seedUnitControlPics(): array
    {
        $unit = WorkUnit::create(['nama' => 'Unit QA']);
        $fw = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);
        $control = $fw->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi',
        ]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        return compact('unit', 'control', 'pic', 'admin');
    }

    private function makeEntry(array $overrides = []): ChecklistEntry
    {
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();

        return ChecklistEntry::create(array_merge([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ], $overrides));
    }

    public function test_store_accepts_maturity_boundaries_and_null(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();

        foreach ([0, 5, null] as $level) {
            $this->actingAs($admin)
                ->postJson('/api/checklist-entries', [
                    'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
                    'status' => ChecklistEntry::WORKFLOW_DALAM_PROSES, 'level_maturity' => $level,
                ])
                ->assertCreated()
                ->assertJsonPath('data.level_maturity', $level);
        }

        // missing key also accepted (defaults null)
        $this->actingAs($admin)
            ->postJson('/api/checklist-entries', [
                'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
                'status' => ChecklistEntry::WORKFLOW_DALAM_PROSES,
            ])
            ->assertCreated()
            ->assertJsonPath('data.level_maturity', null);
    }

    public function test_store_rejects_invalid_maturity(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();

        foreach ([6, -1, 'tinggi'] as $level) {
            $this->actingAs($admin)
                ->postJson('/api/checklist-entries', [
                    'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
                    'status' => ChecklistEntry::WORKFLOW_DALAM_PROSES, 'level_maturity' => $level,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['level_maturity']);
        }
    }

    public function test_update_rejects_invalid_maturity(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $entry = $this->makeEntry();

        foreach ([6, -1, 'tinggi'] as $level) {
            $this->actingAs($admin)
                ->patchJson("/api/checklist-entries/{$entry->id}", ['level_maturity' => $level])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['level_maturity']);
        }
    }

    public function test_update_and_verify_reject_invalid_maturity(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $entry = $this->makeEntry(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($admin)
            ->patchJson("/api/checklist-entries/{$entry->id}/verify", [
                'admin_id' => $admin->id, 'decision' => 'approve', 'level_maturity' => 6,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['level_maturity']);
    }

    public function test_batch_persists_maturity_alongside_status_and_catatan(): void
    {
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $session = ChecklistSession::create(['konteks_penilaian' => 'Sesi batch', 'unit_id' => $unit->id]);
        $entry = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $control->id, 'unit_id' => $unit->id,
            'pic_id' => $pic->id, 'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->actingAs($pic)
            ->postJson('/admin/pic/checklist-entries/batch', [
                'session_id' => $session->id,
                'entries' => [[
                    'id' => $entry->id,
                    'catatan' => 'Progres 50%', 'level_maturity' => 3,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('checklist_entries', [
            'id' => $entry->id, 'status' => 'dalam_proses',
            'catatan' => 'Progres 50%', 'level_maturity' => 3,
        ]);
    }

    public function test_batch_rejects_invalid_maturity(): void
    {
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $session = ChecklistSession::create(['konteks_penilaian' => 'Sesi batch', 'unit_id' => $unit->id]);
        $entry = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $control->id, 'unit_id' => $unit->id,
            'pic_id' => $pic->id, 'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        foreach ([6, -1, 'tinggi'] as $level) {
            $this->actingAs($pic)
                ->postJson('/admin/pic/checklist-entries/batch', [
                    'session_id' => $session->id,
                    'entries' => [['id' => $entry->id, 'level_maturity' => $level]],
                ])
                ->assertStatus(422);
        }
    }

    public function test_verify_corrects_maturity(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $entry = $this->makeEntry(['level_maturity' => 1, 'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($admin)
            ->patchJson("/api/checklist-entries/{$entry->id}/verify", [
                'admin_id' => $admin->id, 'decision' => 'approve', 'level_maturity' => 4,
            ])
            ->assertOk();

        $this->assertDatabaseHas('checklist_entries', ['id' => $entry->id, 'level_maturity' => 4]);
    }

    public function test_status_change_preserves_maturity(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $entry = $this->makeEntry(['level_maturity' => 2]);

        $this->actingAs($admin)
            ->patchJson("/api/checklist-entries/{$entry->id}", ['catatan' => 'Updated'])
            ->assertOk();

        $this->assertDatabaseHas('checklist_entries', [
            'id' => $entry->id, 'status' => 'dalam_proses', 'level_maturity' => 2,
        ]);
    }

    public function test_maturity_only_change_resets_tanggal_verifikasi(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $entry = $this->makeEntry([
            'status' => ChecklistEntry::WORKFLOW_DALAM_PROSES, 'level_maturity' => 2,
            'admin_id' => $admin->id, 'tanggal_verifikasi' => now()->subHour(),
            'catatan_admin' => 'OK sebagian',
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/checklist-entries/{$entry->id}", ['level_maturity' => 4])
            ->assertOk();

        $fresh = $entry->fresh();
        $this->assertSame(4, $fresh->level_maturity);
        $this->assertNull($fresh->tanggal_verifikasi);
    }

    public function test_web_update_maturity_change_resets_verification(): void
    {
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_DALAM_PROSES, 'level_maturity' => 2,
            'admin_id' => $admin->id, 'tanggal_verifikasi' => now()->subHour(),
            'catatan_admin' => 'Perbaiki',
        ]);

        $this->actingAs($pic)
            ->patchJson("/admin/pic/checklist-entries/{$entry->id}", ['level_maturity' => 3])
            ->assertOk();

        $fresh = $entry->fresh();
        $this->assertSame(3, $fresh->level_maturity);
        $this->assertNull($fresh->tanggal_verifikasi);
    }

    public function test_web_update_rejects_invalid_maturity(): void
    {
        ['unit' => $unit, 'control' => $control, 'pic' => $pic] = $this->seedUnitControlPics();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_DALAM_PROSES,
        ]);

        foreach ([6, -1, 'tinggi'] as $level) {
            $this->actingAs($pic)
                ->patchJson("/admin/pic/checklist-entries/{$entry->id}", ['level_maturity' => $level])
                ->assertStatus(422);
        }
    }

    public function test_verify_single_corrects_maturity(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $entry = $this->makeEntry(['level_maturity' => 1]);

        $this->actingAs($admin)
            ->from('/admin/kepatuhan/checklist/verify')
            ->post("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'reject',
                'admin_notes' => 'Maturity terlalu optimis',
                'level_maturity' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('checklist_entries', ['id' => $entry->id, 'level_maturity' => 0]);
    }

    public function test_provision_leaves_maturity_null(): void
    {
        $unit = WorkUnit::create(['nama' => 'Unit Provision']);
        $fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $fw->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi']);
        User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);

        $this->artisan('smki:generate-monthly-checklist')->assertSuccessful();

        $this->assertDatabaseHas('checklist_entries', ['unit_id' => $unit->id, 'level_maturity' => null]);
    }
}
