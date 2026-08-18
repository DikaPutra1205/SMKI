<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\ComplianceEvidence;
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
            ->patchJson("/api/checklist-entries/{$entry->id}", [
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

    // ── Web uploadEvidence() path (POST /admin/pic/checklist-entries/{id}/evidence) ──

    private function seedPicEntry(): array
    {
        $unit = WorkUnit::factory()->create(['nama' => 'Unit Web QA']);
        $user = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $framework = Framework::factory()->create();
        $control = Control::factory()->create(['framework_id' => $framework->id]);
        $session = ChecklistSession::factory()->create([
            'unit_id' => $unit->id,
            'framework_id' => $framework->id,
        ]);
        $entry = ChecklistEntry::create([
            'session_id' => $session->id,
            'control_id' => $control->id,
            'unit_id' => $unit->id,
            'pic_id' => $user->id,
            'status' => 'non_compliant',
            'catatan' => 'Test entry',
            'tanggal_input' => now(),
            'tanggal_verifikasi' => now(),
        ]);

        return ['user' => $user, 'unit' => $unit, 'session' => $session, 'entry' => $entry];
    }

    public function test_web_upload_evidence_success_creates_version(): void
    {
        Storage::fake('supabase');
        ['user' => $user, 'entry' => $entry] = $this->seedPicEntry();

        $this->actingAs($user)->post("/admin/pic/checklist-entries/{$entry->id}/evidence", [
            'bukti_file' => UploadedFile::fake()->create('bukti.pdf', 500, 'application/pdf'),
        ])->assertStatus(302)
            ->assertSessionHas('flash', ['type' => 'success', 'message' => 'Bukti berhasil diunggah.']);

        $evidence = ComplianceEvidence::where('checklist_entry_id', $entry->id)->firstOrFail();
        $this->assertSame(1, $evidence->version_number);
        $this->assertTrue($evidence->is_active);
        $this->assertSame($user->id, $evidence->uploaded_by);
        $this->assertStringContainsString("bukti/{$entry->id}/", $evidence->file_url);
        $this->assertCount(1, Storage::disk('supabase')->allFiles("bukti/{$entry->id}"));
        $this->assertNotNull($entry->fresh()->tanggal_input);
        $this->assertNull($entry->fresh()->tanggal_verifikasi);
    }

    public function test_web_upload_evidence_increments_versions_and_deactivates_prior(): void
    {
        Storage::fake('supabase');
        ['user' => $user, 'entry' => $entry] = $this->seedPicEntry();

        $this->actingAs($user)->post("/admin/pic/checklist-entries/{$entry->id}/evidence",
            ['bukti_file' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf')])->assertStatus(302);
        $this->actingAs($user)->post("/admin/pic/checklist-entries/{$entry->id}/evidence",
            ['bukti_file' => UploadedFile::fake()->create('b.pdf', 100, 'application/pdf')])->assertStatus(302);

        $this->assertDatabaseHas('compliance_evidences', ['checklist_entry_id' => $entry->id, 'version_number' => 1, 'is_active' => false]);
        $this->assertDatabaseHas('compliance_evidences', ['checklist_entry_id' => $entry->id, 'version_number' => 2, 'is_active' => true]);
    }

    public function test_web_upload_evidence_rejects_entry_of_another_pic(): void
    {
        Storage::fake('supabase');
        ['user' => $owner, 'entry' => $entry] = $this->seedPicEntry();
        $other = User::factory()->create(['role' => User::ROLE_PIC]);

        $this->actingAs($other)->post("/admin/pic/checklist-entries/{$entry->id}/evidence",
            ['bukti_file' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf')])
            ->assertStatus(404);
    }

    public function test_web_upload_evidence_rejects_session_of_another_unit(): void
    {
        Storage::fake('supabase');
        ['user' => $owner, 'unit' => $unit, 'entry' => $entry] = $this->seedPicEntry();
        $otherUnit = WorkUnit::factory()->create();
        $otherSession = ChecklistSession::factory()->create(['unit_id' => $otherUnit->id]);
        $entry->update(['session_id' => $otherSession->id, 'unit_id' => $otherUnit->id]);

        $this->actingAs($owner)->post("/admin/pic/checklist-entries/{$entry->id}/evidence",
            ['bukti_file' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf')])
            ->assertStatus(404);
        $this->assertDatabaseMissing('compliance_evidences', ['checklist_entry_id' => $entry->id]);
    }

    public function test_web_upload_evidence_rejects_bad_mime_type(): void
    {
        Storage::fake('supabase');
        ['user' => $user, 'entry' => $entry] = $this->seedPicEntry();

        $this->actingAs($user)->from('/admin/pic/checklist-entries')
            ->post("/admin/pic/checklist-entries/{$entry->id}/evidence",
                ['bukti_file' => UploadedFile::fake()->create('x.exe', 100, 'application/x-msdownload')])
            ->assertStatus(302)
            ->assertSessionHasErrors('bukti_file');
        $this->assertDatabaseMissing('compliance_evidences', ['checklist_entry_id' => $entry->id]);
    }

    public function test_web_upload_evidence_requires_file(): void
    {
        Storage::fake('supabase');
        ['user' => $user, 'entry' => $entry] = $this->seedPicEntry();

        $this->actingAs($user)->from('/admin/pic/checklist-entries')
            ->post("/admin/pic/checklist-entries/{$entry->id}/evidence", [])
            ->assertStatus(302)
            ->assertSessionHasErrors('bukti_file');
        $this->assertDatabaseMissing('compliance_evidences', ['checklist_entry_id' => $entry->id]);
    }
}
