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
use Illuminate\Support\Facades\Gate;

class ChecklistSessionController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = ChecklistSession::with([
            'unit:id,nama',
            'framework:id,nama,versi',
            'creator:id,name',
            'updater:id,name',
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

        if ($request->filled('periode')) {
            $query->where('periode', $request->periode);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $like = config('database.default') === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('konteks_penilaian', $like, "%{$search}%")
                    ->orWhere('periode', $like, "%{$search}%")
                    ->orWhere('catatan', $like, "%{$search}%");
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
        Gate::authorize('checklist-session.create');

        $data = $request->validated();
        $data['created_by'] = $data['created_by'] ?? auth()->id();
        $data['updated_by'] = $data['updated_by'] ?? auth()->id();

        $session = ChecklistSession::create($data);

        // Auto-provision checklist entries for the session
        $this->provisionSessionEntries($session);

        $fresh = $session->fresh([
            'unit:id,nama',
            'framework:id,nama,versi',
            'creator:id,name',
            'updater:id,name',
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
            'updater:id,name',
            'entries.control:id,framework_id,kode_klausul,judul,kategori',
            'entries.control.framework:id,nama,versi',
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
        Gate::authorize('checklist-session.update');

        // Cross-unit tamper guard: a user may only mutate sessions in their own unit.
        if ((int) $checklistSession->unit_id !== (int) auth()->user()->unit_id) {
            abort(403);
        }

        $data = $request->validated();
        $data['updated_by'] = auth()->id();

        $checklistSession->update($data);

        return $this->success(
            $checklistSession->fresh(['unit:id,nama', 'framework:id,nama,versi', 'creator:id,name', 'updater:id,name']),
            'Sesi checklist berhasil diperbarui.'
        );
    }

    public function destroy(ChecklistSession $checklistSession): JsonResponse
    {
        Gate::authorize('checklist-session.delete');

        $checklistSession->entries()->delete();
        $checklistSession->delete();

        return $this->success(null, 'Sesi checklist berhasil dihapus (soft delete).');
    }

    public function restore(int $id): JsonResponse
    {
        Gate::authorize('checklist-session.restore');

        $session = ChecklistSession::withTrashed()->findOrFail($id);
        $session->restore();
        $session->entries()->withTrashed()->restore();

        return $this->success($session->load(['unit', 'framework', 'creator', 'updater']), 'Sesi checklist berhasil dipulihkan.');
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

        $pic = User::with('role:id,name')
            ->where('unit_id', $session->unit_id)
            ->whereHas('role', fn ($q) => $q->where('name', User::ROLE_PIC))
            ->first()
            ?? User::with('role:id,name')->whereHas('role', fn ($q) => $q->where('name', User::ROLE_PIC))->first()
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
