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
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pic;

    private WorkUnit $unitA;

    private WorkUnit $unitB;

    private Framework $iso27001;

    private Framework $iso27701;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = WorkUnit::factory()->create(['nama' => 'Pusat Ekosistem SDM']);
        $this->unitB = WorkUnit::factory()->create(['nama' => 'Biro Teknologi Informasi']);

        $this->admin = User::factory()->create([
            'role' => 'admin_kepatuhan',
            'unit_id' => $this->unitA->id,
        ]);

        $this->pic = User::factory()->create([
            'role' => 'pic',
            'unit_id' => $this->unitA->id,
        ]);

        $this->iso27001 = Framework::factory()->create([
            'nama' => 'ISO/IEC 27001',
            'versi' => '2022',
        ]);

        $this->iso27701 = Framework::factory()->create([
            'nama' => 'ISO/IEC 27701',
            'versi' => '2019',
        ]);
    }

    public function test_admin_receives_complete_dashboard_props_via_inertia(): void
    {
        // Seed some controls and entries
        $ctrl1 = Control::factory()->create(['framework_id' => $this->iso27001->id]);
        $ctrl2 = Control::factory()->create(['framework_id' => $this->iso27701->id]);

        $session = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
            'status' => 'verified',
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'control_id' => $ctrl1->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'control_id' => $ctrl2->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        Finding::factory()->create([
            'control_id' => $ctrl2->id,
            'unit_id' => $this->unitA->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->subDays(2), // Overdue
        ]);

        Risk::factory()->create([
            'control_id' => $ctrl1->id,
            'level_risiko' => Risk::LEVEL_HIGH,
            'status' => Risk::STATUS_OPEN,
        ]);

        AuditLog::factory()->create([
            'actor_id' => $this->admin->id,
            'aksi' => 'verify',
            'entity_type' => 'ChecklistEntry',
            'entity_id' => 1,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/kepatuhan/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin-kepatuhan/dashboard')
            ->has('summary')
            ->has('summary.overall_compliance_rate')
            ->has('summary.frameworks_breakdown', 2)
            ->has('summary.findings_summary')
            ->has('summary.risks_summary')
            ->has('trends')
            ->has('unit_comparisons')
            ->has('recent_activities')
            ->has('workUnits')
        );
    }

    public function test_pic_dashboard_is_strictly_scoped_to_their_unit(): void
    {
        $ctrl1 = Control::factory()->create(['framework_id' => $this->iso27001->id]);

        // Entry for Unit A (PIC's unit)
        ChecklistEntry::factory()->create([
            'control_id' => $ctrl1->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        // Entry for Unit B (Other unit)
        ChecklistEntry::factory()->create([
            'control_id' => $ctrl1->id,
            'unit_id' => $this->unitB->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        // Finding for Unit B
        Finding::factory()->create([
            'control_id' => $ctrl1->id,
            'unit_id' => $this->unitB->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
        ]);

        // Finding for Unit A
        Finding::factory()->create([
            'control_id' => $ctrl1->id,
            'unit_id' => $this->unitA->id,
            'kategori' => Finding::KATEGORI_MINOR,
            'status' => Finding::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->pic)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $data = $response->json('data');

        // PIC of Unit A should only see 1 entry compliant = 100% for Unit A
        $this->assertEquals(100, $data['overall_compliance_rate']);
        // PIC should only see 1 finding from Unit A, not the major finding from Unit B
        $this->assertEquals(1, $data['findings_summary']['total_active']);
        $this->assertEquals(1, $data['findings_summary']['minor']);
        $this->assertEquals(0, $data['findings_summary']['major']);
    }

    public function test_overdue_findings_are_accurately_calculated(): void
    {
        $ctrl = Control::factory()->create(['framework_id' => $this->iso27001->id]);

        // Overdue finding (deadline passed & status open)
        Finding::factory()->create([
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->subDay(),
        ]);

        // On-time open finding
        Finding::factory()->create([
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->addDays(5),
        ]);

        // Closed finding past deadline (should NOT be counted as overdue)
        Finding::factory()->create([
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_CLOSED,
            'deadline' => now()->subDays(10),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.findings_summary.total_active'));
        $this->assertEquals(1, $response->json('data.findings_summary.overdue'));
    }

    public function test_api_v1_dashboard_trends_returns_valid_json(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/trends');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['period', 'label', 'iso27001_rate', 'iso27701_rate', 'overall_rate'],
                ],
            ]);
    }

    public function test_api_v1_dashboard_unit_comparison_returns_valid_json(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/unit-comparison');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['unit_id', 'unit_nama', 'compliance_rate', 'total_entries', 'compliant_count', 'open_findings'],
                ],
            ]);
    }

    public function test_api_v1_dashboard_recent_activities_returns_valid_json(): void
    {
        AuditLog::factory()->create([
            'actor_id' => $this->admin->id,
            'aksi' => 'verify',
            'entity_type' => 'ChecklistEntry',
            'entity_id' => 12,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/recent-activities');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'actor_name', 'actor_role', 'action', 'entity_name', 'time_ago', 'created_at'],
                ],
            ]);
    }

    public function test_pic_cannot_access_recent_activities_log(): void
    {
        $response = $this->actingAs($this->pic)->getJson('/api/v1/dashboard/recent-activities');

        $response->assertForbidden()
            ->assertJson([
                'status' => 'error',
                'message' => 'Akses ditolak. Jejak audit hanya dapat diakses oleh Superadmin, Admin Kepatuhan, Koordinator SMKI, dan Auditor.',
            ]);
    }
}
