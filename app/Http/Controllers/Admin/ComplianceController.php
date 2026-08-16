<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreControlRequest;
use App\Http\Requests\Admin\UpdateControlRequest;
use App\Models\Control;
use App\Services\ComplianceService;
use Illuminate\Http\RedirectResponse;
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

        return Inertia::render('admin-kepatuhan/compliance', [
            'frameworks' => $frameworks,
            'controls' => $controls,
            'workUnits' => $workUnits,
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

    public function store(StoreControlRequest $request): RedirectResponse
    {
        Control::create($request->validated());

        return redirect()->back()->with('flash', [
            'success' => 'Kontrol berhasil ditambahkan.',
        ]);
    }

    public function update(UpdateControlRequest $request, Control $control): RedirectResponse
    {
        $control->update($request->validated());

        return redirect()->back()->with('flash', [
            'success' => 'Kontrol berhasil diperbarui.',
        ]);
    }

    public function destroy(Control $control): RedirectResponse
    {
        $control->delete();

        return redirect()->back()->with('flash', [
            'success' => 'Kontrol berhasil dihapus.',
        ]);
    }
}
