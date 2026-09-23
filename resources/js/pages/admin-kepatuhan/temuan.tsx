import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { DatePicker } from '@/components/ui/DatePicker';
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
    AlertTriangle,
    ArrowRight,
    Building2,
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
    Lock,
    MessageSquare,
    Plus,
    RotateCcw,
    Save,
    Search,
    Shield,
    ShieldAlert,
    Trash2,
    UserCheck,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

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
    catatan?: string | null;
    control?: {
        id: number;
        kode_klausul: string;
        judul: string;
        deskripsi?: string;
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
        id?: string;
        finding_id?: string;
    };
    initialFinding?: FindingItem | null;
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
    { id: 'open', label: 'Terbuka', fullLabel: '1. Terbuka (Open)', desc: 'Ketidaksesuaian/temuan dicatat & menunggu tindakan perbaikan' },
    {
        id: 'in_progress',
        label: 'Penanganan',
        fullLabel: '2. Dalam Penanganan (In Progress)',
        desc: 'Tindakan mitigasi atau perbaikan sedang dikerjakan oleh PIC',
    },
    {
        id: 'resolved',
        label: 'Selesai',
        fullLabel: '3. Selesai Ditindaklanjuti (Resolved)',
        desc: 'Tindakan perbaikan telah selesai dan menunggu verifikasi Admin',
    },
    {
        id: 'closed',
        label: 'Ditutup',
        fullLabel: '4. Ditutup & Terverifikasi (Closed)',
        desc: 'Bukti perbaikan telah diverifikasi dan temuan resmi ditutup',
    },
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

