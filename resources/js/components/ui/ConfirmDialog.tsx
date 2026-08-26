import { cn } from '@/lib/utils';
import { AlertTriangle, Info, Loader2, Trash2 } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';

type ConfirmVariant = 'danger' | 'warning' | 'info';

interface ConfirmDialogProps {
    open: boolean;
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: ConfirmVariant;
    busy?: boolean;
    onCancel: () => void;
    onConfirm: () => void;
}

const variantStyles: Record<ConfirmVariant, { icon: typeof AlertTriangle; iconBg: string; iconText: string; confirmBg: string; confirmHover: string }> = {
    danger: {
        icon: Trash2,
        iconBg: 'bg-danger-bg',
        iconText: 'text-danger dark:text-red-400',
        confirmBg: 'bg-danger',
        confirmHover: 'hover:bg-danger/90',
    },
    warning: {
        icon: AlertTriangle,
        iconBg: 'bg-warning-bg',
        iconText: 'text-warning dark:text-amber-400',
        confirmBg: 'bg-warning',
        confirmHover: 'hover:bg-warning/90',
    },
    info: {
        icon: Info,
        iconBg: 'bg-info-bg',
        iconText: 'text-info dark:text-sky-400',
        confirmBg: 'bg-primary',
        confirmHover: 'hover:bg-primary-700',
    },
};

export function ConfirmDialog({
    open,
    title,
    description,
    confirmLabel = 'Hapus',
    cancelLabel = 'Batal',
    variant = 'danger',
    busy = false,
    onCancel,
    onConfirm,
}: ConfirmDialogProps) {
    const dialogRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;

        function handleKeyDown(e: KeyboardEvent) {
            if (e.key === 'Escape') onCancel();
        }

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [open, onCancel]);

    if (!open) return null;

    const v = variantStyles[variant];
    const Icon = v.icon;

    return createPortal(
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-navy-900/50 p-4"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) onCancel();
            }}
        >
            <div ref={dialogRef} className="w-full max-w-md overflow-hidden rounded-[14px] border border-border dark:border-slate-700 bg-white dark:bg-slate-900 shadow-lg">
                <div className="flex items-start gap-4 p-5">
                    <div className={cn('flex h-10 w-10 shrink-0 items-center justify-center rounded-full', v.iconBg, v.iconText)}>
                        <Icon className="h-5 w-5" />
                    </div>
                    <div>
                        <h2 className="text-base font-bold text-navy dark:text-white">{title}</h2>
                        <p className="mt-1 text-sm text-body dark:text-slate-300">{description}</p>
                    </div>
                </div>

                <div className="flex items-center justify-end gap-3 border-t border-border dark:border-slate-700 bg-surface/60 dark:bg-slate-900/60 px-5 py-4">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={busy}
                        className="rounded-[10px] border border-border-strong dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-2 text-xs font-semibold text-body dark:text-slate-300 transition-colors hover:bg-surface dark:hover:bg-slate-800 disabled:opacity-50 sm:text-sm"
                    >
                        {cancelLabel}
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={busy}
                        className={cn(
                            'inline-flex items-center gap-2 rounded-[10px] px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors disabled:opacity-50 sm:text-sm',
                            v.confirmBg,
                            v.confirmHover,
                        )}
                    >
                        {busy && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}