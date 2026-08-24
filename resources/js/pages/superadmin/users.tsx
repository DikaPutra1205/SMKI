import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Modal } from '@/components/ui/Modal';
import { Toast } from '@/components/ui/Toast';
import AppLayout from '@/layouts/AppLayout';
import { useCan } from '@/lib/can';
import { t } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Building2,
    Pencil,
    Plus,
    Search,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    UserCheck,
    Users as UsersIcon,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

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
    role?: { id: number; name: string; label: string } | string | null;
    unit: { id: number; nama: string } | null;
}
interface Props {
    users: UserRow[];
    roles: RoleOpt[];
    units: UnitOpt[];
}
type ModalMode = 'create' | 'edit' | null;
type FormData = { name: string; email: string; role_id: string; unit_id: string };

function getInitials(name: string): string {
    if (!name) return 'U';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
}

function resolveRoleInfo(u: UserRow, roles: RoleOpt[]) {
    if (u.role_id) {
        const found = roles.find((r) => r.id === u.role_id);
        if (found) return { name: found.name, label: found.label };
    }
    if (typeof u.role === 'object' && u.role?.name) {
        return { name: u.role.name, label: u.role.label || u.role.name };
    }
    if (typeof u.role === 'string') {
        const found = roles.find((r) => r.name.toLowerCase() === (u.role as string).toLowerCase());
        if (found) return { name: found.name, label: found.label };
        return { name: u.role, label: t(`role.${u.role}` as never) || u.role };
    }
    return { name: 'pic', label: 'PIC Unit' };
}

