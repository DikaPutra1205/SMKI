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

        // Pre-fetch PIC users keyed by unit_id (one query instead of N)
        $picMap = User::whereHas('role', fn ($q) => $q->where('name', User::ROLE_PIC))
            ->get()
            ->unique('unit_id')
            ->keyBy('unit_id');

        // Cache today's per-user notification collections (one query per user)
        $todaysCache = [];

        $sent = 0;
        foreach ($findings as $finding) {
            $deadline = Carbon::parse($finding->deadline)->startOfDay();
            $days = (int) $today->diffInDays($deadline, false);
            $isOverdue = $days < 0;

            if (! $isOverdue && ! in_array($days, $thresholds, true)) {
                continue;
            }

            $target = $finding->pic ?? $picMap->get($finding->unit_id);

            if (! $target) {
                continue;
            }

            // Fetch once per user, reuse across findings
            $userId = $target->id;
            if (! isset($todaysCache[$userId])) {
                $todaysCache[$userId] = $target->notifications()
                    ->whereDate('created_at', $today)
                    ->get()
                    ->filter(fn ($n) => ($n->data['type'] ?? null) === 'finding_deadline_reminder');
            }

            $alreadySent = $todaysCache[$userId]->contains(fn ($n) => ($n->data['finding_id'] ?? null) === $finding->id
                && ($isOverdue
                    ? ($n->data['days_remaining'] ?? 1) < 0
                    : ($n->data['days_remaining'] ?? null) === $days));

            if ($alreadySent) {
                continue;
            }

            $target->notify(new FindingDeadlineReminderNotification($finding, $days));
            $sent++;
        }

        $this->info("Terkirim {$sent} pengingat tenggat temuan.");

        return 0;
    }
}
