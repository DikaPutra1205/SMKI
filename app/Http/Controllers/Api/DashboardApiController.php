<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardApiController extends Controller
{
    public function __construct(
        protected DashboardAnalyticsService $analyticsService
    ) {}

    /**
     * GET /api/v1/dashboard/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $unitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;
        $sessionId = $request->filled('session_id') ? (int) $request->input('session_id') : null;

        $summary = $this->analyticsService->getSummary($user, $unitId, $sessionId);

        return response()->json([
            'status' => 'success',
            'data' => $summary,
        ]);
    }

    /**
     * GET /api/v1/dashboard/trends
     */
    public function trends(Request $request): JsonResponse
    {
        $user = $request->user();
        $unitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;
        $months = (int) $request->input('months', 6);

        $trends = $this->analyticsService->getTrends($user, $unitId, $months);

        return response()->json([
            'status' => 'success',
            'data' => $trends,
        ]);
    }

    /**
     * GET /api/v1/dashboard/unit-comparison
     */
    public function unitComparison(Request $request): JsonResponse
    {
        $user = $request->user();
        $comparisons = $this->analyticsService->getUnitComparisons($user);

        return response()->json([
            'status' => 'success',
            'data' => $comparisons,
        ]);
    }

    /**
     * GET /api/v1/dashboard/recent-activities
     */
    public function recentActivities(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, ['superadmin', 'admin_kepatuhan', 'koordinator_smki', 'auditor'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Jejak audit hanya dapat diakses oleh Superadmin, Admin Kepatuhan, Koordinator SMKI, dan Auditor.',
            ], 403);
        }

        $limit = (int) $request->input('limit', 6);
        $activities = $this->analyticsService->getRecentActivities($user, $limit);

        return response()->json([
            'status' => 'success',
            'data' => $activities,
        ]);
    }
}
