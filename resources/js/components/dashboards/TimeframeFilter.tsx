import { router } from '@inertiajs/react';
import { Calendar, Loader2 } from 'lucide-react';
import { useState } from 'react';

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

    const handleSelect = (selected: string) => {
        if (selected === currentValue) return;

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
            className={`inline-flex items-center gap-1 rounded-xl border border-slate-200/80 bg-slate-100 p-1 shadow-2xs dark:border-slate-700/80 dark:bg-slate-800/90 ${className}`}
        >
            <div className="hidden items-center gap-1.5 px-2 text-slate-400 sm:flex dark:text-slate-500">
                <Calendar className="h-3.5 w-3.5" />
                <span className="text-[11px] font-semibold tracking-wider uppercase">Periode:</span>
            </div>
            {OPTIONS.map((opt) => {
                const isActive = currentValue === opt.value;
                const isLoading = loadingValue === opt.value;

                return (
                    <button
                        key={opt.value}
                        type="button"
                        onClick={() => handleSelect(opt.value)}
                        disabled={loadingValue !== null}
                        className={`inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all duration-200 ${
                            isActive
                                ? 'text-primary dark:text-primary-300 bg-white shadow-xs ring-1 ring-black/5 dark:bg-slate-900 dark:ring-white/10'
                                : 'text-slate-600 hover:bg-white/50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700/50 dark:hover:text-white'
                        } ${loadingValue !== null && !isActive ? 'opacity-50' : ''}`}
                    >
                        {isLoading && <Loader2 className="text-primary dark:text-primary-400 h-3 w-3 animate-spin" />}
                        {opt.label}
                    </button>
                );
            })}
        </div>
    );
}
