<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SmkiMasterDataExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportMasterDataRequest;
use App\Http\Requests\StoreControlRequest;
use App\Http\Requests\UpdateControlRequest;
use App\Imports\SmkiMasterDataImport;
use App\Models\Control;
use Illuminate\Http\JsonResponse;
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
     * Dry-run import — analyse the Excel file and return a structured diff
     * WITHOUT persisting any changes. Used by the frontend confirmation modal.
     *
     * Returns JSON so the frontend can render a preview before committing.
     */
    public function previewMasterDataImport(ImportMasterDataRequest $request): JsonResponse
    {
        set_time_limit(180);

        $import = new SmkiMasterDataImport(dryRun: true);

        Excel::import($import, $request->file('file'));

        return response()->json($import->summary());
    }

    /**
     * Execute the actual import — upsert and soft-delete records not present
     * in the Excel file. Returns Inertia-style redirect with detailed flash.
     */
    public function importMasterData(ImportMasterDataRequest $request): RedirectResponse
    {
        set_time_limit(180);

        $import = new SmkiMasterDataImport(dryRun: false);

        Excel::import($import, $request->file('file'));

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => $import->flashMessage(),
            'summary' => $import->summary(),
        ]);
    }
}
