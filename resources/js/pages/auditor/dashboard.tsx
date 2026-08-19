import ComplianceAreaChart from '@/components/dashboards/ComplianceAreaChart';
import RiskAssessment from '@/components/dashboards/RiskAssessment';
import AppLayout from '@/layouts/AppLayout';
import { t } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle2, ClipboardList, ShieldAlert, ShieldCheck, TrendingUp } from 'lucide-react';

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
            major?: number;
            minor?: number;
            observasi?: number;
        };
    };
    trends?: { period: string; label: string; iso27001_rate: number; iso27701_rate: number; overall_rate: number }[];
    recent_activities?: RecentActivity[];
}

export default function AuditorDashboard({ summary, trends = [], recent_activities = [] }: AuditorDashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const userName = auth.user?.name || '';

    const breadcrumbs = [{ label: t('dashboard.title') }];

    const overallRate = summary?.overall_compliance_rate ?? 0;
    const growth = summary?.growth_from_last_period ?? 0;
    const frameworks = summary?.frameworks_breakdown ?? [];
    const findings = summary?.findings_summary ?? { total_active: 0, major: 0, minor: 0, observasi: 0, overdue: 0 };
    const risks = summary?.risks_summary ?? { total_active: 0, critical: 0, high: 0, medium: 0, low: 0 };

    const iso27001 = frameworks.find((f) => f.id === 1);
    const iso27701 = frameworks.find((f) => f.id === 2);

    // ── Trend chart ──────────────────────────────────────────────────────
    const trendLabels = trends.length > 0 ? trends.map((p) => p.label) : ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul'];
    const trendValues = trends.length > 0 ? trends.map((p) => p.overall_rate) : [];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/auditor/dashboard">
            <Head title="Dashboard Auditor" />

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('dashboard.title')}</h1>
                    <p className="text-muted text-sm">
                        {t('dashboard.welcomeBack')}, <span className="text-navy font-semibold">{userName}</span>. {t('dashboard.subtitle')}.
                    </p>
                </div>

                <div className="flex items-center gap-3">
                    <Link
                        href="/admin/kepatuhan/compliance"
                        className="bg-primary shadow-blue hover:bg-primary-700 inline-flex items-center gap-2 rounded-[10px] px-4 py-2 text-sm font-semibold text-white transition-colors"
                    >
                        <ShieldCheck className="h-4 w-4" />
                        <span>{t('compliance.title')}</span>
                    </Link>
                </div>
            </div>

            {/* Row 1: KPI */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="border-border rounded-[14px] border bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <span className="text-muted text-xs font-semibold tracking-wider uppercase">{t('dashboard.overallCompliance')}</span>
                        <div className="bg-success-bg text-success flex h-9 w-9 items-center justify-center rounded-[10px]">
                            <TrendingUp className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-navy text-3xl font-bold">{overallRate.toFixed(1)}%</span>
                        {growth !== 0 && (
                            <span className={`flex items-center text-xs font-medium ${growth >= 0 ? 'text-success' : 'text-danger'}`}>
                                {growth >= 0 ? '+' : ''}
                                {growth.toFixed(1)}% {t('dashboard.thisMonth')}
                            </span>
                        )}
                    </div>
                    <div className="bg-surface-2 mt-3 h-1.5 w-full overflow-hidden rounded-full">
                        <div className="bg-success h-full rounded-full" style={{ width: `${overallRate}%` }} />
                    </div>
                </div>

                <div className="border-border rounded-[14px] border bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <span className="text-muted text-xs font-semibold tracking-wider uppercase">{t('dashboard.activeStandards')}</span>
                        <div className="bg-primary-50 text-primary flex h-9 w-9 items-center justify-center rounded-[10px]">
                            <ShieldCheck className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-navy text-3xl font-bold">{frameworks.length}</span>
                        <span className="border-success-border bg-success-border text-success rounded-[6px] border px-2 py-0.5 text-[11px] font-semibold">
                            {t('common.active')}
                        </span>
                    </div>
                    <p className="text-muted mt-3 text-xs">{frameworks.map((f) => f.nama).join(' · ')}</p>
                </div>

                <div className="border-border rounded-[14px] border bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <span className="text-muted text-xs font-semibold tracking-wider uppercase">{t('dashboard.pendingActions')}</span>
                        <div className="bg-warning-bg text-warning flex h-9 w-9 items-center justify-center rounded-[10px]">
                            <ClipboardList className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-navy text-3xl font-bold">{findings.total_active}</span>
                        <span className="text-warning text-xs font-medium">{t('dashboard.needsAction')}</span>
                    </div>
                    <p className="text-muted mt-3 text-xs">{findings.overdue} overdue</p>
                </div>

                <div className="border-border rounded-[14px] border bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <span className="text-muted text-xs font-semibold tracking-wider uppercase">{t('dashboard.nonCompliances')}</span>
                        <div className="bg-danger-bg text-danger flex h-9 w-9 items-center justify-center rounded-[10px]">
                            <ShieldAlert className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-navy text-3xl font-bold">{risks.total_active}</span>
                        <span className="text-danger text-xs font-medium">{t('status.open')}</span>
                    </div>
                    <p className="text-muted mt-3 text-xs">
                        {risks.major} {t('status.major')} · {risks.minor} {t('status.minor')} · {risks.observasi} {t('status.observation')}
                    </p>
                </div>
            </div>

            {/* Row 2: Standar Kepatuhan */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div className="border-border rounded-[14px] border bg-white p-5 shadow-sm">
                    <div className="border-border flex items-center justify-between border-b pb-3">
                        <h3 className="text-[15px] font-bold">{iso27001?.nama ?? 'ISO/IEC 27001:2022'}</h3>
                        <span className="border-success-border bg-success-border text-success rounded-[6px] border px-2 py-0.5 text-xs font-semibold">
                            {t('common.active')}
                        </span>
                    </div>
                    <div className="flex items-center justify-between pt-4">
                        <strong className="text-navy text-[13.5px]">{t('dashboard.compliance')}</strong>
                        <span className="text-navy text-xs font-bold">
                            {iso27001?.compliance_rate ?? 0}% · {iso27001?.compliant_count ?? 0}/{iso27001?.total_controls ?? 0}{' '}
                            {t('dashboard.controls')}
                        </span>
                    </div>
                    <div className="bg-surface-2 mt-2 h-2 w-full overflow-hidden rounded-full">
                        <div className="bg-primary h-full rounded-full" style={{ width: `${iso27001?.compliance_rate ?? 0}%` }} />
                    </div>
                </div>

                <div className="border-border rounded-[14px] border bg-white p-5 shadow-sm">
                    <div className="border-border flex items-center justify-between border-b pb-3">
                        <h3 className="text-[15px] font-bold">{iso27701?.nama ?? 'ISO/IEC 27701:2019'}</h3>
                        <span className="border-navy/15 bg-navy/5 text-navy rounded-[6px] border px-2 py-0.5 text-xs font-semibold">
                            v{iso27701?.versi ?? '2019'}
                        </span>
                    </div>
                    <div className="flex items-center justify-between pt-4">
                        <strong className="text-navy text-[13.5px]">{t('dashboard.compliance')}</strong>
                        <span className="text-navy text-xs font-bold">
                            {iso27701?.compliance_rate ?? 0}% · {iso27701?.compliant_count ?? 0}/{iso27701?.total_controls ?? 0}{' '}
                            {t('dashboard.controls')}
                        </span>
                    </div>
                    <div className="bg-surface-2 mt-2 h-2 w-full overflow-hidden rounded-full">
                        <div className="bg-warning h-full rounded-full" style={{ width: `${iso27701?.compliance_rate ?? 0}%` }} />
                    </div>
                </div>
            </div>

            {/* Row 3: Tren Kepatuhan + Asesmen Risiko */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-7">
                <div className="border-border rounded-[14px] border bg-white shadow-sm lg:col-span-4">
                    <div className="border-border flex items-center justify-between border-b px-5 py-4">
                        <h3>{t('dashboard.trend')}</h3>
                        <div className="flex items-center gap-4">
                            <span className="text-body flex items-center gap-1.5 text-xs">
                                <span className="bg-primary h-2.5 w-2.5 rounded-full" /> {t('dashboard.compliance')}
                            </span>
                        </div>
                    </div>
                    <div className="px-5 py-4">
                        {trendValues.length > 0 ? (
                            <ComplianceAreaChart labels={trendLabels} values={trendValues} />
                        ) : (
                            <div className="text-muted flex h-[230px] items-center justify-center text-sm">{t('common.noData')}</div>
                        )}
                    </div>
                </div>

                <div className="border-border rounded-[14px] border bg-white shadow-sm lg:col-span-3">
                    <div className="border-border flex items-center justify-between border-b px-5 py-4">
                        <h3>{t('dashboard.riskAssessment')}</h3>
                        <a href="#" className="text-primary hover:text-primary-700 text-xs font-semibold">
                            {t('dashboard.riskRegister')}
                        </a>
                    </div>
                    <div className="px-5 py-4">
                        <RiskAssessment
                            risks={risks}
                            labels={{ criticalLabel: t('dashboard.critical'), mitigationInProgress: t('dashboard.mitigationInProgress') }}
                        />
                    </div>
                </div>
            </div>

            {/* Row 4: Audit Terbaru (real audit logs via getRecentActivities) */}
            <div className="border-border overflow-hidden rounded-[14px] border bg-white shadow-sm">
                <div className="border-border flex items-center justify-between border-b px-5 py-4">
                    <h3>{t('dashboard.recentAudit')}</h3>
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
                                        <td className="text-navy px-5 py-3.5 text-sm font-medium whitespace-nowrap">{act.actor_name}</td>
                                        <td className="px-5 py-3.5">
                                            <span className="text-navy block text-sm font-semibold">{act.action}</span>
                                            <span className="text-muted block text-xs">{act.entity_name}</span>
                                        </td>
                                        <td className="text-body px-5 py-3.5 text-sm whitespace-nowrap">{act.actor_role}</td>
                                        <td className="px-5 py-3.5">
                                            <span className="border-info/20 bg-info-bg text-info inline-flex items-center rounded-[6px] border px-2 py-0.5 text-xs font-semibold">
                                                {t('dashboard.pending')}
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

            {/* Row 5: Tindakan yang Harus Dilakukan */}
            <div className="border-border rounded-[14px] border bg-white shadow-sm">
                <div className="border-border flex items-center justify-between border-b px-5 py-4">
                    <h3>{t('dashboard.todo')}</h3>
                    <span className="bg-surface-2 text-muted rounded-full px-2.5 py-0.5 text-xs font-semibold">
                        {findings.total_active + risks.total_active} {t('dashboard.actionsPending')}
                    </span>
                </div>
                <div className="divide-border divide-y">
                    <div className="flex items-center gap-3 px-5 py-3.5">
                        <div className="bg-primary-50 text-primary flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px]">
                            <CheckCircle2 className="h-[18px] w-[18px]" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <strong className="text-navy block text-sm">
                                {findings.total_active} {t('status.open')} {t('status.observation').toLowerCase()}
                            </strong>
                            <span className="text-muted block text-xs">{findings.overdue} overdue</span>
                        </div>
                        <span className="border-warning-border bg-warning-bg text-warning rounded-[6px] border px-2 py-0.5 text-xs font-semibold">
                            {t('dashboard.pending')}
                        </span>
                    </div>
                    <div className="flex items-center gap-3 px-5 py-3.5">
                        <div className="bg-danger-bg text-danger flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px]">
                            <ShieldAlert className="h-[18px] w-[18px]" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <strong className="text-navy block text-sm">
                                {risks.total_active} {t('dashboard.riskAssessment').toLowerCase()}
                            </strong>
                            <span className="text-muted block text-xs">
                                {risks.critical} {t('status.critical').toLowerCase()} · {risks.high} {t('status.high').toLowerCase()} · {risks.medium}{' '}
                                {t('status.medium').toLowerCase()}
                            </span>
                        </div>
                        <span className="border-danger-border bg-danger-bg text-danger rounded-[6px] border px-2 py-0.5 text-xs font-semibold">
                            {t('status.open')}
                        </span>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
