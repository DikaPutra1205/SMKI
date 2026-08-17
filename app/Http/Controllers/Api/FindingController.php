<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Finding;
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
            'status' => 'sometimes|in:open,in_progress,closed',
            'deadline' => 'nullable|date|after:today',
            'catatan_admin' => 'nullable|string',
        ]);

        $finding = Finding::create($data);

        return $this->created($finding->load(['control', 'unit', 'pic:id,name']));
    }

    public function show(Finding $finding): JsonResponse
    {
        return $this->success($finding->load(['control', 'unit', 'pic:id,name', 'admin:id,name']));
    }

    public function update(Request $request, Finding $finding): JsonResponse
    {
        $data = $request->validate([
            'kategori' => 'sometimes|in:major,minor,observasi',
            'deadline' => 'nullable|date',
            'catatan_admin' => 'nullable|string',
            'pic_id' => 'sometimes|exists:users,id',
        ]);

        $finding->update($data);

        return $this->success($finding, 'Temuan berhasil diperbarui');
    }

    /** Update status temuan saja */
    public function updateStatus(Request $request, Finding $finding): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:open,in_progress,closed',
            'admin_id' => 'required|exists:users,id',
        ]);

        $update = ['status' => $data['status'], 'admin_id' => $data['admin_id']];

        if ($data['status'] === 'closed') {
            $update['tanggal_verifikasi'] = now();
        }

        $finding->update($update);

        return $this->success($finding->fresh(), 'Status temuan berhasil diperbarui');
    }

    public function destroy(Finding $finding): JsonResponse
    {
        $finding->delete();

        return $this->success(null, 'Temuan berhasil dihapus');
    }
}
