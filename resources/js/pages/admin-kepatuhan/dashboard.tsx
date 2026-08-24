import ComplianceAreaChart, { type TrendPoint } from '@/components/dashboards/ComplianceAreaChart';
import AppLayout from '@/layouts/AppLayout';
import { formatDateIndonesian, formatDateTimeIndonesian } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowUpRight, Clock, FileCheck, Layers, Shield, ShieldAlert, ShieldCheck, TrendingUp } from 'lucide-react';
import { useMemo } from 'react';

interface FrameworkBreakdown {
    id: number;
    nama: string;
    versi: string;
    compliance_rate: number;
    compliant_count: number;
    partial_count: number;
    non_compliant_count: number;
    na_count: number;
    total_controls: number;
}

// TrendPoint is imported from ComplianceAreaChart component

interface RecentActivity {
    id: number;
    actor_name: string;
    actor_role: string;
    action: string;
    entity_name: string;
    time_ago: string;
    created_at: string | null;
}

interface AdminDashboardProps {
    summary?: {
        overall_compliance_rate: number;
        growth_from_last_period: number;
        total_controls_active: number;
        frameworks_breakdown: FrameworkBreakdown[];
        findings_summary: { total_active: number; major: number; minor: number; observasi: number; overdue: number };
        risks_summary: { total_active: number; critical: number; high: number; medium: number; low: number };
    };
    trends?: TrendPoint[];
    recent_activities?: RecentActivity[];
}

