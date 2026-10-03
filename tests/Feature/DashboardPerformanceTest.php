<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * §11 "Dashboard performance" gate.
 *
 * Seeds the realistic volume named by the spec — all 12 work units × the full
 * seeded control set (ISO 27001:2022 + ISO 27701:2025) × 12 monthly sessions —
 * then asserts p95 < 2s on GET /dashboard and GET
 * /api/v1/dashboard/trends?months=12, checks that `frameworks_breakdown` /
 * `unit_comparisons` do not fan out into per-unit queries (N+1), and dumps the
 * EXPLAIN plan for the monthly trend aggregate so a plan regression is visible
 * in the CI log rather than only as a slow number.
 *
 * Timing assertions are inherently environment-dependent; the 2s ceiling is the
 * spec's, not a locally tuned one.
 */
class DashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const P95_BUDGET_MS = 2000;

    private const SAMPLES = 5;

    private User $admin;

    private User $pic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $this->pic = User::factory()->create(['role' => User::ROLE_PIC]);
    }

    /**
     * 12 units × 12 months × 120 controls (60 per framework) = 17,280 entries in
     * 288 sessions. The seeded control set is 123 clauses (93 ISO 27001 +
     * 30 ISO 27701), so this is the spec's "all units × full control set ×
     * 12 sessions" within 2.5%.
     */
    private function seedRealisticVolume(): void
    {
        $units = WorkUnit::factory()->count(12)->create();
        $frameworks = Framework::factory()->count(2)->create();

        $controlIds = [];
        foreach ($frameworks as $framework) {
            $ids = [];
            for ($i = 1; $i <= 60; $i++) {
                $ids[] = $framework->controls()->create([
                    'kode_klausul' => "A.{$i}.".$framework->id,
                    'judul' => "Kontrol {$i}",
                    'kategori' => 'teknologi',
                ])->id;
            }
            $controlIds[$framework->id] = $ids;
        }

        $now = now();
        $rows = [];

        foreach ($units as $unit) {
            foreach ($frameworks as $framework) {
                for ($m = 11; $m >= 0; $m--) {
                    $periode = $now->copy()->startOfMonth()->subMonths($m)->format('Y-m');
                    $session = ChecklistSession::create([
                        'konteks_penilaian' => "Penilaian {$periode}",
                        'unit_id' => $unit->id,
                        'framework_id' => $framework->id,
                        'periode' => $periode,
                    ]);

                    foreach ($controlIds[$framework->id] as $controlId) {
                        // ~60% compliant, deterministic by control id.
                        $done = $controlId % 5 < 3;
                        $rows[] = [
                            'session_id' => $session->id,
                            'control_id' => $controlId,
                            'unit_id' => $unit->id,
                            'pic_id' => null,
                            'admin_id' => null,
                            'status' => $done
                                ? ChecklistEntry::WORKFLOW_SELESAI
                                : ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
                            'level_maturity' => $done ? 4 : null,
                            'catatan' => '',
                            'catatan_admin' => null,
                            'tanggal_input' => $now,
                            'tanggal_verifikasi' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            ChecklistEntry::insert($chunk);
        }
    }

    /** @return float[] milliseconds, one per sample */
    private function sample(callable $request): array
    {
        // One warm-up request so the first sample is not paying autoload cost.
        $request();

        $times = [];
        for ($i = 0; $i < self::SAMPLES; $i++) {
            $start = hrtime(true);
            $request();
            $times[] = (hrtime(true) - $start) / 1_000_000;
        }
        sort($times);

        return $times;
    }

    private function p95(array $sortedMs): float
    {
        $index = (int) ceil(0.95 * count($sortedMs)) - 1;

        return $sortedMs[max(0, $index)];
    }

    public function test_dashboard_p95_stays_under_two_seconds_at_realistic_volume(): void
    {
        $this->seedRealisticVolume();

        $this->assertGreaterThanOrEqual(17_000, ChecklistEntry::count(), 'volume seed terlalu kecil untuk gerbang perf');

        $times = $this->sample(fn () => $this->actingAs($this->admin)->get('/dashboard')->assertOk());
        $p95 = $this->p95($times);
        fwrite(STDERR, "\n[dashboard p95] samples=".implode(', ', array_map(fn ($t) => round($t, 1), $times))
            .' ms → p95='.round($p95, 1).' ms (budget '.self::P95_BUDGET_MS." ms)\n");

        $this->assertLessThan(
            self::P95_BUDGET_MS,
            $p95,
            'p95 GET /dashboard melewati 2 detik pada volume realistis'
        );
    }

    public function test_dashboard_trends_p95_stays_under_two_seconds_at_realistic_volume(): void
    {
        $this->seedRealisticVolume();

        $times = $this->sample(fn () => $this->actingAs($this->admin)
            ->getJson('/api/v1/dashboard/trends?months=12')
            ->assertOk()
            ->assertJsonCount(12, 'data'));
        $p95 = $this->p95($times);
        fwrite(STDERR, "\n[trends?months=12 p95] samples=".implode(', ', array_map(fn ($t) => round($t, 1), $times))
            .' ms → p95='.round($p95, 1).' ms (budget '.self::P95_BUDGET_MS." ms)\n");

        $this->assertLessThan(
            self::P95_BUDGET_MS,
            $p95,
            'p95 GET /api/v1/dashboard/trends?months=12 melewati 2 detik pada volume realistis'
        );
    }

    /**
     * `frameworks_breakdown` and `unit_comparisons` must be produced by a fixed
     * number of aggregate queries, not one query per unit. Measured by
     * comparing the query count of GET /api/v1/dashboard/unit-comparison at 3
     * units against the count at 12 units.
     */
    public function test_dashboard_breakdown_and_comparison_do_not_fan_out_into_per_unit_queries(): void
    {
        $framework = Framework::factory()->create();
        $control = $framework->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi',
        ]);

        $measure = function (int $unitCount) use ($control, $framework): int {
            WorkUnit::query()->delete();
            ChecklistSession::query()->delete();

            $units = WorkUnit::factory()->count($unitCount)->create();
            foreach ($units as $unit) {
                $session = ChecklistSession::factory()->create([
                    'unit_id' => $unit->id,
                    'framework_id' => $framework->id,
                    'periode' => now()->format('Y-m'),
                ]);
                ChecklistEntry::factory()->create([
                    'session_id' => $session->id,
                    'control_id' => $control->id,
                    'unit_id' => $unit->id,
                    'status' => ChecklistEntry::WORKFLOW_SELESAI,
                ]);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->actingAs($this->admin)
                ->getJson('/api/v1/dashboard/unit-comparison')
                ->assertOk()
                ->assertJsonCount($unitCount, 'data');

            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $withThree = $measure(3);
        $withTwelve = $measure(12);

        fwrite(STDERR, "\n[unit-comparison queries] 3 units={$withThree}, 12 units={$withTwelve}\n");

        $this->assertSame(
            $withThree,
            $withTwelve,
            'jumlah query pada unit-comparison ikut bertambah saat jumlah unit bertambah — indikasi N+1'
        );
    }

    public function test_dashboard_explain_plan_is_logged_for_regression_review(): void
    {
        $this->seedRealisticVolume();

        // Representative of the latest-session subquery that getSummary() and
        // getTrends() both lean on.
        $plan = DB::select("
            EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
            SELECT ms.unit_id, cf.framework_id,
                   COUNT(DISTINCT CASE WHEN ce.status = 'selesai_diterapkan' THEN ce.control_id END) AS selesai
            FROM (
                SELECT id, unit_id, framework_id,
                       ROW_NUMBER() OVER (PARTITION BY unit_id, framework_id ORDER BY periode DESC) AS rn
                FROM checklist_sessions
                WHERE deleted_at IS NULL
            ) AS ms
            JOIN checklist_entries ce ON ce.session_id = ms.id
            JOIN controls cf ON cf.id = ce.control_id
            WHERE ms.rn = 1
            GROUP BY ms.unit_id, cf.framework_id
        ");

        $text = collect($plan)->pluck('QUERY PLAN')->implode("\n");
        fwrite(STDERR, "\n[EXPLAIN latest-session aggregate]\n".$text."\n");

        $this->assertStringContainsString('Aggregate', $text, 'EXPLAIN gagal menghasilkan rencana yang diharapkan');
        $this->assertMatchesRegularExpression(
            '/Index (Scan|Only Scan)|Seq Scan/',
            $text,
            'rencana tidak menunjukkan jalur pemindaian yang diharapkan'
        );
    }
}
