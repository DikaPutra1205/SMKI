import AssessmentSummarySkeleton from '@/components/skeletons/AssessmentSummarySkeleton';
import { usePageLoading } from '@/hooks/usePageLoading';
import AppLayout from '@/layouts/AppLayout';
import { assessmentStore } from '@/stores/assessmentStore';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, CheckCircle2, Clock, FileText, Send, Shield, ShieldAlert, ShieldCheck, ShieldHalf, XCircle } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

interface ControlData {
    id: number;
    framework_id: number;
    kode_klausul: string;
    judul: string;
    deskripsi: string | null;
    kategori: string;
    framework_name: string;
    framework_versi: string;
}

interface EvidenceData {
    id: number;
    checklist_entry_id: number;
    version_number: number;
    file_url: string;
    nama_file: string;
    is_active: boolean;
}

interface EntryItem {
    id: number;
    control_id: number;
    status: string;
    catatan: string | null;
    catatan_admin: string | null;
    tanggal_input: string | null;
    tanggal_verifikasi: string | null;
    control: ControlData;
    active_evidence: EvidenceData | null;
}

interface SessionData {
    id: number;
    konteks_penilaian: string;
    periode: string | null;
    unit_id: number;
    framework_id: number | null;
    created_at: string;
    updated_at: string;
    unit: { id: number; nama: string };
    framework: { id: number; nama: string; versi: string } | null;
}

interface SummaryData {
    total_entries: number;
    compliant: number;
    partial: number;
    non_compliant: number;
    na: number;
    compliance_percentage: number;
}

interface AssessmentSummaryProps {
    session: SessionData;
    entries: EntryItem[];
    summary: SummaryData;
}

