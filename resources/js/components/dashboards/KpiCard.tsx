import { t } from '@/lib/i18n';
import React from 'react';

interface KpiCardProps {
    title: string;
    value: string | number;
    subtitle?: string;
    trend?: { label: string; delta: number; positive: boolean };
}

export default function KpiCard({ title, value, subtitle, trend }: KpiCardProps) {
    return (
        <div className="border-border rounded-[14px] border bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between">
                <span className="text-muted text-xs font-semibold tracking-wider uppercase">{title}</span>
                <span className="text-navy text-3xl font-bold">{value}</span>
            </div>
            {trend && (
                <span className="text-xs text-muted">
                    {trend.positive ? '+' : ''}{trend.delta} {t('dashboard.thisMonth')}
                </span>
            )}
            {subtitle && <p className="mt-2 text-sm text-muted">{subtitle}</p>}
        </div>
    );
}