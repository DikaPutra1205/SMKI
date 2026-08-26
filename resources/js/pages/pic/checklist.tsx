import AssessmentsListSkeleton from '@/components/skeletons/AssessmentsListSkeleton';
import { usePageLoading } from '@/hooks/usePageLoading';
import AppLayout from '@/layouts/AppLayout';
import { formatDateIndonesian, formatPeriodeIndonesian } from '@/lib/utils';
import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ChevronRight, ClipboardCheck } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

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

export default function Assessments({ sessions, user_unit }: AssessmentsProps) {
    const { flash } = usePage<{ flash?: { type: string; message: string } }>().props;
    const [flashVisible, setFlashVisible] = useState(false);
    const isLoading = usePageLoading('/admin/pic/checklist');

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

            <div className="mb-6">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Daftar Assessment</h1>
                <p className="mt-1 text-sm text-slate-500">Pengecekan Mandiri Kepatuhan &middot; {user_unit?.nama}</p>
            </div>

            {sorted.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 py-20 dark:border-slate-700">
                    <ClipboardCheck className="mb-4 h-16 w-16 text-slate-300 dark:text-slate-600" />
                    <p className="mb-1 text-lg font-semibold text-slate-700 dark:text-slate-300">Belum ada assessment kepatuhan</p>
                    <p className="text-sm text-slate-400">Assessment akan dibuat otomatis oleh sistem setiap bulan untuk satuan kerja Anda.</p>
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
                                onClick={() => router.get(`/admin/pic/checklist/${s.id}`)}
                                className="group hover:border-primary-200 dark:hover:border-primary-800 flex flex-col rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition-all hover:shadow-md dark:border-slate-700 dark:bg-slate-900"
                            >
                                <div className="mb-3 flex items-start justify-between">
                                    <div className="min-w-0 flex-1">
                                        <h3 className="truncate text-sm font-bold text-slate-900 dark:text-white">{s.konteks_penilaian}</h3>
                                        <p className="mt-0.5 text-xs text-slate-400">
                                            {s.periode ? formatPeriodeIndonesian(s.periode) : 'Tanpa Periode'}
                                        </p>
                                    </div>
                                    <ChevronRight className="group-hover:text-primary h-4 w-4 shrink-0 text-slate-300 transition-transform group-hover:translate-x-0.5" />
                                </div>

                                <div className="mb-3">
                                    <div className="mb-1 flex items-baseline justify-between">
                                        <span className="text-xs text-slate-500">
                                            {completed}/{total} Kontrol
                                        </span>
                                        <span className="text-primary text-xs font-bold">{pct}%</span>
                                    </div>
                                    <div className="flex h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        {compliantPct > 0 && (
                                            <div
                                                className="h-full bg-emerald-500 transition-all duration-500"
                                                style={{ width: `${compliantPct}%` }}
                                            />
                                        )}
                                        {partialPct > 0 && (
                                            <div className="h-full bg-amber-400 transition-all duration-500" style={{ width: `${partialPct}%` }} />
                                        )}
                                        {nonCompliantPct > 0 && (
                                            <div className="h-full bg-red-500 transition-all duration-500" style={{ width: `${nonCompliantPct}%` }} />
                                        )}
                                        {naPct > 0 && (
                                            <div
                                                className="h-full bg-slate-300 transition-all duration-500 dark:bg-slate-600"
                                                style={{ width: `${naPct}%` }}
                                            />
                                        )}
                                    </div>
                                </div>

                                <div className="mt-auto flex items-center justify-between text-[11px] text-slate-400">
                                    <span>{s.framework?.nama || 'Semua Kerangka'}</span>
                                    <span>{formatDateIndonesian(s.created_at, { shortMonth: true })}</span>
                                </div>
                            </button>
                        );
                    })}
                </div>
            )}
        </AppLayout>
    );
}
