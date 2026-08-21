<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private WorkUnit $unit;

    private User $superadmin;

    private User $admin;

    private User $koordinator;

    private User $auditor;

    private User $pic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = WorkUnit::factory()->create();

        $this->superadmin = User::factory()->create(['role' => 'superadmin', 'unit_id' => $this->unit->id]);
        $this->admin = User::factory()->create(['role' => 'admin_kepatuhan', 'unit_id' => $this->unit->id]);
        $this->koordinator = User::factory()->create(['role' => 'koordinator_smki', 'unit_id' => $this->unit->id]);
        $this->auditor = User::factory()->create(['role' => 'auditor', 'unit_id' => $this->unit->id]);
        $this->pic = User::factory()->create(['role' => 'pic', 'unit_id' => $this->unit->id]);

        AuditLog::query()->delete();
    }

    public function test_superadmin_admin_and_coordinator_can_view_audit_logs(): void
    {
        AuditLog::factory()->create([
            'actor_id' => $this->admin->id,
            'aksi' => 'verify',
            'entity_type' => 'ChecklistEntry',
            'entity_id' => 10,
        ]);

        // Superadmin
        $response1 = $this->actingAs($this->superadmin)->getJson('/api/v1/audit-logs');
        $response1->assertOk()->assertJsonStructure([
            'status',
            'data' => [
                'data' => [
                    '*' => ['id', 'actor', 'action', 'entity_type', 'entity_id', 'entity_label', 'changes', 'time_ago', 'created_at'],
                ],
            ],
        ]);

        // Admin Kepatuhan
        $response2 = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs');
        $response2->assertOk();

        // Koordinator SMKI
        $response3 = $this->actingAs($this->koordinator)->getJson('/api/v1/audit-logs');
        $response3->assertOk();

        // Auditor
        $response4 = $this->actingAs($this->auditor)->getJson('/api/v1/audit-logs');
        $response4->assertOk();
    }

    public function test_pic_cannot_access_audit_logs_and_receives_forbidden(): void
    {
        $response = $this->actingAs($this->pic)->getJson('/api/v1/audit-logs');
        $response->assertForbidden();

        $statsResponse = $this->actingAs($this->pic)->getJson('/api/v1/audit-logs/stats');
        $statsResponse->assertForbidden();
    }

    public function test_audit_logs_filtering_by_action_and_entity(): void
    {
        AuditLog::factory()->create([
            'actor_id' => $this->admin->id,
            'aksi' => 'create',
            'entity_type' => 'Finding',
        ]);

        AuditLog::factory()->create([
            'actor_id' => $this->admin->id,
            'aksi' => 'bulk_verify',
            'entity_type' => 'ChecklistEntry',
        ]);

        // Filter by action
        $response = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs?action=bulk_verify');
        $response->assertOk();
        $logs = $response->json('data.data');

        $this->assertCount(1, $logs);
        $this->assertEquals('bulk_verify', $logs[0]['action']);
    }

    public function test_audit_logs_handles_boundary_parameters_and_invalid_dates_safely(): void
    {
        AuditLog::factory()->create([
            'actor_id' => $this->admin->id,
            'aksi' => 'verify',
            'entity_type' => 'ChecklistEntry',
        ]);

        // Test with per_page=0 and invalid date string
        $response = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs?per_page=0&start_date=invalid_date_xyz&end_date=999-99-99');
        $response->assertOk();
        $this->assertNotEmpty($response->json('data.data'));
    }

    public function test_audit_logs_case_insensitive_search(): void
    {
        AuditLog::factory()->create([
            'actor_id' => $this->admin->id,
            'aksi' => 'bulk_verify',
            'entity_type' => 'ChecklistEntry',
        ]);

        // Search in uppercase should match lowercase DB record
        $response = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs?search=BULK_VERIFY');
        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_audit_stats_endpoint_returns_valid_aggregations(): void
    {
        AuditLog::factory()->create(['aksi' => 'create', 'entity_type' => 'Finding']);
        AuditLog::factory()->create(['aksi' => 'update', 'entity_type' => 'Risk']);
        AuditLog::factory()->create(['aksi' => 'bulk_verify', 'entity_type' => 'ChecklistEntry']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    'total_logs',
                    'last_24_hours',
                    'by_action' => ['create', 'update', 'delete', 'verify', 'bulk_verify', 'export'],
                    'by_entity',
                ],
            ]);

        $this->assertEquals(3, $response->json('data.total_logs'));
    }

    public function test_observer_records_audit_log_on_model_create_with_snapshot(): void
    {
        $this->actingAs($this->admin);

        $finding = Finding::factory()->create(['unit_id' => $this->unit->id]);

        $log = AuditLog::where('entity_type', 'Finding')
            ->where('entity_id', $finding->id)
            ->where('aksi', 'create')
            ->first();

        $this->assertNotNull($log, 'Observer harus mencatat audit log saat model di-create.');
        $this->assertEquals($this->admin->id, $log->actor_id);
        $this->assertArrayHasKey('data', $log->detail_perubahan);
        $this->assertEquals($finding->id, $log->detail_perubahan['data']['id']);
    }

    public function test_observer_records_audit_log_on_update_with_before_after_diff(): void
    {
        $this->actingAs($this->admin);

        $finding = Finding::factory()->create([
            'unit_id' => $this->unit->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $finding->update(['status' => Finding::STATUS_CLOSED]);

        $log = AuditLog::where('entity_type', 'Finding')
            ->where('entity_id', $finding->id)
            ->where('aksi', 'update')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(['status' => Finding::STATUS_OPEN], $log->detail_perubahan['before']);
        $this->assertEquals(['status' => Finding::STATUS_CLOSED], $log->detail_perubahan['after']);
    }

    public function test_observer_skips_timestamp_only_updates(): void
    {
        $this->actingAs($this->admin);

        $finding = Finding::factory()->create(['unit_id' => $this->unit->id]);
        $finding->touch();

        $this->assertDatabaseMissing('audit_logs', [
            'entity_type' => 'Finding',
            'entity_id' => $finding->id,
            'aksi' => 'update',
        ]);
    }

    public function test_observer_records_audit_log_on_model_delete(): void
    {
        $this->actingAs($this->admin);

        $finding = Finding::factory()->create(['unit_id' => $this->unit->id]);

        $finding->delete();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Finding',
            'entity_id' => $finding->id,
            'aksi' => 'delete',
            'actor_id' => $this->admin->id,
        ]);
    }

    public function test_framework_control_user_and_role_are_audited(): void
    {
        $this->actingAs($this->superadmin);

        // Framework
        $fw = Framework::factory()->create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Framework',
            'entity_id' => $fw->id,
            'aksi' => 'create',
            'actor_id' => $this->superadmin->id,
        ]);

        $fw->update(['nama' => 'ISO 27001:2022 Revisi']);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Framework',
            'entity_id' => $fw->id,
            'aksi' => 'update',
            'actor_id' => $this->superadmin->id,
        ]);

        $fw->delete();
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Framework',
            'entity_id' => $fw->id,
            'aksi' => 'delete',
            'actor_id' => $this->superadmin->id,
        ]);

        // Control
        $control = Control::factory()->create();
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Control',
            'entity_id' => $control->id,
            'aksi' => 'create',
        ]);

        // Role
        $role = Role::create(['name' => 'manager', 'label' => 'Manager']);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Role',
            'entity_id' => $role->id,
            'aksi' => 'create',
        ]);

        // User
        $newUser = User::factory()->create(['unit_id' => $this->unit->id]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'User',
            'entity_id' => $newUser->id,
            'aksi' => 'create',
        ]);
    }

    public function test_user_password_is_not_leaked_in_audit_logs(): void
    {
        $this->actingAs($this->superadmin);

        $user = User::factory()->create([
            'unit_id' => $this->unit->id,
            'password' => 'secret123',
        ]);

        $createLog = AuditLog::where('entity_type', 'User')
            ->where('entity_id', $user->id)
            ->where('aksi', 'create')
            ->first();

        $this->assertNotNull($createLog);
        $this->assertArrayNotHasKey('password', $createLog->detail_perubahan['data'] ?? []);
        $this->assertArrayNotHasKey('remember_token', $createLog->detail_perubahan['data'] ?? []);

        $user->update(['password' => 'new-secret-456']);

        $updateLog = AuditLog::where('entity_type', 'User')
            ->where('entity_id', $user->id)
            ->where('aksi', 'update')
            ->first();

        $this->assertNull($updateLog); // Password was the only change and it's hidden, so no unmasked diff is recorded
    }

    public function test_audit_trail_filters_and_returns_correct_data_for_each_entity_type_using_factories(): void
    {
        $this->actingAs($this->superadmin);

        // Buat data log untuk masing-masing entitas via factory / model creation
        $fw = Framework::factory()->create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);
        $ctrl = Control::factory()->create(['framework_id' => $fw->id, 'kode_klausul' => 'A.5.1', 'judul' => 'Kebijakan']);
        $role = Role::create(['name' => 'auditor_lead', 'label' => 'Lead Auditor']);
        $user = User::factory()->create(['name' => 'Ahmad PIC', 'unit_id' => $this->unit->id]);
        $finding = Finding::factory()->create(['unit_id' => $this->unit->id]);

        $entities = [
            'Framework' => $fw->id,
            'Control' => $ctrl->id,
            'Role' => $role->id,
            'User' => $user->id,
            'Finding' => $finding->id,
        ];

        foreach ($entities as $entityType => $expectedId) {
            $response = $this->getJson("/api/v1/audit-logs?entity_type={$entityType}");
            $response->assertOk();

            $data = $response->json('data.data');
            $this->assertNotEmpty($data, "Audit log untuk entitas {$entityType} harus ada.");

            $matched = collect($data)->firstWhere('entity_id', $expectedId);
            $this->assertNotNull($matched, "Audit log harus memuat entity_id {$expectedId} untuk {$entityType}.");
            $this->assertEquals($entityType, $matched['entity_type']);
            $this->assertEquals('create', $matched['action']);
            $this->assertEquals($this->superadmin->name, $matched['actor']['name']);
            $this->assertIsArray($matched['changes']);
            $this->assertArrayHasKey('data', $matched['changes']);
        }
    }

    public function test_audit_trail_combined_action_and_entity_filter_with_diff_verification(): void
    {
        $this->actingAs($this->superadmin);

        $fw = Framework::factory()->create(['nama' => 'NIST CSF', 'versi' => '1.1']);
        $fw->update(['nama' => 'NIST CSF v2.0', 'versi' => '2.0']);

        $user = User::factory()->create(['name' => 'Bambang', 'unit_id' => $this->unit->id]);
        $user->update(['name' => 'Bambang Sugiharto']);

        // Filter 1: Update pada Framework saja
        $fwUpdateRes = $this->getJson('/api/v1/audit-logs?action=update&entity_type=Framework');
        $fwUpdateRes->assertOk()->assertJsonCount(1, 'data.data');

        $fwLog = $fwUpdateRes->json('data.data.0');
        $this->assertEquals('Framework', $fwLog['entity_type']);
        $this->assertEquals($fw->id, $fwLog['entity_id']);
        $this->assertEquals('update', $fwLog['action']);
        $this->assertEquals('NIST CSF', $fwLog['changes']['before']['nama'] ?? null);
        $this->assertEquals('NIST CSF v2.0', $fwLog['changes']['after']['nama'] ?? null);

        // Filter 2: Update pada User saja
        $userUpdateRes = $this->getJson('/api/v1/audit-logs?action=update&entity_type=User');
        $userUpdateRes->assertOk()->assertJsonCount(1, 'data.data');

        $userLog = $userUpdateRes->json('data.data.0');
        $this->assertEquals('User', $userLog['entity_type']);
        $this->assertEquals($user->id, $userLog['entity_id']);
        $this->assertEquals('update', $userLog['action']);
        $this->assertEquals('Bambang', $userLog['changes']['before']['name'] ?? null);
        $this->assertEquals('Bambang Sugiharto', $userLog['changes']['after']['name'] ?? null);
    }

    public function test_audit_logs_can_be_filtered_by_entity_type(): void
    {
        AuditLog::factory()->create(['aksi' => 'create', 'entity_type' => 'Risk']);
        AuditLog::factory()->create(['aksi' => 'create', 'entity_type' => 'ChecklistEntry']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs?entity_type=Risk');

        $response->assertOk()->assertJsonCount(1, 'data.data');
        $this->assertEquals('Risk', $response->json('data.data.0.entity_type'));
    }

    public function test_audit_logs_can_be_filtered_by_actor_id(): void
    {
        AuditLog::factory()->create(['actor_id' => $this->admin->id]);
        AuditLog::factory()->create(['actor_id' => $this->koordinator->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs?actor_id='.$this->koordinator->id);

        $response->assertOk()->assertJsonCount(1, 'data.data');
        $this->assertEquals($this->koordinator->id, $response->json('data.data.0.actor.id'));
    }

    public function test_audit_logs_can_be_filtered_by_search_on_actor_and_action(): void
    {
        $budi = User::factory()->create(['name' => 'Budi Santoso', 'unit_id' => $this->unit->id]);
        AuditLog::factory()->create(['actor_id' => $budi->id, 'aksi' => 'export', 'entity_type' => 'Report']);
        AuditLog::factory()->create(['actor_id' => $this->koordinator->id, 'aksi' => 'create', 'entity_type' => 'Risk']);

        $byActor = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs?search=Budi');
        $byActor->assertOk()->assertJsonCount(1, 'data.data');
        $this->assertEquals('export', $byActor->json('data.data.0.action'));

        $byAction = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs?search=export');
        $byAction->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_audit_logs_can_be_filtered_by_date_range(): void
    {
        AuditLog::factory()->create(['aksi' => 'create', 'created_at' => now()]);
        AuditLog::factory()->create(['aksi' => 'update', 'created_at' => now()->subDays(30)]);

        $today = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs?start_date='.now()->toDateString());
        $today->assertOk()->assertJsonCount(1, 'data.data');
        $this->assertEquals('create', $today->json('data.data.0.action'));

        $old = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs?end_date='.now()->subDays(1)->toDateString());
        $old->assertOk()->assertJsonCount(1, 'data.data');
        $this->assertEquals('update', $old->json('data.data.0.action'));
    }

    public function test_audit_logs_index_is_paginated_and_respects_per_page(): void
    {
        AuditLog::factory()->count(3)->create(['actor_id' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs?per_page=2');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
        $this->assertEquals(3, $response->json('data.total'));
        $this->assertEquals(2, $response->json('data.last_page'));
        $this->assertEquals(2, $response->json('data.per_page'));
    }

    public function test_system_actor_logs_render_with_system_fallback(): void
    {
        AuditLog::factory()->create([
            'actor_id' => null,
            'aksi' => 'export',
            'entity_type' => 'Report',
            'entity_id' => 99,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs');

        $response->assertOk();
        $log = $response->json('data.data.0');
        $this->assertEquals('Sistem SMKI', $log['actor']['name']);
        $this->assertEquals('system', $log['actor']['role']);
        $this->assertNull($log['actor']['email']);
        $this->assertEquals('Report #99', $log['entity_label']);
    }

    public function test_audit_stats_reports_accurate_aggregations(): void
    {
        AuditLog::factory()->create(['aksi' => 'create', 'entity_type' => 'Finding', 'created_at' => now()]);
        AuditLog::factory()->create(['aksi' => 'export', 'entity_type' => 'Report', 'created_at' => now()]);
        AuditLog::factory()->create(['aksi' => 'create', 'entity_type' => 'Risk', 'created_at' => now()->subDays(3)]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs/stats');

        $response->assertOk();
        $stats = $response->json('data');
        $this->assertEquals(3, $stats['total_logs']);
        $this->assertEquals(2, $stats['last_24_hours']);
        $this->assertEquals(2, $stats['by_action']['create']);
        $this->assertEquals(1, $stats['by_action']['export']);
        $this->assertEquals(0, $stats['by_action']['verify']);
        $this->assertEquals(1, $stats['by_entity']['Finding']);
        $this->assertEquals(1, $stats['by_entity']['Report']);
        $this->assertEquals(1, $stats['by_entity']['Risk']);
    }

    public function test_audit_logs_web_inertia_page_renders_with_props(): void
    {
        AuditLog::factory()->count(2)->create([
            'actor_id' => $this->admin->id,
            'aksi' => 'verify',
            'entity_type' => 'ChecklistEntry',
        ]);

        $manifest = public_path('build/manifest.json');
        $version = file_exists($manifest) ? hash_file('xxh128', $manifest) : '';

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Version', $version)
            ->get('/admin/kepatuhan/audit-logs');

        $response->assertOk();
        $props = $response->json('props');
        $this->assertCount(2, $props['logs']['data']);
        $this->assertEquals(2, $props['stats']['total_logs']);
        $this->assertEquals([], $props['filters']);
        $this->assertCount(5, $props['actors']);
        $this->assertEquals('admin-kepatuhan/audit-logs', $response->json('component'));
    }

    public function test_pic_cannot_access_audit_logs_web_page(): void
    {
        $this->actingAs($this->pic)->get('/admin/kepatuhan/audit-logs')->assertForbidden();
    }
}
