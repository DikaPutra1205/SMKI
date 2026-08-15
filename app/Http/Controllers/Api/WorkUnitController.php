<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkUnit;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkUnitController extends Controller
{
    use ApiResponse;

    /** Mengembalikan semua unit dalam struktur flat */
    public function index(): JsonResponse
    {
        $units = WorkUnit::with('parent')->orderBy('nama')->get();
        return $this->success($units);
    }

    /** Mengembalikan unit dalam struktur tree (root beserta children-nya) */
    public function tree(): JsonResponse
    {
        $tree = WorkUnit::with('children.children')
                        ->whereNull('parent_id')
                        ->orderBy('nama')
                        ->get();
        return $this->success($tree);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama'      => 'required|string|max:255',
            'parent_id' => 'nullable|exists:work_units,id',
        ]);

        $unit = WorkUnit::create($data);
        return $this->created($unit->load('parent'));
    }

    public function show(WorkUnit $workUnit): JsonResponse
    {
        return $this->success($workUnit->load('parent', 'children'));
    }

    public function update(Request $request, WorkUnit $workUnit): JsonResponse
    {
        $data = $request->validate([
            'nama'      => 'sometimes|string|max:255',
            'parent_id' => 'nullable|exists:work_units,id',
        ]);

        $workUnit->update($data);
        return $this->success($workUnit, 'Unit kerja berhasil diperbarui');
    }

    public function destroy(WorkUnit $workUnit): JsonResponse
    {
        $workUnit->delete();
        return $this->success(null, 'Unit kerja berhasil dihapus');
    }
}
