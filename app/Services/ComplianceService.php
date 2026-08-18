<?php

namespace App\Services;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Framework;
use App\Models\WorkUnit;

class ComplianceService
{
    /**
     * Get frameworks with control counts and compliance rates.
     */
    public function getFrameworkSummaries(): array
    {
        return Framework::withCount('controls')
            ->get()
            ->map(function (Framework $fw) {
                $totalEntries = ChecklistEntry::whereHas('control', fn ($q) => $q->where('framework_id', $fw->id))->count();
                $compliantEntries = ChecklistEntry::whereHas('control', fn ($q) => $q->where('framework_id', $fw->id))
                    ->where('status', ChecklistEntry::STATUS_COMPLIANT)
                    ->count();

                $percentage = $totalEntries > 0 ? (int) round(($compliantEntries / $totalEntries) * 100) : 0;

                return [
                    'id' => $fw->id,
                    'nama' => $fw->nama,
                    'versi' => $fw->versi,
                    'url_file' => $fw->url_file,
                    'controls_count' => $fw->controls_count,
                    'compliance_percentage' => $percentage,
                ];
            })
            ->toArray();
    }

    /**
     * Get work units list for selection.
     */
    public function getWorkUnits(): array
    {
        return WorkUnit::select('id', 'nama')
            ->orderBy('nama')
            ->get()
            ->toArray();
    }

    /**
     * Get checklist sessions list with progress statistics.
     */
    public function getChecklistSessions(array $filters = []): array
    {
        $query = ChecklistSession::with(['unit:id,nama', 'framework:id,nama,versi', 'creator:id,name', 'auditor:id,name']);

        if (! empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }

        if (! empty($filters['framework_id'])) {
            $query->where('framework_id', $filters['framework_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['periode'])) {
            $query->where('periode', $filters['periode']);
        }

        return $query->orderByDesc('id')
            ->get()
            ->map(function (ChecklistSession $session) {
                return [
                    'id' => $session->id,
                    'nama_sesi' => $session->nama_sesi,
                    'periode' => $session->periode ?? '',
                    'konteks_penilaian' => $session->konteks_penilaian ?? '',
                    'unit_id' => $session->unit_id,
                    'unit_nama' => $session->unit?->nama ?? '',
                    'framework_id' => $session->framework_id,
                    'framework_nama' => $session->framework ? "{$session->framework->nama}:{$session->framework->versi}" : '',
                    'auditor_id' => $session->auditor_id,
                    'auditor_name' => $session->auditor?->name ?? '',
                    'start_date' => $session->start_date?->format('Y-m-d'),
                    'end_date' => $session->end_date?->format('Y-m-d'),
                    'status' => $session->status,
                    'catatan' => $session->catatan ?? '',
                    'summary' => $session->summary,
                ];
            })
            ->toArray();
    }

    /**
     * Fetch controls ordered by id asc with framework and search/category filtering.
     */
    public function getControls(array $filters = []): array
    {
        $query = Control::with(['framework:id,nama,versi']);

        if (! empty($filters['framework_id'])) {
            $query->where('framework_id', $filters['framework_id']);
        }

        if (! empty($filters['kategori']) && $filters['kategori'] !== 'Semua Kategori') {
            $catSlug = match ($filters['kategori']) {
                'Annex A' => 'annex_a',
                'Klausul 4-10' => 'klausul_4_10',
                default => $filters['kategori'],
            };
            $query->where('kategori', $catSlug);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $driver = \DB::connection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('kode_klausul', $likeOperator, "%{$search}%")
                    ->orWhere('judul', $likeOperator, "%{$search}%")
                    ->orWhere('deskripsi', $likeOperator, "%{$search}%");
            });
        }

        $controls = $query->orderBy('id', 'asc')->get();

        return $controls->map(function (Control $ctrl) {
            return [
                'id' => (string) $ctrl->id,
                'framework_id' => $ctrl->framework_id,
                'framework_nama' => $ctrl->framework ? "{$ctrl->framework->nama}:{$ctrl->framework->versi}" : '',
                'code' => $ctrl->kode_klausul,
                'title' => $ctrl->judul,
                'description' => $ctrl->deskripsi ?? '',
                'category' => $ctrl->kategori === 'annex_a' ? 'Annex A' : 'Klausul 4-10',
            ];
        })->toArray();
    }
}
