import { cn } from '@/lib/utils';
import { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronRight, LayoutGrid, LogOut, Shield, ShieldCheck, X } from 'lucide-react';
import { useState } from 'react';

interface SidebarProps {
    isOpen: boolean;
    onClose?: () => void;
    currentPath?: string;
}

export function Sidebar({ isOpen, onClose, currentPath }: SidebarProps) {
    const page = usePage<SharedData>();
    const authUser = page.props.auth?.user;

    const pathname = currentPath || page.url;

    const isDashboardActive = pathname.includes('/admin/kepatuhan/dashboard') || pathname === '/admin/kepatuhan';
    const isComplianceActive = pathname.includes('/admin/kepatuhan/compliance') || pathname.includes('/admin/kepatuhan/controls');

    const [isComplianceOpen, setIsComplianceOpen] = useState(true);

    const userName = authUser?.name || 'Siti Aisyah';
    const userRole = (authUser as { role?: string })?.role || 'Compliance Admin';

    // Get initials (e.g. Siti Aisyah -> SA)
    const initials = userName
        .split(' ')
        .map((n) => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();

    return (
        <>
            {/* Mobile backdrop */}
            {isOpen && <div className="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm transition-opacity lg:hidden" onClick={onClose} />}

            <aside
                className={cn(
                    'fixed top-0 bottom-0 left-0 z-50 flex w-64 flex-col border-r border-slate-800/60 bg-[#0b192e] text-slate-300 shadow-xl transition-transform duration-300 ease-in-out lg:translate-x-0',
                    isOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                {/* Header Logo */}
                <div className="flex h-18 items-center justify-between border-b border-slate-800/80 px-4 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-lg ring-1 shadow-blue-600/30 ring-blue-400/40">
                            <Shield className="h-5 w-5 fill-white/20" />
                        </div>
                        <div className="flex flex-col">
                            <span className="text-sm leading-tight font-bold tracking-tight text-white">Sistem Kepatuhan Digital</span>
                            <span className="mt-0.5 text-[10px] font-semibold tracking-wider text-blue-400 uppercase">COMPLIANCE SUITE</span>
                        </div>
                    </div>
                    {/* Mobile close button */}
                    <button
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-800 hover:text-white lg:hidden"
                        aria-label="Close sidebar"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Navigation Links */}
                <div className="flex-1 scrollbar-thin scrollbar-thumb-slate-800 overflow-y-auto px-3 py-4">
                    {/* MENU UTAMA */}
                    <div>
                        <div className="mb-2 px-3 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">MENU UTAMA</div>

                        <nav className="space-y-1">
                            {/* Dashboard */}
                            <Link
                                href="/admin/kepatuhan/dashboard"
                                className={cn(
                                    'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150',
                                    isDashboardActive
                                        ? 'bg-blue-600 font-semibold text-white shadow-md shadow-blue-600/25'
                                        : 'text-slate-300 hover:bg-slate-800/60 hover:text-white',
                                )}
                            >
                                <LayoutGrid className={cn('h-4 w-4', isDashboardActive ? 'text-white' : 'text-slate-400')} />
                                <span>Dashboard</span>
                            </Link>

                            {/* Compliance Menu */}
                            <div>
                                <button
                                    type="button"
                                    onClick={() => setIsComplianceOpen(!isComplianceOpen)}
                                    className={cn(
                                        'flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150',
                                        isComplianceActive
                                            ? 'bg-blue-600/15 font-semibold text-blue-400'
                                            : 'text-slate-300 hover:bg-slate-800/60 hover:text-white',
                                    )}
                                >
                                    <div className="flex items-center gap-3">
                                        <ShieldCheck className={cn('h-4 w-4', isComplianceActive ? 'text-blue-400' : 'text-slate-400')} />
                                        <span>Compliance</span>
                                    </div>
                                    {isComplianceOpen ? (
                                        <ChevronDown className="h-4 w-4 text-slate-400" />
                                    ) : (
                                        <ChevronRight className="h-4 w-4 text-slate-400" />
                                    )}
                                </button>

                                {/* Frameworks & Controls Submenu */}
                                {isComplianceOpen && (
                                    <div className="mt-1 ml-4 space-y-1 border-l border-slate-700/60 pl-3">
                                        <Link
                                            href="/admin/kepatuhan/compliance"
                                            className={cn(
                                                'block rounded-md px-3 py-2 text-xs font-medium transition-colors',
                                                isComplianceActive
                                                    ? 'bg-blue-600 font-semibold text-white shadow-sm'
                                                    : 'text-slate-400 hover:bg-slate-800/50 hover:text-white',
                                            )}
                                        >
                                            Controls Management
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </nav>
                    </div>
                </div>

                {/* User Profile Footer */}
                <div className="border-t border-slate-800/80 bg-[#081220] p-3">
                    <div className="flex items-center justify-between rounded-xl border border-slate-800/60 bg-slate-900/60 p-2.5">
                        <div className="flex items-center gap-2.5 overflow-hidden">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white ring-2 ring-blue-500/30">
                                {initials}
                            </div>
                            <div className="flex flex-col truncate">
                                <span className="truncate text-xs leading-tight font-semibold text-white">{userName}</span>
                                <span className="mt-0.5 truncate text-[11px] text-slate-400">{userRole}</span>
                            </div>
                        </div>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-800 hover:text-white"
                            title="Keluar"
                        >
                            <LogOut className="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </aside>
        </>
    );
}
