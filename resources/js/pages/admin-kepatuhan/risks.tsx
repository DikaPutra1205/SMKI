import { EmptyState } from '@/components/ui/EmptyState';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import { SlideOver } from '@/components/ui/SlideOver';
import AppLayout from '@/layouts/AppLayout';
import { t } from '@/lib/i18n';
import { formatDateTimeIndonesian } from '@/lib/utils';
import { Head, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Clock,
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

                    <button
                        type="button"
                        disabled
                        className="inline-flex cursor-not-allowed items-center gap-2 rounded-xl border border-slate-200 bg-slate-100/80 px-4 py-2 text-xs font-semibold text-slate-400 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-500"
                        title="Pendaftaran risiko baru dilakukan melalui verifikasi temuan audit"
                    >
                        <Plus className="h-4 w-4" />
                        <span>Daftarkan Risiko Baru</span>
                    </button>
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

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs sm:text-sm">
                            <thead className="border-b border-slate-100 bg-slate-50/70 text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                                <tr>
                                    <th scope="col" className="px-5 py-3.5">
                                        {t('risks.code')}
                                    </th>
                                    <th scope="col" className="px-5 py-3.5">
                                        Klausul Kontrol & Risiko
                                    </th>
                                    <th scope="col" className="px-5 py-3.5">
                                        Level Risiko
                                    </th>
                                    <th scope="col" className="px-5 py-3.5">
                                        Pemilik Risiko (Owner)
                                    </th>
                                    <th scope="col" className="px-5 py-3.5">
                                        Status Mitigasi
                                    </th>
                                    <th scope="col" className="px-5 py-3.5 text-right">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {items.length > 0 ? (
                                    items.map((r) => (
                                        <tr key={r.id} className="transition-colors hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
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
                                                {r.mitigation_plan || r.rencana_mitigasi ? (
                                                    <p className="mt-1 line-clamp-1 text-[11px] text-slate-400 italic">
                                                        Mitigasi: {r.mitigation_plan || r.rencana_mitigasi}
                                                    </p>
                                                ) : null}
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
                                                <button
                                                    type="button"
                                                    onClick={() => setDetailTarget(r)}
                                                    className="text-primary hover:text-primary-700 dark:text-primary-200 dark:hover:text-primary-200 inline-flex items-center gap-1 text-xs font-semibold"
                                                >
                                                    <Eye className="h-3.5 w-3.5" />
                                                    Detail
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6}>
                                            <EmptyState message="Belum ada data risiko keamanan informasi yang terdaftar." />
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
                    <button
                        type="button"
                        onClick={() => setDetailTarget(null)}
                        className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        Tutup Panel
                    </button>
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
