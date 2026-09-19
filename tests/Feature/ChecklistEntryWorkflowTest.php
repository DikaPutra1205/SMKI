<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
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
        \Illuminate\Support\Facades\Storage::fake('supabase');
        ['pic' => $pic, 'entry' => $entry] = $this->seedWfEntry();

        $this->actingAs($pic)->patchJson("/api/checklist-entries/{$entry->id}", [
            'catatan' => 'SOP tersedia',
            'bukti_file' => \Illuminate\Http\UploadedFile::fake()->create('sop.pdf', 100, 'application/pdf'),
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
}