export default function Findings({ findings, workUnits = [], controls = [], pics = [], filters = {}, initialFinding = null }: FindingsProps) {
    const can = useCan();
    const pageProps = usePage<{ auth?: { user?: AuthUser }; flash?: { type: string; message: string } }>().props;
    const currentUrl = usePage().url;
    const authUser = pageProps.auth?.user;
    const flash = pageProps.flash;

    const [flashVisible, setFlashVisible] = useState(false);
    const [view, setView] = useState<'kanban' | 'list'>('kanban');
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedSeverity, setSelectedSeverity] = useState<string>(filters.category || filters.kategori || 'all');
    const [selectedStatus, setSelectedStatus] = useState<string>(filters.status || 'all');
    const [selectedUnit, setSelectedUnit] = useState<string>(filters.unit_id || 'all');
    const [detailTarget, setDetailTarget] = useState<FindingItem | null>(() => {
        if (initialFinding) return initialFinding;
        const urlParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
        const targetId = filters.id || filters.finding_id || urlParams?.get('id') || urlParams?.get('finding_id');
        if (targetId) {
            return (findings?.data || []).find((f) => String(f.id) === String(targetId)) || null;
        }
        return null;
    });
    const [detailActiveTab, setDetailActiveTab] = useState<'action' | 'history' | 'control'>('action');
    const [showNoteFormOnSameStatus, setShowNoteFormOnSameStatus] = useState(false);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const isFirstRender = useRef(true);

    const page = findings ?? { data: [], current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null };
    const items = page.data;

    // Helper to close drawer and clean query param from URL cleanly
    const closeDetailDrawer = useCallback(() => {
        setDetailTarget(null);
        setDetailActiveTab('action');
        if (typeof window !== 'undefined' && (window.location.search.includes('id=') || window.location.search.includes('finding_id='))) {
            const url = new URL(window.location.href);
            url.searchParams.delete('id');
            url.searchParams.delete('finding_id');
            window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
        }
    }, []);

    const openDetailDrawer = useCallback((f: FindingItem) => {
        setDetailTarget(f);
        setDetailActiveTab('action');
    }, []);

    // Auto-open finding in slide-over drawer if targeted via URL query (e.g. notification click)
    useEffect(() => {
        if (initialFinding) {
            setDetailTarget(initialFinding);
            return;
        }
        const searchStr = currentUrl.includes('?')
            ? currentUrl.split('?')[1]
            : typeof window !== 'undefined'
              ? window.location.search.replace(/^\?/, '')
              : '';
        const urlParams = new URLSearchParams(searchStr);
        const targetId = filters.id || filters.finding_id || urlParams.get('id') || urlParams.get('finding_id');
        if (targetId) {
            const found = items.find((f) => String(f.id) === String(targetId));
            if (found) {
                setDetailTarget(found);
            }
        }
    }, [initialFinding, currentUrl, filters.id, filters.finding_id, items]);

    // Fast instant reaction when notification is clicked on the same page
    useEffect(() => {
        const handleOpenTarget = (e: Event) => {
            const customEvent = e as CustomEvent<{ id: number | string }>;
            const targetId = customEvent.detail?.id;
            if (!targetId) return;
            const found = items.find((f) => String(f.id) === String(targetId));
            if (found) {
                setDetailTarget(found);
            }
        };

        window.addEventListener('open-finding-target', handleOpenTarget);
        return () => window.removeEventListener('open-finding-target', handleOpenTarget);
    }, [items]);

    // Check permissions
    const userRoleStr = typeof authUser?.role === 'string' ? authUser.role : authUser?.role?.name;
    const isUserPic = userRoleStr === 'pic';
    const isAdmin = !isUserPic && (userRoleStr === 'admin_kepatuhan' || userRoleStr === 'superadmin' || can('finding.create'));
    const canDelete = !isUserPic && (userRoleStr === 'admin_kepatuhan' || userRoleStr === 'superadmin' || can('finding.delete'));
    const isReadOnly = !isUserPic && !isAdmin;

    const [deleteTarget, setDeleteTarget] = useState<FindingItem | null>(null);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deleteBusy, setDeleteBusy] = useState(false);

    function handleDelete(f: FindingItem) {
        setDeleteTarget(f);
        setDeleteDialogOpen(true);
    }

    function confirmDelete() {
        if (!deleteTarget) return;
        setDeleteBusy(true);
        router.delete(`/temuan/${deleteTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (detailTarget?.id === deleteTarget.id) {
                    setDetailTarget(null);
                }
                setDeleteDialogOpen(false);
                setDeleteTarget(null);
            },
            onFinish: () => {
                setDeleteBusy(false);
            },
        });
    }

    function cancelDelete() {
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    }

    const [restoreBusy, setRestoreBusy] = useState(false);

    function handleRestore(id: number | string) {
        setRestoreBusy(true);
        router.post(
            `/temuan/${id}/restore`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRestoreBusy(false);
                },
                onError: () => {
                    setRestoreBusy(false);
                },
                onFinish: () => {
                    setRestoreBusy(false);
                },
            },
        );
    }

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
            const ownNote = isUserPic ? (detailTarget.catatan ?? '') : (detailTarget.admin_notes as string) || detailTarget.catatan_admin || '';
            setUpdateData({
                status: detailTarget.status || 'open',
                category: detailTarget.kategori || 'minor',
                deadline: detailTarget.deadline ? detailTarget.deadline.substring(0, 10) : '',
                catatan: ownNote ?? '',
            });
            setShowNoteFormOnSameStatus(false);
        }
    }, [detailTarget, setUpdateData, isUserPic]);

    // Keep detailTarget fresh after props change
    useEffect(() => {
        if (detailTarget) {
            const fresh = items.find((f) => f.id === detailTarget.id);
            if (fresh && fresh !== detailTarget) {
                setDetailTarget(fresh);
            }
        }
    }, [items, detailTarget]);

    function handleCancelUpdate() {
        if (detailTarget) {
            const ownNote = isUserPic ? (detailTarget.catatan ?? '') : (detailTarget.admin_notes as string) || detailTarget.catatan_admin || '';
            setUpdateData({
                status: detailTarget.status || 'open',
                category: detailTarget.kategori || 'minor',
                deadline: detailTarget.deadline ? detailTarget.deadline.substring(0, 10) : '',
                catatan: ownNote ?? '',
            });
        }
        setShowNoteFormOnSameStatus(false);
    }

    function handleUpdateFinding(e: React.FormEvent) {
        e.preventDefault();
        if (!detailTarget) return;

        submitUpdate(`/temuan/${detailTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setUpdateData('catatan', '');
                setShowNoteFormOnSameStatus(false);
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
                <span className="inline-flex shrink-0 items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400">
                    <CheckCircle2 className="h-3 w-3" />
                    {f.verified_at ? t('temuan.verifiedOn', fmtDate(f.verified_at)) : 'Selesai & Terverifikasi'}
                </span>
            );
        }

        if (f.status === 'resolved') {
            return (
                <span className="inline-flex shrink-0 items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300">
                    <Clock className="h-3 w-3" />
                    Menunggu Verifikasi Admin
                </span>
            );
        }

        if (!f.deadline) {
            return (
                <span className="inline-flex shrink-0 items-center gap-1 text-[10px] whitespace-nowrap text-slate-400 dark:text-slate-500">
                    <Calendar className="h-3 w-3" />
                    Belum ada deadline
                </span>
            );
        }

        if (f.is_overdue) {
            return (
                <span className="inline-flex shrink-0 items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300">
                    <Clock className="h-3 w-3 text-rose-600 dark:text-rose-400" />
                    {t('temuan.lateDays', Math.abs(f.days_remaining ?? 0))}
                </span>
            );
        }

        const remaining = f.days_remaining ?? 0;
        if (remaining <= 3) {
            return (
                <span className="inline-flex shrink-0 items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap text-amber-800 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                    <Clock className="h-3 w-3 text-amber-600 dark:text-amber-400" />
                    {remaining === 0 ? 'Hari Ini Jatuh Tempo' : `${remaining} Hari Tersisa`}
                </span>
            );
        }

        return (
            <span className="border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800/60 dark:bg-navy-900/40 dark:text-primary-200 inline-flex shrink-0 items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium whitespace-nowrap">
                <Calendar className="text-primary dark:text-primary-200 h-3 w-3" />
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

    const renderStatusWorkflowHub = (f: FindingItem) => {
        const currentStatus = f.status;
        // PIC only works the checkpoints they can actually move (closure is Admin's job).
        const steps = isUserPic ? STEPS.slice(0, 3) : STEPS;
        const currentIdx = steps.findIndex((s) => s.id === currentStatus);
        const selectedStatus = updateData.status || currentStatus;
        const selectedIdx = steps.findIndex((s) => s.id === selectedStatus);
        const selectedStep = steps[selectedIdx >= 0 ? selectedIdx : currentIdx >= 0 ? currentIdx : 0];
        const isStatusChanging = selectedStatus !== currentStatus;
        const isUpgrade = isStatusChanging && selectedIdx > currentIdx;
        const isDowngrade = isStatusChanging && selectedIdx < currentIdx;
        const canUpdate = canUpdateThisFinding(f);

        const handleStepClick = (stepId: string) => {
            if (!canUpdate) return;
            if (isUserPic && stepId === 'closed') return; // Protected for Admin only

            setUpdateData('status', stepId);
            if (stepId === currentStatus) {
                setShowNoteFormOnSameStatus(false);
            }
        };

        if (f.deleted_at) {
            return (
                <div className="rounded-2xl border border-rose-200/80 bg-rose-50/50 p-4 shadow-2xs dark:border-rose-900/40 dark:bg-rose-950/20">
                    <div className="flex items-center gap-2 text-xs font-bold text-rose-800 dark:text-rose-300">
                        <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600 dark:text-rose-400" />
                        <span>Siklus Status Dinonaktifkan (Temuan Telah Dihapus)</span>
                    </div>
                    <p className="mt-1 text-xs leading-relaxed text-rose-700/80 dark:text-rose-400/80">
                        Temuan audit ini telah dihapus oleh Admin pada {formatDateTimeIndonesian(f.deleted_at as string)}. Status tidak dapat diubah
                        sebelum data dipulihkan kembali.
                    </p>
                </div>
            );
        }

        if (isUserPic && currentStatus === 'closed') {
            return (
                <div className="rounded-2xl border border-emerald-200/80 bg-emerald-50/50 p-4 shadow-2xs dark:border-emerald-900/40 dark:bg-emerald-950/20">
                    <div className="flex items-center gap-2 text-xs font-bold text-emerald-800 dark:text-emerald-300">
                        <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <span>Siklus & Pembaruan Status</span>
                    </div>
                    <p className="mt-1.5 text-xs leading-relaxed text-emerald-800/90 dark:text-emerald-300/80">
                        Temuan telah ditutup &amp; diverifikasi oleh Admin Kepatuhan. Status tidak dapat diubah lagi.
                    </p>
                </div>
            );
        }

        if (isReadOnly) {
            const readonlyStep = STEPS[currentIdx >= 0 ? currentIdx : 0];
            return (
                <div className="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-900/50">
                    <div className="mb-3 flex items-center gap-2">
                        <span className="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                            Siklus &amp; Pembaruan Status
                        </span>
                    </div>
                    <div className="flex items-start gap-3 rounded-xl border border-slate-200/80 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-800/60">
                        <div className="mt-0.5 shrink-0">
                            {currentStatus === 'closed' ? (
                                <CheckCircle2 className="h-5 w-5 text-emerald-500" />
                            ) : currentStatus === 'resolved' ? (
                                <Clock className="text-primary dark:text-primary-300 h-5 w-5" />
                            ) : (
                                <Info className="h-5 w-5 text-amber-500" />
                            )}
                        </div>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-xs font-bold text-slate-900 dark:text-white">{readonlyStep.fullLabel}</span>
                                <StatusBadge tone={STATUS_TONE[currentStatus] ?? 'gray'}>{STATUS_TEXT[currentStatus] ?? currentStatus}</StatusBadge>
                            </div>
                            <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{readonlyStep.desc}</p>
                        </div>
                    </div>
                    <p className="mt-2.5 flex items-start gap-1.5 text-[11px] text-slate-400 dark:text-slate-500">
                        <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        Hanya baca. Status temuan hanya dapat diperbarui oleh Admin Kepatuhan atau PIC unit terkait.
                    </p>
                </div>
            );
        }

        return (
            <div className="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4.5 shadow-2xs dark:border-slate-800 dark:bg-slate-900/50">
                {/* Hub Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                            Siklus & Pembaruan Status
                        </span>
                        {isUserPic && (
                            <span className="bg-primary-100 text-primary-800 dark:bg-primary-950 dark:text-primary-300 rounded-md px-2 py-0.5 text-[10px] font-bold">
                                Mode PIC
                            </span>
                        )}
                    </div>
                    <span className="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                        {isStatusChanging ? (
                            <span
                                className={`font-bold ${
                                    isDowngrade ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300'
                                }`}
                            >
                                Menuju Langkah {selectedIdx + 1} dari {steps.length}
                            </span>
                        ) : (
                            <span>
                                Langkah {currentIdx >= 0 ? currentIdx + 1 : 1} dari {steps.length}
                            </span>
                        )}
                    </span>
                </div>

                {/* Interactive Stepper Progress Bar */}
                <div className="relative mt-4 mb-2 px-3">
                    {/* Connecting background track */}
                    <div className="absolute top-4 right-6 left-6 -z-0 h-0.5 bg-slate-200 dark:bg-slate-700" />
                    {/* Active progress track */}
                    <div
                        className={`absolute top-4 left-6 -z-0 h-0.5 transition-all duration-300 ${isDowngrade ? 'bg-amber-500' : 'bg-primary'}`}
                        style={{
                            width: `${(Math.max(0, isStatusChanging ? selectedIdx : currentIdx) / (steps.length - 1)) * 84}%`,
                        }}
                    />

                    <div className="relative z-10 flex items-center justify-between">
                        {steps.map((step, idx) => {
                            const isCurrent = step.id === currentStatus;
                            const isTarget = step.id === selectedStatus;
                            const isDone = idx < currentIdx;
                            const isPicRestricted = isUserPic && step.id === 'closed';

                            let ringAndDotClass =
                                'border-2 border-slate-300 bg-white text-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-500';
                            if (isTarget && isStatusChanging) {
                                ringAndDotClass = isDowngrade
                                    ? 'bg-amber-600 ring-4 ring-amber-300/40 text-white shadow-sm scale-105'
                                    : 'bg-emerald-600 ring-4 ring-emerald-300/40 text-white shadow-sm scale-105';
                            } else if (isCurrent) {
                                ringAndDotClass = 'bg-primary ring-4 ring-primary/20 text-white shadow-sm';
                            } else if (isDone) {
                                ringAndDotClass = 'bg-emerald-600 text-white hover:bg-emerald-500 dark:bg-emerald-500';
                            }

                            return (
                                <button
                                    key={step.id}
                                    type="button"
                                    onClick={() => handleStepClick(step.id)}
                                    disabled={!canUpdate || isPicRestricted}
                                    className={`group flex flex-col items-center transition-all focus:outline-none ${
                                        isPicRestricted
                                            ? 'cursor-not-allowed opacity-45'
                                            : canUpdate
                                              ? 'cursor-pointer hover:opacity-100'
                                              : 'cursor-default'
                                    }`}
                                    title={
                                        isPicRestricted
                                            ? 'Status Ditutup hanya dapat diverifikasi oleh Admin Kepatuhan'
                                            : canUpdate
                                              ? `Klik untuk beralih ke status ${step.label}`
                                              : step.label
                                    }
                                >
                                    <div
                                        className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition-all ${ringAndDotClass}`}
                                    >
                                        {isPicRestricted ? (
                                            <Lock className="h-3.5 w-3.5" />
                                        ) : isDone && !isTarget ? (
                                            <CheckCircle2 className="h-4 w-4" />
                                        ) : (
                                            idx + 1
                                        )}
                                    </div>
                                    <span
                                        className={`mt-1.5 text-center text-[11px] whitespace-nowrap transition-colors ${
                                            isTarget && isStatusChanging
                                                ? isDowngrade
                                                    ? 'font-bold text-amber-700 underline underline-offset-2 dark:text-amber-300'
                                                    : 'font-bold text-emerald-700 underline underline-offset-2 dark:text-emerald-300'
                                                : isCurrent
                                                  ? 'text-primary dark:text-primary-300 font-bold'
                                                  : isDone
                                                    ? 'font-medium text-emerald-700 dark:text-emerald-400'
                                                    : 'font-normal text-slate-400 dark:text-slate-500'
                                        }`}
                                    >
                                        {step.label}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Sub-text hint if interactive */}
                {canUpdate && !isStatusChanging && !showNoteFormOnSameStatus && (
                    <p className="mt-1 text-center text-[11px] text-slate-400 dark:text-slate-500">
                        Pilih langkah (1 - {steps.length}) di atas untuk mengubah status temuan.
                    </p>
                )}

                {/* Dynamic Panel below Stepper */}
                <div className="mt-3.5">
                    {/* SCENARIO A: Status is being changed OR user explicitly opened note form */}
                    {canUpdate && (isStatusChanging || showNoteFormOnSameStatus) ? (
                        <form
                            onSubmit={handleUpdateFinding}
                            className={`space-y-4 rounded-xl border p-4 shadow-sm transition-all ${
                                isDowngrade
                                    ? 'border-amber-200/80 bg-white dark:border-amber-900/50 dark:bg-slate-900'
                                    : isUpgrade
                                      ? 'border-emerald-200/80 bg-white dark:border-emerald-900/50 dark:bg-slate-900'
                                      : 'border-primary-200/80 dark:border-primary-900/60 bg-white dark:bg-slate-900'
                            }`}
                        >
                            {/* Transition Header */}
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                {isStatusChanging ? (
                                    <div className="flex items-center gap-2 text-xs">
                                        <span className="font-bold text-slate-500 uppercase dark:text-slate-400">Rencana Perubahan:</span>
                                        <div className="flex items-center gap-1.5 font-bold">
                                            <span className="rounded-md bg-slate-100 px-2 py-0.5 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                {STATUS_TEXT[currentStatus] || currentStatus}
                                            </span>
                                            {isDowngrade ? (
                                                <RotateCcw className="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                                            ) : (
                                                <ArrowRight className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                                            )}
                                            <span
                                                className={`rounded-md px-2 py-0.5 font-bold ${
                                                    isDowngrade
                                                        ? 'border border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                                        : 'border border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                }`}
                                            >
                                                {STATUS_TEXT[selectedStatus] || selectedStatus}
                                            </span>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-2 text-xs font-bold text-slate-800 dark:text-slate-200">
                                        <Edit3 className="text-primary h-4 w-4" />
                                        <span>Pembaruan Catatan Progres & SLA</span>
                                    </div>
                                )}

                                <button
                                    type="button"
                                    onClick={handleCancelUpdate}
                                    className="text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                >
                                    Batal
                                </button>
                            </div>

                            {/* Transition contextual explanation with subtle tint */}
                            <div
                                className={`rounded-lg border p-2.5 text-xs ${
                                    isDowngrade
                                        ? 'border-amber-200/80 bg-amber-50/60 text-slate-700 dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-slate-300'
                                        : isUpgrade
                                          ? 'border-emerald-200/80 bg-emerald-50/60 text-slate-700 dark:border-emerald-900/50 dark:bg-emerald-950/25 dark:text-slate-300'
                                          : 'border-slate-200/80 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300'
                                }`}
                            >
                                <span
                                    className={`font-semibold ${
                                        isDowngrade
                                            ? 'text-amber-900 dark:text-amber-200'
                                            : isUpgrade
                                              ? 'text-emerald-900 dark:text-emerald-200'
                                              : 'text-slate-800 dark:text-white'
                                    }`}
                                >
                                    {selectedStep.fullLabel}:
                                </span>{' '}
                                {selectedStep.desc}
                                {isDowngrade && (
                                    <span className="ml-1 font-medium text-amber-700 dark:text-amber-400">
                                        (Status dikembalikan ke tahap sebelumnya)
                                    </span>
                                )}
                            </div>

                            {/* SLA Target Date Input - Admin Only */}
                            {isAdmin && (
                                <div>
                                    <label className="mb-1 block text-[11px] font-bold text-slate-700 dark:text-slate-300">
                                        Penyesuaian Target Batas SLA (Deadline)
                                    </label>
                                    <DatePicker value={updateData.deadline} onChange={(val) => setUpdateData('deadline', val)} />
                                </div>
                            )}

                            {/* Mandatory Notes */}
                            <div>
                                <div className="mb-1 flex items-center justify-between">
                                    <label className="block text-[11px] font-bold text-slate-700 dark:text-slate-300">
                                        {isUserPic ? 'Catatan PIC / Alasan' : 'Catatan Admin / Alasan'} <span className="text-rose-500">*</span>
                                    </label>
                                    <span className="text-[10px] text-slate-400">Tercatat di Audit Trail</span>
                                </div>
                                <textarea
                                    value={updateData.catatan}
                                    onChange={(e) => setUpdateData('catatan', e.target.value)}
                                    required
                                    rows={3}
                                    maxLength={2000}
                                    placeholder={
                                        isDowngrade
                                            ? 'Wajib diisi: Berikan alasan pengembalian status ke tahap ini...'
                                            : isUserPic
                                              ? 'Wajib diisi: Jelaskan tindakan mitigasi yang telah dilakukan atau status perbaikan...'
                                              : 'Wajib diisi: Berikan penjelasan tindakan korektif, status implementasi, atau catatan hasil verifikasi...'
                                    }
                                    className="focus:border-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
                                />
                                {updateErrors.catatan && <p className="mt-1 text-xs text-rose-500">{updateErrors.catatan}</p>}
                            </div>

                            {/* Action Buttons */}
                            <div className="flex items-center justify-between pt-1">
                                <button
                                    type="button"
                                    onClick={handleCancelUpdate}
                                    className="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={updateProcessing}
                                    className={`inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-bold text-white shadow-xs transition-colors disabled:opacity-50 ${
                                        isDowngrade
                                            ? 'bg-amber-600 hover:bg-amber-700 dark:bg-amber-600 dark:hover:bg-amber-700'
                                            : isUpgrade
                                              ? 'bg-emerald-600 hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-700'
                                              : 'bg-primary hover:bg-primary-700'
                                    }`}
                                >
                                    <Save className="h-3.5 w-3.5" />
                                    <span>{updateProcessing ? 'Menyimpan…' : 'Simpan Perubahan'}</span>
                                </button>
                            </div>
                        </form>
                    ) : (
                        /* SCENARIO B: Current Status Info & Quick Action (Clean & Non-Redundant) */
                        <div className="flex flex-col gap-3 rounded-xl border border-slate-200/80 bg-slate-50/70 p-3.5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-800/40">
                            <div className="flex min-w-0 items-center gap-2.5">
                                <div className="shrink-0">
                                    {currentStatus === 'closed' ? (
                                        <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                                    ) : currentStatus === 'resolved' ? (
                                        <Clock className="text-primary dark:text-primary-300 h-4 w-4" />
                                    ) : (
                                        <Info className="h-4 w-4 text-amber-500" />
                                    )}
                                </div>
                                <p className="text-xs leading-relaxed text-slate-600 dark:text-slate-300">{selectedStep.desc}</p>
                            </div>

                            {canUpdate ? (
                                <button
                                    type="button"
                                    onClick={() => setShowNoteFormOnSameStatus(true)}
                                    className="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-bold text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                >
                                    <Edit3 className="h-3.5 w-3.5 text-slate-500" />
                                    <span>Catat Progres</span>
                                </button>
                            ) : (
                                <span className="text-[11px] text-slate-400 italic sm:text-right">Hanya baca</span>
                            )}
                        </div>
                    )}
                </div>

                {/* Unauthorized Warning if user cannot update */}
                {!canUpdate && (
                    <div className="mt-3 flex items-start gap-2 rounded-xl border border-slate-200/80 bg-slate-100/70 p-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-400">
                        <Info className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                        <span>
                            Akses ubah status terbatas. Hanya Admin Kepatuhan dan PIC unit <strong>{f.unit?.nama}</strong> yang berwenang mengubah
                            status.
                        </span>
                    </div>
                )}
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
                                        onClick={() => openDetailDrawer(f)}
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
                                        idx % 2 === 0 ? 'bg-white dark:bg-[#00223d]/70' : 'bg-slate-200/70 dark:bg-[#00172b]/80'
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
                                                    <span className="bg-primary inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-[8px] font-bold text-white">
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
                                        <div className="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                onClick={() => openDetailDrawer(f)}
                                                className="text-primary hover:text-primary-700 dark:text-primary-300 dark:hover:text-primary-200 inline-flex items-center gap-1 text-xs font-semibold"
                                            >
                                                <Eye className="h-3.5 w-3.5" />
                                                Detail & Review
                                            </button>
                                            {canDelete && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleDelete(f)}
                                                    title={t('temuan.deleteFinding')}
                                                    aria-label={`${t('temuan.deleteFinding')} FND-${findingRef(f)}`}
                                                    className="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 hover:text-rose-800 dark:text-rose-400 dark:hover:text-rose-300"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                    <span>Hapus</span>
                                                </button>
                                            )}
                                        </div>
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
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <div className="relative w-full flex-1 sm:min-w-[240px]">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Cari nomor referensi, klausul, judul kontrol, atau catatan..."
                            className="focus:border-primary focus:ring-primary/20 h-10 w-full rounded-xl border border-slate-200 bg-white py-2 pr-4 pl-9 text-xs text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:outline-none sm:text-sm dark:border-slate-800 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                        />
                    </div>

                    <div className="flex flex-col gap-3 sm:flex-none sm:flex-row">
                        <Select value={selectedSeverity} onChange={(e) => setSelectedSeverity(e.target.value)} className="min-w-0 sm:min-w-[160px]">
                            <option value="all">Semua Kategori</option>
                            {SEVERITY_OPTIONS.map((s) => (
                                <option key={s} value={s}>
                                    {severityLabel(s)}
                                </option>
                            ))}
                        </Select>

                        <Select value={selectedStatus} onChange={(e) => setSelectedStatus(e.target.value)} className="min-w-0 sm:min-w-[170px]">
                            <option value="all">Semua Status</option>
                            {STATUS_OPTIONS.map((s) => (
                                <option key={s} value={s}>
                                    {STATUS_TEXT[s]}
                                </option>
                            ))}
                        </Select>

                        <Select value={selectedUnit} onChange={(e) => setSelectedUnit(e.target.value)} className="min-w-0 sm:min-w-[170px]">
                            <option value="all">Semua Unit Kerja</option>
                            {workUnits.map((u) => (
                                <option key={u.id} value={String(u.id)}>
                                    {u.nama}
                                </option>
                            ))}
                        </Select>
                    </div>
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

            {/* SlideOver Tambah Temuan Baru (Admin Kepatuhan) */}
            <SlideOver
                open={isCreateModalOpen}
                title="Buat Temuan Audit Baru"
                subtitle="Terbitkan temuan ketidaksesuaian baru untuk ditindaklanjuti oleh PIC unit kerja."
                onClose={() => setIsCreateModalOpen(false)}
                maxWidth="xl"
                footer={
                    <div className="flex w-full items-center justify-end gap-2.5">
                        <button
                            type="button"
                            onClick={() => setIsCreateModalOpen(false)}
                            className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            form="create-finding-form"
                            disabled={createProcessing}
                            className="bg-primary hover:bg-primary-700 inline-flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-xs font-bold text-white shadow-sm transition-all disabled:opacity-50"
                        >
                            <Save className="h-4 w-4" />
                            <span>{createProcessing ? 'Menerbitkan...' : 'Terbitkan Temuan'}</span>
                        </button>
                    </div>
                }
            >
                <form id="create-finding-form" onSubmit={handleCreateFinding} className="space-y-4 p-1">
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
                            <DatePicker value={createData.deadline} onChange={(val) => setCreateData('deadline', val)} />
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
                            maxLength={2000}
                            placeholder="Jelaskan kondisi faktual yang ditemukan, gap kepatuhan terhadap kontrol, dan rekomendasi perbaikan..."
                            className="focus:border-primary w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        />
                        {createErrors.catatan && <p className="mt-1 text-xs text-rose-500">{createErrors.catatan}</p>}
                    </div>
                </form>
            </SlideOver>

            {/* Detail Slide-Over Drawer with Review & Status Audit Trail */}
            <SlideOver
                open={detailTarget !== null}
                title={
                    detailTarget ? (
                        <div className="flex flex-col gap-1">
                            <div className="flex items-center gap-2">
                                <span>Detail & Review Temuan</span>
                                {Boolean(detailTarget.deleted_at) && (
                                    <span className="rounded-md bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                        Dihapus
                                    </span>
                                )}
                            </div>
                            <code className="text-primary bg-primary-50 border-primary-200 dark:bg-navy-900 dark:border-primary-800 dark:text-primary-200 w-fit rounded border px-2 py-0.5 text-xs font-bold">
                                FND-{findingRef(detailTarget)}
                            </code>
                        </div>
                    ) : (
                        'Detail Temuan'
                    )
                }
                description={undefined}
                onClose={closeDetailDrawer}
                maxWidth="2xl"
                footer={
                    <div className="flex w-full items-center justify-between">
                        {Boolean(detailTarget?.deleted_at) && canDelete && detailTarget ? (
                            <button
                                type="button"
                                disabled={restoreBusy}
                                onClick={() => handleRestore(detailTarget.id)}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-100 disabled:opacity-50 dark:border-emerald-900/60 dark:bg-emerald-950/50 dark:text-emerald-300 dark:hover:bg-emerald-900/60"
                            >
                                <RotateCcw className="h-3.5 w-3.5" />
                                <span>{restoreBusy ? 'Memulihkan...' : 'Pulihkan Temuan'}</span>
                            </button>
                        ) : canDelete && detailTarget ? (
                            <button
                                type="button"
                                onClick={() => handleDelete(detailTarget)}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-50 dark:border-rose-900/60 dark:bg-slate-900 dark:text-rose-400 dark:hover:bg-rose-950/40"
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                                <span>{t('temuan.deleteFinding')}</span>
                            </button>
                        ) : (
                            <span />
                        )}
                        <button
                            type="button"
                            onClick={closeDetailDrawer}
                            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            {t('temuan.close')}
                        </button>
                    </div>
                }
            >
                {detailTarget && (
                    <div className="space-y-4">
                        {/* Soft-deleted Alert Banner */}
                        {Boolean(detailTarget.deleted_at) && (
                            <div className="flex flex-col justify-between gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800 shadow-2xs sm:flex-row sm:items-center dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">
                                <div className="flex items-start gap-2.5 sm:items-center">
                                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-rose-600 sm:mt-0 dark:text-rose-400" />
                                    <div>
                                        <span className="font-bold">Temuan Ini Telah Dihapus (Arsip)</span>
                                        <p className="mt-0.5 text-[11px] text-rose-700/90 dark:text-rose-300/80">
                                            Dihapus pada {formatDateTimeIndonesian(detailTarget.deleted_at as string)}. Status dan pembaruan
                                            dinonaktifkan.
                                        </p>
                                    </div>
                                </div>
                                {canDelete && (
                                    <button
                                        type="button"
                                        disabled={restoreBusy}
                                        onClick={() => handleRestore(detailTarget.id)}
                                        className="inline-flex items-center gap-1.5 self-start rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 disabled:opacity-50 sm:self-auto"
                                    >
                                        <RotateCcw className="h-3.5 w-3.5" />
                                        <span>{restoreBusy ? 'Memulihkan...' : 'Pulihkan Temuan'}</span>
                                    </button>
                                )}
                            </div>
                        )}

                        {/* Navigation Tabs Bar */}
                        <div className="flex items-center gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800/80">
                            <button
                                type="button"
                                onClick={() => setDetailActiveTab('action')}
                                className={`flex flex-1 items-center justify-center gap-1.5 rounded-lg py-2 text-xs font-bold transition-all ${
                                    detailActiveTab === 'action'
                                        ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-white'
                                        : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                                }`}
                            >
                                <CheckCircle2 className="text-primary h-3.5 w-3.5" />
                                <span>Tindak Lanjut &amp; Aksi</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setDetailActiveTab('history')}
                                className={`flex flex-1 items-center justify-center gap-1.5 rounded-lg py-2 text-xs font-bold transition-all ${
                                    detailActiveTab === 'history'
                                        ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-white'
                                        : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                                }`}
                            >
                                <History className="h-3.5 w-3.5 text-slate-500 dark:text-slate-400" />
                                <span>Riwayat Status</span>
                                <span
                                    className={`py-0.2 rounded-full px-1.5 text-[10px] font-bold ${
                                        detailActiveTab === 'history'
                                            ? 'bg-primary/10 text-primary dark:bg-primary-950 dark:text-primary-300'
                                            : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
                                    }`}
                                >
                                    {detailTarget.histories?.length || 0}
                                </span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setDetailActiveTab('control')}
                                className={`flex flex-1 items-center justify-center gap-1.5 rounded-lg py-2 text-xs font-bold transition-all ${
                                    detailActiveTab === 'control'
                                        ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-white'
                                        : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                                }`}
                            >
                                <Shield className="h-3.5 w-3.5 text-slate-500 dark:text-slate-400" />
                                <span>Klausul Kontrol</span>
                            </button>
                        </div>

                        {/* TAB 1: Tindak Lanjut & Aksi */}
                        {detailActiveTab === 'action' && (
                            <div className="space-y-4">
                                {/* Compact Context Summary Card */}
                                <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3 dark:border-slate-800">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="border-primary-200 bg-primary-50 text-primary dark:border-primary-800 dark:bg-navy-900 dark:text-primary-200 inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-bold">
                                                <Shield className="h-3.5 w-3.5" />
                                                {detailTarget.control?.kode_klausul || 'Klausul SMKI'}
                                            </span>
                                            {detailTarget.control?.framework && (
                                                <span className="rounded-lg bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                    {detailTarget.control.framework.nama} ({detailTarget.control.framework.versi})
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <StatusBadge tone={SEVERITY_TONE[detailTarget.kategori] ?? 'gray'}>
                                                {severityLabel(detailTarget.kategori)}
                                            </StatusBadge>
                                        </div>
                                    </div>

                                    <div className="mt-3">
                                        <h3 className="text-sm leading-relaxed font-bold text-slate-900 dark:text-white">
                                            {detailTarget.control?.judul || '—'}
                                        </h3>
                                    </div>

                                    {/* 2-Column Key-Value Row for Unit/PIC and SLA */}
                                    <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div className="flex items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/70 p-3 text-xs dark:border-slate-800 dark:bg-slate-800/40">
                                            <Building2 className="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                            <div className="min-w-0 flex-1">
                                                <span className="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                                                    Unit Kerja &amp; PIC
                                                </span>
                                                <p className="truncate font-semibold text-slate-800 dark:text-slate-200">
                                                    {detailTarget.unit?.nama || '—'}
                                                </p>
                                                <p className="flex items-center gap-1 truncate text-[11px] text-slate-500 dark:text-slate-400">
                                                    <UserCheck className="h-3 w-3 shrink-0 text-slate-400" />
                                                    <span>PIC: {detailTarget.pic?.name || 'Belum ditugaskan'}</span>
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2.5 rounded-xl border border-slate-100 bg-slate-50/70 p-3 text-xs dark:border-slate-800 dark:bg-slate-800/40">
                                            <Calendar className="h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                            <div className="min-w-0 flex-1">
                                                <span className="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                                                    Batas Waktu SLA
                                                </span>
                                                <p className="font-semibold text-slate-800 dark:text-slate-200">
                                                    {detailTarget.deadline ? formatDateIndonesian(detailTarget.deadline) : 'Tidak ditentukan'}
                                                </p>
                                                <div className="mt-1">{deadlineChip(detailTarget)}</div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Catatan Admin & Catatan PIC */}
                                    {(detailTarget.admin_notes || detailTarget.catatan_admin || detailTarget.catatan) && (
                                        <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            {(detailTarget.admin_notes || detailTarget.catatan_admin) && (
                                                <div className="rounded-xl border border-amber-200/90 bg-amber-50/60 p-3 text-xs dark:border-amber-900/50 dark:bg-amber-950/20">
                                                    <div className="flex items-center gap-1.5 font-bold text-amber-900 dark:text-amber-300">
                                                        <FileText className="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                                                        <span>Catatan Admin Kepatuhan</span>
                                                    </div>
                                                    <p className="mt-1 leading-relaxed whitespace-pre-line text-slate-700 dark:text-slate-300">
                                                        {detailTarget.admin_notes || detailTarget.catatan_admin}
                                                    </p>
                                                </div>
                                            )}
                                            {detailTarget.catatan && (
                                                <div className="rounded-xl border border-blue-200/90 bg-blue-50/60 p-3 text-xs dark:border-blue-900/50 dark:bg-blue-950/20">
                                                    <div className="flex items-center gap-1.5 font-bold text-blue-900 dark:text-blue-300">
                                                        <FileText className="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                                                        <span>Catatan PIC</span>
                                                    </div>
                                                    <p className="mt-1 leading-relaxed whitespace-pre-line text-slate-700 dark:text-slate-300">
                                                        {detailTarget.catatan}
                                                    </p>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>

                                {/* Status & Alur Tindak Lanjut (Workflow Hub) */}
                                {renderStatusWorkflowHub(detailTarget)}

                                {/* Hint Shortcut to History Tab */}
                                {detailTarget.histories && detailTarget.histories.length > 0 && (
                                    <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200/80 bg-white p-3 text-xs shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                                        <div className="flex min-w-0 items-center gap-2 text-slate-500 dark:text-slate-400">
                                            <History className="h-3.5 w-3.5 shrink-0 text-slate-400" />
                                            <span className="truncate">
                                                Aktivitas terakhir oleh{' '}
                                                <strong className="text-slate-800 dark:text-slate-200">
                                                    {detailTarget.histories[detailTarget.histories.length - 1]?.user?.name || 'Sistem SMKI'}
                                                </strong>{' '}
                                                ({formatDateTimeIndonesian(detailTarget.histories[detailTarget.histories.length - 1]?.created_at)})
                                            </span>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setDetailActiveTab('history')}
                                            className="text-primary hover:text-primary-700 dark:text-primary-300 inline-flex shrink-0 items-center gap-1 font-semibold"
                                        >
                                            <span>Lihat Riwayat ({detailTarget.histories.length})</span>
                                            <ArrowRight className="h-3 w-3" />
                                        </button>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* TAB 2: Riwayat Status & Audit Trail */}
                        {detailActiveTab === 'history' && (
                            <div className="space-y-4">
                                <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                                    <div className="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                                        <div className="flex items-center gap-2 text-xs font-bold tracking-wider text-slate-800 uppercase dark:text-slate-200">
                                            <History className="text-primary dark:text-primary-300 h-4 w-4" />
                                            <span>Catatan Tindak Lanjut &amp; Riwayat Status</span>
                                        </div>
                                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                            {detailTarget.histories?.length || 0} Entri
                                        </span>
                                    </div>

                                    <div className="space-y-4 pt-3">
                                        {detailTarget.histories && detailTarget.histories.length > 0 ? (
                                            <div className="relative space-y-4 pl-6 before:absolute before:top-2 before:bottom-2 before:left-2.5 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-800">
                                                {detailTarget.histories.map((hist, hIdx) => {
                                                    const isInitial = hist.from_status === null;
                                                    const isBackward =
                                                        hist.from_status &&
                                                        STEPS.findIndex((s) => s.id === hist.from_status) >
                                                            STEPS.findIndex((s) => s.id === hist.to_status);

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
                                                                        <div className="min-w-0 flex-1">
                                                                            {(() => {
                                                                                const r =
                                                                                    typeof hist.user?.role === 'string'
                                                                                        ? hist.user.role
                                                                                        : hist.user?.role?.name;
                                                                                const isPicNote = r === 'pic';
                                                                                return (
                                                                                    <span
                                                                                        className={`mb-1 inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold ${
                                                                                            isPicNote
                                                                                                ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300'
                                                                                                : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                                                                                        }`}
                                                                                    >
                                                                                        {isPicNote ? 'Catatan PIC' : 'Catatan Admin'}
                                                                                    </span>
                                                                                );
                                                                            })()}
                                                                            <p className="italic">"{hist.catatan}"</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        ) : (
                                            <EmptyState message="Belum ada catatan tindak lanjut atau riwayat status tercatat." />
                                        )}
                                    </div>
                                </div>

                                {/* Audit Metadata */}
                                <div className="flex items-center justify-between border-t border-slate-100 px-1 pt-3 text-[11px] text-slate-400 dark:border-slate-800">
                                    <span>Dibuat: {detailTarget.created_at ? formatDateTimeIndonesian(detailTarget.created_at) : '—'}</span>
                                    {detailTarget.verified_at && <span>Diverifikasi: {formatDateTimeIndonesian(detailTarget.verified_at)}</span>}
                                </div>
                            </div>
                        )}

                        {/* TAB 3: Klausul Kontrol */}
                        {detailActiveTab === 'control' && (
                            <div className="space-y-4">
                                <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="border-primary-200 bg-primary-50 text-primary dark:border-primary-800 dark:bg-navy-900 dark:text-primary-200 inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-bold">
                                            <Shield className="h-3.5 w-3.5" />
                                            {detailTarget.control?.kode_klausul || 'Klausul SMKI'}
                                        </span>
                                        {detailTarget.control?.framework && (
                                            <span className="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                {detailTarget.control.framework.nama} ({detailTarget.control.framework.versi})
                                            </span>
                                        )}
                                    </div>

                                    <div className="mt-3">
                                        <span className="text-[10px] font-bold tracking-wider text-slate-400 uppercase">Judul Kontrol</span>
                                        <h3 className="mt-1 text-base font-bold text-slate-900 dark:text-white">
                                            {detailTarget.control?.judul || '—'}
                                        </h3>
                                    </div>

                                    <div className="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                                        <span className="text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                                            Deskripsi &amp; Panduan Implementasi Kontrol
                                        </span>
                                        <div className="mt-2 rounded-xl border border-slate-100 bg-slate-50/70 p-4 text-xs leading-relaxed whitespace-pre-line text-slate-700 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-300">
                                            {detailTarget.control?.deskripsi || 'Tidak ada deskripsi rinci untuk kontrol kepatuhan ini.'}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex justify-end">
                                    <button
                                        type="button"
                                        onClick={() => setDetailActiveTab('action')}
                                        className="text-primary hover:text-primary-700 dark:text-primary-300 inline-flex items-center gap-1.5 text-xs font-semibold"
                                    >
                                        <span>Kembali ke Tindak Lanjut &amp; Aksi</span>
                                        <ArrowRight className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </SlideOver>

            <ConfirmDialog
                open={deleteDialogOpen}
                title={t('temuan.deleteFinding')}
                description={deleteTarget ? t('temuan.deleteConfirm', String(findingRef(deleteTarget))) : ''}
                confirmLabel={t('temuan.deleteFinding')}
                cancelLabel={t('common.cancel')}
                variant="danger"
                busy={deleteBusy}
                onCancel={cancelDelete}
                onConfirm={confirmDelete}
            />
        </AppLayout>
    );
}
