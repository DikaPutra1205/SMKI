import { EmptyState } from '@/components/ui/EmptyState';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import AppLayout from '@/layouts/AppLayout';
import { t } from '@/lib/i18n';
import { formatDateIndonesian, formatPeriodeIndonesian } from '@/lib/utils';
import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle2, Search, Send, ShieldCheck, UserRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface SessionItem {
    id: number;
    konteks_penilaian: string;
    periode: string;
    unit_id: number;
    unit_nama: string;
    framework_id: number | null;
    framework_nama: string;
    creator_id: number | null;
    creator_name: string;
    total_entries: number;
    compliant_entries: number;
    partial_entries: number;
    non_compliant_entries: number;
    na_entries: number;
    verified_entries: number;
    compliance_percentage: number;
    created_at: string;
    updated_at: string;
}

interface WorkUnit {
    id: number;
    nama: string;
}

interface FrameworkItem {
    id: number;
    nama: string;
    versi: string;
}

interface SessionsProps {
    sessions: SessionItem[];
    workUnits: WorkUnit[];
    frameworks: FrameworkItem[];
    periodeOptions: string[];
    filters: Record<string, string>;
}

export default function Sessions({ sessions, workUnits, frameworks, periodeOptions, filters }: SessionsProps) {
    const { flash } = usePage<{ flash?: { type: string; message: string } }>().props;
    const [flashVisible, setFlashVisible] = useState(false);

    const [search, setSearch] = useState(filters.search || '');
    const [unitId, setUnitId] = useState(filters.unit_id || '');
    const [frameworkId, setFrameworkId] = useState(filters.framework_id || '');
    const [periode, setPeriode] = useState(filters.periode || '');

    const [perPage, setPerPage] = useState<number | 'all'>(12);
    const [currentPage, setCurrentPage] = useState(1);
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (flash?.message) {
            setFlashVisible(true);
            const timer = setTimeout(() => setFlashVisible(false), 4000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    useEffect(() => {
        setCurrentPage(1);
    }, [search, unitId, frameworkId, periode, perPage]);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                '/admin/kepatuhan/sessions',
                {
                    search: search || undefined,
                    unit_id: unitId || undefined,
                    framework_id: frameworkId || undefined,
                    periode: periode || undefined,
                },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [search, unitId, frameworkId, periode]);

    const totalItems = sessions.length;
    const effectivePerPage = perPage === 'all' ? totalItems || 1 : perPage;
    const totalPages = perPage === 'all' || totalItems === 0 ? 1 : Math.ceil(totalItems / effectivePerPage);
    const safeCurrentPage = Math.min(Math.max(1, currentPage), totalPages);
    const startIndex = totalItems === 0 ? 0 : (safeCurrentPage - 1) * effectivePerPage;
    const endIndex = perPage === 'all' ? totalItems : Math.min(startIndex + effectivePerPage, totalItems);
    const paginatedSessions = perPage === 'all' ? sessions : sessions.slice(startIndex, endIndex);

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/admin/kepatuhan/dashboard' }, { label: 'Assessment PIC' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/kepatuhan/sessions">
            <Head title="Assessment PIC - Admin Kepatuhan" />

            {flash?.message && flashVisible && (
                <div className="border-border mb-4 flex items-center gap-2 rounded-lg border px-4 py-3 text-sm font-medium shadow-sm dark:border-slate-700">
                    {flash.type === 'success' ? (
                        <div className="text-success flex items-center gap-2 dark:text-emerald-400">
                            <CheckCircle2 className="h-4 w-4" />
                            {flash.message}
                        </div>
                    ) : (
                        <div className="text-danger flex items-center gap-2 dark:text-red-400">
                            <Send className="h-4 w-4" />
                            {flash.message}
                        </div>
                    )}
                </div>
            )}

            <div className="page-head flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Assessment PIC</h1>
                    <p className="text-muted mt-1 text-xs sm:text-sm dark:text-slate-400">
                        Pantau seluruh session pengecekan mandiri dari setiap satuan kerja.
                    </p>
                </div>
            </div>

            {/* Toolbar */}
            <div className="border-border flex flex-col gap-3 rounded-[14px] border bg-white p-3 shadow-sm md:flex-row md:items-center dark:border-slate-700 dark:bg-slate-900">
                <div className="relative min-w-[220px] flex-1">
                    <Search className="text-faint absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 dark:text-slate-500" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari konteks, unit, atau PIC..."
                        className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 h-10 w-full rounded-[10px] border bg-white py-2 pr-4 pl-9 text-xs focus:ring-2 focus:outline-none sm:text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                    />
                </div>

                <Select value={unitId} onChange={(e) => setUnitId(e.target.value)} className="min-w-[170px]">
                    <option value="">Semua Unit</option>
                    {workUnits.map((u) => (
                        <option key={u.id} value={String(u.id)}>
                            {u.nama}
                        </option>
                    ))}
                </Select>

                <Select value={frameworkId} onChange={(e) => setFrameworkId(e.target.value)} className="min-w-[170px]">
                    <option value="">Semua Framework</option>
                    {frameworks.map((f) => (
                        <option key={f.id} value={String(f.id)}>
                            {f.nama} ({f.versi})
                        </option>
                    ))}
                </Select>

                <Select value={periode} onChange={(e) => setPeriode(e.target.value)} className="min-w-[140px]">
                    <option value="">Semua Periode</option>
                    {periodeOptions.map((p) => (
                        <option key={p} value={p}>
                            {formatPeriodeIndonesian(p)}
                        </option>
                    ))}
                </Select>
            </div>

            {/* Sessions */}
            {paginatedSessions.length === 0 ? (
                <EmptyState message="Belum ada session assessment yang cocok dengan filter ini." />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {paginatedSessions.map((s) => {
                        const pct = s.compliance_percentage;
                        const total = s.total_entries || 0;
                        const compliantPct = total > 0 ? (s.compliant_entries / total) * 100 : 0;
                        const partialPct = total > 0 ? ((s.partial_entries || 0) / total) * 100 : 0;
                        const nonCompliantPct = total > 0 ? (s.non_compliant_entries / total) * 100 : 0;
                        const naPct = total > 0 ? (s.na_entries / total) * 100 : 0;

                        return (
                            <div
                                key={s.id}
                                className="border-border group hover:border-primary-200 flex flex-col rounded-[14px] border bg-white p-5 shadow-sm transition-all hover:shadow-md dark:border-slate-700 dark:bg-slate-900"
                            >
                                <div className="mb-3">
                                    <h3 className="text-navy truncate text-sm leading-snug font-bold dark:text-white">{s.konteks_penilaian}</h3>
                                    <p className="text-faint mt-0.5 text-xs dark:text-slate-500">
                                        {s.periode ? formatPeriodeIndonesian(s.periode) : 'Tanpa Periode'}
                                    </p>
                                </div>

                                <div className="text-muted mb-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs dark:text-slate-400">
                                    <span className="inline-flex items-center gap-1.5">
                                        <UserRound className="text-faint h-3.5 w-3.5 dark:text-slate-500" />
                                        {s.unit_nama || 'Unit tidak diketahui'}
                                    </span>
                                    {s.creator_name && <span className="text-faint dark:text-slate-500">oleh {s.creator_name}</span>}
                                </div>

                                {s.framework_nama && (
                                    <div className="mb-3">
                                        <span className="border-border text-body bg-surface inline-flex items-center rounded-[6px] border px-2.5 py-1 text-[11px] font-semibold dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                                            {s.framework_nama}
                                        </span>
                                    </div>
                                )}

                                <div className="mb-3">
                                    <div className="mb-1 flex items-baseline justify-between">
                                        <span className="text-muted text-xs dark:text-slate-400">
                                            {s.compliant_entries}/{s.total_entries} Patuh
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
                                            <div className="h-full bg-amber-500 transition-all duration-500" style={{ width: `${partialPct}%` }} />
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

                                <div className="border-border text-faint mt-auto flex flex-wrap items-center justify-between gap-2 border-t pt-3 text-[11px] dark:border-slate-700 dark:text-slate-500">
                                    <span className="inline-flex items-center gap-1">
                                        <ShieldCheck className="h-3.5 w-3.5" />
                                        {s.verified_entries}/{s.total_entries} terverifikasi
                                    </span>
                                    <span>{formatDateIndonesian(s.created_at, { shortMonth: true })}</span>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {totalItems > 0 && (
                <Pagination
                    currentPage={safeCurrentPage}
                    totalPages={totalPages}
                    perPage={perPage}
                    totalItems={totalItems}
                    startIndex={startIndex}
                    endIndex={endIndex}
                    onPageChange={setCurrentPage}
                    onPerPageChange={setPerPage}
                />
            )}
        </AppLayout>
    );
}
