import ComplianceAreaChart, { type TrendPoint } from '@/components/dashboards/ComplianceAreaChart';
import DashboardFilterBar from '@/components/dashboards/DashboardFilterBar';
import ExportReportModal from '@/components/dashboards/ExportReportModal';
import FrameworkComplianceCard from '@/components/dashboards/FrameworkComplianceCard';
import RecentActivityTable from '@/components/dashboards/RecentActivityTable';
import RiskBreakdown from '@/components/dashboards/RiskBreakdown';
import { ChartSkeleton } from '@/components/skeletons/ChartSkeleton';
import AppLayout from '@/layouts/AppLayout';
import { formatDateIndonesian } from '@/lib/utils';
import { Deferred, Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Database, FileDown, KeyRound, Layers, Lock, Shield, ShieldAlert, ShieldCheck, TrendingUp, Users } from 'lucide-react';
import { useMemo, useState } from 'react';

interface FrameworkSummary {
    id: number;
    nama: string;
    versi: string;
    controls_count: number;
    completion_rate?: number;
}

interface RecentActivity {
    id: number;
    actor_name: string;
    actor_role: string;
    action: string;
    entity_name: string;
    time_ago: string;
    created_at: string | null;
}

interface SuperadminDashboardProps {
    totalUsers: number;
    totalFrameworks: number;
    totalControls: number;
    frameworks: FrameworkSummary[];
    summary?: {
        overall_completion_rate: number;
        growth_from_last_period: number;
        frameworks_breakdown: Array<{
            id: number;
            nama: string;
            versi: string;
            completion_rate: number;
            selesai_count: number;
            total_controls: number;
        }>;
        findings_summary: { total_active: number; major: number; minor: number; observasi: number; overdue: number };
        risks_summary: { total_active: number; critical: number; high: number; medium: number; low: number; very_low: number };
    };
    recent_activities?: RecentActivity[];
    trends?: TrendPoint[];
    workUnits?: Array<{ id: number; nama: string; kode?: string | null }>;
    filters?: {
        unit_id?: number | string | null;
        months?: number | string;
    };
}

