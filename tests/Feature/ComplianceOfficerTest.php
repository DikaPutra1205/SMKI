<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_admin_can_update_finding_and_it_records_single_audit_log_without_duplicates(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $initialLogsCount = AuditLog::where('entity_type', 'Finding')->where('entity_id', $finding->id)->count();

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

        // Assert exactly 1 update log created (no duplicate writes)
        $newLogsCount = AuditLog::where('entity_type', 'Finding')
            ->where('entity_id', $finding->id)
            ->where('aksi', 'update')
            ->count();
        $this->assertEquals(1, $newLogsCount);
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

    public function test_admin_can_update_risk_mitigation_and_it_records_single_audit_log(): void
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

        $updateLogsCount = AuditLog::where('entity_type', 'Risk')
            ->where('entity_id', $risk->id)
            ->where('aksi', 'update')
            ->count();
        $this->assertEquals(1, $updateLogsCount);
    }

    public function test_admin_can_bulk_verify_checklist_entries_preserving_existing_notes(): void
    {
        $entry1 = ChecklistEntry::factory()->create([
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
            'catatan_admin' => 'Catatan terdahulu unit 1',
        ]);
        $entry2 = ChecklistEntry::factory()->create([
            'status' => ChecklistEntry::STATUS_PARTIAL,
            'catatan_admin' => 'Catatan terdahulu unit 2',
        ]);

        // Bulk verify without admin_notes should preserve existing notes
        $response = $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry1->id, $entry2->id],
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'verified_count' => 2,
                ],
            ]);

        $this->assertEquals(ChecklistEntry::STATUS_COMPLIANT, $entry1->fresh()->status);
        $this->assertEquals('Catatan terdahulu unit 1', $entry1->fresh()->catatan_admin);
        $this->assertEquals(ChecklistEntry::STATUS_COMPLIANT, $entry2->fresh()->status);
        $this->assertEquals('Catatan terdahulu unit 2', $entry2->fresh()->catatan_admin);
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

    public function test_findings_are_ordered_with_closed_last(): void
    {
        $closed = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_CLOSED,
            'deadline' => now()->subDays(1),
        ]);

        $open = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/findings');

        $response->assertOk();
        $ids = array_column($response->json('data.data'), 'id');

        $this->assertEquals([$open->id, $closed->id], $ids);
    }

    public function test_findings_can_be_filtered_by_status(): void
    {
        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $inProgress = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/findings?status=in_progress');

        $response->assertOk();
        $findings = $response->json('data.data');

        $this->assertCount(1, $findings);
        $this->assertEquals($inProgress->id, $findings[0]['id']);
    }

    public function test_findings_can_be_filtered_by_category(): void
    {
        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'kategori' => Finding::KATEGORI_MAJOR,
        ]);

        $minor = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'kategori' => Finding::KATEGORI_MINOR,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/findings?category=minor');

        $response->assertOk();
        $findings = $response->json('data.data');

        $this->assertCount(1, $findings);
        $this->assertEquals($minor->id, $findings[0]['id']);
    }

    public function test_findings_can_be_filtered_by_overdue(): void
    {
        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->subDays(3),
        ]);

        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/findings?is_overdue=1');

        $response->assertOk();
        $findings = $response->json('data.data');

        $this->assertCount(1, $findings);
        $this->assertTrue($findings[0]['is_overdue']);
    }

    public function test_findings_can_be_searched_by_admin_notes_or_control(): void
    {
        $match = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'catatan_admin' => 'Temuan unik terkait SIKEJAR',
        ]);

        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'catatan_admin' => 'Catatan lainnya',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/findings?search=SIKEJAR');

        $response->assertOk();
        $findings = $response->json('data.data');

        $this->assertCount(1, $findings);
        $this->assertEquals($match->id, $findings[0]['id']);
    }

    public function test_findings_index_is_paginated_with_per_page(): void
    {
        Finding::factory()->count(6)->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/findings?per_page=2');

        $response->assertOk();
        $this->assertEquals(6, $response->json('data.total'));
        $this->assertEquals(2, $response->json('data.per_page'));
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_findings_can_be_filtered_by_kategori_alias(): void
    {
        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'kategori' => Finding::KATEGORI_MAJOR,
        ]);

        $minor = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'kategori' => Finding::KATEGORI_MINOR,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/findings?kategori=minor');

        $response->assertOk();
        $findings = $response->json('data.data');

        $this->assertCount(1, $findings);
        $this->assertEquals($minor->id, $findings[0]['id']);
    }

    public function test_findings_overdue_filter_false_excludes_overdue_items(): void
    {
        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->subDays(3),
        ]);

        $future = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_IN_PROGRESS,
            'deadline' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/findings?is_overdue=0');

        $response->assertOk();
        $findings = $response->json('data.data');

        $this->assertCount(1, $findings);
        $this->assertEquals($future->id, $findings[0]['id']);
    }

    public function test_pic_unit_id_filter_is_ignored_and_stays_scoped_to_own_unit(): void
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

        $response = $this->actingAs($this->picA)->getJson('/api/v1/compliance-officer/findings?unit_id='.$this->unitB->id);

        $response->assertOk();
        $findings = $response->json('data.data');

        $this->assertCount(1, $findings);
        $this->assertEquals($this->unitA->id, $findings[0]['unit_id']);
    }

    public function test_show_finding_returns_formatted_sla_resource(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->subDays(3),
            'catatan_admin' => 'Catatan khusus dari admin',
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/v1/compliance-officer/findings/{$finding->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $finding->id,
                'category' => Finding::KATEGORI_MAJOR,
                'admin_notes' => 'Catatan khusus dari admin',
                'is_overdue' => true,
            ]);

        $this->assertLessThan(0, $response->json('data.days_remaining'));
        $this->assertArrayHasKey('verified_at', $response->json('data'));
        $this->assertNotNull($response->json('data.control'));
        $this->assertNotNull($response->json('data.unit'));
    }

    public function test_show_finding_returns_404_for_missing_finding(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/findings/999999')
            ->assertNotFound();
    }

    public function test_pic_can_view_finding_of_their_own_unit(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
        ]);

        $this->actingAs($this->picA)->getJson("/api/v1/compliance-officer/findings/{$finding->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $finding->id]);
    }

    public function test_pic_cannot_view_finding_of_another_unit(): void
    {
        $findingB = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitB->id,
            'pic_id' => $this->picB->id,
        ]);

        $this->actingAs($this->picA)->getJson("/api/v1/compliance-officer/findings/{$findingB->id}")
            ->assertForbidden();
    }

    public function test_admin_can_update_finding_deadline_and_category(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'kategori' => Finding::KATEGORI_MINOR,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'category' => Finding::KATEGORI_MAJOR,
            'deadline' => '2026-12-31',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'category' => Finding::KATEGORI_MAJOR,
            ]);

        $this->assertStringStartsWith('2026-12-31', $response->json('data.deadline'));

        $this->assertSame(Finding::KATEGORI_MAJOR, $finding->fresh()->kategori);
        $this->assertStringStartsWith('2026-12-31', (string) $finding->fresh()->deadline);
    }

    public function test_closing_finding_sets_verification_timestamp_and_admin(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_CLOSED,
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('data.verified_at'));

        $fresh = $finding->fresh();
        $this->assertEquals(Finding::STATUS_CLOSED, $fresh->status);
        $this->assertNotNull($fresh->tanggal_verifikasi);
        $this->assertEquals($this->admin->id, $fresh->admin_id);
    }

    public function test_update_finding_rejects_invalid_status(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
        ]);

        $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => 'bogus_status',
        ])->assertStatus(422);
    }

    public function test_update_finding_returns_404_for_missing_finding(): void
    {
        $this->actingAs($this->admin)->putJson('/api/v1/compliance-officer/findings/999999', [
            'status' => Finding::STATUS_CLOSED,
        ])->assertNotFound();
    }

    public function test_pic_can_update_finding_of_their_own_unit(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $this->actingAs($this->picA)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_IN_PROGRESS,
        ])->assertOk();

        $this->assertEquals(Finding::STATUS_IN_PROGRESS, $finding->fresh()->status);
    }

    public function test_admin_can_list_risks_with_english_aliases_and_filters(): void
    {
        $high = Risk::factory()->create([
            'control_id' => $this->control->id,
            'level_risiko' => Risk::LEVEL_HIGH,
            'pemilik_risiko' => 'Budi Santoso',
            'rencana_mitigasi' => 'Pasang WAF dan 2FA',
            'status' => Risk::STATUS_OPEN,
        ]);

        Risk::factory()->create([
            'control_id' => $this->control->id,
            'level_risiko' => Risk::LEVEL_LOW,
            'pemilik_risiko' => 'Siti Aminah',
            'status' => Risk::STATUS_MITIGATED,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/risks?risk_level=high');

        $response->assertOk();
        $risks = $response->json('data.data');

        $this->assertCount(1, $risks);
        $this->assertEquals($high->id, $risks[0]['id']);
        $this->assertEquals(Risk::LEVEL_HIGH, $risks[0]['risk_level']);
        $this->assertEquals('Budi Santoso', $risks[0]['risk_owner']);
        $this->assertEquals('Pasang WAF dan 2FA', $risks[0]['mitigation_plan']);
    }

    public function test_risks_can_be_filtered_by_level_risiko_alias_and_status(): void
    {
        $critical = Risk::factory()->create([
            'control_id' => $this->control->id,
            'level_risiko' => Risk::LEVEL_CRITICAL,
            'status' => Risk::STATUS_OPEN,
        ]);

        Risk::factory()->create([
            'control_id' => $this->control->id,
            'level_risiko' => Risk::LEVEL_CRITICAL,
            'status' => Risk::STATUS_ACCEPTED,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/risks?level_risiko=critical&status=open');

        $response->assertOk();
        $risks = $response->json('data.data');

        $this->assertCount(1, $risks);
        $this->assertEquals($critical->id, $risks[0]['id']);
    }

    public function test_risks_can_be_searched_by_owner_or_mitigation_plan(): void
    {
        $match = Risk::factory()->create([
            'control_id' => $this->control->id,
            'rencana_mitigasi' => 'Audit keamanan SIKEJAR mendatang',
        ]);

        Risk::factory()->create([
            'control_id' => $this->control->id,
            'rencana_mitigasi' => 'Pelatihan karyawan umum',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/risks?search=SIKEJAR');

        $response->assertOk();
        $risks = $response->json('data.data');

        $this->assertCount(1, $risks);
        $this->assertEquals($match->id, $risks[0]['id']);
    }

    public function test_pic_risks_are_scoped_to_their_unit_via_checklist_entries(): void
    {
        ChecklistEntry::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
        ]);
        $riskA = Risk::factory()->create([
            'control_id' => $this->control->id,
            'level_risiko' => Risk::LEVEL_HIGH,
        ]);

        $controlB = Control::factory()->create(['framework_id' => $this->control->framework_id]);
        ChecklistEntry::factory()->create([
            'control_id' => $controlB->id,
            'unit_id' => $this->unitB->id,
        ]);
        $riskB = Risk::factory()->create([
            'control_id' => $controlB->id,
            'level_risiko' => Risk::LEVEL_LOW,
        ]);

        $response = $this->actingAs($this->picA)->getJson('/api/v1/compliance-officer/risks');

        $response->assertOk();
        $risks = $response->json('data.data');

        $this->assertCount(1, $risks);
        $this->assertEquals($riskA->id, $risks[0]['id']);
        $this->assertNotEquals($riskB->id, $risks[0]['id']);
    }

    public function test_risk_matrix_for_pic_is_scoped_to_their_unit(): void
    {
        ChecklistEntry::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
        ]);
        Risk::factory()->create([
            'control_id' => $this->control->id,
            'level_risiko' => Risk::LEVEL_CRITICAL,
        ]);

        $controlB = Control::factory()->create(['framework_id' => $this->control->framework_id]);
        ChecklistEntry::factory()->create([
            'control_id' => $controlB->id,
            'unit_id' => $this->unitB->id,
        ]);
        Risk::factory()->create([
            'control_id' => $controlB->id,
            'level_risiko' => Risk::LEVEL_HIGH,
        ]);

        $response = $this->actingAs($this->picA)->getJson('/api/v1/compliance-officer/risks/matrix');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'total_risks' => 1,
                    'by_level' => [
                        'critical' => 1,
                        'high' => 0,
                        'medium' => 0,
                        'low' => 0,
                    ],
                ],
            ]);
    }

    public function test_show_risk_returns_formatted_resource(): void
    {
        $risk = Risk::factory()->create([
            'control_id' => $this->control->id,
            'level_risiko' => Risk::LEVEL_MEDIUM,
            'pemilik_risiko' => 'Tim IT',
            'rencana_mitigasi' => 'Backup rutin',
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/v1/compliance-officer/risks/{$risk->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $risk->id,
                'risk_level' => Risk::LEVEL_MEDIUM,
                'risk_owner' => 'Tim IT',
                'mitigation_plan' => 'Backup rutin',
            ]);
    }

    public function test_show_risk_returns_404_for_missing_risk(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/compliance-officer/risks/999999')
            ->assertNotFound();
    }

    public function test_admin_can_update_risk_level_and_owner(): void
    {
        $risk = Risk::factory()->create([
            'control_id' => $this->control->id,
            'level_risiko' => Risk::LEVEL_LOW,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/risks/{$risk->id}", [
            'risk_level' => Risk::LEVEL_CRITICAL,
            'risk_owner' => 'Kepala Biro TI',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'risk_level' => Risk::LEVEL_CRITICAL,
                'risk_owner' => 'Kepala Biro TI',
            ]);

        $this->assertDatabaseHas('risks', [
            'id' => $risk->id,
            'level_risiko' => Risk::LEVEL_CRITICAL,
            'pemilik_risiko' => 'Kepala Biro TI',
        ]);
    }

    public function test_update_risk_rejects_invalid_level_and_status(): void
    {
        $risk = Risk::factory()->create([
            'control_id' => $this->control->id,
        ]);

        $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/risks/{$risk->id}", [
            'risk_level' => 'extreme',
        ])->assertStatus(422);

        $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/risks/{$risk->id}", [
            'status' => 'closed',
        ])->assertStatus(422);
    }

    public function test_update_risk_returns_404_for_missing_risk(): void
    {
        $this->actingAs($this->admin)->putJson('/api/v1/compliance-officer/risks/999999', [
            'status' => Risk::STATUS_MITIGATED,
        ])->assertNotFound();
    }

    public function test_bulk_verify_rejects_empty_entry_ids(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [],
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ])->assertStatus(422);
    }

    public function test_bulk_verify_rejects_unknown_entry_ids(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [999999],
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ])->assertStatus(422);
    }

    public function test_bulk_verify_rejects_invalid_status(): void
    {
        $entry = ChecklistEntry::factory()->create();

        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry->id],
            'status' => 'bogus_status',
        ])->assertStatus(422);
    }

    public function test_bulk_verify_with_mixed_valid_and_invalid_ids_is_all_or_nothing(): void
    {
        $entry = ChecklistEntry::factory()->create(['status' => ChecklistEntry::STATUS_NON_COMPLIANT]);

        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry->id, 999999],
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ])->assertStatus(422);

        $this->assertEquals(ChecklistEntry::STATUS_NON_COMPLIANT, $entry->fresh()->status);
        $this->assertNull($entry->fresh()->tanggal_verifikasi);
    }

    public function test_bulk_verify_with_duplicate_ids_counts_rows_not_input(): void
    {
        $entry = ChecklistEntry::factory()->create(['status' => ChecklistEntry::STATUS_PARTIAL]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry->id, $entry->id],
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.verified_count'));
    }

    public function test_superadmin_can_bulk_verify(): void
    {
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $entry = ChecklistEntry::factory()->create(['status' => ChecklistEntry::STATUS_NON_COMPLIANT]);

        $response = $this->actingAs($superadmin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry->id],
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        $response->assertOk();
        $this->assertEquals(ChecklistEntry::STATUS_COMPLIANT, $entry->fresh()->status);
        $this->assertEquals($superadmin->id, $entry->fresh()->admin_id);
    }

    public function test_bulk_verify_without_notes_preserves_existing_admin_notes(): void
    {
        $entry = ChecklistEntry::factory()->create([
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
            'catatan_admin' => 'Dokumentasi bukti sudah lengkap',
        ]);

        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry->id],
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ])->assertOk();

        $this->assertSame('Dokumentasi bukti sudah lengkap', $entry->fresh()->catatan_admin);
    }

    public function test_web_findings_page_renders_with_expected_props(): void
    {
        // committed public/build/manifest.json predates findings/risks pages;
        // withoutVite() skips manifest resolution so Inertia props remain testable
        $this->withoutVite();

        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/kepatuhan/temuan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin-kepatuhan/temuan', false)
                ->has('findings.data')
                ->has('workUnits')
                ->has('filters'));
    }

    public function test_web_risks_page_renders_with_matrix_and_props(): void
    {
        $this->withoutVite();

        Risk::factory()->create([
            'control_id' => $this->control->id,
            'level_risiko' => Risk::LEVEL_HIGH,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/kepatuhan/risks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin-kepatuhan/risks', false)
                ->has('risks.data')
                ->has('matrix.by_level')
                ->has('matrix.by_status')
                ->has('workUnits')
                ->has('filters'));
    }

    public function test_web_update_finding_redirects_back_with_flash(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/temuan')
            ->put("/admin/kepatuhan/temuan/{$finding->id}", [
                'status' => Finding::STATUS_CLOSED,
                'admin_notes' => 'Diverifikasi lewat halaman web',
            ])
            ->assertRedirect('/admin/kepatuhan/temuan')
            ->assertSessionHas('flash.type', 'success');

        $fresh = $finding->fresh();
        $this->assertEquals(Finding::STATUS_CLOSED, $fresh->status);
        $this->assertEquals('Diverifikasi lewat halaman web', $fresh->catatan_admin);
        $this->assertEquals($this->admin->id, $fresh->admin_id);
    }

    public function test_web_update_risk_redirects_back_with_flash(): void
    {
        $risk = Risk::factory()->create([
            'control_id' => $this->control->id,
            'level_risiko' => Risk::LEVEL_HIGH,
            'status' => Risk::STATUS_OPEN,
        ]);

        $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/risks')
            ->put("/admin/kepatuhan/risks/{$risk->id}", [
                'status' => Risk::STATUS_MITIGATED,
                'mitigation_plan' => 'WAF aktif',
            ])
            ->assertRedirect('/admin/kepatuhan/risks')
            ->assertSessionHas('flash.type', 'success');

        $fresh = $risk->fresh();
        $this->assertEquals(Risk::STATUS_MITIGATED, $fresh->status);
        $this->assertEquals('WAF aktif', $fresh->rencana_mitigasi);
    }

    public function test_web_bulk_verify_redirects_back_with_flash(): void
    {
        $entry = ChecklistEntry::factory()->create(['status' => ChecklistEntry::STATUS_NON_COMPLIANT]);

        $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/risks')
            ->post('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$entry->id],
                'status' => ChecklistEntry::STATUS_COMPLIANT,
                'admin_notes' => 'Telah diverifikasi sesuai standar SMKI',
            ])
            ->assertRedirect('/admin/kepatuhan/risks')
            ->assertSessionHas('flash.type', 'success');

        $this->assertEquals(ChecklistEntry::STATUS_COMPLIANT, $entry->fresh()->status);
        $this->assertNotNull($entry->fresh()->tanggal_verifikasi);
    }

    public function test_web_guest_is_redirected_to_login_for_compliance_officer_pages(): void
    {
        $this->get('/admin/kepatuhan/temuan')->assertRedirect(route('login'));
        $this->get('/admin/kepatuhan/risks')->assertRedirect(route('login'));
    }

    public function test_web_pic_cannot_update_finding_of_another_unit(): void
    {
        $findingB = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitB->id,
            'pic_id' => $this->picB->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $this->actingAs($this->picA)
            ->put("/admin/kepatuhan/temuan/{$findingB->id}", [
                'status' => Finding::STATUS_CLOSED,
            ])
            ->assertForbidden();

        $this->assertEquals(Finding::STATUS_OPEN, $findingB->fresh()->status);
    }

    public function test_update_finding_writes_single_audit_log_entry(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_CLOSED,
            'admin_notes' => 'Ditutup',
        ])->assertOk();

        $count = AuditLog::where('entity_type', 'Finding')
            ->where('entity_id', $finding->id)
            ->where('aksi', 'update')
            ->count();

        $this->assertSame(1, $count, 'Satu mutasi temuan harus menghasilkan tepat satu entri audit, bukan duplikat.');
    }

    public function test_update_risk_writes_single_audit_log_entry(): void
    {
        $risk = Risk::factory()->create([
            'control_id' => $this->control->id,
            'status' => Risk::STATUS_OPEN,
        ]);

        $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/risks/{$risk->id}", [
            'status' => Risk::STATUS_MITIGATED,
        ])->assertOk();

        $count = AuditLog::where('entity_type', 'Risk')
            ->where('entity_id', $risk->id)
            ->where('aksi', 'update')
            ->count();

        $this->assertSame(1, $count, 'Satu mutasi risiko harus menghasilkan tepat satu entri audit, bukan duplikat.');
    }

    public function test_admin_can_view_bulk_verify_page_with_review_queue_entries(): void
    {
        $session = ChecklistSession::factory()->create(['unit_id' => $this->unitA->id]);
        ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/kepatuhan/checklist/bulk-verify?session_id={$session->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin-kepatuhan/checklist/bulk-verify', false)
            ->has('entries.data', 1)
            ->where('entries.data.0.control.kode_klausul', 'A.5.1')
            ->where('entries.data.0.unit.nama', 'Pusat Ekosistem SDM')
            ->where('entries.data.0.pic.name', $this->picA->name)
            ->where('entries.data.0.status', ChecklistEntry::STATUS_NON_COMPLIANT)
            ->has('workUnits')
            ->has('filters'));
    }

    public function test_bulk_verify_page_filters_unverified_entries_by_default_filter(): void
    {
        $session = ChecklistSession::factory()->create(['unit_id' => $this->unitA->id]);
        ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
            'tanggal_verifikasi' => now(),
            'admin_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/admin/kepatuhan/checklist/bulk-verify?session_id={$session->id}&is_verified=0");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin-kepatuhan/checklist/bulk-verify', false)
            ->has('entries.data', 0)
            ->where('filters.is_verified', '0'));
    }

    public function test_pic_cannot_access_bulk_verify_page(): void
    {
        $this->actingAs($this->picA)->get('/admin/kepatuhan/checklist/bulk-verify')->assertForbidden();
    }

    public function test_single_verify_requires_catatan_when_status_changes(): void
    {
        $entry = ChecklistEntry::factory()->create(['status' => ChecklistEntry::STATUS_NON_COMPLIANT]);

        // Attempting to change status to compliant without notes should fail with validation error
        $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/checklist/verify')
            ->post("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'status' => 'compliant',
                'admin_notes' => '',
            ])
            ->assertRedirect('/admin/kepatuhan/checklist/verify')
            ->assertSessionHasErrors('admin_notes');

        // With notes, it must succeed
        $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/checklist/verify')
            ->post("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'status' => 'compliant',
                'admin_notes' => 'Telah diverifikasi sesuai standar',
            ])
            ->assertRedirect('/admin/kepatuhan/checklist/verify')
            ->assertSessionHas('flash.type', 'success');

        $this->assertEquals(ChecklistEntry::STATUS_COMPLIANT, $entry->fresh()->status);
        $this->assertEquals('Telah diverifikasi sesuai standar', $entry->fresh()->catatan_admin);
    }

    public function test_single_verify_allows_nullable_catatan_when_status_unchanged(): void
    {
        $entry = ChecklistEntry::factory()->create([
            'status' => ChecklistEntry::STATUS_COMPLIANT,
            'catatan_admin' => 'Catatan lama',
        ]);

        $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/checklist/verify')
            ->post("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'status' => 'compliant',
                'admin_notes' => '',
            ])
            ->assertRedirect('/admin/kepatuhan/checklist/verify')
            ->assertSessionHas('flash.type', 'success');

        $this->assertEquals(ChecklistEntry::STATUS_COMPLIANT, $entry->fresh()->status);
        $this->assertNotNull($entry->fresh()->tanggal_verifikasi);
    }

    public function test_bulk_verify_requires_catatan_when_status_changes(): void
    {
        $entry = ChecklistEntry::factory()->create(['status' => ChecklistEntry::STATUS_NON_COMPLIANT]);

        // Changing status in bulk verify without admin_notes fails
        $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/checklist/verify')
            ->post('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$entry->id],
                'status' => 'compliant',
                'admin_notes' => '',
            ])
            ->assertRedirect('/admin/kepatuhan/checklist/verify')
            ->assertSessionHasErrors('admin_notes');

        // Providing admin_notes succeeds
        $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/checklist/verify')
            ->post('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$entry->id],
                'status' => 'compliant',
                'admin_notes' => 'Catatan verifikasi kepatuhan massal',
            ])
            ->assertRedirect('/admin/kepatuhan/checklist/verify')
            ->assertSessionHas('flash.type', 'success');

        $this->assertEquals(ChecklistEntry::STATUS_COMPLIANT, $entry->fresh()->status);
    }

    public function test_bulk_verify_allows_nullable_catatan_when_status_unchanged(): void
    {
        $entry = ChecklistEntry::factory()->create(['status' => ChecklistEntry::STATUS_COMPLIANT]);

        $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/checklist/verify')
            ->post('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$entry->id],
                'status' => 'compliant',
                'admin_notes' => '',
            ])
            ->assertRedirect('/admin/kepatuhan/checklist/verify')
            ->assertSessionHas('flash.type', 'success');

        $this->assertEquals(ChecklistEntry::STATUS_COMPLIANT, $entry->fresh()->status);
        $this->assertNotNull($entry->fresh()->tanggal_verifikasi);
    }
}
