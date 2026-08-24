import { router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckSquare,
    ChevronRight,
    FileCheck2,
    LayoutDashboard,
    Search,
    Shield,
    ShieldAlert,
    UserCheck,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

interface CommandItem {
    id: string;
    title: string;
    description: string;
    category: string;
    href: string;
    icon: typeof LayoutDashboard;
    keywords: string[];
}

const COMMANDS: CommandItem[] = [
    {
        id: 'dashboard',
        title: 'Dashboard Utama',
        description: 'Ringkasan tingkat kepatuhan SMKI, progres sesi, dan metrik risiko',
        category: 'Navigasi Utama',
        href: '/dashboard',
        icon: LayoutDashboard,
        keywords: ['dashboard', 'beranda', 'home', 'ringkasan', 'overview'],
    },
    {
        id: 'compliance',
        title: 'Pustaka Kontrol & Framework ISO 27001',
        description: 'Daftar klausul, kontrol Annex A, dan parameter kepatuhan',
        category: 'Manajemen Kontrol',
        href: '/compliance',
        icon: Shield,
        keywords: ['kontrol', 'compliance', 'iso 27001', 'annex a', 'klausul', 'framework'],
    },
    {
        id: 'findings',
        title: 'Temuan Audit & Tindak Lanjut (CAPA)',
        description: 'Status penanganan temuan audit, target SLA, dan status perbaikan',
        category: 'Audit & Kepatuhan',
        href: '/findings',
        icon: AlertTriangle,
        keywords: ['temuan', 'findings', 'audit', 'capa', 'sla', 'ketidaksesuaian'],
    },
    {
        id: 'risks',
        title: 'Register & Matriks Risiko',
        description: 'Analisis tingkat keparahan risiko dan rencana mitigasi per unit kerja',
        category: 'Audit & Kepatuhan',
        href: '/risks',
        icon: ShieldAlert,
        keywords: ['risiko', 'risk', 'register', 'matriks', 'mitigasi', 'critical', 'high'],
    },
    {
        id: 'bulk-verify',
        title: 'Verifikasi Massal (Review Queue)',
        description: 'Antrean verifikasi checklist bukti kepatuhan dari seluruh unit kerja',
        category: 'Verifikasi & Checklist',
        href: '/admin/kepatuhan/checklist/bulk-verify',
        icon: CheckSquare,
        keywords: ['verifikasi', 'bulk', 'review', 'queue', 'pic', 'bukti'],
    },
    {
        id: 'sessions',
        title: 'Sesi Asesmen & Pengecekan',
        description: 'Daftar sesi asesmen mandiri aktif per periode',
        category: 'Verifikasi & Checklist',
        href: '/admin/pic/assessments',
        icon: FileCheck2,
        keywords: ['sesi', 'asesmen', 'pengecekan', 'checklist', 'periode'],
    },
    {
        id: 'audit-logs',
        title: 'Audit Trail / Riwayat Aktivitas',
        description: 'Log rekam jejak aktivitas pengguna yang aman dan anti-manipulasi',
        category: 'Keamanan & Sistem',
        href: '/audit-logs',
        icon: Activity,
        keywords: ['audit log', 'trail', 'riwayat', 'log', 'aktivitas', 'history'],
    },
    {
        id: 'users',
        title: 'Manajemen Pengguna',
        description: 'Kelola akun pengguna, unit kerja, dan penetapan peran',
        category: 'Keamanan & Sistem',
        href: '/admin/superadmin/users',
        icon: Users,
        keywords: ['pengguna', 'users', 'user', 'akun', 'pic', 'admin'],
    },
    {
        id: 'roles',
        title: 'Manajemen Peran & Hak Akses',
        description: 'Konfigurasi permission matriks dan hierarki peran sistem',
        category: 'Keamanan & Sistem',
        href: '/admin/superadmin/roles',
        icon: UserCheck,
        keywords: ['peran', 'roles', 'role', 'hak akses', 'permission', 'superadmin'],
    },
];

interface CommandPaletteProps {
    open: boolean;
    onClose: () => void;
}

export function CommandPalette({ open, onClose }: CommandPaletteProps) {
    const [search, setSearch] = useState('');
    const [selectedIndex, setSelectedIndex] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (open) {
            setSearch('');
            setSelectedIndex(0);
            setTimeout(() => inputRef.current?.focus(), 50);
        }
    }, [open]);

    const filteredCommands = useMemo(() => {
        if (!search.trim()) return COMMANDS;
        const q = search.toLowerCase();
        return COMMANDS.filter((cmd) => {
            return (
                cmd.title.toLowerCase().includes(q) ||
                cmd.description.toLowerCase().includes(q) ||
                cmd.category.toLowerCase().includes(q) ||
                cmd.keywords.some((k) => k.includes(q))
            );
        });
    }, [search]);

    const executeCommand = (cmd: CommandItem) => {
        onClose();
        router.visit(cmd.href);
    };

    useEffect(() => {
        if (!open) return;

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                onClose();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                setSelectedIndex((prev) => (prev < filteredCommands.length - 1 ? prev + 1 : 0));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setSelectedIndex((prev) => (prev > 0 ? prev - 1 : filteredCommands.length - 1));
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (filteredCommands[selectedIndex]) {
                    executeCommand(filteredCommands[selectedIndex]);
                }
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [open, selectedIndex, filteredCommands]); // eslint-disable-line react-hooks/exhaustive-deps

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center pt-20 p-4 sm:p-6 bg-slate-900/50 backdrop-blur-sm transition-all animate-in fade-in duration-150">
            <div
                className="w-full max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl transition-all dark:border-slate-800 dark:bg-slate-900 animate-in zoom-in-95 duration-150"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Search Header */}
                <div className="relative flex items-center border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <Search className="pointer-events-none h-4 w-4 text-slate-400 mr-3" />
                    <input
                        ref={inputRef}
                        type="text"
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value);
                            setSelectedIndex(0);
                        }}
                        placeholder="Ketik tujuan navigasi, kontrol, atau tindakan..."
                        className="w-full bg-transparent text-sm text-slate-900 placeholder-slate-400 focus:outline-none dark:text-white"
                    />
                    {search ? (
                        <button type="button" onClick={() => setSearch('')} className="text-slate-400 hover:text-slate-600">
                            <X className="h-4 w-4" />
                        </button>
                    ) : (
                        <kbd className="hidden sm:inline-flex items-center gap-0.5 rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                            ESC
                        </kbd>
                    )}
                </div>

                {/* Results List */}
                <div className="max-h-[360px] overflow-y-auto p-2 space-y-1">
                    {filteredCommands.length > 0 ? (
                        filteredCommands.map((cmd, index) => {
                            const Icon = cmd.icon;
                            const isSelected = index === selectedIndex;
                            return (
                                <button
                                    key={cmd.id}
                                    type="button"
                                    onClick={() => executeCommand(cmd)}
                                    onMouseEnter={() => setSelectedIndex(index)}
                                    className={`flex w-full items-center justify-between rounded-xl px-3.5 py-3 text-left text-xs sm:text-sm transition-colors ${
                                        isSelected
                                            ? 'bg-blue-50 text-blue-900 dark:bg-blue-950/50 dark:text-blue-200'
                                            : 'text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/50'
                                    }`}
                                >
                                    <div className="flex items-center gap-3 min-w-0">
                                        <div
                                            className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${
                                                isSelected
                                                    ? 'bg-blue-600 text-white'
                                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                                            }`}
                                        >
                                            <Icon className="h-4 w-4" />
                                        </div>
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold truncate text-slate-900 dark:text-white">
                                                    {cmd.title}
                                                </span>
                                                <span className="text-[10px] font-medium text-slate-400 rounded bg-slate-100 px-1.5 py-0.5 dark:bg-slate-800 dark:text-slate-400">
                                                    {cmd.category}
                                                </span>
                                            </div>
                                            <div className="text-[11px] text-slate-500 truncate dark:text-slate-400">
                                                {cmd.description}
                                            </div>
                                        </div>
                                    </div>
                                    <ChevronRight className={`h-4 w-4 shrink-0 ${isSelected ? 'text-blue-600' : 'text-slate-300'}`} />
                                </button>
                            );
                        })
                    ) : (
                        <div className="p-8 text-center text-xs text-slate-400 dark:text-slate-500">
                            Tidak ada perintah atau menu yang cocok dengan pencarian &quot;{search}&quot;.
                        </div>
                    )}
                </div>

                {/* Footer Tips */}
                <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-4 py-2.5 text-[11px] text-slate-500 dark:border-slate-800 dark:bg-slate-900/80">
                    <div className="flex items-center gap-3">
                        <span className="inline-flex items-center gap-1">
                            <kbd className="rounded border bg-white px-1 py-0.5 font-sans font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                ↑
                            </kbd>
                            <kbd className="rounded border bg-white px-1 py-0.5 font-sans font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                ↓
                            </kbd>{' '}
                            Navigasi
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <kbd className="rounded border bg-white px-1.5 py-0.5 font-sans font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                ↵
                            </kbd>{' '}
                            Buka Menu
                        </span>
                    </div>
                    <span className="font-semibold text-blue-600 dark:text-blue-400">SMKI Command Palette</span>
                </div>
            </div>
        </div>
    );
}
