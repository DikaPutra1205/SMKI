import AssessmentsListSkeleton from '@/components/skeletons/AssessmentsListSkeleton';
import { usePageLoading } from '@/hooks/usePageLoading';
import AppLayout from '@/layouts/AppLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ChevronRight, ClipboardCheck, Plus, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

interface Framework {
    id: number;
    nama: string;
    versi: string;
}

interface WorkUnit {
    id: number;
    nama: string;
}

interface SessionItem {
    id: number;
    konteks_penilaian: string;
    periode: string | null;
    unit_id: number;
    framework_id: number | null;
    created_at: string;
    updated_at: string;
    unit: WorkUnit;
    framework: Framework | null;
    total_entries: number;
    completed_entries: number;
    compliant_entries: number;
    partial_entries: number;
    non_compliant_entries: number;
    na_entries: number;
}

interface AssessmentsProps {
    sessions: SessionItem[];
    user_unit: WorkUnit;
}

const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

export default function Assessments({ sessions, user_unit }: AssessmentsProps) {
    const { flash } = usePage<{ flash?: { type: string; message: string } }>().props;
    const [flashVisible, setFlashVisible] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const isLoading = usePageLoading();

    useEffect(() => {
        if (flash?.message) {
            setFlashVisible(true);
            const t = setTimeout(() => setFlashVisible(false), 4000);
            return () => clearTimeout(t);
        }
    }, [flash]);

    const sorted = useMemo(() => [...sessions].sort((a, b) => b.id - a.id), [sessions]);

    if (isLoading) {
        return <AssessmentsListSkeleton />;
    }

    return (
        <AppLayout>
            <Head title="Daftar Assessment" />

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

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Daftar Assessment</h1>
                    <p className="mt-1 text-sm text-slate-500">Pengecekan Mandiri Kepatuhan &middot; {user_unit?.nama}</p>
                </div>
                <button
                    type="button"
                    onClick={() => setShowModal(true)}
                    className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700"
                >
                    <Plus className="h-4 w-4" />
                    Buat Assessment Baru
                </button>
            </div>

            {sorted.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 py-20 dark:border-slate-700">
                    <ClipboardCheck className="mb-4 h-16 w-16 text-slate-300 dark:text-slate-600" />
                    <p className="mb-1 text-lg font-semibold text-slate-700 dark:text-slate-300">Belum ada assessment kepatuhan</p>
                    <p className="mb-6 text-sm text-slate-400">Mulai buat assessment baru untuk satuan kerja Anda.</p>
                    <button
                        type="button"
                        onClick={() => setShowModal(true)}
                        className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700"
                    >
                        <Plus className="h-4 w-4" />
                        Buat Assessment Baru
                    </button>
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {sorted.map((s) => {
                        const total = s.total_entries || 0;
                        const completed = s.completed_entries || 0;
                        const pct = total > 0 ? Math.round((completed / total) * 100) : 0;
                        const compliant = s.compliant_entries || 0;
                        const partial = s.partial_entries || 0;
                        const nonCompliant = s.non_compliant_entries || 0;
                        const na = s.na_entries || 0;
                        const compliantPct = total > 0 ? (compliant / total) * 100 : 0;
                        const partialPct = total > 0 ? (partial / total) * 100 : 0;
                        const nonCompliantPct = total > 0 ? (nonCompliant / total) * 100 : 0;
                        const naPct = total > 0 ? (na / total) * 100 : 0;

                        return (
                            <button
                                key={s.id}
                                type="button"
                                onClick={() => router.get(`/admin/pic/assessments/${s.id}`)}
                                className="group flex flex-col rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition-all hover:border-blue-200 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-blue-800"
                            >
                                <div className="mb-3 flex items-start justify-between">
                                    <div className="min-w-0 flex-1">
                                        <h3 className="truncate text-sm font-bold text-slate-900 dark:text-white">{s.konteks_penilaian}</h3>
                                        <p className="mt-0.5 text-xs text-slate-400">{s.periode || 'Tanpa Periode'}</p>
                                    </div>
                                    <ChevronRight className="h-4 w-4 shrink-0 text-slate-300 transition-transform group-hover:translate-x-0.5 group-hover:text-blue-500" />
                                </div>

                                <div className="mb-3">
                                    <div className="mb-1 flex items-baseline justify-between">
                                        <span className="text-xs text-slate-500">
                                            {completed}/{total} Kontrol
                                        </span>
                                        <span className="text-xs font-bold text-blue-600">{pct}%</span>
                                    </div>
                                    <div className="flex h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        {compliantPct > 0 && (
                                            <div className="h-full bg-emerald-500 transition-all duration-500" style={{ width: `${compliantPct}%` }} />
                                        )}
                                        {partialPct > 0 && (
                                            <div className="h-full bg-amber-500 transition-all duration-500" style={{ width: `${partialPct}%` }} />
                                        )}
                                        {nonCompliantPct > 0 && (
                                            <div className="h-full bg-red-500 transition-all duration-500" style={{ width: `${nonCompliantPct}%` }} />
                                        )}
                                        {naPct > 0 && (
                                            <div className="h-full bg-slate-300 transition-all duration-500 dark:bg-slate-600" style={{ width: `${naPct}%` }} />
                                        )}
                                    </div>
                                </div>

                                <div className="mt-auto flex items-center justify-between text-[11px] text-slate-400">
                                    <span>{s.framework?.nama || 'Semua Kerangka'}</span>
                                    <span>
                                        {new Date(s.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}
                                    </span>
                                </div>
                            </button>
                        );
                    })}
                </div>
            )}

            {showModal && <CreateModal onClose={() => setShowModal(false)} userUnit={user_unit} />}
        </AppLayout>
    );
}

