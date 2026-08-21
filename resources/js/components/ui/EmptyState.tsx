import { Inbox } from 'lucide-react';

interface EmptyStateProps {
    message?: string;
    action?: React.ReactNode;
    className?: string;
}

export function EmptyState({ message = 'Belum ada data.', action, className }: EmptyStateProps) {
    return (
        <div className={`flex flex-col items-center justify-center gap-3 py-12 text-center ${className ?? ''}`}>
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-surface dark:bg-slate-900 text-faint dark:text-slate-500">
                <Inbox className="h-6 w-6" />
            </div>
            <p className="text-sm text-muted dark:text-slate-400">{message}</p>
            {action}
        </div>
    );
}