import { NotificationDropdown } from '@/components/layout/NotificationDropdown';
import { ThemeToggle } from '@/components/layout/ThemeToggle';
import { CommandPalette } from '@/components/ui/CommandPalette';
import { t } from '@/lib/i18n';
import { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Menu, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

interface HeaderProps {
    breadcrumbs?: BreadcrumbItem[];
    onToggleSidebar?: () => void;
    isSidebarCollapsed?: boolean;
}

export function Header({ breadcrumbs = [], onToggleSidebar }: HeaderProps) {
    const page = usePage<SharedData>();
    const authUser = page.props.auth?.user;

    const userName = authUser?.name || '';
    const userRole = (authUser as { role?: string })?.role || 'admin_kepatuhan';

    const initials = userName
        .split(' ')
        .map((n) => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();

    const [isUserMenuOpen, setIsUserMenuOpen] = useState(false);
    const [isPaletteOpen, setIsPaletteOpen] = useState(false);

    // Global Ctrl+K / Cmd+K listener
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                setIsPaletteOpen((prev) => !prev);
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    const defaultHref =
        userRole === 'superadmin' ? '/admin/superadmin/dashboard' : userRole === 'pic' ? '/admin/pic/checklist' : '/admin/kepatuhan/dashboard';
    const activeBreadcrumbs = breadcrumbs.length > 0 ? breadcrumbs : [{ label: t('common.dashboard'), href: defaultHref }];
    const roleLabel = t(`role.${userRole}` as never);

    return (
        <>
            <header className="border-border sticky top-0 z-30 flex h-[68px] items-center justify-between border-b bg-white/90 px-4 backdrop-blur-md transition-colors sm:px-6 dark:border-white/10 dark:bg-[#001a30]/90">
                <div className="flex items-center gap-3">
                    {onToggleSidebar && (
                        <button
                            type="button"
                            onClick={onToggleSidebar}
                            className="border-border text-body hover:bg-surface hover:text-navy flex h-9 w-9 items-center justify-center rounded-[10px] border bg-white shadow-sm transition-colors dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                            title={t('layout.toggleSidebar')}
                            aria-label={t('layout.toggleSidebar')}
                        >
                            <Menu className="h-4.5 w-4.5" />
                        </button>
                    )}

                    <nav className="hidden items-center gap-1.5 text-xs font-medium sm:flex sm:text-sm">
                        {activeBreadcrumbs.map((item, index) => {
                            const isLast = index === activeBreadcrumbs.length - 1;

                            return (
                                <div key={index} className="flex items-center gap-1.5">
                                    {index > 0 && <ChevronRight className="text-faint h-3.5 w-3.5 shrink-0" />}
                                    {isLast ? (
                                        <span className="text-navy dark:text-primary-200 font-semibold">{item.label}</span>
                                    ) : (
                                        <Link
                                            href={item.href || '#'}
                                            className="text-muted hover:text-navy dark:hover:text-primary-200 transition-colors dark:text-slate-400"
                                        >
                                            {item.label}
                                        </Link>
                                    )}
                                </div>
                            );
                        })}
                    </nav>
                </div>

                <div className="flex items-center gap-3">
                    {/* Global Command Palette Trigger Button */}
                    <button
                        type="button"
                        onClick={() => setIsPaletteOpen(true)}
                        className="hidden items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-1.5 text-xs text-slate-500 shadow-xs transition-colors hover:border-slate-300 hover:bg-slate-100 sm:flex dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400 dark:hover:border-slate-700"
                    >
                        <Search className="h-3.5 w-3.5 text-slate-400" />
                        <span>Pencarian Cepat...</span>
                        <kbd className="inline-flex items-center gap-0.5 rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            Ctrl K
                        </kbd>
                    </button>

                    <ThemeToggle />

                    <NotificationDropdown userId={authUser?.id} />

                    <div className="relative">
                        <button
                            type="button"
                            onClick={() => setIsUserMenuOpen(!isUserMenuOpen)}
                            className="hover:bg-surface flex items-center gap-2.5 rounded-[10px] p-1.5 text-left transition-colors"
                        >
                            <div className="bg-primary flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white shadow-sm">
                                {initials}
                            </div>
                            <div className="hidden flex-col md:flex">
                                <span className="text-navy text-xs leading-tight font-semibold dark:text-white">{userName}</span>
                                <span className="text-muted mt-0.5 text-[11px] leading-none dark:text-slate-400">{roleLabel}</span>
                            </div>
                            <ChevronDown className="text-muted h-4 w-4" />
                        </button>

                        {isUserMenuOpen && (
                            <div className="animate-in fade-in slide-in-from-top-2 border-border absolute right-0 z-[1000] mt-2 w-48 rounded-[14px] border bg-white p-1.5 shadow-lg duration-150 dark:border-white/10 dark:bg-[#002745]">
                                <div className="border-border border-b px-3 py-2 md:hidden dark:border-white/10">
                                    <p className="text-navy text-xs font-semibold dark:text-white">{userName}</p>
                                    <p className="text-muted text-[11px] dark:text-slate-400">{roleLabel}</p>
                                </div>
                                <button
                                    type="button"
                                    className="text-body hover:bg-surface w-full rounded-[10px] px-3 py-2 text-left text-xs font-medium transition-colors dark:text-slate-300 dark:hover:bg-white/10"
                                >
                                    {t('layout.profile')}
                                </button>
                                <button
                                    type="button"
                                    className="text-body hover:bg-surface w-full rounded-[10px] px-3 py-2 text-left text-xs font-medium transition-colors dark:text-slate-300 dark:hover:bg-white/10"
                                >
                                    {t('layout.changePassword')}
                                </button>
                                <div className="border-border my-1 border-t dark:border-white/10" />
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    className="text-danger hover:bg-danger-bg block w-full rounded-[10px] px-3 py-2 text-left text-xs font-medium transition-colors dark:hover:bg-red-950/40"
                                >
                                    {t('layout.logout')}
                                </Link>
                            </div>
                        )}
                    </div>
                </div>
            </header>

            {/* Global Command Palette */}
            <CommandPalette open={isPaletteOpen} onClose={() => setIsPaletteOpen(false)} />
        </>
    );
}
