import AppLayout from '@/layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, ArrowDownToLine, ArrowUpToLine, Check, CheckCircle2, FileText, Search, Send, Shield, ShieldAlert, ShieldCheck, Upload, XCircle } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface ControlData {
    id: number;
    framework_id: number;
    kode_klausul: string;
    judul: string;
    deskripsi: string | null;
    kategori: string;
    framework_name: string;
    framework_versi: string;
}

interface EvidenceData {
    id: number;
    checklist_entry_id: number;
    version_number: number;
    file_url: string;
    nama_file: string;
    is_active: boolean;
}

interface EntryItem {
    id: number;
    control_id: number;
    status: string;
    catatan: string | null;
    catatan_admin: string | null;
    tanggal_input: string | null;
    tanggal_verifikasi: string | null;
    control: ControlData;
    active_evidence: EvidenceData | null;
}

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

interface ChecklistDetailProps {
    session: SessionData;
    entries: EntryItem[];
}

const STATUS_OPTIONS = [
    { value: 'compliant', label: 'Patuh', icon: ShieldCheck, color: 'border-emerald-500 bg-emerald-50 text-emerald-700', radioColor: 'bg-emerald-500' },
    { value: 'non_compliant', label: 'Ketidaksesuaian', icon: ShieldAlert, color: 'border-red-500 bg-red-50 text-red-700', radioColor: 'bg-red-500' },
    { value: 'na', label: 'Tidak Berlaku', icon: Shield, color: 'border-slate-400 bg-slate-50 text-slate-600', radioColor: 'bg-slate-400' },
];

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function formatKategori(kategori: string): string {
    return kategori
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

function EntryItemRow({ entry, onEntryUpdate }: {
    entry: EntryItem;
    onEntryUpdate: (id: number, data: Partial<EntryItem>) => void;
}) {
    const [localStatus, setLocalStatus] = useState(entry.status);
    const [localCatatan, setLocalCatatan] = useState(entry.catatan || '');
    const [saveState, setSaveState] = useState<'idle' | 'saved'>('idle');
    const [uploading, setUploading] = useState(false);

    const debounceRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
    const abortRef = useRef<AbortController | null>(null);
    const savedTimeoutRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

    const saveToServer = useCallback((data: Record<string, unknown>) => {
        if (abortRef.current) abortRef.current.abort();
        abortRef.current = new AbortController();

        fetch(`/admin/pic/checklist-entries/${entry.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(data),
            signal: abortRef.current.signal,
        })
            .then((r) => {
                if (!r.ok) throw new Error();
                return r.json();
            })
            .then(() => {
                setSaveState('saved');
                if (savedTimeoutRef.current) clearTimeout(savedTimeoutRef.current);
                savedTimeoutRef.current = setTimeout(() => setSaveState('idle'), 2000);
            })
            .catch(() => {
                /* silent */
            });
    }, [entry.id]);

    const debouncedSave = useCallback((data: Record<string, unknown>) => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => saveToServer(data), 800);
    }, [saveToServer]);

    useEffect(() => () => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        if (abortRef.current) abortRef.current.abort();
        if (savedTimeoutRef.current) clearTimeout(savedTimeoutRef.current);
    }, []);

    const handleStatusClick = useCallback((status: string) => {
        setLocalStatus(status);
        onEntryUpdate(entry.id, { status });
        debouncedSave({ status });
    }, [entry.id, onEntryUpdate, debouncedSave]);

    const handleCatatanInput = useCallback((value: string) => {
        setLocalCatatan(value);
        onEntryUpdate(entry.id, { catatan: value });
        debouncedSave({ catatan: value });
    }, [entry.id, onEntryUpdate, debouncedSave]);

    const isVerified = entry.tanggal_verifikasi !== null;
    const isCatatanRequired = localStatus === 'non_compliant' || localStatus === 'na';
    const isCatatanMissing = isCatatanRequired && !localCatatan.trim();

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

            {entry.control.deskripsi && (
                <p className="mb-3 text-xs leading-relaxed text-slate-500">{entry.control.deskripsi}</p>
            )}

            {isVerified && (
                <div className="mb-3 flex items-center gap-1.5 text-xs text-emerald-600">
                    <CheckCircle2 className="h-3.5 w-3.5" />
                    Sudah Diverifikasi oleh Pengelola
                    {entry.catatan_admin && (
                        <span className="text-slate-400">&mdash; {entry.catatan_admin}</span>
                    )}
                </div>
            )}

            <div className="mb-3 flex flex-wrap gap-2">
                {STATUS_OPTIONS.map((opt) => {
                    const Icon = opt.icon;
                    const isActive = localStatus === opt.value;
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
                            <span className={`flex h-4 w-4 items-center justify-center rounded-full border-2 ${isActive ? 'border-current' : 'border-slate-300'}`}>
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
                    <label className={`inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-medium transition-colors ${
                        uploading
                            ? 'cursor-wait border-blue-200 bg-blue-50 text-blue-400 dark:border-blue-800 dark:bg-blue-950'
                            : 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-400'
                    }`}>
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
                                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': getCsrfToken() },
                                    body: fd,
                                })
                                    .then((r) => r.json())
                                    .then((data) => {
                                        if (data?.evidence) {
                                            onEntryUpdate(entry.id, { active_evidence: data.evidence });
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
            </div>

            {entry.active_evidence && (
                <div className="mt-2 flex items-center gap-1.5 text-xs text-slate-500">
                    <FileText className="h-3.5 w-3.5" />
                    <a
                        href={entry.active_evidence.file_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="truncate max-w-[240px] font-medium text-blue-600 underline-offset-2 hover:underline dark:text-blue-400"
                    >
                        {entry.active_evidence.nama_file}
                    </a>
                </div>
            )}
        </div>
    );
}

export default function ChecklistDetail({ session, entries: initialEntries }: ChecklistDetailProps) {
    const { flash } = usePage<{ flash?: { type: string; message: string } }>().props;
    const [flashVisible, setFlashVisible] = useState(false);
    const [entries, setEntries] = useState(initialEntries);
    const [search, setSearch] = useState('');
    const [frameworkFilter, setFrameworkFilter] = useState('');
    const [kategoriFilter, setKategoriFilter] = useState('');
    const [atBottom, setAtBottom] = useState(false);

    useEffect(() => {
        const onScroll = () => {
            const threshold = 80;
            const nearBottom =
                window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - threshold;
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

    const frameworks = useMemo(() => {
        const map = new Map<string, { name: string; ver: string }>();
        entries.forEach((e) => map.set(e.control.framework_name, { name: e.control.framework_name, ver: e.control.framework_versi }));
        return Array.from(map.values());
    }, [entries]);

    const kategoris = useMemo(() => {
        const set = new Set(entries.map((e) => e.control.kategori));
        return Array.from(set).sort().map((k) => ({
            value: k,
            label: formatKategori(k),
        }));
    }, [entries]);

    const filtered = useMemo(() => {
        let result = entries;
        if (search) {
            const q = search.toLowerCase();
            result = result.filter(
                (e) => e.control.kode_klausul.toLowerCase().includes(q) || e.control.judul.toLowerCase().includes(q),
            );
        }
        if (frameworkFilter) {
            result = result.filter((e) => e.control.framework_name === frameworkFilter);
        }
        if (kategoriFilter) {
            result = result.filter((e) => e.control.kategori === kategoriFilter);
        }
        return result;
    }, [entries, search, frameworkFilter, kategoriFilter]);

    const grouped = useMemo(() => {
        const groups = new Map<string, EntryItem[]>();
        filtered.forEach((e) => {
            const key = `${e.control.framework_name} \u2022 ${formatKategori(e.control.kategori)}`;
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key)!.push(e);
        });
        return Array.from(groups.entries());
    }, [filtered]);

    const totalEntries = entries.length;
    const completedEntries = entries.filter(
        (e) => e.status === 'compliant' || (e.status === 'non_compliant' && e.catatan && e.catatan.trim()) || (e.status === 'na' && e.catatan && e.catatan.trim()),
    ).length;
    const progress = totalEntries > 0 ? Math.round((completedEntries / totalEntries) * 100) : 0;

    const invalidCount = entries.filter(
        (e) => !e.status || e.status === 'non_compliant' || e.status === 'na',
    ).filter((e) => !e.catatan || !e.catatan.trim()).length;

    const handleEntryUpdate = useCallback((entryId: number, data: Partial<EntryItem>) => {
        setEntries((prev) => prev.map((e) => (e.id === entryId ? { ...e, ...data } : e)));
    }, []);

    return (
        <AppLayout>
            <Head title={session.konteks_penilaian} />

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
                        <span className="text-xs text-blue-500">{completedEntries}/{totalEntries} Kontrol</span>
                    </div>
                </div>
                <div className="h-2 w-full overflow-hidden rounded-full bg-blue-200/50 dark:bg-blue-800/50">
                    <div
                        className="h-full rounded-full bg-blue-600 transition-all duration-500"
                        style={{ width: `${progress}%` }}
                    />
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
                        <option key={f.name} value={f.name}>{f.name} ({f.ver})</option>
                    ))}
                </select>
                <select
                    value={kategoriFilter}
                    onChange={(e) => setKategoriFilter(e.target.value)}
                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                >
                    <option value="">Semua Kategori</option>
                    {kategoris.map((k) => (
                        <option key={k.value} value={k.value}>{k.label}</option>
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
                                <EntryItemRow
                                    key={entry.id}
                                    entry={entry}
                                    onEntryUpdate={handleEntryUpdate}
                                />
                            ))}
                        </div>
                    </div>
                ))}
            </div>

            <div className="mt-8 flex flex-col items-center gap-3">
                {invalidCount > 0 && (
                    <p className="text-xs text-slate-500">
                        {invalidCount} kontrol belum terisi dengan benar
                    </p>
                )}
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
