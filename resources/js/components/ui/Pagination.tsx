import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

interface PaginationProps {
    currentPage: number;
    totalPages: number;
    perPage: number | 'all';
    totalItems: number;
    startIndex: number;
    endIndex: number;
    onPageChange: (page: number) => void;
    onPerPageChange?: (perPage: number | 'all') => void;
}

const PER_PAGE_OPTIONS: Array<number | 'all'> = [20, 50, 100, 'all'];

export function Pagination({
    currentPage,
    totalPages,
    perPage,
    totalItems,
    startIndex,
    endIndex,
    onPageChange,
    onPerPageChange,
}: PaginationProps) {
    const safeCurrentPage = Math.min(Math.max(1, currentPage), totalPages);
    const shownStart = totalItems === 0 ? 0 : startIndex + 1;

    return (
        <div className="flex flex-col gap-4 border-t border-border bg-surface/50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-3 text-xs text-muted sm:text-sm">
                {onPerPageChange && (
                    <div className="flex items-center gap-2">
                        <span>Tampilkan</span>
                        <select
                            value={String(perPage)}
                            onChange={(e) => {
                                const val = e.target.value;
                                onPerPageChange(val === 'all' ? 'all' : Number(val));
                            }}
                            className="rounded-lg border border-border-strong bg-white px-2.5 py-1.5 text-xs font-semibold text-navy focus:border-primary focus:outline-none"
                        >
                            {PER_PAGE_OPTIONS.map((opt) => (
                                <option key={String(opt)} value={String(opt)}>
                                    {opt === 'all' ? 'Semua' : String(opt)}
                                </option>
                            ))}
                        </select>
                        <span>per halaman</span>
                    </div>
                )}
                <span className="hidden text-border-strong sm:inline">•</span>
                <span>
                    Menampilkan <strong className="font-semibold text-navy">{shownStart}</strong>–<strong className="font-semibold text-navy">{endIndex}</strong> dari{' '}
                    <strong className="font-semibold text-navy">{totalItems}</strong> data
                </span>
            </div>

            {totalPages > 1 && (
                <div className="flex items-center gap-1.5">
                    <button
                        type="button"
                        disabled={safeCurrentPage === 1}
                        onClick={() => onPageChange(Math.max(1, safeCurrentPage - 1))}
                        className="inline-flex items-center gap-1 rounded-lg border border-border-strong bg-white px-3 py-1.5 text-xs font-medium text-body shadow-sm transition-colors hover:bg-surface disabled:opacity-40 disabled:hover:bg-white"
                    >
                        <ChevronLeft className="h-3.5 w-3.5" />
                        <span>Sebelumnya</span>
                    </button>

                    <div className="flex items-center gap-1">
                        {Array.from({ length: totalPages }, (_, i) => i + 1)
                            .filter((p) => p === 1 || p === totalPages || Math.abs(p - safeCurrentPage) <= 1)
                            .map((p, idx, arr) => (
                                <div key={p} className="flex items-center">
                                    {idx > 0 && arr[idx - 1] !== p - 1 && <span className="px-1 text-xs text-faint">...</span>}
                                    <button
                                        type="button"
                                        onClick={() => onPageChange(p)}
                                        className={cn(
                                            'min-w-[32px] rounded-lg px-2.5 py-1.5 text-xs font-semibold transition-colors',
                                            safeCurrentPage === p
                                                ? 'bg-primary text-white shadow-sm'
                                                : 'border border-border-strong bg-white text-body hover:bg-surface',
                                        )}
                                    >
                                        {p}
                                    </button>
                                </div>
                            ))}
                    </div>

                    <button
                        type="button"
                        disabled={safeCurrentPage === totalPages}
                        onClick={() => onPageChange(Math.min(totalPages, safeCurrentPage + 1))}
                        className="inline-flex items-center gap-1 rounded-lg border border-border-strong bg-white px-3 py-1.5 text-xs font-medium text-body shadow-sm transition-colors hover:bg-surface disabled:opacity-40 disabled:hover:bg-white"
                    >
                        <span>Selanjutnya</span>
                        <ChevronRight className="h-3.5 w-3.5" />
                    </button>
                </div>
            )}
        </div>
    );
}