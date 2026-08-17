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
            'nama_sesi' => 'Audit Internal Semester 1 2026',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
            'auditor_id' => $auditor->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'catatan' => 'Sesi audit berkala internal.',
        ];

        $response = $this->actingAs($admin)->postJson('/api/checklist-sessions', $payload);

        $response->assertCreated();
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'session' => ['id', 'nama_sesi', 'status', 'unit_id', 'framework_id'],
                'summary' => ['total_entries', 'compliant', 'compliance_percentage'],
            ],
        ]);

        $session = ChecklistSession::where('nama_sesi', 'Audit Internal Semester 1 2026')->first();
        $this->assertNotNull($session);
        $this->assertSame(ChecklistSession::STATUS_IN_PROGRESS, $session->status);

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
            'nama_sesi' => 'Audit Internal Q1',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
            'status' => ChecklistSession::STATUS_IN_PROGRESS,
        ]);

        $session2 = ChecklistSession::create([
            'nama_sesi' => 'Audit Eksternal Q2',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
            'status' => ChecklistSession::STATUS_CLOSED,
        ]);

        $res = $this->actingAs($admin)->getJson('/api/checklist-sessions?status=in_progress');
        $res->assertOk();
        $res->assertJsonFragment(['nama_sesi' => 'Audit Internal Q1']);
        $res->assertJsonMissing(['nama_sesi' => 'Audit Eksternal Q2']);
    }

    public function test_show_session_returns_entries_and_summary(): void
    {
        extract($this->setupData());

        $session = ChecklistSession::create([
            'nama_sesi' => 'Audit Q1',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
            'status' => ChecklistSession::STATUS_IN_PROGRESS,
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

    public function test_submit_and_verify_session_flow(): void
    {
        extract($this->setupData());

        $session = ChecklistSession::create([
            'nama_sesi' => 'Self Assessment 2026',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
            'status' => ChecklistSession::STATUS_IN_PROGRESS,
        ]);

        // PIC submits session
        $submitRes = $this->actingAs($pic)->postJson("/api/checklist-sessions/{$session->id}/submit");
        $submitRes->assertOk();
        $this->assertSame(ChecklistSession::STATUS_SUBMITTED, $session->fresh()->status);

        // Auditor verifies session and closes it
        $verifyRes = $this->actingAs($auditor)->patchJson("/api/checklist-sessions/{$session->id}/verify", [
            'status' => 'closed',
            'catatan' => 'Semua klausul terverifikasi memuaskan.',
        ]);
        $verifyRes->assertOk();
        $this->assertSame(ChecklistSession::STATUS_CLOSED, $session->fresh()->status);
        $this->assertSame('Semua klausul terverifikasi memuaskan.', $session->fresh()->catatan);
    }

    public function test_closed_session_locks_checklist_entries_from_updates(): void
    {
        extract($this->setupData());

        $session = ChecklistSession::create([
            'nama_sesi' => 'Locked Audit Sesi',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
            'status' => ChecklistSession::STATUS_CLOSED,
        ]);

        $entry = ChecklistEntry::create([
            'session_id' => $session->id,
            'control_id' => $control1->id,
            'unit_id' => $unit->id,
            'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        // Attempt to update entry inside a closed session
        $res = $this->actingAs($pic)->putJson("/api/checklist-entries/{$entry->id}", [
            'status' => 'compliant',
            'catatan' => 'Percobaan update di sesi closed',
        ]);

        $res->assertStatus(422);
        $res->assertJsonFragment([
            'status' => 'error',
            'message' => 'Sesi checklist audit sudah ditutup (closed) dan dikunci.',
        ]);

        $this->assertSame(ChecklistEntry::STATUS_NON_COMPLIANT, $entry->fresh()->status);
    }

    public function test_soft_delete_and_restore_session(): void
    {
        extract($this->setupData());

        $session = ChecklistSession::create([
            'nama_sesi' => 'Audit To Delete',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
            'status' => ChecklistSession::STATUS_IN_PROGRESS,
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
}
