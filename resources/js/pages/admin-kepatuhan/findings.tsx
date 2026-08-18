import { EmptyState } from '@/components/ui/EmptyState';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import { StatusBadge } from '@/components/ui/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { t } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Clock, LayoutGrid, List as ListIcon, Search, ShieldAlert } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface FindingItem {
    id: number;
    kategori: string;
    status: string;
    deadline: string | null;
    created_at?: string | null;
    is_overdue: boolean;
    days_remaining: number | null;
    verified_at: string | null;
    catatan_admin?: string | null;
    admin_notes?: string | null;
    control?: {
        id: number;
        kode_klausul: string;
        judul: string;
        framework?: { id: number; nama: string; versi: string } | null;
    } | null;
    unit?: { id: number; nama: string } | null;
    pic?: { id: number; name: string } | null;
    admin?: { id: number; name: string } | null;
    [key: string]: unknown;
}

interface WorkUnitItem {
    id: number;
    nama: string;
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

interface FindingsProps {
    findings?: Paginator<FindingItem>;
    workUnits?: WorkUnitItem[];
    filters?: {
        status?: string;
        category?: string;
        kategori?: string;
        unit_id?: string;
        search?: string;
    };
}

const SEVERITY_OPTIONS = ['major', 'minor', 'observasi'] as const;
const STATUS_OPTIONS = ['open', 'in_progress', 'closed'] as const;

const KANBAN_COLUMNS: Array<{ status: string; label: string; dotClass: string }> = [
    { status: 'open', label: 'findings.open', dotClass: 'bg-danger' },
    { status: 'in_progress', label: 'findings.inProgress', dotClass: 'bg-warning' },
    { status: 'closed', label: 'findings.closed', dotClass: 'bg-success' },
];

const SEVERITY_TONE: Record<string, 'red' | 'amber' | 'blue'> = {
    major: 'red',
    minor: 'amber',
    observasi: 'blue',
};

const STATUS_TONE: Record<string, 'red' | 'amber' | 'green'> = {
    open: 'red',
    in_progress: 'amber',
    closed: 'green',
};

function severityLabel(kategori: string): string {
    if (kategori === 'major') return t('status.major');
    if (kategori === 'minor') return t('status.minor');
    return t('status.observation');
}

function findingRef(f: FindingItem): string {
    const year = f.created_at ? new Date(f.created_at).getFullYear() : new Date().getFullYear();
    return `${year}-${String(f.id).padStart(3, '0')}`;
}

function fmtDate(value: string | null): string {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

export default function Findings({ findings, workUnits = [], filters = {} }: FindingsProps) {
    const page = findings ?? { data: [], current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null };
    const items = page.data;

    const [view, setView] = useState<'kanban' | 'list'>('kanban');
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedSeverity, setSelectedSeverity] = useState<string>(filters.category || filters.kategori || 'all');
    const [selectedStatus, setSelectedStatus] = useState<string>(filters.status || 'all');
    const [selectedUnit, setSelectedUnit] = useState<string>(filters.unit_id || 'all');
    const [detailTarget, setDetailTarget] = useState<FindingItem | null>(null);
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                '/admin/kepatuhan/findings',
                {
                    search: searchQuery || undefined,
                    category: selectedSeverity !== 'all' ? selectedSeverity : undefined,
                    status: selectedStatus !== 'all' ? selectedStatus : undefined,
                    unit_id: selectedUnit !== 'all' ? selectedUnit : undefined,
                },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [searchQuery, selectedSeverity, selectedStatus, selectedUnit]);

    const breadcrumbs = [
        { label: t('common.dashboard'), href: '/admin/kepatuhan/dashboard' },
        { label: t('findings.title') },
    ];

    const groupByStatus = (status: string) => items.filter((f) => f.status === status);

    const deadlineChip = (f: FindingItem) => {
        if (f.status === 'closed') {
            return (
                <span className="border-success-border bg-success-bg text-success inline-flex items-center gap-1.5 rounded-[6px] border px-2 py-1 text-[11px] font-semibold">
                    <CheckCircle2 className="h-3 w-3" />
                    {f.verified_at ? t('findings.verifiedOn', fmtDate(f.verified_at)) : t('findings.done')}
                </span>
            );
        }

        if (!f.deadline) return null;

        if (f.is_overdue) {
            return (
                <span className="border-danger-border bg-danger-bg text-danger inline-flex items-center gap-1.5 rounded-[6px] border px-2 py-1 text-[11px] font-semibold">
                    <Clock className="h-3 w-3" />
                    {t('findings.lateDays', Math.abs(f.days_remaining ?? 0))}
                </span>
            );
        }

        const remaining = f.days_remaining ?? 0;
        const toneClass =
            remaining <= 3
                ? 'border-warning-border bg-warning-bg text-warning'
                : 'border-success-border bg-success-bg text-success';

        return (
            <span className={`inline-flex items-center gap-1.5 rounded-[6px] border px-2 py-1 text-[11px] font-semibold ${toneClass}`}>
                <Clock className="h-3 w-3" />
                {remaining === 0 ? t('findings.deadlineToday') : t('findings.leftDays', remaining)}
            </span>
        );
    };

