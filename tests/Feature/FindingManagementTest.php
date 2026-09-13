<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use App\Services\ComplianceOfficerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
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

    // ── Raw FindingController tests (GET/POST/PUT/DELETE /api/findings/*) ──────

    public function test_raw_api_index_returns_paginated_findings(): void
    {
        Finding::factory()->count(3)->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
        ]);

        $response = $this->actingAs($this->picA)->getJson('/api/findings');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => ['data', 'current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_raw_api_index_filters_by_status(): void
    {
        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);
        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_CLOSED,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/findings?status=open');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals(Finding::STATUS_OPEN, $response->json('data.data.0.status'));
    }

    public function test_raw_api_store_creates_finding_and_records_history(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->addDays(14)->format('Y-m-d'),
            'catatan' => 'Raw API finding creation.',
        ]);

        $response->assertCreated();
        $findingId = $response->json('data.id');

        $this->assertDatabaseHas('findings', [
            'id' => $findingId,
            'status' => Finding::STATUS_OPEN,
            'kategori' => Finding::KATEGORI_MAJOR,
        ]);

        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $findingId,
            'from_status' => null,
            'to_status' => Finding::STATUS_OPEN,
            'catatan' => 'Raw API finding creation.',
        ]);
    }

    public function test_raw_api_store_rejects_missing_required_fields(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/findings', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['control_id', 'unit_id', 'kategori']);
    }

    public function test_raw_api_store_rejects_invalid_status(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_raw_api_store_rejects_invalid_kategori(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'kategori' => 'critical',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['kategori']);
    }

    public function test_raw_api_show_returns_finding_with_histories(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
        ]);

        $response = $this->actingAs($this->picA)->getJson("/api/findings/{$finding->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $finding->id)
            ->assertJsonStructure(['data' => ['histories']]);
    }

    public function test_raw_api_show_returns_404_for_nonexistent(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/findings/99999');

        $response->assertNotFound();
    }

    public function test_raw_api_update_modifies_finding_attributes(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
            'kategori' => Finding::KATEGORI_MINOR,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/findings/{$finding->id}", [
            'kategori' => Finding::KATEGORI_OBSERVASI,
            'deadline' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $response->assertOk();
        $fresh = $finding->fresh();
        $this->assertEquals(Finding::KATEGORI_OBSERVASI, $fresh->kategori);
    }

    public function test_raw_api_update_records_status_history_on_status_change(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/findings/{$finding->id}", [
            'status' => Finding::STATUS_RESOLVED,
            'catatan' => 'Directly resolved via raw update.',
        ]);

        $response->assertOk();
        $this->assertEquals(Finding::STATUS_RESOLVED, $finding->fresh()->status);

        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $finding->id,
            'from_status' => Finding::STATUS_OPEN,
            'to_status' => Finding::STATUS_RESOLVED,
            'catatan' => 'Directly resolved via raw update.',
        ]);
    }

    public function test_raw_api_update_sets_tanggal_verifikasi_on_close(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_RESOLVED,
        ]);

        $this->actingAs($this->admin)->putJson("/api/findings/{$finding->id}", [
            'status' => Finding::STATUS_CLOSED,
        ]);

        $this->assertNotNull($finding->fresh()->tanggal_verifikasi);
    }

    public function test_raw_api_update_clears_tanggal_verifikasi_when_reopening(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_CLOSED,
            'tanggal_verifikasi' => now(),
        ]);

        $this->actingAs($this->admin)->putJson("/api/findings/{$finding->id}", [
            'status' => Finding::STATUS_IN_PROGRESS,
        ]);

        $this->assertNull($finding->fresh()->tanggal_verifikasi);
    }

    public function test_raw_api_update_status_endpoint_changes_status(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/findings/{$finding->id}/status", [
            'status' => Finding::STATUS_IN_PROGRESS,
        ]);

        $response->assertOk();
        $this->assertEquals(Finding::STATUS_IN_PROGRESS, $finding->fresh()->status);
    }

    public function test_raw_api_update_status_records_history_with_note(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($this->admin)->patchJson("/api/findings/{$finding->id}/status", [
            'status' => Finding::STATUS_RESOLVED,
            'catatan' => 'Status change via PATCH endpoint.',
        ]);

        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $finding->id,
            'from_status' => Finding::STATUS_IN_PROGRESS,
            'to_status' => Finding::STATUS_RESOLVED,
            'catatan' => 'Status change via PATCH endpoint.',
        ]);
    }

    public function test_raw_api_update_status_rejects_invalid_status(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/findings/{$finding->id}/status", [
            'status' => 'bogus',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_raw_api_destroy_deletes_finding(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
        ]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/findings/{$finding->id}");

        $response->assertOk();
        $this->assertSoftDeleted('findings', ['id' => $finding->id]);
    }

    public function test_raw_api_unauthenticated_user_cannot_access_findings(): void
    {
        $response = $this->getJson('/api/findings');

        $response->assertUnauthorized();
    }

    // ── Auth & Policy Enforcement tests ──────────────────────────────────

    public function test_pic_cannot_create_finding_via_raw_api(): void
    {
        $response = $this->actingAs($this->picA)->postJson('/api/findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'kategori' => Finding::KATEGORI_MINOR,
            'status' => Finding::STATUS_OPEN,
        ]);

        $response->assertForbidden();
    }

    public function test_pic_from_other_unit_cannot_update_finding_via_raw_api(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->picB)->putJson("/api/findings/{$finding->id}", [
            'status' => Finding::STATUS_CLOSED,
        ]);

        $response->assertForbidden();
        $this->assertEquals(Finding::STATUS_OPEN, $finding->fresh()->status);
    }

    public function test_pic_from_other_unit_cannot_delete_finding_via_raw_api(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
        ]);

        $response = $this->actingAs($this->picB)->deleteJson("/api/findings/{$finding->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('findings', ['id' => $finding->id, 'deleted_at' => null]);
    }

    public function test_unauthorized_user_cannot_delete_finding_via_raw_api(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
        ]);

        $auditorRole = Role::where('name', 'auditor')->first();
        $stranger = User::factory()->create([
            'role_id' => $auditorRole->id,
            'unit_id' => null,
        ]);

        $response = $this->actingAs($stranger)->deleteJson("/api/findings/{$finding->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('findings', ['id' => $finding->id, 'deleted_at' => null]);
    }

    public function test_auditor_and_koordinator_can_view_findings_but_cannot_create_update_or_delete(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $auditorRole = Role::where('name', 'auditor')->first();
        $koordinatorRole = Role::where('name', 'koordinator_smki')->first();

        $auditor = User::factory()->create(['role_id' => $auditorRole->id]);
        $koordinator = User::factory()->create(['role_id' => $koordinatorRole->id]);

        // 1. Auditor read-only checks
        $this->actingAs($auditor)->getJson('/api/findings')->assertOk();
        $this->actingAs($auditor)->getJson("/api/findings/{$finding->id}")->assertOk();
        $this->actingAs($auditor)->postJson('/api/findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'kategori' => Finding::KATEGORI_MINOR,
        ])->assertForbidden();
        $this->actingAs($auditor)->putJson("/api/findings/{$finding->id}", [
            'status' => Finding::STATUS_IN_PROGRESS,
        ])->assertForbidden();
        $this->actingAs($auditor)->deleteJson("/api/findings/{$finding->id}")->assertForbidden();

        // 2. Koordinator SMKI read-only checks
        $this->actingAs($koordinator)->getJson('/api/findings')->assertOk();
        $this->actingAs($koordinator)->getJson("/api/findings/{$finding->id}")->assertOk();
        $this->actingAs($koordinator)->postJson('/api/findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'kategori' => Finding::KATEGORI_MINOR,
        ])->assertForbidden();
        $this->actingAs($koordinator)->putJson("/api/findings/{$finding->id}", [
            'status' => Finding::STATUS_IN_PROGRESS,
        ])->assertForbidden();
        $this->actingAs($koordinator)->deleteJson("/api/findings/{$finding->id}")->assertForbidden();
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

    public function test_admin_can_delete_finding_via_web_route_with_flash_message(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'admin_id' => $this->admin->id,
            'status' => Finding::STATUS_OPEN,
            'kategori' => Finding::KATEGORI_MAJOR,
        ]);

        $response = $this->actingAs($this->admin)->delete("/temuan/{$finding->id}");

        $response->assertRedirect();
        $response->assertSessionHas('flash.type', 'success');
        $response->assertSessionHas('flash.message', 'Temuan audit berhasil dihapus.');
        $this->assertSoftDeleted('findings', ['id' => $finding->id]);
    }

    public function test_pic_cannot_delete_finding_via_web_route(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'admin_id' => $this->admin->id,
            'status' => Finding::STATUS_OPEN,
            'kategori' => Finding::KATEGORI_MAJOR,
        ]);

        $response = $this->actingAs($this->picA)->delete("/temuan/{$finding->id}");
        $response->assertForbidden();
        $this->assertDatabaseHas('findings', ['id' => $finding->id, 'deleted_at' => null]);
    }

    public function test_compliance_officer_api_destroy_deletes_finding(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'admin_id' => $this->admin->id,
            'status' => Finding::STATUS_OPEN,
            'kategori' => Finding::KATEGORI_MAJOR,
        ]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/compliance-officer/findings/{$finding->id}");

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Temuan audit berhasil dihapus.',
            ]);

        $this->assertSoftDeleted('findings', ['id' => $finding->id]);
    }

    public function test_store_finding_throws_exception_when_unit_has_no_pic_and_no_pic_provided(): void
    {
        $unitWithoutPic = WorkUnit::factory()->create(['nama' => 'Unit Tanpa PIC']);
        $service = app(ComplianceOfficerService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unit kerja yang dipilih belum memiliki PIC terdaftar.');

        $service->storeFinding($this->admin, [
            'control_id' => $this->control->id,
            'unit_id' => $unitWithoutPic->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
        ]);
    }

    public function test_pic_cannot_create_finding_via_service(): void
    {
        $service = app(ComplianceOfficerService::class);

        $this->expectException(AuthorizationException::class);

        $service->storeFinding($this->picA, [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
        ]);
    }

    public function test_update_finding_request_rejects_non_numeric_or_invalid_finding(): void
    {
        $response = $this->actingAs($this->admin)->putJson('/api/findings/non-numeric-id', [
            'status' => Finding::STATUS_IN_PROGRESS,
        ]);

        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    public function test_update_finding_stores_null_when_catatan_empty(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'admin_id' => $this->admin->id,
            'status' => Finding::STATUS_OPEN,
            'catatan_admin' => 'Initial note',
        ]);

        $service = app(ComplianceOfficerService::class);
        $service->updateFinding($this->admin, $finding, [
            'catatan' => '',
        ]);

        // Empty note must NOT wipe the initial admin note — it is preserved.
        $this->assertSame('Initial note', $finding->fresh()->catatan_admin);
    }

    public function test_pic_cannot_change_finding_deadline(): void
    {
        $originalDeadline = now()->addDays(14)->format('Y-m-d');
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'admin_id' => $this->admin->id,
            'status' => Finding::STATUS_OPEN,
            'deadline' => $originalDeadline,
        ]);

        $service = app(ComplianceOfficerService::class);
        $service->updateFinding($this->picA, $finding, [
            'status' => Finding::STATUS_IN_PROGRESS,
            'deadline' => now()->addDays(90)->format('Y-m-d'),
            'catatan' => 'Mencoba mengubah deadline.',
        ]);

        $this->assertEquals($originalDeadline, $finding->fresh()->deadline->format('Y-m-d'));
    }

    public function test_pic_cannot_close_finding(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'admin_id' => $this->admin->id,
            'status' => Finding::STATUS_RESOLVED,
        ]);

        $service = app(ComplianceOfficerService::class);

        $this->expectException(AuthorizationException::class);

        $service->updateFinding($this->picA, $finding, [
            'status' => Finding::STATUS_CLOSED,
            'catatan' => 'PIC mencoba menutup sendiri.',
        ]);
    }

    public function test_web_temuan_loads_initial_finding_when_id_queried(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'admin_id' => $this->admin->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->admin)->get("/temuan?id={$finding->id}");
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin-kepatuhan/temuan')
            ->has('initialFinding')
            ->where('initialFinding.id', $finding->id)
        );
    }

    public function test_web_temuan_loads_soft_deleted_initial_finding_with_warning_flash(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'admin_id' => $this->admin->id,
            'status' => Finding::STATUS_OPEN,
        ]);
        $finding->delete();

        $response = $this->actingAs($this->admin)->get("/temuan?id={$finding->id}");
        $response->assertOk();
        $response->assertSessionHas('flash.type', 'warning');
        $response->assertInertia(fn ($page) => $page
            ->component('admin-kepatuhan/temuan')
            ->has('initialFinding')
            ->where('initialFinding.id', $finding->id)
            ->where('initialFinding.deleted_at', fn ($val) => ! empty($val))
        );
    }

    public function test_admin_can_restore_soft_deleted_finding(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'admin_id' => $this->admin->id,
            'status' => Finding::STATUS_OPEN,
        ]);
        $finding->delete();
        $this->assertSoftDeleted('findings', ['id' => $finding->id]);

        $response = $this->actingAs($this->admin)->post("/temuan/{$finding->id}/restore");
        $response->assertRedirect();
        $response->assertSessionHas('flash.type', 'success');

        $this->assertNotSoftDeleted('findings', ['id' => $finding->id]);
    }
}
