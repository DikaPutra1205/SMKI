<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\ComplianceEvidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistEntryController extends Controller
{
    public function update(Request $request, int $id)
    {
        $user = $request->user();

        $entry = ChecklistEntry::where('pic_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'tidak_berlaku' => 'sometimes|boolean',
            'status' => 'prohibited',
            'level_maturity' => 'sometimes|nullable|integer|min:0|max:5',
            'catatan' => 'required_if:tidak_berlaku,true|nullable|string|max:2000',
        ]);

        $newCatatan = array_key_exists('catatan', $validated) ? $validated['catatan'] : $entry->catatan;
        $naSelected = (bool) ($validated['tidak_berlaku'] ?? $entry->status === ChecklistEntry::WORKFLOW_TIDAK_BERLAKU);
        $hasBukti = $entry->evidences()->exists();

        $updateData = [
            'status' => $entry->applyPicTouch($newCatatan, $hasBukti, $naSelected),
            'tanggal_input' => now(),
            'tanggal_verifikasi' => null,
        ];

        if (array_key_exists('catatan', $validated)) {
            $updateData['catatan'] = $validated['catatan'];
        }
        if (array_key_exists('level_maturity', $validated)) {
            $updateData['level_maturity'] = $validated['level_maturity'];
        }

        $entry->update($updateData);

        return response()->json(['ok' => true]);
    }

    public function batchUpdate(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'session_id' => 'required|integer|exists:checklist_sessions,id',
            'entries' => 'required|array|min:1|max:100',
            'entries.*.id' => 'required|integer|exists:checklist_entries,id',
            'entries.*.tidak_berlaku' => 'sometimes|boolean',
            'entries.*.status' => 'prohibited',
            'entries.*.level_maturity' => 'sometimes|nullable|integer|min:0|max:5',
            'entries.*.catatan' => 'required_if:entries.*.tidak_berlaku,true|sometimes|nullable|string|max:2000',
        ]);

        $session = ChecklistSession::where('id', $validated['session_id'])
            ->where('unit_id', $user->unit_id)
            ->firstOrFail();

        $entryIds = array_column($validated['entries'], 'id');
        $entries = ChecklistEntry::whereIn('id', $entryIds)
            ->where('session_id', $session->id)
            ->where('pic_id', $user->id)
            ->get()
            ->keyBy('id');

        $now = now();
        $updated = 0;

        DB::transaction(function () use ($validated, $entries, $now, &$updated) {
            foreach ($validated['entries'] as $item) {
                $entry = $entries->get($item['id']);
                if (! $entry) {
                    continue;
                }

                $newCatatan = array_key_exists('catatan', $item) ? $item['catatan'] : $entry->catatan;
                $naSelected = (bool) ($item['tidak_berlaku'] ?? $entry->status === ChecklistEntry::WORKFLOW_TIDAK_BERLAKU);
                $hasBukti = $entry->evidences()->exists();

                $updateData = [
                    'status' => $entry->applyPicTouch($newCatatan, $hasBukti, $naSelected),
                    'tanggal_input' => $now,
                    'tanggal_verifikasi' => null,
                ];

                if (array_key_exists('catatan', $item)) {
                    $updateData['catatan'] = $item['catatan'];
                }
                if (array_key_exists('level_maturity', $item)) {
                    $updateData['level_maturity'] = $item['level_maturity'];
                }

                $entry->update($updateData);
                $updated++;
            }
        });

        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    public function uploadEvidence(Request $request, int $id)
    {
        $user = $request->user();

        $entry = ChecklistEntry::where('pic_id', $user->id)->findOrFail($id);

        if ($entry->session_id) {
            ChecklistSession::where('id', $entry->session_id)
                ->where('unit_id', $user->unit_id)
                ->firstOrFail();
        }

        $validated = $request->validate([
            'bukti_file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:10240',
        ]);

        $file = $validated['bukti_file'];
        $path = $file->storeAs('bukti/'.$entry->id, $file->getClientOriginalName(), 'supabase');

        $evidence = DB::transaction(function () use ($entry, $user, $path) {
            $lockedVersions = ComplianceEvidence::withTrashed()
                ->where('checklist_entry_id', $entry->id)
                ->lockForUpdate()
                ->pluck('version_number');
            $lastVersion = $lockedVersions->max() ?? 0;

            $evidence = ComplianceEvidence::create([
                'checklist_entry_id' => $entry->id,
                'uploaded_by' => $user->id,
                'file_url' => $path,
                'version_number' => $lastVersion + 1,
                'is_active' => true,
                'uploaded_at' => now(),
            ]);

            $entry->update([
                'status' => $entry->applyPicTouch($entry->catatan, true, $entry->status === ChecklistEntry::WORKFLOW_TIDAK_BERLAKU),
                'tanggal_input' => now(),
                'tanggal_verifikasi' => null,
            ]);

            return $evidence;
        });

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'evidence' => [
                    'id' => $evidence->id,
                    'checklist_entry_id' => $evidence->checklist_entry_id,
                    'version_number' => $evidence->version_number,
                    'file_url' => $evidence->file_url,
                    'nama_file' => $evidence->nama_file,
                    'is_active' => $evidence->is_active,
                ],
            ]);
        }

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Bukti berhasil diunggah.',
        ]);
    }

    public function deleteEvidence(Request $request, int $id, int $evidenceId)
    {
        $user = $request->user();
        $entry = ChecklistEntry::where('pic_id', $user->id)->findOrFail($id);
        $evidence = ComplianceEvidence::where('checklist_entry_id', $entry->id)->findOrFail($evidenceId);

        $evidence->delete();

        $hasBukti = $entry->evidences()->exists();
        $naSelected = $entry->status === ChecklistEntry::WORKFLOW_TIDAK_BERLAKU;

        $entry->update([
            'status' => $entry->applyPicTouch($entry->catatan, $hasBukti, $naSelected),
            'tanggal_input' => now(),
            'tanggal_verifikasi' => null,
        ]);

        return response()->json(['ok' => true]);
    }
}
