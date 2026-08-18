<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChecklistSessionRequest;
use App\Http\Requests\UpdateChecklistSessionRequest;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistSessionController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = ChecklistSession::with([
            'unit:id,nama',
            'framework:id,nama,versi',
            'creator:id,name',
            'auditor:id,name',
        ])->withCount([
            'entries as total_entries',
            'entries as compliant_entries' => fn ($q) => $q->where('status', ChecklistEntry::STATUS_COMPLIANT),
            'entries as verified_entries' => fn ($q) => $q->whereNotNull('tanggal_verifikasi'),
        ]);

        if ($request->filled('trashed')) {
            if ($request->trashed === 'only' || $request->boolean('only_trashed')) {
                $query->onlyTrashed();
            } elseif ($request->trashed === 'with' || $request->boolean('with_trashed')) {
                $query->withTrashed();
            }
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }

        if ($request->filled('framework_id')) {
            $query->where('framework_id', $request->framework_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('periode')) {
            $query->where('periode', $request->periode);
        }

        if ($request->filled('auditor_id')) {
            $query->where('auditor_id', $request->auditor_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $like = config('database.default') === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('nama_sesi', $like, "%{$search}%")
                    ->orWhere('periode', $like, "%{$search}%")
                    ->orWhere('konteks_penilaian', $like, "%{$search}%");
            });
        }

        $query->orderByDesc('id');

        if ($request->boolean('all')) {
            return $this->success($query->get());
        }

        $perPage = $request->integer('per_page', 15);
        $sessions = $query->paginate($perPage);

        return $this->success($sessions);
    }

    public function store(StoreChecklistSessionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $data['created_by'] ?? auth()->id();
        $data['status'] = $data['status'] ?? ChecklistSession::STATUS_IN_PROGRESS;

        $session = ChecklistSession::create($data);

        // Auto-provision checklist entries for the session
        $this->provisionSessionEntries($session);

        $fresh = $session->fresh([
            'unit:id,nama',
            'framework:id,nama,versi',
            'creator:id,name',
            'auditor:id,name',
        ]);

        return $this->created([
            'session' => $fresh,
            'summary' => $fresh->summary,
        ], 'Sesi checklist audit berhasil dibuat beserta daftar klausulnya.');
    }

    public function show(ChecklistSession $checklistSession): JsonResponse
    {
        $checklistSession->load([
            'unit:id,nama',
            'framework:id,nama,versi',
            'creator:id,name',
            'auditor:id,name',
            'entries.control:id,framework_id,kode_klausul,judul,kategori',
            'entries.pic:id,name',
            'entries.admin:id,name',
            'entries.activeEvidence:id,checklist_entry_id,version_number,file_url,is_active',
        ]);

        return $this->success([
            'session' => $checklistSession,
            'summary' => $checklistSession->summary,
        ]);
    }

    public function update(UpdateChecklistSessionRequest $request, ChecklistSession $checklistSession): JsonResponse
    {
        if ($checklistSession->isClosed() && ! $request->has('status')) {
            return $this->error('Sesi checklist sudah ditutup (closed) dan tidak dapat diubah.', 422);
        }

        $checklistSession->update($request->validated());

        return $this->success(
            $checklistSession->fresh(['unit:id,nama', 'framework:id,nama,versi', 'creator:id,name', 'auditor:id,name']),
            'Sesi checklist berhasil diperbarui.'
        );
    }

    public function submit(Request $request, ChecklistSession $checklistSession): JsonResponse
    {
        if ($checklistSession->isClosed()) {
            return $this->error('Sesi checklist sudah ditutup (closed).', 422);
        }

        $checklistSession->update([
            'status' => ChecklistSession::STATUS_SUBMITTED,
        ]);

        return $this->success(
            $checklistSession->fresh(['unit:id,nama', 'framework:id,nama,versi', 'creator:id,name', 'auditor:id,name']),
            'Sesi checklist berhasil disubmit untuk diverifikasi oleh Auditor / Admin.'
        );
    }

    public function verify(Request $request, ChecklistSession $checklistSession): JsonResponse
    {
        $data = $request->validate([
            'status' => 'nullable|in:verified,closed,in_progress',
            'catatan' => 'nullable|string',
            'auditor_id' => 'nullable|exists:users,id',
        ]);

        $updateData = [
            'status' => $data['status'] ?? ChecklistSession::STATUS_VERIFIED,
        ];

        if (isset($data['catatan'])) {
            $updateData['catatan'] = $data['catatan'];
        }

        if (isset($data['auditor_id'])) {
            $updateData['auditor_id'] = $data['auditor_id'];
        } elseif (! $checklistSession->auditor_id && auth()->id()) {
            $updateData['auditor_id'] = auth()->id();
        }

        $checklistSession->update($updateData);

        return $this->success(
            $checklistSession->fresh(['unit:id,nama', 'framework:id,nama,versi', 'creator:id,name', 'auditor:id,name']),
            "Status sesi checklist berhasil diubah menjadi {$updateData['status']}."
        );
    }

    public function destroy(ChecklistSession $checklistSession): JsonResponse
    {
        $checklistSession->entries()->delete();
        $checklistSession->delete();

        return $this->success(null, 'Sesi checklist berhasil dihapus (soft delete).');
    }

    public function restore(int $id): JsonResponse
    {
        $session = ChecklistSession::withTrashed()->findOrFail($id);
        $session->restore();
        $session->entries()->withTrashed()->restore();

        return $this->success($session->load(['unit', 'framework']), 'Sesi checklist berhasil dipulihkan.');
    }

    protected function provisionSessionEntries(ChecklistSession $session): void
    {
        $query = Control::query();
        if ($session->framework_id) {
            $query->where('framework_id', $session->framework_id);
        }

        $controls = $query->get();
        if ($controls->isEmpty()) {
            return;
        }

        $pic = User::where('unit_id', $session->unit_id)->where('role', User::ROLE_PIC)->first()
            ?? User::where('role', User::ROLE_PIC)->first()
            ?? User::first();

        if (! $pic) {
            return;
        }

        $insertData = [];
        $now = now();

        foreach ($controls as $ctrl) {
            $insertData[] = [
                'session_id' => $session->id,
                'control_id' => $ctrl->id,
                'unit_id' => $session->unit_id,
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
