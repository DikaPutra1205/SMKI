<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistEntry;
use App\Models\ComplianceEvidence;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        Gate::authorize('viewAny', [ComplianceEvidence::class, $checklistEntry]);

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
        Gate::authorize('create', [ComplianceEvidence::class, $checklistEntry, $request->filled('uploaded_by') ? (int) $request->uploaded_by : null]);

        $request->validate([
            'bukti_file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240', // maks 10MB
            'uploaded_by' => 'required|exists:users,id',
        ]);

        $uploaderId = $request->user()?->isPic() ? $request->user()->id : $request->uploaded_by;

        // Upload ke Supabase Storage (S3) — lakukan di luar transaksi karena
        // S3/Supabase tidak transactional; batalkan secara manual jika DB gagal.
        $folder = "bukti/{$checklistEntry->id}";
        $path = Storage::disk('supabase')->put($folder, $request->file('bukti_file'));

        if (! $path) {
            return $this->error('Gagal mengunggah file ke Supabase Storage', 500);
        }

        // Hitung versi berikutnya dan simpan record dalam satu transaksi terkunci
        // untuk mencegah race condition: dua request bersamaan bisa membaca max()
        // yang sama dan menghasilkan versi duplikat.
        $evidence = DB::transaction(function () use ($checklistEntry, $uploaderId, $path) {
            // lockForUpdate memblokir baris lain yang membaca version_number
            // untuk entry yang sama hingga transaksi ini selesai.
            // Catatan: aggregate (max) tidak bisa dikombinasikan dengan FOR UPDATE
            // di PostgreSQL; kita lock semua baris terlebih dahulu lalu hitung
            // max di PHP untuk portabilitas SQLite/PostgreSQL.
            $lockedVersions = $checklistEntry->evidences()
                ->withTrashed()
                ->lockForUpdate()
                ->pluck('version_number');

            $maxVersion = $lockedVersions->max() ?? 0;

            $nextVersion = $maxVersion + 1;

            $evidence = ComplianceEvidence::create([
                'checklist_entry_id' => $checklistEntry->id,
                'uploaded_by' => $uploaderId,
                'file_url' => $path,
                'version_number' => $nextVersion,
                'is_active' => true,
                'uploaded_at' => now(),
            ]);

            // Reset status verifikasi checklist agar diverifikasi ulang oleh Admin
            $checklistEntry->update([
                'tanggal_input' => now(),
                'tanggal_verifikasi' => null,
                'catatan_admin' => null,
                'admin_id' => null,
            ]);

            return $evidence;
        });

        return $this->created(
            $evidence->load('uploader:id,name'),
            "Bukti kepatuhan versi {$evidence->version_number} berhasil diunggah. Status checklist diperbarui (menunggu verifikasi)."
        );
    }

    /** Soft delete satu bukti */
    public function destroy(ComplianceEvidence $complianceEvidence): JsonResponse
    {
        Gate::authorize('delete', $complianceEvidence);

        $complianceEvidence->delete();

        return $this->success(null, 'Bukti berhasil dihapus (soft delete)');
    }

    /** Restore bukti yang pernah di-soft delete */
    public function restore(int $id): JsonResponse
    {
        $evidence = ComplianceEvidence::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $evidence);

        $evidence->restore();

        return $this->success($evidence->load('uploader:id,name'), 'Bukti berhasil dipulihkan');
    }

    /**
     * Unduh file bukti kepatuhan on-demand (Lazy Proxy Download).
     */
    public function download(Request $request, int $id): RedirectResponse
    {
        $evidence = ComplianceEvidence::withTrashed()->findOrFail($id);
        Gate::authorize('view', $evidence);

        $rawKey = $evidence->getRawOriginal('file_url');

        if (! $rawKey) {
            abort(404, 'File bukti tidak ditemukan');
        }

        if (filter_var($rawKey, FILTER_VALIDATE_URL)) {
            return redirect()->away($rawKey);
        }

        $presignedUrl = $evidence->getPresignedUrl(30);

        if (! $presignedUrl) {
            abort(404, 'Gagal menghasilkan tautan unduhan');
        }

        return redirect()->away($presignedUrl);
    }
}
