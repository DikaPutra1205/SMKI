import { BreadcrumbItem, Header } from '@/components/admin-kepatuhan/Header';
import { Sidebar } from '@/components/admin-kepatuhan/Sidebar';
import { ReactNode, useState } from 'react';

interface AdminKepatuhanLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    currentPath?: string;
}

export default function AdminKepatuhanLayout({ children, breadcrumbs, currentPath }: AdminKepatuhanLayoutProps) {
    const [isSidebarOpen, setIsSidebarOpen] = useState(true);

    const toggleSidebar = () => {
        setIsSidebarOpen((prev) => !prev);
    };

    return (
        <div className="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
            {/* Sidebar */}
            <Sidebar isOpen={isSidebarOpen} onClose={() => setIsSidebarOpen(false)} currentPath={currentPath} />

            {/* Main content wrapper with margin for fixed sidebar on lg screens */}
            <div className="flex flex-1 flex-col transition-all duration-300 lg:pl-64">
                {/* Header */}
                <Header onToggleSidebar={toggleSidebar} breadcrumbs={breadcrumbs} />

                {/* Page Content */}
                <main className="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">{children}</main>
            </div>
        </div>
    );
}
