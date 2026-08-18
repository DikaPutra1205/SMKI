<?php

namespace Tests\Feature;

use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInertiaRoutesTest extends TestCase
{
    use RefreshDatabase;

    private User $adminKepatuhan;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminKepatuhan = User::factory()->create([
            'role' => User::ROLE_ADMIN_KEPATUHAN,
        ]);
        $this->superadmin = User::factory()->create([
            'role' => User::ROLE_SUPERADMIN,
        ]);
    }

    public function test_frameworks_crud_via_superadmin_inertia_routes(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->from('/admin/superadmin/frameworks')
            ->post('/admin/superadmin/frameworks', [
                'nama' => 'NIST CSF',
                'versi' => 'v2.0',
                'url_file' => 'https://example.com/nist.pdf',
            ]);

        $response->assertRedirect('/admin/superadmin/frameworks');
        $response->assertSessionHas('flash.type', 'success');
        $this->assertDatabaseHas('frameworks', ['nama' => 'NIST CSF', 'versi' => 'v2.0']);

        $framework = Framework::where('nama', 'NIST CSF')->first();

        $updateResp = $this->actingAs($this->superadmin)
            ->from('/admin/superadmin/frameworks')
            ->patch("/admin/superadmin/frameworks/{$framework->id}", [
                'nama' => 'NIST CSF Updated',
                'versi' => 'v2.0',
            ]);

        $updateResp->assertRedirect('/admin/superadmin/frameworks');
        $this->assertDatabaseHas('frameworks', ['id' => $framework->id, 'nama' => 'NIST CSF Updated']);

        $delResp = $this->actingAs($this->superadmin)
            ->from('/admin/superadmin/frameworks')
            ->delete("/admin/superadmin/frameworks/{$framework->id}");

        $delResp->assertRedirect('/admin/superadmin/frameworks');
        $this->assertSoftDeleted('frameworks', ['id' => $framework->id]);
    }

    public function test_controls_crud_via_inertia_routes(): void
    {
        $framework = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);

        $response = $this->actingAs($this->adminKepatuhan)
            ->from('/admin/kepatuhan/compliance')
            ->post('/admin/kepatuhan/controls', [
                'framework_id' => $framework->id,
                'kode_klausul' => 'A.99.1',
                'judul' => 'Test Inertia Control',
                'kategori' => 'annex_a',
                'deskripsi' => 'Deskripsi uji coba',
            ]);

        $response->assertRedirect('/admin/kepatuhan/compliance');
        $response->assertSessionHas('flash.type', 'success');
        $this->assertDatabaseHas('controls', ['kode_klausul' => 'A.99.1', 'framework_id' => $framework->id]);

        $control = Control::where('kode_klausul', 'A.99.1')->where('framework_id', $framework->id)->first();

        $updateResp = $this->actingAs($this->adminKepatuhan)
            ->from('/admin/kepatuhan/compliance')
            ->put("/admin/kepatuhan/controls/{$control->id}", [
                'framework_id' => $framework->id,
                'kode_klausul' => 'A.99.1',
                'judul' => 'Test Inertia Control Updated',
                'kategori' => 'annex_a',
            ]);

        $updateResp->assertRedirect('/admin/kepatuhan/compliance');
        $updateResp->assertSessionHas('flash.type', 'success');
        $this->assertDatabaseHas('controls', ['id' => $control->id, 'judul' => 'Test Inertia Control Updated']);

        $delResp = $this->actingAs($this->adminKepatuhan)
            ->from('/admin/kepatuhan/compliance')
            ->delete("/admin/kepatuhan/controls/{$control->id}");

        $delResp->assertRedirect('/admin/kepatuhan/compliance');
        $this->assertSoftDeleted('controls', ['id' => $control->id]);
    }

    public function test_checklist_sessions_crud_via_inertia_routes(): void
    {
        $framework = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $control = Control::create(['framework_id' => $framework->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a']);
        $unit = WorkUnit::create(['nama' => 'IT Dept']);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);

        // 1. Create Session
        $res = $this->actingAs($this->adminKepatuhan)
            ->from('/admin/kepatuhan/compliance')
            ->post('/admin/kepatuhan/checklist-sessions', [
                'konteks_penilaian' => 'Audit Q1 2026',
                'periode' => 'Q1 2026',
                'unit_id' => $unit->id,
                'framework_id' => $framework->id,
            ]);

        $res->assertRedirect('/admin/kepatuhan/compliance');
        $res->assertSessionHas('flash.type', 'success');
        $this->assertDatabaseHas('checklist_sessions', ['konteks_penilaian' => 'Audit Q1 2026']);

        $session = ChecklistSession::where('konteks_penilaian', 'Audit Q1 2026')->first();

        // 2. Update Session
        $updateRes = $this->actingAs($this->adminKepatuhan)
            ->from('/admin/kepatuhan/compliance')
            ->put("/admin/kepatuhan/checklist-sessions/{$session->id}", [
                'konteks_penilaian' => 'Audit Q1 2026 - Updated',
                'periode' => 'Q1 2026',
                'catatan' => 'Updated via Web',
            ]);

        $updateRes->assertRedirect('/admin/kepatuhan/compliance');
        $this->assertSame('Audit Q1 2026 - Updated', $session->fresh()->konteks_penilaian);
        $this->assertSame('Updated via Web', $session->fresh()->catatan);

        // 3. Delete & Restore Session
        $delRes = $this->actingAs($this->adminKepatuhan)
            ->from('/admin/kepatuhan/compliance')
            ->delete("/admin/kepatuhan/checklist-sessions/{$session->id}");

        $delRes->assertRedirect('/admin/kepatuhan/compliance');
        $this->assertSoftDeleted('checklist_sessions', ['id' => $session->id]);

        $restoreRes = $this->actingAs($this->adminKepatuhan)
            ->from('/admin/kepatuhan/compliance')
            ->post("/admin/kepatuhan/checklist-sessions/{$session->id}/restore");

        $restoreRes->assertRedirect('/admin/kepatuhan/compliance');
        $this->assertNotSoftDeleted('checklist_sessions', ['id' => $session->id]);
    }
}
