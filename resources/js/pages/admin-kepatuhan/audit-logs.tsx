import { EmptyState } from '@/components/ui/EmptyState';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import { SlideOver } from '@/components/ui/SlideOver';
import { StatusBadge, type StatusTone } from '@/components/ui/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { t, type TranslationKey } from '@/lib/i18n';
import { formatDateTimeIndonesian } from '@/lib/utils';
import { Head, router } from '@inertiajs/react';
import { Clock, Eye, History, Search, ShieldCheck, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface AuditActor {
    id: number | null;
    name: string;
    email: string | null;
    role: string | null;
    unit_name: string;
}

interface AuditLogResource {
    id: number;
    actor: AuditActor;
    action: string;
    entity_type: string;
    entity_id: number;
    entity_label: string;
    changes: Record<string, unknown> | null;
    created_at: string | null;
}

interface AuditStats {
    total_logs: number;
    last_24_hours: number;
    by_action: Record<string, number>;
    by_entity: Record<string, number>;
}

interface ActorItem {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface AuditLogsProps {
    logs?: Paginator<AuditLogResource>;
    stats?: AuditStats;
    filters?: {
        action?: string;
        aksi?: string;
        entity_type?: string;
        actor_id?: string;
        start_date?: string;
        end_date?: string;
        search?: string;
    };
    actors?: ActorItem[];
}

const FALLBACK_ACTIONS = ['create', 'update', 'delete', 'verify', 'bulk_verify', 'export', 'upload'];

const ACTION_TONE: Record<string, StatusTone> = {
    create: 'green',
    update: 'blue',
    delete: 'red',
    verify: 'amber',
    bulk_verify: 'violet',
    export: 'gray',
    upload: 'blue',
};

const KNOWN_ENTITIES = [
    'Framework',
    'Control',
    'User',
    'Role',
    'WorkUnit',
    'ChecklistSession',
    'ChecklistEntry',
    'ComplianceEvidence',
    'Finding',
    'Risk',
];

const KNOWN_ROLES = ['superadmin', 'admin_kepatuhan', 'koordinator_smki', 'auditor', 'pic', 'system'] as const;

function actionLabel(action: string): string {
    if (action in ACTION_TONE) {
        return t(`audit.action.${action}` as TranslationKey);
    }
    return action.toUpperCase();
}

function actionTone(action: string): StatusTone {
    return ACTION_TONE[action] ?? 'gray';
}

function roleLabel(role: string | null): string {
    if (!role) return '';
    return (KNOWN_ROLES as readonly string[]).includes(role) ? t(`role.${role}` as TranslationKey) : role;
}

function getInitials(name: string): string {
    if (!name) return 'U';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
}

function fmtValue(value: unknown): string {
    if (value === null || value === undefined) return 'Kosong / Null';
    if (typeof value === 'boolean') return value ? 'Ya / Benar' : 'Tidak / Salah';
    if (Array.isArray(value)) {
        if (value.length === 0) return '[]';
        if (value.every((v) => typeof v === 'number' || typeof v === 'string')) {
            return `[${value.join(', ')}]`;
        }
        return JSON.stringify(value);
    }
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}

function isPlainObject(val: unknown): val is Record<string, unknown> {
    return typeof val === 'object' && val !== null && !Array.isArray(val);
}

function getSnapshotData(changes: Record<string, unknown> | null): Record<string, unknown> | null {
    if (!changes) return null;
    if (isPlainObject(changes.data)) return changes.data;
    if (isPlainObject(changes.attributes)) return changes.attributes;
    if (isPlainObject(changes.after) && !changes.before) return changes.after;
    return null;
}

function isDiffChanges(changes: Record<string, unknown> | null): boolean {
    if (!changes) return false;
    return Boolean(changes.before || changes.after);
}

function diffEntries(changes: Record<string, unknown> | null): Array<{ field: string; before: unknown; after: unknown }> {
    if (!changes) return [];
    const before = (changes.before ?? {}) as Record<string, unknown>;
    const after = (changes.after ?? {}) as Record<string, unknown>;
    const fields = Array.from(new Set([...Object.keys(before), ...Object.keys(after)]));
    return fields.map((field) => ({ field, before: before[field], after: after[field] }));
}

function formatSnapshotSummary(data: Record<string, unknown>): string {
    const keys = Object.keys(data);
    if (keys.length === 0) return '';

    const excludedKeys = new Set(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes']);
    let candidateKeys = keys.filter((k) => !excludedKeys.has(k));

    const meaningfulKeys = candidateKeys.filter((k) => !['created_at', 'updated_at', 'deleted_at', 'id'].includes(k));
    if (meaningfulKeys.length > 0) {
        candidateKeys = meaningfulKeys;
    }

    if (candidateKeys.length === 0) {
        candidateKeys = keys.filter((k) => !excludedKeys.has(k));
    }

    return candidateKeys.map((k) => `${k}: ${fmtValue(data[k])}`).join('; ');
}

function formatGenericPayload(changes: Record<string, unknown>): string {
    const entries: string[] = [];
    for (const [k, v] of Object.entries(changes)) {
        if (k === 'data' || k === 'attributes' || k === 'before' || k === 'after') continue;
        entries.push(`${k}: ${fmtValue(v)}`);
    }
    return entries.join('; ');
}

function summaryText(action: string, changes: Record<string, unknown> | null, entityLabel?: string): string {
    if (!changes || Object.keys(changes).length === 0) {
        if (action === 'delete') return 'Entitas dihapus dari sistem';
        if (action === 'create') return `Membuat ${entityLabel || 'entitas baru'}`;
        if (action === 'verify') return 'Verifikasi diselesaikan';
        if (action === 'bulk_verify') return 'Verifikasi massal selesai';
        if (action === 'export') return 'Ekspor laporan';
        return 'Tidak ada detail modifikasi langsung';
    }

    if (isDiffChanges(changes)) {
        const changed = diffEntries(changes).filter((entry) => fmtValue(entry.before) !== fmtValue(entry.after));
        if (changed.length > 0) {
            return changed.map((entry) => `${entry.field}: ${fmtValue(entry.before)} → ${fmtValue(entry.after)}`).join('; ');
        }
    }

    const snapshot = getSnapshotData(changes);
    if (snapshot) {
        const summary = formatSnapshotSummary(snapshot);
        if (summary) {
            return summary;
        }
    }

    const genericSummary = formatGenericPayload(changes);
    if (genericSummary) {
        return genericSummary;
    }

    if (action === 'delete') return 'Entitas dihapus dari sistem';
    if (action === 'create') return `Membuat ${entityLabel || 'entitas baru'}`;
    return 'Tidak ada detail modifikasi langsung';
}

function buildFilterParams(
    searchQuery: string,
    selectedAction: string,
    selectedEntity: string,
    selectedActor: string,
    dateFrom: string,
    dateTo: string,
) {
    return {
        search: searchQuery || undefined,
        action: selectedAction !== 'all' ? selectedAction : undefined,
        entity_type: selectedEntity !== 'all' ? selectedEntity : undefined,
        actor_id: selectedActor !== 'all' ? selectedActor : undefined,
        start_date: dateFrom || undefined,
        end_date: dateTo || undefined,
    };
}

export default function AuditLogs({ logs, stats, filters = {}, actors = [] }: AuditLogsProps) {
    const page = logs ?? { data: [], current_page: 1, last_page: 1, per_page: 25, total: 0, from: null, to: null };
    const items = page.data;

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedAction, setSelectedAction] = useState<string>(filters.action || filters.aksi || 'all');
    const [selectedEntity, setSelectedEntity] = useState<string>(filters.entity_type || 'all');
    const [selectedActor, setSelectedActor] = useState<string>(filters.actor_id || 'all');
    const [dateFrom, setDateFrom] = useState(filters.start_date || '');
    const [dateTo, setDateTo] = useState(filters.end_date || '');
    const [detailTarget, setDetailTarget] = useState<AuditLogResource | null>(null);
    const isFirstRender = useRef(true);

    const getBasePath = () => (typeof window !== 'undefined' ? window.location.pathname : '/audit-logs');

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(getBasePath(), buildFilterParams(searchQuery, selectedAction, selectedEntity, selectedActor, dateFrom, dateTo), {
                preserveState: true,
                replace: true,
            });
        }, 350);

        return () => clearTimeout(timer);
    }, [searchQuery, selectedAction, selectedEntity, selectedActor, dateFrom, dateTo]);

    const actionOptions = Array.from(new Set([...(stats?.by_action ? Object.keys(stats.by_action) : []), ...FALLBACK_ACTIONS]));
    const entityOptions = Array.from(new Set([...KNOWN_ENTITIES, ...(stats?.by_entity ? Object.keys(stats.by_entity) : [])]));
    const diff = detailTarget && isDiffChanges(detailTarget.changes) ? diffEntries(detailTarget.changes) : [];
    const snapshotData = detailTarget ? getSnapshotData(detailTarget.changes) : null;
    const genericPayload =
        detailTarget && !isDiffChanges(detailTarget.changes) && !snapshotData && detailTarget.changes && Object.keys(detailTarget.changes).length > 0
            ? detailTarget.changes
            : null;

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/dashboard' }, { label: t('audit.title') }];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath={getBasePath()}>
            <Head title={`${t('audit.title')} - Audit Trail SMKI`} />

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{t('audit.title')}</h1>
                    <p className="text-muted mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">{t('audit.subtitle')}</p>
                </div>
            </div>

            <div className="mb-6 grid grid-cols-2 gap-3.5 sm:grid-cols-4">
                <div
                    onClick={() => setSelectedAction('all')}
                    className={`cursor-pointer rounded-2xl border p-4 transition-all ${
                        selectedAction === 'all'
                            ? 'border-primary bg-primary-50/50 dark:border-primary/60 dark:bg-navy-900/30 shadow-sm'
                            : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900'
                    }`}
                >
                    <div className="mb-1 flex items-center justify-between text-slate-500 dark:text-slate-400">
                        <span className="text-xs font-semibold">{t('audit.totalLogs')}</span>
                        <History className="text-primary h-4 w-4" />
                    </div>
                    <span className="text-2xl font-bold text-slate-900 dark:text-white">{stats?.total_logs ?? page.total}</span>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div className="mb-1 flex items-center justify-between text-emerald-600 dark:text-emerald-400">
                        <span className="text-xs font-semibold">{t('audit.last24Hours')}</span>
                        <Clock className="h-4 w-4" />
                    </div>
                    <span className="text-2xl font-bold text-slate-900 dark:text-white">{stats?.last_24_hours ?? 0}</span>
                </div>

                <div
                    onClick={() => setSelectedAction('verify')}
                    className="cursor-pointer rounded-2xl border border-slate-200 bg-white p-4 transition-all hover:border-amber-300 dark:border-slate-800 dark:bg-slate-900"
                >
                    <div className="mb-1 flex items-center justify-between text-amber-600 dark:text-amber-400">
                        <span className="text-xs font-semibold">Verifikasi Selesai</span>
                        <ShieldCheck className="h-4 w-4" />
                    </div>
                    <span className="text-2xl font-bold text-slate-900 dark:text-white">
                        {(stats?.by_action?.verify ?? 0) + (stats?.by_action?.bulk_verify ?? 0)}
                    </span>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div className="text-primary-800 dark:text-primary-300 mb-1 flex items-center justify-between">
                        <span className="text-xs font-semibold">Pengguna Aktif</span>
                        <Users className="h-4 w-4" />
                    </div>
                    <span className="text-2xl font-bold text-slate-900 dark:text-white">{actors.length}</span>
                </div>
            </div>

            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div className="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
                    <div className="relative min-w-[240px] flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder={t('audit.searchPlaceholder')}
                            className="focus:border-primary focus:ring-primary w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pr-3 pl-9 text-xs text-slate-700 placeholder-slate-400 transition-colors focus:bg-white focus:ring-1 sm:text-sm dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300 dark:focus:bg-slate-900"
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Select value={selectedAction} onChange={(e) => setSelectedAction(e.target.value)} aria-label={t('audit.actionLabel')}>
                            <option value="all">{t('audit.allActions')}</option>
                            {actionOptions.map((a) => (
                                <option key={a} value={a}>
                                    {actionLabel(a)}
                                </option>
                            ))}
                        </Select>

                        <Select value={selectedEntity} onChange={(e) => setSelectedEntity(e.target.value)} aria-label={t('audit.entityLabel')}>
                            <option value="all">{t('audit.allEntities')}</option>
                            {entityOptions.map((ent) => (
                                <option key={ent} value={ent}>
                                    {ent}
                                </option>
                            ))}
                        </Select>

                        <Select value={selectedActor} onChange={(e) => setSelectedActor(e.target.value)} aria-label={t('audit.actorLabel')}>
                            <option value="all">{t('audit.allActors')}</option>
                            {actors.map((actor) => (
                                <option key={actor.id} value={String(actor.id)}>
                                    {actor.name}
                                </option>
                            ))}
                        </Select>

                        <input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="focus:border-primary focus:ring-primary rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:ring-1 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            title="Tanggal Mulai"
                        />
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="focus:border-primary focus:ring-primary rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:ring-1 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            title="Tanggal Selesai"
                        />
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs sm:text-sm">
                        <thead className="border-b border-slate-200 bg-slate-50/80 text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th scope="col" className="px-5 py-3.5 font-semibold">
                                    {t('audit.timeLabel')}
                                </th>
                                <th scope="col" className="px-5 py-3.5 font-semibold">
                                    {t('audit.actorLabel')}
                                </th>
                                <th scope="col" className="px-5 py-3.5 font-semibold">
                                    {t('audit.entityLabel')}
                                </th>
                                <th scope="col" className="px-5 py-3.5 font-semibold">
                                    {t('audit.actionLabel')}
                                </th>
                                <th scope="col" className="px-5 py-3.5 font-semibold">
                                    Ringkasan Perubahan
                                </th>
                                <th scope="col" className="px-5 py-3.5 text-right font-semibold">
                                    {t('common.actions')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {items.length > 0 ? (
                                items.map((log) => (
                                    <tr
                                        key={log.id}
                                        onClick={() => setDetailTarget(log)}
                                        className="cursor-pointer transition-colors hover:bg-slate-50/60 dark:hover:bg-slate-800/40"
                                    >
                                        <td className="px-5 py-4 text-xs whitespace-nowrap text-slate-500 dark:text-slate-400">
                                            {formatDateTimeIndonesian(log.created_at)}
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="font-semibold text-slate-900 dark:text-white">{log.actor.name}</div>
                                            {log.actor.role && (
                                                <div className="text-[11px] text-slate-400 dark:text-slate-500">
                                                    {roleLabel(log.actor.role)}
                                                    {log.actor.unit_name && log.actor.unit_name !== 'Semua Unit' ? ` • ${log.actor.unit_name}` : ''}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-5 py-4">
                                            <span className="block font-semibold text-slate-900 dark:text-white">{log.entity_label}</span>
                                            <span className="text-[11px] text-slate-400 dark:text-slate-500">{log.entity_type}</span>
                                        </td>
                                        <td className="px-5 py-4 whitespace-nowrap">
                                            <StatusBadge tone={actionTone(log.action)}>{actionLabel(log.action)}</StatusBadge>
                                        </td>
                                        <td
                                            className="max-w-[320px] truncate px-5 py-4 text-xs text-slate-600 dark:text-slate-300"
                                            title={summaryText(log.action, log.changes, log.entity_label)}
                                        >
                                            {summaryText(log.action, log.changes, log.entity_label)}
                                        </td>
                                        <td className="px-5 py-4 text-right whitespace-nowrap" onClick={(e) => e.stopPropagation()}>
                                            <button
                                                type="button"
                                                onClick={() => setDetailTarget(log)}
                                                className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                                            >
                                                <Eye className="h-3 w-3" />
                                                {t('audit.detail')}
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={6}>
                                        <EmptyState message={t('audit.noLogs')} />
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination
                    currentPage={page.current_page}
                    totalPages={page.last_page}
                    perPage={page.per_page}
                    totalItems={page.total}
                    startIndex={(page.from ?? 1) - 1}
                    endIndex={page.to ?? page.total}
                    onPageChange={(p) => {
                        router.get(
                            getBasePath(),
                            { ...buildFilterParams(searchQuery, selectedAction, selectedEntity, selectedActor, dateFrom, dateTo), page: p },
                            { preserveState: true, replace: true },
                        );
                    }}
                />
            </div>

            <SlideOver
                open={detailTarget !== null}
                onClose={() => setDetailTarget(null)}
                title={detailTarget ? `Aktivitas #${detailTarget.id} — ${actionLabel(detailTarget.action)}` : 'Detail Aktivitas'}
                subtitle={detailTarget?.created_at ? formatDateTimeIndonesian(detailTarget.created_at) : 'Rekam Jejak Sistem'}
                width="max-w-xl"
                footer={
                    <button
                        type="button"
                        onClick={() => setDetailTarget(null)}
                        className="rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-slate-800 dark:bg-white dark:text-slate-900"
                    >
                        {t('audit.close')}
                    </button>
                }
            >
                {detailTarget && (
                    <div className="space-y-6">
                        <div className="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-800/40">
                            <span className="mb-3 block text-xs font-bold tracking-wider text-slate-400 uppercase">Pelaksana Tindakan (Actor)</span>
                            <div className="flex items-center gap-3.5">
                                <div className="bg-primary flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-sm font-bold text-white shadow-sm">
                                    {getInitials(detailTarget.actor.name)}
                                </div>
                                <div>
                                    <div className="font-bold text-slate-900 dark:text-white">{detailTarget.actor.name}</div>
                                    <div className="text-xs text-slate-400">{detailTarget.actor.email || 'Tanpa Email'}</div>
                                    <div className="mt-1 flex items-center gap-2">
                                        <span className="bg-primary-50 text-primary-700 dark:bg-navy-900 dark:text-primary-200 inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold">
                                            {roleLabel(detailTarget.actor.role)}
                                        </span>
                                        {detailTarget.actor.unit_name && (
                                            <span className="text-[11px] text-slate-500">&bull; {detailTarget.actor.unit_name}</span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <span className="block text-xs font-bold tracking-wider text-slate-400 uppercase">Objek & Tipe Tindakan</span>
                            <div className="flex items-center justify-between pt-1 text-xs sm:text-sm">
                                <span className="text-slate-500">Tindakan</span>
                                <StatusBadge tone={actionTone(detailTarget.action)}>{actionLabel(detailTarget.action)}</StatusBadge>
                            </div>
                            <div className="flex items-center justify-between border-t border-slate-100 pt-3 text-xs sm:text-sm dark:border-slate-800">
                                <span className="text-slate-500">Objek Target</span>
                                <span className="font-semibold text-slate-900 dark:text-white">{detailTarget.entity_label}</span>
                            </div>
                            <div className="flex items-center justify-between border-t border-slate-100 pt-3 text-xs sm:text-sm dark:border-slate-800">
                                <span className="text-slate-500">Tipe Entitas / ID</span>
                                <span className="text-slate-600 dark:text-slate-400">
                                    {detailTarget.entity_type} (#{detailTarget.entity_id})
                                </span>
                            </div>
                        </div>

                        <div>
                            <span className="mb-2 block text-xs font-bold tracking-wider text-slate-400 uppercase">
                                {isDiffChanges(detailTarget.changes)
                                    ? 'Rekam Jejak Nilai Sebelum & Sesudah'
                                    : snapshotData
                                      ? detailTarget.action === 'delete'
                                          ? 'Data Entitas Sebelum Dihapus'
                                          : 'Data Atribut Entitas yang Dibuat'
                                      : 'Detail & Parameter Aktivitas'}
                            </span>
                            {diff.length > 0 ? (
                                <div className="space-y-3">
                                    {diff.map((entry) => (
                                        <div
                                            key={entry.field}
                                            className="space-y-2 rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="text-primary dark:text-primary-200 font-mono text-xs font-bold">{entry.field}</span>
                                            </div>
                                            <div className="grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                                                <div className="rounded-xl border border-rose-100 bg-rose-50/60 p-2.5 dark:border-rose-900/50 dark:bg-rose-950/20">
                                                    <span className="mb-1 block text-[10px] font-bold text-rose-500 uppercase">Sebelum (Old)</span>
                                                    <span className="text-slate-700 line-through decoration-rose-400 dark:text-slate-300">
                                                        {fmtValue(entry.before)}
                                                    </span>
                                                </div>
                                                <div className="rounded-xl border border-emerald-100 bg-emerald-50/60 p-2.5 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                                                    <span className="mb-1 block text-[10px] font-bold text-emerald-600 uppercase">Sesudah (New)</span>
                                                    <span className="font-semibold text-slate-900 dark:text-white">{fmtValue(entry.after)}</span>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : snapshotData && Object.keys(snapshotData).length > 0 ? (
                                <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                    {Object.entries(snapshotData).map(([key, val]) => (
                                        <div
                                            key={key}
                                            className="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xs transition-colors dark:border-slate-800 dark:bg-slate-900"
                                        >
                                            <span className="text-primary dark:text-primary-300 mb-1 block font-mono text-[11px] font-bold">
                                                {key}
                                            </span>
                                            <span className="block text-xs font-semibold break-words text-slate-900 dark:text-white">
                                                {fmtValue(val)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            ) : genericPayload && Object.keys(genericPayload).length > 0 ? (
                                <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                    {Object.entries(genericPayload).map(([key, val]) => (
                                        <div
                                            key={key}
                                            className="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xs transition-colors dark:border-slate-800 dark:bg-slate-900"
                                        >
                                            <span className="text-primary dark:text-primary-300 mb-1 block font-mono text-[11px] font-bold">
                                                {key}
                                            </span>
                                            <span className="block text-xs font-semibold break-words text-slate-900 dark:text-white">
                                                {fmtValue(val)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-xl border border-slate-100 bg-slate-50 p-4 text-center text-xs text-slate-400 dark:border-slate-800 dark:bg-slate-900">
                                    {detailTarget.action === 'delete'
                                        ? 'Entitas berhasil dihapus dari sistem.'
                                        : 'Tidak ada data payload perubahan tambahan yang tersimpan.'}
                                </div>
                            )}
                        </div>

                        <div className="flex items-center gap-3 rounded-2xl border border-sky-100 bg-sky-50/70 p-3.5 text-xs text-sky-800 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-300">
                            <ShieldCheck className="h-5 w-5 shrink-0 text-sky-600 dark:text-sky-400" />
                            <span>Rekam jejak audit trail bersifat permanen (immutable) dan tidak dapat dimodifikasi atau dihapus.</span>
                        </div>
                    </div>
                )}
            </SlideOver>
        </AppLayout>
    );
}
