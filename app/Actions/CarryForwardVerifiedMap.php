<?php

namespace App\Actions;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CarryForwardVerifiedMap
{
    public static function fromPreviousPeriod(int $unitId, string $frameworkId, string $period): Collection
    {
        $prevPeriod = Carbon::parse($period)->subMonth()->format('Y-m');

        $prevSession = ChecklistSession::where('unit_id', $unitId)
            ->where('framework_id', $frameworkId)
            ->where('periode', $prevPeriod)
            ->first();

        if (! $prevSession) {
            return collect();
        }

        return ChecklistEntry::where('session_id', $prevSession->id)
            ->where('status', ChecklistEntry::WORKFLOW_SELESAI)
            ->get()
            ->keyBy('control_id');
    }
}
