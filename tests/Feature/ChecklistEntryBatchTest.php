<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChecklistEntryBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $pic;

    private WorkUnit $unit;

    private ChecklistSession $session;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = WorkUnit::factory()->create();
        $this->pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unit->id]);

        $framework = Framework::factory()->create();
        $this->control = Control::factory()->create(['framework_id' => $framework->id]);

        $this->session = ChecklistSession::factory()->create([
            'unit_id' => $this->unit->id,
            'created_by' => $this->pic->id,
        ]);
    }

    // ── batchUpdate ──────────────────────────────────────────────────────

    public function test_batch_update_happy_path(): void
    {
        $entries = ChecklistEntry::factory()->count(3)->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->actingAs($this->pic)->postJson('/admin/pic/checklist-entries/batch', [
            'session_id' => $this->session->id,
            'entries' => $entries->map(fn ($e) => [
                'id' => $e->id,
                'catatan' => "Batch note {$e->id}",
                'level_maturity' => 3,
            ])->toArray(),
        ])->assertOk()->assertJsonPath('ok', true);

        foreach ($entries as $entry) {
            $fresh = $entry->fresh();
            $this->assertNotNull($fresh->tanggal_input);
            $this->assertNull($fresh->tanggal_verifikasi);
        }
    }

    public function test_batch_update_rejects_empty_entries(): void
    {
        $this->actingAs($this->pic)->postJson('/admin/pic/checklist-entries/batch', [
            'session_id' => $this->session->id,
            'entries' => [],
        ])->assertUnprocessable();
    }

    public function test_batch_update_rejects_missing_session_id(): void
    {
        $this->actingAs($this->pic)->postJson('/admin/pic/checklist-entries/batch', [
            'entries' => [['id' => 1, 'catatan' => 'x']],
        ])->assertUnprocessable();
    }

    public function test_batch_update_rejects_nonexistent_session(): void
    {
        $entry = ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id,
        ]);

        $this->actingAs($this->pic)->postJson('/admin/pic/checklist-entries/batch', [
            'session_id' => 999999,
            'entries' => [['id' => $entry->id, 'catatan' => 'x']],
        ])->assertUnprocessable();
    }

    public function test_batch_update_rejects_other_units_session(): void
    {
        $otherUnit = WorkUnit::factory()->create();
        $otherSession = ChecklistSession::factory()->create(['unit_id' => $otherUnit->id]);

        $entry = ChecklistEntry::factory()->create([
            'session_id' => $otherSession->id,
            'control_id' => $this->control->id,
            'unit_id' => $otherUnit->id,
            'pic_id' => $this->pic->id,
        ]);

        // Entry exists but session belongs to other unit — controller checks session ownership
        // Validation fires first on session_id exists, then controller scopes by unit
        $this->actingAs($this->pic)->postJson('/admin/pic/checklist-entries/batch', [
            'session_id' => $otherSession->id,
            'entries' => [['id' => $entry->id, 'catatan' => 'x']],
        ])->assertNotFound();
    }

    public function test_batch_update_skips_other_users_entries(): void
    {
        $otherPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unit->id]);
        $otherEntry = ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $otherPic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->actingAs($this->pic)->postJson('/admin/pic/checklist-entries/batch', [
            'session_id' => $this->session->id,
            'entries' => [['id' => $otherEntry->id, 'catatan' => 'hijack']],
        ])->assertOk();

        $this->assertNull($otherEntry->fresh()->catatan);
    }

    public function test_batch_update_prohibits_status_field(): void
    {
        $entry = ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id,
        ]);

        $this->actingAs($this->pic)->postJson('/admin/pic/checklist-entries/batch', [
            'session_id' => $this->session->id,
            'entries' => [['id' => $entry->id, 'status' => 'selesai']],
        ])->assertUnprocessable();
    }

    // ── uploadEvidence (web) ─────────────────────────────────────────────

    public function test_upload_evidence_happy_path(): void
    {
        Storage::fake('supabase');

        $entry = ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        $this->actingAs($this->pic)
            ->post("/admin/pic/checklist-entries/{$entry->id}/evidence", [
                'bukti_file' => UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('compliance_evidences', [
            'checklist_entry_id' => $entry->id,
            'uploaded_by' => $this->pic->id,
        ]);
    }

    public function test_upload_evidence_rejects_no_file(): void
    {
        $entry = ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id,
        ]);

        $this->actingAs($this->pic)
            ->post("/admin/pic/checklist-entries/{$entry->id}/evidence", [])
            ->assertSessionHasErrors('bukti_file');
    }

    public function test_upload_evidence_rejects_nonexistent_entry(): void
    {
        $this->actingAs($this->pic)
            ->post('/admin/pic/checklist-entries/999999/evidence', [
                'bukti_file' => UploadedFile::fake()->create('e.pdf', 100, 'application/pdf'),
            ])
            ->assertNotFound();
    }

    public function test_upload_evidence_rejects_other_users_entry(): void
    {
        Storage::fake('supabase');

        $otherPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unit->id]);
        $entry = ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $otherPic->id,
        ]);

        $this->actingAs($this->pic)
            ->post("/admin/pic/checklist-entries/{$entry->id}/evidence", [
                'bukti_file' => UploadedFile::fake()->create('e.pdf', 100, 'application/pdf'),
            ])
            ->assertNotFound();
    }

    // ── deleteEvidence (web) ─────────────────────────────────────────────

    public function test_delete_evidence_happy_path(): void
    {
        $entry = ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id,
        ]);

        $evidence = $entry->evidences()->create([
            'uploaded_by' => $this->pic->id,
            'file_url' => 'bukti/test.pdf',
            'version_number' => 1,
            'is_active' => true,
            'uploaded_at' => now(),
        ]);

        $this->actingAs($this->pic)
            ->delete("/admin/pic/checklist-entries/{$entry->id}/evidence/{$evidence->id}")
            ->assertOk();

        $this->assertSoftDeleted('compliance_evidences', ['id' => $evidence->id]);
    }

    public function test_delete_evidence_rejects_other_users_entry(): void
    {
        $otherPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unit->id]);
        $entry = ChecklistEntry::factory()->create([
            'session_id' => $this->session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $otherPic->id,
        ]);

        $evidence = $entry->evidences()->create([
            'uploaded_by' => $otherPic->id,
            'file_url' => 'bukti/test.pdf',
            'version_number' => 1,
            'is_active' => true,
            'uploaded_at' => now(),
        ]);

        $this->actingAs($this->pic)
            ->delete("/admin/pic/checklist-entries/{$entry->id}/evidence/{$evidence->id}")
            ->assertNotFound();
    }
}
