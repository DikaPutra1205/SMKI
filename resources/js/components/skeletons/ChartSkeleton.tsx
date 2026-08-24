export function ChartSkeleton({ height = 'h-52' }: { height?: string }) {
    return (
        <div className={`w-full ${height} animate-pulse rounded-xl bg-slate-100 p-4 dark:bg-slate-800/60`}>
            <div className="flex h-full flex-col justify-between">
                <div className="flex items-center justify-between">
                    <div className="h-4 w-32 rounded bg-slate-200 dark:bg-slate-700" />
                    <div className="h-4 w-16 rounded bg-slate-200 dark:bg-slate-700" />
                </div>
                <div className="flex items-end justify-between gap-2 px-2">
                    <div className="h-16 w-8 rounded-t bg-slate-200 dark:bg-slate-700" />
                    <div className="h-28 w-8 rounded-t bg-slate-200 dark:bg-slate-700" />
                    <div className="h-20 w-8 rounded-t bg-slate-200 dark:bg-slate-700" />
                    <div className="h-36 w-8 rounded-t bg-slate-200 dark:bg-slate-700" />
                    <div className="h-24 w-8 rounded-t bg-slate-200 dark:bg-slate-700" />
                    <div className="h-40 w-8 rounded-t bg-slate-200 dark:bg-slate-700" />
                </div>
                <div className="flex justify-between">
                    <div className="h-3 w-8 rounded bg-slate-200 dark:bg-slate-700" />
                    <div className="h-3 w-8 rounded bg-slate-200 dark:bg-slate-700" />
                    <div className="h-3 w-8 rounded bg-slate-200 dark:bg-slate-700" />
                    <div className="h-3 w-8 rounded bg-slate-200 dark:bg-slate-700" />
                    <div className="h-3 w-8 rounded bg-slate-200 dark:bg-slate-700" />
                    <div className="h-3 w-8 rounded bg-slate-200 dark:bg-slate-700" />
                </div>
            </div>
        </div>
    );
}

export function UnitComparisonSkeleton() {
    return (
        <div className="animate-pulse space-y-3">
            {[1, 2, 3, 4].map((i) => (
                <div
                    key={i}
                    className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50/50 p-3 dark:border-slate-800 dark:bg-slate-800/40"
                >
                    <div className="space-y-2">
                        <div className="h-4 w-44 rounded bg-slate-200 dark:bg-slate-700" />
                        <div className="h-3 w-28 rounded bg-slate-200 dark:bg-slate-700" />
                    </div>
                    <div className="h-6 w-16 rounded-full bg-slate-200 dark:bg-slate-700" />
                </div>
            ))}
        </div>
    );
}