function formatKategori(kategori: string): string {
    return kategori.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function DonutChart({
    percentage,
    compliant,
    partial,
    nonCompliant,
    na,
}: {
    percentage: number;
    compliant: number;
    partial: number;
    nonCompliant: number;
    na: number;
}) {
    const radius = 48;
    const circumference = 2 * Math.PI * radius;
    const total = compliant + partial + nonCompliant + na;

    const segments = [
        { value: compliant, color: '#10b981' },
        { value: partial, color: '#f59e0b' },
        { value: nonCompliant, color: '#ef4444' },
        { value: na, color: '#94a3b8' },
    ];

    let startOffset = 0;

    return (
        <div className="flex flex-col items-center gap-3">
            <div className="relative inline-flex items-center justify-center">
                <svg width="150" height="150" viewBox="0 0 120 120" className="-rotate-90">
                    <circle
                        cx="60"
                        cy="60"
                        r={radius}
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="10"
                        className="text-slate-100 dark:text-slate-800"
                    />
                    {total > 0 &&
                        segments.map(({ value, color }) => {
                            const dashLength = (value / total) * circumference;
                            const circle = (
                                <circle
                                    key={color}
                                    cx="60"
                                    cy="60"
                                    r={radius}
                                    fill="none"
                                    stroke={color}
                                    strokeWidth="10"
                                    strokeDasharray={`${dashLength} ${circumference - dashLength}`}
                                    strokeDashoffset={-startOffset}
                                />
                            );
                            startOffset += dashLength;
                            return circle;
                        })}
                </svg>
                <div className="absolute flex flex-col items-center">
                    <span className="text-2xl font-black text-slate-900 dark:text-white">{percentage}%</span>
                    <span className="text-[10px] font-semibold tracking-wider text-slate-400 uppercase">Kepatuhan</span>
                </div>
            </div>

            <div className="flex items-center gap-4">
                <div className="flex items-center gap-1.5">
                    <span className="h-2.5 w-2.5 rounded-full bg-emerald-500" />
                    <span className="text-xs font-medium text-slate-600 dark:text-slate-400">
                        Patuh <span className="font-bold text-slate-900 dark:text-white">{compliant}</span>
                    </span>
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-2.5 w-2.5 rounded-full bg-amber-500" />
                    <span className="text-xs font-medium text-slate-600 dark:text-slate-400">
                        Sebagian Patuh <span className="font-bold text-slate-900 dark:text-white">{partial}</span>
                    </span>
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-2.5 w-2.5 rounded-full bg-red-500" />
                    <span className="text-xs font-medium text-slate-600 dark:text-slate-400">
                        Ketidaksesuaian <span className="font-bold text-slate-900 dark:text-white">{nonCompliant}</span>
                    </span>
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-2.5 w-2.5 rounded-full bg-slate-300 dark:bg-slate-500" />
                    <span className="text-xs font-medium text-slate-600 dark:text-slate-400">
                        Tidak Berlaku <span className="font-bold text-slate-900 dark:text-white">{na}</span>
                    </span>
                </div>
            </div>
        </div>
    );
}

function StatCard({
    icon: Icon,
    label,
    value,
    color,
    bgColor,
}: {
    icon: typeof ShieldCheck;
    label: string;
    value: number;
    color: string;
    bgColor: string;
}) {
    return (
        <div className={`flex items-center gap-3 rounded-xl border p-4 ${bgColor}`}>
            <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${color}`}>
                <Icon className="h-5 w-5 text-white" />
            </div>
            <div>
                <p className="text-2xl font-bold text-slate-900 dark:text-white">{value}</p>
                <p className="text-xs font-medium text-slate-500">{label}</p>
            </div>
        </div>
    );
}

function EvidenceLink({ entry }: { entry: EntryItem }) {
    if (entry.active_evidence) {
        return (
            <a
                href={entry.active_evidence.file_url}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[11px] font-medium text-emerald-700 transition-colors hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400"
            >
                <FileText className="h-3.5 w-3.5" />
                <span className="truncate">{entry.active_evidence.nama_file}</span>
            </a>
        );
    }

    return (
        <div className="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-[11px] font-medium text-amber-600 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
            <XCircle className="h-3.5 w-3.5" />
            Belum ada bukti
        </div>
    );
}

function ControlCardHeader({ entry }: { entry: EntryItem }) {
    return (
        <div className="mb-2 flex flex-wrap items-center gap-1.5">
            <span className="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                {entry.control.kode_klausul}
            </span>
            <span className="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-600 dark:bg-blue-900/40 dark:text-blue-400">
                {entry.control.framework_name}
            </span>
            <span className="text-[11px] text-slate-400">{formatKategori(entry.control.kategori)}</span>
        </div>
    );
}

function NonCompliantCard({ entry, index }: { entry: EntryItem; index: number }) {
    return (
        <div className="flex h-full flex-col rounded-xl border border-red-100 bg-white p-4 shadow-sm dark:border-red-900/40 dark:bg-slate-900">
            <div className="mb-2 flex items-center justify-between gap-2">
                <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-red-100 text-xs font-bold text-red-600 dark:bg-red-900/40 dark:text-red-400">
                    {index}
                </div>
                <div className="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400">
                    <ShieldAlert className="h-3.5 w-3.5" />
                    Ketidaksesuaian
                </div>
            </div>
            <ControlCardHeader entry={entry} />
            <h4 className="mb-1 text-sm font-bold text-slate-900 dark:text-white">{entry.control.judul}</h4>
            {entry.control.deskripsi && (
                <p className="mb-3 line-clamp-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{entry.control.deskripsi}</p>
            )}
            {entry.catatan && (
                <div className="mb-3 flex items-start gap-1.5 rounded-lg bg-amber-50 px-2.5 py-2 text-xs text-slate-600 dark:bg-amber-900/20 dark:text-slate-400">
                    <AlertTriangle className="mt-0.5 h-3 w-3 shrink-0 text-amber-500" />
                    <span>
                        <span className="font-semibold text-amber-700 dark:text-amber-400">Catatan:</span>{' '}
                        <span className="italic">&ldquo;{entry.catatan}&rdquo;</span>
                    </span>
                </div>
            )}
            <div className="mt-auto pt-1">
                <EvidenceLink entry={entry} />
            </div>
        </div>
    );
}

function NaCard({ entry, index }: { entry: EntryItem; index: number }) {
    return (
        <div className="flex h-full flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div className="mb-2 flex items-center justify-between gap-2">
                <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    {index}
                </div>
                <div className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                    <Shield className="h-3.5 w-3.5" />
                    Tidak Berlaku
                </div>
            </div>
            <ControlCardHeader entry={entry} />
            <h4 className="mb-1 text-sm font-bold text-slate-900 dark:text-white">{entry.control.judul}</h4>
            {entry.control.deskripsi && (
                <p className="mb-3 line-clamp-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{entry.control.deskripsi}</p>
            )}
            {entry.catatan && (
                <div className="mb-3 flex items-start gap-1.5 rounded-lg bg-slate-50 px-2.5 py-2 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                    <AlertTriangle className="mt-0.5 h-3 w-3 shrink-0 text-amber-500" />
                    <span>
                        <span className="font-semibold text-slate-500 dark:text-slate-300">Catatan:</span>{' '}
                        <span className="italic">&ldquo;{entry.catatan}&rdquo;</span>
                    </span>
                </div>
            )}
            <div className="mt-auto pt-1">
                <EvidenceLink entry={entry} />
            </div>
        </div>
    );
}

function PartialCard({ entry, index }: { entry: EntryItem; index: number }) {
    return (
        <div className="flex h-full flex-col rounded-xl border border-amber-100 bg-white p-4 shadow-sm dark:border-amber-900/40 dark:bg-slate-900">
            <div className="mb-2 flex items-center justify-between gap-2">
                <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-600 dark:bg-amber-900/40 dark:text-amber-400">
                    {index}
                </div>
                <div className="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                    <ShieldHalf className="h-3.5 w-3.5" />
                    Sebagian Patuh
                </div>
            </div>
            <ControlCardHeader entry={entry} />
            <h4 className="mb-1 text-sm font-bold text-slate-900 dark:text-white">{entry.control.judul}</h4>
            {entry.control.deskripsi && (
                <p className="mb-3 line-clamp-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{entry.control.deskripsi}</p>
            )}
            {entry.catatan && (
                <div className="mb-3 flex items-start gap-1.5 rounded-lg bg-amber-50 px-2.5 py-2 text-xs text-slate-600 dark:bg-amber-900/20 dark:text-slate-400">
                    <AlertTriangle className="mt-0.5 h-3 w-3 shrink-0 text-amber-500" />
                    <span>
                        <span className="font-semibold text-amber-700 dark:text-amber-400">Catatan:</span>{' '}
                        <span className="italic">&ldquo;{entry.catatan}&rdquo;</span>
                    </span>
                </div>
            )}
            <div className="mt-auto pt-1">
                <EvidenceLink entry={entry} />
            </div>
        </div>
    );
}

function CompliantCard({ entry }: { entry: EntryItem }) {
    return (
        <div className="flex h-full flex-col rounded-xl border border-emerald-100 bg-white p-4 shadow-sm dark:border-emerald-900/40 dark:bg-slate-900">
            <div className="mb-2 flex items-center justify-between gap-2">
                <div className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                    <CheckCircle2 className="h-3 w-3" />
                    Patuh
                </div>
            </div>
            <ControlCardHeader entry={entry} />
            <h4 className="mb-1 text-sm font-bold text-slate-900 dark:text-white">{entry.control.judul}</h4>
            {entry.control.deskripsi && (
                <p className="mb-3 line-clamp-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{entry.control.deskripsi}</p>
            )}
            {entry.catatan && (
                <p className="mb-3 text-xs text-slate-500 dark:text-slate-400">
                    <span className="font-semibold text-emerald-600 dark:text-emerald-400">Catatan:</span> {entry.catatan}
                </p>
            )}
            <div className="mt-auto pt-1">
                <EvidenceLink entry={entry} />
            </div>
        </div>
    );
}

export default function AssessmentSummary({ session, entries, summary }: AssessmentSummaryProps) {
    const [showConfirm, setShowConfirm] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const submittingRef = useRef(false);
    const [frameworkFilter, setFrameworkFilter] = useState('');
    const [kategoriFilter, setKategoriFilter] = useState('');
    // Summary URLs are /admin/pic/checklist/{id}/summary
    const isLoading = usePageLoading('/admin/pic/checklist/');

    const frameworks = useMemo(() => {
        const map = new Map<string, string>();
        entries.forEach((e) => {
            if (e.control.framework_name && !map.has(e.control.framework_name)) {
                map.set(e.control.framework_name, e.control.framework_versi);
            }
        });
        return Array.from(map.entries());
    }, [entries]);

    const kategoris = useMemo(() => {
        const seen = new Set<string>();
        return entries.map((e) => e.control.kategori).filter((k) => (seen.has(k) ? false : (seen.add(k), true)));
    }, [entries]);

    const filteredEntries = useMemo(
        () =>
            entries.filter(
                (e) =>
                    (!frameworkFilter || e.control.framework_name === frameworkFilter) && (!kategoriFilter || e.control.kategori === kategoriFilter),
            ),
        [entries, frameworkFilter, kategoriFilter],
    );

    const nonCompliantEntries = useMemo(() => filteredEntries.filter((e) => e.status === 'non_compliant'), [filteredEntries]);

    const partialEntries = useMemo(() => filteredEntries.filter((e) => e.status === 'partial'), [filteredEntries]);

    const naEntries = useMemo(() => filteredEntries.filter((e) => e.status === 'na'), [filteredEntries]);

    const compliantEntries = useMemo(() => filteredEntries.filter((e) => e.status === 'compliant'), [filteredEntries]);

    const hasActiveFilter = frameworkFilter !== '' || kategoriFilter !== '';

    const handleSubmit = async () => {
        if (submittingRef.current) return;
        submittingRef.current = true;
        setSubmitting(true);
        try {
            await assessmentStore.flushDirty(session.id);
        } catch {
            // continue anyway
        }
        router.post(
            `/admin/pic/checklist/${session.id}/submit`,
            {},
            {
                onFinish: () => {
                    submittingRef.current = false;
                    setSubmitting(false);
                },
            },
        );
    };

    const complianceColor =
        summary.compliance_percentage >= 80 ? 'text-emerald-600' : summary.compliance_percentage >= 50 ? 'text-amber-600' : 'text-red-600';

    if (isLoading) {
        return <AssessmentSummarySkeleton />;
    }

    return (
        <AppLayout>
            <Head title={`Ringkasan — ${session.konteks_penilaian}`} />

            {/* Header */}
            <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <button
                        type="button"
                        onClick={() => router.get(`/admin/pic/checklist/${session.id}`)}
                        className="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 transition-colors hover:text-blue-700"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Kembali ke Checklist
                    </button>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Ringkasan Assessment</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        {session.konteks_penilaian} &middot; {session.periode || 'Tanpa Periode'}
                    </p>
                </div>
                <div className="flex items-center gap-2 text-sm text-slate-400">
                    <Clock className="h-4 w-4" />
                    {new Date(session.updated_at).toLocaleDateString('id-ID', {
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    })}
                </div>
            </div>

            {/* Stats Overview */}
            <div className="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div className="flex flex-col items-center gap-8 lg:flex-row">
                    {/* Donut Chart */}
                    <div className="flex items-center gap-8">
                        <DonutChart
                            percentage={summary.compliance_percentage}
                            compliant={summary.compliant}
                            partial={summary.partial}
                            nonCompliant={summary.non_compliant}
                            na={summary.na}
                        />
                        <div className="text-left">
                            <p className={`text-sm font-bold ${complianceColor}`}>
                                {summary.compliance_percentage >= 80
                                    ? 'Tingkat Kepatuhan Baik'
                                    : summary.compliance_percentage >= 50
                                      ? 'Perlu Perbaikan'
                                      : 'Tingkat Kepatuhan Rendah'}
                            </p>
                            <p className="text-xs text-slate-400">{summary.total_entries} total kontrol dievaluasi</p>
                        </div>
                    </div>

                    {/* Stat Cards */}
                    <div className="grid flex-1 grid-cols-2 gap-3 lg:grid-cols-3">
                        <StatCard
                            icon={ShieldCheck}
                            label="Patuh"
                            value={summary.compliant}
                            color="bg-emerald-500"
                            bgColor="border-emerald-100 bg-emerald-50/50 dark:border-emerald-900 dark:bg-emerald-950/30"
                        />
                        <StatCard
                            icon={ShieldHalf}
                            label="Sebagian Patuh"
                            value={summary.partial}
                            color="bg-amber-500"
                            bgColor="border-amber-100 bg-amber-50/50 dark:border-amber-900 dark:bg-amber-950/30"
                        />
                        <StatCard
                            icon={ShieldAlert}
                            label="Ketidaksesuaian"
                            value={summary.non_compliant}
                            color="bg-red-500"
                            bgColor="border-red-100 bg-red-50/50 dark:border-red-900 dark:bg-red-950/30"
                        />
                        <StatCard
                            icon={Shield}
                            label="Tidak Berlaku"
                            value={summary.na}
                            color="bg-slate-400"
                            bgColor="border-slate-100 bg-slate-50/50 dark:border-slate-700 dark:bg-slate-800/50"
                        />
                    </div>
                </div>
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    <select
                        value={frameworkFilter}
                        onChange={(e) => setFrameworkFilter(e.target.value)}
                        className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                    >
                        <option value="">Semua Framework</option>
                        {frameworks.map(([name, ver]) => (
                            <option key={name} value={name}>
                                {name} ({ver})
                            </option>
                        ))}
                    </select>
                    <select
                        value={kategoriFilter}
                        onChange={(e) => setKategoriFilter(e.target.value)}
                        className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                    >
                        <option value="">Semua Kategori</option>
                        {kategoris.map((k) => (
                            <option key={k} value={k}>
                                {formatKategori(k)}
                            </option>
                        ))}
                    </select>
                </div>
                {hasActiveFilter && (
                    <span className="text-xs text-slate-400">
                        Menampilkan {filteredEntries.length} dari {entries.length} kontrol
                    </span>
                )}
            </div>

            {/* Non-Compliant Findings */}
            {nonCompliantEntries.length > 0 && (
                <div className="mb-8">
                    <div className="mb-3 flex items-center gap-2">
                        <div className="flex h-6 w-6 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40">
                            <ShieldAlert className="h-3.5 w-3.5 text-red-600 dark:text-red-400" />
                        </div>
                        <h2 className="text-lg font-bold text-slate-900 dark:text-white">Temuan Ketidaksesuaian</h2>
                        <span className="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-bold text-red-700 dark:bg-red-900/40 dark:text-red-400">
                            {nonCompliantEntries.length}
                        </span>
                    </div>
                    <p className="mb-4 text-sm text-slate-500">Kontrol berikut memerlukan tindakan perbaikan untuk mencapai kepatuhan.</p>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {nonCompliantEntries.map((entry, idx) => (
                            <NonCompliantCard key={entry.id} entry={entry} index={idx + 1} />
                        ))}
                    </div>
                </div>
            )}

            {/* Partial Compliance Items */}
            {partialEntries.length > 0 && (
                <div className="mb-8">
                    <div className="mb-3 flex items-center gap-2">
                        <div className="flex h-6 w-6 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                            <ShieldHalf className="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <h2 className="text-lg font-bold text-slate-900 dark:text-white">Kontrol Sebagian Patuh</h2>
                        <span className="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">
                            {partialEntries.length}
                        </span>
                    </div>
                    <p className="mb-4 text-sm text-slate-500">Kontrol berikut baru terpenuhi sebagian dan memerlukan tindak lanjut.</p>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {partialEntries.map((entry, idx) => (
                            <PartialCard key={entry.id} entry={entry} index={idx + 1} />
                        ))}
                    </div>
                </div>
            )}

            {/* N/A Items */}
            {naEntries.length > 0 && (
                <div className="mb-8">
                    <div className="mb-3 flex items-center gap-2">
                        <div className="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                            <Shield className="h-3.5 w-3.5 text-slate-500" />
                        </div>
                        <h2 className="text-lg font-bold text-slate-900 dark:text-white">Kontrol Tidak Berlaku</h2>
                        <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                            {naEntries.length}
                        </span>
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {naEntries.map((entry, idx) => (
                            <NaCard key={entry.id} entry={entry} index={idx + 1} />
                        ))}
                    </div>
                </div>
            )}

            {/* Compliant Summary */}
            {compliantEntries.length > 0 && (
                <div className="mb-8">
                    <div className="mb-3 flex items-center gap-2">
                        <div className="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                            <ShieldCheck className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <h2 className="text-lg font-bold text-slate-900 dark:text-white">Kontrol Patuh</h2>
                        <span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400">
                            {compliantEntries.length}
                        </span>
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {compliantEntries.map((entry) => (
                            <CompliantCard key={entry.id} entry={entry} />
                        ))}
                    </div>
                </div>
            )}

            {/* Submit Action */}
            <div className="mt-10 flex flex-col items-center gap-4 rounded-2xl border border-blue-100 bg-gradient-to-r from-blue-50 to-indigo-50/50 p-8 dark:border-blue-900 dark:from-blue-950/30 dark:to-indigo-950/20">
                <div className="text-center">
                    <h3 className="text-lg font-bold text-slate-900 dark:text-white">Kirim Assessment untuk Verifikasi?</h3>
                    <p className="mt-1 text-sm text-slate-500">Pastikan semua data sudah benar sebelum mengirim ke pengelola kepatuhan.</p>
                </div>
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={() => router.get(`/admin/pic/checklist/${session.id}`)}
                        className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Kembali Edit
                    </button>
                    <button
                        type="button"
                        onClick={() => setShowConfirm(true)}
                        className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-blue-700 hover:shadow-md"
                    >
                        <Send className="h-4 w-4" />
                        Kirim Assessment
                    </button>
                </div>
            </div>

            {/* Confirmation Modal */}
            {showConfirm && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm"
                    onClick={() => !submitting && setShowConfirm(false)}
                >
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex justify-center">
                            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/40">
                                <Send className="h-7 w-7 text-blue-600 dark:text-blue-400" />
                            </div>
                        </div>
                        <h3 className="mb-2 text-center text-lg font-bold text-slate-900 dark:text-white">Konfirmasi Pengiriman</h3>
                        <p className="mb-6 text-center text-sm text-slate-500">
                            Assessment akan dikirim ke pengelola kepatuhan untuk diverifikasi. Anda masih bisa mengedit entri setelah pengiriman jika
                            diperlukan.
                        </p>

                        {nonCompliantEntries.length > 0 && (
                            <div className="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                                <div className="flex items-start gap-2">
                                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                                    <div>
                                        <p className="text-xs font-semibold text-amber-800 dark:text-amber-300">
                                            {nonCompliantEntries.length} temuan ketidaksesuaian
                                        </p>
                                        <p className="text-[11px] text-amber-700 dark:text-amber-400">
                                            Temuan ini akan dicatat dan mungkin memerlukan tindakan perbaikan.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="flex items-center justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => setShowConfirm(false)}
                                disabled={submitting}
                                className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                onClick={handleSubmit}
                                disabled={submitting}
                                className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 disabled:opacity-50"
                            >
                                {submitting ? (
                                    <>
                                        <span className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                        Mengirim...
                                    </>
                                ) : (
                                    <>
                                        <Send className="h-4 w-4" />
                                        Ya, Kirim Sekarang
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
