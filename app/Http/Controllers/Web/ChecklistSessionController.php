<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChecklistSessionRequest;
use App\Http\Requests\UpdateChecklistSessionRequest;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChecklistSessionController extends Controller
{
    public function store(StoreChecklistSessionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $data['created_by'] ?? auth()->id();
        $data['status'] = $data['status'] ?? ChecklistSession::STATUS_IN_PROGRESS;

        $session = ChecklistSession::create($data);

        // Auto provision controls
        $query = Control::query();
        if ($session->framework_id) {
            $query->where('framework_id', $session->framework_id);
        }
        $controls = $query->get();

        $pic = User::where('unit_id', $session->unit_id)->where('role', User::ROLE_PIC)->first()
            ?? User::where('role', User::ROLE_PIC)->first()
            ?? User::first();

        if ($pic && $controls->isNotEmpty()) {
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

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Sesi checklist audit berhasil dibuat.',
        ]);
    }

    public function update(UpdateChecklistSessionRequest $request, ChecklistSession $checklistSession): RedirectResponse
    {
        $checklistSession->update($request->validated());

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Sesi checklist berhasil diperbarui.',
        ]);
    }

    public function submit(Request $request, ChecklistSession $checklistSession): RedirectResponse
    {
        $checklistSession->update([
            'status' => ChecklistSession::STATUS_SUBMITTED,
        ]);

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Sesi checklist berhasil disubmit ke Auditor / Admin.',
        ]);
    }

    public function verify(Request $request, ChecklistSession $checklistSession): RedirectResponse
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

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => "Status sesi checklist berhasil diubah menjadi {$updateData['status']}.",
        ]);
    }

    public function destroy(ChecklistSession $checklistSession): RedirectResponse
    {
        $checklistSession->entries()->delete();
        $checklistSession->delete();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Sesi checklist berhasil dihapus.',
        ]);
    }

    public function restore(int $id): RedirectResponse
    {
        $session = ChecklistSession::withTrashed()->findOrFail($id);
        $session->restore();
        $session->entries()->withTrashed()->restore();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Sesi checklist berhasil dipulihkan.',
        ]);
    }
}
