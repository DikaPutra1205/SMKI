<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\WorkUnit;
use App\Services\ReportGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __construct(
        protected ReportGeneratorService $reportService
    ) {}

    /**
     * Download Compliance Audit Report PDF with dynamic type and period range selection.
     *
     * GET /reports/export-pdf?type=quick-summary|executive|audit-ready&unit_id=...&periode=YYYY-MM&start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
     */
    public function exportPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $request->validate([
            'type' => 'nullable|string|in:quick-summary,executive,audit-ready',
            'unit_id' => 'nullable|integer',
            'periode' => 'nullable|string',
            'start_date' => 'nullable|string',
            'end_date' => 'nullable|string',
            'print_mode' => 'nullable|string|in:latest,per_month',
        ]);

        $reportType = $request->input('type', 'quick-summary');
        $user = $request->user();
        $unitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;
        $periode = $request->input('periode');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $printMode = $request->input('print_mode', 'latest');

        return $this->reportService->export(
            $reportType,
            $user,
            $unitId,
            $periode,
            $startDate,
            $endDate,
            $printMode,
        );
    }

    /**
     * Invoke handler for GET /reports/export-pdf.
     */
    public function __invoke(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        return $this->exportPdf($request);
    }

    /**
     * Get available monthly reporting periods (from earliest DB record to current month) and work units.
     *
     * GET /reports/periods
     */
    public function periods(): JsonResponse
    {
        $periods = $this->reportService->getAvailablePeriods();
        $workUnits = WorkUnit::orderBy('nama')->get(['id', 'nama']);

        return response()->json([
            'status' => 'success',
            'data' => $periods,
            'work_units' => $workUnits,
        ]);
    }

    /**
     * Get list of all active work units for report scoping.
     *
     * GET /reports/work-units
     */
    public function workUnits(): JsonResponse
    {
        $workUnits = WorkUnit::orderBy('nama')->get(['id', 'nama']);

        return response()->json([
            'status' => 'success',
            'data' => $workUnits,
        ]);
    }

    /**
     * Download Compliance Report CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $unitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;

        return $this->reportService->exportComplianceSummaryCsv($user, $unitId);
    }
}
