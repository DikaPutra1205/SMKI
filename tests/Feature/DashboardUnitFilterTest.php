<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardUnitFilterTest extends TestCase
{
    use RefreshDatabase;

    private WorkUnit $unitA;

    private WorkUnit $unitB;

    private Framework $iso27001;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = WorkUnit::factory()->create(['nama' => 'Unit A']);
        $this->unitB = WorkUnit::factory()->create(['nama' => 'Unit B']);

        // DashboardAnalyticsService buckets entries by hardcoded framework ids 1 & 2.
        $this->iso27001 = Framework::factory()->create([
            'id' => 1,
            'nama' => 'ISO/IEC 27001',
            'versi' => '2022',
        ]);
        Framework::factory()->create(['id' => 2, 'nama' => 'ISO/IEC 27701', 'versi' => '2019']);

        if (\DB::connection()->getDriverName() === 'pgsql') {
            \DB::statement("SELECT setval(pg_get_serial_sequence('frameworks', 'id'), (SELECT COALESCE(MAX(id), 1) FROM frameworks))");
        }

        $this->seedEntries();
    }

    /**
     * Unit A: 1 of 2 controls compliant (50%). Unit B: 0 of 1 (0%).
     */
    private function seedEntries(): void
    {
        $ctrl1 = Control::factory()->create(['framework_id' => $this->iso27001->id]);
        $ctrl2 = Control::factory()->create(['framework_id' => $this->iso27001->id]);

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

        ChecklistEntry::factory()->create([
            'session_id' => $sessionA->id, 'control_id' => $ctrl1->id,
            'unit_id' => $this->unitA->id, 'status' => ChecklistEntry::WORKFLOW_SELESAI,
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $sessionA->id, 'control_id' => $ctrl2->id,
            'unit_id' => $this->unitA->id, 'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $sessionB->id, 'control_id' => $ctrl1->id,
            'unit_id' => $this->unitB->id, 'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);
    }

    public function test_superadmin_dashboard_scopes_summary_to_unit_id(): void
    {
        $this->withoutVite();

        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get("/admin/superadmin/dashboard?unit_id={$this->unitA->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('superadmin/dashboard')
                ->where('filters.unit_id', $this->unitA->id)
                ->where('summary.overall_completion_rate', 50)
                ->has('workUnits', 2)
            );
    }

    public function test_superadmin_dashboard_without_unit_id_keeps_global_summary(): void
    {
        $this->withoutVite();

        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get('/admin/superadmin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.unit_id', null)
                // Global view averages per-unit selesai counts (ceil(1/2) = 1) over
                // all applicable entries across units (1 belum each => 3) => 33.
                ->where('summary.overall_completion_rate', 33)
                ->has('workUnits', 2)
            );
    }

    public function test_superadmin_dashboard_scopes_trends_to_unit_id(): void
    {
        $this->withoutVite();

        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get("/admin/superadmin/dashboard?unit_id={$this->unitB->id}&months=3")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.months', '3')
                ->has('trends', 3)
                ->where('trends.2.iso27001_rate', 0)
            );
    }

    public function test_admin_dashboard_unit_scoping_still_works(): void
    {
        $this->withoutVite();

        $admin = User::factory()->create(['role' => 'admin_kepatuhan']);

        $this->actingAs($admin)
            ->get("/admin/kepatuhan/dashboard?unit_id={$this->unitA->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin-kepatuhan/dashboard')
                ->where('filters.unit_id', $this->unitA->id)
                ->where('summary.overall_completion_rate', 50)
                ->has('workUnits', 2)
            );
    }

    public function test_auditor_dashboard_unit_scoping_still_works(): void
    {
        $this->withoutVite();

        $auditor = User::factory()->create(['role' => 'auditor']);

        $this->actingAs($auditor)
            ->get("/admin/auditor/dashboard?unit_id={$this->unitA->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auditor/dashboard')
                ->where('filters.unit_id', $this->unitA->id)
                ->where('summary.overall_completion_rate', 50)
            );
    }

    public function test_pic_cannot_request_out_of_scope_unit(): void
    {
        $this->withoutVite();

        $pic = User::factory()->create(['role' => 'pic', 'unit_id' => $this->unitA->id]);

        $this->actingAs($pic)
            ->get("/admin/auditor/dashboard?unit_id={$this->unitB->id}")
            ->assertForbidden();
    }
}
