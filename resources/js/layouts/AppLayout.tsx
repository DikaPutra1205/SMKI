import { BreadcrumbItem, Header } from '@/components/layout/Header';
import { Sidebar } from '@/components/layout/Sidebar';
import { ReactNode, useState } from 'react';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    currentPath?: string;
}

export default function AppLayout({ children, breadcrumbs, currentPath }: AppLayoutProps) {
    const [isSidebarOpen, setIsSidebarOpen] = useState(true);

    const toggleSidebar = () => {
        setIsSidebarOpen((prev) => !prev);
    };

    return (
        <div className="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
            <Sidebar isOpen={isSidebarOpen} onClose={() => setIsSidebarOpen(false)} currentPath={currentPath} />

            <div className="flex flex-1 flex-col transition-all duration-300 lg:pl-64">
                <Header onToggleSidebar={toggleSidebar} breadcrumbs={breadcrumbs} />

                <main className="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">{children}</main>
            </div>
        </div>
    );
}

export type { BreadcrumbItem } from '@/components/layout/Header';
