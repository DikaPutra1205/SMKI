<?php

namespace App\Console\Commands;

use App\Models\ChecklistEntry;
use App\Models\Control;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Console\Command;

class GenerateMonthlyChecklistCommand extends Command
{
    protected $signature = 'smki:generate-monthly-checklist {--unit= : ID Unit kerja spesifik}';
    protected $description = 'Otomatis men-generate checklist penerapan kontrol ISO 27001 untuk seluruh satuan kerja';

    public function handle(): int
    {
        $this->info('Memulai pembuatan checklist kontrol otomatis...');

        $controls = Control::all();
        if ($controls->isEmpty()) {
            $this->warn('Belum ada master data kontrol.');
            return 1;
        }

        $unitsQuery = WorkUnit::query();
        if ($unitId = $this->option('unit')) {
            $unitsQuery->where('id', $unitId);
        }
        $units = $unitsQuery->get();

        $rowsToInsert = [];
        $now = now();

        foreach ($units as $unit) {
            $pic = User::where('unit_id', $unit->id)->where('role', User::ROLE_PIC)->first()
                ?? User::where('role', User::ROLE_PIC)->first();

            if (!$pic) continue;

            $existingControlIds = ChecklistEntry::where('unit_id', $unit->id)
                                                ->pluck('control_id')
                                                ->flip()
                                                ->toArray();

            foreach ($controls as $ctrl) {
                if (!isset($existingControlIds[$ctrl->id])) {
                    $rowsToInsert[] = [
                        'control_id'    => $ctrl->id,
                        'unit_id'       => $unit->id,
                        'pic_id'        => $pic->id,
                        'status'        => ChecklistEntry::STATUS_NON_COMPLIANT,
                        'catatan'       => 'Belum diisi oleh PIC.',
                        'tanggal_input' => $now,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ];
                }
            }
        }

        $totalCreated = count($rowsToInsert);
        foreach (array_chunk($rowsToInsert, 100) as $chunk) {
            ChecklistEntry::insert($chunk);
        }

        if ($totalCreated === 0) {
            $this->info("Semua lembar checklist untuk seluruh unit kerja sudah lengkap dan tersedia (0 data baru dibuat).");
        } else {
            $this->info("Selesai! {$totalCreated} lembar checklist baru berhasil dibuat secara otomatis.");
        }
        return 0;
    }
}
