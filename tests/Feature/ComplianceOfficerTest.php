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
use App\Services\ComplianceOfficerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ComplianceOfficerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $koordinator;

    private User $auditor;

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

        $this->koordinator = User::factory()->create([
            'role' => 'koordinator_smki',
            'unit_id' => $this->unitA->id,
        ]);

        $this->auditor = User::factory()->create([
            'role' => 'auditor',
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
        ]);

        // The closing note lands in the audit trail; the initial admin note
        // field is preserved (no overwrite on status change).
        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $finding->id,
            'catatan' => 'Telah ditutup oleh Admin Kepatuhan setelah audit',
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

    public function test_admin_can_bulk_verify_checklist_entries_clearing_existing_notes_on_approve(): void
    {
        $entry1 = ChecklistEntry::factory()->create([
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            'catatan_admin' => 'Catatan terdahulu unit 1',
        ]);
        $entry2 = ChecklistEntry::factory()->create([
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            'catatan_admin' => 'Catatan terdahulu unit 2',
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry1->id, $entry2->id],
            'decision' => 'approve',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'verified_count' => 2,
                ],
            ]);

        $this->assertEquals(ChecklistEntry::WORKFLOW_SELESAI, $entry1->fresh()->status);
        $this->assertNull($entry1->fresh()->catatan_admin);
        $this->assertEquals(ChecklistEntry::WORKFLOW_SELESAI, $entry2->fresh()->status);
        $this->assertNull($entry2->fresh()->catatan_admin);
        $this->assertNotNull($entry1->fresh()->tanggal_verifikasi);
        $this->assertEquals($this->admin->id, $entry1->fresh()->admin_id);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'ChecklistEntry',
            'aksi' => 'bulk_verify',
            'actor_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_verify_single_entry_without_admin_notes(): void
    {
        $entry = ChecklistEntry::factory()->create([
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            'catatan' => 'Catatan PIC',
        ]);

        $response = $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/checklist/verify')
            ->post("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'approve',
            ]);

        $response->assertRedirect();
        $fresh = $entry->fresh();

        $this->assertNull($fresh->catatan_admin);
        $this->assertNotNull($fresh->tanggal_verifikasi);
        $this->assertEquals(ChecklistEntry::WORKFLOW_SELESAI, $fresh->status);
        $this->assertEquals($this->admin->id, $fresh->admin_id);
    }

    public function test_admin_can_verify_single_entry_with_admin_notes(): void
    {
        $entry = ChecklistEntry::factory()->create([
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $response = $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/checklist/verify')
            ->post("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'reject',
                'admin_notes' => 'Perlu perbaikan bukti unggah',
            ]);

        $response->assertRedirect();
        $fresh = $entry->fresh();

        $this->assertEquals('Perlu perbaikan bukti unggah', $fresh->catatan_admin);
        $this->assertNull($fresh->tanggal_verifikasi);
        $this->assertEquals(ChecklistEntry::WORKFLOW_DALAM_PROSES, $fresh->status);
    }

    public function test_admin_cannot_attach_catatan_when_approving_single_entry(): void
    {
        $entry = ChecklistEntry::factory()->create([
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            'catatan_admin' => 'Catatan lama',
        ]);

        $response = $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/checklist/verify')
            ->post("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'approve',
                'admin_notes' => 'Catatan ini seharusnya diabaikan',
            ]);

        $response->assertRedirect();
        $fresh = $entry->fresh();

        $this->assertNull($fresh->catatan_admin);
        $this->assertEquals(ChecklistEntry::WORKFLOW_SELESAI, $fresh->status);
        $this->assertNotNull($fresh->tanggal_verifikasi);
    }

    public function test_admin_cannot_attach_catatan_when_bulk_approving(): void
    {
        $entry = ChecklistEntry::factory()->create(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry->id],
            'decision' => 'approve',
            'admin_notes' => 'Catatan ini seharusnya diabaikan',
        ]);

        $response->assertOk();
        $fresh = $entry->fresh();

        $this->assertNull($fresh->catatan_admin);
        $this->assertEquals(ChecklistEntry::WORKFLOW_SELESAI, $fresh->status);
    }

    public function test_admin_attaches_catatan_when_bulk_rejecting(): void
    {
        $entry = ChecklistEntry::factory()->create(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry->id],
            'decision' => 'reject',
            'admin_notes' => 'Perlu perbaikan bukti',
        ]);

        $response->assertOk();
        $fresh = $entry->fresh();

        $this->assertEquals('Perlu perbaikan bukti', $fresh->catatan_admin);
        $this->assertEquals(ChecklistEntry::WORKFLOW_DALAM_PROSES, $fresh->status);
        $this->assertNull($fresh->tanggal_verifikasi);
    }

    public function test_pic_cannot_perform_bulk_verification(): void
    {
        $entry = ChecklistEntry::factory()->create();

        $response = $this->actingAs($this->picA)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry->id],
            'decision' => 'approve',
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

    public function test_pic_out_of_scope_unit_filter_is_forbidden(): void
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

        $response->assertForbidden();
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
        $high = Risk::factory()->withControl($this->control)->create([
            'level_risiko' => Risk::LEVEL_HIGH,
            'pemilik_risiko' => 'Budi Santoso',
            'rencana_mitigasi' => 'Pasang WAF dan 2FA',
            'status' => Risk::STATUS_OPEN,
        ]);

        Risk::factory()->withControl($this->control)->create([
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
        $critical = Risk::factory()->withControl($this->control)->create([
            'level_risiko' => Risk::LEVEL_CRITICAL,
            'status' => Risk::STATUS_OPEN,
        ]);

        Risk::factory()->withControl($this->control)->create([
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
        $match = Risk::factory()->withControl($this->control)->create([
            'rencana_mitigasi' => 'Audit keamanan SIKEJAR mendatang',
        ]);

        Risk::factory()->withControl($this->control)->create([
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
        $riskA = Risk::factory()->withControl($this->control)->create([
            'level_risiko' => Risk::LEVEL_HIGH,
        ]);

        $controlB = Control::factory()->create([
            'framework_id' => $this->control->framework_id,
            'kode_klausul' => 'A.99.99',
        ]);
        ChecklistEntry::factory()->create([
            'control_id' => $controlB->id,
            'unit_id' => $this->unitB->id,
        ]);
        $riskB = Risk::factory()->withControl($controlB)->create([
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
        Risk::factory()->withControl($this->control)->create([
            'level_risiko' => Risk::LEVEL_CRITICAL,
        ]);

        $controlB = Control::factory()->create([
            'framework_id' => $this->control->framework_id,
            'kode_klausul' => 'A.99.99',
        ]);
        ChecklistEntry::factory()->create([
            'control_id' => $controlB->id,
            'unit_id' => $this->unitB->id,
        ]);
        Risk::factory()->withControl($controlB)->create([
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
        $risk = Risk::factory()->withControl($this->control)->create([
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
        $risk = Risk::factory()->withControl($this->control)->create([
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
        $risk = Risk::factory()->withControl($this->control)->create();

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
            'decision' => 'approve',
        ])->assertStatus(422);
    }

    public function test_bulk_verify_rejects_unknown_entry_ids(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [999999],
            'decision' => 'approve',
        ])->assertStatus(422);
    }

    public function test_bulk_verify_rejects_invalid_decision(): void
    {
        $entry = ChecklistEntry::factory()->create();

        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry->id],
            'decision' => 'bogus_decision',
        ])->assertStatus(422);
    }

    public function test_bulk_verify_with_mixed_valid_and_invalid_ids_is_all_or_nothing(): void
    {
        $entry = ChecklistEntry::factory()->create(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry->id, 999999],
            'decision' => 'approve',
        ])->assertStatus(422);

        $this->assertEquals(ChecklistEntry::WORKFLOW_DALAM_TINJAUAN, $entry->fresh()->status);
        $this->assertNull($entry->fresh()->tanggal_verifikasi);
    }

    public function test_bulk_verify_with_duplicate_ids_counts_rows_not_input(): void
    {
        $entry = ChecklistEntry::factory()->create(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry->id, $entry->id],
            'decision' => 'approve',
        ]);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.verified_count'));
    }

    public function test_superadmin_can_bulk_verify(): void
    {
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $entry = ChecklistEntry::factory()->create(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $response = $this->actingAs($superadmin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry->id],
            'decision' => 'approve',
        ]);

        $response->assertOk();
        $this->assertEquals(ChecklistEntry::WORKFLOW_SELESAI, $entry->fresh()->status);
        $this->assertEquals($superadmin->id, $entry->fresh()->admin_id);
    }

    public function test_bulk_verify_reject_replaces_existing_admin_notes(): void
    {
        $entry = ChecklistEntry::factory()->create([
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            'catatan_admin' => 'Dokumentasi bukti sudah lengkap',
        ]);

        $this->actingAs($this->admin)->postJson('/api/v1/compliance-officer/bulk-verify', [
            'entry_ids' => [$entry->id],
            'decision' => 'reject',
            'admin_notes' => 'Perlu perbaikan',
        ])->assertOk();

        $this->assertSame('Perlu perbaikan', $entry->fresh()->catatan_admin);
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

        Risk::factory()->withControl($this->control)->create([
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
        $this->assertEquals($this->admin->id, $fresh->admin_id);

        // Note persisted as an audit-trail entry, not by overwriting catatan_admin.
        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $finding->id,
            'catatan' => 'Diverifikasi lewat halaman web',
        ]);
    }

    public function test_web_update_risk_redirects_back_with_flash(): void
    {
        $risk = Risk::factory()->withControl($this->control)->create([
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
        $entry = ChecklistEntry::factory()->create(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/risks')
            ->post('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$entry->id],
                'decision' => 'approve',
            ])
            ->assertRedirect('/admin/kepatuhan/risks')
            ->assertSessionHas('flash.type', 'success');

        $this->assertEquals(ChecklistEntry::WORKFLOW_SELESAI, $entry->fresh()->status);
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
        $risk = Risk::factory()->withControl($this->control)->create([
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
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/kepatuhan/checklist/verify?session_id={$session->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin-kepatuhan/checklist/verify', false)
            ->has('entries.data', 1)
            ->where('entries.data.0.control.kode_klausul', 'A.5.1')
            ->where('entries.data.0.unit.nama', 'Pusat Ekosistem SDM')
            ->where('entries.data.0.pic.name', $this->picA->name)
            ->where('entries.data.0.status', ChecklistEntry::WORKFLOW_DALAM_TINJAUAN)
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
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'tanggal_verifikasi' => now(),
            'admin_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/admin/kepatuhan/checklist/verify?session_id={$session->id}&is_verified=0");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin-kepatuhan/checklist/verify', false)
            ->has('entries.data', 0)
            ->where('filters.is_verified', '0'));
    }

    public function test_pic_cannot_access_bulk_verify_page(): void
    {
        $this->actingAs($this->picA)->get('/admin/kepatuhan/checklist/verify')->assertForbidden();
    }

    public function test_legacy_bulk_verify_url_redirects_to_verify_page(): void
    {
        $session = ChecklistSession::factory()->create(['unit_id' => $this->unitA->id]);

        $this->actingAs($this->admin)
            ->get('/admin/kepatuhan/checklist/bulk-verify')
            ->assertRedirect('/admin/kepatuhan/checklist/verify');

        $this->actingAs($this->admin)
            ->get("/admin/kepatuhan/checklist/bulk-verify?session_id={$session->id}")
            ->assertRedirect("/admin/kepatuhan/checklist/verify?session_id={$session->id}");
    }

    public function test_koordinator_and_auditor_can_view_but_not_verify_checklists(): void
    {
        $session = ChecklistSession::factory()->create(['unit_id' => $this->unitA->id]);
        $entry = ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
        ]);

        foreach ([$this->koordinator, $this->auditor] as $viewer) {
            $this->actingAs($viewer)->get('/admin/kepatuhan/checklist/verify')->assertOk();
            $this->actingAs($viewer)
                ->get("/admin/kepatuhan/checklist/verify?session_id={$session->id}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('admin-kepatuhan/checklist/verify', false)
                    ->has('entries.data', 1));

            $this->actingAs($viewer)
                ->from('/admin/kepatuhan/checklist/verify')
                ->post("/admin/kepatuhan/checklist/verify/{$entry->id}", ['decision' => 'approve'])
                ->assertForbidden();
            $this->actingAs($viewer)
                ->from('/admin/kepatuhan/checklist/verify')
                ->post('/admin/kepatuhan/bulk-verify', [
                    'entry_ids' => [$entry->id],
                    'decision' => 'approve',
                ])
                ->assertForbidden();
        }

        $this->actingAs($this->picA)->get('/admin/kepatuhan/checklist/verify')->assertForbidden();
    }

    public function test_single_verify_allows_catatan_when_decision_changes_status(): void
    {
        $entry = ChecklistEntry::factory()->create(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/checklist/verify')
            ->post("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'approve',
            ])
            ->assertRedirect('/admin/kepatuhan/checklist/verify')
            ->assertSessionHas('flash.type', 'success');

        $this->assertNull($entry->fresh()->catatan_admin);
        $this->assertNotNull($entry->fresh()->tanggal_verifikasi);

        $entry2 = ChecklistEntry::factory()->create(['status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/checklist/verify')
            ->post("/admin/kepatuhan/checklist/verify/{$entry2->id}", [
                'decision' => 'reject',
                'admin_notes' => 'Telah diverifikasi sesuai standar',
            ])
            ->assertRedirect('/admin/kepatuhan/checklist/verify')
            ->assertSessionHas('flash.type', 'success');

        $this->assertEquals(ChecklistEntry::WORKFLOW_DALAM_PROSES, $entry2->fresh()->status);
        $this->assertEquals('Telah diverifikasi sesuai standar', $entry2->fresh()->catatan_admin);
    }

    public function test_single_verify_allows_nullable_catatan_when_decision_keeps_status(): void
    {
        $entry = ChecklistEntry::factory()->create([
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'catatan_admin' => 'Catatan lama',
        ]);

        $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/checklist/verify')
            ->post("/admin/kepatuhan/checklist/verify/{$entry->id}", [
                'decision' => 'approve',
            ])
            ->assertRedirect('/admin/kepatuhan/checklist/verify')
            ->assertSessionHas('flash.type', 'success');

        $this->assertEquals(ChecklistEntry::WORKFLOW_SELESAI, $entry->fresh()->status);
        $this->assertNotNull($entry->fresh()->tanggal_verifikasi);
    }

    public function test_bulk_verify_allows_decision_change_without_catatan(): void
    {
        $entry = ChecklistEntry::factory()->create([
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            'catatan_admin' => 'Catatan lama',
        ]);

        $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/checklist/verify')
            ->post('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$entry->id],
                'decision' => 'approve',
            ])
            ->assertRedirect('/admin/kepatuhan/checklist/verify')
            ->assertSessionHas('flash.type', 'success');

        $fresh = $entry->fresh();
        $this->assertEquals(ChecklistEntry::WORKFLOW_SELESAI, $fresh->status);
        $this->assertNull($fresh->catatan_admin);
    }

    public function test_bulk_verify_allows_nullable_catatan_when_decision_keeps_status(): void
    {
        $entry = ChecklistEntry::factory()->create(['status' => ChecklistEntry::WORKFLOW_SELESAI]);

        $this->actingAs($this->admin)
            ->from('/admin/kepatuhan/checklist/verify')
            ->post('/admin/kepatuhan/bulk-verify', [
                'entry_ids' => [$entry->id],
                'decision' => 'approve',
            ])
            ->assertRedirect('/admin/kepatuhan/checklist/verify')
            ->assertSessionHas('flash.type', 'success');

        $this->assertEquals(ChecklistEntry::WORKFLOW_SELESAI, $entry->fresh()->status);
        $this->assertNotNull($entry->fresh()->tanggal_verifikasi);
    }

    public function test_review_queue_includes_child_unit_items_for_parent_pic(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);
        $parentPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $parent->id]);
        ChecklistEntry::factory()->create(['unit_id' => $child->id, 'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN]);

        $queue = app(ComplianceOfficerService::class)->getReviewQueueEntries($parentPic);

        $this->assertTrue($queue->getCollection()->contains(fn ($e) => (int) $e->unit_id === $child->id));
    }

    public function test_findings_include_child_unit_for_parent_pic(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);
        $parentPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $parent->id]);
        Finding::factory()->create(['unit_id' => $child->id]);

        $findings = app(ComplianceOfficerService::class)->getFindings($parentPic);

        $this->assertTrue($findings->getCollection()->contains(fn ($f) => (int) $f->unit_id === $child->id));
    }

    public function test_explicit_sibling_unit_filter_throws_for_pic(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $sibling = WorkUnit::create(['nama' => 'Sibling']);
        $parentPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $parent->id]);

        $this->expectException(AuthorizationException::class);

        app(ComplianceOfficerService::class)->getFindings($parentPic, ['unit_id' => $sibling->id]);
    }

    public function test_explicit_child_unit_filter_allowed_for_parent_pic(): void
    {
        $parent = WorkUnit::create(['nama' => 'Parent']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $parent->id]);
        $parentPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $parent->id]);
        Finding::factory()->create(['unit_id' => $child->id]);

        $findings = app(ComplianceOfficerService::class)->getFindings($parentPic, ['unit_id' => $child->id]);

        $this->assertTrue($findings->getCollection()->contains(fn ($f) => (int) $f->unit_id === $child->id));
    }
}
