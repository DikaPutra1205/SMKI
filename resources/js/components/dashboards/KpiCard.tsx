import { t } from '@/lib/i18n';

interface KpiCardProps {
    title: string;
    value: string | number;
    subtitle?: string;
    trend?: { label: string; delta: number; positive: boolean };
}

export default function KpiCard({ title, value, subtitle, trend }: KpiCardProps) {
    return (
        <div className="border-border rounded-[14px] border bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div className="flex items-center justify-between">
                <span className="text-muted text-xs font-semibold tracking-wider uppercase dark:text-slate-400">{title}</span>
                <span className="text-navy text-3xl font-bold dark:text-white">{value}</span>
            </div>
            {trend && (
                <span className="text-muted text-xs dark:text-slate-400">
                    {trend.positive ? '+' : ''}
                    {trend.delta} {t('dashboard.thisMonth')}
                </span>
            )}
            {subtitle && <p className="text-muted mt-2 text-sm dark:text-slate-400">{subtitle}</p>}
        </div>
    );
}
