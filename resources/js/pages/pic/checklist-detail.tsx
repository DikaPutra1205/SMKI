import ChecklistDetailSkeleton from '@/components/skeletons/ChecklistDetailSkeleton';
import SyncWorker from '@/components/SyncWorker';
import { Modal } from '@/components/ui/Modal';
import { useAssessmentEntry, useAssessmentStore } from '@/hooks/useAssessmentStore';
import { usePageLoading } from '@/hooks/usePageLoading';
import AppLayout from '@/layouts/AppLayout';
import { assessmentStore } from '@/stores/assessmentStore';
import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownToLine,
    ArrowLeft,
    ArrowRight,
    ArrowUpToLine,
    Check,
    CheckCircle2,
    ExternalLink,
    Eye,
    Filter,
    Search,
    Send,
    Shield,
    ShieldAlert,
    ShieldCheck,
    ShieldHalf,
    Upload,
    XCircle,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface SessionData {
    id: number;
    konteks_penilaian: string;
    periode: string | null;
    unit_id: number;
    framework_id: number | null;
    created_at: string;
    updated_at: string;
    unit: { id: number; nama: string };
    framework: { id: number; nama: string; versi: string } | null;
}

interface PageMeta {
    index: number;
    framework_name: string;
    kategori: string;
    entry_count: number;
}

interface ChecklistPageResponse {
    entries: EntryInput[];
    page_meta: PageMeta[];
    total_entries: number;
}

interface EntryInput {
    id: number;
    control_id: number;
    status: string;
    catatan: string | null;
    catatan_admin: string | null;
    tanggal_input: string | null;
    tanggal_verifikasi: string | null;
    control: {
        id: number;
        framework_id: number;
        kode_klausul: string;
        judul: string;
        deskripsi: string | null;
        kategori: string;
        framework_name: string;
        framework_versi: string;
    };
    active_evidence: {
        id: number;
        checklist_entry_id: number;
        version_number: number;
        file_url: string;
        nama_file: string;
        is_active: boolean;
    } | null;
}

interface ChecklistDetailProps {
    session: SessionData;
    initialEntries: EntryInput[];
    pageMeta: PageMeta[];
    totalEntries: number;
}

const STATUS_OPTIONS = [
    {
        value: 'compliant',
        label: 'Patuh',
        icon: ShieldCheck,
        color: 'border-emerald-500 bg-emerald-50 text-emerald-700',
        radioColor: 'bg-emerald-500',
    },
    { value: 'partial', label: 'Sebagian Patuh', icon: ShieldHalf, color: 'border-amber-500 bg-amber-50 text-amber-700', radioColor: 'bg-amber-500' },
    { value: 'non_compliant', label: 'Ketidaksesuaian', icon: ShieldAlert, color: 'border-red-500 bg-red-50 text-red-700', radioColor: 'bg-red-500' },
    { value: 'na', label: 'Tidak Berlaku', icon: Shield, color: 'border-slate-400 bg-slate-50 text-slate-600', radioColor: 'bg-slate-400' },
];

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function formatKategori(kategori: string): string {
    return kategori.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function fetchChecklistPage(sessionId: number, page: number): Promise<ChecklistPageResponse> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `/admin/pic/assessments/${sessionId}/checklist-page?page=${page}`);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(JSON.parse(xhr.responseText));
            } else {
                reject(new Error(`HTTP ${xhr.status}`));
            }
        };
        xhr.onerror = () => reject(new Error('Network error'));
        xhr.send();
    });
}

