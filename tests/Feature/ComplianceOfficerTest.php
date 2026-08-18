<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceOfficerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $picA;

    private User $picB;

    private WorkUnit $unitA;

    private WorkUnit $unitB;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = WorkUnit::factory()->create(['nama' => 'Pusat Ekosistem SDM']);
        $this->unitB = WorkUnit::factory()->create(['nama' => 'Biro Teknologi Informasi']);

        $this->admin = User::factory()->create([
            'role' => 'admin_kepatuhan',
            'unit_id' => $this->unitA->id,
        ]);

        $this->picA = User::factory()->create([
            'role' => 'pic',
            'unit_id' => $this->unitA->id,
        ]);

        $this->picB = User::factory()->create([
            'role' => 'pic',
            'unit_id' => $this->unitB->id,
        ]);

        $framework = Framework::factory()->create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        $this->control = Control::factory()->create(['framework_id' => $framework->id, 'kode_klausul' => 'A.5.1']);
    }

    public function test_admin_can_view_all_findings_with_sla_and_overdue_calculation(): void
    {
        // 1 overdue finding
        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->subDays(3),
        ]);

        // 1 future finding
        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitB->id,
            'pic_id' => $this->picB->id,
            'kategori' => Finding::KATEGORI_MINOR,
            'status' => Finding::STATUS_IN_PROGRESS,
            'deadline' => now()->addDays(10),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/findings');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    'data' => [
                        '*' => ['id', 'kategori', 'status', 'deadline', 'is_overdue', 'days_remaining', 'control', 'unit'],
                    ],
                ],
            ]);

        $findings = $response->json('data.data');
        $this->assertCount(2, $findings);

        $overdueItem = collect($findings)->firstWhere('kategori', Finding::KATEGORI_MAJOR);
        $this->assertTrue($overdueItem['is_overdue']);
        $this->assertLessThan(0, $overdueItem['days_remaining']);

        $futureItem = collect($findings)->firstWhere('kategori', Finding::KATEGORI_MINOR);
        $this->assertFalse($futureItem['is_overdue']);
        $this->assertGreaterThan(0, $futureItem['days_remaining']);
    }

    public function test_pic_can_only_view_findings_for_their_own_unit(): void
    {
        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
        ]);

        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitB->id,
            'pic_id' => $this->picB->id,
        ]);

        $response = $this->actingAs($this->picA)->getJson('/api/v1/compliance-officer/findings');

        $response->assertOk();
        $findings = $response->json('data.data');

        $this->assertCount(1, $findings);
        $this->assertEquals($this->unitA->id, $findings[0]['unit_id']);
    }

    public function test_admin_can_update_finding_and_it_records_audit_log(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_CLOSED,
            'admin_notes' => 'Telah ditutup oleh Admin Kepatuhan setelah audit',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Temuan audit berhasil diperbarui.',
            ]);

        $this->assertDatabaseHas('findings', [
            'id' => $finding->id,
            'status' => Finding::STATUS_CLOSED,
            'admin_id' => $this->admin->id,
            'catatan_admin' => 'Telah ditutup oleh Admin Kepatuhan setelah audit',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Finding',
            'entity_id' => $finding->id,
            'aksi' => 'update',
            'actor_id' => $this->admin->id,
        ]);
    }

    public function test_pic_cannot_update_finding_belonging_to_another_unit(): void
    {
        $findingB = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitB->id,
            'pic_id' => $this->picB->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->picA)->putJson("/api/v1/compliance-officer/findings/{$findingB->id}", [
            'status' => Finding::STATUS_CLOSED,
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_view_risk_matrix_summary(): void
    {
        Risk::factory()->create(['level_risiko' => Risk::LEVEL_CRITICAL, 'status' => Risk::STATUS_OPEN]);
        Risk::factory()->create(['level_risiko' => Risk::LEVEL_HIGH, 'status' => Risk::STATUS_OPEN]);
        Risk::factory()->create(['level_risiko' => Risk::LEVEL_HIGH, 'status' => Risk::STATUS_MITIGATED]);
        Risk::factory()->create(['level_risiko' => Risk::LEVEL_MEDIUM, 'status' => Risk::STATUS_ACCEPTED]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/risks/matrix');

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'total_risks' => 4,
                    'by_level' => [
                        'critical' => 1,
                        'high' => 2,
                        'medium' => 1,
                        'low' => 0,
                    ],
                    'by_status' => [
                        'open' => 2,
                        'mitigated' => 1,
                        'accepted' => 1,
                    ],
                ],
            ]);
    }

    public function test_admin_can_update_risk_mitigation_and_it_records_audit_log(): void
    {
        $risk = Risk::factory()->create([
            'level_risiko' => Risk::LEVEL_HIGH,
            'status' => Risk::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/risks/{$risk->id}", [
            'status' => Risk::STATUS_MITIGATED,
            'mitigation_plan' => 'Implementasi WAF dan 2FA',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Register risiko berhasil diperbarui.',
            ]);

        $this->assertDatabaseHas('risks', [
            'id' => $risk->id,
            'status' => Risk::STATUS_MITIGATED,
            'rencana_mitigasi' => 'Implementasi WAF dan 2FA',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Risk',
            'entity_id' => $risk->id,
            'aksi' => 'update',
            'actor_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_bulk_verify_checklist_entries(): void
    {
        $entry1 = ChecklistEntry::factory()->create(['status' => ChecklistEntry::STATUS_NON_COMPLIANT]);
        $entry2 = ChecklistEntry::factory()->create(['status' => ChecklistEntry::STATUS_PARTIAL]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry1->id, $entry2->id],
            'status' => ChecklistEntry::STATUS_COMPLIANT,
            'admin_notes' => 'Diverifikasi massal oleh Admin Kepatuhan',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'verified_count' => 2,
                ],
            ]);

        $this->assertEquals(ChecklistEntry::STATUS_COMPLIANT, $entry1->fresh()->status);
        $this->assertEquals(ChecklistEntry::STATUS_COMPLIANT, $entry2->fresh()->status);
        $this->assertNotNull($entry1->fresh()->tanggal_verifikasi);
        $this->assertEquals($this->admin->id, $entry1->fresh()->admin_id);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'ChecklistEntry',
            'aksi' => 'bulk_verify',
            'actor_id' => $this->admin->id,
        ]);
    }

    public function test_pic_cannot_perform_bulk_verification(): void
    {
        $entry = ChecklistEntry::factory()->create();

        $response = $this->actingAs($this->picA)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry->id],
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        $response->assertForbidden();
    }
}
