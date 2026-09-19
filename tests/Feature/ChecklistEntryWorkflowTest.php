<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use App\Services\ComplianceOfficerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ChecklistEntryWorkflowTest extends TestCase
{
    public function test_resolve_pic_workflow_both_present_is_tinjauan(): void
    {
        $this->assertSame(
            ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            ChecklistEntry::resolvePicWorkflow(true, true)
        );
    }

    public function test_resolve_pic_workflow_one_present_is_proses(): void
    {
        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_PROSES, ChecklistEntry::resolvePicWorkflow(true, false));
        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_PROSES, ChecklistEntry::resolvePicWorkflow(false, true));
    }

    public function test_resolve_pic_workflow_none_is_belum(): void
    {
        $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, ChecklistEntry::resolvePicWorkflow(false, false));
    }

    public function test_workflow_values_lists_five_states(): void
    {
        $this->assertSame(
            ['belum_dimulai', 'dalam_proses', 'dalam_tinjauan', 'selesai_diterapkan', 'tidak_berlaku'],
            ChecklistEntry::workflowValues()
        );
    }

    private function seedWfEntry(): array
    {
        $unit = WorkUnit::create(['nama' => 'Unit WF']);
        $fw = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);
        $control = $fw->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi']);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI, 'catatan' => '',
        ]);

        return compact('unit', 'control', 'pic', 'entry');
    }

    public function test_api_update_catatan_only_derives_proses_and_clears_verification(): void
    {
        ['pic' => $pic, 'entry' => $entry] = $this->seedWfEntry();
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $entry->update(['status' => ChecklistEntry::WORKFLOW_SELESAI, 'tanggal_verifikasi' => now(), 'admin_id' => $admin->id]);

        $this->actingAs($pic)
            ->patchJson("/api/checklist-entries/{$entry->id}", ['catatan' => 'SOP tersedia'])
            ->assertOk();

        $fresh = $entry->fresh();
        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_PROSES, $fresh->status);
        $this->assertNull($fresh->tanggal_verifikasi);
        $this->assertSame($admin->id, $fresh->admin_id);
    }

    public function test_api_update_catatan_plus_evidence_derives_tinjauan(): void
    {
        Storage::fake('supabase');
        ['pic' => $pic, 'entry' => $entry] = $this->seedWfEntry();

        $this->actingAs($pic)->patchJson("/api/checklist-entries/{$entry->id}", [
            'catatan' => 'SOP tersedia',
            'bukti_file' => UploadedFile::fake()->create('sop.pdf', 100, 'application/pdf'),
        ])->assertOk();

        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_TINJAUAN, $entry->fresh()->status);
    }

    public function test_api_update_tidak_berlaku_sticks(): void
    {
        ['pic' => $pic, 'entry' => $entry] = $this->seedWfEntry();

        $this->actingAs($pic)->patchJson("/api/checklist-entries/{$entry->id}", [
            'tidak_berlaku' => true, 'catatan' => 'Kontrol ini tidak relevan karena layanan cloud.',
        ])->assertOk();

        $fresh = $entry->fresh();
        $this->assertSame(ChecklistEntry::WORKFLOW_TIDAK_BERLAKU, $fresh->status);
    }

    public function test_api_update_na_stays_na_on_catatan_edit(): void
    {
        ['pic' => $pic, 'entry' => $entry] = $this->seedWfEntry();
        $entry->update(['status' => ChecklistEntry::WORKFLOW_TIDAK_BERLAKU, 'catatan' => 'Alasan awal']);

        $this->actingAs($pic)->patchJson("/api/checklist-entries/{$entry->id}", [
            'tidak_berlaku' => true, 'catatan' => 'Alasan diperbarui',
        ])->assertOk();

        $this->assertSame(ChecklistEntry::WORKFLOW_TIDAK_BERLAKU, $entry->fresh()->status);
    }

    public function test_api_update_na_requires_justifikasi(): void
    {
        ['pic' => $pic, 'entry' => $entry] = $this->seedWfEntry();

        $this->actingAs($pic)->patchJson("/api/checklist-entries/{$entry->id}", [
            'tidak_berlaku' => true, 'catatan' => '',
        ])->assertStatus(422);
    }

    public function test_index_auto_provisions_belum_dimulai_with_empty_catatan(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $unit = WorkUnit::create(['nama' => 'Unit WF']);
        $fw = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);
        $fw->controls()->create(['kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi']);
        User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);

        $this->actingAs($admin)->getJson("/api/checklist-entries?unit_id={$unit->id}")->assertOk();

        $entry = ChecklistEntry::where('unit_id', $unit->id)->firstOrFail();
        $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, $entry->status);
        $this->assertSame('', $entry->catatan);
    }

    public function test_api_store_sets_workflow_from_catatan(): void
    {
        $unit = WorkUnit::create(['nama' => 'Unit Store']);
        $fw = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);
        $control = $fw->controls()->create(['kode_klausul' => 'A.5.2', 'judul' => 'Policies', 'kategori' => 'teknologi']);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);

        $this->actingAs($pic)->postJson('/api/checklist-entries', [
            'control_id' => $control->id,
            'unit_id' => $unit->id,
            'pic_id' => $pic->id,
            'catatan' => 'SOP tersedia',
        ])->assertCreated();

        $entry = ChecklistEntry::where('control_id', $control->id)->where('unit_id', $unit->id)->firstOrFail();
        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_PROSES, $entry->status);
        $this->assertSame('SOP tersedia', $entry->catatan);
    }

    public function test_api_verify_approve_sets_selesai(): void
    {
        ['pic' => $pic, 'entry' => $entry] = $this->seedWfEntry();
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $entry->update(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($admin)->patchJson("/api/checklist-entries/{$entry->id}/verify", [
            'admin_id' => $admin->id,
            'decision' => 'approve',
        ])->assertOk();

        $fresh = $entry->fresh();
        $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $fresh->status);
        $this->assertNotNull($fresh->tanggal_verifikasi);
    }

    public function test_api_verify_approve_na_keeps_na(): void
    {
        ['pic' => $pic, 'entry' => $entry] = $this->seedWfEntry();
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $entry->update(['status' => ChecklistEntry::WORKFLOW_TIDAK_BERLAKU]);

        $this->actingAs($admin)->patchJson("/api/checklist-entries/{$entry->id}/verify", [
            'admin_id' => $admin->id,
            'decision' => 'approve',
        ])->assertOk();

        $fresh = $entry->fresh();
        $this->assertSame(ChecklistEntry::WORKFLOW_TIDAK_BERLAKU, $fresh->status);
        $this->assertNotNull($fresh->tanggal_verifikasi);
    }

    public function test_api_verify_reject_returns_proses(): void
    {
        ['pic' => $pic, 'entry' => $entry] = $this->seedWfEntry();
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $entry->update(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($admin)->patchJson("/api/checklist-entries/{$entry->id}/verify", [
            'admin_id' => $admin->id,
            'decision' => 'reject',
            'catatan_admin' => 'Bukti tidak lengkap',
        ])->assertOk();

        $fresh = $entry->fresh();
        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_PROSES, $fresh->status);
        $this->assertNull($fresh->tanggal_verifikasi);
        $this->assertSame('Bukti tidak lengkap', $fresh->catatan_admin);
    }

    public function test_api_verify_reject_requires_note_422(): void
    {
        ['pic' => $pic, 'entry' => $entry] = $this->seedWfEntry();
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $entry->update(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($admin)->patchJson("/api/checklist-entries/{$entry->id}/verify", [
            'admin_id' => $admin->id,
            'decision' => 'reject',
        ])->assertStatus(422);
    }

    public function test_api_verify_stamps_authed_user(): void
    {
        ['pic' => $pic, 'entry' => $entry] = $this->seedWfEntry();
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $entry->update(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($admin)->patchJson("/api/checklist-entries/{$entry->id}/verify", [
            'admin_id' => $admin->id,
            'decision' => 'approve',
        ])->assertOk();

        $this->assertSame($admin->id, $entry->fresh()->admin_id);
    }

    public function test_api_verify_rejects_stale_status_422(): void
    {
        ['pic' => $pic, 'entry' => $entry] = $this->seedWfEntry();
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $entry->update(['status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI]);

        $this->actingAs($admin)->patchJson("/api/checklist-entries/{$entry->id}/verify", [
            'admin_id' => $admin->id,
            'decision' => 'approve',
        ])->assertStatus(422);
    }

    public function test_bulk_approve_mixed_with_na(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        ['pic' => $pic, 'control' => $control, 'unit' => $unit] = $this->seedWfEntry();
        $entry1 = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);
        $entry2 = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_TIDAK_BERLAKU,
        ]);

        $service = app(ComplianceOfficerService::class);
        $count = $service->bulkVerifyChecklistEntries($admin, [$entry1->id, $entry2->id], 'approve');

        $this->assertEquals(2, $count);
        $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $entry1->fresh()->status);
        $this->assertNotNull($entry1->fresh()->tanggal_verifikasi);
        $this->assertSame(ChecklistEntry::WORKFLOW_TIDAK_BERLAKU, $entry2->fresh()->status);
        $this->assertNotNull($entry2->fresh()->tanggal_verifikasi);
        $this->assertNull($entry1->fresh()->catatan_admin);
    }

    public function test_bulk_reject_sets_proses_and_keeps_note(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        ['pic' => $pic, 'control' => $control, 'unit' => $unit] = $this->seedWfEntry();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $service = app(ComplianceOfficerService::class);
        $count = $service->bulkVerifyChecklistEntries($admin, [$entry->id], 'reject', 'Perbaiki bukti');

        $this->assertEquals(1, $count);
        $fresh = $entry->fresh();
        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_PROSES, $fresh->status);
        $this->assertSame('Perbaiki bukti', $fresh->catatan_admin);
        $this->assertNull($fresh->tanggal_verifikasi);
        $this->assertSame($admin->id, $fresh->admin_id);
    }

    public function test_bulk_verify_rejects_unknown_decision(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        ['pic' => $pic, 'control' => $control, 'unit' => $unit] = $this->seedWfEntry();
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $service = app(ComplianceOfficerService::class);
        $this->expectException(HttpException::class);
        $service->bulkVerifyChecklistEntries($admin, [$entry->id], 'selesai_diterapkan');
    }

    public function test_web_pic_update_catatan_derives_proses_and_clears_timestamp_only(): void
    {
        ['pic' => $pic, 'entry' => $entry] = $this->seedWfEntry();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $entry->update([
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'tanggal_verifikasi' => now(),
            'admin_id' => $admin->id,
            'catatan_admin' => 'OK immers?',
        ]);

        $this->actingAs($pic)
            ->patchJson("/admin/pic/checklist-entries/{$entry->id}", ['catatan' => 'SOP diperbarui'])
            ->assertOk();

        $fresh = $entry->fresh();
        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_PROSES, $fresh->status);
        $this->assertNull($fresh->tanggal_verifikasi);
        $this->assertSame('OK immers?', $fresh->catatan_admin);
        $this->assertSame($admin->id, $fresh->admin_id);
    }

    public function test_web_evidence_delete_recomputes_to_proses(): void
    {
        Storage::fake('supabase');
        ['pic' => $pic, 'entry' => $entry] = $this->seedWfEntry();
        $entry->update([
            'catatan' => 'SOP tersedia',
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $evidence = $entry->evidences()->create([
            'uploaded_by' => $pic->id,
            'file_url' => 'bukti/'.$entry->id.'/sop.pdf',
            'version_number' => 1,
            'is_active' => true,
            'uploaded_at' => now(),
        ]);

        $this->actingAs($pic)
            ->deleteJson("/admin/pic/checklist-entries/{$entry->id}/evidence/{$evidence->id}")
            ->assertOk();

        $fresh = $entry->fresh();
        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_PROSES, $fresh->status);
        $this->assertNull($fresh->tanggal_verifikasi);
    }

    public function test_session_summary_reports_workflow_buckets(): void
    {
        $unit = WorkUnit::create(['nama' => 'Unit Summary']);
        $fw = Framework::create(['nama' => 'ISO27001', 'versi' => '2022']);
        $control1 = $fw->controls()->create(['kode_klausul' => 'A5.1', 'judul' => 'Policies', 'kategori' => 'teknologi']);
        $control2 = $fw->controls()->create(['kode_klausul' => 'A5.2', 'judul' => 'People', 'kategori' => 'teknologi']);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Audit',
            'unit_id' => $unit->id,
            'framework_id' => $fw->id,
            'created_by' => $pic->id,
            'updated_by' => $pic->id,
        ]);

        $entry1 = ChecklistEntry::create([
            'control_id' => $control1->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI, 'tanggal_verifikasi' => now(),
        ]);
        $entry2 = ChecklistEntry::create([
            'control_id' => $control2->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);
        $session->entries()->saveMany([$entry1, $entry2]);

        $summary = $session->summary;

        $this->assertEquals(2, $summary['total_entries']);
        $this->assertEquals(1, $summary['selesai_entries']);
        $this->assertEquals(1, $summary['belum_entries']);
        $this->assertEquals(0, $summary['tinjauan_entries']);
        $this->assertEquals(0, $summary['proses_entries']);
        $this->assertEquals(0, $summary['na_entries']);
        $this->assertEquals(1, $summary['completed']);
        $this->assertEquals(50, $summary['completion_percentage']);
    }
}
