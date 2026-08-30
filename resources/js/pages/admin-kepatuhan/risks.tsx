import { EmptyState } from '@/components/ui/EmptyState';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import { SlideOver } from '@/components/ui/SlideOver';
import AppLayout from '@/layouts/AppLayout';
import { useCan } from '@/lib/can';
import { t } from '@/lib/i18n';
import { formatDateTimeIndonesian } from '@/lib/utils';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Clock,
    Edit2,
    Eye,
    FileText,
    Flame,
    Plus,
    Search,
    Shield,
    ShieldAlert,
    ShieldCheck,
    UserCheck,
} from 'lucide-react';
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

function riskRef(r: RiskItem): string {
    return String(r.id).padStart(3, '0');
}

export default function Risks({ risks, matrix = {}, workUnits = [], filters = {} }: RisksProps) {
    const page = risks ?? { data: [], current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null };
    const items = page.data;

    const can = useCan();
    const canUpdate = can('risk.update');
    const canCreate = can('risk.create');

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedLevel, setSelectedLevel] = useState<string>(filters.risk_level || filters.level_risiko || 'all');
    const [selectedStatus, setSelectedStatus] = useState<string>(filters.status || 'all');
    const [selectedUnit, setSelectedUnit] = useState<string>(filters.unit_id || 'all');
    const [detailTarget, setDetailTarget] = useState<RiskItem | null>(null);
    const [editTarget, setEditTarget] = useState<RiskItem | null>(null);
    const isFirstRender = useRef(true);

    const updateForm = useForm({
        risk_level: '',
        status: '',
        mitigation_plan: '',
        risk_owner: '',
    });

    const openEditModal = (r: RiskItem) => {
        setEditTarget(r);
        updateForm.setData({
            risk_level: r.risk_level || r.level_risiko || 'low',
            status: r.status || 'open',
            mitigation_plan: r.mitigation_plan || r.rencana_mitigasi || '',
            risk_owner: r.risk_owner || r.pemilik_risiko || '',
        });
    };

    const closeEditModal = () => {
        setEditTarget(null);
        updateForm.reset();
        updateForm.clearErrors();
    };

    // Status that needs notes: when going back to 'open' from a resolved state
    const needsNotes = updateForm.data.status === 'open' && editTarget?.status !== 'open';

    const submitUpdate = () => {
        if (!editTarget) return;
        updateForm.put(`/admin/kepatuhan/risks/${editTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                closeEditModal();
                router.reload({ only: ['risks', 'matrix'] });
            },
        });
    };

    const totalRisks = matrix.total_risks ?? page.total;
    const critical = matrix.by_level?.critical ?? 0;
    const high = matrix.by_level?.high ?? 0;
    const mitigated = matrix.by_status?.mitigated ?? 0;

    const getBasePath = () => (typeof window !== 'undefined' ? window.location.pathname : '/admin/kepatuhan/risks');

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                getBasePath(),
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

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/dashboard' }, { label: t('risks.title') }];

    const goToPage = (p: number) =>
        router.get(
            getBasePath(),
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
            key: 'all',
            label: 'Total Register Risiko',
            value: totalRisks,
            icon: ShieldAlert,
            accent: 'blue',
            badge: `${items.length} di Halaman Ini`,
            borderClass: selectedLevel === 'all' ? 'ring-2 ring-primary border-primary' : '',
            iconClass: 'bg-primary-50 text-primary dark:bg-navy-900/60 dark:text-primary-200',
        },
        {
            key: 'critical',
            label: 'Risiko Kritis (Critical)',
            value: critical,
            icon: Flame,
            accent: 'red',
            badge: 'Prioritas Tertinggi',
            borderClass: selectedLevel === 'critical' ? 'ring-2 ring-rose-500 border-rose-500' : '',
            iconClass: 'bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400',
        },
        {
            key: 'high',
            label: 'Risiko Tinggi (High)',
            value: high,
            icon: AlertTriangle,
            accent: 'amber',
            badge: 'Perhatian Khusus',
            borderClass: selectedLevel === 'high' ? 'ring-2 ring-amber-500 border-amber-500' : '',
            iconClass: 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400',
        },
        {
            key: 'mitigated',
            label: 'Telah Dimitigasi',
            value: mitigated,
            icon: ShieldCheck,
            accent: 'emerald',
            badge: 'Terkendali',
            borderClass: selectedStatus === 'mitigated' ? 'ring-2 ring-emerald-500 border-emerald-500' : '',
            iconClass: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400',
        },
    ];

    const getRiskLevelBadge = (level: string) => {
        switch (level) {
            case 'critical':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-400">
                        <Flame className="h-3.5 w-3.5 text-rose-600 dark:text-rose-400" />
                        Kritis (Critical)
                    </span>
                );
            case 'high':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-300">
                        <AlertTriangle className="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                        Tinggi (High)
                    </span>
                );
            case 'medium':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-800 dark:border-sky-800/60 dark:bg-sky-950/40 dark:text-sky-300">
                        <Activity className="h-3.5 w-3.5 text-sky-600 dark:text-sky-400" />
                        Sedang (Medium)
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-300">
                        <Shield className="h-3.5 w-3.5 text-slate-500" />
                        Rendah (Low)
                    </span>
                );
        }
    };

    const getMitigationStatus = (status: string) => {
        if (status === 'mitigated') {
            return (
                <span className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-400">
                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                    Selesai Dimitigasi
                </span>
            );
        }
        if (status === 'accepted') {
            return (
                <span className="border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800/60 dark:bg-navy-900/40 dark:text-primary-200 inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[11px] font-semibold">
                    <ShieldCheck className="text-primary dark:text-primary-200 h-3.5 w-3.5" />
                    Risiko Diterima (Accepted)
                </span>
            );
        }
        return (
            <span className="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-400">
                <Clock className="h-3.5 w-3.5 text-rose-600 dark:text-rose-400" />
                Terbuka (Belum Dimitigasi)
            </span>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/risks">
            <Head title={`${t('risks.title')} - SMKI`} />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col gap-4 border-b border-slate-200/80 pb-5 sm:flex-row sm:items-end sm:justify-between dark:border-slate-800">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{t('risks.title')}</h1>
                            <span className="bg-primary-50 text-primary-700 border-primary-200 dark:bg-navy-900/60 dark:border-primary-800 dark:text-primary-200 rounded-full border px-2.5 py-0.5 text-xs font-bold">
                                {totalRisks} Risiko Terdaftar
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                            Pemetaan dan mitigasi risiko keamanan informasi berbasis standar ISO 27001
                        </p>
                    </div>

                    {canCreate && (
                        <button
                            type="button"
                            disabled
                            className="inline-flex cursor-not-allowed items-center gap-2 rounded-xl border border-slate-200 bg-slate-100/80 px-4 py-2 text-xs font-semibold text-slate-400 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-500"
                            title="Pendaftaran risiko baru dilakukan melalui verifikasi temuan audit"
                        >
                            <Plus className="h-4 w-4" />
                            <span>{t('risks.newRisk')}</span>
                        </button>
                    )}
                </div>

                {/* Interactive KPI Cards */}
                <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
                    {kpiCards.map((kpi) => {
                        const Icon = kpi.icon;
                        return (
                            <button
                                key={kpi.label}
                                type="button"
                                onClick={() => {
                                    if (kpi.key === 'mitigated') {
                                        setSelectedStatus(selectedStatus === 'mitigated' ? 'all' : 'mitigated');
                                    } else {
                                        setSelectedLevel(selectedLevel === kpi.key ? 'all' : kpi.key);
                                    }
                                }}
                                className={`hover:border-primary flex flex-col rounded-2xl border border-slate-200/80 bg-white p-5 text-left shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900 ${kpi.borderClass}`}
                            >
                                <div className="flex w-full items-center justify-between">
                                    <div className={`grid h-10 w-10 place-items-center rounded-xl ${kpi.iconClass}`}>
                                        <Icon className="h-5 w-5" />
                                    </div>
                                    <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-800">
                                        {kpi.badge}
                                    </span>
                                </div>
                                <div className="mt-4 text-2xl font-bold text-slate-900 dark:text-white">{kpi.value}</div>
                                <div className="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">{kpi.label}</div>
                            </button>
                        );
                    })}
                </div>

                {/* Main Table Section */}
                <section className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 className="text-sm font-bold text-slate-900 dark:text-white">Daftar Register Risiko Keamanan Informasi</h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Seluruh risiko yang teridentifikasi dari gap pemenuhan kontrol SMKI
                        </p>
                    </div>

                    {/* Filter Bar */}
                    <div className="flex flex-wrap items-center gap-3 border-b border-slate-100 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                        <div className="relative min-w-[240px] flex-1">
                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" />
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Cari deskripsi risiko, nomor klausul, atau pemilik risiko..."
                                className="focus:border-primary focus:ring-primary/20 h-10 w-full rounded-xl border border-slate-200 bg-white py-2 pr-4 pl-9 text-xs text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:outline-none sm:text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                            />
                        </div>

                        <Select value={selectedLevel} onChange={(e) => setSelectedLevel(e.target.value)} className="min-w-[160px]">
                            <option value="all">Semua Level Risiko</option>
                            {LEVEL_OPTIONS.map((l) => (
                                <option key={l} value={l}>
                                    {t(`status.${l}`)}
                                </option>
                            ))}
                        </Select>

                        <Select value={selectedStatus} onChange={(e) => setSelectedStatus(e.target.value)} className="min-w-[160px]">
                            <option value="all">Semua Status</option>
                            {STATUS_OPTIONS.map((s) => (
                                <option key={s} value={s}>
                                    {t(`status.${s}`)}
                                </option>
                            ))}
                        </Select>

                        <Select value={selectedUnit} onChange={(e) => setSelectedUnit(e.target.value)} className="min-w-[170px]">
                            <option value="all">Semua Unit Kerja</option>
                            {workUnits.map((u) => (
                                <option key={u.id} value={String(u.id)}>
                                    {u.nama}
                                </option>
                            ))}
                        </Select>
                    </div>

                    {/* ── Mobile card list (< md) ── */}
                    <div className="divide-y divide-slate-100 md:hidden dark:divide-slate-800">
                        {items.length === 0 ? (
                            <EmptyState message={t('risks.noRisks')} />
                        ) : (
                            items.map((r) => (
                                <div key={r.id} className="space-y-3 p-4">
                                    {/* Card header */}
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <code className="text-primary dark:text-primary-200 text-[11px] font-bold">RSK-{riskRef(r)}</code>
                                            <p className="mt-0.5 text-sm font-semibold text-slate-900 dark:text-white">
                                                {r.control?.judul || t('common.noData')}
                                            </p>
                                            {r.control?.kode_klausul && (
                                                <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                                    {r.control.kode_klausul}
                                                    {r.control.framework && ` · ${r.control.framework.nama}`}
                                                </p>
                                            )}
                                        </div>
                                        <div className="shrink-0">{getRiskLevelBadge(r.risk_level || r.level_risiko)}</div>
                                    </div>

                                    {/* Status + owner */}
                                    <div className="flex flex-wrap items-center gap-2">
                                        {getMitigationStatus(r.status)}
                                        {(r.risk_owner || r.pemilik_risiko) && (
                                            <span className="inline-flex items-center gap-1 text-[11px] text-slate-500 dark:text-slate-400">
                                                <UserCheck className="h-3 w-3" />
                                                {r.risk_owner || r.pemilik_risiko}
                                            </span>
                                        )}
                                    </div>

                                    {/* Mitigation snippet */}
                                    {(r.mitigation_plan || r.rencana_mitigasi) && (
                                        <p className="line-clamp-2 text-[11px] text-slate-400 italic dark:text-slate-500">
                                            {r.mitigation_plan || r.rencana_mitigasi}
                                        </p>
                                    )}

                                    {/* Actions */}
                                    <div className="flex items-center gap-3 pt-1">
                                        <button
                                            type="button"
                                            onClick={() => setDetailTarget(r)}
                                            className="text-primary dark:text-primary-200 inline-flex items-center gap-1 text-xs font-semibold"
                                        >
                                            <Eye className="h-3.5 w-3.5" />
                                            Detail
                                        </button>
                                        {canUpdate && (
                                            <button
                                                type="button"
                                                onClick={() => openEditModal(r)}
                                                className="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                                            >
                                                <Edit2 className="h-3.5 w-3.5" />
                                                Perbarui
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    {/* ── Desktop table (≥ md) ── */}
                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full text-left text-xs sm:text-sm">
                            <thead className="border-b border-slate-200 bg-slate-50/90 text-[11px] font-bold tracking-wider text-slate-600 uppercase dark:border-slate-800 dark:bg-[#001f38] dark:text-slate-300">
                                <tr>
                                    <th scope="col" className="px-5 py-3.5">
                                        {t('risks.code')}
                                    </th>
                                    <th scope="col" className="px-5 py-3.5">
                                        {t('risks.controlClause')}
                                    </th>
                                    <th scope="col" className="px-5 py-3.5">
                                        {t('risks.levelLabel')}
                                    </th>
                                    <th scope="col" className="px-5 py-3.5">
                                        {t('risks.owner')}
                                    </th>
                                    <th scope="col" className="px-5 py-3.5">
                                        {t('risks.statusMitigation')}
                                    </th>
                                    <th scope="col" className="px-5 py-3.5 text-right">
                                        {t('risks.actions')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/70">
                                {items.length > 0 ? (
                                    items.map((r, idx) => (
                                        <tr
                                            key={r.id}
                                            className={`transition-colors ${
                                                idx % 2 === 0 ? 'bg-white dark:bg-[#00223d]/70' : 'bg-slate-50/75 dark:bg-[#00172b]/80'
                                            } hover:bg-primary-50/40 dark:hover:bg-[#0a3b63]/60`}
                                        >
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <code className="text-primary dark:text-primary-200 text-xs font-bold">RSK-{riskRef(r)}</code>
                                            </td>
                                            <td className="px-5 py-4">
                                                <button
                                                    type="button"
                                                    onClick={() => setDetailTarget(r)}
                                                    className="hover:text-primary dark:hover:text-primary-300 line-clamp-1 text-left font-semibold text-slate-900 transition-colors dark:text-white"
                                                >
                                                    {r.control?.judul || t('common.noData')}
                                                </button>
                                                <div className="mt-1 flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                                                    <span className="font-semibold text-slate-700 dark:text-slate-300">
                                                        {r.control?.kode_klausul}
                                                    </span>
                                                    {r.control?.framework && <span>· {r.control.framework.nama}</span>}
                                                </div>
                                                {(r.mitigation_plan || r.rencana_mitigasi) && (
                                                    <p className="mt-1 line-clamp-1 text-[11px] text-slate-400 italic">
                                                        Mitigasi: {r.mitigation_plan || r.rencana_mitigasi}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">{getRiskLevelBadge(r.risk_level || r.level_risiko)}</td>
                                            <td className="px-5 py-4 whitespace-nowrap text-slate-700 dark:text-slate-300">
                                                <div className="flex items-center gap-1.5">
                                                    <UserCheck className="h-3.5 w-3.5 text-slate-400" />
                                                    <span>{r.risk_owner || r.pemilik_risiko || '—'}</span>
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">{getMitigationStatus(r.status)}</td>
                                            <td className="px-5 py-4 text-right whitespace-nowrap">
                                                <div className="inline-flex items-center gap-3">
                                                    <button
                                                        type="button"
                                                        onClick={() => setDetailTarget(r)}
                                                        className="text-primary hover:text-primary-700 dark:text-primary-200 inline-flex items-center gap-1 text-xs font-semibold"
                                                    >
                                                        <Eye className="h-3.5 w-3.5" />
                                                        Detail
                                                    </button>
                                                    {canUpdate && (
                                                        <button
                                                            type="button"
                                                            onClick={() => openEditModal(r)}
                                                            className="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                                                        >
                                                            <Edit2 className="h-3.5 w-3.5" />
                                                            Perbarui
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6}>
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
            </div>

            {/* ── Update Status Modal ── */}
            <Modal
                open={editTarget !== null}
                onClose={closeEditModal}
                title={t('risks.updateTitle')}
                description={editTarget ? `RSK-${riskRef(editTarget)} · ${editTarget.control?.judul || ''}` : undefined}
            >
                {editTarget && (
                    <div className="space-y-4 pt-1">
                        {/* Status selector */}
                        <div>
                            <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                {t('risks.updateStatus')} <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={updateForm.data.status}
                                onChange={(e) => updateForm.setData('status', e.target.value)}
                                className="focus:border-primary focus:ring-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:ring-1 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="open">{t('risks.open')}</option>
                                <option value="mitigated">{t('risks.mitigated')}</option>
                                <option value="accepted">{t('risks.accepted')}</option>
                            </select>
                            {updateForm.errors.status && <p className="mt-1 text-xs text-red-500">{updateForm.errors.status}</p>}
                        </div>

                        {/* Level selector */}
                        <div>
                            <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                {t('risks.updateLevel')} <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={updateForm.data.risk_level}
                                onChange={(e) => updateForm.setData('risk_level', e.target.value)}
                                className="focus:border-primary focus:ring-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:ring-1 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="low">{t('risks.low')}</option>
                                <option value="medium">{t('risks.medium')}</option>
                                <option value="high">{t('risks.high')}</option>
                                <option value="critical">{t('risks.critical')}</option>
                            </select>
                            {updateForm.errors.risk_level && <p className="mt-1 text-xs text-red-500">{updateForm.errors.risk_level}</p>}
                        </div>

                        {/* Owner */}
                        <div>
                            <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">{t('risks.updateOwner')}</label>
                            <input
                                type="text"
                                value={updateForm.data.risk_owner}
                                onChange={(e) => updateForm.setData('risk_owner', e.target.value)}
                                placeholder={t('risks.updateOwnerPlaceholder')}
                                className="focus:border-primary focus:ring-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm placeholder:text-slate-400 focus:ring-1 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            />
                        </div>

                        {/* Mitigation plan — always visible */}
                        <div>
                            <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                {t('risks.updateMitigation')}
                            </label>
                            <textarea
                                value={updateForm.data.mitigation_plan}
                                onChange={(e) => updateForm.setData('mitigation_plan', e.target.value)}
                                placeholder={t('risks.updateMitigationPlaceholder')}
                                rows={4}
                                className="focus:border-primary focus:ring-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm placeholder:text-slate-400 focus:ring-1 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            />
                        </div>

                        {/* Notes — only shown when reverting status back to open */}
                        {needsNotes && (
                            <div className="rounded-xl border border-amber-200 bg-amber-50 p-3.5 dark:border-amber-800/60 dark:bg-amber-950/30">
                                <label className="mb-1.5 block text-xs font-semibold text-amber-800 dark:text-amber-300">
                                    {t('risks.updateNotes')} <span className="text-red-500">*</span>
                                </label>
                                <textarea
                                    rows={3}
                                    placeholder={t('risks.updateNotesPlaceholder')}
                                    className="w-full rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm placeholder:text-slate-400 focus:outline-none dark:border-amber-800/40 dark:bg-slate-800 dark:text-white"
                                />
                                <p className="mt-1.5 text-[11px] text-amber-700 dark:text-amber-400">{t('risks.updateNotesRequired')}</p>
                            </div>
                        )}

                        {/* Footer actions */}
                        <div className="flex items-center justify-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                            <button
                                type="button"
                                onClick={closeEditModal}
                                className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                {t('risks.updateCancel')}
                            </button>
                            <button
                                type="button"
                                onClick={submitUpdate}
                                disabled={updateForm.processing}
                                className="bg-primary hover:bg-primary-700 disabled:bg-primary/60 rounded-xl px-4 py-2 text-xs font-semibold text-white transition-colors"
                            >
                                {updateForm.processing ? 'Menyimpan…' : t('risks.updateSubmit')}
                            </button>
                        </div>
                    </div>
                )}
            </Modal>

            {/* Risk Detail Slide-Over Drawer */}
            <SlideOver
                open={detailTarget !== null}
                title={
                    detailTarget ? (
                        <div className="flex items-center gap-2.5">
                            <span>Detail Risiko</span>
                            <code className="text-primary bg-primary-50 border-primary-200 dark:bg-navy-900 dark:border-primary-800 dark:text-primary-200 rounded border px-2 py-0.5 text-xs font-bold">
                                RSK-{riskRef(detailTarget)}
                            </code>
                        </div>
                    ) : (
                        'Detail Risiko'
                    )
                }
                description={detailTarget?.control?.judul || 'Analisis dan rencana perlakuan risiko keamanan'}
                onClose={() => setDetailTarget(null)}
                maxWidth="xl"
                footer={
                    <div className="flex items-center gap-3">
                        {canUpdate && detailTarget && (
                            <button
                                type="button"
                                onClick={() => {
                                    setDetailTarget(null);
                                    openEditModal(detailTarget);
                                }}
                                className="bg-primary hover:bg-primary-700 inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold text-white transition-colors"
                            >
                                <Edit2 className="h-3.5 w-3.5" />
                                Perbarui Status
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={() => setDetailTarget(null)}
                            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            {t('risks.close')}
                        </button>
                    </div>
                }
            >
                {detailTarget && (
                    <div className="space-y-6">
                        {/* Top Information Cards */}
                        <div className="grid grid-cols-2 gap-3">
                            <div className="rounded-xl border border-slate-200/80 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
                                <span className="text-[11px] font-medium text-slate-400">Level Keparahan Risiko</span>
                                <div className="mt-2">{getRiskLevelBadge(detailTarget.risk_level || detailTarget.level_risiko)}</div>
                            </div>

                            <div className="rounded-xl border border-slate-200/80 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
                                <span className="text-[11px] font-medium text-slate-400">Status Mitigasi</span>
                                <div className="mt-2">{getMitigationStatus(detailTarget.status)}</div>
                            </div>
                        </div>

                        {/* Control Klausul Association */}
                        <div className="space-y-3 rounded-xl border border-slate-200/80 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <div className="flex items-center gap-2 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                <Shield className="text-primary dark:text-primary-200 h-4 w-4" />
                                <span>Kontrol SMKI Terkait</span>
                            </div>
                            <div className="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                                <div className="text-primary dark:text-primary-200 text-xs font-bold">
                                    {detailTarget.control?.kode_klausul}
                                    {detailTarget.control?.framework && (
                                        <span className="font-normal text-slate-500">
                                            {' '}
                                            · {detailTarget.control.framework.nama} ({detailTarget.control.framework.versi})
                                        </span>
                                    )}
                                </div>
                                <div className="mt-1 text-sm leading-relaxed font-semibold text-slate-900 dark:text-white">
                                    {detailTarget.control?.judul || '—'}
                                </div>
                            </div>
                        </div>

                        {/* Risk Owner Information */}
                        <div className="space-y-2 rounded-xl border border-slate-200/80 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <div className="flex items-center gap-2 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                <UserCheck className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                <span>Pemilik Risiko (Risk Owner)</span>
                            </div>
                            <p className="rounded-lg bg-slate-50 p-3 text-xs font-semibold text-slate-900 dark:bg-slate-800/60 dark:text-white">
                                {detailTarget.risk_owner || detailTarget.pemilik_risiko || 'Belum ditugaskan'}
                            </p>
                        </div>

                        {/* Mitigation Plan & Actions */}
                        <div className="space-y-2 rounded-xl border border-slate-200/80 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <div className="flex items-center gap-2 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                <FileText className="h-4 w-4 text-amber-500" />
                                <span>Rencana Tindakan Mitigasi</span>
                            </div>
                            <p className="rounded-lg bg-slate-50 p-3 text-xs leading-relaxed text-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                                {detailTarget.mitigation_plan ||
                                    detailTarget.rencana_mitigasi ||
                                    'Belum ada rencana perlakuan risiko yang didokumentasikan.'}
                            </p>
                        </div>

                        {/* Metadata Timeline */}
                        {detailTarget.created_at && (
                            <div className="border-t border-slate-100 pt-3 text-[11px] text-slate-400 dark:border-slate-800">
                                Terdaftar pada: {formatDateTimeIndonesian(detailTarget.created_at)}
                            </div>
                        )}
                    </div>
                )}
            </SlideOver>
        </AppLayout>
    );
}
