<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChecklistSessionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $sessions = ChecklistSession::with([
            'unit:id,nama',
            'framework:id,nama,versi',
        ])
            ->where('unit_id', $user->unit_id)
            ->withCount([
                'entries as total_entries',
                'entries as completed_entries' => fn ($q) => $q->where('status', ChecklistEntry::STATUS_COMPLIANT)
                    ->orWhere(function ($q2) {
                        $q2->whereIn('status', [ChecklistEntry::STATUS_PARTIAL, ChecklistEntry::STATUS_NON_COMPLIANT, ChecklistEntry::STATUS_NA])
                            ->where('catatan', '!=', '')
                            ->whereNotNull('catatan');
                    }),
                'entries as compliant_entries' => fn ($q) => $q->where('status', ChecklistEntry::STATUS_COMPLIANT),
                'entries as partial_entries' => fn ($q) => $q->where('status', ChecklistEntry::STATUS_PARTIAL),
                'entries as non_compliant_entries' => fn ($q) => $q->where('status', ChecklistEntry::STATUS_NON_COMPLIANT),
                'entries as na_entries' => fn ($q) => $q->where('status', ChecklistEntry::STATUS_NA),
            ])
            ->orderByDesc('id')
            ->get();

        return Inertia::render('pic/checklist', [
            'sessions' => $sessions,
            'user_unit' => $user->unit,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('checklist-session.create');

        $validated = $request->validate([
            'konteks_penilaian' => 'required|string|max:255',
            'periode' => 'nullable|string|max:100',
            'unit_id' => 'required|exists:work_units,id',
            'framework_id' => 'nullable|exists:frameworks,id',
        ]);

        $user = $request->user();

        $session = ChecklistSession::create([
            ...$validated,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $query = Control::query();
        if ($session->framework_id) {
            $query->where('framework_id', $session->framework_id);
        }
        $controls = $query->get();

        if ($controls->isNotEmpty()) {
            $insertData = [];
            $now = now();
            foreach ($controls as $ctrl) {
                $insertData[] = [
                    'session_id' => $session->id,
                    'control_id' => $ctrl->id,
                    'unit_id' => $session->unit_id,
                    'pic_id' => $user->id,
                    'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
                    'catatan' => null,
                    'tanggal_input' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($insertData, 100) as $chunk) {
                ChecklistEntry::insert($chunk);
            }
        }

        return redirect()->route('admin.pic.checklist.show', $session)
            ->with('flash', ['type' => 'success', 'message' => 'Assessment berhasil dibuat.']);
    }

    public function show(ChecklistSession $checklistSession): Response
    {
        $user = request()->user();

        if ($checklistSession->unit_id !== $user->unit_id) {
            abort(403);
        }

        $checklistSession->load([
            'unit:id,nama',
            'framework:id,nama,versi',
        ]);

        $allEntries = ChecklistEntry::where('checklist_entries.session_id', $checklistSession->id)
            ->with([
                'control.framework:id,nama,versi',
                'activeEvidence:id,checklist_entry_id,version_number,file_url,is_active',
            ])
            ->join('controls', 'controls.id', '=', 'checklist_entries.control_id')
            ->orderBy('controls.kategori', 'desc')
            ->orderBy('controls.kode_klausul', 'asc')
            ->select('checklist_entries.*')
            ->get();

        $grouped = $allEntries->groupBy(fn ($e) => $e->control->framework_name.'|||'.$e->control->kategori);

        $pages = [];
        $index = 0;
        foreach ($grouped as $key => $items) {
            [$frameworkName, $kategori] = explode('|||', $key);
            $pages[] = [
                'index' => $index,
                'framework_name' => $frameworkName,
                'kategori' => $kategori,
                'entry_count' => $items->count(),
            ];
            $index++;
        }

        $firstPageEntries = $grouped->values()->first() ?? collect();

        return Inertia::render('pic/checklist-detail', [
            'session' => $checklistSession,
            'initialEntries' => $firstPageEntries->values(),
            'pageMeta' => $pages,
            'totalEntries' => $allEntries->count(),
        ]);
    }

    public function checklistPage(ChecklistSession $checklistSession, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($checklistSession->unit_id !== $user->unit_id) {
            abort(403);
        }

        $allEntries = ChecklistEntry::where('checklist_entries.session_id', $checklistSession->id)
            ->with([
                'control.framework:id,nama,versi',
                'activeEvidence:id,checklist_entry_id,version_number,file_url,is_active',
            ])
            ->join('controls', 'controls.id', '=', 'checklist_entries.control_id')
            ->orderBy('controls.kategori', 'desc')
            ->orderBy('controls.kode_klausul', 'asc')
            ->select('checklist_entries.*')
            ->get();

        $grouped = $allEntries->groupBy(fn ($e) => $e->control->framework_name.'|||'.$e->control->kategori);

        $pages = [];
        $index = 0;
        foreach ($grouped as $key => $items) {
            [$frameworkName, $kategori] = explode('|||', $key);
            $pages[] = [
                'index' => $index,
                'framework_name' => $frameworkName,
                'kategori' => $kategori,
                'entry_count' => $items->count(),
            ];
            $index++;
        }

        $page = (int) $request->query('page', 0);
        $page = max(0, min($page, count($pages) - 1));

        $pageEntries = $grouped->values()->get($page) ?? collect();

        return response()->json([
            'entries' => $pageEntries->values(),
            'page_meta' => $pages,
            'current_page' => $page,
            'total_entries' => $allEntries->count(),
        ]);
    }

    public function summary(ChecklistSession $checklistSession): Response
    {
        $user = request()->user();

        if ($checklistSession->unit_id !== $user->unit_id) {
            abort(403);
        }

        $checklistSession->load([
            'unit:id,nama',
            'framework:id,nama,versi',
            'entries' => function ($q) {
                $q->with([
                    'control.framework:id,nama,versi',
                    'activeEvidence:id,checklist_entry_id,version_number,file_url,is_active',
                ])
                    ->join('controls', 'controls.id', '=', 'checklist_entries.control_id')
                    ->orderBy('controls.kategori', 'desc')
                    ->orderBy('controls.kode_klausul', 'asc')
                    ->select('checklist_entries.*');
            },
            'entries.control:id,framework_id,kode_klausul,judul,deskripsi,kategori',
            'entries.control.framework:id,nama,versi',
        ]);

        $summary = $checklistSession->summary;

        return Inertia::render('pic/checklist-summary', [
            'session' => $checklistSession,
            'entries' => $checklistSession->entries,
            'summary' => $summary,
        ]);
    }

    public function submitAssessment(Request $request, ChecklistSession $checklistSession): RedirectResponse
    {
        Gate::authorize('checklist-session.update');

        $user = $request->user();

        if ($checklistSession->unit_id !== $user->unit_id) {
            abort(403);
        }

        $incomplete = $checklistSession->entries()
            ->whereIn('status', [ChecklistEntry::STATUS_PARTIAL, ChecklistEntry::STATUS_NON_COMPLIANT, ChecklistEntry::STATUS_NA])
            ->where(fn ($q) => $q->whereNull('catatan')->orWhere('catatan', ''))
            ->count();

        if ($incomplete > 0) {
            return redirect()->back()
                ->with('flash', ['type' => 'error', 'message' => "{$incomplete} kontrol belum diisi catatan untuk status Sebagian Patuh/Ketidaksesuaian/Tidak Berlaku."]);
        }

        // Mark all entries with tanggal_input
        $checklistSession->entries()
            ->whereNull('tanggal_input')
            ->update([
                'tanggal_input' => now(),
            ]);

        $checklistSession->update([
            'updated_by' => $user->id,
        ]);

        return redirect()->route('admin.pic.checklist')
            ->with('flash', ['type' => 'success', 'message' => 'Assessment berhasil dikirim untuk verifikasi.']);
    }

    public function update(Request $request, ChecklistSession $checklistSession): RedirectResponse
    {
        Gate::authorize('checklist-session.update');

        if ($checklistSession->unit_id !== $request->user()->unit_id) {
            abort(403);
        }

        $validated = $request->validate([
            'konteks_penilaian' => 'sometimes|required|string|max:255',
            'periode' => 'nullable|string|max:100',
            'catatan' => 'nullable|string',
        ]);

        $validated['updated_by'] = $request->user()->id;

        $checklistSession->update($validated);

        return redirect()->back()
            ->with('flash', ['type' => 'success', 'message' => 'Assessment berhasil diperbarui.']);
    }
}
