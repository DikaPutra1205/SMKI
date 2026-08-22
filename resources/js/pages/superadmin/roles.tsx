import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Modal } from '@/components/ui/Modal';
import { Toast } from '@/components/ui/Toast';
import AppLayout from '@/layouts/AppLayout';
import { useCan } from '@/lib/can';
import { t } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ChevronDown, KeyRound, Pencil, Plus, Search, Shield, ShieldCheck, Trash2 } from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';

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

const MODULE_METADATA: Record<string, { label: string; desc: string }> = {
    checklist: {
        label: 'Penilaian & Sesi Checklist',
        desc: 'Pengisian, pengajuan, dan verifikasi asesmen kepatuhan kontrol.',
    },
    evidence: {
        label: 'Dokumen Bukti (Evidence)',
        desc: 'Pengunggahan, pengelolaan, dan validasi berkas pendukung.',
    },
    controls: {
        label: 'Pustaka Kontrol SMKI',
        desc: 'Master daftar klausul dan kontrol standar kepatuhan.',
    },
    frameworks: {
        label: 'Standar Framework',
        desc: 'Pengelolaan master framework seperti ISO/IEC 27001 & 27701.',
    },
    findings: {
        label: 'Temuan Audit (Findings)',
        desc: 'Pencatatan ketidaksesuaian (gap), observasi, dan rencana tindak lanjut.',
    },
    risks: {
        label: 'Register Risiko Keamanan',
        desc: 'Identifikasi risiko, evaluasi dampak, dan rencana mitigasi.',
    },
    reports: {
        label: 'Laporan & Rekapitulasi',
        desc: 'Pembuatan dan pengunduhan laporan kepatuhan eksekutif.',
    },
    'audit-logs': {
        label: 'Audit Trail Sistem',
        desc: 'Pencatatan rekam jejak aktivitas yang bersifat permanen (immutable).',
    },
    users: {
        label: 'Manajemen Pengguna',
        desc: 'Pengelolaan akun pengguna (PIC, Auditor, dan Administrator).',
    },
    roles: {
        label: 'Manajemen Role & Izin',
        desc: 'Konfigurasi peran dan alokasi hak akses sistem.',
    },
    'work-units': {
        label: 'Unit Kerja Organisasi',
        desc: 'Pengelolaan daftar divisi, direktorat, dan unit kerja.',
    },
    dashboard: {
        label: 'Dashboard & Analitik',
        desc: 'Akses ke dashboard analisis, KPI kepatuhan, dan ringkasan eksekutif.',
    },
};

const ACTION_DESCRIPTIONS: Record<string, string> = {
    view: 'Melihat data dan daftar pada modul ini',
    create: 'Menambahkan data atau dokumen baru',
    update: 'Mengubah dan memperbarui data yang ada',
    delete: 'Menghapus data dari sistem',
    'bulk-verify': 'Memverifikasi penilaian (satuan & massal)',
    upload: 'Mengunggah berkas dokumen bukti',
    export: 'Mengekspor laporan ke format PDF / CSV',
};

function getPermissionLabel(key: string): { actionName: string; actionDesc: string } {
    const parts = key.split('.');
    const actionKey = parts[parts.length - 1];

    let actionName = actionKey;
    if (actionKey === 'view') actionName = 'Melihat Data';
    else if (actionKey === 'create') actionName = 'Tambah Data';
    else if (actionKey === 'update') actionName = 'Ubah / Edit';
    else if (actionKey === 'delete') actionName = 'Hapus Data';
    else if (actionKey === 'bulk-verify') actionName = 'Verifikasi Penilaian';
    else if (actionKey === 'upload') actionName = 'Unggah Bukti';
    else if (actionKey === 'export') actionName = 'Ekspor Laporan';

    const actionDesc = ACTION_DESCRIPTIONS[actionKey] || `Hak akses untuk ${key}`;

    return { actionName, actionDesc };
}

