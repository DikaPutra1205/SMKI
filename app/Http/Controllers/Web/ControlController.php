<?php

namespace App\Http\Controllers\Web;

use App\Exports\SmkiMasterDataExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportMasterDataRequest;
use App\Http\Requests\StoreControlRequest;
use App\Http\Requests\UpdateControlRequest;
use App\Imports\SmkiMasterDataImport;
use App\Imports\SmkiSingleSheetImport;
use App\Models\Control;
use App\Models\Framework;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Exceptions\SheetNotFoundException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ControlController extends Controller
{
    /**
     * Master Data page — framework list + Excel import/export with
     * preview-before-confirm. Uploading here parses controls; attaching a
     * file on the Framework form only stores a document link.
     */
    public function masterDataPage(): Response
    {
        Gate::authorize('control.view');

        $frameworks = Framework::withCount('controls')
            ->orderBy('id')
            ->get()
            ->map(fn (Framework $fw) => [
                'id' => $fw->id,
                'nama' => $fw->nama,
                'versi' => $fw->versi,
                'controls_count' => $fw->controls_count,
            ])
            ->toArray();

        return Inertia::render('admin-kepatuhan/master-data', [
            'frameworks' => $frameworks,
        ]);
    }

    /**
     * Store a newly created control.
     * Inertia-style: redirect back with flash on success, validation errors
     * bubble automatically via Inertia's shared error bag.
     */
    public function store(StoreControlRequest $request): RedirectResponse
    {
        Gate::authorize('control.create');

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
        Gate::authorize('control.update');

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
        Gate::authorize('control.delete');

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
        Gate::authorize('control.export');

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
        Gate::authorize('control.import');

        set_time_limit(180);

        $import = new SmkiMasterDataImport(dryRun: true);

        try {
            Excel::import($import, $request->file('file'));
        } catch (SheetNotFoundException $e) {
            try {
                Excel::import(new SmkiSingleSheetImport($import), $request->file('file'));
                if (count($import->controlsCreatedDetail) === 0 && count($import->controlsUpdatedDetail) === 0) {
                    return response()->json([
                        'message' => 'Format file tidak sesuai: Sheet Frameworks dan Controls wajib ada.',
                    ], 422);
                }
            } catch (\Throwable $fallbackErr) {
                return response()->json([
                    'message' => 'Format file tidak sesuai: Sheet Frameworks dan Controls wajib ada.',
                ], 422);
            }
        }

        return response()->json($import->summary());
    }

    /**
     * Execute the actual import — upsert and soft-delete records not present
     * in the Excel file. Returns Inertia-style redirect with detailed flash.
     */
    public function importMasterData(ImportMasterDataRequest $request): RedirectResponse
    {
        Gate::authorize('control.import');

        set_time_limit(180);

        $import = new SmkiMasterDataImport(dryRun: false);

        try {
            DB::transaction(function () use ($import, $request) {
                try {
                    Excel::import($import, $request->file('file'));
                } catch (SheetNotFoundException $e) {
                    Excel::import(new SmkiSingleSheetImport($import), $request->file('file'));
                    if (count($import->controlsCreatedDetail) === 0 && count($import->controlsUpdatedDetail) === 0) {
                        throw $e;
                    }
                }
            });
        } catch (SheetNotFoundException $e) {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Format file tidak sesuai: Sheet Frameworks dan Controls wajib ada.',
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Gagal mengimpor master data: '.$e->getMessage(),
            ]);
        }

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => $import->flashMessage(),
            'summary' => $import->summary(),
        ]);
    }
}