export default function SuperadminDashboard({
    totalUsers,
    totalFrameworks,
    totalControls,
    frameworks,
    summary,
    recent_activities = [],
    trends = [],
    workUnits = [],
    filters = {},
}: SuperadminDashboardProps) {
    const breadcrumbs = [{ label: 'Command Center' }];

    const overallRate = summary?.overall_completion_rate ?? 0;
    const growth = summary?.growth_from_last_period ?? 0;
    const findings = summary?.findings_summary ?? { total_active: 0, major: 0, minor: 0, observasi: 0, overdue: 0 };
    const risks = summary?.risks_summary ?? { total_active: 0, critical: 0, high: 0, medium: 0, low: 0, very_low: 0 };
    const breakdown = summary?.frameworks_breakdown ?? [];

    const frameworkRate = (id: number) => breakdown.find((f) => f.id === id)?.completion_rate ?? 0;
    const frameworkCompliant = (id: number) => breakdown.find((f) => f.id === id)?.selesai_count ?? 0;
    const frameworkTotal = (id: number) => breakdown.find((f) => f.id === id)?.total_controls ?? 0;

    const totalRisks = (risks.critical || 0) + (risks.high || 0) + (risks.medium || 0) + (risks.low || 0) + (risks.very_low || 0);

    const currentDateFormatted = useMemo(() => {
        const d = new Date();
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return `${days[d.getDay()]}, ${formatDateIndonesian(d)}`;
    }, []);

    const [isExportModalOpen, setIsExportModalOpen] = useState(false);

    const basePath =
        typeof window !== 'undefined' && window.location.pathname.startsWith('/admin/superadmin') ? '/admin/superadmin/dashboard' : '/dashboard';

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/superadmin/dashboard">
            <Head title="Command Center — Superadmin" />

            {/* Header */}
            <div className="flex flex-col gap-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="max-w-2xl">
                        <div>
                            <span className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                {currentDateFormatted} · Administrator Utama
                            </span>
                        </div>
                        <h1 className="mt-3 text-[28px] leading-tight font-extrabold tracking-tight text-slate-900 dark:text-white">
                            Command Center Sistem
                        </h1>
                        <p className="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
                            Pantau integritas sistem SMKI, alokasi peran pengguna, dan status kepatuhan secara menyeluruh.
                        </p>
                    </div>
                </div>

                <DashboardFilterBar
                    months={filters.months}
                    unitId={filters.unit_id}
                    workUnits={workUnits}
                    basePath={basePath}
                    actions={
                        <>
                            <button
                                type="button"
                                onClick={() => setIsExportModalOpen(true)}
                                className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 text-xs font-semibold text-slate-600 shadow-xs transition-colors hover:bg-slate-50 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                            >
                                <FileDown className="h-4 w-4" />
                                Unduh Laporan PDF
                            </button>
                            <Link
                                href="/admin/superadmin/frameworks"
                                className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 text-xs font-semibold text-slate-600 shadow-xs transition-colors hover:bg-slate-50 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                            >
                                <Database className="h-4 w-4 text-slate-400" />
                                Standar Framework
                            </Link>
                            <Link
                                href="/admin/superadmin/roles"
                                className="bg-primary hover:bg-primary shadow-primary/20 inline-flex h-10 items-center justify-center gap-2 rounded-lg px-4 text-xs font-semibold text-white shadow-md transition-all hover:brightness-105 active:scale-[0.99]"
                            >
                                <KeyRound className="h-4 w-4" />
                                Manajemen Role & Izin
                            </Link>
                        </>
                    }
                />
            </div>

            <ExportReportModal
                open={isExportModalOpen}
                onClose={() => setIsExportModalOpen(false)}
                unitId={filters.unit_id ? Number(filters.unit_id) : undefined}
                workUnits={workUnits}
            />

            {/* Row 1: KPI Cards */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {/* 1. Overall Compliance */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-shadow hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-bold tracking-widest text-slate-500 uppercase dark:text-slate-400">Tingkat Kepatuhan</span>
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100 ring-inset dark:bg-emerald-950/50 dark:text-emerald-400 dark:ring-emerald-900/40">
                            <TrendingUp className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-baseline gap-2">
                            <span className="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">{overallRate.toFixed(1)}%</span>
                            {growth !== 0 && (
                                <span
                                    className={`rounded-md px-1.5 py-0.5 text-[11px] font-bold ${
                                        growth >= 0
                                            ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400'
                                            : 'bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400'
                                    }`}
                                >
                                    {growth >= 0 ? '↑' : '↓'} {growth.toFixed(1)}% bln ini
                                </span>
                            )}
                        </div>
                        <div className="mt-3.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div
                                className="h-full rounded-full bg-emerald-500 transition-all duration-500"
                                style={{ width: `${Math.min(100, Math.max(0, overallRate))}%` }}
                            />
                        </div>
                    </div>
                </div>

                {/* 2. Total Pengguna */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-shadow hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-bold tracking-widest text-slate-500 uppercase dark:text-slate-400">Pengguna Aktif</span>
                        <div className="bg-primary-50 text-primary ring-primary-100 dark:bg-navy-900/50 dark:text-primary-200 dark:ring-primary-900/40 flex h-10 w-10 items-center justify-center rounded-xl ring-1 ring-inset">
                            <Users className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-baseline gap-2">
                            <span className="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">{totalUsers}</span>
                            <span className="bg-primary-50 text-primary-700 dark:bg-navy-900/60 dark:text-primary-200 rounded-md px-2 py-0.5 text-[11px] font-bold">
                                Akun Terdaftar
                            </span>
                        </div>
                        <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">Semua unit kerja terhubung</p>
                    </div>
                </div>

                {/* 3. Pustaka Kontrol SMKI */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-shadow hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-bold tracking-widest text-slate-500 uppercase dark:text-slate-400">Pustaka Kontrol</span>
                        <div className="bg-primary-100 text-primary-800 ring-primary-200/60 dark:bg-primary-950/60 dark:text-primary-300 dark:ring-primary-900/40 flex h-10 w-10 items-center justify-center rounded-xl ring-1 ring-inset">
                            <Layers className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-baseline gap-2">
                            <span className="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">{totalControls || 127}</span>
                            <span className="bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300 rounded-md px-2 py-0.5 text-[11px] font-bold">
                                {totalFrameworks} Framework
                            </span>
                        </div>
                        <p className="mt-3 truncate text-xs text-slate-500 dark:text-slate-400">
                            {frameworks.map((f) => f.nama).join(' · ') || 'ISO 27001 · ISO 27701'}
                        </p>
                    </div>
                </div>

                {/* 4. Temuan Ketidaksesuaian */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-shadow hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-bold tracking-widest text-slate-500 uppercase dark:text-slate-400">
                            Temuan Audit Terbuka
                        </span>
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-rose-100 ring-inset dark:bg-rose-950/50 dark:text-rose-400 dark:ring-rose-900/40">
                            <ShieldAlert className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-baseline gap-2">
                            <span className="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">{findings.total_active}</span>
                            <span className="rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-bold text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">
                                Gap Aktif
                            </span>
                        </div>
                        <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
                            {findings.overdue > 0 ? `${findings.overdue} melewati batas SLA` : 'Semua item dalam batas SLA'}
                        </p>
                    </div>
                </div>
            </div>

            {/* Row 2: Standar Framework Overview */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <FrameworkComplianceCard
                    nama={frameworks[0]?.nama || 'ISO/IEC 27001:2022'}
                    deskripsi="Sistem Manajemen Keamanan Informasi"
                    completionRate={frameworkRate(1)}
                    selesaiCount={frameworkCompliant(1)}
                    totalControls={frameworkTotal(1)}
                    icon={<Shield className="h-4 w-4" />}
                    realisasiLabel="Realisasi Kontrol Organisasi"
                />

                <FrameworkComplianceCard
                    nama={frameworks[1]?.nama || 'ISO/IEC 27701:2025'}
                    deskripsi="Sistem Manajemen Informasi Privasi (PIMS)"
                    completionRate={frameworkRate(2)}
                    selesaiCount={frameworkCompliant(2)}
                    totalControls={frameworkTotal(2)}
                    icon={<ShieldCheck className="h-4 w-4" />}
                    iconClassName="bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300"
                    barClassName="bg-primary-800 dark:bg-primary-400"
                    realisasiLabel="Realisasi Kontrol Organisasi"
                />
            </div>

            {/* Row 3: Tren Kepatuhan & Status Risiko Keamanan */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-7">
                {/* Tren Kepatuhan Organisasi */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-shadow hover:shadow-md lg:col-span-4 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-start justify-between gap-3 border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div>
                            <h3 className="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                                <div className="bg-primary-50 text-primary dark:bg-navy-900/50 dark:text-primary-200 flex h-7 w-7 items-center justify-center rounded-lg">
                                    <TrendingUp className="h-4 w-4" />
                                </div>
                                Tren Kepatuhan Organisasi
                            </h3>
                            <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Riwayat perkembangan kepatuhan bulanan</p>
                        </div>
                        {/* Legend */}
                        <div className="flex flex-wrap items-center justify-end gap-x-3 gap-y-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                            <span className="flex items-center gap-1.5">
                                <span className="h-2 w-4 rounded-full" style={{ background: '#0284c7' }} />
                                Rata-rata
                            </span>
                            <span className="flex items-center gap-1.5">
                                <span className="h-0.5 w-4" style={{ borderTop: '2px dashed #196ecd', display: 'block' }} />
                                ISO 27001
                            </span>
                            <span className="flex items-center gap-1.5">
                                <span className="h-0.5 w-4" style={{ borderTop: '2px dashed #0f4c81', display: 'block' }} />
                                ISO 27701
                            </span>
                        </div>
                    </div>

                    <div className="pt-4">
                        <Deferred data="trends" fallback={<ChartSkeleton height="h-[200px]" />}>
                            <ComplianceAreaChart trends={trends} />
                        </Deferred>
                    </div>
                </div>

                {/* Status Risiko Keamanan */}
                <div className="lg:col-span-3">
                    <RiskBreakdown
                        risks={risks}
                        title="Status Risiko Keamanan"
                        subtitle={`${totalRisks} risiko terdaftar di register`}
                        footer={
                            <div className="flex items-start gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs leading-relaxed text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">
                                <Lock className="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400 dark:text-slate-500" />
                                <span>Pencatatan rekam jejak audit trail bersifat permanen (immutable) dan tidak dapat dimanipulasi.</span>
                            </div>
                        }
                    />
                </div>
            </div>

            {/* Row 4: Audit Trail & Aktivitas Sistem */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-7">
                <div className="flex flex-col rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-shadow hover:shadow-md lg:col-span-4 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white">Audit Trail & Aktivitas Sistem</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Rekam jejak tindakan seluruh pengguna sistem</p>
                        </div>
                        <Link
                            href="/audit-logs"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-500 dark:text-blue-400"
                        >
                            Lihat Semua
                            <ArrowUpRight className="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <RecentActivityTable activities={recent_activities} limit={5} statusLabel="Tercatat" showRole />
                </div>
            </div>
        </AppLayout>
    );
}
