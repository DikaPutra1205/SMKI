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
                    <label htmlFor={inputId} className="text-xs font-semibold text-navy">
                        {label}
                    </label>
                )}
                <input
                    ref={ref}
                    id={inputId}
                    className={cn(
                        'h-10 w-full rounded-[10px] border bg-white px-3 text-sm text-ink placeholder:text-faint',
                        'focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none',
                        error ? 'border-danger' : 'border-border-strong',
                        className,
                    )}
                    {...props}
                />
                {hint && !error && <span className="text-[11px] text-muted">{hint}</span>}
                {error && <span className="text-[11px] font-medium text-danger">{error}</span>}
            </div>
        );
    },
);

Input.displayName = 'Input';