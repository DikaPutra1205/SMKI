<?php

namespace App\Console\Commands;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateMonthlyChecklistCommand extends Command
{
    protected $signature = 'smki:generate-monthly-checklist {--unit= : ID Unit kerja spesifik} {--periode= : Periode spesifik dalam format YYYY-MM}';

    protected $description = 'Otomatis men-generate sesi checklist bulanan beserta seluruh kontrol ISO 27001 untuk satuan kerja';

    public function handle(): int
    {
        $this->info('Memulai pembuatan sesi checklist kontrol otomatis...');

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

        if ($units->isEmpty()) {
            $this->warn('Tidak ada unit kerja yang ditemukan.');

            return 0;
        }

        $now = now();
        $period = $this->option('periode') ?: $now->format('Y-m');
        $periodLabel = Carbon::parse($period)->translatedFormat('F Y');

        $frameworks = Framework::whereHas('controls')->get();
        if ($frameworks->isEmpty()) {
            $frameworks = collect([null]);
        }

        $rowsToInsert = [];
        $sessionsCreated = 0;

        foreach ($units as $unit) {
            $pic = User::with('role:id,name')
                ->where('unit_id', $unit->id)
                ->whereHas('role', fn ($q) => $q->where('name', User::ROLE_PIC))
                ->first()
                ?? User::with('role:id,name')->whereHas('role', fn ($q) => $q->where('name', User::ROLE_PIC))->first();

            foreach ($frameworks as $framework) {
                $frameworkId = $framework?->id;
                $frameworkControls = Control::when($frameworkId, fn ($q) => $q->where('framework_id', $frameworkId))->get();

                if ($frameworkControls->isEmpty()) {
                    continue;
                }

                $session = ChecklistSession::firstOrCreate(
                    [
                        'unit_id' => $unit->id,
                        'framework_id' => $frameworkId,
                        'periode' => $period,
                    ],
                    [
                        'konteks_penilaian' => "Penilaian Bulanan SMKI - {$periodLabel}".($framework ? " ({$framework->nama})" : ''),
                        'created_by' => $pic?->id,
                        'updated_by' => $pic?->id,
                        'catatan' => 'Otomatis di-generate oleh sistem untuk periode '.$periodLabel,
                    ]
                );

                if ($session->wasRecentlyCreated) {
                    $sessionsCreated++;
                }

                $existingControlIds = ChecklistEntry::where('session_id', $session->id)
                    ->pluck('control_id')
                    ->flip()
                    ->toArray();

                foreach ($frameworkControls as $ctrl) {
                    if (! isset($existingControlIds[$ctrl->id])) {
                        $rowsToInsert[] = [
                            'session_id' => $session->id,
                            'control_id' => $ctrl->id,
                            'unit_id' => $unit->id,
                            'pic_id' => $pic?->id,
                            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
                            'catatan' => 'Belum diisi oleh PIC.',
                            'tanggal_input' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }
        }

        $totalCreated = count($rowsToInsert);
        foreach (array_chunk($rowsToInsert, 100) as $chunk) {
            ChecklistEntry::insert($chunk);
        }

        if ($totalCreated === 0 && $sessionsCreated === 0) {
            $this->info('Semua sesi dan lembar checklist untuk unit kerja sudah lengkap dan tersedia (0 data baru dibuat).');
        } else {
            $this->info("Selesai! {$sessionsCreated} sesi baru dan {$totalCreated} lembar checklist baru berhasil dibuat secara otomatis.");
        }

        return 0;
    }
}
