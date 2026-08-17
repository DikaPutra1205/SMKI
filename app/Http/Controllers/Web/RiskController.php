<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRiskRequest;
use App\Http\Requests\UpdateRiskRequest;
use App\Models\Risk;
use Illuminate\Http\RedirectResponse;

class RiskController extends Controller
{
    public function store(StoreRiskRequest $request): RedirectResponse
    {
        Risk::create($request->validated());

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Risiko kepatuhan berhasil ditambahkan.',
        ]);
    }

    public function update(UpdateRiskRequest $request, Risk $risk): RedirectResponse
    {
        $risk->update($request->validated());

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Risiko kepatuhan berhasil diperbarui.',
        ]);
    }

    public function destroy(Risk $risk): RedirectResponse
    {
        $risk->delete();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Risiko kepatuhan berhasil dihapus.',
        ]);
    }
}