    const initials = (name?: string) =>
        (name || '')
            .split(' ')
            .map((n) => n[0])
            .join('')
            .substring(0, 2)
            .toUpperCase();

    const renderKanban = () => (
        <div className="grid grid-cols-1 gap-[18px] md:grid-cols-3">
            {KANBAN_COLUMNS.map((col) => {
                const columnItems = groupByStatus(col.status);

                return (
                    <div key={col.status} className="border-border bg-surface/40 flex flex-col rounded-[14px] border p-3.5">
                        <div className="mb-3 flex items-center justify-between px-1">
                            <div className="flex items-center gap-2">
                                <span className={`h-2 w-2 rounded-full ${col.dotClass}`} />
                                <strong className="text-navy text-[13px] font-bold">{t(col.label as never)}</strong>
                                <span className="border-border bg-white text-body rounded-full border px-2 py-0.5 text-[11px] font-semibold">
                                    {columnItems.length}
                                </span>
                            </div>
                        </div>

                        <div className="space-y-3">
                            {columnItems.length > 0 ? (
                                columnItems.map((f) => (
                                    <button
                                        key={f.id}
                                        type="button"
                                        onClick={() => setDetailTarget(f)}
                                        className="hover:border-primary-300 border-border bg-white block w-full rounded-[12px] border p-3.5 text-left shadow-sm transition-colors"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <code className="text-primary text-[12px] font-bold">FND-{findingRef(f)}</code>
                                            <StatusBadge tone={SEVERITY_TONE[f.kategori] ?? 'gray'}>
                                                {severityLabel(f.kategori)}
                                            </StatusBadge>
                                        </div>

                                        <div className="text-navy mt-2 line-clamp-2 text-[13px] leading-snug font-semibold">
                                            {f.control?.judul || t('common.noData')}
                                        </div>

                                        <div className="text-faint mt-2.5 flex items-center justify-between gap-2 text-[11px]">
                                            <span className="inline-flex min-w-0 items-center gap-1 truncate">
                                                <span className="bg-primary/10 text-primary grid h-5 w-5 shrink-0 place-items-center rounded-[6px]">
                                                    <ShieldAlert className="h-3 w-3" />
                                                </span>
                                                {f.unit?.nama || '—'}
                                            </span>
                                            {f.pic?.name && (
                                                <span className="bg-primary text-white grid h-5 w-5 shrink-0 place-items-center rounded-full text-[9px] font-bold">
                                                    {initials(f.pic.name)}
                                                </span>
                                            )}
                                        </div>

                                        <div className="mt-2.5">{deadlineChip(f)}</div>
                                    </button>
                                ))
                            ) : (
                                <div className="border-border bg-white rounded-[12px] border border-dashed p-6 text-center">
                                    <span className="text-faint text-[12px]">{t('common.noData')}</span>
                                </div>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );

    const renderList = () => (
        <section className="border-border overflow-hidden rounded-[14px] border bg-white shadow-sm">
            <div className="overflow-x-auto">
                <table className="w-full text-left text-xs sm:text-sm">
                    <thead className="border-border bg-surface/60 text-muted border-b text-[11px] font-bold tracking-wider uppercase">
                        <tr>
                            <th scope="col" className="px-5 py-3 text-left font-semibold">
                                {t('findings.ref')}
                            </th>
                            <th scope="col" className="px-5 py-3 text-left font-semibold">
                                {t('findings.judul')}
                            </th>
                            <th scope="col" className="px-5 py-3 text-left font-semibold">
                                {t('findings.severity')}
                            </th>
                            <th scope="col" className="px-5 py-3 text-left font-semibold">
                                {t('findings.workUnit')}
                            </th>
                            <th scope="col" className="px-5 py-3 text-left font-semibold">
                                {t('findings.assignee')}
                            </th>
                            <th scope="col" className="px-5 py-3 text-left font-semibold">
                                {t('findings.status')}
                            </th>
                            <th scope="col" className="px-5 py-3 text-left font-semibold">
                                {t('findings.deadline')}
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-border divide-y">
                        {items.length > 0 ? (
                            items.map((f) => (
                                <tr key={f.id} className="hover:bg-surface/50 transition-colors">
                                    <td className="px-5 py-4 whitespace-nowrap">
                                        <code className="text-primary font-bold">FND-{findingRef(f)}</code>
                                    </td>
                                    <td className="px-5 py-4">
                                        <button
                                            type="button"
                                            onClick={() => setDetailTarget(f)}
                                            className="text-navy text-left font-semibold hover:text-primary"
                                        >
                                            {f.control?.judul || t('common.noData')}
                                        </button>
                                    </td>
                                    <td className="px-5 py-4 whitespace-nowrap">
                                        <StatusBadge tone={SEVERITY_TONE[f.kategori] ?? 'gray'}>
                                            {severityLabel(f.kategori)}
                                        </StatusBadge>
                                    </td>
                                    <td className="text-body px-5 py-4 whitespace-nowrap">{f.unit?.nama || '—'}</td>
                                    <td className="text-body px-5 py-4 whitespace-nowrap">{f.pic?.name || '—'}</td>
                                    <td className="px-5 py-4 whitespace-nowrap">
                                        <StatusBadge tone={STATUS_TONE[f.status] ?? 'gray'}>
                                            {t(`status.${f.status as 'open' | 'in_progress' | 'closed'}`)}
                                        </StatusBadge>
                                    </td>
                                    <td className="px-5 py-4 whitespace-nowrap">{deadlineChip(f) ?? <span className="text-faint">—</span>}</td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={7}>
                                    <EmptyState message={t('findings.noFindings')} />
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
                        '/admin/kepatuhan/findings',
                        {
                            search: searchQuery || undefined,
                            category: selectedSeverity !== 'all' ? selectedSeverity : undefined,
                            status: selectedStatus !== 'all' ? selectedStatus : undefined,
                            unit_id: selectedUnit !== 'all' ? selectedUnit : undefined,
                            page: p,
                        },
                        { preserveState: true, replace: true },
                    )
                }
            />
        </section>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/kepatuhan/findings">
            <Head title={`${t('findings.title')} - Admin Kepatuhan`} />

            <div className="page-head flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('findings.title')}</h1>
                    <p className="text-muted mt-1 text-xs sm:text-sm">
                        {t('findings.subtitle')}
                        {items.some((f) => f.is_overdue) && (
                            <strong className="text-danger"> · {items.filter((f) => f.is_overdue).length} {t('findings.overdue')}</strong>
                        )}
                    </p>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-2.5">
                <div className="border-border bg-white flex items-center rounded-[10px] border p-0.5 shadow-sm">
                    <button
                        type="button"
                        onClick={() => setView('kanban')}
                        className={`inline-flex items-center gap-1.5 rounded-[8px] px-3 py-1.5 text-xs font-semibold transition-colors ${
                            view === 'kanban' ? 'bg-primary text-white shadow-sm' : 'text-body hover:bg-surface'
                        }`}
                    >
                        <LayoutGrid className="h-3.5 w-3.5" />
                        {t('findings.kanban')}
                    </button>
                    <button
                        type="button"
                        onClick={() => setView('list')}
                        className={`inline-flex items-center gap-1.5 rounded-[8px] px-3 py-1.5 text-xs font-semibold transition-colors ${
                            view === 'list' ? 'bg-primary text-white shadow-sm' : 'text-body hover:bg-surface'
                        }`}
                    >
                        <ListIcon className="h-3.5 w-3.5" />
                        {t('findings.list')}
                    </button>
                </div>

                <div className="relative min-w-[220px] flex-1">
                    <Search className="text-faint absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder={t('findings.searchPlaceholder')}
                        className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 h-10 w-full rounded-[10px] border bg-white py-2 pr-4 pl-9 text-xs focus:ring-2 focus:outline-none sm:text-sm"
                    />
                </div>

                <Select value={selectedSeverity} onChange={(e) => setSelectedSeverity(e.target.value)} className="min-w-[160px]">
                    <option value="all">{t('findings.allSeverity')}</option>
                    {SEVERITY_OPTIONS.map((s) => (
                        <option key={s} value={s}>
                            {severityLabel(s)}
                        </option>
                    ))}
                </Select>

                <Select value={selectedStatus} onChange={(e) => setSelectedStatus(e.target.value)} className="min-w-[170px]">
                    <option value="all">{t('findings.allStatus')}</option>
                    {STATUS_OPTIONS.map((s) => (
                        <option key={s} value={s}>
                            {t(`status.${s}`)}
                        </option>
                    ))}
                </Select>

                <Select value={selectedUnit} onChange={(e) => setSelectedUnit(e.target.value)} className="min-w-[170px]">
                    <option value="all">{t('findings.allUnits')}</option>
                    {workUnits.map((u) => (
                        <option key={u.id} value={String(u.id)}>
                            {u.nama}
                        </option>
                    ))}
                </Select>
            </div>

            {view === 'kanban' ? renderKanban() : renderList()}

            {view === 'kanban' && page.last_page > 1 && (
                <Pagination
                    currentPage={page.current_page}
                    totalPages={page.last_page}
                    perPage={page.per_page}
                    totalItems={page.total}
                    startIndex={(page.from ?? 1) - 1}
                    endIndex={page.to ?? page.total}
                    onPageChange={(p) =>
                        router.get(
                            '/admin/kepatuhan/findings',
                            {
                                search: searchQuery || undefined,
                                category: selectedSeverity !== 'all' ? selectedSeverity : undefined,
                                status: selectedStatus !== 'all' ? selectedStatus : undefined,
                                unit_id: selectedUnit !== 'all' ? selectedUnit : undefined,
                                page: p,
                            },
                            { preserveState: true, replace: true },
                        )
                    }
                />
            )}

            <Modal
                open={detailTarget !== null}
                title={t('findings.detailTitle')}
                description={detailTarget ? `FND-${findingRef(detailTarget)}` : undefined}
                onClose={() => setDetailTarget(null)}
                maxWidth="lg"
                footer={
                    <button
                        type="button"
                        onClick={() => setDetailTarget(null)}
                        className="border-border-strong text-body hover:bg-surface rounded-[10px] border bg-white px-4 py-2 text-sm font-medium transition-colors"
                    >
                        {t('findings.close')}
                    </button>
                }
            >
                {detailTarget && (
                    <div className="space-y-4">
                        <div className="border-border overflow-hidden rounded-[10px] border">
                            <div className="border-border flex items-center justify-between border-b px-4 py-2.5">
                                <span className="text-body text-[13px] font-medium">{t('findings.controlLabel')}</span>
                                <span className="text-navy max-w-[60%] text-right text-[13px] font-semibold">
                                    {detailTarget.control?.kode_klausul} — {detailTarget.control?.judul}
                                </span>
                            </div>
                            <div className="border-border flex items-center justify-between border-b px-4 py-2.5">
                                <span className="text-body text-[13px] font-medium">{t('findings.unitLabel')}</span>
                                <span className="text-navy text-[13px] font-semibold">{detailTarget.unit?.nama || '—'}</span>
                            </div>
                            <div className="border-border flex items-center justify-between border-b px-4 py-2.5">
                                <span className="text-body text-[13px] font-medium">{t('findings.picLabel')}</span>
                                <span className="text-navy text-[13px] font-semibold">{detailTarget.pic?.name || '—'}</span>
                            </div>
                            <div className="flex items-center justify-between px-4 py-2.5">
                                <span className="text-body text-[13px] font-medium">{t('findings.status')}</span>
                                <StatusBadge tone={STATUS_TONE[detailTarget.status] ?? 'gray'}>
                                    {t(`status.${detailTarget.status as 'open' | 'in_progress' | 'closed'}`)}
                                </StatusBadge>
                            </div>
                        </div>

                        <div>
                            <h4 className="text-navy text-sm font-bold">{t('findings.severity')}</h4>
                            <div className="mt-2">
                                <StatusBadge tone={SEVERITY_TONE[detailTarget.kategori] ?? 'gray'}>
                                    {severityLabel(detailTarget.kategori)}
                                </StatusBadge>
                            </div>
                        </div>

                        {detailTarget.admin_notes || detailTarget.catatan_admin ? (
                            <div>
                                <h4 className="text-navy text-sm font-bold">{t('findings.notesLabel')}</h4>
                                <p className="text-body mt-2 rounded-[10px] border border-border bg-surface/50 p-3.5 text-[13px] leading-relaxed">
                                    {detailTarget.admin_notes || detailTarget.catatan_admin}
                                </p>
                            </div>
                        ) : null}

                        <div className="border-info/20 bg-info-bg flex gap-3 rounded-[10px] border p-3.5">
                            <Clock className="text-info mt-0.5 h-4 w-4 shrink-0" />
                            <div className="text-info text-[13px] font-semibold">{deadlineChip(detailTarget) ?? t('common.noData')}</div>
                        </div>
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}