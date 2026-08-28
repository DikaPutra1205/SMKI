import ComplianceAreaChart, { type TrendPoint } from '@/components/dashboards/ComplianceAreaChart';
import { ChartSkeleton } from '@/components/skeletons/ChartSkeleton';
import AppLayout from '@/layouts/AppLayout';
import { formatDateIndonesian, formatDateTimeIndonesian } from '@/lib/utils';
import { Deferred, Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Database, KeyRound, Layers, Shield, ShieldAlert, ShieldCheck, TrendingUp, Users } from 'lucide-react';
import { useMemo } from 'react';

interface FrameworkSummary {
    id: number;
    nama: string;
    versi: string;
    controls_count: number;
    compliance_rate?: number;
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
        overall_compliance_rate: number;
        growth_from_last_period: number;
        frameworks_breakdown: Array<{
            id: number;
            nama: string;
            versi: string;
            compliance_rate: number;
            compliant_count: number;
            total_controls: number;
        }>;
        findings_summary: { total_active: number; major: number; minor: number; observasi: number; overdue: number };
        risks_summary: { total_active: number; critical: number; high: number; medium: number; low: number };
    };
    recent_activities?: RecentActivity[];
    trends?: TrendPoint[];
}

export default function SuperadminDashboard({
    totalUsers,
    totalFrameworks,
    totalControls,
    frameworks,
    summary,
    recent_activities = [],
    trends = [],
}: SuperadminDashboardProps) {
    const breadcrumbs = [{ label: 'Command Center' }];

    const overallRate = summary?.overall_compliance_rate ?? 0;
    const growth = summary?.growth_from_last_period ?? 0;
    const findings = summary?.findings_summary ?? { total_active: 0, major: 0, minor: 0, observasi: 0, overdue: 0 };
    const risks = summary?.risks_summary ?? { total_active: 0, critical: 0, high: 0, medium: 0, low: 0 };
    const breakdown = summary?.frameworks_breakdown ?? [];

    const frameworkRate = (id: number) => breakdown.find((f) => f.id === id)?.compliance_rate ?? 0;
    const frameworkCompliant = (id: number) => breakdown.find((f) => f.id === id)?.compliant_count ?? 0;
    const frameworkTotal = (id: number) => breakdown.find((f) => f.id === id)?.total_controls ?? 0;

    const totalRisks = (risks.critical || 0) + (risks.high || 0) + (risks.medium || 0) + (risks.low || 0);

    const currentDateFormatted = useMemo(() => {
        const d = new Date();
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return `${days[d.getDay()]}, ${formatDateIndonesian(d)}`;
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/superadmin/dashboard">
            <Head title="Command Center — Superadmin" />

            {/* Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <span className="text-primary dark:text-primary-200 text-xs font-bold tracking-wide uppercase">
                            {currentDateFormatted} · Administrator Utama
                        </span>
                    </div>
                    <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Command Center Sistem</h1>
                    <p className="mt-0.5 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                        Pantau integritas sistem SMKI, alokasi peran pengguna, dan status kepatuhan secara menyeluruh.
                    </p>
                </div>

                <div className="flex items-center gap-2.5">
                    <Link
                        href="/admin/superadmin/frameworks"
                        className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        <Database className="h-4 w-4 text-slate-500" />
                        Standar Framework
                    </Link>
                    <Link
                        href="/admin/superadmin/roles"
                        className="bg-primary hover:bg-primary inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-sm transition-all active:scale-95 sm:text-sm"
                    >
                        <KeyRound className="h-4 w-4" />
                        Manajemen Role & Izin
                    </Link>
                </div>
            </div>

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

                {/* 2. Total Pengguna */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Pengguna Aktif</span>
                        <div className="bg-primary-50 text-primary dark:bg-navy-900/50 dark:text-primary-200 flex h-9 w-9 items-center justify-center rounded-xl">
                            <Users className="h-4.5 w-4.5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-baseline gap-2">
                            <span className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{totalUsers}</span>
                            <span className="bg-primary-50 text-primary-700 dark:bg-navy-900/60 dark:text-primary-200 rounded-md px-2 py-0.5 text-xs font-semibold">
                                Akun Terdaftar
                            </span>
                        </div>
                        <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">Semua unit kerja terhubung</p>
                    </div>
                </div>

                {/* 3. Pustaka Kontrol SMKI */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Pustaka Kontrol</span>
                        <div className="bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300 flex h-9 w-9 items-center justify-center rounded-xl">
                            <Layers className="h-4.5 w-4.5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-baseline gap-2">
                            <span className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{totalControls || 127}</span>
                            <span className="bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300 rounded-md px-2 py-0.5 text-xs font-semibold">
                                {totalFrameworks} Framework
                            </span>
                        </div>
                        <p className="mt-3 truncate text-xs text-slate-500 dark:text-slate-400">
                            {frameworks.map((f) => f.nama).join(' · ') || 'ISO 27001 · ISO 27701'}
                        </p>
                    </div>
                </div>

                {/* 4. Temuan Ketidaksesuaian */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Temuan Audit Terbuka</span>
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
                            {findings.overdue > 0 ? `${findings.overdue} melewati batas SLA` : 'Semua item dalam batas SLA'}
                        </p>
                    </div>
                </div>
            </div>

            {/* Row 2: Standar Framework Overview */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div className="flex items-center gap-2.5">
                            <div className="bg-primary-50 text-primary dark:bg-navy-900/50 dark:text-primary-200 flex h-8 w-8 items-center justify-center rounded-lg">
                                <Shield className="h-4 w-4" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">{frameworks[0]?.nama || 'ISO/IEC 27001:2022'}</h3>
                                <p className="text-[11px] text-slate-500 dark:text-slate-400">Sistem Manajemen Keamanan Informasi</p>
                            </div>
                        </div>
                        <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                            {frameworkRate(1)}% Patuh
                        </span>
                    </div>

                    <div className="pt-4">
                        <div className="flex items-center justify-between text-xs">
                            <span className="font-medium text-slate-500 dark:text-slate-400">Realisasi Kontrol Organisasi</span>
                            <span className="font-bold text-slate-900 dark:text-white">
                                {frameworkCompliant(1)} dari {frameworkTotal(1)} Kontrol Terpenuhi
                            </span>
                        </div>
                        <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div className="bg-primary h-full rounded-full transition-all duration-500" style={{ width: `${frameworkRate(1)}%` }} />
                        </div>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div className="flex items-center gap-2.5">
                            <div className="bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300 flex h-8 w-8 items-center justify-center rounded-lg">
                                <ShieldCheck className="h-4 w-4" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">{frameworks[1]?.nama || 'ISO/IEC 27701:2019'}</h3>
                                <p className="text-[11px] text-slate-500 dark:text-slate-400">Sistem Manajemen Informasi Privasi (PIMS)</p>
                            </div>
                        </div>
                        <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                            {frameworkRate(2)}% Patuh
                        </span>
                    </div>

                    <div className="pt-4">
                        <div className="flex items-center justify-between text-xs">
                            <span className="font-medium text-slate-500 dark:text-slate-400">Realisasi Kontrol Organisasi</span>
                            <span className="font-bold text-slate-900 dark:text-white">
                                {frameworkCompliant(2)} dari {frameworkTotal(2)} Kontrol Terpenuhi
                            </span>
                        </div>
                        <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div
                                className="bg-primary-800 dark:bg-primary-400 h-full rounded-full transition-all duration-500"
                                style={{ width: `${frameworkRate(2)}%` }}
                            />
                        </div>
                    </div>
                </div>
            </div>

            {/* Row 3: Tren Kepatuhan & Status Risiko Keamanan */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-7">
                {/* Tren Kepatuhan Organisasi */}
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
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-3 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white">Status Risiko Keamanan</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">{totalRisks} risiko terdaftar di register</p>
                        </div>
                    </div>

                    <div className="space-y-3.5 py-3">
                        {/* Critical */}
                        <div>
                            <div className="flex items-center justify-between text-xs font-medium">
                                <span className="flex items-center gap-1.5 text-rose-600 dark:text-rose-400">
                                    <span className="h-2 w-2 rounded-full bg-rose-500" />
                                    Risiko Kritis
                                </span>
                                <span className="font-bold text-slate-900 dark:text-white">{risks.critical || 0}</span>
                            </div>
                            <div className="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    className="h-full rounded-full bg-rose-500 transition-all duration-300"
                                    style={{ width: `${totalRisks ? ((risks.critical || 0) / totalRisks) * 100 : 0}%` }}
                                />
                            </div>
                        </div>

                        {/* High */}
                        <div>
                            <div className="flex items-center justify-between text-xs font-medium">
                                <span className="flex items-center gap-1.5 text-amber-600 dark:text-amber-400">
                                    <span className="h-2 w-2 rounded-full bg-amber-500" />
                                    Risiko Tinggi
                                </span>
                                <span className="font-bold text-slate-900 dark:text-white">{risks.high || 0}</span>
                            </div>
                            <div className="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    className="h-full rounded-full bg-amber-500 transition-all duration-300"
                                    style={{ width: `${totalRisks ? ((risks.high || 0) / totalRisks) * 100 : 0}%` }}
                                />
                            </div>
                        </div>

                        {/* Medium */}
                        <div>
                            <div className="flex items-center justify-between text-xs font-medium">
                                <span className="text-primary dark:text-primary-200 flex items-center gap-1.5">
                                    <span className="bg-primary h-2 w-2 rounded-full" />
                                    Risiko Sedang
                                </span>
                                <span className="font-bold text-slate-900 dark:text-white">{risks.medium || 0}</span>
                            </div>
                            <div className="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    className="bg-primary h-full rounded-full transition-all duration-300"
                                    style={{ width: `${totalRisks ? ((risks.medium || 0) / totalRisks) * 100 : 0}%` }}
                                />
                            </div>
                        </div>

                        {/* Low */}
                        <div>
                            <div className="flex items-center justify-between text-xs font-medium">
                                <span className="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                                    <span className="h-2 w-2 rounded-full bg-emerald-500" />
                                    Risiko Rendah
                                </span>
                                <span className="font-bold text-slate-900 dark:text-white">{risks.low || 0}</span>
                            </div>
                            <div className="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    className="h-full rounded-full bg-emerald-500 transition-all duration-300"
                                    style={{ width: `${totalRisks ? ((risks.low || 0) / totalRisks) * 100 : 0}%` }}
                                />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">
                        Pencatatan rekam jejak audit trail bersifat permanen (immutable) dan tidak dapat dimanipulasi.
                    </div>
                </div>
            </div>

            {/* Row 4: Audit Trail & Aktivitas Sistem */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-7">
                <div className="flex flex-col rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-4 dark:border-slate-800 dark:bg-slate-900">
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

                    <div className="mt-3 flex-1 overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-slate-200 bg-slate-50/90 text-[11px] font-bold tracking-wider text-slate-600 uppercase dark:border-slate-800 dark:bg-[#001f38] dark:text-slate-300">
                                <tr>
                                    <th className="px-3 py-2.5">Waktu</th>
                                    <th className="px-3 py-2.5">Pengguna</th>
                                    <th className="px-3 py-2.5">Aktivitas</th>
                                    <th className="px-3 py-2.5 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/70">
                                {recent_activities.length > 0 ? (
                                    recent_activities.slice(0, 5).map((act, idx) => (
                                        <tr
                                            key={act.id}
                                            className={`transition-colors ${
                                                idx % 2 === 0 ? 'bg-white dark:bg-[#00223d]/70' : 'bg-slate-50/75 dark:bg-[#00172b]/80'
                                            } hover:bg-primary-50/40 dark:hover:bg-[#0a3b63]/60`}
                                        >
                                            <td className="px-3 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">
                                                {act.created_at ? formatDateTimeIndonesian(act.created_at) : act.time_ago}
                                            </td>
                                            <td className="px-3 py-3 font-semibold whitespace-nowrap text-slate-900 dark:text-white">
                                                {act.actor_name}
                                                <span className="block text-[10.5px] font-normal text-slate-400">{act.actor_role}</span>
                                            </td>
                                            <td className="px-3 py-3 text-slate-700 dark:text-slate-300">
                                                <span className="font-medium">{act.action}</span>
                                                {act.entity_name && (
                                                    <span className="block text-[11px] text-slate-400 dark:text-slate-500">{act.entity_name}</span>
                                                )}
                                            </td>
                                            <td className="py-3 pl-3 text-right whitespace-nowrap">
                                                <span className="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                                                    Tercatat
                                                </span>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={4} className="py-8 text-center text-xs text-slate-500 dark:text-slate-400">
                                            Belum ada log aktivitas baru.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
