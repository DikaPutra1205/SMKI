import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Bell, ChevronDown, ChevronRight, Menu } from 'lucide-react';
import { useState } from 'react';

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

interface HeaderProps {
    onToggleSidebar: () => void;
    breadcrumbs?: BreadcrumbItem[];
}

export function Header({ onToggleSidebar, breadcrumbs = [] }: HeaderProps) {
    const page = usePage<SharedData>();
    const authUser = page.props.auth?.user;

    const userName = authUser?.name || 'Siti Aisyah';
    const userRole = (authUser as { role?: string })?.role || 'Compliance Admin';

    const initials = userName
        .split(' ')
        .map((n) => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();

    const [isUserMenuOpen, setIsUserMenuOpen] = useState(false);

    const activeBreadcrumbs = breadcrumbs.length > 0 ? breadcrumbs : [{ label: 'Dashboard', href: '/admin/kepatuhan/dashboard' }];

    return (
        <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 bg-white/90 px-4 backdrop-blur-md transition-colors sm:px-6 dark:border-gray-800 dark:bg-gray-900/90">
            <div className="flex items-center gap-3">
                <button
                    type="button"
                    onClick={onToggleSidebar}
                    className="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 shadow-xs transition-colors hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    aria-label="Toggle menu"
                >
                    <Menu className="h-5 w-5" />
                </button>

                <nav className="flex items-center gap-1.5 text-xs font-medium sm:text-sm">
                    {activeBreadcrumbs.map((item, index) => {
                        const isLast = index === activeBreadcrumbs.length - 1;

                        return (
                            <div key={index} className="flex items-center gap-1.5">
                                {index > 0 && <ChevronRight className="h-3.5 w-3.5 shrink-0 text-gray-400 dark:text-gray-500" />}
                                {isLast ? (
                                    <span className="font-semibold text-gray-900 dark:text-white">{item.label}</span>
                                ) : (
                                    <a
                                        href={item.href || '#'}
                                        className="text-gray-500 transition-colors hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                                    >
                                        {item.label}
                                    </a>
                                )}
                            </div>
                        );
                    })}
                </nav>
            </div>

            <div className="flex items-center gap-3">
                <button
                    type="button"
                    className="relative flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 shadow-xs transition-colors hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    aria-label="Notifications"
                >
                    <Bell className="h-4.5 w-4.5" />
                    <span className="absolute -top-1 -right-1 flex h-4.5 w-4.5 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white shadow-xs ring-2 ring-white dark:ring-gray-900">
                        5
                    </span>
                </button>

                <div className="relative">
                    <button
                        type="button"
                        onClick={() => setIsUserMenuOpen(!isUserMenuOpen)}
                        className="flex items-center gap-2.5 rounded-lg p-1.5 text-left transition-colors hover:bg-gray-100 dark:hover:bg-gray-800"
                    >
                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white shadow-xs ring-2 ring-blue-500/20">
                            {initials}
                        </div>
                        <div className="hidden flex-col md:flex">
                            <span className="text-xs leading-tight font-semibold text-gray-900 dark:text-white">{userName}</span>
                            <span className="mt-0.5 text-[11px] leading-none text-gray-500 dark:text-gray-400">{userRole}</span>
                        </div>
                        <ChevronDown className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                    </button>

                    {isUserMenuOpen && (
                        <div className="animate-in fade-in slide-in-from-top-2 absolute right-0 z-50 mt-2 w-48 rounded-xl border border-gray-100 bg-white p-1.5 shadow-xl duration-150 dark:border-gray-700 dark:bg-gray-800">
                            <div className="border-b border-gray-100 px-3 py-2 md:hidden dark:border-gray-700">
                                <p className="text-xs font-semibold text-gray-900 dark:text-white">{userName}</p>
                                <p className="text-[11px] text-gray-500 dark:text-gray-400">{userRole}</p>
                            </div>
                            <button
                                type="button"
                                className="w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60"
                            >
                                Profile Settings
                            </button>
                            <a
                                href="/logout"
                                className="block w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                            >
                                Log out
                            </a>
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
}
