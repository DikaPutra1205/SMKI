import AppLayout from '@/layouts/AppLayout';
import { t } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';
import { Database, Shield, ShieldCheck, TrendingUp, UserPlus, Users } from 'lucide-react';

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
}

export default function SuperadminDashboard({ totalUsers, totalFrameworks, frameworks, summary, recent_activities = [] }: SuperadminDashboardProps) {
    const breadcrumbs = [{ label: t('common.dashboard') }];

    const overallRate = summary?.overall_compliance_rate ?? 0;
    const growth = summary?.growth_from_last_period ?? 0;
    const findings = summary?.findings_summary ?? { total_active: 0, major: 0, minor: 0, observasi: 0, overdue: 0 };
    const risks = summary?.risks_summary ?? { total_active: 0, critical: 0, high: 0, medium: 0, low: 0 };
    const breakdown = summary?.frameworks_breakdown ?? [];

    const frameworkRate = (id: number) => breakdown.find((f) => f.id === id)?.compliance_rate ?? 0;
    const frameworkCompliant = (id: number) => breakdown.find((f) => f.id === id)?.compliant_count ?? 0;
    const frameworkTotal = (id: number) => breakdown.find((f) => f.id === id)?.total_controls ?? 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/superadmin/dashboard">
            <Head title="Dashboard - Superadmin" />

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Command Center</h1>
                    <p className="text-muted text-sm">
                        {t('superadmin.subtitle')} ·{' '}
                        <strong className="text-navy font-semibold">
                            {totalUsers} pengguna · {totalFrameworks} framework
                        </strong>
                    </p>
                </div>

                <div className="flex items-center gap-3">
                    <Link
                        href="/admin/superadmin/frameworks"
                        className="border-border-strong text-navy hover:bg-surface inline-flex items-center gap-2 rounded-[10px] border bg-white px-4 py-2 text-sm font-semibold shadow-sm transition-colors"
                    >
                        <Database className="h-4 w-4" />
                        <span>{t('superadmin.frameworkManagement')}</span>
                    </Link>
                    <Link
                        href="/admin/superadmin/frameworks"
                        className="bg-primary shadow-blue hover:bg-primary-700 inline-flex items-center gap-2 rounded-[10px] px-4 py-2 text-sm font-semibold text-white transition-colors"
                    >
                        <UserPlus className="h-4 w-4" />
                        <span>{t('superadmin.userManagement')}</span>
                    </Link>
                </div>
            </div>

            {/* Row 1: KPI */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div className="border-border rounded-[14px] border bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <span className="text-muted text-xs font-semibold tracking-wider uppercase">{t('dashboard.overallCompliance')}</span>
                        <div className="bg-primary-50 text-primary flex h-9 w-9 items-center justify-center rounded-[10px]">
                            <TrendingUp className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-navy text-3xl font-bold">{overallRate.toFixed(1)}%</span>
                        {growth !== 0 && (
                            <span className={`flex items-center text-xs font-medium ${growth >= 0 ? 'text-success' : 'text-danger'}`}>
                                {growth >= 0 ? '+' : ''}
                                {growth.toFixed(1)}%
                            </span>
                        )}
                    </div>
                    <p className="text-muted mt-3 text-xs">
                        {totalFrameworks} {t('dashboard.activeStandards').toLowerCase()} · target 80%
                    </p>
                </div>

                <div className="border-border rounded-[14px] border bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <span className="text-muted text-xs font-semibold tracking-wider uppercase">{t('dashboard.activeStandards')}</span>
                        <div className="bg-navy/10 text-navy flex h-9 w-9 items-center justify-center rounded-[10px]">
                            <Database className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-navy text-3xl font-bold">{totalFrameworks}</span>
                        <span className="border-success-border bg-success-bg text-success rounded-[6px] border px-2 py-0.5 text-[11px] font-semibold">
                            {t('common.active')}
                        </span>
                    </div>
                    <p className="text-muted mt-3 text-xs">{frameworks.map((f) => f.nama).join(' · ')}</p>
                </div>

                <div className="border-border rounded-[14px] border bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <span className="text-muted text-xs font-semibold tracking-wider uppercase">{t('dashboard.pendingActions')}</span>
                        <div className="bg-warning-bg text-warning flex h-9 w-9 items-center justify-center rounded-[10px]">
                            <Shield className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-navy text-3xl font-bold">{findings.total_active + risks.total_active}</span>
                        <span className="border-warning-border bg-warning-bg text-warning rounded-[6px] border px-2 py-0.5 text-[11px] font-semibold">
                            {findings.total_active + risks.total_active} {t('dashboard.actionsPending')}
                        </span>
                    </div>
                    <p className="text-muted mt-3 text-xs">checklist · evidence · temuan</p>
                </div>

                <div className="border-border rounded-[14px] border bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <span className="text-muted text-xs font-semibold tracking-wider uppercase">{t('dashboard.nonCompliances')}</span>
                        <div className="bg-danger-bg text-danger flex h-9 w-9 items-center justify-center rounded-[10px]">
                            <ShieldCheck className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-navy text-3xl font-bold">{findings.total_active}</span>
                        <span className="border-danger-border bg-danger-bg text-danger rounded-[6px] border px-2 py-0.5 text-[11px] font-semibold">
                            {findings.overdue} overdue
                        </span>
                    </div>
                    <p className="text-muted mt-3 text-xs">
                        {findings.major} {t('status.major')} · {findings.minor} {t('status.minor')} · {findings.observasi} {t('status.observation')}
                    </p>
                </div>

                <div className="border-border rounded-[14px] border bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <span className="text-muted text-xs font-semibold tracking-wider uppercase">{t('superadmin.totalActiveUsers')}</span>
                        <div className="bg-violet-bg text-violet flex h-9 w-9 items-center justify-center rounded-[10px]">
                            <Users className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-navy text-3xl font-bold">{totalUsers}</span>
                        <span className="border-success-border bg-success-bg text-success rounded-[6px] border px-2 py-0.5 text-[11px] font-semibold">
                            {t('common.active')}
                        </span>
                    </div>
                    <p className="text-muted mt-3 text-xs">5 role · semua unit aktif</p>
                </div>
            </div>

            {/* Row 2: Standar Kepatuhan */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div className="border-border rounded-[14px] border bg-white p-5 shadow-sm">
                    <div className="border-border flex items-center justify-between border-b pb-3">
                        <h3 className="text-[15px] font-bold">{frameworks[0]?.nama ?? 'ISO/IEC 27001:2022'}</h3>
                        <span className="border-success-border bg-success-bg text-success rounded-[6px] border px-2 py-0.5 text-xs font-semibold">
                            {t('common.active')}
                        </span>
                    </div>
                    <div className="flex items-center justify-between pt-4">
                        <strong className="text-navy text-[13.5px]">{t('dashboard.compliance')}</strong>
                        <span className="text-navy text-xs font-bold">
                            {frameworkRate(1)}% · {frameworkCompliant(1)}/{frameworkTotal(1)} {t('dashboard.controls')}
                        </span>
                    </div>
                    <div className="bg-surface-2 mt-2 h-2 w-full overflow-hidden rounded-full">
                        <div className="bg-primary h-full rounded-full" style={{ width: `${frameworkRate(1)}%` }} />
                    </div>
                    <Link href="/admin/superadmin/frameworks" className="text-primary hover:text-primary-700 mt-3 inline-block text-xs font-semibold">
                        {t('superadmin.manage')} framework →
                    </Link>
                </div>

                <div className="border-border rounded-[14px] border bg-white p-5 shadow-sm">
                    <div className="border-border flex items-center justify-between border-b pb-3">
                        <h3 className="text-[15px] font-bold">{frameworks[1]?.nama ?? 'ISO/IEC 27701:2019'}</h3>
                        <span className="border-navy/15 bg-navy/5 text-navy rounded-[6px] border px-2 py-0.5 text-xs font-semibold">
                            v{frameworks[1]?.versi ?? '2019'}
                        </span>
                    </div>
                    <div className="flex items-center justify-between pt-4">
                        <strong className="text-navy text-[13.5px]">{t('dashboard.compliance')}</strong>
                        <span className="text-navy text-xs font-bold">
                            {frameworkRate(2)}% · {frameworkCompliant(2)}/{frameworkTotal(2)} {t('dashboard.controls')}
                        </span>
                    </div>
                    <div className="bg-surface-2 mt-2 h-2 w-full overflow-hidden rounded-full">
                        <div className="bg-warning h-full rounded-full" style={{ width: `${frameworkRate(2)}%` }} />
                    </div>
                    <Link href="/admin/superadmin/frameworks" className="text-primary hover:text-primary-700 mt-3 inline-block text-xs font-semibold">
                        {t('superadmin.manage')} framework →
                    </Link>
                </div>
            </div>

            {/* Row 3: Recent Audit Trail + Pending Access */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-7">
                <div className="border-border rounded-[14px] border bg-white shadow-sm lg:col-span-4">
                    <div className="border-border flex items-center justify-between border-b px-5 py-4">
                        <h3>{t('superadmin.recentActivities')}</h3>
                        <a href="#" className="text-primary hover:text-primary-700 text-xs font-semibold">
                            {t('common.viewAll')}
                        </a>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-border bg-surface/60 text-muted border-b text-[11px] font-bold tracking-wider uppercase">
                                <tr>
                                    <th scope="col" className="px-5 py-3 font-semibold">
                                        {t('dashboard.time')}
                                    </th>
                                    <th scope="col" className="px-5 py-3 font-semibold">
                                        {t('dashboard.user')}
                                    </th>
                                    <th scope="col" className="px-5 py-3 font-semibold">
                                        {t('dashboard.action')}
                                    </th>
                                    <th scope="col" className="px-5 py-3 font-semibold">
                                        {t('dashboard.module')}
                                    </th>
                                    <th scope="col" className="px-5 py-3 font-semibold">
                                        {t('dashboard.status')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-border divide-y">
                                {recent_activities.length > 0 ? (
                                    recent_activities.map((act) => (
                                        <tr key={act.id} className="hover:bg-surface/50 transition-colors">
                                            <td className="text-muted px-5 py-3.5 text-xs whitespace-nowrap">{act.time_ago}</td>
                                            <td className="px-5 py-3.5">
                                                <span className="text-navy block text-sm font-medium">{act.actor_name}</span>
                                                <span className="text-muted block text-xs">{act.actor_role}</span>
                                            </td>
                                            <td className="text-body px-5 py-3.5 text-sm">{act.action}</td>
                                            <td className="text-body px-5 py-3.5 text-sm">{act.entity_name}</td>
                                            <td className="px-5 py-3.5">
                                                <span className="border-success-border bg-success-bg text-success inline-flex items-center rounded-[6px] border px-2 py-0.5 text-xs font-semibold">
                                                    {t('status.approved')}
                                                </span>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={5} className="text-muted px-5 py-10 text-center text-sm">
                                            {t('common.noData')}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="border-border rounded-[14px] border bg-white shadow-sm lg:col-span-3">
                    <div className="border-border flex items-center justify-between border-b px-5 py-4">
                        <h3>{t('dashboard.riskAssessment')}</h3>
                        <span className="bg-surface-2 text-muted rounded-full px-2.5 py-0.5 text-xs font-semibold">
                            {risks.total_active} {t('common.active').toLowerCase()}
                        </span>
                    </div>
                    <div className="px-5 py-4">
                        <div className="flex items-end justify-center gap-8" style={{ height: '150px' }}>
                            {[
                                { label: t('status.low'), value: risks.low, color: 'linear-gradient(180deg,#7CC0A8,#BDE0D1)' },
                                { label: t('status.medium'), value: risks.medium, color: 'linear-gradient(180deg,#F0B45E,#F8DCA6)' },
                                { label: t('status.high'), value: risks.high, color: 'linear-gradient(180deg,#D15A4A,#E8947A)' },
                            ].map((b) => (
                                <div key={b.label} className="flex w-12 flex-col items-center gap-1">
                                    <span className="text-navy text-xs font-bold">{b.value}</span>
                                    <div
                                        className="w-full rounded-t-[8px]"
                                        style={{ height: `${Math.max(6, b.value * 18)}px`, background: b.color }}
                                    />
                                    <span className="text-muted text-[11px]">{b.label}</span>
                                </div>
                            ))}
                        </div>
                        {risks.critical > 0 && (
                            <div className="border-danger-border bg-danger-bg mt-3.5 rounded-[10px] border px-3.5 py-2.5 text-xs">
                                <strong className="text-danger">RISK-CRITICAL</strong>{' '}
                                <span className="text-danger/80">
                                    {risks.critical} {t('status.critical').toLowerCase()} — {t('dashboard.mitigationInProgress')}.
                                </span>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Row 4: Framework Overview */}
            <div className="border-border rounded-[14px] border bg-white shadow-sm">
                <div className="border-border flex items-center justify-between border-b px-5 py-4">
                    <h3>{t('superadmin.frameworkOverview')}</h3>
                    <Link href="/admin/superadmin/frameworks" className="text-primary hover:text-primary-700 text-xs font-semibold">
                        {t('superadmin.manage')} →
                    </Link>
                </div>
                <div className="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
                    {frameworks.length > 0 ? (
                        frameworks.map((fw) => (
                            <div key={fw.id} className="border-border bg-surface/50 flex items-center justify-between rounded-[10px] border p-4">
                                <div className="flex items-center gap-3">
                                    <div className="bg-primary flex h-9 w-9 items-center justify-center rounded-[10px] text-white shadow-sm">
                                        <Database className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <h4 className="text-navy text-sm font-bold">{fw.nama}</h4>
                                        <p className="text-muted text-xs">Versi {fw.versi}</p>
                                    </div>
                                </div>
                                <span className="bg-surface-2 text-body rounded-[6px] px-2.5 py-1 text-xs font-medium">
                                    {fw.controls_count} {t('dashboard.controls')}
                                </span>
                            </div>
                        ))
                    ) : (
                        <p className="text-muted col-span-2 py-4 text-center text-sm">{t('common.noData')}</p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
