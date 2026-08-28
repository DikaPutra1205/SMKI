import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import AppLayout from '@/layouts/AppLayout';
import { useCan } from '@/lib/can';
import { t } from '@/lib/i18n';
import { formatDateIndonesian, formatPeriodeIndonesian } from '@/lib/utils';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, CheckCircle2, Pencil, Plus, Search, Send, ShieldCheck, Trash2, UserRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface SessionItem {
    id: number;
    konteks_penilaian: string;
    periode: string;
    unit_id: number;
    unit_nama: string;
    framework_id: number | null;
    framework_nama: string;
    creator_id: number | null;
    creator_name: string;
    catatan: string | null;
    total_entries: number;
    compliant_entries: number;
    partial_entries: number;
    non_compliant_entries: number;
    na_entries: number;
    verified_entries: number;
    compliance_percentage: number;
    created_at: string;
    updated_at: string;
}

interface WorkUnit {
    id: number;
    nama: string;
}

interface FrameworkItem {
    id: number;
    nama: string;
    versi: string;
}

interface SessionsProps {
    sessions: SessionItem[];
    workUnits: WorkUnit[];
    frameworks: FrameworkItem[];
    periodeOptions: string[];
    filters: Record<string, string>;
}

type ModalMode = 'create' | 'edit' | null;

type SessionFormData = {
    unit_id: string;
    framework_id: string;
    periode: string;
    konteks_penilaian: string;
    catatan: string;
};

