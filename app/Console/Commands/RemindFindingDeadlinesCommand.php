<?php

namespace App\Console\Commands;

use App\Models\Finding;
use App\Models\User;
use App\Notifications\FindingDeadlineReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RemindFindingDeadlinesCommand extends Command
{
    protected $signature = 'smki:remind-finding-deadlines';

    protected $description = 'Kirim pengingat tenggat temuan (H-7/H-3/H-1 + overdue harian) ke PIC';

    public function handle(): int
    {
        if (! config('smki.reminders.enabled', true)) {
            $this->info('Pengingat dinonaktifkan via konfigurasi.');

            return 0;
        }

        $today = Carbon::today();
        $thresholds = array_values(array_filter(
            (array) config('smki.reminders.finding_deadline_days', [7, 3, 1]),
            fn ($d) => is_numeric($d) && (int) $d > 0
        ));

        $findings = Finding::with(['control', 'unit', 'pic'])
            ->whereNotNull('deadline')
            ->where('status', '!=', Finding::STATUS_CLOSED)
            ->get();

        $sent = 0;
        foreach ($findings as $finding) {
            $deadline = Carbon::parse($finding->deadline)->startOfDay();
            $days = (int) $today->diffInDays($deadline, false);
            $isOverdue = $days < 0;

            if (! $isOverdue && ! in_array($days, $thresholds, true)) {
                continue;
            }

            $target = $finding->pic
                ?? User::where('unit_id', $finding->unit_id)
                    ->whereHas('role', fn ($q) => $q->where('name', User::ROLE_PIC))
                    ->first();

            if (! $target) {
                continue;
            }

            if ($this->alreadySentToday($target, (int) $finding->id, $days, $isOverdue)) {
                continue;
            }

            $target->notify(new FindingDeadlineReminderNotification($finding, $days));
            $sent++;
        }

        $this->info("Terkirim {$sent} pengingat tenggat temuan.");

        return 0;
    }

    protected function alreadySentToday(User $user, int $findingId, int $days, bool $isOverdue): bool
    {
        return $user->notifications()
            ->whereDate('created_at', Carbon::today())
            ->get()
            ->contains(fn ($n) => ($n->data['type'] ?? null) === 'finding_deadline_reminder'
                && ($n->data['finding_id'] ?? null) === $findingId
                && ($isOverdue
                    ? ($n->data['days_remaining'] ?? 1) < 0
                    : ($n->data['days_remaining'] ?? null) === $days));
    }
}
