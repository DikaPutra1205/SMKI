import { useId } from 'react';

interface TrendChartProps {
    labels: string[];
    values: number[];
}

const W = 720;
const H = 260;
const PAD_L = 36;
const PAD_R = 24;
const PAD_T = 14;
const PAD_B = 32;
const INNER_W = W - PAD_L - PAD_R;
const INNER_H = H - PAD_T - PAD_B;

type Pt = { x: number; y: number };

/**
 * Monotone cubic (Fritsch-Carlson) interpolation — smooth curve that never
 * overshoots the actual data points, so the trend shape stays truthful.
 */
function monotonePath(points: Pt[]): string {
    const n = points.length;
    if (n === 0) return '';
    if (n === 1) return `M ${points[0].x} ${points[0].y}`;
    if (n === 2) return `M ${points[0].x} ${points[0].y} L ${points[1].x} ${points[1].y}`;

    const dx: number[] = [];
    const slope: number[] = [];
    for (let i = 0; i < n - 1; i++) {
        dx.push(points[i + 1].x - points[i].x);
        slope.push((points[i + 1].y - points[i].y) / dx[i]);
    }

    const tan: number[] = [slope[0]];
    for (let i = 1; i < n - 1; i++) {
        if (slope[i - 1] * slope[i] <= 0) {
            tan.push(0);
        } else {
            let t = (slope[i - 1] + slope[i]) / 2;
            const lim = 3 * Math.min(Math.abs(slope[i - 1]), Math.abs(slope[i]));
            if (Math.abs(t) > lim) t = Math.sign(t) * lim;
            tan.push(t);
        }
    }
    tan.push(slope[n - 2]);

    let d = `M ${points[0].x} ${points[0].y}`;
    for (let i = 0; i < n - 1; i++) {
        const h = dx[i];
        d +=
            ` C ${points[i].x + h / 3} ${points[i].y + (tan[i] * h) / 3}` +
            `, ${points[i + 1].x - h / 3} ${points[i + 1].y - (tan[i + 1] * h) / 3}` +
            `, ${points[i + 1].x} ${points[i + 1].y}`;
    }
    return d;
}

export default function TrendAreaChart({ labels, values }: TrendChartProps) {
    const gradId = useId();
    const count = values.length;
    if (count === 0) return null;

    const chartX = (i: number) => (count <= 1 ? PAD_L + INNER_W / 2 : PAD_L + (INNER_W * i) / (count - 1));
    const chartY = (v: number) => PAD_T + INNER_H - (INNER_H * Math.min(100, Math.max(0, v))) / 100;
    const floorY = PAD_T + INNER_H;

    const points = values.map((v, i) => ({ x: chartX(i), y: chartY(v) }));
    const lineD = monotonePath(points);
    const areaD =
        count > 1
            ? `${lineD} L ${chartX(count - 1)} ${floorY} L ${chartX(0)} ${floorY} Z`
            : '';

    const labelAnchor = (i: number): 'start' | 'middle' | 'end' =>
        count <= 1 ? 'middle' : i === 0 ? 'start' : i === count - 1 ? 'end' : 'middle';

    return (
        <svg
            className="block aspect-[720/260] w-full"
            viewBox={`0 0 ${W} ${H}`}
            role="img"
            aria-label="Tren kepatuhan bulanan"
        >
            <defs>
                <linearGradient id={gradId} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="#2563eb" stopOpacity="0.28" />
                    <stop offset="50%" stopColor="#2563eb" stopOpacity="0.10" />
                    <stop offset="100%" stopColor="#2563eb" stopOpacity="0" />
                </linearGradient>
            </defs>

            {/* Gridlines — one per y tick so lines and labels always align */}
            <g stroke="#f1f5f9" className="dark:stroke-slate-800" strokeWidth="1">
                {[100, 75, 50, 25, 0].map((p) => (
                    <line key={p} x1={PAD_L} y1={chartY(p)} x2={W - PAD_R} y2={chartY(p)} />
                ))}
            </g>

            {areaD && <path d={areaD} fill={`url(#${gradId})`} />}

            {lineD && (
                <path
                    d={lineD}
                    fill="none"
                    stroke="#2563eb"
                    className="dark:stroke-blue-400"
                    strokeWidth="2.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            )}

            {/* Point markers — uniform circles now that scaling is proportional */}
            {points.map((p, i) => (
                <circle key={i} cx={p.x} cy={p.y} r="3.5" className="fill-white stroke-blue-600 dark:fill-slate-900 dark:stroke-blue-400" strokeWidth="2" />
            ))}

            {/* X labels — first/last anchored inward so they can never clip */}
            <g fill="#94a3b8" className="dark:fill-slate-500" fontFamily="inherit" fontSize="11" fontWeight="500">
                {labels.slice(0, count).map((label, i) => (
                    <text key={`${label}-${i}`} x={chartX(i)} y={H - 8} textAnchor={labelAnchor(i)}>
                        {label}
                    </text>
                ))}
            </g>

            {/* Y labels */}
            <g fill="#94a3b8" className="dark:fill-slate-500" fontSize="10" fontWeight="500" textAnchor="end">
                {[100, 75, 50, 25, 0].map((p) => (
                    <text key={p} x={PAD_L - 8} y={chartY(p) + 3}>
                        {p}%
                    </text>
                ))}
            </g>
        </svg>
    );
}
