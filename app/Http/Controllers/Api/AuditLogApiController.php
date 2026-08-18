<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditTrailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogApiController extends Controller
{
    public function __construct(
        protected AuditTrailService $auditTrailService
    ) {}

    /**
     * GET /api/v1/audit-logs
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['action', 'aksi', 'entity_type', 'actor_id', 'start_date', 'end_date', 'search']);
        $perPage = (int) $request->input('per_page', 25);

        $logs = $this->auditTrailService->getAuditLogs($user, $filters, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $logs,
        ]);
    }

    /**
     * GET /api/v1/audit-logs/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $stats = $this->auditTrailService->getAuditStats($user);

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }
}
