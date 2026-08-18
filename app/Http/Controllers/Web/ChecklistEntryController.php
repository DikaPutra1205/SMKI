<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\ComplianceEvidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ChecklistEntryController extends Controller
{
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $entry = ChecklistEntry::where('pic_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|nullable|string|in:compliant,partial,non_compliant,na',
            'catatan' => [
                'nullable', 'string', 'max:2000',
                Rule::when(
                    in_array($request->input('status'), ['non_compliant', 'na']),
                    ['required'],
                ),
            ],
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

        $session = ChecklistSession::where('id', $entry->session_id)
            ->where('unit_id', $user->unit_id)
            ->firstOrFail();

        $validated = $request->validate([
            'bukti_file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ]);

        $file = $validated['bukti_file'];
        $filename = time().'_'.$user->id.'_'.$file->getClientOriginalName();
        $path = $file->storeAs('bukti/'.$entry->id, $filename, 'supabase');

        $lastVersion = ComplianceEvidence::withTrashed()
            ->where('checklist_entry_id', $entry->id)
            ->max('version_number') ?? 0;

        ComplianceEvidence::create([
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

        return redirect()->back()
            ->with('flash', ['type' => 'success', 'message' => 'Bukti berhasil diunggah.']);
    }
}
