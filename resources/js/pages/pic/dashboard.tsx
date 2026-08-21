import AppLayout from '@/layouts/AppLayout';
import { t } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle2, ClipboardList, ShieldAlert, ShieldCheck, TrendingUp } from 'lucide-react';

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
            major?: number;
            minor?: number;
            observasi?: number;
        };
    };
    recent_sessions?: RecentSession[];
}

export default function PicDashboard({ summary, recent_sessions = [] }: PicDashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const userName = auth.user?.name || '';

    const breadcrumbs = [{ label: t('dashboard.title') }];

    const overallRate = summary?.overall_compliance_rate ?? 0;
    const growth = summary?.growth_from_last_period ?? 0;
    const frameworks = summary?.frameworks_breakdown ?? [];
    const findings = summary?.findings_summary ?? { total_active: 0, major: 0, minor: 0, observasi: 0, overdue: 0 };
    const risks = summary?.risks_summary ?? { total_active: 0, critical: 0, high: 0, medium: 0, low: 0, major: 0, minor: 0, observasi: 0 };

    const iso27001 = frameworks.find((f) => f.id === 1);
    const iso27701 = frameworks.find((f) => f.id === 2);

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/pic/dashboard">
            <Head title="Dashboard PIC" />

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('dashboard.title')}</h1>
                    <p className="text-muted dark:text-slate-400 text-sm">
                        {t('dashboard.welcomeBack')}, <span className="text-navy dark:text-white font-semibold">{userName}</span>. {t('dashboard.subtitle')}.
                    </p>
                </div>

                <div className="flex items-center gap-3">
                    <Link
                        href="/admin/pic/assessments"
                        className="bg-primary shadow-blue hover:bg-primary-700 inline-flex items-center gap-2 rounded-[10px] px-4 py-2 text-sm font-semibold text-white transition-colors"
                    >
                        <ClipboardList className="h-4 w-4" />
                        <span>{t('compliance.title')}</span>
                    </Link>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="border-border dark:border-slate-700 rounded-[14px] border bg-white dark:bg-slate-900 p-5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <span className="text-muted dark:text-slate-400 text-xs font-semibold tracking-wider uppercase">{t('dashboard.overallCompliance')}</span>
                        <div className="bg-success-bg text-success dark:text-emerald-400 flex h-9 w-9 items-center justify-center rounded-[10px]">
                            <TrendingUp className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-navy dark:text-white text-3xl font-bold">{overallRate.toFixed(1)}%</span>
                        {growth !== 0 && (
                            <span className={`flex items-center text-xs font-medium ${growth >= 0 ? 'text-success dark:text-emerald-400' : 'text-danger dark:text-red-400'}`}>
                                {growth >= 0 ? '+' : ''}
                                {growth.toFixed(1)}% {t('dashboard.thisMonth')}
                            </span>
                        )}
                    </div>
                    <div className="bg-surface-2 dark:bg-slate-800 mt-3 h-1.5 w-full overflow-hidden rounded-full">
                        <div className="bg-success h-full rounded-full" style={{ width: `${overallRate}%` }} />
                    </div>
                </div>

                <div className="border-border dark:border-slate-700 rounded-[14px] border bg-white dark:bg-slate-900 p-5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <span className="text-muted dark:text-slate-400 text-xs font-semibold tracking-wider uppercase">{t('dashboard.activeStandards')}</span>
                        <div className="bg-primary-50 dark:bg-primary/10 text-primary flex h-9 w-9 items-center justify-center rounded-[10px]">
                            <ShieldCheck className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-navy dark:text-white text-3xl font-bold">{frameworks.length}</span>
                        <span className="border-success-border dark:border-emerald-800 bg-success-border text-success dark:text-emerald-400 rounded-[6px] border px-2 py-0.5 text-[11px] font-semibold">
                            {t('common.active')}
                        </span>
                    </div>
                    <p className="text-muted dark:text-slate-400 mt-3 text-xs">{frameworks.map((f) => f.nama).join(' · ')}</p>
                </div>

                <div className="border-border dark:border-slate-700 rounded-[14px] border bg-white dark:bg-slate-900 p-5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <span className="text-muted dark:text-slate-400 text-xs font-semibold tracking-wider uppercase">{t('dashboard.pendingActions')}</span>
                        <div className="bg-warning-bg text-warning dark:text-amber-400 flex h-9 w-9 items-center justify-center rounded-[10px]">
                            <ClipboardList className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-navy dark:text-white text-3xl font-bold">{findings.total_active + risks.total_active}</span>
                        <span className="text-warning dark:text-amber-400 text-xs font-medium">{t('dashboard.needsAction')}</span>
                    </div>
                    <p className="text-muted dark:text-slate-400 mt-3 text-xs">{recent_sessions.length} sesi</p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div className="border-border dark:border-slate-700 rounded-[14px] border bg-white dark:bg-slate-900 p-5 shadow-sm">
                    <div className="border-border dark:border-slate-700 flex items-center justify-between border-b pb-3">
                        <h3 className="text-[15px] font-bold">{iso27001?.nama ?? 'ISO/IEC 27001:2022'}</h3>
                        <span className="border-success-border dark:border-emerald-800 bg-success-border text-success dark:text-emerald-400 rounded-[6px] border px-2 py-0.5 text-xs font-semibold">
                            {t('common.active')}
                        </span>
                    </div>
                    <div className="flex items-center justify-between pt-4">
                        <strong className="text-navy dark:text-white text-[13.5px]">{t('dashboard.compliance')}</strong>
                        <span className="text-navy dark:text-white text-xs font-bold">
                            {iso27001?.compliance_rate ?? 0}% · {iso27001?.compliant_count ?? 0}/{iso27001?.total_controls ?? 0}{' '}
                            {t('dashboard.controls')}
                        </span>
                    </div>
                    <div className="bg-surface-2 dark:bg-slate-800 mt-2 h-2 w-full overflow-hidden rounded-full">
                        <div className="bg-primary h-full rounded-full" style={{ width: `${iso27001?.compliance_rate ?? 0}%` }} />
                    </div>
                </div>

                <div className="border-border dark:border-slate-700 rounded-[14px] border bg-white dark:bg-slate-900 p-5 shadow-sm">
                    <div className="border-border dark:border-slate-700 flex items-center justify-between border-b pb-3">
                        <h3 className="text-[15px] font-bold">{iso27701?.nama ?? 'ISO/IEC 27701:2019'}</h3>
                        <span className="border-navy/15 dark:border-white/10 bg-navy/5 dark:bg-white/5 text-navy dark:text-white rounded-[6px] border px-2 py-0.5 text-xs font-semibold">v2019</span>
                    </div>
                    <div className="flex items-center justify-between pt-4">
                        <strong className="text-navy dark:text-white text-[13.5px]">{t('dashboard.compliance')}</strong>
                        <span className="text-navy dark:text-white text-xs font-bold">
                            {iso27701?.compliance_rate ?? 0}% · {iso27701?.compliant_count ?? 0}/{iso27701?.total_controls ?? 0}{' '}
                            {t('dashboard.controls')}
                        </span>
                    </div>
                    <div className="bg-surface-2 dark:bg-slate-800 mt-2 h-2 w-full overflow-hidden rounded-full">
                        <div className="bg-warning h-full rounded-full" style={{ width: `${iso27701?.compliance_rate ?? 0}%` }} />
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-7">
                <div className="border-border dark:border-slate-700 rounded-[14px] border bg-white dark:bg-slate-900 shadow-sm lg:col-span-4">
                    <div className="border-border dark:border-slate-700 flex items-center justify-between border-b px-5 py-4">
                        <h3>{t('dashboard.trend')}</h3>
                        <div className="flex items-center gap-4">
                            <span className="text-body dark:text-slate-300 flex items-center gap-1.5 text-xs">
                                <span className="bg-primary h-2.5 w-2.5 rounded-full" /> {t('dashboard.compliance')}
                            </span>
                        </div>
                    </div>
                    <div className="px-5 py-4">
                        <div className="text-muted dark:text-slate-400 flex h-[230px] items-center justify-center text-sm">{t('common.noData')}</div>
                    </div>
                </div>

                <div className="border-border dark:border-slate-700 rounded-[14px] border bg-white dark:bg-slate-900 shadow-sm lg:col-span-3">
                    <div className="border-border dark:border-slate-700 flex items-center justify-between border-b px-5 py-4">
                        <h3>{t('dashboard.riskAssessment')}</h3>
                        <a href="#" className="text-primary hover:text-primary-700 dark:hover:text-primary-200 text-xs font-semibold">
                            {t('dashboard.riskRegister')}
                        </a>
                    </div>
                    <div className="px-5 py-4">
                        <div className="flex items-end justify-center gap-8" style={{ height: '150px' }}>
                            {[
                                { label: t('status.low'), value: risks.low, color: 'linear-gradient(180deg,#7CC0A8,#BDE0D1)' },
                                { label: t('status.medium'), value: risks.medium, color: 'linear-gradient(180deg,#F0B45E,#F8DCA6)' },
                                { label: t('status.high'), value: risks.high, color: 'linear-gradient(180deg,#D15A4A,#E8947A)' },
                            ].map((b) => (
                                <div key={b.label} className="flex w-12 flex-col items-center gap-1">
                                    <span className="text-navy dark:text-white text-xs font-bold">{b.value}</span>
                                    <div
                                        className="w-full rounded-t-[8px]"
                                        style={{ height: `${Math.max(6, b.value * 18)}px`, background: b.color }}
                                    />
                                    <span className="text-muted dark:text-slate-400 text-[11px]">{b.label}</span>
                                </div>
                            ))}
                        </div>
                        {risks.critical > 0 && (
                            <div className="border-danger-border dark:border-red-800 bg-danger-bg mt-3.5 rounded-[10px] border px-3.5 py-2.5 text-xs">
                                <strong className="text-danger dark:text-red-400">RISK-CRITICAL</strong>{' '}
                                <span className="text-danger/80 dark:text-red-400/80">
                                    {risks.critical} {t('status.critical').toLowerCase()} — {t('dashboard.mitigationInProgress')}.
                                </span>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <div className="border-border dark:border-slate-700 overflow-hidden rounded-[14px] border bg-white dark:bg-slate-900 shadow-sm">
                <div className="border-border dark:border-slate-700 flex items-center justify-between border-b px-5 py-4">
                    <h3>{t('dashboard.recentActivity')}</h3>
                    <a href="#" className="text-primary hover:text-primary-700 dark:hover:text-primary-200 text-xs font-semibold">
                        {t('common.viewAll')}
                    </a>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="border-border dark:border-slate-700 bg-surface/60 dark:bg-slate-900/60 text-muted dark:text-slate-400 border-b text-[11px] font-bold tracking-wider uppercase">
                            <tr>
                                <th scope="col" className="px-5 py-3 font-semibold">
                                    {t('dashboard.time')}
                                </th>
                                <th scope="col" className="px-5 py-3 font-semibold">
                                    {t('dashboard.session')}
                                </th>
                                <th scope="col" className="px-5 py-3 font-semibold">
                                    {t('dashboard.framework')}
                                </th>
                                <th scope="col" className="px-5 py-3 font-semibold">
                                    {t('dashboard.compliance')}
                                </th>
                                <th scope="col" className="px-5 py-3 font-semibold">
                                    {t('dashboard.progress')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-border dark:divide-slate-700 divide-y">
                            {recent_sessions.length > 0 ? (
                                recent_sessions.map((session) => (
                                    <tr key={session.id} className="hover:bg-surface/50 dark:hover:bg-slate-800/50 transition-colors">
                                        <td className="text-muted dark:text-slate-400 px-5 py-3.5 text-xs whitespace-nowrap">
                                            {session.created_at
                                                ? new Date(session.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) +
                                                  ' · ' +
                                                  new Date(session.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
                                                : '—'}
                                        </td>
                                        <td className="text-navy dark:text-white px-5 py-3.5 text-sm font-medium whitespace-nowrap">{session.konteks_penilaian}</td>
                                        <td className="px-5 py-3.5 text-sm whitespace-nowrap">{session.framework}</td>
                                        <td className="text-body dark:text-slate-300 px-5 py-3.5 text-sm whitespace-nowrap">
                                            {session.total_entries} {t('common.active')}
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <div className="inline-flex items-center gap-1 rounded-[6px] border px-2 py-0.5 text-xs font-semibold">
                                                <span
                                                    className={`bg-surface-2 dark:bg-slate-800 text-body dark:text-slate-300 inline-flex items-center gap-0.5 rounded-[3px] px-1.5 py-0.5 text-[10px] font-semibold ${session.completed_entries > 0 ? 'text-success dark:text-emerald-400' : 'text-muted dark:text-slate-400'}`}
                                                >
                                                    {session.completed_entries}/{session.total_entries}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={5} className="text-muted dark:text-slate-400 px-5 py-10 text-center text-sm">
                                        {t('common.noData')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="border-border dark:border-slate-700 rounded-[14px] border bg-white dark:bg-slate-900 shadow-sm">
                <div className="border-border dark:border-slate-700 flex items-center justify-between border-b px-5 py-4">
                    <h3>{t('dashboard.todo')}</h3>
                    <span className="bg-surface-2 dark:bg-slate-800 text-muted dark:text-slate-400 rounded-full px-2.5 py-0.5 text-xs font-semibold">
                        {findings.total_active + risks.total_active} {t('dashboard.actionsPending')}
                    </span>
                </div>
                <div className="divide-border dark:divide-slate-700 divide-y">
                    <div className="flex items-center gap-3 px-5 py-3.5">
                        <div className="bg-primary-50 dark:bg-primary/10 text-primary flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px]">
                            <CheckCircle2 className="h-[18px] w-[18px]" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <strong className="text-navy dark:text-white block text-sm">
                                {findings.total_active} {t('status.open')} {t('status.observation').toLowerCase()}
                            </strong>
                            <span className="text-muted dark:text-slate-400 block text-xs">{findings.overdue} overdue</span>
                        </div>
                        <span className="border-warning-border dark:border-amber-800 bg-warning-bg text-warning dark:text-amber-400 rounded-[6px] border px-2 py-0.5 text-xs font-semibold">
                            {t('dashboard.pending')}
                        </span>
                    </div>
                    <div className="flex items-center gap-3 px-5 py-3.5">
                        <div className="bg-danger-bg text-danger dark:text-red-400 flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px]">
                            <ShieldAlert className="h-[18px] w-[18px]" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <strong className="text-navy dark:text-white block text-sm">
                                {risks.total_active} {t('dashboard.riskAssessment').toLowerCase()}
                            </strong>
                            <span className="text-muted dark:text-slate-400 block text-xs">
                                {risks.critical} {t('status.critical').toLowerCase()} · {risks.high} {t('status.high').toLowerCase()} · {risks.medium}{' '}
                                {t('status.medium').toLowerCase()}
                            </span>
                        </div>
                        <span className="border-danger-border dark:border-red-800 bg-danger-bg text-danger dark:text-red-400 rounded-[6px] border px-2 py-0.5 text-xs font-semibold">
                            {t('status.open')}
                        </span>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