export default function Sessions({ sessions, workUnits, frameworks, periodeOptions, filters }: SessionsProps) {
    const can = useCan();
    const { flash } = usePage<{ flash?: { type: string; message: string } }>().props;
    const [flashVisible, setFlashVisible] = useState(false);

    const [search, setSearch] = useState(filters.search || '');
    const [unitId, setUnitId] = useState(filters.unit_id || '');
    const [frameworkId, setFrameworkId] = useState(filters.framework_id || '');
    const [periode, setPeriode] = useState(filters.periode || '');

    const [perPage, setPerPage] = useState<number | 'all'>(12);
    const [currentPage, setCurrentPage] = useState(1);
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (flash?.message) {
            setFlashVisible(true);
            const timer = setTimeout(() => setFlashVisible(false), 4000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    useEffect(() => {
        setCurrentPage(1);
    }, [search, unitId, frameworkId, periode, perPage]);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                '/admin/kepatuhan/sessions',
                {
                    search: search || undefined,
                    unit_id: unitId || undefined,
                    framework_id: frameworkId || undefined,
                    periode: periode || undefined,
                },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [search, unitId, frameworkId, periode]);

    // ── Create / Edit modal ──────────────────────────────────────────────────
    const [modalMode, setModalMode] = useState<ModalMode>(null);
    const [editingId, setEditingId] = useState<number | null>(null);

    const form = useForm<SessionFormData>({
        unit_id: '',
        framework_id: '',
        periode: new Date().toISOString().slice(0, 7),
        konteks_penilaian: '',
        catatan: '',
    });

    function openCreate() {
        form.reset();
        form.clearErrors();
        form.setData('periode', new Date().toISOString().slice(0, 7));
        setEditingId(null);
        setModalMode('create');
    }

    function openEdit(item: SessionItem) {
        form.setData({
            unit_id: String(item.unit_id),
            framework_id: item.framework_id ? String(item.framework_id) : '',
            periode: item.periode || '',
            konteks_penilaian: item.konteks_penilaian,
            catatan: item.catatan ?? '',
        });
        form.clearErrors();
        setEditingId(item.id);
        setModalMode('edit');
    }

    function closeModal() {
        setModalMode(null);
        setEditingId(null);
        form.reset();
        form.clearErrors();
    }

    function submitForm(e: React.FormEvent) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            framework_id: data.framework_id || null,
        }));
        if (modalMode === 'create') {
            form.post('/admin/kepatuhan/checklist-sessions', {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        } else if (modalMode === 'edit' && editingId !== null) {
            form.patch(`/admin/kepatuhan/checklist-sessions/${editingId}`, {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        }
    }

    // ── Delete ───────────────────────────────────────────────────────────────
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<SessionItem | null>(null);
    const [deleteBusy, setDeleteBusy] = useState(false);

    // ── Generate Bulanan ──────────────────────────────────────────────────────
    const [generateDialogOpen, setGenerateDialogOpen] = useState(false);
    const [generateBusy, setGenerateBusy] = useState(false);

    function confirmGenerateMonthly() {
        setGenerateBusy(true);
        router.post(
            '/admin/kepatuhan/generate-monthly',
            {},
            {
                onFinish: () => {
                    setGenerateBusy(false);
                    setGenerateDialogOpen(false);
                },
            },
        );
    }

    function handleDelete(item: SessionItem) {
        setDeleteTarget(item);
        setDeleteDialogOpen(true);
    }

    function confirmDelete() {
        if (!deleteTarget) return;
        setDeleteBusy(true);
        router.delete(`/admin/kepatuhan/checklist-sessions/${deleteTarget.id}`, {
            onFinish: () => {
                setDeleteBusy(false);
                setDeleteDialogOpen(false);
                setDeleteTarget(null);
            },
        });
    }

    function cancelDelete() {
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    }

    const totalItems = sessions.length;
    const effectivePerPage = perPage === 'all' ? totalItems || 1 : perPage;
    const totalPages = perPage === 'all' || totalItems === 0 ? 1 : Math.ceil(totalItems / effectivePerPage);
    const safeCurrentPage = Math.min(Math.max(1, currentPage), totalPages);
    const startIndex = totalItems === 0 ? 0 : (safeCurrentPage - 1) * effectivePerPage;
    const endIndex = perPage === 'all' ? totalItems : Math.min(startIndex + effectivePerPage, totalItems);
    const paginatedSessions = perPage === 'all' ? sessions : sessions.slice(startIndex, endIndex);

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/admin/kepatuhan/dashboard' }, { label: 'Manajemen Sesi Checklist' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/kepatuhan/sessions">
            <Head title="Manajemen Sesi Checklist - Admin Kepatuhan" />

            {flash?.message && flashVisible && (
                <div className="border-border mb-4 flex items-center gap-2 rounded-lg border px-4 py-3 text-sm font-medium shadow-sm dark:border-slate-700">
                    {flash.type === 'success' ? (
                        <div className="text-success flex items-center gap-2 dark:text-emerald-400">
                            <CheckCircle2 className="h-4 w-4" />
                            {flash.message}
                        </div>
                    ) : (
                        <div className="text-danger flex items-center gap-2 dark:text-red-400">
                            <Send className="h-4 w-4" />
                            {flash.message}
                        </div>
                    )}
                </div>
            )}

            <div className="page-head flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Manajemen Sesi Checklist</h1>
                    <p className="text-muted mt-1 text-xs sm:text-sm dark:text-slate-400">
                        Buat dan kelola sesi pengecekan mandiri untuk setiap satuan kerja.
                    </p>
                </div>
                {can('checklist-session.create') && (
                    <>
                        <button
                            type="button"
                            onClick={() => setGenerateDialogOpen(true)}
                            className="border-border-strong text-navy hover:bg-surface inline-flex items-center gap-2 rounded-[10px] border bg-white px-4 py-2 text-xs font-semibold transition-colors sm:text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800"
                        >
                            <CalendarClock className="h-4 w-4" />
                            <span>Generate Bulanan</span>
                        </button>
                        <button
                            type="button"
                            onClick={openCreate}
                            className="bg-primary shadow-blue hover:bg-primary-700 inline-flex items-center gap-2 rounded-[10px] px-4 py-2 text-xs font-semibold text-white transition-colors sm:text-sm"
                        >
                            <Plus className="h-4 w-4" />
                            <span>Buat Sesi</span>
                        </button>
                    </>
                )}
            </div>

            {/* Toolbar */}
            <div className="border-border flex flex-col gap-3 rounded-[14px] border bg-white p-3 shadow-sm md:flex-row md:items-center dark:border-slate-700 dark:bg-slate-900">
                <div className="relative min-w-[220px] flex-1">
                    <Search className="text-faint absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 dark:text-slate-500" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari konteks, unit, atau PIC..."
                        className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 h-10 w-full rounded-[10px] border bg-white py-2 pr-4 pl-9 text-xs focus:ring-2 focus:outline-none sm:text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                    />
                </div>

                <Select value={unitId} onChange={(e) => setUnitId(e.target.value)} className="min-w-[170px]">
                    <option value="">Semua Unit</option>
                    {workUnits.map((u) => (
                        <option key={u.id} value={String(u.id)}>
                            {u.nama}
                        </option>
                    ))}
                </Select>

                <Select value={frameworkId} onChange={(e) => setFrameworkId(e.target.value)} className="min-w-[170px]">
                    <option value="">Semua Framework</option>
                    {frameworks.map((f) => (
                        <option key={f.id} value={String(f.id)}>
                            {f.nama} ({f.versi})
                        </option>
                    ))}
                </Select>

                <Select value={periode} onChange={(e) => setPeriode(e.target.value)} className="min-w-[140px]">
                    <option value="">Semua Periode</option>
                    {periodeOptions.map((p) => (
                        <option key={p} value={p}>
                            {formatPeriodeIndonesian(p)}
                        </option>
                    ))}
                </Select>
            </div>

            {/* Sessions */}
            {paginatedSessions.length === 0 ? (
                <EmptyState message="Belum ada sesi checklist yang cocok dengan filter ini." />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {paginatedSessions.map((s) => {
                        const pct = s.compliance_percentage;
                        const total = s.total_entries || 0;
                        const compliantPct = total > 0 ? (s.compliant_entries / total) * 100 : 0;
                        const partialPct = total > 0 ? ((s.partial_entries || 0) / total) * 100 : 0;
                        const nonCompliantPct = total > 0 ? (s.non_compliant_entries / total) * 100 : 0;
                        const naPct = total > 0 ? (s.na_entries / total) * 100 : 0;

                        return (
                            <div
                                key={s.id}
                                className="border-border group hover:border-primary-200 flex flex-col rounded-[14px] border bg-white p-5 shadow-sm transition-all hover:shadow-md dark:border-slate-700 dark:bg-slate-900"
                            >
                                <div className="mb-3">
                                    <h3 className="text-navy truncate text-sm leading-snug font-bold dark:text-white">{s.konteks_penilaian}</h3>
                                    <p className="text-faint mt-0.5 text-xs dark:text-slate-500">
                                        {s.periode ? formatPeriodeIndonesian(s.periode) : 'Tanpa Periode'}
                                    </p>
                                </div>

                                <div className="text-muted mb-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs dark:text-slate-400">
                                    <span className="inline-flex items-center gap-1.5">
                                        <UserRound className="text-faint h-3.5 w-3.5 dark:text-slate-500" />
                                        {s.unit_nama || 'Unit tidak diketahui'}
                                    </span>
                                    {s.creator_name && <span className="text-faint dark:text-slate-500">oleh {s.creator_name}</span>}
                                </div>

                                {s.framework_nama && (
                                    <div className="mb-3">
                                        <span className="border-border text-body bg-surface inline-flex items-center rounded-[6px] border px-2.5 py-1 text-[11px] font-semibold dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                                            {s.framework_nama}
                                        </span>
                                    </div>
                                )}

                                <div className="mb-3">
                                    <div className="mb-1 flex items-baseline justify-between">
                                        <span className="text-muted text-xs dark:text-slate-400">
                                            {s.compliant_entries}/{s.total_entries} Patuh
                                        </span>
                                        <span className="text-primary text-xs font-bold">{pct}%</span>
                                    </div>
                                    <div className="flex h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        {compliantPct > 0 && (
                                            <div
                                                className="h-full bg-emerald-500 transition-all duration-500"
                                                style={{ width: `${compliantPct}%` }}
                                            />
                                        )}
                                        {partialPct > 0 && (
                                            <div className="h-full bg-amber-500 transition-all duration-500" style={{ width: `${partialPct}%` }} />
                                        )}
                                        {nonCompliantPct > 0 && (
                                            <div className="h-full bg-red-500 transition-all duration-500" style={{ width: `${nonCompliantPct}%` }} />
                                        )}
                                        {naPct > 0 && (
                                            <div
                                                className="h-full bg-slate-300 transition-all duration-500 dark:bg-slate-600"
                                                style={{ width: `${naPct}%` }}
                                            />
                                        )}
                                    </div>
                                </div>

                                {(can('checklist-session.update') || can('checklist-session.delete')) && (
                                    <div className="border-border text-faint mt-auto flex flex-wrap items-center justify-end gap-1.5 border-t pt-3 dark:border-slate-700">
                                        {can('checklist-session.update') && (
                                            <button
                                                type="button"
                                                onClick={() => openEdit(s)}
                                                className="border-border-strong text-navy hover:bg-surface inline-flex items-center gap-1.5 rounded-[10px] border bg-white px-3 py-1.5 text-xs font-semibold transition-colors dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800"
                                            >
                                                <Pencil className="h-3.5 w-3.5" />
                                                Edit
                                            </button>
                                        )}
                                        {can('checklist-session.delete') && (
                                            <button
                                                type="button"
                                                onClick={() => handleDelete(s)}
                                                className="border-danger-border bg-danger-bg text-danger hover:bg-danger/10 inline-flex items-center gap-1.5 rounded-[10px] border px-3 py-1.5 text-xs font-semibold transition-colors dark:border-red-800 dark:text-red-400"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                                Hapus
                                            </button>
                                        )}
                                    </div>
                                )}

                                <div className="border-border text-faint mt-3 flex flex-wrap items-center justify-between gap-2 border-t pt-3 text-[11px] dark:border-slate-700 dark:text-slate-500">
                                    <span className="inline-flex items-center gap-1">
                                        <ShieldCheck className="h-3.5 w-3.5" />
                                        {s.verified_entries}/{s.total_entries} terverifikasi
                                    </span>
                                    <span>{formatDateIndonesian(s.created_at, { shortMonth: true })}</span>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {totalItems > 0 && (
                <Pagination
                    currentPage={safeCurrentPage}
                    totalPages={totalPages}
                    perPage={perPage}
                    totalItems={totalItems}
                    startIndex={startIndex}
                    endIndex={endIndex}
                    onPageChange={setCurrentPage}
                    onPerPageChange={setPerPage}
                />
            )}

            <Modal
                open={modalMode !== null}
                title={modalMode === 'create' ? 'Buat Sesi Checklist' : 'Edit Sesi Checklist'}
                onClose={closeModal}
                maxWidth="lg"
                footer={
                    <>
                        <button
                            type="button"
                            onClick={closeModal}
                            className="border-border-strong text-body hover:bg-surface rounded-[10px] border bg-white px-4 py-2 text-sm font-medium transition-colors dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            form="session-form"
                            disabled={form.processing}
                            className="bg-primary hover:bg-primary-700 inline-flex items-center gap-2 rounded-[10px] px-5 py-2 text-sm font-semibold text-white transition-colors disabled:opacity-50"
                        >
                            {form.processing ? 'Menyimpan...' : modalMode === 'create' ? 'Buat' : 'Simpan'}
                        </button>
                    </>
                }
            >
                <form id="session-form" onSubmit={submitForm} className="space-y-4">
                    <div>
                        <label className="text-body mb-1 block text-xs font-semibold dark:text-slate-300">Unit Kerja</label>
                        <Select value={form.data.unit_id} onChange={(e) => form.setData('unit_id', e.target.value)} disabled={modalMode === 'edit'}>
                            <option value="">Pilih Unit Kerja</option>
                            {workUnits.map((u) => (
                                <option key={u.id} value={String(u.id)}>
                                    {u.nama}
                                </option>
                            ))}
                        </Select>
                        {form.errors.unit_id && <p className="text-danger mt-1 text-[11px] font-medium dark:text-red-400">{form.errors.unit_id}</p>}
                    </div>

                    <div>
                        <label className="text-body mb-1 block text-xs font-semibold dark:text-slate-300">Framework</label>
                        <Select
                            value={form.data.framework_id}
                            onChange={(e) => form.setData('framework_id', e.target.value)}
                            disabled={modalMode === 'edit'}
                        >
                            <option value="">Tanpa Framework</option>
                            {frameworks.map((f) => (
                                <option key={f.id} value={String(f.id)}>
                                    {f.nama} ({f.versi})
                                </option>
                            ))}
                        </Select>
                        {form.errors.framework_id && (
                            <p className="text-danger mt-1 text-[11px] font-medium dark:text-red-400">{form.errors.framework_id}</p>
                        )}
                    </div>

                    <div>
                        <label className="text-body mb-1 block text-xs font-semibold dark:text-slate-300">Periode (YYYY-MM)</label>
                        <input
                            type="text"
                            value={form.data.periode}
                            onChange={(e) => form.setData('periode', e.target.value)}
                            placeholder="2026-08"
                            disabled={modalMode === 'edit'}
                            className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 h-10 w-full rounded-[10px] border bg-white px-3 text-sm focus:ring-2 focus:outline-none disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                        />
                        {form.errors.periode && <p className="text-danger mt-1 text-[11px] font-medium dark:text-red-400">{form.errors.periode}</p>}
                    </div>

                    <div>
                        <label className="text-body mb-1 block text-xs font-semibold dark:text-slate-300">Konteks Penilaian</label>
                        <input
                            type="text"
                            value={form.data.konteks_penilaian}
                            onChange={(e) => form.setData('konteks_penilaian', e.target.value)}
                            placeholder="Penilaian Bulanan SMKI - Agustus 2026"
                            className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 h-10 w-full rounded-[10px] border bg-white px-3 text-sm focus:ring-2 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                        />
                        {form.errors.konteks_penilaian && (
                            <p className="text-danger mt-1 text-[11px] font-medium dark:text-red-400">{form.errors.konteks_penilaian}</p>
                        )}
                    </div>

                    <div>
                        <label className="text-body mb-1 block text-xs font-semibold dark:text-slate-300">Catatan</label>
                        <textarea
                            value={form.data.catatan}
                            onChange={(e) => form.setData('catatan', e.target.value)}
                            rows={3}
                            className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 w-full rounded-[10px] border bg-white px-3 py-2 text-sm focus:ring-2 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                        />
                        {form.errors.catatan && <p className="text-danger mt-1 text-[11px] font-medium dark:text-red-400">{form.errors.catatan}</p>}
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                open={deleteDialogOpen}
                title="Hapus Sesi Checklist"
                description={deleteTarget ? `Hapus sesi "${deleteTarget.konteks_penilaian}" beserta seluruh lembar checklist?` : ''}
                confirmLabel="Hapus"
                cancelLabel="Batal"
                variant="danger"
                busy={deleteBusy}
                onCancel={cancelDelete}
                onConfirm={confirmDelete}
            />

            <ConfirmDialog
                open={generateDialogOpen}
                title="Generate Bulanan"
                description="Buat sesi checklist untuk seluruh satuan kerja periode bulan ini? Sesi yang sudah ada akan dilewati."
                confirmLabel="Generate"
                cancelLabel="Batal"
                busy={generateBusy}
                onCancel={() => setGenerateDialogOpen(false)}
                onConfirm={confirmGenerateMonthly}
            />
        </AppLayout>
    );
}
