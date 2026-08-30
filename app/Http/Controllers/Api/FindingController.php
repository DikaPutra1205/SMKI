<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Finding;
use App\Models\FindingStatusHistory;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FindingController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Finding::with(['control', 'unit', 'pic:id,name', 'admin:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        $findings = $query->latest()->paginate(20);

        return $this->success($findings);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'control_id' => 'required|exists:controls,id',
            'unit_id' => 'required|exists:work_units,id',
            'pic_id' => 'required|exists:users,id',
            'admin_id' => 'nullable|exists:users,id',
            'kategori' => 'required|in:major,minor,observasi',
            'status' => 'sometimes|in:open,in_progress,resolved,closed',
            'deadline' => 'nullable|date',
            'catatan_admin' => 'nullable|string',
            'catatan' => 'nullable|string',
        ]);

        $note = $data['catatan'] ?? $data['catatan_admin'] ?? 'Temuan audit diterbitkan.';
        $data['catatan_admin'] = $note;

        $finding = Finding::create($data);

        FindingStatusHistory::create([
            'finding_id' => $finding->id,
            'user_id' => $request->user()?->id ?? $data['admin_id'] ?? $data['pic_id'],
            'from_status' => null,
            'to_status' => $finding->status,
            'catatan' => $note,
        ]);

        return $this->created($finding->load(['control', 'unit', 'pic:id,name', 'histories.user']));
    }

    public function show(Finding $finding): JsonResponse
    {
        return $this->success($finding->load(['control', 'unit', 'pic:id,name', 'admin:id,name', 'histories.user']));
    }

    public function update(Request $request, Finding $finding): JsonResponse
    {
        $data = $request->validate([
            'status' => 'sometimes|in:open,in_progress,resolved,closed',
            'kategori' => 'sometimes|in:major,minor,observasi',
            'deadline' => 'nullable|date',
            'catatan_admin' => 'nullable|string',
            'catatan' => 'nullable|string',
            'pic_id' => 'sometimes|exists:users,id',
        ]);

        $oldStatus = $finding->status;
        $newStatus = $data['status'] ?? null;

        if ($newStatus !== null && $newStatus !== $oldStatus) {
            $note = $data['catatan'] ?? $data['catatan_admin'] ?? "Status diubah dari {$oldStatus} ke {$newStatus}";
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

        $finding->update($data);

        return $this->success($finding->fresh(['control', 'unit', 'pic:id,name', 'histories.user']), 'Temuan berhasil diperbarui');
    }

    /** Update status temuan saja */
    public function updateStatus(Request $request, Finding $finding): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
            'admin_id' => 'nullable|exists:users,id',
            'catatan' => 'nullable|string',
        ]);

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
        $finding->delete();

        return $this->success(null, 'Temuan berhasil dihapus');
    }
}
