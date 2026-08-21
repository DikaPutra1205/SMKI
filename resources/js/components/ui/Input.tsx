import { forwardRef } from 'react';
import { cn } from '@/lib/utils';

type InputProps = React.InputHTMLAttributes<HTMLInputElement> & {
    label?: string;
    error?: string;
    hint?: string;
};

export const Input = forwardRef<HTMLInputElement, InputProps>(
    ({ className, label, error, hint, id, ...props }, ref) => {
        const inputId = id || label ? `input-${label?.toLowerCase().replace(/\s+/g, '-')}` : undefined;

        return (
            <div className="flex flex-col gap-1.5">
                {label && (
                    <label htmlFor={inputId} className="text-xs font-semibold text-navy dark:text-white">
                        {label}
                    </label>
                )}
                <input
                    ref={ref}
                    id={inputId}
                    className={cn(
                        'h-10 w-full rounded-[10px] border bg-white dark:bg-slate-900 px-3 text-sm text-ink dark:text-white placeholder:text-faint dark:placeholder:text-slate-500',
                        'focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none',
                        error ? 'border-danger dark:border-red-700' : 'border-border-strong dark:border-slate-600',
                        className,
                    )}
                    {...props}
                />
                {hint && !error && <span className="text-[11px] text-muted dark:text-slate-400">{hint}</span>}
                {error && <span className="text-[11px] font-medium text-danger dark:text-red-400">{error}</span>}
            </div>
        );
    },
);

Input.displayName = 'Input';