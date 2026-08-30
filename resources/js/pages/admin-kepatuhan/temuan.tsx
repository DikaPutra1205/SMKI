import { EmptyState } from '@/components/ui/EmptyState';
import { Modal } from '@/components/ui/Modal';
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
    ArrowRight,
    Calendar,
    CheckCircle2,
    Clock,
    Edit3,
    Eye,
    FileText,
    History,
    Info,
    LayoutGrid,
    List as ListIcon,
    MessageSquare,
    Plus,
    RotateCcw,
    Save,
    Search,
    Shield,
    ShieldAlert,
    UserCheck,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface FindingHistoryItem {
    id: number;
    finding_id: number;
    user_id: number | null;
    from_status: string | null;
    to_status: string;
    catatan: string;
    created_at: string;
    user?: {
        id: number;
        name: string;
        role?: string | { id: number; name: string; label: string } | null;
        unit?: { id: number; nama: string } | null;
    } | null;
}

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
    histories?: FindingHistoryItem[];
    [key: string]: unknown;
}

interface WorkUnitItem {
    id: number;
    nama: string;
}

interface ControlItem {
    id: number;
    framework_id: number;
    kode_klausul: string;
    judul: string;
    framework?: { id: number; nama: string; versi: string } | null;
}

interface PicUserItem {
    id: number;
    name: string;
    unit_id?: number | null;
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
    controls?: ControlItem[];
    pics?: PicUserItem[];
    filters?: {
        status?: string;
        category?: string;
        kategori?: string;
        unit_id?: string;
        search?: string;
    };
}

interface AuthUser {
    id: number;
    name: string;
    role?: string | { id: number; name: string; label: string } | null;
    unit_id?: number | null;
}

const SEVERITY_OPTIONS = ['major', 'minor', 'observasi'] as const;
const STATUS_OPTIONS = ['open', 'in_progress', 'resolved', 'closed'] as const;

const KANBAN_COLUMNS: Array<{
    status: string;
    label: string;
    dotClass: string;
    badgeTone: 'red' | 'amber' | 'blue' | 'green';
    headerBg: string;
}> = [
    {
        status: 'open',
        label: 'temuan.open',
        dotClass: 'bg-rose-500',
        badgeTone: 'red',
        headerBg: 'border-rose-200/80 bg-rose-50/50 dark:border-rose-900/30 dark:bg-rose-950/20',
    },
    {
        status: 'in_progress',
        label: 'temuan.inProgress',
        dotClass: 'bg-amber-500',
        badgeTone: 'amber',
        headerBg: 'border-amber-200/80 bg-amber-50/50 dark:border-amber-900/30 dark:bg-amber-950/20',
    },
    {
        status: 'resolved',
        label: 'temuan.resolved',
        dotClass: 'bg-blue-500',
        badgeTone: 'blue',
        headerBg: 'border-blue-200/80 bg-blue-50/50 dark:border-blue-900/30 dark:bg-blue-950/20',
    },
    {
        status: 'closed',
        label: 'temuan.closed',
        dotClass: 'bg-emerald-500',
        badgeTone: 'green',
        headerBg: 'border-emerald-200/80 bg-emerald-50/50 dark:border-emerald-900/30 dark:bg-emerald-950/20',
    },
];

const SEVERITY_TONE: Record<string, 'red' | 'amber' | 'blue'> = {
    major: 'red',
    minor: 'amber',
    observasi: 'blue',
};

const STATUS_TONE: Record<string, 'red' | 'amber' | 'blue' | 'green'> = {
    open: 'red',
    in_progress: 'amber',
    resolved: 'blue',
    closed: 'green',
};

const STATUS_TEXT: Record<string, string> = {
    open: 'Terbuka',
    in_progress: 'Dalam Penanganan',
    resolved: 'Selesai Ditindaklanjuti',
    closed: 'Ditutup & Terverifikasi',
};

const STEPS = [
    { id: 'open', label: 'Terbuka', desc: 'Gap/temuan dicatat' },
    { id: 'in_progress', label: 'Dalam Penanganan', desc: 'PIC melakukan tindakan perbaikan' },
    { id: 'resolved', label: 'Selesai Ditindaklanjuti', desc: 'PIC telah menyelesaikan perbaikan' },
    { id: 'closed', label: 'Ditutup & Diverifikasi', desc: 'Admin memverifikasi dan menutup temuan' },
];

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
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function getRoleName(role?: string | { id: number; name: string; label: string } | null): string {
    if (!role) return 'Pengguna';
    if (typeof role === 'string') {
        if (role === 'admin_kepatuhan') return 'Admin Kepatuhan';
        if (role === 'superadmin') return 'Super Admin';
        if (role === 'pic') return 'PIC Satker';
        if (role === 'auditor') return 'Auditor';
        if (role === 'koordinator_smki') return 'Koordinator SMKI';
        return role;
    }
    return role.label || role.name || 'Pengguna';
}

