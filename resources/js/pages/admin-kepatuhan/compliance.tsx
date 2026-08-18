import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { Select } from '@/components/ui/Select';
import { StatusBadge, statusTone } from '@/components/ui/StatusBadge';
import { Toast } from '@/components/ui/Toast';
import AppLayout from '@/layouts/AppLayout';
import { t } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Eye, Plus, Search } from 'lucide-react';
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
    status: string | null;
    unit_nama: string;
    pic_name: string;
}

export interface WorkUnitItem {
    id: number;
    nama: string;
}

interface ComplianceProps {
    frameworks?: FrameworkItem[];
    controls?: ControlItem[];
    workUnits?: WorkUnitItem[];
    filters?: {
        search?: string;
        status?: string;
        unit_id?: string;
        framework_id?: string;
        kategori?: string;
    };
}

type ModalMode = 'create' | 'edit' | null;

interface ControlFormData {
    framework_id: string;
    kode_klausul: string;
    judul: string;
    kategori: string;
    deskripsi: string;
    [key: string]: string;
}

const STATUS_OPTIONS = ['compliant', 'partial', 'non_compliant', 'na'] as const;

export default function Compliance({ frameworks = [], controls = [], workUnits = [], filters = {} }: ComplianceProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || 'Semua Status');
    const [selectedUnit, setSelectedUnit] = useState<string>(filters.unit_id || 'Semua Unit');
    const [selectedFrameworkId, setSelectedFrameworkId] = useState<number | null>(
        filters.framework_id ? Number(filters.framework_id) : (frameworks[0]?.id ?? null),
    );

    const [perPage, setPerPage] = useState<number | 'all'>(20);
    const [currentPage, setCurrentPage] = useState(1);

    const activeFramework = frameworks.find((f) => f.id === selectedFrameworkId) ?? null;
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
    const [editingId, setEditingId] = useState<string | null>(null);
    const [detailTarget, setDetailTarget] = useState<ControlItem | null>(null);

    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<ControlItem | null>(null);
    const [deleteBusy, setDeleteBusy] = useState(false);

    const form = useForm<ControlFormData>({
        framework_id: String(selectedFrameworkId ?? frameworks[0]?.id ?? ''),
        kode_klausul: '',
        judul: '',
        kategori: 'annex_a',
        deskripsi: '',
    });

    function openCreate() {
        form.reset();
        form.setData('framework_id', String(selectedFrameworkId ?? frameworks[0]?.id ?? ''));
        form.clearErrors();
        setEditingId(null);
        setModalMode('create');
    }

    function openEdit(item: ControlItem) {
        form.setData({
            framework_id: String(item.framework_id ?? ''),
            kode_klausul: item.code,
            judul: item.title,
            kategori: item.category === 'Annex A' ? 'annex_a' : 'klausul_4_10',
            deskripsi: item.description,
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
            form.post('/admin/kepatuhan/controls', { onSuccess: closeModal });
        } else if (modalMode === 'edit' && editingId) {
            form.put(`/admin/kepatuhan/controls/${editingId}`, { onSuccess: closeModal });
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

    useEffect(() => {
        setCurrentPage(1);
    }, [searchQuery, selectedStatus, selectedUnit, selectedFrameworkId, perPage]);

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
                    status: selectedStatus !== 'Semua Status' ? selectedStatus : undefined,
                    unit_id: selectedUnit !== 'Semua Unit' ? selectedUnit : undefined,
                    framework_id: selectedFrameworkId ? String(selectedFrameworkId) : undefined,
                },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [searchQuery, selectedStatus, selectedUnit, selectedFrameworkId]);

    const totalItems = controls.length;
    const effectivePerPage = perPage === 'all' ? totalItems || 1 : perPage;
    const totalPages = perPage === 'all' || totalItems === 0 ? 1 : Math.ceil(totalItems / effectivePerPage);
    const safeCurrentPage = Math.min(Math.max(1, currentPage), totalPages);

    const startIndex = totalItems === 0 ? 0 : (safeCurrentPage - 1) * effectivePerPage;
    const endIndex = perPage === 'all' ? totalItems : Math.min(startIndex + effectivePerPage, totalItems);

    const paginatedControls = perPage === 'all' ? controls : controls.slice(startIndex, endIndex);

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/admin/kepatuhan/dashboard' }, { label: t('compliance.title') }];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/kepatuhan/compliance">
            <Head title={`${t('compliance.title')} - Admin Kepatuhan`} />

            <div className="page-head flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('compliance.title')}</h1>
                    <p className="text-muted mt-1 text-xs sm:text-sm">{t('compliance.subtitle')}</p>
                </div>

                <button
                    type="button"
                    onClick={openCreate}
                    className="bg-primary shadow-blue hover:bg-primary-700 inline-flex items-center gap-2 rounded-[10px] px-4 py-2 text-xs font-semibold text-white transition-colors sm:text-sm"
                >
                    <Plus className="h-4 w-4" />
                    <span>{t('compliance.addControl')}</span>
                </button>
            </div>

            {/* Framework cards grid */}
            <div className="mb-5 grid grid-cols-1 gap-[18px] md:grid-cols-2 xl:grid-cols-3">
                {frameworks.map((fw) => {
                    const isActive = fw.id === selectedFrameworkId;
                    const is27001 = fw.nama.toLowerCase().includes('27001');
                    return (
                        <button
                            key={fw.id}
                            type="button"
                            onClick={() => setSelectedFrameworkId(fw.id)}
                            className={`font-body hover:border-primary-300 flex cursor-pointer flex-col items-start gap-[7px] rounded-[14px] border bg-white p-[18px_20px] text-left shadow-sm transition-all hover:shadow-md ${
                                isActive ? 'border-primary shadow-[0_0_0_3px_#eaf3fb]' : 'border-border'
                            }`}
                        >
                            <span className="flex w-full items-center justify-between">
                                <span
                                    className={`grid h-[38px] w-[38px] place-items-center rounded-[10px] ${
                                        is27001 ? 'bg-primary-50 text-primary' : 'bg-violet-bg text-violet'
                                    }`}
                                >
                                    <svg
                                        width="18"
                                        height="18"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <path d="M12 2 2 7l10 5 10-5-10-5z" />
                                        <path d="M2 17l10 5 10-5" />
                                        <path d="M2 12l10 5 10-5" />
                                    </svg>
                                </span>
                                {isActive ? (
                                    <span className="border-success-border bg-success-bg text-success inline-flex items-center gap-1.5 rounded-[6px] border px-2.5 py-1 text-xs font-semibold">
                                        <span className="h-1.5 w-1.5 rounded-full bg-current" />
                                        {t('common.active')}
                                    </span>
                                ) : (
                                    <span className="border-border-strong bg-surface-2 text-navy inline-flex items-center rounded-[6px] border px-2.5 py-1 text-xs font-semibold">
                                        v{fw.versi}
                                    </span>
                                )}
                            </span>
                            <strong className="text-navy text-[14.5px] font-bold">{fw.nama}</strong>
                            <span className="text-muted text-xs">
                                {is27001 ? t('compliance.controlsManagement') : t('compliance.privacyManagement')} · {fw.controls_count}{' '}
                                {t('compliance.controlsUnit')} · {fw.compliance_percentage}% {t('compliance.compliantPct')}
                            </span>
                            <span className="bg-surface-2 h-[6px] w-full overflow-hidden rounded-full">
                                <span
                                    className={`block h-full rounded-full ${is27001 ? 'bg-primary' : 'bg-violet'}`}
                                    style={{ width: `${Math.min(100, fw.compliance_percentage)}%` }}
                                />
                            </span>
                        </button>
                    );
                })}
            </div>

            {/* Controls panel */}
            <section className="border-border overflow-hidden rounded-[14px] border bg-white shadow-sm">
                <div className="border-border flex flex-col gap-2 border-b p-[16px_18px] sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 className="text-[15px] font-bold">{activeFramework ? activeFramework.nama : t('compliance.title')}</h3>
                        <p className="text-faint mt-0.5 text-xs">
                            {activeFramework ? `${activeFramework.controls_count} ${t('compliance.controlsUnit')}` : ''} ·{' '}
                            {activeFramework?.compliance_percentage ?? 0}% {t('compliance.compliantPct')}
                        </p>
                    </div>
                </div>

                <div className="border-border flex flex-col gap-3 border-b bg-white p-[12px_16px] md:flex-row md:items-center">
                    <div className="relative min-w-[220px] flex-1">
                        <Search className="text-faint absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder={t('compliance.searchPlaceholder')}
                            className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 h-10 w-full rounded-[10px] border bg-white py-2 pr-4 pl-9 text-xs focus:ring-2 focus:outline-none sm:text-sm"
                        />
                    </div>

                    <Select value={selectedStatus} onChange={(e) => setSelectedStatus(e.target.value)} className="min-w-[150px]">
                        <option value="Semua Status">{t('compliance.allStatus')}</option>
                        {STATUS_OPTIONS.map((s) => (
                            <option key={s} value={s}>
                                {t(`status.${s}`)}
                            </option>
                        ))}
                    </Select>

                    <Select value={selectedUnit} onChange={(e) => setSelectedUnit(e.target.value)} className="min-w-[170px]">
                        <option value="Semua Unit">{t('compliance.allUnits')}</option>
                        {workUnits.map((u) => (
                            <option key={u.id} value={String(u.id)}>
                                {u.nama}
                            </option>
                        ))}
                    </Select>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs sm:text-sm">
                        <thead className="border-border bg-surface/60 text-muted border-b text-[11px] font-bold tracking-wider uppercase">
                            <tr>
                                <th scope="col" className="px-5 py-3 text-left font-semibold">
                                    {t('compliance.code')}
                                </th>
                                <th scope="col" className="px-5 py-3 text-left font-semibold">
                                    {t('compliance.controlClause')}
                                </th>
                                <th scope="col" className="px-5 py-3 text-left font-semibold">
                                    {t('compliance.category')}
                                </th>
                                <th scope="col" className="px-5 py-3 text-left font-semibold">
                                    {t('compliance.workUnit')}
                                </th>
                                <th scope="col" className="px-5 py-3 text-left font-semibold">
                                    {t('compliance.status')}
                                </th>
                                <th scope="col" className="px-5 py-3 text-right font-semibold">
                                    {t('compliance.actions')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {paginatedControls.length > 0 ? (
                                paginatedControls.map((item) => (
                                    <tr key={item.id} className="hover:bg-surface/50 transition-colors">
                                        <td className="text-primary px-5 py-4 font-bold whitespace-nowrap">{item.code}</td>
                                        <td className="px-5 py-4 text-left">
                                            <div className="text-navy font-semibold">{item.title}</div>
                                            <div className="text-faint mt-0.5 text-xs">{item.description}</div>
                                        </td>
                                        <td className="px-5 py-4 text-left whitespace-nowrap">
                                            <span className="border-border text-body inline-flex items-center rounded-[6px] border bg-white px-2.5 py-1 text-xs font-semibold">
                                                {item.category === 'Annex A' ? 'Annex A' : 'Klausul 4-10'}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4 text-left whitespace-nowrap">
                                            {item.unit_nama ? (
                                                <>
                                                    <div className="text-navy font-medium">{item.unit_nama}</div>
                                                    <div className="text-faint text-xs">{item.pic_name}</div>
                                                </>
                                            ) : (
                                                <span className="text-faint text-xs">{t('compliance.noEntryYet')}</span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-left whitespace-nowrap">
                                            {item.status ? (
                                                <StatusBadge tone={statusTone(item.status)}>
                                                    {t(`status.${item.status as 'compliant' | 'partial' | 'non_compliant' | 'na'}`)}
                                                </StatusBadge>
                                            ) : (
                                                <StatusBadge tone="gray">{t('status.na')}</StatusBadge>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-right whitespace-nowrap">
                                            <div className="flex items-center justify-end gap-1.5">
                                                <button
                                                    type="button"
                                                    onClick={() => setDetailTarget(item)}
                                                    className="text-body hover:bg-surface-2 rounded-lg p-1.5 transition-colors"
                                                    title={t('compliance.detailRef')}
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => openEdit(item)}
                                                    className="text-muted hover:bg-surface-2 rounded-lg p-1.5 transition-colors"
                                                    title={t('compliance.editControl')}
                                                >
                                                    <svg
                                                        width="16"
                                                        height="16"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        strokeWidth="2"
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                    >
                                                        <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                                                    </svg>
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => handleDelete(item)}
                                                    className="text-danger hover:bg-danger-bg rounded-lg p-1.5 transition-colors"
                                                    title={t('compliance.deleteControl')}
                                                >
                                                    <svg
                                                        width="16"
                                                        height="16"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        strokeWidth="2"
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                    >
                                                        <path d="M3 6h18" />
                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                        <line x1="10" x2="10" y1="11" y2="17" />
                                                        <line x1="14" x2="14" y1="11" y2="17" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={6}>
                                        <EmptyState message={t('compliance.noResults')} />
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

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
            </section>

            {/* Control detail modal */}
            <Modal
                open={detailTarget !== null}
                title={detailTarget ? `${detailTarget.code} · ${detailTarget.title}` : ''}
                description={detailTarget?.framework_nama ? t('compliance.detailRef') : undefined}
                onClose={() => setDetailTarget(null)}
                maxWidth="lg"
                footer={
                    <>
                        <button
                            type="button"
                            onClick={() => setDetailTarget(null)}
                            className="border-border-strong text-body hover:bg-surface rounded-[10px] border bg-white px-4 py-2 text-sm font-medium transition-colors"
                        >
                            {t('compliance.close')}
                        </button>
                    </>
                }
            >
                {detailTarget && (
                    <div className="space-y-4">
                        <div className="border-info/20 bg-info-bg flex gap-3 rounded-[10px] border p-3.5">
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                className="text-info mt-0.5 shrink-0"
                            >
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" x2="12" y1="16" y2="12" />
                                <line x1="12" x2="12.01" y1="8" y2="8" />
                            </svg>
                            <div>
                                <strong className="text-ink block text-[13px]">{t('compliance.detailDescriptionLabel')}</strong>
                                <span className="text-body mt-0.5 block text-[13px] leading-relaxed">{detailTarget.description}</span>
                            </div>
                        </div>

                        <div className="border-border overflow-hidden rounded-[10px] border">
                            <div className="border-border flex items-center justify-between border-b px-4 py-2.5">
                                <span className="text-body text-[13px] font-medium">{t('compliance.frameworkLabel')}</span>
                                <span className="text-navy text-[13px] font-semibold">{detailTarget.framework_nama}</span>
                            </div>
                            <div className="flex items-center justify-between px-4 py-2.5">
                                <span className="text-body text-[13px] font-medium">{t('compliance.categoryLabel')}</span>
                                <span className="text-navy text-[13px] font-semibold">
                                    {detailTarget.category === 'Annex A' ? 'Annex A' : 'Klausul 4-10'}
                                </span>
                            </div>
                        </div>

                        <h4 className="text-navy text-sm font-bold">{t('compliance.unitsAssessed')}</h4>
                        {detailTarget.unit_nama ? (
                            <div className="border-border overflow-hidden rounded-[10px] border">
                                <table className="w-full text-left text-[13px]">
                                    <thead className="border-border bg-surface/60 text-muted border-b text-[11px] font-bold tracking-wider uppercase">
                                        <tr>
                                            <th className="px-4 py-2.5 font-semibold">{t('compliance.unitWork')}</th>
                                            <th className="px-4 py-2.5 font-semibold">{t('compliance.pic')}</th>
                                            <th className="px-4 py-2.5 font-semibold">{t('compliance.cycleStatus')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr className="border-border border-t">
                                            <td className="text-navy px-4 py-3 font-semibold">{detailTarget.unit_nama}</td>
                                            <td className="px-4 py-3">{detailTarget.pic_name}</td>
                                            <td className="px-4 py-3">
                                                {detailTarget.status ? (
                                                    <StatusBadge tone={statusTone(detailTarget.status)}>
                                                        {t(`status.${detailTarget.status as 'compliant' | 'partial' | 'non_compliant' | 'na'}`)}
                                                    </StatusBadge>
                                                ) : (
                                                    <StatusBadge tone="gray">{t('status.na')}</StatusBadge>
                                                )}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-faint text-[13px]">{t('compliance.noEntryYet')}</p>
                        )}
                    </div>
                )}
            </Modal>

            {/* Add/edit control modal */}
            <Modal
                open={modalMode !== null}
                title={modalMode === 'create' ? t('compliance.createTitle') : t('compliance.editTitle')}
                description={modalMode === 'create' ? t('compliance.createSubtitle') : undefined}
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
                            form="control-form"
                            disabled={form.processing}
                            className="bg-primary hover:bg-primary-700 inline-flex items-center gap-2 rounded-[10px] px-5 py-2 text-sm font-semibold text-white transition-colors disabled:opacity-50"
                        >
                            {form.processing ? t('common.saving') : t('compliance.addControlBtn')}
                        </button>
                    </>
                }
            >
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
                            <option value="annex_a">Annex A</option>
                            <option value="klausul_4_10">Klausul 4-10</option>
                        </Select>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <input
                            type="text"
                            value={form.data.kode_klausul}
                            onChange={(e) => form.setData('kode_klausul', e.target.value)}
                            placeholder={t('compliance.codePlaceholder')}
                            aria-label={t('compliance.code')}
                            className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 h-10 w-full rounded-[10px] border bg-white px-3 text-sm focus:ring-2 focus:outline-none"
                        />
                        {form.errors.kode_klausul && <p className="text-danger text-[11px] font-medium">{form.errors.kode_klausul}</p>}
                    </div>

                    <input
                        type="text"
                        value={form.data.judul}
                        onChange={(e) => form.setData('judul', e.target.value)}
                        placeholder={t('compliance.titlePlaceholder')}
                        className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 h-10 w-full rounded-[10px] border bg-white px-3 text-sm focus:ring-2 focus:outline-none"
                        aria-label={t('compliance.controlTitle')}
                    />
                    {form.errors.judul && <p className="text-danger text-[11px] font-medium">{form.errors.judul}</p>}

                    <textarea
                        value={form.data.deskripsi}
                        onChange={(e) => form.setData('deskripsi', e.target.value)}
                        rows={3}
                        placeholder={t('compliance.descriptionPlaceholder')}
                        className="border-border-strong text-ink placeholder:text-faint focus:border-primary focus:ring-primary/20 w-full resize-none rounded-[10px] border bg-white px-3 py-2 text-sm focus:ring-2 focus:outline-none"
                        aria-label={t('compliance.description')}
                    />
                    {form.errors.deskripsi && <p className="text-danger text-[11px] font-medium">{form.errors.deskripsi}</p>}
                </form>
            </Modal>

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
