import { t } from '@/lib/i18n';

interface RiskAssessmentProps {
    risks: { total_active: number; critical: number; high: number; medium: number; low: number };
    riskRegisterLabel?: string;
    labels?: {
        criticalLabel?: string;
        mitigationInProgress?: string;
    };
}

export default function RiskAssessment({ risks, riskRegisterLabel = 'Buka register', labels }: RiskAssessmentProps) {
    return (
        <div className="border-border dark:border-slate-700 rounded-[14px] border bg-white dark:bg-slate-900 shadow-sm">
            <div className="border-border dark:border-slate-700 flex items-center justify-between border-b px-5 py-4">
                <h3>{t('dashboard.riskAssessment')}</h3>
                <a
                    href={`/admin/kepatuhan/findings?status=${riskRegisterLabel}`}
                    className="text-primary hover:text-primary-700 dark:hover:text-primary-200 text-xs font-semibold"
                >
                    {t('dashboard.riskRegister')}
                </a>
            </div>
            <div className="px-5 py-4">
                <div className="flex items-end justify-center gap-8" style={{ height: '150px' }}>
                    {[
                        { label: labels?.criticalLabel ?? t('status.low'), value: risks.low, color: 'linear-gradient(180deg,#7CC0A8,#BDE0D1)' },
                        {
                            label: labels?.mitigationInProgress ?? t('status.medium'),
                            value: risks.medium,
                            color: 'linear-gradient(180deg,#F0B45E,#F8DCA6)',
                        },
                        { label: t('status.high'), value: risks.high, color: 'linear-gradient(180deg,#D15A4A,#E8947A)' },
                    ].map((b) => (
                        <div key={b.label} className="flex w-12 flex-col items-center gap-1">
                            <span className="text-navy dark:text-white text-xs font-bold">{b.value}</span>
                            <div className="w-full rounded-t-[8px]" style={{ height: `${Math.max(6, b.value * 18)}px`, background: b.color }} />
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
    );
}
