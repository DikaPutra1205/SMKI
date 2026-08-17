<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplianceEvidenceRequest;
use App\Models\ChecklistEntry;
use App\Models\ComplianceEvidence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class ComplianceEvidenceController extends Controller
{
    /** Upload bukti baru ke checklist entry via Inertia Form Request */
    public function store(StoreComplianceEvidenceRequest $request, ChecklistEntry $checklistEntry): RedirectResponse
    {
        $maxVersion = $checklistEntry->evidences()->withTrashed()->max('version_number') ?? 0;
        $nextVersion = $maxVersion + 1;

        // Upload ke Supabase Storage
        $folder = "bukti/{$checklistEntry->id}";
        $path = Storage::disk('supabase')->put($folder, $request->file('bukti_file'));

        if (! $path) {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Gagal mengunggah file bukti ke storage.',
            ]);
        }

        ComplianceEvidence::create([
            'checklist_entry_id' => $checklistEntry->id,
            'uploaded_by' => $request->uploaded_by,
            'file_url' => $path,
            'version_number' => $nextVersion,
            'is_active' => true,
            'uploaded_at' => now(),
        ]);

        // Reset verifikasi checklist
        $checklistEntry->update([
            'tanggal_input' => now(),
            'tanggal_verifikasi' => null,
        ]);

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => "Bukti kepatuhan versi {$nextVersion} berhasil diunggah.",
        ]);
    }

    /** Soft delete satu bukti */
    public function destroy(ComplianceEvidence $complianceEvidence): RedirectResponse
    {
        $version = $complianceEvidence->version_number;
        $complianceEvidence->delete();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => "Bukti kepatuhan versi {$version} berhasil dihapus.",
        ]);
    }

    /** Restore bukti yang pernah di-soft-delete */
    public function restore(int $id): RedirectResponse
    {
        $evidence = ComplianceEvidence::withTrashed()->findOrFail($id);
        $evidence->restore();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => "Bukti kepatuhan versi {$evidence->version_number} berhasil dipulihkan.",
        ]);
    }
}
