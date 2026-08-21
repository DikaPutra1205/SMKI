import { Skeleton } from '@/components/ui/Skeleton';
import AppLayout from '@/layouts/AppLayout';

export default function ChecklistDetailSkeleton() {
    return (
        <AppLayout>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <Skeleton className="mb-2 h-8 w-56" />
                    <Skeleton className="h-4 w-72" />
                </div>
                <Skeleton className="h-10 w-28 rounded-lg" />
            </div>

            <div className="sticky top-[76px] z-30 mb-6 rounded-2xl border border-blue-100 bg-gradient-to-r from-blue-50 to-blue-100/50 p-5 shadow-sm backdrop-blur-sm">
                <div className="mb-2 flex items-center justify-between">
                    <Skeleton className="h-3 w-32" />
                    <div className="flex items-baseline gap-1.5">
                        <Skeleton className="h-8 w-12" />
                        <Skeleton className="h-3 w-24" />
                    </div>
                </div>
                <Skeleton className="h-2 w-full rounded-full" />
            </div>

            <div className="mb-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4 shadow-sm">
                <div className="flex items-center justify-between">
                    <Skeleton className="h-10 w-28 rounded-lg" />
                    <div className="flex flex-col items-center gap-1">
                        <Skeleton className="h-3 w-36" />
                        <Skeleton className="h-5 w-48" />
                        <Skeleton className="h-3 w-20" />
                    </div>
                    <Skeleton className="h-10 w-28 rounded-lg" />
                </div>
            </div>

            <div className="mb-3 flex items-center gap-3 rounded-xl border border-blue-100 bg-white dark:bg-slate-900 px-4 py-3 shadow-sm">
                <Skeleton className="h-8 w-24 rounded-lg" />
                <Skeleton className="h-4 w-1" />
                <Skeleton className="h-5 w-32" />
                <Skeleton className="ml-auto h-6 w-20 rounded-full" />
            </div>

            <div className="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4 shadow-sm">
                {Array.from({ length: 4 }).map((_, i) => (
                    <div key={i} className={`py-5 ${i < 3 ? 'border-b border-slate-100 dark:border-slate-800' : ''}`}>
                        <div className="mb-2 flex items-start justify-between">
                            <div className="flex items-center gap-2">
                                <Skeleton className="h-5 w-16 rounded-md" />
                                <Skeleton className="h-4 w-48" />
                            </div>
                        </div>
                        <Skeleton className="mb-3 h-3 w-full max-w-md" />
                        <div className="mb-3 flex flex-wrap gap-2">
                            <Skeleton className="h-9 w-24 rounded-lg" />
                            <Skeleton className="h-9 w-32 rounded-lg" />
                            <Skeleton className="h-9 w-28 rounded-lg" />
                        </div>
                        <div className="flex gap-3">
                            <Skeleton className="h-9 flex-1 rounded-lg" />
                            <Skeleton className="h-9 w-28 rounded-lg" />
                        </div>
                    </div>
                ))}
            </div>
        </AppLayout>
    );
}
