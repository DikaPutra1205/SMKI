import AppLayout from '@/layouts/AppLayout';
import { formatDateIndonesian, formatPeriodeIndonesian } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowUpRight, ClipboardCheck, Clock, FileEdit, Layers, Shield, ShieldCheck, TrendingUp } from 'lucide-react';
import { useMemo } from 'react';

interface FrameworkBreakdown {
    id: number;
    nama: string;
    compliance_rate: number;
    compliant_count: number;
    partial_count: number;
    non_compliant_count: number;
    na_count: number;
    total_controls: number;
}

interface RecentSession {
    id: number;
    konteks_penilaian: string;
    periode: string;
    framework: string;
    total_entries: number;
    completed_entries: number;
    created_at: string | null;
}

interface PicDashboardProps {
    summary?: {
        overall_compliance_rate: number;
        growth_from_last_period: number;
        total_controls_active: number;
        frameworks_breakdown: FrameworkBreakdown[];
        findings_summary: { total_active: number; major: number; minor: number; observasi: number; overdue: number };
        risks_summary: {
            total_active: number;
            critical: number;
            high: number;
            medium: number;
            low: number;
        };
    };
    recent_sessions?: RecentSession[];
}

export default function PicDashboard({ summary, recent_sessions = [] }: PicDashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const userName = auth.user?.name || 'Petugas PIC';
    const userUnit = auth.user?.unit?.nama || 'Unit Kerja';

    const breadcrumbs = [{ label: 'Dashboard' }];

    const overallRate = summary?.overall_compliance_rate ?? 0;
    const growth = summary?.growth_from_last_period ?? 0;
    const frameworks = summary?.frameworks_breakdown ?? [];
    const findings = summary?.findings_summary ?? { total_active: 0, major: 0, minor: 0, observasi: 0, overdue: 0 };

    const iso27001 = frameworks.find((f) => f.id === 1) || frameworks[0];
    const iso27701 = frameworks.find((f) => f.id === 2) || frameworks[1];

    const currentDateFormatted = useMemo(() => {
        const d = new Date();
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return `${days[d.getDay()]}, ${formatDateIndonesian(d)}`;
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/dashboard">
            <Head title="Dashboard — PIC Unit Kerja" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <span className="text-primary dark:text-primary-200 text-xs font-bold tracking-wide uppercase">
                            {currentDateFormatted} · {userUnit}
                        </span>
                    </div>
                    <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Selamat Datang, {userName}</h1>
                    <p className="mt-0.5 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                        Kelola asesmen kepatuhan dan lengkapi bukti dukung keamanan informasi unit Anda.
                    </p>
                </div>

                <div className="flex items-center gap-2.5">
                    <Link
                        href="/checklist"
                        className="bg-primary hover:bg-primary inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-sm transition-all active:scale-95 sm:text-sm"
                    >
                        <ClipboardCheck className="h-4 w-4" />
                        Daftar Penilaian
                    </Link>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Kepatuhan Unit</span>
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

                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Sesi Penilaian Aktif</span>
                        <div className="bg-primary-50 text-primary dark:bg-navy-900/50 dark:text-primary-200 flex h-9 w-9 items-center justify-center rounded-xl">
                            <Layers className="h-4.5 w-4.5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-baseline gap-2">
                            <span className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{recent_sessions.length}</span>
                            <span className="bg-primary-50 text-primary-700 dark:bg-navy-900/60 dark:text-primary-200 rounded-md px-2 py-0.5 text-xs font-semibold">
                                Sesi Berjalan
                            </span>
                        </div>
                        <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">Tersedia untuk pengisian checklist</p>
                    </div>
                </div>

                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Standar Kepatuhan</span>
                        <div className="bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300 flex h-9 w-9 items-center justify-center rounded-xl">
                            <ShieldCheck className="h-4.5 w-4.5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-baseline gap-2">
                            <span className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{frameworks.length || 2}</span>
                            <span className="bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300 rounded-md px-2 py-0.5 text-xs font-semibold">
                                Framework
                            </span>
                        </div>
                        <p className="mt-3 truncate text-xs text-slate-500 dark:text-slate-400">
                            {frameworks.map((f) => f.nama).join(' · ') || 'ISO 27001 · ISO 27701'}
                        </p>
                    </div>
                </div>

                <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Temuan Audit Terbuka</span>
                        <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                            <AlertCircle className="h-4.5 w-4.5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <div className="flex items-baseline gap-2">
                            <span className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{findings.total_active}</span>
                            <span className="rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">
                                Perlu Tindak Lanjut
                            </span>
                        </div>
                        <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
                            {findings.overdue > 0 ? (
                                <span className="font-semibold text-rose-600 dark:text-rose-400">{findings.overdue} melewati batas SLA</span>
                            ) : (
                                'Semua temuan dalam pantauan'
                            )}
                        </p>
                    </div>
                </div>
            </div>

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
                            <div className="bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300 flex h-8 w-8 items-center justify-center rounded-lg">
                                <ShieldCheck className="h-4 w-4" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">{iso27701?.nama || 'ISO/IEC 27701:2019'}</h3>
                                <p className="text-[11px] text-slate-500 dark:text-slate-400">Sistem Manajemen Informasi Privasi (PIMS)</p>
                            </div>
                        </div>
                        <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
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
                                className="bg-primary-800 dark:bg-primary-400 h-full rounded-full transition-all duration-500"
                                style={{ width: `${iso27701?.compliance_rate ?? 0}%` }}
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-7">
                <div className="flex flex-col rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-4 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white">Sesi Penilaian Aktif Unit Anda</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Daftar asesmen kepatuhan yang sedang berlangsung</p>
                        </div>
                        <Link
                            href="/checklist"
                            className="text-primary hover:text-primary dark:text-primary-200 inline-flex items-center gap-1 text-xs font-semibold"
                        >
                            Lihat Semua
                            <ArrowUpRight className="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <div className="mt-3 divide-y divide-slate-100 dark:divide-slate-800/60">
                        {recent_sessions.length > 0 ? (
                            recent_sessions.slice(0, 4).map((s) => {
                                const pct = s.total_entries > 0 ? Math.round((s.completed_entries / s.total_entries) * 100) : 0;
                                return (
                                    <div key={s.id} className="flex items-center justify-between gap-4 py-3.5 first:pt-1">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <h4 className="truncate text-xs font-bold text-slate-900 dark:text-white">{s.konteks_penilaian}</h4>
                                                <span className="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10.5px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                    {s.framework}
                                                </span>
                                            </div>
                                            <div className="mt-1 flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                                                <span>Periode: {formatPeriodeIndonesian(s.periode)}</span>
                                                <span>·</span>
                                                <span>
                                                    {s.completed_entries} dari {s.total_entries} Kontrol Terisi ({pct}%)
                                                </span>
                                            </div>
                                            <div className="mt-2 h-1.5 w-full max-w-[280px] overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                                <div
                                                    className="bg-primary h-full rounded-full transition-all duration-300"
                                                    style={{ width: `${pct}%` }}
                                                />
                                            </div>
                                        </div>

                                        <Link
                                            href={`/checklist/${s.id}`}
                                            className="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                        >
                                            <FileEdit className="h-3.5 w-3.5 text-slate-500" />
                                            Lanjutkan
                                        </Link>
                                    </div>
                                );
                            })
                        ) : (
                            <div className="py-10 text-center">
                                <ClipboardCheck className="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                                <h4 className="mt-2 text-xs font-bold text-slate-700 dark:text-slate-300">Belum ada sesi penilaian aktif</h4>
                                <p className="mt-1 text-[11px] text-slate-500">Mulai asesmen baru untuk unit kerja Anda.</p>
                            </div>
                        )}
                    </div>
                </div>

                <div className="flex flex-col rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-3 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 dark:text-white">Tindakan & Checklist PIC</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Item prioritas yang menunggu penyelesaian Anda</p>
                        </div>
                    </div>

                    <div className="mt-3 space-y-3">
                        <Link
                            href="/checklist"
                            className="group hover:border-primary-300 hover:bg-primary-50/30 flex items-start gap-3 rounded-xl border border-slate-200/70 p-3.5 transition-colors dark:border-slate-800 dark:hover:border-slate-700 dark:hover:bg-slate-800/40"
                        >
                            <div className="bg-primary-50 text-primary dark:bg-navy-900/50 dark:text-primary-200 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl">
                                <FileEdit className="h-4.5 w-4.5" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <h4 className="group-hover:text-primary dark:group-hover:text-primary-300 text-xs font-bold text-slate-900 dark:text-white">
                                    Lengkapi Bukti Evidence Kontrol
                                </h4>
                                <p className="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                    Unggah dokumen pendukung untuk setiap kontrol kepatuhan SMKI.
                                </p>
                            </div>
                        </Link>

                        <div className="rounded-xl border border-slate-200/70 p-3.5 dark:border-slate-800">
                            <div className="flex items-start gap-3">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                                    <Clock className="h-4.5 w-4.5" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <h4 className="text-xs font-bold text-slate-900 dark:text-white">Pantau Status Verifikasi Admin</h4>
                                    <p className="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                        Setelah checklist diajukan, Admin Kepatuhan akan memeriksa kelengkapan berkas Anda.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
