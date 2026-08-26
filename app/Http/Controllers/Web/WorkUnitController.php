<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkUnitRequest;
use App\Http\Requests\UpdateWorkUnitRequest;
use App\Models\WorkUnit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WorkUnitController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('work-unit.view');

        $units = WorkUnit::with('parent:id,nama')
            ->orderBy('nama')
            ->get(['id', 'nama', 'parent_id']);

        return Inertia::render('superadmin/units', [
            'units' => $units,
        ]);
    }

    public function store(StoreWorkUnitRequest $request): RedirectResponse
    {
        $this->authorize('work-unit.create');

        WorkUnit::create($request->validated());

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Unit kerja berhasil ditambahkan.',
        ]);
    }

    public function update(UpdateWorkUnitRequest $request, WorkUnit $workUnit): RedirectResponse
    {
        $this->authorize('work-unit.update');

        $parentId = $request->input('parent_id');
        if ($parentId && $this->wouldCreateCycle($workUnit, (int) $parentId)) {
            abort(422, 'Unit tidak dapat dijadikan induk dari dirinya sendiri atau turunannya.');
        }

        $workUnit->update($request->validated());

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Unit kerja berhasil diperbarui.',
        ]);
    }

    public function destroy(WorkUnit $workUnit): RedirectResponse
    {
        $this->authorize('work-unit.delete');

        if ($workUnit->children()->exists() || $workUnit->users()->exists()) {
            abort(422, 'Unit yang masih memiliki sub-unit atau user tidak dapat dihapus.');
        }

        $workUnit->delete();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Unit kerja berhasil dihapus.',
        ]);
    }

    /** True jika $parentId adalah $unit itu sendiri atau salah satu turunannya. */
    private function wouldCreateCycle(WorkUnit $unit, int $parentId): bool
    {
        if ($parentId === $unit->id) {
            return true;
        }

        $candidate = WorkUnit::find($parentId);
        while ($candidate?->parent_id) {
            if ($candidate->parent_id === $unit->id) {
                return true;
            }
            $candidate = $candidate->parent;
        }

        return false;
    }
}
