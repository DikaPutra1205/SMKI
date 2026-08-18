<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportApiController extends Controller
{
    public function __construct(
        protected ReportGeneratorService $reportService
    ) {}

    /**
     * GET /api/v1/reports/compliance-summary
     */
    public function complianceSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $unitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;

        $reportData = $this->reportService->getComplianceReportData($user, $unitId);

        return response()->json([
            'status' => 'success',
            'data' => $reportData,
        ]);
    }

    /**
     * GET /api/v1/reports/export-pdf
     */
    public function exportPdf(Request $request): Response
    {
        $user = $request->user();
        $unitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;

        return $this->reportService->exportComplianceSummaryPdf($user, $unitId);
    }

    /**
     * GET /api/v1/reports/export-csv
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $unitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;

        return $this->reportService->exportComplianceSummaryCsv($user, $unitId);
    }
}