function EntryItemRow({
    entryId,
    onEntryUpdate,
    onEvidenceUpdate,
    onPreviewEvidence,
    highlight,
}: {
    entryId: number;
    onEntryUpdate: (id: number, changes: Record<string, unknown>) => void;
    onEvidenceUpdate: (
        id: number,
        evidence: { id: number; checklist_entry_id: number; version_number: number; file_url: string; nama_file: string; is_active: boolean },
    ) => void;
    onPreviewEvidence: (evidence: { nama_file: string; file_url: string }) => void;
    highlight: boolean;
}) {
    const entry = useAssessmentEntry(entryId);
    const [localCatatan, setLocalCatatan] = useState(entry?.catatan || '');
    const [saveState, setSaveState] = useState<'idle' | 'saved'>('idle');
    const [uploading, setUploading] = useState(false);
    const savedTimeoutRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

    useEffect(() => {
        setLocalCatatan(entry?.catatan || '');
    }, [entry?.catatan]);

    useEffect(
        () => () => {
            if (savedTimeoutRef.current) clearTimeout(savedTimeoutRef.current);
        },
        [],
    );

    const showSaved = useCallback(() => {
        setSaveState('saved');
        if (savedTimeoutRef.current) clearTimeout(savedTimeoutRef.current);
        savedTimeoutRef.current = setTimeout(() => setSaveState('idle'), 2000);
    }, []);

    const handleStatusClick = useCallback(
        (status: string) => {
            onEntryUpdate(entryId, { status });
            showSaved();
        },
        [entryId, onEntryUpdate, showSaved],
    );

    const handleCatatanInput = useCallback(
        (value: string) => {
            setLocalCatatan(value);
            onEntryUpdate(entryId, { catatan: value });
            showSaved();
        },
        [entryId, onEntryUpdate, showSaved],
    );

    if (!entry) return null;

    const isVerified = entry.tanggal_verifikasi !== null;
    const missingStatus = !entry.status;
    const isEvidenceMissing = !entry.active_evidence;
    const isIncomplete = missingStatus || isEvidenceMissing;
    const showErrorLabels = highlight && isIncomplete;

    return (
        <div
            id={`entry-row-${entryId}`}
            className={`border-b border-slate-100 py-5 last:border-b-0 dark:border-slate-800 ${
                showErrorLabels ? 'rounded-lg border-l-4 border-l-red-400 bg-red-50/50 pr-2 pl-3 dark:bg-red-950/20' : ''
            }`}
        >
            <div className="mb-2 flex items-start justify-between">
                <div className="flex items-center gap-2">
                    <span className="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800">
                        {entry.control.kode_klausul}
                    </span>
                    <h4 className={`text-sm font-bold ${showErrorLabels ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white'}`}>
                        {entry.control.judul}
                    </h4>
                </div>
                {saveState !== 'idle' && (
                    <span className="text-primary inline-flex items-center gap-1 text-xs font-medium">
                        <Check className="h-3.5 w-3.5" />
                        Tersimpan
                    </span>
                )}
            </div>

            {entry.control.deskripsi && <p className="mb-3 text-xs leading-relaxed text-slate-500">{entry.control.deskripsi}</p>}

            {isVerified && (
                <div className="mb-3 flex items-center gap-1.5 text-xs text-emerald-600">
                    <CheckCircle2 className="h-3.5 w-3.5" />
                    Sudah Diverifikasi oleh Pengelola
                    {entry.catatan_admin && <span className="text-slate-400">&mdash; {entry.catatan_admin}</span>}
                </div>
            )}

            {showErrorLabels && missingStatus && (
                <div className="mb-2 flex items-center gap-1 text-[11px] font-medium text-red-500">
                    <XCircle className="h-3 w-3" />
                    Status wajib dipilih
                </div>
            )}

            <div className="mb-3 flex flex-wrap gap-2">
                {STATUS_OPTIONS.map((opt) => {
                    const Icon = opt.icon;
                    const isActive = entry.status === opt.value;
                    return (
                        <button
                            key={opt.value}
                            type="button"
                            onClick={() => handleStatusClick(opt.value)}
                            className={`flex items-center gap-2 rounded-lg border-2 px-3 py-2 text-xs font-semibold transition-all ${
                                isActive
                                    ? `${opt.color} border-current ring-1 ring-current/20`
                                    : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900'
                            }`}
                        >
                            <span
                                className={`flex h-4 w-4 items-center justify-center rounded-full border-2 ${isActive ? 'border-current' : 'border-slate-300'}`}
                            >
                                {isActive && <span className={`h-2 w-2 rounded-full ${opt.radioColor}`} />}
                            </span>
                            <Icon className="h-3.5 w-3.5" />
                            {opt.label}
                        </button>
                    );
                })}
            </div>

            <div className="flex flex-col gap-1.5">
                <div className="flex gap-3">
                    <input
                        type="text"
                        value={localCatatan}
                        onChange={(e) => handleCatatanInput(e.target.value)}
                        placeholder="Catatan tindak lanjut (opsional)..."
                        className="focus:border-primary focus:ring-primary flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 placeholder-slate-400 transition-colors focus:ring-1 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                    />
                    <label
                        className={`inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-medium transition-colors ${
                            uploading
                                ? 'border-primary-200 bg-primary-50 text-primary-300 dark:border-primary-800 dark:bg-navy-900 cursor-wait'
                                : 'border-primary-200 bg-primary-50 text-primary-700 hover:bg-primary-100 dark:border-primary-800 dark:bg-navy-900 dark:text-primary-200'
                        }`}
                    >
                        {uploading ? (
                            <>
                                <span className="border-primary h-3.5 w-3.5 animate-spin rounded-full border-2 border-t-transparent" />
                                Mengunggah...
                            </>
                        ) : (
                            <>
                                <Upload className="h-3.5 w-3.5" />
                                {entry.active_evidence ? 'Unggah Ulang' : 'Unggah Bukti'}
                            </>
                        )}
                        <input
                            type="file"
                            className="hidden"
                            accept=".jpg,.jpeg,.png,.webp,.gif,.pdf"
                            disabled={uploading}
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (!file || uploading) return;
                                setUploading(true);
                                const fd = new FormData();
                                fd.append('bukti_file', file);
                                fetch(`/admin/pic/checklist-entries/${entry.id}/evidence`, {
                                    method: 'POST',
                                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
                                    body: fd,
                                })
                                    .then((r) => r.json())
                                    .then((data) => {
                                        if (data?.evidence) {
                                            onEvidenceUpdate(entry.id, data.evidence);
                                        }
                                        showSaved();
                                    })
                                    .catch(() => {
                                        /* silent */
                                    })
                                    .finally(() => setUploading(false));
                            }}
                        />
                    </label>
                </div>
                {isEvidenceMissing && (
                    <div className={`flex items-center gap-1 text-[11px] font-medium ${showErrorLabels ? 'text-red-500' : 'text-amber-500'}`}>
                        <XCircle className="h-3 w-3" />
                        Bukti wajib diunggah
                    </div>
                )}
            </div>

            {entry.active_evidence && (
                <div className="mt-2.5 flex items-center gap-3 text-xs">
                    <button
                        type="button"
                        onClick={() => onPreviewEvidence({ nama_file: entry.active_evidence!.nama_file, file_url: entry.active_evidence!.file_url })}
                        className="text-primary hover:text-primary-700 dark:text-primary-200 dark:hover:text-primary-200 inline-flex items-center gap-1.5 font-semibold hover:underline"
                    >
                        <Eye className="h-3.5 w-3.5" />
                        <span className="max-w-[260px] truncate">{entry.active_evidence.nama_file}</span>
                    </button>
                    <span className="text-[11px] text-slate-400 dark:text-slate-500">(Klik untuk pratinjau)</span>
                </div>
            )}
        </div>
    );
}

