import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import { SlideOver } from '@/components/ui/SlideOver';
import AppLayout from '@/layouts/AppLayout';
import { useCan } from '@/lib/can';
import { t } from '@/lib/i18n';
import { formatDateIndonesian, formatPeriodeIndonesian } from '@/lib/utils';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, CalendarClock, CheckCircle2, Pencil, Plus, Search, Send, ShieldCheck, Trash2, UserRound } from 'lucide-react';
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
    selesai_entries: number;
    tinjauan_entries: number;
    proses_entries: number;
    na_entries: number;
    verified_entries: number;
    completion_percentage: number;
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
                <div className="border-border mb-4 flex items-center gap-2 rounded-lg border bg-white px-4 py-3 text-sm font-medium shadow-sm dark:border-slate-700 dark:bg-slate-900">
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

            <div className="page-head flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Manajemen Sesi Checklist</h1>
                    <p className="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                        Buat dan kelola sesi pengecekan mandiri untuk setiap satuan kerja.
                    </p>
                </div>
                {can('checklist-session.create') && (
                    <div className="flex items-center justify-end gap-2.5 sm:shrink-0">
                        <button
                            type="button"
                            onClick={() => setGenerateDialogOpen(true)}
                            className="dark:hover:bg-slate-750 inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-2 text-xs font-semibold text-slate-700 shadow-2xs transition-colors hover:bg-slate-100 sm:text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <CalendarClock className="h-4 w-4 text-slate-500 dark:text-slate-400" />
                            <span>Generate Bulanan</span>
                        </button>
                        <button
                            type="button"
                            onClick={openCreate}
                            className="bg-primary hover:bg-primary-700 inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-xs transition-colors sm:text-sm"
                        >
                            <Plus className="h-4 w-4" />
                            <span>Buat Sesi</span>
                        </button>
                    </div>
                )}
            </div>

            {/* Toolbar */}
            <div className="flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-2xs md:flex-row md:items-center dark:border-slate-800 dark:bg-slate-900">
                <div className="relative min-w-[220px] flex-1">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari konteks, unit, atau PIC..."
                        className="focus:border-primary focus:ring-primary/20 h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2 pr-4 pl-9 text-xs text-slate-900 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:outline-none sm:text-sm dark:border-slate-700 dark:bg-slate-800/50 dark:text-white dark:placeholder:text-slate-500"
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
                        const pct = s.completion_percentage;
                        const total = s.total_entries || 0;
                        const selesaiPct = total > 0 ? (s.selesai_entries / total) * 100 : 0;
                        const tinjauanPct = total > 0 ? ((s.tinjauan_entries || 0) / total) * 100 : 0;
                        const prosesPct = total > 0 ? (s.proses_entries / total) * 100 : 0;
                        const naPct = total > 0 ? (s.na_entries / total) * 100 : 0;

                        return (
                            <div
                                key={s.id}
                                className="group flex flex-col rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs transition-all hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
                            >
                                <div className="mb-3">
                                    <h3 className="truncate text-sm leading-snug font-bold text-slate-900 dark:text-white">{s.konteks_penilaian}</h3>
                                    <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                        {s.periode ? formatPeriodeIndonesian(s.periode) : 'Tanpa Periode'}
                                    </p>
                                </div>

                                <div className="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-600 dark:text-slate-400">
                                    <span className="inline-flex items-center gap-1.5">
                                        <UserRound className="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" />
                                        {s.unit_nama || 'Unit tidak diketahui'}
                                    </span>
                                    {s.creator_name && <span className="text-slate-400 dark:text-slate-500">oleh {s.creator_name}</span>}
                                </div>

                                {s.framework_nama && (
                                    <div className="mb-3">
                                        <span className="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                            {s.framework_nama}
                                        </span>
                                    </div>
                                )}

                                <div className="mb-3">
                                    <div className="mb-1 flex items-baseline justify-between">
                                        <span className="text-xs text-slate-500 dark:text-slate-400">
                                            {s.selesai_entries}/{s.total_entries} Selesai Diterapkan
                                        </span>
                                        <span className="text-primary text-xs font-bold">{pct}%</span>
                                    </div>
                                    <div className="flex h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        {selesaiPct > 0 && (
                                            <div className="h-full bg-emerald-500 transition-all duration-500" style={{ width: `${selesaiPct}%` }} />
                                        )}
                                        {tinjauanPct > 0 && (
                                            <div className="h-full bg-blue-400 transition-all duration-500" style={{ width: `${tinjauanPct}%` }} />
                                        )}
                                        {prosesPct > 0 && (
                                            <div className="h-full bg-amber-500 transition-all duration-500" style={{ width: `${prosesPct}%` }} />
                                        )}
                                        {naPct > 0 && (
                                            <div
                                                className="h-full bg-slate-300 transition-all duration-500 dark:bg-slate-600"
                                                style={{ width: `${naPct}%` }}
                                            />
                                        )}
                                    </div>
                                </div>

                                <div className="mt-auto border-t border-slate-100 pt-3 dark:border-slate-800">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <Link
                                            href={`/admin/kepatuhan/checklist/verify?session_id=${s.id}`}
                                            className="text-primary hover:text-primary-700 dark:text-primary-300 dark:hover:text-primary-200 inline-flex items-center gap-1 text-xs font-semibold"
                                        >
                                            <span>Verifikasi Kontrol</span>
                                            <ArrowRight className="h-3.5 w-3.5" />
                                        </Link>

                                        {(can('checklist-session.update') || can('checklist-session.delete')) && (
                                            <div className="flex items-center gap-1.5">
                                                {can('checklist-session.update') && (
                                                    <button
                                                        type="button"
                                                        onClick={() => openEdit(s)}
                                                        className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                                    >
                                                        <Pencil className="h-3 w-3" />
                                                        Edit
                                                    </button>
                                                )}
                                                {can('checklist-session.delete') && (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDelete(s)}
                                                        className="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-700 transition-colors hover:bg-rose-100 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-900/60"
                                                    >
                                                        <Trash2 className="h-3 w-3" />
                                                        Hapus
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                    </div>

                                    <div className="mt-2.5 flex items-center justify-between text-[11px] text-slate-400 dark:text-slate-500">
                                        <span className="inline-flex items-center gap-1">
                                            <ShieldCheck className="h-3.5 w-3.5" />
                                            {s.verified_entries}/{s.total_entries} terverifikasi
                                        </span>
                                        <span>{formatDateIndonesian(s.created_at, { shortMonth: true })}</span>
                                    </div>
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

            <SlideOver
                open={modalMode !== null}
                title={modalMode === 'create' ? 'Buat Sesi Checklist' : 'Edit Sesi Checklist'}
                subtitle={modalMode === 'create' ? 'Inisialisasi lembar checklist baru untuk unit kerja' : 'Perbarui konteks atau catatan sesi'}
                onClose={closeModal}
                maxWidth="lg"
                footer={
                    <div className="flex w-full items-center justify-end gap-3">
                        <button
                            type="button"
                            onClick={closeModal}
                            className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            form="session-form"
                            disabled={form.processing}
                            className="bg-primary hover:bg-primary-700 inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition-colors disabled:opacity-50"
                        >
                            {form.processing ? 'Menyimpan...' : modalMode === 'create' ? 'Buat Sesi' : 'Simpan Perubahan'}
                        </button>
                    </div>
                }
            >
                <form id="session-form" onSubmit={submitForm} className="space-y-4 p-1">
                    <div>
                        <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Unit Kerja <span className="text-red-500">*</span>
                        </label>
                        <Select value={form.data.unit_id} onChange={(e) => form.setData('unit_id', e.target.value)} disabled={modalMode === 'edit'}>
                            <option value="">Pilih Unit Kerja</option>
                            {workUnits.map((u) => (
                                <option key={u.id} value={String(u.id)}>
                                    {u.nama}
                                </option>
                            ))}
                        </Select>
                        {form.errors.unit_id && <p className="mt-1 text-xs text-red-500">{form.errors.unit_id}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">Framework</label>
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
                        {form.errors.framework_id && <p className="mt-1 text-xs text-red-500">{form.errors.framework_id}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Periode (YYYY-MM) <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            value={form.data.periode}
                            onChange={(e) => form.setData('periode', e.target.value)}
                            placeholder="2026-08"
                            disabled={modalMode === 'edit'}
                            className="focus:border-primary focus:ring-primary/20 h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:outline-none disabled:opacity-60 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
                        />
                        {form.errors.periode && <p className="mt-1 text-xs text-red-500">{form.errors.periode}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Konteks Penilaian <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            value={form.data.konteks_penilaian}
                            onChange={(e) => form.setData('konteks_penilaian', e.target.value)}
                            placeholder="Penilaian Bulanan SMKI - Agustus 2026"
                            className="focus:border-primary focus:ring-primary/20 h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
                        />
                        {form.errors.konteks_penilaian && <p className="mt-1 text-xs text-red-500">{form.errors.konteks_penilaian}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">Catatan</label>
                        <textarea
                            value={form.data.catatan}
                            onChange={(e) => form.setData('catatan', e.target.value)}
                            rows={3}
                            placeholder="Tambahkan catatan jika ada..."
                            className="focus:border-primary focus:ring-primary/20 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
                        />
                        {form.errors.catatan && <p className="mt-1 text-xs text-red-500">{form.errors.catatan}</p>}
                    </div>
                </form>
            </SlideOver>

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
