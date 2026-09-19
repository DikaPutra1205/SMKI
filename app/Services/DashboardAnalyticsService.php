<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ChecklistEntry;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkUnit;
use Carbon\Carbon;

class DashboardAnalyticsService
{
    /**
     * Resolve effective unit_id based on User role scoping.
     * PIC is strictly scoped to their assigned unit.
     */
    public function resolveScopedUnitId(User $user, ?int $requestedUnitId = null): ?int
    {
        if ($user->isPic()) {
            return $user->unit_id ? (int) $user->unit_id : null;
        }

        return $requestedUnitId;
    }

    /**
     * Get complete dashboard summary analytics.
     */
    public function getSummary(User $user, ?int $unitId = null, ?int $sessionId = null, ?int $months = null): array
    {
        $scopedUnitId = $this->resolveScopedUnitId($user, $unitId);
        $cutoffDate = $months ? Carbon::now()->startOfMonth()->subMonths($months - 1)->startOfMonth() : null;
        $cutoffPeriode = $months ? Carbon::now()->startOfMonth()->subMonths($months - 1)->format('Y-m') : null;

        // 1. Frameworks Breakdown & Overall Compliance Rate via Single SQL Aggregation
        $frameworks = Framework::withCount('controls')->orderBy('id')->get();
        $frameworksBreakdown = [];
        $totalApplicableOverall = 0;
        $totalSelesaiOverall = 0;

        // Explicit session drill-down overrides the most-recent-session rule.
        if ($sessionId) {
            $entryQuery = ChecklistEntry::query()
                ->join('controls', 'checklist_entries.control_id', '=', 'controls.id')
                ->where('checklist_entries.session_id', $sessionId)
                ->selectRaw('
                    controls.framework_id,
                    checklist_entries.unit_id AS session_unit_id,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as selesai_count,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as tinjauan_count,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as proses_count,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as belum_count,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as na_count
                ', [
                    ChecklistEntry::WORKFLOW_SELESAI,
                    ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
                    ChecklistEntry::WORKFLOW_DALAM_PROSES,
                    ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
                    ChecklistEntry::WORKFLOW_TIDAK_BERLAKU,
                ]);

            if ($scopedUnitId) {
                $entryQuery->where('checklist_entries.unit_id', $scopedUnitId);
            }

            $statsByFrameworkUnit = $entryQuery->groupBy('controls.framework_id', 'checklist_entries.unit_id')->get();
        } else {
            // Scope entries to the most-recent session per (unit, framework) by
            // `periode` (yyyy-mm), so each control is counted once in the latest
            // assessment rather than across every historical session.
            $periodeConstraint = $cutoffPeriode ? "AND periode >= '{$cutoffPeriode}'" : '';
            $latestSessionSql = "
                SELECT id, unit_id, framework_id,
                       ROW_NUMBER() OVER (PARTITION BY unit_id, framework_id ORDER BY periode DESC) AS rn
                FROM checklist_sessions
                WHERE deleted_at IS NULL {$periodeConstraint}";

            $entryQuery = ChecklistEntry::from(\DB::raw("({$latestSessionSql}) AS ms"))
                ->join('checklist_entries', 'checklist_entries.session_id', '=', 'ms.id')
                ->join('controls', 'checklist_entries.control_id', '=', 'controls.id')
                ->selectRaw('
                    controls.framework_id,
                    ms.unit_id AS session_unit_id,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as selesai_count,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as tinjauan_count,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as proses_count,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as belum_count,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as na_count
                ', [
                    ChecklistEntry::WORKFLOW_SELESAI,
                    ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
                    ChecklistEntry::WORKFLOW_DALAM_PROSES,
                    ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
                    ChecklistEntry::WORKFLOW_TIDAK_BERLAKU,
                ])
                ->where('ms.rn', 1);

            if ($scopedUnitId) {
                $entryQuery->where('ms.unit_id', $scopedUnitId);
            }

