import { AlertTriangle, Loader2 } from 'lucide-react';

interface ConfirmDialogProps {
    open: boolean;
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    busy?: boolean;
    onCancel: () => void;
    onConfirm: () => void;
}

export function ConfirmDialog({
    open,
    title,
    description,
    confirmLabel = 'Hapus',
    cancelLabel = 'Batal',
    busy = false,
    onCancel,
    onConfirm,
}: ConfirmDialogProps) {
    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" role="dialog" aria-modal="true">
            <div className="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                <div className="flex items-start gap-4 p-5">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-500 dark:bg-red-950/50 dark:text-red-400">
                        <AlertTriangle className="h-5 w-5" />
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
                        className="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-red-700 disabled:opacity-50 sm:text-sm"
                    >
                        {busy && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
