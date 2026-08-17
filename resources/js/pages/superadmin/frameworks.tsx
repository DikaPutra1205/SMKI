import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import AppLayout from '@/layouts/AppLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, ExternalLink, Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface FrameworkItem {
    id: number;
    nama: string;
    versi: string;
    url_file: string | null;
    controls_count: number;
}

interface FrameworksProps {
    frameworks: FrameworkItem[];
    filters?: { search?: string };
}

type ModalMode = 'create' | 'edit' | null;

interface FrameworkFormData {
    nama: string;
    versi: string;
    url_file: string;
    [key: string]: string;
}

export default function Frameworks({ frameworks = [], filters = {} }: FrameworksProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [perPage, setPerPage] = useState<number | 'all'>(20);
    const [currentPage, setCurrentPage] = useState(1);
    const isFirstRender = useRef(true);

    const { flash } = usePage<{ flash?: { type: string; message: string } }>().props;
    const [flashVisible, setFlashVisible] = useState(false);
    useEffect(() => {
        if (flash?.message) {
            setFlashVisible(true);
            const t = setTimeout(() => setFlashVisible(false), 4000);
            return () => clearTimeout(t);
        }
    }, [flash]);

    const [modalMode, setModalMode] = useState<ModalMode>(null);
    const [editingId, setEditingId] = useState<number | null>(null);

    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<FrameworkItem | null>(null);
    const [deleteBusy, setDeleteBusy] = useState(false);

    const form = useForm<FrameworkFormData>({
        nama: '',
        versi: '',
        url_file: '',
    });

    function openCreate() {
        form.reset();
        form.clearErrors();
        setEditingId(null);
        setModalMode('create');
    }

    function openEdit(item: FrameworkItem) {
        form.setData({
            nama: item.nama,
            versi: item.versi,
            url_file: item.url_file ?? '',
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
        if (modalMode === 'create') {
            form.post('/admin/superadmin/frameworks', { onSuccess: closeModal });
        } else if (modalMode === 'edit' && editingId !== null) {
            form.patch(`/admin/superadmin/frameworks/${editingId}`, { onSuccess: closeModal });
        }
    }

    function handleDelete(item: FrameworkItem) {
        setDeleteTarget(item);
        setDeleteDialogOpen(true);
    }

    function confirmDelete() {
        if (!deleteTarget) return;
        setDeleteBusy(true);
        router.delete(`/admin/superadmin/frameworks/${deleteTarget.id}`, {
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

    useEffect(() => {
        setCurrentPage(1);
    }, [searchQuery, perPage]);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        const timer = setTimeout(() => {
            router.get(
                '/admin/superadmin/frameworks',
                { search: searchQuery || undefined },
                { preserveState: true, replace: true },
            );
        }, 350);
        return () => clearTimeout(timer);
    }, [searchQuery]);

    const filteredFrameworks = frameworks.filter((item) => {
        const q = searchQuery.toLowerCase();
        return !q || item.nama.toLowerCase().includes(q) || item.versi.toLowerCase().includes(q);
    });

    const totalItems = filteredFrameworks.length;
    const effectivePerPage = perPage === 'all' ? totalItems || 1 : perPage;
    const totalPages = perPage === 'all' || totalItems === 0 ? 1 : Math.ceil(totalItems / effectivePerPage);
    const safeCurrentPage = Math.min(Math.max(1, currentPage), totalPages);

    const startIndex = totalItems === 0 ? 0 : (safeCurrentPage - 1) * effectivePerPage;
    const endIndex = perPage === 'all' ? totalItems : Math.min(startIndex + effectivePerPage, totalItems);
    const paginatedFrameworks = perPage === 'all' ? filteredFrameworks : filteredFrameworks.slice(startIndex, endIndex);

    const breadcrumbs = [
        { label: 'Dashboard', href: '/admin/kepatuhan/dashboard' },
        { label: 'Framework Management' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/superadmin/frameworks">
            <Head title="Framework Management" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Framework Management</h1>
                    <p className="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                        Kelola seluruh framework standar kepatuhan beserta tautan dokumen referensi.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={openCreate}
                    className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 sm:text-sm"
                >
                    <Plus className="h-4 w-4" />
                    <span>Tambah Framework</span>
                </button>
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div className="border-b border-slate-100 bg-slate-50/50 p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900/50">
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div className="relative min-w-[280px] flex-1">
                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Cari berdasarkan nama atau versi..."
                                className="w-full rounded-lg border border-slate-200 bg-white py-2 pr-4 pl-9 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none sm:text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
                            />
                        </div>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs sm:text-sm">
                        <thead className="border-b border-slate-100 bg-slate-50/80 text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th scope="col" className="px-4 py-3 text-center font-semibold">ID</th>
                                <th scope="col" className="px-4 py-3 text-left font-semibold">NAMA</th>
                                <th scope="col" className="px-4 py-3 text-center font-semibold">VERSI</th>
                                <th scope="col" className="px-4 py-3 text-left font-semibold">URL FILE</th>
                                <th scope="col" className="px-4 py-3 text-center font-semibold">JUMLAH KONTROL</th>
                                <th scope="col" className="px-4 py-3 text-center font-semibold">AKSI</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/70">
                            {paginatedFrameworks.length > 0 ? (
                                paginatedFrameworks.map((item) => (
                                    <tr key={item.id} className="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                        <td className="px-4 py-4 text-center font-mono text-xs font-semibold whitespace-nowrap text-slate-500 dark:text-slate-400">
                                            #{item.id}
                                        </td>
                                        <td className="px-4 py-4 text-left font-semibold text-slate-900 dark:text-white">{item.nama}</td>
                                        <td className="px-4 py-4 text-center whitespace-nowrap">
                                            <span className="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 ring-1 ring-blue-600/20 ring-inset dark:bg-blue-950/40 dark:text-blue-300 dark:ring-blue-500/30">
                                                {item.versi}
                                            </span>
                                        </td>
                                        <td className="px-4 py-4 text-left">
                                            {item.url_file ? (
                                                <a
                                                    href={item.url_file}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline dark:text-blue-400"
                                                >
                                                    {item.url_file.length > 40 ? item.url_file.slice(0, 40) + '...' : item.url_file}
                                                    <ExternalLink className="h-3 w-3" />
                                                </a>
                                            ) : (
                                                <span className="text-xs text-slate-400">-</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-4 text-center whitespace-nowrap">
                                            <span className="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                {item.controls_count}
                                            </span>
                                        </td>
                                        <td className="px-4 py-4 text-center whitespace-nowrap">
                                            <div className="flex items-center justify-center gap-1.5">
                                                <button
                                                    type="button"
                                                    onClick={() => openEdit(item)}
                                                    className="rounded-lg p-1.5 text-blue-500 transition-colors hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950/50 dark:hover:text-blue-300"
                                                    title="Edit Framework"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => handleDelete(item)}
                                                    className="rounded-lg p-1.5 text-red-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/50 dark:hover:text-red-400"
                                                    title="Hapus Framework"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={6} className="py-8 text-center text-sm text-slate-400">
                                        Tidak ada framework yang cocok dengan kriteria pencarian.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-col gap-4 border-t border-slate-100 bg-slate-50/50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900/50">
                    <div className="flex flex-wrap items-center gap-3 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                        <div className="flex items-center gap-2">
                            <span>Tampilkan</span>
                            <select
                                value={perPage}
                                onChange={(e) => setPerPage(e.target.value === 'all' ? 'all' : Number(e.target.value))}
                                className="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            >
                                <option value={20}>20</option>
                                <option value={50}>50</option>
                                <option value={100}>100</option>
                                <option value="all">Semua</option>
                            </select>
                            <span>per halaman</span>
                        </div>
                        <span className="hidden text-slate-300 sm:inline dark:text-slate-700">&bull;</span>
                        <span>
                            Showing <strong className="font-semibold text-slate-900 dark:text-white">{endIndex}</strong> of{' '}
                            <strong className="font-semibold text-slate-900 dark:text-white">{totalItems}</strong> total entries
                        </span>
                    </div>

                    {totalPages > 1 && (
                        <div className="flex items-center gap-1.5">
                            <button
                                type="button"
                                disabled={safeCurrentPage === 1}
                                onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-xs transition-colors hover:bg-slate-50 disabled:opacity-40 disabled:hover:bg-white dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/60"
                            >
                                <ChevronLeft className="h-3.5 w-3.5" />
                                <span>Sebelumnya</span>
                            </button>

                            <div className="flex items-center gap-1">
                                {Array.from({ length: totalPages }, (_, i) => i + 1)
                                    .filter((p) => p === 1 || p === totalPages || Math.abs(p - safeCurrentPage) <= 1)
                                    .map((p, idx, arr) => (
                                        <div key={p} className="flex items-center">
                                            {idx > 0 && arr[idx - 1] !== p - 1 && <span className="px-1 text-xs text-slate-400">...</span>}
                                            <button
                                                type="button"
                                                onClick={() => setCurrentPage(p)}
                                                className={`min-w-[32px] rounded-lg px-2.5 py-1.5 text-xs font-semibold transition-colors ${
                                                    safeCurrentPage === p
                                                        ? 'bg-blue-600 text-white shadow-xs'
                                                        : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/60'
                                                }`}
                                            >
                                                {p}
                                            </button>
                                        </div>
                                    ))}
                            </div>

                            <button
                                type="button"
                                disabled={safeCurrentPage === totalPages}
                                onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                                className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-xs transition-colors hover:bg-slate-50 disabled:opacity-40 disabled:hover:bg-white dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/60"
                            >
                                <span>Selanjutnya</span>
                                <ChevronRight className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    )}
                </div>
            </div>

            {flashVisible && flash?.message && (
                <div
                    className={`fixed right-4 bottom-4 z-50 flex items-center gap-3 rounded-xl border px-5 py-3.5 shadow-xl transition-all duration-300 ${
                        flash.type === 'success'
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                            : 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200'
                    }`}
                >
                    <span className="text-sm font-medium">{flash.message}</span>
                    <button type="button" onClick={() => setFlashVisible(false)}>
                        <X className="h-4 w-4 opacity-60" />
                    </button>
                </div>
            )}

            {modalMode && (
                <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/40 backdrop-blur-sm" onClick={closeModal}>
                    <div
                        className="mx-4 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="mb-5 flex items-center justify-between">
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                {modalMode === 'create' ? 'Tambah Framework Baru' : 'Edit Framework'}
                            </h3>
                            <button type="button" onClick={closeModal} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <form onSubmit={submitForm} className="space-y-4">
                            <div>
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">Nama Framework</label>
                                <input
                                    type="text"
                                    value={form.data.nama}
                                    onChange={(e) => form.setData('nama', e.target.value)}
                                    placeholder="Contoh: ISO/IEC 27001"
                                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                />
                                {form.errors.nama && <p className="mt-1 text-xs text-red-500">{form.errors.nama}</p>}
                            </div>

                            <div>
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">Versi</label>
                                <input
                                    type="text"
                                    value={form.data.versi}
                                    onChange={(e) => form.setData('versi', e.target.value)}
                                    placeholder="Contoh: 2022"
                                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                />
                                {form.errors.versi && <p className="mt-1 text-xs text-red-500">{form.errors.versi}</p>}
                            </div>

                            <div>
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    URL File <span className="font-normal text-slate-400">(opsional)</span>
                                </label>
                                <input
                                    type="url"
                                    value={form.data.url_file}
                                    onChange={(e) => form.setData('url_file', e.target.value)}
                                    placeholder="https://..."
                                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                />
                                {form.errors.url_file && <p className="mt-1 text-xs text-red-500">{form.errors.url_file}</p>}
                            </div>

                            <div className="flex justify-end gap-3 pt-1">
                                <button
                                    type="button"
                                    onClick={closeModal}
                                    className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                                >
                                    {form.processing ? 'Menyimpan...' : modalMode === 'create' ? 'Tambah' : 'Simpan Perubahan'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            <ConfirmDialog
                open={deleteDialogOpen}
                title="Hapus Framework"
                description={`Hapus framework "${deleteTarget?.nama} ${deleteTarget?.versi}"? Semua kontrol terkait juga akan dihapus.`}
                confirmLabel="Hapus"
                cancelLabel="Batal"
                variant="danger"
                busy={deleteBusy}
                onCancel={cancelDelete}
                onConfirm={confirmDelete}
            />
        </AppLayout>
    );
}
