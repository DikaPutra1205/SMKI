<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInertiaRoutesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'role' => 'admin_kepatuhan',
        ]);
    }

    public function test_frameworks_crud_via_inertia_routes(): void
    {
        $response = $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/compliance')
            ->post('/admin/kepatuhan/frameworks', [
                'nama' => 'NIST CSF',
                'versi' => 'v2.0',
                'url_file' => 'https://example.com/nist.pdf',
            ]);

        $response->assertRedirect('/admin/kepatuhan/compliance');
        $response->assertSessionHas('flash.type', 'success');
        $this->assertDatabaseHas('frameworks', ['nama' => 'NIST CSF', 'versi' => 'v2.0']);

        $framework = Framework::where('nama', 'NIST CSF')->first();

        $updateResp = $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/compliance')
            ->put("/admin/kepatuhan/frameworks/{$framework->id}", [
                'nama' => 'NIST CSF Updated',
                'versi' => 'v2.0',
            ]);

        $updateResp->assertRedirect('/admin/kepatuhan/compliance');
        $this->assertDatabaseHas('frameworks', ['id' => $framework->id, 'nama' => 'NIST CSF Updated']);

        $delResp = $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/compliance')
            ->delete("/admin/kepatuhan/frameworks/{$framework->id}");

        $delResp->assertRedirect('/admin/kepatuhan/compliance');
        $this->assertSoftDeleted('frameworks', ['id' => $framework->id]);
    }

    public function test_work_units_crud_via_inertia_routes(): void
    {
        $response = $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/compliance')
            ->post('/admin/kepatuhan/work-units', [
                'nama' => 'Divisi Keamanan Siber',
            ]);

        $response->assertRedirect('/admin/kepatuhan/compliance');
        $response->assertSessionHas('flash.type', 'success');
        $this->assertDatabaseHas('work_units', ['nama' => 'Divisi Keamanan Siber']);

        $unit = WorkUnit::where('nama', 'Divisi Keamanan Siber')->first();

        $delResp = $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/compliance')
            ->delete("/admin/kepatuhan/work-units/{$unit->id}");

        $delResp->assertRedirect('/admin/kepatuhan/compliance');
        $this->assertSoftDeleted('work_units', ['id' => $unit->id]);
    }

    public function test_controls_crud_via_inertia_routes(): void
    {
        $framework = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);

        $response = $this->actingAs($this->admin)
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

        $delResp = $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/compliance')
            ->delete("/admin/kepatuhan/controls/{$control->id}");

        $delResp->assertRedirect('/admin/kepatuhan/compliance');
        $this->assertSoftDeleted('controls', ['id' => $control->id]);
    }

    public function test_findings_crud_via_inertia_routes(): void
    {
        $framework = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $control = Control::create(['framework_id' => $framework->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a']);
        $unit = WorkUnit::create(['nama' => 'IT Dept']);
        $pic = User::factory()->create(['role' => 'pic_unit', 'unit_id' => $unit->id]);

        $response = $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/compliance')
            ->post('/admin/kepatuhan/findings', [
                'control_id' => $control->id,
                'unit_id' => $unit->id,
                'pic_id' => $pic->id,
                'kategori' => 'major',
                'status' => 'open',
            ]);

        $response->assertRedirect('/admin/kepatuhan/compliance');
        $response->assertSessionHas('flash.type', 'success');
        $this->assertDatabaseHas('findings', ['control_id' => $control->id, 'kategori' => 'major']);
    }

    public function test_risks_crud_via_inertia_routes(): void
    {
        $framework = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $control = Control::create(['framework_id' => $framework->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a']);

        $response = $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/compliance')
            ->post('/admin/kepatuhan/risks', [
                'control_id' => $control->id,
                'level_risiko' => 'high',
                'pemilik_risiko' => 'CISO',
                'status' => 'open',
            ]);

        $response->assertRedirect('/admin/kepatuhan/compliance');
        $response->assertSessionHas('flash.type', 'success');
        $this->assertDatabaseHas('risks', ['control_id' => $control->id, 'level_risiko' => 'high']);
    }
}
