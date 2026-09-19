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
