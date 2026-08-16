import AdminKepatuhanLayout from '@/layouts/AdminKepatuhanLayout';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowUpRight, CheckCircle2, Clock, FileCheck, Layers, ShieldAlert, ShieldCheck, TrendingUp } from 'lucide-react';

export default function Dashboard() {
    const { auth } = usePage<SharedData>().props;
    const userName = auth.user?.name || 'Siti Aisyah';

    const breadcrumbs = [{ label: 'Dashboard' }];

    return (
        <AdminKepatuhanLayout breadcrumbs={breadcrumbs} currentPath="/admin/kepatuhan/dashboard">
            <Head title="Dashboard - Admin Kepatuhan" />

            {/* Welcome Header */}
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Dashboard Kepatuhan</h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400">
                        Selamat datang kembali, <span className="font-semibold text-slate-800 dark:text-slate-200">{userName}</span>. Berikut
                        ringkasan status kepatuhan digital organisasi Anda.
                    </p>
                </div>

                <div className="flex items-center gap-3">
                    <Link
                        href="/admin/kepatuhan/compliance"
                        className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700"
                    >
                        <ShieldCheck className="h-4 w-4" />
                        <span>Lihat Compliance</span>
                    </Link>
                </div>
            </div>

            {/* Key Metrics Cards */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {/* Metric 1 */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">Tingkat Kepatuhan</span>
                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                            <TrendingUp className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-3xl font-bold text-slate-900 dark:text-white">84%</span>
                        <span className="flex items-center text-xs font-medium text-emerald-600 dark:text-emerald-400">+2.4% bln ini</span>
                    </div>
                    <div className="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div className="h-full w-[84%] rounded-full bg-emerald-500" />
                    </div>
                </div>

                {/* Metric 2 */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                            Total Kontrol Active
                        </span>
                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                            <Layers className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-3xl font-bold text-slate-900 dark:text-white">119</span>
                        <span className="text-xs text-slate-500 dark:text-slate-400">2 Frameworks</span>
                    </div>
                    <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">93 ISO 27001 · 26 ISO 27701</p>
                </div>

                {/* Metric 3 */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">Temuan / Findings</span>
                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-400">
                            <AlertCircle className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-3xl font-bold text-slate-900 dark:text-white">7</span>
                        <span className="text-xs font-medium text-red-600 dark:text-red-400">Perlu Tindakan</span>
                    </div>
                    <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">2 Mayor · 5 Minor</p>
                </div>

                {/* Metric 4 */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">Status Risiko</span>
                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                            <ShieldAlert className="h-5 w-5" />
                        </div>
                    </div>
                    <div className="mt-3 flex items-baseline gap-2">
                        <span className="text-3xl font-bold text-slate-900 dark:text-white">3</span>
                        <span className="text-xs font-medium text-amber-600 dark:text-amber-400">Tinggi</span>
                    </div>
                    <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">Mitigasi dalam proses</p>
                </div>
            </div>

            {/* Quick Actions & Framework Overview */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Left col: Framework Summary */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-xs lg:col-span-2 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                        <div>
                            <h2 className="text-base font-bold text-slate-900 dark:text-white">Ringkasan Framework Utama</h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Progres implementasi kontrol kepatuhan standar ISO</p>
                        </div>
                        <Link
                            href="/admin/kepatuhan/compliance"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400"
                        >
                            <span>Buka Controls Management</span>
                            <ArrowUpRight className="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <div className="mt-5 space-y-4">
                        {/* ISO 27001 */}
                        <div className="rounded-lg border border-slate-100 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-800/40">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 text-white shadow-xs">
                                        <ShieldCheck className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 className="text-sm font-bold text-slate-900 dark:text-white">ISO/IEC 27001:2022</h3>
                                        <p className="text-xs text-slate-500 dark:text-slate-400">Manajemen Keamanan Informasi (ISMS)</p>
                                    </div>
                                </div>
                                <span className="text-sm font-bold text-emerald-600 dark:text-emerald-400">84% Compliant</span>
                            </div>

                            <div className="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                                <div className="rounded border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
                                    <span className="block text-[10px] text-slate-400">Compliant</span>
                                    <span className="font-bold text-emerald-600">78</span>
                                </div>
                                <div className="rounded border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
                                    <span className="block text-[10px] text-slate-400">Partial</span>
                                    <span className="font-bold text-amber-600">10</span>
                                </div>
                                <div className="rounded border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
                                    <span className="block text-[10px] text-slate-400">Non-Compliant</span>
                                    <span className="font-bold text-red-600">5</span>
                                </div>
                            </div>
                        </div>

                        {/* ISO 27701 */}
                        <div className="rounded-lg border border-slate-100 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-800/40">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-purple-600 text-white shadow-xs">
                                        <FileCheck className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 className="text-sm font-bold text-slate-900 dark:text-white">ISO/IEC 27701:2019</h3>
                                        <p className="text-xs text-slate-500 dark:text-slate-400">Manajemen Informasi Privasi (PIMS)</p>
                                    </div>
                                </div>
                                <span className="text-sm font-bold text-purple-600 dark:text-purple-400">79% Compliant</span>
                            </div>

                            <div className="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                                <div className="rounded border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
                                    <span className="block text-[10px] text-slate-400">Compliant</span>
                                    <span className="font-bold text-emerald-600">20</span>
                                </div>
                                <div className="rounded border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
                                    <span className="block text-[10px] text-slate-400">Partial</span>
                                    <span className="font-bold text-amber-600">4</span>
                                </div>
                                <div className="rounded border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
                                    <span className="block text-[10px] text-slate-400">Non-Compliant</span>
                                    <span className="font-bold text-red-600">2</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Right col: Recent Activity / Tasks */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                    <h2 className="border-b border-slate-100 pb-4 text-base font-bold text-slate-900 dark:border-slate-800 dark:text-white">
                        Aktivitas & Catatan Terbaru
                    </h2>

                    <div className="mt-4 space-y-4">
                        <div className="flex items-start gap-3">
                            <div className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400">
                                <CheckCircle2 className="h-4 w-4" />
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-slate-800 dark:text-slate-200">Bukti Kepatuhan A.8.1.1 Diperbarui</p>
                                <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                    Budi Santoso memperbarui bukti inventarisasi aset IT.
                                </p>
                                <span className="mt-1 flex items-center gap-1 text-[10px] text-slate-400">
                                    <Clock className="h-3 w-3" /> 2 jam yang lalu
                                </span>
                            </div>
                        </div>

                        <div className="flex items-start gap-3">
                            <div className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-950 dark:text-red-400">
                                <AlertCircle className="h-4 w-4" />
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-slate-800 dark:text-slate-200">Finding Baru pada Kontrol A.5.1</p>
                                <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                    Dokumen kebijakan keamanan informasi belum di-review tahunan.
                                </p>
                                <span className="mt-1 flex items-center gap-1 text-[10px] text-slate-400">
                                    <Clock className="h-3 w-3" /> 5 jam yang lalu
                                </span>
                            </div>
                        </div>

                        <div className="flex items-start gap-3">
                            <div className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                                <ShieldCheck className="h-4 w-4" />
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-slate-800 dark:text-slate-200">Audit Trail Diperbarui</p>
                                <p className="text-[11px] text-slate-500 dark:text-slate-400">Siti Aisyah menambahkan klausul Annex A baru.</p>
                                <span className="mt-1 flex items-center gap-1 text-[10px] text-slate-400">
                                    <Clock className="h-3 w-3" /> Kemarin
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminKepatuhanLayout>
    );
}
