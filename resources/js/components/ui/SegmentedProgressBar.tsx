/**
 * SegmentedProgressBar
 *
 * A reusable stacked/segmented progress bar for compliance visualization.
 * Used on bulk-verify session cards, checklist cards, and dashboards.
 *
 * Segments are rendered proportionally based on `total`; each value ≥ 0.
 * The unfilled portion (total - sum of values) renders as the track color.
 */

import { cn } from '@/lib/utils';

export interface ProgressSegment {
    value: number;
    /** Tailwind background class, e.g. 'bg-emerald-500' */
    colorClass: string;
    label?: string;
}

interface SegmentedProgressBarProps {
    /** Total possible value (denominator). */
    total: number;
    /** Array of segments to render left-to-right. Values are raw counts (not %). */
    segments: ProgressSegment[];
    /** Height class, defaults to 'h-2' */
    heightClass?: string;
    /** Extra classes on the container track */
    className?: string;
    /** Whether to show transition animation */
    animate?: boolean;
}

export function SegmentedProgressBar({ total, segments, heightClass = 'h-2', className, animate = true }: SegmentedProgressBarProps) {
    const safeTotal = total > 0 ? total : 1;

    return (
        <div className={cn('flex w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800', heightClass, className)}>
            {segments.map((seg, i) => {
                const pct = Math.min(100, (seg.value / safeTotal) * 100);
                if (pct <= 0) return null;
                return (
                    <div
                        key={i}
                        className={cn('h-full', seg.colorClass, animate && 'transition-all duration-500 ease-out')}
                        style={{ width: `${pct}%` }}
                        title={seg.label ? `${seg.label}: ${seg.value}` : undefined}
                    />
                );
            })}
        </div>
    );
}

/** Pre-built workflow segment configuration (selesai / tinjauan / proses / belum / NA) */
export function workflowSegments(args: {
    selesai: number;
    tinjauan: number;
    proses: number;
    belum?: number;
    na?: number;
}): ProgressSegment[] {
    return [
        { value: args.selesai, colorClass: 'bg-emerald-500', label: 'Selesai Diterapkan' },
        { value: args.tinjauan, colorClass: 'bg-blue-400', label: 'Dalam Tinjauan' },
        { value: args.proses, colorClass: 'bg-amber-500', label: 'Dalam Proses' },
        { value: args.belum ?? 0, colorClass: 'bg-slate-400', label: 'Belum Dimulai' },
        { value: args.na ?? 0, colorClass: 'bg-slate-300 dark:bg-slate-600', label: 'Tidak Berlaku' },
    ];
}
