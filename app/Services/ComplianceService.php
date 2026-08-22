<?php

namespace App\Services;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Framework;
use App\Models\WorkUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ComplianceService
{
    /**
     * Get frameworks with control counts and compliance rates.
     */
    public function getFrameworkSummaries(): array
    {
        $frameworks = Framework::withCount('controls')->orderBy('id')->get();

        $stats = ChecklistEntry::join('controls', 'checklist_entries.control_id', '=', 'controls.id')
            ->selectRaw('
                controls.framework_id,
                COUNT(*) as total_entries,
                SUM(CASE WHEN checklist_entries.status = ? THEN 1 ELSE 0 END) as compliant_entries
            ', [ChecklistEntry::STATUS_COMPLIANT])
            ->groupBy('controls.framework_id')
            ->get()
            ->keyBy('framework_id');

        return $frameworks->map(function (Framework $fw) use ($stats) {
            $fwStat = $stats->get($fw->id);
            $totalEntries = $fwStat ? (int) $fwStat->total_entries : 0;
            $compliantEntries = $fwStat ? (int) $fwStat->compliant_entries : 0;
            $percentage = $totalEntries > 0 ? (int) round(($compliantEntries / $totalEntries) * 100) : 0;

            return [
                'id' => $fw->id,
                'nama' => $fw->nama,
                'versi' => $fw->versi,
                'url_file' => $fw->url_file,
                'controls_count' => $fw->controls_count,
                'compliance_percentage' => $percentage,
            ];
        })->toArray();
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
     * Get checklist sessions list with progress statistics (eager aggregated, no N+1).
     */
    public function getChecklistSessions(array $filters = []): array
    {
        $query = ChecklistSession::with([
            'unit:id,nama',
            'framework:id,nama,versi',
            'creator:id,name',
            'updater:id,name',
        ])->withCount([
            'entries as total_entries',
            'entries as compliant_entries' => fn ($q) => $q->where('status', ChecklistEntry::STATUS_COMPLIANT),
            'entries as partial_entries' => fn ($q) => $q->where('status', ChecklistEntry::STATUS_PARTIAL),
            'entries as non_compliant_entries' => fn ($q) => $q->where('status', ChecklistEntry::STATUS_NON_COMPLIANT),
            'entries as na_entries' => fn ($q) => $q->where('status', ChecklistEntry::STATUS_NA),
            'entries as verified_entries' => fn ($q) => $q->whereNotNull('tanggal_verifikasi'),
            'entries as completed_entries' => fn ($q) => $q->where(fn ($q2) => $q2->where('status', ChecklistEntry::STATUS_COMPLIANT)
                ->orWhere(fn ($q3) => $q3->whereIn('status', [
                    ChecklistEntry::STATUS_PARTIAL,
                    ChecklistEntry::STATUS_NON_COMPLIANT,
                    ChecklistEntry::STATUS_NA,
                ])->whereNotNull('catatan')->where('catatan', '!=', ''))),
        ]);

        if (! empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }

        if (! empty($filters['framework_id'])) {
            $query->where('framework_id', $filters['framework_id']);
        }

        if (! empty($filters['periode'])) {
            $query->where('periode', $filters['periode']);
        }

        return $query->orderByDesc('id')
            ->get()
            ->map(function (ChecklistSession $session) {
                $total = (int) $session->total_entries;
                $completed = (int) $session->completed_entries;
                $compliant = (int) $session->compliant_entries;

                return [
                    'id' => $session->id,
                    'konteks_penilaian' => $session->konteks_penilaian,
                    'periode' => $session->periode ?? '',
                    'unit_id' => $session->unit_id,
                    'unit_nama' => $session->unit?->nama ?? '',
                    'framework_id' => $session->framework_id,
                    'framework_nama' => $session->framework ? "{$session->framework->nama}:{$session->framework->versi}" : '',
                    'created_by' => $session->created_by,
                    'creator_name' => $session->creator?->name ?? '',
                    'updated_by' => $session->updated_by,
                    'updater_name' => $session->updater?->name ?? '',
                    'catatan' => $session->catatan ?? '',
                    'summary' => [
                        'total_entries' => $total,
                        'compliant' => $compliant,
                        'partial' => (int) $session->partial_entries,
                        'non_compliant' => (int) $session->non_compliant_entries,
                        'na' => (int) $session->na_entries,
                        'verified_entries' => (int) $session->verified_entries,
                        'completed' => $completed,
                        'completion_percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
                        'compliance_percentage' => $total > 0 ? (int) round(($compliant / $total) * 100) : 0,
                    ],
                ];
            })
            ->toArray();
    }

    /**
     * Semua session assessment dari seluruh unit (untuk admin kepatuhan), lengkap
     * dengan progres per session. Status session tidak disediakan karena memang
     * tidak ada kolom status — hanya progres + jumlah terverifikasi.
     */
    public function getAdminSessions(array $filters = []): array
    {
        $query = ChecklistSession::with([
            'unit:id,nama',
            'framework:id,nama,versi',
            'creator:id,name',
            'updater:id,name',
        ])->withCount([
            'entries as total_entries',
            'entries as compliant_entries' => fn ($q) => $q->where('status', ChecklistEntry::STATUS_COMPLIANT),
            'entries as partial_entries' => fn ($q) => $q->where('status', ChecklistEntry::STATUS_PARTIAL),
            'entries as non_compliant_entries' => fn ($q) => $q->where('status', ChecklistEntry::STATUS_NON_COMPLIANT),
            'entries as na_entries' => fn ($q) => $q->where('status', ChecklistEntry::STATUS_NA),
            'entries as verified_entries' => fn ($q) => $q->whereNotNull('tanggal_verifikasi'),
        ]);

        if (! empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }

        if (! empty($filters['framework_id'])) {
            $query->where('framework_id', $filters['framework_id']);
        }

        if (! empty($filters['periode'])) {
            $query->where('periode', $filters['periode']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $driver = \DB::connection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('konteks_penilaian', $likeOperator, "%{$search}%")
                    ->orWhere('periode', $likeOperator, "%{$search}%")
                    ->orWhereHas('unit', fn ($uq) => $uq->where('nama', $likeOperator, "%{$search}%"))
                    ->orWhereHas('creator', fn ($cq) => $cq->where('name', $likeOperator, "%{$search}%"));
            });
        }

        $sessions = $query->orderByDesc('id')->get();

        return $sessions->map(function (ChecklistSession $session) {
            $total = (int) $session->total_entries;
            $verified = (int) $session->verified_entries;
            $compliant = (int) $session->compliant_entries;

            return [
                'id' => $session->id,
                'konteks_penilaian' => $session->konteks_penilaian,
                'periode' => $session->periode ?? '',
                'unit_id' => $session->unit_id,
                'unit_nama' => $session->unit?->nama ?? '',
                'framework_id' => $session->framework_id,
                'framework_nama' => $session->framework ? "{$session->framework->nama}:{$session->framework->versi}" : '',
                'creator_id' => $session->created_by,
                'creator_name' => $session->creator?->name ?? '',
                'total_entries' => $total,
                'compliant_entries' => $compliant,
                'partial_entries' => (int) $session->partial_entries,
                'non_compliant_entries' => (int) $session->non_compliant_entries,
                'na_entries' => (int) $session->na_entries,
                'verified_entries' => $verified,
                'compliance_percentage' => $total > 0 ? (int) round(($compliant / $total) * 100) : 0,
                'created_at' => $session->created_at,
                'updated_at' => $session->updated_at,
            ];
        })->toArray();
    }

    /**
     * Daftar periode unik (desc) dari seluruh session, untuk pilihan filter.
     */
    public function getSessionPeriodeOptions(): array
    {
        return ChecklistSession::query()
            ->whereNotNull('periode')
            ->where('periode', '<>', '')
            ->distinct()
            ->orderByDesc('periode')
            ->pluck('periode')
            ->values()
            ->toArray();
    }

    /**
     * Fetch controls ordered by id asc with framework and search/category filtering.
     */
    public function getControls(array $filters = [], int $perPage = 20): LengthAwarePaginator
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

        $perPage = max(1, min(100, $perPage));

        return $query->orderBy('id', 'asc')
            ->paginate($perPage)
            ->through(function (Control $ctrl) {
                return [
                    'id' => (string) $ctrl->id,
                    'framework_id' => $ctrl->framework_id,
                    'framework_nama' => $ctrl->framework ? "{$ctrl->framework->nama}:{$ctrl->framework->versi}" : '',
                    'code' => $ctrl->kode_klausul,
                    'title' => $ctrl->judul,
                    'description' => $ctrl->deskripsi ?? '',
                    'category' => $ctrl->kategori === 'annex_a' ? 'Annex A' : 'Klausul 4-10',
                ];
            });
    }
}
