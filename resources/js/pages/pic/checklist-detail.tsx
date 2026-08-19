import ChecklistDetailSkeleton from '@/components/skeletons/ChecklistDetailSkeleton';
import SyncWorker from '@/components/SyncWorker';
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
    FileText,
    Search,
    Send,
    Shield,
    ShieldAlert,
    ShieldCheck,
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
}: {
    entryId: number;
    onEntryUpdate: (id: number, changes: Record<string, unknown>) => void;
    onEvidenceUpdate: (
        id: number,
        evidence: { id: number; checklist_entry_id: number; version_number: number; file_url: string; nama_file: string; is_active: boolean },
    ) => void;
}) {
    const entry = useAssessmentEntry(entryId);
    const [localCatatan, setLocalCatatan] = useState(entry?.catatan || '');
    const [saveState, setSaveState] = useState<'idle' | 'saved'>('idle');
    const [uploading, setUploading] = useState(false);
    const savedTimeoutRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

    useEffect(() => {
        if (entry?.catatan !== undefined && entry.catatan !== localCatatan) {
            setLocalCatatan(entry.catatan || '');
        }
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
    const isCatatanRequired = entry.status === 'non_compliant' || entry.status === 'na';
    const isCatatanMissing = isCatatanRequired && !localCatatan.trim();
    const isEvidenceMissing = !entry.active_evidence;

    return (
        <div className="border-b border-slate-100 py-5 last:border-b-0 dark:border-slate-800">
            <div className="mb-2 flex items-start justify-between">
                <div className="flex items-center gap-2">
                    <span className="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800">
                        {entry.control.kode_klausul}
                    </span>
                    <h4 className="text-sm font-bold text-slate-900 dark:text-white">{entry.control.judul}</h4>
                </div>
                {saveState !== 'idle' && (
                    <span className="inline-flex items-center gap-1 text-xs font-medium text-blue-600">
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
                        placeholder={isCatatanRequired ? 'Catatan wajib diisi...' : 'Catatan...'}
                        className={`flex-1 rounded-lg border bg-white px-3 py-2 text-xs text-slate-700 placeholder-slate-400 transition-colors focus:ring-1 dark:bg-slate-900 dark:text-slate-300 ${
                            isCatatanMissing
                                ? 'border-red-300 focus:border-red-400 focus:ring-red-400'
                                : 'border-slate-200 focus:border-blue-400 focus:ring-blue-400 dark:border-slate-700'
                        }`}
                    />
                    <label
                        className={`inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-medium transition-colors ${
                            uploading
                                ? 'cursor-wait border-blue-200 bg-blue-50 text-blue-400 dark:border-blue-800 dark:bg-blue-950'
                                : 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-400'
                        }`}
                    >
                        {uploading ? (
                            <>
                                <span className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-blue-400 border-t-transparent" />
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
                            accept=".jpg,.jpeg,.png,.webp,.gif"
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
                                        setSaveState('saved');
                                        if (savedTimeoutRef.current) clearTimeout(savedTimeoutRef.current);
                                        savedTimeoutRef.current = setTimeout(() => setSaveState('idle'), 2000);
                                    })
                                    .catch(() => {
                                        /* silent */
                                    })
                                    .finally(() => setUploading(false));
                            }}
                        />
                    </label>
                </div>
                {isCatatanMissing && (
                    <div className="flex items-center gap-1 text-[11px] font-medium text-red-500">
                        <XCircle className="h-3 w-3" />
                        Catatan wajib diisi untuk status ini
                    </div>
                )}
                {isEvidenceMissing && (
                    <div className="flex items-center gap-1 text-[11px] font-medium text-amber-500">
                        <XCircle className="h-3 w-3" />
                        Bukti wajib diunggah
                    </div>
                )}
            </div>

            {entry.active_evidence && (
                <div className="mt-2 flex items-center gap-1.5 text-xs text-slate-500">
                    <FileText className="h-3.5 w-3.5" />
                    <a
                        href={entry.active_evidence.file_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="max-w-[240px] truncate font-medium text-blue-600 underline-offset-2 hover:underline dark:text-blue-400"
                    >
                        {entry.active_evidence.nama_file}
                    </a>
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
    const isLoading = usePageLoading();

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

    const filteredEntries = useMemo(() => {
        if (!searchQuery.trim()) return currentEntries;
        const q = searchQuery.toLowerCase();
        return currentEntries.filter(
            (e) =>
                e.control.kode_klausul.toLowerCase().includes(q) ||
                e.control.judul.toLowerCase().includes(q) ||
                (e.control.deskripsi && e.control.deskripsi.toLowerCase().includes(q)),
        );
    }, [currentEntries, searchQuery]);

    const currentPageMeta = pageMeta[currentPageIndex];

    const isCurrentPageComplete = useMemo(() => {
        if (currentEntries.length === 0) return false;
        return currentEntries.every((e) => e.status && e.status !== '' && e.active_evidence);
    }, [currentEntries]);

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
            loadPage(currentPageIndex - 1);
            scrollToTop();
        }
    };

    const handleNextPage = () => {
        if (currentPageIndex < pageMeta.length - 1) {
            loadPage(currentPageIndex + 1);
            scrollToTop();
        }
    };

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

            <div className="sticky top-[76px] z-30 mb-6 rounded-2xl border border-blue-100 bg-gradient-to-r from-blue-50 to-blue-100/50 p-5 shadow-sm backdrop-blur-sm dark:border-blue-900 dark:from-blue-950/50 dark:to-blue-900/30">
                <div className="mb-2 flex items-center justify-between">
                    <span className="text-[11px] font-bold tracking-wider text-blue-600 uppercase">Progress Pengecekan</span>
                    <div className="flex items-baseline gap-1.5">
                        <span className="text-2xl font-bold text-blue-700 dark:text-blue-400">{progress}%</span>
                        <span className="text-xs text-blue-500">
                            {completedEntries}/{totalEntries} Kontrol
                        </span>
                    </div>
                </div>
                <div className="h-2 w-full overflow-hidden rounded-full bg-blue-200/50 dark:bg-blue-800/50">
                    <div className="h-full rounded-full bg-blue-600 transition-all duration-500" style={{ width: `${progress}%` }} />
                </div>
            </div>

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="relative flex-1">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari kode atau judul kontrol..."
                        className="w-full rounded-lg border border-slate-200 bg-white py-2 pr-3 pl-9 text-sm text-slate-700 placeholder-slate-400 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                    />
                </div>
                <select
                    value={frameworkFilter}
                    onChange={(e) => setFrameworkFilter(e.target.value)}
                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                >
                    <option value="">Semua Framework</option>
                    {frameworks.map((f) => (
                        <option key={f.name} value={f.name}>
                            {f.name} ({f.ver})
                        </option>
                    ))}
                </select>
                <select
                    value={kategoriFilter}
                    onChange={(e) => setKategoriFilter(e.target.value)}
                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                >
                    <option value="">Semua Kategori</option>
                    {kategoris.map((k) => (
                        <option key={k.value} value={k.value}>
                            {k.label}
                        </option>
                    ))}
                </select>
            </div>

            <div className="space-y-6">
                {grouped.map(([groupKey, items]) => (
                    <div key={groupKey}>
                        <div className="mb-3 flex items-center gap-3 rounded-xl border border-blue-100 bg-white px-4 py-3 shadow-sm dark:border-blue-900 dark:bg-slate-900">
                            <span className="inline-flex items-center rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-bold text-white shadow-sm">
                                {items[0].control.framework_name}
                            </span>
                            <span className="text-slate-300 dark:text-slate-600">|</span>
                            <span className="text-sm font-semibold text-slate-700 dark:text-slate-300">
                                {formatKategori(items[0].control.kategori)}
                            </span>
                            <span className="ml-auto rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                {items.length} Kontrol
                            </span>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                            {items.map((entry) => (
                                <EntryItemRow key={entry.id} entry={entry} onEntryUpdate={handleEntryUpdate} />
                            ))}
                        </div>
                    </div>
                ))}
            </div>

            <div className="mt-8 flex flex-col items-center gap-3">
                {invalidCount > 0 && <p className="text-xs text-slate-500">{invalidCount} kontrol belum terisi dengan benar</p>}
                <button
                    type="button"
                    disabled={invalidCount > 0}
                    onClick={() => router.get(`/admin/pic/assessments/${session.id}/summary`)}
                    className={`inline-flex items-center gap-2 rounded-lg px-6 py-3 text-sm font-semibold shadow-sm transition-all ${
                        invalidCount === 0
                            ? 'bg-blue-600 text-white hover:bg-blue-700'
                            : 'cursor-not-allowed bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-600'
                    }`}
                >
                    <Send className="h-4 w-4" />
                    Kirim Pengecekan
                </button>
            </div>

            <button
                type="button"
                onClick={atBottom ? scrollToTop : scrollToBottom}
                aria-label={atBottom ? 'Ke atas' : 'Ke bawah'}
                className="fixed right-5 bottom-5 z-40 flex h-12 w-12 items-center justify-center rounded-full bg-blue-600 text-white shadow-lg shadow-blue-600/30 transition-all hover:bg-blue-700 hover:shadow-blue-600/40"
            >
                {atBottom ? <ArrowUpToLine className="h-5 w-5" /> : <ArrowDownToLine className="h-5 w-5" />}
            </button>
        </AppLayout>
    );
}
