import { Skeleton } from '@/components/ui/Skeleton';
import AppLayout from '@/layouts/AppLayout';

export default function AssessmentSummarySkeleton() {
    return (
        <AppLayout>
            <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <Skeleton className="mb-2 h-4 w-36" />
                    <Skeleton className="mb-1 h-8 w-56" />
                    <Skeleton className="h-4 w-72" />
                </div>
                <Skeleton className="h-4 w-40" />
            </div>

            <div className="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div className="flex flex-col items-center gap-8 lg:flex-row">
                    <div className="flex items-center gap-8">
                        <Skeleton className="h-[150px] w-[150px] rounded-full" />
                        <div className="space-y-2">
                            <Skeleton className="h-5 w-40" />
                            <Skeleton className="h-3 w-48" />
                        </div>
                    </div>
                    <div className="grid flex-1 grid-cols-2 gap-3 lg:grid-cols-3">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <div key={i} className="flex items-center gap-3 rounded-xl border p-4">
                                <Skeleton className="h-10 w-10 shrink-0 rounded-lg" />
                                <div>
                                    <Skeleton className="mb-1 h-7 w-12" />
                                    <Skeleton className="h-3 w-16" />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <div className="mb-6 flex items-center gap-3">
                <Skeleton className="h-10 w-48 rounded-lg" />
                <Skeleton className="h-10 w-40 rounded-lg" />
            </div>

            {Array.from({ length: 3 }).map((_, section) => (
                <div key={section} className="mb-8">
                    <div className="mb-3 flex items-center gap-2">
                        <Skeleton className="h-6 w-6 rounded-full" />
                        <Skeleton className="h-6 w-40" />
                        <Skeleton className="h-5 w-8 rounded-full" />
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <div
                                key={i}
                                className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                            >
                                <div className="mb-2 flex items-center justify-between">
                                    <Skeleton className="h-7 w-7 rounded-full" />
                                    <Skeleton className="h-6 w-24 rounded-lg" />
                                </div>
                                <div className="mb-2 flex flex-wrap gap-1.5">
                                    <Skeleton className="h-4 w-16 rounded-md" />
                                    <Skeleton className="h-4 w-20 rounded-md" />
                                    <Skeleton className="h-3 w-14" />
                                </div>
                                <Skeleton className="mb-1 h-4 w-full" />
                                <Skeleton className="mb-3 h-3 w-3/4" />
                                <Skeleton className="h-8 w-full rounded-lg" />
                            </div>
                        ))}
                    </div>
                </div>
            ))}

            <div className="mt-10 flex flex-col items-center gap-4 rounded-2xl border border-blue-100 bg-gradient-to-r from-blue-50 to-indigo-50/50 p-8">
                <Skeleton className="h-6 w-56" />
                <Skeleton className="h-4 w-80" />
                <div className="flex items-center gap-3">
                    <Skeleton className="h-10 w-32 rounded-lg" />
                    <Skeleton className="h-10 w-36 rounded-lg" />
                </div>
            </div>
        </AppLayout>
    );
}
