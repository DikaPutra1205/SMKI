<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFindingRequest;
use App\Http\Requests\UpdateFindingRequest;
use App\Http\Requests\UpdateFindingStatusRequest;
use App\Models\Finding;
use Illuminate\Http\RedirectResponse;

class FindingController extends Controller
{
    public function store(StoreFindingRequest $request): RedirectResponse
    {
        $finding = Finding::create($request->validated());

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Temuan kepatuhan berhasil ditambahkan.',
        ]);
    }

    public function update(UpdateFindingRequest $request, Finding $finding): RedirectResponse
    {
        $finding->update($request->validated());

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Temuan kepatuhan berhasil diperbarui.',
        ]);
    }

    public function updateStatus(UpdateFindingStatusRequest $request, Finding $finding): RedirectResponse
    {
        $data = $request->validated();
        $update = [
            'status' => $data['status'],
            'admin_id' => $data['admin_id'],
        ];

        if ($data['status'] === 'closed') {
            $update['tanggal_verifikasi'] = now();
        }

        $finding->update($update);

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => "Status temuan berhasil diubah menjadi '{$data['status']}'.",
        ]);
    }

    public function destroy(Finding $finding): RedirectResponse
    {
        $finding->delete();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Temuan kepatuhan berhasil dihapus.',
        ]);
    }
}
