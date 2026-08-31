<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ComplianceService;
use App\Services\DashboardAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditorDashboardController extends Controller
{
    public function __construct(
        protected DashboardAnalyticsService $analyticsService,
        protected ComplianceService $complianceService
    ) {}

    /**
     * Auditor Dashboard page — mirrors the ComplianceController::dashboard pattern.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $unitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;
        $sessionId = $request->filled('session_id') ? (int) $request->input('session_id') : null;
        $timeframe = $request->input('months');
        $months = in_array((string) $timeframe, ['3', '6', '12'], true) ? (int) $timeframe : null;

        return Inertia::render('auditor/dashboard', [
            'summary' => $this->analyticsService->getSummary($user, $unitId, $sessionId, $months),
            'trends' => $this->analyticsService->getTrends($user, $unitId, $months),
            'unit_comparisons' => $this->analyticsService->getUnitComparisons($user, $months),
            'recent_activities' => $this->analyticsService->getRecentActivities($user, 6, $months),
            'workUnits' => $this->complianceService->getWorkUnits(),
            'filters' => [
                'unit_id' => $unitId,
                'session_id' => $sessionId,
                'months' => $months ? (string) $months : 'all',
            ],
        ]);
    }
}
