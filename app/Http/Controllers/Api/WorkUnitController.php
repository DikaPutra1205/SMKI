<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkUnitRequest;
use App\Http\Requests\UpdateWorkUnitRequest;
use App\Models\WorkUnit;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class WorkUnitController extends Controller
{
    use ApiResponse;

    /** Mengembalikan semua unit dalam struktur flat */
    public function index(): JsonResponse
    {
        Gate::authorize('work-unit.view');

        $units = WorkUnit::with('parent')->orderBy('nama')->get();

        return $this->success($units);
    }

    /** Mengembalikan unit dalam struktur tree (root beserta children-nya) */
    public function tree(): JsonResponse
    {
        Gate::authorize('work-unit.view');

        $tree = WorkUnit::with('children.children')
            ->whereNull('parent_id')
            ->orderBy('nama')
            ->get();

        return $this->success($tree);
    }

    public function store(StoreWorkUnitRequest $request): JsonResponse
    {
        Gate::authorize('work-unit.create');

        $unit = WorkUnit::create($request->validated());

        return $this->created($unit->load('parent'));
    }

    public function show(WorkUnit $workUnit): JsonResponse
    {
        Gate::authorize('work-unit.view');

        return $this->success($workUnit->load('parent', 'children'));
    }

    public function update(UpdateWorkUnitRequest $request, WorkUnit $workUnit): JsonResponse
    {
        Gate::authorize('work-unit.update');

        $workUnit->update($request->validated());

        return $this->success($workUnit, 'Unit kerja berhasil diperbarui');
    }

    public function destroy(WorkUnit $workUnit): JsonResponse
    {
        Gate::authorize('work-unit.delete');

        if ($workUnit->children()->exists() || $workUnit->users()->exists()) {
            abort(422, 'Unit yang masih memiliki sub-unit atau user tidak dapat dihapus.');
        }

        $workUnit->delete();

        return $this->success(null, 'Unit kerja berhasil dihapus');
    }
}
