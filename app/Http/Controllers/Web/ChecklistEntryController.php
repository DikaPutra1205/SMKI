<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\ComplianceEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChecklistEntryController extends Controller
{
    public function update(Request $request, int $id)
    {
        $user = $request->user();

        $entry = ChecklistEntry::where('pic_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|nullable|string|in:compliant,partial,non_compliant,na',
            'catatan' => 'required_if:status,non_compliant,na|nullable|string|max:2000',
        ]);

        $updateData = array_merge(
            $validated,
            ['tanggal_input' => now()]
        );

        if (isset($validated['status']) && $validated['status'] !== $entry->status) {
            $updateData['tanggal_verifikasi'] = null;
        }

        $entry->update($updateData);

        return response()->json(['ok' => true]);
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
            'file_url' => Storage::disk('supabase')->url($path),
            'version_number' => $lastVersion + 1,
            'is_active' => true,
            'uploaded_at' => now(),
        ]);

        $entry->update([
            'tanggal_input' => now(),
            'tanggal_verifikasi' => null,
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