export default function Roles({ roles, permissionCatalog }: Props) {
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
    const [delTarget, setDelTarget] = useState<RoleRow | null>(null);
    const [delBusy, setDelBusy] = useState(false);
    const [selectedPerms, setSelectedPerms] = useState<Set<string>>(new Set());

    // Search and filter inside modal
    const [query, setQuery] = useState('');
    const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});

    const form = useForm<{ name: string; label: string }>({ name: '', label: '' });

    function openCreate() {
        form.reset();
        form.clearErrors();
        setEditingId(null);
        setSelectedPerms(new Set());
        setQuery('');
        setMode('create');
    }

    function openEdit(r: RoleRow) {
        form.setData({ name: r.name, label: r.label });
        form.clearErrors();
        setEditingId(r.id);
        setSelectedPerms(new Set(r.permissions.map((p) => p.key)));
        setQuery('');
        setMode('edit');
    }

    function close() {
        setMode(null);
        setEditingId(null);
        setSelectedPerms(new Set());
        form.reset();
        form.clearErrors();
        setQuery('');
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (mode === 'create') {
            form.post('/admin/superadmin/roles', { onSuccess: close });
        } else if (mode === 'edit' && editingId) {
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

    const breadcrumbs = [{ label: t('common.dashboard'), href: '/admin/superadmin/dashboard' }, { label: 'Manajemen Role & Hak Akses' }];

    const totalAvailablePermissions = useMemo(() => Object.values(permissionCatalog).flat().length, [permissionCatalog]);

    const modules = useMemo(
        () =>
            Object.keys(permissionCatalog).filter((m) => {
                if (!query) return true;
                const q = query.toLowerCase();
                const modMeta = MODULE_METADATA[m];
                const modLabel = modMeta?.label?.toLowerCase() ?? m.toLowerCase();
                return (
                    m.toLowerCase().includes(q) ||
                    modLabel.includes(q) ||
                    permissionCatalog[m].some((k) => {
                        const { actionName, actionDesc } = getPermissionLabel(k);
                        return k.toLowerCase().includes(q) || actionName.toLowerCase().includes(q) || actionDesc.toLowerCase().includes(q);
                    })
                );
            }),
        [permissionCatalog, query],
    );

    function isModuleAllChecked(mod: string) {
        return permissionCatalog[mod].every((k) => selectedPerms.has(k));
    }

    function isModulePartialChecked(mod: string) {
        const checkedCount = permissionCatalog[mod].filter((k) => selectedPerms.has(k)).length;
        return checkedCount > 0 && checkedCount < permissionCatalog[mod].length;
    }

    function toggleGroup(mod: string) {
        const keys = permissionCatalog[mod];
        const allOn = isModuleAllChecked(mod);
        setSelectedPerms((prev) => {
            const next = new Set(prev);
            keys.forEach((k) => {
                if (allOn) next.delete(k);
                else next.add(k);
            });
            return next;
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/superadmin/roles">
            <Head title="Manajemen Role & Hak Akses" />

            <Toast
                visible={visible}
                tone={flash?.type === 'success' ? 'success' : 'error'}
                message={flash?.message}
                onDismiss={() => setVisible(false)}
            />

            {/* Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Manajemen Role & Hak Akses</h1>
                    <p className="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                        Atur peran pengguna dan alokasikan izin akses modul kepatuhan secara granular.
                    </p>
                </div>
                {can('role.create') && (
                    <button
                        type="button"
                        onClick={openCreate}
                        className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition-all hover:bg-blue-500 active:scale-95 sm:text-sm"
                    >
                        <Plus className="h-4 w-4" />
                        Tambah Role Baru
                    </button>
                )}
            </div>

            {/* KPI / Quick Metric Row */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                            <Shield className="h-5 w-5" />
                        </div>
                        <div>
                            <p className="text-xs font-medium text-slate-500 dark:text-slate-400">Total Role Terdaftar</p>
                            <h3 className="text-xl font-bold text-slate-900 dark:text-white">{roles.length} Role</h3>
                        </div>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                            <KeyRound className="h-5 w-5" />
                        </div>
                        <div>
                            <p className="text-xs font-medium text-slate-500 dark:text-slate-400">Total Hak Akses Sistem</p>
                            <h3 className="text-xl font-bold text-slate-900 dark:text-white">{totalAvailablePermissions} Hak Akses</h3>
                        </div>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                            <ShieldCheck className="h-5 w-5" />
                        </div>
                        <div>
                            <p className="text-xs font-medium text-slate-500 dark:text-slate-400">Modul Fungsional</p>
                            <h3 className="text-xl font-bold text-slate-900 dark:text-white">{Object.keys(permissionCatalog).length} Modul</h3>
                        </div>
                    </div>
                </div>
            </div>

            {/* Role List Cards */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {roles.length > 0 ? (
                    roles.map((r) => {
                        const permCount = r.permissions.length;
                        const pct = Math.round((permCount / totalAvailablePermissions) * 100);

                        return (
                            <div
                                key={r.id}
                                className="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition-all hover:border-blue-200 hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div>
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <h3 className="text-base font-bold text-slate-900 dark:text-white">{r.label}</h3>
                                                <span className="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                    {r.name}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                {r.description || `Role ${r.label} untuk pengelolaan modul kepatuhan sistem.`}
                                            </p>
                                        </div>

                                        <span className="inline-flex shrink-0 items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950/60 dark:text-blue-400">
                                            {permCount} Hak Akses
                                        </span>
                                    </div>

                                    {/* Permission coverage bar */}
                                    <div className="mt-4">
                                        <div className="mb-1.5 flex items-center justify-between text-[11px]">
                                            <span className="font-medium text-slate-500 dark:text-slate-400">Cakupan Hak Akses</span>
                                            <span className="font-bold text-slate-700 dark:text-slate-300">
                                                {pct}% ({permCount}/{totalAvailablePermissions})
                                            </span>
                                        </div>
                                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                            <div
                                                className="h-full rounded-full bg-blue-600 transition-all duration-300"
                                                style={{ width: `${pct}%` }}
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Actions Footer */}
                                <div className="mt-5 flex items-center justify-end gap-2 border-t border-slate-100 pt-3.5 dark:border-slate-800">
                                    {can('role.update') && (
                                        <button
                                            type="button"
                                            onClick={() => openEdit(r)}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                        >
                                            <Pencil className="h-3.5 w-3.5" />
                                            Kelola Hak Akses
                                        </button>
                                    )}
                                    {can('role.delete') && r.name !== 'superadmin' && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setDelTarget(r);
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
                        );
                    })
                ) : (
                    <div className="col-span-2 rounded-2xl border border-slate-200 bg-white p-12 text-center dark:border-slate-800 dark:bg-slate-900">
                        <Shield className="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600" />
                        <h4 className="mt-3 text-sm font-bold text-slate-700 dark:text-slate-300">Belum ada role terdaftar</h4>
                        <p className="mt-1 text-xs text-slate-500">Klik tombol "Tambah Role Baru" untuk membuat peran baru.</p>
                    </div>
                )}
            </div>

            {/* Modal Form: Create / Edit Role */}
            <Modal
                open={mode !== null}
                title={mode === 'create' ? 'Tambah Role Baru' : `Kelola Hak Akses: ${form.data.label || 'Role'}`}
                onClose={close}
                maxWidth="xl"
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
                            form="role-form"
                            disabled={form.processing}
                            className="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-5 py-2 text-xs font-semibold text-white shadow-sm transition-all hover:bg-blue-500 active:scale-95 disabled:opacity-50"
                        >
                            {form.processing ? 'Menyimpan…' : mode === 'create' ? 'Simpan Role' : 'Perbarui Hak Akses'}
                        </button>
                    </>
                }
            >
                <form id="role-form" onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Label Role Resmi <span className="text-red-500">*</span>
                            </label>
                            <input
                                value={form.data.label}
                                onChange={(e) => form.setData('label', e.target.value)}
                                placeholder="Contoh: Administrator Kepatuhan"
                                className="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                            {form.errors.label && <p className="mt-1 text-[11px] font-medium text-red-500">{form.errors.label}</p>}
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Nama Identifikasi Sistem <span className="text-red-500">*</span>
                            </label>
                            <input
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder="Contoh: admin_kepatuhan"
                                className="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 font-mono text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                            {form.errors.name && <p className="mt-1 text-[11px] font-medium text-red-500">{form.errors.name}</p>}
                        </div>
                    </div>

                    {mode === 'edit' && (
                        <div className="rounded-2xl border border-slate-200/90 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                            {/* Permissions Header & Global Actions */}
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h4 className="text-sm font-bold text-slate-900 dark:text-white">Alokasi Hak Akses Modul</h4>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        Pilih izin yang diizinkan untuk peran ini ({selectedPerms.size} dari {totalAvailablePermissions} aktif).
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setSelectedPerms(new Set(Object.values(permissionCatalog).flat()))}
                                        className="text-xs font-semibold text-blue-600 hover:text-blue-500 dark:text-blue-400"
                                    >
                                        Pilih Semua Izin
                                    </button>
                                    <span className="text-slate-300 dark:text-slate-600">·</span>
                                    <button
                                        type="button"
                                        onClick={() => setSelectedPerms(new Set())}
                                        className="text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                                    >
                                        Kosongkan
                                    </button>
                                </div>
                            </div>

                            {/* Search Filter */}
                            <div className="relative mt-3">
                                <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Cari modul atau hak akses..."
                                    className="h-9 w-full rounded-xl border border-slate-200 bg-white py-1.5 pr-3 pl-9 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>

                            {/* Structured Module Accordion List */}
                            <div className="mt-3 max-h-[48vh] space-y-2.5 overflow-y-auto pr-1">
                                {modules.length > 0 ? (
                                    modules.map((mod) => {
                                        const meta = MODULE_METADATA[mod] || { label: mod, desc: `Modul ${mod}` };
                                        const keys = permissionCatalog[mod].filter((k) => {
                                            if (!query) return true;
                                            const q = query.toLowerCase();
                                            const { actionName, actionDesc } = getPermissionLabel(k);
                                            return (
                                                k.toLowerCase().includes(q) ||
                                                actionName.toLowerCase().includes(q) ||
                                                actionDesc.toLowerCase().includes(q)
                                            );
                                        });

                                        const allChecked = isModuleAllChecked(mod);
                                        const partialChecked = isModulePartialChecked(mod);
                                        const isCollapsed = !!collapsed[mod];
                                        const activeCount = permissionCatalog[mod].filter((k) => selectedPerms.has(k)).length;

                                        return (
                                            <div
                                                key={mod}
                                                className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
                                            >
                                                {/* Module Group Header */}
                                                <div className="flex items-center justify-between gap-3 bg-slate-50/70 px-4 py-2.5 dark:bg-slate-800/40">
                                                    <div className="flex items-center gap-3">
                                                        <input
                                                            type="checkbox"
                                                            checked={allChecked}
                                                            ref={(el) => {
                                                                if (el) el.indeterminate = partialChecked;
                                                            }}
                                                            onChange={() => toggleGroup(mod)}
                                                            className="h-4 w-4 rounded-md border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900"
                                                        />
                                                        <div>
                                                            <div className="flex items-center gap-2">
                                                                <h5 className="text-xs font-bold text-slate-900 dark:text-white">{meta.label}</h5>
                                                                <span className="py-0.2 rounded-full bg-slate-200/80 px-2 text-[10px] font-bold text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                                                    {activeCount}/{permissionCatalog[mod].length} Aktif
                                                                </span>
                                                            </div>
                                                            <p className="text-[11px] text-slate-500 dark:text-slate-400">{meta.desc}</p>
                                                        </div>
                                                    </div>

                                                    <button
                                                        type="button"
                                                        onClick={() => setCollapsed((p) => ({ ...p, [mod]: !p[mod] }))}
                                                        className="rounded-lg p-1 text-slate-400 hover:bg-slate-200/60 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-white"
                                                    >
                                                        <ChevronDown
                                                            className={`h-4 w-4 transition-transform duration-200 ${isCollapsed ? '-rotate-90' : ''}`}
                                                        />
                                                    </button>
                                                </div>

                                                {/* Permission Items */}
                                                {!isCollapsed && (
                                                    <div className="grid grid-cols-1 gap-2 p-3 sm:grid-cols-2">
                                                        {keys.map((key) => {
                                                            const on = selectedPerms.has(key);
                                                            const { actionName, actionDesc } = getPermissionLabel(key);

                                                            return (
                                                                <label
                                                                    key={key}
                                                                    className={`flex cursor-pointer items-start gap-2.5 rounded-xl border p-2.5 transition-all ${
                                                                        on
                                                                            ? 'border-blue-300 bg-blue-50/60 ring-1 ring-blue-400/20 dark:border-blue-800 dark:bg-blue-950/30'
                                                                            : 'border-slate-200/80 bg-white hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700'
                                                                    }`}
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={on}
                                                                        onChange={() => togglePerm(key)}
                                                                        className="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900"
                                                                    />
                                                                    <div className="min-w-0 flex-1">
                                                                        <div className="flex items-center justify-between gap-1">
                                                                            <span
                                                                                className={`text-xs font-bold ${on ? 'text-blue-900 dark:text-blue-300' : 'text-slate-900 dark:text-white'}`}
                                                                            >
                                                                                {actionName}
                                                                            </span>
                                                                            <span className="font-mono text-[9.5px] text-slate-400">{key}</span>
                                                                        </div>
                                                                        <p className="mt-0.5 text-[11px] leading-tight text-slate-500 dark:text-slate-400">
                                                                            {actionDesc}
                                                                        </p>
                                                                    </div>
                                                                </label>
                                                            );
                                                        })}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })
                                ) : (
                                    <div className="py-8 text-center text-xs text-slate-500 dark:text-slate-400">
                                        Tidak ada hak akses yang sesuai dengan pencarian "{query}".
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </form>
            </Modal>

            {/* Confirm Delete Dialog */}
            <ConfirmDialog
                open={delOpen}
                title="Hapus Role Ini?"
                description={
                    delTarget
                        ? `Apakah Anda yakin ingin menghapus role "${delTarget.label}" (${delTarget.name})? Tindakan ini tidak dapat dibatalkan.`
                        : ''
                }
                confirmLabel="Hapus Role"
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
