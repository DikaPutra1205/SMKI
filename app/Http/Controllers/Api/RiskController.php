<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Risk;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Risk::with('control.framework');

        if ($request->filled('level_risiko')) {
            $query->where('level_risiko', $request->level_risiko);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $risks = $query->latest()->paginate(20);

        return $this->success($risks);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'control_id' => 'required|exists:controls,id',
            'level_risiko' => 'required|in:low,medium,high,critical',
            'pemilik_risiko' => 'required|string|max:255',
            'rencana_mitigasi' => 'nullable|string',
            'status' => 'sometimes|in:open,mitigated,accepted',
        ]);

        $risk = Risk::create($data);

        return $this->created($risk->load('control'));
    }

    public function show(Risk $risk): JsonResponse
    {
        return $this->success($risk->load('control.framework'));
    }

    public function update(Request $request, Risk $risk): JsonResponse
    {
        $data = $request->validate([
            'level_risiko' => 'sometimes|in:low,medium,high,critical',
            'pemilik_risiko' => 'sometimes|string|max:255',
            'rencana_mitigasi' => 'nullable|string',
            'status' => 'sometimes|in:open,mitigated,accepted',
        ]);

        $risk->update($data);

        return $this->success($risk, 'Risiko berhasil diperbarui');
    }

    public function destroy(Risk $risk): JsonResponse
    {
        $risk->delete();

        return $this->success(null, 'Risiko berhasil dihapus');
    }
}
