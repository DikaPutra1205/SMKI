import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

const INDONESIAN_MONTHS_MAP: Record<string, string> = {
    january: 'Januari',
    february: 'Februari',
    march: 'Maret',
    april: 'April',
    may: 'Mei',
    june: 'Juni',
    july: 'Juli',
    august: 'Agustus',
    september: 'September',
    october: 'Oktober',
    november: 'November',
    december: 'Desember',
    jan: 'Jan',
    feb: 'Feb',
    mar: 'Mar',
    apr: 'Apr',
    jun: 'Jun',
    jul: 'Jul',
    aug: 'Agu',
    sep: 'Sep',
    oct: 'Okt',
    nov: 'Nov',
    dec: 'Des',
};

const INDONESIAN_MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

const INDONESIAN_SHORT_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

/**
 * Format date into Indonesian standard e.g. "22 Agustus 2026" or "22 Agu 2026".
 */
export function formatDateIndonesian(
    dateInput: string | Date | null | undefined,
    options: { showDay?: boolean; shortMonth?: boolean; withYear?: boolean } = { showDay: true, shortMonth: false, withYear: true },
): string {
    if (!dateInput) return '—';
    const d = typeof dateInput === 'string' ? new Date(dateInput) : dateInput;
    if (isNaN(d.getTime())) return String(dateInput);

    const day = d.getDate();
    const monthIndex = d.getMonth();
    const year = d.getFullYear();
    const month = options.shortMonth ? INDONESIAN_SHORT_MONTHS[monthIndex] : INDONESIAN_MONTHS[monthIndex];

    const parts: string[] = [];
    if (options.showDay !== false) parts.push(String(day));
    parts.push(month);
    if (options.withYear !== false) parts.push(String(year));

    return parts.join(' ');
}

export const formatDate = formatDateIndonesian;

/**
 * Format datetime into Indonesian standard e.g. "22 Agu 2026 · 14:30".
 */
export function formatDateTimeIndonesian(dateInput: string | Date | null | undefined): string {
    if (!dateInput) return '—';
    const d = typeof dateInput === 'string' ? new Date(dateInput) : dateInput;
    if (isNaN(d.getTime())) return String(dateInput);

    const dateStr = formatDateIndonesian(d, { showDay: true, shortMonth: true, withYear: true });
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');

    return `${dateStr} · ${hours}:${minutes}`;
}

/**
 * Intelligently formats period strings into Indonesian standard.
 * Examples:
 * - "2026-08" -> "Agustus 2026"
 * - "August 2026" -> "Agustus 2026"
 * - "Q1 2026" -> "Triwulan 1 2026"
 * - "Semester 1 2026" -> "Semester 1 2026"
 */
export function formatPeriodeIndonesian(periode: string | null | undefined): string {
    if (!periode || typeof periode !== 'string') return '—';
    const trimmed = periode.trim();
    if (!trimmed) return '—';

    // Matches YYYY-MM (e.g. 2026-08)
    const yyyymmMatch = trimmed.match(/^(\d{4})-(\d{1,2})$/);
    if (yyyymmMatch) {
        const year = yyyymmMatch[1];
        const monthNum = parseInt(yyyymmMatch[2], 10);
        if (monthNum >= 1 && monthNum <= 12) {
            return `${INDONESIAN_MONTHS[monthNum - 1]} ${year}`;
        }
    }

    // Matches Q1-Q4 (e.g. Q1 2026)
    const qMatch = trimmed.match(/^Q([1-4])\s*(\d{4})$/i);
    if (qMatch) {
        return `Triwulan ${qMatch[1]} ${qMatch[2]}`;
    }

    // Replace any English month names found inside the string
    let result = trimmed;
    for (const [en, id] of Object.entries(INDONESIAN_MONTHS_MAP)) {
        const regex = new RegExp(`\\b${en}\\b`, 'gi');
        result = result.replace(regex, id);
    }

    return result;
}

/**
 * Format timestamp into Indonesian relative time (e.g. "Baru saja", "5 mnt lalu", "2 jam lalu", "Kemarin", "3 hr lalu").
 */
export function formatTimeAgoIndonesian(dateInput: string | Date | null | undefined): string {
    if (!dateInput) return '—';
    const d = typeof dateInput === 'string' ? new Date(dateInput) : dateInput;
    if (isNaN(d.getTime())) return String(dateInput);

    const now = new Date();
    const diffMs = now.getTime() - d.getTime();
    const diffSec = Math.floor(diffMs / 1000);

    if (diffSec < 45) return 'Baru saja';
    if (diffSec < 90) return '1 mnt lalu';

    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) return `${diffMin} mnt lalu`;

    const diffHours = Math.floor(diffMin / 60);
    if (diffHours < 24) return `${diffHours} jam lalu`;

    const diffDays = Math.floor(diffHours / 24);
    if (diffDays === 1) return 'Kemarin';
    if (diffDays < 7) return `${diffDays} hr lalu`;

    return formatDateIndonesian(d, { showDay: true, shortMonth: true, withYear: d.getFullYear() !== now.getFullYear() });
}
