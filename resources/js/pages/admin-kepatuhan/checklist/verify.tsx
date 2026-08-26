/**
 * verify.tsx  —  Admin Kepatuhan: Unified Checklist Verification Page
 *
 * Designed with a world-class, frictionless UX:
 * 1. Checkboxes are always present from the start (no cumbersome mode toggling).
 * 2. Selecting items smoothly invokes a sleek, elevated floating action dock at the bottom.
 * 3. Individual entries can be inspected in-depth anytime via the Slide-Over Detail Panel.
 */

import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import { StatusBadge, statusTone } from '@/components/ui/StatusBadge';
import { Textarea } from '@/components/ui/Textarea';
import { Toast } from '@/components/ui/Toast';
import AppLayout from '@/layouts/AppLayout';
import { useCan } from '@/lib/can';
import { t } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeft,
    Building2,
    CheckCircle2,
    ExternalLink,
    Eye,
    FileText,
    Filter,
    Loader2,
    Search,
    Shield,
    ShieldCheck,
    X,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

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
    active_evidence?: { id: number; file_url: string; version_number: number; is_active: boolean; nama_file?: string } | null;
}

interface WorkUnitItem {
    id: number;
    nama: string;
}

interface SessionRef {
    id: number;
    konteks_penilaian: string;
    periode?: string;
    unit?: { id: number; nama: string } | null;
    framework?: { id: number; nama: string; versi: string } | null;
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
    session?: SessionRef | null;
    workUnits?: WorkUnitItem[];
    filters?: {
        status?: string;
        unit_id?: string;
        framework_id?: string;
        session_id?: string;
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

function isImageFile(filename?: string): boolean {
    if (!filename) return false;
    const ext = filename.split('.').pop()?.toLowerCase();
    return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'].includes(ext ?? '');
}

/* ─── Detail Slide-Over Panel (Single Review) ────────────────────────────── */

interface DetailPanelProps {
    entry: VerifyEntry | null;
    onClose: () => void;
    onPreviewEvidence: (evidence: { file_url: string; nama_file?: string }) => void;
}

function DetailPanel({ entry, onClose, onPreviewEvidence }: DetailPanelProps) {
    const can = useCan();
    const { flash } = usePage<{ flash?: { type: string; message: string } }>().props;
    const [flashVisible, setFlashVisible] = useState(false);
    const [confirmAction, setConfirmAction] = useState<ConfirmAction>(null);
    const [adminNote, setAdminNote] = useState('');
    const [noteError, setNoteError] = useState<string | null>(null);
    const [actionSubmitting, setActionSubmitting] = useState<'approve' | 'reject' | null>(null);

    useEffect(() => {
        setAdminNote(entry?.tanggal_verifikasi ? entry?.catatan_admin || '' : '');
        setNoteError(null);
        setConfirmAction(null);
        setActionSubmitting(null);
    }, [entry?.id, entry?.catatan_admin, entry?.tanggal_verifikasi]);

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

    const executeVerify = (action: 'approve' | 'reject') => {
        if (!entry || actionSubmitting) return;
        const targetStatus = action === 'approve' ? 'compliant' : 'non_compliant';

        if (action === 'reject' && !adminNote.trim()) {
            setNoteError('Catatan verifikasi admin wajib diisi sebelum menolak.');
            return;
        }

        setNoteError(null);
        setActionSubmitting(action);

        router.post(
            `/admin/kepatuhan/checklist/verify/${entry.id}`,
            {
                status: targetStatus,
                admin_notes: adminNote.trim() || undefined,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['entries', 'session', 'flash'],
                onSuccess: () => {
                    setActionSubmitting(null);
                    setConfirmAction(null);
                    setAdminNote('');
                    setNoteError(null);
                    onClose();
                },
                onError: () => {
                    setActionSubmitting(null);
                    setConfirmAction(null);
                },
            },
        );
    };

    const handleActionClick = (action: 'approve' | 'reject') => {
        if (!entry || actionSubmitting) return;
        if (action === 'approve') {
            // One-click fast approval
            executeVerify('approve');
            return;
        }
        if (action === 'reject') {
            if (!adminNote.trim()) {
                setNoteError('Catatan verifikasi admin wajib diisi sebelum menolak.');
                return;
            }
            setNoteError(null);
            setConfirmAction('reject');
        }
    };

    if (!entry) return null;

    const code = entry.control?.kode_klausul ?? '—';
    const title = entry.control?.judul ?? t('common.noData');
    const framework = entry.control?.framework
        ? `${entry.control.framework.nama}${entry.control.framework.versi ? ` ${entry.control.framework.versi}` : ''}`
        : null;
    const hasEvidence = Boolean(entry.active_evidence?.file_url);
    const alreadyVerified = Boolean(entry.tanggal_verifikasi);

    return createPortal(
        <>
            <Toast
                visible={flashVisible}
                tone={flash?.type === 'error' ? 'error' : 'success'}
                message={flash?.message}
                onDismiss={() => setFlashVisible(false)}
            />

            {/* Backdrop */}
            <div className="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-[2px] transition-opacity" onClick={onClose} aria-hidden="true" />

            {/* Panel */}
            <aside className="border-border fixed inset-y-0 right-0 z-50 flex w-full max-w-[500px] flex-col border-l bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                {/* Header */}
                <div className="border-border flex items-start justify-between gap-4 border-b px-5 py-4 dark:border-slate-700">
                    <div className="min-w-0">
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="text-primary h-5 w-5 shrink-0" />
                            <h2 className="text-navy text-base font-bold dark:text-white">Verifikasi Entri Kontrol</h2>
                        </div>
                        <p className="text-muted mt-0.5 font-mono text-xs font-semibold dark:text-slate-400">{code}</p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-muted hover:bg-surface hover:text-navy rounded-lg p-1.5 transition-colors dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                        aria-label="Tutup panel"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Body */}
                <div className="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    {/* Control info */}
                    <div className="border-border bg-surface/50 overflow-hidden rounded-[12px] border dark:border-slate-700 dark:bg-slate-800/40">
                        <div className="border-border flex items-center justify-between border-b px-4 py-2.5 dark:border-slate-700">
                            <span className="text-muted text-xs font-bold tracking-wide uppercase dark:text-slate-400">Kontrol / Klausul</span>
                            {framework && (
                                <span className="border-border text-navy rounded-[6px] border bg-white px-2 py-0.5 text-[11px] font-semibold dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                    {framework}
                                </span>
                            )}
                        </div>
                        <div className="px-4 py-3">
                            <p className="text-navy text-sm leading-snug font-bold dark:text-white">{title}</p>
                            {entry.control?.deskripsi && (
                                <p className="text-body mt-2 text-xs leading-relaxed dark:text-slate-300">{entry.control.deskripsi}</p>
                            )}
                            {entry.control?.kategori && (
                                <span className="text-muted mt-2 inline-block text-[11px] font-medium dark:text-slate-400">
                                    Kategori: {kategoriLabel(entry.control.kategori)}
                                </span>
                            )}
                        </div>
                    </div>

                    {/* Meta: unit, PIC, status, dates */}
                    <div className="border-border overflow-hidden rounded-[12px] border dark:border-slate-700">
                        {[
                            { label: 'Unit Kerja', value: entry.unit?.nama || '—' },
                            { label: 'PIC Pengisi', value: entry.pic?.name || '—' },
                            {
                                label: 'Status PIC',
                                value: <StatusBadge tone={statusTone(entry.status)}>{t(`status.${entry.status ?? 'pending'}` as never)}</StatusBadge>,
                            },
                            { label: 'Tanggal Input', value: fmtDate(entry.tanggal_input) || '—' },
                        ].map(({ label, value }, i, arr) => (
                            <div
                                key={label}
                                className={`flex items-center justify-between gap-3 px-4 py-2.5 ${i < arr.length - 1 ? 'border-border border-b dark:border-slate-700' : ''}`}
                            >
                                <span className="text-body text-xs font-medium dark:text-slate-300">{label}</span>
                                <span className="text-navy text-right text-xs font-semibold dark:text-white">{value}</span>
                            </div>
                        ))}
                    </div>

                    {/* PIC notes */}
                    {entry.catatan && (
                        <div>
                            <h3 className="text-navy mb-1.5 text-xs font-bold tracking-wide uppercase dark:text-white">Catatan PIC</h3>
                            <p className="border-border bg-surface/50 text-body rounded-[10px] border px-3.5 py-3 text-xs leading-relaxed dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-300">
                                {entry.catatan}
                            </p>
                        </div>
                    )}

                    {/* Evidence */}
                    <div>
                        <h3 className="text-navy mb-1.5 text-xs font-bold tracking-wide uppercase dark:text-white">Dokumen Bukti (Evidence)</h3>
                        {hasEvidence ? (
                            <button
                                type="button"
                                onClick={() =>
                                    onPreviewEvidence({
                                        file_url: entry.active_evidence!.file_url,
                                        nama_file: entry.active_evidence?.nama_file || `${code} Evidence`,
                                    })
                                }
                                className="flex w-full items-center justify-between gap-2.5 rounded-[10px] border border-emerald-300 bg-emerald-50 px-3.5 py-3 text-xs font-semibold text-emerald-800 transition-colors hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950/60"
                            >
                                <div className="flex items-center gap-2 truncate">
                                    <FileText className="h-4 w-4 shrink-0" />
                                    <span className="truncate">Pratinjau Bukti (Lightbox)</span>
                                </div>
                                <Eye className="h-4 w-4 shrink-0" />
                            </button>
                        ) : (
                            <div className="flex items-center gap-2.5 rounded-[10px] border border-amber-300 bg-amber-50 px-3.5 py-3 text-xs font-semibold text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                                <XCircle className="h-4 w-4 shrink-0" />
                                Belum ada dokumen evidence yang dilampirkan
                            </div>
                        )}
                    </div>

                    {/* Previous admin notes (if already verified before) */}
                    {alreadyVerified && (
                        <div className="border-info/20 bg-info-bg rounded-[10px] border px-3.5 py-3 dark:bg-sky-950/30">
                            <p className="text-info text-xs font-semibold dark:text-sky-400">
                                Telah Diverifikasi · {fmtDate(entry.tanggal_verifikasi)}
                                {entry.admin?.name ? ` oleh ${entry.admin.name}` : ''}
                            </p>
                            {entry.catatan_admin && <p className="text-body mt-1 text-xs dark:text-slate-300">{entry.catatan_admin}</p>}
                        </div>
                    )}

                    {/* Admin notes textarea — only visible for reject flow */}
                    {confirmAction !== 'approve' && (
                        <div className="space-y-1">
                            <Textarea
                                label="Catatan Verifikasi Admin"
                                value={adminNote}
                                onChange={(e) => {
                                    setAdminNote(e.target.value);
                                    if (noteError && e.target.value.trim()) setNoteError(null);
                                }}
                                placeholder="Tuliskan alasan penolakan dan arahan tindak lanjut untuk PIC unit kerja..."
                                rows={3}
                                hint="Wajib diisi saat menolak entri."
                            />
                            {noteError && <p className="text-xs font-semibold text-rose-600 dark:text-rose-400">{noteError}</p>}
                        </div>
                    )}
                </div>

                {/* Footer action bar */}
                {can('checklist.bulk-verify') && (
                    <div className="border-border bg-surface/60 flex items-center gap-3 border-t px-5 py-4 dark:border-slate-700 dark:bg-slate-900/60">
                        <button
                            type="button"
                            disabled={actionSubmitting !== null}
                            onClick={() => handleActionClick('approve')}
                            className="bg-success hover:bg-success/90 inline-flex flex-1 items-center justify-center gap-2 rounded-[10px] px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition-all disabled:opacity-50 active:scale-[0.98]"
                        >
                            {actionSubmitting === 'approve' ? (
                                <>
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    Menyetujui...
                                </>
                            ) : (
                                <>
                                    <CheckCircle2 className="h-4 w-4" />
                                    Setujui (Patuh)
                                </>
                            )}
                        </button>
                        <button
                            type="button"
                            disabled={actionSubmitting !== null}
                            onClick={() => handleActionClick('reject')}
                            className="bg-danger hover:bg-danger/90 inline-flex flex-1 items-center justify-center gap-2 rounded-[10px] px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition-all disabled:opacity-50 active:scale-[0.98]"
                        >
                            {actionSubmitting === 'reject' ? (
                                <>
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    Menolak...
                                </>
                            ) : (
                                <>
                                    <XCircle className="h-4 w-4" />
                                    Tolak (Tidak Patuh)
                                </>
                            )}
                        </button>
                    </div>
                )}
            </aside>

            <ConfirmDialog
                open={confirmAction === 'reject'}
                title="Tolak Kontrol Ini?"
                description="Tandai entri kontrol ini sebagai Tidak Patuh? PIC unit kerja perlu menindaklanjuti."
                confirmLabel="Tolak (Tidak Patuh)"
                variant="danger"
                busy={actionSubmitting === 'reject'}
                onCancel={() => setConfirmAction(null)}
                onConfirm={() => executeVerify('reject')}
            />
        </>,
        document.body,
    );
}

/* ─── Unified Verify Page with Always-On Selection & Floating Dock ─────────── */

const STATUS_OPTIONS = ['compliant', 'partial', 'non_compliant', 'na'] as const;

export default function Verify({ entries, session, workUnits = [], filters = {} }: VerifyProps) {
    const can = useCan();
    const page = entries ?? { data: [], current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null };
    const items = page.data;

    const { flash } = usePage<{ flash?: { type: string; message: string } }>().props;
    const [flashVisible, setFlashVisible] = useState(false);

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState<string>(filters.status || 'all');
    const [selectedUnit, setSelectedUnit] = useState<string>(filters.unit_id || 'all');
    const [selectedSessionId] = useState<string>(filters.session_id || '');
    const [verification, setVerification] = useState<string>(() => {
        const v = filters.is_verified;
        if (!v || v === '' || v === 'all') return 'all';
        return v === '1' || v === 'true' ? 'verified' : 'unverified';
    });
    const isFirstRender = useRef(true);

    // ── Single slideover detail state ─────────────────────────────────────────
    const [activeEntry, setActiveEntry] = useState<VerifyEntry | null>(null);

    // ── Lightbox Evidence Modal State ─────────────────────────────────────────
    const [previewEvidence, setPreviewEvidence] = useState<{ file_url: string; nama_file?: string } | null>(null);
    const [evidenceLoading, setEvidenceLoading] = useState(true);

    useEffect(() => {
        setEvidenceLoading(previewEvidence !== null);
    }, [previewEvidence]);

    // ── Checkbox Selection State ──────────────────────────────────────────────
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [bulkConfirmAction, setBulkConfirmAction] = useState<ConfirmAction>(null);
    const [bulkAdminNote, setBulkAdminNote] = useState('');
    const [bulkNoteError, setBulkNoteError] = useState<string | null>(null);
    const [bulkBusy, setBulkBusy] = useState(false);

    function clearSelection() {
        setSelectedIds(new Set());
        setBulkAdminNote('');
        setBulkNoteError(null);
        setBulkConfirmAction(null);
    }

    const unverifiedCount = useMemo(() => items.filter((e) => !e.tanggal_verifikasi).length, [items]);
    const firstUnverifiedEntry = useMemo(() => items.find((e) => !e.tanggal_verifikasi), [items]);

    function scrollToFirstUnverified() {
        if (!firstUnverifiedEntry) return;
        const target = document.getElementById(`verify-row-${firstUnverifiedEntry.id}`);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.classList.add('ring-2', 'ring-primary', 'dark:ring-primary');
            setTimeout(() => target.classList.remove('ring-2', 'ring-primary', 'dark:ring-primary'), 2500);
        }
    }

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
                    session_id: selectedSessionId || undefined,
                    is_verified: verification === 'all' ? undefined : verification === 'verified' ? '1' : '0',
                },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [searchQuery, selectedStatus, selectedUnit, verification, selectedSessionId]);

    const pageIds = items.map((i) => i.id);
    const allSelectedOnPage = pageIds.length > 0 && pageIds.every((id) => selectedIds.has(id));

    function toggleAll() {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (allSelectedOnPage) {
                pageIds.forEach((id) => next.delete(id));
            } else {
                pageIds.forEach((id) => next.add(id));
            }
            return next;
        });
    }

    function toggleOne(id: number) {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    }

    function openBulkConfirm(action: Exclude<ConfirmAction, null>) {
        if (selectedIds.size === 0) return;
        setBulkConfirmAction(action);
    }

    function submitBulkDecision() {
        if (!bulkConfirmAction || selectedIds.size === 0) return;

        const targetStatus = bulkConfirmAction === 'approve' ? 'compliant' : 'non_compliant';

        if (bulkConfirmAction === 'reject' && !bulkAdminNote.trim()) {
            setBulkNoteError('Catatan verifikasi admin wajib diisi sebelum menolak secara massal.');
            return;
        }

        setBulkBusy(true);
        router.post(
            '/admin/kepatuhan/bulk-verify',
            {
                entry_ids: Array.from(selectedIds),
                status: targetStatus,
                admin_notes: bulkAdminNote.trim() || undefined,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['entries', 'session', 'flash'],
                onSuccess: () => {
                    setBulkBusy(false);
                    setBulkNoteError(null);
                    clearSelection();
                },
                onError: () => setBulkBusy(false),
            },
        );
    }

    const breadcrumbs = [
        { label: t('common.dashboard'), href: '/admin/kepatuhan/dashboard' },
        { label: t('bulkVerify.title'), href: '/admin/kepatuhan/checklist/verify' },
        ...(session?.konteks_penilaian ? [{ label: session.konteks_penilaian }] : []),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/kepatuhan/checklist/verify">
            <Head title={`${t('bulkVerify.title')} — ${session?.konteks_penilaian ?? 'Detail Sesi'}`} />

            <Toast
                visible={flashVisible}
                tone={flash?.type === 'error' ? 'error' : 'success'}
                message={flash?.message}
                onDismiss={() => setFlashVisible(false)}
            />

            {/* Back link + Page header */}
            <div className="flex flex-col gap-4">
                <div className="flex items-center gap-2">
                    <Link
                        href="/admin/kepatuhan/checklist/verify"
                        className="text-muted hover:text-navy inline-flex items-center gap-1.5 text-xs font-semibold transition-colors dark:text-slate-400 dark:hover:text-white"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Kembali ke Daftar Sesi
                    </Link>
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{t('bulkVerify.title')}</h1>
                            <span className="border-border text-body inline-flex items-center rounded-full border bg-white px-2.5 py-0.5 text-xs font-semibold shadow-xs dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                                {page.total} Kontrol
                            </span>
                        </div>
                        <p className="text-muted mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                            {session?.konteks_penilaian
                                ? `Meninjau entri checklist untuk sesi "${session.konteks_penilaian}". Centang baris untuk verifikasi massal atau klik baris untuk melihat detail.`
                                : t('bulkVerify.subtitle')}
                        </p>
                    </div>
                </div>
            </div>

            {/* Session Context Summary Banner & Quick Actions */}
            {session && (
                <div className="border-border flex flex-wrap items-center justify-between gap-3.5 rounded-2xl border bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex flex-wrap items-center gap-3.5">
                        <div className="flex items-center gap-2">
                            <div className="bg-primary-50 text-primary dark:bg-navy-900/50 dark:text-primary-200 flex h-8 w-8 items-center justify-center rounded-xl">
                                <Building2 className="h-4 w-4 shrink-0" />
                            </div>
                            <div>
                                <span className="block text-[10px] font-bold text-slate-400 uppercase">Unit Kerja</span>
                                <span className="text-xs font-bold text-slate-900 dark:text-white">{session.unit?.nama ?? 'Semua Unit'}</span>
                            </div>
                        </div>

                        {session.framework && (
                            <div className="flex items-center gap-2 border-l border-slate-100 pl-3.5 dark:border-slate-800">
                                <div className="bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300 flex h-8 w-8 items-center justify-center rounded-xl">
                                    <Shield className="h-4 w-4 shrink-0" />
                                </div>
                                <div>
                                    <span className="block text-[10px] font-bold text-slate-400 uppercase">Framework</span>
                                    <span className="text-xs font-bold text-slate-900 dark:text-white">
                                        {session.framework.nama} {session.framework.versi}
                                    </span>
                                </div>
                            </div>
                        )}

                        {session.periode && (
                            <div className="border-l border-slate-100 pl-3.5 dark:border-slate-800">
                                <span className="block text-[10px] font-bold text-slate-400 uppercase">Periode</span>
                                <span className="text-xs font-semibold text-slate-700 dark:text-slate-300">{session.periode}</span>
                            </div>
                        )}
                    </div>

                    {/* Quick Jump to First Unverified Control */}
                    {firstUnverifiedEntry && (
                        <button
                            type="button"
                            onClick={scrollToFirstUnverified}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 shadow-xs transition-colors hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                        >
                            <ArrowDown className="h-3.5 w-3.5" />
                            <span>Lompat ke Kontrol Belum Diverifikasi</span>
                        </button>
                    )}
                </div>
            )}

            {/* Filter Toolbar */}
            <div className="border-border flex flex-col gap-3 rounded-2xl border bg-white p-4 shadow-sm lg:flex-row lg:items-center dark:border-slate-800 dark:bg-slate-900">
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Cari kode klausul atau judul kontrol…"
                        className="focus:border-primary w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2 pr-3 pl-9 text-xs text-slate-900 placeholder:text-slate-400 focus:bg-white focus:outline-none sm:text-sm dark:border-slate-700 dark:bg-slate-800/40 dark:text-white"
                    />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {/* Instant Toggle Button for Unverified Only */}
                    <button
                        type="button"
                        onClick={() => setVerification(verification === 'unverified' ? 'all' : 'unverified')}
                        className={`inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold transition-all ${
                            verification === 'unverified'
                                ? 'border-primary bg-primary-50 text-primary-700 dark:border-primary dark:bg-navy-900/40 dark:text-primary-200 shadow-xs'
                                : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                        }`}
                    >
                        <Filter className="h-3.5 w-3.5" />
                        <span>Hanya Belum Diverifikasi</span>
                        {unverifiedCount > 0 && (
                            <span className="py-0.2 rounded-full bg-amber-100 px-1.5 text-[10px] font-bold text-amber-800 dark:bg-amber-900/60 dark:text-amber-300">
                                {unverifiedCount}
                            </span>
                        )}
                    </button>

                    <Select value={selectedStatus} onChange={(e) => setSelectedStatus(e.target.value)} className="min-w-[160px]">
                        <option value="all">Semua Status Kepatuhan</option>
                        {STATUS_OPTIONS.map((s) => (
                            <option key={s} value={s}>
                                {t(`status.${s}`)}
                            </option>
                        ))}
                    </Select>

                    {workUnits.length > 0 && (
                        <Select value={selectedUnit} onChange={(e) => setSelectedUnit(e.target.value)} className="min-w-[170px]">
                            <option value="all">Semua Unit Kerja</option>
                            {workUnits.map((u) => (
                                <option key={u.id} value={String(u.id)}>
                                    {u.nama}
                                </option>
                            ))}
                        </Select>
                    )}
                </div>
            </div>

            {/* Main Table */}
            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[850px] text-left text-xs sm:text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 bg-slate-50/80 text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400">
                                <th className="w-12 px-4 py-3.5 text-center">
                                    <input
                                        type="checkbox"
                                        checked={allSelectedOnPage}
                                        onChange={toggleAll}
                                        aria-label="Pilih semua baris pada halaman ini"
                                        className="accent-primary h-4 w-4 cursor-pointer rounded dark:border-slate-600"
                                    />
                                </th>
                                <th className="px-4 py-3.5">Kode Klausul</th>
                                <th className="px-4 py-3.5">Kontrol / Klausul</th>
                                <th className="px-4 py-3.5">Unit Kerja</th>
                                <th className="px-4 py-3.5">PIC</th>
                                <th className="px-4 py-3.5">Status PIC</th>
                                <th className="px-4 py-3.5">Bukti (Evidence)</th>
                                <th className="px-4 py-3.5">Status Verifikasi</th>
                                <th className="px-4 py-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {items.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="px-4 py-12">
                                        <EmptyState message="Tidak ada entri checklist yang cocok dengan kriteria filter ini." />
                                    </td>
                                </tr>
                            ) : (
                                items.map((entry) => {
                                    const code = entry.control?.kode_klausul ?? '—';
                                    const title = entry.control?.judul ?? t('common.noData');
                                    const framework = entry.control?.framework
                                        ? `${entry.control.framework.nama}${entry.control.framework.versi ? ` ${entry.control.framework.versi}` : ''}`
                                        : '';
                                    const hasEvidence = Boolean(entry.active_evidence?.file_url);
                                    const isChecked = selectedIds.has(entry.id);
                                    const isPanelActive = activeEntry?.id === entry.id;

                                    return (
                                        <tr
                                            key={entry.id}
                                            id={`verify-row-${entry.id}`}
                                            onClick={() => setActiveEntry(isPanelActive ? null : entry)}
                                            className={`cursor-pointer transition-all ${
                                                isChecked
                                                    ? 'bg-primary-50/70 dark:bg-navy-900/40'
                                                    : isPanelActive
                                                      ? 'bg-primary-50/50 dark:bg-navy-900/30'
                                                      : 'hover:bg-slate-50/60 dark:hover:bg-slate-800/40'
                                            }`}
                                        >
                                            <td className="px-4 py-3 text-center" onClick={(e) => e.stopPropagation()}>
                                                <input
                                                    type="checkbox"
                                                    checked={isChecked}
                                                    onChange={() => toggleOne(entry.id)}
                                                    aria-label={`Pilih baris ${code}`}
                                                    className="accent-primary h-4 w-4 cursor-pointer rounded dark:border-slate-600"
                                                />
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs font-bold text-slate-900 dark:text-white">{code}</td>
                                            <td className="max-w-[280px] px-4 py-3">
                                                <p className="truncate font-semibold text-slate-900 dark:text-white">{title}</p>
                                                {framework && <p className="truncate text-[11px] text-slate-400">{framework}</p>}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600 dark:text-slate-300">{entry.unit?.nama ?? '—'}</td>
                                            <td className="px-4 py-3 text-slate-600 dark:text-slate-300">{entry.pic?.name ?? '—'}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge tone={statusTone(entry.status)}>
                                                    {t(`status.${entry.status ?? 'pending'}` as never)}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
                                                {hasEvidence ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setPreviewEvidence({
                                                                file_url: entry.active_evidence!.file_url,
                                                                nama_file: entry.active_evidence?.nama_file || `${code} Evidence`,
                                                            })
                                                        }
                                                        className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                                                    >
                                                        <Eye className="h-3 w-3" />
                                                        <span>Lihat Bukti</span>
                                                    </button>
                                                ) : (
                                                    <span className="text-xs text-slate-400">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {entry.tanggal_verifikasi ? (
                                                    <div className="flex flex-col">
                                                        <span className="text-xs font-semibold text-slate-900 dark:text-slate-200">
                                                            {fmtDate(entry.tanggal_verifikasi)}
                                                        </span>
                                                        {entry.admin?.name && (
                                                            <span className="text-[11px] text-slate-400">oleh {entry.admin.name}</span>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-xs font-semibold text-amber-600 dark:text-amber-400">
                                                        Belum Diverifikasi
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <button
                                                    type="button"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        setActiveEntry(isPanelActive ? null : entry);
                                                    }}
                                                    className={`rounded-xl border px-3 py-1.5 text-xs font-semibold transition-all ${
                                                        isPanelActive
                                                            ? 'border-primary bg-primary text-white shadow-sm'
                                                            : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
                                                    }`}
                                                >
                                                    {isPanelActive ? 'Meninjau' : 'Tinjau'}
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
                    startIndex={(page.from ?? 1) - 1}
                    endIndex={page.to ?? page.total}
                    onPageChange={(p) => {
                        router.get(
                            '/admin/kepatuhan/checklist/verify',
                            {
                                search: searchQuery || undefined,
                                status: selectedStatus !== 'all' ? selectedStatus : undefined,
                                unit_id: selectedUnit !== 'all' ? selectedUnit : undefined,
                                session_id: selectedSessionId || undefined,
                                is_verified: verification === 'all' ? undefined : verification === 'verified' ? '1' : '0',
                                page: p,
                            },
                            { preserveState: true, replace: true },
                        );
                    }}
                />
            </div>

            {/* ─── Modern Floating Action Dock (Bright in Light Mode, Sidebar Navy in Dark Mode) ─── */}
            {selectedIds.size > 0 && can('checklist.bulk-verify') && (
                <div className="animate-in fade-in slide-in-from-bottom-4 fixed bottom-6 left-1/2 z-40 -translate-x-1/2 duration-200">
                    <div className="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white/95 px-4 py-2.5 shadow-2xl backdrop-blur-md sm:gap-3 dark:border-slate-800 dark:bg-slate-900/95">
                        <div className="flex items-center gap-2 border-r border-slate-200 pr-3 dark:border-slate-700">
                            <span className="bg-primary-100 text-primary-700 border-primary-200 dark:bg-navy-900 dark:text-primary-200 dark:border-primary-800 flex h-6 w-6 items-center justify-center rounded-full border text-xs font-bold">
                                {selectedIds.size}
                            </span>
                            <span className="hidden text-xs font-medium text-slate-500 sm:inline dark:text-slate-400">terpilih</span>
                        </div>

                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => openBulkConfirm('approve')}
                                className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-emerald-500 active:scale-95"
                            >
                                <CheckCircle2 className="h-3.5 w-3.5" />
                                Setujui (Patuh)
                            </button>

                            <button
                                type="button"
                                onClick={() => openBulkConfirm('reject')}
                                className="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-rose-500 active:scale-95"
                            >
                                <XCircle className="h-3.5 w-3.5" />
                                Tolak (Tidak Patuh)
                            </button>

                            <button
                                type="button"
                                onClick={clearSelection}
                                className="rounded-xl p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                title="Batal pilih"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Slide-over detail panel for single review */}
            <DetailPanel entry={activeEntry} onClose={() => setActiveEntry(null)} onPreviewEvidence={(evidence) => setPreviewEvidence(evidence)} />

            {/* In-App Evidence Preview Lightbox Modal */}
            <Modal
                open={previewEvidence !== null}
                title={previewEvidence?.nama_file || 'Pratinjau Bukti Dokumen'}
                description="Pratinjau langsung bukti kepatuhan yang dilampirkan"
                onClose={() => setPreviewEvidence(null)}
                maxWidth="xl"
                footer={
                    <div className="flex w-full items-center justify-between">
                        {previewEvidence && (
                            <a
                                href={previewEvidence.file_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-primary hover:text-primary-700 dark:text-primary-200 inline-flex items-center gap-1.5 text-xs font-semibold"
                            >
                                <ExternalLink className="h-3.5 w-3.5" />
                                Buka di Tab Baru
                            </a>
                        )}
                        <button
                            type="button"
                            onClick={() => setPreviewEvidence(null)}
                            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        >
                            Tutup
                        </button>
                    </div>
                }
            >
                {previewEvidence && (
                    <div className="flex flex-col items-center justify-center">
                        {isImageFile(previewEvidence.nama_file) ? (
                            <div
                                className={`relative flex max-h-[65vh] w-full items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 p-2 dark:border-slate-800 dark:bg-slate-950 ${evidenceLoading ? 'min-h-[60vh]' : ''}`}
                            >
                                {evidenceLoading && (
                                    <div className="absolute inset-0 flex items-center justify-center">
                                        <Loader2 className="h-8 w-8 animate-spin text-slate-400" />
                                    </div>
                                )}
                                <img
                                    src={previewEvidence.file_url}
                                    alt={previewEvidence.nama_file}
                                    onLoad={() => setEvidenceLoading(false)}
                                    onError={() => setEvidenceLoading(false)}
                                    className={`max-h-[60vh] max-w-full rounded-lg object-contain transition-opacity ${evidenceLoading ? 'opacity-0' : 'opacity-100'}`}
                                />
                            </div>
                        ) : (
                            <div className="h-[55vh] w-full overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                                <iframe
                                    src={previewEvidence.file_url}
                                    title={previewEvidence.nama_file || 'Dokumen Evidence'}
                                    className="h-full w-full"
                                />
                            </div>
                        )}
                    </div>
                )}
            </Modal>

            {/* Bulk Verification Modal Dialog with Optional/Required Admin Notes */}
            {bulkConfirmAction !== null &&
                createPortal(
                    <div className="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-[2px] duration-150">
                        <div className="w-full max-w-[480px] rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                            <div className="mb-4 flex items-center gap-3">
                                <div
                                    className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${
                                        bulkConfirmAction === 'approve'
                                            ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400'
                                            : 'bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400'
                                    }`}
                                >
                                    {bulkConfirmAction === 'approve' ? <CheckCircle2 className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
                                </div>
                                <div>
                                    <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                        {bulkConfirmAction === 'approve' ? 'Setujui Entri Terpilih?' : 'Tolak Entri Terpilih?'}
                                    </h3>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        {selectedIds.size} entri kontrol checklist akan ditandai sebagai{' '}
                                        <span className="font-semibold text-slate-900 dark:text-white">
                                            {bulkConfirmAction === 'approve' ? 'Patuh' : 'Tidak Patuh'}
                                        </span>
                                        .
                                    </p>
                                </div>
                            </div>

                            {bulkConfirmAction !== 'approve' && (
                                <div className="mb-5 space-y-1">
                                    <Textarea
                                        label="Catatan Verifikasi Admin"
                                        value={bulkAdminNote}
                                        onChange={(e) => {
                                            setBulkAdminNote(e.target.value);
                                            if (bulkNoteError && e.target.value.trim()) setBulkNoteError(null);
                                        }}
                                        placeholder="Tuliskan alasan penolakan dan arahan tindak lanjut yang akan disertakan pada seluruh entri terpilih..."
                                        rows={3}
                                        hint="Wajib diisi saat menolak entri."
                                    />
                                    {bulkNoteError && <p className="text-xs font-semibold text-rose-600 dark:text-rose-400">{bulkNoteError}</p>}
                                </div>
                            )}

                            <div className="flex items-center justify-end gap-2.5">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setBulkConfirmAction(null);
                                        setBulkNoteError(null);
                                    }}
                                    className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                                >
                                    Batal
                                </button>
                                <button
                                    type="button"
                                    disabled={bulkBusy}
                                    onClick={submitBulkDecision}
                                    className={`inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-sm transition-all active:scale-95 ${
                                        bulkConfirmAction === 'approve' ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-rose-600 hover:bg-rose-500'
                                    } disabled:opacity-50`}
                                >
                                    {bulkBusy ? 'Memproses…' : 'Konfirmasi & Simpan'}
                                </button>
                            </div>
                        </div>
                    </div>,
                    document.body,
                )}
        </AppLayout>
    );
}
