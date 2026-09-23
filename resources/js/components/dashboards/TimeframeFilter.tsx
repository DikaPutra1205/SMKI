import { router } from '@inertiajs/react';
import { Calendar, Loader2 } from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';

interface TimeframeFilterProps {
    value?: string | number | null;
    onChange?: (timeframe: string) => void;
    basePath?: string;
    extraParams?: Record<string, string | number | boolean | undefined | null>;
    className?: string;
    only?: string[];
}

const OPTIONS = [
    { label: '3 Bulan', value: '3' },
    { label: '6 Bulan', value: '6' },
    { label: '12 Bulan', value: '12' },
    { label: 'Semua', value: 'all' },
];

export default function TimeframeFilter({
    value = 'all',
    onChange,
    basePath,
    extraParams = {},
    className = '',
    only = ['summary', 'trends', 'unit_comparisons', 'recent_activities', 'recent_sessions', 'filters'],
}: TimeframeFilterProps) {
    const [loadingValue, setLoadingValue] = useState<string | null>(null);
    const currentValue = String(value || 'all');

    const containerRef = useRef<HTMLDivElement>(null);
    const optionRefs = useRef<Record<string, HTMLButtonElement | null>>({});

    const [indicator, setIndicator] = useState({ left: 0, width: 0, measured: false });

    const measure = (target: string) => {
        const btn = optionRefs.current[target];
        const container = containerRef.current;
        if (!btn || !container) return;

        const containerRect = container.getBoundingClientRect();
        const btnRect = btn.getBoundingClientRect();

        const padding = 4; // matches the container's p-1
        const left = btnRect.left - containerRect.left - container.clientLeft;
        const width = btnRect.width;

        const clampedWidth = Math.min(width, containerRect.width - padding * 2);
        const clampedLeft = Math.max(padding, Math.min(left, containerRect.width - padding - clampedWidth));

        setIndicator((prev) =>
            prev.measured && prev.left === clampedLeft && prev.width === clampedWidth
                ? prev
                : { left: clampedLeft, width: clampedWidth, measured: true },
        );
    };

    useLayoutEffect(() => {
        measure(currentValue);
        const onResize = () => measure(currentValue);
        window.addEventListener('resize', onResize);
        return () => window.removeEventListener('resize', onResize);
    }, [currentValue, loadingValue]);

    const handleSelect = (selected: string) => {
        if (selected === currentValue) return;

        measure(selected);

        if (onChange) {
            onChange(selected);
            return;
        }

        setLoadingValue(selected);
        const path = basePath || (typeof window !== 'undefined' ? window.location.pathname : '/dashboard');
        const params: Record<string, string | number | boolean | undefined | null> = { ...extraParams };

        if (selected !== 'all') {
            params.months = selected;
        } else {
            delete params.months;
        }

        router.get(path, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: only,
            onFinish: () => setLoadingValue(null),
        });
    };

    return (
        <div
            ref={containerRef}
            className={`relative inline-flex h-10 items-stretch gap-1 overflow-hidden rounded-lg border border-slate-200/80 bg-slate-100 p-1 shadow-2xs dark:border-slate-700/80 dark:bg-slate-800/90 ${className}`}
        >
            <div className="hidden items-center gap-1.5 px-2 text-slate-400 sm:flex dark:text-slate-500">
                <Calendar className="h-3.5 w-3.5" />
                <span className="text-[11px] font-semibold tracking-wider uppercase">Periode:</span>
            </div>

            <div
                aria-hidden
                className={`absolute top-1 bottom-1 z-10 rounded-md bg-white shadow-xs ring-1 ring-black/5 dark:bg-slate-900 dark:ring-white/10 ${
                    indicator.measured ? 'transition-[left,width] duration-300 ease-in-out' : ''
                }`}
                style={{ left: indicator.left, width: indicator.width }}
            />

            {OPTIONS.map((opt) => {
                const isActive = currentValue === opt.value;
                const isLoading = loadingValue === opt.value;

                return (
                    <button
                        key={opt.value}
                        ref={(el) => {
                            optionRefs.current[opt.value] = el;
                        }}
                        type="button"
                        onClick={() => handleSelect(opt.value)}
                        disabled={loadingValue !== null}
                        className={`relative z-20 inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-3 text-xs font-bold transition-colors duration-200 ${
                            isActive
                                ? 'text-primary dark:text-primary-300'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                        } ${loadingValue !== null && !isActive ? 'opacity-50' : ''}`}
                    >
                        <span aria-hidden className="inline-flex h-3 w-3 shrink-0 justify-center">
                            {isLoading ? (
                                <Loader2 className="text-primary dark:text-primary-400 h-3 w-3 animate-spin" />
                            ) : (
                                <span
                                    className={`mt-0.5 h-1.5 w-1.5 rounded-full transition-colors duration-200 ${
                                        isActive ? 'bg-primary dark:bg-primary-400' : 'bg-slate-300 dark:bg-slate-600'
                                    }`}
                                />
                            )}
                        </span>
                        {opt.label}
                    </button>
                );
            })}
        </div>
    );
}
