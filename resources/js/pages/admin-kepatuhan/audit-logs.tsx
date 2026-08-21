import { EmptyState } from '@/components/ui/EmptyState';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import { StatusBadge, type StatusTone } from '@/components/ui/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { t, type TranslationKey } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';
import { Search, ShieldCheck } from 'lucide-react';
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
    time_ago: string;
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

function fmtDateTime(iso: string | null, withYear: boolean): string {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '—';
    const date = d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        ...(withYear ? { year: 'numeric' as const } : {}),
    });
    const time = d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    return `${date}, ${time}`;
}

function fmtValue(value: unknown): string {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'boolean') return value ? 'true' : 'false';
    if (typeof value === 'object') return JSON.stringify(value);
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
    if (changed.length === 0) return '—';
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

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                '/admin/kepatuhan/audit-logs',
                buildFilterParams(searchQuery, selectedAction, selectedEntity, selectedActor, dateFrom, dateTo),
                {
                    preserveState: true,
                    replace: true,
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [searchQuery, selectedAction, selectedEntity, selectedActor, dateFrom, dateTo]);

    const actionOptions = Array.from(new Set([...(stats?.by_action ? Object.keys(stats.by_action) : []), ...FALLBACK_ACTIONS]));
    const entityOptions = Array.from(new Set([...KNOWN_ENTITIES, ...(stats?.by_entity ? Object.keys(stats.by_entity) : [])]));
    const diff = detailTarget ? diffEntries(detailTarget.changes) : [];

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/admin/kepatuhan/dashboard' }, { label: t('audit.title') }];

    return (
        <>
            <Head title={`${t('audit.title')} - Admin Kepatuhan`} />
            <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/kepatuhan/audit-logs">
                <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                    <div>
                        <h1 className="text-navy dark:text-white text-2xl font-bold tracking-tight">{t('audit.title')}</h1>
                        <p className="text-muted dark:text-slate-400 mt-1 text-sm">{t('audit.subtitle')}</p>
                    </div>

                    <section className="border-border dark:border-slate-700 rounded-[14px] border bg-white dark:bg-slate-900 shadow-sm">
                        <div className="border-border dark:border-slate-700 border-b px-5 py-4">
                            <h3 className="text-navy dark:text-white text-[15px] font-bold">{t('audit.cardTitle')}</h3>
                        </div>

                        <div className="border-border dark:border-slate-700 flex flex-wrap items-center gap-3 border-b px-5 py-4">
                            <div className="relative min-w-[220px] flex-1">
                                <Search className="text-muted dark:text-slate-400 pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                <input
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder={t('audit.searchPlaceholder')}
                                    className="border-border-strong dark:border-slate-600 text-ink dark:text-white focus:border-primary focus:ring-primary/20 h-10 w-full rounded-[10px] border bg-white dark:bg-slate-900 pr-3 pl-9 text-sm focus:ring-2 focus:outline-none"
                                />
                            </div>
                            <Select value={selectedAction} onChange={(e) => setSelectedAction(e.target.value)} className="w-44">
                                <option value="all">{t('audit.allActions')}</option>
                                {actionOptions.map((action) => (
                                    <option key={action} value={action}>
                                        {actionLabel(action)}
                                    </option>
                                ))}
                            </Select>
                            <Select value={selectedEntity} onChange={(e) => setSelectedEntity(e.target.value)} className="w-44">
                                <option value="all">{t('audit.allEntities')}</option>
                                {entityOptions.map((entity) => (
                                    <option key={entity} value={entity}>
                                        {entity}
                                    </option>
                                ))}
                            </Select>
                            <Select value={selectedActor} onChange={(e) => setSelectedActor(e.target.value)} className="w-52">
                                <option value="all">{t('audit.allActors')}</option>
                                {actors.map((actor) => (
                                    <option key={actor.id} value={String(actor.id)}>
                                        {actor.name}
                                    </option>
                                ))}
                            </Select>
                            <div className="flex items-center gap-2">
                                <input
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) => setDateFrom(e.target.value)}
                                    aria-label={t('audit.dateFrom')}
                                    className="border-border-strong dark:border-slate-600 text-ink dark:text-white focus:border-primary h-10 rounded-[10px] border bg-white dark:bg-slate-900 px-3 text-sm focus:outline-none"
                                />
                                <span className="text-muted dark:text-slate-400 text-xs">→</span>
                                <input
                                    type="date"
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                    aria-label={t('audit.dateTo')}
                                    className="border-border-strong dark:border-slate-600 text-ink dark:text-white focus:border-primary h-10 rounded-[10px] border bg-white dark:bg-slate-900 px-3 text-sm focus:outline-none"
                                />
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-border dark:border-slate-700 text-muted dark:text-slate-400 border-b text-xs">
                                        <th scope="col" className="px-5 py-3 font-semibold">
                                            {t('audit.colTime')}
                                        </th>
                                        <th scope="col" className="px-5 py-3 font-semibold">
                                            {t('audit.colActor')}
                                        </th>
                                        <th scope="col" className="px-5 py-3 font-semibold">
                                            {t('audit.colEntity')}
                                        </th>
                                        <th scope="col" className="px-5 py-3 font-semibold">
                                            {t('audit.colAction')}
                                        </th>
                                        <th scope="col" className="px-5 py-3 font-semibold">
                                            {t('audit.colSummary')}
                                        </th>
                                        <th scope="col" className="px-5 py-3 text-right font-semibold">
                                            {t('common.actions')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-border dark:divide-slate-700 divide-y">
                                    {items.length > 0 ? (
                                        items.map((log) => (
                                            <tr key={log.id} className="hover:bg-surface/50 dark:hover:bg-slate-800/50 transition-colors">
                                                <td className="text-muted dark:text-slate-400 px-5 py-4 text-xs whitespace-nowrap">
                                                    {fmtDateTime(log.created_at, false)}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <span className="text-navy dark:text-white block text-sm font-medium">{log.actor.name}</span>
                                                    {log.actor.role && (
                                                        <span className="text-muted dark:text-slate-400 block text-xs">
                                                            {roleLabel(log.actor.role)}
                                                            {log.actor.unit_name && log.actor.unit_name !== 'Semua Unit'
                                                                ? ` • ${log.actor.unit_name}`
                                                                : ''}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <span className="text-navy dark:text-white block text-sm font-medium">{log.entity_label}</span>
                                                    <span className="text-muted dark:text-slate-400 block text-xs">{log.entity_type}</span>
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap">
                                                    <StatusBadge tone={actionTone(log.action)}>{actionLabel(log.action)}</StatusBadge>
                                                </td>
                                                <td className="text-body dark:text-slate-300 px-5 py-4 text-sm">{summaryText(log.changes)}</td>
                                                <td className="px-5 py-4 text-right whitespace-nowrap">
                                                    <button
                                                        type="button"
                                                        onClick={() => setDetailTarget(log)}
                                                        className="border-border-strong dark:border-slate-600 text-body dark:text-slate-300 hover:bg-surface dark:hover:bg-slate-800 rounded-lg border bg-white dark:bg-slate-900 px-3 py-1.5 text-xs font-semibold shadow-sm transition-colors"
                                                    >
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
                            onPageChange={(p) =>
                                router.get(
                                    '/admin/kepatuhan/audit-logs',
                                    { ...buildFilterParams(searchQuery, selectedAction, selectedEntity, selectedActor, dateFrom, dateTo), page: p },
                                    { preserveState: true, replace: true },
                                )
                            }
                        />
                    </section>
                </div>

                <Modal
                    open={detailTarget !== null}
                    title={detailTarget ? `${t('audit.detailTitle')} — ${actionLabel(detailTarget.action)}` : ''}
                    description={t('audit.detailDesc')}
                    onClose={() => setDetailTarget(null)}
                    maxWidth="xl"
                    footer={
                        <button
                            type="button"
                            onClick={() => setDetailTarget(null)}
                            className="border-border-strong dark:border-slate-600 text-body dark:text-slate-300 hover:bg-surface dark:hover:bg-slate-800 rounded-lg border bg-white dark:bg-slate-900 px-4 py-2 text-sm font-semibold shadow-sm transition-colors"
                        >
                            {t('audit.close')}
                        </button>
                    }
                >
                    {detailTarget && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <span className="text-muted dark:text-slate-400 block text-xs">{t('audit.timeLabel')}</span>
                                    <span className="text-navy dark:text-white block font-medium">{fmtDateTime(detailTarget.created_at, true)}</span>
                                </div>
                                <div>
                                    <span className="text-muted dark:text-slate-400 block text-xs">{t('audit.actorLabel')}</span>
                                    <span className="text-navy dark:text-white block font-medium">{detailTarget.actor.name}</span>
                                    {detailTarget.actor.role && (
                                        <span className="text-muted dark:text-slate-400 block text-xs">
                                            {roleLabel(detailTarget.actor.role)}
                                            {detailTarget.actor.unit_name && detailTarget.actor.unit_name !== 'Semua Unit'
                                                ? ` • ${detailTarget.actor.unit_name}`
                                                : ''}
                                        </span>
                                    )}
                                </div>
                                <div>
                                    <span className="text-muted dark:text-slate-400 block text-xs">{t('audit.entityLabel')}</span>
                                    <span className="text-navy dark:text-white block font-medium">{detailTarget.entity_label}</span>
                                </div>
                                <div>
                                    <span className="text-muted dark:text-slate-400 block text-xs">{t('audit.actionLabel')}</span>
                                    <StatusBadge tone={actionTone(detailTarget.action)}>{actionLabel(detailTarget.action)}</StatusBadge>
                                </div>
                            </div>

                            <div>
                                <h4 className="text-navy dark:text-white mb-2 text-[13px] font-bold">{t('audit.changesLabel')}</h4>
                                {diff.length > 0 ? (
                                    <div className="border-border dark:border-slate-700 overflow-x-auto rounded-[10px] border">
                                        <table className="w-full text-left text-sm">
                                            <thead>
                                                <tr className="border-border dark:border-slate-700 bg-surface/60 dark:bg-slate-900/60 border-b text-xs">
                                                    <th scope="col" className="text-navy dark:text-white px-4 py-2.5 font-semibold">
                                                        {t('audit.field')}
                                                    </th>
                                                    <th scope="col" className="text-danger dark:text-red-400 px-4 py-2.5 font-semibold">
                                                        {t('audit.before')}
                                                    </th>
                                                    <th scope="col" className="text-success dark:text-emerald-400 px-4 py-2.5 font-semibold">
                                                        {t('audit.after')}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-border dark:divide-slate-700 divide-y">
                                                {diff.map((entry) => (
                                                    <tr key={entry.field}>
                                                        <td className="px-4 py-2.5">
                                                            <code className="text-navy dark:text-white text-xs">{entry.field}</code>
                                                        </td>
                                                        <td className="text-muted dark:text-slate-400 px-4 py-2.5 text-xs">{fmtValue(entry.before)}</td>
                                                        <td className="text-body dark:text-slate-300 px-4 py-2.5 text-xs font-medium">{fmtValue(entry.after)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <p className="text-muted dark:text-slate-400 text-sm">{t('common.noData')}</p>
                                )}
                            </div>

                            <div className="border-info/20 bg-info-bg text-ink dark:text-white flex items-start gap-2.5 rounded-[10px] border p-3.5 text-[13px]">
                                <ShieldCheck className="text-info dark:text-sky-400 mt-0.5 h-4 w-4 shrink-0" />
                                <span>{t('audit.immutable')}</span>
                            </div>
                        </div>
                    )}
                </Modal>
            </AppLayout>
        </>
    );
}
