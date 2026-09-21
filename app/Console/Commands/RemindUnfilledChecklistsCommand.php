<?php

namespace App\Console\Commands;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\User;
use App\Notifications\ChecklistUnfilledReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RemindUnfilledChecklistsCommand extends Command
{
    protected $signature = 'smki:remind-unfilled-checklists';

    protected $description = 'Kirim rangkuman checklist belum diisi (H-3/H-1/hari terakhir) ke PIC';

    public function handle(): int
    {
        if (! config('smki.reminders.enabled', true)) {
            $this->info('Pengingat dinonaktifkan via konfigurasi.');

            return 0;
        }

        $today = Carbon::today();
        $endOfMonth = $today->copy()->endOfMonth()->startOfDay();
        $daysToEnd = (int) $today->diffInDays($endOfMonth, false);
        $thresholds = array_values(array_filter(
            (array) config('smki.reminders.checklist_nudge_days_to_month_end', [3, 1, 0]),
            fn ($d) => is_numeric($d) && (int) $d >= 0
        ));

        if (! in_array($daysToEnd, $thresholds, true)) {
            $this->info('Bukan jadwal nudge checklist.');

            return 0;
        }

        $sessions = ChecklistSession::with(['entries', 'unit'])
            ->where('periode', $today->format('Y-m'))
            ->get();

        $sent = 0;
        foreach ($sessions as $session) {
            $total = $session->entries->count();
            $unfilled = $session->entries
                ->whereIn('status', [
                    ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
                    ChecklistEntry::WORKFLOW_DALAM_PROSES,
                ])->count();

            if ($total === 0 || $unfilled === 0) {
                continue;
            }

            $pic = User::where('unit_id', $session->unit_id)
                ->whereHas('role', fn ($q) => $q->where('name', User::ROLE_PIC))
                ->first();

            if (! $pic) {
                continue;
            }

            $already = $pic->notifications()
                ->whereDate('created_at', $today)
                ->get()
                ->contains(fn ($n) => ($n->data['type'] ?? null) === 'checklist_unfilled_reminder'
                    && ($n->data['session_id'] ?? null) === $session->id);

            if ($already) {
                continue;
            }

            $pic->notify(new ChecklistUnfilledReminderNotification($session, $unfilled, $total));
            $sent++;
        }

        $this->info("Terkirim {$sent} rangkuman checklist belum diisi.");

        return 0;
    }
}
