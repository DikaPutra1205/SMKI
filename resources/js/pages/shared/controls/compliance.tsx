import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import { SlideOver } from '@/components/ui/SlideOver';
import { Toast } from '@/components/ui/Toast';
import AppLayout from '@/layouts/AppLayout';
import { useCan } from '@/lib/can';
import { t } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Eye, Layers, Pencil, Plus, Save, Search, Shield, Trash2, XCircle } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

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
    domain_peran?: string | null;
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

export interface WorkUnitItem {
    id: number;
    nama: string;
}

interface ComplianceProps {
    frameworks?: FrameworkItem[];
    controls?: Paginator<ControlItem>;
    workUnits?: WorkUnitItem[];
    filters?: {
        search?: string;
        status?: string;
        unit_id?: string;
        framework_id?: string;
        kategori?: string;
        domain_peran?: string;
    };
}

interface ControlFormData {
    framework_id: string;
    kode_klausul: string;
    judul: string;
    kategori: string;
    domain_peran: string;
    deskripsi: string;
    [key: string]: string;
}

const DOMAIN_TABS = [
    { id: 'all', label: 'Semua Kontrol', kategori: '' },
    { id: 'organisasional', label: 'Organisasional', kategori: 'Organisasional' },
    { id: 'orang', label: 'Orang', kategori: 'Orang' },
    { id: 'fisik', label: 'Fisik', kategori: 'Fisik' },
    { id: 'teknologi', label: 'Teknologi', kategori: 'Teknologi' },
];

