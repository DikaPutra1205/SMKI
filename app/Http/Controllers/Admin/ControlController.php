<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SmkiMasterDataExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportMasterDataRequest;
use App\Http\Requests\StoreControlRequest;
use App\Http\Requests\UpdateControlRequest;
use App\Imports\SmkiMasterDataImport;
use App\Models\Control;
use Illuminate\Http\RedirectResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ControlController extends Controller
{
    /**
     * Store a newly created control.
     * Inertia-style: redirect back with flash on success, validation errors
     * bubble automatically via Inertia's shared error bag.
     */
    public function store(StoreControlRequest $request): RedirectResponse
    {
        Control::create($request->validated());

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Kontrol berhasil ditambahkan.',
        ]);
    }

    /**
     * Update an existing control.
     */
    public function update(UpdateControlRequest $request, Control $control): RedirectResponse
    {
        $control->update($request->validated());

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Kontrol berhasil diperbarui.',
        ]);
    }

    /**
     * Soft-delete a control.
     */
    public function destroy(Control $control): RedirectResponse
    {
        $control->delete();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Kontrol berhasil dihapus.',
        ]);
    }

    /**
     * Export all frameworks + controls as a unified 2-sheet Excel file.
     * Returns a streamed download — no redirect needed.
     */
    public function exportMasterData(): BinaryFileResponse
    {
        $filename = 'smki-master-data-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new SmkiMasterDataExport, $filename);
    }

    /**
     * Import frameworks and controls from the unified 2-sheet Excel file.
     * Order: Frameworks sheet first → Controls sheet second.
     */
    public function importMasterData(ImportMasterDataRequest $request): RedirectResponse
    {
        $import = new SmkiMasterDataImport;

        Excel::import($import, $request->file('file'));

        $summary = sprintf(
            'Import selesai: %d framework baru, %d framework diperbarui, %d kontrol baru, %d kontrol diperbarui.',
            $import->frameworksCreated,
            $import->frameworksUpdated,
            $import->controlsCreated,
            $import->controlsUpdated,
        );

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => $summary,
        ]);
    }
}