export default function ChecklistDetail({ session, initialEntries, pageMeta, totalEntries }: ChecklistDetailProps) {
    const { flash } = usePage<{ flash?: { type: string; message: string } }>().props;
    const [flashVisible, setFlashVisible] = useState(false);
    const [currentPageIndex, setCurrentPageIndex] = useState(0);
    const [pageLoading, setPageLoading] = useState(false);
    const [atBottom, setAtBottom] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [showOnlyIncomplete, setShowOnlyIncomplete] = useState(false);
    const [previewEvidence, setPreviewEvidence] = useState<{ nama_file: string; file_url: string } | null>(null);
    const [highlightIncomplete, setHighlightIncomplete] = useState(false);
    const isLoading = usePageLoading('/admin/pic/assessments/');

    const {
        initialize,
        updateEntry,
        updateEvidence,
        entries: storeEntries,
        computeProgress,
        setCurrentPage,
        setPageEntries,
        getPageEntries,
        hasPage,
    } = useAssessmentStore();

    const loadPage = useCallback(
        async (pageIndex: number) => {
            if (hasPage(pageIndex)) {
                setCurrentPage(pageIndex);
                setCurrentPageIndex(pageIndex);
                return;
            }
            setPageLoading(true);
            try {
                const data = await fetchChecklistPage(session.id, pageIndex);
                setPageEntries(pageIndex, data.entries as unknown as import('@/stores/assessmentStore').EntryItem[]);
                setCurrentPage(pageIndex);
                setCurrentPageIndex(pageIndex);
            } finally {
                setPageLoading(false);
            }
        },
        [session.id, hasPage, setCurrentPage, setPageEntries],
    );

    useEffect(() => {
        initialize(session.id, initialEntries as unknown as import('@/stores/assessmentStore').EntryItem[], pageMeta, totalEntries);
        setPageEntries(0, initialEntries as unknown as import('@/stores/assessmentStore').EntryItem[]);
        setCurrentPage(0);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (pageMeta.length <= 1) return;
        const pagesToFetch: number[] = [];
        for (let p = 1; p < pageMeta.length; p++) {
            if (!hasPage(p)) {
                pagesToFetch.push(p);
            }
        }
        for (const p of pagesToFetch) {
            fetchChecklistPage(session.id, p).then((data) => {
                setPageEntries(p, data.entries as unknown as import('@/stores/assessmentStore').EntryItem[]);
            });
        }
    }, [session.id, pageMeta, hasPage, setPageEntries]);

    const currentEntries: EntryInput[] = useMemo(() => {
        return getPageEntries(currentPageIndex) ?? [];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentPageIndex, getPageEntries, storeEntries]);

    const isEntryIncomplete = (e: EntryInput): boolean => {
        return !e.status || !e.active_evidence;
    };

    const filteredEntries = useMemo(() => {
        let list = currentEntries;
        if (showOnlyIncomplete) {
            list = list.filter((e) => isEntryIncomplete(e));
        }
        if (!searchQuery.trim()) return list;
        const q = searchQuery.toLowerCase();
        return list.filter(
            (e) =>
                e.control.kode_klausul.toLowerCase().includes(q) ||
                e.control.judul.toLowerCase().includes(q) ||
                (e.control.deskripsi && e.control.deskripsi.toLowerCase().includes(q)),
        );
    }, [currentEntries, showOnlyIncomplete, searchQuery]);

    const currentPageMeta = pageMeta[currentPageIndex];

    const isCurrentPageComplete = useMemo(() => {
        if (currentEntries.length === 0) return false;
        return currentEntries.every((e) => !isEntryIncomplete(e));
    }, [currentEntries]);

    const incompleteCount = useMemo(() => currentEntries.filter((e) => isEntryIncomplete(e)).length, [currentEntries]);

    const firstIncompleteEntryId = useMemo(() => {
        const source = filteredEntries.length > 0 ? filteredEntries : currentEntries;
        return source.find((e) => isEntryIncomplete(e))?.id ?? null;
    }, [filteredEntries, currentEntries]);

    const jumpToNextIncomplete = () => {
        if (firstIncompleteEntryId !== null) {
            setHighlightIncomplete(true);
            document.getElementById(`entry-row-${firstIncompleteEntryId}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };

    useEffect(() => {
        const onScroll = () => {
            const threshold = 80;
            const nearBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - threshold;
            setAtBottom(nearBottom);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const scrollToBottom = () => {
        window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
    };

    const scrollToTop = () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    useEffect(() => {
        if (flash?.message) {
            setFlashVisible(true);
            const t = setTimeout(() => setFlashVisible(false), 4000);
            return () => clearTimeout(t);
        }
    }, [flash]);

    const progress = computeProgress();

    const handleEntryUpdate = useCallback(
        (entryId: number, data: Record<string, unknown>) => {
            updateEntry(entryId, data as Partial<import('@/stores/assessmentStore').EntryItem>);
        },
        [updateEntry],
    );

    const handleEvidenceUpdate = useCallback(
        (entryId: number, evidence: import('@/stores/assessmentStore').EvidenceData) => {
            updateEvidence(entryId, evidence);
        },
        [updateEvidence],
    );

    const handlePrevPage = () => {
        if (currentPageIndex > 0) {
            setHighlightIncomplete(false);
            loadPage(currentPageIndex - 1);
            scrollToTop();
        }
    };

    const handleNextPage = () => {
        if (!isCurrentPageComplete) {
            setHighlightIncomplete(true);
            if (firstIncompleteEntryId !== null) {
                document.getElementById(`entry-row-${firstIncompleteEntryId}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }
        setHighlightIncomplete(false);
        if (currentPageIndex < pageMeta.length - 1) {
            loadPage(currentPageIndex + 1);
            scrollToTop();
        }
    };

    const isImageFile = (filename: string) => /\.(jpe?g|png|gif|webp|svg)$/i.test(filename);

    if (isLoading) {
        return <ChecklistDetailSkeleton />;
    }

    return (
        <AppLayout>
            <Head title={session.konteks_penilaian} />
            <SyncWorker sessionId={session.id} />

            {flash?.message && flashVisible && (
                <div className="mb-4 flex items-center gap-2 rounded-lg border px-4 py-3 text-sm font-medium shadow-sm">
                    {flash.type === 'success' ? (
                        <div className="flex items-center gap-2 text-emerald-700">
                            <CheckCircle2 className="h-4 w-4" />
                            {flash.message}
                        </div>
                    ) : (
                        <div className="flex items-center gap-2 text-red-700">
                            <AlertTriangle className="h-4 w-4" />
                            {flash.message}
                        </div>
                    )}
                </div>
            )}

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">{session.konteks_penilaian}</h1>
                    <p className="mt-1 text-sm text-slate-500">Pengecekan Mandiri Kepatuhan &middot; {session.periode || 'Tanpa Periode'}</p>
                </div>
                <button
                    type="button"
                    onClick={() => router.get('/admin/pic/assessments')}
                    className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                >
                    Kembali
                </button>
            </div>

            {/* Sticky Progress Header */}
            <div className="border-primary-100 from-primary-50 to-primary-100/50 dark:border-navy-800 dark:from-navy-900/50 dark:to-navy-800/30 sticky top-[76px] z-10 mb-6 rounded-2xl border bg-gradient-to-r p-5 shadow-sm backdrop-blur-sm">
                <div className="mb-2 flex items-center justify-between">
                    <span className="text-primary text-[11px] font-bold tracking-wider uppercase">Progress Pengecekan</span>
                    <div className="flex items-center gap-3">
                        {incompleteCount > 0 && (
                            <button
                                type="button"
                                onClick={jumpToNextIncomplete}
                                className="hidden items-center gap-1 rounded-lg bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800 transition-colors hover:bg-amber-200 sm:inline-flex dark:bg-amber-950/60 dark:text-amber-300"
                            >
                                <ArrowDownToLine className="h-3 w-3" />
                                Lompat ke Kontrol Belum Terisi
                            </button>
                        )}
                        <div className="flex items-baseline gap-1.5">
                            <span className="text-primary-700 dark:text-primary-200 text-2xl font-bold">{progress.percentage}%</span>
                            <span className="text-primary text-xs">
                                {progress.completed}/{progress.total} Kontrol
                            </span>
                        </div>
                    </div>
                </div>
                <div className="bg-primary-100/50 dark:bg-navy-800/50 flex h-2 w-full overflow-hidden rounded-full">
                    {progress.compliantCount > 0 && (
                        <div
                            className="h-full bg-emerald-500 transition-all duration-500"
                            style={{ width: `${progress.total > 0 ? (progress.compliantCount / progress.total) * 100 : 0}%` }}
                        />
                    )}
                    {progress.partialCount > 0 && (
                        <div
                            className="h-full bg-amber-500 transition-all duration-500"
                            style={{ width: `${progress.total > 0 ? (progress.partialCount / progress.total) * 100 : 0}%` }}
                        />
                    )}
                    {progress.nonCompliantCount > 0 && (
                        <div
                            className="h-full bg-red-500 transition-all duration-500"
                            style={{ width: `${progress.total > 0 ? (progress.nonCompliantCount / progress.total) * 100 : 0}%` }}
                        />
                    )}
                    {progress.naCount > 0 && (
                        <div
                            className="h-full bg-slate-300 transition-all duration-500 dark:bg-slate-600"
                            style={{ width: `${progress.total > 0 ? (progress.naCount / progress.total) * 100 : 0}%` }}
                        />
                    )}
                </div>
            </div>

            {pageMeta.length > 0 && (
                <div className="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <button
                            type="button"
                            onClick={handlePrevPage}
                            disabled={currentPageIndex === 0 || pageLoading}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Sebelumnya
                        </button>

                        <div className="flex flex-col items-center gap-1">
                            <span className="text-xs font-medium text-slate-500">
                                Halaman {currentPageIndex + 1} dari {pageMeta.length}
                            </span>
                            {currentPageMeta && (
                                <span className="text-sm font-semibold text-slate-700 dark:text-slate-300">
                                    {currentPageMeta.framework_name} &bull; {formatKategori(currentPageMeta.kategori)}
                                </span>
                            )}
                            {currentPageMeta && <span className="text-[11px] text-slate-400">{currentPageMeta.entry_count} kontrol</span>}
                        </div>

                        <button
                            type="button"
                            onClick={handleNextPage}
                            disabled={currentPageIndex >= pageMeta.length - 1 || pageLoading}
                            title={!isCurrentPageComplete ? 'Masih ada kontrol yang belum lengkap — klik untuk menuju bagian yang belum diisi' : ''}
                            className={`inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium shadow-sm transition-colors ${
                                !isCurrentPageComplete && currentPageIndex < pageMeta.length - 1
                                    ? 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-400'
                                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
                            }`}
                        >
                            Selanjutnya
                            <ArrowRight className="h-4 w-4" />
                        </button>
                    </div>
                    {pageLoading && (
                        <div className="mt-2 flex justify-center">
                            <span className="text-primary text-xs">Memuat halaman...</span>
                        </div>
                    )}
                    {!pageLoading && !isCurrentPageComplete && (
                        <div className="mt-2 flex justify-center">
                            <span className="text-xs font-medium text-red-500">
                                {incompleteCount} kontrol belum lengkap (status atau bukti pendukung)
                                {currentPageIndex < pageMeta.length - 1 ? ' — klik "Selanjutnya" untuk menuju bagian yang belum diisi' : ''}
                            </span>
                        </div>
                    )}
                </div>
            )}

            <div className="space-y-6">
                <div>
                    {currentPageMeta && (
                        <div className="border-primary-100 dark:border-navy-800 mb-3 flex items-center gap-3 rounded-xl border bg-white px-4 py-3 shadow-sm dark:bg-slate-900">
                            <span className="bg-primary inline-flex items-center rounded-lg px-3 py-1.5 text-sm font-bold text-white shadow-sm">
                                {currentPageMeta.framework_name}
                            </span>
                            <span className="text-slate-300 dark:text-slate-600">|</span>
                            <span className="text-sm font-semibold text-slate-700 dark:text-slate-300">
                                {formatKategori(currentPageMeta.kategori)}
                            </span>
                            <span className="ml-auto rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                {currentEntries.length} Kontrol
                            </span>
                        </div>
                    )}

                    {/* Filter and Search Bar */}
                    <div className="mb-4 flex flex-wrap items-center gap-3">
                        <div className="relative min-w-[240px] flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Cari kode klausul atau judul kontrol..."
                                className="focus:border-primary focus:ring-primary w-full rounded-xl border border-slate-200 bg-white py-2.5 pr-3 pl-9 text-sm text-slate-700 placeholder-slate-400 transition-colors focus:ring-1 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:placeholder-slate-500"
                            />
                            {searchQuery && (
                                <button
                                    type="button"
                                    onClick={() => setSearchQuery('')}
                                    className="absolute top-1/2 right-3 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                >
                                    <XCircle className="h-4 w-4" />
                                </button>
                            )}
                        </div>

                        {/* Incomplete Filter Toggle */}
                        <button
                            type="button"
                            onClick={() => setShowOnlyIncomplete(!showOnlyIncomplete)}
                            className={`inline-flex items-center gap-2 rounded-xl border px-3.5 py-2.5 text-xs font-semibold transition-all ${
                                showOnlyIncomplete
                                    ? 'border-rose-400 bg-rose-50 text-rose-700 shadow-sm dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-400'
                                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
                            }`}
                        >
                            <Filter className="h-3.5 w-3.5" />
                            <span>{showOnlyIncomplete ? 'Menampilkan Kontrol Belum Lengkap' : 'Hanya Belum Selesai'}</span>
                            {incompleteCount > 0 && (
                                <span className="rounded-full bg-rose-200 px-1.5 py-0.5 text-[10px] font-bold text-rose-800 dark:bg-rose-900 dark:text-rose-200">
                                    {incompleteCount}
                                </span>
                            )}
                        </button>
                    </div>

                    {filteredEntries.length === 0 && (
                        <div className="mb-4 rounded-xl border border-slate-100 bg-slate-50 p-6 text-center text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
                            {showOnlyIncomplete
                                ? 'Selamat! Seluruh kontrol pada halaman ini telah lengkap terisi.'
                                : 'Tidak ada kontrol yang cocok dengan kriteria pencarian.'}
                        </div>
                    )}

                    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        {filteredEntries.map((entry) => (
                            <EntryItemRow
                                key={entry.id}
                                entryId={entry.id}
                                onEntryUpdate={handleEntryUpdate}
                                onEvidenceUpdate={handleEvidenceUpdate}
                                onPreviewEvidence={(evidence) => setPreviewEvidence(evidence)}
                                highlight={highlightIncomplete}
                            />
                        ))}
                    </div>
                </div>
            </div>

            <div className="mt-8 flex flex-col items-center gap-3">
                <button
                    type="button"
                    disabled={progress.invalidCount > 0}
                    onClick={async () => {
                        try {
                            await assessmentStore.flushDirty(session.id);
                        } catch {
                            /* continue */
                        }
                        router.get(`/admin/pic/assessments/${session.id}/summary`);
                    }}
                    className={`inline-flex items-center gap-2 rounded-lg px-6 py-3 text-sm font-semibold shadow-sm transition-all ${
                        progress.invalidCount === 0
                            ? 'bg-primary hover:bg-primary-700 text-white'
                            : 'cursor-not-allowed bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-600'
                    }`}
                >
                    <Send className="h-4 w-4" />
                    Kirim Pengecekan
                </button>
            </div>

            {/* Evidence Preview Lightbox Modal */}
            <Modal
                open={previewEvidence !== null}
                title={previewEvidence?.nama_file || 'Pratinjau Bukti Dokumen'}
                description="Pratinjau langsung bukti kepatuhan yang diunggah"
                onClose={() => setPreviewEvidence(null)}
                maxWidth="xl"
                footer={
                    <div className="flex w-full items-center justify-between">
                        {previewEvidence && (
                            <a
                                href={previewEvidence.file_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-primary hover:text-primary-700 dark:text-primary-200 inline-flex items-center gap-1.5 text-xs font-semibold"
                            >
                                <ExternalLink className="h-3.5 w-3.5" />
                                Buka Dokumen Asli
                            </a>
                        )}
                        <button
                            type="button"
                            onClick={() => setPreviewEvidence(null)}
                            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        >
                            Tutup
                        </button>
                    </div>
                }
            >
                {previewEvidence && (
                    <div className="flex flex-col items-center justify-center">
                        {isImageFile(previewEvidence.nama_file) ? (
                            <div className="flex max-h-[65vh] w-full items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 p-2 dark:border-slate-800 dark:bg-slate-950">
                                <img
                                    src={previewEvidence.file_url}
                                    alt={previewEvidence.nama_file}
                                    className="max-h-[60vh] max-w-full rounded-lg object-contain"
                                />
                            </div>
                        ) : (
                            <div className="h-[55vh] w-full overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                                <iframe src={previewEvidence.file_url} title={previewEvidence.nama_file} className="h-full w-full" />
                            </div>
                        )}
                    </div>
                )}
            </Modal>

            <button
                type="button"
                onClick={atBottom ? scrollToTop : scrollToBottom}
                aria-label={atBottom ? 'Ke atas' : 'Ke bawah'}
                className="bg-primary shadow-primary/30 hover:bg-primary-700 hover:shadow-primary/40 fixed right-5 bottom-5 z-40 flex h-12 w-12 items-center justify-center rounded-full text-white shadow-lg transition-all"
            >
                {atBottom ? <ArrowUpToLine className="h-5 w-5" /> : <ArrowDownToLine className="h-5 w-5" />}
            </button>
        </AppLayout>
    );
}
