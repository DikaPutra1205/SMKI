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
use Carbon\Carbon;
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

        $this->resetSharedFeatureTables();

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

        // DashboardAnalyticsService buckets entries by hardcoded framework ids 1 & 2.
        $this->iso27001 = Framework::factory()->create([
            'id' => 1,
            'nama' => 'ISO/IEC 27001',
            'versi' => '2022',
        ]);

        $this->iso27701 = Framework::factory()->create([
            'id' => 2,
            'nama' => 'ISO/IEC 27701',
            'versi' => '2019',
        ]);

        // Explicit ids do not advance the Postgres sequence; re-sync the
        // sequence so factory-created frameworks never collide with ids 1 and 2.
        if (\DB::connection()->getDriverName() === 'pgsql') {
            \DB::statement("SELECT setval(pg_get_serial_sequence('frameworks', 'id'), (SELECT COALESCE(MAX(id), 1) FROM frameworks))");
        }
    }

    protected function resetSharedFeatureTables(): void
    {
        AuditLog::query()->delete();
        ChecklistEntry::query()->forceDelete();
        Finding::query()->forceDelete();
        Risk::query()->forceDelete();
        ChecklistSession::query()->forceDelete();
        Control::query()->forceDelete();
        User::query()->delete();
        WorkUnit::query()->forceDelete();
        Framework::query()->forceDelete();
    }

    public function test_admin_receives_complete_dashboard_props_via_inertia(): void
    {
        // Seed some controls and entries
        $ctrl1 = Control::factory()->create(['framework_id' => $this->iso27001->id]);
        $ctrl2 = Control::factory()->create(['framework_id' => $this->iso27701->id]);

        $session = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
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

    public function test_auditor_receives_complete_dashboard_props_via_inertia(): void
    {
        $auditor = User::factory()->create([
            'role' => 'auditor',
            'unit_id' => $this->unitA->id,
        ]);

        $ctrl1 = Control::factory()->create(['framework_id' => $this->iso27001->id]);

        ChecklistEntry::factory()->create([
            'control_id' => $ctrl1->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        $response = $this->actingAs($auditor)->get('/admin/auditor/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('auditor/dashboard')
            ->has('summary')
            ->has('summary.overall_compliance_rate')
            ->has('summary.frameworks_breakdown')
            ->has('summary.findings_summary')
            ->has('summary.risks_summary')
            ->has('trends')
            ->has('recent_activities')
            ->has('workUnits')
        );
    }

    public function test_pic_dashboard_is_strictly_scoped_to_their_unit(): void
    {
        $ctrl1 = Control::factory()->create(['framework_id' => $this->iso27001->id]);

        // Unit A's most-recent session (PIC's unit) — compliant entry.
        $sessionA = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
            'periode' => now()->format('Y-m'),
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $sessionA->id,
            'control_id' => $ctrl1->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        // Unit B's own most-recent session — non-compliant entry. The PIC is
        // scoped away from Unit B, so this must not influence the PIC's 100%.
        $sessionB = ChecklistSession::factory()->create([
            'unit_id' => $this->unitB->id,
            'framework_id' => $this->iso27001->id,
            'periode' => now()->format('Y-m'),
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $sessionB->id,
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

    public function test_summary_compliance_rate_math_per_framework(): void
    {
        $ctrlA = Control::factory()->create(['framework_id' => $this->iso27001->id]);
        $ctrlB = Control::factory()->create(['framework_id' => $this->iso27701->id]);

        // Entries are scoped to the most-recent session per (unit, framework),
        // so they must belong to an explicit, distinct latest session each.
        $sessionA = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
            'periode' => now()->format('Y-m'),
        ]);
        $sessionB = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27701->id,
            'periode' => now()->format('Y-m'),
        ]);

        // Framework 1: 1 compliant + 1 partial + 1 non-compliant => applicable 3, rate round(1/3*100)=33
        foreach ([
            ChecklistEntry::STATUS_COMPLIANT,
            ChecklistEntry::STATUS_PARTIAL,
            ChecklistEntry::STATUS_NON_COMPLIANT,
        ] as $status) {
            ChecklistEntry::factory()->create([
                'session_id' => $sessionA->id,
                'control_id' => $ctrlA->id,
                'unit_id' => $this->unitA->id,
                'status' => $status,
            ]);
        }

        // Framework 2: only NA => applicable 0, rate 0
        ChecklistEntry::factory()->create([
            'session_id' => $sessionB->id,
            'control_id' => $ctrlB->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_NA,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertEquals(33, $data['overall_compliance_rate']);
        $this->assertEquals(33, $data['frameworks_breakdown'][0]['compliance_rate']);
        $this->assertEquals(1, $data['frameworks_breakdown'][0]['compliant_count']);
        $this->assertEquals(1, $data['frameworks_breakdown'][0]['partial_count']);
        $this->assertEquals(1, $data['frameworks_breakdown'][0]['non_compliant_count']);
        $this->assertEquals(0, $data['frameworks_breakdown'][0]['na_count']);
        $this->assertEquals(0, $data['frameworks_breakdown'][1]['compliance_rate']);
        $this->assertEquals(1, $data['frameworks_breakdown'][1]['na_count']);
        $this->assertEquals(2, $data['total_controls_active']);
    }

    public function test_pic_dashboard_uses_most_recent_session_for_unit(): void
    {
        // PIC is locked to unitA via resolveScopedUnitId.
        $this->pic->update(['unit_id' => $this->unitA->id]);

        $ctrl = Control::factory()->create(['framework_id' => $this->iso27001->id]);

        // Older session (compliant) and newest session (non-compliant) for unitA.
        $older = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
            'periode' => now()->subMonth()->format('Y-m'),
        ]);
        $newest = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
            'periode' => now()->format('Y-m'),
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $older->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $newest->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        $response = $this->actingAs($this->pic)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $data = $response->json('data');

        // Only the newest periode session counts: 0 compliant of 1 applicable.
        $this->assertEquals(0, $data['overall_compliance_rate']);
        $this->assertEquals(0, $data['frameworks_breakdown'][0]['compliant_count']);
        $this->assertEquals(1, $data['frameworks_breakdown'][0]['non_compliant_count']);
    }

    public function test_non_unit_role_averages_per_unit_compliant_counts(): void
    {
        // admin_kepatuhan is a non-unit-scoped role (no ?unit_id) => overall is
        // the average of each unit's compliant-control count from its latest session.
        $ctrlA = Control::factory()->create(['framework_id' => $this->iso27001->id]);
        $ctrlB = Control::factory()->create(['framework_id' => $this->iso27001->id]);
        $ctrlC = Control::factory()->create(['framework_id' => $this->iso27001->id]);

        $sessionA = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
            'periode' => now()->format('Y-m'),
        ]);
        $sessionB = ChecklistSession::factory()->create([
            'unit_id' => $this->unitB->id,
            'framework_id' => $this->iso27001->id,
            'periode' => now()->format('Y-m'),
        ]);

        // unitA: 2 of 3 controls compliant; unitB: 1 of 3 compliant.
        foreach ([$ctrlA, $ctrlB] as $c) {
            ChecklistEntry::factory()->create([
                'session_id' => $sessionA->id, 'control_id' => $c->id,
                'unit_id' => $this->unitA->id, 'status' => ChecklistEntry::STATUS_COMPLIANT,
            ]);
        }
        ChecklistEntry::factory()->create([
            'session_id' => $sessionA->id, 'control_id' => $ctrlC->id,
            'unit_id' => $this->unitA->id, 'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $sessionB->id, 'control_id' => $ctrlA->id,
            'unit_id' => $this->unitB->id, 'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $sessionB->id, 'control_id' => $ctrlB->id,
            'unit_id' => $this->unitB->id, 'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $sessionB->id, 'control_id' => $ctrlC->id,
            'unit_id' => $this->unitB->id, 'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $data = $response->json('data');

        // Average of per-unit compliant counts: round((2 + 1) / 2) = 2.
        $this->assertEquals(2, $data['frameworks_breakdown'][0]['compliant_count']);
        // compliance_rate = average of per-unit rates: (2/3 + 1/3) / 2 = 0.5 => 50.
        $this->assertEquals(50, $data['frameworks_breakdown'][0]['compliance_rate']);
    }

    public function test_pic_full_compliance_shows_total_controls_out_of_total(): void
    {
        $this->pic->update(['unit_id' => $this->unitA->id]);

        // All framework-1 controls compliant in unitA's latest session => 118/118.
        // Create via the model to avoid the Control factory's unique kode_klausul
        // generator (it exhausts its pool after many controls in one process).
        $controls = collect(range(1, 118))->map(fn ($i) => Control::create([
            'framework_id' => $this->iso27001->id,
            'kode_klausul' => 'A.5.'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'judul' => "Control {$i}",
            'kategori' => 'annex_a',
        ]));

        $session = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
            'periode' => now()->format('Y-m'),
        ]);

        foreach ($controls as $ctrl) {
            ChecklistEntry::factory()->create([
                'session_id' => $session->id,
                'control_id' => $ctrl->id,
                'unit_id' => $this->unitA->id,
                'status' => ChecklistEntry::STATUS_COMPLIANT,
            ]);
        }

        $response = $this->actingAs($this->pic)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $fw = $response->json('data.frameworks_breakdown')[0];

        $this->assertEquals(118, $fw['total_controls']);
        $this->assertEquals(118, $fw['compliant_count']);
        $this->assertEquals(100, $fw['compliance_rate']);
    }

    public function test_summary_growth_rate_compares_current_vs_last_month(): void
    {
        $ctrl = Control::factory()->create(['framework_id' => $this->iso27001->id]);

        $session = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
            'periode' => now()->format('Y-m'),
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
            'tanggal_input' => now(),
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
            'tanggal_input' => now()->subMonth(),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $data = $response->json('data');

        // current overall rate 50 (1 of 2), previous period rate 0 => growth +50.0
        $this->assertEquals(50, $data['overall_compliance_rate']);
        $this->assertEquals(50.0, $data['growth_from_last_period']);
    }

    public function test_summary_risks_summary_counts_levels_and_excludes_accepted(): void
    {
        $ctrl = Control::factory()->create(['framework_id' => $this->iso27001->id]);

        Risk::factory()->create(['control_id' => $ctrl->id, 'level_risiko' => Risk::LEVEL_CRITICAL, 'status' => Risk::STATUS_OPEN]);
        Risk::factory()->create(['control_id' => $ctrl->id, 'level_risiko' => Risk::LEVEL_HIGH, 'status' => Risk::STATUS_MITIGATED]);
        Risk::factory()->create(['control_id' => $ctrl->id, 'level_risiko' => Risk::LEVEL_MEDIUM, 'status' => Risk::STATUS_ACCEPTED]);
        Risk::factory()->create(['control_id' => $ctrl->id, 'level_risiko' => Risk::LEVEL_LOW, 'status' => Risk::STATUS_OPEN]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $risks = $response->json('data.risks_summary');

        $this->assertEquals(3, $risks['total_active']);
        $this->assertEquals(1, $risks['critical']);
        $this->assertEquals(1, $risks['high']);
        $this->assertEquals(0, $risks['medium']);
        $this->assertEquals(1, $risks['low']);
    }

    public function test_summary_findings_in_progress_and_null_deadline_handling(): void
    {
        $ctrl = Control::factory()->create(['framework_id' => $this->iso27001->id]);

        // In-progress overdue finding must count as active AND overdue
        Finding::factory()->create([
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_IN_PROGRESS,
            'kategori' => Finding::KATEGORI_OBSERVASI,
            'deadline' => now()->subDay(),
        ]);

        // Open finding without deadline: active but never overdue
        Finding::factory()->create([
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN,
            'kategori' => Finding::KATEGORI_MAJOR,
            'deadline' => null,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $findings = $response->json('data.findings_summary');

        $this->assertEquals(2, $findings['total_active']);
        $this->assertEquals(1, $findings['overdue']);
        $this->assertEquals(1, $findings['major']);
        $this->assertEquals(1, $findings['observasi']);
        $this->assertEquals(0, $findings['minor']);
    }

    public function test_summary_finding_deadline_today_is_not_overdue(): void
    {
        $ctrl = Control::factory()->create(['framework_id' => $this->iso27001->id]);

        Finding::factory()->create([
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN,
            'deadline' => Carbon::today(),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.findings_summary.total_active'));
        $this->assertEquals(0, $response->json('data.findings_summary.overdue'));
    }

    public function test_summary_empty_database_returns_zero_rates_and_counts(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertEquals(0, $data['overall_compliance_rate']);
        $this->assertEquals(0.0, $data['growth_from_last_period']);
        $this->assertEquals(0, $data['total_controls_active']);
        $this->assertEquals(0, $data['findings_summary']['total_active']);
        $this->assertEquals(0, $data['findings_summary']['overdue']);
        $this->assertEquals(0, $data['risks_summary']['total_active']);
        $this->assertEquals(0, $data['frameworks_breakdown'][0]['compliance_rate']);
        $this->assertEquals(0, $data['frameworks_breakdown'][1]['compliance_rate']);
    }

    public function test_pic_without_assigned_unit_is_not_scoped_to_any_unit(): void
    {
        $ctrl = Control::factory()->create(['framework_id' => $this->iso27001->id]);

        Finding::factory()->create([
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'kategori' => Finding::KATEGORI_MINOR,
            'status' => Finding::STATUS_OPEN,
        ]);

        Finding::factory()->create([
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitB->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
        ]);

        $unitlessPic = User::factory()->create(['role' => 'pic', 'unit_id' => null]);

        $response = $this->actingAs($unitlessPic)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        // Documents current behavior: a PIC without an assigned unit falls back to
        // an unscoped (global) view and sees findings from every unit.
        $this->assertEquals(2, $response->json('data.findings_summary.total_active'));
    }

    public function test_summary_honors_unit_and_session_filters(): void
    {
        $ctrl = Control::factory()->create(['framework_id' => $this->iso27001->id]);

        // sessionB has the newest periode, so it is the "most-recent session".
        // sessionA is an older period chosen explicitly via ?session_id.
        $sessionA = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
            'periode' => now()->subMonth()->format('Y-m'),
        ]);
        $sessionB = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
            'periode' => now()->format('Y-m'),
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $sessionA->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $sessionB->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        // Explicit ?session_id override wins over the most-recent-session rule.
        $scoped = $this->actingAs($this->admin)
            ->getJson("/api/v1/dashboard/summary?unit_id={$this->unitA->id}&session_id={$sessionA->id}");

        $scoped->assertOk();
        $this->assertEquals(100, $scoped->json('data.overall_compliance_rate'));

        // No session override: the most-recent session (sessionB, non-compliant)
        // is used, not the older compliant sessionA.
        $unscoped = $this->actingAs($this->admin)
            ->getJson("/api/v1/dashboard/summary?unit_id={$this->unitA->id}");

        $unscoped->assertOk();
        $this->assertEquals(0, $unscoped->json('data.overall_compliance_rate'));
    }

    public function test_trends_buckets_by_session_periode_not_tanggal_input(): void
    {
        $ctrl = Control::factory()->create(['framework_id' => $this->iso27001->id]);

        // Session in the current assessment period: 1 compliant + 1 non-compliant.
        $currentSession = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
            'periode' => now()->format('Y-m'),
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $currentSession->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $currentSession->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        // A session two periods ago must NOT bleed into the current or middle bucket.
        $oldSession = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
            'periode' => now()->startOfMonth()->subMonths(2)->format('Y-m'),
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $oldSession->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/trends?months=3');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(3, $data);
        $this->assertEquals(now()->startOfMonth()->subMonths(2)->format('Y-m'), $data[0]['period']);
        $this->assertEquals(now()->startOfMonth()->subMonths(0)->format('Y-m'), $data[2]['period']);

        // Oldest bucket: only its own session (1 compliant of 1 applicable) => 100.
        $this->assertEquals(100, $data[0]['iso27001_rate']);
        // Middle bucket: no session for that period => 0.
        $this->assertEquals(0, $data[1]['iso27001_rate']);
        // Current bucket: its own session only (1 compliant of 2 applicable) => 50.
        $this->assertEquals(50, $data[2]['iso27001_rate']);
        $this->assertEquals(0, $data[2]['iso27701_rate']);
        $this->assertEquals($data[2]['iso27001_rate'], $data[2]['overall_rate']);
    }

    public function test_trends_with_no_entries_returns_zero_rates_for_each_period(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/trends?months=5');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(5, $data);
        foreach ($data as $period) {
            $this->assertEquals(0, $period['iso27001_rate']);
            $this->assertEquals(0, $period['iso27701_rate']);
            $this->assertEquals(0, $period['overall_rate']);
        }
    }

    public function test_trends_shows_per_period_rates_without_carry_over(): void
    {
        $ctrl = Control::first() ?? Control::factory()->create(['framework_id' => $this->iso27001->id]);

        $sessionOld = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
            'periode' => now()->startOfMonth()->subMonths(3)->format('Y-m'),
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $sessionOld->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/trends?months=4');
        $response->assertOk();

        $rates = collect($response->json('data'))->pluck('iso27001_rate', 'period');
        $this->assertEquals(100, $rates[now()->startOfMonth()->subMonths(3)->format('Y-m')]);
        $this->assertEquals(0, $rates[now()->startOfMonth()->subMonths(2)->format('Y-m')]);
        $this->assertEquals(0, $rates[now()->startOfMonth()->subMonths(1)->format('Y-m')]);

        // August session: half compliant (different period, distinct rate).
        $augSession = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
            'periode' => now()->format('Y-m'),
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $augSession->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $augSession->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/trends?months=4');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(4, $data);

        // Map period => rate for easy assertion.
        $rates = collect($data)->keyBy('period')->map(fn ($p) => $p['iso27001_rate']);

        // Each period reports only its own session: May 100, Aug 50, gaps 0.
        $this->assertEquals(100, $rates[now()->subMonths(3)->format('Y-m')]);
        $this->assertEquals(0, $rates[now()->subMonths(2)->format('Y-m')]);
        $this->assertEquals(0, $rates[now()->subMonths(1)->format('Y-m')]);
        $this->assertEquals(50, $rates[now()->format('Y-m')]);
    }

    public function test_unit_comparison_returns_rates_for_all_units_with_zero_data_safe(): void
    {
        $ctrl = Control::factory()->create(['framework_id' => $this->iso27001->id]);
        $session = ChecklistSession::factory()->create([
            'unit_id' => $this->unitA->id,
            'framework_id' => $this->iso27001->id,
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        Finding::factory()->create([
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/unit-comparison');

        $response->assertOk();
        $data = $response->json('data');

        // Ordered by nama: 'Biro Teknologi Informasi' before 'Pusat Ekosistem SDM'
        $this->assertCount(2, $data);
        $this->assertEquals($this->unitB->id, $data[0]['unit_id']);
        $this->assertEquals(0, $data[0]['compliance_rate']);
        $this->assertEquals(0, $data[0]['total_entries']);
        $this->assertEquals(0, $data[0]['open_findings']);

        $this->assertEquals($this->unitA->id, $data[1]['unit_id']);
        $this->assertEquals(50, $data[1]['compliance_rate']);
        $this->assertEquals(2, $data[1]['total_entries']);
        $this->assertEquals(1, $data[1]['compliant_count']);
        $this->assertEquals(1, $data[1]['open_findings']);
    }

    public function test_unit_comparison_pic_only_sees_own_unit(): void
    {
        $response = $this->actingAs($this->pic)->getJson('/api/v1/dashboard/unit-comparison');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals($this->unitA->id, $data[0]['unit_id']);
    }

    public function test_recent_activities_respects_limit_ordering_and_system_fallback(): void
    {
        $logs = [];
        for ($i = 1; $i <= 8; $i++) {
            $logs[] = AuditLog::factory()->create([
                'actor_id' => $i === 1 ? null : $this->admin->id,
                'aksi' => "action-{$i}",
                'entity_type' => 'ChecklistEntry',
                'entity_id' => $i,
                'created_at' => now()->subMinutes(9 - $i),
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/recent-activities?limit=3');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(3, $data);
        $ids = array_column($data, 'id');
        $expectedIds = collect($logs)->sortByDesc('id')->take(3)->pluck('id')->all();
        $this->assertEquals($expectedIds, $ids);

        $oldest = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/recent-activities?limit=8');
        $oldestData = $oldest->json('data');
        $this->assertEquals('Sistem SMKI', $oldestData[7]['actor_name']);
        $this->assertEquals('system', $oldestData[7]['actor_role']);
    }

    public function test_recent_activities_accessible_to_koordinator_auditor_and_superadmin(): void
    {
        AuditLog::factory()->create(['actor_id' => $this->admin->id, 'aksi' => 'verify', 'entity_type' => 'ChecklistEntry', 'entity_id' => 5]);

        foreach (['superadmin', 'koordinator_smki', 'auditor'] as $role) {
            $user = User::factory()->create(['role' => $role, 'unit_id' => $this->unitA->id]);

            $response = $this->actingAs($user)
                ->getJson('/api/v1/dashboard/recent-activities');

            $response->assertOk();
            $this->assertNotEmpty($response->json('data'));
        }
    }

    public function test_web_dashboard_props_carry_exact_summary_values_and_filters(): void
    {
        $ctrl = Control::factory()->create(['framework_id' => $this->iso27001->id]);

        $session = ChecklistSession::factory()->create(['unit_id' => $this->unitA->id, 'framework_id' => $this->iso27001->id]);

        ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/admin/kepatuhan/dashboard?unit_id={$this->unitA->id}&session_id={$session->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin-kepatuhan/dashboard')
            ->where('summary.overall_compliance_rate', 50)
            ->where('filters.unit_id', $this->unitA->id)
            ->where('filters.session_id', $session->id)
            ->has('trends', 6)
            ->has('unit_comparisons', 2)
            ->has('workUnits', 2)
        );
    }

    public function test_superadmin_dashboard_props_include_totals_and_framework_control_counts(): void
    {
        Control::factory()->create(['framework_id' => $this->iso27001->id]);
        Control::factory()->create(['framework_id' => $this->iso27001->id]);
        Control::factory()->create(['framework_id' => $this->iso27701->id]);

        $superadmin = User::factory()->create(['role' => 'superadmin', 'unit_id' => $this->unitA->id]);

        $response = $this->actingAs($superadmin)->get('/admin/superadmin/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('superadmin/dashboard')
            ->where('totalUsers', 3)
            ->where('totalFrameworks', 2)
            ->where('totalControls', 3)
            ->has('frameworks', 2)
            ->has('trends', 6)
        );
    }

    public function test_pic_web_dashboard_trends_are_scoped_to_their_unit(): void
    {
        $ctrl = Control::factory()->create(['framework_id' => $this->iso27001->id]);

        // Own-unit entry (compliant) vs other-unit entry (non-compliant).
        ChecklistEntry::factory()->create([
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitA->id,
            'status' => ChecklistEntry::STATUS_COMPLIANT,
            'tanggal_input' => now(),
        ]);

        ChecklistEntry::factory()->create([
            'control_id' => $ctrl->id,
            'unit_id' => $this->unitB->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
            'tanggal_input' => now(),
        ]);

        $response = $this->actingAs($this->pic)->get('/admin/pic/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('pic/dashboard')
            ->has('trends', 6)
            ->where('trends.5.period', now()->format('Y-m'))
            // Only the PIC's own unit entry counts: 100%, not blended 50%.
            ->where('trends.5.iso27001_rate', 100)
            ->where('trends.5.overall_rate', 100)
        );
    }
}