export default function Dashboard({ summary, trends = [], recent_activities = [] }: AdminDashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const userName = auth.user?.name || 'Administrator';

    const breadcrumbs = [{ label: 'Dashboard' }];

    const overallRate = summary?.overall_compliance_rate ?? 0;
    const growth = summary?.growth_from_last_period ?? 0;
    const frameworks = summary?.frameworks_breakdown ?? [];
    const findings = summary?.findings_summary ?? { total_active: 0, major: 0, minor: 0, observasi: 0, overdue: 0 };
    const risks = summary?.risks_summary ?? { total_active: 0, critical: 0, high: 0, medium: 0, low: 0 };

    const iso27001 = frameworks.find((f) => f.id === 1) || frameworks[0];
    const iso27701 = frameworks.find((f) => f.id === 2) || frameworks[1];

    // Current date formatted in Indonesian
    const currentDateFormatted = useMemo(() => {
        const d = new Date();
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const dayName = days[d.getDay()];
        return `${dayName}, ${formatDateIndonesian(d)}`;
    }, []);

    // Trend data langsung dari backend (tidak perlu kalkulasi manual)

    const totalRisks = (risks.critical || 0) + (risks.high || 0) + (risks.medium || 0) + (risks.low || 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/kepatuhan/dashboard">
            <Head title="Dashboard — Admin Kepatuhan" />

            {/* Top Greeting Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <span className="text-primary dark:text-primary-200 text-xs font-bold tracking-wide uppercase">{currentDateFormatted}</span>
                    </div>
                    <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Selamat Datang, {userName}</h1>
                    <p className="mt-0.5 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                        Berikut adalah ringkasan status kepatuhan standar keamanan informasi organisasi Anda.
                    </p>
                </div>

                <div className="flex items-center gap-2.5">
                    <Link
                        href="/admin/kepatuhan/compliance"
                        className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        <Layers className="h-4 w-4 text-slate-500" />
                        Pustaka Kontrol
                    </Link>
                    <Link
                        href="/admin/kepatuhan/checklist/verify"
                        className="bg-primary hover:bg-primary inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-sm transition-all active:scale-95"
                    >
                        <FileCheck className="h-4 w-4" />
                        Verifikasi Penilaian
                    </Link>
                </div>
            </div>

            {/* Row 1: Executive KPI Cards */}
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

                {/* 2. Active Standards */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Standar Terdaftar</span>
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
                                <span className="font-semibold text-rose-600 dark:text-rose-400">{findings.overdue} melewati batas waktu</span>
                            ) : (
                                'Semua item dalam batas SLA'
                            )}
                        </p>
                    </div>
                </div>

                {/* 4. Active Findings */}
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
                                Gap Terbuka
                            </span>
                        </div>
                        <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
                            {findings.major} Mayor · {findings.minor} Minor · {findings.observasi} Observasi
                        </p>
                    </div>
                </div>
            </div>

            {/* Row 2: Standar Kepatuhan ISO Cards */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {/* ISO 27001 */}
                <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div className="flex items-center gap-2.5">
                            <div className="bg-primary-50 text-primary dark:bg-navy-900/50 dark:text-primary-200 flex h-8 w-8 items-center justify-center rounded-lg">
                                <Shield className="h-4 w-4" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">{iso27001?.nama || 'ISO/IEC 27001:2022'}</h3>
                                <p className="text-[11px] text-slate-500 dark:text-slate-400">Sistem Manajemen Keamanan Informasi</p>
                            </div>
                        </div>
                        <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                            {iso27001?.compliance_rate ?? 0}% Patuh
                        </span>
                    </div>

                    <div className="pt-4">
                        <div className="flex items-center justify-between text-xs">
                            <span className="font-medium text-slate-500 dark:text-slate-400">Realisasi Kontrol</span>
                            <span className="font-bold text-slate-900 dark:text-white">
                                {iso27001?.compliant_count ?? 0} dari {iso27001?.total_controls ?? 0} Kontrol Terpenuhi
                            </span>
                        </div>
                        <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div
                                className="bg-primary h-full rounded-full transition-all duration-500"
                                style={{ width: `${iso27001?.compliance_rate ?? 0}%` }}
                            />
                        </div>
                    </div>
                </div>

                {/* ISO 27701 */}
                <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div className="flex items-center gap-2.5">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                                <ShieldCheck className="h-4 w-4" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">{iso27701?.nama || 'ISO/IEC 27701:2019'}</h3>
                                <p className="text-[11px] text-slate-500 dark:text-slate-400">Sistem Manajemen Informasi Privasi (PIMS)</p>
                            </div>
                        </div>
                        <span className="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400">
                            {iso27701?.compliance_rate ?? 0}% Patuh
                        </span>
                    </div>

                    <div className="pt-4">
                        <div className="flex items-center justify-between text-xs">
                            <span className="font-medium text-slate-500 dark:text-slate-400">Realisasi Kontrol</span>
                            <span className="font-bold text-slate-900 dark:text-white">
                                {iso27701?.compliant_count ?? 0} dari {iso27701?.total_controls ?? 0} Kontrol Terpenuhi
                            </span>
                        </div>
                        <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div
                                className="h-full rounded-full bg-indigo-600 transition-all duration-500"
                                style={{ width: `${iso27701?.compliance_rate ?? 0}%` }}
                            />
                        </div>
                    </div>
                </div>
            </div>

            {/* Row 3: Tren Kepatuhan & Asesmen Risiko Organisasi */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-7">
                {/* Tren Kepatuhan — Recharts AreaChart */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-4 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-start justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white">Tren Kepatuhan Organisasi</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Riwayat perkembangan kepatuhan bulanan</p>
                        </div>
                        {/* Legend */}
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                            <span className="flex items-center gap-1.5">
                                <span className="h-2 w-4 rounded-full" style={{ background: '#196ecd' }} />
                                Rata-rata
                            </span>
                            <span className="flex items-center gap-1.5">
                                <span className="h-0.5 w-4" style={{ borderTop: '2px dashed #002745', display: 'block' }} />
                                ISO 27001
                            </span>
                            <span className="flex items-center gap-1.5">
                                <span className="h-0.5 w-4" style={{ borderTop: '2px dashed #4a9fd4', display: 'block' }} />
                                ISO 27701
                            </span>
                        </div>
                    </div>

                    <div className="pt-4">
                        <ComplianceAreaChart trends={trends} />
                    </div>
                </div>

                {/* Distribusi Risiko Keamanan Informasi */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-3 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white">Asesmen Risiko Keamanan</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">{totalRisks} risiko teridentifikasi</p>
                        </div>
                        <Link
                            href="/admin/kepatuhan/risks"
                            className="text-primary hover:text-primary dark:text-primary-200 inline-flex items-center gap-1 text-xs font-semibold"
                        >
                            Buka Register
                            <ArrowUpRight className="h-3.5 w-3.5" />
                        </Link>
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

                    {risks.critical > 0 ? (
                        <div className="rounded-xl border border-rose-200 bg-rose-50/60 p-3 text-xs text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300">
                            <strong>Perhatian:</strong> Terdapat {risks.critical} risiko berstatus Kritis yang memerlukan rencana mitigasi aktif.
                        </div>
                    ) : (
                        <div className="rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">
                            Tidak ada risiko kritis yang memerlukan tindakan darurat saat ini.
                        </div>
                    )}
                </div>
            </div>

            {/* Row 4: Tindakan Prioritas & Log Aktivitas Terbaru */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-7">
                {/* Tindakan Prioritas */}
                <div className="flex flex-col rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-3 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white">Tindakan Prioritas</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Item yang membutuhkan persetujuan atau tindak lanjut</p>
                        </div>
                        <span className="bg-primary-50 text-primary-700 dark:bg-navy-900/60 dark:text-primary-200 rounded-full px-2.5 py-0.5 text-xs font-bold">
                            {findings.total_active + risks.total_active} Item
                        </span>
                    </div>

                    <div className="mt-3 space-y-3">
                        <Link
                            href="/admin/kepatuhan/checklist/verify"
                            className="group hover:border-primary-300 hover:bg-primary-50/30 flex items-start gap-3 rounded-xl border border-slate-200/70 p-3.5 transition-colors dark:border-slate-800 dark:hover:border-slate-700 dark:hover:bg-slate-800/40"
                        >
                            <div className="bg-primary-50 text-primary dark:bg-navy-900/50 dark:text-primary-200 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl">
                                <FileCheck className="h-4.5 w-4.5" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <h4 className="group-hover:text-primary dark:group-hover:text-primary-300 text-xs font-bold text-slate-900 dark:text-white">
                                    Verifikasi Pengajuan Checklist PIC
                                </h4>
                                <p className="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                    Tinjau bukti evidence dan konfirmasi status kepatuhan unit kerja.
                                </p>
                            </div>
                        </Link>

                        <Link
                            href="/admin/kepatuhan/findings"
                            className="group flex items-start gap-3 rounded-xl border border-slate-200/70 p-3.5 transition-colors hover:border-amber-300 hover:bg-amber-50/30 dark:border-slate-800 dark:hover:border-slate-700 dark:hover:bg-slate-800/40"
                        >
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                                <AlertCircle className="h-4.5 w-4.5" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <h4 className="text-xs font-bold text-slate-900 group-hover:text-amber-600 dark:text-white dark:group-hover:text-amber-400">
                                    {findings.total_active} Temuan Audit Terbuka
                                </h4>
                                <p className="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                    {findings.overdue > 0 ? `${findings.overdue} temuan melewati batas SLA.` : 'Pantau rencana perbaikan PIC.'}
                                </p>
                            </div>
                        </Link>
                    </div>
                </div>

                {/* Riwayat Aktivitas Audit */}
                <div className="flex flex-col rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-4 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white">Aktivitas & Log Terbaru</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Catatan aktivitas audit sistem terkini</p>
                        </div>
                        <Link
                            href="/audit-logs"
                            className="text-primary hover:text-primary dark:text-primary-200 inline-flex items-center gap-1 text-xs font-semibold"
                        >
                            Lihat Semua
                            <ArrowUpRight className="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <div className="mt-3 flex-1 overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-slate-100 text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:text-slate-400">
                                <tr>
                                    <th className="py-2.5 pr-3">Waktu</th>
                                    <th className="px-3 py-2.5">Pengguna</th>
                                    <th className="px-3 py-2.5">Aktivitas</th>
                                    <th className="py-2.5 pl-3 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {recent_activities.length > 0 ? (
                                    recent_activities.slice(0, 5).map((act) => (
                                        <tr key={act.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                                            <td className="py-3 pr-3 whitespace-nowrap text-slate-500 dark:text-slate-400">
                                                {act.created_at ? formatDateTimeIndonesian(act.created_at) : act.time_ago}
                                            </td>
                                            <td className="px-3 py-3 font-semibold whitespace-nowrap text-slate-900 dark:text-white">
                                                {act.actor_name}
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
