import { ConfirmDialog } from '@/components/admin-kepatuhan/ConfirmDialog';
import { ControlFormModal } from '@/components/admin-kepatuhan/ControlFormModal';
import AdminKepatuhanLayout from '@/layouts/AdminKepatuhanLayout';
import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Filter, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface FrameworkItem {
    id: number;
    nama: string;
    versi: string;
    url_file: string | null;
    controls_count: number;
    compliance_percentage: number;
}

export interface ControlItem {
    id: string;
    framework_id?: number;
    framework_nama?: string;
    code: string;
    title: string;
    description: string;
    category: string;
}

interface ComplianceProps {
    frameworks?: FrameworkItem[];
    controls?: ControlItem[];
    filters?: {
        search?: string;
        kategori?: string;
        framework_id?: string;
    };
}

const initialControlsFallback: ControlItem[] = [
    {
        id: '1',
        framework_id: 1,
        framework_nama: 'ISO/IEC 27001:2022',
        code: 'A.5.1',
        title: 'Kebijakan Keamanan Informasi',
        description: 'Dokumen kebijakan disetujui manajemen dan direview',
        category: 'Annex A',
    },
    {
        id: '2',
        framework_id: 1,
        framework_nama: 'ISO/IEC 27001:2022',
        code: 'A.6.1.2',
        title: 'Pemisahan Tugas',
        description: 'Pemisahan tugas dan area tanggung jawab',
        category: 'Annex A',
    },
    {
        id: '3',
        framework_id: 1,
        framework_nama: 'ISO/IEC 27001:2022',
        code: 'A.8.1.1',
        title: 'Inventarisasi Aset',
        description: 'Daftar aset informasi yang teridentifikasi',
        category: 'Annex A',
    },
    {
        id: '4',
        framework_id: 1,
        framework_nama: 'ISO/IEC 27001:2022',
        code: 'A.9.4.1',
        title: 'Informasi Pembatasan Akses',
        description: 'Pembatasan akses atas informasi dan aset pendukung',
        category: 'Annex A',
    },
    {
        id: '5',
        framework_id: 1,
        framework_nama: 'ISO/IEC 27001:2022',
        code: 'A.10.1.1',
        title: 'Penggunaan Utilitas Kriptografi',
        description: 'Kebijakan penggunaan proteksi kriptografi',
        category: 'Annex A',
    },
    {
        id: '6',
        framework_id: 1,
        framework_nama: 'ISO/IEC 27001:2022',
        code: 'A.12.3.1',
        title: 'Cadangan Informasi',
        description: 'Cadangan dan pemulihan dilakukan dan diuji',
        category: 'Annex A',
    },
];

