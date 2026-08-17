import { cn } from '@/lib/utils';
import { AlertTriangle, Info, Loader2, Trash2 } from 'lucide-react';
import { useEffect, useRef } from 'react';

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
        iconBg: 'bg-red-50 dark:bg-red-950/50',
        iconText: 'text-red-500 dark:text-red-400',
        confirmBg: 'bg-red-600',
        confirmHover: 'hover:bg-red-700',
    },
    warning: {
        icon: AlertTriangle,
        iconBg: 'bg-amber-50 dark:bg-amber-950/50',
        iconText: 'text-amber-500 dark:text-amber-400',
        confirmBg: 'bg-amber-600',
        confirmHover: 'hover:bg-amber-700',
    },
    info: {
        icon: Info,
        iconBg: 'bg-blue-50 dark:bg-blue-950/50',
        iconText: 'text-blue-500 dark:text-blue-400',
        confirmBg: 'bg-blue-600',
        confirmHover: 'hover:bg-blue-700',
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

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) onCancel();
            }}
        >
            <div ref={dialogRef} className="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                <div className="flex items-start gap-4 p-5">
                    <div className={cn('flex h-10 w-10 shrink-0 items-center justify-center rounded-full', v.iconBg, v.iconText)}>
                        <Icon className="h-5 w-5" />
                    </div>
                    <div>
                        <h2 className="text-base font-bold text-slate-900 dark:text-white">{title}</h2>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{description}</p>
                    </div>
                </div>

                <div className="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-5 py-4 dark:border-slate-800 dark:bg-slate-900/50">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={busy}
                        className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50 disabled:opacity-50 sm:text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/60"
                    >
                        {cancelLabel}
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={busy}
                        className={cn(
                            'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors disabled:opacity-50 sm:text-sm',
                            v.confirmBg,
                            v.confirmHover,
                        )}
                    >
                        {busy && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
