import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Modal } from '@/components/ui/Modal';
import { Toast } from '@/components/ui/Toast';
import AppLayout from '@/layouts/AppLayout';
import { t } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ChevronDown, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface RolePerm {
    id: number;
    key: string;
    module: string;
}
interface RoleRow {
    id: number;
    name: string;
    label: string;
    description: string | null;
    permissions: RolePerm[];
}
interface Props {
    roles: RoleRow[];
    permissionCatalog: Record<string, string[]>;
}

type ModalMode = 'create' | 'edit' | null;

export default function Roles({ roles, permissionCatalog }: Props) {
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
    const [delTarget, setDelTarget] = useState<RoleRow | null>(null);
    const [delBusy, setDelBusy] = useState(false);
    const [selectedPerms, setSelectedPerms] = useState<Set<string>>(new Set());

    const form = useForm<{ name: string; label: string }>({ name: '', label: '' });

    function openCreate() {
        form.reset();
        form.clearErrors();
        setEditingId(null);
        setSelectedPerms(new Set());
        setMode('create');
    }
    function openEdit(r: RoleRow) {
        form.setData({ name: r.name, label: r.label });
        form.clearErrors();
        setEditingId(r.id);
        setSelectedPerms(new Set(r.permissions.map((p) => p.key)));
        setMode('edit');
    }
    function close() {
        setMode(null);
        setEditingId(null);
        setSelectedPerms(new Set());
        form.reset();
        form.clearErrors();
    }
    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (mode === 'create') form.post('/admin/superadmin/roles', { onSuccess: close });
        else if (mode === 'edit' && editingId) {
            form.transform((data) => ({ ...data, permissions: Array.from(selectedPerms) }));
            form.patch(`/admin/superadmin/roles/${editingId}`, { onSuccess: close });
        }
    }
    function togglePerm(key: string) {
        setSelectedPerms((prev) => {
            const next = new Set(prev);
            if (next.has(key)) next.delete(key);
            else next.add(key);
            return next;
        });
    }
    function confirmDelete() {
        if (!delTarget) return;
        setDelBusy(true);
        router.delete(`/admin/superadmin/roles/${delTarget.id}`, {
            onFinish: () => {
                setDelBusy(false);
                setDelOpen(false);
                setDelTarget(null);
            },
        });
    }

    const [query, setQuery] = useState('');
    const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/admin/superadmin/dashboard' }, { label: t('admin.roles.title') }];
    const modules = useMemo(
        () =>
            Object.keys(permissionCatalog).filter(
                (m) =>
                    !query ||
                    m.toLowerCase().includes(query.toLowerCase()) ||
                    permissionCatalog[m].some((k) => k.toLowerCase().includes(query.toLowerCase())),
            ),
        [permissionCatalog, query],
    );

    function allChecked(mod: string) {
        return permissionCatalog[mod].every((k) => selectedPerms.has(k));
    }
    function toggleGroup(mod: string) {
        const keys = permissionCatalog[mod];
        const on = allChecked(mod);
        setSelectedPerms((prev) => {
            const n = new Set(prev);
            keys.forEach((k) => (on ? n.delete(k) : n.add(k)));
            return n;
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/superadmin/roles">
            <Head title={t('admin.roles.title')} />
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('admin.roles.title')}</h1>
                    <p className="text-muted mt-1 text-xs sm:text-sm">{t('admin.roles.subtitle')}</p>
                </div>
                <button
                    type="button"
                    onClick={openCreate}
                    className="bg-primary shadow-blue hover:bg-primary-700 inline-flex items-center gap-2 rounded-[10px] px-4 py-2 text-xs font-semibold text-white transition-colors sm:text-sm"
                >
                    <Plus className="h-4 w-4" />
                    {t('admin.roles.addRole')}
                </button>
            </div>

            <div className="border-border overflow-hidden rounded-[14px] border bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="border-border bg-surface/60 text-muted border-b text-[11px] font-bold tracking-wider uppercase">
                            <tr>
                                <th className="px-5 py-3">{t('admin.roles.label')}</th>
                                <th className="px-5 py-3">{t('admin.roles.name')}</th>
                                <th className="px-5 py-3">{t('admin.roles.grants')}</th>
                                <th className="px-5 py-3 text-right">{t('common.actions')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {roles.length ? (
                                roles.map((r) => (
                                    <tr key={r.id} className="hover:bg-surface/50">
                                        <td className="text-navy px-5 py-3 font-medium">{r.label}</td>
                                        <td className="text-body px-5 py-3 text-xs">{r.name}</td>
                                        <td className="px-5 py-3">
                                            <span className="bg-primary-50 text-primary rounded-[6px] px-2 py-0.5 text-xs font-semibold">
                                                {r.permissions.length} {t('admin.roles.grants')}
                                            </span>
                                        </td>
                                        <td className="px-5 py-3 text-right">
                                            <div className="flex justify-end gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => openEdit(r)}
                                                    className="border-border-strong text-navy hover:bg-surface inline-flex items-center gap-1 rounded-[8px] border bg-white px-2.5 py-1.5 text-xs font-semibold"
                                                >
                                                    <Pencil className="h-3 w-3" />
                                                    {t('common.edit')}
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setDelTarget(r);
                                                        setDelOpen(true);
                                                    }}
                                                    className="border-danger-border bg-danger-bg text-danger hover:bg-danger/10 inline-flex items-center gap-1 rounded-[8px] border px-2.5 py-1.5 text-xs font-semibold"
                                                >
                                                    <Trash2 className="h-3 w-3" />
                                                    {t('common.delete')}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={4} className="text-muted px-5 py-10 text-center text-sm">
                                        {t('admin.roles.noRoles')}
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
                title={mode === 'create' ? t('admin.roles.createTitle') : t('admin.roles.editTitle')}
                onClose={close}
                maxWidth={mode === 'edit' ? 'xl' : 'lg'}
                footer={
                    <>
                        <button
                            type="button"
                            onClick={close}
                            className="border-border-strong text-body hover:bg-surface rounded-[10px] border bg-white px-4 py-2 text-sm font-medium"
                        >
                            {t('common.cancel')}
                        </button>
                        <button
                            type="submit"
                            form="role-form"
                            disabled={form.processing}
                            className="bg-primary hover:bg-primary-700 rounded-[10px] px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {form.processing ? t('common.saving') : mode === 'create' ? t('common.add') : t('common.save')}
                        </button>
                    </>
                }
            >
                <form id="role-form" onSubmit={submit} className="space-y-3">
                    <div>
                        <input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder={t('admin.roles.name')}
                            className="border-border-strong text-ink placeholder:text-faint focus:border-primary h-10 w-full rounded-[10px] border bg-white px-3 text-sm focus:ring-2 focus:outline-none"
                        />
                        {form.errors.name && <p className="text-danger mt-1 text-[11px]">{form.errors.name}</p>}
                    </div>
                    <div>
                        <input
                            value={form.data.label}
                            onChange={(e) => form.setData('label', e.target.value)}
                            placeholder={t('admin.roles.label')}
                            className="border-border-strong text-ink placeholder:text-faint focus:border-primary h-10 w-full rounded-[10px] border bg-white px-3 text-sm focus:ring-2 focus:outline-none"
                        />
                        {form.errors.label && <p className="text-danger mt-1 text-[11px]">{form.errors.label}</p>}
                    </div>

                    {mode === 'edit' && (
                        <div className="border-border mt-2 overflow-hidden rounded-[10px] border">
                            <div className="bg-surface/60 flex items-center justify-between border-b px-4 py-3">
                                <p className="text-navy text-xs font-bold tracking-wider uppercase">
                                    {t('admin.roles.grants')} · {selectedPerms.size}/{Object.values(permissionCatalog).flat().length}
                                </p>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setSelectedPerms(new Set(Object.values(permissionCatalog).flat()))}
                                        className="text-primary text-[11px] font-semibold hover:underline"
                                    >
                                        Select all
                                    </button>
                                    <span className="text-muted text-[11px]">·</span>
                                    <button
                                        type="button"
                                        onClick={() => setSelectedPerms(new Set())}
                                        className="text-muted text-[11px] font-semibold hover:underline"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>
                            <div className="bg-surface/40 relative border-b px-3 py-2">
                                <Search className="text-faint pointer-events-none absolute top-1/2 left-6 h-3.5 w-3.5 -translate-y-1/2" />
                                <input
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Filter module / permission…"
                                    className="border-border-strong text-ink placeholder:text-faint focus:border-primary h-8 w-full rounded-[8px] border bg-white py-1.5 pr-3 pl-8 text-xs focus:ring-1 focus:outline-none"
                                />
                            </div>
                            <div className="max-h-[50vh] overflow-y-auto">
                                {modules.length ? (
                                    modules.map((mod) => {
                                        const keys = permissionCatalog[mod].filter((k) => !query || k.toLowerCase().includes(query.toLowerCase()));
                                        const checked = permissionCatalog[mod].every((k) => selectedPerms.has(k));
                                        const partial = !checked && permissionCatalog[mod].some((k) => selectedPerms.has(k));
                                        const isCollapsed = !!collapsed[mod];
                                        return (
                                            <div key={mod} className="border-border border-b last:border-0">
                                                <div className="flex items-center gap-2 px-3 py-2">
                                                    <input
                                                        type="checkbox"
                                                        checked={checked}
                                                        ref={(el) => {
                                                            if (el) el.indeterminate = partial;
                                                        }}
                                                        onChange={() => toggleGroup(mod)}
                                                        className="accent-primary h-3.5 w-3.5"
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => setCollapsed((p) => ({ ...p, [mod]: !p[mod] }))}
                                                        className="flex flex-1 items-center justify-between text-left"
                                                    >
                                                        <span className="text-navy text-xs font-bold tracking-wider uppercase">
                                                            {mod}{' '}
                                                            <span className="text-muted ml-1 text-[11px] font-medium normal-case">
                                                                {permissionCatalog[mod].filter((k) => selectedPerms.has(k)).length}/
                                                                {permissionCatalog[mod].length}
                                                            </span>
                                                        </span>
                                                        <ChevronDown
                                                            className={`text-muted h-3.5 w-3.5 transition-transform ${isCollapsed ? '-rotate-90' : ''}`}
                                                        />
                                                    </button>
                                                </div>
                                                {!isCollapsed && (
                                                    <div className="flex flex-wrap gap-1.5 px-3 pt-0 pb-2.5">
                                                        {keys.map((key) => {
                                                            const on = selectedPerms.has(key);
                                                            return (
                                                                <label
                                                                    key={key}
                                                                    className={`inline-flex cursor-pointer items-center gap-1.5 rounded-[8px] border px-2.5 py-1.5 text-xs transition-colors ${on ? 'border-primary/30 bg-primary-50 text-primary font-medium' : 'border-border-strong hover:bg-surface text-body bg-white'}`}
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={on}
                                                                        onChange={() => togglePerm(key)}
                                                                        className="accent-primary h-3 w-3"
                                                                    />
                                                                    <span>{key.split('.')[1] ?? key}</span>
                                                                </label>
                                                            );
                                                        })}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })
                                ) : (
                                    <p className="text-muted py-8 text-center text-xs">No permissions match filter.</p>
                                )}
                            </div>
                        </div>
                    )}
                </form>
            </Modal>

            <ConfirmDialog
                open={delOpen}
                title={t('admin.roles.deleteTitle')}
                description={delTarget ? t('admin.roles.deleteConfirm', delTarget.label) : ''}
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
