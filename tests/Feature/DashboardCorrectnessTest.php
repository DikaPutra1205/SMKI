<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Permission;
use App\Models\Risk;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use App\Services\DashboardAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Spec §9 dashboard correctness in one place: rate math and bounds,
 * latest-session supersede rules, trends buckets and clamps, findings/risks
 * summary invariants, PIC subtree scoping, recent-activities gates, query-count
 * shape, plus the QA runtime top-up (unit_comparisons vs breakdown divergence,
 * growth baseline window, session drill-down, API/web months parity,
 * superadmin-dashboard gate, overdue scoping, year-boundary buckets).
 *
 * Tests pinning CURRENT behaviour of a spec deviation keep their
 * `..._documents_gap_...` / `..._documented_gap_...` names so the gap stays
 * visible while the suite stays green.
 * Merged from DashboardSpecAuditTest + DashboardQa9RuntimeTest.
 * (DashboardPerformanceTest stays separate: p95 timing, not correctness.)
 */
class DashboardCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pic;

    private WorkUnit $unitA;

    private WorkUnit $unitB;

    private WorkUnit $childOfA;

    private Framework $fw27001;

    private Framework $fw27701;

    private Framework $fwOther;

    private DashboardAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = WorkUnit::factory()->create(['nama' => 'Unit A']);
        $this->unitB = WorkUnit::factory()->create(['nama' => 'Unit B']);
        $this->childOfA = WorkUnit::factory()->create(['nama' => 'Anak A', 'parent_id' => $this->unitA->id]);

        $this->admin = User::factory()->create(['role' => 'admin_kepatuhan', 'unit_id' => $this->unitA->id]);
        $this->pic = User::factory()->create(['role' => 'pic', 'unit_id' => $this->unitA->id]);

        // getTrends() hardcodes framework ids 1 & 2, so pin them explicitly and
        // add a third framework to prove what the hardcoding does.
        $this->fw27001 = Framework::factory()->create(['id' => 1, 'nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        $this->fw27701 = Framework::factory()->create(['id' => 2, 'nama' => 'ISO/IEC 27701', 'versi' => '2019']);
        $this->fwOther = Framework::factory()->create(['id' => 3, 'nama' => 'ISO/IEC 42001', 'versi' => '2023']);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('frameworks', 'id'), (SELECT COALESCE(MAX(id), 1) FROM frameworks))");
        }

        $this->service = app(DashboardAnalyticsService::class);
    }

    /**
     * Create one session for (unit, framework, periode) with $counts entries.
     *
     * getSummary() counts DISTINCT control_id per status bucket, so every entry
     * gets its own control unless $controls is passed explicitly.
     *
     * @param  array<string,int>  $counts  status => how many entries
     * @param  array<int,Control>|null  $controls
     */
    private function sessionWithEntries(WorkUnit $unit, Framework $framework, string $periode, array $counts, ?array $controls = null, ?string $tanggalInput = null): ChecklistSession
    {
        $session = ChecklistSession::factory()->create([
            'unit_id' => $unit->id,
            'framework_id' => $framework->id,
            'periode' => $periode,
        ]);

        $needed = array_sum($counts);
        $controls ??= Control::factory()->count($needed)
            ->create(['framework_id' => $framework->id])->all();

        $i = 0;
        foreach ($counts as $status => $n) {
            for ($j = 0; $j < $n; $j++) {
                ChecklistEntry::factory()->create(array_merge([
                    'session_id' => $session->id,
                    'control_id' => $controls[$i]->id,
                    'unit_id' => $unit->id,
                    'status' => $status,
                ], $tanggalInput !== null ? ['tanggal_input' => $tanggalInput] : []));
                $i++;
            }
        }

        return $session;
    }

    /** @return array<string,mixed> */
    private function summaryFor(User $user, ?int $unitId = null, ?int $sessionId = null, ?int $months = null): array
    {
        return $this->service->getSummary($user, $unitId, $sessionId, $months);
    }

    /** @return array<string,mixed> framework breakdown row keyed by framework id */
    private function breakdownByFramework(array $summary): array
    {
        $rows = [];
        foreach ($summary['frameworks_breakdown'] as $row) {
            $rows[$row['id']] = $row;
        }

        return $rows;
    }

    // ─────────────────────────── 1. rate math / bounds ───────────────────────────

    public function test_completion_rate_is_100_when_all_applicable_entries_are_selesai_and_na_are_excluded(): void
    {
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 4,
            ChecklistEntry::WORKFLOW_TIDAK_BERLAKU => 6,
        ]);

        $summary = $this->summaryFor($this->admin);
        $row = $this->breakdownByFramework($summary)[$this->fw27001->id];

        // 6 NA rows must not dilute the denominator.
        $this->assertSame(4, $row['selesai_count']);
        $this->assertSame(6, $row['na_count']);
        $this->assertSame(4, $row['selesai_count'] + $row['tinjauan_count'] + $row['proses_count'] + $row['belum_count']);
        $this->assertSame(100, $row['completion_rate']);
        $this->assertSame(100, $summary['overall_completion_rate']);
    }

    public function test_session_with_only_tidak_berlaku_entries_reports_zero_rate_without_division_error(): void
    {
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_TIDAK_BERLAKU => 3,
        ]);

        $summary = $this->summaryFor($this->admin);
        $row = $this->breakdownByFramework($summary)[$this->fw27001->id];

        $this->assertSame(0, $row['completion_rate']);
        $this->assertSame(3, $row['na_count']);
        $this->assertSame(0, $summary['overall_completion_rate']);
    }

    public function test_completion_rate_stays_inside_0_100_for_every_status_mix(): void
    {
        $statuses = ChecklistEntry::workflowValues();

        foreach ($statuses as $status) {
            $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [$status => 3]);
            $summary = $this->summaryFor($this->admin);
            $rate = $this->breakdownByFramework($summary)[$this->fw27001->id]['completion_rate'];

            $this->assertGreaterThanOrEqual(0, $rate, "rate below 0 for status {$status}");
            $this->assertLessThanOrEqual(100, $rate, "rate above 100 for status {$status}");
            $this->assertGreaterThanOrEqual(0, $summary['overall_completion_rate']);

            ChecklistEntry::query()->forceDelete();
            ChecklistSession::query()->forceDelete();
            Control::query()->forceDelete();
        }
    }

    public function test_latest_session_per_unit_and_framework_supersedes_older_period_for_global_roles(): void
    {
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->subMonths(2)->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 5,
        ]);
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_BELUM_DIMULAI => 5,
        ]);

        $row = $this->breakdownByFramework($this->summaryFor($this->admin))[$this->fw27001->id];

        $this->assertSame(0, $row['selesai_count'], 'older period must not be counted');
        $this->assertSame(0, $row['completion_rate']);
    }

    public function test_latest_session_is_chosen_per_unit_and_framework_pair(): void
    {
        // Unit A gets a new 27701 session, but its 27001 session stays a month old.
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->subMonth()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 2,
        ]);
        $this->sessionWithEntries($this->unitA, $this->fw27701, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 2,
        ]);
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 2,
            ChecklistEntry::WORKFLOW_BELUM_DIMULAI => 2,
        ]);

        $rows = $this->breakdownByFramework($this->summaryFor($this->admin));

        $this->assertSame(50, $rows[$this->fw27001->id]['completion_rate']);
        $this->assertSame(100, $rows[$this->fw27701->id]['completion_rate']);
    }

    /**
     * Documents a known gap: `overall_completion_rate` is NOT the average of the
     * per-framework rates. Non-PIC roles average each unit's *rate* for the
     * breakdown, but the headline divides a ceil()-ed average selesai count by the
     * summed applicable count (DashboardAnalyticsService.php:129-163), so the
     * headline can read far lower than every card below it.
     */
    public function test_overall_completion_rate_can_diverge_from_frameworks_breakdown_rate(): void
    {
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 1,
        ]);
        $this->sessionWithEntries($this->unitB, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_BELUM_DIMULAI => 9,
        ]);

        $summary = $this->summaryFor($this->admin);
        $row = $this->breakdownByFramework($summary)[$this->fw27001->id];

        $this->assertSame(50, $row['completion_rate'], 'breakdown averages per-unit rates');
        // ceil((1+0)/2) selesai over (1+9) applicable = 10%
        $this->assertSame(10, $summary['overall_completion_rate']);
        $this->assertLessThan(
            $row['completion_rate'],
            $summary['overall_completion_rate'],
            'guard: this test only makes sense while the headline under-reports'
        );
    }

    public function test_pic_subtree_selesai_count_can_exceed_framework_total_controls(): void
    {
        // Documents a known gap: for a scoped (PIC) role selesai/applicable are
        // SUMMED across the subtree while total_controls is the framework's own
        // distinct control count, so the dashboard card can read "4 dari 2 Kontrol".
        $shared = Control::factory()->count(2)->create(['framework_id' => $this->fw27001->id])->all();

        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 2,
        ], $shared);
        $this->sessionWithEntries($this->childOfA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 2,
        ], $shared);

        $row = $this->breakdownByFramework($this->summaryFor($this->pic))[$this->fw27001->id];

        $this->assertSame(4, $row['selesai_count'], 'subtree counts are summed');
        $this->assertSame(2, $row['total_controls'], 'total_controls is per framework, not per subtree');
        $this->assertSame(100, $row['completion_rate']);
        $this->assertGreaterThan($row['total_controls'], $row['selesai_count']);
    }

    // ─────────────────────────────────── 2. trends ───────────────────────────────────

    public function test_trends_default_to_twelve_consecutive_monthly_buckets_ending_this_month(): void
    {
        $trends = $this->service->getTrends($this->admin);

        $this->assertCount(12, $trends);
        $this->assertSame(now()->format('Y-m'), end($trends)['period']);
        $this->assertSame(now()->subMonths(11)->format('Y-m'), $trends[0]['period']);

        foreach ($trends as $i => $bucket) {
            $this->assertSame(now()->subMonths(11 - $i)->format('Y-m'), $bucket['period']);
            $this->assertNotSame('', $bucket['label']);
        }
    }

    public function test_trends_months_parameter_is_clamped_to_one_through_twenty_four(): void
    {
        $this->assertCount(3, $this->service->getTrends($this->admin, null, 3));
        $this->assertCount(6, $this->service->getTrends($this->admin, null, 6));
        $this->assertCount(12, $this->service->getTrends($this->admin, null, 12));
        $this->assertCount(24, $this->service->getTrends($this->admin, null, 24));
        $this->assertCount(24, $this->service->getTrends($this->admin, null, 999));
        $this->assertCount(1, $this->service->getTrends($this->admin, null, -5));
        // 0 is falsy, so it silently falls back to the 12-month default.
        $this->assertCount(12, $this->service->getTrends($this->admin, null, 0));
    }

    public function test_trends_api_default_is_six_buckets_not_twelve(): void
    {
        // Documents a known gap: FUNCTIONAL_SPEC §9.2 says "default 12 points,
        // ?months=3|6|12" but DashboardApiController::trends() hardcodes
        // input('months', 6) (DashboardApiController.php:40).
        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/trends');

        $response->assertOk();
        $this->assertCount(6, $response->json('data'));
    }

    public function test_trends_api_rejects_nothing_for_unlisted_months_values(): void
    {
        // The web dashboards validate months against ['3','6','12'] but the API
        // endpoint clamps via a shared resolver instead: ?months=999 caps at 24,
        // garbage/zero/negative fall back to the API default of 6.
        $this->assertCount(24, $this->actingAs($this->admin)->getJson('/api/v1/dashboard/trends?months=999')->json('data'));
        $this->assertCount(6, $this->actingAs($this->admin)->getJson('/api/v1/dashboard/trends?months=abc')->json('data'));
        $this->assertCount(6, $this->actingAs($this->admin)->getJson('/api/v1/dashboard/trends?months=0')->json('data'));
        $this->assertCount(6, $this->actingAs($this->admin)->getJson('/api/v1/dashboard/trends?months=-1')->json('data'));
    }

    public function test_trends_rates_stay_inside_0_100_for_mixed_statuses(): void
    {
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 2,
            ChecklistEntry::WORKFLOW_DALAM_TINJAUAN => 1,
            ChecklistEntry::WORKFLOW_DALAM_PROSES => 1,
            ChecklistEntry::WORKFLOW_BELUM_DIMULAI => 6,
            ChecklistEntry::WORKFLOW_TIDAK_BERLAKU => 5,
        ]);

        $bucket = collect($this->service->getTrends($this->admin, null, 1))->first();

        $this->assertSame(20, $bucket['iso27001_rate'], '2 selesai / 10 applicable');
        $this->assertSame(20, $bucket['overall_rate']);
        $this->assertSame(0, $bucket['iso27701_rate']);
    }

    /**
     * Documents a known gap: getTrends() buckets strictly by the hardcoded
     * framework ids 1 and 2 (DashboardAnalyticsService.php:274-277). Frameworks
     * added at runtime (spec §10: "Add new Frameworks at runtime — IN SCOPE")
     * never get their own series, and the chart legend is hardcoded to
     * "ISO 27001 / ISO 27701" in ComplianceAreaChart.tsx.
     */
    public function test_trends_per_framework_series_ignore_frameworks_other_than_ids_1_and_2(): void
    {
        $this->sessionWithEntries($this->unitA, $this->fwOther, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 4,
        ]);
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_BELUM_DIMULAI => 4,
        ]);

        $bucket = collect($this->service->getTrends($this->admin, null, 1))->first();

        $this->assertSame(50, $bucket['overall_rate'], 'both frameworks land in the overall series');
        $this->assertSame(0, $bucket['iso27001_rate']);
        $this->assertSame(0, $bucket['iso27701_rate'], 'framework id 3 has no series at all');
    }

    public function test_summary_frameworks_breakdown_does_cover_runtime_frameworks(): void
    {
        $this->sessionWithEntries($this->unitA, $this->fwOther, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 2,
            ChecklistEntry::WORKFLOW_BELUM_DIMULAI => 2,
        ]);

        $row = $this->breakdownByFramework($this->summaryFor($this->admin))[$this->fwOther->id];

        $this->assertSame(50, $row['completion_rate']);
        $this->assertSame(4, $row['total_controls']);
    }

    /**
     * getTrends() joins `checklist_sessions` with a `deleted_at` filter, so a
     * soft-deleted month moves neither the trend line nor the summary — the two
     * agree for the same data.
     */
    public function test_trends_exclude_soft_deleted_sessions(): void
    {
        $session = $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 4,
        ]);
        $session->delete();

        $trendBucket = collect($this->service->getTrends($this->admin, null, 1))->first();
        $summaryRow = $this->breakdownByFramework($this->summaryFor($this->admin))[$this->fw27001->id];

        $this->assertSame(0, $trendBucket['iso27001_rate'], 'soft-deleted session feeds neither trends nor summary');
        $this->assertSame(0, $trendBucket['overall_rate']);
        $this->assertSame(0, $summaryRow['completion_rate']);
    }

    public function test_trends_exclude_soft_deleted_entries(): void
    {
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 2,
            ChecklistEntry::WORKFLOW_BELUM_DIMULAI => 2,
        ]);
        ChecklistEntry::query()->where('status', ChecklistEntry::WORKFLOW_SELESAI)->get()
            ->each->delete();

        $bucket = collect($this->service->getTrends($this->admin, null, 1))->first();

        $this->assertSame(0, $bucket['iso27001_rate']);
    }

    /**
     * Documents a known gap: with a months filter, getUnitComparisons() joins
     * `checklist_sessions` without a `deleted_at` filter
     * (DashboardAnalyticsService.php:332), so a deleted month still inflates
     * total_entries and the completion rate.
     */
    public function test_unit_comparisons_still_count_soft_deleted_sessions_when_months_filter_applied_documented_gap(): void
    {
        $session = $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 4,
        ]);
        $session->delete();

        $rows = collect($this->service->getUnitComparisons($this->admin, 3))
            ->keyBy('unit_id');

        $this->assertSame(4, $rows[$this->unitA->id]['total_entries']);
        $this->assertSame(100, $rows[$this->unitA->id]['completion_rate']);

        // Without a months filter the entries still count (no session join at all).
        $plain = collect($this->service->getUnitComparisons($this->admin))->keyBy('unit_id');
        $this->assertSame(4, $plain[$this->unitA->id]['total_entries']);
    }

    // ──────────────────────── 3. findings / risks summaries ────────────────────────

    public function test_findings_and_risks_summaries_exclude_soft_deleted_rows(): void
    {
        $control = Control::factory()->create(['framework_id' => $this->fw27001->id]);

        Finding::factory()->create([
            'control_id' => $control->id, 'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN, 'kategori' => Finding::KATEGORI_MAJOR,
        ]);
        $deletedFinding = Finding::factory()->create([
            'control_id' => $control->id, 'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN, 'kategori' => Finding::KATEGORI_MINOR,
            'deadline' => now()->subWeek(),
        ]);
        $deletedFinding->delete();

        Risk::factory()->create([
            'unit_id' => $this->unitA->id, 'level_risiko' => Risk::LEVEL_HIGH, 'status' => Risk::STATUS_OPEN,
        ]);
        $deletedRisk = Risk::factory()->create([
            'unit_id' => $this->unitA->id, 'level_risiko' => Risk::LEVEL_CRITICAL, 'status' => Risk::STATUS_OPEN,
        ]);
        $deletedRisk->delete();

        $summary = $this->summaryFor($this->admin);

        $this->assertSame(1, $summary['findings_summary']['total_active']);
        $this->assertSame(1, $summary['findings_summary']['major']);
        $this->assertSame(0, $summary['findings_summary']['minor']);
        $this->assertSame(0, $summary['findings_summary']['overdue']);
        $this->assertSame(1, $summary['risks_summary']['total_active']);
        $this->assertSame(1, $summary['risks_summary']['high']);
        $this->assertSame(0, $summary['risks_summary']['critical']);
    }

    public function test_risks_summary_total_active_equals_sum_of_level_buckets(): void
    {
        $control = Control::factory()->create(['framework_id' => $this->fw27001->id]);

        $levels = [Risk::LEVEL_CRITICAL, Risk::LEVEL_HIGH, Risk::LEVEL_MEDIUM, Risk::LEVEL_LOW];

        foreach ($levels as $i => $level) {
            Risk::factory()->create([
                'unit_id' => $this->unitA->id,
                'level_risiko' => $level,
                'status' => $i % 2 === 0 ? Risk::STATUS_OPEN : Risk::STATUS_MITIGATED,
            ]);
        }

        // accepted must stay out of the dashboard entirely
        Risk::factory()->create([
            'unit_id' => $this->unitA->id, 'level_risiko' => Risk::LEVEL_CRITICAL, 'status' => Risk::STATUS_ACCEPTED,
        ]);

        $risks = $this->summaryFor($this->admin)['risks_summary'];

        $this->assertSame(4, $risks['total_active']);
        $this->assertSame(
            $risks['total_active'],
            $risks['critical'] + $risks['high'] + $risks['medium'] + $risks['low']
        );
    }

    public function test_findings_summary_total_active_equals_kategori_buckets(): void
    {
        $control = Control::factory()->create(['framework_id' => $this->fw27001->id]);

        foreach ([Finding::KATEGORI_MAJOR, Finding::KATEGORI_MINOR, Finding::KATEGORI_OBSERVASI] as $kategori) {
            Finding::factory()->create([
                'control_id' => $control->id, 'unit_id' => $this->unitA->id,
                'status' => Finding::STATUS_IN_PROGRESS, 'kategori' => $kategori, 'deadline' => null,
            ]);
        }

        $findings = $this->summaryFor($this->admin)['findings_summary'];

        $this->assertSame(3, $findings['total_active']);
        $this->assertSame(1, $findings['major']);
        $this->assertSame(1, $findings['minor']);
        $this->assertSame(1, $findings['observasi']);
        $this->assertSame(0, $findings['overdue']);
    }

    public function test_pic_risks_summary_includes_unassigned_risks(): void
    {
        Risk::factory()->create([
            'unit_id' => null, 'level_risiko' => Risk::LEVEL_CRITICAL, 'status' => Risk::STATUS_OPEN,
        ]);
        Risk::factory()->create([
            'unit_id' => $this->unitA->id, 'level_risiko' => Risk::LEVEL_LOW, 'status' => Risk::STATUS_OPEN,
        ]);
        Risk::factory()->create([
            'unit_id' => $this->unitB->id, 'level_risiko' => Risk::LEVEL_HIGH, 'status' => Risk::STATUS_OPEN,
        ]);

        $risks = $this->summaryFor($this->pic)['risks_summary'];

        // DashboardAnalyticsService.php:204-210 adds unassigned risks for PICs.
        $this->assertSame(2, $risks['total_active']);
        $this->assertSame(1, $risks['critical']);
        $this->assertSame(1, $risks['low']);
        $this->assertSame(0, $risks['high'], 'other units stay invisible');
    }

    public function test_overdue_finding_counts_only_open_and_in_progress_with_past_deadline(): void
    {
        $control = Control::factory()->create(['framework_id' => $this->fw27001->id]);

        // open + past deadline → overdue
        Finding::factory()->create([
            'control_id' => $control->id, 'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN, 'deadline' => now()->subDay(),
        ]);
        // in_progress + past deadline → overdue
        Finding::factory()->create([
            'control_id' => $control->id, 'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_IN_PROGRESS, 'deadline' => now()->subDays(3),
        ]);
        // resolved + past deadline → not overdue (resolved is neither open nor in_progress)
        Finding::factory()->create([
            'control_id' => $control->id, 'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_RESOLVED, 'deadline' => now()->subDays(5),
        ]);
        // open, due today → not overdue
        Finding::factory()->create([
            'control_id' => $control->id, 'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN, 'deadline' => now(),
        ]);
        // open, future deadline → not overdue
        Finding::factory()->create([
            'control_id' => $control->id, 'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN, 'deadline' => now()->addMonth(),
        ]);

        $findings = $this->summaryFor($this->admin)['findings_summary'];

        $this->assertSame(4, $findings['total_active'], 'open + in_progress only');
        $this->assertSame(2, $findings['overdue']);
    }

    // ───────────────────────── 4. auth / unit scoping ─────────────────────────

    public function test_pic_requesting_out_of_scope_unit_id_is_rejected(): void
    {
        $this->sessionWithEntries($this->unitB, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 3,
        ]);

        $this->actingAs($this->pic)
            ->getJson("/api/v1/dashboard/summary?unit_id={$this->unitB->id}")
            ->assertForbidden();

        $this->actingAs($this->pic)
            ->getJson("/api/v1/dashboard/trends?unit_id={$this->unitB->id}")
            ->assertForbidden();
    }

    public function test_pic_summary_includes_child_unit_rows_but_not_sibling_units(): void
    {
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 1,
        ]);
        $this->sessionWithEntries($this->childOfA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 1,
        ]);
        $this->sessionWithEntries($this->unitB, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_BELUM_DIMULAI => 4,
        ]);

        $picRate = $this->summaryFor($this->pic)['overall_completion_rate'];
        $adminRate = $this->summaryFor($this->admin)['overall_completion_rate'];

        $this->assertSame(100, $picRate, 'own unit + child unit both compliant');
        $this->assertLessThan(100, $adminRate, 'sibling unit drags the global number down');
    }

    public function test_unit_comparisons_for_pic_lists_only_subtree_units(): void
    {
        $this->sessionWithEntries($this->childOfA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 1,
        ]);

        $unitIds = collect($this->service->getUnitComparisons($this->pic))->pluck('unit_id');

        $this->assertTrue($unitIds->contains($this->childOfA->id));
        $this->assertTrue($unitIds->contains($this->unitA->id));
        $this->assertFalse($unitIds->contains($this->unitB->id));
    }

    public function test_pic_web_dashboard_does_not_expose_recent_activities_prop(): void
    {
        AuditLog::factory()->create(['actor_id' => $this->admin->id, 'entity_type' => 'User', 'entity_id' => 1]);

        $props = $this->actingAs($this->pic)->get('/admin/pic/dashboard')->assertOk()->viewData('page')['props'];

        $this->assertArrayHasKey('summary', $props);
        $this->assertArrayNotHasKey('recent_activities', $props);
    }

    public function test_pic_cannot_reach_recent_activities_on_any_dashboard_endpoint(): void
    {
        $this->actingAs($this->pic)->getJson('/api/v1/dashboard/recent-activities')->assertForbidden();
        $this->actingAs($this->pic)->getJson('/api/v1/dashboard/recent-activities?limit=100')->assertForbidden();
        $this->assertSame([], $this->service->getRecentActivities($this->pic, 6));
    }

    /**
     * Documents a known gap: the controller gates on `dashboard.recent-activities`
     * while the service gates on `audit-log.view` (DashboardAnalyticsService.php:391).
     * The seeded matrix grants both to the same four roles, so the drift is
     * invisible today but a custom role with only one of the two gets a
     * 200-with-empty-body or a 403-with-available-data.
     */
    public function test_recent_activities_service_gate_uses_audit_log_view_not_dashboard_permission(): void
    {
        AuditLog::factory()->create(['actor_id' => $this->admin->id, 'entity_type' => 'User', 'entity_id' => 1]);

        $dashboardOnly = $this->userWithPermissions(['dashboard.read', 'dashboard.recent-activities']);
        $auditOnly = $this->userWithPermissions(['dashboard.read', 'audit-log.view']);

        // controller passes, service refuses → 200 with an empty list
        $this->actingAs($dashboardOnly)->getJson('/api/v1/dashboard/recent-activities')
            ->assertOk()
            ->assertJsonPath('data', []);

        // service would serve rows, controller refuses first → 403
        $this->actingAs($auditOnly)->getJson('/api/v1/dashboard/recent-activities')->assertForbidden();
        $this->assertNotSame([], $this->service->getRecentActivities($auditOnly, 6));
    }

    public function test_recent_activities_respects_limit_bounds(): void
    {
        AuditLog::factory()->count(4)->create(['actor_id' => $this->admin->id, 'entity_type' => 'User']);

        // setUp() already wrote observer audit logs (users, units, frameworks).
        $total = AuditLog::count();
        $this->assertGreaterThanOrEqual(4, $total);

        $this->assertCount(2, $this->service->getRecentActivities($this->admin, 2));
        $this->assertCount($total, $this->service->getRecentActivities($this->admin, 100));
        // limit=0 is falsy, so it silently returns the default of 6.
        $this->assertCount(6, $this->service->getRecentActivities($this->admin, 0));
        $this->assertCount(1, $this->service->getRecentActivities($this->admin, -3));
    }

    // ─────────────────────────── 5. §11 perf shape ───────────────────────────

    public function test_get_summary_issues_a_bounded_number_of_queries_regardless_of_framework_count(): void
    {
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 3,
        ]);
        $this->sessionWithEntries($this->unitA, $this->fwOther, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 3,
        ]);

        $queries = $this->countQueries(fn () => $this->service->getSummary($this->admin));

        // frameworks+withCount, latest-session aggregate, growth, findings, risks.
        $this->assertLessThanOrEqual(5, $queries, "getSummary ran {$queries} dashboard queries");
    }

    public function test_get_trends_issues_a_single_grouped_query(): void
    {
        // getTrends() runs one grouped aggregate over the whole window,
        // i.e. constant in ?months=, not one round-trip per bucket.
        $this->assertSame(1, $this->countQueries(fn () => $this->service->getTrends($this->admin, null, 12)));
        $this->assertSame(1, $this->countQueries(fn () => $this->service->getTrends($this->admin, null, 3)));
    }

    public function test_get_unit_comparisons_and_recent_activities_stay_constant_query_count(): void
    {
        AuditLog::factory()->count(3)->create(['actor_id' => $this->admin->id, 'entity_type' => 'User']);
        WorkUnit::factory()->count(3)->create();

        // units, entries aggregate, findings aggregate — no per-unit query.
        $this->assertLessThanOrEqual(
            3,
            $this->countQueries(fn () => $this->service->getUnitComparisons($this->admin)),
            'unit_comparisons must not N+1 per unit'
        );
        // logs + actor + actor.workUnit + actor.role.
        $this->assertLessThanOrEqual(
            4,
            $this->countQueries(fn () => $this->service->getRecentActivities($this->admin, 6)),
            'recent_activities must eager-load, not N+1 per log'
        );
    }

    // ─────────────── 6. QA runtime top-up (ported from QA9) ───────────────

    /**
     * Documents a gap: getSummary() counts only the latest session per
     * (unit × framework) — DashboardAnalyticsService.php:65-69 — while
     * getUnitComparisons() aggregates *every* entry row in scope
     * (DashboardAnalyticsService.php:330-351). The two land on the same
     * dashboard page, so the KPI card and the per-unit table disagree for the
     * same unit and month.
     *
     * Fix = apply the same latest-session rule (or document that the table is
     * an all-periods roll-up and label it as such in the UI).
     */
    public function test_qa9_documents_gap_unit_comparisons_ignores_latest_session_rule(): void
    {
        // Last month: fully compliant. This month: nothing done.
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->subMonth()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 4,
        ]);
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_BELUM_DIMULAI => 4,
        ]);

        $summary = $this->summaryFor($this->admin);
        $rows = collect($this->service->getUnitComparisons($this->admin))->keyBy('unit_id');

        // Headline + breakdown use the latest session only → 0%.
        $this->assertSame(0, $this->breakdownByFramework($summary)[$this->fw27001->id]['completion_rate']);
        $this->assertSame(0, $summary['overall_completion_rate']);

        // Unit table pools both months → 4/8 = 50%.
        $this->assertSame(8, $rows[$this->unitA->id]['total_entries']);
        $this->assertSame(50, $rows[$this->unitA->id]['completion_rate']);
        $this->assertNotSame(
            $this->breakdownByFramework($summary)[$this->fw27001->id]['completion_rate'],
            $rows[$this->unitA->id]['completion_rate'],
            'guard: this test only makes sense while the two rules disagree'
        );
    }

    /**
     * Same divergence, observed through the real HTTP payload the
     * admin-kepatuhan dashboard renders, so a fix has to land in the prop
     * shape the page already consumes. Rows are located by unit_id: row order
     * is not part of the contract.
     */
    public function test_qa9_documents_gap_dashboard_page_ships_two_conflicting_rates_for_one_unit(): void
    {
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->subMonth()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 4,
        ]);
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_BELUM_DIMULAI => 4,
        ]);

        $rows = [];
        $this->actingAs($this->admin)->get('/admin/kepatuhan/dashboard')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$rows) {
                $page->where('summary.overall_completion_rate', 0)
                    ->where('summary.frameworks_breakdown.0.completion_rate', 0);
                $rows = $page->toArray()['props']['unit_comparisons'];
            });

        $unitRow = collect($rows)->firstWhere('unit_id', $this->unitA->id);
        $this->assertNotNull($unitRow, 'unit A must appear in unit_comparisons');
        $this->assertSame(50, $unitRow['completion_rate']);
        $this->assertSame(8, $unitRow['total_entries']);
    }

    /**
     * `growth_from_last_period` baselines on the previous `periode` only
     * (tanggal_input within last month), not all history: a unit compliant six
     * months ago that changed nothing since reads 0% growth, as labelled.
     */
    public function test_qa9_growth_baseline_covers_only_last_month(): void
    {
        // Six months ago: 4/4 selesai. This month: 0/4.
        $this->sessionWithEntries(
            $this->unitA,
            $this->fw27001,
            now()->subMonths(6)->format('Y-m'),
            [ChecklistEntry::WORKFLOW_SELESAI => 4],
            null,
            now()->subMonths(6)->toDateString()
        );
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_BELUM_DIMULAI => 4,
        ]);

        $summary = $this->summaryFor($this->admin, $this->unitA->id);

        $this->assertSame(0, $summary['overall_completion_rate']);
        // No entries last month → previous rate 0 → growth 0.
        $this->assertSame(0.0, $summary['growth_from_last_period']);
    }

    /**
     * The counter-case: when last month itself is the only compliant history,
     * the current behaviour happens to produce the number the label promises.
     * Guards against a "fix" that over-corrects into comparing nothing.
     */
    public function test_qa9_growth_is_zero_when_nothing_changed_since_last_month(): void
    {
        $this->sessionWithEntries(
            $this->unitA,
            $this->fw27001,
            now()->subMonth()->format('Y-m'),
            [ChecklistEntry::WORKFLOW_SELESAI => 4],
            null,
            now()->subMonth()->toDateString()
        );
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 4,
        ]);

        $summary = $this->summaryFor($this->admin, $this->unitA->id);

        $this->assertSame(100, $summary['overall_completion_rate']);
        $this->assertSame(0.0, $summary['growth_from_last_period']);
    }

    /**
     * Documents a gap: the drill-down branch of getSummary()
     * (DashboardAnalyticsService.php:35-59) queries `checklist_entries` by
     * session id and never touches `checklist_sessions`, so a soft-deleted
     * session still drives the headline. The latest-session branch filters
     * `deleted_at IS NULL` (line 69) and getTrends() does not filter at all
     * (line 266) — three different answers for one deleted month.
     *
     * Fix = join `checklist_sessions` and filter `deleted_at IS NULL` here.
     */
    public function test_qa9_documents_gap_session_drilldown_counts_soft_deleted_session(): void
    {
        $session = $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 4,
        ]);
        $session->delete();

        $drilldown = $this->summaryFor($this->admin, null, $session->id);
        $latest = $this->summaryFor($this->admin);

        $this->assertSame(4, $this->breakdownByFramework($drilldown)[$this->fw27001->id]['selesai_count'], 'drill-down ignores the delete');
        $this->assertSame(100, $drilldown['overall_completion_rate']);
        $this->assertSame(0, $this->breakdownByFramework($latest)[$this->fw27001->id]['selesai_count'], 'latest-session branch filters it');
        $this->assertSame(0, $latest['overall_completion_rate']);
    }

    /**
     * A PIC asking for a sibling unit's session id gets an empty summary with
     * 200, not 403. No data leaks (the entry query is still unit-scoped), but
     * the same request shape 403s for ?unit_id= — inconsistent, and it makes
     * session ids probeable for existence.
     */
    public function test_qa9_documents_gap_pic_out_of_scope_session_id_returns_zeros_not_403(): void
    {
        $foreign = $this->sessionWithEntries($this->unitB, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 4,
        ]);

        // Same request, unit_id form → AuthorizationException → 403.
        $this->actingAs($this->pic)
            ->getJson("/api/v1/dashboard/summary?unit_id={$this->unitB->id}")
            ->assertForbidden();

        // session_id form → 200 with the numbers zeroed out.
        $response = $this->actingAs($this->pic)
            ->getJson("/api/v1/dashboard/summary?session_id={$foreign->id}");

        $response->assertOk();
        $this->assertSame(0, $response->json('data.overall_completion_rate'));
        $this->assertSame(0, collect($response->json('data.frameworks_breakdown'))->sum('selesai_count'));
    }

    /**
     * API/web parity for ?months=: the API resolver honours `months` exactly
     * like ComplianceController::dashboard() — a 3-month window sees only this
     * month's 4 entries on both surfaces.
     */
    public function test_qa9_api_unit_comparison_honors_months_like_web(): void
    {
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->subMonths(6)->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 4,
        ]);
        $this->sessionWithEntries($this->unitA, $this->fw27001, now()->format('Y-m'), [
            ChecklistEntry::WORKFLOW_SELESAI => 4,
        ]);

        $allTime = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/unit-comparison?months=all');
        $allTime->assertOk();
        $this->assertSame(8, collect($allTime->json('data'))->sum('total_entries'));

        // The default call applies the 3-month window, like the web default.
        $default = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/unit-comparison');
        $default->assertOk();
        $this->assertSame(4, collect($default->json('data'))->sum('total_entries'));

        // ?months=3 is honoured by the API.
        $apiMonths = $this->actingAs($this->admin)->getJson('/api/v1/dashboard/unit-comparison?months=3');
        $apiMonths->assertOk();
        $this->assertSame(4, collect($apiMonths->json('data'))->sum('total_entries'));

        // The web page in the same session agrees.
        $rows = [];
        $this->actingAs($this->admin)->get('/admin/kepatuhan/dashboard?months=3')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$rows) {
                $page->where('filters.months', '3');
                $rows = $page->toArray()['props']['unit_comparisons'];
            });

        $unitRow = collect($rows)->firstWhere('unit_id', $this->unitA->id);
        $this->assertNotNull($unitRow, 'unit A must appear in unit_comparisons');
        $this->assertSame(4, $unitRow['total_entries']);
    }

    /**
     * Documents a gap: routes/web.php:131 puts the superadmin dashboard in a
     * group with no role middleware and FrameworkController::dashboard()
     * (app/Http/Controllers/Web/FrameworkController.php:27-46) checks no
     * permission — unlike ComplianceController::dashboard() (line 60-62) and
     * PicDashboardController::index() (line 21-23). A PIC reaches the
     * superadmin-only page and reads the global user/framework/control counts
     * that spec §1 US-G3 reserves for superadmin. The compliance numbers on
     * that page stay unit-scoped, so no other unit's records leak.
     *
     * The auditor page has the same missing gate, already pinned as intended by
     * AuditorDashboardTest::test_pic_can_view_auditor_dashboard.
     *
     * Fix = add the same permission/role guard the other three dashboards use.
     */
    public function test_qa9_documents_gap_pic_reaches_superadmin_dashboard_and_global_master_counts(): void
    {
        $response = $this->actingAs($this->pic)->get('/admin/superadmin/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('superadmin/dashboard')
            ->where('totalUsers', User::count())
            ->where('totalControls', Control::count())
            ->has('summary')
        );
    }

    public function test_qa9_pic_findings_summary_excludes_sibling_unit_findings(): void
    {
        $control = Control::factory()->create(['framework_id' => $this->fw27001->id]);

        Finding::factory()->create([
            'control_id' => $control->id,
            'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN,
            'kategori' => Finding::KATEGORI_MAJOR,
        ]);
        Finding::factory()->create([
            'control_id' => $control->id,
            'unit_id' => $this->unitB->id,
            'status' => Finding::STATUS_OPEN,
            'kategori' => Finding::KATEGORI_MINOR,
        ]);

        $picFindings = $this->summaryFor($this->pic)['findings_summary'];
        $adminFindings = $this->summaryFor($this->admin)['findings_summary'];

        $this->assertSame(1, $picFindings['total_active']);
        $this->assertSame(1, $picFindings['major']);
        $this->assertSame(0, $picFindings['minor'], 'sibling unit finding stays invisible');

        $this->assertSame(2, $adminFindings['total_active'], 'global roles still see both');
    }

    public function test_qa9_pic_overdue_count_is_also_subtree_scoped(): void
    {
        $control = Control::factory()->create(['framework_id' => $this->fw27001->id]);

        Finding::factory()->create([
            'control_id' => $control->id,
            'unit_id' => $this->unitA->id,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->subWeek(),
        ]);
        Finding::factory()->create([
            'control_id' => $control->id,
            'unit_id' => $this->unitB->id,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->subWeek(),
        ]);

        $this->assertSame(1, $this->summaryFor($this->pic)['findings_summary']['overdue']);
        $this->assertSame(2, $this->summaryFor($this->admin)['findings_summary']['overdue']);
    }

    /**
     * Spec §9.4: risks_summary excludes `accepted`. "Active" therefore means
     * not-accepted, so a `mitigated` risk still counts — including in its level
     * bucket. Pins both halves so a future "active = open" refactor is caught.
     */
    public function test_qa9_risks_summary_excludes_accepted_and_keeps_mitigated(): void
    {
        $open = [
            Risk::LEVEL_CRITICAL => Risk::STATUS_OPEN,
            Risk::LEVEL_HIGH => Risk::STATUS_OPEN,
            Risk::LEVEL_MEDIUM => Risk::STATUS_OPEN,
            Risk::LEVEL_LOW => Risk::STATUS_OPEN,
        ];
        foreach ($open as $level => $status) {
            Risk::factory()->create(['unit_id' => $this->unitA->id, 'level_risiko' => $level, 'status' => $status]);
        }

        // One accepted per level, plus one mitigated critical.
        foreach ([Risk::LEVEL_CRITICAL, Risk::LEVEL_HIGH, Risk::LEVEL_MEDIUM, Risk::LEVEL_LOW] as $level) {
            Risk::factory()->create([
                'unit_id' => $this->unitA->id,
                'level_risiko' => $level,
                'status' => Risk::STATUS_ACCEPTED,
            ]);
        }
        Risk::factory()->create([
            'unit_id' => $this->unitA->id,
            'level_risiko' => Risk::LEVEL_CRITICAL,
            'status' => Risk::STATUS_MITIGATED,
        ]);

        $risks = $this->summaryFor($this->admin)['risks_summary'];

        $this->assertSame(5, $risks['total_active'], '4 open + 1 mitigated, 4 accepted dropped');
        $this->assertSame(2, $risks['critical'], 'open + mitigated');
        $this->assertSame(1, $risks['high']);
        $this->assertSame(1, $risks['medium']);
        $this->assertSame(1, $risks['low']);
        $this->assertSame(5, $risks['critical'] + $risks['high'] + $risks['medium'] + $risks['low']);
    }

    public function test_qa9_trend_buckets_stay_consecutive_across_a_year_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-15 10:00:00'));

        try {
            $trends = $this->service->getTrends($this->admin, null, 3);

            $this->assertSame(['2025-11', '2025-12', '2026-01'], array_column($trends, 'period'));
            foreach ($trends as $bucket) {
                $this->assertSame(
                    Carbon::parse($bucket['period'])->startOfMonth()->translatedFormat('F Y'),
                    $bucket['label'],
                    'label must be derived from its own period bucket'
                );
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_qa9_trend_buckets_end_on_the_first_of_the_current_month(): void
    {
        // A test-now mid-month must not shift the window: the last bucket is
        // always the current calendar month.
        Carbon::setTestNow(Carbon::parse('2026-03-31 23:59:00'));

        try {
            $trends = $this->service->getTrends($this->admin, null, 12);

            $this->assertCount(12, $trends);
            $this->assertSame('2026-03', end($trends)['period']);
            $this->assertSame('2025-04', $trends[0]['period']);
        } finally {
            Carbon::setTestNow();
        }
    }

    // ───────────────────────────── 7. skipped spec assertions ─────────────────────────────

    public function test_api_trends_default_should_be_twelve_buckets_per_spec(): void
    {
        $this->markTestSkipped(
            'SPEC GAP §9.2: default must be 12 points. Fix DashboardApiController::trends() '
            .'(app/Http/Controllers/Api/DashboardApiController.php:40) to input(\'months\', 12).'
        );
    }

    public function test_trends_should_expose_a_series_per_framework_not_hardcoded_ids(): void
    {
        $this->markTestSkipped(
            'SPEC GAP §9.2/§10: per-framework trend series are hardcoded to framework ids 1 & 2 '
            .'(app/Services/DashboardAnalyticsService.php:274-277) and the chart legend is hardcoded '
            .'(resources/js/components/dashboards/ComplianceAreaChart.tsx:115-149).'
        );
    }

    public function test_summary_trends_and_comparisons_should_ignore_soft_deleted_sessions(): void
    {
        $this->markTestSkipped(
            'SPEC GAP: getTrends() joins checklist_sessions without a deleted_at filter '
            .'(app/Services/DashboardAnalyticsService.php:266) and getUnitComparisons() does the same '
            .'(app/Services/DashboardAnalyticsService.php:332). getSummary() already filters '
            .'(app/Services/DashboardAnalyticsService.php:69).'
        );
    }

    // ─────────────────────────────────── helpers ───────────────────────────────────

    /** @param  array<int,string>  $keys */
    private function userWithPermissions(array $keys): User
    {
        $role = Role::create(['name' => 'custom_'.implode('_', $keys), 'label' => 'Custom']);
        $role->permissions()->sync(Permission::whereIn('key', $keys)->pluck('id')->all());
        Role::flushPermissionsCache($role->id);

        // UserFactory defaults `role => pic`, so assign role_id after creation.
        $user = User::factory()->create(['unit_id' => null]);
        $user->forceFill(['role_id' => $role->id])->save();

        return $user->fresh();
    }

    /**
     * Count dashboard-owned SELECTs. `User::$role` (getRoleAttribute) re-queries
     * `roles` on every access, which adds 2 queries per service call; they are
     * excluded here so the numbers describe the analytics queries only.
     */
    private function countQueries(callable $fn): int
    {
        $count = 0;
        DB::listen(function ($query) use (&$count) {
            $sql = strtolower(ltrim($query->sql));

            if (str_starts_with($sql, 'select') && ! str_contains($sql, 'from "roles"')) {
                $count++;
            }
        });

        $fn();

        return $count;
    }
}
