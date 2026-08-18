import { ChevronDown } from 'lucide-react';
import { forwardRef } from 'react';
import { cn } from '@/lib/utils';

type SelectProps = React.SelectHTMLAttributes<HTMLSelectElement> & {
    label?: string;
    error?: string;
};

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
    ({ className, label, error, id, children, ...props }, ref) => {
        const selectId = id || label ? `select-${label?.toLowerCase().replace(/\s+/g, '-')}` : undefined;

        return (
            <div className="flex flex-col gap-1.5">
                {label && (
                    <label htmlFor={selectId} className="text-xs font-semibold text-navy">
                        {label}
                    </label>
                )}
                <div className="relative">
                    <select
                        ref={ref}
                        id={selectId}
                        className={cn(
                            'h-10 w-full appearance-none rounded-[10px] border bg-white px-3 pr-9 text-sm text-ink',
                            'focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none',
                            error ? 'border-danger' : 'border-border-strong',
                            className,
                        )}
                        {...props}
                    >
                        {children}
                    </select>
                    <ChevronDown className="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-muted" />
                </div>
                {error && <span className="text-[11px] font-medium text-danger">{error}</span>}
            </div>
        );
    },
);

Select.displayName = 'Select';