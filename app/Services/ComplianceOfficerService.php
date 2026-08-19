<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ChecklistEntry;
use App\Models\Finding;
use App\Models\Risk;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ComplianceOfficerService
{
    /**
     * Resolve effective unit ID based on role scoping.
     * PIC is strictly scoped to their assigned unit.
     */
    public function resolveScopedUnitId(User $user, ?int $requestedUnitId = null): ?int
    {
        if ($user->isPic()) {
            return $user->unit_id ? (int) $user->unit_id : null;
        }

        return $requestedUnitId;
    }

    /**
     * Get paginated findings list with SLA and overdue calculations.
     */
    public function getFindings(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $scopedUnitId = $this->resolveScopedUnitId($user, $filters['unit_id'] ?? null);

        $query = Finding::with(['control.framework', 'unit:id,nama', 'pic:id,name', 'admin:id,name'])
            ->orderByRaw("CASE WHEN status = 'closed' THEN 1 ELSE 0 END")
            ->orderBy('deadline', 'asc');

        if ($scopedUnitId) {
            $query->where('unit_id', $scopedUnitId);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $category = $filters['category'] ?? ($filters['kategori'] ?? null);
        if (! empty($category)) {
            $query->where('kategori', $category);
        }

        $today = Carbon::today();

        if (isset($filters['is_overdue'])) {
            $isOverdue = filter_var($filters['is_overdue'], FILTER_VALIDATE_BOOLEAN);
            if ($isOverdue) {
                $query->where('status', '!=', Finding::STATUS_CLOSED)
                    ->whereNotNull('deadline')
                    ->where('deadline', '<', $today);
            } else {
                $query->where(function ($q) use ($today) {
                    $q->where('status', Finding::STATUS_CLOSED)
                        ->orWhereNull('deadline')
                        ->orWhere('deadline', '>=', $today);
                });
            }
        }

        if (! empty($filters['search'])) {
            $search = mb_strtolower(trim($filters['search']));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(catatan_admin) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('control', fn ($cq) => $cq->whereRaw('LOWER(kode_klausul) LIKE ?', ["%{$search}%"])->orWhereRaw('LOWER(judul) LIKE ?', ["%{$search}%"]));
            });
        }

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function (Finding $finding) use ($today) {
            return $this->formatFindingResource($finding, $today);
        });

        return $paginator;
    }

    /**
     * Get single finding detail.
     */
    public function getFinding(User $user, int $id): Finding
    {
        $finding = Finding::with(['control.framework', 'unit', 'pic', 'admin'])->findOrFail($id);

        if ($user->isPic() && $finding->unit_id !== $user->unit_id) {
            throw new AuthorizationException('Anda tidak memiliki hak akses untuk temuan unit lain.');
        }

        return $this->formatFindingResource($finding, Carbon::today());
    }

    /**
     * Update finding details (status, category, deadline, admin notes).
     */
    public function updateFinding(User $user, Finding $finding, array $data): Finding
    {
        if ($user->isPic() && $finding->unit_id !== $user->unit_id) {
            throw new AuthorizationException('Anda tidak memiliki wewenang untuk mengubah temuan unit lain.');
        }

        $oldValues = $finding->only(['status', 'kategori', 'deadline', 'catatan_admin', 'admin_id', 'tanggal_verifikasi']);

        $updateData = [];

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
            if ($data['status'] === Finding::STATUS_CLOSED) {
                $updateData['tanggal_verifikasi'] = now();
                $updateData['admin_id'] = $user->id;
            }
        }

        if (isset($data['category'])) {
            $updateData['kategori'] = $data['category'];
        } elseif (isset($data['kategori'])) {
            $updateData['kategori'] = $data['kategori'];
        }

        if (array_key_exists('deadline', $data)) {
            $updateData['deadline'] = $data['deadline'];
        }

        if (array_key_exists('admin_notes', $data)) {
            $updateData['catatan_admin'] = $data['admin_notes'];
        } elseif (array_key_exists('catatan_admin', $data)) {
            $updateData['catatan_admin'] = $data['catatan_admin'];
        }

        $finding->update($updateData);
        $freshFinding = $finding->fresh(['control.framework', 'unit', 'pic', 'admin']);

        return $this->formatFindingResource($freshFinding, Carbon::today());
    }

    /**
     * Get paginated risks register list.
     */
    public function getRisks(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $scopedUnitId = $this->resolveScopedUnitId($user, $filters['unit_id'] ?? null);

        $query = Risk::with(['control.framework'])->orderByDesc('id');

        if ($scopedUnitId) {
            $query->whereHas('control.checklistEntries', fn ($q) => $q->where('unit_id', $scopedUnitId));
        }

        $riskLevel = $filters['risk_level'] ?? ($filters['level_risiko'] ?? null);
        if (! empty($riskLevel)) {
            $query->where('level_risiko', $riskLevel);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = mb_strtolower(trim($filters['search']));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(pemilik_risiko) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(rencana_mitigasi) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('control', fn ($cq) => $cq->whereRaw('LOWER(kode_klausul) LIKE ?', ["%{$search}%"])->orWhereRaw('LOWER(judul) LIKE ?', ["%{$search}%"]));
            });
        }

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function (Risk $risk) {
            return $this->formatRiskResource($risk);
        });

        return $paginator;
    }

    /**
     * Get risk matrix scoring distribution.
     */
    public function getRiskMatrix(User $user): array
    {
        $scopedUnitId = $this->resolveScopedUnitId($user);

        $query = Risk::query();
        if ($scopedUnitId) {
            $query->whereHas('control.checklistEntries', fn ($q) => $q->where('unit_id', $scopedUnitId));
        }

        $risks = $query->get();

        $byLevel = [
            'critical' => $risks->where('level_risiko', Risk::LEVEL_CRITICAL)->count(),
            'high' => $risks->where('level_risiko', Risk::LEVEL_HIGH)->count(),
            'medium' => $risks->where('level_risiko', Risk::LEVEL_MEDIUM)->count(),
            'low' => $risks->where('level_risiko', Risk::LEVEL_LOW)->count(),
        ];

        $byStatus = [
            'open' => $risks->where('status', Risk::STATUS_OPEN)->count(),
            'mitigated' => $risks->where('status', Risk::STATUS_MITIGATED)->count(),
            'accepted' => $risks->where('status', Risk::STATUS_ACCEPTED)->count(),
        ];

        return [
            'total_risks' => $risks->count(),
            'by_level' => $byLevel,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Get single risk detail.
     */
    public function getRisk(User $user, int $id): Risk
    {
        $risk = Risk::with(['control.framework'])->findOrFail($id);

        return $this->formatRiskResource($risk);
    }

    /**
     * Update risk mitigation plan and status.
     */
    public function updateRisk(User $user, Risk $risk, array $data): Risk
    {
        $oldValues = $risk->only(['level_risiko', 'pemilik_risiko', 'rencana_mitigasi', 'status']);

        $updateData = [];

        if (isset($data['risk_level'])) {
            $updateData['level_risiko'] = $data['risk_level'];
        } elseif (isset($data['level_risiko'])) {
            $updateData['level_risiko'] = $data['level_risiko'];
        }

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        if (array_key_exists('mitigation_plan', $data)) {
            $updateData['rencana_mitigasi'] = $data['mitigation_plan'];
        } elseif (array_key_exists('rencana_mitigasi', $data)) {
            $updateData['rencana_mitigasi'] = $data['rencana_mitigasi'];
        }

        if (isset($data['risk_owner'])) {
            $updateData['pemilik_risiko'] = $data['risk_owner'];
        } elseif (isset($data['pemilik_risiko'])) {
            $updateData['pemilik_risiko'] = $data['pemilik_risiko'];
        }

        $risk->update($updateData);
        $freshRisk = $risk->fresh(['control.framework']);

        return $this->formatRiskResource($freshRisk);
    }

    /**
     * Bulk verify checklist entries by compliance officer or superadmin.
     */
    public function bulkVerifyChecklistEntries(User $user, array $entryIds, string $status, ?string $adminNotes = null): int
    {
        if (! $user->hasPermissionTo('checklist.bulk-verify')) {
            throw new AuthorizationException('Hanya Admin Kepatuhan dan Superadmin yang memiliki wewenang verifikasi massal.');
        }

        return DB::transaction(function () use ($user, $entryIds, $status, $adminNotes) {
            $updatePayload = [
                'status' => $status,
                'tanggal_verifikasi' => now(),
                'admin_id' => $user->id,
            ];

            if ($adminNotes !== null) {
                $updatePayload['catatan_admin'] = $adminNotes;
            }

            $updatedCount = ChecklistEntry::whereIn('id', $entryIds)->update($updatePayload);

            AuditLog::catat(
                'ChecklistEntry',
                0,
                'bulk_verify',
                $user->id,
                [
                    'count' => $updatedCount,
                    'status' => $status,
                    'admin_notes' => $adminNotes,
                    'entry_ids' => $entryIds,
                ]
            );

            return $updatedCount;
        });
    }

    /**
     * Format finding model into consistent English resource representation.
     */
    protected function formatFindingResource(Finding $finding, Carbon $today): Finding
    {
        $deadline = $finding->deadline ? Carbon::parse($finding->deadline) : null;
        $isOverdue = false;
        $daysRemaining = null;

        if ($deadline) {
            $isOverdue = $finding->status !== Finding::STATUS_CLOSED && $deadline->isBefore($today);
            $daysRemaining = $today->diffInDays($deadline, false);
        }

        $finding->setAttribute('is_overdue', $isOverdue);
        $finding->setAttribute('days_remaining', $daysRemaining);
        $finding->setAttribute('category', $finding->kategori);
        $finding->setAttribute('admin_notes', $finding->catatan_admin);
        $finding->setAttribute('verified_at', $finding->tanggal_verifikasi);

        return $finding;
    }

    /**
     * Format risk model into consistent English resource representation.
     */
    protected function formatRiskResource(Risk $risk): Risk
    {
        $risk->setAttribute('risk_level', $risk->level_risiko);
        $risk->setAttribute('risk_owner', $risk->pemilik_risiko);
        $risk->setAttribute('mitigation_plan', $risk->rencana_mitigasi);

        return $risk;
    }
}