function CreateModal({ onClose, userUnit }: { onClose: () => void; userUnit: WorkUnit }) {
    const { data, setData, post, processing, errors } = useForm({
        konteks_penilaian: '',
        periode: '',
        unit_id: userUnit?.id || 0,
        framework_id: '' as string | number,
    });

    const currentYear = new Date().getFullYear();

    const selectedMonth = useMemo(() => {
        const m = parseInt(data.periode?.split(' ')[0] ?? '', 10);
        return isNaN(m) ? -1 : m - 1;
    }, [data.periode]);

    const handleMonthChange = useCallback(
        (monthIndex: number) => {
            const monthName = MONTHS[monthIndex];
            const yearPart = data.periode?.split(' ')[1] || String(new Date().getFullYear());
            setData('periode', `${monthName} ${yearPart}`);
        },
        [data.periode, setData],
    );

    const handleYearChange = useCallback(
        (year: number) => {
            const monthPart = data.periode?.split(' ')[0] || MONTHS[new Date().getMonth()];
            setData('periode', `${monthPart} ${year}`);
        },
        [data.periode, setData],
    );

    const selectedYear = parseInt(data.periode?.split(' ')[1] || '', 10) || currentYear;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/pic/assessments', {
            onSuccess: () => onClose(),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm" onClick={onClose}>
            <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900" onClick={(e) => e.stopPropagation()}>
                <div className="mb-5 flex items-center justify-between">
                    <h2 className="text-lg font-bold text-slate-900 dark:text-white">Buat Assessment Baru</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Konteks Penilaian</label>
                        <input
                            type="text"
                            value={data.konteks_penilaian}
                            onChange={(e) => setData('konteks_penilaian', e.target.value)}
                            placeholder="Kepatuhan Keamanan Informasi"
                            className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 placeholder-slate-400 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            required
                        />
                        {errors.konteks_penilaian && <p className="mt-1 text-xs text-red-500">{errors.konteks_penilaian}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Bulan</label>
                            <select
                                value={selectedMonth >= 0 ? selectedMonth : ''}
                                onChange={(e) => handleMonthChange(parseInt(e.target.value))}
                                className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                <option value="">Pilih bulan</option>
                                {MONTHS.map((m, i) => (
                                    <option key={m} value={i}>
                                        {m}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Tahun</label>
                            <select
                                value={selectedYear}
                                onChange={(e) => handleYearChange(parseInt(e.target.value))}
                                className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                {[currentYear - 1, currentYear, currentYear + 1].map((y) => (
                                    <option key={y} value={y}>
                                        {y}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Periode</label>
                        <input
                            type="text"
                            value={data.periode || ''}
                            onChange={(e) => setData('periode', e.target.value)}
                            placeholder="Januari 2026"
                            className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 placeholder-slate-400 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        />
                        {errors.periode && <p className="mt-1 text-xs text-red-500">{errors.periode}</p>}
                    </div>

                    <div className="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={processing || !data.konteks_penilaian}
                            className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 disabled:opacity-50"
                        >
                            {processing ? 'Membuat...' : 'Buat Assessment'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
