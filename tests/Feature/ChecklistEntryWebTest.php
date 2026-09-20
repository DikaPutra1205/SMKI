<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\ComplianceEvidence;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChecklistEntryWebTest extends TestCase
{
    use RefreshDatabase;

    private function seedUnit(): array
    {
        $unit = WorkUnit::create(['nama' => 'Unit Web']);
        $fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $control = $fw->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi',
        ]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Sesi Web', 'unit_id' => $unit->id, 'framework_id' => $fw->id,
        ]);

        return compact('unit', 'control', 'pic', 'session');
    }

    public function test_batch_update_updates_multiple_entries(): void
    {
        ['unit' => $unit, 'control' => $control, 'pic' => $pic, 'session' => $session] = $this->seedUnit();

        $e1 = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $control->id,
            'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);
        $e2 = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $control->id,
            'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->actingAs($pic)
            ->from('/admin/pic/checklist')
            ->postJson('/admin/pic/checklist-entries/batch', [
                'session_id' => $session->id,
                'entries' => [
                    ['id' => $e1->id, 'catatan' => 'Updated 1'],
                    ['id' => $e2->id, 'catatan' => 'Updated 2'],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'updated' => 2]);

        $this->assertDatabaseHas('checklist_entries', ['id' => $e1->id, 'catatan' => 'Updated 1']);
        $this->assertDatabaseHas('checklist_entries', ['id' => $e2->id, 'catatan' => 'Updated 2']);
    }

    public function test_batch_update_skips_non_owned_entries(): void
    {
        ['unit' => $unit, 'control' => $control, 'pic' => $pic, 'session' => $session] = $this->seedUnit();
        $otherPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);

        $ownEntry = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $control->id,
            'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);
        $otherEntry = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $control->id,
            'unit_id' => $unit->id, 'pic_id' => $otherPic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->actingAs($pic)
            ->from('/admin/pic/checklist')
            ->postJson('/admin/pic/checklist-entries/batch', [
                'session_id' => $session->id,
                'entries' => [
                    ['id' => $ownEntry->id, 'catatan' => 'Mine'],
                    ['id' => $otherEntry->id, 'catatan' => 'Theirs'],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'updated' => 1]);

        $this->assertDatabaseHas('checklist_entries', ['id' => $ownEntry->id, 'catatan' => 'Mine']);
        $this->assertDatabaseMissing('checklist_entries', ['id' => $otherEntry->id, 'catatan' => 'Theirs']);
    }

    public function test_batch_update_rejects_prohibited_status_field(): void
    {
        ['unit' => $unit, 'control' => $control, 'pic' => $pic, 'session' => $session] = $this->seedUnit();
        $entry = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $control->id,
            'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->actingAs($pic)
            ->from('/admin/pic/checklist')
            ->postJson('/admin/pic/checklist-entries/batch', [
                'session_id' => $session->id,
                'entries' => [['id' => $entry->id, 'status' => 'selesai_diterapkan']],
            ])
            ->assertStatus(422);
    }

    public function test_delete_evidence_soft_deletes_and_updates_status(): void
    {
        Storage::fake('supabase');

        ['unit' => $unit, 'control' => $control, 'pic' => $pic, 'session' => $session] = $this->seedUnit();
        $entry = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $control->id,
            'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
        ]);
        $evidence = ComplianceEvidence::create([
            'checklist_entry_id' => $entry->id, 'uploaded_by' => $pic->id,
            'file_url' => 'bukti/1/test.pdf', 'version_number' => 1,
            'is_active' => true, 'uploaded_at' => now(),
        ]);

        $this->actingAs($pic)
            ->from('/admin/pic/checklist')
            ->deleteJson("/admin/pic/checklist-entries/{$entry->id}/evidence/{$evidence->id}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSoftDeleted('compliance_evidences', ['id' => $evidence->id]);
        $this->assertNull($entry->fresh()->tanggal_verifikasi);
    }

    public function test_delete_evidence_not_found_returns_404(): void
    {
        ['unit' => $unit, 'control' => $control, 'pic' => $pic, 'session' => $session] = $this->seedUnit();
        $entry = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $control->id,
            'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->actingAs($pic)
            ->from('/admin/pic/checklist')
            ->deleteJson("/admin/pic/checklist-entries/{$entry->id}/evidence/99999")
            ->assertNotFound();
    }

    public function test_update_sets_tidak_berlaku_and_clears_verification(): void
    {
        ['unit' => $unit, 'control' => $control, 'pic' => $pic, 'session' => $session] = $this->seedUnit();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $entry = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $control->id,
            'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'tanggal_verifikasi' => now(), 'admin_id' => $admin->id,
        ]);

        $this->actingAs($pic)
            ->from('/admin/pic/checklist')
            ->patchJson("/admin/pic/checklist-entries/{$entry->id}", [
                'tidak_berlaku' => true,
                'catatan' => 'Tidak berlaku untuk unit ini',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $fresh = $entry->fresh();
        $this->assertSame(ChecklistEntry::WORKFLOW_TIDAK_BERLAKU, $fresh->status);
        $this->assertNull($fresh->tanggal_verifikasi);
    }

    public function test_update_sets_level_maturity(): void
    {
        ['unit' => $unit, 'control' => $control, 'pic' => $pic, 'session' => $session] = $this->seedUnit();
        $entry = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $control->id,
            'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->actingAs($pic)
            ->from('/admin/pic/checklist')
            ->patchJson("/admin/pic/checklist-entries/{$entry->id}", [
                'level_maturity' => 4,
            ])
            ->assertOk();

        $this->assertSame(4, $entry->fresh()->level_maturity);
    }

    public function test_update_rejects_status_field(): void
    {
        ['unit' => $unit, 'control' => $control, 'pic' => $pic, 'session' => $session] = $this->seedUnit();
        $entry = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $control->id,
            'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->actingAs($pic)
            ->from('/admin/pic/checklist')
            ->patchJson("/admin/pic/checklist-entries/{$entry->id}", [
                'status' => 'selesai_diterapkan',
            ])
            ->assertStatus(422);
    }
}
