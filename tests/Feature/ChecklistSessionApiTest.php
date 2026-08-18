<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistSessionApiTest extends TestCase
{
    use RefreshDatabase;

    private function setupData(): array
    {
        $unit = WorkUnit::create(['nama' => 'Biro Sistem Informasi']);
        $fw = Framework::create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        $control1 = $fw->controls()->create([
            'kode_klausul' => 'A.5.1',
            'judul' => 'Kebijakan Keamanan Informasi',
            'kategori' => 'annex_a',
        ]);
        $control2 = $fw->controls()->create([
            'kode_klausul' => 'A.5.2',
            'judul' => 'Peran dan Tanggung Jawab Keamanan',
            'kategori' => 'annex_a',
        ]);

        $pic = User::factory()->create([
            'name' => 'PIC User',
            'role' => User::ROLE_PIC,
            'unit_id' => $unit->id,
        ]);

        $auditor = User::factory()->create([
            'name' => 'Auditor User',
            'role' => User::ROLE_AUDITOR,
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin Kepatuhan',
            'role' => User::ROLE_ADMIN_KEPATUHAN,
        ]);

        return compact('unit', 'fw', 'control1', 'control2', 'pic', 'auditor', 'admin');
    }

    public function test_store_creates_session_and_provisions_checklist_entries(): void
    {
        extract($this->setupData());

        $payload = [
            'konteks_penilaian' => 'Audit Internal Semester 1 2026 - Layanan Cloud',
            'periode' => 'Semester 1 2026',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
            'catatan' => 'Sesi audit berkala internal.',
        ];

        $response = $this->actingAs($admin)->postJson('/api/checklist-sessions', $payload);

        $response->assertCreated();
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'session' => ['id', 'konteks_penilaian', 'periode', 'unit_id', 'framework_id', 'created_by', 'updated_by'],
                'summary' => ['total_entries', 'compliant', 'compliance_percentage'],
            ],
        ]);

        $session = ChecklistSession::where('konteks_penilaian', 'Audit Internal Semester 1 2026 - Layanan Cloud')->first();
        $this->assertNotNull($session);
        $this->assertSame('Semester 1 2026', $session->periode);
        $this->assertSame($admin->id, $session->created_by);
        $this->assertSame($admin->id, $session->updated_by);

        // Checklist entries should be auto-provisioned for all controls in framework
        $this->assertDatabaseHas('checklist_entries', [
            'session_id' => $session->id,
            'control_id' => $control1->id,
            'unit_id' => $unit->id,
            'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        $this->assertDatabaseHas('checklist_entries', [
            'session_id' => $session->id,
            'control_id' => $control2->id,
            'unit_id' => $unit->id,
            'pic_id' => $pic->id,
        ]);

        $this->assertSame(2, $session->entries()->count());
    }

    public function test_index_lists_sessions_with_filters(): void
    {
        extract($this->setupData());

        $session1 = ChecklistSession::create([
            'konteks_penilaian' => 'Audit Internal Q1 Surabaya',
            'periode' => 'Q1 2026',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
        ]);

        $session2 = ChecklistSession::create([
            'konteks_penilaian' => 'Audit Eksternal Q2 Jakarta',
            'periode' => 'Q2 2026',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
        ]);

        $resPeriode = $this->actingAs($admin)->getJson('/api/checklist-sessions?periode=Q1 2026');
        $resPeriode->assertOk();
        $resPeriode->assertJsonFragment(['periode' => 'Q1 2026']);
        $resPeriode->assertJsonMissing(['periode' => 'Q2 2026']);

        $resSearch = $this->actingAs($admin)->getJson('/api/checklist-sessions?search=Surabaya');
        $resSearch->assertOk();
        $resSearch->assertJsonFragment(['konteks_penilaian' => 'Audit Internal Q1 Surabaya']);
        $resSearch->assertJsonMissing(['konteks_penilaian' => 'Audit Eksternal Q2 Jakarta']);
    }

    public function test_show_session_returns_entries_and_summary(): void
    {
        extract($this->setupData());

        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Audit Q1',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
        ]);

        $entry = ChecklistEntry::create([
            'session_id' => $session->id,
            'control_id' => $control1->id,
            'unit_id' => $unit->id,
            'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        $res = $this->actingAs($admin)->getJson("/api/checklist-sessions/{$session->id}");
        $res->assertOk();
        $res->assertJsonPath('data.session.id', $session->id);
        $res->assertJsonPath('data.summary.total_entries', 1);
        $res->assertJsonPath('data.summary.compliant', 1);
        $res->assertJsonPath('data.summary.compliance_percentage', 100);
    }

    public function test_update_session(): void
    {
        extract($this->setupData());

        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Audit Draft',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
            'created_by' => $admin->id,
        ]);

        $res = $this->actingAs($admin)->putJson("/api/checklist-sessions/{$session->id}", [
            'konteks_penilaian' => 'Audit Finalized',
            'periode' => 'Semester 2 2026',
            'catatan' => 'Catatan revisi',
        ]);

        $res->assertOk();
        $fresh = $session->fresh();
        $this->assertSame('Audit Finalized', $fresh->konteks_penilaian);
        $this->assertSame('Semester 2 2026', $fresh->periode);
        $this->assertSame('Catatan revisi', $fresh->catatan);
        $this->assertSame($admin->id, $fresh->updated_by);
    }

    public function test_soft_delete_and_restore_session(): void
    {
        extract($this->setupData());

        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Audit To Delete',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
        ]);

        $entry = ChecklistEntry::create([
            'session_id' => $session->id,
            'control_id' => $control1->id,
            'unit_id' => $unit->id,
            'pic_id' => $pic->id,
        ]);

        $delRes = $this->actingAs($admin)->deleteJson("/api/checklist-sessions/{$session->id}");
        $delRes->assertOk();

        $this->assertSoftDeleted('checklist_sessions', ['id' => $session->id]);
        $this->assertSoftDeleted('checklist_entries', ['id' => $entry->id]);

        $restoreRes = $this->actingAs($admin)->postJson("/api/checklist-sessions/{$session->id}/restore");
        $restoreRes->assertOk();

        $this->assertNotSoftDeleted('checklist_sessions', ['id' => $session->id]);
        $this->assertNotSoftDeleted('checklist_entries', ['id' => $entry->id]);
    }

    // Validation: store without konteks_penilaian should fail with 422.
    // This previously surfaced as "test_checklist_sessions_crud Error: konteks_penilaian required"
    // because the test payload was missing the required field.
    public function test_store_rejects_missing_konteks_penilaian(): void
    {
        extract($this->setupData());

        $response = $this->actingAs($admin)->postJson('/api/checklist-sessions', [
            'periode' => 'Q1 2026',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
            // konteks_penilaian intentionally omitted
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['konteks_penilaian']);
    }

    // Validation: store with a non-existent unit_id should fail with 422.
    public function test_store_rejects_invalid_unit_id(): void
    {
        extract($this->setupData());

        $response = $this->actingAs($admin)->postJson('/api/checklist-sessions', [
            'konteks_penilaian' => 'Audit Q1',
            'unit_id' => 99999, // does not exist
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unit_id']);
    }

    // Validation: update without any fields should still succeed (no-op).
    public function test_update_empty_payload_returns_ok(): void
    {
        extract($this->setupData());

        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Audit To Update',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
        ]);

        $this->actingAs($admin)
            ->putJson("/api/checklist-sessions/{$session->id}", [])
            ->assertOk();
    }

    // Edge: show non-existent session should 404, not 500.
    public function test_show_nonexistent_session_returns_404(): void
    {
        extract($this->setupData());

        $this->actingAs($admin)
            ->getJson('/api/checklist-sessions/99999')
            ->assertNotFound();
    }
}
