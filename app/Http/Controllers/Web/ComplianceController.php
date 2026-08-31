<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ComplianceService;
use App\Services\DashboardAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComplianceController extends Controller
{
    public function __construct(
        protected ComplianceService $complianceService,
        protected DashboardAnalyticsService $analyticsService
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'unit_id', 'framework_id', 'kategori']);
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        $frameworks = $this->complianceService->getFrameworkSummaries();
        $workUnits = $this->complianceService->getWorkUnits();
        $controls = $this->complianceService->getControls($filters, $perPage);
        $sessions = $this->complianceService->getChecklistSessions($filters);

        return Inertia::render('shared/controls/compliance', [
            'frameworks' => $frameworks,
            'controls' => $controls,
            'workUnits' => $workUnits,
            'sessions' => $sessions,
            'filters' => $filters,
        ]);
    }

    public function sessions(Request $request): Response
    {
        $filters = $request->only(['search', 'unit_id', 'framework_id', 'periode']);

        $sessions = $this->complianceService->getAdminSessions($filters);

        return Inertia::render('shared/checklist/sessions', [
            'sessions' => $sessions,
            'workUnits' => $this->complianceService->getWorkUnits(),
            'frameworks' => $this->complianceService->getFrameworkSummaries(),
            'periodeOptions' => $this->complianceService->getSessionPeriodeOptions(),
            'filters' => $filters,
        ]);
    }

    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $unitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;
        $sessionId = $request->filled('session_id') ? (int) $request->input('session_id') : null;
        $timeframe = $request->input('months');
        $months = in_array((string) $timeframe, ['3', '6', '12'], true) ? (int) $timeframe : null;

        $summary = $this->analyticsService->getSummary($user, $unitId, $sessionId, $months);
        $trends = $this->analyticsService->getTrends($user, $unitId, $months);
        $unitComparisons = $this->analyticsService->getUnitComparisons($user, $months);
        $recentActivities = $this->analyticsService->getRecentActivities($user, 6, $months);
        $workUnits = $this->complianceService->getWorkUnits();

        return Inertia::render('admin-kepatuhan/dashboard', [
            'summary' => $summary,
            'trends' => $trends,
            'unit_comparisons' => $unitComparisons,
            'recent_activities' => $recentActivities,
            'workUnits' => $workUnits,
            'filters' => [
                'unit_id' => $unitId,
                'session_id' => $sessionId,
                'months' => $months ? (string) $months : 'all',
            ],
        ]);
    }
}
