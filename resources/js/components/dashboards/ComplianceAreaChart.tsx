interface TrendChartProps {
    labels: string[];
    values: number[];
}

const FALLBACK_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul'];

export default function ComplianceAreaChart({ labels, values }: TrendChartProps) {
    const effectiveLabels = labels.length > 0 ? labels : FALLBACK_LABELS;
    const effectiveValues = values.length > 0 ? values : [];

    return (
        <svg className="h-[230px] w-full" viewBox="0 0 640 240" preserveAspectRatio="none" role="img" aria-label="Tren kepatuhan bulanan">
            <defs>
                <linearGradient id="areaFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="#196ECD" stopOpacity="0.22" />
                    <stop offset="100%" stopColor="#196ECD" stopOpacity="0" />
                </linearGradient>
            </defs>
            <g stroke="#E3E9F0" className="dark:stroke-slate-800" strokeWidth="1">
                <line x1={40} y1={20} x2={620} y2={20} stroke="#E3E9F0" className="dark:stroke-slate-800" strokeWidth="1" />
                <line x1={40} y1={75} x2={620} y2={75} stroke="#E3E9F0" className="dark:stroke-slate-800" strokeWidth="1" />
                <line x1={40} y1={130} x2={620} y2={130} stroke="#E3E9F0" className="dark:stroke-slate-800" strokeWidth="1" />
                <line x1={40} y1={185} x2={620} y2={185} stroke="#E3E9F0" className="dark:stroke-slate-800" strokeWidth="1" />
                <line x1={40} y1={20} x2={40} y2={210} stroke="#E3E9F0" className="dark:stroke-slate-800" strokeWidth="1" />
                <line x1={620} y1={20} x2={620} y2={210} stroke="#E3E9F0" className="dark:stroke-slate-800" strokeWidth="1" />
            </g>
            <g fill="#fff" className="dark:fill-slate-900">
                {effectiveValues.length > 0 &&
                    effectiveValues.map((v, i) => (
                        <g key={i}>
                            <circle
                                cx={40 + (600 * i) / (effectiveValues.length - 1)}
                                cy={240 - (210 * v) / 100}
                                r="4"
                                fill="#fff"
                                className="dark:fill-slate-900"
                            />
                        </g>
                    ))}
            </g>
            <g fill="#8798AB" className="dark:fill-slate-500" fontFamily="Inter, sans-serif" fontSize="10.5" textAnchor="middle">
                {effectiveLabels.length > 0 &&
                    effectiveLabels.map((label, i) => (
                        <text key={i} x={40 + (600 * i) / (effectiveLabels.length - 1)} y={240 + 18}>
                            {label}
                        </text>
                    ))}
            </g>
            <g fill="#8798AB" className="dark:fill-slate-500" fontFamily="Inter, sans-serif" fontSize="10" textAnchor="end">
                {[100, 80, 60, 40, 20, 0].map((p) => (
                    <text key={p} x={20} y={20 + (210 * p) / 100}>
                        {p}%
                    </text>
                ))}
            </g>
        </svg>
    );
}
