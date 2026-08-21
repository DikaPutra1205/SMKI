import { BreadcrumbItem, Header } from '@/components/layout/Header';
import { Sidebar } from '@/components/layout/Sidebar';
import { ReactNode } from 'react';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    currentPath?: string;
}

export default function AppLayout({ children, breadcrumbs, currentPath }: AppLayoutProps) {
    return (
        <div className="bg-surface text-body flex min-h-screen flex-col font-sans">
            <Sidebar isOpen onClose={() => undefined} currentPath={currentPath} />

            <div className="flex flex-1 flex-col transition-all duration-300 lg:pl-64">
                <Header breadcrumbs={breadcrumbs} />

                <main className="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">{children}</main>
            </div>
        </div>
    );
}

export type { BreadcrumbItem } from '@/components/layout/Header';
