import AppLayout from '@/layouts/AppLayout';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Database, FileText, Settings, Shield, Users } from 'lucide-react';

interface FrameworkSummary {
    id: number;
    nama: string;
    versi: string;
    controls_count: number;
}

interface SuperadminDashboardProps {
    totalUsers: number;
    totalFrameworks: number;
    totalControls: number;
    frameworks: FrameworkSummary[];
}

export default function SuperadminDashboard({ totalUsers, totalFrameworks, totalControls, frameworks }: SuperadminDashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const userName = auth.user?.name || 'Administrator';

    const breadcrumbs = [{ label: 'Dashboard' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs} currentPath="/admin/superadmin/dashboard">
            <Head title="Dashboard - Superadmin" />

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Dashboard Superadmin</h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400">
                        Selamat datang kembali, <span className="font-semibold text-slate-800 dark:text-slate-200">{userName}</span>. Kelola
                        seluruh konfigurasi sistem kepatuhan digital.
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">Total Users</span>
                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                            <Users className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <span className="text-3xl font-bold text-slate-900 dark:text-white">{totalUsers}</span>
                    </div>
                    <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">Semua role aktif</p>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">Framework</span>
                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-purple-50 text-purple-600 dark:bg-purple-950/50 dark:text-purple-400">
                            <Database className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <span className="text-3xl font-bold text-slate-900 dark:text-white">{totalFrameworks}</span>
                    </div>
                    <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">Standar kepatuhan terdaftar</p>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">Total Kontrol</span>
                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                            <Shield className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <span className="text-3xl font-bold text-slate-900 dark:text-white">{totalControls}</span>
                    </div>
                    <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">Klausul aktif seluruh framework</p>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">Kebijakan</span>
                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                            <FileText className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <span className="text-3xl font-bold text-slate-900 dark:text-white">-</span>
                    </div>
                    <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">Segera hadir</p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                        <div>
                            <h2 className="text-base font-bold text-slate-900 dark:text-white">Framework Overview</h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Daftar seluruh framework kepatuhan</p>
                        </div>
                        <Link
                            href="/admin/superadmin/frameworks"
                            className="text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400"
                        >
                            Kelola &rarr;
                        </Link>
                    </div>
                    <div className="mt-4 space-y-3">
                        {frameworks.length > 0 ? (
                            frameworks.map((fw) => (
                                <div
                                    key={fw.id}
                                    className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50/60 p-3 dark:border-slate-800 dark:bg-slate-800/40"
                                >
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 text-white shadow-xs">
                                            <Database className="h-4 w-4" />
                                        </div>
                                        <div>
                                            <h3 className="text-sm font-bold text-slate-900 dark:text-white">{fw.nama}</h3>
                                            <p className="text-xs text-slate-500 dark:text-slate-400">Versi {fw.versi}</p>
                                        </div>
                                    </div>
                                    <span className="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                        {fw.controls_count} kontrol
                                    </span>
                                </div>
                            ))
                        ) : (
                            <p className="py-4 text-center text-sm text-slate-400">Belum ada framework terdaftar.</p>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                    <div className="border-b border-slate-100 pb-4 dark:border-slate-800">
                        <h2 className="text-base font-bold text-slate-900 dark:text-white">Quick Actions</h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400">Akses cepat ke manajemen sistem</p>
                    </div>
                    <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Link
                            href="/admin/superadmin/frameworks"
                            className="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50/60 p-4 transition-colors hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-800/40 dark:hover:bg-slate-800"
                        >
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 text-purple-600 dark:bg-purple-950/50 dark:text-purple-400">
                                <Database className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">Framework Management</h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400">CRUD framework & kontrol</p>
                            </div>
                        </Link>

                        <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50/60 p-4 opacity-50 dark:border-slate-800 dark:bg-slate-800/40">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                                <Users className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">User Management</h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400">Segera hadir</p>
                            </div>
                        </div>

                        <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50/60 p-4 opacity-50 dark:border-slate-800 dark:bg-slate-800/40">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                                <Settings className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">System Settings</h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400">Segera hadir</p>
                            </div>
                        </div>

                        <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50/60 p-4 opacity-50 dark:border-slate-800 dark:bg-slate-800/40">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                                <FileText className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">Audit Logs</h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400">Segera hadir</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
