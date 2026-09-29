import { NavEntry, SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    CheckSquare,
    ChevronRight,
    ClipboardCheck,
    Database,
    FileCheck2,
    History,
    LayoutDashboard,
    LayoutGrid,
    Search,
    Shield,
    ShieldAlert,
    ShieldCheck,
    UserCheck,
    Users,
    X,
} from 'lucide-react';
import { type ComponentType, useEffect, useMemo, useRef, useState } from 'react';

// Map icon names (string) ke komponen Lucide — harus konsisten dengan Sidebar
const ICON_MAP: Record<string, ComponentType<{ className?: string }>> = {
    LayoutGrid,
    LayoutDashboard,
    Database,
    ShieldCheck,
    ClipboardCheck,
    CheckSquare,
    History,
    AlertCircle,
    AlertTriangle,
    Users,
    Shield,
    ShieldAlert,
    FileCheck2,
    Activity,
    UserCheck,
};

interface CommandItem {
    id: string;
    title: string;
    description: string;
    category: string;
    href: string;
    icon: ComponentType<{ className?: string }>;
    keywords: string[];
}

// Keyword hints tambahan per URL pattern
const URL_KEYWORDS: Record<string, string[]> = {
    '/dashboard': ['dashboard', 'beranda', 'home', 'ringkasan', 'overview'],
    '/findings': ['temuan', 'findings', 'audit', 'capa', 'sla', 'ketidaksesuaian'],
    '/risks': ['risiko', 'risk', 'register', 'matriks', 'mitigasi', 'critical', 'high'],
    '/bulk-verify': ['verifikasi', 'bulk', 'review', 'queue', 'pic', 'bukti'],
    '/assessments': ['sesi', 'asesmen', 'pengecekan', 'checklist', 'periode'],
    '/audit-logs': ['audit log', 'trail', 'riwayat', 'log', 'aktivitas', 'history'],
    '/users': ['pengguna', 'users', 'user', 'akun', 'pic', 'admin'],
    '/roles': ['peran', 'roles', 'role', 'hak akses', 'permission', 'superadmin'],
    '/compliance': ['kontrol', 'compliance', 'iso 27001', 'annex a', 'klausul', 'framework'],
    '/controls': ['kontrol', 'compliance', 'iso', 'annex', 'klausul', 'framework'],
    '/master-data': ['master', 'data', 'referensi'],
    '/checklist': ['checklist', 'bukti', 'verifikasi', 'kepatuhan'],
};

function getKeywordsForUrl(url: string): string[] {
    for (const [pattern, kw] of Object.entries(URL_KEYWORDS)) {
        if (url.includes(pattern)) return kw;
    }
    return [];
}

function navEntriesToCommands(entries: NavEntry[]): CommandItem[] {
    const commands: CommandItem[] = [];

    for (const entry of entries) {
        if (entry.children && entry.children.length > 0) {
            // Grup navigasi: tambahkan setiap child sebagai command
            for (const child of entry.children) {
                if (!child.url) continue;
                const icon = ICON_MAP[entry.icon ?? ''] ?? LayoutGrid;
                commands.push({
                    id: child.url,
                    title: child.label,
                    description: `${entry.label} — ${child.label}`,
                    category: entry.label,
                    href: child.url,
                    icon,
                    keywords: getKeywordsForUrl(child.url),
                });
            }
        } else if (entry.url) {
            const icon = ICON_MAP[entry.icon ?? ''] ?? LayoutGrid;
            commands.push({
                id: entry.url,
                title: entry.label,
                description: entry.label,
                category: 'Navigasi',
                href: entry.url,
                icon,
                keywords: getKeywordsForUrl(entry.url),
            });
        }
    }

    return commands;
}

interface CommandPaletteProps {
    open: boolean;
    onClose: () => void;
}

