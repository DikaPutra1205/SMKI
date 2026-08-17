<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistEntry;
use App\Models\ComplianceEvidence;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ComplianceEvidenceController extends Controller
{
    use ApiResponse;

    /**
     * List seluruh versi bukti untuk satu checklist entry.
     * Secara default menyertakan seluruh riwayat versi termasuk yang di-soft-delete (withTrashed),
     * sehingga Frontend bebas membedakan tampilannya berdasarkan kolom deleted_at.
     */
    public function index(Request $request, ChecklistEntry $checklistEntry): JsonResponse
    {
        $query = $checklistEntry->evidences()->with('uploader:id,name');

        if ($request->trashed === 'only' || $request->boolean('only_trashed')) {
            $query->onlyTrashed();
        } else {
            // Default: sertakan semua riwayat versi termasuk yang pernah dihapus
            $query->withTrashed();
        }

        $evidences = $query->orderByDesc('version_number')->get();

        return $this->success($evidences);
    }

    /** Upload bukti baru — otomatis buat versi baru & tandai revisi */
    public function store(Request $request, ChecklistEntry $checklistEntry): JsonResponse
    {
        $request->validate([
            'bukti_file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240', // maks 10MB
            'uploaded_by' => 'required|exists:users,id',
        ]);

        // Hitung nomor versi berikutnya dari seluruh riwayat (termasuk yang di-soft-delete)
        $maxVersion = $checklistEntry->evidences()->withTrashed()->max('version_number') ?? 0;
        $nextVersion = $maxVersion + 1;

        // Upload ke Supabase Storage (S3)
        $folder = "bukti/{$checklistEntry->id}";
        $path = Storage::disk('supabase')->put($folder, $request->file('bukti_file'));

        if (! $path) {
            return $this->error('Gagal mengunggah file ke Supabase Storage', 500);
        }

        // Simpan record bukti baru
        $evidence = ComplianceEvidence::create([
            'checklist_entry_id' => $checklistEntry->id,
            'uploaded_by' => $request->uploaded_by,
            'file_url' => $path,
            'version_number' => $nextVersion,
            'is_active' => true,
            'uploaded_at' => now(),
        ]);

        // Reset status verifikasi checklist agar diverifikasi ulang oleh Admin
        $checklistEntry->update([
            'tanggal_input' => now(),
            'tanggal_verifikasi' => null,
        ]);

        return $this->created(
            $evidence->load('uploader:id,name'),
            "Bukti kepatuhan versi {$nextVersion} berhasil diunggah. Status checklist diperbarui (menunggu verifikasi)."
        );
    }

    /** Soft delete satu bukti */
    public function destroy(ComplianceEvidence $complianceEvidence): JsonResponse
    {
        $complianceEvidence->delete();

        return $this->success(null, 'Bukti berhasil dihapus (soft delete)');
    }

    /** Restore bukti yang pernah di-soft delete */
    public function restore(int $id): JsonResponse
    {
        $evidence = ComplianceEvidence::withTrashed()->findOrFail($id);
        $evidence->restore();

        return $this->success($evidence->load('uploader:id,name'), 'Bukti berhasil dipulihkan');
    }
}
