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
            'status' => 'sometimes|nullable|string|in:compliant,partial,non_compliant,na',
            'level_maturity' => 'sometimes|nullable|integer|min:0|max:5',
            'catatan' => 'nullable|string|max:2000',
        ]);

        $updateData = array_merge(
            $validated,
            ['tanggal_input' => now()]
        );

        // Loose compare: request may carry '3' (string) vs 3 (int cast).
        $maturityChanging = array_key_exists('level_maturity', $validated)
            && $validated['level_maturity'] != $entry->level_maturity;

        if ((isset($validated['status']) && $validated['status'] !== $entry->status) || $maturityChanging) {
            $updateData['tanggal_verifikasi'] = null;
            $updateData['catatan_admin'] = null;
            $updateData['admin_id'] = null;
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
            'entries.*.status' => 'sometimes|nullable|string|in:compliant,partial,non_compliant,na',
            'entries.*.level_maturity' => 'sometimes|nullable|integer|min:0|max:5',
            'entries.*.catatan' => 'sometimes|nullable|string|max:2000',
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

                $updateData = ['updated_at' => $now];

                $statusChanging = array_key_exists('status', $item) && $item['status'] !== $entry->status;
                $maturityChanging = array_key_exists('level_maturity', $item) && $item['level_maturity'] != $entry->level_maturity;

                if ($statusChanging) {
                    $updateData['status'] = $item['status'];
                }
                if ($maturityChanging) {
                    $updateData['level_maturity'] = $item['level_maturity'];
                }
                if ($statusChanging || $maturityChanging) {
                    $updateData['tanggal_verifikasi'] = null;
                    $updateData['catatan_admin'] = null;
                    $updateData['admin_id'] = null;
                    $updateData['tanggal_input'] = $now;
                }
                if (array_key_exists('catatan', $item)) {
                    $updateData['catatan'] = $item['catatan'];
                    $updateData['tanggal_input'] = $now;
                }

                if (count($updateData) > 1) {
                    $entry->update($updateData);
                    $updated++;
                }
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

        $lastVersion = ComplianceEvidence::withTrashed()
            ->where('checklist_entry_id', $entry->id)
            ->max('version_number') ?? 0;

        $evidence = ComplianceEvidence::create([
            'checklist_entry_id' => $entry->id,
            'uploaded_by' => $user->id,
            'file_url' => $path,
            'version_number' => $lastVersion + 1,
            'is_active' => true,
            'uploaded_at' => now(),
        ]);

        $entry->update([
            'tanggal_input' => now(),
            'tanggal_verifikasi' => null,
            'catatan_admin' => null,
            'admin_id' => null,
        ]);

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
}
