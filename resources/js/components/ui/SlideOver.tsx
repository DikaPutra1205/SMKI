import { cn } from '@/lib/utils';
import { X } from 'lucide-react';
import React, { useEffect } from 'react';

export interface SlideOverProps {
    open: boolean;
    title?: React.ReactNode;
    description?: React.ReactNode;
    subtitle?: React.ReactNode;
    onClose: () => void;
    children?: React.ReactNode;
    footer?: React.ReactNode;
    maxWidth?: 'md' | 'lg' | 'xl' | '2xl';
    width?: string;
    className?: string;
}

const maxWidthClasses: Record<NonNullable<SlideOverProps['maxWidth']>, string> = {
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
};

export function SlideOver({
    open,
    title,
    description,
    subtitle,
    onClose,
    children,
    footer,
    maxWidth = 'lg',
    width,
    className,
}: SlideOverProps) {
    useEffect(() => {
        if (!open) return;

        function handleKeyDown(e: KeyboardEvent) {
            if (e.key === 'Escape') onClose();
        }

        document.addEventListener('keydown', handleKeyDown);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-hidden" role="dialog" aria-modal="true">
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-slate-950/50 backdrop-blur-[2px] transition-opacity animate-in fade-in duration-200"
                onClick={onClose}
                aria-hidden="true"
            />

            <div className="fixed inset-y-0 right-0 flex max-w-full pl-10 pointer-events-none">
                {/* Panel */}
                <div
                    className={cn(
                        'pointer-events-auto w-screen flex flex-col bg-white dark:bg-slate-900 border-l border-slate-200/90 dark:border-slate-800 shadow-2xl animate-in slide-in-from-right duration-300',
                        maxWidthClasses[maxWidth],
                        width,
                        className,
                    )}
                >
                    {/* Header */}
                    <div className="flex items-start justify-between gap-4 border-b border-slate-100 dark:border-slate-800 px-6 py-5 bg-white dark:bg-slate-900">
                        <div className="space-y-1">
                            {title && (
                                <h3 className="text-base font-bold text-slate-900 dark:text-white leading-6">
                                    {title}
                                </h3>
                            )}
                            {description && (
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {description}
                                </p>
                            )}
                            {subtitle && (
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {subtitle}
                                </p>
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 dark:hover:text-white transition-colors"
                            aria-label="Tutup panel"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    {/* Scrollable Content Body */}
                    <div className="flex-1 overflow-y-auto px-6 py-5 space-y-6">
                        {children}
                    </div>

                    {/* Footer */}
                    {footer && (
                        <div className="flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/90 px-6 py-4">
                            {footer}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
