import { BreadcrumbItem, Header } from '@/components/layout/Header';
import NotificationProvider from '@/components/layout/NotificationProvider';
import { Sidebar } from '@/components/layout/Sidebar';
import { cn } from '@/lib/utils';
import { ReactNode, useEffect, useState } from 'react';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    currentPath?: string;
}

export default function AppLayout({ children, breadcrumbs, currentPath }: AppLayoutProps) {
    const [isCollapsed, setIsCollapsed] = useState(false);
    const [isMobileOpen, setIsMobileOpen] = useState(false);

    useEffect(() => {
        const stored = localStorage.getItem('smki_sidebar_collapsed');
        if (stored !== null) {
            setIsCollapsed(stored === 'true');
        }
    }, []);

    const toggleSidebar = () => {
        if (window.innerWidth < 1024) {
            setIsMobileOpen((prev) => !prev);
        } else {
            setIsCollapsed((prev) => {
                const next = !prev;
                localStorage.setItem('smki_sidebar_collapsed', String(next));
                return next;
            });
        }
    };

    return (
        <NotificationProvider>
            <div className="bg-surface text-body flex min-h-screen flex-col font-sans dark:bg-[#00101f] dark:text-slate-300">
                <Sidebar
                    isOpen={isMobileOpen}
                    isCollapsed={isCollapsed}
                    onClose={() => setIsMobileOpen(false)}
                    onToggleCollapse={() => {
                        setIsCollapsed((prev) => {
                            const next = !prev;
                            localStorage.setItem('smki_sidebar_collapsed', String(next));
                            return next;
                        });
                    }}
                    currentPath={currentPath}
                />

                <div className={cn('flex flex-1 flex-col transition-all duration-300 ease-in-out', isCollapsed ? 'lg:pl-0' : 'lg:pl-64')}>
                    <Header breadcrumbs={breadcrumbs} onToggleSidebar={toggleSidebar} isSidebarCollapsed={isCollapsed} />

                    <main className="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">{children}</main>
                </div>
            </div>
        </NotificationProvider>
    );
}

export type { BreadcrumbItem } from '@/components/layout/Header';
