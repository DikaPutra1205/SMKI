import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { Toast } from '@/components/ui/Toast';
import AppLayout from '@/layouts/AppLayout';
import { t } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Database, ExternalLink, Pencil, Plus, Search, Trash2 } from 'lucide-react';
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
            const timer = setTimeout(() => setFlashVisible(false), 4000);
            return () => clearTimeout(timer);
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
            router.get('/admin/superadmin/frameworks', { search: searchQuery || undefined }, { preserveState: true, replace: true });
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

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/admin/superadmin/dashboard' }, { label: t('frameworks.title') }];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/superadmin/frameworks">
            <Head title={t('frameworks.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('frameworks.title')}</h1>
                    <p className="text-muted mt-1 text-xs sm:text-sm">{t('frameworks.subtitle')}</p>
                </div>
                <button
                    type="button"
                    onClick={openCreate}
                    className="bg-primary shadow-blue hover:bg-primary-700 inline-flex items-center gap-2 rounded-[10px] px-4 py-2 text-xs font-semibold text-white transition-colors sm:text-sm"
                >
                    <Plus className="h-4 w-4" />
                    <span>{t('frameworks.addFramework')}</span>
                </button>
            </div>

            <div className="border-border overflow-hidden rounded-[14px] border bg-white shadow-sm">
                <div className="border-border bg-surface/50 border-b p-4 sm:p-5">
                    <div className="relative min-w-[280px]">
                        <Search className="text-faint absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder={t('frameworks.searchPlaceholder')}
                            className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 h-10 w-full rounded-[10px] border bg-white py-2 pr-4 pl-9 text-xs focus:ring-2 focus:outline-none sm:text-sm"
                        />
                    </div>
                </div>

                {paginatedFrameworks.length > 0 ? (
                    <div className="grid grid-cols-1 gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                        {paginatedFrameworks.map((item) => (
                            <div
                                key={item.id}
                                className="border-border bg-surface/40 hover:border-primary/40 flex flex-col rounded-[14px] border p-5 transition-all hover:shadow-md"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="bg-primary shadow-blue flex h-11 w-11 items-center justify-center rounded-[12px] text-white">
                                        <Database className="h-5 w-5" />
                                    </div>
                                    <span className="bg-primary-50 text-primary rounded-[6px] px-2.5 py-1 text-xs font-semibold">v{item.versi}</span>
                                </div>

                                <h3 className="text-navy mt-4 text-base font-bold">{item.nama}</h3>

                                <div className="mt-3 flex items-center justify-between">
                                    <span className="text-muted text-xs">
                                        {item.controls_count} {t('dashboard.controls')}
                                    </span>
                                </div>

                                {item.url_file && (
                                    <a
                                        href={item.url_file}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-primary mt-2 inline-flex items-center gap-1 text-xs hover:underline"
                                    >
                                        {item.url_file.length > 36 ? item.url_file.slice(0, 36) + '...' : item.url_file}
                                        <ExternalLink className="h-3 w-3" />
                                    </a>
                                )}

                                <div className="border-border mt-4 flex items-center justify-end gap-1.5 border-t pt-3">
                                    <button
                                        type="button"
                                        onClick={() => openEdit(item)}
                                        className="border-border-strong text-navy hover:bg-surface inline-flex items-center gap-1.5 rounded-[10px] border bg-white px-3 py-1.5 text-xs font-semibold transition-colors"
                                    >
                                        <Pencil className="h-3.5 w-3.5" />
                                        {t('common.edit')}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => handleDelete(item)}
                                        className="border-danger-border bg-danger-bg text-danger hover:bg-danger/10 inline-flex items-center gap-1.5 rounded-[10px] border px-3 py-1.5 text-xs font-semibold transition-colors"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                        {t('common.delete')}
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <EmptyState message={t('frameworks.noResults')} />
                )}

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
            </div>

            <Toast
                visible={flashVisible}
                tone={flash?.type === 'success' ? 'success' : 'error'}
                message={flash?.message}
                onDismiss={() => setFlashVisible(false)}
            />

            <Modal
                open={modalMode !== null}
                title={modalMode === 'create' ? t('frameworks.createTitle') : t('frameworks.editTitle')}
                onClose={closeModal}
                maxWidth="lg"
                footer={
                    <>
                        <button
                            type="button"
                            onClick={closeModal}
                            className="border-border-strong text-body hover:bg-surface rounded-[10px] border bg-white px-4 py-2 text-sm font-medium transition-colors"
                        >
                            {t('common.cancel')}
                        </button>
                        <button
                            type="submit"
                            form="framework-form"
                            disabled={form.processing}
                            className="bg-primary hover:bg-primary-700 inline-flex items-center gap-2 rounded-[10px] px-5 py-2 text-sm font-semibold text-white transition-colors disabled:opacity-50"
                        >
                            {form.processing ? t('common.saving') : modalMode === 'create' ? t('common.add') : t('common.save')}
                        </button>
                    </>
                }
            >
                <form id="framework-form" onSubmit={submitForm} className="space-y-4">
                    <input
                        type="text"
                        value={form.data.nama}
                        onChange={(e) => form.setData('nama', e.target.value)}
                        placeholder={t('frameworks.namePlaceholder')}
                        aria-label={t('frameworks.nameLabel')}
                        className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 h-10 w-full rounded-[10px] border bg-white px-3 text-sm focus:ring-2 focus:outline-none"
                    />
                    {form.errors.nama && <p className="text-danger text-[11px] font-medium">{form.errors.nama}</p>}

                    <input
                        type="text"
                        value={form.data.versi}
                        onChange={(e) => form.setData('versi', e.target.value)}
                        placeholder={t('frameworks.versionPlaceholder')}
                        aria-label={t('frameworks.versionLabel')}
                        className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 h-10 w-full rounded-[10px] border bg-white px-3 text-sm focus:ring-2 focus:outline-none"
                    />
                    {form.errors.versi && <p className="text-danger text-[11px] font-medium">{form.errors.versi}</p>}

                    <input
                        type="url"
                        value={form.data.url_file}
                        onChange={(e) => form.setData('url_file', e.target.value)}
                        placeholder={t('frameworks.urlPlaceholder')}
                        aria-label={`${t('frameworks.urlLabel')} (${t('common.optional')})`}
                        className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 h-10 w-full rounded-[10px] border bg-white px-3 text-sm focus:ring-2 focus:outline-none"
                    />
                    {form.errors.url_file && <p className="text-danger text-[11px] font-medium">{form.errors.url_file}</p>}
                </form>
            </Modal>

            <ConfirmDialog
                open={deleteDialogOpen}
                title={t('frameworks.deleteFramework')}
                description={deleteTarget ? t('frameworks.deleteConfirm', deleteTarget.nama, deleteTarget.versi) : ''}
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
