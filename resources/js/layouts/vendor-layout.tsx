import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { VendorSidebar } from '@/components/vendor-sidebar';
import type { BreadcrumbItem } from '@/types';

export default function VendorLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <AppShell variant="sidebar">
            <VendorSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="flex flex-1 flex-col gap-4 px-4 py-6 md:px-6 lg:px-8">
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