function initials(name?: string) {
    return (name || '')
        .split(' ')
        .map((n) => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
}

export default function Findings({ findings, workUnits = [], controls = [], pics = [], filters = {} }: FindingsProps) {
    const can = useCan();
    const pageProps = usePage<{ auth?: { user?: AuthUser }; flash?: { type: string; message: string } }>().props;
    const authUser = pageProps.auth?.user;
    const flash = pageProps.flash;

    const [flashVisible, setFlashVisible] = useState(false);
    const [view, setView] = useState<'kanban' | 'list'>('kanban');
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedSeverity, setSelectedSeverity] = useState<string>(filters.category || filters.kategori || 'all');
    const [selectedStatus, setSelectedStatus] = useState<string>(filters.status || 'all');
    const [selectedUnit, setSelectedUnit] = useState<string>(filters.unit_id || 'all');
    const [detailTarget, setDetailTarget] = useState<FindingItem | null>(null);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const isFirstRender = useRef(true);

    const page = findings ?? { data: [], current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null };
    const items = page.data;

    // Check permissions
    const userRoleStr = typeof authUser?.role === 'string' ? authUser.role : authUser?.role?.name;
    const isAdmin = userRoleStr === 'admin_kepatuhan' || userRoleStr === 'superadmin' || can('finding.create');
    const isUserPic = userRoleStr === 'pic';

    // Status Update Form
    const {
        data: updateData,
        setData: setUpdateData,
        put: submitUpdate,
        processing: updateProcessing,
        errors: updateErrors,
    } = useForm({
        status: '',
        category: '',
        deadline: '',
        catatan: '',
    });

    // Create Finding Form
    const {
        data: createData,
        setData: setCreateData,
        post: submitCreate,
        processing: createProcessing,
        errors: createErrors,
        reset: resetCreateForm,
    } = useForm({
        control_id: '',
        unit_id: '',
        pic_id: '',
        kategori: 'observasi',
        status: 'open',
        deadline: '',
        catatan: '',
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
                catatan: '',
            });
        }
    }, [detailTarget, setUpdateData]);

    // Keep detailTarget fresh after props change
    useEffect(() => {
        if (detailTarget) {
            const fresh = items.find((f) => f.id === detailTarget.id);
            if (fresh && fresh !== detailTarget) {
                setDetailTarget(fresh);
            }
        }
    }, [items, detailTarget]);

    function handleUpdateFinding(e: React.FormEvent) {
        e.preventDefault();
        if (!detailTarget) return;

        submitUpdate(`/temuan/${detailTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setUpdateData('catatan', '');
            },
        });
    }

    function handleCreateFinding(e: React.FormEvent) {
        e.preventDefault();
        submitCreate('/temuan', {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateModalOpen(false);
                resetCreateForm();
            },
        });
    }

    const getBasePath = () => (typeof window !== 'undefined' ? window.location.pathname : '/temuan');

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

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/dashboard' }, { label: t('temuan.title') }];

    const groupByStatus = (status: string) => items.filter((f) => f.status === status);

    const deadlineChip = (f: FindingItem) => {
        if (f.status === 'closed') {
            return (
                <span className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400">
                    <CheckCircle2 className="h-3.5 w-3.5" />
                    {f.verified_at ? t('temuan.verifiedOn', fmtDate(f.verified_at)) : 'Selesai & Terverifikasi'}
                </span>
            );
        }

        if (f.status === 'resolved') {
            return (
                <span className="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
                    <Clock className="h-3.5 w-3.5" />
                    Menunggu Verifikasi Admin
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
            return (
                <span className="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-400">
                    <Clock className="h-3.5 w-3.5 text-rose-600" />
                    {t('temuan.lateDays', Math.abs(f.days_remaining ?? 0))}
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
                {remaining === 0 ? t('temuan.deadlineToday') : t('temuan.leftDays', remaining)}
            </span>
        );
    };

    // Check if the auth user can update this specific finding
    const canUpdateThisFinding = (f: FindingItem | null) => {
        if (!f) return false;
        if (isAdmin) return true;
        if (isUserPic && authUser?.unit_id && f.unit?.id && (authUser.unit_id === f.unit.id || authUser.id === f.pic?.id)) {
            return true;
        }
        return false;
    };

    const renderStatusSteps = (status: string) => {
        const currentIndex = STEPS.findIndex((s) => s.id === status);

        return (
            <div className="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                <div className="mb-3 flex items-center justify-between">
                    <span className="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                        Tahapan Siklus Temuan (4 Status)
                    </span>
                    <span className="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                        Langkah {currentIndex >= 0 ? currentIndex + 1 : 1} dari 4
                    </span>
                </div>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    {STEPS.map((step, idx) => {
                        const isDone = idx < currentIndex;
                        const isCurrent = idx === currentIndex;
                        return (
                            <div
                                key={step.id}
                                className={`flex flex-col rounded-xl border p-3 text-left transition-all ${
                                    isCurrent
                                        ? 'border-primary ring-primary/20 dark:border-primary bg-white shadow-sm ring-2 dark:bg-slate-800'
                                        : isDone
                                          ? 'border-emerald-200 bg-emerald-50/70 dark:border-emerald-900/40 dark:bg-emerald-950/30'
                                          : 'border-slate-200/80 bg-white/60 opacity-60 dark:border-slate-800 dark:bg-slate-900/40'
                                }`}
                            >
                                <div className="flex items-center gap-1.5">
                                    {isDone ? (
                                        <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                    ) : isCurrent ? (
                                        <Clock className="text-primary dark:text-primary-300 h-4 w-4 shrink-0" />
                                    ) : (
                                        <div className="h-3.5 w-3.5 shrink-0 rounded-full border-2 border-slate-300 dark:border-slate-600" />
                                    )}
                                    <span
                                        className={`text-xs font-bold ${
                                            isCurrent
                                                ? 'text-primary dark:text-primary-200'
                                                : isDone
                                                  ? 'text-emerald-900 dark:text-emerald-200'
                                                  : 'text-slate-600 dark:text-slate-400'
                                        }`}
                                    >
                                        {step.label}
                                    </span>
                                </div>
                                <span className="mt-1 line-clamp-2 text-[10px] text-slate-500 dark:text-slate-400">{step.desc}</span>
                            </div>
                        );
                    })}
                </div>
            </div>
        );
    };

    const renderKanban = () => (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            {KANBAN_COLUMNS.map((col) => {
                const columnItems = groupByStatus(col.status);

                return (
                    <div key={col.status} className={`flex flex-col rounded-2xl border p-3.5 shadow-sm transition-colors ${col.headerBg}`}>
                        <div className="mb-3 flex items-center justify-between px-1">
                            <div className="flex items-center gap-2">
                                <span className={`h-2.5 w-2.5 rounded-full ${col.dotClass}`} />
                                <strong className="text-xs font-bold tracking-tight text-slate-900 sm:text-sm dark:text-white">
                                    {t(col.label as never)}
                                </strong>
                            </div>
                            <span className="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-bold text-slate-700 shadow-2xs dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                {columnItems.length}
                            </span>
                        </div>

                        <div className="space-y-3">
                            {columnItems.length > 0 ? (
                                columnItems.map((f) => (
                                    <button
                                        key={f.id}
                                        type="button"
                                        onClick={() => setDetailTarget(f)}
                                        className="group hover:border-primary dark:hover:border-primary block w-full rounded-xl border border-slate-200/80 bg-white p-3.5 text-left shadow-2xs transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <code className="text-primary dark:text-primary-200 text-xs font-bold">FND-{findingRef(f)}</code>
                                            <StatusBadge tone={SEVERITY_TONE[f.kategori] ?? 'gray'}>{severityLabel(f.kategori)}</StatusBadge>
                                        </div>

                                        <div className="group-hover:text-primary dark:group-hover:text-primary-300 mt-2 line-clamp-2 text-xs leading-snug font-semibold text-slate-900 transition-colors sm:text-sm dark:text-white">
                                            {f.control?.judul || t('common.noData')}
                                        </div>

                                        <div className="mt-1.5 flex items-center gap-1 text-[11px] text-slate-500 dark:text-slate-400">
                                            <Shield className="h-3 w-3 shrink-0 text-slate-400" />
                                            <span className="font-semibold">{f.control?.kode_klausul}</span>
                                            {f.control?.framework && <span className="truncate text-slate-400">· {f.control.framework.nama}</span>}
                                        </div>

                                        <div className="mt-2.5 flex items-center justify-between gap-2 border-t border-slate-100 pt-2 text-[11px] dark:border-slate-800/80">
                                            <span className="inline-flex min-w-0 items-center gap-1.5 truncate text-slate-600 dark:text-slate-400">
                                                <span className="grid h-4.5 w-4.5 shrink-0 place-items-center rounded bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                    <ShieldAlert className="h-3 w-3" />
                                                </span>
                                                <span className="truncate font-medium">{f.unit?.nama || '—'}</span>
                                            </span>
                                            {f.pic?.name && (
                                                <span
                                                    title={`PIC: ${f.pic.name}`}
                                                    className="bg-primary grid h-5 w-5 shrink-0 place-items-center rounded-full text-[9px] font-bold text-white shadow-2xs"
                                                >
                                                    {initials(f.pic.name)}
                                                </span>
                                            )}
                                        </div>

                                        <div className="mt-2 flex items-center justify-between">
                                            <div>{deadlineChip(f)}</div>
                                            {f.histories && f.histories.length > 0 && (
                                                <span className="inline-flex items-center gap-1 text-[10px] text-slate-400 dark:text-slate-500">
                                                    <MessageSquare className="h-3 w-3" />
                                                    {f.histories.length}
                                                </span>
                                            )}
                                        </div>
                                    </button>
                                ))
                            ) : (
                                <div className="rounded-xl border border-dashed border-slate-200/80 bg-white/40 p-5 text-center dark:border-slate-800 dark:bg-slate-900/30">
                                    <span className="text-xs text-slate-400 dark:text-slate-500">Tidak ada temuan</span>
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
                    <thead className="border-b border-slate-200 bg-slate-50/90 text-[11px] font-bold tracking-wider text-slate-600 uppercase dark:border-slate-800 dark:bg-[#001f38] dark:text-slate-300">
                        <tr>
                            <th scope="col" className="px-5 py-3.5 text-left font-semibold">
                                {t('temuan.ref')}
                            </th>
                            <th scope="col" className="px-5 py-3.5 text-left font-semibold">
                                {t('temuan.judul')}
                            </th>
                            <th scope="col" className="px-5 py-3.5 text-left font-semibold">
                                {t('temuan.severity')}
                            </th>
                            <th scope="col" className="px-5 py-3.5 text-left font-semibold">
                                {t('temuan.workUnitPic')}
                            </th>
                            <th scope="col" className="px-5 py-3.5 text-left font-semibold">
                                {t('temuan.status')}
                            </th>
                            <th scope="col" className="px-5 py-3.5 text-left font-semibold">
                                {t('temuan.deadline')}
                            </th>
                            <th scope="col" className="px-5 py-3.5 text-right font-semibold">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800/70">
                        {items.length > 0 ? (
                            items.map((f, idx) => (
                                <tr
                                    key={f.id}
                                    className={`transition-colors ${
                                        idx % 2 === 0 ? 'bg-white dark:bg-[#00223d]/70' : 'bg-slate-50/75 dark:bg-[#00172b]/80'
                                    } hover:bg-primary-50/40 dark:hover:bg-[#0a3b63]/60`}
                                >
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
                                    <td className="px-5 py-4 whitespace-nowrap">
                                        <div className="font-medium text-slate-900 dark:text-white">{f.unit?.nama || '—'}</div>
                                        <div className="mt-1 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                            {f.pic?.name ? (
                                                <>
                                                    <span className="bg-primary grid h-4 w-4 shrink-0 place-items-center rounded-full text-[8px] font-bold text-white">
                                                        {initials(f.pic.name)}
                                                    </span>
                                                    <span>{f.pic.name}</span>
                                                </>
                                            ) : (
                                                <span className="text-slate-400">—</span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-5 py-4 whitespace-nowrap">
                                        <StatusBadge tone={STATUS_TONE[f.status] ?? 'gray'}>{STATUS_TEXT[f.status] ?? f.status}</StatusBadge>
                                    </td>
                                    <td className="px-5 py-4 whitespace-nowrap">{deadlineChip(f)}</td>
                                    <td className="px-5 py-4 text-right whitespace-nowrap">
                                        <button
                                            type="button"
                                            onClick={() => setDetailTarget(f)}
                                            className="text-primary hover:text-primary-700 dark:text-primary-300 dark:hover:text-primary-200 inline-flex items-center gap-1 text-xs font-semibold"
                                        >
                                            <Eye className="h-3.5 w-3.5" />
                                            Detail & Review
                                        </button>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={7}>
                                    <EmptyState message={t('temuan.noFindings')} />
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
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/temuan">
            <Head title={`${t('temuan.title')} - Sistem Kepatuhan SMKI`} />

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
                        <div className="flex items-center gap-2.5">
                            <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{t('temuan.title')}</h1>
                            <span className="bg-primary-50 text-primary-700 border-primary-200 dark:bg-navy-900/60 dark:border-primary-800 dark:text-primary-200 rounded-full border px-2.5 py-0.5 text-xs font-bold">
                                {page.total} Total Temuan
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                            Pengelolaan temuan ketidaksesuaian lintas unit kerja dengan siklus 4 status dan audit trail lengkap.
                            {items.some((f) => f.is_overdue) && (
                                <span className="font-bold text-rose-600 dark:text-rose-400">
                                    {' '}
                                    · {items.filter((f) => f.is_overdue).length} Melewati SLA
                                </span>
                            )}
                        </p>
                    </div>

                    <div className="flex items-center gap-2.5">
                        {/* Add Finding Button (Admin only) */}
                        {isAdmin && (
                            <button
                                type="button"
                                onClick={() => setIsCreateModalOpen(true)}
                                className="bg-primary hover:bg-primary-700 inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold text-white shadow-sm transition-all hover:shadow-md"
                            >
                                <Plus className="h-4 w-4" />
                                <span>Tambah Temuan</span>
                            </button>
                        )}

                        {/* View Switcher */}
                        <div className="flex items-center rounded-xl border border-slate-200/80 bg-white p-1 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                            <button
                                type="button"
                                onClick={() => setView('kanban')}
                                className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${
                                    view === 'kanban'
                                        ? 'bg-primary text-white shadow-xs'
                                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                                }`}
                            >
                                <LayoutGrid className="h-3.5 w-3.5" />
                                <span>{t('temuan.kanban')}</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setView('list')}
                                className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${
                                    view === 'list'
                                        ? 'bg-primary text-white shadow-xs'
                                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                                }`}
                            >
                                <ListIcon className="h-3.5 w-3.5" />
                                <span>{t('temuan.list')}</span>
                            </button>
                        </div>
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
                            placeholder="Cari nomor referensi, klausul, judul kontrol, atau catatan..."
                            className="focus:border-primary focus:ring-primary/20 h-10 w-full rounded-xl border border-slate-200 bg-white py-2 pr-4 pl-9 text-xs text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:outline-none sm:text-sm dark:border-slate-800 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                        />
                    </div>

                    <Select value={selectedSeverity} onChange={(e) => setSelectedSeverity(e.target.value)} className="min-w-[160px]">
                        <option value="all">Semua Kategori</option>
                        {SEVERITY_OPTIONS.map((s) => (
                            <option key={s} value={s}>
                                {severityLabel(s)}
                            </option>
                        ))}
                    </Select>

                    <Select value={selectedStatus} onChange={(e) => setSelectedStatus(e.target.value)} className="min-w-[170px]">
                        <option value="all">Semua Status</option>
                        {STATUS_OPTIONS.map((s) => (
                            <option key={s} value={s}>
                                {STATUS_TEXT[s]}
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

            {/* Modal Tambah Temuan Baru (Admin Kepatuhan) */}
            <Modal
                open={isCreateModalOpen}
                title="Buat Temuan Audit Baru"
                description="Terbitkan temuan ketidaksesuaian baru untuk ditindaklanjuti oleh PIC unit kerja."
                onClose={() => setIsCreateModalOpen(false)}
                maxWidth="xl"
            >
                <form onSubmit={handleCreateFinding} className="space-y-4">
                    <div>
                        <label className="mb-1 block text-xs font-bold text-slate-700 dark:text-slate-300">
                            Pilih Kontrol / Klausul SMKI <span className="text-rose-500">*</span>
                        </label>
                        <select
                            value={createData.control_id}
                            onChange={(e) => setCreateData('control_id', e.target.value)}
                            required
                            className="focus:border-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        >
                            <option value="">-- Pilih Klausul & Kontrol Terkait --</option>
                            {controls.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.kode_klausul} - {c.judul} {c.framework ? `(${c.framework.nama})` : ''}
                                </option>
                            ))}
                        </select>
                        {createErrors.control_id && <p className="mt-1 text-xs text-rose-500">{createErrors.control_id}</p>}
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Unit Kerja Terkait <span className="text-rose-500">*</span>
                            </label>
                            <select
                                value={createData.unit_id}
                                onChange={(e) => {
                                    const unitId = e.target.value;
                                    setCreateData((prev) => {
                                        const matchingPic = pics.find((p) => String(p.unit_id) === unitId);
                                        return {
                                            ...prev,
                                            unit_id: unitId,
                                            pic_id: matchingPic ? String(matchingPic.id) : prev.pic_id,
                                        };
                                    });
                                }}
                                required
                                className="focus:border-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            >
                                <option value="">-- Pilih Unit Kerja --</option>
                                {workUnits.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.nama}
                                    </option>
                                ))}
                            </select>
                            {createErrors.unit_id && <p className="mt-1 text-xs text-rose-500">{createErrors.unit_id}</p>}
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-bold text-slate-700 dark:text-slate-300">PIC Penanggung Jawab</label>
                            <select
                                value={createData.pic_id}
                                onChange={(e) => setCreateData('pic_id', e.target.value)}
                                className="focus:border-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            >
                                <option value="">-- Otomatis Sesuai Unit Kerja --</option>
                                {pics
                                    .filter((p) => !createData.unit_id || String(p.unit_id) === createData.unit_id)
                                    .map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                            </select>
                            {createErrors.pic_id && <p className="mt-1 text-xs text-rose-500">{createErrors.pic_id}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Tingkat Keparahan <span className="text-rose-500">*</span>
                            </label>
                            <select
                                value={createData.kategori}
                                onChange={(e) => setCreateData('kategori', e.target.value)}
                                required
                                className="focus:border-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            >
                                <option value="major">Mayor (Risiko Tinggi)</option>
                                <option value="minor">Minor (Risiko Sedang)</option>
                                <option value="observasi">Observasi / Peluang Perbaikan</option>
                            </select>
                            {createErrors.kategori && <p className="mt-1 text-xs text-rose-500">{createErrors.kategori}</p>}
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Target Batas Waktu SLA (Deadline)
                            </label>
                            <input
                                type="date"
                                value={createData.deadline}
                                onChange={(e) => setCreateData('deadline', e.target.value)}
                                className="focus:border-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                            {createErrors.deadline && <p className="mt-1 text-xs text-rose-500">{createErrors.deadline}</p>}
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-bold text-slate-700 dark:text-slate-300">
                            Catatan Temuan & Deskripsi Ketidaksesuaian <span className="text-rose-500">*</span>
                        </label>
                        <textarea
                            value={createData.catatan}
                            onChange={(e) => setCreateData('catatan', e.target.value)}
                            required
                            rows={3}
                            placeholder="Jelaskan kondisi faktual yang ditemukan, gap kepatuhan terhadap kontrol, dan rekomendasi perbaikan..."
                            className="focus:border-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        />
                        {createErrors.catatan && <p className="mt-1 text-xs text-rose-500">{createErrors.catatan}</p>}
                    </div>

                    <div className="flex items-center justify-end gap-2.5 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <button
                            type="button"
                            onClick={() => setIsCreateModalOpen(false)}
                            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={createProcessing}
                            className="bg-primary hover:bg-primary-700 inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-bold text-white shadow-sm transition-all disabled:opacity-50"
                        >
                            <Save className="h-4 w-4" />
                            <span>{createProcessing ? 'Menerbitkan...' : 'Terbitkan Temuan'}</span>
                        </button>
                    </div>
                </form>
            </Modal>

            {/* Detail Slide-Over Drawer with Review & Status Audit Trail */}
            <SlideOver
                open={detailTarget !== null}
                title={
                    detailTarget ? (
                        <div className="flex items-center gap-2.5">
                            <span>Detail & Review Temuan</span>
                            <code className="text-primary bg-primary-50 border-primary-200 dark:bg-navy-900 dark:border-primary-800 dark:text-primary-200 rounded border px-2 py-0.5 text-xs font-bold">
                                FND-{findingRef(detailTarget)}
                            </code>
                        </div>
                    ) : (
                        'Detail Temuan'
                    )
                }
                description={detailTarget?.control?.judul || 'Informasi lengkap siklus temuan audit ketidaksesuaian'}
                onClose={() => setDetailTarget(null)}
                maxWidth="xl"
                footer={
                    <button
                        type="button"
                        onClick={() => setDetailTarget(null)}
                        className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        {t('temuan.close')}
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
                                <span className="text-[11px] font-medium text-slate-400">Status Siklus Temuan</span>
                                <div className="mt-1.5 flex items-center gap-2">
                                    <StatusBadge tone={STATUS_TONE[detailTarget.status] ?? 'gray'}>
                                        {STATUS_TEXT[detailTarget.status] ?? detailTarget.status}
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
                        {canUpdateThisFinding(detailTarget) ? (
                            <form
                                onSubmit={handleUpdateFinding}
                                className="border-primary-200/80 bg-primary-50/40 dark:border-primary-900/40 dark:bg-navy-900/30 space-y-4 rounded-2xl border p-4 shadow-2xs"
                            >
                                <div className="text-primary dark:text-primary-200 flex items-center gap-2 text-xs font-bold tracking-wider uppercase">
                                    <Edit3 className="h-4 w-4" />
                                    <span>Ubah Status & Tindak Lanjut</span>
                                </div>

                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-[11px] font-bold text-slate-700 dark:text-slate-300">
                                            Status Baru <span className="text-rose-500">*</span>
                                        </label>
                                        <select
                                            value={updateData.status}
                                            onChange={(e) => setUpdateData('status', e.target.value)}
                                            required
                                            className="focus:border-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                        >
                                            <option value="open">1. Terbuka (Open)</option>
                                            <option value="in_progress">2. Dalam Penanganan (In Progress)</option>
                                            <option value="resolved">3. Selesai Ditindaklanjuti (Resolved)</option>
                                            <option value="closed">4. Ditutup & Terverifikasi (Closed)</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-[11px] font-bold text-slate-700 dark:text-slate-300">
                                            Penyesuaian Target SLA (Deadline)
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
                                    <div className="mb-1 flex items-center justify-between">
                                        <label className="block text-[11px] font-bold text-slate-700 dark:text-slate-300">
                                            Catatan Perubahan Status & Tindak Lanjut <span className="text-rose-500">*</span>
                                        </label>
                                        <span className="text-[10px] text-slate-500 dark:text-slate-400">Tercatat ke Riwayat Status</span>
                                    </div>
                                    <textarea
                                        value={updateData.catatan}
                                        onChange={(e) => setUpdateData('catatan', e.target.value)}
                                        required
                                        rows={3}
                                        placeholder="Wajib diisi: Berikan penjelasan tindakan korektif, status implementasi, atau catatan hasil verifikasi..."
                                        className="focus:border-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                                    />
                                    {updateErrors.catatan && <p className="mt-1 text-xs text-rose-500">{updateErrors.catatan}</p>}
                                </div>

                                <div className="flex items-center justify-between pt-1">
                                    <div className="flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                                        <Info className="h-3.5 w-3.5 text-slate-400" />
                                        <span>Status dapat digerakkan maju atau mundur dengan alasan jelas.</span>
                                    </div>
                                    <button
                                        type="submit"
                                        disabled={updateProcessing}
                                        className="bg-primary hover:bg-primary-700 inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-bold text-white shadow-sm transition-colors disabled:opacity-50"
                                    >
                                        <Save className="h-3.5 w-3.5" />
                                        <span>{updateProcessing ? 'Menyimpan…' : 'Simpan Status & Catatan'}</span>
                                    </button>
                                </div>
                            </form>
                        ) : (
                            <div className="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50/70 p-3.5 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">
                                <Info className="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                <div>
                                    <span className="font-bold">Akses Ubah Status Dibatasi</span>
                                    <p className="mt-0.5 text-[11px] leading-relaxed opacity-90">
                                        Hanya Admin Kepatuhan dan PIC yang ditugaskan untuk unit <strong>{detailTarget.unit?.nama}</strong> yang
                                        berwenang mengubah status temuan ini.
                                    </p>
                                </div>
                            </div>
                        )}

                        {/* Riwayat Perubahan Status (Status Audit Trail Timeline) */}
                        <div className="space-y-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                            <div className="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                                <div className="flex items-center gap-2 text-xs font-bold tracking-wider text-slate-800 uppercase dark:text-slate-200">
                                    <History className="text-primary dark:text-primary-300 h-4 w-4" />
                                    <span>Riwayat Perubahan Status (Audit Trail)</span>
                                </div>
                                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {detailTarget.histories?.length || 0} Entri
                                </span>
                            </div>

                            <div className="space-y-4 pt-1">
                                {detailTarget.histories && detailTarget.histories.length > 0 ? (
                                    <div className="relative space-y-4 pl-6 before:absolute before:top-2 before:bottom-2 before:left-2.5 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-800">
                                        {detailTarget.histories.map((hist, hIdx) => {
                                            const isInitial = hist.from_status === null;
                                            const isBackward =
                                                hist.from_status &&
                                                STEPS.findIndex((s) => s.id === hist.from_status) > STEPS.findIndex((s) => s.id === hist.to_status);

                                            return (
                                                <div key={hist.id || hIdx} className="group relative">
                                                    {/* Timeline Bullet */}
                                                    <div
                                                        className={`absolute top-1 -left-6 grid h-5 w-5 place-items-center rounded-full border-2 bg-white dark:bg-slate-900 ${
                                                            isInitial
                                                                ? 'border-blue-500 text-blue-500'
                                                                : isBackward
                                                                  ? 'border-rose-500 text-rose-500'
                                                                  : 'border-emerald-500 text-emerald-500'
                                                        }`}
                                                    >
                                                        {isBackward ? (
                                                            <RotateCcw className="h-2.5 w-2.5" />
                                                        ) : (
                                                            <div className="h-1.5 w-1.5 rounded-full bg-current" />
                                                        )}
                                                    </div>

                                                    <div className="rounded-xl border border-slate-100 bg-slate-50/70 p-3 text-xs dark:border-slate-800/80 dark:bg-slate-800/40">
                                                        <div className="flex flex-wrap items-center justify-between gap-1.5 pb-1.5">
                                                            <div className="flex items-center gap-1.5">
                                                                <span className="font-bold text-slate-900 dark:text-white">
                                                                    {hist.user?.name || 'Sistem SMKI'}
                                                                </span>
                                                                <span className="rounded bg-slate-200/80 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                                                    {getRoleName(hist.user?.role)}
                                                                </span>
                                                            </div>
                                                            <span className="text-[10px] text-slate-400 dark:text-slate-500">
                                                                {formatDateTimeIndonesian(hist.created_at)}
                                                            </span>
                                                        </div>

                                                        {/* Transition Pill */}
                                                        <div className="my-1.5 flex items-center gap-1.5">
                                                            {isInitial ? (
                                                                <span className="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                                                                    Dibuat Awal → {STATUS_TEXT[hist.to_status] || hist.to_status}
                                                                </span>
                                                            ) : (
                                                                <div className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-bold text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                                                    <span>{STATUS_TEXT[hist.from_status || ''] || hist.from_status}</span>
                                                                    <ArrowRight className="h-3 w-3 text-slate-400" />
                                                                    <span
                                                                        className={
                                                                            isBackward
                                                                                ? 'text-rose-600 dark:text-rose-400'
                                                                                : 'text-emerald-600 dark:text-emerald-400'
                                                                        }
                                                                    >
                                                                        {STATUS_TEXT[hist.to_status] || hist.to_status}
                                                                    </span>
                                                                    {isBackward && (
                                                                        <span className="ml-1 rounded bg-rose-100 px-1 text-[9px] text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                                                            Kembali
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            )}
                                                        </div>

                                                        {/* Notes Content */}
                                                        <div className="mt-2 rounded-lg bg-white p-2.5 text-xs leading-relaxed text-slate-700 shadow-2xs dark:bg-slate-900/80 dark:text-slate-300">
                                                            <div className="flex items-start gap-1.5">
                                                                <MessageSquare className="mt-0.5 h-3 w-3 shrink-0 text-slate-400" />
                                                                <p className="italic">"{hist.catatan}"</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <div className="rounded-xl border border-dashed border-slate-200 p-4 text-center text-xs text-slate-400 dark:border-slate-800 dark:text-slate-500">
                                        Belum ada riwayat status tercatat.
                                    </div>
                                )}
                            </div>
                        </div>

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

                        {/* Initial Finding Note */}
                        {(detailTarget.admin_notes || detailTarget.catatan_admin) && (
                            <div className="space-y-2 rounded-xl border border-slate-200/80 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                                <div className="flex items-center gap-2 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                    <FileText className="h-4 w-4 text-amber-500" />
                                    <span>Catatan Awal Temuan</span>
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