export default function Compliance({ frameworks = [], controls, filters = {} }: ComplianceProps) {
    const can = useCan();
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedFrameworkId, setSelectedFrameworkId] = useState<number | null>(
        filters.framework_id ? Number(filters.framework_id) : (frameworks[0]?.id ?? null),
    );
    const [selectedDomain, setSelectedDomain] = useState('all');
    const [selectedPeran, setSelectedPeran] = useState(filters.domain_peran || 'all');

    const isFirstRender = useRef(true);

    const filteredItems = useMemo(() => {
        let items = controls?.data ?? [];
        if (selectedPeran === 'unknown') items = items.filter((item) => !item.domain_peran);
        else if (selectedPeran !== 'all') items = items.filter((item) => item.domain_peran === selectedPeran);
        if (selectedDomain === 'all') return items;
        const tab = DOMAIN_TABS.find((t) => t.id === selectedDomain);
        if (!tab) return items;
        return items.filter((item) => item.category === tab.kategori);
    }, [controls?.data, selectedDomain, selectedPeran]);

    const { flash } = usePage<{ flash?: { type: string; message: string } }>().props;
    const [flashVisible, setFlashVisible] = useState(false);
    useEffect(() => {
        if (flash?.message) {
            setFlashVisible(true);
            const timer = setTimeout(() => setFlashVisible(false), 4000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    const [drawerMode, setDrawerMode] = useState<'detail' | 'edit' | 'create' | null>(null);
    const [activeControl, setActiveControl] = useState<ControlItem | null>(null);

    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<ControlItem | null>(null);
    const [deleteBusy, setDeleteBusy] = useState(false);

    const form = useForm<ControlFormData>({
        framework_id: String(selectedFrameworkId ?? frameworks[0]?.id ?? ''),
        kode_klausul: '',
        judul: '',
        kategori: 'organisasional',
        domain_peran: '',
        deskripsi: '',
    });

    function openDetail(item: ControlItem) {
        setActiveControl(item);
        setDrawerMode('detail');
    }

    function openCreate() {
        form.reset();
        form.setData('framework_id', String(selectedFrameworkId ?? frameworks[0]?.id ?? ''));
        form.clearErrors();
        setActiveControl(null);
        setDrawerMode('create');
    }

    function openEdit(item: ControlItem) {
        form.setData({
            framework_id: String(item.framework_id ?? selectedFrameworkId ?? frameworks[0]?.id ?? ''),
            kode_klausul: item.code,
            judul: item.title,
            kategori: item.category.toLowerCase(),
            domain_peran: item.domain_peran ?? '',
            deskripsi: item.description,
        });
        form.clearErrors();
        setActiveControl(item);
        setDrawerMode('edit');
    }

    function closeDrawer() {
        setDrawerMode(null);
        setActiveControl(null);
        form.reset();
        form.clearErrors();
    }

    function submitForm(e: React.FormEvent) {
        e.preventDefault();
        if (drawerMode === 'create') {
            form.post('/admin/kepatuhan/controls', {
                preserveScroll: true,
                onSuccess: closeDrawer,
            });
        } else if (drawerMode === 'edit' && activeControl) {
            form.put(`/admin/kepatuhan/controls/${activeControl.id}`, {
                preserveScroll: true,
                onSuccess: closeDrawer,
            });
        }
    }

    function handleDelete(item: ControlItem) {
        setDeleteTarget(item);
        setDeleteDialogOpen(true);
    }

    function confirmDelete() {
        if (!deleteTarget) return;
        setDeleteBusy(true);
        router.delete(`/admin/kepatuhan/controls/${deleteTarget.id}`, {
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

    const getBasePath = () => (typeof window !== 'undefined' ? window.location.pathname : '/admin/kepatuhan/compliance');

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                getBasePath(),
                {
                    search: searchQuery || undefined,
                    framework_id: selectedFrameworkId ? String(selectedFrameworkId) : undefined,
                    domain_peran: selectedPeran !== 'all' ? selectedPeran : undefined,
                },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [searchQuery, selectedFrameworkId, selectedPeran]);

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/admin/kepatuhan/dashboard' }, { label: t('compliance.title') }];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath={getBasePath()}>
            <Head title={`${t('compliance.title')} - Pustaka Kontrol SMKI`} />

            {/* Page Header */}
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{t('compliance.title')}</h1>
                    <p className="text-muted mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">{t('compliance.subtitle')}</p>
                </div>

                {can('control.create') && (
                    <button
                        type="button"
                        onClick={openCreate}
                        className="bg-primary hover:bg-primary-700 inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition-colors sm:text-sm"
                    >
                        <Plus className="h-4 w-4" />
                        <span>{t('compliance.addControl')}</span>
                    </button>
                )}
            </div>

            {/* Framework Cards */}
            <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                {frameworks.map((fw) => {
                    const isActive = fw.id === selectedFrameworkId;
                    const is27001 = fw.nama.toLowerCase().includes('27001');
                    return (
                        <button
                            key={fw.id}
                            type="button"
                            onClick={() => setSelectedFrameworkId(fw.id)}
                            className={`flex flex-col items-start gap-3 rounded-2xl border p-5 text-left transition-all ${
                                isActive
                                    ? 'border-primary bg-primary-50/40 ring-primary/20 dark:border-primary dark:bg-navy-900/20 shadow-sm ring-1'
                                    : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900'
                            }`}
                        >
                            <div className="flex w-full items-center justify-between">
                                <div className="flex items-center gap-2.5">
                                    <div
                                        className={`flex h-10 w-10 items-center justify-center rounded-xl ${
                                            is27001
                                                ? 'bg-primary-100 text-primary-700 dark:bg-navy-900/40 dark:text-primary-200'
                                                : 'bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300'
                                        }`}
                                    >
                                        <Layers className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div className="font-bold text-slate-900 dark:text-white">{fw.nama}</div>
                                        <div className="text-[11px] text-slate-400">Versi {fw.versi}</div>
                                    </div>
                                </div>
                                {isActive && (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                        Aktif
                                    </span>
                                )}
                            </div>

                            <div className="w-full">
                                <div className="mb-1.5 flex justify-between text-xs text-slate-500">
                                    <span>{fw.controls_count} Kontrol Terdaftar</span>
                                    <span className="font-bold text-slate-700 dark:text-slate-300">{fw.compliance_percentage}% Patuh</span>
                                </div>
                                <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div
                                        className={`h-full rounded-full transition-all duration-500 ${is27001 ? 'bg-primary' : 'bg-primary-800 dark:bg-primary-400'}`}
                                        style={{ width: `${Math.min(100, fw.compliance_percentage)}%` }}
                                    />
                                </div>
                            </div>
                        </button>
                    );
                })}
            </div>

            {/* Controls Panel Container */}
            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                {/* Kategori Filter Tabs */}
                <div className="flex items-center gap-1 overflow-x-auto border-b border-slate-200 bg-slate-50/50 p-2.5 dark:border-slate-800 dark:bg-slate-900/50">
                    {DOMAIN_TABS.map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => setSelectedDomain(tab.id)}
                            className={`rounded-xl px-3.5 py-2 text-xs font-semibold whitespace-nowrap transition-all ${
                                selectedDomain === tab.id
                                    ? 'text-primary dark:text-primary-200 border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800'
                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* Search Bar */}
                <div className="flex items-center justify-between gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
                    <div className="relative min-w-[240px] flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Cari kode klausul, judul klausul, atau deskripsi..."
                            className="focus:border-primary focus:ring-primary w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pr-3 pl-9 text-xs text-slate-700 placeholder-slate-400 transition-colors focus:bg-white focus:ring-1 sm:text-sm dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300 dark:focus:bg-slate-900"
                        />
                        {searchQuery && (
                            <button
                                type="button"
                                onClick={() => setSearchQuery('')}
                                className="absolute top-1/2 right-3 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                            >
                                <XCircle className="h-4 w-4" />
                            </button>
                        )}
                    </div>
                    <select
                        value={selectedPeran}
                        onChange={(e) => setSelectedPeran(e.target.value)}
                        aria-label="Filter peran domain"
                        className="focus:border-primary focus:ring-primary shrink-0 rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-xs text-slate-700 transition-colors focus:bg-white focus:ring-1 sm:text-sm dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300 dark:focus:bg-slate-900"
                    >
                        <option value="all">Semua Peran</option>
                        <option value="controller">controller</option>
                        <option value="processor">processor</option>
                        <option value="unknown">Tidak diketahui</option>
                    </select>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs sm:text-sm">
                        <thead className="border-b border-slate-200 bg-slate-50/90 text-[11px] font-bold tracking-wider text-slate-600 uppercase dark:border-slate-800 dark:bg-[#001f38] dark:text-slate-300">
                            <tr>
                                <th scope="col" className="px-5 py-3.5 text-left font-semibold">
                                    {t('compliance.code')}
                                </th>
                                <th scope="col" className="px-5 py-3.5 text-left font-semibold">
                                    {t('compliance.controlClause')}
                                </th>
                                <th scope="col" className="px-5 py-3.5 text-left font-semibold">
                                    {t('compliance.category')}
                                </th>
                                <th scope="col" className="px-5 py-3.5 text-left font-semibold">
                                    Peran Domain
                                </th>
                                <th scope="col" className="px-5 py-3.5 text-right font-semibold">
                                    {t('compliance.actions')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/70">
                            {filteredItems.length > 0 ? (
                                filteredItems.map((item, idx) => (
                                    <tr
                                        key={item.id}
                                        onClick={() => openDetail(item)}
                                        className={`cursor-pointer transition-colors ${
                                            idx % 2 === 0 ? 'bg-white dark:bg-[#00223d]/70' : 'bg-slate-200/70 dark:bg-[#00172b]/80'
                                        } hover:bg-primary-50/40 dark:hover:bg-[#0a3b63]/60`}
                                    >
                                        <td className="text-primary dark:text-primary-200 px-5 py-4 font-bold whitespace-nowrap">{item.code}</td>
                                        <td className="px-5 py-4 text-left">
                                            <div className="font-bold text-slate-900 dark:text-white">{item.title}</div>
                                            <div className="mt-1 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{item.description}</div>
                                        </td>
                                        <td className="px-5 py-4 text-left whitespace-nowrap">
                                            <span className="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                {item.category}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4 text-left whitespace-nowrap">
                                            {item.domain_peran ? (
                                                <span className="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 capitalize dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                    {item.domain_peran}
                                                </span>
                                            ) : (
                                                <span className="text-xs text-slate-400">—</span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-right whitespace-nowrap" onClick={(e) => e.stopPropagation()}>
                                            <div className="flex items-center justify-end gap-1.5">
                                                <button
                                                    type="button"
                                                    onClick={() => openDetail(item)}
                                                    className="rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                                                    title={t('compliance.detailRef')}
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </button>
                                                {can('control.update') && (
                                                    <button
                                                        type="button"
                                                        onClick={() => openEdit(item)}
                                                        className="rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                                                        title={t('compliance.editControl')}
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </button>
                                                )}
                                                {can('control.delete') && (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDelete(item)}
                                                        className="rounded-lg p-2 text-rose-600 transition-colors hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40"
                                                        title={t('compliance.deleteControl')}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={5}>
                                        <EmptyState message={t('compliance.noResults')} />
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination
                    currentPage={controls?.current_page ?? 1}
                    totalPages={controls?.last_page ?? 1}
                    perPage={controls?.per_page ?? 20}
                    totalItems={controls?.total ?? 0}
                    startIndex={((controls?.current_page ?? 1) - 1) * (controls?.per_page ?? 20)}
                    endIndex={controls?.to ?? 0}
                    onPageChange={(target) =>
                        router.get(
                            getBasePath(),
                            {
                                search: searchQuery || undefined,
                                framework_id: selectedFrameworkId ? String(selectedFrameworkId) : undefined,
                                domain_peran: selectedPeran !== 'all' ? selectedPeran : undefined,
                                page: target > 1 ? target : undefined,
                            },
                            { preserveState: true },
                        )
                    }
                />
            </div>

            {/* SlideOver Drawer for Control Detail & Edit/Create */}
            <SlideOver
                open={drawerMode !== null}
                onClose={closeDrawer}
                title={
                    drawerMode === 'create'
                        ? 'Tambah Kontrol Baru'
                        : drawerMode === 'edit'
                          ? `Ubah Kontrol: ${activeControl?.code ?? ''}`
                          : 'Detail Kontrol SMKI'
                }
                subtitle={
                    drawerMode === 'create'
                        ? 'Daftarkan klausul kontrol kepatuhan baru ke dalam framework SMKI.'
                        : drawerMode === 'edit'
                          ? 'Perbarui parameter, klausul, atau deskripsi kontrol kepatuhan ini.'
                          : undefined
                }
                maxWidth="2xl"
                footer={
                    drawerMode === 'detail' ? (
                        <div className="flex w-full items-center justify-between">
                            {can('control.update') && activeControl && (
                                <button
                                    type="button"
                                    onClick={() => openEdit(activeControl)}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    <Pencil className="h-3.5 w-3.5" />
                                    <span>Ubah Kontrol Ini</span>
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={closeDrawer}
                                className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Tutup
                            </button>
                        </div>
                    ) : (
                        <div className="flex w-full items-center justify-between">
                            <button
                                type="button"
                                onClick={() => {
                                    if (drawerMode === 'edit' && activeControl) {
                                        setDrawerMode('detail');
                                    } else {
                                        closeDrawer();
                                    }
                                }}
                                className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                            >
                                {drawerMode === 'edit' ? 'Kembali ke Detail' : t('common.cancel')}
                            </button>
                            <button
                                type="submit"
                                form="control-form"
                                disabled={form.processing}
                                className="bg-primary hover:bg-primary-700 inline-flex items-center gap-1.5 rounded-xl px-5 py-2.5 text-xs font-semibold text-white shadow-xs transition-colors disabled:opacity-50"
                            >
                                <Save className="h-3.5 w-3.5" />
                                <span>
                                    {form.processing
                                        ? t('common.saving')
                                        : drawerMode === 'create'
                                          ? t('compliance.addControlBtn')
                                          : 'Simpan Perubahan'}
                                </span>
                            </button>
                        </div>
                    )
                }
            >
                {drawerMode === 'detail' && activeControl && (
                    <div className="space-y-4">
                        {/* Header Box */}
                        <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3 dark:border-slate-800">
                                <div className="flex items-center gap-2">
                                    <span className="border-primary-200 bg-primary-50 text-primary dark:border-primary-800 dark:bg-navy-900 dark:text-primary-200 inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-bold">
                                        <Shield className="h-3.5 w-3.5" />
                                        {activeControl.code}
                                    </span>
                                    {activeControl.framework_nama && (
                                        <span className="rounded-lg bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                            {activeControl.framework_nama}
                                        </span>
                                    )}
                                </div>
                                <span className="text-primary-700 border-primary-200 dark:text-primary-200 inline-flex items-center rounded-lg border bg-white px-2.5 py-1 text-xs font-semibold dark:border-slate-700 dark:bg-slate-800">
                                    {activeControl.category}
                                </span>
                            </div>

                            <div className="mt-3">
                                <span className="text-[10px] font-bold tracking-wider text-slate-400 uppercase">Judul Kontrol</span>
                                <h3 className="mt-0.5 text-base font-bold text-slate-900 dark:text-white">{activeControl.title}</h3>
                            </div>

                            {activeControl.domain_peran && (
                                <div className="mt-3 flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                                    <span className="font-semibold text-slate-400">Peran Domain:</span>
                                    <span className="rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-700 capitalize dark:bg-slate-800 dark:text-slate-300">
                                        {activeControl.domain_peran}
                                    </span>
                                </div>
                            )}
                        </div>

                        {/* Deskripsi & Persyaratan Kontrol */}
                        <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                            <span className="text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                                Deskripsi &amp; Panduan Implementasi Kontrol
                            </span>
                            <div className="mt-2 rounded-xl border border-slate-100 bg-slate-50/70 p-4 text-xs leading-relaxed whitespace-pre-line text-slate-700 sm:text-sm dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-300">
                                {activeControl.description || 'Tidak ada deskripsi rinci untuk klausul kontrol ini.'}
                            </div>
                        </div>
                    </div>
                )}

                {(drawerMode === 'create' || drawerMode === 'edit') && (
                    <form id="control-form" onSubmit={submitForm} className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Select
                                label={t('compliance.frameworkLabel')}
                                value={form.data.framework_id}
                                onChange={(e) => form.setData('framework_id', e.target.value)}
                                error={form.errors.framework_id}
                            >
                                {frameworks.map((fw) => (
                                    <option key={fw.id} value={String(fw.id)}>
                                        {fw.nama} ({fw.versi})
                                    </option>
                                ))}
                            </Select>

                            <Select
                                label={t('compliance.categoryLabel')}
                                value={form.data.kategori}
                                onChange={(e) => form.setData('kategori', e.target.value)}
                                error={form.errors.kategori}
                            >
                                <option value="organisasional">Organisasional</option>
                                <option value="orang">Orang</option>
                                <option value="fisik">Fisik</option>
                                <option value="teknologi">Teknologi</option>
                            </Select>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Kode Klausul / Kontrol <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.kode_klausul}
                                    onChange={(e) => form.setData('kode_klausul', e.target.value)}
                                    placeholder="Contoh: A.5.1 atau 5.1"
                                    aria-label={t('compliance.code')}
                                    className="focus:border-primary focus:ring-primary w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 focus:ring-1 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                />
                                {form.errors.kode_klausul && <p className="mt-1 text-xs text-red-500">{form.errors.kode_klausul}</p>}
                            </div>

                            <Select
                                label="Peran Domain"
                                value={form.data.domain_peran}
                                onChange={(e) => form.setData('domain_peran', e.target.value)}
                                error={form.errors.domain_peran}
                            >
                                <option value="">— Tidak diketahui —</option>
                                <option value="controller">controller</option>
                                <option value="processor">processor</option>
                            </Select>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Judul Kontrol <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={form.data.judul}
                                onChange={(e) => form.setData('judul', e.target.value)}
                                placeholder="Contoh: Kebijakan Keamanan Informasi"
                                className="focus:border-primary focus:ring-primary w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 focus:ring-1 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                aria-label={t('compliance.controlTitle')}
                            />
                            {form.errors.judul && <p className="mt-1 text-xs text-red-500">{form.errors.judul}</p>}
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Deskripsi &amp; Detail Klausul
                            </label>
                            <textarea
                                value={form.data.deskripsi}
                                onChange={(e) => form.setData('deskripsi', e.target.value)}
                                rows={5}
                                placeholder="Jelaskan ruang lingkup atau persyaratan kontrol..."
                                className="focus:border-primary focus:ring-primary w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 focus:ring-1 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                aria-label={t('compliance.description')}
                            />
                            {form.errors.deskripsi && <p className="mt-1 text-xs text-red-500">{form.errors.deskripsi}</p>}
                        </div>
                    </form>
                )}
            </SlideOver>

            <Toast
                visible={flashVisible}
                tone={flash?.type === 'success' ? 'success' : 'error'}
                message={flash?.message}
                onDismiss={() => setFlashVisible(false)}
            />

            <ConfirmDialog
                open={deleteDialogOpen}
                title={t('compliance.deleteControl')}
                description={deleteTarget ? t('compliance.deleteConfirm', deleteTarget.code, deleteTarget.title) : ''}
                confirmLabel={t('common.delete')}
                cancelLabel={t('common.cancel')}
                variant="danger"
                busy={deleteBusy}
                onCancel={cancelDelete}
                onConfirm={confirmDelete}
            />
        </AppLayout>
    );
}
