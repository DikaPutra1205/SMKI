import { EmptyState } from '@/components/ui/EmptyState';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import { StatusBadge } from '@/components/ui/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { t } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Plus, Search, ShieldAlert } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface RiskItem {
    id: number;
    level_risiko: string;
    pemilik_risiko: string;
    rencana_mitigasi: string | null;
    status: string;
    created_at?: string | null;
    risk_level?: string;
    risk_owner?: string;
    mitigation_plan?: string | null;
    control?: {
        id: number;
        kode_klausul: string;
        judul: string;
        framework?: { id: number; nama: string; versi: string } | null;
    } | null;
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

interface RiskMatrix {
    total_risks?: number;
    by_level?: { critical?: number; high?: number; medium?: number; low?: number };
    by_status?: { open?: number; mitigated?: number; accepted?: number };
    [key: string]: unknown;
}

interface RisksProps {
    risks?: Paginator<RiskItem>;
    matrix?: RiskMatrix;
    workUnits?: WorkUnitItem[];
    filters?: {
        risk_level?: string;
        level_risiko?: string;
        status?: string;
        unit_id?: string;
        search?: string;
    };
}

const LEVEL_OPTIONS = ['critical', 'high', 'medium', 'low'] as const;
const STATUS_OPTIONS = ['open', 'mitigated', 'accepted'] as const;

const LEVEL_TONE: Record<string, 'red' | 'amber' | 'blue'> = {
    critical: 'red',
    high: 'red',
    medium: 'amber',
    low: 'blue',
};

const STATUS_TONE: Record<string, 'red' | 'green'> = {
    open: 'red',
    mitigated: 'green',
    accepted: 'green',
};

function riskRef(r: RiskItem): string {
    return String(r.id).padStart(3, '0');
}

