/**
 * verify.tsx  —  Admin Kepatuhan: Single-Entry Verify
 *
 * A focused one-at-a-time verification flow (contrast: bulk-verify handles many at once).
 *
 * UX:
 *   1. Filterable table of pending checklist entries (default: is_verified=0).
 *   2. Click any row → slide-over detail panel: control info, evidence link, PIC notes.
 *   3. Detail panel has Approve (Patuh) + Reject (Tidak Patuh) buttons + optional admin note.
 *   4. Submit fires POST /admin/kepatuhan/checklist/verify/{entry_id}.
 *   5. On success: flash toast, panel closes, table refreshes (Inertia back()).
 *
 * Data: same getReviewQueueEntries paginator as bulk-verify. No new backend data needed.
 * Action endpoint: POST /admin/kepatuhan/checklist/verify/{entry} (verifySingle).
 */

import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import { StatusBadge, statusTone } from '@/components/ui/StatusBadge';
import { Textarea } from '@/components/ui/Textarea';
import { Toast } from '@/components/ui/Toast';
import AppLayout from '@/layouts/AppLayout';
import { useCan } from '@/lib/can';
import { t } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, FileText, Search, ShieldCheck, X, XCircle } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/* ─── Types ─────────────────────────────────────────────────────────────── */

interface ControlRef {
    id: number;
    kode_klausul: string;
    judul: string;
    kategori: string;
    deskripsi?: string | null;
    framework?: { id: number; nama: string; versi: string } | null;
}

interface VerifyEntry {
    id: number;
    status: string | null;
    catatan: string | null;
    catatan_admin?: string | null;
    tanggal_input: string | null;
    tanggal_verifikasi: string | null;
    control?: ControlRef | null;
    unit?: { id: number; nama: string } | null;
    pic?: { id: number; name: string } | null;
    admin?: { id: number; name: string } | null;
    active_evidence?: { id: number; file_url: string; version_number: number; is_active: boolean } | null;
}

