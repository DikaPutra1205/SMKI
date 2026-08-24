export function ActivitySkeleton({ count = 5 }: { count?: number }) {
    return (
        <div className="animate-pulse space-y-4">
            {Array.from({ length: count }).map((_, i) => (
                <div key={i} className="flex items-start gap-3">
                    <div className="mt-0.5 h-8 w-8 shrink-0 rounded-full bg-slate-200 dark:bg-slate-700" />
                    <div className="flex-1 space-y-1.5">
                        <div className="flex items-center justify-between">
                            <div className="h-3.5 w-32 rounded bg-slate-200 dark:bg-slate-700" />
                            <div className="h-3 w-16 rounded bg-slate-200 dark:bg-slate-700" />
                        </div>
                        <div className="h-3 w-48 rounded bg-slate-200 dark:bg-slate-700" />
                    </div>
                </div>
            ))}
        </div>
    );
}
