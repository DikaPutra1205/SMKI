<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FindingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $picA;

    protected User $picB;

    protected WorkUnit $unitA;

    protected WorkUnit $unitB;

    protected Control $control;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $adminRole = Role::where('name', 'admin_kepatuhan')->first();
        $picRole = Role::where('name', 'pic')->first();

        $this->unitA = WorkUnit::factory()->create(['nama' => 'Biro Teknologi Informasi']);
        $this->unitB = WorkUnit::factory()->create(['nama' => 'Biro SDM']);

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'unit_id' => null,
        ]);

        $this->picA = User::factory()->create([
            'role_id' => $picRole->id,
            'unit_id' => $this->unitA->id,
        ]);

        $this->picB = User::factory()->create([
            'role_id' => $picRole->id,
            'unit_id' => $this->unitB->id,
        ]);

        $framework = Framework::factory()->create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        $this->control = Control::factory()->create([
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.8.8',
            'judul' => 'Manajemen Kerentanan Teknis',
        ]);
    }

    public function test_admin_kepatuhan_can_create_finding_and_records_initial_status_history(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->addDays(14)->format('Y-m-d'),
            'catatan' => 'Ditemukan server web belum menerapkan security update patch CVE-2026-1122.',
        ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'Temuan audit baru berhasil diterbitkan.',
            ]);

        $findingId = $response->json('data.id');

        $this->assertDatabaseHas('findings', [
            'id' => $findingId,
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'admin_id' => $this->admin->id,
            'status' => Finding::STATUS_OPEN,
            'kategori' => Finding::KATEGORI_MAJOR,
        ]);

        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $findingId,
            'user_id' => $this->admin->id,
            'from_status' => null,
            'to_status' => Finding::STATUS_OPEN,
            'catatan' => 'Ditemukan server web belum menerapkan security update patch CVE-2026-1122.',
        ]);
    }

    public function test_pic_cannot_create_initial_finding(): void
    {
        $response = $this->actingAs($this->picA)->postJson('/api/v1/compliance-officer/findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
            'catatan' => 'PIC mencoba membuat temuan awal sendiri.',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('findings', 0);
    }

    public function test_relevant_pic_can_update_status_and_requires_notes(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        // Step 1: Open -> In Progress
        $response1 = $this->actingAs($this->picA)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'PIC Biro TI telah menjadwalkan maintenance window untuk patching.',
        ]);

        $response1->assertOk();
        $this->assertEquals(Finding::STATUS_IN_PROGRESS, $finding->fresh()->status);

        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $finding->id,
            'user_id' => $this->picA->id,
            'from_status' => Finding::STATUS_OPEN,
            'to_status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'PIC Biro TI telah menjadwalkan maintenance window untuk patching.',
        ]);

        // Step 2: In Progress -> Resolved
        $response2 = $this->actingAs($this->picA)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_RESOLVED,
            'catatan' => 'Patch telah selesai diinstal dan scan ulang menunjukkan zero high vulnerabilities.',
        ]);

        $response2->assertOk();
        $this->assertEquals(Finding::STATUS_RESOLVED, $finding->fresh()->status);

        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $finding->id,
            'user_id' => $this->picA->id,
            'from_status' => Finding::STATUS_IN_PROGRESS,
            'to_status' => Finding::STATUS_RESOLVED,
            'catatan' => 'Patch telah selesai diinstal dan scan ulang menunjukkan zero high vulnerabilities.',
        ]);
    }

    public function test_pic_from_other_unit_cannot_update_finding_status(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        // PIC B (Unit B) tries to update Finding of Unit A
        $response = $this->actingAs($this->picB)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'PIC B mencoba mengubah status temuan unit A.',
        ]);

        $response->assertForbidden();
        $this->assertEquals(Finding::STATUS_OPEN, $finding->fresh()->status);
    }

    public function test_admin_can_verify_and_close_resolved_finding(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_RESOLVED,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_CLOSED,
            'catatan' => 'Admin telah meninjau bukti hasil scanning ulang. Temuan diverifikasi tuntas dan ditutup.',
        ]);

        $response->assertOk();
        $fresh = $finding->fresh();

        $this->assertEquals(Finding::STATUS_CLOSED, $fresh->status);
        $this->assertNotNull($fresh->tanggal_verifikasi);
        $this->assertEquals($this->admin->id, $fresh->admin_id);

        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $finding->id,
            'user_id' => $this->admin->id,
            'from_status' => Finding::STATUS_RESOLVED,
            'to_status' => Finding::STATUS_CLOSED,
            'catatan' => 'Admin telah meninjau bukti hasil scanning ulang. Temuan diverifikasi tuntas dan ditutup.',
        ]);
    }

    public function test_status_can_move_backward_with_notes(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_CLOSED,
            'tanggal_verifikasi' => now()->subDay(),
            'admin_id' => $this->admin->id,
        ]);

        // Admin reopens closed finding back to In Progress because a recurrence was discovered
        $response = $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'Ditemukan kembali anomali konfigurasi, status dikembalikan ke In Progress untuk diperiksa ulang.',
        ]);

        $response->assertOk();
        $fresh = $finding->fresh();

        $this->assertEquals(Finding::STATUS_IN_PROGRESS, $fresh->status);
        $this->assertNull($fresh->tanggal_verifikasi);

        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $finding->id,
            'user_id' => $this->admin->id,
            'from_status' => Finding::STATUS_CLOSED,
            'to_status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'Ditemukan kembali anomali konfigurasi, status dikembalikan ke In Progress untuk diperiksa ulang.',
        ]);
    }

    public function test_web_routes_create_and_update_with_flash_message(): void
    {
        // Admin creates via web POST
        $createResponse = $this->actingAs($this->admin)->post('/temuan', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'kategori' => Finding::KATEGORI_MINOR,
            'status' => Finding::STATUS_OPEN,
            'catatan' => 'Temuan web request.',
        ]);

        $createResponse->assertRedirect();
        $this->assertDatabaseHas('findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'kategori' => Finding::KATEGORI_MINOR,
        ]);

        $finding = Finding::latest('id')->first();

        // Relevant PIC updates via web PUT
        $updateResponse = $this->actingAs($this->picA)->put("/temuan/{$finding->id}", [
            'status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'Tindak lanjut web request oleh PIC.',
        ]);

        $updateResponse->assertRedirect();
        $this->assertEquals(Finding::STATUS_IN_PROGRESS, $finding->fresh()->status);
    }
}
