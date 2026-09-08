import { EmptyState } from '@/components/ui/EmptyState';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import { SlideOver } from '@/components/ui/SlideOver';
import AppLayout from '@/layouts/AppLayout';
import { useCan } from '@/lib/can';
import { t } from '@/lib/i18n';
import { formatDateTimeIndonesian } from '@/lib/utils';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    Building2,
    Calendar,
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
    unit_id?: number | null;
    unit?: { id: number; nama: string } | null;
    deadline?: string | null;
    catatan_admin?: string | null;
    admin_notes?: string | null;
    is_overdue?: boolean;
    days_remaining?: number | null;
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

interface ControlItem {
    id: number;
    framework_id?: number;
    kode_klausul: string;
    judul: string;
    framework?: { id: number; nama: string; versi: string } | null;
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
    controls?: ControlItem[];
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

export default function Risks({ risks, matrix = {}, workUnits = [], controls = [], filters = {} }: RisksProps) {
    const page = risks ?? { data: [], current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null };
    const items = page.data;

    const auth = usePage<{ auth?: { user?: { role?: string; unit_id?: number; name?: string } } }>().props.auth;
    const authUser = auth?.user;
    const isKoordinator = authUser?.role === 'koordinator_smki';
    const isAdmin = authUser?.role === 'admin_kepatuhan' || authUser?.role === 'superadmin';
    const isPic = authUser?.role === 'pic';

    const can = useCan();
    const canUpdate = can('risk.update') || isKoordinator || isAdmin || isPic;
    const canCreate = !isPic && (isAdmin || isKoordinator || can('risk.create'));

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedLevel, setSelectedLevel] = useState<string>(filters.risk_level || filters.level_risiko || 'all');
    const [selectedStatus, setSelectedStatus] = useState<string>(filters.status || 'all');
    const [selectedUnit, setSelectedUnit] = useState<string>(filters.unit_id || 'all');
    const [detailTarget, setDetailTarget] = useState<RiskItem | null>(null);
    const [editTarget, setEditTarget] = useState<RiskItem | null>(null);
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const isFirstRender = useRef(true);

    const createForm = useForm({
        control_id: '',
        unit_id: isPic && authUser?.unit_id ? String(authUser.unit_id) : '',
        risk_level: 'low',
        risk_owner: '',
        deadline: '',
        mitigation_plan: '',
        admin_notes: '',
    });

    const updateForm = useForm({
        risk_level: '',
        status: '',
        mitigation_plan: '',
        risk_owner: '',
        deadline: '',
        admin_notes: '',
    });

    const openCreateModal = () => {
        createForm.reset();
        createForm.clearErrors();
        if (isPic && authUser?.unit_id) {
            createForm.setData('unit_id', String(authUser.unit_id));
        }
        setCreateModalOpen(true);
    };

    const closeCreateModal = () => {
        setCreateModalOpen(false);
        createForm.reset();
        createForm.clearErrors();
    };

