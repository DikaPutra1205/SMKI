import ComplianceAreaChart, { type TrendPoint } from '@/components/dashboards/ComplianceAreaChart';
import DashboardFilterBar from '@/components/dashboards/DashboardFilterBar';
import ExportReportModal from '@/components/dashboards/ExportReportModal';
import FrameworkComplianceCard from '@/components/dashboards/FrameworkComplianceCard';
import RecentActivityTable from '@/components/dashboards/RecentActivityTable';
import RiskBreakdown from '@/components/dashboards/RiskBreakdown';
import { ActivitySkeleton } from '@/components/skeletons/ActivitySkeleton';
import { ChartSkeleton } from '@/components/skeletons/ChartSkeleton';
import AppLayout from '@/layouts/AppLayout';
import { useCan } from '@/lib/can';
import { formatDateIndonesian } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import { ClipboardCheck, Clock, FileDown, FileSearch, Shield, ShieldAlert, ShieldCheck, TrendingUp } from 'lucide-react';
import { useMemo, useState } from 'react';

interface RecentActivity {
    id: number;
    actor_name: string;
    actor_role: string;
    action: string;
    entity_name: string;
    time_ago: string;
    created_at: string | null;
}

interface AuditorDashboardProps {
    summary?: {
        overall_completion_rate: number;
        growth_from_last_period: number;
        total_controls_active: number;
        frameworks_breakdown: { id: number; nama: string; versi: string; completion_rate: number; selesai_count: number; total_controls: number }[];
        findings_summary: { total_active: number; major: number; minor: number; observasi: number; overdue: number };
        risks_summary: {
            total_active: number;
            critical: number;
            high: number;
            medium: number;
            low: number;
            very_low: number;
        };
    };
    trends?: TrendPoint[];
    recent_activities?: RecentActivity[];
    workUnits?: Array<{ id: number; nama: string; kode?: string | null }>;
    filters?: {
        unit_id?: number | string;
        session_id?: number | string;
        months?: number | string;
    };
}

