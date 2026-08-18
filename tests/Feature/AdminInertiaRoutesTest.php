<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
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
            'role' => 'superadmin',
        ]);
    }

    public function test_frameworks_crud_via_inertia_routes(): void
    {
        $response = $this->actingAs($this->admin)
            ->from('/admin/superadmin/dashboard')
            ->post('/admin/superadmin/frameworks', [
                'nama' => 'NIST CSF',
                'versi' => 'v2.0',
                'url_file' => 'https://example.com/nist.pdf',
            ]);

        $response->assertRedirect('/admin/superadmin/dashboard');
        $response->assertSessionHas('flash.type', 'success');
        $this->assertDatabaseHas('frameworks', ['nama' => 'NIST CSF', 'versi' => 'v2.0']);

        $framework = Framework::where('nama', 'NIST CSF')->first();

        $updateResp = $this->actingAs($this->admin)
            ->from('/admin/superadmin/dashboard')
            ->patch("/admin/superadmin/frameworks/{$framework->id}", [
                'nama' => 'NIST CSF Updated',
                'versi' => 'v2.0',
            ]);

        $updateResp->assertRedirect('/admin/superadmin/dashboard');
        $this->assertDatabaseHas('frameworks', ['id' => $framework->id, 'nama' => 'NIST CSF Updated']);

        $delResp = $this->actingAs($this->admin)
            ->from('/admin/superadmin/dashboard')
            ->delete("/admin/superadmin/frameworks/{$framework->id}");

        $delResp->assertRedirect('/admin/superadmin/dashboard');
        $this->assertSoftDeleted('frameworks', ['id' => $framework->id]);
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

        $updateResp = $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/compliance')
            ->put("/admin/kepatuhan/controls/{$control->id}", [
                'framework_id' => $framework->id,
                'kode_klausul' => 'A.99.1',
                'judul' => 'Test Inertia Control Updated',
                'kategori' => 'klausul_4_10',
            ]);

        $updateResp->assertRedirect('/admin/kepatuhan/compliance');
        $this->assertDatabaseHas('controls', ['id' => $control->id, 'judul' => 'Test Inertia Control Updated']);

        $delResp = $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/compliance')
            ->delete("/admin/kepatuhan/controls/{$control->id}");

        $delResp->assertRedirect('/admin/kepatuhan/compliance');
        $this->assertSoftDeleted('controls', ['id' => $control->id]);
    }
}
