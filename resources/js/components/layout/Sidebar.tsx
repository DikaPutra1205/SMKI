import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { NavEntry, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { AlertCircle, AlertTriangle, CheckSquare, ChevronDown, ChevronRight, ClipboardCheck, Database, History, LayoutGrid, LogOut, Shield, ShieldCheck, Users, X } from 'lucide-react';
import { type ComponentType, useState } from 'react';

interface SidebarProps {
    isOpen: boolean;
    onClose?: () => void;
    currentPath?: string;
}

const ICON_MAP: Record<string, ComponentType<{ className?: string }>> = {
    LayoutGrid,
    Database,
    ShieldCheck,
    ClipboardCheck,
    CheckSquare,
    History,
    AlertCircle,
    AlertTriangle,
    Users,
    Shield,
};

export function Sidebar({ isOpen, onClose, currentPath }: SidebarProps) {
    const page = usePage<SharedData>();
    const authUser = page.props.auth?.user;
    const navigation: NavEntry[] = page.props.navigation || [];

    const pathname = currentPath || page.url;
    const userRole = (authUser as { role?: string })?.role || 'admin_kepatuhan';

    const userName = authUser?.name || '';

    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({});

    const initials = userName
        .split(' ')
        .map((n) => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();

    const toggleGroup = (label: string) => {
        setOpenGroups((prev) => ({ ...prev, [label]: !prev[label] }));
    };

    const isUrlActive = (url: string) => pathname === url || pathname.startsWith(url + '/');

    const resolveIcon = (iconName?: string) => (iconName ? ICON_MAP[iconName] || LayoutGrid : LayoutGrid);

    const roleLabel = t(`role.${userRole}` as never);

    return (
        <>
            {isOpen && <div className="bg-navy-900/60 fixed inset-0 z-40 backdrop-blur-sm transition-opacity lg:hidden" onClick={onClose} />}

            <aside
                className={cn(
                    'from-navy fixed top-0 bottom-0 left-0 z-50 flex w-64 flex-col bg-gradient-to-b to-[#001A30] text-[#A9C3DB] shadow-xl transition-transform duration-300 ease-in-out lg:translate-x-0',
                    isOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <div className="flex h-[68px] items-center justify-between border-b border-white/10 px-4 py-3">
                    <div className="flex items-center gap-3">
                        <div className="bg-primary shadow-blue flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white">
                            <Shield className="h-5 w-5 fill-white/20" />
                        </div>
                        <div className="flex flex-col">
                            <span className="text-sm leading-tight font-bold tracking-tight text-white">{t('layout.brand')}</span>
                        </div>
                    </div>
                    <button
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-[#A9C3DB] transition-colors hover:bg-white/10 hover:text-white lg:hidden"
                        aria-label={t('layout.closeSidebar')}
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <div className="flex-1 scrollbar-thin scrollbar-thumb-slate-800 overflow-y-auto px-3 py-4">
                    <div>
                        <div className="mb-2 px-3 text-[11px] font-semibold tracking-wider text-[#7D9BB5] uppercase">{t('layout.mainMenu')}</div>

                        <nav className="space-y-1">
                            {navigation.map((entry) => {
                                if (entry.children && entry.children.length > 0) {
                                    const groupActive = entry.children.some((child) => isUrlActive(child.url));
                                    const isGroupOpen = openGroups[entry.label] ?? groupActive;
                                    const Icon = resolveIcon(entry.icon);

                                    return (
                                        <div key={entry.label}>
                                            <button
                                                type="button"
                                                onClick={() => toggleGroup(entry.label)}
                                                className={cn(
                                                    'flex w-full items-center justify-between rounded-[10px] px-3 py-2.5 text-sm font-medium transition-all duration-150',
                                                    groupActive
                                                        ? 'bg-primary/15 text-primary-200 font-semibold'
                                                        : 'text-[#A9C3DB] hover:bg-white/8 hover:text-white',
                                                )}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <Icon className={cn('h-4 w-4', groupActive ? 'text-primary-200' : 'text-[#7D9BB5]')} />
                                                    <span>{entry.label}</span>
                                                </div>
                                                {isGroupOpen ? (
                                                    <ChevronDown className="h-4 w-4 text-[#7D9BB5]" />
                                                ) : (
                                                    <ChevronRight className="h-4 w-4 text-[#7D9BB5]" />
                                                )}
                                            </button>

                                            {isGroupOpen && (
                                                <div className="mt-1 ml-4 space-y-1 border-l border-white/10 pl-3">
                                                    {entry.children.map((child) => {
                                                        const childActive = isUrlActive(child.url);

                                                        return (
                                                            <Link
                                                                key={child.url}
                                                                href={child.url}
                                                                className={cn(
                                                                    'block rounded-[8px] px-3 py-2 text-xs font-medium transition-colors',
                                                                    childActive
                                                                        ? 'bg-primary font-semibold text-white shadow-sm'
                                                                        : 'text-[#A9C3DB] hover:bg-white/8 hover:text-white',
                                                                )}
                                                            >
                                                                {child.label}
                                                            </Link>
                                                        );
                                                    })}
                                                </div>
                                            )}
                                        </div>
                                    );
                                }

                                if (entry.url) {
                                    const active = isUrlActive(entry.url);
                                    const Icon = resolveIcon(entry.icon);

                                    return (
                                        <Link
                                            key={entry.url}
                                            href={entry.url}
                                            className={cn(
                                                'flex items-center gap-3 rounded-[10px] px-3 py-2.5 text-sm font-medium transition-all duration-150',
                                                active
                                                    ? 'bg-primary shadow-primary/25 font-semibold text-white shadow-md'
                                                    : 'text-[#A9C3DB] hover:bg-white/8 hover:text-white',
                                            )}
                                        >
                                            <Icon className={cn('h-4 w-4', active ? 'text-white' : 'text-[#7D9BB5]')} />
                                            <span>{entry.label}</span>
                                        </Link>
                                    );
                                }

                                return null;
                            })}
                        </nav>
                    </div>
                </div>

                <div className="border-t border-white/10 bg-[#001A30]/60 p-3">
                    <div className="flex items-center justify-between rounded-[10px] border border-white/10 bg-white/5 p-2.5">
                        <div className="flex items-center gap-2.5 overflow-hidden">
                            <div className="bg-primary flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white">
                                {initials}
                            </div>
                            <div className="flex flex-col truncate">
                                <span className="truncate text-xs leading-tight font-semibold text-white">{userName}</span>
                                <span className="mt-0.5 truncate text-[11px] text-[#7D9BB5]">{roleLabel}</span>
                            </div>
                        </div>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="rounded-lg p-1.5 text-[#7D9BB5] transition-colors hover:bg-white/10 hover:text-white"
                            title={t('layout.logout')}
                        >
                            <LogOut className="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </aside>
        </>
    );
}
