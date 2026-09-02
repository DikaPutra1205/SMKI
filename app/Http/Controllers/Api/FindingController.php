<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFindingRequest;
use App\Http\Requests\UpdateFindingRequest;
use App\Http\Requests\UpdateFindingStatusRequest;
use App\Models\Finding;
use App\Models\FindingStatusHistory;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FindingController extends Controller
{
    use ApiResponse;

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
        $data = $request->validated();

        $oldStatus = $finding->status;
        $newStatus = $data['status'] ?? null;

        if ($newStatus !== null && $newStatus !== $oldStatus) {
            $note = $data['catatan'] ?? $data['catatan_admin'] ?? $data['admin_notes'] ?? $data['notes'] ?? "Status diubah dari {$oldStatus} ke {$newStatus}";
            FindingStatusHistory::create([
                'finding_id' => $finding->id,
                'user_id' => $request->user()?->id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'catatan' => $note,
            ]);

            if ($newStatus === 'closed') {
                $data['tanggal_verifikasi'] = now();
            } elseif ($oldStatus === 'closed') {
                $data['tanggal_verifikasi'] = null;
            }
        }

        if (isset($data['category']) && ! isset($data['kategori'])) {
            $data['kategori'] = $data['category'];
        }

        if (isset($data['admin_notes']) && ! isset($data['catatan_admin'])) {
            $data['catatan_admin'] = $data['admin_notes'];
        }

        $finding->update($data);

        return $this->success($finding->fresh(['control', 'unit', 'pic:id,name', 'histories.user']), 'Temuan berhasil diperbarui');
    }

    /** Update status temuan saja */
    public function updateStatus(UpdateFindingStatusRequest $request, Finding $finding): JsonResponse
    {
        $data = $request->validated();

        $oldStatus = $finding->status;
        $newStatus = $data['status'];
        $actorId = $request->user()?->id ?? $data['admin_id'] ?? null;
        $note = $data['catatan'] ?? "Status diubah dari {$oldStatus} ke {$newStatus}";

        $update = ['status' => $newStatus];
        if ($actorId) {
            $update['admin_id'] = $actorId;
        }

        if ($newStatus === 'closed') {
            $update['tanggal_verifikasi'] = now();
        } elseif ($oldStatus === 'closed') {
            $update['tanggal_verifikasi'] = null;
        }

        FindingStatusHistory::create([
            'finding_id' => $finding->id,
            'user_id' => $actorId,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'catatan' => $note,
        ]);

        $finding->update($update);

        return $this->success($finding->fresh(['control', 'unit', 'pic:id,name', 'histories.user']), 'Status temuan berhasil diperbarui');
    }

    public function destroy(Finding $finding): JsonResponse
    {
        Gate::authorize('delete', $finding);

        $finding->delete();

        return $this->success(null, 'Temuan berhasil dihapus');
    }
}
