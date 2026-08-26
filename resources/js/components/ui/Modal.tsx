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
            className="fixed inset-0 z-50 flex items-center justify-center bg-navy-900/50 p-4"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose();
            }}
        >
            <div
                className={cn(
                    'w-full overflow-hidden rounded-[14px] border border-border dark:border-slate-700 bg-white dark:bg-slate-900 shadow-lg',
                    maxWidthClasses[maxWidth],
                    className,
                )}
            >
                <div className="flex items-start justify-between gap-4 border-b border-border dark:border-slate-700 px-5 py-4">
                    <div>
                        {title && <h3 className="text-base font-bold text-navy dark:text-white">{title}</h3>}
                        {description && <p className="mt-0.5 text-xs text-muted dark:text-slate-400">{description}</p>}
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-muted dark:text-slate-400 transition-colors hover:bg-surface dark:hover:bg-slate-800 hover:text-navy dark:hover:text-white"
                        aria-label="Tutup"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>

                <div className="px-5 py-4">{children}</div>

                {footer && <div className="flex items-center justify-end gap-3 border-t border-border dark:border-slate-700 bg-surface/60 dark:bg-slate-900/60 px-5 py-4">{footer}</div>}
            </div>
        </div>,
        document.body,
    );
}