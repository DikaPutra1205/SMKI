import { EmptyState } from '@/components/ui/EmptyState';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import { SlideOver } from '@/components/ui/SlideOver';
import { StatusBadge, type StatusTone } from '@/components/ui/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { t, type TranslationKey } from '@/lib/i18n';
import { formatDateTimeIndonesian } from '@/lib/utils';
import { Head, router } from '@inertiajs/react';
import {
    Clock,
    Eye,
    History,
    Search,
    ShieldCheck,
    Users,
} from 'lucide-react';
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
    if (typeof value === 'object') return JSON.stringify(value, null, 2);
    return String(value);
}

function diffEntries(changes: Record<string, unknown> | null): Array<{ field: string; before: unknown; after: unknown }> {
    if (!changes) return [];
    const before = (changes.before ?? {}) as Record<string, unknown>;
    const after = (changes.after ?? {}) as Record<string, unknown>;
    const fields = Array.from(new Set([...Object.keys(before), ...Object.keys(after)]));
    return fields.map((field) => ({ field, before: before[field], after: after[field] }));
}

function summaryText(changes: Record<string, unknown> | null): string {
    const changed = diffEntries(changes).filter((entry) => fmtValue(entry.before) !== fmtValue(entry.after));
    if (changed.length === 0) return 'Tidak ada detail modifikasi langsung';
    return changed.map((entry) => `${entry.field}: ${fmtValue(entry.before)} → ${fmtValue(entry.after)}`).join('; ');
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
    const diff = detailTarget ? diffEntries(detailTarget.changes) : [];

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/dashboard' }, { label: t('audit.title') }];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath={getBasePath()}>
            <Head title={`${t('audit.title')} - Audit Trail SMKI`} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{t('audit.title')}</h1>
                    <p className="text-muted mt-1 text-xs sm:text-sm text-slate-500 dark:text-slate-400">{t('audit.subtitle')}</p>
                </div>
            </div>

            <div className="mb-6 grid grid-cols-2 gap-3.5 sm:grid-cols-4">
                <div
                    onClick={() => setSelectedAction('all')}
                    className={`cursor-pointer rounded-2xl border p-4 transition-all ${
                        selectedAction === 'all'
                            ? 'border-blue-500 bg-blue-50/50 shadow-sm dark:border-blue-500/60 dark:bg-blue-950/30'
                            : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900'
                    }`}
                >
                    <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                        <span className="text-xs font-semibold">{t('audit.totalLogs')}</span>
                        <History className="h-4 w-4 text-blue-600" />
                    </div>
                    <span className="text-2xl font-bold text-slate-900 dark:text-white">{stats?.total_logs ?? page.total}</span>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between text-emerald-600 dark:text-emerald-400 mb-1">
                        <span className="text-xs font-semibold">{t('audit.last24Hours')}</span>
                        <Clock className="h-4 w-4" />
                    </div>
                    <span className="text-2xl font-bold text-slate-900 dark:text-white">{stats?.last_24_hours ?? 0}</span>
                </div>

                <div
                    onClick={() => setSelectedAction('verify')}
                    className="cursor-pointer rounded-2xl border border-slate-200 bg-white p-4 hover:border-amber-300 transition-all dark:border-slate-800 dark:bg-slate-900"
                >
                    <div className="flex items-center justify-between text-amber-600 dark:text-amber-400 mb-1">
                        <span className="text-xs font-semibold">Verifikasi Selesai</span>
                        <ShieldCheck className="h-4 w-4" />
                    </div>
                    <span className="text-2xl font-bold text-slate-900 dark:text-white">
                        {(stats?.by_action?.verify ?? 0) + (stats?.by_action?.bulk_verify ?? 0)}
                    </span>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between text-purple-600 dark:text-purple-400 mb-1">
                        <span className="text-xs font-semibold">Pengguna Aktif</span>
                        <Users className="h-4 w-4" />
                    </div>
                    <span className="text-2xl font-bold text-slate-900 dark:text-white">{actors.length}</span>
                </div>
            </div>

            <div className="border border-slate-200 overflow-hidden rounded-2xl bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex flex-wrap items-center gap-3">
                    <div className="relative flex-1 min-w-[240px]">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder={t('audit.searchPlaceholder')}
                            className="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pr-3 pl-9 text-xs sm:text-sm text-slate-700 placeholder-slate-400 transition-colors focus:border-blue-400 focus:bg-white focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300 dark:focus:bg-slate-900"
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Select
                            value={selectedAction}
                            onChange={(e) => setSelectedAction(e.target.value)}
                            aria-label={t('audit.actionLabel')}
                        >
                            <option value="all">{t('audit.allActions')}</option>
                            {actionOptions.map((a) => (
                                <option key={a} value={a}>
                                    {actionLabel(a)}
                                </option>
                            ))}
                        </Select>

                        <Select
                            value={selectedEntity}
                            onChange={(e) => setSelectedEntity(e.target.value)}
                            aria-label={t('audit.entityLabel')}
                        >
                            <option value="all">{t('audit.allEntities')}</option>
                            {entityOptions.map((ent) => (
                                <option key={ent} value={ent}>
                                    {ent}
                                </option>
                            ))}
                        </Select>

                        <Select
                            value={selectedActor}
                            onChange={(e) => setSelectedActor(e.target.value)}
                            aria-label={t('audit.actorLabel')}
                        >
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
                            className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            title="Tanggal Mulai"
                        />
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
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
                                        className="hover:bg-slate-50/60 transition-colors dark:hover:bg-slate-800/40 cursor-pointer"
                                    >
                                        <td className="px-5 py-4 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
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
                                            <span className="font-semibold text-slate-900 dark:text-white block">{log.entity_label}</span>
                                            <span className="text-[11px] text-slate-400 dark:text-slate-500">{log.entity_type}</span>
                                        </td>
                                        <td className="px-5 py-4 whitespace-nowrap">
                                            <StatusBadge tone={actionTone(log.action)}>{actionLabel(log.action)}</StatusBadge>
                                        </td>
                                        <td className="px-5 py-4 text-xs text-slate-600 dark:text-slate-300 max-w-[280px] truncate">
                                            {summaryText(log.changes)}
                                        </td>
                                        <td className="px-5 py-4 text-right whitespace-nowrap" onClick={(e) => e.stopPropagation()}>
                                            <button
                                                type="button"
                                                onClick={() => setDetailTarget(log)}
                                                className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors"
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
                            <span className="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-3">
                                Pelaksana Tindakan (Actor)
                            </span>
                            <div className="flex items-center gap-3.5">
                                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-sm font-bold text-white shadow-sm">
                                    {getInitials(detailTarget.actor.name)}
                                </div>
                                <div>
                                    <div className="font-bold text-slate-900 dark:text-white">{detailTarget.actor.name}</div>
                                    <div className="text-xs text-slate-400">{detailTarget.actor.email || 'Tanpa Email'}</div>
                                    <div className="mt-1 flex items-center gap-2">
                                        <span className="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                            {roleLabel(detailTarget.actor.role)}
                                        </span>
                                        {detailTarget.actor.unit_name && (
                                            <span className="text-[11px] text-slate-500">&bull; {detailTarget.actor.unit_name}</span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-white p-4 space-y-3 dark:border-slate-800 dark:bg-slate-900">
                            <span className="text-xs font-bold text-slate-400 uppercase tracking-wider block">
                                Objek & Tipe Tindakan
                            </span>
                            <div className="flex items-center justify-between text-xs sm:text-sm pt-1">
                                <span className="text-slate-500">Tindakan</span>
                                <StatusBadge tone={actionTone(detailTarget.action)}>{actionLabel(detailTarget.action)}</StatusBadge>
                            </div>
                            <div className="border-t border-slate-100 dark:border-slate-800 pt-3 flex items-center justify-between text-xs sm:text-sm">
                                <span className="text-slate-500">Objek Target</span>
                                <span className="font-semibold text-slate-900 dark:text-white">{detailTarget.entity_label}</span>
                            </div>
                            <div className="border-t border-slate-100 dark:border-slate-800 pt-3 flex items-center justify-between text-xs sm:text-sm">
                                <span className="text-slate-500">Tipe Entitas / ID</span>
                                <span className="text-slate-600 dark:text-slate-400">
                                    {detailTarget.entity_type} (#{detailTarget.entity_id})
                                </span>
                            </div>
                        </div>

                        <div>
                            <span className="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-2">
                                Rekam Jejak Nilai Sebelum & Sesudah
                            </span>
                            {diff.length > 0 ? (
                                <div className="space-y-3">
                                    {diff.map((entry) => (
                                        <div
                                            key={entry.field}
                                            className="rounded-2xl border border-slate-200 bg-white p-3.5 space-y-2 dark:border-slate-800 dark:bg-slate-900"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">
                                                    {entry.field}
                                                </span>
                                            </div>
                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                                <div className="rounded-xl border border-rose-100 bg-rose-50/60 p-2.5 dark:border-rose-900/50 dark:bg-rose-950/20">
                                                    <span className="text-[10px] font-bold text-rose-500 uppercase block mb-1">
                                                        Sebelum (Old)
                                                    </span>
                                                    <span className="text-slate-700 dark:text-slate-300 line-through decoration-rose-400">
                                                        {fmtValue(entry.before)}
                                                    </span>
                                                </div>
                                                <div className="rounded-xl border border-emerald-100 bg-emerald-50/60 p-2.5 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                                                    <span className="text-[10px] font-bold text-emerald-600 uppercase block mb-1">
                                                        Sesudah (New)
                                                    </span>
                                                    <span className="text-slate-900 dark:text-white font-semibold">
                                                        {fmtValue(entry.after)}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-xl border border-slate-100 bg-slate-50 p-4 text-center text-xs text-slate-400 dark:border-slate-800 dark:bg-slate-900">
                                    Tidak ada data payload perubahan sebelum / sesudah yang tersimpan.
                                </div>
                            )}
                        </div>

                        <div className="rounded-2xl border border-sky-100 bg-sky-50/70 p-3.5 flex items-center gap-3 text-xs text-sky-800 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-300">
                            <ShieldCheck className="h-5 w-5 shrink-0 text-sky-600 dark:text-sky-400" />
                            <span>Rekam jejak audit trail bersifat permanen (immutable) dan tidak dapat dimodifikasi atau dihapus.</span>
                        </div>
                    </div>
                )}
            </SlideOver>
        </AppLayout>
    );
}
