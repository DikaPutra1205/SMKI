import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import { StatusBadge, statusTone } from '@/components/ui/StatusBadge';
import { Textarea } from '@/components/ui/Textarea';
import { Toast } from '@/components/ui/Toast';
import AppLayout from '@/layouts/AppLayout';
import { t } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, CheckSquare, FileText, Search, Square, X, XCircle } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface ControlRef {
    id: number;
    kode_klausul: string;
    judul: string;
    kategori: string;
    framework?: { id: number; nama: string; versi: string } | null;
}

export interface BulkVerifyEntry {
    id: number;
    status: string | null;
    catatan: string | null;
    tanggal_input: string | null;
    tanggal_verifikasi: string | null;
    catatan_admin?: string | null;
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

interface BulkVerifyProps {
    entries?: Paginator<BulkVerifyEntry>;
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

const STATUS_OPTIONS = ['compliant', 'partial', 'non_compliant', 'na'] as const;

type ConfirmAction = 'approve' | 'reject' | null;

function fmtDate(value: string | null): string {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function BulkVerify({ entries, workUnits = [], filters = {} }: BulkVerifyProps) {
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

    // ── Selection-mode state (photo-picker pattern) ───────────────────────────
    const [selectionMode, setSelectionMode] = useState(false);
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [confirmAction, setConfirmAction] = useState<ConfirmAction>(null);
    const [adminNote, setAdminNote] = useState('');
    const [busy, setBusy] = useState(false);

    const form = useForm({ entry_ids: [] as number[], status: '', admin_notes: '' });

    /** Exit selection mode and deselect everything. */
    function exitSelectionMode() {
        setSelectionMode(false);
        setSelectedIds(new Set());
        setAdminNote('');
        setConfirmAction(null);
    }

    useEffect(() => {
        if (flash?.message) {
            setFlashVisible(true);
            const timer = setTimeout(() => setFlashVisible(false), 4000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                '/admin/kepatuhan/checklist/bulk-verify',
                {
                    search: searchQuery || undefined,
                    status: selectedStatus !== 'all' ? selectedStatus : undefined,
                    unit_id: selectedUnit !== 'all' ? selectedUnit : undefined,
                    is_verified: verification === 'all' ? undefined : verification === 'verified' ? '1' : '0',
                    session_id: selectedSessionId || undefined,
                },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [searchQuery, selectedStatus, selectedUnit, verification, selectedSessionId]);

    const pageIds = items.map((i) => i.id);
    const allSelectedOnPage = selectionMode && pageIds.length > 0 && pageIds.every((id) => selectedIds.has(id));

    function toggleAll() {
        if (!selectionMode) return;
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
        if (!selectionMode) return;
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    }

    function openConfirm(action: Exclude<ConfirmAction, null>) {
        if (selectedIds.size === 0) return;
        setConfirmAction(action);
    }

    function submitDecision() {
        if (!confirmAction || selectedIds.size === 0) return;
        setBusy(true);
        form.setData({
            entry_ids: Array.from(selectedIds),
            status: confirmAction === 'approve' ? 'compliant' : 'non_compliant',
            admin_notes: adminNote,
        });
        form.post('/admin/kepatuhan/bulk-verify', {
            preserveScroll: true,
            onSuccess: () => {
                setBusy(false);
                exitSelectionMode();
            },
            onError: () => setBusy(false),
        });
    }

    const breadcrumbs = selectedSessionId
        ? [
              { label: t('common.dashboard'), href: '/admin/kepatuhan/dashboard' },
              { label: t('bulkVerify.title'), href: '/admin/kepatuhan/checklist/bulk-verify' },
              { label: 'Detail Sesi' },
          ]
        : [{ label: t('common.dashboard'), href: '/admin/kepatuhan/dashboard' }, { label: t('bulkVerify.title') }];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/kepatuhan/checklist/bulk-verify">
            <Head title={t('bulkVerify.title')} />

            <Toast
                visible={flashVisible}
                tone={flash?.type === 'error' ? 'error' : 'success'}
                message={flash?.message}
                onDismiss={() => setFlashVisible(false)}
            />

            {/* ── Page header + "Pilih" toggle ── */}
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('bulkVerify.title')}</h1>
                    <p className="text-muted text-sm">{t('bulkVerify.subtitle')}</p>
                </div>

                {/* Photo-picker style: only one button visible at a time */}
                {!selectionMode ? (
                    <button
                        type="button"
                        onClick={() => setSelectionMode(true)}
                        className="bg-primary hover:bg-primary/90 inline-flex items-center gap-2 self-start rounded-[10px] px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors sm:self-auto"
                    >
                        <CheckSquare className="h-4 w-4" />
                        Pilih / Verifikasi Massal
                    </button>
                ) : (
                    <button
                        type="button"
                        onClick={exitSelectionMode}
                        className="border-border-strong hover:bg-surface text-body inline-flex items-center gap-2 self-start rounded-[10px] border bg-white px-4 py-2 text-sm font-semibold shadow-sm transition-colors sm:self-auto"
                    >
                        <X className="h-4 w-4" />
                        Batal
                    </button>
                )}
            </div>

            {/* ── Selection-mode active banner ── */}
            {selectionMode && (
                <div className="border-primary/30 bg-primary/5 flex items-center gap-3 rounded-[12px] border px-4 py-3">
                    <Square className="text-primary h-4 w-4 shrink-0" />
                    <p className="text-navy text-sm font-medium">
                        Mode pilih aktif — centang baris yang ingin diverifikasi, lalu pilih tindakan di bawah.
                    </p>
                </div>
            )}

            {/* ── Filter bar ── */}
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
                        <option value="all">{t('bulkVerify.allVerified')}</option>
                        <option value="unverified">{t('bulkVerify.unverified')}</option>
                        <option value="verified">{t('bulkVerify.verified')}</option>
                    </Select>
                </div>
            </div>

            {/* ── Bulk action bar — only visible in selection mode with items selected ── */}
            {selectionMode && selectedIds.size > 0 && (
                <div className="border-primary/30 bg-primary/5 rounded-[14px] border p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <span className="text-navy text-sm font-semibold">{t('bulkVerify.selected', selectedIds.size)}</span>
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => openConfirm('approve')}
                                className="bg-success hover:bg-success/90 inline-flex items-center gap-2 rounded-[10px] px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors sm:text-sm"
                            >
                                <CheckCircle2 className="h-4 w-4" />
                                {t('bulkVerify.approveSelected')}
                            </button>
                            <button
                                type="button"
                                onClick={() => openConfirm('reject')}
                                className="bg-danger hover:bg-danger/90 inline-flex items-center gap-2 rounded-[10px] px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors sm:text-sm"
                            >
                                <XCircle className="h-4 w-4" />
                                {t('bulkVerify.rejectSelected')}
                            </button>
                        </div>
                    </div>
                    <div className="mt-3">
                        <Textarea
                            label={t('bulkVerify.notesLabel')}
                            value={adminNote}
                            onChange={(e) => setAdminNote(e.target.value)}
                            placeholder={t('bulkVerify.notesPlaceholder')}
                            rows={2}
                        />
                    </div>
                </div>
            )}

            {/* ── Table ── */}
            <div className="border-border overflow-hidden rounded-[14px] border bg-white">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[900px] text-left text-sm">
                        <thead>
                            <tr className="border-border bg-surface/60 text-muted border-b text-xs">
                                {/* Checkbox col: only shown in selection mode */}
                                {selectionMode && (
                                    <th className="w-10 px-4 py-3">
                                        <input
                                            type="checkbox"
                                            checked={allSelectedOnPage}
                                            onChange={toggleAll}
                                            aria-label={t('bulkVerify.selectAll')}
                                            className="border-border-strong accent-primary h-4 w-4 rounded"
                                        />
                                    </th>
                                )}
                                <th className="px-4 py-3 font-semibold">{t('bulkVerify.code')}</th>
                                <th className="px-4 py-3 font-semibold">{t('bulkVerify.control')}</th>
                                <th className="px-4 py-3 font-semibold">{t('bulkVerify.workUnit')}</th>
                                <th className="px-4 py-3 font-semibold">{t('bulkVerify.pic')}</th>
                                <th className="px-4 py-3 font-semibold">{t('bulkVerify.status')}</th>
                                <th className="px-4 py-3 font-semibold">{t('bulkVerify.evidence')}</th>
                                <th className="px-4 py-3 font-semibold">{t('bulkVerify.verifiedBy')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.length === 0 ? (
                                <tr>
                                    <td colSpan={selectionMode ? 9 : 8} className="px-4 py-10">
                                        <EmptyState message={t('bulkVerify.noEntries')} />
                                    </td>
                                </tr>
                            ) : (
                                items.map((entry) => {
                                    const code = entry.control?.kode_klausul ?? '';
                                    const title = entry.control?.judul ?? '';
                                    const framework = entry.control?.framework
                                        ? `${entry.control.framework.nama}${entry.control.framework.versi ? `:${entry.control.framework.versi}` : ''}`
                                        : '';
                                    const hasEvidence = Boolean(entry.active_evidence?.file_url);
                                    const isChecked = selectedIds.has(entry.id);

                                    return (
                                        <tr
                                            key={entry.id}
                                            onClick={() => selectionMode && toggleOne(entry.id)}
                                            className={`border-border border-b transition-colors last:border-0 ${
                                                selectionMode
                                                    ? isChecked
                                                        ? 'bg-primary/5 cursor-pointer'
                                                        : 'hover:bg-surface/50 cursor-pointer'
                                                    : 'hover:bg-surface/50'
                                            }`}
                                        >
                                            {/* Checkbox cell — only in selection mode */}
                                            {selectionMode && (
                                                <td className="px-4 py-3">
                                                    <input
                                                        type="checkbox"
                                                        checked={isChecked}
                                                        onChange={() => toggleOne(entry.id)}
                                                        onClick={(e) => e.stopPropagation()}
                                                        className="border-border-strong accent-primary h-4 w-4 rounded"
                                                    />
                                                </td>
                                            )}
                                            <td className="text-navy px-4 py-3 font-mono text-xs font-semibold">{code}</td>
                                            <td className="max-w-[280px] px-4 py-3">
                                                <p className="text-navy truncate font-semibold">{title}</p>
                                                {framework && <p className="text-muted truncate text-xs">{framework}</p>}
                                            </td>
                                            <td className="text-body px-4 py-3">{entry.unit?.nama ?? t('bulkVerify.none')}</td>
                                            <td className="text-body px-4 py-3">{entry.pic?.name ?? t('bulkVerify.none')}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge tone={statusTone(entry.status)}>
                                                    {t(`status.${entry.status ?? 'pending'}` as never)}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-4 py-3">
                                                {hasEvidence ? (
                                                    <a
                                                        href={entry.active_evidence?.file_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        onClick={(e) => e.stopPropagation()}
                                                        className="text-primary inline-flex items-center gap-1.5 text-xs font-semibold hover:underline"
                                                    >
                                                        <FileText className="h-3.5 w-3.5" />
                                                        {t('bulkVerify.openEvidence')}
                                                    </a>
                                                ) : (
                                                    <span className="text-muted text-xs">{t('bulkVerify.none')}</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {entry.tanggal_verifikasi ? (
                                                    <span className="text-muted text-xs">
                                                        {t('bulkVerify.verifiedOn', fmtDate(entry.tanggal_verifikasi))}
                                                        {entry.admin?.name ? ` · ${entry.admin.name}` : ''}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted text-xs">{t('bulkVerify.none')}</span>
                                                )}
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
                        router.get('/admin/kepatuhan/checklist/bulk-verify', { page: p > 1 ? p : undefined }, { preserveState: true, replace: true })
                    }
                />
            </div>

            <ConfirmDialog
                open={confirmAction !== null}
                title={confirmAction === 'approve' ? t('bulkVerify.confirmApproveTitle') : t('bulkVerify.confirmRejectTitle')}
                description={
                    confirmAction === 'approve'
                        ? t('bulkVerify.confirmApproveDesc', selectedIds.size)
                        : t('bulkVerify.confirmRejectDesc', selectedIds.size)
                }
                confirmLabel={confirmAction === 'approve' ? t('bulkVerify.approve') : t('bulkVerify.reject')}
                variant={confirmAction === 'approve' ? 'info' : 'danger'}
                busy={busy}
                onCancel={() => setConfirmAction(null)}
                onConfirm={submitDecision}
            />
        </AppLayout>
    );
}