            $statsByFrameworkUnit = $entryQuery->groupBy('controls.framework_id', 'ms.unit_id')->get();
        }

        foreach ($frameworks as $fw) {
            $unitRows = $statsByFrameworkUnit->where('framework_id', $fw->id);

            if ($scopedUnitId) {
                $stats = $unitRows->first();
                $selesaiCount = $stats ? (int) $stats->selesai_count : 0;
                $tinjauanCount = $stats ? (int) $stats->tinjauan_count : 0;
                $prosesCount = $stats ? (int) $stats->proses_count : 0;
                $belumCount = $stats ? (int) $stats->belum_count : 0;
                $naCount = $stats ? (int) $stats->na_count : 0;

                $applicableCount = $selesaiCount + $tinjauanCount + $prosesCount + $belumCount;
                $completionRate = $applicableCount > 0 ? (int) round(($selesaiCount / $applicableCount) * 100) : 0;
            } else {
                // Overall (non-unit roles): average each unit's selesai-control
                // count and rate from its most-recent session. Units never
                // assessed contribute 0 selesai (no latest session row).
                $perUnitRates = [];
                $perUnitSelesai = [];
                foreach ($unitRows as $row) {
                    $selesai = (int) $row->selesai_count;
                    $applicable = $selesai + (int) $row->tinjauan_count + (int) $row->proses_count + (int) $row->belum_count;
                    $perUnitSelesai[] = $selesai;
                    if ($applicable > 0) {
                        $perUnitRates[] = $selesai / $applicable;
                    }
                }

                $selesaiCount = $perUnitSelesai
                    ? (int) round(array_sum($perUnitSelesai) / count($perUnitSelesai))
                    : 0;
                $tinjauanCount = (int) $unitRows->sum('tinjauan_count');
                $prosesCount = (int) $unitRows->sum('proses_count');
                $belumCount = (int) $unitRows->sum('belum_count');
                $naCount = (int) $unitRows->sum('na_count');

                $completionRate = $perUnitRates
                    ? (int) round((array_sum($perUnitRates) / count($perUnitRates)) * 100)
                    : 0;

                // Overall applicable across units drives overall_compliance_rate.
                $applicableCount = $selesaiCount + $tinjauanCount + $prosesCount + $belumCount;
            }

            $totalApplicableOverall += $applicableCount;
            $totalSelesaiOverall += $selesaiCount;

            $frameworksBreakdown[] = [
                'id' => $fw->id,
                'nama' => $fw->nama,
                'versi' => $fw->versi,
                'completion_rate' => $completionRate,
                'selesai_count' => $selesaiCount,
                'tinjauan_count' => $tinjauanCount,
                'proses_count' => $prosesCount,
                'belum_count' => $belumCount,
                'na_count' => $naCount,
                'total_controls' => $fw->controls_count,
            ];
        }

        $overallCompletionRate = $totalApplicableOverall > 0
            ? (int) round(($totalSelesaiOverall / $totalApplicableOverall) * 100)
            : 0;

        // 2. Growth from last period (compare with previous month / session)
        $growthFromLastPeriod = $this->calculateGrowthRate($scopedUnitId, $overallCompletionRate);

        // 3. Findings Summary & Overdue Calculation via SQL Aggregate
        $today = Carbon::today();
        $findingQuery = Finding::query();
        if ($scopedUnitId) {
            $findingQuery->where('unit_id', $scopedUnitId);
        }
        if ($cutoffDate) {
            $findingQuery->where('created_at', '>=', $cutoffDate);
        }

        $findingStats = $findingQuery->selectRaw('
            SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as total_active,
            SUM(CASE WHEN status IN (?, ?) AND kategori = ? THEN 1 ELSE 0 END) as major,
            SUM(CASE WHEN status IN (?, ?) AND kategori = ? THEN 1 ELSE 0 END) as minor,
            SUM(CASE WHEN status IN (?, ?) AND kategori = ? THEN 1 ELSE 0 END) as observasi,
            SUM(CASE WHEN status IN (?, ?) AND deadline IS NOT NULL AND deadline < ? THEN 1 ELSE 0 END) as overdue
        ', [
            Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS,
            Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS, Finding::KATEGORI_MAJOR,
            Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS, Finding::KATEGORI_MINOR,
            Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS, Finding::KATEGORI_OBSERVASI,
            Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS, $today,
        ])->first();

        $findingsSummary = [
            'total_active' => (int) ($findingStats->total_active ?? 0),
            'major' => (int) ($findingStats->major ?? 0),
            'minor' => (int) ($findingStats->minor ?? 0),
            'observasi' => (int) ($findingStats->observasi ?? 0),
            'overdue' => (int) ($findingStats->overdue ?? 0),
        ];

        // 4. Risks Summary via SQL Aggregate
        $riskQuery = Risk::query();
        if ($scopedUnitId) {
            $riskQuery->whereHas('controls.checklistEntries', fn ($q) => $q->where('unit_id', $scopedUnitId));
        }
        if ($cutoffDate) {
            $riskQuery->where('created_at', '>=', $cutoffDate);
        }

        $riskStats = $riskQuery->selectRaw('
            SUM(CASE WHEN status != ? THEN 1 ELSE 0 END) as total_active,
            SUM(CASE WHEN status != ? AND level_risiko = ? THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN status != ? AND level_risiko = ? THEN 1 ELSE 0 END) as high,
            SUM(CASE WHEN status != ? AND level_risiko = ? THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN status != ? AND level_risiko = ? THEN 1 ELSE 0 END) as low
        ', [
            Risk::STATUS_ACCEPTED,
            Risk::STATUS_ACCEPTED, Risk::LEVEL_CRITICAL,
            Risk::STATUS_ACCEPTED, Risk::LEVEL_HIGH,
            Risk::STATUS_ACCEPTED, Risk::LEVEL_MEDIUM,
            Risk::STATUS_ACCEPTED, Risk::LEVEL_LOW,
        ])->first();

        $risksSummary = [
            'total_active' => (int) ($riskStats->total_active ?? 0),
            'critical' => (int) ($riskStats->critical ?? 0),
            'high' => (int) ($riskStats->high ?? 0),
            'medium' => (int) ($riskStats->medium ?? 0),
            'low' => (int) ($riskStats->low ?? 0),
        ];

        return [
            'overall_compliance_rate' => $overallCompletionRate,
            'growth_from_last_period' => $growthFromLastPeriod,
            'total_controls_active' => array_sum(array_column($frameworksBreakdown, 'total_controls')),
            'frameworks_breakdown' => $frameworksBreakdown,
            'findings_summary' => $findingsSummary,
            'risks_summary' => $risksSummary,
            'months' => $months ? (string) $months : 'all',
        ];
    }

    /**
     * Get monthly compliance trends over the last N months.
     * When $months is null (all-time), defaults to 12 months for comprehensive trend display.
     */
    public function getTrends(User $user, ?int $unitId = null, ?int $months = null): array
    {
        $scopedUnitId = $this->resolveScopedUnitId($user, $unitId);
        $safeMonths = $months ? max(1, min($months, 24)) : 12;
        $trends = [];

        for ($i = $safeMonths - 1; $i >= 0; $i--) {
            $date = Carbon::now()->startOfMonth()->subMonths($i);
            $yearMonth = $date->format('Y-m');
            $label = $date->translatedFormat('F Y');

            $query = ChecklistEntry::query()
                ->join('controls', 'checklist_entries.control_id', '=', 'controls.id')
                ->join('checklist_sessions', 'checklist_entries.session_id', '=', 'checklist_sessions.id')
                ->where('checklist_sessions.periode', '=', $yearMonth);

            if ($scopedUnitId) {
                $query->where('checklist_entries.unit_id', $scopedUnitId);
            }

            $stats = $query->selectRaw('
                SUM(CASE WHEN controls.framework_id = 1 AND checklist_entries.status = ? THEN 1 ELSE 0 END) as iso27001_compliant,
                SUM(CASE WHEN controls.framework_id = 1 AND checklist_entries.status IN (?, ?, ?) THEN 1 ELSE 0 END) as iso27001_applicable,
                SUM(CASE WHEN controls.framework_id = 2 AND checklist_entries.status = ? THEN 1 ELSE 0 END) as iso27701_compliant,
                SUM(CASE WHEN controls.framework_id = 2 AND checklist_entries.status IN (?, ?, ?) THEN 1 ELSE 0 END) as iso27701_applicable,
                SUM(CASE WHEN checklist_entries.status = ? THEN 1 ELSE 0 END) as overall_compliant,
                SUM(CASE WHEN checklist_entries.status IN (?, ?, ?) THEN 1 ELSE 0 END) as overall_applicable
            ', [
                ChecklistEntry::WORKFLOW_SELESAI,
                ChecklistEntry::WORKFLOW_SELESAI, ChecklistEntry::WORKFLOW_DALAM_PROSES, ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
                ChecklistEntry::WORKFLOW_SELESAI,
                ChecklistEntry::WORKFLOW_SELESAI, ChecklistEntry::WORKFLOW_DALAM_PROSES, ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
                ChecklistEntry::WORKFLOW_SELESAI,
                ChecklistEntry::WORKFLOW_SELESAI, ChecklistEntry::WORKFLOW_DALAM_PROSES, ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
            ])->first();

            $iso27001App = (int) ($stats->iso27001_applicable ?? 0);
            $iso27001Comp = (int) ($stats->iso27001_compliant ?? 0);
            $iso27001Rate = $iso27001App > 0 ? (int) round(($iso27001Comp / $iso27001App) * 100) : 0;

            $iso27701App = (int) ($stats->iso27701_applicable ?? 0);
            $iso27701Comp = (int) ($stats->iso27701_compliant ?? 0);
            $iso27701Rate = $iso27701App > 0 ? (int) round(($iso27701Comp / $iso27701App) * 100) : 0;

            $overallApp = (int) ($stats->overall_applicable ?? 0);
            $overallComp = (int) ($stats->overall_compliant ?? 0);
            $overallRate = $overallApp > 0 ? (int) round(($overallComp / $overallApp) * 100) : 0;

            $trends[] = [
                'period' => $yearMonth,
                'label' => $label,
                'iso27001_rate' => $iso27001Rate,
                'iso27701_rate' => $iso27701Rate,
                'overall_rate' => $overallRate,
            ];
        }

        return $trends;
    }

    /**
     * Get compliance comparison across all work units.
     */
    public function getUnitComparisons(User $user, ?int $months = null): array
    {
        $scopedUnitId = $this->resolveScopedUnitId($user);
        $cutoffDate = $months ? Carbon::now()->startOfMonth()->subMonths($months - 1)->startOfMonth() : null;
        $cutoffPeriode = $months ? Carbon::now()->startOfMonth()->subMonths($months - 1)->format('Y-m') : null;

        $unitsQuery = WorkUnit::select('id', 'nama')->orderBy('nama');
        if ($scopedUnitId) {
            $unitsQuery->where('id', $scopedUnitId);
        }

        $units = $unitsQuery->get();
        $unitIds = $units->pluck('id');

        $entriesQuery = ChecklistEntry::whereIn('checklist_entries.unit_id', $unitIds);
        if ($cutoffPeriode) {
            $entriesQuery->join('checklist_sessions', 'checklist_entries.session_id', '=', 'checklist_sessions.id')
                ->where('checklist_sessions.periode', '>=', $cutoffPeriode);
        }

        $entriesByUnit = $entriesQuery
            ->selectRaw('
                checklist_entries.unit_id,
                COUNT(*) as total_entries,
                SUM(CASE WHEN checklist_entries.status = ? THEN 1 ELSE 0 END) as compliant_count,
                SUM(CASE WHEN checklist_entries.status IN (?, ?, ?) THEN 1 ELSE 0 END) as applicable_count
            ', [
                ChecklistEntry::WORKFLOW_SELESAI,
                ChecklistEntry::WORKFLOW_SELESAI,
                ChecklistEntry::WORKFLOW_DALAM_PROSES,
                ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
            ])
            ->groupBy('checklist_entries.unit_id')
            ->get()
            ->keyBy('unit_id');

        $findingsQuery = Finding::whereIn('unit_id', $unitIds)
            ->whereIn('status', [Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS]);
        if ($cutoffDate) {
            $findingsQuery->where('created_at', '>=', $cutoffDate);
        }

        $findingsByUnit = $findingsQuery
            ->selectRaw('unit_id, COUNT(*) as open_count')
            ->groupBy('unit_id')
            ->get()
            ->keyBy('unit_id');

        return $units->map(function (WorkUnit $unit) use ($entriesByUnit, $findingsByUnit) {
            $entryStat = $entriesByUnit->get($unit->id);
            $totalEntries = $entryStat ? (int) $entryStat->total_entries : 0;
            $compliantCount = $entryStat ? (int) $entryStat->compliant_count : 0;
            $applicableCount = $entryStat ? (int) $entryStat->applicable_count : 0;

            $rate = $applicableCount > 0 ? (int) round(($compliantCount / $applicableCount) * 100) : 0;
            $openFindings = (int) ($findingsByUnit->get($unit->id)?->open_count ?? 0);

            return [
                'unit_id' => $unit->id,
                'unit_nama' => $unit->nama,
                'compliance_rate' => $rate,
                'total_entries' => $totalEntries,
                'compliant_count' => $compliantCount,
                'open_findings' => $openFindings,
            ];
        })->toArray();
    }

    /**
     * Get recent audit activity logs.
     * Accessible only by superadmin, admin_kepatuhan, koordinator_smki, and auditor.
     */
    public function getRecentActivities(User $user, int $limit = 6, ?int $months = null): array
    {
        if (! $user->hasPermissionTo('audit-log.view')) {
            return [];
        }

        $safeLimit = max(1, min((int) ($limit ?: 6), 100));
        $cutoffDate = $months ? Carbon::now()->startOfMonth()->subMonths($months - 1)->startOfMonth() : null;

        $query = AuditLog::with(['actor.workUnit', 'actor.role']);
        if ($cutoffDate) {
            $query->where('created_at', '>=', $cutoffDate);
        }

        return $query
            ->orderByDesc('id')
            ->limit($safeLimit)
            ->get()
            ->map(function (AuditLog $log) {
                return [
                    'id' => $log->id,
                    'actor_name' => $log->actor?->name ?? 'Sistem SMKI',
                    'actor_role' => $log->actor?->role ?? 'system',
                    'action' => $log->aksi,
                    'entity_name' => "{$log->entity_type} #{$log->entity_id}",
                    'time_ago' => $log->created_at ? $log->created_at->diffForHumans() : 'baru saja',
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            })
            ->toArray();
    }

    /**
     * Calculate growth rate compared to previous period.
     */
    protected function calculateGrowthRate(?int $unitId, int $currentRate): float
    {
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        $query = ChecklistEntry::where('tanggal_input', '<=', $endOfLastMonth);

        if ($unitId) {
            $query->where('unit_id', $unitId);
        }

        $stats = $query->selectRaw('
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as compliant_count,
            SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) as applicable_count
        ', [
            ChecklistEntry::WORKFLOW_SELESAI,
            ChecklistEntry::WORKFLOW_SELESAI,
            ChecklistEntry::WORKFLOW_DALAM_PROSES,
            ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ])->first();

        $applicableCount = (int) ($stats->applicable_count ?? 0);
        $compliantCount = (int) ($stats->compliant_count ?? 0);

        $previousRate = $applicableCount > 0 ? (int) round(($compliantCount / $applicableCount) * 100) : 0;

        return (float) round($currentRate - $previousRate, 1);
    }
}
