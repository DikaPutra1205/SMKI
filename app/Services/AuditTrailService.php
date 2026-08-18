<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class AuditTrailService
{
    /**
     * Get paginated audit trail logs with filtering.
     */
    public function getAuditLogs(User $user, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        if (Gate::forUser($user)->denies('view-audit-logs')) {
            throw new AuthorizationException('Anda tidak memiliki wewenang untuk melihat jejak audit.');
        }

        $query = AuditLog::with(['actor.workUnit'])->orderByDesc('id');

        if (! empty($filters['action'])) {
            $query->where('aksi', $filters['action']);
        } elseif (! empty($filters['aksi'])) {
            $query->where('aksi', $filters['aksi']);
        }

        if (! empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (! empty($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', Carbon::parse($filters['start_date']));
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', Carbon::parse($filters['end_date']));
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('aksi', 'like', "%{$search}%")
                    ->orWhere('entity_type', 'like', "%{$search}%")
                    ->orWhereHas('actor', fn ($aq) => $aq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function (AuditLog $log) {
            return $this->formatAuditLogResource($log);
        });

        return $paginator;
    }

    /**
     * Get summary statistics of audit logs.
     */
    public function getAuditStats(User $user): array
    {
        if (Gate::forUser($user)->denies('view-audit-logs')) {
            throw new AuthorizationException('Anda tidak memiliki wewenang untuk melihat statistik jejak audit.');
        }

        $totalLogs = AuditLog::count();
        $sinceYesterday = AuditLog::where('created_at', '>=', now()->subDay())->count();

        $actionBreakdown = [
            'create' => AuditLog::where('aksi', 'create')->count(),
            'update' => AuditLog::where('aksi', 'update')->count(),
            'delete' => AuditLog::where('aksi', 'delete')->count(),
            'verify' => AuditLog::where('aksi', 'verify')->count(),
            'bulk_verify' => AuditLog::where('aksi', 'bulk_verify')->count(),
            'export' => AuditLog::where('aksi', 'export')->count(),
        ];

        $entityBreakdown = AuditLog::selectRaw('entity_type, COUNT(*) as count')
            ->groupBy('entity_type')
            ->pluck('count', 'entity_type')
            ->toArray();

        return [
            'total_logs' => $totalLogs,
            'last_24_hours' => $sinceYesterday,
            'by_action' => $actionBreakdown,
            'by_entity' => $entityBreakdown,
        ];
    }

    /**
     * Format raw audit log into clean English resource object.
     */
    public function formatAuditLogResource(AuditLog $log): array
    {
        $actor = $log->actor;

        return [
            'id' => $log->id,
            'actor' => [
                'id' => $actor?->id,
                'name' => $actor?->name ?? 'Sistem SMKI',
                'email' => $actor?->email,
                'role' => $actor?->role ?? 'system',
                'unit_name' => $actor?->workUnit?->nama ?? 'Semua Unit',
            ],
            'action' => $log->aksi,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'entity_label' => "{$log->entity_type} #{$log->entity_id}",
            'changes' => $log->detail_perubahan ?? [],
            'time_ago' => $log->created_at ? $log->created_at->diffForHumans() : 'baru saja',
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
