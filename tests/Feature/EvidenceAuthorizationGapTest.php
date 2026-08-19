<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceAuthorizationGapTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(): array
    {
        $unitA = WorkUnit::create(['nama' => 'Unit A']);
        $unitB = WorkUnit::create(['nama' => 'Unit B']);
        $fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $control = $fw->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a',
        ]);
        $picA = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unitA->id]);
        $picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unitB->id]);

        return compact('unitA', 'unitB', 'control', 'picA', 'picB');
    }

    // Currently FAILS by design: store_accepts_arbitrary_uploaded_by (ComplianceEvidenceApiTest:293)
    public function test_store_does_not_allow_arbitrary_uploaded_by(): void
    {
        Storage::fake('supabase');
        ['control' => $control, 'picA' => $picA, 'unitB' => $unitB, 'picB' => $picB] = $this->seedData();

        $entryInB = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unitB->id, 'pic_id' => $picB->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        $this->actingAs($picA) // picA uploads for an entry in unitB
            ->postJson("/api/checklist-entries/{$entryInB->id}/evidences", [
                'uploaded_by' => $picB->id, // attempting to spoof the uploader
                'bukti_file' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden(); // expected once validated; currently 201 (gap)
    }

    // Currently FAILS by design: destroy_cross_entry_is_not_scoped (ComplianceEvidenceApiTest:485)
    public function test_destroy_scopes_to_authenticated_users_unit(): void
    {
        Storage::fake('supabase');
        ['control' => $control, 'picA' => $picA, 'unitB' => $unitB, 'picB' => $picB] = $this->seedData();

        $entryInB = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unitB->id, 'pic_id' => $picB->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        $evidence = $entryInB->evidences()->create([
            'uploaded_by' => $picB->id,
            'file_url' => 'bukti/1/placeholder.pdf',
            'version_number' => 1,
            'is_active' => true,
            'uploaded_at' => now(),
        ]);

        $this->actingAs($picA)
            ->delete("/api/evidences/{$evidence->id}")
            ->assertForbidden(); // expected once scoped; currently 200/204 (gap)
    }
}
