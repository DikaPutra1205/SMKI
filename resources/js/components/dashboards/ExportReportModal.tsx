import { DatePicker } from '@/components/ui/DatePicker';
import { Modal } from '@/components/ui/Modal';
import { Select } from '@/components/ui/Select';
import { usePage } from '@inertiajs/react';
import { Download, Loader2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface PeriodOption {
    value: string;
    label: string;
    year: number;
    month: number;
    start_date?: string;
    end_date?: string;
}

interface WorkUnitOption {
    id: number;
    nama: string;
    kode?: string | null;
}

interface ExportReportModalProps {
    open: boolean;
    onClose: () => void;
    unitId?: number | null;
    workUnits?: WorkUnitOption[];
}

export default function ExportReportModal({ open, onClose, unitId, workUnits: initialWorkUnits }: ExportReportModalProps) {
    const { auth } = usePage<{ auth?: { user?: { role?: string; unit_id?: number; name?: string } } }>().props;
    const role = auth?.user?.role || 'guest';

    const [periods, setPeriods] = useState<PeriodOption[]>([]);
    const [workUnits, setWorkUnits] = useState<WorkUnitOption[]>(initialWorkUnits || []);
    const [loadingData, setLoadingData] = useState(false);
    const [selectedType, setSelectedType] = useState<'quick-summary' | 'executive'>('quick-summary');
    const [selectedUnitId, setSelectedUnitId] = useState<string>(unitId ? String(unitId) : '');

    // Date range
    const [startDate, setStartDate] = useState<string>('');
    const [endDate, setEndDate] = useState<string>('');
    const [printMode, setPrintMode] = useState<'latest' | 'per_month'>('latest');

    // Available report types for current role:
    // - admin_kepatuhan: quick-summary only
    // - koordinator_smki: executive only
    // - superadmin, auditor: both quick-summary and executive
    const availableReports = useMemo(() => {
        const reports: Array<{
            id: 'quick-summary' | 'executive';
            label: string;
            pagesBadge: string;
        }> = [];

        if (['superadmin', 'admin_kepatuhan', 'auditor'].includes(role)) {
            reports.push({
                id: 'quick-summary',
                label: 'Laporan Progres Kepatuhan',
                pagesBadge: 'Detail Multi-Halaman',
            });
        }

        if (['superadmin', 'auditor', 'koordinator_smki'].includes(role)) {
            reports.push({
                id: 'executive',
                label: 'Ringkasan Eksekutif',
                pagesBadge: '1 Halaman Ringkas',
            });
        }

        return reports;
    }, [role]);

    // Ensure selected type matches available options
    useEffect(() => {
        if (availableReports.length > 0 && !availableReports.some((r) => r.id === selectedType)) {
            setSelectedType(availableReports[0].id);
        }
    }, [availableReports, selectedType]);

    // Update selected unit when unitId prop changes
    useEffect(() => {
        if (unitId) {
            setSelectedUnitId(String(unitId));
        }
    }, [unitId]);

    useEffect(() => {
        if (initialWorkUnits && initialWorkUnits.length > 0) {
            setWorkUnits(initialWorkUnits);
        }
    }, [initialWorkUnits]);

    // Fetch periods and work units from backend & initialize dates
    useEffect(() => {
        if (!open) return;

        setLoadingData(true);
        fetch('/reports/periods', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((res) => res.json())
            .then((res) => {
                if (res.status === 'success') {
                    if (Array.isArray(res.data) && res.data.length > 0) {
                        setPeriods(res.data);
                        const earliest = res.data[res.data.length - 1];

                        if (earliest?.start_date) {
                            setStartDate((prev) => prev || (earliest.start_date as string));
                        }
                        setEndDate((prev) => prev || new Date().toISOString().slice(0, 10));
                    }

                    if (Array.isArray(res.work_units) && res.work_units.length > 0) {
                        setWorkUnits(res.work_units);
                    }
                }
            })
            .catch((err) => {
                console.error('Failed to load reporting periods / units', err);
            })
            .finally(() => {
                setLoadingData(false);
            });
    }, [open]);

    // Quick range preset handlers
    const applyPreset = (preset: 'this-month' | 'last-3-months' | 'last-6-months' | 'all-time') => {
        const today = new Date();
        const endStr = today.toISOString().slice(0, 10);
        setEndDate(endStr);

        if (preset === 'this-month') {
            const start = new Date(today.getFullYear(), today.getMonth(), 1);
            setStartDate(start.toISOString().slice(0, 10));
        } else if (preset === 'last-3-months') {
            const start = new Date(today.getFullYear(), today.getMonth() - 2, 1);
            setStartDate(start.toISOString().slice(0, 10));
        } else if (preset === 'last-6-months') {
            const start = new Date(today.getFullYear(), today.getMonth() - 5, 1);
            setStartDate(start.toISOString().slice(0, 10));
        } else if (preset === 'all-time') {
            if (periods.length > 0) {
                const earliest = periods[periods.length - 1];
                setStartDate(earliest.start_date || `${earliest.year}-01-01`);
            }
        }
    };

    const handleDownload = () => {
        const params = new URLSearchParams();
        params.set('type', selectedType);

        if (startDate && startDate.trim() !== '') {
            params.set('start_date', startDate.trim());
        }
        if (endDate && endDate.trim() !== '') {
            params.set('end_date', endDate.trim());
        }

        const effectiveUnitId = selectedUnitId || (unitId ? String(unitId) : '');
        if (effectiveUnitId && effectiveUnitId.trim() !== '') {
            params.set('unit_id', effectiveUnitId.trim());
        }

        params.set('print_mode', printMode);

        params.set('t', Date.now().toString());

        const url = `/reports/export-pdf?${params.toString()}`;
        window.open(url, '_blank');
        onClose();
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Ekspor Laporan Kepatuhan SMKI"
            description="Pilih format laporan, satuan unit kerja, dan kurun waktu penilaian data"
            maxWidth="md"
            footer={
                <div className="flex w-full items-center justify-end gap-2.5">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        onClick={handleDownload}
                        disabled={loadingData || availableReports.length === 0}
                        className="inline-flex items-center gap-2 rounded-xl bg-primary px-4.5 py-2 text-xs font-semibold text-white shadow-xs transition-all hover:bg-primary/90 active:scale-95 disabled:opacity-50"
                    >
                        <Download className="h-4 w-4" />
                        Cetak &amp; Unduh PDF
                    </button>
                </div>
            }
        >
            <div className="space-y-4 py-1">
                {/* 1. Format Laporan (Hanya tampil jika ada pilihan lebih dari 1) */}
                {availableReports.length > 1 && (
                    <div>
                        <label className="text-xs font-bold tracking-wider text-slate-600 uppercase dark:text-slate-300">Format Laporan</label>
                        <div className="mt-1.5 grid grid-cols-2 gap-2">
                            {availableReports.map((report) => {
                                const isSelected = selectedType === report.id;
                                return (
                                    <button
                                        key={report.id}
                                        type="button"
                                        onClick={() => setSelectedType(report.id)}
                                        className={`flex flex-col items-start rounded-xl border p-2.5 text-left transition-all ${
                                            isSelected
                                                ? 'border-primary bg-primary/10 text-primary shadow-xs dark:border-primary dark:bg-primary/20 dark:text-primary-200'
                                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                        }`}
                                    >
                                        <span className="text-xs font-bold">{report.label}</span>
                                        <span className="mt-0.5 text-[11px] opacity-75">{report.pagesBadge}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* 2. Filter Satuan Unit Kerja */}
                <div>
                    <Select
                        label="Satuan Unit Kerja (Cakupan Penilaian)"
                        value={selectedUnitId}
                        onChange={(e) => setSelectedUnitId(e.target.value)}
                    >
                        <option value="">Seluruh Satuan Unit Kerja (Komdigi)</option>
                        {workUnits.map((u) => (
                            <option key={u.id} value={String(u.id)}>
                                {u.kode ? `[${u.kode}] ${u.nama}` : u.nama}
                            </option>
                        ))}
                    </Select>
                </div>

                {/* 3. Kurun Waktu Penilaian */}
                <div>
                    <div className="flex items-center justify-between">
                        <label className="text-xs font-bold tracking-wider text-slate-600 uppercase dark:text-slate-300">Kurun Waktu Penilaian</label>
                        {loadingData && (
                            <span className="flex items-center gap-1 text-[11px] text-slate-400">
                                <Loader2 className="h-3 w-3 animate-spin" /> Memuat...
                            </span>
                        )}
                    </div>

                    {/* Presets */}
                    <div className="mt-2 flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            onClick={() => applyPreset('this-month')}
                            className="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-700 transition-colors hover:border-primary/40 hover:bg-primary/5 hover:text-primary dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            Bulan Ini
                        </button>
                        <button
                            type="button"
                            onClick={() => applyPreset('last-3-months')}
                            className="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-700 transition-colors hover:border-primary/40 hover:bg-primary/5 hover:text-primary dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            3 Bulan Terakhir
                        </button>
                        <button
                            type="button"
                            onClick={() => applyPreset('last-6-months')}
                            className="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-700 transition-colors hover:border-primary/40 hover:bg-primary/5 hover:text-primary dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            Semester Ini (6 Bulan)
                        </button>
                        <button
                            type="button"
                            onClick={() => applyPreset('all-time')}
                            className="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-700 transition-colors hover:border-primary/40 hover:bg-primary/5 hover:text-primary dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            Seluruh Riwayat
                        </button>
                    </div>

                    {/* Kurun Awal & Kurun Akhir DatePicker */}
                    <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <DatePicker
                            label="Kurun Awal (Tanggal Mulai)"
                            value={startDate}
                            onChange={setStartDate}
                            placeholder="Pilih tanggal mulai"
                        />
                        <DatePicker
                            label="Kurun Akhir (Tanggal Selesai)"
                            value={endDate}
                            onChange={setEndDate}
                            placeholder="Pilih tanggal selesai"
                        />
                    </div>
                </div>

                {/* 4. Mode Cetak */}
                <div>
                    <label className="text-xs font-bold tracking-wider text-slate-600 uppercase dark:text-slate-300">Mode Cetak Laporan</label>
                    <div className="mt-1.5 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onClick={() => setPrintMode('latest')}
                            className={`flex flex-col items-start rounded-xl border p-2.5 text-left transition-all ${
                                printMode === 'latest'
                                    ? 'border-primary bg-primary/10 text-primary shadow-xs dark:border-primary dark:bg-primary/20 dark:text-primary-200'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                            }`}
                        >
                            <span className="text-xs font-bold">Data Sesi Terakhir</span>
                            <span className="mt-0.5 text-[11px] opacity-75">Hanya asesmen terbaru</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setPrintMode('per_month')}
                            className={`flex flex-col items-start rounded-xl border p-2.5 text-left transition-all ${
                                printMode === 'per_month'
                                    ? 'border-primary bg-primary/10 text-primary shadow-xs dark:border-primary dark:bg-primary/20 dark:text-primary-200'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                            }`}
                        >
                            <span className="text-xs font-bold">Jabarkan Per Bulan</span>
                            <span className="mt-0.5 text-[11px] opacity-75">Dokumen terpisah per bulan (ZIP)</span>
                        </button>
                    </div>
                </div>
            </div>
        </Modal>
    );
}
