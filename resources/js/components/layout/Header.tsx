import { t } from '@/lib/i18n';
import { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bell, ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { ThemeToggle } from '@/components/layout/ThemeToggle';

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

interface HeaderProps {
    breadcrumbs?: BreadcrumbItem[];
}

export function Header({ breadcrumbs = [] }: HeaderProps) {
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

    const defaultHref =
        userRole === 'superadmin' ? '/admin/superadmin/dashboard' : userRole === 'pic' ? '/admin/pic/assessments' : '/admin/kepatuhan/dashboard';
    const activeBreadcrumbs = breadcrumbs.length > 0 ? breadcrumbs : [{ label: t('common.dashboard'), href: defaultHref }];
    const roleLabel = t(`role.${userRole}` as never);

    return (
        <header className="border-border dark:border-white/10 sticky top-0 z-30 flex h-[68px] items-center justify-between border-b bg-white/90 px-4 backdrop-blur-md transition-colors sm:px-6 dark:bg-[#001a30]/90">
            <div className="flex items-center gap-3">
                <nav className="flex items-center gap-1.5 text-xs font-medium sm:text-sm">
                    {activeBreadcrumbs.map((item, index) => {
                        const isLast = index === activeBreadcrumbs.length - 1;

                        return (
                            <div key={index} className="flex items-center gap-1.5">
                                {index > 0 && <ChevronRight className="text-faint h-3.5 w-3.5 shrink-0" />}
                                {isLast ? (
                                    <span className="text-navy dark:text-primary-200 font-semibold">{item.label}</span>
                                ) : (
                                    <Link href={item.href || '#'} className="text-muted hover:text-navy dark:text-slate-400 dark:hover:text-primary-200 transition-colors">
                                        {item.label}
                                    </Link>
                                )}
                            </div>
                        );
                    })}
                </nav>
            </div>

            <div className="flex items-center gap-3">
                <ThemeToggle />

                <button
                    type="button"
                    className="border-border text-body hover:bg-surface hover:text-navy dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white relative flex h-9 w-9 items-center justify-center rounded-[10px] border bg-white shadow-sm transition-colors dark:bg-white/5"
                    aria-label={t('layout.notifications')}
                >
                    <Bell className="h-4.5 w-4.5" />
                    <span className="bg-danger absolute -top-1 -right-1 flex h-4.5 w-4.5 items-center justify-center rounded-full text-[10px] font-bold text-white shadow-sm ring-2 ring-white dark:ring-[#001a30]">
                        5
                    </span>
                </button>

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
                            <span className="text-navy dark:text-white text-xs leading-tight font-semibold">{userName}</span>
                            <span className="text-muted dark:text-slate-400 mt-0.5 text-[11px] leading-none">{roleLabel}</span>
                        </div>
                        <ChevronDown className="text-muted h-4 w-4" />
                    </button>

                    {isUserMenuOpen && (
                        <div className="animate-in fade-in slide-in-from-top-2 border-border dark:border-white/10 absolute right-0 z-[1000] mt-2 w-48 rounded-[14px] border bg-white p-1.5 shadow-lg duration-150 dark:bg-[#002745]">
                            <div className="border-border dark:border-white/10 border-b px-3 py-2 md:hidden">
                                <p className="text-navy dark:text-white text-xs font-semibold">{userName}</p>
                                <p className="text-muted dark:text-slate-400 text-[11px]">{roleLabel}</p>
                            </div>
                            <button
                                type="button"
                                className="text-body hover:bg-surface dark:text-slate-300 dark:hover:bg-white/10 w-full rounded-[10px] px-3 py-2 text-left text-xs font-medium transition-colors"
                            >
                                {t('layout.profile')}
                            </button>
                            <button
                                type="button"
                                className="text-body hover:bg-surface dark:text-slate-300 dark:hover:bg-white/10 w-full rounded-[10px] px-3 py-2 text-left text-xs font-medium transition-colors"
                            >
                                {t('layout.changePassword')}
                            </button>
                            <div className="border-border dark:border-white/10 my-1 border-t" />
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="text-danger hover:bg-danger-bg dark:hover:bg-red-950/40 block w-full rounded-[10px] px-3 py-2 text-left text-xs font-medium transition-colors"
                            >
                                {t('layout.logout')}
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
}
