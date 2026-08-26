import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Modal } from '@/components/ui/Modal';
import { Select } from '@/components/ui/Select';
import { Toast } from '@/components/ui/Toast';
import AppLayout from '@/layouts/AppLayout';
import { useCan } from '@/lib/can';
import { t } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Building2, Pencil, Plus, Trash2 } from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';

interface UnitRow {
    id: number;
    nama: string;
    parent_id: number | null;
    parent?: { id: number; nama: string } | null;
}

interface Props {
    units: UnitRow[];
}

type ModalMode = 'create' | 'edit' | null;

/** Collect an unit's id plus all descendant ids — excluded from the parent dropdown to prevent cycles. */
function descendantsWithSelf(units: UnitRow[], id: number): Set<number> {
    const blocked = new Set<number>([id]);
    const stack = [id];
    while (stack.length) {
        const current = stack.pop()!;
        for (const u of units) {
            if (u.parent_id === current && !blocked.has(u.id)) {
                blocked.add(u.id);
                stack.push(u.id);
            }
        }
    }
    return blocked;
}

export default function Units({ units }: Props) {
    const can = useCan();
    const { flash } = usePage<{ flash?: { type: string; message: string } }>().props;
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (flash?.message) {
            setVisible(true);
            const tm = setTimeout(() => setVisible(false), 4000);
            return () => clearTimeout(tm);
        }
    }, [flash]);

    const [mode, setMode] = useState<ModalMode>(null);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [delOpen, setDelOpen] = useState(false);
    const [delTarget, setDelTarget] = useState<UnitRow | null>(null);
    const [delBusy, setDelBusy] = useState(false);

    const form = useForm<{ nama: string; parent_id: string | null }>({ nama: '', parent_id: null });

    function openCreate() {
        form.reset();
        form.clearErrors();
        setEditingId(null);
        setMode('create');
    }

    function openEdit(u: UnitRow) {
        form.setData({ nama: u.nama, parent_id: u.parent_id ? String(u.parent_id) : null });
        form.clearErrors();
        setEditingId(u.id);
        setMode('edit');
    }

    function close() {
        setMode(null);
        setEditingId(null);
        form.reset();
        form.clearErrors();
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (mode === 'create') {
            form.post('/admin/superadmin/units', { onSuccess: close });
        } else if (mode === 'edit' && editingId) {
            form.patch(`/admin/superadmin/units/${editingId}`, { onSuccess: close });
        }
    }

    function confirmDelete() {
        if (!delTarget) return;
        setDelBusy(true);
        router.delete(`/admin/superadmin/units/${delTarget.id}`, {
            onFinish: () => {
                setDelBusy(false);
                setDelOpen(false);
                setDelTarget(null);
            },
        });
    }

    // Parent options exclude the unit itself and its descendants.
    const blockedIds = useMemo(
        () => (editingId ? descendantsWithSelf(units, editingId) : new Set<number>()),
        [units, editingId],
    );
    const parentOptions = useMemo(() => units.filter((u) => !blockedIds.has(u.id)), [units, blockedIds]);

    const breadcrumbs = [
        { label: t('common.dashboard'), href: '/admin/superadmin/dashboard' },
        { label: 'Manajemen Unit' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/superadmin/units">
            <Head title="Manajemen Unit" />

            <Toast
                visible={visible}
                tone={flash?.type === 'success' ? 'success' : 'error'}
                message={flash?.message}
                onDismiss={() => setVisible(false)}
            />

            {/* Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Manajemen Unit</h1>
                    <p className="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                        Kelola daftar unit kerja organisasi beserta hierarki induk-turunannya.
                    </p>
                </div>
                {can('work-unit.create') && (
                    <button
                        type="button"
                        onClick={openCreate}
                        className="bg-primary hover:bg-primary inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition-all active:scale-95 sm:text-sm"
                    >
                        <Plus className="h-4 w-4" />
                        Tambah Unit Baru
                    </button>
                )}
            </div>

            {/* Table */}
            <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div className="grid grid-cols-[1fr_1fr_auto] gap-4 border-b border-slate-100 px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:border-slate-800 dark:text-slate-500">
                    <div>Nama Unit</div>
                    <div>Induk</div>
                    <div className="text-right">Aksi</div>
                </div>

                {units.length > 0 ? (
                    units.map((u) => (
                        <div
                            key={u.id}
                            className="grid grid-cols-[1fr_1fr_auto] items-center gap-4 border-b border-slate-100 px-5 py-3.5 last:border-0 hover:bg-slate-50/60 dark:border-slate-800 dark:hover:bg-slate-800/40"
                        >
                            <div className="flex min-w-0 items-center gap-2.5">
                                <div className="bg-primary-50 text-primary flex h-8 w-8 shrink-0 items-center justify-center rounded-lg dark:bg-navy-900/50 dark:text-primary-200">
                                    <Building2 className="h-4 w-4" />
                                </div>
                                <span className="truncate text-sm font-semibold text-slate-900 dark:text-white">{u.nama}</span>
                            </div>
                            <div className="truncate text-sm text-slate-500 dark:text-slate-400">{u.parent?.nama ?? '—'}</div>
                            <div className="flex items-center justify-end gap-2">
                                {can('work-unit.update') && (
                                    <button
                                        type="button"
                                        onClick={() => openEdit(u)}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                    >
                                        <Pencil className="h-3.5 w-3.5" />
                                        Edit
                                    </button>
                                )}
                                {can('work-unit.delete') && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setDelTarget(u);
                                            setDelOpen(true);
                                        }}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50/50 px-3 py-1.5 text-xs font-semibold text-red-600 transition-colors hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-950/50"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                        Hapus
                                    </button>
                                )}
                            </div>
                        </div>
                    ))
                ) : (
                    <div className="px-5 py-12 text-center">
                        <Building2 className="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600" />
                        <h4 className="mt-3 text-sm font-bold text-slate-700 dark:text-slate-300">Belum ada unit terdaftar</h4>
                        <p className="mt-1 text-xs text-slate-500">Klik tombol "Tambah Unit Baru" untuk membuat unit kerja.</p>
                    </div>
                )}
            </div>

            {/* Modal Form: Create / Edit Unit */}
            <Modal
                open={mode !== null}
                title={mode === 'create' ? 'Tambah Unit Baru' : 'Edit Unit'}
                onClose={close}
                maxWidth="lg"
                footer={
                    <>
                        <button
                            type="button"
                            onClick={close}
                            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            form="unit-form"
                            disabled={form.processing}
                            className="bg-primary hover:bg-primary inline-flex items-center gap-1.5 rounded-xl px-5 py-2 text-xs font-semibold text-white shadow-sm transition-all active:scale-95 disabled:opacity-50"
                        >
                            {form.processing ? 'Menyimpan…' : mode === 'create' ? 'Simpan Unit' : 'Perbarui Unit'}
                        </button>
                    </>
                }
            >
                <form id="unit-form" onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Nama Unit <span className="text-red-500">*</span>
                        </label>
                        <input
                            value={form.data.nama}
                            onChange={(e) => form.setData('nama', e.target.value)}
                            placeholder="Contoh: Biro Humas dan Protokol"
                            className="focus:border-primary focus:ring-primary/20 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        />
                        {form.errors.nama && <p className="mt-1 text-[11px] font-medium text-red-500">{form.errors.nama}</p>}
                    </div>

                    <Select
                        label="Unit Induk (opsional)"
                        value={form.data.parent_id ?? ''}
                        onChange={(e) => form.setData('parent_id', e.target.value || null)}
                        error={form.errors.parent_id}
                    >
                        <option value="">— Tanpa induk (unit puncak) —</option>
                        {parentOptions.map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.nama}
                            </option>
                        ))}
                    </Select>
                </form>
            </Modal>

            {/* Confirm Delete Dialog */}
            <ConfirmDialog
                open={delOpen}
                title="Hapus Unit Ini?"
                description={
                    delTarget
                        ? `Apakah Anda yakin ingin menghapus unit "${delTarget.nama}"? Tindakan ini tidak dapat dibatalkan.`
                        : ''
                }
                confirmLabel="Hapus Unit"
                cancelLabel="Batal"
                variant="danger"
                busy={delBusy}
                onCancel={() => {
                    setDelOpen(false);
                    setDelTarget(null);
                }}
                onConfirm={confirmDelete}
            />
        </AppLayout>
    );
}
