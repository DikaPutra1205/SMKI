<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistEntry;
use App\Models\ComplianceEvidence;
use App\Models\Control;
use App\Models\User;
use App\Models\WorkUnit;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChecklistEntryController extends Controller
{
    use ApiResponse;

    /**
     * Mengambil daftar checklist dengan urutan konsisten berdasarkan Klausul Standar ISO.
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Auto-provisioning otomatis jika belum ada data di database atau per-unit
        $this->ensureChecklistProvisioned($request->unit_id);

        // Query join ke controls agar pengurutan selalu konsisten berdasarkan Klausul Standar
        $query = ChecklistEntry::select('checklist_entries.*')
            ->join('controls', 'controls.id', '=', 'checklist_entries.control_id')
            ->with([
                'control:id,framework_id,kode_klausul,judul,kategori',
                'unit:id,nama',
                'pic:id,name',
                'admin:id,name',
                'activeEvidence:id,checklist_entry_id,version_number,file_url,is_active',
            ]);

        // ── Filter Soft Deletes ──
        if ($request->filled('trashed')) {
            if ($request->trashed === 'only' || $request->boolean('only_trashed')) {
                $query->onlyTrashed();
            } elseif ($request->trashed === 'with' || $request->boolean('with_trashed')) {
                $query->withTrashed();
            }
        }

        // ── Filter Unit Kerja ──
        if ($request->filled('unit_id')) {
            $query->where('checklist_entries.unit_id', $request->unit_id);
        }

        // ── Filter Status Kepatuhan ──
        if ($request->filled('status')) {
            $query->where('checklist_entries.status', $request->status);
        }

        // ── Filter Kontrol Spesifik ──
        if ($request->filled('control_id')) {
            $query->where('checklist_entries.control_id', $request->control_id);
        }

        // ── Filter Framework ──
        if ($request->filled('framework_id')) {
            $query->where('controls.framework_id', $request->framework_id);
        }

        // ── Filter Kategori ──
        if ($request->filled('kategori')) {
            $query->where('controls.kategori', $request->kategori);
        }

        // ── Filter Status Verifikasi ──
        if ($request->has('is_verified')) {
            if ($request->boolean('is_verified')) {
                $query->whereNotNull('checklist_entries.tanggal_verifikasi');
            } else {
                $query->whereNull('checklist_entries.tanggal_verifikasi');
            }
        }

        // ── Filter Periode Bulan & Tahun ──
        if ($request->filled('bulan')) {
            $query->whereMonth('checklist_entries.tanggal_input', $request->bulan);
        }
        if ($request->filled('tahun')) {
            $query->whereYear('checklist_entries.tanggal_input', $request->tahun);
        }

        // ── Pencarian Teks ──
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('controls.kode_klausul', 'ilike', "%{$search}%")
                    ->orWhere('controls.judul', 'ilike', "%{$search}%");
            });
        }

        // ── Pengurutan Standar: Klausul Manajemen 4-10 lebih dulu, kemudian Annex A (A.5 -> A.8) ──
        $query->orderBy('controls.kategori', 'desc')
            ->orderBy('controls.kode_klausul', 'asc')
            ->orderBy('checklist_entries.unit_id', 'asc')
            ->orderBy('checklist_entries.id', 'asc');

        if ($request->boolean('all')) {
            return $this->success($query->get());
        }

        $perPage = $request->integer('per_page', 20);
        $entries = $query->paginate($perPage);

        return $this->success($entries);
    }

    protected function ensureChecklistProvisioned(?int $unitId = null): void
    {
        $units = $unitId ? WorkUnit::where('id', $unitId)->get() : WorkUnit::all();
        if ($units->isEmpty()) {
            return;
        }

        $controls = Control::all();
        if ($controls->isEmpty()) {
            return;
        }

        foreach ($units as $unit) {
            $hasEntries = ChecklistEntry::where('unit_id', $unit->id)->exists();
            if (! $hasEntries) {
                $pic = User::where('unit_id', $unit->id)->where('role', User::ROLE_PIC)->first()
                    ?? User::where('role', User::ROLE_PIC)->first();

                if ($pic) {
                    $insertData = [];
                    $now = now();
                    foreach ($controls as $ctrl) {
                        $insertData[] = [
                            'control_id' => $ctrl->id,
                            'unit_id' => $unit->id,
                            'pic_id' => $pic->id,
                            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
                            'catatan' => 'Belum diisi oleh PIC.',
                            'tanggal_input' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    foreach (array_chunk($insertData, 100) as $chunk) {
                        ChecklistEntry::insert($chunk);
                    }
                }
            }
        }
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'control_id' => 'required|exists:controls,id',
            'unit_id' => 'required|exists:work_units,id',
            'pic_id' => 'required|exists:users,id',
            'status' => 'required|in:compliant,partial,non_compliant,na',
            'catatan' => 'nullable|string',
            'tanggal_input' => 'nullable|date',
        ]);

        $data['tanggal_input'] = $data['tanggal_input'] ?? now();

        $entry = ChecklistEntry::create($data);

        return $this->created($entry->load(['control', 'unit', 'pic:id,name']));
    }

    public function show(ChecklistEntry $checklistEntry): JsonResponse
    {
        $checklistEntry->load([
            'control.framework',
            'unit:id,nama',
            'pic:id,name,email',
            'admin:id,name,email',
            'evidences' => function ($q) {
                $q->withTrashed()->orderByDesc('version_number')->with('uploader:id,name');
            },
        ]);

        return $this->success($checklistEntry);
    }

    public function update(Request $request, ChecklistEntry $checklistEntry): JsonResponse
    {
        $data = $request->validate([
            'status' => 'sometimes|in:compliant,partial,non_compliant,na',
            'catatan' => 'nullable|string',
            'bukti_file' => 'nullable|file|max:10240',
            'uploaded_by' => 'nullable|exists:users,id',
        ]);

        $checklistEntry->update([
            'status' => $data['status'] ?? $checklistEntry->status,
            'catatan' => $data['catatan'] ?? $checklistEntry->catatan,
            'tanggal_input' => now(),
            'tanggal_verifikasi' => null,
        ]);

        if ($request->hasFile('bukti_file')) {
            $uploaderId = $request->uploaded_by ?? $checklistEntry->pic_id;
            $nextVersion = ($checklistEntry->evidences()->withTrashed()->max('version_number') ?? 0) + 1;

            $folder = "bukti/{$checklistEntry->id}";
            $path = Storage::disk('supabase')->put($folder, $request->file('bukti_file'));

            if ($path) {
                ComplianceEvidence::create([
                    'checklist_entry_id' => $checklistEntry->id,
                    'uploaded_by' => $uploaderId,
                    'file_url' => $path,
                    'version_number' => $nextVersion,
                    'is_active' => true,
                    'uploaded_at' => now(),
                ]);
            }
        }

        $fresh = $checklistEntry->fresh([
            'control.framework',
            'unit:id,nama',
            'pic:id,name',
            'admin:id,name',
            'evidences' => fn ($q) => $q->withTrashed()->orderByDesc('version_number')->with('uploader:id,name'),
        ]);

        return $this->success($fresh, 'Penilaian dan dokumen bukti berhasil disimpan.');
    }

    public function verify(Request $request, ChecklistEntry $checklistEntry): JsonResponse
    {
        $data = $request->validate([
            'admin_id' => 'required|exists:users,id',
            'catatan_admin' => 'nullable|string',
            'status' => 'required|in:compliant,partial,non_compliant,na',
        ]);

        $checklistEntry->update([
            'admin_id' => $data['admin_id'],
            'catatan_admin' => $data['catatan_admin'] ?? null,
            'status' => $data['status'],
            'tanggal_verifikasi' => now(),
        ]);

        return $this->success($checklistEntry->fresh(['control', 'unit', 'pic', 'admin']), 'Checklist berhasil diverifikasi oleh Admin');
    }

    public function destroy(ChecklistEntry $checklistEntry): JsonResponse
    {
        $checklistEntry->delete();

        return $this->success(null, 'Checklist berhasil dihapus (soft delete)');
    }

    public function restore(int $id): JsonResponse
    {
        $entry = ChecklistEntry::withTrashed()->findOrFail($id);
        $entry->restore();

        return $this->success($entry, 'Checklist berhasil dipulihkan');
    }
}
