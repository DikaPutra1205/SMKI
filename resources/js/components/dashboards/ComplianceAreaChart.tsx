import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export interface TrendPoint {
    period: string;
    label: string;
    iso27001_rate: number;
    iso27701_rate: number;
    overall_rate: number;
}

interface ComplianceAreaChartProps {
    trends: TrendPoint[];
}

// Digitalent palette — hanya gunakan dark & light blue
const COLOR_OVERALL = '#0284c7'; // mid/sky blue untuk garis rata-rata
const COLOR_27001 = '#196ecd'; // light blue (ISO 27001)
const COLOR_27701 = '#0f4c81'; // dark blue (ISO 27701)

interface CustomTooltipProps {
    active?: boolean;
    payload?: Array<{
        dataKey?: string | number;
        color?: string;
        name?: string;
        value?: number | string;
        payload?: { fullMonth?: string; [key: string]: unknown };
    }>;
    label?: string;
}

function CustomTooltip({ active, payload, label }: CustomTooltipProps) {
    if (!active || !payload || payload.length === 0) return null;
    const fullMonth = payload[0]?.payload?.fullMonth ?? label;
    return (
        <div className="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 shadow-lg dark:border-white/10 dark:bg-[#002745]">
            <p className="dark:text-primary-300 mb-1.5 text-[11px] font-bold text-slate-500">{fullMonth}</p>
            {payload.map((entry) => (
                <div key={entry.dataKey} className="flex items-center gap-2 text-xs">
                    <span className="h-2 w-2 rounded-full" style={{ background: entry.color }} />
                    <span className="text-slate-600 dark:text-slate-300">{entry.name}:</span>
                    <span className="font-bold text-slate-900 dark:text-white">{Number(entry.value).toFixed(1)}%</span>
                </div>
            ))}
        </div>
    );
}

function formatLabel(label: string) {
    return label.split(' ')[0].substring(0, 3);
}

interface MonthTickProps {
    x?: number;
    y?: number;
    payload?: { value?: string };
}

function MonthTick({ x = 0, y = 0, payload }: MonthTickProps) {
    return (
        <g transform={`translate(${x},${y})`}>
            <circle cx={0} cy={0} r={2} fill="#94a3b8" />
            <text x={0} y={16} textAnchor="middle" fill="#94a3b8" fontSize={10.5} fontWeight={500}>
                {payload?.value ?? ''}
            </text>
        </g>
    );
}

export default function ComplianceAreaChart({ trends }: ComplianceAreaChartProps) {
    const data = trends.map((t) => ({
        label: t.label,
        fullMonth: t.label.split(' ')[0],
        shortLabel: formatLabel(t.label),
        overall: Number(t.overall_rate.toFixed(1)),
        iso27001: Number(t.iso27001_rate.toFixed(1)),
        iso27701: Number(t.iso27701_rate.toFixed(1)),
    }));

    return (
        <ResponsiveContainer width="100%" height={200}>
            <AreaChart data={data} margin={{ top: 8, right: 8, left: -20, bottom: 0 }}>
                <defs>
                    <linearGradient id="gradOverall" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor={COLOR_OVERALL} stopOpacity={0.25} />
                        <stop offset="95%" stopColor={COLOR_OVERALL} stopOpacity={0} />
                    </linearGradient>
                    <linearGradient id="grad27001" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor={COLOR_27001} stopOpacity={0.15} />
                        <stop offset="95%" stopColor={COLOR_27001} stopOpacity={0} />
                    </linearGradient>
                    <linearGradient id="grad27701" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor={COLOR_27701} stopOpacity={0.15} />
                        <stop offset="95%" stopColor={COLOR_27701} stopOpacity={0} />
                    </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" className="dark:[&_line]:stroke-slate-800" />
                <XAxis
                    dataKey="shortLabel"
                    tick={<MonthTick />}
                    tickLine={false}
                    height={32}
                    axisLine={true}
                    className="[&_line]:stroke-slate-200 dark:[&_line]:stroke-slate-700"
                />
                <YAxis
                    domain={[0, 100]}
                    tick={{ fontSize: 10, fontWeight: 500, fill: '#94a3b8' }}
                    axisLine={false}
                    tickLine={false}
                    tickFormatter={(v) => `${v}%`}
                    ticks={[0, 25, 50, 75, 100]}
                />
                <Tooltip content={<CustomTooltip />} cursor={{ stroke: COLOR_OVERALL, strokeWidth: 1, strokeDasharray: '4 4' }} />
                <Area
                    type="monotone"
                    dataKey="iso27001"
                    name="ISO 27001"
                    stroke={COLOR_27001}
                    strokeWidth={1.5}
                    strokeDasharray="5 3"
                    fill="url(#grad27001)"
                    dot={{ r: 2.5, fill: COLOR_27001, strokeWidth: 0 }}
                    activeDot={{ r: 4, fill: COLOR_27001, strokeWidth: 0 }}
                    animationDuration={800}
                />
                <Area
                    type="monotone"
                    dataKey="iso27701"
                    name="ISO 27701"
                    stroke={COLOR_27701}
                    strokeWidth={1.5}
                    strokeDasharray="5 3"
                    fill="url(#grad27701)"
                    dot={{ r: 2.5, fill: COLOR_27701, strokeWidth: 0 }}
                    activeDot={{ r: 4, fill: COLOR_27701, strokeWidth: 0 }}
                    animationDuration={800}
                />
                <Area
                    type="monotone"
                    dataKey="overall"
                    name="Rata-rata"
                    stroke={COLOR_OVERALL}
                    strokeWidth={2.5}
                    fill="url(#gradOverall)"
                    dot={{ r: 3, fill: '#fff', stroke: COLOR_OVERALL, strokeWidth: 2 }}
                    activeDot={{ r: 5, fill: '#fff', stroke: COLOR_OVERALL, strokeWidth: 2.5 }}
                    animationDuration={1000}
                />
            </AreaChart>
        </ResponsiveContainer>
    );
}
