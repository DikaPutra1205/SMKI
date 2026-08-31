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
    public function getSummary(User $user, ?int $unitId = null, ?int $sessionId = null): array
    {
        $scopedUnitId = $this->resolveScopedUnitId($user, $unitId);

        // 1. Frameworks Breakdown & Overall Compliance Rate via Single SQL Aggregation
        $frameworks = Framework::withCount('controls')->orderBy('id')->get();
        $frameworksBreakdown = [];
        $totalApplicableOverall = 0;
        $totalCompliantOverall = 0;

        // Explicit session drill-down overrides the most-recent-session rule.
        if ($sessionId) {
            $entryQuery = ChecklistEntry::query()
                ->join('controls', 'checklist_entries.control_id', '=', 'controls.id')
                ->where('checklist_entries.session_id', $sessionId)
                ->selectRaw('
                    controls.framework_id,
                    checklist_entries.unit_id AS session_unit_id,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as compliant_count,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as partial_count,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as non_compliant_count,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as na_count
                ', [
                    ChecklistEntry::STATUS_COMPLIANT,
                    ChecklistEntry::STATUS_PARTIAL,
                    ChecklistEntry::STATUS_NON_COMPLIANT,
                    ChecklistEntry::STATUS_NA,
                ]);

            if ($scopedUnitId) {
                $entryQuery->where('checklist_entries.unit_id', $scopedUnitId);
            }

            $statsByFrameworkUnit = $entryQuery->groupBy('controls.framework_id', 'checklist_entries.unit_id')->get();
        } else {
            // Scope entries to the most-recent session per (unit, framework) by
            // `periode` (yyyy-mm), so each control is counted once in the latest
            // assessment rather than across every historical session. Sessions are
            // soft-deletable, hence deleted_at IS NULL.
            $latestSessionSql = '
                SELECT id, unit_id, framework_id,
                       ROW_NUMBER() OVER (PARTITION BY unit_id, framework_id ORDER BY periode DESC) AS rn
                FROM checklist_sessions
                WHERE deleted_at IS NULL';

            $entryQuery = ChecklistEntry::from(\DB::raw("({$latestSessionSql}) AS ms"))
                ->join('checklist_entries', 'checklist_entries.session_id', '=', 'ms.id')
                ->join('controls', 'checklist_entries.control_id', '=', 'controls.id')
                ->selectRaw('
                    controls.framework_id,
                    ms.unit_id AS session_unit_id,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as compliant_count,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as partial_count,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as non_compliant_count,
                    COUNT(DISTINCT CASE WHEN checklist_entries.status = ? THEN checklist_entries.control_id END) as na_count
                ', [
                    ChecklistEntry::STATUS_COMPLIANT,
                    ChecklistEntry::STATUS_PARTIAL,
                    ChecklistEntry::STATUS_NON_COMPLIANT,
                    ChecklistEntry::STATUS_NA,
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
                $compliantCount = $stats ? (int) $stats->compliant_count : 0;
                $partialCount = $stats ? (int) $stats->partial_count : 0;
                $nonCompliantCount = $stats ? (int) $stats->non_compliant_count : 0;
                $naCount = $stats ? (int) $stats->na_count : 0;

                $applicableCount = $compliantCount + $partialCount + $nonCompliantCount;
                $complianceRate = $applicableCount > 0 ? (int) round(($compliantCount / $applicableCount) * 100) : 0;
            } else {
                // Overall (non-unit roles): average each unit's compliant-control
                // count and rate from its most-recent session. Units never
                // assessed contribute 0 compliant (no latest session row).
                $perUnitRates = [];
                $perUnitCompliant = [];
                foreach ($unitRows as $row) {
                    $compliant = (int) $row->compliant_count;
                    $applicable = $compliant + (int) $row->partial_count + (int) $row->non_compliant_count;
                    $perUnitCompliant[] = $compliant;
                    if ($applicable > 0) {
                        $perUnitRates[] = $compliant / $applicable;
                    }
                }

                $compliantCount = $perUnitCompliant
                    ? (int) round(array_sum($perUnitCompliant) / count($perUnitCompliant))
                    : 0;
                $partialCount = (int) $unitRows->sum('partial_count');
                $nonCompliantCount = (int) $unitRows->sum('non_compliant_count');
                $naCount = (int) $unitRows->sum('na_count');

                $complianceRate = $perUnitRates
                    ? (int) round((array_sum($perUnitRates) / count($perUnitRates)) * 100)
                    : 0;

                // Overall applicable across units drives overall_compliance_rate.
                $applicableCount = $compliantCount + $partialCount + $nonCompliantCount;
            }

            $totalApplicableOverall += $applicableCount;
            $totalCompliantOverall += $compliantCount;

            $frameworksBreakdown[] = [
                'id' => $fw->id,
                'nama' => $fw->nama,
                'versi' => $fw->versi,
                'compliance_rate' => $complianceRate,
                'compliant_count' => $compliantCount,
                'partial_count' => $partialCount,
                'non_compliant_count' => $nonCompliantCount,
                'na_count' => $naCount,
                'total_controls' => $fw->controls_count,
            ];
        }

        $overallComplianceRate = $totalApplicableOverall > 0
            ? (int) round(($totalCompliantOverall / $totalApplicableOverall) * 100)
            : 0;

        // 2. Growth from last period (compare with previous month / session)
        $growthFromLastPeriod = $this->calculateGrowthRate($scopedUnitId, $overallComplianceRate);

        // 3. Findings Summary & Overdue Calculation via SQL Aggregate
        $today = Carbon::today();
        $findingQuery = Finding::query();
        if ($scopedUnitId) {
            $findingQuery->where('unit_id', $scopedUnitId);
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
            $riskQuery->whereHas('control.checklistEntries', fn ($q) => $q->where('unit_id', $scopedUnitId));
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
            'overall_compliance_rate' => $overallComplianceRate,
            'growth_from_last_period' => $growthFromLastPeriod,
            'total_controls_active' => array_sum(array_column($frameworksBreakdown, 'total_controls')),
            'frameworks_breakdown' => $frameworksBreakdown,
            'findings_summary' => $findingsSummary,
            'risks_summary' => $risksSummary,
        ];
    }

    /**
     * Get monthly compliance trends over the last N months.
     */
    public function getTrends(User $user, ?int $unitId = null, int $months = 6): array
    {
        $scopedUnitId = $this->resolveScopedUnitId($user, $unitId);
        $safeMonths = max(1, min($months, 24));
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
                ChecklistEntry::STATUS_COMPLIANT,
                ChecklistEntry::STATUS_COMPLIANT, ChecklistEntry::STATUS_PARTIAL, ChecklistEntry::STATUS_NON_COMPLIANT,
                ChecklistEntry::STATUS_COMPLIANT,
                ChecklistEntry::STATUS_COMPLIANT, ChecklistEntry::STATUS_PARTIAL, ChecklistEntry::STATUS_NON_COMPLIANT,
                ChecklistEntry::STATUS_COMPLIANT,
                ChecklistEntry::STATUS_COMPLIANT, ChecklistEntry::STATUS_PARTIAL, ChecklistEntry::STATUS_NON_COMPLIANT,
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
    public function getUnitComparisons(User $user): array
    {
        $scopedUnitId = $this->resolveScopedUnitId($user);

        $unitsQuery = WorkUnit::select('id', 'nama')->orderBy('nama');
        if ($scopedUnitId) {
            $unitsQuery->where('id', $scopedUnitId);
        }

        $units = $unitsQuery->get();
        $unitIds = $units->pluck('id');

        $entriesByUnit = ChecklistEntry::whereIn('unit_id', $unitIds)
            ->selectRaw('
                unit_id,
                COUNT(*) as total_entries,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as compliant_count,
                SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) as applicable_count
            ', [
                ChecklistEntry::STATUS_COMPLIANT,
                ChecklistEntry::STATUS_COMPLIANT,
                ChecklistEntry::STATUS_PARTIAL,
                ChecklistEntry::STATUS_NON_COMPLIANT,
            ])
            ->groupBy('unit_id')
            ->get()
            ->keyBy('unit_id');

        $findingsByUnit = Finding::whereIn('unit_id', $unitIds)
            ->whereIn('status', [Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS])
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
    public function getRecentActivities(User $user, int $limit = 6): array
    {
        if (! $user->hasPermissionTo('audit-log.view')) {
            return [];
        }

        $safeLimit = max(1, min((int) ($limit ?: 6), 100));

        return AuditLog::with(['actor.workUnit', 'actor.role'])
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
            ChecklistEntry::STATUS_COMPLIANT,
            ChecklistEntry::STATUS_COMPLIANT,
            ChecklistEntry::STATUS_PARTIAL,
            ChecklistEntry::STATUS_NON_COMPLIANT,
        ])->first();

        $applicableCount = (int) ($stats->applicable_count ?? 0);
        $compliantCount = (int) ($stats->compliant_count ?? 0);

        $previousRate = $applicableCount > 0 ? (int) round(($compliantCount / $applicableCount) * 100) : 0;

        return (float) round($currentRate - $previousRate, 1);
    }
}
