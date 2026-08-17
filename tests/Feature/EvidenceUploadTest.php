<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_evidence_upload_rejects_unallowed_file_types(): void
    {
        Storage::fake('supabase');

        $user = User::factory()->create(['role' => User::ROLE_PIC]);
        $unit = WorkUnit::factory()->create();
        $framework = Framework::factory()->create();
        $control = Control::factory()->create(['framework_id' => $framework->id]);

        $entry = ChecklistEntry::create([
            'control_id' => $control->id,
            'unit_id' => $unit->id,
            'pic_id' => $user->id,
            'status' => 'non_compliant',
            'catatan' => 'Test entry',
            'tanggal_input' => now(),
        ]);

        $exeFile = UploadedFile::fake()->create('malicious.exe', 500, 'application/x-msdownload');

        // Test in ComplianceEvidenceController::store
        $response1 = $this->actingAs($user)
            ->postJson("/api/checklist-entries/{$entry->id}/evidences", [
                'bukti_file' => $exeFile,
                'uploaded_by' => $user->id,
            ]);

        $response1->assertStatus(422);
        $response1->assertJsonValidationErrors(['bukti_file']);

        // Test in ChecklistEntryController::update
        $response2 = $this->actingAs($user)
            ->postJson("/api/checklist-entries/{$entry->id}", [
                'bukti_file' => $exeFile,
            ]);

        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors(['bukti_file']);
    }

    public function test_evidence_upload_accepts_valid_pdf_file(): void
    {
        Storage::fake('supabase');

        $user = User::factory()->create(['role' => User::ROLE_PIC]);
        $unit = WorkUnit::factory()->create();
        $framework = Framework::factory()->create();
        $control = Control::factory()->create(['framework_id' => $framework->id]);

        $entry = ChecklistEntry::create([
            'control_id' => $control->id,
            'unit_id' => $unit->id,
            'pic_id' => $user->id,
            'status' => 'non_compliant',
            'catatan' => 'Test entry',
            'tanggal_input' => now(),
        ]);

        $pdfFile = UploadedFile::fake()->create('sop_keamanan.pdf', 500, 'application/pdf');

        $response = $this->actingAs($user)
            ->postJson("/api/checklist-entries/{$entry->id}/evidences", [
                'bukti_file' => $pdfFile,
                'uploaded_by' => $user->id,
            ]);

        $response->assertCreated();
    }
}