function getRoleBadge(roleName?: string, roleLabel?: string) {
    const role = (roleName || '').toLowerCase();
    if (role.includes('superadmin')) {
        return {
            label: roleLabel || 'Super Admin',
            classes: 'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-800 dark:bg-purple-950/40 dark:text-purple-400',
            dot: 'bg-purple-500',
        };
    }
    if (role.includes('compliance') || role.includes('kepatuhan') || role.includes('koordinator')) {
        return {
            label: roleLabel || 'Admin Kepatuhan',
            classes: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-400',
            dot: 'bg-blue-500',
        };
    }
    if (role.includes('auditor')) {
        return {
            label: roleLabel || 'Auditor',
            classes: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-400',
            dot: 'bg-amber-500',
        };
    }
    return {
        label: roleLabel || 'PIC Unit',
        classes: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400',
        dot: 'bg-emerald-500',
    };
}

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

    const [searchQuery, setSearchQuery] = useState('');
    const [selectedRole, setSelectedRole] = useState('all');
    const [selectedUnit, setSelectedUnit] = useState('all');

    const [mode, setMode] = useState<ModalMode>(null);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [delOpen, setDelOpen] = useState(false);
    const [delTarget, setDelTarget] = useState<UserRow | null>(null);
    const [delBusy, setDelBusy] = useState(false);

    const form = useForm<FormData>({ name: '', email: '', role_id: '', unit_id: '' });

    const userRoleInfoMap = useMemo(() => {
        const map = new Map<number, { name: string; label: string }>();
        users.forEach((u) => {
            map.set(u.id, resolveRoleInfo(u, roles));
        });
        return map;
    }, [users, roles]);

    const filteredUsers = useMemo(() => {
        return users.filter((u) => {
            if (searchQuery.trim()) {
                const q = searchQuery.toLowerCase();
                const matchName = u.name.toLowerCase().includes(q);
                const matchEmail = u.email.toLowerCase().includes(q);
                const matchUnit = u.unit?.nama.toLowerCase().includes(q);
                if (!matchName && !matchEmail && !matchUnit) return false;
            }
            if (selectedRole !== 'all' && String(u.role_id) !== selectedRole) {
                return false;
            }
            if (selectedUnit !== 'all') {
                if (selectedUnit === 'none' && u.unit_id !== null) return false;
                if (selectedUnit !== 'none' && String(u.unit_id) !== selectedUnit) return false;
            }
            return true;
        });
    }, [users, searchQuery, selectedRole, selectedUnit]);

    // Statistics counts
    const superadminCount = useMemo(
        () => users.filter((u) => userRoleInfoMap.get(u.id)?.name.toLowerCase().includes('superadmin')).length,
        [users, userRoleInfoMap],
    );
    const complianceCount = useMemo(
        () =>
            users.filter((u) => {
                const n = userRoleInfoMap.get(u.id)?.name.toLowerCase() || '';
                return n.includes('compliance') || n.includes('kepatuhan') || n.includes('koordinator');
            }).length,
        [users, userRoleInfoMap],
    );
    const picCount = useMemo(
        () => users.filter((u) => userRoleInfoMap.get(u.id)?.name.toLowerCase().includes('pic')).length,
        [users, userRoleInfoMap],
    );

    function openCreate() {
        form.reset();
        form.clearErrors();
        setEditingId(null);
        setMode('create');
    }

    function openEdit(u: UserRow) {
        form.setData({
            name: u.name,
            email: u.email,
            role_id: String(u.role_id),
            unit_id: u.unit_id ? String(u.unit_id) : '',
        });
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

            {/* Page Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{t('admin.users.title')}</h1>
                    <p className="text-muted mt-1 text-xs sm:text-sm text-slate-500 dark:text-slate-400">{t('admin.users.subtitle')}</p>
                </div>
                {can('user.create') && (
                    <button
                        type="button"
                        onClick={openCreate}
                        className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs sm:text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors"
                    >
                        <Plus className="h-4 w-4" />
                        {t('admin.users.addUser')}
                    </button>
                )}
            </div>

            {/* Quick KPI Stat Cards */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3.5 mb-6">
                <div
                    onClick={() => setSelectedRole('all')}
                    className={`cursor-pointer rounded-2xl border p-4 transition-all ${
                        selectedRole === 'all'
                            ? 'border-blue-500 bg-blue-50/50 shadow-sm dark:border-blue-500/60 dark:bg-blue-950/30'
                            : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900'
                    }`}
                >
                    <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                        <span className="text-xs font-semibold">Total Pengguna</span>
                        <UsersIcon className="h-4 w-4 text-blue-600" />
                    </div>
                    <span className="text-2xl font-bold text-slate-900 dark:text-white">{users.length}</span>
                </div>

                <div
                    onClick={() => {
                        const r = roles.find((role) => role.name.toLowerCase().includes('superadmin'));
                        if (r) setSelectedRole(String(r.id));
                    }}
                    className="cursor-pointer rounded-2xl border border-slate-200 bg-white p-4 hover:border-purple-300 transition-all dark:border-slate-800 dark:bg-slate-900"
                >
                    <div className="flex items-center justify-between text-purple-600 dark:text-purple-400 mb-1">
                        <span className="text-xs font-semibold">Super Admin</span>
                        <ShieldAlert className="h-4 w-4" />
                    </div>
                    <span className="text-2xl font-bold text-slate-900 dark:text-white">{superadminCount}</span>
                </div>

                <div
                    onClick={() => {
                        const r = roles.find((role) => role.name.toLowerCase().includes('compliance') || role.name.toLowerCase().includes('kepatuhan'));
                        if (r) setSelectedRole(String(r.id));
                    }}
                    className="cursor-pointer rounded-2xl border border-slate-200 bg-white p-4 hover:border-blue-300 transition-all dark:border-slate-800 dark:bg-slate-900"
                >
                    <div className="flex items-center justify-between text-blue-600 dark:text-blue-400 mb-1">
                        <span className="text-xs font-semibold">Admin Kepatuhan</span>
                        <ShieldCheck className="h-4 w-4" />
                    </div>
                    <span className="text-2xl font-bold text-slate-900 dark:text-white">{complianceCount}</span>
                </div>

                <div
                    onClick={() => {
                        const r = roles.find((role) => role.name.toLowerCase().includes('pic'));
                        if (r) setSelectedRole(String(r.id));
                    }}
                    className="cursor-pointer rounded-2xl border border-slate-200 bg-white p-4 hover:border-emerald-300 transition-all dark:border-slate-800 dark:bg-slate-900"
                >
                    <div className="flex items-center justify-between text-emerald-600 dark:text-emerald-400 mb-1">
                        <span className="text-xs font-semibold">PIC Unit Kerja</span>
                        <UserCheck className="h-4 w-4" />
                    </div>
                    <span className="text-2xl font-bold text-slate-900 dark:text-white">{picCount}</span>
                </div>
            </div>

            {/* Filter & Table Container */}
            <div className="border border-slate-200 overflow-hidden rounded-2xl bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                {/* Search & Select Filters */}
                <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex flex-wrap items-center gap-3">
                    <div className="relative flex-1 min-w-[240px]">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Cari nama, email, atau unit kerja..."
                            className="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pr-3 pl-9 text-xs sm:text-sm text-slate-700 placeholder-slate-400 transition-colors focus:border-blue-400 focus:bg-white focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300 dark:focus:bg-slate-900"
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

                    <div className="flex items-center gap-2">
                        <select
                            value={selectedRole}
                            onChange={(e) => setSelectedRole(e.target.value)}
                            className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs sm:text-sm text-slate-700 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            <option value="all">Semua Peran (Role)</option>
                            {roles.map((r) => (
                                <option key={r.id} value={String(r.id)}>
                                    {r.label}
                                </option>
                            ))}
                        </select>

                        <select
                            value={selectedUnit}
                            onChange={(e) => setSelectedUnit(e.target.value)}
                            className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs sm:text-sm text-slate-700 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            <option value="all">Semua Unit Kerja</option>
                            <option value="none">Tanpa Unit Kerja</option>
                            {units.map((u) => (
                                <option key={u.id} value={String(u.id)}>
                                    {u.nama}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-slate-200 bg-slate-50/80 text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th className="px-5 py-3.5">{t('admin.users.name')}</th>
                                <th className="px-5 py-3.5">Peran (Role)</th>
                                <th className="px-5 py-3.5">Unit Kerja</th>
                                <th className="px-5 py-3.5 text-right">{t('common.actions')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {filteredUsers.length ? (
                                filteredUsers.map((u) => {
                                    const roleInfo = userRoleInfoMap.get(u.id) || resolveRoleInfo(u, roles);
                                    const badge = getRoleBadge(roleInfo.name, roleInfo.label);
                                    const initials = getInitials(u.name);
                                    return (
                                        <tr key={u.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="px-5 py-3.5">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                                        {initials}
                                                    </div>
                                                    <div>
                                                        <div className="font-semibold text-slate-900 dark:text-white">{u.name}</div>
                                                        <div className="text-xs text-slate-400 dark:text-slate-500">{u.email}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-5 py-3.5">
                                                <span className={`inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-semibold ${badge.classes}`}>
                                                    <span className={`h-1.5 w-1.5 rounded-full ${badge.dot}`} />
                                                    {badge.label}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3.5 text-xs text-slate-600 dark:text-slate-300">
                                                {u.unit?.nama ? (
                                                    <span className="inline-flex items-center gap-1 text-slate-700 dark:text-slate-300 font-medium">
                                                        <Building2 className="h-3.5 w-3.5 text-slate-400" />
                                                        {u.unit.nama}
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400 dark:text-slate-500 italic">—</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 text-right">
                                                <div className="flex justify-end gap-1.5">
                                                    {can('user.update') && (
                                                        <button
                                                            type="button"
                                                            onClick={() => openEdit(u)}
                                                            className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
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
                                                            className="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-400"
                                                        >
                                                            <Trash2 className="h-3 w-3" />
                                                            {t('common.delete')}
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td colSpan={4} className="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
                                        Tidak ada pengguna yang cocok dengan kriteria pencarian.
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

            {/* Modal Create / Edit User */}
            <Modal
                open={mode !== null}
                title={mode === 'create' ? t('admin.users.createTitle') : t('admin.users.editTitle')}
                description="Kelola akun pengguna dan hak akses peran dalam sistem SMKI"
                onClose={close}
                maxWidth="md"
                footer={
                    <>
                        <button
                            type="button"
                            onClick={close}
                            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            {t('common.cancel')}
                        </button>
                        <button
                            type="submit"
                            form="user-form"
                            disabled={form.processing}
                            className="rounded-xl bg-blue-600 hover:bg-blue-700 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50 transition-colors shadow-sm"
                        >
                            {form.processing ? t('common.saving') : mode === 'create' ? t('common.add') : t('common.save')}
                        </button>
                    </>
                }
            >
                <form id="user-form" onSubmit={submit} className="space-y-4 pt-2">
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {t('admin.users.nameLabel')} <span className="text-red-500">*</span>
                        </label>
                        <input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Contoh: Dika Pratama"
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        {form.errors.name && <p className="text-red-500 mt-1 text-xs">{form.errors.name}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Alamat Email <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            placeholder="Contoh: user@instansi.go.id"
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        {form.errors.email && <p className="text-red-500 mt-1 text-xs">{form.errors.email}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Peran Pengguna (Role) <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={form.data.role_id}
                            onChange={(e) => form.setData('role_id', e.target.value)}
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        >
                            <option value="">— Pilih Role —</option>
                            {roles.map((r) => (
                                <option key={r.id} value={String(r.id)}>
                                    {r.label} ({r.name})
                                </option>
                            ))}
                        </select>
                        {form.errors.role_id && <p className="text-red-500 mt-1 text-xs">{form.errors.role_id}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Unit Kerja <span className="text-slate-400 font-normal">(Wajib untuk PIC)</span>
                        </label>
                        <select
                            value={form.data.unit_id}
                            onChange={(e) => form.setData('unit_id', e.target.value)}
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        >
                            <option value="">— Tanpa Unit Kerja —</option>
                            {units.map((u) => (
                                <option key={u.id} value={String(u.id)}>
                                    {u.nama}
                                </option>
                            ))}
                        </select>
                        {form.errors.unit_id && <p className="text-red-500 mt-1 text-xs">{form.errors.unit_id}</p>}
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

