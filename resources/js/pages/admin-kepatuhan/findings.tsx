import { EmptyState } from '@/components/ui/EmptyState';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import { SlideOver } from '@/components/ui/SlideOver';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { Toast } from '@/components/ui/Toast';
import AppLayout from '@/layouts/AppLayout';
import { useCan } from '@/lib/can';
import { t } from '@/lib/i18n';
import { formatDateIndonesian, formatDateTimeIndonesian } from '@/lib/utils';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Calendar,
    CheckCircle2,
    Clock,
    Edit3,
    Eye,
    FileText,
    LayoutGrid,
    List as ListIcon,
    Save,
    Search,
    Shield,
    ShieldAlert,
    UserCheck,
} from 'lucide-react';
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

const KANBAN_COLUMNS: Array<{ status: string; label: string; dotClass: string; borderTone: string }> = [
    { status: 'open', label: 'findings.open', dotClass: 'bg-rose-500', borderTone: 'border-rose-200 dark:border-rose-900/40' },
    { status: 'in_progress', label: 'findings.inProgress', dotClass: 'bg-amber-500', borderTone: 'border-amber-200 dark:border-amber-900/40' },
    { status: 'closed', label: 'findings.closed', dotClass: 'bg-emerald-500', borderTone: 'border-emerald-200 dark:border-emerald-900/40' },
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

export default function Findings({ findings, workUnits = [], filters = {} }: FindingsProps) {
    const can = useCan();
    const { flash } = usePage<{ flash?: { type: string; message: string } }>().props;
    const [flashVisible, setFlashVisible] = useState(false);

    const page = findings ?? { data: [], current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null };
    const items = page.data;

    const [view, setView] = useState<'kanban' | 'list'>('kanban');
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedSeverity, setSelectedSeverity] = useState<string>(filters.category || filters.kategori || 'all');
    const [selectedStatus, setSelectedStatus] = useState<string>(filters.status || 'all');
    const [selectedUnit, setSelectedUnit] = useState<string>(filters.unit_id || 'all');
    const [detailTarget, setDetailTarget] = useState<FindingItem | null>(null);
    const isFirstRender = useRef(true);

    const {
        data: updateData,
        setData: setUpdateData,
        put: submitUpdate,
        processing: updateProcessing,
    } = useForm({
        status: '',
        category: '',
        deadline: '',
        admin_notes: '',
    });

    useEffect(() => {
        if (flash?.message) {
            setFlashVisible(true);
            const timer = setTimeout(() => setFlashVisible(false), 4000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    useEffect(() => {
        if (detailTarget) {
            setUpdateData({
                status: detailTarget.status || 'open',
                category: detailTarget.kategori || 'minor',
                deadline: detailTarget.deadline ? detailTarget.deadline.substring(0, 10) : '',
                admin_notes: detailTarget.admin_notes || detailTarget.catatan_admin || '',
            });
        }
    }, [detailTarget, setUpdateData]);

    function handleUpdateFinding(e: React.FormEvent) {
        e.preventDefault();
        if (!detailTarget) return;
        submitUpdate(`/findings/${detailTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDetailTarget(null);
            },
        });
    }

    const getBasePath = () => (typeof window !== 'undefined' ? window.location.pathname : '/admin/kepatuhan/findings');

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
                    category: selectedSeverity !== 'all' ? selectedSeverity : undefined,
                    status: selectedStatus !== 'all' ? selectedStatus : undefined,
                    unit_id: selectedUnit !== 'all' ? selectedUnit : undefined,
                },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [searchQuery, selectedSeverity, selectedStatus, selectedUnit]);

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/dashboard' }, { label: t('findings.title') }];

    const groupByStatus = (status: string) => items.filter((f) => f.status === status);

    const deadlineChip = (f: FindingItem) => {
        if (f.status === 'closed') {
            return (
                <span className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-400">
                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                    {f.verified_at ? `Selesai ${formatDateIndonesian(f.verified_at)}` : 'Selesai'}
                </span>
            );
        }

        if (!f.deadline) {
            return (
                <span className="inline-flex items-center gap-1 text-[11px] text-slate-400 dark:text-slate-500">
                    <Calendar className="h-3 w-3" />
                    Belum ada deadline
                </span>
            );
        }

        if (f.is_overdue) {
            const days = Math.abs(f.days_remaining ?? 0);
            return (
                <span className="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700 dark:border-rose-800 dark:bg-rose-950/60 dark:text-rose-400">
                    <Clock className="h-3.5 w-3.5 text-rose-600 dark:text-rose-400" />
                    Terlambat {days} Hari
                </span>
            );
        }

        const remaining = f.days_remaining ?? 0;
        if (remaining <= 3) {
            return (
                <span className="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-800 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                    <Clock className="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                    {remaining === 0 ? 'Hari Ini Jatuh Tempo' : `${remaining} Hari Tersisa`}
                </span>
            );
        }

        return (
            <span className="border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800/60 dark:bg-navy-900/40 dark:text-primary-200 inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[11px] font-semibold">
                <Calendar className="text-primary dark:text-primary-200 h-3.5 w-3.5" />
                {remaining} Hari Tersisa
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

    const renderStatusSteps = (status: string) => {
        const steps = [
            { id: 'open', label: 'Terbuka', desc: 'Gap teridentifikasi' },
            { id: 'in_progress', label: 'Dalam Penanganan', desc: 'PIC melakukan perbaikan' },
            { id: 'closed', label: 'Selesai / Terverifikasi', desc: 'Telah diverifikasi Admin' },
        ];

        const currentIndex = steps.findIndex((s) => s.id === status);

        return (
            <div className="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/60">
                <div className="mb-3 text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">Tahapan Progres Temuan</div>
                <div className="grid grid-cols-3 gap-2">
                    {steps.map((step, idx) => {
                        const isDone = idx < currentIndex;
                        const isCurrent = idx === currentIndex;
                        return (
                            <div
                                key={step.id}
                                className={`flex flex-col rounded-lg border p-2.5 text-left transition-all ${
                                    isCurrent
                                        ? 'border-primary bg-primary-50/80 dark:border-primary dark:bg-navy-900/50 shadow-sm'
                                        : isDone
                                          ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/40 dark:bg-emerald-950/30'
                                          : 'border-slate-200 bg-white opacity-60 dark:border-slate-800 dark:bg-slate-900'
                                }`}
                            >
                                <div className="flex items-center gap-1.5">
                                    {isDone ? (
                                        <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                    ) : isCurrent ? (
                                        <Clock className="text-primary dark:text-primary-200 h-4 w-4 shrink-0" />
                                    ) : (
                                        <div className="h-3.5 w-3.5 shrink-0 rounded-full border-2 border-slate-300 dark:border-slate-600" />
                                    )}
                                    <span
                                        className={`text-xs font-bold ${
                                            isCurrent
                                                ? 'text-navy dark:text-primary-200'
                                                : isDone
                                                  ? 'text-emerald-900 dark:text-emerald-200'
                                                  : 'text-slate-600 dark:text-slate-400'
                                        }`}
                                    >
                                        {step.label}
                                    </span>
                                </div>
                                <span className="mt-1 line-clamp-1 text-[10px] text-slate-500 dark:text-slate-400">{step.desc}</span>
                            </div>
                        );
                    })}
                </div>
            </div>
        );
    };

    const renderKanban = () => (
        <div className="grid grid-cols-1 gap-5 md:grid-cols-3">
            {KANBAN_COLUMNS.map((col) => {
                const columnItems = groupByStatus(col.status);

                return (
                    <div
                        key={col.status}
                        className="flex flex-col rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <div className="mb-3.5 flex items-center justify-between px-1">
                            <div className="flex items-center gap-2">
                                <span className={`h-2.5 w-2.5 rounded-full ${col.dotClass}`} />
                                <strong className="text-sm font-bold text-slate-900 dark:text-white">{t(col.label as never)}</strong>
                                <span className="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-bold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
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
                                        className="group hover:border-primary dark:hover:border-primary block w-full rounded-xl border border-slate-200/80 bg-white p-4 text-left shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <code className="text-primary dark:text-primary-200 text-xs font-bold">FND-{findingRef(f)}</code>
                                            <StatusBadge tone={SEVERITY_TONE[f.kategori] ?? 'gray'}>{severityLabel(f.kategori)}</StatusBadge>
                                        </div>

                                        <div className="group-hover:text-primary dark:group-hover:text-primary-300 mt-2.5 line-clamp-2 text-[13px] leading-snug font-semibold text-slate-900 transition-colors dark:text-white">
                                            {f.control?.judul || t('common.noData')}
                                        </div>

                                        <div className="mt-2 flex items-center gap-1 text-[11px] text-slate-500 dark:text-slate-400">
                                            <Shield className="h-3 w-3 text-slate-400" />
                                            <span>{f.control?.kode_klausul}</span>
                                            {f.control?.framework && <span className="text-slate-400">· {f.control.framework.nama}</span>}
                                        </div>

                                        <div className="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-2.5 text-[11px] dark:border-slate-800/80">
                                            <span className="inline-flex min-w-0 items-center gap-1.5 truncate text-slate-600 dark:text-slate-400">
                                                <span className="grid h-5 w-5 shrink-0 place-items-center rounded bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                    <ShieldAlert className="h-3 w-3" />
                                                </span>
                                                {f.unit?.nama || '—'}
                                            </span>
                                            {f.pic?.name && (
                                                <span
                                                    title={f.pic.name}
                                                    className="bg-primary grid h-6 w-6 shrink-0 place-items-center rounded-full text-[10px] font-bold text-white shadow-sm"
                                                >
                                                    {initials(f.pic.name)}
                                                </span>
                                            )}
                                        </div>

                                        <div className="mt-2.5">{deadlineChip(f)}</div>
                                    </button>
                                ))
                            ) : (
                                <div className="rounded-xl border border-dashed border-slate-200 bg-white/50 p-6 text-center dark:border-slate-800 dark:bg-slate-900/50">
                                    <span className="text-xs text-slate-400 dark:text-slate-500">Tidak ada temuan pada kolom ini</span>
                                </div>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );

    const renderList = () => (
        <section className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div className="overflow-x-auto">
                <table className="w-full text-left text-xs sm:text-sm">
                    <thead className="border-b border-slate-100 bg-slate-50/70 text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" className="px-5 py-3.5">
                                {t('findings.ref')}
                            </th>
                            <th scope="col" className="px-5 py-3.5">
                                {t('findings.judul')}
                            </th>
                            <th scope="col" className="px-5 py-3.5">
                                {t('findings.severity')}
                            </th>
                            <th scope="col" className="px-5 py-3.5">
                                {t('findings.workUnit')}
                            </th>
                            <th scope="col" className="px-5 py-3.5">
                                {t('findings.assignee')}
                            </th>
                            <th scope="col" className="px-5 py-3.5">
                                {t('findings.status')}
                            </th>
                            <th scope="col" className="px-5 py-3.5">
                                {t('findings.deadline')}
                            </th>
                            <th scope="col" className="px-5 py-3.5 text-right">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {items.length > 0 ? (
                            items.map((f) => (
                                <tr key={f.id} className="transition-colors hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                                    <td className="px-5 py-4 whitespace-nowrap">
                                        <code className="text-primary dark:text-primary-200 text-xs font-bold">FND-{findingRef(f)}</code>
                                    </td>
                                    <td className="px-5 py-4">
                                        <div className="line-clamp-1 font-semibold text-slate-900 dark:text-white">
                                            {f.control?.judul || t('common.noData')}
                                        </div>
                                        <div className="text-[11px] text-slate-500 dark:text-slate-400">
                                            {f.control?.kode_klausul} {f.control?.framework ? `· ${f.control.framework.nama}` : ''}
                                        </div>
                                    </td>
                                    <td className="px-5 py-4 whitespace-nowrap">
                                        <StatusBadge tone={SEVERITY_TONE[f.kategori] ?? 'gray'}>{severityLabel(f.kategori)}</StatusBadge>
                                    </td>
                                    <td className="px-5 py-4 whitespace-nowrap text-slate-700 dark:text-slate-300">{f.unit?.nama || '—'}</td>
                                    <td className="px-5 py-4 whitespace-nowrap text-slate-700 dark:text-slate-300">
                                        {f.pic?.name ? (
                                            <div className="flex items-center gap-1.5">
                                                <span className="bg-primary grid h-5 w-5 place-items-center rounded-full text-[9px] font-bold text-white">
                                                    {initials(f.pic.name)}
                                                </span>
                                                <span>{f.pic.name}</span>
                                            </div>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="px-5 py-4 whitespace-nowrap">
                                        <StatusBadge tone={STATUS_TONE[f.status] ?? 'gray'}>
                                            {t(`status.${f.status as 'open' | 'in_progress' | 'closed'}`)}
                                        </StatusBadge>
                                    </td>
                                    <td className="px-5 py-4 whitespace-nowrap">{deadlineChip(f)}</td>
                                    <td className="px-5 py-4 text-right whitespace-nowrap">
                                        <button
                                            type="button"
                                            onClick={() => setDetailTarget(f)}
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
                                <td colSpan={8}>
                                    <EmptyState message="Belum ada data temuan audit yang sesuai dengan filter pencarian." />
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
                        getBasePath(),
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
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/findings">
            <Head title={`${t('findings.title')} - SMKI`} />

            <Toast
                visible={flashVisible}
                tone={flash?.type === 'error' ? 'error' : 'success'}
                message={flash?.message}
                onDismiss={() => setFlashVisible(false)}
            />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col gap-4 border-b border-slate-200/80 pb-5 sm:flex-row sm:items-end sm:justify-between dark:border-slate-800">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{t('findings.title')}</h1>
                            <span className="bg-primary-50 text-primary-700 border-primary-200 dark:bg-navy-900/60 dark:border-primary-800 dark:text-primary-200 rounded-full border px-2.5 py-0.5 text-xs font-bold">
                                {page.total} Total
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                            {t('findings.subtitle')}
                            {items.some((f) => f.is_overdue) && (
                                <span className="font-bold text-rose-600 dark:text-rose-400">
                                    {' '}
                                    · {items.filter((f) => f.is_overdue).length} Temuan Melewati Batas SLA
                                </span>
                            )}
                        </p>
                    </div>

                    {/* View Switcher */}
                    <div className="flex items-center rounded-xl border border-slate-200/80 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <button
                            type="button"
                            onClick={() => setView('kanban')}
                            className={`inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-all ${
                                view === 'kanban'
                                    ? 'bg-primary text-white shadow-sm'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                            }`}
                        >
                            <LayoutGrid className="h-3.5 w-3.5" />
                            {t('findings.kanban')}
                        </button>
                        <button
                            type="button"
                            onClick={() => setView('list')}
                            className={`inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-all ${
                                view === 'list'
                                    ? 'bg-primary text-white shadow-sm'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                            }`}
                        >
                            <ListIcon className="h-3.5 w-3.5" />
                            {t('findings.list')}
                        </button>
                    </div>
                </div>

                {/* Filters */}
                <div className="flex flex-wrap items-center gap-3">
                    <div className="relative min-w-[240px] flex-1">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Cari temuan, nomor klausul, atau judul kontrol..."
                            className="focus:border-primary focus:ring-primary/20 h-10 w-full rounded-xl border border-slate-200 bg-white py-2 pr-4 pl-9 text-xs text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:outline-none sm:text-sm dark:border-slate-800 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                        />
                    </div>

                    <Select value={selectedSeverity} onChange={(e) => setSelectedSeverity(e.target.value)} className="min-w-[160px]">
                        <option value="all">Semua Tingkat Keparahan</option>
                        {SEVERITY_OPTIONS.map((s) => (
                            <option key={s} value={s}>
                                {severityLabel(s)}
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

                {/* Content View */}
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
                                getBasePath(),
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
            </div>

            {/* Detail Slide-Over Drawer */}
            <SlideOver
                open={detailTarget !== null}
                title={
                    detailTarget ? (
                        <div className="flex items-center gap-2.5">
                            <span>Detail Temuan</span>
                            <code className="text-primary bg-primary-50 border-primary-200 dark:bg-navy-900 dark:border-primary-800 dark:text-primary-200 rounded border px-2 py-0.5 text-xs font-bold">
                                FND-{findingRef(detailTarget)}
                            </code>
                        </div>
                    ) : (
                        'Detail Temuan'
                    )
                }
                description={detailTarget?.control?.judul || 'Informasi lengkap temuan audit ketidaksesuaian'}
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
                        {/* Status Progression Tracker */}
                        {renderStatusSteps(detailTarget.status)}

                        {/* Top Information Cards */}
                        <div className="grid grid-cols-2 gap-3">
                            <div className="rounded-xl border border-slate-200/80 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
                                <span className="text-[11px] font-medium text-slate-400">Tingkat Keparahan</span>
                                <div className="mt-1.5 flex items-center gap-2">
                                    <StatusBadge tone={SEVERITY_TONE[detailTarget.kategori] ?? 'gray'}>
                                        {severityLabel(detailTarget.kategori)}
                                    </StatusBadge>
                                </div>
                            </div>

                            <div className="rounded-xl border border-slate-200/80 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
                                <span className="text-[11px] font-medium text-slate-400">Status Penyelesaian</span>
                                <div className="mt-1.5 flex items-center gap-2">
                                    <StatusBadge tone={STATUS_TONE[detailTarget.status] ?? 'gray'}>
                                        {t(`status.${detailTarget.status as 'open' | 'in_progress' | 'closed'}`)}
                                    </StatusBadge>
                                </div>
                            </div>
                        </div>

                        {/* SLA & Deadline Information */}
                        <div className="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/60">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Clock className="h-4 w-4 text-slate-500 dark:text-slate-400" />
                                    <span className="text-xs font-bold text-slate-900 dark:text-white">Batas Waktu Penyelesaian (SLA)</span>
                                </div>
                                <div>{deadlineChip(detailTarget)}</div>
                            </div>
                            {detailTarget.deadline && (
                                <div className="mt-2.5 text-xs text-slate-600 dark:text-slate-400">
                                    Target Deadline:{' '}
                                    <strong className="text-slate-900 dark:text-white">{formatDateIndonesian(detailTarget.deadline)}</strong>
                                </div>
                            )}
                        </div>

                        {/* Interactive Status & Action Update Form for Authorized Users */}
                        {can('finding.update-status') && (
                            <form
                                onSubmit={handleUpdateFinding}
                                className="border-primary-200/80 bg-primary-50/50 dark:border-navy-800/50 dark:bg-navy-900/20 space-y-3.5 rounded-2xl border p-4"
                            >
                                <div className="text-navy dark:text-primary-200 flex items-center gap-2 text-xs font-bold tracking-wider uppercase">
                                    <Edit3 className="text-primary dark:text-primary-200 h-4 w-4" />
                                    <span>Perbarui Status & Tindak Lanjut</span>
                                </div>

                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                            Ubah Status Temuan
                                        </label>
                                        <select
                                            value={updateData.status}
                                            onChange={(e) => setUpdateData('status', e.target.value)}
                                            className="focus:border-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                        >
                                            <option value="open">Terbuka (Open)</option>
                                            <option value="in_progress">Dalam Penanganan (In Progress)</option>
                                            <option value="closed">Selesai / Diverifikasi (Closed)</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                            Target Deadline SLA
                                        </label>
                                        <input
                                            type="date"
                                            value={updateData.deadline}
                                            onChange={(e) => setUpdateData('deadline', e.target.value)}
                                            className="focus:border-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="mb-1 block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                        Catatan Verifikasi / Tindak Lanjut
                                    </label>
                                    <textarea
                                        value={updateData.admin_notes}
                                        onChange={(e) => setUpdateData('admin_notes', e.target.value)}
                                        rows={2}
                                        placeholder="Tuliskan catatan perbaikan atau progres penanganan..."
                                        className="focus:border-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>

                                <div className="flex justify-end pt-1">
                                    <button
                                        type="submit"
                                        disabled={updateProcessing}
                                        className="bg-primary hover:bg-primary-700 inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors disabled:opacity-50"
                                    >
                                        <Save className="h-3.5 w-3.5" />
                                        <span>{updateProcessing ? 'Menyimpan…' : 'Simpan Perubahan'}</span>
                                    </button>
                                </div>
                            </form>
                        )}

                        {/* Control Klausul Details */}
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

                        {/* Unit & Stakeholder Assignment */}
                        <div className="space-y-3 rounded-xl border border-slate-200/80 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <div className="flex items-center gap-2 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                <UserCheck className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                <span>Penanggung Jawab & Unit Kerja</span>
                            </div>
                            <div className="grid grid-cols-2 gap-3 text-xs">
                                <div className="rounded-lg border border-slate-100 p-2.5 dark:border-slate-800">
                                    <span className="text-[11px] text-slate-400">Unit Kerja / Satuan Kerja</span>
                                    <p className="mt-1 font-semibold text-slate-900 dark:text-white">{detailTarget.unit?.nama || '—'}</p>
                                </div>
                                <div className="rounded-lg border border-slate-100 p-2.5 dark:border-slate-800">
                                    <span className="text-[11px] text-slate-400">PIC Penanggung Jawab</span>
                                    <p className="mt-1 font-semibold text-slate-900 dark:text-white">{detailTarget.pic?.name || '—'}</p>
                                </div>
                            </div>
                        </div>

                        {/* Admin / Auditor Notes */}
                        {(detailTarget.admin_notes || detailTarget.catatan_admin) && (
                            <div className="space-y-2 rounded-xl border border-slate-200/80 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                                <div className="flex items-center gap-2 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    <FileText className="h-4 w-4 text-amber-500" />
                                    <span>Catatan Auditor / Admin Kepatuhan</span>
                                </div>
                                <p className="rounded-lg bg-slate-50 p-3 text-xs leading-relaxed text-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                                    {detailTarget.admin_notes || detailTarget.catatan_admin}
                                </p>
                            </div>
                        )}

                        {/* Audit Metadata Timeline */}
                        <div className="flex items-center justify-between border-t border-slate-100 pt-3 text-[11px] text-slate-400 dark:border-slate-800">
                            <span>Dibuat: {detailTarget.created_at ? formatDateTimeIndonesian(detailTarget.created_at) : '—'}</span>
                            {detailTarget.verified_at && <span>Diverifikasi: {formatDateTimeIndonesian(detailTarget.verified_at)}</span>}
                        </div>
                    </div>
                )}
            </SlideOver>
        </AppLayout>
    );
}
