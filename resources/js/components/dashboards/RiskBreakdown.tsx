import type { ReactNode } from 'react';

export interface RiskSummary {
    total_active?: number;
    critical: number;
    high: number;
    medium: number;
    low: number;
    very_low: number;
}

interface RiskBreakdownProps {
    risks: RiskSummary;
    title?: string;
    subtitle?: string;
    headerAction?: ReactNode;
    footer?: ReactNode;
}

function RiskRow({ label, value, total, dotClass, barClass }: { label: string; value: number; total: number; dotClass: string; barClass: string }) {
    return (
        <div>
            <div className="flex items-center justify-between text-xs font-medium">
                <span className={`flex items-center gap-1.5 ${dotClass}`}>
                    <span className={`h-2 w-2 rounded-full ${barClass}`} />
                    {label}
                </span>
                <span className="font-bold text-slate-900 dark:text-white">{value || 0}</span>
            </div>
            <div className="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div
                    className={`h-full rounded-full transition-all duration-300 ${barClass}`}
                    style={{ width: `${total ? ((value || 0) / total) * 100 : 0}%` }}
                />
            </div>
        </div>
    );
}

export default function RiskBreakdown({ risks, title = 'Status Risiko Keamanan', subtitle, headerAction, footer }: RiskBreakdownProps) {
    const total = (risks.critical || 0) + (risks.high || 0) + (risks.medium || 0) + (risks.low || 0) + (risks.very_low || 0);

    return (
        <div className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                <div>
                    <h3 className="text-sm font-bold text-slate-900 dark:text-white">{title}</h3>
                    <p className="text-xs text-slate-500 dark:text-slate-400">{subtitle ?? `${total} risiko teridentifikasi`}</p>
                </div>
                {headerAction}
            </div>

            <div className="space-y-3.5 py-3">
                <RiskRow
                    label="Risiko Kritis"
                    value={risks.critical}
                    total={total}
                    dotClass="text-rose-600 dark:text-rose-400"
                    barClass="bg-rose-500"
                />
                <RiskRow
                    label="Risiko Tinggi"
                    value={risks.high}
                    total={total}
                    dotClass="text-amber-600 dark:text-amber-400"
                    barClass="bg-amber-500"
                />
                <RiskRow
                    label="Risiko Sedang"
                    value={risks.medium}
                    total={total}
                    dotClass="text-primary dark:text-primary-200"
                    barClass="bg-primary"
                />
                <RiskRow
                    label="Risiko Rendah"
                    value={risks.low}
                    total={total}
                    dotClass="text-emerald-600 dark:text-emerald-400"
                    barClass="bg-emerald-500"
                />
                <RiskRow
                    label="Risiko Sangat Rendah"
                    value={risks.very_low}
                    total={total}
                    dotClass="text-teal-600 dark:text-teal-400"
                    barClass="bg-teal-500"
                />
            </div>

            {footer}
        </div>
    );
}
