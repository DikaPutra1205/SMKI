<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkVerifyChecklistRequest;
use App\Http\Requests\StoreFindingRequest;
use App\Http\Requests\StoreRiskRequest;
use App\Http\Requests\UpdateFindingRequest;
use App\Http\Requests\UpdateRiskRequest;
use App\Models\Finding;
use App\Models\Risk;
use App\Services\ComplianceOfficerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceOfficerApiController extends Controller
{
    public function __construct(
        protected ComplianceOfficerService $complianceOfficerService
    ) {}

    /**
     * GET /api/v1/compliance-officer/findings
     */
    public function indexFindings(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['status', 'category', 'kategori', 'unit_id', 'is_overdue', 'search']);
        $perPage = (int) $request->input('per_page', 15);

        $findings = $this->complianceOfficerService->getFindings($user, $filters, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $findings,
        ]);
    }

    /**
     * POST /api/v1/compliance-officer/findings
     */
    public function storeFinding(StoreFindingRequest $request): JsonResponse
    {
        $user = $request->user();
        $finding = $this->complianceOfficerService->storeFinding($user, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Temuan audit baru berhasil diterbitkan.',
            'data' => $finding,
        ], 201);
    }

    /**
     * GET /api/v1/compliance-officer/findings/{id}
     */
    public function showFinding(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $finding = $this->complianceOfficerService->getFinding($user, $id);

        return response()->json([
            'status' => 'success',
            'data' => $finding,
        ]);
    }

    /**
     * PUT /api/v1/compliance-officer/findings/{id}
     */
    public function updateFinding(UpdateFindingRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $finding = Finding::findOrFail($id);

        $updated = $this->complianceOfficerService->updateFinding($user, $finding, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Temuan audit berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    /**
     * GET /api/v1/compliance-officer/risks
     */
    public function indexRisks(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['risk_level', 'level_risiko', 'status', 'unit_id', 'search']);
        $perPage = (int) $request->input('per_page', 15);

        $risks = $this->complianceOfficerService->getRisks($user, $filters, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $risks,
        ]);
    }

    /**
     * POST /api/v1/compliance-officer/risks
     */
    public function storeRisk(StoreRiskRequest $request): JsonResponse
    {
        $user = $request->user();
        $risk = $this->complianceOfficerService->storeRisk($user, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Register risiko baru berhasil ditambahkan.',
            'data' => $risk,
        ], 201);
    }

    /**
     * GET /api/v1/compliance-officer/risks/matrix
     */
    public function riskMatrix(Request $request): JsonResponse
    {
        $user = $request->user();
        $matrix = $this->complianceOfficerService->getRiskMatrix($user);

        return response()->json([
            'status' => 'success',
            'data' => $matrix,
        ]);
    }

    /**
     * GET /api/v1/compliance-officer/risks/{id}
     */
    public function showRisk(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $risk = $this->complianceOfficerService->getRisk($user, $id);

        return response()->json([
            'status' => 'success',
            'data' => $risk,
        ]);
    }

    /**
     * PUT /api/v1/compliance-officer/risks/{id}
     */
    public function updateRisk(UpdateRiskRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $risk = Risk::findOrFail($id);

        $updated = $this->complianceOfficerService->updateRisk($user, $risk, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Register risiko berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    /**
     * POST /api/v1/compliance-officer/bulk-verify
     */
    public function bulkVerify(BulkVerifyChecklistRequest $request): JsonResponse
    {
        $user = $request->user();
        $entryIds = $request->input('entry_ids', []);
        $status = $request->input('status');
        $adminNotes = $request->input('admin_notes');

        $verifiedCount = $this->complianceOfficerService->bulkVerifyChecklistEntries($user, $entryIds, $status, $adminNotes);

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil memverifikasi {$verifiedCount} entri checklist secara massal.",
            'data' => [
                'verified_count' => $verifiedCount,
            ],
        ]);
    }
}
