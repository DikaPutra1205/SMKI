<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __construct(
        protected AuditTrailService $auditTrailService
    ) {}

    /**
     * Audit Trail Viewer Page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['action', 'aksi', 'entity_type', 'actor_id', 'start_date', 'end_date', 'search']);
        $logs = $this->auditTrailService->getAuditLogs($user, $filters, 25);
        $stats = $this->auditTrailService->getAuditStats($user);
        $actors = User::select(['id', 'name', 'email', 'role'])->orderBy('name')->get();

        return Inertia::render('admin-kepatuhan/audit-logs', [
            'logs' => $logs,
            'stats' => $stats,
            'filters' => $filters,
            'actors' => $actors,
        ]);
    }
}
