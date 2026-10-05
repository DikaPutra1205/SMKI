import { formatDateTimeIndonesian } from '@/lib/utils';

export interface DashboardActivity {
    id: number;
    actor_name: string;
    actor_role: string;
    action: string;
    entity_name: string;
    time_ago: string;
    created_at: string | null;
}

interface RecentActivityTableProps {
    activities: DashboardActivity[];
    limit?: number;
    statusLabel?: string;
    showRole?: boolean;
    emptyMessage?: string;
}

export default function RecentActivityTable({
    activities,
    limit = 5,
    statusLabel = 'Tercatat',
    showRole = false,
    emptyMessage = 'Belum ada log aktivitas baru.',
}: RecentActivityTableProps) {
    const rows = activities.slice(0, limit);

    return (
        <div className="mt-3 flex-1 overflow-x-auto">
            <table className="w-full text-left text-xs">
                <thead className="border-b border-slate-200 bg-slate-50/90 text-[11px] font-bold tracking-wider text-slate-600 uppercase dark:border-slate-800 dark:bg-[#001f38] dark:text-slate-300">
                    <tr>
                        <th className="px-3 py-2.5">Waktu</th>
                        <th className="px-3 py-2.5">Pengguna</th>
                        <th className="px-3 py-2.5">Aktivitas</th>
                        <th className="px-3 py-2.5 text-right">Status</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/70">
                    {rows.length > 0 ? (
                        rows.map((act, idx) => (
                            <tr
                                key={act.id}
                                className={`transition-colors ${
                                    idx % 2 === 0 ? 'bg-white dark:bg-[#00223d]/70' : 'bg-slate-200/70 dark:bg-[#00172b]/80'
                                } hover:bg-primary-50/40 dark:hover:bg-[#0a3b63]/60`}
                            >
                                <td className="px-3 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">
                                    {act.created_at ? formatDateTimeIndonesian(act.created_at) : act.time_ago}
                                </td>
                                <td className="px-3 py-3 font-semibold whitespace-nowrap text-slate-900 dark:text-white">
                                    {act.actor_name}
                                    {showRole && <span className="block text-[10.5px] font-normal text-slate-400">{act.actor_role}</span>}
                                </td>
                                <td className="px-3 py-3 text-slate-700 dark:text-slate-300">
                                    <span className="font-medium">{act.action}</span>
                                    {act.entity_name && (
                                        <span className="block text-[11px] text-slate-400 dark:text-slate-500">{act.entity_name}</span>
                                    )}
                                </td>
                                <td className="py-3 pl-3 text-right whitespace-nowrap">
                                    <span className="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                                        {statusLabel}
                                    </span>
                                </td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td colSpan={4} className="py-8 text-center text-xs text-slate-500 dark:text-slate-400">
                                {emptyMessage}
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