export default function AuditorDashboard({ summary, trends = [], recent_activities = [], workUnits = [], filters = {} }: AuditorDashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const can = useCan();
    const userName = auth.user?.name || 'Auditor Kepatuhan';

    const breadcrumbs = [{ label: 'Dashboard Auditor' }];

    const overallRate = summary?.overall_completion_rate ?? 0;
    const growth = summary?.growth_from_last_period ?? 0;
    const frameworks = summary?.frameworks_breakdown ?? [];
    const findings = summary?.findings_summary ?? { total_active: 0, major: 0, minor: 0, observasi: 0, overdue: 0 };
    const risks = summary?.risks_summary ?? { total_active: 0, critical: 0, high: 0, medium: 0, low: 0, very_low: 0 };

    const iso27001 = frameworks.find((f) => f.id === 1) || frameworks[0];
    const iso27701 = frameworks.find((f) => f.id === 2) || frameworks[1];

    const currentDateFormatted = useMemo(() => {
        const d = new Date();
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return `${days[d.getDay()]}, ${formatDateIndonesian(d)}`;
    }, []);

    // ── Trend Chart Data ──────────────────────────────────────────────────────
    const totalRisks = (risks.critical || 0) + (risks.high || 0) + (risks.medium || 0) + (risks.low || 0) + (risks.very_low || 0);

    const [isExportModalOpen, setIsExportModalOpen] = useState(false);

    const basePath =
        typeof window !== 'undefined' && window.location.pathname.startsWith('/admin/auditor') ? '/admin/auditor/dashboard' : '/dashboard';

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/auditor/dashboard">
            <Head title="Dashboard — Auditor Kepatuhan" />

            {/* Header */}
            <div className="flex flex-col gap-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-primary dark:text-primary-200 text-xs font-bold tracking-wide uppercase">
                                {currentDateFormatted} · Evaluasi Independen
                            </span>
                        </div>
                        <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Selamat Datang, {userName}</h1>
                        <p className="mt-0.5 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                            Pantau kepatuhan kontrol, status temuan audit gap, dan tingkat maturitas keamanan informasi.
                        </p>
                    </div>
                </div>

                <DashboardFilterBar
                    months={filters.months}
                    unitId={filters.unit_id}
                    workUnits={workUnits}
                    basePath={basePath}
                    timeframeExtra={{ session_id: filters.session_id }}
                    selectExtra={{ session_id: filters.session_id }}
                    actions={
                        <>
                            <button
                                type="button"
                                onClick={() => setIsExportModalOpen(true)}
                                className="border-primary/20 bg-primary/5 text-primary hover:bg-primary/10 dark:border-primary/40 dark:bg-primary/10 dark:text-primary-200 dark:hover:bg-primary/20 inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-semibold shadow-xs transition-colors"
                            >
                                <FileDown className="h-4 w-4" />
                                Unduh Laporan PDF
                            </button>
                            <Link
                                href="/temuan"
                                className="bg-primary hover:bg-primary inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-sm transition-all active:scale-95 sm:text-sm"
                            >
                                <FileSearch className="h-4 w-4" />
                                Temuan Audit
                            </Link>
                            {can('checklist.view') && can('audit-log.view') && (
                                <Link
                                    href="/admin/kepatuhan/checklist/verify"
                                    className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-xs transition-colors hover:bg-slate-50 sm:text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                >
                                    <ClipboardCheck className="h-4 w-4" />
                                    Verifikasi Checklists
                                </Link>
                            )}
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
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Tingkat Kepatuhan</span>
                        <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                            <TrendingUp className="h-4.5 w-4.5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-baseline gap-2">
                            <span className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{overallRate.toFixed(1)}%</span>
                            {growth !== 0 && (
                                <span
                                    className={`text-xs font-semibold ${
                                        growth >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'
                                    }`}
                                >
                                    {growth >= 0 ? '+' : ''}
                                    {growth.toFixed(1)}% bln ini
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

                {/* 2. Standar Terdaftar */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Standar Diaudit</span>
                        <div className="bg-primary-50 text-primary dark:bg-navy-900/50 dark:text-primary-200 flex h-9 w-9 items-center justify-center rounded-xl">
                            <ShieldCheck className="h-4.5 w-4.5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-baseline gap-2">
                            <span className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{frameworks.length || 2}</span>
                            <span className="bg-primary-50 text-primary-700 dark:bg-navy-900/60 dark:text-primary-200 rounded-md px-2 py-0.5 text-xs font-semibold">
                                Standar Aktif
                            </span>
                        </div>
                        <p className="mt-3 truncate text-xs text-slate-500 dark:text-slate-400">
                            {frameworks.map((f) => f.nama).join(' · ') || 'ISO 27001 · ISO 27701'}
                        </p>
                    </div>
                </div>

                {/* 3. Pending Actions */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Perlu Tindakan</span>
                        <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                            <Clock className="h-4.5 w-4.5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-baseline gap-2">
                            <span className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                                {findings.total_active + risks.total_active}
                            </span>
                            <span className="rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">
                                Item Terbuka
                            </span>
                        </div>
                        <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
                            {findings.overdue > 0 ? (
                                <span className="font-semibold text-rose-600 dark:text-rose-400">{findings.overdue} temuan overdue</span>
                            ) : (
                                'Semua temuan dalam pantauan'
                            )}
                        </p>
                    </div>
                </div>

                {/* 4. Non Compliances */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Temuan Ketidaksesuaian</span>
                        <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400">
                            <ShieldAlert className="h-4.5 w-4.5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-baseline gap-2">
                            <span className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{findings.total_active}</span>
                            <span className="rounded-md bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">
                                Gap Aktif
                            </span>
                        </div>
                        <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
                            {findings.major} Mayor · {findings.minor} Minor · {findings.observasi} Observasi
                        </p>
                    </div>
                </div>
            </div>

            {/* Row 2: Standar Kepatuhan ISO */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <FrameworkComplianceCard
                    nama={iso27001?.nama || 'ISO/IEC 27001:2022'}
                    deskripsi="Sistem Manajemen Keamanan Informasi"
                    completionRate={iso27001?.completion_rate ?? 0}
                    selesaiCount={iso27001?.selesai_count ?? 0}
                    totalControls={iso27001?.total_controls ?? 0}
                    icon={<Shield className="h-4 w-4" />}
                />

                <FrameworkComplianceCard
                    nama={iso27701?.nama || 'ISO/IEC 27701:2025'}
                    deskripsi="Sistem Manajemen Informasi Privasi (PIMS)"
                    completionRate={iso27701?.completion_rate ?? 0}
                    selesaiCount={iso27701?.selesai_count ?? 0}
                    totalControls={iso27701?.total_controls ?? 0}
                    icon={<ShieldCheck className="h-4 w-4" />}
                    iconClassName="bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300"
                    barClassName="bg-primary-800 dark:bg-primary-400"
                />
            </div>

            {/* Row 3: Tren & Risiko */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-7">
                {/* Tren Kepatuhan */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-4 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-start justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white">Tren Kepatuhan Organisasi</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Riwayat perkembangan kepatuhan bulanan</p>
                        </div>
                        {/* Legend */}
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">
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
                        subtitle={`${totalRisks} risiko teridentifikasi`}
                        footer={
                            <div className="rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">
                                Hasil evaluasi ini disajikan untuk mendukung audit kepatuhan internal dan eksternal.
                            </div>
                        }
                    />
                </div>
            </div>

            {/* Row 4: Log Aktivitas Terbaru */}
            <div className="flex flex-col rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                    <div>
                        <h3 className="text-sm font-bold text-slate-900 dark:text-white">Aktivitas & Log Audit Terbaru</h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400">Catatan aktivitas audit sistem terkini</p>
                    </div>
                </div>

                <Deferred data="recent_activities" fallback={<ActivitySkeleton count={6} />}>
                    <RecentActivityTable activities={recent_activities} limit={6} statusLabel="Tervalidasi" showRole />
                </Deferred>
            </div>
        </AppLayout>
    );
}
