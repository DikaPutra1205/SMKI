import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Modal } from '@/components/ui/Modal';
import { Toast } from '@/components/ui/Toast';
import AppLayout from '@/layouts/AppLayout';
import { useCan } from '@/lib/can';
import { t } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface RoleOpt {
    id: number;
    name: string;
    label: string;
}
interface UnitOpt {
    id: number;
    nama: string;
}
interface UserRow {
    id: number;
    name: string;
    email: string;
    role_id: number;
    unit_id: number | null;
    role: { id: number; name: string; label: string } | null;
    unit: { id: number; nama: string } | null;
}
interface Props {
    users: UserRow[];
    roles: RoleOpt[];
    units: UnitOpt[];
}
type ModalMode = 'create' | 'edit' | null;
type FormData = { name: string; email: string; role_id: string; unit_id: string };

export default function Users({ users, roles, units }: Props) {
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
    const [delTarget, setDelTarget] = useState<UserRow | null>(null);
    const [delBusy, setDelBusy] = useState(false);

    const form = useForm<FormData>({ name: '', email: '', role_id: '', unit_id: '' });

    function openCreate() {
        form.reset();
        form.clearErrors();
        setEditingId(null);
        setMode('create');
    }
    function openEdit(u: UserRow) {
        form.setData({ name: u.name, email: u.email, role_id: String(u.role_id), unit_id: u.unit_id ? String(u.unit_id) : '' });
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
        const coerce = (data: FormData) => ({
            ...data,
            role_id: data.role_id ? Number(data.role_id) : null,
            unit_id: data.unit_id ? Number(data.unit_id) : null,
        });
        if (mode === 'create') {
            form.transform(coerce);
            form.post('/admin/superadmin/users', { onSuccess: close });
        } else if (mode === 'edit' && editingId) {
            form.transform(coerce);
            form.patch(`/admin/superadmin/users/${editingId}`, { onSuccess: close });
        }
    }
    function confirmDelete() {
        if (!delTarget) return;
        setDelBusy(true);
        router.delete(`/admin/superadmin/users/${delTarget.id}`, {
            onFinish: () => {
                setDelBusy(false);
                setDelOpen(false);
                setDelTarget(null);
            },
        });
    }

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/admin/superadmin/dashboard' }, { label: t('admin.users.title') }];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/superadmin/users">
            <Head title={t('admin.users.title')} />
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('admin.users.title')}</h1>
                    <p className="text-muted mt-1 text-xs sm:text-sm dark:text-slate-400">{t('admin.users.subtitle')}</p>
                </div>
                {can('user.create') && (
                    <button
                        type="button"
                        onClick={openCreate}
                        className="bg-primary shadow-blue hover:bg-primary-700 inline-flex items-center gap-2 rounded-[10px] px-4 py-2 text-xs font-semibold text-white transition-colors sm:text-sm"
                    >
                        <Plus className="h-4 w-4" />
                        {t('admin.users.addUser')}
                    </button>
                )}
            </div>

            <div className="border-border overflow-hidden rounded-[14px] border bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="border-border bg-surface/60 text-muted border-b text-[11px] font-bold tracking-wider uppercase dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th className="px-5 py-3">{t('admin.users.name')}</th>
                                <th className="px-5 py-3">Email</th>
                                <th className="px-5 py-3">Role</th>
                                <th className="px-5 py-3">Unit</th>
                                <th className="px-5 py-3 text-right">{t('common.actions')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y dark:divide-slate-700">
                            {users.length ? (
                                users.map((u) => (
                                    <tr key={u.id} className="hover:bg-surface/50 dark:hover:bg-slate-800/50">
                                        <td className="text-navy px-5 py-3 font-medium dark:text-white">{u.name}</td>
                                        <td className="text-body px-5 py-3 text-xs dark:text-slate-300">{u.email}</td>
                                        <td className="px-5 py-3">
                                            <span className="bg-primary-50 dark:bg-primary/10 text-primary rounded-[6px] px-2 py-0.5 text-xs font-semibold">
                                                {u.role?.label ?? u.role_id}
                                            </span>
                                        </td>
                                        <td className="text-body px-5 py-3 text-xs dark:text-slate-300">{u.unit?.nama ?? '—'}</td>
                                        <td className="px-5 py-3 text-right">
                                            <div className="flex justify-end gap-1">
                                                {can('user.update') && (
                                                    <button
                                                        type="button"
                                                        onClick={() => openEdit(u)}
                                                        className="border-border-strong text-navy hover:bg-surface inline-flex items-center gap-1 rounded-[8px] border bg-white px-2.5 py-1.5 text-xs font-semibold dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800"
                                                    >
                                                        <Pencil className="h-3 w-3" />
                                                        {t('common.edit')}
                                                    </button>
                                                )}
                                                {can('user.delete') && (
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setDelTarget(u);
                                                            setDelOpen(true);
                                                        }}
                                                        className="border-danger-border bg-danger-bg text-danger hover:bg-danger/10 inline-flex items-center gap-1 rounded-[8px] border px-2.5 py-1.5 text-xs font-semibold dark:border-red-800 dark:text-red-400"
                                                    >
                                                        <Trash2 className="h-3 w-3" />
                                                        {t('common.delete')}
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={5} className="text-muted px-5 py-10 text-center text-sm dark:text-slate-400">
                                        {t('common.noData')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Toast
                visible={visible}
                tone={flash?.type === 'success' ? 'success' : 'error'}
                message={flash?.message}
                onDismiss={() => setVisible(false)}
            />

            <Modal
                open={mode !== null}
                title={mode === 'create' ? t('admin.users.createTitle') : t('admin.users.editTitle')}
                onClose={close}
                footer={
                    <>
                        <button
                            type="button"
                            onClick={close}
                            className="border-border-strong text-body hover:bg-surface rounded-[10px] border bg-white px-4 py-2 text-sm font-medium dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            {t('common.cancel')}
                        </button>
                        <button
                            type="submit"
                            form="user-form"
                            disabled={form.processing}
                            className="bg-primary hover:bg-primary-700 rounded-[10px] px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {form.processing ? t('common.saving') : mode === 'create' ? t('common.add') : t('common.save')}
                        </button>
                    </>
                }
            >
                <form id="user-form" onSubmit={submit} className="space-y-3">
                    <div>
                        <input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder={t('admin.users.nameLabel')}
                            className="border-border-strong text-ink placeholder:text-faint focus:border-primary h-10 w-full rounded-[10px] border bg-white px-3 text-sm focus:ring-2 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                        />
                        {form.errors.name && <p className="text-danger mt-1 text-[11px] dark:text-red-400">{form.errors.name}</p>}
                    </div>
                    <div>
                        <input
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            placeholder="Email"
                            className="border-border-strong text-ink placeholder:text-faint focus:border-primary h-10 w-full rounded-[10px] border bg-white px-3 text-sm focus:ring-2 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                        />
                        {form.errors.email && <p className="text-danger mt-1 text-[11px] dark:text-red-400">{form.errors.email}</p>}
                    </div>
                    <div>
                        <select
                            value={form.data.role_id}
                            onChange={(e) => form.setData('role_id', e.target.value)}
                            className="border-border-strong text-ink focus:border-primary h-10 w-full rounded-[10px] border bg-white px-3 text-sm focus:ring-2 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                        >
                            <option value="">— Pilih Role —</option>
                            {roles.map((r) => (
                                <option key={r.id} value={String(r.id)}>
                                    {r.label} ({r.name})
                                </option>
                            ))}
                        </select>
                        {form.errors.role_id && <p className="text-danger mt-1 text-[11px] dark:text-red-400">{form.errors.role_id}</p>}
                    </div>
                    <div>
                        <select
                            value={form.data.unit_id}
                            onChange={(e) => form.setData('unit_id', e.target.value)}
                            className="border-border-strong text-ink focus:border-primary h-10 w-full rounded-[10px] border bg-white px-3 text-sm focus:ring-2 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                        >
                            <option value="">— Tanpa Unit —</option>
                            {units.map((u) => (
                                <option key={u.id} value={String(u.id)}>
                                    {u.nama}
                                </option>
                            ))}
                        </select>
                        {form.errors.unit_id && <p className="text-danger mt-1 text-[11px] dark:text-red-400">{form.errors.unit_id}</p>}
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                open={delOpen}
                title={t('admin.users.deleteTitle')}
                description={delTarget ? t('admin.users.deleteConfirm', delTarget.name) : ''}
                confirmLabel={t('common.delete')}
                cancelLabel={t('common.cancel')}
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
