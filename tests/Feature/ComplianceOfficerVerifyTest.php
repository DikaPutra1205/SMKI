<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ComplianceOfficerVerifyTest extends TestCase
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
        $this->unit = WorkUnit::create(['nama' => 'Unit Verify']);
        $this->fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $this->control = $this->fw->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi',
        ]);
        $this->pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unit->id]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
    }

    public function test_verify_single_approve_sets_selesai(): void
    {
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id, 'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'approve',
            ])
            ->assertRedirect();

        $fresh = $entry->fresh();
        $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $fresh->status);
        $this->assertNotNull($fresh->tanggal_verifikasi);
        $this->assertSame($this->admin->id, $fresh->admin_id);
    }

    public function test_verify_single_approve_na_stays_na(): void
    {
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id, 'status' => ChecklistEntry::WORKFLOW_TIDAK_BERLAKU,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'approve',
            ])
            ->assertRedirect();

        $this->assertSame(ChecklistEntry::WORKFLOW_TIDAK_BERLAKU, $entry->fresh()->status);
    }

    public function test_verify_single_reject_sends_to_proses_and_notifies_pic(): void
    {
        Notification::fake();

        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id, 'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'reject',
                'admin_notes' => 'Bukti tidak lengkap',
            ])
            ->assertRedirect();

        $fresh = $entry->fresh();
        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_PROSES, $fresh->status);
        $this->assertNull($fresh->tanggal_verifikasi);
        $this->assertSame('Bukti tidak lengkap', $fresh->catatan_admin);
    }

    public function test_verify_single_reject_requires_admin_notes(): void
    {
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id, 'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'reject',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_notes']);
    }

    public function test_verify_single_sets_level_maturity(): void
    {
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id, 'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'approve',
                'level_maturity' => 5,
            ])
            ->assertRedirect();

        $this->assertSame(5, $entry->fresh()->level_maturity);
    }

    public function test_verify_single_pic_cannot_verify(): void
    {
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id, 'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $this->actingAs($this->pic)
            ->postJson("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'approve',
            ])
            ->assertForbidden();
    }

    public function test_bulk_verify_approve_sets_all_entries(): void
    {
        $e1 = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id, 'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);
        $e2 = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id, 'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $this->actingAs($this->admin)
            ->postJson('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$e1->id, $e2->id],
                'decision' => 'approve',
            ])
            ->assertRedirect();

        $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $e1->fresh()->status);
        $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $e2->fresh()->status);
        $this->assertNotNull($e1->fresh()->tanggal_verifikasi);
    }

    public function test_bulk_verify_reject_sends_to_proses(): void
    {
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id, 'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $this->actingAs($this->admin)
            ->postJson('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$entry->id],
                'decision' => 'reject',
                'admin_notes' => 'Perlu perbaikan',
            ])
            ->assertRedirect();

        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_PROSES, $entry->fresh()->status);
        $this->assertSame('Perlu perbaikan', $entry->fresh()->catatan_admin);
    }

    public function test_bulk_verify_pic_cannot_bulk_verify(): void
    {
        $entry = ChecklistEntry::create([
            'control_id' => $this->control->id, 'unit_id' => $this->unit->id,
            'pic_id' => $this->pic->id, 'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $this->actingAs($this->pic)
            ->postJson('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$entry->id],
                'decision' => 'approve',
            ])
            ->assertForbidden();
    }
}
