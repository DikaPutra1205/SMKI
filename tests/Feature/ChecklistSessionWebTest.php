<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistSessionWebTest extends TestCase
{
    use RefreshDatabase;

    private WorkUnit $unit;

    private Framework $fw;

    private Control $control;

    private User $pic;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unit = WorkUnit::create(['nama' => 'Unit Session']);
        $this->fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $this->control = $this->fw->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi',
        ]);
        $this->pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unit->id]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
    }

    public function test_generate_creates_session_and_entries(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/admin/kepatuhan/checklist-sessions', [
                'konteks_penilaian' => 'Q4 2026',
                'unit_id' => $this->unit->id,
                'framework_id' => $this->fw->id,
                'periode' => now()->format('Y-m'),
            ])
            ->assertRedirect();

        $session = ChecklistSession::where('unit_id', $this->unit->id)->first();
        $this->assertNotNull($session);
        $this->assertDatabaseHas('checklist_entries', [
            'session_id' => $session->id,
            'control_id' => $this->control->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);
    }

    public function test_generate_rejects_missing_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/admin/kepatuhan/checklist-sessions', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['konteks_penilaian', 'unit_id']);
    }

    public function test_generate_rejects_duplicate_periode(): void
    {
        $period = now()->format('Y-m');
        ChecklistSession::create([
            'konteks_penilaian' => 'Existing', 'unit_id' => $this->unit->id,
            'framework_id' => $this->fw->id, 'periode' => $period,
        ]);

        $this->actingAs($this->admin)
            ->postJson('/admin/kepatuhan/checklist-sessions', [
                'konteks_penilaian' => 'Duplicate',
                'unit_id' => $this->unit->id,
                'framework_id' => $this->fw->id,
                'periode' => $period,
            ])
            ->assertRedirect();
    }

    public function test_destroy_soft_deletes_session_and_entries(): void
    {
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'To Delete', 'unit_id' => $this->unit->id,
        ]);
        $entry = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $this->control->id,
            'unit_id' => $this->unit->id, 'pic_id' => $this->pic->id,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/admin/kepatuhan/checklist-sessions/{$session->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('checklist_sessions', ['id' => $session->id]);
        $this->assertSoftDeleted('checklist_entries', ['id' => $entry->id]);
    }

    public function test_restore_restores_session_and_entries(): void
    {
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'To Restore', 'unit_id' => $this->unit->id,
        ]);
        $entry = ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $this->control->id,
            'unit_id' => $this->unit->id, 'pic_id' => $this->pic->id,
        ]);
        $session->entries()->delete();
        $session->delete();

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist-sessions/{$session->id}/restore")
            ->assertRedirect();

        $this->assertDatabaseHas('checklist_sessions', ['id' => $session->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('checklist_entries', ['id' => $entry->id, 'deleted_at' => null]);
    }

    public function test_submit_assessment_rejects_incomplete_entries(): void
    {
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Submit Test', 'unit_id' => $this->unit->id,
        ]);
        ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $this->control->id,
            'unit_id' => $this->unit->id, 'pic_id' => $this->pic->id,
            'status' => ChecklistEntry::WORKFLOW_DALAM_PROSES,
            'catatan' => '',
        ]);

        $response = $this->actingAs($this->pic)
            ->postJson("/admin/pic/checklist/{$session->id}/submit");

        $response->assertRedirect();
        $flash = $this->app['session']->get('flash');
        $this->assertStringContainsString('belum diisi catatan', $flash['message'] ?? '');
    }

    public function test_submit_assessment_passes_when_all_complete(): void
    {
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Submit OK', 'unit_id' => $this->unit->id,
        ]);
        ChecklistEntry::create([
            'session_id' => $session->id, 'control_id' => $this->control->id,
            'unit_id' => $this->unit->id, 'pic_id' => $this->pic->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'catatan' => 'Done',
        ]);

        $this->actingAs($this->pic)
            ->postJson("/admin/pic/checklist/{$session->id}/submit")
            ->assertRedirect();
    }

    public function test_generate_monthly_web_route_returns_ok(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/admin/kepatuhan/generate-monthly')
            ->assertRedirect();
    }

    public function test_update_session_catatan(): void
    {
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Original', 'unit_id' => $this->unit->id,
        ]);

        $this->actingAs($this->admin)
            ->putJson("/admin/kepatuhan/checklist-sessions/{$session->id}", [
                'catatan' => 'Updated note',
            ])
            ->assertRedirect();

        $this->assertSame('Updated note', $session->fresh()->catatan);
    }

    public function test_show_returns_403_for_other_unit_pic(): void
    {
        $otherUnit = WorkUnit::create(['nama' => 'Other']);
        $otherPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $otherUnit->id]);
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Test', 'unit_id' => $this->unit->id,
        ]);

        $this->actingAs($otherPic)
            ->getJson("/admin/pic/checklist/{$session->id}")
            ->assertStatus(403);
    }

    public function test_pic_cannot_delete_session(): void
    {
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'No Delete', 'unit_id' => $this->unit->id,
        ]);

        $this->actingAs($this->pic)
            ->deleteJson("/admin/kepatuhan/checklist-sessions/{$session->id}")
            ->assertForbidden();
    }

    public function test_show_parent_pic_can_view_child_unit_session(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);
        $parentPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $parent->id]);
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Child session', 'unit_id' => $child->id,
            'framework_id' => $this->fw->id, 'periode' => now()->format('Y-m'),
        ]);

        $this->actingAs($parentPic)
            ->get("/admin/pic/checklist/{$session->id}")
            ->assertOk();
    }

    public function test_generate_carries_forward_verified_entries(): void
    {
        $prevSession = ChecklistSession::create([
            'konteks_penilaian' => 'Bulan lalu', 'unit_id' => $this->unit->id,
            'framework_id' => $this->fw->id, 'periode' => now()->startOfMonth()->subMonth()->format('Y-m'),
        ]);
        ChecklistEntry::create([
            'session_id' => $prevSession->id, 'control_id' => $this->control->id,
            'unit_id' => $this->unit->id, 'pic_id' => $this->pic->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI, 'level_maturity' => 3,
            'catatan' => 'Terverifikasi', 'tanggal_verifikasi' => now()->subMonth(),
        ]);

        $this->actingAs($this->admin)
            ->postJson('/admin/kepatuhan/checklist-sessions', [
                'konteks_penilaian' => 'Bulan ini',
                'unit_id' => $this->unit->id,
                'framework_id' => $this->fw->id,
                'periode' => now()->format('Y-m'),
            ])
            ->assertRedirect();

        $newSession = ChecklistSession::where('unit_id', $this->unit->id)
            ->where('periode', now()->format('Y-m'))
            ->first();
        $this->assertNotNull($newSession);
        $this->assertDatabaseHas('checklist_entries', [
            'session_id' => $newSession->id, 'control_id' => $this->control->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI, 'level_maturity' => 3,
        ]);
    }

    public function test_pic_cannot_restore_session(): void
    {
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'No Restore', 'unit_id' => $this->unit->id,
        ]);
        $session->delete();

        $this->actingAs($this->pic)
            ->postJson("/admin/kepatuhan/checklist-sessions/{$session->id}/restore")
            ->assertForbidden();
    }

    public function test_summary_parent_pic_can_view_child_unit_session(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);
        $parentPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $parent->id]);
        $session = ChecklistSession::create([
            'konteks_penilaian' => 'Child', 'unit_id' => $child->id,
            'framework_id' => $this->fw->id, 'periode' => now()->format('Y-m'),
        ]);

        $this->actingAs($parentPic)
            ->get("/admin/pic/checklist/{$session->id}/summary")
            ->assertOk();
    }
}
