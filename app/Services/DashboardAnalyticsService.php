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
use Illuminate\Support\Collection;

class DashboardAnalyticsService
{
    /**
     * Resolve effective unit_id based on User role scoping.
     * PIC is strictly scoped to their assigned unit.
     */
    public function resolveScopedUnitId(User $user, ?int $requestedUnitId = null): ?int
    {
        if ($user->role === 'pic') {
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

        // 1. Frameworks Breakdown & Overall Compliance Rate
        $frameworks = Framework::withCount('controls')->orderBy('id')->get();
        $frameworksBreakdown = [];
        $totalApplicableOverall = 0;
        $totalCompliantOverall = 0;

        foreach ($frameworks as $fw) {
            $entryQuery = ChecklistEntry::whereHas('control', fn ($q) => $q->where('framework_id', $fw->id));

            if ($scopedUnitId) {
                $entryQuery->where('unit_id', $scopedUnitId);
            }

            if ($sessionId) {
                $entryQuery->where('session_id', $sessionId);
            }

            $entries = $entryQuery->select('status')->get();

            $compliantCount = $entries->where('status', ChecklistEntry::STATUS_COMPLIANT)->count();
            $partialCount = $entries->where('status', ChecklistEntry::STATUS_PARTIAL)->count();
            $nonCompliantCount = $entries->where('status', ChecklistEntry::STATUS_NON_COMPLIANT)->count();
            $naCount = $entries->where('status', ChecklistEntry::STATUS_NA)->count();

            $applicableCount = $compliantCount + $partialCount + $nonCompliantCount;
            $complianceRate = $applicableCount > 0 ? (int) round(($compliantCount / $applicableCount) * 100) : 0;

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

        // 3. Findings Summary & Overdue Calculation
        $findingQuery = Finding::query();
        if ($scopedUnitId) {
            $findingQuery->where('unit_id', $scopedUnitId);
        }

        $findings = $findingQuery->get();
        $activeFindings = $findings->whereIn('status', [Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS]);

        $today = Carbon::today();
        $overdueCount = $activeFindings->filter(function (Finding $f) use ($today) {
            return $f->deadline && Carbon::parse($f->deadline)->isBefore($today);
        })->count();

        $findingsSummary = [
            'total_active' => $activeFindings->count(),
            'major' => $activeFindings->where('kategori', Finding::KATEGORI_MAJOR)->count(),
            'minor' => $activeFindings->where('kategori', Finding::KATEGORI_MINOR)->count(),
            'observasi' => $activeFindings->where('kategori', Finding::KATEGORI_OBSERVASI)->count(),
            'overdue' => $overdueCount,
        ];

        // 4. Risks Summary
        $riskQuery = Risk::query();
        if ($scopedUnitId) {
            $riskQuery->whereHas('control.checklistEntries', fn ($q) => $q->where('unit_id', $scopedUnitId));
        }

        $risks = $riskQuery->get();
        $activeRisks = $risks->where('status', '!=', Risk::STATUS_ACCEPTED);

        $risksSummary = [
            'total_active' => $activeRisks->count(),
            'critical' => $activeRisks->where('level_risiko', Risk::LEVEL_CRITICAL)->count(),
            'high' => $activeRisks->where('level_risiko', Risk::LEVEL_HIGH)->count(),
            'medium' => $activeRisks->where('level_risiko', Risk::LEVEL_MEDIUM)->count(),
            'low' => $activeRisks->where('level_risiko', Risk::LEVEL_LOW)->count(),
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
            $date = Carbon::now()->subMonths($i);
            $yearMonth = $date->format('Y-m');
            $label = $date->translatedFormat('F Y');
            $endOfPeriod = $date->copy()->endOfMonth();

            // Query entries up to the end of this month/period
            $entryQuery = ChecklistEntry::with('control')
                ->where('tanggal_input', '<=', $endOfPeriod);

            if ($scopedUnitId) {
                $entryQuery->where('unit_id', $scopedUnitId);
            }

            $entries = $entryQuery->get();

            $iso27001Entries = $entries->filter(fn ($e) => $e->control?->framework_id === 1);
            $iso27701Entries = $entries->filter(fn ($e) => $e->control?->framework_id === 2);

            $iso27001Rate = $this->computeRate($iso27001Entries);
            $iso27701Rate = $this->computeRate($iso27701Entries);
            $overallRate = $this->computeRate($entries);

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

        // Batch query all entries and findings to prevent N+1 queries
        $entriesByUnit = ChecklistEntry::whereIn('unit_id', $unitIds)
            ->select('id', 'unit_id', 'status')
            ->get()
            ->groupBy('unit_id');

        $findingsByUnit = Finding::whereIn('unit_id', $unitIds)
            ->whereIn('status', [Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS])
            ->select('id', 'unit_id')
            ->get()
            ->groupBy('unit_id');

        return $units->map(function (WorkUnit $unit) use ($entriesByUnit, $findingsByUnit) {
            $entries = $entriesByUnit->get($unit->id, collect());
            $compliantCount = $entries->where('status', ChecklistEntry::STATUS_COMPLIANT)->count();
            $applicableCount = $entries->whereIn('status', [
                ChecklistEntry::STATUS_COMPLIANT,
                ChecklistEntry::STATUS_PARTIAL,
                ChecklistEntry::STATUS_NON_COMPLIANT,
            ])->count();

            $rate = $applicableCount > 0 ? (int) round(($compliantCount / $applicableCount) * 100) : 0;
            $openFindings = $findingsByUnit->get($unit->id, collect())->count();

            return [
                'unit_id' => $unit->id,
                'unit_nama' => $unit->nama,
                'compliance_rate' => $rate,
                'total_entries' => $entries->count(),
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
        if (! in_array($user->role, ['superadmin', 'admin_kepatuhan', 'koordinator_smki', 'auditor'])) {
            return [];
        }

        $safeLimit = max(1, min((int) ($limit ?: 6), 100));

        return AuditLog::with('actor.workUnit')
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
     * Compute compliance percentage from a collection of checklist entries.
     */
    protected function computeRate(Collection $entries): int
    {
        $applicable = $entries->whereIn('status', [
            ChecklistEntry::STATUS_COMPLIANT,
            ChecklistEntry::STATUS_PARTIAL,
            ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        $applicableCount = $applicable->count();
        if ($applicableCount === 0) {
            return 0;
        }

        $compliantCount = $applicable->where('status', ChecklistEntry::STATUS_COMPLIANT)->count();

        return (int) round(($compliantCount / $applicableCount) * 100);
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

        $previousEntries = $query->get();
        $previousRate = $this->computeRate($previousEntries);

        return (float) round($currentRate - $previousRate, 1);
    }
}
