import { cn } from '@/lib/utils';

export type StatusTone = 'green' | 'blue' | 'red' | 'amber' | 'gray' | 'violet' | 'navy';

const toneStyles: Record<StatusTone, string> = {
    green: 'bg-success-bg text-success dark:text-emerald-400 border-success-border dark:border-emerald-800',
    blue: 'bg-info-bg text-info dark:text-sky-400 border-info/20',
    red: 'bg-danger-bg text-danger dark:text-red-400 border-danger-border dark:border-red-800',
    amber: 'bg-warning-bg text-warning dark:text-amber-400 border-warning-border dark:border-amber-800',
    gray: 'bg-neutral-bg dark:bg-slate-800 text-neutral dark:text-slate-400 border-border dark:border-slate-700',
    violet: 'bg-violet-bg text-violet dark:text-violet-400 border-violet/20',
    navy: 'bg-navy-50 dark:bg-white/5 text-navy dark:text-white border-navy/15 dark:border-white/10',
};

interface StatusBadgeProps {
    tone?: StatusTone;
    children: React.ReactNode;
    className?: string;
}

export function StatusBadge({ tone = 'gray', children, className }: StatusBadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-[6px] border px-2.5 py-1 text-xs font-semibold whitespace-nowrap',
                toneStyles[tone],
                className,
            )}
        >
            <span className="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
            {children}
        </span>
    );
}

/** Map a raw compliance/risk/finding status string to a badge tone. */
export function statusTone(status?: string | null): StatusTone {
    const s = (status || '').toLowerCase();

    if (['compliant', 'approved', 'verified', 'patuh', 'aktif', 'disetujui', 'terverifikasi', 'closed', 'ditutup', 'low', 'rendah'].includes(s)) {
        return 'green';
    }
    if (['partial', 'sebagian', 'sebagian patuh', 'pending', 'menunggu', 'menunggu verifikasi', 'in_progress', 'sedang diproses', 'terbuka', 'open', 'medium', 'sedang'].includes(s)) {
        return 'amber';
    }
    if (['non_compliant', 'tidak patuh', 'rejected', 'ditolak', 'overdue', 'critical', 'kritis', 'high', 'tinggi'].includes(s)) {
        return 'red';
    }
    if (['na', 'tidak berlaku', 'automatic', 'otomatis'].includes(s)) {
        return 'gray';
    }
    if (['major', 'mayor'].includes(s)) {
        return 'red';
    }
    if (['minor', 'observation', 'observasi'].includes(s)) {
        return 'violet';
    }
    if (['active', 'aktif'].includes(s)) {
        return 'green';
    }
    return 'blue';
}