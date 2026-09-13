<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFindingRequest;
use App\Http\Requests\UpdateFindingRequest;
use App\Http\Requests\UpdateFindingStatusRequest;
use App\Models\Finding;
use App\Models\FindingStatusHistory;
use App\Models\User;
use App\Services\ComplianceOfficerService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FindingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ComplianceOfficerService $complianceOfficerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Finding::class);

        $user = $request->user();
        $query = Finding::with(['control', 'unit', 'pic:id,name', 'admin:id,name']);

        if ($user?->isPic()) {
            $query->where('unit_id', $user->unit_id);
        } elseif ($request->filled('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        $findings = $query->latest()->paginate(20);

        return $this->success($findings);
    }

    public function store(StoreFindingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        if (empty($data['pic_id'])) {
            $unitPic = User::where('unit_id', $data['unit_id'])
                ->whereHas('role', fn ($q) => $q->where('name', User::ROLE_PIC))
                ->first();
            $data['pic_id'] = $unitPic?->id ?? $user?->id;
        }

        if (empty($data['admin_id']) && $user) {
            $data['admin_id'] = $user->id;
        }

        $note = $data['catatan'] ?? $data['catatan_admin'] ?? $data['admin_notes'] ?? 'Temuan audit diterbitkan.';
        $data['catatan_admin'] = $note;

        $finding = Finding::create($data);

        FindingStatusHistory::create([
            'finding_id' => $finding->id,
            'user_id' => $user?->id ?? $data['admin_id'] ?? $data['pic_id'],
            'from_status' => null,
            'to_status' => $finding->status,
            'catatan' => $note,
        ]);

        return $this->created($finding->load(['control', 'unit', 'pic:id,name', 'histories.user']));
    }

    public function show(Finding $finding): JsonResponse
    {
        Gate::authorize('view', $finding);

        return $this->success($finding->load(['control', 'unit', 'pic:id,name', 'admin:id,name', 'histories.user']));
    }

    public function update(UpdateFindingRequest $request, Finding $finding): JsonResponse
    {
        $user = $request->user();
        $updated = $this->complianceOfficerService->updateFinding($user, $finding, $request->validated());

        return $this->success($updated, 'Temuan berhasil diperbarui');
    }

    /** Update status temuan saja */
    public function updateStatus(UpdateFindingStatusRequest $request, Finding $finding): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Delegated to the service so role rules apply uniformly: PIC may not
        // close a finding, and every status change lands in the audit trail.
        $updated = $this->complianceOfficerService->updateFinding($user, $finding, [
            'status' => $data['status'],
            'catatan' => $data['catatan'] ?? null,
        ]);

        return $this->success($updated, 'Status temuan berhasil diperbarui');
    }

    public function destroy(Finding $finding): JsonResponse
    {
        Gate::authorize('delete', $finding);

        $finding->delete();

        return $this->success(null, 'Temuan berhasil dihapus');
    }
}
