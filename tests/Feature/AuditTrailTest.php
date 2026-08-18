<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $admin;

    private User $koordinator;

    private User $auditor;

    private User $pic;

    protected function setUp(): void
    {
        parent::setUp();

        $unit = WorkUnit::factory()->create();

        $this->superadmin = User::factory()->create(['role' => 'superadmin', 'unit_id' => $unit->id]);
        $this->admin = User::factory()->create(['role' => 'admin_kepatuhan', 'unit_id' => $unit->id]);
        $this->koordinator = User::factory()->create(['role' => 'koordinator_smki', 'unit_id' => $unit->id]);
        $this->auditor = User::factory()->create(['role' => 'auditor', 'unit_id' => $unit->id]);
        $this->pic = User::factory()->create(['role' => 'pic', 'unit_id' => $unit->id]);
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

    public function test_audit_logs_web_inertia_page_renders_with_props(): void
    {
        AuditLog::factory()->count(3)->create(['actor_id' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->get('/admin/kepatuhan/audit-logs');

        $response->assertOk();
    }
}
