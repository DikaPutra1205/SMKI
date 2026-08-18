<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ReportGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __construct(
        protected ReportGeneratorService $reportService
    ) {}

    /**
     * Download Compliance Audit Report PDF.
     */
    public function exportPdf(Request $request): Response
    {
        $user = $request->user();
        $unitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;

        return $this->reportService->exportComplianceSummaryPdf($user, $unitId);
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