    const submitCreate = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        createForm.post('/admin/kepatuhan/risks', {
            preserveScroll: true,
            onSuccess: () => {
                closeCreateModal();
                router.reload({ only: ['risks', 'matrix'] });
            },
        });
    };

    const openEditModal = (r: RiskItem) => {
        setEditTarget(r);
        updateForm.setData({
            risk_level: r.risk_level || r.level_risiko || 'low',
            status: r.status || 'open',
            mitigation_plan: r.mitigation_plan || r.rencana_mitigasi || '',
            risk_owner: r.risk_owner || r.pemilik_risiko || '',
            deadline: r.deadline ? String(r.deadline).split('T')[0] : '',
            admin_notes: (r.admin_notes || r.catatan_admin || '') as string,
        });
    };

    const closeEditModal = () => {
        setEditTarget(null);
        updateForm.reset();
        updateForm.clearErrors();
    };

    // Highlight note requirement when reverting to open status
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

    const getRiskLevelBadge = (level?: string) => {
        switch (level) {
            case 'critical':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700 shadow-sm dark:border-rose-800/60 dark:bg-rose-950/50 dark:text-rose-300">
                        <Flame className="h-3.5 w-3.5 text-rose-600 dark:text-rose-400" />
                        Kritis (Critical)
                    </span>
                );
            case 'high':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-800 shadow-sm dark:border-amber-800/60 dark:bg-amber-950/50 dark:text-amber-300">
                        <AlertTriangle className="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                        Tinggi (High)
                    </span>
                );
            case 'medium':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-lg border border-sky-300 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-800 dark:border-sky-800/60 dark:bg-sky-950/50 dark:text-sky-300">
                        <Activity className="h-3.5 w-3.5 text-sky-600 dark:text-sky-400" />
                        Sedang (Medium)
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        <Shield className="h-3.5 w-3.5 text-slate-500 dark:text-slate-400" />
                        Rendah (Low)
                    </span>
                );
        }
    };

    const getMitigationStatus = (status: string) => {
        if (status === 'mitigated') {
            return (
                <span className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 shadow-sm dark:border-emerald-800/60 dark:bg-emerald-950/50 dark:text-emerald-300">
                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                    Selesai Dimitigasi
                </span>
            );
        }
        if (status === 'accepted') {
            return (
                <span className="inline-flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-2.5 py-1 text-[11px] font-bold text-indigo-700 shadow-sm dark:border-indigo-800/60 dark:bg-indigo-950/50 dark:text-indigo-300">
                    <ShieldCheck className="h-3.5 w-3.5 text-indigo-600 dark:text-indigo-400" />
                    Risiko Diterima (Accepted)
                </span>
            );
        }
        return (
            <span className="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700 shadow-sm dark:border-rose-800/60 dark:bg-rose-950/50 dark:text-rose-400">
                <Clock className="h-3.5 w-3.5 text-rose-600 dark:text-rose-400" />
                Terbuka (Belum Dimitigasi)
            </span>
        );
    };

    const getDeadlineBadge = (r: RiskItem) => {
        if (!r.deadline) {
            return (
                <span className="text-xs italic text-slate-400 dark:text-slate-500">
                    Belum ditentukan
                </span>
            );
        }

        const dateStr = new Date(r.deadline).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });

        if (r.is_overdue) {
            return (
                <div className="inline-flex flex-col items-start gap-0.5">
                    <span className="inline-flex items-center gap-1 rounded-md border border-rose-200 bg-rose-50 px-2 py-0.5 text-[11px] font-bold text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-400">
                        <AlertCircle className="h-3 w-3 text-rose-600 dark:text-rose-400" />
                        Terlewat ({Math.abs(r.days_remaining ?? 0)} hari)
                    </span>
                    <span className="text-[10px] text-slate-500 dark:text-slate-400">{dateStr}</span>
                </div>
            );
        }

        if (
            r.days_remaining !== null &&
            r.days_remaining !== undefined &&
            r.days_remaining <= 3 &&
            r.days_remaining >= 0 &&
            r.status === 'open'
        ) {
            return (
                <div className="inline-flex flex-col items-start gap-0.5">
                    <span className="inline-flex items-center gap-1 rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300">
                        <Clock className="h-3 w-3 text-amber-600 dark:text-amber-400" />
                        Sisa {r.days_remaining} hari
                    </span>
                    <span className="text-[10px] text-slate-500 dark:text-slate-400">{dateStr}</span>
                </div>
            );
        }

        return (
            <div className="inline-flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-300">
                <Calendar className="h-3.5 w-3.5 text-slate-400" />
                <span>{dateStr}</span>
            </div>
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
                            <span className="rounded-full border border-primary-200 bg-primary-50 px-2.5 py-0.5 text-xs font-bold text-primary-700 dark:border-primary-800 dark:bg-navy-900/60 dark:text-primary-200">
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
                            onClick={openCreateModal}
                            className="bg-primary hover:bg-primary-700 inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition-all hover:shadow-md"
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
                                className={`flex flex-col rounded-2xl border border-slate-200/80 bg-white p-5 text-left shadow-sm transition-all hover:border-primary hover:shadow-md dark:border-slate-800 dark:bg-slate-900 ${kpi.borderClass}`}
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
                                className="h-10 w-full rounded-xl border border-slate-200 bg-white py-2 pr-4 pl-9 text-xs text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none sm:text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
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
                                            <div className="flex items-center gap-2">
                                                <code className="text-[11px] font-bold text-primary dark:text-primary-200">
                                                    RSK-{riskRef(r)}
                                                </code>
                                                {r.unit?.nama && (
                                                    <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                        <Building2 className="h-2.5 w-2.5 text-slate-400" />
                                                        {r.unit.nama}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
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

                                    {/* Status + owner + deadline */}
                                    <div className="flex flex-wrap items-center gap-2">
                                        {getMitigationStatus(r.status)}
                                        {(r.risk_owner || r.pemilik_risiko) && (
                                            <span className="inline-flex items-center gap-1 text-[11px] text-slate-500 dark:text-slate-400">
                                                <UserCheck className="h-3 w-3" />
                                                {r.risk_owner || r.pemilik_risiko}
                                            </span>
                                        )}
                                    </div>

                                    {/* Deadline badge */}
                                    <div className="pt-0.5">{getDeadlineBadge(r)}</div>

                                    {/* Mitigation snippet */}
                                    {(r.mitigation_plan || r.rencana_mitigasi) && (
                                        <p className="line-clamp-2 text-[11px] italic text-slate-500 dark:text-slate-400">
                                            Mitigasi: {r.mitigation_plan || r.rencana_mitigasi}
                                        </p>
                                    )}

                                    {/* Notes snippet if present */}
                                    {(r.catatan_admin || r.admin_notes) && (
                                        <div className="rounded-lg bg-amber-50/80 p-2 text-[11px] text-amber-900 dark:bg-amber-950/30 dark:text-amber-300">
                                            <span className="font-semibold">Catatan Evaluasi:</span> {r.catatan_admin || r.admin_notes}
                                        </div>
                                    )}

                                    {/* Actions */}
                                    <div className="flex items-center gap-3 pt-1">
                                        <button
                                            type="button"
                                            onClick={() => setDetailTarget(r)}
                                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary dark:text-primary-200"
                                        >
                                            <Eye className="h-3.5 w-3.5" />
                                            Detail
                                        </button>
                                        {canUpdate && (
                                            <button
                                                type="button"
                                                onClick={() => openEditModal(r)}
                                                className="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
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
                                        Tenggat Waktu
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
                                            <td className="whitespace-nowrap px-5 py-4">
                                                <code className="text-xs font-bold text-primary dark:text-primary-200">
                                                    RSK-{riskRef(r)}
                                                </code>
                                            </td>
                                            <td className="px-5 py-4">
                                                <button
                                                    type="button"
                                                    onClick={() => setDetailTarget(r)}
                                                    className="line-clamp-1 text-left font-semibold text-slate-900 transition-colors hover:text-primary dark:text-white dark:hover:text-primary-300"
                                                >
                                                    {r.control?.judul || t('common.noData')}
                                                </button>
                                                <div className="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                                                    <span className="font-semibold text-slate-700 dark:text-slate-300">
                                                        {r.control?.kode_klausul}
                                                    </span>
                                                    {r.control?.framework && <span>· {r.control.framework.nama}</span>}
                                                    {r.unit?.nama && (
                                                        <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.2 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                            <Building2 className="h-2.5 w-2.5 text-slate-400" />
                                                            {r.unit.nama}
                                                        </span>
                                                    )}
                                                </div>
                                                {(r.mitigation_plan || r.rencana_mitigasi) && (
                                                    <p className="mt-1 line-clamp-1 text-[11px] italic text-slate-400">
                                                        Mitigasi: {r.mitigation_plan || r.rencana_mitigasi}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-5 py-4">{getRiskLevelBadge(r.risk_level || r.level_risiko)}</td>
                                            <td className="whitespace-nowrap px-5 py-4 text-slate-700 dark:text-slate-300">
                                                <div className="flex items-center gap-1.5">
                                                    <UserCheck className="h-3.5 w-3.5 text-slate-400" />
                                                    <span>{r.risk_owner || r.pemilik_risiko || '—'}</span>
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-5 py-4">{getDeadlineBadge(r)}</td>
                                            <td className="whitespace-nowrap px-5 py-4">{getMitigationStatus(r.status)}</td>
                                            <td className="whitespace-nowrap px-5 py-4 text-right">
                                                <div className="inline-flex items-center gap-3">
                                                    <button
                                                        type="button"
                                                        onClick={() => setDetailTarget(r)}
                                                        className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:text-primary-700 dark:text-primary-200"
                                                    >
                                                        <Eye className="h-3.5 w-3.5" />
                                                        Detail
                                                    </button>
                                                    {canUpdate && (
                                                        <button
                                                            type="button"
                                                            onClick={() => openEditModal(r)}
                                                            className="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
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
            </div>

            {/* ── Create Risk Modal ── */}
            <Modal
                open={createModalOpen}
                onClose={closeCreateModal}
                title={t('risks.createTitle')}
                description={t('risks.createDesc')}
            >
                <form onSubmit={submitCreate} className="space-y-4 pt-1">
                    {/* Kontrol SMKI */}
                    <div>
                        <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            {t('risks.controlSelect')} <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={createForm.data.control_id}
                            onChange={(e) => createForm.setData('control_id', e.target.value)}
                            required
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        >
                            <option value="">{t('risks.controlSelectPlaceholder')}</option>
                            {controls.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.kode_klausul} - {c.judul} {c.framework ? `(${c.framework.nama})` : ''}
                                </option>
                            ))}
                        </select>
                        {createForm.errors.control_id && (
                            <p className="mt-1 text-xs text-red-500">{createForm.errors.control_id}</p>
                        )}
                    </div>

                    {/* Unit Kerja */}
                    <div>
                        <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            {t('risks.unitSelect')}
                        </label>
                        <select
                            value={createForm.data.unit_id}
                            onChange={(e) => createForm.setData('unit_id', e.target.value)}
                            disabled={isPic && !!authUser?.unit_id}
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:disabled:bg-slate-800/50"
                        >
                            <option value="">{t('risks.unitSelectPlaceholder')}</option>
                            {workUnits.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.nama}
                                </option>
                            ))}
                        </select>
                        {createForm.errors.unit_id && (
                            <p className="mt-1 text-xs text-red-500">{createForm.errors.unit_id}</p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {/* Level selector */}
                        <div>
                            <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                {t('risks.updateLevel')} <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={createForm.data.risk_level}
                                onChange={(e) => createForm.setData('risk_level', e.target.value)}
                                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="low">{t('risks.low')}</option>
                                <option value="medium">{t('risks.medium')}</option>
                                <option value="high">{t('risks.high')}</option>
                                <option value="critical">{t('risks.critical')}</option>
                            </select>
                            {createForm.errors.risk_level && (
                                <p className="mt-1 text-xs text-red-500">{createForm.errors.risk_level}</p>
                            )}
                        </div>

                        {/* Custom Deadline */}
                        <div>
                            <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                {t('risks.deadline')}
                            </label>
                            <input
                                type="date"
                                value={createForm.data.deadline}
                                onChange={(e) => createForm.setData('deadline', e.target.value)}
                                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            />
                            {createForm.errors.deadline && (
                                <p className="mt-1 text-xs text-red-500">{createForm.errors.deadline}</p>
                            )}
                        </div>
                    </div>

                    {/* Owner */}
                    <div>
                        <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            {t('risks.updateOwner')}
                        </label>
                        <input
                            type="text"
                            value={createForm.data.risk_owner}
                            onChange={(e) => createForm.setData('risk_owner', e.target.value)}
                            placeholder={t('risks.updateOwnerPlaceholder')}
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm placeholder:text-slate-400 focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        {createForm.errors.risk_owner && (
                            <p className="mt-1 text-xs text-red-500">{createForm.errors.risk_owner}</p>
                        )}
                    </div>

                    {/* Mitigation plan */}
                    <div>
                        <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            {t('risks.updateMitigation')}
                        </label>
                        <textarea
                            value={createForm.data.mitigation_plan}
                            onChange={(e) => createForm.setData('mitigation_plan', e.target.value)}
                            placeholder={t('risks.updateMitigationPlaceholder')}
                            rows={3}
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm placeholder:text-slate-400 focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        {createForm.errors.mitigation_plan && (
                            <p className="mt-1 text-xs text-red-500">{createForm.errors.mitigation_plan}</p>
                        )}
                    </div>

                    {/* Admin notes */}
                    <div>
                        <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            {t('risks.adminNotes')}
                        </label>
                        <textarea
                            value={createForm.data.admin_notes}
                            onChange={(e) => createForm.setData('admin_notes', e.target.value)}
                            placeholder={t('risks.adminNotesPlaceholder')}
                            rows={2}
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm placeholder:text-slate-400 focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        {createForm.errors.admin_notes && (
                            <p className="mt-1 text-xs text-red-500">{createForm.errors.admin_notes}</p>
                        )}
                    </div>

                    {/* Footer actions */}
                    <div className="flex items-center justify-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <button
                            type="button"
                            onClick={closeCreateModal}
                            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            {t('risks.updateCancel')}
                        </button>
                        <button
                            type="submit"
                            disabled={createForm.processing}
                            className="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-primary-700 disabled:bg-primary/60"
                        >
                            {createForm.processing ? 'Menyimpan…' : t('risks.createSubmit')}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* ── Update Status Modal ── */}
            <Modal
                open={editTarget !== null}
                onClose={closeEditModal}
                title={t('risks.updateTitle')}
                description={editTarget ? `RSK-${riskRef(editTarget)} · ${editTarget.control?.judul || ''}` : undefined}
            >
                {editTarget && (
                    <div className="space-y-4 pt-1">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            {/* Status selector */}
                            <div>
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    {t('risks.updateStatus')} <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={updateForm.data.status}
                                    onChange={(e) => updateForm.setData('status', e.target.value)}
                                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-700 dark:bg-slate-800 dark:text-white"
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
                                    {t('risks.updateLevel')} {!isPic && <span className="text-red-500">*</span>}
                                </label>
                                {isPic ? (
                                    <div className="flex h-[38px] items-center rounded-xl border border-slate-200 bg-slate-100/70 px-3 text-sm dark:border-slate-700 dark:bg-slate-800/60">
                                        {getRiskLevelBadge(updateForm.data.risk_level)}
                                    </div>
                                ) : (
                                    <select
                                        value={updateForm.data.risk_level}
                                        onChange={(e) => updateForm.setData('risk_level', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    >
                                        <option value="low">{t('risks.low')}</option>
                                        <option value="medium">{t('risks.medium')}</option>
                                        <option value="high">{t('risks.high')}</option>
                                        <option value="critical">{t('risks.critical')}</option>
                                    </select>
                                )}
                                {updateForm.errors.risk_level && <p className="mt-1 text-xs text-red-500">{updateForm.errors.risk_level}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            {/* Owner */}
                            <div>
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">{t('risks.updateOwner')}</label>
                                <input
                                    type="text"
                                    value={updateForm.data.risk_owner}
                                    onChange={(e) => updateForm.setData('risk_owner', e.target.value)}
                                    disabled={isPic}
                                    placeholder={t('risks.updateOwnerPlaceholder')}
                                    className={`w-full rounded-xl border px-3 py-2 text-sm ${
                                        isPic
                                            ? 'cursor-not-allowed border-slate-200 bg-slate-100/70 text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400'
                                            : 'border-slate-200 bg-white placeholder:text-slate-400 focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-700 dark:bg-slate-800 dark:text-white'
                                    }`}
                                />
                            </div>

                            {/* Custom Deadline */}
                            <div>
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    {t('risks.deadline')}
                                </label>
                                <input
                                    type="date"
                                    value={updateForm.data.deadline}
                                    onChange={(e) => updateForm.setData('deadline', e.target.value)}
                                    disabled={isPic}
                                    className={`w-full rounded-xl border px-3 py-2 text-sm ${
                                        isPic
                                            ? 'cursor-not-allowed border-slate-200 bg-slate-100/70 text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400'
                                            : 'border-slate-200 bg-white text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-700 dark:bg-slate-800 dark:text-white'
                                    }`}
                                />
                            </div>
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
                                rows={3}
                                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm placeholder:text-slate-400 focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            />
                        </div>

                        {/* Admin notes — editable for Admin/Koordinator, read-only/hidden for PIC */}
                        {!isPic ? (
                            <div
                                className={`rounded-xl border p-3.5 ${
                                    needsNotes
                                        ? 'border-amber-200 bg-amber-50 dark:border-amber-800/60 dark:bg-amber-950/30'
                                        : 'border-slate-200/80 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-800/30'
                                }`}
                            >
                                <label
                                    className={`mb-1.5 block text-xs font-semibold ${
                                        needsNotes ? 'text-amber-800 dark:text-amber-300' : 'text-slate-700 dark:text-slate-300'
                                    }`}
                                >
                                    {t('risks.updateNotes')} {needsNotes && <span className="text-red-500">*</span>}
                                </label>
                                <textarea
                                    rows={2}
                                    value={updateForm.data.admin_notes}
                                    onChange={(e) => updateForm.setData('admin_notes', e.target.value)}
                                    placeholder={t('risks.updateNotesPlaceholder')}
                                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm placeholder:text-slate-400 focus:border-primary focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                />
                                {needsNotes && (
                                    <p className="mt-1.5 text-[11px] text-amber-700 dark:text-amber-400">{t('risks.updateNotesRequired')}</p>
                                )}
                            </div>
                        ) : updateForm.data.admin_notes ? (
                            <div className="rounded-xl border border-slate-200/80 bg-slate-50/70 p-3.5 dark:border-slate-800 dark:bg-slate-800/30">
                                <label className="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">
                                    Catatan Admin
                                </label>
                                <p className="text-xs text-slate-700 dark:text-slate-300 whitespace-pre-wrap">
                                    {updateForm.data.admin_notes}
                                </p>
                            </div>
                        ) : null}

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
                                className="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-primary-700 disabled:bg-primary/60"
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
                            <code className="rounded border border-primary-200 bg-primary-50 px-2 py-0.5 text-xs font-bold text-primary dark:border-primary-800 dark:bg-navy-900 dark:text-primary-200">
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
                                    const target = detailTarget;
                                    setDetailTarget(null);
                                    openEditModal(target);
                                }}
                                className="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-primary-700"
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
                                <Shield className="h-4 w-4 text-primary dark:text-primary-200" />
                                <span>Kontrol SMKI Terkait</span>
                            </div>
                            <div className="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                                <div className="text-xs font-bold text-primary dark:text-primary-200">
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

                        {/* Unit Kerja */}
                        {detailTarget.unit?.nama && (
                            <div className="space-y-2 rounded-xl border border-slate-200/80 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                                <div className="flex items-center gap-2 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    <Building2 className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                                    <span>Satuan Kerja / Unit</span>
                                </div>
                                <p className="rounded-lg bg-slate-50 p-3 text-xs font-semibold text-slate-900 dark:bg-slate-800/60 dark:text-white">
                                    {detailTarget.unit.nama}
                                </p>
                            </div>
                        )}

                        {/* Tenggat Waktu (Custom Deadline) */}
                        <div className="space-y-2 rounded-xl border border-slate-200/80 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <div className="flex items-center gap-2 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                <Calendar className="h-4 w-4 text-rose-500" />
                                <span>Tenggat Waktu Mitigasi (SLA)</span>
                            </div>
                            <div className="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                                {getDeadlineBadge(detailTarget)}
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

                        {/* Catatan Admin / Evaluasi */}
                        {(detailTarget.catatan_admin || detailTarget.admin_notes) && (
                            <div className="space-y-2 rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-900/50 dark:bg-amber-950/30">
                                <div className="flex items-center gap-2 text-xs font-bold tracking-wider text-amber-800 uppercase dark:text-amber-300">
                                    <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                    <span>Catatan Evaluasi / Admin</span>
                                </div>
                                <p className="text-xs leading-relaxed text-amber-900 dark:text-amber-200">
                                    {detailTarget.catatan_admin || detailTarget.admin_notes}
                                </p>
                            </div>
                        )}

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