export function CommandPalette({ open, onClose }: CommandPaletteProps) {
    const page = usePage<SharedData>();

    const [search, setSearch] = useState('');
    const [selectedIndex, setSelectedIndex] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);

    // Build command list dari navigasi RBAC yang sudah di-filter server
    const allCommands = useMemo(() => navEntriesToCommands(page.props.navigation || []), [page.props.navigation]);

    useEffect(() => {
        if (open) {
            setSearch('');
            setSelectedIndex(0);
            setTimeout(() => inputRef.current?.focus(), 50);
        }
    }, [open]);

    const filteredCommands = useMemo(() => {
        if (!search.trim()) return allCommands;
        const q = search.toLowerCase();
        return allCommands.filter((cmd) => {
            return (
                cmd.title.toLowerCase().includes(q) ||
                cmd.description.toLowerCase().includes(q) ||
                cmd.category.toLowerCase().includes(q) ||
                cmd.keywords.some((k) => k.includes(q))
            );
        });
    }, [search, allCommands]);

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
                className="w-full max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl transition-all dark:border-white/10 dark:bg-[#002745] animate-in zoom-in-95 duration-150"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Search Header */}
                <div className="relative flex items-center border-b border-slate-200 px-4 py-3 dark:border-white/10">
                    <Search className="pointer-events-none h-4 w-4 text-slate-400 mr-3 dark:text-primary-200" />
                    <input
                        ref={inputRef}
                        type="text"
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value);
                            setSelectedIndex(0);
                        }}
                        placeholder="Ketik tujuan navigasi, kontrol, atau tindakan..."
                        className="w-full bg-transparent text-sm text-slate-900 placeholder-slate-400 focus:outline-none dark:text-white dark:placeholder-primary-300"
                    />
                    {search ? (
                        <button type="button" onClick={() => setSearch('')} className="text-slate-400 hover:text-slate-600 dark:text-primary-300 dark:hover:text-white">
                            <X className="h-4 w-4" />
                        </button>
                    ) : (
                        <kbd className="hidden sm:inline-flex items-center gap-0.5 rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500 dark:border-white/10 dark:bg-white/10 dark:text-primary-200">
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
                                            ? 'bg-primary-50 text-navy dark:bg-primary/20 dark:text-white'
                                            : 'text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/8'
                                    }`}
                                >
                                    <div className="flex items-center gap-3 min-w-0">
                                        <div
                                            className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${
                                                isSelected
                                                    ? 'bg-primary text-white'
                                                    : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-primary-200'
                                            }`}
                                        >
                                            <Icon className="h-4 w-4" />
                                        </div>
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold truncate text-slate-900 dark:text-white">
                                                    {cmd.title}
                                                </span>
                                                <span className="text-[10px] font-medium text-slate-400 rounded bg-slate-100 px-1.5 py-0.5 dark:bg-white/10 dark:text-primary-200">
                                                    {cmd.category}
                                                </span>
                                            </div>
                                            <div className="text-[11px] text-slate-500 truncate dark:text-primary-300">
                                                {cmd.description}
                                            </div>
                                        </div>
                                    </div>
                                    <ChevronRight className={`h-4 w-4 shrink-0 ${isSelected ? 'text-primary' : 'text-slate-300 dark:text-slate-600'}`} />
                                </button>
                            );
                        })
                    ) : (
                        <div className="p-8 text-center text-xs text-slate-400 dark:text-primary-300">
                            Tidak ada menu yang cocok dengan pencarian &quot;{search}&quot;.
                        </div>
                    )}
                </div>

                {/* Footer Tips */}
                <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-4 py-2.5 text-[11px] text-slate-500 dark:border-white/10 dark:bg-[#001a30]/80">
                    <div className="flex items-center gap-3">
                        <span className="inline-flex items-center gap-1">
                            <kbd className="rounded border bg-white px-1 py-0.5 font-sans font-semibold text-slate-600 dark:border-white/10 dark:bg-white/10 dark:text-primary-200">
                                ↑
                            </kbd>
                            <kbd className="rounded border bg-white px-1 py-0.5 font-sans font-semibold text-slate-600 dark:border-white/10 dark:bg-white/10 dark:text-primary-200">
                                ↓
                            </kbd>{' '}
                            Navigasi
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <kbd className="rounded border bg-white px-1.5 py-0.5 font-sans font-semibold text-slate-600 dark:border-white/10 dark:bg-white/10 dark:text-primary-200">
                                ↵
                            </kbd>{' '}
                            Buka Menu
                        </span>
                    </div>
                    <span className="font-semibold text-primary dark:text-primary-200">SMKI Command Palette</span>
                </div>
            </div>
        </div>
    );
}
