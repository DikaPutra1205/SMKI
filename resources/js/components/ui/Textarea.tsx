import { forwardRef } from 'react';
import { cn } from '@/lib/utils';

type TextareaProps = React.TextareaHTMLAttributes<HTMLTextAreaElement> & {
    label?: string;
    error?: string;
    hint?: string;
};

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
    ({ className, label, error, hint, id, ...props }, ref) => {
        const textareaId = id || label ? `textarea-${label?.toLowerCase().replace(/\s+/g, '-')}` : undefined;

        return (
            <div className="flex flex-col gap-1.5">
                {label && (
                    <label htmlFor={textareaId} className="text-xs font-semibold text-navy">
                        {label}
                    </label>
                )}
                <textarea
                    ref={ref}
                    id={textareaId}
                    className={cn(
                        'w-full resize-none rounded-[10px] border bg-white px-3 py-2 text-sm text-ink placeholder:text-faint',
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

Textarea.displayName = 'Textarea';