export default function Compliance({ frameworks = [], controls, filters = {} }: ComplianceProps) {
    const activeControlsList = controls && controls.length > 0 ? controls : initialControlsFallback;

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState(filters.kategori || 'Semua Kategori');
    const [selectedFrameworkId, setSelectedFrameworkId] = useState<number | null>(filters.framework_id ? Number(filters.framework_id) : null);

    // Pagination state
    const [perPage, setPerPage] = useState<number | 'all'>(20);
    const [currentPage, setCurrentPage] = useState(1);

    const activeFramework = frameworks.find((f) => f.id === selectedFrameworkId);
    const isFirstRender = useRef(true);

    // CRUD modal state
    const [formOpen, setFormOpen] = useState(false);
    const [formMode, setFormMode] = useState<'create' | 'edit'>('create');
    const [selectedControl, setSelectedControl] = useState<ControlItem | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<ControlItem | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const openForm = (mode: 'create' | 'edit', control?: ControlItem) => {
        setFormMode(mode);
        setSelectedControl(control ?? null);
        setFormOpen(true);
    };

    const handleDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setIsDeleting(true);
        router.delete(route('admin.kepatuhan.controls.destroy', deleteTarget.id), {
            onFinish: () => {
                setIsDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    // Reset pagination to page 1 whenever filters change
    useEffect(() => {
        setCurrentPage(1);
    }, [searchQuery, selectedCategory, selectedFrameworkId, perPage]);

    // Debounced search & filter trigger
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                '/admin/kepatuhan/compliance',
                {
                    search: searchQuery || undefined,
                    kategori: selectedCategory !== 'Semua Kategori' ? selectedCategory : undefined,
                    framework_id: selectedFrameworkId ? String(selectedFrameworkId) : undefined,
                },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [searchQuery, selectedCategory, selectedFrameworkId]);

    // Client-side filtering as fallback/immediate feedback sorted by ID ascending
    const filteredControls = activeControlsList
        .filter((item) => {
            if (selectedFrameworkId && item.framework_id && item.framework_id !== selectedFrameworkId) {
                return false;
            }

            const q = searchQuery.toLowerCase();
            const matchesSearch =
                !q || item.code.toLowerCase().includes(q) || item.title.toLowerCase().includes(q) || item.description.toLowerCase().includes(q);

            const matchesCategory =
                selectedCategory === 'Semua Kategori' ||
                item.category === selectedCategory ||
                (selectedCategory === 'Annex A' && item.category.toLowerCase().includes('annex')) ||
                (selectedCategory === 'Klausul 4-10' && item.category.toLowerCase().includes('klausul'));

            return matchesSearch && matchesCategory;
        })
        .sort((a, b) => Number(a.id) - Number(b.id));

    // Pagination logic
    const totalItems = filteredControls.length;
    const effectivePerPage = perPage === 'all' ? totalItems || 1 : perPage;
    const totalPages = perPage === 'all' || totalItems === 0 ? 1 : Math.ceil(totalItems / effectivePerPage);
    const safeCurrentPage = Math.min(Math.max(1, currentPage), totalPages);

    const startIndex = totalItems === 0 ? 0 : (safeCurrentPage - 1) * effectivePerPage;
    const endIndex = perPage === 'all' ? totalItems : Math.min(startIndex + effectivePerPage, totalItems);

    const paginatedControls = perPage === 'all' ? filteredControls : filteredControls.slice(startIndex, endIndex);

    const breadcrumbs = [
        { label: 'Dashboard', href: '/admin/kepatuhan/dashboard' },
        { label: 'Compliance', href: '/admin/kepatuhan/compliance' },
        { label: 'Controls Management' },
    ];

    return (
        <AdminKepatuhanLayout breadcrumbs={breadcrumbs} currentPath="/admin/kepatuhan/compliance">
            <Head title="Controls Management - Compliance Admin" />

            {/* Header Title Section */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Controls Management</h1>
                    <p className="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                        Kelola seluruh kontrol dan klausul kepatuhan standar keamanan informasi.
                    </p>
                </div>

                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={() => openForm('create')}
                        className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 sm:text-sm"
                    >
                        <Plus className="h-4 w-4" />
                        <span>Tambah Kontrol</span>
                    </button>
                </div>
            </div>

            {/* Controls Management Container */}
            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                {/* Active Framework Information Header */}
                <div className="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                    <div>
                        <h2 className="text-base font-bold text-slate-900 sm:text-lg dark:text-white">
                            {activeFramework ? `${activeFramework.nama}:${activeFramework.versi}` : 'Semua Framework'}
                        </h2>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="border-b border-slate-100 bg-slate-50/50 p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900/50">
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        {/* Search Input with Debounce */}
                        <div className="relative min-w-[280px] flex-1">
                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Cari berdasarkan kode, judul, atau deskripsi..."
                                className="w-full rounded-lg border border-slate-200 bg-white py-2 pr-4 pl-9 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none sm:text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
                            />
                        </div>

                        {/* Filters Group */}
                        <div className="flex flex-wrap items-center gap-3">
                            {/* Framework Filter */}
                            <div className="flex items-center gap-2">
                                <Filter className="h-4 w-4 text-slate-400" />
                                <span className="text-xs font-medium text-slate-600 dark:text-slate-400">Framework:</span>
                            </div>
                            <select
                                value={selectedFrameworkId ? String(selectedFrameworkId) : 'Semua Framework'}
                                onChange={(e) => {
                                    const val = e.target.value;
                                    setSelectedFrameworkId(val === 'Semua Framework' ? null : Number(val));
                                }}
                                className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 focus:border-blue-500 focus:outline-none sm:text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            >
                                <option value="Semua Framework">Semua Framework</option>
                                {frameworks.map((fw) => (
                                    <option key={fw.id} value={String(fw.id)}>
                                        {fw.nama}:{fw.versi}
                                    </option>
                                ))}
                            </select>

                            {/* Category Filter */}
                            <div className="flex items-center gap-2">
                                <span className="text-xs font-medium text-slate-600 dark:text-slate-400">Kategori:</span>
                            </div>
                            <select
                                value={selectedCategory}
                                onChange={(e) => setSelectedCategory(e.target.value)}
                                className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 focus:border-blue-500 focus:outline-none sm:text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            >
                                <option value="Semua Kategori">Semua Kategori</option>
                                <option value="Annex A">Annex A</option>
                                <option value="Klausul 4-10">Klausul 4-10</option>
                            </select>
                        </div>
                    </div>
                </div>

                {/* Controls Data Table */}
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs sm:text-sm">
                        <thead className="border-b border-slate-100 bg-slate-50/80 text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th scope="col" className="px-4 py-3 text-center font-semibold">
                                    ID
                                </th>
                                <th scope="col" className="px-4 py-3 text-center font-semibold">
                                    KODE KLAUSUL
                                </th>
                                <th scope="col" className="px-4 py-3 text-center font-semibold">
                                    FRAMEWORK
                                </th>
                                <th scope="col" className="px-4 py-3 text-left font-semibold">
                                    JUDUL & DESKRIPSI
                                </th>
                                <th scope="col" className="px-4 py-3 text-center font-semibold">
                                    KATEGORI
                                </th>
                                <th scope="col" className="px-4 py-3 text-center font-semibold">
                                    AKSI
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/70">
                            {paginatedControls.length > 0 ? (
                                paginatedControls.map((item) => (
                                    <tr key={item.id} className="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                        <td className="px-4 py-4 text-center font-mono text-xs font-semibold whitespace-nowrap text-slate-500 dark:text-slate-400">
                                            #{item.id}
                                        </td>
                                        <td className="px-4 py-4 text-center font-bold whitespace-nowrap text-blue-600 dark:text-blue-400">
                                            {item.code}
                                        </td>
                                        <td className="px-4 py-4 text-center whitespace-nowrap">
                                            <span className="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 ring-1 ring-blue-600/20 ring-inset dark:bg-blue-950/40 dark:text-blue-300 dark:ring-blue-500/30">
                                                {item.framework_nama || 'ISO/IEC 27001:2022'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-4 text-left">
                                            <div className="font-semibold text-slate-900 dark:text-white">{item.title}</div>
                                            <div className="mt-0.5 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{item.description}</div>
                                        </td>
                                        <td className="px-4 py-4 text-center whitespace-nowrap">
                                            <span className="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                {item.category}
                                            </span>
                                        </td>
                                        <td className="px-4 py-4 text-center whitespace-nowrap">
                                            <div className="flex items-center justify-center gap-1.5">
                                                <button
                                                    type="button"
                                                    onClick={() => openForm('edit', item)}
                                                    className="rounded-lg p-1.5 text-blue-500 transition-colors hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950/50 dark:hover:text-blue-300"
                                                    title="Edit Kontrol"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setDeleteTarget(item)}
                                                    className="rounded-lg p-1.5 text-red-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/50 dark:hover:text-red-400"
                                                    title="Hapus Kontrol"
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
                                        Tidak ada kontrol yang cocok dengan kriteria pencarian.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination Controls & Showing info */}
                <div className="flex flex-col gap-4 border-t border-slate-100 bg-slate-50/50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900/50">
                    {/* Items Per Page Selector & Info */}
                    <div className="flex flex-wrap items-center gap-3 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                        <div className="flex items-center gap-2">
                            <span>Tampilkan</span>
                            <select
                                value={perPage}
                                onChange={(e) => {
                                    const val = e.target.value;
                                    setPerPage(val === 'all' ? 'all' : Number(val));
                                }}
                                className="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            >
                                <option value={20}>20</option>
                                <option value={50}>50</option>
                                <option value={100}>100</option>
                                <option value="all">Semua</option>
                            </select>
                            <span>per halaman</span>
                        </div>
                        <span className="hidden text-slate-300 sm:inline dark:text-slate-700">•</span>
                        <span>
                            Showing <strong className="font-semibold text-slate-900 dark:text-white">{endIndex}</strong> of{' '}
                            <strong className="font-semibold text-slate-900 dark:text-white">{totalItems}</strong> total entries
                        </span>
                    </div>

                    {/* Page Navigation Buttons */}
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

            <ControlFormModal open={formOpen} mode={formMode} control={selectedControl} frameworks={frameworks} onClose={() => setFormOpen(false)} />

            <ConfirmDialog
                open={deleteTarget !== null}
                title="Hapus Kontrol"
                description={
                    deleteTarget ? `Kontrol "${deleteTarget.code} - ${deleteTarget.title}" akan dihapus. Tindakan ini tidak dapat dibatalkan.` : ''
                }
                busy={isDeleting}
                onCancel={() => setDeleteTarget(null)}
                onConfirm={handleDelete}
            />
        </AdminKepatuhanLayout>
    );
}
