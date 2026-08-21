import { t } from '@/lib/i18n';

interface KpiCardProps {
    title: string;
    value: string | number;
    subtitle?: string;
    trend?: { label: string; delta: number; positive: boolean };
}

export default function KpiCard({ title, value, subtitle, trend }: KpiCardProps) {
    return (
        <div className="border-border dark:border-slate-700 rounded-[14px] border bg-white dark:bg-slate-900 p-5 shadow-sm">
            <div className="flex items-center justify-between">
                <span className="text-muted dark:text-slate-400 text-xs font-semibold tracking-wider uppercase">{title}</span>
                <span className="text-navy dark:text-white text-3xl font-bold">{value}</span>
            </div>
            {trend && (
                <span className="text-muted dark:text-slate-400 text-xs">
                    {trend.positive ? '+' : ''}
                    {trend.delta} {t('dashboard.thisMonth')}
                </span>
            )}
            {subtitle && <p className="text-muted dark:text-slate-400 mt-2 text-sm">{subtitle}</p>}
        </div>
    );
}
