<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkUnitRequest;
use App\Http\Requests\UpdateWorkUnitRequest;
use App\Models\WorkUnit;
use Illuminate\Http\RedirectResponse;

class WorkUnitController extends Controller
{
    public function store(StoreWorkUnitRequest $request): RedirectResponse
    {
        $unit = WorkUnit::create($request->validated());

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => "Unit kerja '{$unit->nama}' berhasil ditambahkan.",
        ]);
    }

    public function update(UpdateWorkUnitRequest $request, WorkUnit $workUnit): RedirectResponse
    {
        $workUnit->update($request->validated());

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => "Unit kerja '{$workUnit->nama}' berhasil diperbarui.",
        ]);
    }

    public function destroy(WorkUnit $workUnit): RedirectResponse
    {
        $nama = $workUnit->nama;
        $workUnit->delete();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => "Unit kerja '{$nama}' berhasil dihapus.",
        ]);
    }
}
