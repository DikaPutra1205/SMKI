<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
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
}