interface WorkUnitItem {
    id: number;
    nama: string;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface VerifyProps {
    entries?: Paginator<VerifyEntry>;
    workUnits?: WorkUnitItem[];
    filters?: {
        status?: string;
        unit_id?: string;
        framework_id?: string;
        is_verified?: string;
        search?: string;
    };
}

type ConfirmAction = 'approve' | 'reject' | null;

/* ─── Helpers ────────────────────────────────────────────────────────────── */

function fmtDate(value: string | null): string {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function kategoriLabel(k: string): string {
    return k === 'annex_a' ? 'Annex A' : 'Klausul 4–10';
}

/* ─── Detail Slide-Over Panel ────────────────────────────────────────────── */

interface DetailPanelProps {
    entry: VerifyEntry | null;
    onClose: () => void;
}

function DetailPanel({ entry, onClose }: DetailPanelProps) {
    const can = useCan();
    const { flash } = usePage<{ flash?: { type: string; message: string } }>().props;
    const [flashVisible, setFlashVisible] = useState(false);
    const [confirmAction, setConfirmAction] = useState<ConfirmAction>(null);
    const [adminNote, setAdminNote] = useState('');
    const [busy, setBusy] = useState(false);
    const form = useForm({ status: '', admin_notes: '' });

    useEffect(() => {
        // Reset panel state when a new entry opens
        setAdminNote('');
        setConfirmAction(null);
    }, [entry?.id]);

    useEffect(() => {
        if (flash?.message) {
            setFlashVisible(true);
            const t = setTimeout(() => setFlashVisible(false), 4000);
            return () => clearTimeout(t);
        }
    }, [flash]);

    useEffect(() => {
        if (!entry) return;
        const handler = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
        };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [entry, onClose]);

    function submitDecision() {
        if (!confirmAction || !entry) return;
        setBusy(true);
        form.setData({
            status: confirmAction === 'approve' ? 'compliant' : 'non_compliant',
            admin_notes: adminNote,
        });
        form.post(`/admin/kepatuhan/checklist/verify/${entry.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setBusy(false);
                setConfirmAction(null);
                setAdminNote('');
                onClose();
            },
            onError: () => setBusy(false),
        });
    }

    if (!entry) return null;

    const code = entry.control?.kode_klausul ?? '—';
    const title = entry.control?.judul ?? t('common.noData');
    const framework = entry.control?.framework
        ? `${entry.control.framework.nama}${entry.control.framework.versi ? ` ${entry.control.framework.versi}` : ''}`
        : null;
    const hasEvidence = Boolean(entry.active_evidence?.file_url);
    const alreadyVerified = Boolean(entry.tanggal_verifikasi);

    return (
        <>
            <Toast
                visible={flashVisible}
                tone={flash?.type === 'error' ? 'error' : 'success'}
                message={flash?.message}
                onDismiss={() => setFlashVisible(false)}
            />

            {/* Backdrop */}
            <div className="fixed inset-0 z-40 bg-slate-900/30 backdrop-blur-[2px] transition-opacity" onClick={onClose} aria-hidden="true" />

            {/* Panel */}
            <aside className="border-border fixed inset-y-0 right-0 z-50 flex w-full max-w-[480px] flex-col border-l bg-white shadow-2xl">
                {/* Header */}
                <div className="border-border flex items-start justify-between gap-4 border-b px-5 py-4">
                    <div className="min-w-0">
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="text-primary h-4 w-4 shrink-0" />
                            <h2 className="text-navy text-base font-bold">Verifikasi Entri</h2>
                        </div>
                        <p className="text-muted mt-0.5 font-mono text-xs">{code}</p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-muted hover:bg-surface hover:text-navy rounded-lg p-1.5 transition-colors"
                        aria-label="Tutup panel"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>

                {/* Body */}
                <div className="flex-1 space-y-5 overflow-y-auto px-5 py-4">
                    {/* Control info */}
                    <div className="border-border bg-surface/40 overflow-hidden rounded-[12px] border">
                        <div className="border-border flex items-center justify-between border-b px-4 py-2.5">
                            <span className="text-muted text-xs font-semibold tracking-wide uppercase">Kontrol</span>
                            {framework && (
                                <span className="border-border text-body rounded-[6px] border bg-white px-2 py-0.5 text-[11px] font-semibold">
                                    {framework}
                                </span>
                            )}
                        </div>
                        <div className="px-4 py-3">
                            <p className="text-navy text-sm leading-snug font-bold">{title}</p>
                            {entry.control?.deskripsi && (
                                <p className="text-body mt-1.5 line-clamp-3 text-xs leading-relaxed">{entry.control.deskripsi}</p>
                            )}
                            {entry.control?.kategori && (
                                <span className="text-muted mt-2 inline-block text-[11px] font-medium">{kategoriLabel(entry.control.kategori)}</span>
                            )}
                        </div>
                    </div>

                    {/* Meta: unit, PIC, status, dates */}
                    <div className="border-border overflow-hidden rounded-[12px] border">
                        {[
                            { label: 'Unit Kerja', value: entry.unit?.nama || '—' },
                            { label: 'PIC', value: entry.pic?.name || '—' },
                            {
                                label: 'Status PIC',
                                value: <StatusBadge tone={statusTone(entry.status)}>{t(`status.${entry.status ?? 'pending'}` as never)}</StatusBadge>,
                            },
                            { label: 'Tanggal Input', value: fmtDate(entry.tanggal_input) || '—' },
                        ].map(({ label, value }, i, arr) => (
                            <div
                                key={label}
                                className={`flex items-center justify-between gap-3 px-4 py-2.5 ${i < arr.length - 1 ? 'border-border border-b' : ''}`}
                            >
                                <span className="text-body text-[13px] font-medium">{label}</span>
                                <span className="text-navy text-right text-[13px] font-semibold">{value}</span>
                            </div>
                        ))}
                    </div>

                    {/* PIC notes */}
                    {entry.catatan && (
                        <div>
                            <h3 className="text-navy mb-1.5 text-xs font-bold tracking-wide uppercase">Catatan PIC</h3>
                            <p className="border-border bg-surface/50 text-body rounded-[10px] border px-3.5 py-3 text-[13px] leading-relaxed">
                                {entry.catatan}
                            </p>
                        </div>
                    )}

                    {/* Evidence */}
                    <div>
                        <h3 className="text-navy mb-1.5 text-xs font-bold tracking-wide uppercase">Evidence</h3>
                        {hasEvidence ? (
                            <a
                                href={entry.active_evidence!.file_url}
                                target="_blank"
                                rel="noreferrer"
                                className="flex items-center gap-2.5 rounded-[10px] border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-100"
                            >
                                <FileText className="h-4 w-4 shrink-0" />
                                <span className="flex-1 truncate">Lihat Dokumen Evidence</span>
                                <ExternalLink className="h-3.5 w-3.5 shrink-0 opacity-60" />
                            </a>
                        ) : (
                            <div className="flex items-center gap-2.5 rounded-[10px] border border-amber-200 bg-amber-50 px-3.5 py-3 text-sm font-semibold text-amber-600">
                                <XCircle className="h-4 w-4 shrink-0" />
                                Belum ada evidence
                            </div>
                        )}
                    </div>

                    {/* Previous admin notes (if already verified before) */}
                    {alreadyVerified && (
                        <div className="border-info/20 bg-info-bg rounded-[10px] border px-3.5 py-3">
                            <p className="text-info text-xs font-semibold">
                                Sudah diverifikasi · {fmtDate(entry.tanggal_verifikasi)}
                                {entry.admin?.name ? ` oleh ${entry.admin.name}` : ''}
                            </p>
                            {entry.catatan_admin && <p className="text-body mt-1 text-xs">{entry.catatan_admin}</p>}
                        </div>
                    )}

                    {/* Admin notes textarea */}
                    <Textarea
                        label="Catatan Admin (opsional)"
                        value={adminNote}
                        onChange={(e) => setAdminNote(e.target.value)}
                        placeholder="Catatan untuk PIC…"
                        rows={3}
                    />
                </div>

                {/* Footer action bar */}
                {can('checklist.bulk-verify') && (
                    <div className="border-border bg-surface/60 flex items-center gap-3 border-t px-5 py-4">
                        <button
                            type="button"
                            onClick={() => setConfirmAction('approve')}
                            className="bg-success hover:bg-success/90 inline-flex flex-1 items-center justify-center gap-2 rounded-[10px] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors"
                        >
                            <CheckCircle2 className="h-4 w-4" />
                            Setujui (Patuh)
                        </button>
                        <button
                            type="button"
                            onClick={() => setConfirmAction('reject')}
                            className="bg-danger hover:bg-danger/90 inline-flex flex-1 items-center justify-center gap-2 rounded-[10px] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors"
                        >
                            <XCircle className="h-4 w-4" />
                            Tolak (Tidak Patuh)
                        </button>
                    </div>
                )}
            </aside>

            <ConfirmDialog
                open={confirmAction !== null}
                title={confirmAction === 'approve' ? t('bulkVerify.confirmApproveTitle') : t('bulkVerify.confirmRejectTitle')}
                description={
                    confirmAction === 'approve'
                        ? `Tandai entri ini sebagai Patuh? Catatan admin akan dikirim ke PIC.`
                        : `Tandai entri ini sebagai Tidak Patuh? PIC perlu menindaklanjuti.`
                }
                confirmLabel={confirmAction === 'approve' ? t('bulkVerify.approve') : t('bulkVerify.reject')}
                variant={confirmAction === 'approve' ? 'info' : 'danger'}
                busy={busy}
                onCancel={() => setConfirmAction(null)}
                onConfirm={submitDecision}
            />
        </>
    );
}

/* ─── Page ───────────────────────────────────────────────────────────────── */

const STATUS_OPTIONS = ['compliant', 'partial', 'non_compliant', 'na'] as const;

export default function Verify({ entries, workUnits = [], filters = {} }: VerifyProps) {
    const page = entries ?? { data: [], current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null };
    const items = page.data;

    const { flash } = usePage<{ flash?: { type: string; message: string } }>().props;
    const [flashVisible, setFlashVisible] = useState(false);

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState<string>(filters.status || 'all');
    const [selectedUnit, setSelectedUnit] = useState<string>(filters.unit_id || 'all');
    const [verification, setVerification] = useState<string>(() => {
        const v = filters.is_verified;
        if (!v || v === '' || v === 'all') return 'all';
        return v === '1' || v === 'true' ? 'verified' : 'unverified';
    });
    const isFirstRender = useRef(true);

    const [activeEntry, setActiveEntry] = useState<VerifyEntry | null>(null);

    useEffect(() => {
        if (flash?.message && !activeEntry) {
            setFlashVisible(true);
            const timer = setTimeout(() => setFlashVisible(false), 4000);
            return () => clearTimeout(timer);
        }
    }, [flash, activeEntry]);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                '/admin/kepatuhan/checklist/verify',
                {
                    search: searchQuery || undefined,
                    status: selectedStatus !== 'all' ? selectedStatus : undefined,
                    unit_id: selectedUnit !== 'all' ? selectedUnit : undefined,
                    is_verified: verification === 'all' ? undefined : verification === 'verified' ? '1' : '0',
                },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [searchQuery, selectedStatus, selectedUnit, verification]);

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/admin/kepatuhan/dashboard' }, { label: 'Verifikasi Entri' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/kepatuhan/checklist/verify">
            <Head title="Verifikasi Entri — Admin Kepatuhan" />

            <Toast
                visible={flashVisible}
                tone={flash?.type === 'error' ? 'error' : 'success'}
                message={flash?.message}
                onDismiss={() => setFlashVisible(false)}
            />

            {/* Page header */}
            <div className="page-head flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Verifikasi Entri</h1>
                    <p className="text-muted mt-1 text-sm">
                        Tinjau setiap entri checklist satu per satu — klik baris untuk membuka detail dan mengambil keputusan.
                    </p>
                </div>
                <div className="text-muted text-xs">{page.total} entri ditemukan</div>
            </div>

            {/* Filter bar */}
            <div className="border-border flex flex-col gap-3 rounded-[14px] border bg-white p-4 lg:flex-row lg:items-center">
                <div className="relative flex-1">
                    <Search className="text-faint pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder={t('bulkVerify.searchPlaceholder')}
                        className="border-border-strong bg-surface/40 text-navy placeholder:text-muted focus:border-primary w-full rounded-[10px] border py-2.5 pr-3 pl-9 text-sm focus:outline-none"
                    />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Select value={selectedStatus} onChange={(e) => setSelectedStatus(e.target.value)} className="min-w-[160px]">
                        <option value="all">{t('bulkVerify.allStatus')}</option>
                        {STATUS_OPTIONS.map((s) => (
                            <option key={s} value={s}>
                                {t(`status.${s}`)}
                            </option>
                        ))}
                    </Select>

                    <Select value={selectedUnit} onChange={(e) => setSelectedUnit(e.target.value)} className="min-w-[180px]">
                        <option value="all">{t('bulkVerify.allUnits')}</option>
                        {workUnits.map((u) => (
                            <option key={u.id} value={String(u.id)}>
                                {u.nama}
                            </option>
                        ))}
                    </Select>

                    <Select value={verification} onChange={(e) => setVerification(e.target.value)} className="min-w-[180px]">
                        <option value="unverified">Belum Diverifikasi</option>
                        <option value="all">{t('bulkVerify.allVerified')}</option>
                        <option value="verified">{t('bulkVerify.verified')}</option>
                    </Select>
                </div>
            </div>

            {/* Table */}
            <div className="border-border overflow-hidden rounded-[14px] border bg-white">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead>
                            <tr className="border-border bg-surface/60 text-muted border-b text-xs font-semibold tracking-wider uppercase">
                                <th className="px-4 py-3">{t('bulkVerify.code')}</th>
                                <th className="px-4 py-3">{t('bulkVerify.control')}</th>
                                <th className="px-4 py-3">{t('bulkVerify.workUnit')}</th>
                                <th className="px-4 py-3">{t('bulkVerify.pic')}</th>
                                <th className="px-4 py-3">{t('bulkVerify.status')}</th>
                                <th className="px-4 py-3">{t('bulkVerify.evidence')}</th>
                                <th className="px-4 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-10">
                                        <EmptyState message="Tidak ada entri yang cocok dengan filter ini." />
                                    </td>
                                </tr>
                            ) : (
                                items.map((entry) => {
                                    const hasEvidence = Boolean(entry.active_evidence?.file_url);
                                    const isActive = activeEntry?.id === entry.id;

                                    return (
                                        <tr
                                            key={entry.id}
                                            onClick={() => setActiveEntry(isActive ? null : entry)}
                                            className={`border-border cursor-pointer border-b transition-colors last:border-0 ${
                                                isActive ? 'bg-primary/5 ring-primary/20 ring-1 ring-inset' : 'hover:bg-surface/50'
                                            }`}
                                        >
                                            <td className="text-navy px-4 py-3 font-mono text-xs font-bold">{entry.control?.kode_klausul ?? '—'}</td>
                                            <td className="max-w-[250px] px-4 py-3">
                                                <p className="text-navy truncate font-semibold">{entry.control?.judul ?? t('common.noData')}</p>
                                                {entry.control?.framework && (
                                                    <p className="text-muted truncate text-xs">{entry.control.framework.nama}</p>
                                                )}
                                            </td>
                                            <td className="text-body px-4 py-3">{entry.unit?.nama ?? '—'}</td>
                                            <td className="text-body px-4 py-3">{entry.pic?.name ?? '—'}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge tone={statusTone(entry.status)}>
                                                    {t(`status.${entry.status ?? 'pending'}` as never)}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-4 py-3">
                                                {hasEvidence ? (
                                                    <span className="border-success-border bg-success-bg text-success inline-flex items-center gap-1 rounded-[6px] border px-2 py-0.5 text-[11px] font-semibold">
                                                        <FileText className="h-3 w-3" />
                                                        Ada
                                                    </span>
                                                ) : (
                                                    <span className="text-muted text-xs">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <button
                                                    type="button"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        setActiveEntry(isActive ? null : entry);
                                                    }}
                                                    className={`rounded-[8px] border px-3 py-1.5 text-xs font-semibold transition-colors ${
                                                        isActive
                                                            ? 'border-primary bg-primary text-white'
                                                            : 'border-border-strong text-body hover:bg-surface bg-white'
                                                    }`}
                                                >
                                                    {isActive ? 'Aktif' : 'Tinjau'}
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination
                    currentPage={page.current_page}
                    totalPages={page.last_page}
                    perPage={page.per_page}
                    totalItems={page.total}
                    startIndex={(page.current_page - 1) * page.per_page}
                    endIndex={Math.min(page.to ?? page.total, page.total)}
                    onPageChange={(p) =>
                        router.get(
                            '/admin/kepatuhan/checklist/verify',
                            {
                                page: p > 1 ? p : undefined,
                                search: searchQuery || undefined,
                                status: selectedStatus !== 'all' ? selectedStatus : undefined,
                                unit_id: selectedUnit !== 'all' ? selectedUnit : undefined,
                                is_verified: verification === 'all' ? undefined : verification === 'verified' ? '1' : '0',
                            },
                            { preserveState: true, replace: true },
                        )
                    }
                />
            </div>

            {/* Slide-over detail panel */}
            <DetailPanel entry={activeEntry} onClose={() => setActiveEntry(null)} />
        </AppLayout>
    );
}