export default function Risks({ risks, matrix = {}, workUnits = [], filters = {} }: RisksProps) {
    const page = risks ?? { data: [], current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null };
    const items = page.data;

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedLevel, setSelectedLevel] = useState<string>(filters.risk_level || filters.level_risiko || 'all');
    const [selectedStatus, setSelectedStatus] = useState<string>(filters.status || 'all');
    const [selectedUnit, setSelectedUnit] = useState<string>(filters.unit_id || 'all');
    const [detailTarget, setDetailTarget] = useState<RiskItem | null>(null);
    const isFirstRender = useRef(true);

    const totalRisks = matrix.total_risks ?? page.total;
    const critical = matrix.by_level?.critical ?? 0;
    const high = matrix.by_level?.high ?? 0;
    const mitigated = matrix.by_status?.mitigated ?? 0;

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                '/admin/kepatuhan/risks',
                {
                    search: searchQuery || undefined,
                    risk_level: selectedLevel !== 'all' ? selectedLevel : undefined,
                    status: selectedStatus !== 'all' ? selectedStatus : undefined,
                    unit_id: selectedUnit !== 'all' ? selectedUnit : undefined,
                },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [searchQuery, selectedLevel, selectedStatus, selectedUnit]);

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/admin/kepatuhan/dashboard' }, { label: t('risks.title') }];

    const goToPage = (p: number) =>
        router.get(
            '/admin/kepatuhan/risks',
            {
                search: searchQuery || undefined,
                risk_level: selectedLevel !== 'all' ? selectedLevel : undefined,
                status: selectedStatus !== 'all' ? selectedStatus : undefined,
                unit_id: selectedUnit !== 'all' ? selectedUnit : undefined,
                page: p,
            },
            { preserveState: true, replace: true },
        );

    const kpiCards = [
        {
            label: t('risks.totalRisks'),
            value: totalRisks,
            iconClass: 'bg-violet-bg text-violet dark:text-violet-400',
            Icon: ShieldAlert,
        },
        {
            label: t('risks.critical'),
            value: critical,
            iconClass: 'bg-danger-bg text-danger dark:text-red-400',
            Icon: AlertTriangle,
        },
        {
            label: t('risks.high'),
            value: high,
            iconClass: 'bg-warning-bg text-warning dark:text-amber-400',
            Icon: AlertTriangle,
        },
        {
            label: t('risks.mitigated'),
            value: mitigated,
            iconClass: 'bg-info-bg text-info dark:text-sky-400',
            Icon: ShieldAlert,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/kepatuhan/risks">
            <Head title={`${t('risks.title')} - Admin Kepatuhan`} />

            <div className="page-head flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('risks.title')}</h1>
                    <p className="text-muted dark:text-slate-400 mt-1 text-xs sm:text-sm">{t('risks.subtitle')}</p>
                </div>

                <button
                    type="button"
                    disabled
                    className="bg-surface-2 dark:bg-slate-800 border-border dark:border-slate-700 text-faint dark:text-slate-500 inline-flex items-center gap-2 rounded-[10px] border px-4 py-2 text-xs font-semibold sm:text-sm"
                    title={t('risks.comingSoon')}
                >
                    <Plus className="h-4 w-4" />
                    <span>{t('risks.newRisk')}</span>
                    <span className="text-[10px] font-bold tracking-wider uppercase">({t('risks.comingSoon')})</span>
                </button>
            </div>

            <div className="grid grid-cols-2 gap-[14px] xl:grid-cols-4">
                {kpiCards.map((kpi) => (
                    <div key={kpi.label} className="border-border dark:border-slate-700 rounded-[14px] border bg-white dark:bg-slate-900 p-[18px_20px] shadow-sm">
                        <div className="flex items-center justify-between">
                            <div className={`grid h-9 w-9 place-items-center rounded-[10px] ${kpi.iconClass}`}>
                                <kpi.Icon className="h-[18px] w-[18px]" />
                            </div>
                        </div>
                        <div className="text-navy dark:text-white mt-3 text-[26px] leading-none font-bold">{kpi.value}</div>
                        <div className="text-muted dark:text-slate-400 mt-1.5 text-xs font-semibold">{kpi.label}</div>
                    </div>
                ))}
            </div>

            <section className="border-border dark:border-slate-700 overflow-hidden rounded-[14px] border bg-white dark:bg-slate-900 shadow-sm">
                <div className="border-border dark:border-slate-700 border-b px-5 py-4">
                    <h3 className="text-[15px] font-bold">{t('risks.title')}</h3>
                </div>

                <div className="border-border dark:border-slate-700 flex flex-col gap-3 border-b bg-white dark:bg-slate-900 p-[12px_16px] md:flex-row md:items-center">
                    <div className="relative min-w-[220px] flex-1">
                        <Search className="text-faint dark:text-slate-500 absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder={t('risks.searchPlaceholder')}
                            className="border-border-strong dark:border-slate-600 text-ink dark:text-white placeholder:text-faint dark:placeholder:text-slate-500 focus:border-primary focus:ring-primary/20 h-10 w-full rounded-[10px] border bg-white dark:bg-slate-900 py-2 pr-4 pl-9 text-xs focus:ring-2 focus:outline-none sm:text-sm"
                        />
                    </div>

                    <Select value={selectedLevel} onChange={(e) => setSelectedLevel(e.target.value)} className="min-w-[150px]">
                        <option value="all">{t('risks.allLevels')}</option>
                        {LEVEL_OPTIONS.map((l) => (
                            <option key={l} value={l}>
                                {t(`status.${l}`)}
                            </option>
                        ))}
                    </Select>

                    <Select value={selectedStatus} onChange={(e) => setSelectedStatus(e.target.value)} className="min-w-[150px]">
                        <option value="all">{t('risks.allStatus')}</option>
                        {STATUS_OPTIONS.map((s) => (
                            <option key={s} value={s}>
                                {t(`status.${s}`)}
                            </option>
                        ))}
                    </Select>

                    <Select value={selectedUnit} onChange={(e) => setSelectedUnit(e.target.value)} className="min-w-[170px]">
                        <option value="all">{t('risks.allUnits')}</option>
                        {workUnits.map((u) => (
                            <option key={u.id} value={String(u.id)}>
                                {u.nama}
                            </option>
                        ))}
                    </Select>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs sm:text-sm">
                        <thead className="border-border dark:border-slate-700 bg-surface/60 dark:bg-slate-900/60 text-muted dark:text-slate-400 border-b text-[11px] font-bold tracking-wider uppercase">
                            <tr>
                                <th scope="col" className="px-5 py-3 text-left font-semibold">
                                    {t('risks.code')}
                                </th>
                                <th scope="col" className="px-5 py-3 text-left font-semibold">
                                    {t('risks.risk')}
                                </th>
                                <th scope="col" className="px-5 py-3 text-left font-semibold">
                                    {t('risks.unitControl')}
                                </th>
                                <th scope="col" className="px-5 py-3 text-left font-semibold">
                                    {t('risks.level')}
                                </th>
                                <th scope="col" className="px-5 py-3 text-left font-semibold">
                                    {t('risks.owner')}
                                </th>
                                <th scope="col" className="px-5 py-3 text-left font-semibold">
                                    {t('risks.status')}
                                </th>
                                <th scope="col" className="px-5 py-3 text-left font-semibold">
                                    {t('risks.target')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-border dark:divide-slate-700 divide-y">
                            {items.length > 0 ? (
                                items.map((r) => (
                                    <tr key={r.id} className="hover:bg-surface/50 dark:hover:bg-slate-800/50 transition-colors">
                                        <td className="px-5 py-4 whitespace-nowrap">
                                            <code className="text-primary font-bold">RSK-{riskRef(r)}</code>
                                        </td>
                                        <td className="px-5 py-4">
                                            <button
                                                type="button"
                                                onClick={() => setDetailTarget(r)}
                                                className="text-navy dark:text-white hover:text-primary text-left font-semibold"
                                            >
                                                {r.control?.judul || t('common.noData')}
                                            </button>
                                            {r.mitigation_plan || r.rencana_mitigasi ? (
                                                <div className="text-faint dark:text-slate-500 mt-0.5 line-clamp-1 text-xs">
                                                    {r.mitigation_plan || r.rencana_mitigasi}
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className="text-body dark:text-slate-300 px-5 py-4 whitespace-nowrap">
                                            <span className="text-navy dark:text-white font-medium">{r.control?.kode_klausul || '—'}</span>
                                            {r.control?.framework ? <div className="text-faint dark:text-slate-500 text-xs">{r.control.framework.nama}</div> : null}
                                        </td>
                                        <td className="px-5 py-4 whitespace-nowrap">
                                            <StatusBadge tone={LEVEL_TONE[r.risk_level || r.level_risiko] ?? 'gray'}>
                                                {t(`status.${(r.risk_level || r.level_risiko) as 'critical' | 'high' | 'medium' | 'low'}`)}
                                            </StatusBadge>
                                        </td>
                                        <td className="text-body dark:text-slate-300 px-5 py-4 whitespace-nowrap">{r.risk_owner || r.pemilik_risiko || '—'}</td>
                                        <td className="px-5 py-4 whitespace-nowrap">
                                            <StatusBadge tone={STATUS_TONE[r.status] ?? 'gray'}>
                                                {t(`status.${r.status as 'open' | 'mitigated' | 'accepted'}`)}
                                            </StatusBadge>
                                        </td>
                                        <td className="text-faint dark:text-slate-500 px-5 py-4 whitespace-nowrap">{t('risks.noTarget')}</td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={7}>
                                        <EmptyState message={t('risks.noRisks')} />
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
                    onPageChange={goToPage}
                />
            </section>

            <Modal
                open={detailTarget !== null}
                title={t('risks.detailTitle')}
                description={detailTarget ? `RSK-${riskRef(detailTarget)}` : undefined}
                onClose={() => setDetailTarget(null)}
                maxWidth="lg"
                footer={
                    <button
                        type="button"
                        onClick={() => setDetailTarget(null)}
                        className="border-border-strong dark:border-slate-600 text-body dark:text-slate-300 hover:bg-surface dark:hover:bg-slate-800 rounded-[10px] border bg-white dark:bg-slate-900 px-4 py-2 text-sm font-medium transition-colors"
                    >
                        {t('risks.close')}
                    </button>
                }
            >
                {detailTarget && (
                    <div className="space-y-4">
                        <div className="border-border dark:border-slate-700 overflow-hidden rounded-[10px] border">
                            <div className="border-border dark:border-slate-700 flex items-center justify-between border-b px-4 py-2.5">
                                <span className="text-body dark:text-slate-300 text-[13px] font-medium">{t('risks.risk')}</span>
                                <span className="text-navy dark:text-white max-w-[60%] text-right text-[13px] font-semibold">{detailTarget.control?.judul}</span>
                            </div>
                            <div className="border-border dark:border-slate-700 flex items-center justify-between border-b px-4 py-2.5">
                                <span className="text-body dark:text-slate-300 text-[13px] font-medium">{t('risks.unitControl')}</span>
                                <span className="text-navy dark:text-white text-[13px] font-semibold">
                                    {detailTarget.control?.kode_klausul || '—'}
                                    {detailTarget.control?.framework ? ` · ${detailTarget.control.framework.nama}` : ''}
                                </span>
                            </div>
                            <div className="border-border dark:border-slate-700 flex items-center justify-between border-b px-4 py-2.5">
                                <span className="text-body dark:text-slate-300 text-[13px] font-medium">{t('risks.frameworkLabel')}</span>
                                <span className="text-navy dark:text-white text-[13px] font-semibold">{detailTarget.control?.framework?.nama || '—'}</span>
                            </div>
                            <div className="flex items-center justify-between px-4 py-2.5">
                                <span className="text-body dark:text-slate-300 text-[13px] font-medium">{t('risks.owner')}</span>
                                <span className="text-navy dark:text-white text-[13px] font-semibold">
                                    {detailTarget.risk_owner || detailTarget.pemilik_risiko || '—'}
                                </span>
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-4">
                            <div>
                                <h4 className="text-navy dark:text-white text-sm font-bold">{t('risks.level')}</h4>
                                <div className="mt-2">
                                    <StatusBadge tone={LEVEL_TONE[detailTarget.risk_level || detailTarget.level_risiko] ?? 'gray'}>
                                        {t(
                                            `status.${(detailTarget.risk_level || detailTarget.level_risiko) as 'critical' | 'high' | 'medium' | 'low'}`,
                                        )}
                                    </StatusBadge>
                                </div>
                            </div>
                            <div>
                                <h4 className="text-navy dark:text-white text-sm font-bold">{t('risks.status')}</h4>
                                <div className="mt-2">
                                    <StatusBadge tone={STATUS_TONE[detailTarget.status] ?? 'gray'}>
                                        {t(`status.${detailTarget.status as 'open' | 'mitigated' | 'accepted'}`)}
                                    </StatusBadge>
                                </div>
                            </div>
                        </div>

                        {detailTarget.mitigation_plan || detailTarget.rencana_mitigasi ? (
                            <div>
                                <h4 className="text-navy dark:text-white text-sm font-bold">{t('risks.mitigationLabel')}</h4>
                                <p className="text-body dark:text-slate-300 border-border dark:border-slate-700 bg-surface/50 dark:bg-slate-900/50 mt-2 rounded-[10px] border p-3.5 text-[13px] leading-relaxed">
                                    {detailTarget.mitigation_plan || detailTarget.rencana_mitigasi}
                                </p>
                            </div>
                        ) : null}
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
