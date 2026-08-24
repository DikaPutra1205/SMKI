import AppLayout from '@/layouts/AppLayout';
import { formatDateIndonesian, formatDateTimeIndonesian } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Clock, FileSearch, Shield, ShieldAlert, ShieldCheck, TrendingUp } from 'lucide-react';
import { useMemo } from 'react';

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
        overall_compliance_rate: number;
        growth_from_last_period: number;
        total_controls_active: number;
        frameworks_breakdown: { id: number; nama: string; versi: string; compliance_rate: number; compliant_count: number; total_controls: number }[];
        findings_summary: { total_active: number; major: number; minor: number; observasi: number; overdue: number };
        risks_summary: {
            total_active: number;
            critical: number;
            high: number;
            medium: number;
            low: number;
        };
    };
    trends?: { period: string; label: string; iso27001_rate: number; iso27701_rate: number; overall_rate: number }[];
    recent_activities?: RecentActivity[];
}

const FALLBACK_TREND_LABELS = ['Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu'];

export default function AuditorDashboard({ summary, trends = [], recent_activities = [] }: AuditorDashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const userName = auth.user?.name || 'Auditor Kepatuhan';

    const breadcrumbs = [{ label: 'Dashboard Auditor' }];

    const overallRate = summary?.overall_compliance_rate ?? 0;
    const growth = summary?.growth_from_last_period ?? 0;
    const frameworks = summary?.frameworks_breakdown ?? [];
    const findings = summary?.findings_summary ?? { total_active: 0, major: 0, minor: 0, observasi: 0, overdue: 0 };
    const risks = summary?.risks_summary ?? { total_active: 0, critical: 0, high: 0, medium: 0, low: 0 };

    const iso27001 = frameworks.find((f) => f.id === 1) || frameworks[0];
    const iso27701 = frameworks.find((f) => f.id === 2) || frameworks[1];

    const currentDateFormatted = useMemo(() => {
        const d = new Date();
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return `${days[d.getDay()]}, ${formatDateIndonesian(d)}`;
    }, []);

    // ── Trend Chart Geometry ──────────────────────────────────────────────────
    const chartW = 600;
    const chartH = 200;
    const chartPadL = 36;
    const chartPadR = 16;
    const chartPadT = 16;
    const chartPadB = 28;
    const chartInnerW = chartW - chartPadL - chartPadR;
    const chartInnerH = chartH - chartPadT - chartPadB;

    const trendPoints = trends.length > 0 ? trends : [];
    const trendLabels = trendPoints.length > 0 ? trendPoints.map((p) => p.label) : FALLBACK_TREND_LABELS;
    const trendValues = trendPoints.length > 0 ? trendPoints.map((p) => p.overall_rate) : [65, 70, 72, 75, 78, overallRate || 80];

    const chartX = (i: number) => {
        const n = trendLabels.length;
        return n <= 1 ? chartPadL + chartInnerW / 2 : chartPadL + (chartInnerW * i) / (n - 1);
    };
    const chartY = (v: number) => chartPadT + chartInnerH - (chartInnerH * Math.min(100, Math.max(0, v))) / 100;

    const linePoints = trendValues.map((v, i) => `${chartX(i)},${chartY(v)}`).join(' ');
    const areaPath =
        trendValues.length > 0
            ? `M ${chartX(0)} ${chartY(trendValues[0])} ` +
              trendValues.map((v, i) => `L ${chartX(i)} ${chartY(v)}`).join(' ') +
              ` L ${chartX(trendValues.length - 1)} ${chartPadT + chartInnerH} L ${chartX(0)} ${chartPadT + chartInnerH} Z`
            : '';

    const totalRisks = (risks.critical || 0) + (risks.high || 0) + (risks.medium || 0) + (risks.low || 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/auditor/dashboard">
            <Head title="Dashboard — Auditor Kepatuhan" />

            {/* Header */}
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

                <div className="flex items-center gap-2.5">
                    <Link
                        href="/admin/auditor/findings"
                        className="bg-primary hover:bg-primary inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-sm transition-all active:scale-95 sm:text-sm"
                    >
                        <FileSearch className="h-4 w-4" />
                        Temuan Audit
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

            {/* Row 3: Tren & Risiko */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-7">
                {/* Tren Kepatuhan */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-4 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white">Tren Kepatuhan Organisasi</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Riwayat perkembangan kepatuhan bulanan</p>
                        </div>
                        <div className="text-primary dark:text-primary-200 flex items-center gap-1.5 text-xs font-semibold">
                            <span className="bg-primary h-2.5 w-2.5 rounded-full" />
                            Rata-rata Kepatuhan (%)
                        </div>
                    </div>

                    <div className="pt-4">
                        <svg className="h-[200px] w-full" viewBox={`0 0 ${chartW} ${chartH}`} preserveAspectRatio="none" role="img">
                            <defs>
                                <linearGradient id="auditorAreaGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor="#2563eb" stopOpacity="0.25" />
                                    <stop offset="100%" stopColor="#2563eb" stopOpacity="0.0" />
                                </linearGradient>
                            </defs>
                            {[0, 1, 2, 3].map((g) => {
                                const y = chartPadT + (chartInnerH * g) / 3;
                                return (
                                    <line
                                        key={g}
                                        x1={chartPadL}
                                        y1={y}
                                        x2={chartW - chartPadR}
                                        y2={y}
                                        stroke="#f1f5f9"
                                        className="dark:stroke-slate-800"
                                        strokeWidth="1"
                                    />
                                );
                            })}
                            {areaPath && <path d={areaPath} fill="url(#auditorAreaGrad)" />}
                            {linePoints && (
                                <polyline
                                    points={linePoints}
                                    fill="none"
                                    stroke="#2563eb"
                                    strokeWidth="2.5"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                            )}
                            {trendValues.map((v, i) => (
                                <circle
                                    key={i}
                                    cx={chartX(i)}
                                    cy={chartY(v)}
                                    r="4"
                                    className="stroke-primary fill-white dark:fill-slate-900"
                                    strokeWidth="2"
                                />
                            ))}
                            {/* X-axis Labels */}
                            <g fill="#94a3b8" className="dark:fill-slate-500" fontSize="10.5" fontWeight="500" textAnchor="middle">
                                {trendLabels.map((label, i) => (
                                    <text key={label + i} x={chartX(i)} y={chartH - 8}>
                                        {label}
                                    </text>
                                ))}
                            </g>
                            {/* Y-axis Labels */}
                            <g fill="#94a3b8" className="dark:fill-slate-500" fontSize="10" fontWeight="500" textAnchor="end">
                                {[100, 75, 50, 25, 0].map((p) => (
                                    <text key={p} x={chartPadL - 8} y={chartY(p) + 3}>
                                        {p}%
                                    </text>
                                ))}
                            </g>
                        </svg>
                    </div>
                </div>

                {/* Status Risiko Keamanan */}
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-3 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white">Status Risiko Keamanan</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">{totalRisks} risiko teridentifikasi</p>
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
                        Hasil evaluasi ini disajikan untuk mendukung audit kepatuhan internal dan eksternal.
                    </div>
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
                                recent_activities.slice(0, 6).map((act) => (
                                    <tr key={act.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                                        <td className="py-3 pr-3 whitespace-nowrap text-slate-500 dark:text-slate-400">
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
                                                Tervalidasi
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
        </AppLayout>
    );
}
