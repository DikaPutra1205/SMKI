<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Routing\PageDispatcher;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PageController extends Controller
{
    public function __construct(protected PageDispatcher $dispatcher) {}

    public function dashboard(Request $request)
    {
        return $this->render($request, 'dashboard');
    }

    public function frameworks(Request $request)
    {
        return $this->render($request, 'frameworks');
    }

    public function users(Request $request)
    {
        return $this->render($request, 'users');
    }

    public function roles(Request $request)
    {
        return $this->render($request, 'roles');
    }

    public function assessments(Request $request)
    {
        return $this->render($request, 'assessments');
    }

    public function compliance(Request $request)
    {
        return $this->render($request, 'compliance');
    }

    public function findings(Request $request)
    {
        return $this->render($request, 'findings');
    }

    public function risks(Request $request)
    {
        return $this->render($request, 'risks');
    }

    public function auditLogs(Request $request)
    {
        return $this->render($request, 'audit-logs');
    }

    private function render(Request $request, string $page)
    {
        $user = $request->user();
        $resolution = $this->dispatcher->resolve($user, $page);

        if (! $resolution->allowed) {
            // Plan: unauthorized flat page → 403 or redirect. Choose 403 for explicit hits.
            throw new HttpException(403, 'Forbidden');
        }

        // Dispatch to the appropriate legacy controller by page, but gate already passed.
        // Keep thin delegation to existing controllers to preserve business logic.
        return match ($page) {
            'dashboard' => $this->dashboardForRole($request, $user),
            'frameworks' => app(FrameworkController::class)->index($request),
            'users' => app(UserController::class)->index($request),
            'roles' => app(RoleController::class)->index($request),
            'assessments' => app(ChecklistSessionController::class)->index($request),
            'compliance' => app(ComplianceController::class)->index($request),
            'findings' => app(ComplianceOfficerController::class)->findings($request),
            'risks' => app(ComplianceOfficerController::class)->risks($request),
            'audit-logs' => app(AuditLogController::class)->index($request),
            default => Inertia::render($resolution->component ?? 'welcome'),
        };
    }

    private function dashboardForRole(Request $request, $user)
    {
        $roleName = $user->role()->value('name');

        return match ($roleName) {
            'superadmin' => app(FrameworkController::class)->dashboard($request),
            'auditor' => app(AuditorDashboardController::class)->index($request),
            'pic' => app(PicDashboardController::class)->index($request),
            default => app(ComplianceController::class)->dashboard($request),
        };
    }
}
