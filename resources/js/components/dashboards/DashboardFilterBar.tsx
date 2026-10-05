import TimeframeFilter from '@/components/dashboards/TimeframeFilter';
import { Select } from '@/components/ui/Select';
import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';

interface WorkUnitOption {
    id: number;
    nama: string;
    kode?: string | null;
}

interface DashboardFilterBarProps {
    months?: number | string | null;
    unitId?: number | string | null;
    workUnits?: WorkUnitOption[];
    basePath: string;
    timeframeExtra?: Record<string, string | number | boolean | undefined | null>;
    selectExtra?: Record<string, string | number | boolean | undefined | null>;
    showUnitFilter?: boolean;
    actions?: ReactNode;
}

export default function DashboardFilterBar({
    months,
    unitId,
    workUnits = [],
    basePath,
    timeframeExtra = {},
    selectExtra = {},
    showUnitFilter = true,
    actions,
}: DashboardFilterBarProps) {
    return (
        <div className="flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white p-3 shadow-xs lg:flex-row lg:items-center lg:justify-between dark:border-slate-800 dark:bg-slate-900">
            <div className="flex flex-wrap items-center gap-2.5">
                <TimeframeFilter value={months || 'all'} basePath={basePath} extraParams={{ unit_id: unitId, ...timeframeExtra }} />
                {showUnitFilter && (
                    <Select
                        aria-label="Filter unit kerja"
                        value={unitId ? String(unitId) : 'all'}
                        onChange={(e) =>
                            router.get(
                                basePath,
                                {
                                    unit_id: e.target.value === 'all' ? undefined : e.target.value,
                                    months,
                                    ...selectExtra,
                                },
                                { preserveState: true, preserveScroll: true, replace: true },
                            )
                        }
                        className="min-w-[170px]"
                    >
                        <option value="all">Semua Unit Kerja</option>
                        {workUnits.map((u) => (
                            <option key={u.id} value={String(u.id)}>
                                {u.nama}
                            </option>
                        ))}
                    </Select>
                )}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2.5 lg:justify-end">{actions}</div>}
        </div>
    );
}
