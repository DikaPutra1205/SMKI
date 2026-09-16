<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Framework;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $koordinator;

    private User $picA;

    private User $picB;

    private WorkUnit $unitA;

    private WorkUnit $unitB;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = WorkUnit::factory()->create(['nama' => 'Pusat Pengembangan Ekosistem SDM']);
        $this->unitB = WorkUnit::factory()->create(['nama' => 'Biro Keuangan dan Tata Kelola']);

        $this->admin = User::factory()->create([
            'name' => 'Admin Kepatuhan',
            'role' => 'admin_kepatuhan',
            'unit_id' => $this->unitA->id,
        ]);

        $this->koordinator = User::factory()->create([
            'name' => 'Koordinator SMKI',
            'role' => 'koordinator_smki',
            'unit_id' => $this->unitA->id,
        ]);

        $this->picA = User::factory()->create([
            'name' => 'PIC Unit A',
            'role' => 'pic',
            'unit_id' => $this->unitA->id,
        ]);

        $this->picB = User::factory()->create([
            'name' => 'PIC Unit B',
            'role' => 'pic',
            'unit_id' => $this->unitB->id,
        ]);

        $framework = Framework::factory()->create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        $this->control = Control::factory()->create([
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.5.1',
            'judul' => 'Kebijakan Keamanan Informasi',
        ]);
    }

    public function test_koordinator_or_admin_can_create_new_risk_with_custom_deadline(): void
    {
        $payload = [
            'control_ids' => [$this->control->id],
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_HIGH,
            'pemilik_risiko' => 'Koordinator Keamanan Sistem',
            'rencana_mitigasi' => 'Melakukan pembaruan SOP dan training berkala.',
            'status' => Risk::STATUS_OPEN,
            'deadline' => now()->addDays(14)->toDateString(),
            'catatan_admin' => 'Harap dikoordinasikan dengan PIC Satker.',
        ];

        $response = $this->actingAs($this->koordinator)->postJson('/api/v1/compliance-officer/risks', $payload);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.level_risiko', Risk::LEVEL_HIGH)
            ->assertJsonPath('data.status', Risk::STATUS_OPEN)
            ->assertJsonPath('data.admin_notes', 'Harap dikoordinasikan dengan PIC Satker.');

        $this->assertDatabaseHas('risks', [
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_HIGH,
            'pemilik_risiko' => 'Koordinator Keamanan Sistem',
            'status' => Risk::STATUS_OPEN,
        ]);

        $this->assertDatabaseHas('control_risk', [
            'control_id' => $this->control->id,
        ]);

        // Verify immutable audit log
        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'created',
            'entity_type' => 'Risk',
            'actor_id' => $this->koordinator->id,
        ]);
    }

    public function test_pic_and_admin_can_update_risk_status_and_admin_notes_iteratively(): void
    {
        $risk = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_CRITICAL,
            'pemilik_risiko' => 'Tim Infrastruktur',
            'rencana_mitigasi' => 'Tahap awal identifikasi kerentanan.',
            'status' => Risk::STATUS_OPEN,
            'deadline' => now()->addDays(7)->toDateString(),
        ]);

        // Step 1: PIC updates progress & mitigation
        $picUpdate = [
            'status' => Risk::STATUS_MITIGATED,
            'mitigation_plan' => 'Patching server cloud dan konfigurasi firewall selesai.',
        ];

        $this->actingAs($this->picA)
            ->putJson("/api/v1/compliance-officer/risks/{$risk->id}", $picUpdate)
            ->assertOk()
            ->assertJsonPath('data.status', Risk::STATUS_MITIGATED)
            ->assertJsonPath('data.mitigation_plan', 'Patching server cloud dan konfigurasi firewall selesai.');

        // Step 2: Admin reviews and adds catatan_admin / returns to open for re-verification
        $adminReview = [
            'status' => Risk::STATUS_OPEN,
            'admin_notes' => 'Bukti pengujian penetrasi belum disertakan, mohon lengkapi.',
        ];

        $this->actingAs($this->admin)
            ->putJson("/api/v1/compliance-officer/risks/{$risk->id}", $adminReview)
            ->assertOk()
            ->assertJsonPath('data.status', Risk::STATUS_OPEN)
            ->assertJsonPath('data.admin_notes', 'Bukti pengujian penetrasi belum disertakan, mohon lengkapi.');

        // Verify database state
        $freshRisk = $risk->fresh();
        $this->assertEquals(Risk::STATUS_OPEN, $freshRisk->status);
        $this->assertEquals('Bukti pengujian penetrasi belum disertakan, mohon lengkapi.', $freshRisk->catatan_admin);
        $this->assertEquals('Patching server cloud dan konfigurasi firewall selesai.', $freshRisk->rencana_mitigasi);
    }

    public function test_risk_overdue_and_days_remaining_calculation(): void
    {
        $overdueRisk = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitA->id,
            'status' => Risk::STATUS_OPEN,
            'deadline' => now()->subDays(4)->toDateString(),
        ]);

        $futureRisk = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitA->id,
            'status' => Risk::STATUS_OPEN,
            'deadline' => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/risks');

        $response->assertOk();
        $items = $response->json('data.data');

        $overdueItem = collect($items)->firstWhere('id', $overdueRisk->id);
        $this->assertTrue($overdueItem['is_overdue']);
        $this->assertLessThan(0, $overdueItem['days_remaining']);

        $futureItem = collect($items)->firstWhere('id', $futureRisk->id);
        $this->assertFalse($futureItem['is_overdue']);
        $this->assertGreaterThan(0, $futureItem['days_remaining']);
    }

    public function test_pic_cannot_update_other_unit_risk(): void
    {
        $otherUnitRisk = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitB->id,
            'status' => Risk::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->picA)->putJson("/api/v1/compliance-officer/risks/{$otherUnitRisk->id}", [
            'status' => Risk::STATUS_MITIGATED,
        ]);

        $response->assertForbidden();
    }

    public function test_generic_risk_controller_enforces_unit_scoping_for_pic(): void
    {
        $riskA = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitA->id,
            'pemilik_risiko' => 'Owner Unit A',
        ]);

        $riskB = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitB->id,
            'pemilik_risiko' => 'Owner Unit B',
        ]);

        $response = $this->actingAs($this->picA)->getJson('/api/risks');

        $response->assertOk();
        $items = $response->json('data.data');

        $this->assertTrue(collect($items)->contains('id', $riskA->id));
        $this->assertFalse(collect($items)->contains('id', $riskB->id));
    }

    public function test_generic_risk_controller_prevents_pic_from_modifying_other_unit_risk(): void
    {
        $riskB = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitB->id,
            'status' => Risk::STATUS_OPEN,
        ]);

        // PIC A tries to update Risk of Unit B via generic /api/risks
        $this->actingAs($this->picA)
            ->putJson("/api/risks/{$riskB->id}", [
                'status' => Risk::STATUS_MITIGATED,
            ])
            ->assertForbidden();

        // PIC A tries to delete Risk of Unit B via generic /api/risks
        $this->actingAs($this->picA)
            ->deleteJson("/api/risks/{$riskB->id}")
            ->assertForbidden();
    }

    public function test_auditor_cannot_delete_risks_via_generic_api(): void
    {
        $auditor = User::factory()->create([
            'role' => 'auditor',
            'unit_id' => $this->unitA->id,
        ]);

        $risk = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitA->id,
        ]);

        $this->actingAs($auditor)
            ->deleteJson("/api/risks/{$risk->id}")
            ->assertForbidden();
    }

    public function test_pic_cannot_view_other_unit_risk_via_compliance_officer_api(): void
    {
        $riskB = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitB->id,
            'status' => Risk::STATUS_OPEN,
        ]);

        $this->actingAs($this->picA)
            ->getJson("/api/v1/compliance-officer/risks/{$riskB->id}")
            ->assertForbidden();
    }

    public function test_pic_can_view_own_unit_risk_via_compliance_officer_api(): void
    {
        $riskA = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitA->id,
            'status' => Risk::STATUS_OPEN,
        ]);

        $this->actingAs($this->picA)
            ->getJson("/api/v1/compliance-officer/risks/{$riskA->id}")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $riskA->id);
    }

    public function test_pic_can_update_own_unit_risk_via_generic_api(): void
    {
        $riskA = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitA->id,
            'status' => Risk::STATUS_OPEN,
        ]);

        $this->actingAs($this->picA)
            ->putJson("/api/risks/{$riskA->id}", [
                'status' => Risk::STATUS_MITIGATED,
                'mitigation_plan' => 'Mitigasi unit sendiri via API.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertEquals(Risk::STATUS_MITIGATED, $riskA->fresh()->status);
    }

    public function test_pic_cannot_view_other_unit_risk_via_generic_api(): void
    {
        $riskB = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitB->id,
            'status' => Risk::STATUS_OPEN,
        ]);

        $this->actingAs($this->picA)
            ->getJson("/api/risks/{$riskB->id}")
            ->assertForbidden();
    }

    public function test_pic_cannot_modify_level_deadline_owner_or_admin_notes(): void
    {
        $originalDeadline = now()->addDays(10)->toDateString();
        $risk = Risk::factory()->withControl($this->control)->create([
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_CRITICAL,
            'pemilik_risiko' => 'Original Owner',
            'deadline' => $originalDeadline,
            'catatan_admin' => 'Original Admin Note',
            'rencana_mitigasi' => 'Original Mitigation',
            'status' => Risk::STATUS_OPEN,
        ]);

        $tamperPayload = [
            'status' => Risk::STATUS_MITIGATED,
            'mitigation_plan' => 'Updated Mitigation by PIC',
            'level_risiko' => Risk::LEVEL_LOW,
            'risk_level' => Risk::LEVEL_LOW,
            'pemilik_risiko' => 'Hacked Owner',
            'risk_owner' => 'Hacked Owner',
            'deadline' => now()->addDays(50)->toDateString(),
            'admin_notes' => 'Tampered Admin Note',
            'catatan_admin' => 'Tampered Admin Note',
        ];

        $response = $this->actingAs($this->picA)->putJson("/api/v1/compliance-officer/risks/{$risk->id}", $tamperPayload);

        $response->assertOk();

        $fresh = $risk->fresh();
        // Allowed changes
        $this->assertEquals(Risk::STATUS_MITIGATED, $fresh->status);
        $this->assertEquals('Updated Mitigation by PIC', $fresh->rencana_mitigasi);

        // Restricted fields MUST remain untampered
        $this->assertEquals(Risk::LEVEL_CRITICAL, $fresh->level_risiko);
        $this->assertEquals('Original Owner', $fresh->pemilik_risiko);
        $this->assertEquals($originalDeadline, $fresh->deadline?->toDateString());
        $this->assertEquals('Original Admin Note', $fresh->catatan_admin);
    }
}
