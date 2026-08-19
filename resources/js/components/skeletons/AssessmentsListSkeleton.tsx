import { Skeleton } from '@/components/ui/Skeleton';
import AppLayout from '@/layouts/AppLayout';

export default function AssessmentsListSkeleton() {
    return (
        <AppLayout>
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <Skeleton className="mb-2 h-8 w-48" />
                    <Skeleton className="h-4 w-64" />
                </div>
                <Skeleton className="h-10 w-44 rounded-lg" />
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {Array.from({ length: 6 }).map((_, i) => (
                    <div key={i} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <div className="mb-3 flex items-start justify-between">
                            <div className="min-w-0 flex-1">
                                <Skeleton className="mb-1.5 h-4 w-36" />
                                <Skeleton className="h-3 w-24" />
                            </div>
                            <Skeleton className="h-4 w-4 rounded" />
                        </div>
                        <div className="mb-3">
                            <div className="mb-1 flex items-baseline justify-between">
                                <Skeleton className="h-3 w-20" />
                                <Skeleton className="h-3 w-8" />
                            </div>
                            <Skeleton className="h-1.5 w-full rounded-full" />
                        </div>
                        <div className="flex items-center justify-between">
                            <Skeleton className="h-3 w-28" />
                            <Skeleton className="h-3 w-16" />
                        </div>
                    </div>
                ))}
            </div>
        </AppLayout>
    );
}
