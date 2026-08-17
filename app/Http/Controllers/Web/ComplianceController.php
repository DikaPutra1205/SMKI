<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ComplianceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComplianceController extends Controller
{
    public function __construct(
        protected ComplianceService $complianceService
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'unit_id', 'framework_id', 'kategori']);

        $frameworks = $this->complianceService->getFrameworkSummaries();
        $workUnits = $this->complianceService->getWorkUnits();
        $controls = $this->complianceService->getControls($filters);
        $sessions = $this->complianceService->getChecklistSessions($filters);

        return Inertia::render('admin-kepatuhan/compliance', [
            'frameworks' => $frameworks,
            'controls' => $controls,
            'workUnits' => $workUnits,
            'sessions' => $sessions,
            'filters' => $filters,
        ]);
    }

    public function dashboard(): Response
    {
        $frameworks = $this->complianceService->getFrameworkSummaries();

        return Inertia::render('admin-kepatuhan/dashboard', [
            'frameworks' => $frameworks,
        ]);
    }
}
