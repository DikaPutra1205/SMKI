<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRiskRequest;
use App\Http\Requests\UpdateRiskRequest;
use App\Models\Risk;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RiskController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Risk::class);

        $user = $request->user();
        $query = Risk::with(['control.framework', 'unit']);

        if ($user->isPic()) {
            $query->where(function ($q) use ($user) {
                $q->where('unit_id', $user->unit_id)
                    ->orWhereHas('control.checklistEntries', fn ($cq) => $cq->where('unit_id', $user->unit_id));
            });
        } elseif ($request->filled('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }

        if ($request->filled('level_risiko')) {
            $query->where('level_risiko', $request->level_risiko);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $risks = $query->latest()->paginate(20);

        return $this->success($risks);
    }

    public function store(StoreRiskRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        if ($user->isPic()) {
            $data['unit_id'] = $user->unit_id;
        }

        if (isset($data['risk_level'])) {
            $data['level_risiko'] = $data['risk_level'];
        }
        if (isset($data['risk_owner'])) {
            $data['pemilik_risiko'] = $data['risk_owner'];
        }
        if (isset($data['mitigation_plan'])) {
            $data['rencana_mitigasi'] = $data['mitigation_plan'];
        }
        if (isset($data['admin_notes'])) {
            $data['catatan_admin'] = $data['admin_notes'];
        }

        $risk = Risk::create($data);

        return $this->created($risk->load(['control.framework', 'unit']));
    }

    public function show(Risk $risk): JsonResponse
    {
        Gate::authorize('view', $risk);

        return $this->success($risk->load(['control.framework', 'unit']));
    }

    public function update(UpdateRiskRequest $request, Risk $risk): JsonResponse
    {
        $data = $request->validated();

        $updateData = [];
        if (isset($data['risk_level'])) {
            $updateData['level_risiko'] = $data['risk_level'];
        } elseif (isset($data['level_risiko'])) {
            $updateData['level_risiko'] = $data['level_risiko'];
        }

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        if (array_key_exists('mitigation_plan', $data)) {
            $updateData['rencana_mitigasi'] = $data['mitigation_plan'];
        } elseif (array_key_exists('rencana_mitigasi', $data)) {
            $updateData['rencana_mitigasi'] = $data['rencana_mitigasi'];
        }

        if (isset($data['risk_owner'])) {
            $updateData['pemilik_risiko'] = $data['risk_owner'];
        } elseif (isset($data['pemilik_risiko'])) {
            $updateData['pemilik_risiko'] = $data['pemilik_risiko'];
        }

        if (array_key_exists('unit_id', $data)) {
            $updateData['unit_id'] = $data['unit_id'];
        }

        if (array_key_exists('deadline', $data)) {
            $updateData['deadline'] = $data['deadline'];
        }

        if (array_key_exists('admin_notes', $data)) {
            $updateData['catatan_admin'] = $data['admin_notes'];
        } elseif (array_key_exists('catatan_admin', $data)) {
            $updateData['catatan_admin'] = $data['catatan_admin'];
        }

        $risk->update($updateData);

        return $this->success($risk->fresh(['control.framework', 'unit']), 'Risiko berhasil diperbarui');
    }

    public function destroy(Risk $risk): JsonResponse
    {
        Gate::authorize('delete', $risk);

        $risk->delete();

        return $this->success(null, 'Risiko berhasil dihapus');
    }
}
