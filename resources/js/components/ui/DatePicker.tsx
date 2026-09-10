import { ChevronLeft, ChevronRight, Calendar, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

const MONTHS_ID = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

const DAYS_SHORT = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

function toYmd(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function parseYmd(s: string): Date | null {
    if (!s) return null;
    const [y, m, d] = s.split('-').map(Number);
    if (!y || !m || !d) return null;
    const dt = new Date(y, m - 1, d);
    return toYmd(dt) === s ? dt : null;
}

function formatDisplay(ymd: string): string {
    const d = parseYmd(ymd);
    if (!d) return '';
    return `${d.getDate()} ${MONTHS_ID[d.getMonth()]} ${d.getFullYear()}`;
}

function getDaysInMonth(year: number, month: number): number {
    return new Date(year, month + 1, 0).getDate();
}

function getFirstDayOfWeek(year: number, month: number): number {
    return new Date(year, month, 1).getDay();
}

interface DatePickerProps {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    label?: string;
    error?: string;
    disabled?: boolean;
    className?: string;
    id?: string;
}

export function DatePicker({
    value,
    onChange,
    placeholder = 'Pilih tanggal',
    label,
    error,
    disabled = false,
    className,
    id,
}: DatePickerProps) {
    const [isOpen, setIsOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const popoverRef = useRef<HTMLDivElement>(null);

    const selected = parseYmd(value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const [viewYear, setViewYear] = useState(selected?.getFullYear() ?? today.getFullYear());
    const [viewMonth, setViewMonth] = useState(selected?.getMonth() ?? today.getMonth());

    useEffect(() => {
        if (selected) {
            setViewYear(selected.getFullYear());
            setViewMonth(selected.getMonth());
        }
    }, [selected]);

    const close = useCallback(() => setIsOpen(false), []);

    useEffect(() => {
        if (!isOpen) return;
        function handleClick(e: MouseEvent) {
            if (
                popoverRef.current && !popoverRef.current.contains(e.target as Node) &&
                triggerRef.current && !triggerRef.current.contains(e.target as Node)
            ) {
                close();
            }
        }
        function handleKey(e: KeyboardEvent) {
            if (e.key === 'Escape') close();
        }
        document.addEventListener('mousedown', handleClick);
        document.addEventListener('keydown', handleKey);
        return () => {
            document.removeEventListener('mousedown', handleClick);
            document.removeEventListener('keydown', handleKey);
        };
    }, [isOpen, close]);

    function selectDate(ymd: string) {
        onChange(ymd);
        close();
    }

    function goMonth(delta: number) {
        let m = viewMonth + delta;
        let y = viewYear;
        if (m < 0) { m = 11; y--; }
        if (m > 11) { m = 0; y++; }
        setViewMonth(m);
        setViewYear(y);
    }

    const daysInMonth = getDaysInMonth(viewYear, viewMonth);
    const firstDay = getFirstDayOfWeek(viewYear, viewMonth);
    const todayStr = toYmd(today);
    const selectedStr = value || '';

    const calendarDays: (number | null)[] = [];
    for (let i = 0; i < firstDay; i++) calendarDays.push(null);
    for (let d = 1; d <= daysInMonth; d++) calendarDays.push(d);

    const popoverPos = (() => {
        if (!triggerRef.current) return { top: 0, left: 0 };
        const rect = triggerRef.current.getBoundingClientRect();
        return {
            top: rect.bottom + window.scrollY + 6,
            left: Math.min(rect.left + window.scrollX, window.innerWidth - 320),
        };
    })();

    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            {label && (
                <label htmlFor={id} className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                    {label}
                </label>
            )}
            <button
                ref={triggerRef}
                id={id}
                type="button"
                disabled={disabled}
                onClick={() => !disabled && setIsOpen(!isOpen)}
                className={cn(
                    'flex h-10 w-full items-center gap-2 rounded-xl border bg-white px-3 text-left text-sm transition-colors',
                    'focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none',
                    disabled
                        ? 'cursor-not-allowed border-slate-200 bg-slate-100/70 text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400'
                        : value
                          ? 'border-slate-200 text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-white'
                          : 'border-slate-200 text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500',
                )}
            >
                <Calendar className="h-4 w-4 shrink-0 text-slate-400 dark:text-slate-500" />
                <span className="flex-1 truncate">{value ? formatDisplay(value) : placeholder}</span>
                {value && !disabled && (
                    <span
                        role="button"
                        tabIndex={0}
                        onClick={(e) => { e.stopPropagation(); onChange(''); }}
                        onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.stopPropagation(); onChange(''); } }}
                        className="ml-1 rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                    >
                        <X className="h-3.5 w-3.5" />
                    </span>
                )}
            </button>

            {error && <span className="text-[11px] font-medium text-rose-500 dark:text-red-400">{error}</span>}

            {isOpen && createPortal(
                <div
                    ref={popoverRef}
                    className="fixed z-[1060] w-[300px] rounded-2xl border border-slate-200 bg-white p-3 shadow-lg dark:border-slate-700 dark:bg-slate-900"
                    style={{ top: popoverPos.top, left: popoverPos.left }}
                >
                    {/* Header */}
                    <div className="mb-2 flex items-center justify-between">
                        <button
                            type="button"
                            onClick={() => goMonth(-1)}
                            className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </button>
                        <span className="text-xs font-bold text-slate-800 dark:text-white">
                            {MONTHS_ID[viewMonth]} {viewYear}
                        </span>
                        <button
                            type="button"
                            onClick={() => goMonth(1)}
                            className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                        >
                            <ChevronRight className="h-4 w-4" />
                        </button>
                    </div>

                    {/* Day-of-week headers */}
                    <div className="mb-1 grid grid-cols-7 gap-0.5">
                        {DAYS_SHORT.map((d) => (
                            <div key={d} className="py-1 text-center text-[10px] font-bold text-slate-400 dark:text-slate-500">
                                {d}
                            </div>
                        ))}
                    </div>

                    {/* Day grid */}
                    <div className="grid grid-cols-7 gap-0.5">
                        {calendarDays.map((day, i) => {
                            if (day === null) return <div key={`e${i}`} />;
                            const ymd = `${viewYear}-${String(viewMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                            const isToday = ymd === todayStr;
                            const isSelected = ymd === selectedStr;

                            return (
                                <button
                                    key={ymd}
                                    type="button"
                                    onClick={() => selectDate(ymd)}
                                    className={cn(
                                        'h-8 w-full rounded-lg text-xs font-medium transition-colors',
                                        isSelected
                                            ? 'bg-primary text-white shadow-sm'
                                            : isToday
                                              ? 'bg-primary-50 text-primary font-bold dark:bg-primary/20 dark:text-primary-200'
                                              : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800',
                                    )}
                                >
                                    {day}
                                </button>
                            );
                        })}
                    </div>

                    {/* Footer actions */}
                    <div className="mt-2 flex items-center justify-between border-t border-slate-100 pt-2 dark:border-slate-800">
                        <button
                            type="button"
                            onClick={() => { onChange(toYmd(today)); close(); }}
                            className="rounded-lg px-2.5 py-1 text-[11px] font-semibold text-primary hover:bg-primary-50 dark:text-primary-200 dark:hover:bg-primary/10"
                        >
                            Hari Ini
                        </button>
                        {value && (
                            <button
                                type="button"
                                onClick={() => { onChange(''); close(); }}
                                className="rounded-lg px-2.5 py-1 text-[11px] font-semibold text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                            >
                                Hapus
                            </button>
                        )}
                    </div>
                </div>,
                document.body,
            )}
        </div>
    );
}
