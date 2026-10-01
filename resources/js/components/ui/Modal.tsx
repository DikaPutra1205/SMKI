import { X } from 'lucide-react';
import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

interface ModalProps {
    open: boolean;
    title?: React.ReactNode;
    description?: React.ReactNode;
    onClose: () => void;
    children?: React.ReactNode;
    footer?: React.ReactNode;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl';
    className?: string;
}

const maxWidthClasses: Record<NonNullable<ModalProps['maxWidth']>, string> = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-2xl',
};

export function Modal({ open, title, description, onClose, children, footer, maxWidth = 'lg', className }: ModalProps) {
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

    return createPortal(
        <div
            className="fixed inset-0 z-50 flex items-center justify-center overflow-hidden bg-navy-900/50 p-4"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose();
            }}
        >
            <div
                className={cn(
                    'flex max-h-[calc(100dvh-2rem)] w-full flex-col overflow-hidden rounded-[14px] border border-border dark:border-slate-700 bg-white dark:bg-slate-900 shadow-lg',
                    maxWidthClasses[maxWidth],
                    className,
                )}
            >
                <div className="flex shrink-0 items-start justify-between gap-4 border-b border-border dark:border-slate-700 px-5 py-4">
                    <div className="min-w-0">
                        {title && <h3 className="text-base font-bold break-words text-navy dark:text-white">{title}</h3>}
                        {description && <p className="mt-0.5 text-xs text-muted dark:text-slate-400">{description}</p>}
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="shrink-0 rounded-lg p-1.5 text-muted transition-colors hover:bg-surface hover:text-navy dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                        aria-label="Tutup"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>

                <div className="min-h-0 overflow-x-hidden overflow-y-auto overscroll-contain px-5 py-4">{children}</div>

                {footer && (
                    <div className="flex shrink-0 flex-wrap items-center justify-end gap-3 border-t border-border bg-surface/60 px-5 py-4 dark:border-slate-700 dark:bg-slate-900/60">{footer}</div>
                )}
            </div>
        </div>,
        document.body,
    );
}