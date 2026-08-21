import { AlertCircle, CheckCircle2, X } from 'lucide-react';
import { cn } from '@/lib/utils';

type ToastTone = 'success' | 'error';

interface ToastProps {
    visible: boolean;
    tone?: ToastTone;
    message?: string;
    onDismiss?: () => void;
}

export function Toast({ visible, tone = 'success', message = '', onDismiss }: ToastProps) {
    if (!visible || !message) return null;

    const Icon = tone === 'success' ? CheckCircle2 : AlertCircle;

    return (
        <div
            className={cn(
                'fixed right-4 bottom-4 z-50 flex items-center gap-3 rounded-[14px] border px-5 py-3.5 shadow-lg',
                tone === 'success' ? 'border-success-border dark:border-emerald-800 bg-success-bg text-success dark:text-emerald-400' : 'border-danger-border dark:border-red-800 bg-danger-bg text-danger dark:text-red-400',
            )}
            role="status"
        >
            <Icon className="h-5 w-5 shrink-0" />
            <span className="text-sm font-medium">{message}</span>
            {onDismiss && (
                <button type="button" onClick={onDismiss} aria-label="Tutup">
                    <X className="h-4 w-4 opacity-60" />
                </button>
            )}
        </div>
    );
}