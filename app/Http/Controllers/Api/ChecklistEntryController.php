<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\ComplianceEvidence;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use App\Notifications\ChecklistEntryRejectedNotification;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ChecklistEntryController extends Controller
{
    use ApiResponse;

    /**
     * Mengambil daftar checklist dengan urutan konsisten berdasarkan Klausul Standar ISO.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            Gate::authorize('viewAny', [ChecklistEntry::class, $request->filled('unit_id') ? (int) $request->unit_id : null]);
        }

        $targetUnitId = $request->filled('unit_id')
            ? (int) $request->unit_id
            : ($user?->isPic() ? (int) $user->unit_id : null);

        // 1. Auto-provisioning otomatis jika belum ada data di database atau per-unit
        $this->ensureChecklistProvisioned($targetUnitId);

        // Query join ke controls agar pengurutan selalu konsisten berdasarkan Klausul Standar
        $query = ChecklistEntry::select('checklist_entries.*')
            ->join('controls', 'controls.id', '=', 'checklist_entries.control_id')
            ->with([
                'control:id,framework_id,kode_klausul,judul,kategori',
                'control.framework:id,nama,versi',
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

        // ── Filter Session ──
        if ($request->filled('session_id')) {
            $query->where('checklist_entries.session_id', $request->session_id);
        }

        // ── Filter Unit Kerja ──
        if ($request->filled('unit_id')) {
            $query->where('checklist_entries.unit_id', $request->unit_id);
        } elseif ($user?->isPic()) {
            $query->where('checklist_entries.unit_id', $user->unit_id);
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
            $like = config('database.default') === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('controls.kode_klausul', $like, "%{$search}%")
                    ->orWhere('controls.judul', $like, "%{$search}%");
            });
        }

        // Urutkan berdasarkan kategori lalu kode
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
                $pic = User::with('role:id,name')
                    ->where('unit_id', $unit->id)
                    ->whereHas('role', fn ($q) => $q->where('name', User::ROLE_PIC))
                    ->first()
                    ?? User::with('role:id,name')->whereHas('role', fn ($q) => $q->where('name', User::ROLE_PIC))->first();

                if ($pic) {
                    $insertData = [];
                    $now = now();
                    foreach ($controls as $ctrl) {
                        $insertData[] = [
                            'control_id' => $ctrl->id,
                            'unit_id' => $unit->id,
                            'pic_id' => $pic->id,
                            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
                            'catatan' => '',
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
            'session_id' => 'nullable|exists:checklist_sessions,id',
            'control_id' => 'required|exists:controls,id',
            'unit_id' => 'required|exists:work_units,id',
            'pic_id' => 'required|exists:users,id',
            'level_maturity' => 'nullable|integer|min:0|max:5',
            'catatan' => 'nullable|string',
            'tanggal_input' => 'nullable|date',
        ]);

        Gate::authorize('create', [ChecklistEntry::class, (int) $data['unit_id']]);

        $data['catatan'] = $data['catatan'] ?? '';
        $data['status'] = ChecklistEntry::resolvePicWorkflow(trim((string) ($data['catatan'] ?? '')) !== '', false);
        $data['tanggal_input'] = $data['tanggal_input'] ?? now();

        $entry = ChecklistEntry::create($data);

        return $this->created($entry->load(['control', 'unit', 'pic:id,name', 'session']));
    }

    public function show(ChecklistEntry $checklistEntry): JsonResponse
    {
        Gate::authorize('view', $checklistEntry);

        $checklistEntry->load([
            'session',
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
        Gate::authorize('update', $checklistEntry);

        $data = $request->validate([
            'level_maturity' => 'nullable|integer|min:0|max:5',
            'catatan' => 'required_if:tidak_berlaku,true|nullable|string',
            'tidak_berlaku' => 'sometimes|boolean',
            'status' => 'prohibited',
            'bukti_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'uploaded_by' => 'nullable|exists:users,id',
        ]);

        $evidenceData = null;
        if ($request->hasFile('bukti_file')) {
            Gate::authorize('uploadEvidence', [$checklistEntry, $request->filled('uploaded_by') ? (int) $request->uploaded_by : null]);

            $uploaderId = $request->user()?->isPic()
                ? $request->user()->id
                : ($request->uploaded_by ?? $checklistEntry->pic_id);

            // Upload the file first (outside the transaction — S3 is not transactional).
            $folder = "bukti/{$checklistEntry->id}";
            $path = Storage::disk('supabase')->put($folder, $request->file('bukti_file'));

            if ($path) {
                // Determine the next version inside a locked transaction to prevent
                // race-condition duplicate version numbers.
                // Aggregate (max) cannot be combined with FOR UPDATE in PostgreSQL;
                // lock rows via pluck then compute max in PHP.
                $nextVersion = DB::transaction(function () use ($checklistEntry) {
                    $lockedVersions = $checklistEntry->evidences()
                        ->withTrashed()
                        ->lockForUpdate()
                        ->pluck('version_number');

                    return ($lockedVersions->max() ?? 0) + 1;
                });

                $evidenceData = [
                    'checklist_entry_id' => $checklistEntry->id,
                    'uploaded_by' => $uploaderId,
                    'file_url' => $path,
                    'version_number' => $nextVersion,
                    'is_active' => true,
                    'uploaded_at' => now(),
                ];
            }
        }

        $newCatatan = array_key_exists('catatan', $data) ? $data['catatan'] : $checklistEntry->catatan;
        $hasBukti = $evidenceData !== null || $checklistEntry->evidences()->exists();
        $naSelected = (bool) ($data['tidak_berlaku'] ?? $checklistEntry->status === ChecklistEntry::WORKFLOW_TIDAK_BERLAKU);

        $updatePayload = [
            'status' => $checklistEntry->applyPicTouch($newCatatan, $hasBukti, $naSelected),
            'catatan' => $newCatatan,
            'tanggal_input' => now(),
            'tanggal_verifikasi' => null,
        ];

        if (array_key_exists('level_maturity', $data)) {
            $updatePayload['level_maturity'] = $data['level_maturity'];
        }

        $checklistEntry->update($updatePayload);

        if ($evidenceData) {
            ComplianceEvidence::create($evidenceData);
        }

        $fresh = $checklistEntry->fresh([
            'session',
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
        Gate::authorize('verify', $checklistEntry);

        $data = $request->validate([
            'admin_id' => 'required|exists:users,id',
            'decision' => 'required|in:approve,reject',
            'status' => 'prohibited',
            'catatan_admin' => 'required_if:decision,reject|nullable|string|max:2000',
            'level_maturity' => 'nullable|integer|min:0|max:5',
        ]);

        if (! in_array($checklistEntry->status, [
            ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            ChecklistEntry::WORKFLOW_TIDAK_BERLAKU,
        ], true)) {
            return response()->json([
                'message' => 'Verifikasi hanya dapat dilakukan pada entri dengan status dalam tinjauan atau tidak berlaku.',
            ], 422);
        }

        $adminId = $request->user()?->id ?? $data['admin_id'];
        $isNa = $checklistEntry->status === ChecklistEntry::WORKFLOW_TIDAK_BERLAKU;

        if ($data['decision'] === 'approve') {
            $checklistEntry->update(array_merge([
                'admin_id' => $adminId,
                'status' => $isNa ? ChecklistEntry::WORKFLOW_TIDAK_BERLAKU : ChecklistEntry::WORKFLOW_SELESAI,
                'tanggal_verifikasi' => now(),
                'catatan_admin' => null,
            ], array_key_exists('level_maturity', $data) ? ['level_maturity' => $data['level_maturity']] : []));
        } else {
            $checklistEntry->update(array_merge([
                'admin_id' => $adminId,
                'status' => ChecklistEntry::WORKFLOW_DALAM_PROSES,
                'tanggal_verifikasi' => null,
                'catatan_admin' => $data['catatan_admin'] ?? null,
            ], array_key_exists('level_maturity', $data) ? ['level_maturity' => $data['level_maturity']] : []));

            $user = $request->user() ?? User::find($adminId);
            $targetPic = $checklistEntry->pic ?? User::where('unit_id', $checklistEntry->unit_id)
                ->whereHas('role', fn ($q) => $q->where('name', User::ROLE_PIC))
                ->first();
            if ($targetPic && $user && $targetPic->id !== $user->id) {
                $targetPic->notify(new ChecklistEntryRejectedNotification(
                    $checklistEntry->fresh(['control', 'session']),
                    $user,
                    $data['catatan_admin'] ?? null
                ));
            }
        }

        return $this->success($checklistEntry->fresh(['session', 'control', 'unit', 'pic', 'admin']), 'Checklist berhasil diverifikasi oleh Admin');
    }

    public function destroy(ChecklistEntry $checklistEntry): JsonResponse
    {
        Gate::authorize('delete', $checklistEntry);

        $checklistEntry->delete();

        return $this->success(null, 'Checklist berhasil dihapus (soft delete)');
    }

    public function restore(int $id): JsonResponse
    {
        $entry = ChecklistEntry::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $entry);

        $entry->restore();

        return $this->success($entry, 'Checklist berhasil dipulihkan');
    }

    /**
     * Trigger monthly checklist auto-provisioning via HTTP.
     *
     * Replicates the logic in GenerateMonthlyChecklistCommand so the feature
     * is accessible without SSH/scheduler access. Accepts optional ?unit_id
     * to scope provisioning to a single work unit.
     *
     * Previously the route pointed to a non-existent method, causing a 500.
     */
    public function generateMonthly(Request $request): JsonResponse
    {
        $unitId = $request->filled('unit_id') ? (int) $request->unit_id : null;

        $controls = Control::all();
        if ($controls->isEmpty()) {
            return $this->success(['created' => 0], 'Belum ada master data kontrol.');
        }

        $unitsQuery = WorkUnit::query();
        if ($unitId) {
            $unitsQuery->where('id', $unitId);
        }
        $units = $unitsQuery->get();

        if ($units->isEmpty()) {
            return $this->success(['created' => 0], 'Tidak ada unit kerja yang ditemukan.');
        }

        $now = now();
        $period = $request->input('periode', $now->format('Y-m'));
        $periodLabel = Carbon::parse($period)->translatedFormat('F Y');

        $frameworks = Framework::whereHas('controls')->get();
        if ($frameworks->isEmpty()) {
            $frameworks = collect([null]);
        }

        $rowsToInsert = [];
        $sessionsCreated = 0;

        foreach ($units as $unit) {
            $pic = User::with('role:id,name')
                ->where('unit_id', $unit->id)
                ->whereHas('role', fn ($q) => $q->where('name', User::ROLE_PIC))
                ->first()
                ?? User::with('role:id,name')->whereHas('role', fn ($q) => $q->where('name', User::ROLE_PIC))->first();

            foreach ($frameworks as $framework) {
                $frameworkId = $framework?->id;
                $frameworkControls = Control::when($frameworkId, fn ($q) => $q->where('framework_id', $frameworkId))->get();

                if ($frameworkControls->isEmpty()) {
                    continue;
                }

                $session = ChecklistSession::firstOrCreate(
                    [
                        'unit_id' => $unit->id,
                        'framework_id' => $frameworkId,
                        'periode' => $period,
                    ],
                    [
                        'konteks_penilaian' => "Penilaian Bulanan SMKI - {$periodLabel}".($framework ? " ({$framework->nama})" : ''),
                        'created_by' => $pic?->id,
                        'updated_by' => $pic?->id,
                        'catatan' => 'Otomatis di-generate oleh sistem untuk periode '.$periodLabel,
                    ]
                );

                if ($session->wasRecentlyCreated) {
                    $sessionsCreated++;
                }

                $existingControlIds = ChecklistEntry::where('session_id', $session->id)
                    ->pluck('control_id')
                    ->flip()
                    ->toArray();

                foreach ($frameworkControls as $ctrl) {
                    if (! isset($existingControlIds[$ctrl->id])) {
                        $rowsToInsert[] = [
                            'session_id' => $session->id,
                            'control_id' => $ctrl->id,
                            'unit_id' => $unit->id,
                            'pic_id' => $pic?->id,
                            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
                            'catatan' => '',
                            'tanggal_input' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }
        }

        $totalCreated = count($rowsToInsert);
        foreach (array_chunk($rowsToInsert, 100) as $chunk) {
            ChecklistEntry::insert($chunk);
        }

        $message = ($totalCreated === 0 && $sessionsCreated === 0)
            ? 'Semua checklist sudah lengkap, tidak ada entri baru yang dibuat.'
            : "{$sessionsCreated} sesi dan {$totalCreated} checklist baru berhasil dibuat.";

        return $this->success(['created' => $totalCreated, 'sessions_created' => $sessionsCreated], $message);
    }
}
