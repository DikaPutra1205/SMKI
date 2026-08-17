<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateChecklistEntryRequest;
use App\Http\Requests\VerifyChecklistRequest;
use App\Models\ChecklistEntry;
use App\Models\Control;
use App\Models\WorkUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChecklistEntryController extends Controller
{
    /** Update status / PIC checklist entry */
    public function update(UpdateChecklistEntryRequest $request, ChecklistEntry $checklistEntry): RedirectResponse
    {
        $checklistEntry->update($request->validated());

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Status checklist berhasil diperbarui.',
        ]);
    }

    /** Verifikasi status checklist oleh Admin */
    public function verify(VerifyChecklistRequest $request, ChecklistEntry $checklistEntry): RedirectResponse
    {
        $data = $request->validated();
        $isVerified = $data['is_verified'] ?? true;

        $checklistEntry->update([
            'admin_id' => $data['admin_id'],
            'catatan_admin' => $data['catatan_admin'] ?? $checklistEntry->catatan_admin,
            'tanggal_verifikasi' => $isVerified ? now() : null,
        ]);

        $statusMsg = $isVerified ? 'berhasil diverifikasi' : 'batal diverifikasi';

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => "Checklist {$statusMsg}.",
        ]);
    }

    /** Generate otomatis checklist bulanan */
    public function generateMonthly(Request $request): RedirectResponse
    {
        $bulan = (int) ($request->bulan ?? now()->month);
        $tahun = (int) ($request->tahun ?? now()->year);
        $unitId = $request->unit_id;

        $units = $unitId ? WorkUnit::where('id', $unitId)->get() : WorkUnit::all();
        $controls = Control::all();

        $created = 0;
        foreach ($units as $unit) {
            foreach ($controls as $control) {
                $exists = ChecklistEntry::where('control_id', $control->id)
                    ->where('unit_id', $unit->id)
                    ->whereYear('tanggal_input', $tahun)
                    ->whereMonth('tanggal_input', $bulan)
                    ->exists();

                if (! $exists) {
                    ChecklistEntry::create([
                        'control_id' => $control->id,
                        'unit_id' => $unit->id,
                        'status' => 'belum_diterapkan',
                        'tanggal_input' => now()->setDate($tahun, $bulan, 1),
                    ]);
                    $created++;
                }
            }
        }

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => "Generate checklist periode {$bulan}/{$tahun} selesai. {$created} entry baru dibuat.",
        ]);
    }

    /** Restore checklist entry */
    public function restore(int $id): RedirectResponse
    {
        $entry = ChecklistEntry::withTrashed()->findOrFail($id);
        $entry->restore();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Checklist entry berhasil dipulihkan.',
        ]);
    }
}
