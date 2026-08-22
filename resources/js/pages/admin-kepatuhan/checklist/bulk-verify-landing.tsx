import { EmptyState } from '@/components/ui/EmptyState';
import { Pagination } from '@/components/ui/Pagination';
import { SegmentedProgressBar, complianceSegments } from '@/components/ui/SegmentedProgressBar';
import { Select } from '@/components/ui/Select';
import AppLayout from '@/layouts/AppLayout';
import { t } from '@/lib/i18n';
import { formatDateIndonesian, formatPeriodeIndonesian } from '@/lib/utils';
import { Head, router } from '@inertiajs/react';
import { Building2, CalendarDays, ChevronRight, Search, ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/* ─── Data shapes (mirrors shared/checklist/sessions.tsx SessionItem) ────── */

interface SessionItem {
    id: number;
    konteks_penilaian: string;
    periode: string;
    unit_id: number;
    unit_nama: string;
    framework_id: number | null;
    framework_nama: string;
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

interface BulkVerifyLandingProps {
    sessions: SessionItem[];
    workUnits: WorkUnit[];
    frameworks: FrameworkItem[];
    periodeOptions: string[];
    filters: Record<string, string>;
}

function complianceColor(pct: number): string {
    if (pct >= 80) return 'text-emerald-600 dark:text-emerald-400';
    if (pct >= 50) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-500 dark:text-red-400';
}

/* ─── Session Card ───────────────────────────────────────────────────────── */

function SessionCard({ session }: { session: SessionItem }) {
    const segments = complianceSegments({
        compliant: session.compliant_entries,
        partial: session.partial_entries,
        nonCompliant: session.non_compliant_entries,
        na: session.na_entries,
    });
    const pct = session.compliance_percentage;

    function handleClick() {
        router.get('/admin/kepatuhan/checklist/verify', { session_id: String(session.id) });
    }

    return (
        <button
            type="button"
            onClick={handleClick}
            className="group border-border hover:border-primary-400 focus-visible:ring-primary/40 relative flex flex-col rounded-[16px] border bg-white p-5 text-left shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:outline-none dark:border-slate-700 dark:bg-slate-900"
        >
            {/* Top: Unit badge + Period */}
            <div className="mb-2.5 flex items-center justify-between gap-2">
                <div className="flex min-w-0 items-center gap-1.5">
                    <Building2 className="text-primary h-3.5 w-3.5 shrink-0" />
                    <span className="text-navy truncate text-xs font-bold tracking-tight dark:text-white">{session.unit_nama || 'Semua Unit'}</span>
                </div>
                <span className="text-muted shrink-0 text-[11px] font-medium dark:text-slate-400">
                    {session.periode ? formatPeriodeIndonesian(session.periode) : 'Tanpa Periode'}
                </span>
            </div>

            {/* Context title + chevron */}
            <div className="mb-2 flex items-start justify-between gap-2">
                <h3 className="text-navy line-clamp-2 text-sm leading-snug font-bold dark:text-white">{session.konteks_penilaian}</h3>
                <ChevronRight className="text-muted group-hover:text-primary mt-0.5 h-4 w-4 shrink-0 transition-transform group-hover:translate-x-0.5 dark:text-slate-500" />
            </div>

            {/* Framework badge */}
            {session.framework_nama && (
                <div className="mb-3.5">
                    <span className="border-border bg-surface text-body inline-flex items-center rounded-[6px] border px-2.5 py-0.5 text-[11px] font-semibold dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        {session.framework_nama}
                    </span>
                </div>
            )}

            {/* Progress bar + percentage */}
            <div className="mb-3.5">
                <div className="mb-1.5 flex items-baseline justify-between">
                    <span className="text-muted text-xs font-medium dark:text-slate-400">
                        {session.compliant_entries}/{session.total_entries} Kontrol Patuh
                    </span>
                    <span className={`text-sm font-bold ${complianceColor(pct)}`}>{pct}%</span>
                </div>
                <SegmentedProgressBar total={session.total_entries} segments={segments} heightClass="h-2" animate />
            </div>

            {/* Footer meta */}
            <div className="border-border text-muted mt-auto flex items-center justify-between gap-2 border-t pt-3 text-[11px] dark:border-slate-700 dark:text-slate-400">
                <span className="inline-flex items-center gap-1 font-medium">
                    <ShieldCheck className="text-primary h-3.5 w-3.5" />
                    {session.verified_entries}/{session.total_entries} terverifikasi
                </span>
                <span className="inline-flex items-center gap-1">
                    <CalendarDays className="h-3 w-3" />
                    {formatDateIndonesian(session.updated_at, { shortMonth: true })}
                </span>
            </div>
        </button>
    );
}

/* ─── Page ───────────────────────────────────────────────────────────────── */

export default function BulkVerifyLanding({ sessions, workUnits, frameworks, periodeOptions, filters }: BulkVerifyLandingProps) {
    const [search, setSearch] = useState(filters.search || '');
    const [unitId, setUnitId] = useState(filters.unit_id || '');
    const [frameworkId, setFrameworkId] = useState(filters.framework_id || '');
    const [periode, setPeriode] = useState(filters.periode || '');

    const [perPage, setPerPage] = useState<number | 'all'>(12);
    const [currentPage, setCurrentPage] = useState(1);
    const isFirstRender = useRef(true);

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
                '/admin/kepatuhan/checklist/verify',
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

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/admin/kepatuhan/dashboard' }, { label: t('bulkVerify.title') }];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/kepatuhan/checklist/verify">
            <Head title={`${t('bulkVerify.title')} — ${t('bulkVerify.landingTitle')}`} />

            {/* Page header */}
            <div className="page-head flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="flex items-center gap-2.5">
                        <h1 className="text-2xl font-bold tracking-tight">{t('bulkVerify.title')}</h1>
                        <span className="border-border text-body inline-flex items-center rounded-full border bg-white px-2.5 py-0.5 text-xs font-semibold shadow-xs dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                            {totalItems} Sesi
                        </span>
                    </div>
                    <p className="text-muted mt-1 text-xs sm:text-sm dark:text-slate-400">{t('bulkVerify.landingSubtitle')}</p>
                </div>
            </div>

            {/* Filter toolbar */}
            <div className="border-border flex flex-col gap-3 rounded-[14px] border bg-white p-3 shadow-sm md:flex-row md:items-center dark:border-slate-700 dark:bg-slate-900">
                <div className="relative min-w-[220px] flex-1">
                    <Search className="text-faint absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 dark:text-slate-500" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari konteks penilaian, unit, atau PIC..."
                        className="border-border-strong text-navy placeholder:text-faint focus:border-primary focus:ring-primary/20 h-10 w-full rounded-[10px] border bg-white py-2 pr-4 pl-9 text-xs focus:ring-2 focus:outline-none sm:text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                    />
                </div>

                <Select value={unitId} onChange={(e) => setUnitId(e.target.value)} className="min-w-[170px]">
                    <option value="">Semua Unit Kerja</option>
                    {workUnits.map((u) => (
                        <option key={u.id} value={String(u.id)}>
                            {u.nama}
                        </option>
                    ))}
                </Select>

                <Select value={frameworkId} onChange={(e) => setFrameworkId(e.target.value)} className="min-w-[170px]">
                    <option value="">Semua Standar Framework</option>
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

            {/* Session card grid */}
            {paginatedSessions.length === 0 ? (
                <EmptyState message="Belum ada sesi penilaian yang cocok dengan filter pencarian ini." />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {paginatedSessions.map((s) => (
                        <SessionCard key={s.id} session={s} />
                    ))}
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
