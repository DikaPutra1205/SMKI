import type { ReactNode } from 'react';

interface FrameworkComplianceCardProps {
    nama: string;
    deskripsi: string;
    completionRate: number;
    selesaiCount: number;
    totalControls: number;
    icon?: ReactNode;
    iconClassName?: string;
    barClassName?: string;
    realisasiLabel?: string;
}

export default function FrameworkComplianceCard({
    nama,
    deskripsi,
    completionRate,
    selesaiCount,
    totalControls,
    icon,
    iconClassName = 'bg-primary-50 text-primary dark:bg-navy-900/50 dark:text-primary-200',
    barClassName = 'bg-primary',
    realisasiLabel = 'Realisasi Kontrol',
}: FrameworkComplianceCardProps) {
    return (
        <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
                <div className="flex items-center gap-2.5">
                    <div className={`flex h-8 w-8 items-center justify-center rounded-lg ${iconClassName}`}>{icon}</div>
                    <div>
                        <h3 className="text-sm font-bold text-slate-900 dark:text-white">{nama}</h3>
                        <p className="text-[11px] text-slate-500 dark:text-slate-400">{deskripsi}</p>
                    </div>
                </div>
                <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                    {completionRate}% Patuh
                </span>
            </div>

            <div className="pt-4">
                <div className="flex items-center justify-between text-xs">
                    <span className="font-medium text-slate-500 dark:text-slate-400">{realisasiLabel}</span>
                    <span className="font-bold text-slate-900 dark:text-white">
                        {selesaiCount} dari {totalControls} Kontrol Selesai Diterapkan
                    </span>
                </div>
                <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div className={`h-full rounded-full transition-all duration-500 ${barClassName}`} style={{ width: `${completionRate}%` }} />
                </div>
            </div>
        </div>
    );
}
