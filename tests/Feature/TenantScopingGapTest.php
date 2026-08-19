<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopingGapTest extends TestCase
{
    use RefreshDatabase;

    private function seedTwoUnits(): array
    {
        $unitA = WorkUnit::create(['nama' => 'Unit A']);
        $unitB = WorkUnit::create(['nama' => 'Unit B']);
        $fw = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);
        $control = $fw->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a',
        ]);
        $picA = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unitA->id]);
        $picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unitB->id]);

        return compact('unitA', 'unitB', 'control', 'picA', 'picB');
    }

    // Currently FAILS by design: index_cross_unit_read_is_not_scoped (ChecklistEntryApiTest:143)
    public function test_pic_cannot_read_other_units_entries(): void
    {
        ['unitA' => $unitA, 'unitB' => $unitB, 'control' => $control, 'picA' => $picA, 'picB' => $picB]
            = $this->seedTwoUnits();

        ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unitB->id, 'pic_id' => $picB->id,
            'status' => ChecklistEntry::STATUS_PARTIAL,
        ]);

        $this->actingAs($picA)
            ->getJson("/api/checklist-entries?unit_id={$unitB->id}")
            ->assertForbidden(); // expected once scoped; currently 200 (gap)
    }

    // Currently FAILS by design: verify_cross_unit_not_scoped (ChecklistEntryApiTest:585)
    public function test_pic_cannot_verify_other_units_entry(): void
    {
        ['unitA' => $unitA, 'unitB' => $unitB, 'control' => $control, 'picA' => $picA, 'picB' => $picB]
            = $this->seedTwoUnits();

        $entryInB = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unitB->id, 'pic_id' => $picB->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        $this->actingAs($picA)
            ->patch("/api/checklist-entries/{$entryInB->id}", [
                'status' => ChecklistEntry::STATUS_COMPLIANT,
                'catatan' => 'cross-unit attempt',
            ])
            ->assertForbidden(); // expected once scoped; currently 200 (gap)
    }
}
